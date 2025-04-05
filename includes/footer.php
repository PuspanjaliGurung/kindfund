</main>
    
    <footer class="bg-white dark:bg-gray-800 shadow-inner py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-wrap justify-between">
                <div class="w-full md:w-1/4 mb-6 md:mb-0">
                    <h3 class="text-lg font-bold mb-4 text-indigo-600 dark:text-indigo-400">Kind<span class="text-purple-600 dark:text-purple-400">Fund</span></h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                        Making a difference through technology. KindFund provides a powerful platform for charities to connect with donors and make a positive impact.
                    </p>
                </div>
                
                <div class="w-full md:w-1/4 mb-6 md:mb-0">
                    <h3 class="text-md font-bold mb-4 text-gray-700 dark:text-gray-300">Quick Links</h3>
                    <ul class="text-sm">
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">Home</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/index.php?page=donations" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">Donations</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/index.php?page=events" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">Events</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/index.php?page=about" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">About</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/index.php?page=contact" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">Contact</a></li>
                    </ul>
                </div>
                
                <div class="w-full md:w-1/4 mb-6 md:mb-0">
                    <h3 class="text-md font-bold mb-4 text-gray-700 dark:text-gray-300">Connect With Us</h3>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <div class="w-full md:w-1/4">
                    <h3 class="text-md font-bold mb-4 text-gray-700 dark:text-gray-300">Newsletter</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">Subscribe to our newsletter for the latest updates</p>
                    <form>
                        <div class="flex flex-wrap">
                            <input type="email" placeholder="Your email" class="text-base w-full md:w-2/3 px-3 py-2 border rounded-l text-gray-700 dark:text-gray-300 focus:outline-none" required>
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-r">
                                Subscribe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700 mt-8 pt-8 text-center text-sm text-gray-600 dark:text-gray-400">
                <p>© <?php echo date('Y'); ?> KindFund. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('main-nav').classList.toggle('hidden');
        });
        
        // Dark mode detection
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            if (event.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
    </script>
</body>
</html>