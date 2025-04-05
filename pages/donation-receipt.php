<?php
// Required configuration and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Starting donation receipt page");

// Get donation ID from URL
$donationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
error_log("Donation ID: " . $donationId);

// Check if user is logged in
if (!isLoggedIn()) {
    error_log("User not logged in, redirecting to login");
    header("Location: " . SITE_URL . "/index.php?page=login&redirect=donation-receipt&id=" . $donationId);
    exit;
}

error_log("User is logged in: " . $_SESSION['user_id']);

// Fetch donation details
$conn = getDBConnection();
$sql = "SELECT d.*, c.title as campaign_title, c.description as campaign_description, 
         c.campaign_id, ch.charity_name
         FROM donations d
         JOIN campaigns c ON d.campaign_id = c.campaign_id
         JOIN charities ch ON c.charity_id = ch.charity_id
         WHERE d.donation_id = ? AND (d.user_id = ? OR ? = 1)";

$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin' ? 1 : 0;
error_log("Is admin: " . $isAdmin);

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $donationId, $_SESSION['user_id'], $isAdmin);
$stmt->execute();
$result = $stmt->get_result();

error_log("Query result rows: " . $result->num_rows);

if ($result->num_rows !== 1) {
    // Donation not found or user not authorized
    echo "<div style='background-color: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;'>
            <p>Donation not found or you don't have permission to view this receipt.</p>
          </div>";
    echo "<div style='text-align: center; margin-top: 20px;'>
            <a href='".SITE_URL."/index.php?page=dashboard' style='background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>
                Return to Dashboard
            </a>
          </div>";
    exit;
}

$donation = $result->fetch_assoc();

// Generate receipt number if not exists
if (empty($donation['receipt_number'])) {
    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad($donationId, 6, '0', STR_PAD_LEFT);
    $updateStmt = $conn->prepare("UPDATE donations SET receipt_number = ? WHERE donation_id = ?");
    $updateStmt->bind_param("si", $receiptNumber, $donationId);
    $updateStmt->execute();
    $donation['receipt_number'] = $receiptNumber;
}

// Get donor details
$userQuery = "SELECT * FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $donation['user_id']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
?>

