<?php

require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/functions.php';

/**
 * Register a new user
 */
function registerUser($username, $email, $password, $firstName, $lastName, $userType = 'donor') {
    $conn = getDBConnection();
    
    // Check if username or email already exists
    $checkSql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        return [
            'success' => false,
            'message' => 'Username or email already exists'
        ];
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $sql = "INSERT INTO users (username, email, password, first_name, last_name, user_type) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $username, $email, $hashedPassword, $firstName, $lastName, $userType);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'user_id' => $stmt->insert_id,
            'message' => 'Registration successful'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Registration failed: ' . $stmt->error
        ];
    }
}

/**
 * Authenticate a user
 */
function loginUser($username, $password) {
    $conn = getDBConnection();
    
    // Get user by username or email
    $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['user_id']; // Set user_id in session
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];  // Ensure user type is set
        $_SESSION['logged_in'] = true;

        // Debugging: Log session values
        error_log("User logged in: " . $_SESSION['username']);
        error_log("Logged in status: " . $_SESSION['logged_in']);
        error_log("User type: " . $_SESSION['user_type']);
        error_log("Session ID: " . session_id()); // Log session ID for debugging

        
        return [
            'success' => true,
            'user' => $user,
            'message' => 'Login successful'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
}

/**
 * Update user profile
 */
function updateProfile($userId, $firstName, $lastName, $email, $profileImage = null) {
    $conn = getDBConnection();
    
    // Check if email already exists for another user
    $checkSql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $email, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        return [
            'success' => false,
            'message' => 'Email already exists for another user'
        ];
    }
    
    // Update profile
    if ($profileImage) {
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_image = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $firstName, $lastName, $email, $profileImage, $userId);
    } else {
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $firstName, $lastName, $email, $userId);
    }
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Profile updated successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Profile update failed: ' . $stmt->error
        ];
    }
}

/**
 * Change user password
 */
function changePassword($userId, $currentPassword, $newPassword) {
    $conn = getDBConnection();
    
    // Get current user data
    $sql = "SELECT password FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        return [
            'success' => false,
            'message' => 'User not found'
        ];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        return [
            'success' => false,
            'message' => 'Current password is incorrect'
        ];
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateSql = "UPDATE users SET password = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $hashedPassword, $userId);
    
    if ($updateStmt->execute()) {
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Password change failed: ' . $updateStmt->error
        ];
    }
}
