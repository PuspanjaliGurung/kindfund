<?php
// Get all campaign categories
$conn = getDBConnection();
$categorySql = "SELECT * FROM categories ORDER BY category_name";
$categoryResult = $conn->query($categorySql);

$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
}

// Get filter parameters
$selectedCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Build query based on filters
$campaignSql = "
    SELECT c.*, ch.charity_name,
           (SELECT GROUP_CONCAT(cat.category_name SEPARATOR ', ')
            FROM campaign_categories cc
            JOIN categories cat ON cc.category_id = cat.category_id
            WHERE cc.campaign_id = c.campaign_id) AS categories
    FROM campaigns c
    JOIN charities ch ON c.charity_id = ch.charity_id
    WHERE c.is_active = 1 AND c.end_date >= NOW()
";

// Add category filter if selected
if ($selectedCategory > 0) {
    $campaignSql .= "
        AND c.campaign_id IN (
            SELECT campaign_id FROM campaign_categories WHERE category_id = $selectedCategory
        )
    ";
}

// Add search filter if provided
if (!empty($searchTerm)) {
    $campaignSql .= "
        AND (c.title LIKE '%$searchTerm%' OR c.description LIKE '%$searchTerm%' OR ch.charity_name LIKE '%$searchTerm%')
    ";
}

// Add sorting
switch ($sort) {
    case 'newest':
        $campaignSql .= " ORDER BY c.creation_date DESC";
        break;
    case 'oldest':
        $campaignSql .= " ORDER BY c.creation_date ASC";
        break;
    case 'most_funded':
        $campaignSql .= " ORDER BY c.current_amount DESC";
        break;
    case 'least_funded':
        $campaignSql .= " ORDER BY c.current_amount ASC";
        break;
    case 'ending_soon':
        $campaignSql .= " ORDER BY c.end_date ASC";
        break;
    default:
        $campaignSql .= " ORDER BY c.creation_date DESC";
}

$campaignResult = $conn->query($campaignSql);

$campaigns = [];
while ($row = $campaignResult->fetch_assoc()) {
    $campaigns[] = $row;
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Donation Campaigns</h1>
    <p class="text-gray-600 dark:text-gray-400">
        Browse through our active campaigns and support causes that matter to you.
    </p>
</div>

<!-- Filters Section -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
    <form action="" method="GET" class="space-y-4">
        <input type="hidden" name="page" value="donations">
        
        <div class="flex flex-wrap gap-4">
            <div class="w-full md:w-1/3">
                <label for="category" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Category</label>
                <select id="category" name="category" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo $selectedCategory == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo $category['category_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="w-full md:w-1/3">
                <label for="sort" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Sort By</label>
                <select id="sort" name="sort" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="most_funded" <?php echo $sort === 'most_funded' ? 'selected' : ''; ?>>Most Funded</option>
                    <option value="least_funded" <?php echo $sort === 'least_funded' ? 'selected' : ''; ?>>Least Funded</option>
                    <option value="ending_soon" <?php echo $sort === 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                </select>
            </div>
            
            <div class="w-full md:w-1/3">
                <label for="search" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Search campaigns...">
            </div>
        </div>
        
        <div class="flex justify-end">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Campaigns Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($campaigns)): ?>
        <div class="col-span-full text-center py-8">
            <p class="text-gray-600 dark:text-gray-400 text-lg mb-4">No campaigns found matching your criteria.</p>
            <a href="<?php echo SITE_URL; ?>/index.php?page=donations" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                View All Campaigns
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($campaigns as $campaign): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden transition-transform duration-300 hover:shadow-lg hover:-translate-y-1">
                <div class="h-48 bg-gray-300 dark:bg-gray-700 relative">
                    <!-- Placeholder for campaign image -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-lg font-bold text-gray-600 dark:text-gray-400"><?php echo $campaign['title']; ?></span>
                    </div>
                    
                    <?php if (isset($campaign['categories'])): ?>
                        <div class="absolute top-2 left-2">
                            <span class="inline-block px-2 py-1 text-xs bg-white dark:bg-gray-900 bg-opacity-90 dark:bg-opacity-90 rounded text-gray-700 dark:text-gray-300">
                                <?php echo $campaign['categories']; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo $campaign['charity_name']; ?></span>
                        <span class="text-xs px-2 py-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-full">
                            <?php 
                                $daysLeft = floor((strtotime($campaign['end_date']) - time()) / (60 * 60 * 24));
                                echo $daysLeft > 0 ? $daysLeft . ' days left' : 'Ending today';
                            ?>
                        </span>
                    </div>
                    
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2"><?php echo $campaign['title']; ?></h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
                        <?php echo substr($campaign['description'], 0, 100) . '...'; ?>
                    </p>
                    
                    <!-- Progress bar -->
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-2">
                        <?php $progress = calculateProgress($campaign['current_amount'], $campaign['goal_amount']); ?>
                        <div class="bg-indigo-600 dark:bg-indigo-500 h-2.5 rounded-full progress-bar" data-percent="<?php echo $progress; ?>" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    
                    <div class="flex justify-between text-sm mb-4">
                        <span class="text-gray-600 dark:text-gray-400">
                            <?php echo formatCurrency($campaign['current_amount']); ?> raised
                        </span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            <?php echo round($progress); ?>% of <?php echo formatCurrency($campaign['goal_amount']); ?>
                        </span>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $campaign['campaign_id']; ?>" class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                        Donate Now
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>