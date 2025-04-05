<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Get all charities for the dropdown
$conn = getDBConnection();
$charitiesQuery = "SELECT charity_id, charity_name FROM charities ORDER BY charity_name";
$charitiesResult = $conn->query($charitiesQuery);

$charities = [];
while ($row = $charitiesResult->fetch_assoc()) {
    $charities[] = $row;
}

// Get all categories for the checkboxes
$categoriesQuery = "SELECT category_id, category_name FROM categories ORDER BY category_name";
$categoriesResult = $conn->query($categoriesQuery);

$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_campaign'])) {
    $charityId = intval($_POST['charity_id']);
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $goalAmount = floatval($_POST['goal_amount']);
    $startDate = sanitize($_POST['start_date']);
    $endDate = sanitize($_POST['end_date']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $campaignImage = '';
    
    // Handle image upload
    if (isset($_FILES['campaign_image']) && $_FILES['campaign_image']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (in_array($_FILES['campaign_image']['type'], $allowedTypes) && $_FILES['campaign_image']['size'] <= $maxSize) {
            $fileName = time() . '_' . basename($_FILES['campaign_image']['name']);
            $targetPath = '../uploads/campaigns/' . $fileName;
            
            if (move_uploaded_file($_FILES['campaign_image']['tmp_name'], $targetPath)) {
                $campaignImage = $fileName;
            } else {
                $message = "Error uploading image.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid image file. Please upload a JPG or PNG file under 2MB.";
            $messageType = "error";
        }
    }
    
    // Validate input
    if (empty($charityId) || empty($title) || empty($description) || $goalAmount <= 0 || empty($startDate) || empty($endDate)) {
        $message = "All required fields must be filled out";
        $messageType = "error";
    } else {
        // Insert campaign
        $insertSql = "INSERT INTO campaigns (charity_id, title, description, goal_amount, current_amount, start_date, end_date, is_active, campaign_image) 
                      VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)";
        
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("issdssss", $charityId, $title, $description, $goalAmount, $startDate, $endDate, $isActive, $campaignImage);
        
        if ($insertStmt->execute()) {
            $campaignId = $insertStmt->insert_id;
            
            // Insert campaign categories
            if (!empty($selectedCategories)) {
                $categoryInsertSql = "INSERT INTO campaign_categories (campaign_id, category_id) VALUES (?, ?)";
                $categoryStmt = $conn->prepare($categoryInsertSql);
                
                foreach ($selectedCategories as $categoryId) {
                    $categoryStmt->bind_param("ii", $campaignId, $categoryId);
                    $categoryStmt->execute();
                }
            }
            
            $message = "Campaign added successfully";
            $messageType = "success";
            
            // Redirect to campaigns list after successful addition
            header("Location: campaigns.php?success=added");
            exit;
        } else {
            $message = "Error adding campaign: " . $insertStmt->error;
            $messageType = "error";
        }
    }
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Add New Campaign</h1>
        
        <a href="campaigns.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Campaigns
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Campaign Details</h2>
                        
                        <div class="mb-4">
                            <label for="charity_id" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Charity</label>
                            <select id="charity_id" name="charity_id" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                                <option value="">Select a Charity</option>
                                <?php foreach ($charities as $charity): ?>
                                    <option value="<?php echo $charity['charity_id']; ?>"><?php echo $charity['charity_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="title" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Campaign Title</label>
                            <input type="text" id="title" name="title" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="goal_amount" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Goal Amount (NPR)</label>
                            <input type="number" id="goal_amount" name="goal_amount" min="1" step="0.01" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="start_date" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            <div>
                                <label for="end_date" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="is_active" name="is_active" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Active Campaign
                                </label>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">If checked, the campaign will be visible to users.</p>
                        </div>
                    </div>
                    
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Campaign Content</h2>
                        
                        <div class="mb-4">
                            <label for="description" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Description</label>
                            <textarea id="description" name="description" rows="10" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Categories</label>
                            <div class="grid grid-cols-2 gap-2">
                                <?php foreach ($categories as $category): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="category_<?php echo $category['category_id']; ?>" name="categories[]" value="<?php echo $category['category_id']; ?>" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="category_<?php echo $category['category_id']; ?>" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo $category['category_name']; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="campaign_image" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Campaign Image (optional)</label>
                            <input type="file" id="campaign_image" name="campaign_image" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Recommended size: 1200x600 pixels, max 2MB.</p>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
                    <button type="submit" name="add_campaign" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                        Create Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set minimum date for start and end date fields
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        
        // Make end date dependent on start date
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
            
            // If end date is before start date, reset it
            if (document.getElementById('end_date').value < this.value) {
                document.getElementById('end_date').value = this.value;
            }
        });
    });
</script>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>