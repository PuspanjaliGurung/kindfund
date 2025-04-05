<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$message = '';
$messageType = '';
$certificate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $certificateCode = sanitize($_POST['certificate_code']);
    
    if (empty($certificateCode)) {
        $message = 'Please enter a certificate code';
        $messageType = 'error';
    } else {
        // Query the database for the certificate
        $conn = getDBConnection();
        $query = "
            SELECT vr.*, ve.title as event_title, ve.event_date, ve.location, 
                  u.first_name, u.last_name
            FROM volunteer_registrations vr
            JOIN volunteer_events ve ON vr.event_id = ve.event_id
            JOIN users u ON vr.user_id = u.user_id
            WHERE vr.certificate_code = ? AND vr.status = 'attended'
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $certificateCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $certificate = $result->fetch_assoc();
            $message = 'Certificate verified successfully!';
            $messageType = 'success';
        } else {
            $message = 'Invalid certificate code. Please check and try again.';
            $messageType = 'error';
        }
    }
}

// Include the header
include 'includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h1 style="font-size: 28px; font-weight: bold; margin-bottom: 20px; color: #1f2937; text-align: center;">
        Verify Volunteer Certificate
    </h1>
    
    <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 30px;">
        <p style="margin-bottom: 20px; color: #4b5563; text-align: center;">
            Enter the certificate code to verify the authenticity of a volunteer certificate.
        </p>
        
        <form method="POST" action="" style="max-width: 400px; margin: 0 auto;">
            <div style="margin-bottom: 20px;">
                <label for="certificate_code" style="display: block; margin-bottom: 5px; font-weight: bold; color: #1f2937;">
                    Certificate Code
                </label>
                <input type="text" id="certificate_code" name="certificate_code" placeholder="e.g. CERT-ABC123DEF" 
                    style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 4px;" required>
            </div>
            
            <button type="submit" name="verify" style="width: 100%; background-color: #5D5CDE; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                Verify Certificate
            </button>
        </form>
    </div>
    
    <?php if ($message): ?>
        <div style="margin-bottom: 20px; padding: 15px; border-radius: 4px; 
            <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>">
            <p style="margin: 0; text-align: center;"><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($certificate): ?>
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 25px;">
            <h2 style="font-size: 22px; font-weight: bold; margin-bottom: 20px; color: #1f2937; text-align: center;">
                Certificate Information
            </h2>
            
            <div style="border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                <div style="background-color: #f3f4f6; padding: 12px 16px; border-bottom: 1px solid #d1d5db;">
                    <h3 style="margin: 0; font-size: 18px; color: #1f2937;">Certificate Details</h3>
                </div>
                
                <div style="padding: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <p style="margin: 0 0 5px 0; font-weight: bold; color: #4b5563;">Certificate Code:</p>
                            <p style="margin: 0; color: #1f2937;"><?php echo $certificate['certificate_code']; ?></p>
                        </div>
                        
                        <div>
                            <p style="margin: 0 0 5px 0; font-weight: bold; color: #4b5563;">Issue Date:</p>
                            <p style="margin: 0; color: #1f2937;"><?php echo date('F j, Y', strtotime($certificate['certificate_date'])); ?></p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <p style="margin: 0 0 5px 0; font-weight: bold; color: #4b5563;">Volunteer Name:</p>
                        <p style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?></p>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <p style="margin: 0 0 5px 0; font-weight: bold; color: #4b5563;">Event:</p>
                        <p style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($certificate['event_title']); ?></p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <p style="margin: 0 0 5px 0; font-weight: bold; color: #4b5563;">Event Date:</p>
                            <p style="margin: 0; color: #1f2937;"><?php echo date('F j, Y', strtotime($certificate['event_date'])); ?></p>
                        </div>
                        
                        <div>
                            <p style="margin: 0 0 5px 0; font-weight: bold; color: #4b5563;">Location:</p>
                            <p style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($certificate['location']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="<?php echo SITE_URL; ?>/volunteer-certificate.php?code=<?php echo $certificate['certificate_code']; ?>" style="display: inline-block; background-color: #10b981; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">
                    View Full Certificate
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Include the footer
include 'includes/footer.php';
?>