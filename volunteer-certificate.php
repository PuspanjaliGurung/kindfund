<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Get certificate code from URL
$certificateCode = isset($_GET['code']) ? sanitize($_GET['code']) : '';

if (empty($certificateCode)) {
    header("Location: " . SITE_URL);
    exit;
}

// Get certificate details
$conn = getDBConnection();
$query = "
    SELECT vr.*, ve.title as event_title, ve.event_date, ve.location, 
           u.first_name, u.last_name, u.email
    FROM volunteer_registrations vr
    JOIN volunteer_events ve ON vr.event_id = ve.event_id
    JOIN users u ON vr.user_id = u.user_id
    WHERE vr.certificate_code = ? AND vr.status = 'attended'
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $certificateCode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Certificate not found or not valid
    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Certificate Not Found - KindFund</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f3f4f6;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .container {
                max-width: 400px;
                width: 100%;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .header {
                background-color: #ef4444;
                padding: 20px;
                color: white;
            }
            .content {
                padding: 20px;
            }
            .button {
                display: inline-block;
                background-color: #5D5CDE;
                color: white;
                padding: 8px 16px;
                text-decoration: none;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 24px;'>Certificate Not Found</h1>
            </div>
            <div class='content'>
                <p style='color: #4b5563; margin-bottom: 20px;'>The certificate you are looking for does not exist or is not valid.</p>
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "' class='button'>
                        Return to Homepage
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

$certificate = $result->fetch_assoc();

// Format the certificate date
$formattedDate = date('F j, Y', strtotime($certificate['certificate_date'] ?: date('Y-m-d')));
$eventDate = date('F j, Y', strtotime($certificate['event_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Certificate - KindFund</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .controls {
            margin-bottom: 20px;
        }
        
        .certificate {
            background-color: white;
            border: 4px solid #5D5CDE;
            padding: 15px;
            max-width: 800px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        
        .certificate-inner {
            border: 2px dashed #5D5CDE;
            padding: 30px;
            text-align: center;
        }
        
        .certificate-title {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .certificate-subtitle {
            color: #6b7280;
            margin-bottom: 30px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
        }
        
        .divider-line {
            height: 1px;
            width: 100px;
            background-color: #d1d5db;
        }
        
        .divider-icon {
            margin: 0 10px;
            color: #5D5CDE;
        }
        
        .recipient {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin: 20px 0;
        }
        
        .event-title {
            font-size: 18px;
            color: #5D5CDE;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-around;
            margin: 40px 0;
        }
        
        .signature {
            width: 200px;
            text-align: center;
        }
        
        .signature-line {
            width: 100%;
            height: 1px;
            background-color: #6b7280;
            margin: 10px 0;
        }
        
        .footer {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6b7280;
            margin-top: 20px;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .controls, .footer-text {
                display: none;
            }
            
            .certificate {
                box-shadow: none;
                border: 2px solid #5D5CDE;
                max-width: 100%;
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <a href="<?php echo SITE_URL; ?>" style="color: #5D5CDE; text-decoration: none; margin-right: 20px;">
            ← Return to Homepage
        </a>
        <button onclick="window.print();" style="background-color: #5D5CDE; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
            Print Certificate
        </button>
    </div>
    
    <div class="certificate">
        <div class="certificate-inner">
            <!-- Header -->
            <div class="certificate-title">Certificate of Appreciation</div>
            <div class="certificate-subtitle">For volunteer service with KindFund</div>
            
            <!-- Divider -->
            <div class="divider">
                <div class="divider-line"></div>
                <div class="divider-icon">✦</div>
                <div class="divider-line"></div>
            </div>
            
            <!-- Main Content -->
            <div style="margin: 30px 0;">
                <p style="color: #4b5563;">This certificate is presented to</p>
                <div class="recipient"><?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?></div>
                
                <p style="color: #4b5563; margin: 20px 0;">
                    for outstanding volunteer service at the event<br>
                    <span class="event-title">"<?php echo htmlspecialchars($certificate['event_title']); ?>"</span><br>
                    held on <?php echo $eventDate; ?> at <?php echo htmlspecialchars($certificate['location']); ?>
                </p>
                
                <p style="color: #4b5563;">
                    Your dedication, compassion, and service have made a significant difference<br>
                    in our community and to those we serve. Thank you for your valuable contribution.
                </p>
            </div>
            
            <!-- Signatures -->
            <div class="signature-section">
                <div class="signature">
                    <div style="height: 60px; display: flex; align-items: flex-end; justify-content: center;">
                        <span style="font-style: italic; font-size: 24px;">John Doe</span>
                    </div>
                    <div class="signature-line"></div>
                    <p style="margin: 5px 0; font-weight: bold; color: #1f2937;">John Doe</p>
                    <p style="margin: 0; font-size: 14px; color: #6b7280;">Executive Director</p>
                </div>
                
                <div class="signature">
                    <div style="height: 60px; display: flex; align-items: flex-end; justify-content: center;">
                        <span style="font-style: italic; font-size: 24px;">Jane Smith</span>
                    </div>
                    <div class="signature-line"></div>
                    <p style="margin: 5px 0; font-weight: bold; color: #1f2937;">Jane Smith</p>
                    <p style="margin: 0; font-size: 14px; color: #6b7280;">Volunteer Coordinator</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div>
                    <p style="margin: 0;">Certificate Code: <?php echo $certificate['certificate_code']; ?></p>
                    <p style="margin: 0;">Issued on: <?php echo $formattedDate; ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0;">Verify at: <?php echo SITE_URL; ?>/verify-certificate.php</p>
                    <p style="margin: 0; color: #5D5CDE; font-weight: bold;">KindFund</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-text" style="color: #6b7280; text-align: center; font-size: 14px;">
        <p>This certificate is issued by KindFund as recognition of volunteer service.</p>
        <p>If you have any questions, please contact <a href="mailto:support@kindfund.org" style="color: #5D5CDE; text-decoration: none;">support@kindfund.org</a></p>
    </div>
</body>
</html>