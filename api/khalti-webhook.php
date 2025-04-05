<?php
require_once '../config/config.php';
require_once '../config/khalti-config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/khalti-payment.php';

// Get the payload
$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_KHALTI_SIGNATURE']) ? $_SERVER['HTTP_KHALTI_SIGNATURE'] : '';

// In production, verify the signature
// This is a simplified example - in production you need more robust security checks
if (empty($payload) || empty($signature)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook payload']);
    exit;
}

// Parse the payload
$data = json_decode($payload, true);

if (!isset($data['event']) || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook data format']);
    exit;
}

// Log the webhook for debugging
$logFile = fopen('../logs/khalti_webhook.log', 'a');
fwrite($logFile, date('Y-m-d H:i:s') . ' - Webhook received: ' . $payload . PHP_EOL);
fclose($logFile);

// Handle different events
switch ($data['event']) {
    case 'payment_verification_success':
        handlePaymentSuccess($data['data']);
        break;
        
    case 'payment_verification_failed':
        handlePaymentFailure($data['data']);
        break;
        
    default:
        // Unhandled event type
        http_response_code(200); // Acknowledge receipt even for unhandled events
        echo json_encode(['status' => 'success', 'message' => 'Event acknowledged but not processed']);
        exit;
}

/**
 * Handle successful payment verification
 */
function handlePaymentSuccess($paymentData) {
    $conn = getDBConnection();
    $transactionId = $paymentData['transaction_id'] ?? '';
    
    // Check if payment has already been processed
    $checkSql = "SELECT donation_id FROM donations WHERE transaction_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $transactionId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Already processed, just acknowledge
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment already processed']);
        exit;
    }
    
    // Get payment details from session or database
    // In a real application, you'd store pending payments in the database
    if (!isset($_SESSION['khalti_payment']) || $_SESSION['khalti_payment']['transaction_id'] !== $transactionId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Payment session not found']);
        exit;
    }
    
    $paymentInfo = $_SESSION['khalti_payment'];
    
    // Process the payment
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
    
    if ($insertStmt->execute()) {
        $donationId = $insertStmt->insert_id;
        
        // Update campaign's current amount
        $updateSql = "UPDATE campaigns SET current_amount = current_amount + ? WHERE campaign_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("di", $paymentInfo['amount'], $paymentInfo['campaign_id']);
        $updateStmt->execute();
        
        // Clear the payment session
        unset($_SESSION['khalti_payment']);
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully', 'donation_id' => $donationId]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to process payment']);
        exit;
    }
}

/**
 * Handle failed payment verification
 */
function handlePaymentFailure($paymentData) {
    // Log the failure
    $logFile = fopen('../logs/khalti_errors.log', 'a');
    fwrite($logFile, date('Y-m-d H:i:s') . ' - Payment verification failed: ' . json_encode($paymentData) . PHP_EOL);
    fclose($logFile);
    
    // Acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Payment failure logged']);
    exit;
}