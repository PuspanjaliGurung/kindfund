<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/index.php?page=login");
    exit;
}

$user = getCurrentUser();
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($firstName)) {
        $errors[] = "First name is required";
    }
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }

    // Password change validation
    $passwordChanged = false;
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $errors[] = "Current password is required to change password";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } else {
            $passwordChanged = true;
        }
    }

    // Update profile if no errors
    if (empty($errors)) {
        $updateData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email
        ];

        if ($passwordChanged) {
            $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (updateUserProfile($user['user_id'], $updateData)) {
            $success = true;
            // Refresh user data
            $user = getCurrentUser();
        } else {
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
}
?>

<div class="bg-gray-50 dark:bg-gray-900 min-h-screen py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Edit Profile</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Update your personal information and account settings</p>
        </div>
        
        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 dark:bg-green-900 border-l-4 border-green-500 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Profile updated successfully!</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900 border-l-4 border-red-500 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Please correct the following errors:</h3>
                        <ul class="mt-1 list-disc pl-5 space-y-1 text-sm text-red-700 dark:text-red-300">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Main Form Container -->
        <form method="POST" action="" class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <!-- Profile Information -->
            <div class="px-4 py-5 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Profile Information</h2>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                    <div class="col-span-6 sm:col-span-3">
                        <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                        <input type="text" name="first_name" id="first_name" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white text-base"
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    
                    <div class="col-span-6 sm:col-span-3">
                        <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                        <input type="text" name="last_name" id="last_name" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white text-base"
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                    
                    <div class="col-span-6">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                        <input type="email" name="email" id="email" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white text-base"
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="col-span-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-medium">Member since:</span> <?php echo date('F j, Y', strtotime($user['registration_date'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Section -->
            <div class="px-4 py-5 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Password</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Leave these fields empty if you don't want to change your password</p>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                    <div class="col-span-6">
                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                        <input type="password" name="current_password" id="current_password" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white text-base">
                    </div>
                    
                    <div class="col-span-6 sm:col-span-3">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                        <input type="password" name="new_password" id="new_password" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white text-base">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters</p>
                    </div>
                    
                    <div class="col-span-6 sm:col-span-3">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white text-base">
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="px-4 py-5 sm:p-6 flex flex-col sm:flex-row-reverse sm:justify-between gap-3">
                <div>
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Changes
                    </button>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>/index.php?page=dashboard" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-base font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </form>
        
        <!-- Additional Actions -->
        <div class="mt-8 text-center">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Need help? <a href="#" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Contact support</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>