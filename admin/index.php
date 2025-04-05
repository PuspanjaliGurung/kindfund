<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Set page title
$pageTitle = "Dashboard";

// Get stats
$conn = getDBConnection();

// Total donations
$donationQuery = "SELECT COUNT(*) as total, SUM(amount) as amount FROM donations";
$donationResult = $conn->query($donationQuery);
$donationStats = $donationResult->fetch_assoc();

// Active campaigns
$campaignQuery = "SELECT COUNT(*) as total FROM campaigns WHERE is_active = 1";
$campaignResult = $conn->query($campaignQuery);
$campaignStats = $campaignResult->fetch_assoc();

// Total users
$userQuery = "SELECT COUNT(*) as total FROM users";
$userResult = $conn->query($userQuery);
$userStats = $userResult->fetch_assoc();

// Recent donations
$recentDonationsQuery = "
    SELECT d.*, u.username, c.title as campaign_title
    FROM donations d
    JOIN users u ON d.user_id = u.user_id
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    ORDER BY d.donation_date DESC
    LIMIT 5
";
$recentDonationsResult = $conn->query($recentDonationsQuery);
$recentDonations = [];
while ($row = $recentDonationsResult->fetch_assoc()) {
    $recentDonations[] = $row;
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div class="container mx-auto px-4 py-4">
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 mr-4">
                    <i class="fas fa-hand-holding-heart text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Donations</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $donationStats['total']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-600 mr-4">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Amount</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($donationStats['amount']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mr-4">
                    <i class="fas fa-bullhorn text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Active Campaigns</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $campaignStats['total']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Registered Users</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $userStats['total']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Donations -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800">Recent Donations</h2>
                <a href="donations.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View All</a>
            </div>
        </div>
        
        <div class="p-6">
            <?php if (empty($recentDonations)): ?>
                <p class="text-gray-600 text-center py-4">No donations found.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentDonations as $donation): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo $donation['is_anonymous'] ? 'Anonymous' : $donation['username']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $donation['campaign_title']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?php echo formatCurrency($donation['amount']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($donation['donation_date'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?php echo SITE_URL; ?>/index.php?page=donation-receipt&id=<?php echo $donation['donation_id']; ?>" style="color: #10b981; text-decoration: none;">
                                            View Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-800">Quick Actions</h2>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="campaigns.php?action=add" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition duration-150">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mr-3">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div>
                        <p class="text-blue-600 font-medium">New Campaign</p>
                        <p class="text-blue-500 text-sm">Create a new campaign</p>
                    </div>
                </a>
                
                <a href="volunteering.php?action=add" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition duration-150">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600 mr-3">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div>
                        <p class="text-green-600 font-medium">New Event</p>
                        <p class="text-green-500 text-sm">Schedule a new event</p>
                    </div>
                </a>
                
                <a href="reports.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition duration-150">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mr-3">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <p class="text-purple-600 font-medium">Generate Reports</p>
                        <p class="text-purple-500 text-sm">View and export reports</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>