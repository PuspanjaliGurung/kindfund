<?php
require_once '../includes/admin-auth.php';
require_once '../includes/header.php';
require_once '../includes/admin-header.php';

// Database connection
require_once '../config/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and update settings
    $siteName = filter_input(INPUT_POST, 'site_name', FILTER_SANITIZE_STRING);
    $siteEmail = filter_input(INPUT_POST, 'site_email', FILTER_SANITIZE_EMAIL);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_STRING);
    
    // In a real application, you would save these to a settings table
    $_SESSION['settings_updated'] = true;
    header('Location: settings.php');
    exit;
}

// Get current settings (in a real app, from database)
$currentSettings = [
    'site_name' => 'KindFund',
    'site_email' => 'info@kindfund.org',
    'currency' => 'NPR'
];
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <h2>System Settings</h2>
            
            <?php if (isset($_SESSION['settings_updated'])): ?>
            <div class="alert alert-success">Settings updated successfully!</div>
            <?php unset($_SESSION['settings_updated']); endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="site_name" class="form-label">Site Name</label>
                    <input type="text" class="form-control" id="site_name" name="site_name" 
                           value="<?= htmlspecialchars($currentSettings['site_name']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="site_email" class="form-label">Admin Email</label>
                    <input type="email" class="form-control" id="site_email" name="site_email" 
                           value="<?= htmlspecialchars($currentSettings['site_email']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="currency" class="form-label">Currency</label>
                    <select class="form-control" id="currency" name="currency">
                        <option value="NPR" <?= $currentSettings['currency'] === 'NPR' ? 'selected' : '' ?>>NPR (रु)</option>
                        <option value="USD" <?= $currentSettings['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                        <option value="EUR" <?= $currentSettings['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </main>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>