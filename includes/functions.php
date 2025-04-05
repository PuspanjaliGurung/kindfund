<?php
require_once(__DIR__ . '/../config/db.php');


/**
 * Write message to log file
 * 
 * @param string $message The message to log
 * @param string $file The log file name
 * @return bool Success or failure
 */
function writeToLog($message, $file = 'general.log') {
    $logDir = ROOT_PATH . 'logs/';
    $logPath = $logDir . $file;
    
    // Create timestamp
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Try to write to file
    $result = file_put_contents($logPath, $formattedMessage, FILE_APPEND);
    
    return $result !== false;
}






// Clean and sanitize input data
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Redirect to a specific page
function redirect($page) {
    header("Location: " . SITE_URL . "/" . $page);
    exit;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Format currency
function formatCurrency($amount) {
    if (defined('CURRENCY_SYMBOL')) {
        return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
    } else {
        return 'Rs. ' . number_format($amount, 2);
    }
}

// Format currency specifically in Nepali format
function formatNepaliCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}
// Get active campaigns
function getActiveCampaigns($limit = 6) {
    $conn = getDBConnection();
    $sql = "SELECT c.*, ch.charity_name 
            FROM campaigns c 
            JOIN charities ch ON c.charity_id = ch.charity_id 
            WHERE c.is_active = 1 AND c.end_date >= NOW() 
            ORDER BY c.creation_date DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $campaigns = [];
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    
    return $campaigns;
}

// Calculate campaign progress percentage
function calculateProgress($current, $goal) {
    if ($goal <= 0) return 0;
    $progress = ($current / $goal) * 100;
    return min(100, $progress); // Cap at 100%
}

// Get upcoming events
function getUpcomingEvents($limit = 4) {
    $conn = getDBConnection();
    $sql = "SELECT e.*, ch.charity_name 
            FROM events e 
            JOIN charities ch ON e.charity_id = ch.charity_id 
            WHERE e.is_active = 1 AND e.event_date >= NOW() 
            ORDER BY e.event_date ASC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    return $events;
}

// Recommendation algorithm function
/**
 * Update user profile information
 * 
 * @param int $userId The user ID to update
 * @param array $data Associative array of fields to update
 * @return bool True on success, false on failure
 */
function updateUserProfile($userId, $data) {
    $conn = getDBConnection();
    
    // Prepare SET clause
    $setParts = [];
    $params = [];
    $types = '';
    
    foreach ($data as $field => $value) {
        $setParts[] = "$field = ?";
        $params[] = $value;
        $types .= 's'; // All fields are strings
    }
    
    if (empty($setParts)) {
        return false;
    }
    
    $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE user_id = ?";
    $params[] = $userId;
    $types .= 'i'; // user_id is integer
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    $result = $stmt->execute();
    
    // Update session if email was changed
    if ($result && isset($data['email'])) {
        $_SESSION['email'] = $data['email'];
    }
    
    return $result;
}

function getRecommendedCampaigns($userId, $limit = 3) {
    $conn = getDBConnection();
    
    // If user is not logged in, return random campaigns
    if (!$userId) {
        $sql = "SELECT c.*, ch.charity_name 
                FROM campaigns c 
                JOIN charities ch ON c.charity_id = ch.charity_id 
                WHERE c.is_active = 1 AND c.end_date >= NOW() 
                ORDER BY RAND() 
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $campaigns = [];
        while ($row = $result->fetch_assoc()) {
            $campaigns[] = $row;
        }
        
        return $campaigns;
    }
    
    // Get categories from user's previous donations
    $sql = "SELECT DISTINCT cc.category_id
            FROM donations d
            JOIN campaigns c ON d.campaign_id = c.campaign_id
            JOIN campaign_categories cc ON c.campaign_id = cc.campaign_id
            WHERE d.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category_id'];
    }
    
    // If no previous donations, return random campaigns
    if (empty($categories)) {
        $sql = "SELECT c.*, ch.charity_name 
                FROM campaigns c 
                JOIN charities ch ON c.charity_id = ch.charity_id 
                WHERE c.is_active = 1 AND c.end_date >= NOW() 
                ORDER BY RAND() 
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $campaigns = [];
        while ($row = $result->fetch_assoc()) {
            $campaigns[] = $row;
        }
        
        return $campaigns;
    }
    
    // Get campaigns that match user's interests (categories)
    $placeholders = str_repeat('?,', count($categories) - 1) . '?';
    $sql = "SELECT DISTINCT c.*, ch.charity_name 
            FROM campaigns c 
            JOIN charities ch ON c.charity_id = ch.charity_id 
            JOIN campaign_categories cc ON c.campaign_id = cc.campaign_id
            WHERE c.is_active = 1 AND c.end_date >= NOW() 
            AND cc.category_id IN ($placeholders)
            AND c.campaign_id NOT IN (
                SELECT campaign_id FROM donations WHERE user_id = ?
            )
            ORDER BY c.creation_date DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    
    // Dynamically bind parameters
    $types = str_repeat('i', count($categories) + 2);
    $params = array_merge($categories, [$userId, $limit]);
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $campaigns = [];
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    
    // If not enough recommendations, fill with random campaigns
    if (count($campaigns) < $limit) {
        $remaining = $limit - count($campaigns);
        
        // Get existing campaign IDs to exclude
        $existingIds = array_column($campaigns, 'campaign_id');
        $excludeIds = empty($existingIds) ? "" : "AND c.campaign_id NOT IN (" . implode(',', $existingIds) . ")";
        
        $sql = "SELECT c.*, ch.charity_name 
                FROM campaigns c 
                JOIN charities ch ON c.charity_id = ch.charity_id 
                WHERE c.is_active = 1 AND c.end_date >= NOW() 
                $excludeIds
                ORDER BY RAND() 
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $remaining);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $campaigns[] = $row;
        }
    }
    
    return $campaigns;
}