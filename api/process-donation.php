<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/khalti-config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to donate']);
    exit;
}

// Get form data
$campaignId = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
$isAnonymous = isset($_POST['anonymous']) ? 1 : 0;

// Validate data
if ($campaignId <= 0 || $amount <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid donation data']);
    exit;
}

// Get campaign details for payment description
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT c.title, ch.charity_name FROM campaigns c JOIN charities ch ON c.charity_id = ch.charity_id WHERE c.campaign_id = ?");
$stmt->bind_param("i", $campaignId);
$stmt->execute();
$result = $stmt->get_result();
$campaign = $result->fetch_assoc();

// Generate a unique purchase order ID
$purchaseOrderId = 'KF-' . $campaignId . '-' . time() . '-' . $_SESSION['user_id'];

// Store in session for later reference
$_SESSION['khalti_payment'] = [
    'campaign_id' => $campaignId,
    'amount' => $amount,
    'user_id' => $_SESSION['user_id'],
    'message' => $message,
    'is_anonymous' => $isAnonymous,
    'purchase_order_id' => $purchaseOrderId,
    'timestamp' => time()
];

// Prepare the payload for Khalti payment initiation
$payload = [
    'return_url' => KHALTI_RETURN_URL,
    'website_url' => KHALTI_WEBSITE_URL,
    'amount' => intval($amount * 100), // Convert to paisa
    'purchase_order_id' => $purchaseOrderId,
    'purchase_order_name' => 'Donation to ' . $campaign['title'],
    'customer_info' => [
        'name' => $_SESSION['username'],
        'email' => '', // Add email if available
        'phone' => '' // Add phone if available
    ]
];

// User data if available
$userStmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$userResult = $userStmt->get_result();
if ($userResult->num_rows > 0) {
    $user = $userResult->fetch_assoc();
    if (!empty($user['email'])) {
        $payload['customer_info']['email'] = $user['email'];
    }
}

// Optional amount breakdown
$payload['amount_breakdown'] = [
    [
        'label' => 'Donation Amount',
        'amount' => intval($amount * 100)
    ]
];

// Optional product details
$payload['product_details'] = [
    [
        'identity' => strval($campaignId),
        'name' => $campaign['title'],
        'total_price' => intval($amount * 100),
        'quantity' => 1,
        'unit_price' => intval($amount * 100)
    ]
];

// Log the request payload for debugging
error_log("Khalti payment initiation payload: " . json_encode($payload));

// Send the payment initiation request to Khalti
$ch = curl_init(KHALTI_API_ENDPOINT . 'epayment/initiate/');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . KHALTI_SECRET_KEY,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log the response for debugging
error_log("Khalti payment initiation response ($statusCode): " . $response);

$responseData = json_decode($response, true);

header('Content-Type: application/json');
if ($statusCode === 200 && isset($responseData['payment_url'])) {
    // Store the pidx in session for verification later
    $_SESSION['khalti_payment']['pidx'] = $responseData['pidx'];
    
    echo json_encode([
        'success' => true,
        'redirect_url' => $responseData['payment_url']
    ]);
} else {
    $errorMessage = isset($responseData['detail']) ? $responseData['detail'] : 'Payment initialization failed';
    if (isset($responseData['error_key'])) {
        $errorMessage .= ' (' . $responseData['error_key'] . ')';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'response' => $responseData
    ]);
}
exit;