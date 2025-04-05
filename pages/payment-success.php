<?php
// Get donation ID from URL
$donationId = isset($_GET['donation_id']) ? intval($_GET['donation_id']) : 0;

// Fetch donation details
$conn = getDBConnection();
$sql = "SELECT d.*, c.title as campaign_title, c.campaign_id, ch.charity_name
         FROM donations d
         JOIN campaigns c ON d.campaign_id = c.campaign_id
         JOIN charities ch ON c.charity_id = ch.charity_id
         WHERE d.donation_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $donationId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Donation not found, redirect to homepage
    header("Location: " . SITE_URL);
    exit;
}

$donation = $result->fetch_assoc();
?>

<div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
    <div class="bg-green-500 p-6 text-center">
        <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check-circle text-green-500 text-4xl"></i>
        </div>
        <h1 class="text-white text-2xl font-bold">Payment Successful!</h1>
    </div>
    
    <div class="p-6">
        <div class="mb-6 text-center">
            <p class="text-gray-600 dark:text-gray-400 text-lg">
                Thank you for your generous donation. Your contribution will make a real difference.
            </p>
        </div>
        
        <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Donation Details</h2>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Donation Amount:</span>
                    <span class="font-bold text-gray-800 dark:text-gray-200"><?php echo formatCurrency($donation['amount']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Campaign:</span>
                    <span class="font-medium text-indigo-600 dark:text-indigo-400"><?php echo $donation['campaign_title']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Charity:</span>
                    <span class="text-gray-800 dark:text-gray-200"><?php echo $donation['charity_name']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Transaction ID:</span>
                    <span class="text-gray-800 dark:text-gray-200"><?php echo $donation['transaction_id']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Date:</span>
                    <span class="text-gray-800 dark:text-gray-200"><?php echo date('M d, Y, h:i A', strtotime($donation['donation_date'])); ?></span>
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $donation['campaign_id']; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                Return to Campaign
            </a>
            
            <a href="<?php echo SITE_URL; ?>/index.php?page=dashboard" class="border-2 border-indigo-600 dark:border-indigo-500 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500 dark:hover:text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                View My Donations
            </a>
        </div>
    </div>
</div>