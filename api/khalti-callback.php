<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/khalti-config.php';
require_once '../includes/functions.php';

// Log the callback parameters for debugging
error_log("Khalti callback received: " . json_encode($_GET));

// Get parameters from callback
$pidx = isset($_GET['pidx']) ? $_GET['pidx'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$transactionId = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';
$amount = isset($_GET['amount']) ? intval($_GET['amount']) : 0;
$purchaseOrderId = isset($_GET['purchase_order_id']) ? $_GET['purchase_order_id'] : '';

// Verify the payment using lookup API
$ch = curl_init(KHALTI_API_ENDPOINT . 'epayment/lookup/');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pidx' => $pidx]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . KHALTI_SECRET_KEY,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log the verification response
error_log("Khalti payment verification response ($statusCode): " . $response);

$verificationData = json_decode($response, true);

// Check if payment was successful
if ($statusCode === 200 && isset($verificationData['status']) && $verificationData['status'] === 'Completed') {
    // Get the payment info from session
    $paymentInfo = $_SESSION['khalti_payment'] ?? [];
    
    if (empty($paymentInfo)) {
        error_log("Payment session not found for pidx: $pidx");
        header("Location: " . SITE_URL . "/index.php?page=donations&payment_status=error&message=" . urlencode("Payment session not found"));
        exit;
    }
    
    // Verify the purchase order ID matches
    if ($purchaseOrderId !== $paymentInfo['purchase_order_id']) {
        error_log("Purchase order ID mismatch: Expected {$paymentInfo['purchase_order_id']}, got $purchaseOrderId");
        header("Location: " . SITE_URL . "/index.php?page=donations&payment_status=error&message=" . urlencode("Purchase order ID mismatch"));
        exit;
    }
    
    $conn = getDBConnection();
    
    // Check if payment has already been processed
    $checkSql = "SELECT donation_id FROM donations WHERE transaction_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $transactionId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Payment already processed
        $donationId = $checkResult->fetch_assoc()['donation_id'];
        header("Location: " . SITE_URL . "/index.php?page=payment-success&donation_id=" . $donationId);
        exit;
    }
    
    // Insert donation record
    $insertSql = "INSERT INTO donations (user_id, campaign_id, amount, donation_date, payment_method, transaction_id, is_anonymous, message) 
                  VALUES (?, ?, ?, NOW(), 'khalti', ?, ?, ?)";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param(
        "idssss", 
        $paymentInfo['user_id'], 
        $paymentInfo['campaign_id'], 
        $paymentInfo['amount'], 
        $transactionId,
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
        
        // Redirect to success page
        header("Location: " . SITE_URL . "/index.php?page=payment-success&donation_id=" . $donationId);
        exit;
    } else {
        error_log("Failed to insert donation: " . $insertStmt->error);
        header("Location: " . SITE_URL . "/index.php?page=donations&payment_status=error&message=" . urlencode("Failed to process payment"));
        exit;
    }
} else {
    // Payment failed or pending
    $errorMessage = "Payment was not completed. Status: " . ($verificationData['status'] ?? 'Unknown');
    error_log("Payment verification failed: " . $errorMessage);
    header("Location: " . SITE_URL . "/index.php?page=donations&payment_status=error&message=" . urlencode($errorMessage));
    exit;
}