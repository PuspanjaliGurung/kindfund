<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin-auth.php';

// Check if user is admin
requireAdmin();

$conn = getDBConnection();

// Get report type and date range
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'donations';
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Report data
$reportData = [];
$totalAmount = 0;
$totalCount = 0;

// Generate report based on type
switch ($reportType) {
    case 'donations':
        // Get donations data
        $query = "
            SELECT 
                d.donation_id, d.amount, d.donation_date, d.is_anonymous,
                u.username, u.first_name, u.last_name,
                c.title as campaign_title
            FROM donations d
            JOIN users u ON d.user_id = u.user_id
            JOIN campaigns c ON d.campaign_id = c.campaign_id
            WHERE d.donation_date BETWEEN ? AND ?
            ORDER BY d.donation_date DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDateParam, $endDateParam);
        $startDateParam = $startDate . ' 00:00:00';
        $endDateParam = $endDate . ' 23:59:59';
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
            $totalAmount += $row['amount'];
        }
        $totalCount = count($reportData);
        break;
        
    case 'volunteers':
        // Get volunteers data
        $query = "
            SELECT 
                vr.registration_id, vr.registration_date, vr.status,
                u.first_name, u.last_name, u.email,
                ve.title as event_title, ve.event_date
            FROM volunteer_registrations vr
            JOIN users u ON vr.user_id = u.user_id
            JOIN volunteer_events ve ON vr.event_id = ve.event_id
            WHERE vr.registration_date BETWEEN ? AND ?
            ORDER BY vr.registration_date DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDateParam, $endDateParam);
        $startDateParam = $startDate . ' 00:00:00';
        $endDateParam = $endDate . ' 23:59:59';
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        $totalCount = count($reportData);
        break;
        
    case 'campaigns':
        // Get campaigns data
        $query = "
            SELECT 
                c.campaign_id, c.title, c.goal_amount, c.current_amount, c.creation_date, c.end_date,
                (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.campaign_id) as donation_count
            FROM campaigns c
            WHERE c.creation_date BETWEEN ? AND ?
            ORDER BY c.creation_date DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDateParam, $endDateParam);
        $startDateParam = $startDate . ' 00:00:00';
        $endDateParam = $endDate . ' 23:59:59';
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
            $totalAmount += $row['current_amount'];
        }
        $totalCount = count($reportData);
        break;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'kindfund_' . $reportType . '_report_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers based on report type
    switch ($reportType) {
        case 'donations':
            fputcsv($output, ['ID', 'Donor', 'Amount', 'Date', 'Campaign']);
            foreach ($reportData as $row) {
                $donor = $row['is_anonymous'] ? 'Anonymous' : ($row['first_name'] . ' ' . $row['last_name']);
                fputcsv($output, [
                    $row['donation_id'],
                    $donor,
                    $row['amount'],
                    $row['donation_date'],
                    $row['campaign_title']
                ]);
            }
            break;
            
        case 'volunteers':
            fputcsv($output, ['ID', 'Volunteer Name', 'Email', 'Event', 'Registration Date', 'Status']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['registration_id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email'],
                    $row['event_title'],
                    $row['registration_date'],
                    $row['status']
                ]);
            }
            break;
            
        case 'campaigns':
            fputcsv($output, ['ID', 'Title', 'Goal', 'Current', 'Created', 'Ends', 'Donations']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['campaign_id'],
                    $row['title'],
                    $row['goal_amount'],
                    $row['current_amount'],
                    $row['creation_date'],
                    $row['end_date'],
                    $row['donation_count']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// Include the admin header
include 'includes/admin-header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h1 style="font-size: 24px; font-weight: bold;">Reports</h1>
        
        <?php if (!empty($reportData)): ?>
            <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'export=csv'; ?>" 
                style="background-color: #22c55e; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                Export to CSV
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Report Type and Filters -->
    <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px;">
        <form action="" method="GET">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">
                <div>
                    <label for="report_type" style="display: block; margin-bottom: 5px; font-weight: bold;">Report Type</label>
                    <select id="report_type" name="report_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="donations" <?php echo $reportType === 'donations' ? 'selected' : ''; ?>>Donations Report</option>
                        <option value="volunteers" <?php echo $reportType === 'volunteers' ? 'selected' : ''; ?>>Volunteers Report</option>
                        <option value="campaigns" <?php echo $reportType === 'campaigns' ? 'selected' : ''; ?>>Campaigns Report</option>
                    </select>
                </div>
                
                <div>
                    <label for="start_date" style="display: block; margin-bottom: 5px; font-weight: bold;">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div>
                    <label for="end_date" style="display: block; margin-bottom: 5px; font-weight: bold;">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            
            <div style="text-align: right;">
                <button type="submit" style="background-color: #5D5CDE; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
    
    <!-- Report Summary -->
    <?php if (!empty($reportData)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <?php if ($reportType === 'donations' || $reportType === 'campaigns'): ?>
                <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">Total <?php echo ucfirst($reportType); ?> Amount</h3>
                    <p style="font-size: 24px; font-weight: bold; color: #22c55e;"><?php echo formatCurrency($totalAmount); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
                <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">Total <?php echo ucfirst($reportType); ?> Count</h3>
                <p style="font-size: 24px; font-weight: bold; color: #5D5CDE;"><?php echo $totalCount; ?></p>
            </div>
            
            <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
                <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">Report Period</h3>
                <p style="font-size: 18px; color: #4b5563;">
                    <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                </p>
            </div>
        </div>
        
        <!-- Report Data Table -->
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px;">
            <div style="padding: 15px 20px; border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 18px; font-weight: bold;">
                    <?php echo ucfirst($reportType); ?> Report Data
                </h2>
            </div>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background-color: #f3f4f6;">
                        <tr>
                            <?php if ($reportType === 'donations'): ?>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">ID</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Donor</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Amount</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Date</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Campaign</th>
                            <?php elseif ($reportType === 'volunteers'): ?>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">ID</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Volunteer</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Event</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Date</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Status</th>
                            <?php elseif ($reportType === 'campaigns'): ?>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">ID</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Title</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Created</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Ends</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 14px; color: #6b7280;">Progress</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reportType === 'donations'): ?>
                            <?php foreach ($reportData as $row): ?>
                                <tr style="border-top: 1px solid #e5e7eb;">
                                    <td style="padding: 12px 16px;"><?php echo $row['donation_id']; ?></td>
                                    <td style="padding: 12px 16px;">
                                        <?php echo $row['is_anonymous'] ? 'Anonymous' : htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </td>
                                    <td style="padding: 12px 16px; font-weight: bold;"><?php echo formatCurrency($row['amount']); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo date('M d, Y H:i', strtotime($row['donation_date'])); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo htmlspecialchars($row['campaign_title']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif ($reportType === 'volunteers'): ?>
                            <?php foreach ($reportData as $row): ?>
                                <tr style="border-top: 1px solid #e5e7eb;">
                                    <td style="padding: 12px 16px;"><?php echo $row['registration_id']; ?></td>
                                    <td style="padding: 12px 16px;">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </td>
                                    <td style="padding: 12px 16px;"><?php echo htmlspecialchars($row['event_title']); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo date('M d, Y H:i', strtotime($row['registration_date'])); ?></td>
                                    <td style="padding: 12px 16px;">
                                        <span style="padding: 4px 8px; font-size: 12px; border-radius: 9999px; 
                                            <?php 
                                            switch($row['status']) {
                                                case 'pending': echo 'background-color: #fef3c7; color: #92400e;'; break;
                                                case 'approved': echo 'background-color: #d1fae5; color: #047857;'; break;
                                                case 'rejected': echo 'background-color: #fee2e2; color: #b91c1c;'; break;
                                                case 'attended': echo 'background-color: #dbeafe; color: #1d4ed8;'; break;
                                                case 'no_show': echo 'background-color: #f3f4f6; color: #4b5563;'; break;
                                                default: echo 'background-color: #f3f4f6; color: #4b5563;';
                                            }
                                            ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif ($reportType === 'campaigns'): ?>
                            <?php foreach ($reportData as $row): ?>
                                <?php $progress = ($row['goal_amount'] > 0) ? ($row['current_amount'] / $row['goal_amount']) * 100 : 0; ?>
                                <tr style="border-top: 1px solid #e5e7eb;">
                                    <td style="padding: 12px 16px;"><?php echo $row['campaign_id']; ?></td>
                                    <td style="padding: 12px 16px;"><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo date('M d, Y', strtotime($row['creation_date'])); ?></td>
                                    <td style="padding: 12px 16px;"><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                    <td style="padding: 12px 16px;">
                                        <div>
                                            <div style="width: 100%; background-color: #e5e7eb; height: 8px; border-radius: 4px; margin-bottom: 4px;">
                                                <div style="width: <?php echo min(100, $progress); ?>%; background-color: #5D5CDE; height: 8px; border-radius: 4px;"></div>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; font-size: 12px;">
                                                <span><?php echo formatCurrency($row['current_amount']); ?></span>
                                                <span><?php echo round($progress, 1); ?>%</span>
                                                <span><?php echo formatCurrency($row['goal_amount']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div style="background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 30px; text-align: center;">
            <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">No Report Data</h3>
            <p style="color: #6b7280; margin-bottom: 20px;">
                <?php if (empty($_GET) || ($_GET['report_type'] ?? '') === ''): ?>
                    Please select a report type and apply filters to generate a report.
                <?php else: ?>
                    No data found for the selected filters. Try changing your criteria.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php
// Include the admin footer
include 'includes/admin-footer.php';
?>