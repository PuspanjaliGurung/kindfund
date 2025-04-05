</main>
    </div>
    
    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        
        darkModeToggle.addEventListener('click', function() {
            document.documentElement.classList.toggle('dark');
            
            // Save preference
            const isDarkMode = document.documentElement.classList.contains('dark');
            localStorage.setItem('darkMode', isDarkMode ? 'true' : 'false');
        });
        
        // Check for dark mode preference
        if (localStorage.getItem('darkMode') === 'true' || 
            (localStorage.getItem('darkMode') === null && 
             window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</body>
</html>