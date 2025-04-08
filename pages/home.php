<?php
require_once __DIR__ . '/../includes/admin-auth.php';

// Get active campaigns
$campaigns = getActiveCampaigns(6);

// Get recommended campaigns if user is logged in and not admin
$recommended = [];
if (isLoggedIn() && !isAdmin()) {
    $recommended = getRecommendedCampaigns($_SESSION['user_id'], 3);
}
?>

<!-- Hero Section -->
<section class="relative bg-gray-100 dark:bg-gray-800 rounded-xl overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 to-purple-600 opacity-90"></div>
    <div class="relative px-6 py-12 md:py-20 md:px-12 text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">Make a Difference Today</h1>
        <p class="text-lg md:text-xl text-white mb-8 max-w-2xl mx-auto">
            Join thousands of donors supporting charitable causes worldwide. Your contribution can change lives.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="<?php echo SITE_URL; ?>/index.php?page=donations" class="bg-white text-indigo-600 hover:bg-gray-100 font-bold py-3 px-6 rounded-full shadow-lg transition duration-300">
                Donate Now
            </a>
            <a href="<?php echo SITE_URL; ?>/index.php?page=about" class="bg-transparent border-2 border-white text-white hover:bg-white hover:text-indigo-600 font-bold py-3 px-6 rounded-full shadow-lg transition duration-300">
                Learn More
            </a>
        </div>
    </div>
</section>

<!-- Featured Campaigns Section -->
<section class="py-10">
    <div class="mb-8 text-center">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200">Featured Campaigns</h2>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Support these worthy causes and make a real impact</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($campaigns as $campaign): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden transition-transform duration-300 hover:shadow-lg hover:-translate-y-1">
                <div class="h-48 bg-gray-300 dark:bg-gray-700 relative">
                    <?php if (!empty($campaign['campaign_image'])): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/campaigns/<?php echo htmlspecialchars($campaign['campaign_image']); ?>" 
                             alt="<?php echo htmlspecialchars($campaign['title']); ?>"
                             class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold text-gray-600 dark:text-gray-400">No Image Available</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo $campaign['charity_name']; ?></span>
                        <span class="text-xs px-2 py-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-full">
                            <?php echo date('M d', strtotime($campaign['end_date'])); ?> deadline
                        </span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2"><?php echo $campaign['title']; ?></h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
                        <?php echo substr($campaign['description'], 0, 100) . '...'; ?>
                    </p>
                    
                    <!-- Progress bar -->
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-2">
                        <?php $progress = calculateProgress($campaign['current_amount'], $campaign['goal_amount']); ?>
                        <div class="bg-indigo-600 dark:bg-indigo-500 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $progress; ?>%"></div>
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
    </div>
    
    <div class="text-center mt-8">
        <a href="<?php echo SITE_URL; ?>/index.php?page=donations" class="inline-block border-2 border-indigo-600 dark:border-indigo-500 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500 dark:hover:text-white font-bold py-2 px-6 rounded-full transition duration-300">
            View All Campaigns
        </a>
    </div>
</section>>


<!-- Testimonials Section -->
<section class="py-10">
    <div class="mb-8 text-center">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200">What People Say</h2>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Testimonials from donors and charities</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <div class="flex items-center mb-4">
            <img src="assets\images\puspa.png" alt="Elina Gurung" class="w-12 h-12 rounded-full object-cover mr-3" />
                <div>
                    <h4 class="font-bold text-gray-800 dark:text-gray-200">Puspa Gurung</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Regular Donor</p>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 italic">"I love how transparent KindFund is. I can track exactly where my donations go and see the real impact I'm making."</p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <div class="flex items-center mb-4">
            <img src="assets\images\elina.png" alt="Elina Gurung" class="w-12 h-12 rounded-full object-cover mr-3" />
                <div>
                    <h4 class="font-bold text-gray-800 dark:text-gray-200">Elina Gurung</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Charity Administrator</p>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 italic">"KindFund has revolutionized our fundraising efforts. The platform is easy to use and has helped us reach more donors than ever before."</p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
    <div class="flex items-center mb-4">
        <img src="assets\images\santoshi.png" alt="Elina Gurung" class="w-12 h-12 rounded-full object-cover mr-3" />
        <div>
            <h4 class="font-bold text-gray-800 dark:text-gray-200">Santoshi Gurung</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Volunteer</p>
        </div>
    </div>
    <p class="text-gray-600 dark:text-gray-400 italic">"I found amazing volunteer opportunities through KindFund. The platform connected me with causes I'm passionate about."</p>
</div>

    </div>
</section>

<!-- Call to Action -->
<section class="py-10 bg-gray-50 dark:bg-gray-900 rounded-xl text-center px-6">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-200 mb-4">Ready to Make a Difference?</h2>
    <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-2xl mx-auto">
        Join thousands of donors who are making a positive impact through KindFund. Start donating today and change lives.
    </p>
    <div class="flex flex-wrap justify-center gap-4">
        <a href="<?php echo SITE_URL; ?>/index.php?page=donations" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-300">
            Donate Now
        </a>
        <?php if (!isLoggedIn()): ?>
            <a href="<?php echo SITE_URL; ?>/index.php?page=register" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-300">
                Create Account
            </a>
        <?php endif; ?>
    </div>
</section>