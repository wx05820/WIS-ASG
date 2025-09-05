<?php
require_once '../_base.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();

// Get user's order history
$stmt = $_db->prepare("
    SELECT o.*, p.payMethod, p.payStatus, p.payDate
    FROM `order` o
    LEFT JOIN payment p ON o.payID = p.payID
    WHERE o.userID = ?
    ORDER BY o.orderDate DESC, o.orderID DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Order History';
$skip_fontawesome = true; // Skip Font Awesome since this page doesn't use icons
include '../header.php';
?>

<link rel="stylesheet" href="../css/index.css">

<main class="container" style="padding: 20px 0;">
    <h1>Your Order History</h1>
    
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h3>No Orders Yet</h3>
            <p>You haven't placed any orders yet. Start shopping to see your order history here.</p>
            <a href="/userProduct/productList.php" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Order #<?= htmlspecialchars($order['orderID']) ?></h3>
                            <p class="order-date"><?= date('F j, Y', strtotime($order['orderDate'])) ?></p>
                        </div>
                        <div class="order-status">
                            <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <span><strong>Payment Method:</strong></span>
                            <span><?= htmlspecialchars($order['payMethod']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span><strong>Payment Status:</strong></span>
                            <span class="status-badge status-<?= strtolower($order['payStatus']) ?>">
                                <?= htmlspecialchars($order['payStatus']) ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span><strong>Shipping:</strong></span>
                            <span><?= ucfirst(htmlspecialchars($order['shipping_method'])) ?> Delivery</span>
                        </div>
                        <div class="detail-row">
                            <span><strong>Total:</strong></span>
                            <span class="order-total">RM <?= number_format($order['total'], 2) ?></span>
                        </div>
                    </div>
                    
                    <div class="order-actions">
                        <a href="/order/order_details.php?order_id=<?= htmlspecialchars($order['orderID']) ?>" 
                           class="btn btn-secondary">View Details</a>
                        <?php if ($order['status'] === 'Pending'): ?>
                            <button class="btn btn-danger" onclick="cancelOrder('<?= htmlspecialchars($order['orderID']) ?>')">
                                Cancel Order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<style>
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 40px 0;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #333;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.order-info h3 {
    margin: 0 0 5px 0;
    color: #333;
}

.order-date {
    color: #6c757d;
    margin: 0;
    font-size: 14px;
}

.order-details {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.detail-row:last-child {
    margin-bottom: 0;
}

.order-total {
    font-weight: 600;
    color: #28a745;
}

.order-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-shipped {
    background: #cce5ff;
    color: #004085;
}

.status-delivered {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.status-success {
    background: #d4edda;
    color: #155724;
}

.status-failed {
    background: #f8d7da;
    color: #721c24;
}

@media (max-width: 768px) {
    .order-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 2px;
    }
}
</style>

<script>
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        // Here you would typically make an AJAX call to cancel the order
        alert('Order cancellation feature will be implemented soon.');
    }
}
</script>

<?php include '../footer.php'; ?>