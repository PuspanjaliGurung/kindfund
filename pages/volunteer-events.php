<?php
// Get all active volunteer events
$conn = getDBConnection();
$eventsQuery = "SELECT * FROM volunteer_events WHERE is_active = 1 AND event_date > NOW() ORDER BY event_date ASC";
$eventsResult = $conn->query($eventsQuery);
$events = [];
while ($row = $eventsResult->fetch_assoc()) {
    $events[] = $row;
}
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h1 style="font-size: 28px; font-weight: bold; margin-bottom: 20px; color: #1f2937;">Volunteer Opportunities</h1>
    
    <p style="margin-bottom: 30px; color: #4b5563;">
        Make a difference by volunteering for one of our upcoming events. Your time and skills can help us create a better community.
    </p>
    
    <?php if (empty($events)): ?>
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 30px; text-align: center;">
            <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #1f2937;">No Upcoming Events</h3>
            <p style="color: #4b5563;">
                There are no volunteer events currently scheduled. Please check back later.
            </p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($events as $event): ?>
                <?php
                // Get current volunteer count
                $countQuery = "SELECT COUNT(*) as count FROM volunteer_registrations WHERE event_id = ? AND status != 'rejected'";
                $countStmt = $conn->prepare($countQuery);
                $countStmt->bind_param("i", $event['event_id']);
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $currentVolunteers = $countResult->fetch_assoc()['count'];
                
                // Check if event is full
                $isFull = ($event['required_volunteers'] > 0 && $currentVolunteers >= $event['required_volunteers']);
                ?>
                
                <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
                    <div style="height: 150px; background-color: #e5e7eb; position: relative;">
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
                        
                        <!-- Date badge -->
                        <div style="position: absolute; top: 10px; right: 10px; background-color: #5D5CDE; color: white; padding: 8px 12px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 20px; font-weight: bold;"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                            <div style="font-size: 12px;"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div style="padding: 20px;">
                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #1f2937;">
                            <?php echo htmlspecialchars($event['title']); ?>
                        </h3>
                        
                        <div style="margin-bottom: 15px; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; gap: 8px; color: #4b5563;">
                                <div style="width: 20px; text-align: center;">📅</div>
                                <div>
                                    <div><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?></div>
                                    <div><?php echo date('g:i A', strtotime($event['event_date'])); ?></div>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 8px; color: #4b5563;">
                                <div style="width: 20px; text-align: center;">📍</div>
                                <div><?php echo htmlspecialchars($event['location']); ?></div>
                            </div>
                            
                            <div style="display: flex; gap: 8px; color: #4b5563;">
                                <div style="width: 20px; text-align: center;">👥</div>
                                <div>
                                    <?php echo $currentVolunteers; ?> / <?php echo $event['required_volunteers']; ?> volunteers
                                    <?php if ($isFull): ?>
                                        <span style="color: #ef4444; font-weight: bold;"> (Full)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <p style="margin-bottom: 20px; color: #4b5563; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                            <?php echo htmlspecialchars(substr($event['description'], 0, 150) . (strlen($event['description']) > 150 ? '...' : '')); ?>
                        </p>
                        
                        <a href="<?php echo SITE_URL; ?>/index.php?page=volunteer&id=<?php echo $event['event_id']; ?>" 
                            style="display: inline-block; width: 100%; text-align: center; background-color: #5D5CDE; color: white; padding: 10px 16px; text-decoration: none; border-radius: 4px;">
                            <?php echo $isFull ? 'View Details' : 'Volunteer Now'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>