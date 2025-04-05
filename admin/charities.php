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

if ($status === 'verified') {
    $conditions[] = "ch.is_verified = 1";
} elseif ($status === 'unverified') {
    $conditions[] = "ch.is_verified = 0";
}

if (!empty($search)) {
    $conditions[] = "(ch.charity_name LIKE ? OR ch.email LIKE ? OR ch.registration_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
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
    FROM charities ch
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

// Get charities
$charitiesQuery = "
    SELECT ch.*,
           u.username as admin_username,
           (SELECT COUNT(*) FROM campaigns WHERE charity_id = ch.charity_id) as campaign_count,
           (SELECT SUM(amount) FROM donations d JOIN campaigns c ON d.campaign_id = c.campaign_id WHERE c.charity_id = ch.charity_id) as total_donations
    FROM charities ch
    LEFT JOIN users u ON ch.admin_id = u.user_id
    $whereClause
    ORDER BY ch.creation_date DESC
    LIMIT $offset, $perPage
";

if (!empty($params)) {
    $charitiesStmt = $conn->prepare($charitiesQuery);
    $charitiesStmt->bind_param($types, ...$params);
    $charitiesStmt->execute();
    $charitiesResult = $charitiesStmt->get_result();
} else {
    $charitiesResult = $conn->query($charitiesQuery);
}

$charities = [];
while ($row = $charitiesResult->fetch_assoc()) {
    $charities[] = $row;
}

// Process charity action if provided
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_verification']) && isset($_POST['charity_id'])) {
        $charityId = intval($_POST['charity_id']);
        $newStatus = $_POST['is_verified'] === '1' ? 0 : 1;
        
        $updateStmt = $conn->prepare("UPDATE charities SET is_verified = ? WHERE charity_id = ?");
        $updateStmt->bind_param("ii", $newStatus, $charityId);
        
        if ($updateStmt->execute()) {
            $message = "Charity verification status updated successfully";
            $messageType = "success";
            
            // Update the charity in the array
            foreach ($charities as &$charity) {
                if ($charity['charity_id'] === $charityId) {
                    $charity['is_verified'] = $newStatus;
                    break;
                }
            }
        } else {
            $message = "Error updating charity verification status";
            $messageType = "error";
        }
    } elseif (isset($_POST['delete_charity']) && isset($_POST['charity_id'])) {
        $charityId = intval($_POST['charity_id']);
        
        // First check if charity has campaigns
        $checkCampaignsStmt = $conn->prepare("SELECT COUNT(*) as count FROM campaigns WHERE charity_id = ?");
        $checkCampaignsStmt->bind_param("i", $charityId);
        $checkCampaignsStmt->execute();
        $campaignCount = $checkCampaignsStmt->get_result()->fetch_assoc()['count'];
        
        if ($campaignCount > 0) {
            $message = "Cannot delete charity with active campaigns. Please delete the campaigns first.";
            $messageType = "error";
        } else {
            // Delete the charity
            $deleteStmt = $conn->prepare("DELETE FROM charities WHERE charity_id = ?");
            $deleteStmt->bind_param("i", $charityId);
            
            if ($deleteStmt->execute()) {
                $message = "Charity deleted successfully";
                $messageType = "success";
                
                // Remove the charity from the array
                foreach ($charities as $key => $charity) {
                    if ($charity['charity_id'] === $charityId) {
                        unset($charities[$key]);
                        break;
                    }
                }
            } else {
                $message = "Error deleting charity";
                $messageType = "error";
            }
        }
    }
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Manage Charities</h1>
        
        <a href="add-charity.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Charity
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
                    <option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="unverified" <?php echo $status === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                </select>
            </div>
            
            <div class="w-full sm:w-auto flex-1">
                <label for="search" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Search by name, email, or registration number...">
            </div>
            
            <div class="w-full sm:w-auto flex items-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Charities Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <?php if (empty($charities)): ?>
            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                <p>No charities found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Charity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Campaigns</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Donations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($charities as $charity): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800 dark:text-gray-200"><?php echo $charity['charity_name']; ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">ID: <?php echo $charity['charity_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600 dark:text-gray-400"><?php echo $charity['email']; ?></div>
                                    <?php if (!empty($charity['phone'])): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo $charity['phone']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo $charity['campaign_count']; ?> campaigns
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo formatNepaliCurrency($charity['total_donations'] ?: 0); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $charity['is_verified'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200'; ?>">
                                        <?php echo $charity['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="edit-charity.php?id=<?php echo $charity['charity_id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" title="Edit">
                                            <i class="fas fa-pen-to-square"></i>
                                        </a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to <?php echo $charity['is_verified'] ? 'unverify' : 'verify'; ?> this charity?');">
                                            <input type="hidden" name="charity_id" value="<?php echo $charity['charity_id']; ?>">
                                            <input type="hidden" name="is_verified" value="<?php echo $charity['is_verified']; ?>">
                                            <button type="submit" name="toggle_verification" class="text-<?php echo $charity['is_verified'] ? 'yellow' : 'green'; ?>-600 dark:text-<?php echo $charity['is_verified'] ? 'yellow' : 'green'; ?>-400 hover:text-<?php echo $charity['is_verified'] ? 'yellow' : 'green'; ?>-900 dark:hover:text-<?php echo $charity['is_verified'] ? 'yellow' : 'green'; ?>-300" title="<?php echo $charity['is_verified'] ? 'Unverify' : 'Verify'; ?>">
                                                <i class="fas <?php echo $charity['is_verified'] ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if ($charity['campaign_count'] == 0): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this charity? This action cannot be undone.');">
                                                <input type="hidden" name="charity_id" value="<?php echo $charity['charity_id']; ?>">
                                                <button type="submit" name="delete_charity" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="view-charity.php?id=<?php echo $charity['charity_id']; ?>" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300" title="View Details">
                                            <i class="fas fa-file-lines"></i>
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
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalItems); ?> of <?php echo $totalItems; ?> charities
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