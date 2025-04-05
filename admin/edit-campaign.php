<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Get campaign ID
$campaignId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$campaignId) {
    header('Location: campaigns.php');
    exit;
}

$conn = getDBConnection();

// Get campaign data
$stmt = $conn->prepare("SELECT * FROM campaigns WHERE campaign_id = ?");
$stmt->bind_param("i", $campaignId);
$stmt->execute();
$result = $stmt->get_result();
$campaign = $result->fetch_assoc();

if (!$campaign) {
    header('Location: campaigns.php');
    exit;
}

// Get selected categories
$stmt = $conn->prepare("SELECT category_id FROM campaign_categories WHERE campaign_id = ?");
$stmt->bind_param("i", $campaignId);
$stmt->execute();
$result = $stmt->get_result();
$selectedCategories = [];
while ($row = $result->fetch_assoc()) {
    $selectedCategories[] = $row['category_id'];
}

// Get charities for dropdown
$charitiesQuery = "SELECT charity_id, charity_name FROM charities ORDER BY charity_name";
$charitiesResult = $conn->query($charitiesQuery);
$charities = [];
while ($row = $charitiesResult->fetch_assoc()) {
    $charities[] = $row;
}

// Get categories
$categoriesQuery = "SELECT category_id, category_name FROM categories ORDER BY category_name";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_campaign'])) {
    $charityId = intval($_POST['charity_id']);
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $goalAmount = floatval($_POST['goal_amount']);
    $startDate = sanitize($_POST['start_date']);
    $endDate = sanitize($_POST['end_date']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];

    // Validate input
    if (empty($charityId) || empty($title) || empty($description) || $goalAmount <= 0 || empty($startDate) || empty($endDate)) {
        $message = "All required fields must be filled out";
        $messageType = "error";
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Update campaign
            $updateSql = "UPDATE campaigns SET 
                        charity_id = ?, 
                        title = ?, 
                        description = ?, 
                        goal_amount = ?,
                        start_date = ?, 
                        end_date = ?, 
                        is_active = ?
                        WHERE campaign_id = ?";
            
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("issdssii", $charityId, $title, $description, $goalAmount, $startDate, $endDate, $isActive, $campaignId);
            $updateStmt->execute();

            // Update categories
            $deleteCatsSql = "DELETE FROM campaign_categories WHERE campaign_id = ?";
            $deleteStmt = $conn->prepare($deleteCatsSql);
            $deleteStmt->bind_param("i", $campaignId);
            $deleteStmt->execute();

            if (!empty($selectedCategories)) {
                $insertCatsSql = "INSERT INTO campaign_categories (campaign_id, category_id) VALUES (?, ?)";
                $insertStmt = $conn->prepare($insertCatsSql);
                
                foreach ($selectedCategories as $categoryId) {
                    $insertStmt->bind_param("ii", $campaignId, $categoryId);
                    $insertStmt->execute();
                }
            }

            $conn->commit();
            header("Location: campaigns.php?success=updated");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating campaign: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Edit Campaign</h1>
        
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
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Campaign Details</h2>
                        
                        <div class="mb-4">
                            <label for="charity_id" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Charity</label>
                            <select id="charity_id" name="charity_id" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                                <option value="">Select a Charity</option>
                                <?php foreach ($charities as $charity): ?>
                                    <option value="<?php echo $charity['charity_id']; ?>" <?php echo $charity['charity_id'] == $campaign['charity_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($charity['charity_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="title" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Campaign Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($campaign['title']); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="goal_amount" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Goal Amount (NPR)</label>
                            <input type="number" id="goal_amount" name="goal_amount" value="<?php echo htmlspecialchars($campaign['goal_amount']); ?>" min="1" step="0.01" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="start_date" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime($campaign['start_date'])); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                            <div>
                                <label for="end_date" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime($campaign['end_date'])); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="is_active" name="is_active" <?php echo $campaign['is_active'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
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
                            <textarea id="description" name="description" rows="10" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required><?php echo htmlspecialchars($campaign['description']); ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Categories</label>
                            <div class="grid grid-cols-2 gap-2">
                                <?php foreach ($categories as $category): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="category_<?php echo $category['category_id']; ?>" 
                                               name="categories[]" 
                                               value="<?php echo $category['category_id']; ?>" 
                                               <?php echo in_array($category['category_id'], $selectedCategories) ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="category_<?php echo $category['category_id']; ?>" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
                    <button type="submit" name="update_campaign" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                        Update Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        // Make end date dependent on start date
        startDateInput.addEventListener('change', function() {
            endDateInput.min = this.value;
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = this.value;
            }
        });
        
        // Set initial min value for end date
        endDateInput.min = startDateInput.value;
    });
</script>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>