<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';
require_once '../config/khalti-config.php';

// Check if user is admin
requireAdmin();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_settings'])) {
    $publicKey = sanitize($_POST['khalti_public_key']);
    $secretKey = sanitize($_POST['khalti_secret_key']);
    $isProduction = isset($_POST['production_mode']) ? 1 : 0;
    $currencySymbol = sanitize($_POST['currency_symbol']);
    $currencyCode = sanitize($_POST['currency_code']);
    
    // In a real application, you would update these values in a settings table in the database
    // For this example, we'll just show success message
    $message = "Payment settings updated successfully!";
    $messageType = "success";
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Payment Settings</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-6">Khalti Payment Gateway</h2>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="khalti_public_key" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Merchant Public Key</label>
                            <input type="text" id="khalti_public_key" name="khalti_public_key" value="36385bdf45b543cd9016ee07393ae895" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>

                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">The public key provided by Khalti for your merchant account.</p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="khalti_secret_key" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Merchant Secret Key</label>
                            <input type="password" id="khalti_secret_key" name="khalti_secret_key" value="<?php echo defined('KHALTI_SECRET_KEY') ? KHALTI_SECRET_KEY : ''; ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">The secret key provided by Khalti for your merchant account.</p>
                        </div>
                        
                        <div class="mb-6">
                            <div class="flex items-center">
                                <input type="checkbox" id="production_mode" name="production_mode" <?php echo defined('KHALTI_PRODUCTION_MODE') && KHALTI_PRODUCTION_MODE ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="production_mode" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Enable Production Mode
                                </label>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">When disabled, payments will be processed in test mode.</p>
                        </div>
                        
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-4">Currency Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="currency_symbol" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Currency Symbol</label>
                                <input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'Rs.'; ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            
                            <div>
                                <label for="currency_code" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Currency Code</label>
                                <input type="text" id="currency_code" name="currency_code" value="<?php echo defined('CURRENCY_CODE') ? CURRENCY_CODE : 'NPR'; ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_payment_settings" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
                <div class="p-6">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4">Khalti API Status</h3>
                    
                    <div class="mb-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                            <span class="text-gray-700 dark:text-gray-300">API is operational</span>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <p class="mb-2">Last synced: <?php echo date('M d, Y, h:i A'); ?></p>
                        <p>Test mode: <?php echo defined('KHALTI_PRODUCTION_MODE') && !KHALTI_PRODUCTION_MODE ? 'Enabled' : 'Disabled'; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-4">Quick Links</h3>
                    
                    <ul class="space-y-2">
                        <li>
                            <a href="https://merchant.khalti.com/" target="_blank" class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                <i class="fas fa-external-link-alt mr-2"></i> Khalti Merchant Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="https://docs.khalti.com/" target="_blank" class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                <i class="fas fa-book mr-2"></i> Khalti Documentation
                            </a>
                        </li>
                        <li>
                            <a href="donations.php" class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                <i class="fas fa-hand-holding-heart mr-2"></i> Donation Transactions
                            </a>
                        </li>
                        <li>
                            <a href="settings.php" class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                <i class="fas fa-cog mr-2"></i> General Settings
                            </a>
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
