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

<!-- Donation Form (1/3 width on large screens) -->
<div class="lg:col-span-1">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden sticky top-4">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Make a Donation</h3>
            
            <?php if ($donationSuccess): ?>
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <p class="font-bold">Thank you for your donation!</p>
                    <p>Your contribution will help make a difference.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($donationError): ?>
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <p><?php echo $donationError; ?></p>
                </div>
            <?php endif; ?>
            
            <form id="donation-form" action="javascript:void(0);" data-validate="true">
                <input type="hidden" name="campaign_id" id="campaign_id" value="<?php echo $campaignId; ?>">
                <input type="hidden" name="amount" id="amount_value" value="500">
                
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Select Amount (NPR)</label>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div class="amount-option active bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-lg py-2 text-center cursor-pointer hover:bg-indigo-200 dark:hover:bg-indigo-800 transition duration-200" data-amount="500">
                            Rs. 500
                        </div>
                        <div class="amount-option bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg py-2 text-center cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900 transition duration-200" data-amount="1000">
                            Rs. 1,000
                        </div>
                        <div class="amount-option bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg py-2 text-center cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900 transition duration-200" data-amount="2000">
                            Rs. 2,000
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        <label for="custom-amount" class="block text-gray-700 dark:text-gray-300 text-sm mb-1">Or enter custom amount:</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-600 dark:text-gray-400">Rs.</span>
                            <input type="number" id="custom-amount" class="w-full pl-10 pr-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" min="10" placeholder="Enter amount">
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="message" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Message (Optional)</label>
                    <textarea id="message" name="message" rows="3" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Add a message of support..."></textarea>
                </div>
                
                <div class="mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="anonymous" name="anonymous" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="anonymous" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Make my donation anonymous
                        </label>
                    </div>
                </div>
                
                <?php if (isLoggedIn()): ?>
                    <button type="button" id="khalti-payment-button" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512" class="mr-2"><path fill="#ffffff" d="M57.7 193l9.4 16.4c8.3 14.5 21.9 25.2 38 29.8L163 255.7c17.2 4.9 29 20.6 29 38.5v39.9c0 11 6.2 21 16 25.9s16 14.9 16 25.9v39c0 15.6 14.9 26.9 29.9 22.6c16.1-4.6 28.6-17.5 32.7-33.8l2.8-11.2c4.2-16.9 15.2-31.4 30.3-40l8.1-4.6c15-8.5 24.2-24.5 24.2-41.7v-8.3c0-12.7-5.1-24.9-14.1-33.9l-3.9-3.9c-9-9-21.2-14.1-33.9-14.1H257c-11.1 0-22.1-2.9-31.8-8.4l-34.5-19.7c-4.3-2.5-7.6-6.5-9.2-11.2c-3.2-9.6 1.1-20 10.2-24.5l5.9-3c6.6-3.3 14.3-3.9 21.3-1.5l23.2 7.7c8.2 2.7 17.2-.4 21.9-7.5c4.7-7 4.2-16.3-1.2-22.8l-13.6-16.3c-10-12-9.9-29.5.3-41.3l15.7-18.3c8.8-10.3 10.2-25 3.5-36.7l-2.4-4.2c-3.5-.2-6.9-.3-10.4-.3C163.1 48 84.4 108.9 57.7 193zM464 256c0-36.8-9.6-71.4-26.4-101.5L412 164.8c-15.7 6.3-23.8 23.8-18.5 39.8l16.9 50.7c3.5 10.4 12 18.3 22.6 20.9l29.1 7.3c1.2-9 1.8-18.2 1.8-27.5zM0 256C0 114.6 114.6 0 256 0S512 114.6 512 256s-114.6 256-256 256S0 397.4 0 256z"/></svg>
                        Pay with Khalti
                    </button>
                <?php else: ?>
                    <div class="space-y-3">
                        <a href="<?php echo SITE_URL; ?>/index.php?page=login&amp;redirect=campaign&amp;id=<?php echo $campaignId; ?>" class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                            Login to Donate
                        </a>
                        <p class="text-gray-600 dark:text-gray-400 text-sm text-center">
                            Don't have an account? 
                            <a href="<?php echo SITE_URL; ?>/index.php?page=register&amp;redirect=campaign&amp;id=<?php echo $campaignId; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                Register
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="mt-6 text-sm text-center text-gray-600 dark:text-gray-400">
                <p>
                    <i class="fas fa-lock mr-1"></i> Secure payment powered by Khalti
                </p>
            </div>
        </div>
    </div>
