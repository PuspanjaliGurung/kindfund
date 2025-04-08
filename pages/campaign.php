<?php
// Get campaign ID from URL
$campaignId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if campaign ID is valid
if ($campaignId <= 0) {
    // Redirect to donations page if invalid ID
    header("Location: " . SITE_URL . "/index.php?page=donations");
    exit;
}

// Get campaign details
$conn = getDBConnection();
$campaignSql = "
    SELECT c.*, ch.charity_name, ch.description AS charity_description, ch.email AS charity_email, ch.website AS charity_website,
           (SELECT GROUP_CONCAT(cat.category_name SEPARATOR ', ')
            FROM campaign_categories cc
            JOIN categories cat ON cc.category_id = cat.category_id
            WHERE cc.campaign_id = c.campaign_id) AS categories
    FROM campaigns c
    JOIN charities ch ON c.charity_id = ch.charity_id
    WHERE c.campaign_id = ?
";

$stmt = $conn->prepare($campaignSql);
$stmt->bind_param("i", $campaignId);
$stmt->execute();
$result = $stmt->get_result();

// Check if campaign exists
if ($result->num_rows !== 1) {
    // Redirect to donations page if campaign not found
    header("Location: " . SITE_URL . "/index.php?page=donations");
    exit;
}

$campaign = $result->fetch_assoc();

// Calculate days left
$daysLeft = floor((strtotime($campaign['end_date']) - time()) / (60 * 60 * 24));
$progress = calculateProgress($campaign['current_amount'], $campaign['goal_amount']);

// Get recent donations for this campaign
$donationsSql = "
    SELECT d.*, u.username, u.first_name, u.last_name
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.campaign_id = ?
    ORDER BY d.donation_date DESC
    LIMIT 5
";

$stmtDonations = $conn->prepare($donationsSql);
$stmtDonations->bind_param("i", $campaignId);
$stmtDonations->execute();
$donationsResult = $stmtDonations->get_result();

$donations = [];
while ($row = $donationsResult->fetch_assoc()) {
    $donations[] = $row;
}

