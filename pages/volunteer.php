<?php
// Get event ID from URL
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($eventId === 0) {
    // If no specific event is requested, redirect to events list
    header("Location: " . SITE_URL . "/index.php?page=volunteering");
    exit;
}

// Fetch event details
$conn = getDBConnection();
$eventQuery = "SELECT * FROM volunteer_events WHERE event_id = ? AND is_active = 1";
$eventStmt = $conn->prepare($eventQuery);
$eventStmt->bind_param("i", $eventId);
$eventStmt->execute();
$eventResult = $eventStmt->get_result();

if ($eventResult->num_rows === 0) {
    // Event not found or inactive
    echo "<div style='background-color: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;'>
            <p>Event not found or no longer active.</p>
          </div>";
    echo "<div style='text-align: center; margin-top: 20px;'>
            <a href='".SITE_URL."/index.php?page=volunteering' style='background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>
                Browse Events
            </a>
          </div>";
    exit;
}

$event = $eventResult->fetch_assoc();

// Check if event date has passed
$eventDate = strtotime($event['event_date']);
if ($eventDate < time()) {
    echo "<div style='background-color: #fef3c7; border: 1px solid #f59e0b; color: #b45309; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;'>
            <p>This event has already taken place.</p>
          </div>";
    echo "<div style='text-align: center; margin-top: 20px;'>
            <a href='".SITE_URL."/index.php?page=volunteering' style='background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>
                Browse Upcoming Events
            </a>
          </div>";
    exit;
}

