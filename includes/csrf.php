<?php
/**
 * CSRF Protection Functions
 */

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a CSRF token and store it in the session
     * 
     * @return string The generated CSRF token
     */
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        // Clean up old tokens (tokens older than 2 hours)
        $currentTime = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $time) {
            if ($currentTime - $time > 7200) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
        
        // Generate a new token
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time();
        
        return $token;
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate a CSRF token
     * 
     * @param string $token The token to validate
     * @return bool Whether the token is valid
     */
    function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_tokens']) || !isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        // Token is valid, remove it so it can't be reused
        unset($_SESSION['csrf_tokens'][$token]);
        
        return true;
    }
}

if (!function_exists('csrfField')) {
    /**
     * Generate a hidden input field with a CSRF token
     * 
     * @return string HTML for a hidden input field with a CSRF token
     */
    function csrfField() {
        $token = generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}