<div style="max-width: 800px; margin: 0 auto 30px auto;">
    <div style="background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;">
        <!-- Receipt Header -->
        <div style="background-color: #5D5CDE; padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 24px; font-weight: bold; margin: 0 0 5px 0;">Donation Receipt</h1>
                <p style="margin: 0;">Thank you for your generous contribution!</p>
            </div>
            <div>
                <button onclick="window.print();" style="background-color: white; color: #5D5CDE; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                    Print Receipt
                </button>
            </div>
        </div>
        
        <!-- Receipt Content -->
        <div style="padding: 20px;">
            <!-- Organization Info -->
            <div style="display: flex; flex-wrap: wrap; margin-bottom: 30px;">
                <div style="flex: 1; min-width: 200px; margin-bottom: 15px;">
                    <h2 style="font-size: 20px; font-weight: bold; color: #1f2937; margin: 0 0 10px 0;">KindFund</h2>
                    <p style="color: #4b5563; margin: 0;">
                        123 Charity Lane<br>
                        Kathmandu, Nepal<br>
                        contact@kindfund.org
                    </p>
                </div>
                <div style="flex: 1; min-width: 200px; text-align: right;">
                    <div style="color: #4b5563; margin-bottom: 8px;">
                        <span style="font-weight: 600;">Receipt #:</span> <?php echo $donation['receipt_number']; ?>
                    </div>
                    <div style="color: #4b5563; margin-bottom: 8px;">
                        <span style="font-weight: 600;">Date:</span> <?php echo date('F j, Y', strtotime($donation['donation_date'])); ?>
                    </div>
                    <div style="color: #4b5563;">
                        <span style="font-weight: 600;">Transaction ID:</span> <?php echo $donation['transaction_id']; ?>
                    </div>
                </div>
            </div>
            
            <!-- Donor Info -->
            <div style="border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 20px 0; margin-bottom: 30px;">
                <h3 style="font-size: 18px; font-weight: bold; color: #1f2937; margin: 0 0 15px 0;">Donor Information</h3>
                
                <div style="display: flex; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px; margin-bottom: 15px;">
                        <p style="color: #4b5563; margin: 0 0 8px 0;">
                            <span style="font-weight: 600;">Name:</span> 
                            <?php echo $donation['is_anonymous'] ? 'Anonymous Donor' : htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </p>
                        <?php if (!$donation['is_anonymous']): ?>
                            <p style="color: #4b5563; margin: 0 0 8px 0;">
                                <span style="font-weight: 600;">Email:</span> <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <p style="color: #4b5563; margin: 0; text-align: right;">
                            <span style="font-weight: 600;">Donation ID:</span> #<?php echo $donation['donation_id']; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Donation Details -->
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 18px; font-weight: bold; color: #1f2937; margin: 0 0 15px 0;">Donation Details</h3>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f3f4f6;">
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Description</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Campaign</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Charity</th>
                            <th style="padding: 12px 16px; text-align: right; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 12px 16px; color: #4b5563; border-bottom: 1px solid #e5e7eb;">
                                Donation to charity campaign
                                <?php if (!empty($donation['message'])): ?>
                                    <div style="font-size: 12px; font-style: italic; margin-top: 5px;">"<?php echo htmlspecialchars($donation['message']); ?>"</div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; color: #4b5563; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($donation['campaign_title']); ?></td>
                            <td style="padding: 12px 16px; color: #4b5563; border-bottom: 1px solid #e5e7eb;"><?php echo htmlspecialchars($donation['charity_name']); ?></td>
                            <td style="padding: 12px 16px; color: #1f2937; font-weight: bold; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo formatCurrency($donation['amount']); ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f3f4f6;">
                            <td colspan="3" style="padding: 12px 16px; text-align: right; font-weight: bold; color: #1f2937;">Total Amount</td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: bold; color: #1f2937;"><?php echo formatCurrency($donation['amount']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Payment Information -->
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 18px; font-weight: bold; color: #1f2937; margin: 0 0 15px 0;">Payment Information</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 4px;">
                        <p style="color: #4b5563; margin: 0 0 8px 0;">
                            <span style="font-weight: 600;">Payment Method:</span> <?php echo ucfirst($donation['payment_method']); ?>
                        </p>
                        <p style="color: #4b5563; margin: 0 0 8px 0;">
                            <span style="font-weight: 600;">Transaction ID:</span> <?php echo $donation['transaction_id']; ?>
                        </p>
                        <p style="color: #4b5563; margin: 0;">
                            <span style="font-weight: 600;">Status:</span> <span style="color: #059669;">Completed</span>
                        </p>
                    </div>
                    
                    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 4px;">
                        <p style="color: #4b5563; margin: 0 0 8px 0;">
                            <span style="font-weight: 600;">Donation Date:</span> <?php echo date('F j, Y', strtotime($donation['donation_date'])); ?>
                        </p>
                        <p style="color: #4b5563; margin: 0;">
                            <span style="font-weight: 600;">Receipt Generated:</span> <?php echo date('F j, Y'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Thank You Note -->
            <div style="background-color: #eef2ff; padding: 20px; border-radius: 4px; text-align: center; margin-bottom: 30px;">
                <h3 style="font-size: 18px; font-weight: bold; color: #4338ca; margin: 0 0 10px 0;">Thank You for Your Support!</h3>
                <p style="color: #4f46e5; margin: 0;">
                    Your generous contribution helps us make a difference in the lives of those we serve.
                    Together, we're building a better future for all.
                </p>
            </div>
            
            <!-- Campaign Info -->
            <div style="border-top: 1px solid #e5e7eb; padding-top: 20px;">
                <h3 style="font-size: 18px; font-weight: bold; color: #1f2937; margin: 0 0 15px 0;">About the Campaign</h3>
                
                <div style="display: flex; flex-wrap: wrap;">
                    <div style="flex: 3; min-width: 200px; margin-bottom: 15px;">
                        <h4 style="font-weight: bold; color: #4b5563; margin: 0 0 8px 0;"><?php echo htmlspecialchars($donation['campaign_title']); ?></h4>
                        <p style="color: #4b5563; margin: 0 0 8px 0;">
                            <?php echo nl2br(htmlspecialchars(substr($donation['campaign_description'], 0, 200) . (strlen($donation['campaign_description']) > 200 ? '...' : ''))); ?>
                        </p>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $donation['campaign_id']; ?>" style="color: #5D5CDE; text-decoration: none;">
                            View Campaign
                        </a>
                    </div>
                    <div style="flex: 1; min-width: 150px; text-align: center; margin-top: 15px;">
                        <p style="font-size: 12px; color: #6b7280; margin: 0 0 5px 0;">Organized by</p>
                        <div style="font-weight: 500; color: #1f2937;"><?php echo htmlspecialchars($donation['charity_name']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Receipt Footer -->
        <div style="background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 14px; color: #4b5563;">
            <p style="margin: 0 0 8px 0;">
                This receipt was issued by KindFund for tax deduction purposes.<br>
                Keep this receipt for your records.
            </p>
            <p style="margin: 0;">
                If you have any questions, please contact us at <a href="mailto:support@kindfund.org" style="color: #5D5CDE; text-decoration: none;">support@kindfund.org</a>
            </p>
        </div>
    </div>
    
    <div style="margin-top: 20px; text-align: center;">
        <a href="<?php echo SITE_URL; ?>/index.php?page=dashboard" style="background-color: #6b7280; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-right: 10px;">
            Return to Dashboard
        </a>
        <button onclick="window.print();" style="background-color: #5D5CDE; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
            Print Receipt
        </button>
    </div>
</div>

<style>
    @media print {
        body {
            background-color: white;
            color: black;
        }
        div {
            max-width: none;
            margin: 0;
        }
        button, a[href] {
            display: none;
        }
    }
</style>