<?php
// Get user information
$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$userQuery = "SELECT * FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

// Get user's donation history
$donationQuery = "SELECT d.*, c.title as campaign_title, c.campaign_id 
                  FROM donations d 
                  JOIN campaigns c ON d.campaign_id = c.campaign_id 
                  WHERE d.user_id = ? 
                  ORDER BY d.donation_date DESC";
$donationStmt = $conn->prepare($donationQuery);
$donationStmt->bind_param("i", $userId);
$donationStmt->execute();
$donationResult = $donationStmt->get_result();

$donations = [];
while ($row = $donationResult->fetch_assoc()) {
    $donations[] = $row;
}

// Calculate total donation amount
$totalDonationQuery = "SELECT SUM(amount) as total FROM donations WHERE user_id = ?";
$totalStmt = $conn->prepare($totalDonationQuery);
$totalStmt->bind_param("i", $userId);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalDonation = $totalResult->fetch_assoc()['total'] ?? 0;

// Get volunteer history
$volunteerQuery = "SELECT vr.*, ve.title as event_title, ve.event_date, ve.location 
                  FROM volunteer_registrations vr 
                  JOIN volunteer_events ve ON vr.event_id = ve.event_id 
                  WHERE vr.user_id = ? 
                  ORDER BY vr.registration_date DESC";
$volunteerStmt = $conn->prepare($volunteerQuery);
$volunteerStmt->bind_param("i", $userId);
$volunteerStmt->execute();
$volunteerResult = $volunteerStmt->get_result();

$volunteering = [];
while ($row = $volunteerResult->fetch_assoc()) {
    $volunteering[] = $row;
}

// Get donation categories for user
$categoryQuery = "
    SELECT DISTINCT cc.category_id, cat.category_name, COUNT(d.donation_id) as donation_count
    FROM donations d
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    JOIN campaign_categories cc ON c.campaign_id = cc.campaign_id
    JOIN categories cat ON cc.category_id = cat.category_id
    WHERE d.user_id = ?
    GROUP BY cc.category_id
    ORDER BY donation_count DESC
    LIMIT 3
";
$categoryStmt = $conn->prepare($categoryQuery);
$categoryStmt->bind_param("i", $userId);
$categoryStmt->execute();
$categoryResult = $categoryStmt->get_result();

$userInterests = [];
while ($row = $categoryResult->fetch_assoc()) {
    $userInterests[] = $row;
}

// Get recommended campaigns based on user's interests
$recommendedCampaigns = getRecommendedCampaigns($userId, 3);
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h1 style="font-size: 28px; font-weight: bold; margin-bottom: 20px; color: #1f2937;">User Dashboard</h1>
    
    <!-- User Info & Stats Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- User Info Card -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <div style="width: 60px; height: 60px; background-color: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #5D5CDE; font-size: 24px; font-weight: bold; margin-right: 15px;">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <div>
                    <h2 style="font-size: 18px; font-weight: bold; color: #1f2937; margin: 0;">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </h2>
                    <p style="color: #6b7280; margin: 0;"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 15px;">
                <p style="color: #6b7280; margin: 0;">
                    Member since: <?php echo date('M d, Y', strtotime($user['registration_date'])); ?>
                </p>
            </div>
        </div>
        
        <!-- Donation Stats Card -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 15px;">Donation Stats</h3>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <p style="color: #6b7280; margin: 0 0 5px 0;">Total Donations</p>
                    <p style="font-size: 18px; font-weight: bold; color: #5D5CDE; margin: 0;">
                        <?php echo count($donations); ?>
                    </p>
                </div>
                
                <div>
                    <p style="color: #6b7280; margin: 0 0 5px 0;">Total Amount Donated</p>
                    <p style="font-size: 18px; font-weight: bold; color: #10b981; margin: 0;">
                        <?php echo formatCurrency($totalDonation); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Volunteer Stats Card -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 15px;">Volunteer Stats</h3>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <p style="color: #6b7280; margin: 0 0 5px 0;">Total Events</p>
                    <p style="font-size: 18px; font-weight: bold; color: #5D5CDE; margin: 0;">
                        <?php echo count($volunteering); ?>
                    </p>
                </div>
                
                <div>
                    <p style="color: #6b7280; margin: 0 0 5px 0;">Certificates Earned</p>
                    <?php
                    $certificateCount = 0;
                    foreach ($volunteering as $event) {
                        if (!empty($event['certificate_code'])) {
                            $certificateCount++;
                        }
                    }
                    ?>
                    <p style="font-size: 18px; font-weight: bold; color: #10b981; margin: 0;">
                        <?php echo $certificateCount; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 15px;">Quick Actions</h3>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="<?php echo SITE_URL; ?>/index.php?page=profile" style="display: block; width: 100%; text-align: center; background-color: #5D5CDE; color: white; padding: 8px 0; text-decoration: none; border-radius: 4px;">
    Edit Profile
