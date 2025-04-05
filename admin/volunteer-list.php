<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

// Set page title
$pageTitle = "Volunteer List";

$conn = getDBConnection();
$message = '';
$messageType = '';

// Get event ID
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($eventId === 0) {
    header("Location: volunteering.php");
    exit;
}

// Get event details
$eventStmt = $conn->prepare("SELECT * FROM volunteer_events WHERE event_id = ?");
$eventStmt->bind_param("i", $eventId);
$eventStmt->execute();
$eventResult = $eventStmt->get_result();

if ($eventResult->num_rows === 0) {
    header("Location: volunteering.php");
    exit;
}

$event = $eventResult->fetch_assoc();

// Handle form submission for status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update volunteer status
    if (isset($_POST['update_status'])) {
        $registrationId = intval($_POST['registration_id']);
        $status = sanitize($_POST['status']);
        
        $validStatuses = ['pending', 'approved', 'rejected', 'attended', 'no_show'];
        if (in_array($status, $validStatuses)) {
            $stmt = $conn->prepare("UPDATE volunteer_registrations SET status = ? WHERE registration_id = ?");
            $stmt->bind_param("si", $status, $registrationId);
            
            if ($stmt->execute()) {
                $message = 'Volunteer status updated successfully';
                $messageType = 'success';
                
                // If status is 'attended', generate certificate if it doesn't exist
                if ($status === 'attended') {
                    $certQuery = "SELECT certificate_code FROM volunteer_registrations WHERE registration_id = ?";
                    $certStmt = $conn->prepare($certQuery);
                    $certStmt->bind_param("i", $registrationId);
                    $certStmt->execute();
                    $certResult = $certStmt->get_result();
                    $certData = $certResult->fetch_assoc();
                    
                    if (empty($certData['certificate_code'])) {
                        // Generate unique certificate code
                        $certificateCode = 'CERT-' . strtoupper(substr(md5($registrationId . time()), 0, 10));
                        
                        // Update registration with certificate code
                        $updateCertStmt = $conn->prepare("
                            UPDATE volunteer_registrations 
                            SET certificate_code = ?, certificate_date = NOW() 
                            WHERE registration_id = ?
                        ");
                        $updateCertStmt->bind_param("si", $certificateCode, $registrationId);
                        $updateCertStmt->execute();
                    }
                }
            } else {
                $message = 'Error updating volunteer status: ' . $stmt->error;
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid status';
            $messageType = 'error';
        }
    }
    
    // Generate certificates for selected volunteers
    if (isset($_POST['generate_certificates']) && isset($_POST['selected_volunteers'])) {
        $selectedIds = $_POST['selected_volunteers'];
        $successCount = 0;
        
        foreach ($selectedIds as $registrationId) {
            // Only generate for attended volunteers
            $checkStmt = $conn->prepare("SELECT status FROM volunteer_registrations WHERE registration_id = ?");
            $checkStmt->bind_param("i", $registrationId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $volunteerData = $checkResult->fetch_assoc();
            
            if ($volunteerData['status'] === 'attended') {
                // Generate unique certificate code
                $certificateCode = 'CERT-' . strtoupper(substr(md5($registrationId . time()), 0, 10));
                
                // Update registration with certificate code
                $updateStmt = $conn->prepare("
                    UPDATE volunteer_registrations 
                    SET certificate_code = ?, certificate_date = NOW() 
                    WHERE registration_id = ?
                ");
                $updateStmt->bind_param("si", $certificateCode, $registrationId);
                
                if ($updateStmt->execute()) {
                    $successCount++;
                }
            }
        }
        
        if ($successCount > 0) {
            $message = "Generated certificates for $successCount volunteers";
            $messageType = 'success';
        } else {
            $message = "No certificates generated. Make sure volunteers are marked as 'attended'";
            $messageType = 'error';
        }
    }
}

// Get volunteers for this event
$volunteersQuery = "
    SELECT vr.*, u.first_name, u.last_name, u.email
    FROM volunteer_registrations vr
    JOIN users u ON vr.user_id = u.user_id
    WHERE vr.event_id = ?
    ORDER BY vr.registration_date DESC
";

$volunteersStmt = $conn->prepare($volunteersQuery);
$volunteersStmt->bind_param("i", $eventId);
$volunteersStmt->execute();
$volunteersResult = $volunteersStmt->get_result();

$volunteers = [];
while ($row = $volunteersResult->fetch_assoc()) {
    $volunteers[] = $row;
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="volunteering.php" style="color: #5D5CDE; text-decoration: none; display: inline-flex; align-items: center;">
                ← Back to Volunteering Events
            </a>
            <h1 style="font-size: 24px; font-weight: bold; margin-top: 10px;">
                Volunteers for: <?php echo htmlspecialchars($event['title']); ?>
            </h1>
            <p style="color: #6b7280;">
                Event Date: <?php echo date('F j, Y, g:i a', strtotime($event['event_date'])); ?> | 
                Location: <?php echo htmlspecialchars($event['location']); ?>
            </p>
        </div>
        
        <form method="POST" id="certificate-form">
            <button type="submit" name="generate_certificates" style="background-color: #10b981; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center;">
                🏆 Generate Certificates
            </button>
        </form>
    </div>
    
    <?php if ($message): ?>
        <div style="margin-bottom: 20px; padding: 15px; border-radius: 4px; 
            <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Volunteers Table -->
    <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
        <?php if (empty($volunteers)): ?>
            <div style="padding: 20px; text-align: center; color: #6b7280;">
                <p>No volunteers found for this event</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background-color: #f3f4f6;">
                        <tr>
                            <th style="padding: 12px 16px; text-align: center; width: 40px;">
                                <input type="checkbox" id="select-all" style="cursor: pointer;">
                            </th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Name</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Contact</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Registration Date</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Status</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Certificate</th>
                            <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($volunteers as $volunteer): ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 12px 16px; text-align: center;">
                                    <input type="checkbox" name="selected_volunteers[]" form="certificate-form" value="<?php echo $volunteer['registration_id']; ?>" class="volunteer-checkbox" style="cursor: pointer;">
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php echo htmlspecialchars($volunteer['first_name'] . ' ' . $volunteer['last_name']); ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php echo htmlspecialchars($volunteer['email']); ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php echo date('M d, Y H:i', strtotime($volunteer['registration_date'])); ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="registration_id" value="<?php echo $volunteer['registration_id']; ?>">
                                        <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                                            <option value="pending" <?php echo $volunteer['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $volunteer['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $volunteer['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="attended" <?php echo $volunteer['status'] === 'attended' ? 'selected' : ''; ?>>Attended</option>
                                            <option value="no_show" <?php echo $volunteer['status'] === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php if (!empty($volunteer['certificate_code'])): ?>
                                        <a href="<?php echo SITE_URL; ?>/volunteer-certificate.php?code=<?php echo $volunteer['certificate_code']; ?>" target="_blank" style="color: #10b981; text-decoration: none;">
                                            <i class="fas fa-certificate"></i> View
                                        </a>
                                    <?php elseif ($volunteer['status'] === 'attended'): ?>
                                        <span style="color: #6b7280;">Generate certificate</span>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">Not available</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <button type="button" onclick="showVolunteerDetails(<?php echo $volunteer['registration_id']; ?>)" style="background: none; border: none; color: #5D5CDE; cursor: pointer; text-decoration: underline;">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Hidden row for volunteer details -->
                            <tr id="details-<?php echo $volunteer['registration_id']; ?>" style="display: none; border-top: 1px solid #e5e7eb; background-color: #f9fafb;">
                                <td colspan="7" style="padding: 16px;">
                                    <div style="margin-bottom: 10px;">
                                        <h4 style="font-size: 16px; font-weight: bold; margin-bottom: 5px; color: #1f2937;">Why They Want to Volunteer:</h4>
                                        <p style="color: #4b5563; white-space: pre-line;"><?php echo nl2br(htmlspecialchars($volunteer['motivation'] ?: 'Not provided')); ?></p>
                                    </div>
                                    
                                    <div>
                                        <h4 style="font-size: 16px; font-weight: bold; margin-bottom: 5px; color: #1f2937;">Previous Experience:</h4>
                                        <p style="color: #4b5563; white-space: pre-line;"><?php echo nl2br(htmlspecialchars($volunteer['experience'] ?: 'Not provided')); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Handle select all checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('select-all');
        const volunteerCheckboxes = document.querySelectorAll('.volunteer-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                volunteerCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    });
    
    // Function to show/hide volunteer details
    function showVolunteerDetails(id) {
        const detailsRow = document.getElementById('details-' + id);
        if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
            detailsRow.style.display = 'table-row';
        } else {
            detailsRow.style.display = 'none';
        }
    }
</script>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>