<?php

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KindFund Admin</title>
    
    <!-- Tailwind CSS - Local -->
    <link href="<?php echo SITE_URL; ?>/assets/vendor/css/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome - CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Alpine.js - Local -->
    <script src="<?php echo SITE_URL; ?>/assets/vendor/js/alpine.min.js" defer></script>
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin-style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 min-h-screen flex flex-col">
    <header class="bg-white dark:bg-gray-800 shadow-md">
        <div class="container mx-auto px-4 py-3 flex flex-wrap items-center justify-between">
            <a href="<?php echo SITE_URL; ?>/admin/" class="flex items-center">
                <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">Kind<span class="text-purple-600 dark:text-purple-400">Fund</span> Admin</span>
            </a>
            
            <div class="flex items-center">
                
                <!-- Admin dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center text-sm font-medium rounded-full focus:outline-none">
                        <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <?php echo strtoupper(substr($currentUser['first_name'], 0, 1)); ?>
                        </div>
                        <span class="ml-2 text-gray-700 dark:text-gray-300"><?php echo $currentUser['first_name']; ?></span>
                        <i class="fas fa-chevron-down ml-1"></i>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg z-10">
                        <div class="py-1">
                            <a href="<?php echo SITE_URL; ?>/index.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-home mr-2"></i> Main Site
                            </a>
                            <!-- <a href="<?php echo SITE_URL; ?>/index.php?page=dashboard" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-user mr-2"></i> My Profile
                            </a> -->
                            <div class="border-t border-gray-200 dark:border-gray-700"></div>
                            <a href="<?php echo SITE_URL; ?>/index.php?page=logout" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="flex flex-1">
        <!-- Sidebar -->
        <aside class="w-64 bg-white dark:bg-gray-800 shadow-md hidden md:block">
            <nav class="mt-5 px-4">
                <a href="<?php echo SITE_URL; ?>/admin/" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md mb-1 <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/campaigns.php" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md mb-1 <?php echo basename($_SERVER['PHP_SELF']) === 'campaigns.php' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-bullhorn mr-3"></i> Campaigns
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/charities.php" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md mb-1 <?php echo basename($_SERVER['PHP_SELF']) === 'charities.php' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-building mr-3"></i> Charities
                </a>
               
                <a href="<?php echo SITE_URL; ?>/admin/users.php" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md mb-1 <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-users mr-3"></i> Users
                </a>
               
                <a href="<?php echo SITE_URL; ?>/admin/volunteer-events.php" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md mb-1 <?php echo basename($_SERVER['PHP_SELF']) === 'volunteer-events.php' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-hands-helping mr-3"></i> Volunteer Events
                </a>
            </nav>
        </aside>
        
        <!-- Mobile menu toggle -->
        <div class="md:hidden fixed bottom-0 inset-x-0 bg-white dark:bg-gray-800 z-10 shadow-md">
            <div class="flex justify-around">
                <a href="<?php echo SITE_URL; ?>/admin/" class="flex flex-col items-center py-2 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-tachometer-alt text-lg"></i>
                    <span class="text-xs mt-1">Dashboard</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/campaigns.php" class="flex flex-col items-center py-2 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 <?php echo basename($_SERVER['PHP_SELF']) === 'campaigns.php' ? 'text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-bullhorn text-lg"></i>
                    <span class="text-xs mt-1">Campaigns</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/users.php" class="flex flex-col items-center py-2 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-users text-lg"></i>
                    <span class="text-xs mt-1">Users</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/donations.php" class="flex flex-col items-center py-2 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 <?php echo basename($_SERVER['PHP_SELF']) === 'donations.php' ? 'text-indigo-600 dark:text-indigo-400' : ''; ?>">
                    <i class="fas fa-hand-holding-heart text-lg"></i>
                    <span class="text-xs mt-1">Donations</span>
                </a>
            </div>
        </div>
        
        <!-- Main content -->
        <main class="flex-1 overflow-y-auto p-5 pb-20 md:pb-5">
