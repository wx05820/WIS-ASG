<?php
session_start();
require_once '../_base.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../user/login.php');
    exit;
}

// Handle refund approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['orderID'])) {
    $orderID = $_POST['orderID'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    try {
        if ($action === 'approve') {
            // Try to update refund request status (if table exists)
            try {
                $updateRefundQuery = "UPDATE refund_requests SET status = 'approved', admin_notes = ?, processed_date = NOW(), processed_by = ? WHERE orderID = ?";
                $updateRefundStmt = $_db->prepare($updateRefundQuery);
                $updateRefundStmt->execute([$adminNotes, $_SESSION['user_id'], $orderID]);
            } catch (Exception $e) {
                error_log("Could not update refund_requests table: " . $e->getMessage());
            }
            
            // Update order status to Refunded
            $updateOrderQuery = "UPDATE `order` SET status = 'Refunded' WHERE orderID = ?";
            $updateOrderStmt = $_db->prepare($updateOrderQuery);
            $updateOrderStmt->execute([$orderID]);
            
            $_SESSION['success'] = "Refund approved for Order #$orderID";
            
        } elseif ($action === 'reject') {
            // Try to update refund request status (if table exists)
            try {
                $updateRefundQuery = "UPDATE refund_requests SET status = 'rejected', admin_notes = ?, processed_date = NOW(), processed_by = ? WHERE orderID = ?";
                $updateRefundStmt = $_db->prepare($updateRefundQuery);
                $updateRefundStmt->execute([$adminNotes, $_SESSION['user_id'], $orderID]);
            } catch (Exception $e) {
                error_log("Could not update refund_requests table: " . $e->getMessage());
            }
            
            // Update order status back to Delivered
            $updateOrderQuery = "UPDATE `order` SET status = 'Delivered' WHERE orderID = ?";
            $updateOrderStmt = $_db->prepare($updateOrderQuery);
            $updateOrderStmt->execute([$orderID]);
            
            $_SESSION['success'] = "Refund rejected for Order #$orderID";
        }
        
    } catch (Exception $e) {
        error_log("Refund management error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to process refund request.';
    }
    
    header('Location: refund_management.php');
    exit;
}

// Get pending refund requests (orders with Processing status)
$pendingQuery = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email 
                 FROM `order` o 
                 JOIN user u ON o.userID = u.userID 
                 WHERE o.status = 'Processing' 
                 ORDER BY o.orderDate DESC";
$pendingStmt = $_db->prepare($pendingQuery);
$pendingStmt->execute();
$pendingRefunds = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Get processed refund requests (orders with Refunded status)
$processedQuery = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email 
                   FROM `order` o 
                   JOIN user u ON o.userID = u.userID 
                   WHERE o.status = 'Refunded' 
                   ORDER BY o.orderDate DESC";
$processedStmt = $_db->prepare($processedQuery);
$processedStmt->execute();
$processedRefunds = $processedStmt->fetchAll(PDO::FETCH_ASSOC);

include '../header.php';
?>

<body class="admin-page">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-undo"></i> Refund Management</h2>
                    <a href="adminpage.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Admin
                    </a>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Pending Refund Requests -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Pending Refund Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingRefunds)): ?>
                            <p class="text-muted">No pending refund requests.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Order Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingRefunds as $refund): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($refund['orderID']) ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars($refund['username']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($refund['email']) ?></small>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($refund['orderDate'])) ?></td>
                                                <td><strong>RM <?= number_format($refund['total'], 2) ?></strong></td>
                                                <td><span class="badge bg-warning"><?= htmlspecialchars($refund['status']) ?></span></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="orderID" value="<?= htmlspecialchars($refund['orderID']) ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <div class="mb-2">
                                                            <textarea name="admin_notes" class="form-control form-control-sm" 
                                                                      placeholder="Admin notes (optional)" rows="2"></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-success btn-sm me-1" 
                                                                onclick="return confirm('Approve refund for Order #<?= htmlspecialchars($refund['orderID']) ?>?')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="orderID" value="<?= htmlspecialchars($refund['orderID']) ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <div class="mb-2">
                                                            <textarea name="admin_notes" class="form-control form-control-sm" 
                                                                      placeholder="Rejection reason" rows="2" required></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Reject refund for Order #<?= htmlspecialchars($refund['orderID']) ?>?')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Processed Refund Requests -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Processed Refund Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($processedRefunds)): ?>
                            <p class="text-muted">No processed refund requests.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Order Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($processedRefunds as $refund): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($refund['orderID']) ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars($refund['username']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($refund['email']) ?></small>
                                                </td>
                                                <td><strong>RM <?= number_format($refund['total'], 2) ?></strong></td>
                                                <td><?= date('M d, Y', strtotime($refund['orderDate'])) ?></td>
                                                <td><span class="badge bg-success"><?= htmlspecialchars($refund['status']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

<?php include '../footer.php'; ?>
