<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    
    <!-- Tailwind CSS - Local -->
    <link href="<?php echo SITE_URL; ?>/assets/vendor/css/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome - CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 min-h-screen flex flex-col">
    <header class="bg-white dark:bg-gray-800 shadow-md">
        <nav class="container mx-auto px-4 py-3 flex flex-wrap items-center justify-between">
            <div class="flex items-center">
                <a href="<?php echo SITE_URL; ?>" class="flex items-center">
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">Kind<span class="text-purple-600 dark:text-purple-400">Fund</span></span>
                </a>
            </div>
            
            <div class="block lg:hidden">
                <button id="mobile-menu-button" class="flex items-center px-3 py-2 border rounded text-indigo-600 dark:text-indigo-400 border-indigo-600 dark:border-indigo-400 hover:text-purple-600 hover:border-purple-600">
                    <svg class="fill-current h-4 w-4" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z"/></svg>
                </button>
            </div>
            
            <div id="main-nav" class="hidden w-full lg:flex lg:items-center lg:w-auto">
                <div class="text-md lg:flex-grow">
                    <a href="<?php echo SITE_URL; ?>" class="block mt-4 lg:inline-block lg:mt-0 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 mr-4">
                        Home
                    </a>
                    <a href="<?php echo SITE_URL; ?>/index.php?page=donations" class="block mt-4 lg:inline-block lg:mt-0 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 mr-4">
                        Donations
                    </a>
                    <a href="<?php echo SITE_URL; ?>/index.php?page=about" class="block mt-4 lg:inline-block lg:mt-0 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 mr-4">
                        About
                    </a>
                    <?php if (!isLoggedIn() || (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'admin')): ?>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=contact" class="block mt-4 lg:inline-block lg:mt-0 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                            Contact
                        </a>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=volunteer-events" class="block mt-4 lg:inline-block lg:mt-0 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                            Volunteer
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 lg:mt-0">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'admin'): ?>
                            <a href="<?php echo SITE_URL; ?>/index.php?page=dashboard" class="...">Dashboard</a>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/" class="...">Admin</a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=logout" class="...">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=login" class="...">Login</a>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=register" class="...">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="container mx-auto px-4 py-6 flex-grow">