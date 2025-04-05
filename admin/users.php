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
$userType = isset($_GET['user_type']) ? sanitize($_GET['user_type']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($userType)) {
    $conditions[] = "u.user_type = ?";
    $params[] = $userType;
    $types .= 's';
}

if ($status === 'active') {
    $conditions[] = "u.is_active = 1";
} elseif ($status === 'inactive') {
    $conditions[] = "u.is_active = 0";
}

if (!empty($search)) {
    $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
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
    FROM users u
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

// Get users
$usersQuery = "
    SELECT u.*,
        (SELECT COUNT(*) FROM donations WHERE user_id = u.user_id) as donation_count,
        (SELECT SUM(amount) FROM donations WHERE user_id = u.user_id) as total_donated
    FROM users u
    $whereClause
    ORDER BY u.registration_date DESC
    LIMIT $offset, $perPage
";

if (!empty($params)) {
    $usersStmt = $conn->prepare($usersQuery);
    $usersStmt->bind_param($types, ...$params);
    $usersStmt->execute();
    $usersResult = $usersStmt->get_result();
} else {
    $usersResult = $conn->query($usersQuery);
}

$users = [];
while ($row = $usersResult->fetch_assoc()) {
    $users[] = $row;
}

// Process user actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        $newStatus = $_POST['is_active'] === '1' ? 0 : 1;
        
        $updateStmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $updateStmt->bind_param("ii", $newStatus, $userId);
        
        if ($updateStmt->execute()) {
            $message = "User status updated successfully";
            $messageType = "success";
            
            // Update the user in the array
            foreach ($users as &$user) {
                if ($user['user_id'] === $userId) {
                    $user['is_active'] = $newStatus;
                    break;
                }
            }
        } else {
            $message = "Error updating user status";
            $messageType = "error";
        }
    }
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Manage Users</h1>
        
        <a href="add-user.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add User
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
                <label for="user_type" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">User Type</label>
                <select id="user_type" name="user_type" class="w-full sm:w-40 px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    <option value="">All Types</option>
                    <option value="donor" <?php echo $userType === 'donor' ? 'selected' : ''; ?>>Donor</option>
                    <option value="charity_admin" <?php echo $userType === 'charity_admin' ? 'selected' : ''; ?>>Charity Admin</option>
                    <option value="admin" <?php echo $userType === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            
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
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Search by username, email, name...">
            </div>
            
            <div class="w-full sm:w-auto flex items-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <?php if (empty($users)): ?>
            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                <p>No users found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-<?php echo getProfileColor($user['user_type']); ?>-100 dark:bg-<?php echo getProfileColor($user['user_type']); ?>-900 rounded-full flex items-center justify-center text-<?php echo getProfileColor($user['user_type']); ?>-600 dark:text-<?php echo getProfileColor($user['user_type']); ?>-400 mr-3">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800 dark:text-gray-200"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">@<?php echo $user['username']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo $user['email']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo getUserTypeClass($user['user_type']); ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php if ($user['donation_count'] > 0): ?>
                                        <div><?php echo $user['donation_count']; ?> donations</div>
                                        <div class="text-xs"><?php echo formatNepaliCurrency($user['total_donated'] ?: 0); ?> total</div>
                                    <?php else: ?>
                                        <span class="text-gray-500 dark:text-gray-500">No donations</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo date('M d, Y', strtotime($user['registration_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $user['is_active'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="edit-user.php?id=<?php echo $user['user_id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $user['is_active']; ?>">
                                            <button type="submit" name="toggle_status" class="text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-600 dark:text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-400 hover:text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-900 dark:hover:text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-300" title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-user-times' : 'fa-user-check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <a href="view-user.php?id=<?php echo $user['user_id']; ?>" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300" title="View Details">
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
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalItems); ?> of <?php echo $totalItems; ?> users
                        </div>
                        
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $userType ? '&user_type=' . $userType : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo $userType ? '&user_type=' . $userType : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $userType ? '&user_type=' . $userType : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
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

// Helper functions for user display
function getProfileColor($userType) {
    switch ($userType) {
        case 'admin':
            return 'red';
        case 'charity_admin':
            return 'green';
        default:
            return 'indigo';
    }
}

function getUserTypeClass($userType) {
    switch ($userType) {
        case 'admin':
            return 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200';
        case 'charity_admin':
            return 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200';
        default:
            return 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200';
    }
}
?>