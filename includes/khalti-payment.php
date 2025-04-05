<?php
require_once '../config/config.php';
require_once '../config/khalti-config.php';
require_once '../config/db.php';
require_once 'functions.php';

/**
 * Initialize Khalti payment
 * 
 * @param float $amount Amount in NPR (will be converted to paisa - 1 NPR = 100 paisa)
 * @param string $campaignId The campaign ID
 * @param string $userId The user ID
 * @param string $message Donation message (optional)
 * @param bool $isAnonymous Whether the donation is anonymous
 * @return array Payment initialization data
 */
function initializeKhaltiPayment($amount, $campaignId, $userId, $message = '', $isAnonymous = false) {
    // Store the payment information in a temporary payment record
    $conn = getDBConnection();
    
    // Generate a unique transaction ID
    $transactionId = 'KF' . time() . rand(1000, 9999);
    
    // Store donation info in session for later reference
    $_SESSION['khalti_payment'] = [
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'campaign_id' => $campaignId,
        'user_id' => $userId,
        'message' => $message,
        'is_anonymous' => $isAnonymous,
        'timestamp' => time()
    ];
    
    // Log the payment initialization
    writeToLog("Payment initialization - Amount: $amount, Campaign: $campaignId, User: $userId, Transaction: $transactionId", 'khalti_payments.log');
    
    // Amount in paisa (Khalti accepts amount in paisa - 1 NPR = 100 paisa)
    $amountInPaisa = $amount * 100;
    
    // Get campaign details for payment description
    $stmt = $conn->prepare("SELECT title FROM campaigns WHERE campaign_id = ?");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $result = $stmt->get_result();
    $campaign = $result->fetch_assoc();
    
    $paymentData = [
        'publicKey' =>  "36385bdf45b543cd9016ee07393ae895",
        'amount' => $amountInPaisa,
        'productIdentity' => $campaignId,
        'productName' => 'Donation: ' . $campaign['title'],
        'productUrl' => SITE_URL . '/index.php?page=campaign&id=' . $campaignId,
        'transactionId' => $transactionId,
        'returnUrl' => KHALTI_RETURN_URL,
        'webhookUrl' => SITE_URL . '/api/khalti-webhook.php'
    ];
    
    // For customer info - if available
    if ($userId) {
        $userStmt = $conn->prepare("SELECT first_name, last_name, email, username FROM users WHERE user_id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            $paymentData['customerInfo'] = [
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'customerReference' => $user['username']
            ];
        }
    }
    
    return $paymentData;
}

/**
 * Verify Khalti payment
 * 
 * @param string $token Payment token from Khalti
 * @param float $amount Amount in paisa
 * @return array Verification result
 */
function verifyKhaltiPayment($token, $amount) {
    // Make sure we're using the test API endpoint when in test mode
    $url = KHALTI_API_ENDPOINT . 'payment/verify/';
    
    $payload = [
        'token' => $token,
        'amount' => $amount
    ];
    
    // Log verification attempt
    writeToLog("Payment verification attempt - Token: $token, Amount: $amount", 'khalti_payments.log');
    
    // Log the payload being sent to Khalti
    writeToLog("Payload sent to Khalti: " . json_encode($payload), 'khalti_payments.log');
    
    // Setup cURL request with Khalti API authorization
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . KHALTI_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log cURL errors if any
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        writeToLog("cURL Error: $curlError", 'khalti_payments.log');
    }
    
    // Log the raw response for debugging
    writeToLog("Payment verification response - Status: $statusCode, Response: $response", 'khalti_payments.log');
    
    curl_close($ch);
    
    return [
        'status' => $statusCode === 200,
        'data' => json_decode($response, true),
    ];
}

/**
 * Process a successful Khalti payment
 * 
 * @param array $paymentData Payment verification data from Khalti
 * @return array Processing result
 */
function processSuccessfulKhaltiPayment($paymentData) {
    if (!isset($_SESSION['khalti_payment'])) {
        writeToLog("Payment processing failed - Session data not found", 'khalti_payments.log');
        return [
            'success' => false,
            'message' => 'Payment session not found'
        ];
    }
    
    $paymentInfo = $_SESSION['khalti_payment'];
    $conn = getDBConnection();
    
    // Log the payment processing attempt
    writeToLog("Processing payment - Transaction ID: {$paymentInfo['transaction_id']}, Amount: {$paymentInfo['amount']}", 'khalti_payments.log');
    
    // Check if payment has already been processed
    $checkSql = "SELECT donation_id FROM donations WHERE transaction_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $paymentInfo['transaction_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        writeToLog("Payment already processed - Transaction ID: {$paymentInfo['transaction_id']}", 'khalti_payments.log');
        return [
            'success' => true,
            'message' => 'Payment already processed',
            'donation_id' => $checkResult->fetch_assoc()['donation_id']
        ];
    }
    
    // Insert donation record
    $insertSql = "INSERT INTO donations (user_id, campaign_id, amount, donation_date, payment_method, transaction_id, is_anonymous, message) 
                  VALUES (?, ?, ?, NOW(), 'khalti', ?, ?, ?)";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param(
        "iddsss", 
        $paymentInfo['user_id'], 
        $paymentInfo['campaign_id'], 
        $paymentInfo['amount'], 
        $paymentInfo['transaction_id'],
        $paymentInfo['is_anonymous'],
        $paymentInfo['message']
    );
    
    $success = $insertStmt->execute();
    
    if ($success) {
        $donationId = $insertStmt->insert_id;
        
        // Update campaign's current amount
        $updateSql = "UPDATE campaigns SET current_amount = current_amount + ? WHERE campaign_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("di", $paymentInfo['amount'], $paymentInfo['campaign_id']);
        $updateStmt->execute();
        
        // Log the successful payment processing
        writeToLog("Payment processed successfully - Donation ID: $donationId, Transaction ID: {$paymentInfo['transaction_id']}", 'khalti_payments.log');
        
        // Clear the payment session
        unset($_SESSION['khalti_payment']);
        
        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'donation_id' => $donationId
        ];
    } else {
        writeToLog("Payment processing failed - SQL Error: {$insertStmt->error}", 'khalti_payments.log');
        return [
            'success' => false,
            'message' => 'Failed to process payment: ' . $insertStmt->error
        ];
    }
}

/**
 * Format amount in Nepali Rupees
 * 
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function formatNepaliCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

/**
 * Write message to log file
 * 
 * @param string $message The message to log
 * @param string $file The log file name
 * @return bool Success or failure
 */
function writeToLog($message, $file = 'general.log') {
    $logDir = ROOT_PATH . 'logs/';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logPath = $logDir . $file;
    
    // Create timestamp
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Try to write to file
    $result = file_put_contents($logPath, $formattedMessage, FILE_APPEND);
    
    return $result !== false;
}