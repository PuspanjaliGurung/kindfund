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

// Get charity details
$conn = getDBConnection();
$charitySql = "
    SELECT ch.*, u.username as admin_username, u.first_name as admin_first_name, u.last_name as admin_last_name
    FROM charities ch
    LEFT JOIN users u ON ch.admin_id = u.user_id
    WHERE ch.charity_id = ?
";
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

// Get campaigns for this charity
$campaignsSql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM donations WHERE campaign_id = c.campaign_id) as donation_count,
           (SELECT SUM(amount) FROM donations WHERE campaign_id = c.campaign_id) as total_donations
    FROM campaigns c
    WHERE c.charity_id = ?
    ORDER BY c.creation_date DESC
";
$campaignsStmt = $conn->prepare($campaignsSql);
$campaignsStmt->bind_param("i", $charityId);
$campaignsStmt->execute();
$campaignsResult = $campaignsStmt->get_result();

$campaigns = [];
while ($row = $campaignsResult->fetch_assoc()) {
    $campaigns[] = $row;
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <h1 class="text-2xl font-bold"><?php echo $charity['charity_name']; ?></h1>
            <span class="ml-4 px-3 py-1 text-sm rounded-full <?php echo $charity['is_verified'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200'; ?>">
                <?php echo $charity['is_verified'] ? 'Verified' : 'Unverified'; ?>
            </span>
        </div>
        
        <div class="flex space-x-3">
            <a href="edit-charity.php?id=<?php echo $charityId; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
            
            <a href="charities.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Charity Details -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-8">
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Charity Information</h2>
                    
                    <div class="prose dark:prose-invert max-w-none mb-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">About</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            <?php echo nl2br($charity['description']); ?>
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">Contact Details</h3>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                                <li><strong>Email:</strong> <?php echo $charity['email']; ?></li>
                                <?php if (!empty($charity['phone'])): ?>
                                    <li><strong>Phone:</strong> <?php echo $charity['phone']; ?></li>
                                <?php endif; ?>
                                <?php if (!empty($charity['website'])): ?>
                                    <li><strong>Website:</strong> <a href="<?php echo $charity['website']; ?>" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline"><?php echo $charity['website']; ?></a></li>
                                <?php endif; ?>
                                <?php if (!empty($charity['address'])): ?>
                                    <li><strong>Address:</strong> <?php echo $charity['address']; ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">Registration Details</h3>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                                <?php if (!empty($charity['registration_number'])): ?>
                                    <li><strong>Registration Number:</strong> <?php echo $charity['registration_number']; ?></li>
                                <?php endif; ?>
                                <li><strong>Created On:</strong> <?php echo date('M d, Y', strtotime($charity['creation_date'])); ?></li>
                                <li><strong>Managed By:</strong> 
                                    <?php if ($charity['admin_id']): ?>
                                        <?php echo $charity['admin_first_name'] . ' ' . $charity['admin_last_name'] . ' (' . $charity['admin_username'] . ')'; ?>
                                    <?php else: ?>
                                        <span class="text-yellow-600 dark:text-yellow-400">No admin assigned</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charity Campaigns -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Campaigns</h2>
                        
                        <a href="add-campaign.php?charity_id=<?php echo $charityId; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-1 px-3 rounded-lg flex items-center">
                            <i class="fas fa-plus mr-1"></i> Add Campaign
                        </a>
                    </div>
                    
                    <?php if (empty($campaigns)): ?>
                        <p class="text-gray-600 dark:text-gray-400">No campaigns found for this charity.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($campaigns as $campaign): ?>
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <div class="flex justify-between mb-2">
                                        <h3 class="font-bold text-gray-800 dark:text-gray-200">
                                            <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $campaign['campaign_id']; ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                <?php echo $campaign['title']; ?>
                                            </a>
                                        </h3>
                                        
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $campaign['is_active'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'; ?>">
                                            <?php echo $campaign['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-500">Goal:</span> 
                                            <?php echo formatNepaliCurrency($campaign['goal_amount']); ?>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-500">Raised:</span> 
                                            <?php echo formatNepaliCurrency($campaign['total_donations'] ?: 0); ?>
                                            (<?php echo $campaign['donation_count']; ?> donations)
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-500">End Date:</span> 
                                            <?php echo date('M d, Y', strtotime($campaign['end_date'])); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress bar -->
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-2">
                                        <?php $progress = calculateProgress($campaign['current_amount'], $campaign['goal_amount']); ?>
                                        <div class="bg-indigo-600 dark:bg-indigo-500 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    
                                    <div class="flex justify-end mt-2">
                                        <a href="edit-campaign.php?id=<?php echo $campaign['campaign_id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 text-sm mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="view-campaign.php?id=<?php echo $campaign['campaign_id']; ?>" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 text-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-4">Charity Statistics</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Campaigns</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?php echo count($campaigns); ?></div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Active Campaigns</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                                <?php 
                                    $activeCampaigns = array_filter($campaigns, function($c) { return $c['is_active'] == 1; });
                                    echo count($activeCampaigns);
                                ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Raised</div>
                            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                <?php 
                                    $totalRaised = array_reduce($campaigns, function($carry, $c) { 
                                        return $carry + ($c['total_donations'] ?: 0); 
                                    }, 0);
                                    echo formatNepaliCurrency($totalRaised);
                                ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Donations</div>
                            <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                                <?php 
                                    $totalDonations = array_reduce($campaigns, function($carry, $c) { 
                                        return $carry + $c['donation_count']; 
                                    }, 0);
                                    echo $totalDonations;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-4">Quick Actions</h3>
                    
                    <div class="space-y-3">
                        <a href="edit-charity.php?id=<?php echo $charityId; ?>" class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                            <i class="fas fa-edit mr-2"></i> Edit Charity
                        </a>
                        
                        <a href="add-campaign.php?charity_id=<?php echo $charityId; ?>" class="flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                            <i class="fas fa-plus mr-2"></i> Add Campaign
                        </a>
                        
                        <?php if ($charity['is_verified']): ?>
                            <form method="POST" action="charities.php" class="block" onsubmit="return confirm('Are you sure you want to unverify this charity?');">
                                <input type="hidden" name="charity_id" value="<?php echo $charityId; ?>">
                                <input type="hidden" name="is_verified" value="1">
                                <button type="submit" name="toggle_verification" class="flex items-center text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300">
                                    <i class="fas fa-times-circle mr-2"></i> Unverify Charity
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="charities.php" class="block" onsubmit="return confirm('Are you sure you want to verify this charity?');">
                                <input type="hidden" name="charity_id" value="<?php echo $charityId; ?>">
                                <input type="hidden" name="is_verified" value="0">
                                <button type="submit" name="toggle_verification" class="flex items-center text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300">
                                    <i class="fas fa-check-circle mr-2"></i> Verify Charity
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if (count($campaigns) === 0): ?>
                            <form method="POST" action="charities.php" class="block" onsubmit="return confirm('Are you sure you want to delete this charity? This action cannot be undone.');">
                                <input type="hidden" name="charity_id" value="<?php echo $charityId; ?>">
                                <button type="submit" name="delete_charity" class="flex items-center text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                                    <i class="fas fa-trash-alt mr-2"></i> Delete Charity
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>