<?php
// Include error logging
require_once 'log_errors.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration files
require_once 'config/config.php';
require_once 'config/db.php';
// Include khalti-config if it exists
if (file_exists('config/khalti-config.php')) {
    require_once 'config/khalti-config.php';
}
require_once 'includes/functions.php';

// Rest of your index.php code...

// Determine which page to load
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Define allowed pages
$allowedPages = [
    'home', 'donations', 'login', 'register', 
    'dashboard', 'campaign', 'about', 'contact',
    'logout', 'charity', 'volunteer-events', 'volunteer',
    'donation-receipt', 'payment-success', 'payment-failure',
    'profile', 'change-password'
    
];

// Validate page parameter
if (!in_array($page, $allowedPages)) {
    $page = 'home'; // Default to home if invalid page
}

// Check if login required for this page
$loginRequiredPages = ['dashboard'];
if (in_array($page, $loginRequiredPages) && !isLoggedIn()) {
    // Redirect to login page with return URL
    header("Location: " . SITE_URL . "/index.php?page=login&redirect=" . urlencode($page));
    exit;
}

// Handle logout
if ($page === 'logout') {
    // Clear session
    session_unset();
    session_destroy();
    
    // Redirect to home page
    header("Location: " . SITE_URL);
    exit;
}

error_log("Current Working Directory: " . getcwd());
error_log("Include Path: " . get_include_path());

// Include header
include 'includes/header.php';

// Check if the requested page file exists
if (file_exists('pages/' . $page . '.php')) {
    // Load the requested page
    include 'pages/' . $page . '.php';
} else {
    echo "<h1>404 - Page Not Found</h1>";
}

// Include footer
include 'includes/footer.php';
?>
