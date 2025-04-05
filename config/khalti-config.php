<?php
// Khalti configuration - using your sandbox credentials
define('KHALTI_PUBLIC_KEY', '36385bdf45b543cd9016ee07393ae895'); // Your sandbox public key
define('KHALTI_SECRET_KEY', 'cd4f4d8eff3343b49e9ada4a78da9875'); // Your sandbox secret key
define('KHALTI_RETURN_URL', SITE_URL . '/api/khalti-callback.php');
define('KHALTI_WEBSITE_URL', SITE_URL);

// Set to false for sandbox testing
define('KHALTI_PRODUCTION_MODE', false);

// Sandbox API endpoint
define('KHALTI_API_ENDPOINT', 'https://dev.khalti.com/api/v2/');

// Currency configuration
define('CURRENCY_SYMBOL', 'Rs.');
define('CURRENCY_CODE', 'NPR');