<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if user ID is valid
if ($userId <= 0) {
    header("Location: " . SITE_URL . "/admin/users.php");
    exit;
}

// Initialize variables
$message = '';
$messageType = '';

// Get user details
$conn = getDBConnection();
$userSql = "SELECT * FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();

// Check if user exists
if ($userResult->num_rows !== 1) {
    header("Location: " . SITE_URL . "/admin/users.php");
    exit;
}

$user = $userResult->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Get form data
        $formData = [
            'username' => sanitize($_POST['username']),
            'email' => sanitize($_POST['email']),
            'first_name' => sanitize($_POST['first_name']),
            'last_name' => sanitize($_POST['last_name']),
            'user_type' => sanitize($_POST['user_type']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate form data
        $errors = [];
        
        if (empty($formData['username'])) {
            $errors[] = 'Username is required';
        }
        
        if (empty($formData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($formData['first_name'])) {
            $errors[] = 'First name is required';
        }
        
        if (empty($formData['last_name'])) {
            $errors[] = 'Last name is required';
        }
        
        // Check if username or email already exists (excluding current user)
        $checkSql = "SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ssi", $formData['username'], $formData['email'], $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $errors[] = 'Username or email already exists';
        }
        
        // If no errors, update the user
        if (empty($errors)) {
            $updateSql = "UPDATE users SET 
                username = ?, 
                email = ?, 
                first_name = ?, 
                last_name = ?, 
                user_type = ?, 
                is_active = ?
                WHERE user_id = ?";
            
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param(
                "sssssii", 
                $formData['username'],
                $formData['email'],
                $formData['first_name'],
                $formData['last_name'],
                $formData['user_type'],
                $formData['is_active'],
                $userId
            );
            
            if ($updateStmt->execute()) {
                $message = "User profile updated successfully!";
                $messageType = "success";
                
                // Refresh user data
                $userStmt->execute();
                $user = $userStmt->get_result()->fetch_assoc();
            } else {
                $message = "Error updating user profile: " . $updateStmt->error;
                $messageType = "error";
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = "error";
        }
    } elseif (isset($_POST['reset_password'])) {
        // Handle password reset
        $newPassword = generateTemporaryPassword();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $resetSql = "UPDATE users SET password = ? WHERE user_id = ?";
        $resetStmt = $conn->prepare($resetSql);
        $resetStmt->bind_param("si", $hashedPassword, $userId);
        
        if ($resetStmt->execute()) {
            $message = "Password has been reset to: <strong>" . $newPassword . "</strong><br>Please save this and share it with the user securely.";
            $messageType = "success";
        } else {
            $message = "Error resetting password: " . $resetStmt->error;
            $messageType = "error";
        }
    }
}

// Include the admin header
include 'includes/admin-header.php';

// Function to generate a temporary password
function generateTemporaryPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_=+';
    $password = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $max)];
    }
    
    return $password;
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Edit User: <?php echo $user['username']; ?></h1>
        
        <a href="users.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Users
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- User Profile Form (2/3 width on large screens) -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">User Information</h2>
                    
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="username" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Username <span class="text-red-500">*</span></label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            
                            <div>
                                <label for="first_name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">First Name <span class="text-red-500">*</span></label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            
                            <div>
                                <label for="user_type" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">User Type <span class="text-red-500">*</span></label>
                                <select id="user_type" name="user_type" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                                    <option value="donor" <?php echo $user['user_type'] === 'donor' ? 'selected' : ''; ?>>Donor</option>
                                    <option value="charity_admin" <?php echo $user['user_type'] === 'charity_admin' ? 'selected' : ''; ?>>Charity Admin</option>
                                    <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" id="is_active" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Active Account
                                </label>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="update_profile" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar (1/3 width on large screens) -->
        <div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-4">User Details</h3>
                    
                    <ul class="space-y-3 text-gray-600 dark:text-gray-400">
                        <li class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-500">User ID:</span>
                            <span><?php echo $user['user_id']; ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-500">Registered:</span>
                            <span><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-500">Status:</span>
                            <span class="<?php echo $user['is_active'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-500">User Type:</span>
                            <span class="<?php 
                                switch($user['user_type']) {
                                    case 'admin':
                                        echo 'text-red-600 dark:text-red-400';
                                        break;
                                    case 'charity_admin':
                                        echo 'text-green-600 dark:text-green-400';
                                        break;
                                    default:
                                        echo 'text-blue-600 dark:text-blue-400';
                                }
                            ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            
            
        </div>
    </div>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>