// Handle donation form submission
$donationSuccess = false;
$donationError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donate'])) {
    $amount = floatval(sanitize($_POST['amount']));
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    $isAnonymous = isset($_POST['anonymous']) ? 1 : 0;
    $paymentMethod = sanitize($_POST['payment_method']);
    
    // Validate amount
    if ($amount <= 0) {
        $donationError = 'Please enter a valid donation amount';
    } else {
        // In a real application, you would process the payment here
        // For this example, we'll just insert the donation directly
        
        $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
        $transactionId = 'txn_' . time() . rand(1000, 9999); // Simulate transaction ID
        
        // Insert donation
        $insertSql = "
            INSERT INTO donations (user_id, campaign_id, amount, payment_method, transaction_id, is_anonymous, message)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmtInsert = $conn->prepare($insertSql);
        $stmtInsert->bind_param("idsssss", $userId, $campaignId, $amount, $paymentMethod, $transactionId, $isAnonymous, $message);
        
        if ($stmtInsert->execute()) {
            // Update campaign's current amount
            $updateSql = "
                UPDATE campaigns
                SET current_amount = current_amount + ?
                WHERE campaign_id = ?
            ";
            
            $stmtUpdate = $conn->prepare($updateSql);
            $stmtUpdate->bind_param("di", $amount, $campaignId);
            $stmtUpdate->execute();
            
            // Set success flag
            $donationSuccess = true;
            
            // Refresh campaign data
            $stmt->execute();
            $result = $stmt->get_result();
            $campaign = $result->fetch_assoc();
            
            // Recalculate progress
            $progress = calculateProgress($campaign['current_amount'], $campaign['goal_amount']);
        } else {
            $donationError = 'An error occurred while processing your donation. Please try again.';
        }
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Campaign Details (2/3 width on large screens) -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="h-60 bg-gray-300 dark:bg-gray-700 relative">
                <?php if (!empty($campaign['campaign_image'])): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/campaigns/<?php echo htmlspecialchars($campaign['campaign_image']); ?>" 
                         alt="<?php echo htmlspecialchars($campaign['title']); ?>"
                         class="w-full h-full object-cover">
                <?php else: ?>
                    <!-- Placeholder for campaign without image -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-2xl font-bold text-gray-600 dark:text-gray-400"><?php echo $campaign['title']; ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($campaign['categories'])): ?>
                    <div class="absolute top-4 left-4">
                        <span class="inline-block px-3 py-1 text-sm bg-white dark:bg-gray-900 bg-opacity-90 dark:bg-opacity-90 rounded-lg text-gray-700 dark:text-gray-300">
                            <?php echo $campaign['categories']; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="p-6">
                <div class="flex flex-wrap justify-between items-center mb-4">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2 md:mb-0">
                        <?php echo $campaign['title']; ?>
                    </h1>
                    
                    <div class="flex items-center">
                        <span class="bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-3 py-1 rounded-full text-sm font-medium">
                            <?php 
                                if ($daysLeft > 0) {
                                    echo $daysLeft . ' ' . ($daysLeft === 1 ? 'day' : 'days') . ' left';
                                } else {
                                    echo 'Ending today';
                                }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-6">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        By <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline"><?php echo $campaign['charity_name']; ?></a>
                    </div>
                    
                    <!-- Progress bar -->
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 mb-3">
                        <div class="bg-indigo-600 dark:bg-indigo-500 h-3 rounded-full progress-bar" data-percent="<?php echo $progress; ?>" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    
                    <div class="flex justify-between text-sm mb-4">
                        <span class="text-gray-600 dark:text-gray-400">
                            <span class="font-bold text-gray-800 dark:text-gray-200 text-lg"><?php echo formatCurrency($campaign['current_amount']); ?></span> raised
                        </span>
                        <span class="text-gray-600 dark:text-gray-400">
                            <span class="font-bold text-gray-800 dark:text-gray-200"><?php echo round($progress); ?>%</span> of <?php echo formatCurrency($campaign['goal_amount']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="prose dark:prose-invert max-w-none mb-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-3">About This Campaign</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        <?php echo nl2br($campaign['description']); ?>
                    </p>
                </div>
                
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-3">About <?php echo $campaign['charity_name']; ?></h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <?php echo substr($campaign['charity_description'], 0, 300) . (strlen($campaign['charity_description']) > 300 ? '...' : ''); ?>
                    </p>
                    
                    <div class="flex flex-wrap gap-3">
                        <?php if (!empty($campaign['charity_website'])): ?>
                            <a href="<?php echo $campaign['charity_website']; ?>" target="_blank" class="inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:underline">
                                <i class="fas fa-globe mr-1"></i> Visit Website
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($campaign['charity_email'])): ?>
                            <a href="mailto:<?php echo $campaign['charity_email']; ?>" class="inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:underline">
                                <i class="fas fa-envelope mr-1"></i> Contact
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Donations -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mt-8">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Recent Donations</h3>
                
                <?php if (empty($donations)): ?>
                    <p class="text-gray-600 dark:text-gray-400">
                        No donations yet. Be the first to donate!
                    </p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($donations as $donation): ?>
                            <div class="flex items-start">
                                <div class="bg-indigo-100 dark:bg-indigo-900 rounded-full w-10 h-10 flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-indigo-600 dark:text-indigo-400"></i>
                                </div>
                                <div>
                                    <div class="flex items-center">
                                        <span class="font-bold text-gray-800 dark:text-gray-200">
                                            <?php 
                                                if ($donation['is_anonymous']) {
                                                    echo 'Anonymous';
                                                } else {
                                                    echo $donation['first_name'] ? $donation['first_name'] . ' ' . $donation['last_name'] : $donation['username'];
                                                }
                                            ?>
                                        </span>
                                        <span class="text-gray-600 dark:text-gray-400 text-sm ml-2">
                                            <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                        </span>
                                    </div>
                                    <p class="font-bold text-indigo-600 dark:text-indigo-400">
                                        <?php echo formatCurrency($donation['amount']); ?>
                                    </p>
                                    <?php if (!empty($donation['message'])): ?>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm italic">
                                            "<?php echo $donation['message']; ?>"
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
   <!-- Donation Form (1/3 width on large screens) -->
<!-- Donation Form (1/3 width on large screens) -->
<div class="lg:col-span-1">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden sticky top-4">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Make a Donation</h3>
            
            <?php if (isLoggedIn()): ?>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                    <div class="bg-blue-100 dark:bg-blue-900 border border-blue-400 dark:border-blue-700 text-blue-700 dark:text-blue-300 px-4 py-3 rounded mb-4">
                        <p class="font-bold">Admin Note</p>
                        <p>As an admin, you can view campaign details but donation functionality is available only to regular users.</p>
                    </div>
                <?php else: ?>
                    <!-- Regular donation form content for non-admin users -->
                    <form id="donation-form" method="POST" action="javascript:void(0);" data-validate="true">
                        <input type="hidden" name="campaign_id" id="campaign_id" value="<?php echo $campaignId; ?>">
                        <input type="hidden" name="amount" id="amount_value" value="500">
                        
                        <div class="mb-4">
                        
                            
                            <div class="mt-2">
                                <label for="custom-amount" class="block text-gray-700 dark:text-gray-300 text-sm mb-1"> enter custom amount:</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-600 dark:text-gray-400">Rs.</span>
                                    <input type="number" id="custom-amount" class="w-full pl-10 pr-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" min="10" placeholder="Enter amount">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Message (Optional)</label>
                            <textarea id="message" name="message" rows="3" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" placeholder="Add a message of support..."></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <div class="flex items-center">
                                <input type="checkbox" id="anonymous" name="anonymous" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="anonymous" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Make my donation anonymous
                                </label>
                            </div>
                        </div>
                        
                        <button type="button" id="khalti-payment-button" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512" class="mr-2"><path fill="#ffffff" d="M57.7 193l9.4 16.4c8.3 14.5 21.9 25.2 38 29.8L163 255.7c17.2 4.9 29 20.6 29 38.5v39.9c0 11 6.2 21 16 25.9s16 14.9 16 25.9v39c0 15.6 14.9 26.9 29.9 22.6c16.1-4.6 28.6-17.5 32.7-33.8l2.8-11.2c4.2-16.9 15.2-31.4 30.3-40l8.1-4.6c15-8.5 24.2-24.5 24.2-41.7v-8.3c0-12.7-5.1-24.9-14.1-33.9l-3.9-3.9c-9-9-21.2-14.1-33.9-14.1H257c-11.1 0-22.1-2.9-31.8-8.4l-34.5-19.7c-4.3-2.5-7.6-6.5-9.2-11.2c-3.2-9.6 1.1-20 10.2-24.5l5.9-3c6.6-3.3 14.3-3.9 21.3-1.5l23.2 7.7c8.2 2.7 17.2-.4 21.9-7.5c4.7-7 4.2-16.3-1.2-22.8l-13.6-16.3c-10-12-9.9-29.5.3-41.3l15.7-18.3c8.8-10.3 10.2-25 3.5-36.7l-2.4-4.2c-3.5-.2-6.9-.3-10.4-.3C163.1 48 84.4 108.9 57.7 193zM464 256c0-36.8-9.6-71.4-26.4-101.5L412 164.8c-15.7 6.3-23.8 23.8-18.5 39.8l16.9 50.7c3.5 10.4 12 18.3 22.6 20.9l29.1 7.3c1.2-9 1.8-18.2 1.8-27.5zM0 256C0 114.6 114.6 0 256 0S512 114.6 512 256s-114.6 256-256 256S0 397.4 0 256z"/></svg>
                            Pay with Khalti
                        </button>
                        
                    
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="space-y-3">
                    <a href="<?php echo SITE_URL; ?>/index.php?page=login&amp;redirect=campaign&amp;id=<?php echo $campaignId; ?>" class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                        Login to Donate
                    </a>
                    <p class="text-gray-600 dark:text-gray-400 text-sm text-center">
                        Don't have an account? 
                        <a href="<?php echo SITE_URL; ?>/index.php?page=register&amp;redirect=campaign&amp;id=<?php echo $campaignId; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                            Register
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="mt-6 text-sm text-center text-gray-600 dark:text-gray-400">
                <p>
                    <i class="fas fa-lock mr-1"></i> Secure payment powered by Khalti
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle amount selection
        const amountOptions = document.querySelectorAll('.amount-option');
        const customAmountInput = document.getElementById('custom-amount');
        const amountValue = document.getElementById('amount_value');
        
        amountOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                amountOptions.forEach(opt => opt.classList.remove('active'));
                amountOptions.forEach(opt => opt.classList.remove('bg-indigo-100', 'dark:bg-indigo-900', 'text-indigo-800', 'dark:text-indigo-200'));
                amountOptions.forEach(opt => opt.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200'));
                
                // Add active class to selected option
                option.classList.add('active');
                option.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');
                option.classList.add('bg-indigo-100', 'dark:bg-indigo-900', 'text-indigo-800', 'dark:text-indigo-200');
                
                // Update hidden input with selected amount
                const selectedAmount = option.getAttribute('data-amount');
                amountValue.value = selectedAmount;
                
                // Clear custom amount
                if (customAmountInput) {
                    customAmountInput.value = '';
                }
            });
        });
        
        // Handle custom amount input
        if (customAmountInput) {
            customAmountInput.addEventListener('input', function() {
                if (this.value) {
                    // Remove active class from predefined options
                    amountOptions.forEach(opt => opt.classList.remove('active'));
                    amountOptions.forEach(opt => opt.classList.remove('bg-indigo-100', 'dark:bg-indigo-900', 'text-indigo-800', 'dark:text-indigo-200'));
                    amountOptions.forEach(opt => opt.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200'));
                    
                    // Update hidden input with custom amount
                    amountValue.value = this.value;
                }
            });
        }
        
        // Handle Khalti payment button
        const khaltiBtn = document.getElementById('khalti-payment-button');
        const donationForm = document.getElementById('donation-form');
        const messageInput = document.getElementById('message');
        const anonymousCheckbox = document.getElementById('anonymous');
        const campaignId = document.getElementById('campaign_id')?.value;
        
        if (khaltiBtn && campaignId) {
            khaltiBtn.addEventListener('click', function() {
                // Show loading state
                khaltiBtn.disabled = true;
                khaltiBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
                
                // Get current amount value
                const amount = parseFloat(amountValue.value);
                
                if (isNaN(amount) || amount < 10) {
                    alert('Please enter a valid amount (minimum Rs. 10)');
                    khaltiBtn.disabled = false;
                    khaltiBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512" class="mr-2"><path fill="#ffffff" d="M57.7 193l9.4 16.4c8.3 14.5 21.9 25.2 38 29.8L163 255.7c17.2 4.9 29 20.6 29 38.5v39.9c0 11 6.2 21 16 25.9s16 14.9 16 25.9v39c0 15.6 14.9 26.9 29.9 22.6c16.1-4.6 28.6-17.5 32.7-33.8l2.8-11.2c4.2-16.9 15.2-31.4 30.3-40l8.1-4.6c15-8.5 24.2-24.5 24.2-41.7v-8.3c0-12.7-5.1-24.9-14.1-33.9l-3.9-3.9c-9-9-21.2-14.1-33.9-14.1H257c-11.1 0-22.1-2.9-31.8-8.4l-34.5-19.7c-4.3-2.5-7.6-6.5-9.2-11.2c-3.2-9.6 1.1-20 10.2-24.5l5.9-3c6.6-3.3 14.3-3.9 21.3-1.5l23.2 7.7c8.2 2.7 17.2-.4 21.9-7.5c4.7-7 4.2-16.3-1.2-22.8l-13.6-16.3c-10-12-9.9-29.5.3-41.3l15.7-18.3c8.8-10.3 10.2-25 3.5-36.7l-2.4-4.2c-3.5-.2-6.9-.3-10.4-.3C163.1 48 84.4 108.9 57.7 193zM464 256c0-36.8-9.6-71.4-26.4-101.5L412 164.8c-15.7 6.3-23.8 23.8-18.5 39.8l16.9 50.7c3.5 10.4 12 18.3 22.6 20.9l29.1 7.3c1.2-9 1.8-18.2 1.8-27.5zM0 256C0 114.6 114.6 0 256 0S512 114.6 512 256s-114.6 256-256 256S0 397.4 0 256z"/></svg> Pay with Khalti';
                    return;
                }
                
                // Create form data
                const formData = new FormData();
                formData.append('campaign_id', campaignId);
                formData.append('amount', amount);
                formData.append('message', messageInput?.value || '');
                
                if (anonymousCheckbox?.checked) {
                    formData.append('anonymous', '1');
                }
                
                // Send to server to initialize payment
                fetch('<?php echo SITE_URL; ?>/api/process-donation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to Khalti payment URL
                        window.location.href = data.redirect_url;
                    } else {
                        console.error('Payment initialization error:', data);
                        alert(data.message || 'An error occurred. Please try again.');
                        khaltiBtn.disabled = false;
                        khaltiBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512" class="mr-2"><path fill="#ffffff" d="M57.7 193l9.4 16.4c8.3 14.5 21.9 25.2 38 29.8L163 255.7c17.2 4.9 29 20.6 29 38.5v39.9c0 11 6.2 21 16 25.9s16 14.9 16 25.9v39c0 15.6 14.9 26.9 29.9 22.6c16.1-4.6 28.6-17.5 32.7-33.8l2.8-11.2c4.2-16.9 15.2-31.4 30.3-40l8.1-4.6c15-8.5 24.2-24.5 24.2-41.7v-8.3c0-12.7-5.1-24.9-14.1-33.9l-3.9-3.9c-9-9-21.2-14.1-33.9-14.1H257c-11.1 0-22.1-2.9-31.8-8.4l-34.5-19.7c-4.3-2.5-7.6-6.5-9.2-11.2c-3.2-9.6 1.1-20 10.2-24.5l5.9-3c6.6-3.3 14.3-3.9 21.3-1.5l23.2 7.7c8.2 2.7 17.2-.4 21.9-7.5c4.7-7 4.2-16.3-1.2-22.8l-13.6-16.3c-10-12-9.9-29.5.3-41.3l15.7-18.3c8.8-10.3 10.2-25 3.5-36.7l-2.4-4.2c-3.5-.2-6.9-.3-10.4-.3C163.1 48 84.4 108.9 57.7 193zM464 256c0-36.8-9.6-71.4-26.4-101.5L412 164.8c-15.7 6.3-23.8 23.8-18.5 39.8l16.9 50.7c3.5 10.4 12 18.3 22.6 20.9l29.1 7.3c1.2-9 1.8-18.2 1.8-27.5zM0 256C0 114.6 114.6 0 256 0S512 114.6 512 256s-114.6 256-256 256S0 397.4 0 256z"/></svg> Pay with Khalti';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request. Please try again.');
                    khaltiBtn.disabled = false;
                    khaltiBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 512 512" class="mr-2"><path fill="#ffffff" d="M57.7 193l9.4 16.4c8.3 14.5 21.9 25.2 38 29.8L163 255.7c17.2 4.9 29 20.6 29 38.5v39.9c0 11 6.2 21 16 25.9s16 14.9 16 25.9v39c0 15.6 14.9 26.9 29.9 22.6c16.1-4.6 28.6-17.5 32.7-33.8l2.8-11.2c4.2-16.9 15.2-31.4 30.3-40l8.1-4.6c15-8.5 24.2-24.5 24.2-41.7v-8.3c0-12.7-5.1-24.9-14.1-33.9l-3.9-3.9c-9-9-21.2-14.1-33.9-14.1H257c-11.1 0-22.1-2.9-31.8-8.4l-34.5-19.7c-4.3-2.5-7.6-6.5-9.2-11.2c-3.2-9.6 1.1-20 10.2-24.5l5.9-3c6.6-3.3 14.3-3.9 21.3-1.5l23.2 7.7c8.2 2.7 17.2-.4 21.9-7.5c4.7-7 4.2-16.3-1.2-22.8l-13.6-16.3c-10-12-9.9-29.5.3-41.3l15.7-18.3c8.8-10.3 10.2-25 3.5-36.7l-2.4-4.2c-3.5-.2-6.9-.3-10.4-.3C163.1 48 84.4 108.9 57.7 193zM464 256c0-36.8-9.6-71.4-26.4-101.5L412 164.8c-15.7 6.3-23.8 23.8-18.5 39.8l16.9 50.7c3.5 10.4 12 18.3 22.6 20.9l29.1 7.3c1.2-9 1.8-18.2 1.8-27.5zM0 256C0 114.6 114.6 0 256 0S512 114.6 512 256s-114.6 256-256 256S0 397.4 0 256z"/></svg> Pay with Khalti';
                });
            });
        }
    });
</script>

</div>