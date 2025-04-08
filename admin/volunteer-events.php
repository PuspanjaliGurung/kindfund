<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add/Edit volunteer event
    if (isset($_POST['save_event'])) {
        $eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $location = sanitize($_POST['location']);
        $eventDate = sanitize($_POST['event_date']);
        $requiredVolunteers = intval($_POST['required_volunteers']);
        $skillsNeeded = isset($_POST['skills_needed']) ? sanitize($_POST['skills_needed']) : '';
        $contactEmail = isset($_POST['contact_email']) ? sanitize($_POST['contact_email']) : '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Handle image upload
        $eventImage = '';
        $uploadDir = '../uploads/events/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (in_array($_FILES['event_image']['type'], $allowedTypes) && $_FILES['event_image']['size'] <= $maxSize) {
                $fileName = time() . '_' . basename($_FILES['event_image']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['event_image']['tmp_name'], $targetPath)) {
                    $eventImage = $fileName;
                } else {
                    $message = 'Error uploading image.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Invalid image file. Please upload a JPG or PNG file under 2MB.';
                $messageType = 'error';
            }
        }

        if (empty($title) || empty($description) || empty($location) || empty($eventDate)) {
            $message = 'Please fill in all required fields';
            $messageType = 'error';
        } else {
            if ($eventId > 0) {
                // Update existing event
                $stmt = $conn->prepare("UPDATE volunteer_events SET 
                    title = ?, description = ?, location = ?, event_date = ?, 
                    required_volunteers = ?, skills_needed = ?, contact_email = ?, 
                    is_active = ?, event_image = ?, last_updated = NOW() 
                    WHERE event_id = ?");
                
                $stmt->bind_param("ssssissssi", $title, $description, $location, 
                                $eventDate, $requiredVolunteers, $skillsNeeded, 
                                $contactEmail, $isActive, $eventImage, $eventId);
                
                if ($stmt->execute()) {
                    $message = 'Volunteering event updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating volunteering event: ' . $stmt->error;
                    $messageType = 'error';
                }
            } else {
                // Add new event
                $stmt = $conn->prepare("INSERT INTO volunteer_events 
                    (title, description, location, event_date, required_volunteers, 
                    skills_needed, contact_email, is_active, event_image, created_at, last_updated) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->bind_param("ssssissss", $title, $description, $location, 
                                $eventDate, $requiredVolunteers, $skillsNeeded, 
                                $contactEmail, $isActive, $eventImage);
                
                if ($stmt->execute()) {
                    $message = 'Volunteering event added successfully';
                    $messageType = 'success';
                    // Clear form
                    $eventId = 0;
                    $title = $description = $location = $eventDate = $skillsNeeded = $contactEmail = '';
                    $requiredVolunteers = 0;
                    $isActive = 1;
                } else {
                    $message = 'Error adding volunteering event: ' . $stmt->error;
                    $messageType = 'error';
                }
            }
        }
    }
    
    // Delete event
    if (isset($_POST['delete_event'])) {
        $eventId = intval($_POST['event_id']);
        
        // First check if there are volunteers registered for this event
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM volunteer_registrations WHERE event_id = ?");
        $checkStmt->bind_param("i", $eventId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $message = 'Cannot delete event. There are ' . $count . ' volunteers registered for this event.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM volunteer_events WHERE event_id = ?");
            $stmt->bind_param("i", $eventId);
            
            if ($stmt->execute()) {
                $message = 'Volunteering event deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Error deleting volunteering event: ' . $stmt->error;
                $messageType = 'error';
            }
        }
    }
}

// Get event to edit
$event = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $eventId = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM volunteer_events WHERE event_id = ?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $event = $result->fetch_assoc();
    } else {
        $message = 'Event not found';
        $messageType = 'error';
    }
}