// Check if user is logged in and not an admin
$userIsRegistered = false;
$registrationStatus = '';
$message = '';
$messageType = '';
$isAdmin = false;

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    
    // Check if user is admin
    $isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    
    // Check if user is already registered
    $checkQuery = "SELECT * FROM volunteer_registrations WHERE event_id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $eventId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $userIsRegistered = true;
        $registration = $checkResult->fetch_assoc();
        $registrationStatus = $registration['status'];
    }
    
    // Handle registration form submission (only for non-admin users)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && !$isAdmin) {
        if ($userIsRegistered) {
            $message = "You are already registered for this event.";
            $messageType = "error";
        } else {
            // Check if event is full
            $countQuery = "SELECT COUNT(*) as current_count FROM volunteer_registrations WHERE event_id = ? AND status != 'rejected'";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->bind_param("i", $eventId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $currentCount = $countResult->fetch_assoc()['current_count'];
            
            if ($event['required_volunteers'] > 0 && $currentCount >= $event['required_volunteers']) {
                $message = "Sorry, this event is already full.";
                $messageType = "error";
            } else {
                // Get form data
                $motivation = isset($_POST['motivation']) ? sanitize($_POST['motivation']) : '';
                $experience = isset($_POST['experience']) ? sanitize($_POST['experience']) : '';
                
                // Insert registration
                $insertQuery = "INSERT INTO volunteer_registrations 
                               (event_id, user_id, registration_date, status, motivation, experience) 
                               VALUES (?, ?, NOW(), 'pending', ?, ?)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("iiss", $eventId, $userId, $motivation, $experience);
                
                if ($insertStmt->execute()) {
                    $message = "Thank you for registering! Your application has been submitted successfully.";
                    $messageType = "success";
                    $userIsRegistered = true;
                    $registrationStatus = 'pending';
                } else {
                    $message = "An error occurred while processing your registration. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
    
    // Handle cancellation request (only for non-admin users)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_registration']) && !$isAdmin) {
        if (!$userIsRegistered) {
            $message = "You are not registered for this event.";
            $messageType = "error";
        } else if ($registrationStatus === 'attended') {
            $message = "You cannot cancel a registration for an event you have already attended.";
            $messageType = "error";
        } else {
            // Delete registration
            $deleteQuery = "DELETE FROM volunteer_registrations WHERE event_id = ? AND user_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("ii", $eventId, $userId);
            
            if ($deleteStmt->execute()) {
                $message = "Your registration has been cancelled successfully.";
                $messageType = "success";
                $userIsRegistered = false;
                $registrationStatus = '';
            } else {
                $message = "An error occurred while cancelling your registration. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// Get current volunteer count
$countQuery = "SELECT COUNT(*) as count FROM volunteer_registrations WHERE event_id = ? AND status != 'rejected'";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $eventId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$currentVolunteers = $countResult->fetch_assoc()['count'];

// Check if the event is full
$isFull = $event['required_volunteers'] > 0 && $currentVolunteers >= $event['required_volunteers'];
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 20px;">
        <a href="<?php echo SITE_URL; ?>/index.php?page=volunteering" style="color: #5D5CDE; text-decoration: none; display: inline-flex; align-items: center;">
            ← Back to Events
        </a>
    </div>
    
    <?php if ($message): ?>
        <div style="margin-bottom: 20px; padding: 15px; border-radius: 4px; 
            <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Event Details -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="height: 200px; background-color: #e5e7eb; position: relative;">
                <?php if (!empty($event['event_image'])): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo htmlspecialchars($event['event_image']); ?>" 
                         alt="<?php echo htmlspecialchars($event['title']); ?>"
                         style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <!-- Placeholder for events without images -->
                    <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 18px; font-weight: bold; color: #6b7280;">No Image Available</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="padding: 25px;">
                <h1 style="font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #1f2937;"><?php echo htmlspecialchars($event['title']); ?></h1>
                
                <div style="margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 20px;">
                    <div style="display: flex; gap: 10px; color: #4b5563;">
                        <div style="width: 20px; text-align: center;">📅</div>
                        <div>
                            <div style="font-weight: 500; color: #1f2937;"><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?></div>
                            <div><?php echo date('g:i A', strtotime($event['event_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; color: #4b5563;">
                        <div style="width: 20px; text-align: center;">📍</div>
                        <div>
                            <div style="font-weight: 500; color: #1f2937;"><?php echo htmlspecialchars($event['location']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #1f2937;">Event Description</h3>
                    <div style="color: #4b5563; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #1f2937;">Volunteer Information</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 4px;">
                            <div style="font-weight: 500; color: #1f2937; margin-bottom: 5px;">Required Volunteers</div>
                            <div style="color: #4b5563;">
                                <?php echo $currentVolunteers; ?> / <?php echo $event['required_volunteers']; ?> volunteers registered
                            </div>
                            
                            <?php if ($isFull): ?>
                                <div style="color: #ef4444; font-weight: 500; margin-top: 5px;">
                                    This event is currently full
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($event['skills_needed'])): ?>
                            <div style="background-color: #f3f4f6; padding: 15px; border-radius: 4px;">
                                <div style="font-weight: 500; color: #1f2937; margin-bottom: 5px;">Skills Needed</div>
                                <div style="color: #4b5563;">
                                    <?php echo htmlspecialchars($event['skills_needed']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($event['contact_email'])): ?>
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 20px;">
                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #1f2937;">Contact Information</h3>
                        <a href="mailto:<?php echo $event['contact_email']; ?>" style="color: #5D5CDE; text-decoration: none; display: inline-flex; align-items: center;">
                            ✉️ <?php echo $event['contact_email']; ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Registration Form -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; height: fit-content;">
            <div style="padding: 25px;">
                <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #1f2937;">Volunteer Registration</h3>
                
                <?php if (time() > strtotime($event['event_date'])): ?>
                    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; color: #b45309; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="font-weight: bold;">This event has already taken place.</p>
                    </div>
                <?php elseif ($isAdmin): ?>
                    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; color: #b45309; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="font-weight: bold;">Admin Notice:</p>
                        <p>As an admin, you cannot register as a volunteer.</p>
                        <p style="margin-top: 10px;">You can view and manage volunteers for this event in the admin panel.</p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/admin/volunteer-list.php?event_id=<?php echo $eventId; ?>" style="display: block; width: 100%; background-color: #5D5CDE; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; text-align: center;">
                        Manage Volunteers
                    </a>
                <?php elseif ($isFull && !$userIsRegistered): ?>
                    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; color: #b45309; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;">
                        <p style="font-weight: bold;">This event is full.</p>
                        <p>Sorry, all available spots have been filled.</p>
                    </div>
                <?php elseif (isLoggedIn() && !$isAdmin): ?>
                    <?php if ($userIsRegistered): ?>
                        <div style="background-color: #d1fae5; border: 1px solid #059669; color: #047857; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;">
                            <p style="font-weight: bold;">You're registered!</p>
                            <p>Your registration status: <span style="font-weight: bold;"><?php echo ucfirst($registrationStatus); ?></span></p>
                            
                            <?php if ($registrationStatus === 'approved'): ?>
                                <p style="margin-top: 10px;">You have been approved to volunteer at this event. Please arrive 15 minutes before the start time.</p>
                            <?php elseif ($registrationStatus === 'pending'): ?>
                                <p style="margin-top: 10px;">Your registration is currently pending review. We'll notify you once it's approved.</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($registrationStatus !== 'attended' && $registrationStatus !== 'no_show'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel your registration?');">
                                <button type="submit" name="cancel_registration" style="width: 100%; background-color: #ef4444; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">
                                    Cancel Registration
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div style="margin-bottom: 15px;">
                                <label for="motivation" style="display: block; margin-bottom: 5px; font-weight: bold;">Why do you want to volunteer?*</label>
                                <textarea id="motivation" name="motivation" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required></textarea>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label for="experience" style="display: block; margin-bottom: 5px; font-weight: bold;">Previous Volunteer Experience</label>
                                <textarea id="experience" name="experience" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            </div>
                            
                            <button type="submit" name="register" style="width: 100%; background-color: #5D5CDE; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">
                                Register as Volunteer
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center;">
                        <p style="color: #4b5563; margin-bottom: 15px;">Please login to register for this event.</p>
                        <a href="<?php echo SITE_URL; ?>/index.php?page=login&redirect=volunteer&id=<?php echo $eventId; ?>" style="display: block; width: 100%; background-color: #5D5CDE; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-bottom: 10px;">
                            Login to Register
                        </a>
                        <p style="color: #4b5563; font-size: 14px;">
                            Don't have an account? 
                            <a href="<?php echo SITE_URL; ?>/index.php?page=register&redirect=volunteer&id=<?php echo $eventId; ?>" style="color: #5D5CDE; text-decoration: none;">
                                Register
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>