</div>
<!-- Khalti Integration Script -->
<script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.22.0.0.0/khalti-checkout.iffe.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle amount selection
        const amountOptions = document.querySelectorAll('.amount-option');
        const customAmountInput = document.getElementById('custom-amount');
        const amountValue = document.getElementById('amount_value');
        
        amountOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                amountOptions.forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');
                
                // Update hidden input with selected amount
                const selectedAmount = option.getAttribute('data-amount');
                amountValue.value = selectedAmount;
                
                // Clear custom amount
                if (customAmountInput) {
                    customAmountInput.value = '';
                }
            });
        });
        
        // Handle custom amount input
        if (customAmountInput) {
            customAmountInput.addEventListener('input', function() {
                // Remove active class from predefined options
                amountOptions.forEach(opt => opt.classList.remove('active'));
                
                // Update hidden input with custom amount
                amountValue.value = customAmountInput.value;
            });
        }
        
        // Handle Khalti payment button
        const khaltiBtn = document.getElementById('khalti-payment-button');
        const donationForm = document.getElementById('donation-form');
        const messageInput = document.getElementById('message');
        const anonymousCheckbox = document.getElementById('anonymous');
        const campaignId = document.getElementById('campaign_id').value;
        
        if (khaltiBtn) {
            khaltiBtn.addEventListener('click', function() {
                // Get current amount value
                const amount = parseFloat(amountValue.value);
                
                if (isNaN(amount) || amount < 10) {
                    alert('Please enter a valid amount (minimum Rs. 10)');
                    return;
                }
                
                // Create form data
                const formData = new FormData();
                formData.append('campaign_id', campaignId);
                formData.append('amount', amount);
                formData.append('message', messageInput.value);
                
                if (anonymousCheckbox.checked) {
                    formData.append('anonymous', '1');
                }
                
                // Send to server to initialize payment
                fetch('<?php echo SITE_URL; ?>/api/process-donation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        initializeKhaltiPayment(data.payment_data);
                    } else {
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request. Please try again.');
                });
            });
        }
        
        function initializeKhaltiPayment(paymentData) {
            // For test mode, show test credentials message
            if (!khaltiBtn.classList.contains('test-info-shown')) {
                const testInfoDiv = document.createElement('div');
                testInfoDiv.className = 'mt-4 p-3 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-sm rounded-lg';
                testInfoDiv.innerHTML = `
                    <p class="font-bold mb-1">🔔 Test Mode Enabled</p>
                    <p>Use these test credentials:</p>
                    <ul class="list-disc pl-4 mt-1">
                        <li>Mobile: 9800000000</li>
                        <li>MPIN: 1111</li>
                        <li>OTP: 987654</li>
                    </ul>
                `;
                khaltiBtn.parentNode.insertBefore(testInfoDiv, khaltiBtn.nextSibling);
                khaltiBtn.classList.add('test-info-shown');
            }
            
            // Initialize Khalti widget
            const config = {
                "publicKey": '36385bdf45b543cd9016ee07393ae895',
                "productIdentity": paymentData.productIdentity,
                "productName": paymentData.productName,
                "productUrl": paymentData.productUrl,
                "amount": paymentData.amount,
                "paymentPreference": [
                    "KHALTI",
                    "EBANKING",
                    "MOBILE_BANKING",
                    "CONNECT_IPS",
                    "SCT"
                ],
                "eventHandler": {
                    onSuccess (payload) {
                        // Redirect to callback URL with token
                        window.location.href = "<?php echo SITE_URL; ?>/api/khalti-callback.php?token=" + payload.token + "&amount=" + paymentData.amount;
                    },
                    onError (error) {
                        console.log(error);
                        alert("Payment Error: " + error.message);
                    },
                    onClose () {
                        console.log('Widget closed without payment');
                    }
                }
            };
            
            const checkout = new KhaltiCheckout(config);
            checkout.show({amount: paymentData.amount});
        }
    });
</script>
<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>