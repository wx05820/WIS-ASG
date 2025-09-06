<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

$orderID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$orderID) {
    $_SESSION['error'] = "Invalid order ID";
    header('Location: order_history.php');
    exit();
}

// Get order details with payment info
$orderQuery = "SELECT o.*, u.name as customer_name, u.email as customer_email,
                      p.payMethod, p.payStatus, p.payDate, p.amount as payment_amount,
                      p.transaction_id, p.payment_details
               FROM `order` o 
               JOIN users u ON o.userID = u.userID 
               LEFT JOIN payment p ON o.payID = p.payID
               WHERE o.orderID = ? AND o.userID = ?";

$orderStmt = $_db->prepare($orderQuery);
$orderStmt->execute([$orderID, $userID]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: order_history.php');
    exit();
}

// Get order items
$itemsQuery = "SELECT oi.*, p.name, p.price, p.image1, p.prodID
               FROM order_items oi 
               JOIN product p ON oi.prodID = p.prodID 
               WHERE oi.orderID = ?
               ORDER BY oi.order_item_id";

$itemsStmt = $_db->prepare($itemsQuery);
$itemsStmt->execute([$orderID]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get delivery status history
$statusHistory = [];
$historyQuery = "SELECT status, courier, notes, current_location, updated_at 
                 FROM deliverystatus 
                 WHERE orderID = ? 
                 ORDER BY updated_at DESC";
$historyStmt = $_db->prepare($historyQuery);
$historyStmt->execute([$orderID]);
$statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info', 
        'processing' => 'badge-primary',
        'shipped' => 'badge-secondary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    
    $class = $badges[$status] ?? 'badge-secondary';
    return "<span class='badge {$class}'>" . ucfirst($status) . "</span>";
}

function formatDate($date) {
    return date('M d, Y - H:i A', strtotime($date));
}

function formatAddress($order) {
    $address = [];
    if (!empty($order['unitNo'])) $address[] = $order['unitNo'];
    if (!empty($order['address_line_1'])) $address[] = $order['address_line_1'];
    if (!empty($order['address_line_2'])) $address[] = $order['address_line_2'];
    if (!empty($order['city'])) $address[] = $order['city'];
    if (!empty($order['postcode'])) $address[] = $order['postcode'];
    if (!empty($order['state'])) $address[] = $order['state'];
    
    return implode('<br>', $address);
}

if (isset($_SESSION['success'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            showSuccess("'.htmlspecialchars($_SESSION['success']).'");
        });
        </script>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            showError("'.htmlspecialchars($_SESSION['error']).'");
        });
        </script>';
    unset($_SESSION['error']);
}

include '../header.php';
?>

<link rel="stylesheet" href="../css/history.css">