</a>
                
                <a href="<?php echo SITE_URL; ?>/index.php?page=donations" style="display: block; width: 100%; text-align: center; background-color: #10b981; color: white; padding: 8px 0; text-decoration: none; border-radius: 4px;">
                    Browse Campaigns
                </a>
                
                <a href="<?php echo SITE_URL; ?>/index.php?page=volunteer-events" style="display: block; width: 100%; text-align: center; background-color: #3b82f6; color: white; padding: 8px 0; text-decoration: none; border-radius: 4px;">
                    Volunteer Opportunities
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recommended Campaigns Section -->
    <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 30px;">
        <div style="padding: 15px 20px; border-bottom: 1px solid #e5e7eb;">
            <h2 style="font-size: 18px; font-weight: bold; color: #1f2937;">Recommended For You</h2>
            <?php if (!empty($userInterests)): ?>
                <p style="color: #6b7280; margin: 5px 0 0 0; font-size: 14px;">
                    Based on your interest in 
                    <?php
                    $interestNames = array_map(function($interest) {
                        return $interest['category_name'];
                    }, $userInterests);
                    echo implode(', ', $interestNames);
                    ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div style="padding: 20px;">
            <?php if (empty($recommendedCampaigns)): ?>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 24px; color: #9ca3af; margin-bottom: 10px;">🔍</div>
                    <p style="color: #4b5563; margin-bottom: 15px;">Make your first donation to get personalized recommendations!</p>
                    <a href="<?php echo SITE_URL; ?>/index.php?page=donations" style="display: inline-block; background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                        Browse Campaigns
                    </a>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($recommendedCampaigns as $campaign): ?>
                        <?php 
                        $progress = ($campaign['goal_amount'] > 0) ? ($campaign['current_amount'] / $campaign['goal_amount']) * 100 : 0;
                        $daysLeft = max(0, ceil((strtotime($campaign['end_date']) - time()) / (60 * 60 * 24)));
                        ?>
                        
                        <div style="background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                            <div style="height: 150px; background-color: #e5e7eb; position: relative;">
                                <!-- Campaign image placeholder -->
                                <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #6b7280; font-weight: bold;">
                                    <?php echo htmlspecialchars($campaign['title']); ?>
                                </div>
                            </div>
                            
                            <div style="padding: 15px;">
                                <div style="margin-bottom: 5px; font-size: 14px; color: #6b7280;">
                                    <?php echo htmlspecialchars($campaign['charity_name']); ?>
                                </div>
                                
                                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 10px; margin-top: 0;">
                                    <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $campaign['campaign_id']; ?>" style="color: #1f2937; text-decoration: none;">
                                        <?php echo htmlspecialchars($campaign['title']); ?>
                                    </a>
                                </h3>
                                
                                <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars(substr($campaign['description'], 0, 100) . '...'); ?>
                                </p>
                                
                                <div style="margin-bottom: 15px;">
                                    <div style="height: 8px; background-color: #e5e7eb; border-radius: 4px; margin-bottom: 5px;">
                                        <div style="height: 8px; width: <?php echo min(100, $progress); ?>%; background-color: #5D5CDE; border-radius: 4px;"></div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                                        <div style="color: #5D5CDE; font-weight: 600;"><?php echo formatCurrency($campaign['current_amount']); ?></div>
                                        <div style="color: #6b7280;"><?php echo round($progress); ?>% of <?php echo formatCurrency($campaign['goal_amount']); ?></div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; color: #6b7280; margin-bottom: 15px;">
                                    <div><?php echo $daysLeft; ?> days left</div>
                                </div>
                                
                                <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $campaign['campaign_id']; ?>" style="display: block; text-align: center; background-color: #5D5CDE; color: white; padding: 8px 0; text-decoration: none; border-radius: 4px;">
                                    Donate Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Donation History Section -->
    <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 30px;">
        <div style="padding: 15px 20px; border-bottom: 1px solid #e5e7eb;">
            <h2 style="font-size: 18px; font-weight: bold; color: #1f2937;">Your Donation History</h2>
        </div>
        
        <div style="padding: 20px;">
            <?php if (empty($donations)): ?>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 24px; color: #9ca3af; margin-bottom: 10px;">💰</div>
                    <p style="color: #4b5563; margin-bottom: 15px;">You haven't made any donations yet.</p>
                    <a href="<?php echo SITE_URL; ?>/index.php?page=donations" style="display: inline-block; background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                        Browse Campaigns
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Campaign</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Amount</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Date</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                                <tr style="border-top: 1px solid #e5e7eb;">
                                    <td style="padding: 12px 15px;">
                                        <a href="<?php echo SITE_URL; ?>/index.php?page=campaign&id=<?php echo $donation['campaign_id']; ?>" style="color: #5D5CDE; text-decoration: none; font-weight: 500;">
                                            <?php echo htmlspecialchars($donation['campaign_title']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px 15px; font-weight: 500; color: #1f2937;">
                                        <?php echo formatCurrency($donation['amount']); ?>
                                    </td>
                                    <td style="padding: 12px 15px; color: #4b5563;">
                                        <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                    </td>
                                    <td style="padding: 12px 15px;">
                                        <a href="<?php echo SITE_URL; ?>/index.php?page=donation-receipt&id=<?php echo $donation['donation_id']; ?>" style="color: #10b981; text-decoration: none;">
                                            View Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Volunteer History Section -->
    <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 30px;">
        <div style="padding: 15px 20px; border-bottom: 1px solid #e5e7eb;">
            <h2 style="font-size: 18px; font-weight: bold; color: #1f2937;">Your Volunteer History</h2>
        </div>
        
        <div style="padding: 20px;">
            <?php if (empty($volunteering)): ?>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 24px; color: #9ca3af; margin-bottom: 10px;">👐</div>
                    <p style="color: #4b5563; margin-bottom: 15px;">You haven't volunteered for any events yet.</p>
                    <a href="<?php echo SITE_URL; ?>/index.php?page=volunteer-events" style="display: inline-block; background-color: #5D5CDE; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                        Browse Volunteer Opportunities
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Event</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Date</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Status</th>
                                <th style="padding: 10px 15px; text-align: left; font-size: 14px; color: #6b7280; font-weight: 500;">Certificate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteering as $event): ?>
                                <tr style="border-top: 1px solid #e5e7eb;">
                                    <td style="padding: 12px 15px;">
                                        <div style="font-weight: 500; color: #1f2937;"><?php echo htmlspecialchars($event['event_title']); ?></div>
                                        <div style="font-size: 14px; color: #6b7280;"><?php echo htmlspecialchars($event['location']); ?></div>
                                    </td>
                                    <td style="padding: 12px 15px; color: #4b5563;">
                                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                    </td>
                                    <td style="padding: 12px 15px;">
                                        <?php
                                        $statusColors = [
                                            'pending' => 'background-color: #fef3c7; color: #92400e;',
                                            'approved' => 'background-color: #d1fae5; color: #047857;',
                                            'rejected' => 'background-color: #fee2e2; color: #b91c1c;',
                                            'attended' => 'background-color: #dbeafe; color: #1d4ed8;',
                                            'no_show' => 'background-color: #f3f4f6; color: #4b5563;'
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pending',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                            'attended' => 'Attended',
                                            'no_show' => 'No Show'
                                        ];
                                        $statusColor = $statusColors[$event['status']] ?? 'background-color: #f3f4f6; color: #4b5563;';
                                        $statusLabel = $statusLabels[$event['status']] ?? 'Unknown';
                                        ?>
                                        <span style="display: inline-block; padding: 4px 8px; font-size: 12px; border-radius: 9999px; <?php echo $statusColor; ?>">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 15px;">
                                        <?php if (!empty($event['certificate_code'])): ?>
                                            <a href="<?php echo SITE_URL; ?>/volunteer-certificate.php?code=<?php echo $event['certificate_code']; ?>" target="_blank" style="color: #10b981; text-decoration: none;">
                                                View Certificate
                                            </a>
                                        <?php else: ?>
                                            <?php if ($event['status'] === 'attended'): ?>
                                                <span style="color: #6b7280;">Pending issuance</span>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">Not available</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>