<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($status === 'active') {
    $conditions[] = "c.is_active = 1";
} elseif ($status === 'inactive') {
    $conditions[] = "c.is_active = 0";
}

if (!empty($search)) {
    $conditions[] = "(c.title LIKE ? OR ch.charity_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

// Build the WHERE clause
$whereClause = '';
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(' AND ', $conditions);
}

// Get total count for pagination
$conn = getDBConnection();
$countQuery = "
    SELECT COUNT(*) as total
    FROM campaigns c
    JOIN charities ch ON c.charity_id = ch.charity_id
    $whereClause
";

if (!empty($params)) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
} else {
    $countResult = $conn->query($countQuery);
}

$totalItems = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalItems / $perPage);

// Get campaigns
$campaignsQuery = "
    SELECT c.*, ch.charity_name,
           (SELECT SUM(amount) FROM donations WHERE campaign_id = c.campaign_id) as total_donations,
           (SELECT COUNT(*) FROM donations WHERE campaign_id = c.campaign_id) as donation_count
    FROM campaigns c
    JOIN charities ch ON c.charity_id = ch.charity_id
    $whereClause
    ORDER BY c.creation_date DESC
    LIMIT $offset, $perPage
";

if (!empty($params)) {
    $campaignsStmt = $conn->prepare($campaignsQuery);
    $campaignsStmt->bind_param($types, ...$params);
    $campaignsStmt->execute();
    $campaignsResult = $campaignsStmt->get_result();
} else {
    $campaignsResult = $conn->query($campaignsQuery);
}

$campaigns = [];
while ($row = $campaignsResult->fetch_assoc()) {
    $campaigns[] = $row;
}

// Process campaign action if provided
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status']) && isset($_POST['campaign_id'])) {
        $campaignId = intval($_POST['campaign_id']);
        $newStatus = $_POST['status'] === '1' ? 0 : 1;
        
        $updateStmt = $conn->prepare("UPDATE campaigns SET is_active = ? WHERE campaign_id = ?");
        $updateStmt->bind_param("ii", $newStatus, $campaignId);
        
        if ($updateStmt->execute()) {
            $message = "Campaign status updated successfully";
            $messageType = "success";
            
            // Update the campaign in the array
            foreach ($campaigns as &$campaign) {
                if ($campaign['campaign_id'] === $campaignId) {
                    $campaign['is_active'] = $newStatus;
                    break;
                }
            }
        } else {
            $message = "Error updating campaign status";
            $messageType = "error";
        }
    } elseif (isset($_POST['delete_campaign']) && isset($_POST['campaign_id'])) {
        $campaignId = intval($_POST['campaign_id']);
        
        // First delete related records (donations)
        $deletedonationsStmt = $conn->prepare("DELETE FROM donations WHERE campaign_id = ?");
        $deletedonationsStmt->bind_param("i", $campaignId);
        $deletedonationsStmt->execute();
        
        // Then delete the campaign
        $deleteStmt = $conn->prepare("DELETE FROM campaigns WHERE campaign_id = ?");
        $deleteStmt->bind_param("i", $campaignId);
        
        if ($deleteStmt->execute()) {
            $message = "Campaign deleted successfully";
            $messageType = "success";
            
            // Remove the campaign from the array
            foreach ($campaigns as $key => $campaign) {
                if ($campaign['campaign_id'] === $campaignId) {
                    unset($campaigns[$key]);
                    break;
                }
            }
        } else {
            $message = "Error deleting campaign";
            $messageType = "error";
        }
    }
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Manage Campaigns</h1>
        
        <a href="add-campaign.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Campaign
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
        <form action="" method="GET" class="flex flex-wrap gap-4">
            <div class="w-full sm:w-auto">
                <label for="status" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Status</label>
                <select id="status" name="status" class="w-full sm:w-40 px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="w-full sm:w-auto flex-1">
                <label for="search" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Search by campaign or charity name...">
            </div>
            
            <div class="w-full sm:w-auto flex items-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Campaigns Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <?php if (empty($campaigns)): ?>
            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                <p>No campaigns found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Campaign</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Charity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Goal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">End Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                                            <i class="fas fa-bullhorn"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800 dark:text-gray-200"><?php echo $campaign['title']; ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">ID: <?php echo $campaign['campaign_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo $campaign['charity_name']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo formatNepaliCurrency($campaign['goal_amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php $progress = calculateProgress($campaign['current_amount'], $campaign['goal_amount']); ?>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mr-2 max-w-[100px]">
                                            <div class="bg-indigo-600 dark:bg-indigo-500 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            <?php echo round($progress); ?>%
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <?php echo formatNepaliCurrency($campaign['current_amount']); ?> raised
                                        <?php if ($campaign['donation_count'] > 0): ?>
                                            (<?php echo $campaign['donation_count']; ?> donations)
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php 
                                        echo date('M d, Y', strtotime($campaign['end_date']));
                                        $daysLeft = floor((strtotime($campaign['end_date']) - time()) / (60 * 60 * 24));
                                        if ($daysLeft > 0) {
                                            echo "<div class='text-xs text-gray-500 dark:text-gray-400 mt-1'>$daysLeft days left</div>";
                                        } elseif ($daysLeft === 0) {
                                            echo "<div class='text-xs text-yellow-500 dark:text-yellow-400 mt-1'>Ends today</div>";
                                        } else {
                                            echo "<div class='text-xs text-red-500 dark:text-red-400 mt-1'>Ended</div>";
                                        }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $campaign['is_active'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'; ?>">
                                        <?php echo $campaign['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="edit-campaign.php?id=<?php echo $campaign['campaign_id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to <?php echo $campaign['is_active'] ? 'deactivate' : 'activate'; ?> this campaign?');">
                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $campaign['is_active']; ?>">
                                            <button type="submit" name="toggle_status" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300" title="<?php echo $campaign['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $campaign['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this campaign? This action cannot be undone.');">
                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                            <button type="submit" name="delete_campaign" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        
                                        <a href="view-campaign.php?id=<?php echo $campaign['campaign_id']; ?>" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalItems); ?> of <?php echo $totalItems; ?> campaigns
                        </div>
                        
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>