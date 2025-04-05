<?php

// Admin authentication check
function isAdmin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';


}

// Redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        // Ensure SITE_URL is defined in your config.php
        header("Location: " . SITE_URL . "/index.php?page=login&redirect=admin&error=access_denied");
        exit;
    }
}
?>
