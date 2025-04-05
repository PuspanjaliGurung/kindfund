<?php
require_once '../includes/admin-auth.php';
require_once '../includes/header.php';
require_once '../includes/admin-header.php';

// Database connection
require_once '../config/db.php';

// Get campaign ID
$campaignId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$campaignId) {
    header('Location: campaigns.php');
    exit;
}

// Get campaign data with charity info
$stmt = $pdo->prepare("
    SELECT c.*, ch.charity_name 
    FROM campaigns c
    JOIN charities ch ON c.charity_id = ch.charity_id
    WHERE c.campaign_id = ?
");
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch();

if (!$campaign) {
    header('Location: campaigns.php');
    exit;
}

// Get campaign categories
$stmt = $pdo->prepare("
    SELECT cat.category_name 
    FROM campaign_categories cc
    JOIN categories cat ON cc.category_id = cat.category_id
    WHERE cc.campaign_id = ?
");
$stmt->execute([$campaignId]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get donations for this campaign
$stmt = $pdo->prepare("
    SELECT d.*, u.username 
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.campaign_id = ?
    ORDER BY d.donation_date DESC
");
$stmt->execute([$campaignId]);
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>Campaign Details</h2>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="edit-campaign.php?id=<?= $campaignId ?>" class="btn btn-sm btn-primary me-2">Edit</a>
                    <a href="campaigns.php" class="btn btn-sm btn-secondary">Back to List</a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <h3><?= htmlspecialchars($campaign['title']) ?></h3>
                    <p>By <?= htmlspecialchars($campaign['charity_name']) ?></p>
                    
                    <div class="mb-4">
                        <p><?= nl2br(htmlspecialchars($campaign['description'])) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h5>Categories</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($categories as $category): ?>
                            <span class="badge bg-primary"><?= htmlspecialchars($category) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Goal Amount</h5>
                                    <p class="card-text">Rs. <?= number_format($campaign['goal_amount'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Raised Amount</h5>
                                    <p class="card-text">Rs. <?= number_format($campaign['current_amount'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Start Date</h5>
                                    <p class="card-text"><?= date('F j, Y', strtotime($campaign['start_date'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">End Date</h5>
                                    <p class="card-text"><?= date('F j, Y', strtotime($campaign['end_date'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="progress" style="height: 30px;">
                            <?php 
                            $percentage = $campaign['goal_amount'] > 0 
                                ? min(100, ($campaign['current_amount'] / $campaign['goal_amount']) * 100) 
                                : 0;
                            ?>
                            <div class="progress-bar progress-bar-striped bg-success" 
                                 role="progressbar" 
                                 style="width: <?= $percentage ?>%" 
                                 aria-valuenow="<?= $percentage ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?= number_format($percentage, 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Campaign Status</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                Status: <span class="badge bg-<?= $campaign['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $campaign['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </p>
                            <p class="card-text">
                                Created: <?= date('F j, Y', strtotime($campaign['creation_date'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($campaign['campaign_image']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Campaign Image</h5>
                        </div>
                        <div class="card-body text-center">
                            <img src="../uploads/campaigns/<?= htmlspecialchars($campaign['campaign_image']) ?>" 
                                 alt="Campaign Image" class="img-fluid">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-5">
                <h4>Recent Donations</h4>
                
                <?php if (empty($donations)): ?>
                <p>No donations yet for this campaign.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Donor</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($donation['donation_date'])) ?></td>
                                <td><?= $donation['is_anonymous'] ? 'Anonymous' : htmlspecialchars($donation['username']) ?></td>
                                <td>Rs. <?= number_format($donation['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($donation['payment_method']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>