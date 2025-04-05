<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';



$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Prevent admins from donating
if (isAdmin($currentUser)) {
    header('Location: index.php');
    exit;
}

$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get filter parameters
$status = isset($_GET['payment_status']) ? sanitize($_GET['payment_status']) : '';
$dateRange = isset($_GET['date_range']) ? sanitize($_GET['date_range']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($status)) {
    $conditions[] = "d.payment_method = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($dateRange)) {
    switch ($dateRange) {
        case 'today':
            $conditions[] = "DATE(d.donation_date) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(d.donation_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'last7days':
            $conditions[] = "d.donation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'thismonth':
            $conditions[] = "MONTH(d.donation_date) = MONTH(CURDATE()) AND YEAR(d.donation_date) = YEAR(CURDATE())";
            break;
        case 'lastmonth':
            $conditions[] = "MONTH(d.donation_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(d.donation_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
    }
}

if (!empty($search)) {
    $conditions[] = "(c.title LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR d.transaction_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ssss';
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
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
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

// Get donations
$donationsQuery = "
    SELECT d.*, 
           c.title as campaign_title, c.campaign_id, 
           ch.charity_name, 
           u.username, u.first_name, u.last_name, u.email
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    JOIN charities ch ON c.charity_id = ch.charity_id
    $whereClause
    ORDER BY d.donation_date DESC
    LIMIT $offset, $perPage
";

if (!empty($params)) {
    $donationsStmt = $conn->prepare($donationsQuery);
    $donationsStmt->bind_param($types, ...$params);
    $donationsStmt->execute();
    $donationsResult = $donationsStmt->get_result();
} else {
    $donationsResult = $conn->query($donationsQuery);
}

$donations = [];
while ($row = $donationsResult->fetch_assoc()) {
    $donations[] = $row;
}

// Get payment statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        COUNT(DISTINCT user_id) as unique_donors,
        COUNT(DISTINCT campaign_id) as campaigns_supported,
        AVG(amount) as average_donation
    FROM donations
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Donation Transactions</h1>
        
        <a href="#" onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-file-export mr-2"></i> Export Report
        </a>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 mr-4">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Total Donations</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-200"><?php echo $stats['total_count']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400 mr-4">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Total Amount</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-200"><?php echo formatNepaliCurrency($stats['total_amount']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 mr-4">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Unique Donors</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-200"><?php echo $stats['unique_donors']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400 mr-4">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Campaigns</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-200"><?php echo $stats['campaigns_supported']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-600 dark:text-yellow-400 mr-4">
                    <i class="fas fa-calculator"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Average</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-gray-200"><?php echo formatNepaliCurrency($stats['average_donation']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
        <form action="" method="GET" class="flex flex-wrap gap-4">
            <div class="w-full sm:w-auto">
                <label for="payment_status" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Payment Method</label>
                <select id="payment_status" name="payment_status" class="w-full sm:w-40 px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    <option value="">All Methods</option>
                    <option value="khalti" <?php echo $status === 'khalti' ? 'selected' : ''; ?>>Khalti</option>
                    <option value="credit_card" <?php echo $status === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="bank_transfer" <?php echo $status === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                </select>
            </div>
            
            <div class="w-full sm:w-auto">
                <label for="date_range" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Date Range</label>
                <select id="date_range" name="date_range" class="w-full sm:w-40 px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    <option value="">All Time</option>
                    <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="last7days" <?php echo $dateRange === 'last7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="thismonth" <?php echo $dateRange === 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                    <option value="lastmonth" <?php echo $dateRange === 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                </select>
            </div>
            
            <div class="w-full sm:w-auto flex-1">
                <label for="search" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Search by campaign, donor, or transaction ID...">
            </div>
            
            <div class="w-full sm:w-auto flex items-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Donations Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <?php if (empty($donations)): ?>
            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                <p>No donations found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Donor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Campaign</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($donations as $donation): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo $donation['donation_id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php if (empty($donation['username'])): ?>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Anonymous</span>
                                    <?php else: ?>
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                                                <?php echo $donation['first_name'] ? strtoupper(substr($donation['first_name'], 0, 1)) : 'U'; ?>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                                    <?php echo $donation['first_name'] ? $donation['first_name'] . ' ' . $donation['last_name'] : $donation['username']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo $donation['email']; ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo $donation['campaign_title']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo formatNepaliCurrency($donation['amount']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo date('Y-m-d', strtotime($donation['donation_date'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo ucfirst($donation['payment_method']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo $donation['transaction_id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?php echo SITE_URL; ?>/index.php?page=donation-receipt&id=<?php echo $donation['donation_id']; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">View Receipt</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <div class="flex justify-center mt-6">
        <?php if ($totalPages > 1): ?>
            <nav class="inline-flex">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="px-4 py-2 mx-1 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo $page === $i ? 'bg-indigo-600 text-white' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
