<?php
require_once 'includes/auth.php';

$registerError = '';
$registerSuccess = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Check if already logged in
if (isLoggedIn()) {
    header("Location: " . SITE_URL . ($redirect ? "/index.php?page=$redirect" : ""));
    exit;
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $password = $_POST['password']; // Don't sanitize passwords
    $confirmPassword = $_POST['confirm_password']; // Don't sanitize passwords
    
    // Validate input
    if (empty($username) || empty($email) || empty($firstName) || empty($lastName) || empty($password) || empty($confirmPassword)) {
        $registerError = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Please enter a valid email address';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $registerError = 'Password must be at least 8 characters long';
    } else {
        // Attempt registration
        $result = registerUser($username, $email, $password, $firstName, $lastName);
        
        if ($result['success']) {
            // Auto-login after registration
            $loginResult = loginUser($username, $password);
            
            if ($loginResult['success']) {
                // Redirect to intended page or homepage
                header("Location: " . SITE_URL . ($redirect ? "/index.php?page=$redirect" : ""));
                exit;
            } else {
                $registerSuccess = 'Registration successful! Please login.';
            }
        } else {
            $registerError = $result['message'];
        }
    }
}
?>

<div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-center text-gray-800 dark:text-gray-200 mb-6">Create an Account</h1>
        
        <?php if ($registerError): ?>
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                <p><?php echo $registerError; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($registerSuccess): ?>
            <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                <p><?php echo $registerSuccess; ?></p>
                <p class="mt-2">
                    <a href="<?php echo SITE_URL; ?>/index.php?page=login<?php echo $redirect ? '&redirect=' . urlencode($redirect) : ''; ?>" class="text-green-700 dark:text-green-300 font-bold underline">
                        Click here to login
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true"> 
            <input type="hidden" name="user_type" value="donor">


            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                </div>
                
                <div>
                    <label for="last_name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="username" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Username</label>
                <input type="text" id="username" name="username" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
            </div>
            
            <div class="mb-4">
                <label for="password" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">Must be at least 8 characters long</p>
            </div>
            
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" id="terms" name="terms" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" required>
                    <label for="terms" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        I agree to the <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">Terms of Service</a> and <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">Privacy Policy</a>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Register
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600 dark:text-gray-400">
                Already have an account? 
                <a href="<?php echo SITE_URL; ?>/index.php?page=login<?php echo $redirect ? '&redirect=' . urlencode($redirect) : ''; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    Login
                </a>
            </p>
        </div>
    </div>
</div>
