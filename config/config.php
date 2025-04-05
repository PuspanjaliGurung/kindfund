<?php
// Website configuration
define('SITE_NAME', 'KindFund');
define('SITE_URL', 'http://localhost/kindfund');
define('ADMIN_EMAIL', 'admin@kindfund.com');

// Paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDE_PATH', ROOT_PATH . 'includes/');
define('UPLOAD_PATH', ROOT_PATH . 'assets/uploads/');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}