<body data-user-id="<?= $userID ?>">
    <div class="container mt-4">
        <!-- Print Button -->
        <button onclick="window.print()" class="btn btn-outline-primary print-btn no-print">
            <i class="fas fa-print"></i> Print Order
        </button>
        
        <!-- Header -->
        <div class="row no-print">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-receipt"></i> Order Details</h2>
                    <a href="order_history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="order-summary">
            <div class="row">
                <div class="col-md-6">
                    <h4>Order #<?= $order['orderID'] ?></h4>
                    <p class="mb-2">
                        <strong>Date:</strong> <?= formatDate($order['orderDate']) ?><br>
                        <strong>Status:</strong> <?= getStatusBadge($order['status']) ?><br>
                        <strong>Shipping Method:</strong> <?= ucfirst($order['shipping_method'] ?? 'Standard') ?><br>
                        <strong>Payment Method:</strong> <?= ucfirst($order['payMethod'] ?? 'N/A') ?><br>
                        <strong>Payment Status:</strong> <?= ucfirst($order['payStatus'] ?? 'N/A') ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <h6>Customer Information</h6>
                    <p class="mb-0">
                        <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                        <?= htmlspecialchars($order['customer_email']) ?><br>
                        Phone: <?= htmlspecialchars($order['phoneNo'] ?? 'N/A') ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Items -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shopping-bag"></i> Order Items (<?= count($items) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($items as $item): ?>
                            <div class="item-card">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>" 
                                             alt="<?= htmlspecialchars($item['product_name'] ?? $item['name']) ?>" 
                                             class="item-image">
                                    </div>
                                    <div class="col">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['product_name'] ?? $item['name']) ?></h6>
                                        <?php if (!empty($item['product_color'])): ?>
                                            <p class="mb-1 text-muted">Color: <?= htmlspecialchars($item['product_color']) ?></p>
                                        <?php endif; ?>
                                        <p class="mb-1 text-muted">
                                            Price: $<?= number_format($item['price'], 2) ?> × <?= $item['qty'] ?>
                                        </p>
                                        <small class="text-muted">Product ID: #<?= $item['prodID'] ?></small>
                                    </div>
                                    <div class="col-auto text-end">
                                        <strong class="h6">$<?= number_format($item['price'] * $item['qty'], 2) ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Order Totals -->
                        <div class="border-top pt-3 mt-3">
                            <div class="row">
                                <div class="col-6 text-end">
                                    <p class="mb-1">Subtotal:</p>
                                    <?php if ($order['shipping_fee'] > 0): ?>
                                        <p class="mb-1">Shipping Fee:</p>
                                    <?php endif; ?>
                                    <?php if ($order['discount'] > 0): ?>
                                        <p class="mb-1">Discount:</p>
                                    <?php endif; ?>
                                    <strong>Total Amount:</strong>
                                </div>
                                <div class="col-6 text-end">
                                    <p class="mb-1">$<?= number_format($order['subtotals'], 2) ?></p>
                                    <?php if ($order['shipping_fee'] > 0): ?>
                                        <p class="mb-1">$<?= number_format($order['shipping_fee'], 2) ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['discount'] > 0): ?>
                                        <p class="mb-1 text-success">-$<?= number_format($order['discount'], 2) ?></p>
                                    <?php endif; ?>
                                    <strong class="h5 text-primary">$<?= number_format($order['total'], 2) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Information -->
            <div class="col-lg-4">
                <!-- Shipping Address -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-shipping-fast"></i> Shipping Address</h6>
                    </div>
                    <div class="card-body">
                        <div class="address-box">
                            <strong><?= htmlspecialchars($order['recipient_name']) ?></strong><br>
                            <?= formatAddress($order) ?>
                        </div>
                        <?php if (!empty($order['notes'])): ?>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <strong>Notes:</strong> <?= htmlspecialchars($order['notes']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if (!empty($order['payMethod'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="fas fa-credit-card"></i> Payment Details</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Method:</strong> <?= ucfirst($order['payMethod']) ?></p>
                            <p class="mb-1"><strong>Status:</strong> <?= getStatusBadge($order['payStatus']) ?></p>
                            <?php if (!empty($order['payDate'])): ?>
                                <p class="mb-1"><strong>Date:</strong> <?= formatDate($order['payDate']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order['transaction_id'])): ?>
                                <p class="mb-1"><strong>Transaction ID:</strong> <?= htmlspecialchars($order['transaction_id']) ?></p>
                            <?php endif; ?>
                            <p class="mb-0"><strong>Amount:</strong> $<?= number_format($order['payment_amount'] ?? $order['total'], 2) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Actions -->
                <div class="card mb-4 no-print">
                    <div class="card-header">
                        <h6><i class="fas fa-cog"></i> Actions</h6>
                    </div>
                    <div class="card-body">
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
                        
                        <?php if ($order['status'] === 'delivered'): ?>
                            <form method="POST" action="../order/reorder.php">
                                <input type="hidden" name="orderID" value="<?= $order['orderID'] ?>">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                <button type="submit" class="btn btn-primary w-100 mb-2 reorder-btn" 
                                        data-order="<?= $order['orderID'] ?>">
                                    <i class="fas fa-redo"></i> Reorder Items
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                            <form method="POST" action="../order/cancel_order.php">
                                <input type="hidden" name="orderID" value="<?= $order['orderID'] ?>">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                <button type="submit" class="btn btn-danger w-100 mb-2 cancel-order-btn" 
                                        data-order="<?= $order['orderID'] ?>">
                                    <i class="fas fa-times"></i> Cancel Order
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <button onclick="window.print()" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-print"></i> Print Order
                        </button>
                    </div>
                </div>

                <!-- Delivery Status History -->
                <?php if (!empty($statusHistory)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-truck"></i> Delivery Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="status-timeline">
                                <?php foreach ($statusHistory as $history): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-content">
                                            <strong><?= ucfirst($history['status']) ?></strong>
                                            <?php if (!empty($history['courier'])): ?>
                                                <br><small>Courier: <?= htmlspecialchars($history['courier']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($history['current_location'])): ?>
                                                <br><small>Location: <?= htmlspecialchars($history['current_location']) ?></small>
                                            <?php endif; ?>
                                            <br><small class="text-muted">
                                                <?= formatDate($history['updated_at']) ?>
                                            </small>
                                            <?php if (!empty($history['notes'])): ?>
                                                <br><small><?= htmlspecialchars($history['notes']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/history.js"></script>
    <script src="../js/userProduct.js"></script>
</body>
<?php include '../footer.php'; ?>