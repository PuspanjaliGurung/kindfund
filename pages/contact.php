<?php
// Initialize message variables
$contactSuccess = false;
$contactError = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $contactError = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter a valid email address';
    } else {
        // In a real application, you would send an email or save to database
        // For now, just set success message
        $contactSuccess = true;
        
        // Clear form data after successful submission
        $name = $email = $subject = $message = '';
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-800 dark:text-gray-200 mb-4">Contact Us</h1>
        <p class="text-gray-600 dark:text-gray-400 text-lg">
            Have questions or need assistance? We're here to help.
        </p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center">
            <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-400 mx-auto mb-4">
                <i class="fas fa-envelope text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">Email</h3>
            <p class="text-gray-600 dark:text-gray-400">
                <a href="mailto:info@kindfund.org" class="hover:text-indigo-600 dark:hover:text-indigo-400">info@kindfund.org</a>
            </p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center">
            <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center text-purple-600 dark:text-purple-400 mx-auto mb-4">
                <i class="fas fa-phone text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">Phone</h3>
            <p class="text-gray-600 dark:text-gray-400">
                <a href="tel:+9779812345678" class="hover:text-indigo-600 dark:hover:text-indigo-400">+977 98-1234-5678</a>
            </p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center">
            <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center text-green-600 dark:text-green-400 mx-auto mb-4">
                <i class="fas fa-map-marker-alt text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">Location</h3>
            <p class="text-gray-600 dark:text-gray-400">
                Thapathali, Kathmandu<br>Nepal
            </p>
        </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-8">
        <div class="p-6 md:p-8">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-6">Send Us a Message</h2>
            
            <?php if ($contactSuccess): ?>
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-6">
                    <p class="font-bold">Message sent successfully!</p>
                    <p>Thank you for contacting us. We'll respond to your inquiry as soon as possible.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($contactError): ?>
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-6">
                    <p><?php echo $contactError; ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" data-validate="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Your Name</label>
                        <input type="text" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Your Email</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="subject" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Subject</label>
                    <input type="text" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required>
                </div>
                
                <div class="mb-6">
                    <label for="message" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Message</label>
                    <textarea id="message" name="message" rows="5" class="w-full px-3 py-2 text-base border rounded-lg text-gray-700 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-indigo-500" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                </div>
                
                <button type="submit" name="contact_submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300">
                    Send Message
                </button>
            </form>
        </div>
    </div>
    

</div>