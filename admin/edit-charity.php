<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Get charity ID from URL
$charityId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if charity ID is valid
if ($charityId <= 0) {
    header("Location: " . SITE_URL . "/admin/charities.php");
    exit;
}

// Initialize variables
$message = '';
$messageType = '';

// Get charity details
$conn = getDBConnection();
$charitySql = "SELECT * FROM charities WHERE charity_id = ?";
$charityStmt = $conn->prepare($charitySql);
$charityStmt->bind_param("i", $charityId);
$charityStmt->execute();
$charityResult = $charityStmt->get_result();

// Check if charity exists
if ($charityResult->num_rows !== 1) {
    header("Location: " . SITE_URL . "/admin/charities.php");
    exit;
}

$charity = $charityResult->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_charity'])) {
    // Get form data
    $formData = [
        'charity_name' => sanitize($_POST['charity_name']),
        'description' => sanitize($_POST['description']),
        'website' => sanitize($_POST['website']),
        'email' => sanitize($_POST['email']),
        'phone' => sanitize($_POST['phone']),
        'address' => sanitize($_POST['address']),
        'registration_number' => sanitize($_POST['registration_number']),
        'is_verified' => isset($_POST['is_verified']) ? 1 : 0
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($formData['charity_name'])) {
        $errors[] = 'Charity name is required';
    }
    
    if (empty($formData['description'])) {
        $errors[] = 'Description is required';
    }
    
    if (empty($formData['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // If no errors, update in database
    if (empty($errors)) {
        // Check if charity with same name already exists (excluding current one)
        $checkSql = "SELECT charity_id FROM charities WHERE charity_name = ? AND charity_id != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("si", $formData['charity_name'], $charityId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $message = "A charity with this name already exists";
            $messageType = "error";
        } else {
            // Update charity
            $updateSql = "UPDATE charities SET 
                charity_name = ?, 
                description = ?, 
                website = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                registration_number = ?,
                is_verified = ?
                WHERE charity_id = ?";
            
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param(
                "sssssssii", 
                $formData['charity_name'],
                $formData['description'],
                $formData['website'],
                $formData['email'],
                $formData['phone'],
                $formData['address'],
                $formData['registration_number'],
                $formData['is_verified'],
                $charityId
            );
            
            if ($updateStmt->execute()) {
                $message = "Charity updated successfully!";
                $messageType = "success";
                
                // Refresh charity data
                $charityStmt->execute();
                $charity = $charityStmt->get_result()->fetch_assoc();
            } else {
                $message = "Error updating charity: " . $updateStmt->error;
                $messageType = "error";
            }
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = "error";
    }
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Edit Charity</h1>
        
        <a href="charities.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Charities
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="charity_name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Charity Name <span class="text-red-500">*</span></label>
                        <input type="text" id="charity_name" name="charity_name" value="<?php echo htmlspecialchars($charity['charity_name']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                    </div>
                    
                    <div>
                        <label for="registration_number" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Registration Number</label>
                        <input type="text" id="registration_number" name="registration_number" value="<?php echo htmlspecialchars($charity['registration_number']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="description" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Description <span class="text-red-500">*</span></label>
                        <textarea id="description" name="description" rows="5" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required><?php echo htmlspecialchars($charity['description']); ?></textarea>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($charity['email']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($charity['phone']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label for="website" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Website</label>
                        <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($charity['website']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="https://">
                    </div>
                    
                    <div>
                        <label for="address" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($charity['address']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="is_verified" name="is_verified" <?php echo $charity['is_verified'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="is_verified" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Verified Charity
                            </label>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <button type="submit" name="update_charity" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                            Update Charity
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>