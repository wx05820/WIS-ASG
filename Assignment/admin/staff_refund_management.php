<?php
session_start();
require_once '../_base.php';

// Check if user is staff or admin
if (!isset($_SESSION['user_id']) || (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'staff'))) {
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
            // Update order status to Refunded
            $updateOrderQuery = "UPDATE `order` SET status = 'Refunded' WHERE orderID = ?";
            $updateOrderStmt = $_db->prepare($updateOrderQuery);
            $updateOrderStmt->execute([$orderID]);
            
            $_SESSION['success'] = "Refund approved for Order #$orderID";
            
        } elseif ($action === 'reject') {
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
    
    header('Location: staff_refund_management.php');
    exit;
}

// Get pending refund requests (orders with Processing status)
$pendingQuery = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email, u.phone
                 FROM `order` o 
                 JOIN user u ON o.userID = u.userID 
                 WHERE o.status = 'Processing' 
                 ORDER BY o.orderDate DESC";
$pendingStmt = $_db->prepare($pendingQuery);
$pendingStmt->execute();
$pendingRefunds = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Get processed refund requests (orders with Refunded status)
$processedQuery = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email, u.phone
                   FROM `order` o 
                   JOIN user u ON o.userID = u.userID 
                   WHERE o.status = 'Refunded' 
                   ORDER BY o.orderDate DESC";
$processedStmt = $_db->prepare($processedQuery);
$processedStmt->execute();
$processedRefunds = $processedStmt->fetchAll(PDO::FETCH_ASSOC);

// Get order items for pending refunds
$orderItems = [];
if (!empty($pendingRefunds)) {
    $orderIDs = array_column($pendingRefunds, 'orderID');
    $placeholders = str_repeat('?,', count($orderIDs) - 1) . '?';
    
    $itemsQuery = "SELECT oi.*, p.name, p.price, p.image1, p.prodID
                   FROM order_items oi 
                   JOIN product p ON oi.prodID = p.prodID 
                   WHERE oi.orderID IN ($placeholders)
                   ORDER BY oi.orderID, oi.order_item_id";
    
    $itemsStmt = $_db->prepare($itemsQuery);
    $itemsStmt->execute($orderIDs);
    $result = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $item) {
        $orderItems[$item['orderID']][] = $item;
    }
}

include '../header.php';
?>

<body class="admin-page">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-undo"></i> Staff Refund Management</h2>
                    <div>
                        <a href="adminpage.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Admin
                        </a>
                        <span class="badge bg-info">Staff Access</span>
                    </div>
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
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-clock"></i> Pending Refund Requests (<?= count($pendingRefunds) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingRefunds)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted">No pending refund requests.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingRefunds as $refund): ?>
                                <div class="card mb-3 border-warning">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="card-title">
                                                    <i class="fas fa-receipt"></i> Order #<?= htmlspecialchars($refund['orderID']) ?>
                                                    <span class="badge bg-warning ms-2">Processing</span>
                                                </h6>
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($refund['username']) ?></p>
                                                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($refund['email']) ?></p>
                                                        <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($refund['phone'] ?? 'N/A') ?></p>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <p class="mb-1"><strong>Order Date:</strong> <?= date('M d, Y H:i', strtotime($refund['orderDate'])) ?></p>
                                                        <p class="mb-1"><strong>Total Amount:</strong> <span class="text-primary fw-bold">RM <?= number_format($refund['total'], 2) ?></span></p>
                                                    </div>
                                                </div>
                                                
                                                <!-- Order Items -->
                                                <?php if (isset($orderItems[$refund['orderID']])): ?>
                                                    <div class="mt-3">
                                                        <h6><i class="fas fa-box"></i> Order Items:</h6>
                                                        <div class="row">
                                                            <?php foreach ($orderItems[$refund['orderID']] as $item): ?>
                                                                <div class="col-md-6 mb-2">
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>" 
                                                                             alt="<?= htmlspecialchars($item['name']) ?>" 
                                                                             class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                                        <div>
                                                                            <small class="fw-bold"><?= htmlspecialchars($item['name']) ?></small><br>
                                                                            <small class="text-muted">RM <?= number_format($item['price'], 2) ?> × <?= $item['qty'] ?></small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-grid gap-2">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="orderID" value="<?= htmlspecialchars($refund['orderID']) ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <div class="mb-2">
                                                            <textarea name="admin_notes" class="form-control form-control-sm" 
                                                                      placeholder="Approval notes (optional)" rows="2"></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-success btn-sm w-100" 
                                                                onclick="return confirm('Approve refund for Order #<?= htmlspecialchars($refund['orderID']) ?>?')">
                                                            <i class="fas fa-check"></i> Approve Refund
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="orderID" value="<?= htmlspecialchars($refund['orderID']) ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <div class="mb-2">
                                                            <textarea name="admin_notes" class="form-control form-control-sm" 
                                                                      placeholder="Rejection reason" rows="2" required></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-danger btn-sm w-100" 
                                                                onclick="return confirm('Reject refund for Order #<?= htmlspecialchars($refund['orderID']) ?>?')">
                                                            <i class="fas fa-times"></i> Reject Refund
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Processed Refund Requests -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-history"></i> Processed Refund Requests (<?= count($processedRefunds) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($processedRefunds)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No processed refund requests.</p>
                            </div>
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
