<?php
require_once 'includes/auth.php'; // Include authentication logic
error_log("Current Working Directory: " . getcwd());
error_log("Expected Path for auth.php: " . realpath('../includes/auth.php'));

error_log("Current Working Directory: " . getcwd());
error_log("Expected Path for auth.php: " . realpath('../includes/auth.php'));

require_once 'includes/admin-auth.php'; // Include admin authentication logic

require_once 'includes/admin-auth.php'; // Include admin authentication logic

// Debugging: Check if auth.php is included successfully
if (!file_exists('includes/auth.php')) {
    error_log("auth.php file not found.");
    die("Authentication file not found.");
}


$loginError = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

if ($error === 'access_denied') {
    $loginError = 'You need administrator privileges to access that area.';
}

// Check if already logged in
if (isLoggedIn()) { 
    // Redirect admins to admin dashboard if they're trying to access it 
    if ($redirect === 'admin' && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header("Location: " . SITE_URL . "admin");
        exit;
    }
    // Otherwise redirect to the requested page or homepage
    header("Location: " . SITE_URL . ($redirect ? "/index.php?page=$redirect" : ""));
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $username = sanitize($_POST['username']);
    $password = $_POST['password']; // Don't sanitize passwords
    
    // Validate input
    if (empty($username) || empty($password)) {
        $loginError = 'Please enter both username and password';
    } else {
        // Attempt login
        $result = loginUser($username, $password);
        
        if ($result['success']) {
            // Set logged_in flag in session
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;  // Store username in session
            $_SESSION['user_type'] = $result['user']['user_type']; // Ensure user_type is set
            
            // Debugging: Log session variables after successful login
            error_log("Logged In: " . ($_SESSION['logged_in'] ? 'true' : 'false'));
            error_log("Username: " . $_SESSION['username']);
            error_log("User Type: " . $_SESSION['user_type']);
            
            // Custom log for debugging session variables
            error_log("Debug Log - Session Variables", 3, "c:/xampp/htdocs/kindfund/debug.log");
            error_log("Logged In: " . ($_SESSION['logged_in'] ? 'true' : 'false'), 3, "c:/xampp/htdocs/kindfund/debug.log");
            error_log("Username: " . $_SESSION['username'], 3, "c:/xampp/htdocs/kindfund/debug.log");
            error_log("User Type: " . $_SESSION['user_type'], 3, "c:/xampp/htdocs/kindfund/debug.log");
            
            // If user is admin and trying to access admin area, direct them there
            if ($redirect === 'admin' && $_SESSION['user_type'] === 'admin') {
                header("Location: " . SITE_URL . "/admin/");
                exit;
            }
            
            // Otherwise redirect to intended page or homepage
            header("Location: " . SITE_URL . ($redirect ? "/index.php?page=$redirect" : ""));
            exit;
        } else {
            $loginError = $result['message'];  // Show error message if login fails
        }
    }
}
?>
<!-- Rest of your login.php HTML content -->

<div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-center text-gray-800 dark:text-gray-200 mb-6">Login to KindFund</h1>
        
        <?php if ($loginError): ?>
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                <p><?php echo $loginError; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate="true">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            
            <div class="mb-4">
                <label for="username" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Username or Email</label>
                <input type="text" id="username" name="username" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
            </div>
            
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Remember me</label>
                </div>
                
                <a href="#" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Forgot Password?</a>
            </div>
            
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Login
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600 dark:text-gray-400">
                Don't have an account? 
                <a href="<?php echo SITE_URL; ?>/index.php?page=register<?php echo $redirect ? '&redirect=' . urlencode($redirect) : ''; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    Register
                </a>
            </p>
        </div>
    </div>
</div>
        By logging in, you agree to our 
        <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">Terms of Service</a> and 
        <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">Privacy Policy</a>.
    </p>
</div>