// Get all events
$eventsQuery = "SELECT * FROM volunteer_events ORDER BY event_date DESC";
$eventsResult = $conn->query($eventsQuery);
$events = [];
while ($row = $eventsResult->fetch_assoc()) {
    $events[] = $row;
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h1 style="font-size: 24px; font-weight: bold;">
            <?php echo isset($_GET['action']) && $_GET['action'] === 'edit' ? 'Edit Volunteer Event' : 'Volunteer Events'; ?>
        </h1>
        
        <?php if (!isset($_GET['action'])): ?>
            <a href="?action=add" style="background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                Add New Volunteer Event
            </a>
        <?php endif; ?>
    </div>
    
    <?php if ($message): ?>
        <div style="margin-bottom: 20px; padding: 15px; border-radius: 4px; 
            <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724;' : 'background-color: #f8d7da; color: #721c24;'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['action']) && ($_GET['action'] === 'add' || $_GET['action'] === 'edit')): ?>
        <!-- Add/Edit Form -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; padding: 20px;">
            <form method="POST" action="" enctype="multipart/form-data">
                <?php if (isset($event['event_id'])): ?>
                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                <?php endif; ?>
                
                <div style="margin-bottom: 15px;">
                    <label for="title" style="display: block; margin-bottom: 5px; font-weight: bold;">Event Title*</label>
                    <input type="text" id="title" name="title" value="<?php echo isset($event['title']) ? htmlspecialchars($event['title']) : ''; ?>" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="description" style="display: block; margin-bottom: 5px; font-weight: bold;">Description*</label>
                    <textarea id="description" name="description" rows="4" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required><?php echo isset($event['description']) ? htmlspecialchars($event['description']) : ''; ?></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="location" style="display: block; margin-bottom: 5px; font-weight: bold;">Location*</label>
                    <input type="text" id="location" name="location" value="<?php echo isset($event['location']) ? htmlspecialchars($event['location']) : ''; ?>" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="event_date" style="display: block; margin-bottom: 5px; font-weight: bold;">Event Date*</label>
                    <input type="datetime-local" id="event_date" name="event_date" 
                        value="<?php echo isset($event['event_date']) ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : ''; ?>" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="required_volunteers" style="display: block; margin-bottom: 5px; font-weight: bold;">Required Volunteers</label>
                    <input type="number" id="required_volunteers" name="required_volunteers" 
                        value="<?php echo isset($event['required_volunteers']) ? $event['required_volunteers'] : '10'; ?>" min="1" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="skills_needed" style="display: block; margin-bottom: 5px; font-weight: bold;">Skills Needed</label>
                    <textarea id="skills_needed" name="skills_needed" rows="2" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo isset($event['skills_needed']) ? htmlspecialchars($event['skills_needed']) : ''; ?></textarea>
                    <p style="font-size: 12px; color: #666; margin-top: 4px;">Separate skills with commas</p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="contact_email" style="display: block; margin-bottom: 5px; font-weight: bold;">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" 
                        value="<?php echo isset($event['contact_email']) ? htmlspecialchars($event['contact_email']) : ''; ?>" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="event_image" style="display: block; margin-bottom: 5px; font-weight: bold;">Event Image</label>
                    <input type="file" id="event_image" name="event_image" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php if (isset($event['event_image']) && $event['event_image']): ?>
                        <p style="font-size: 12px; color: #666; margin-top: 4px;">Current Image: <?php echo htmlspecialchars($event['event_image']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="is_active" name="is_active" 
                            <?php echo !isset($event['is_active']) || $event['is_active'] ? 'checked' : ''; ?>>
                        <span style="margin-left: 8px;">Active Event</span>
                    </label>
                    <p style="font-size: 12px; color: #666; margin-top: 4px;">Only active events are visible to users</p>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <a href="volunteer-events.php" style="background-color: #e2e8f0; color: #1a202c; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                        Cancel
                    </a>
                    
                    <button type="submit" name="save_event" style="background-color: #5D5CDE; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                        <?php echo isset($event['event_id']) ? 'Update Event' : 'Add Event'; ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Events Table -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
            <?php if (empty($events)): ?>
                <div style="padding: 20px; text-align: center; color: #6b7280;">
                    <p>No volunteering events found</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Title</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Location</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Date</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Volunteers</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Status</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <?php
                                // Get the current volunteer count
                                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM volunteer_registrations WHERE event_id = ?");
                                $countStmt->bind_param("i", $event['event_id']);
                                $countStmt->execute();
                                $countResult = $countStmt->get_result();
                                $currentVolunteers = $countResult->fetch_assoc()['count'];
                                ?>
                                <tr style="border-top: 1px solid #e5e7eb;">
                                    <td style="padding: 12px 16px;"><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo date('M d, Y H:i', strtotime($event['event_date'])); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo $currentVolunteers; ?> / <?php echo $event['required_volunteers']; ?></td>
                                    <td style="padding: 12px 16px;">
                                        <span style="padding: 4px 8px; font-size: 12px; border-radius: 9999px; 
                                            <?php echo $event['is_active'] ? 'background-color: #d1fae5; color: #047857;' : 'background-color: #fee2e2; color: #b91c1c;'; ?>">
                                            <?php echo $event['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <div style="display: flex; gap: 8px;">
                                            <a href="volunteer-events.php?action=edit&id=<?php echo $event['event_id']; ?>" style="color: #5D5CDE; text-decoration: none;">
                                                Edit
                                            </a>
                                            
                                            <a href="volunteer-list.php?event_id=<?php echo $event['event_id']; ?>" style="color: #3b82f6; text-decoration: none;">
                                                View Volunteers
                                            </a>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                <button type="submit" name="delete_event" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0; text-decoration: underline;">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>