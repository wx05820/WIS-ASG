<?php
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

// Track shipping statuses
$trackingStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped'];
$statusPlaceholders = implode(',', array_fill(0, count($trackingStatuses), '?'));

// Orders
$ordersQuery = "SELECT o.*, 
                COUNT(oi.orderID) as item_count,
                SUM(oi.qty) as total_items
                FROM `order` o 
                LEFT JOIN order_items oi ON o.orderID = oi.orderID 
                WHERE o.userID = ? AND o.status IN ($statusPlaceholders)
                GROUP BY o.orderID 
                ORDER BY FIELD(o.status, 'Pending', 'Confirmed', 'Processing', 'Shipped'), 
                         o.orderDate DESC, o.orderID DESC";

$ordersStmt = $_db->prepare($ordersQuery);
$ordersStmt->execute(array_merge([$userID], $trackingStatuses));
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Order items
$orderItems = [];
if (!empty($orders)) {
    $orderIDs = array_column($orders, 'orderID');
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

// Delivery history
$deliveryHistory = [];
if (!empty($orders)) {
    $orderIDs = array_column($orders, 'orderID');
    $placeholders = str_repeat('?,', count($orderIDs) - 1) . '?';
    
    $historyQuery = "SELECT orderID, status, courier, notes, current_location, updated_at 
                     FROM deliverystatus 
                     WHERE orderID IN ($placeholders)
                     ORDER BY orderID, updated_at DESC";
    
    $historyStmt = $_db->prepare($historyQuery);
    $historyStmt->execute($orderIDs);
    $historyResult = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($historyResult as $history) {
        $deliveryHistory[$history['orderID']][] = $history;
    }
}

function formatDate($date) {
    return date('M d, Y - H:i', strtotime($date));
}

function getStatusBadgeClass($status) {
    $statusClasses = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'primary',
        'shipped' => 'secondary',
        'delivered' => 'success'
    ];
    return $statusClasses[strtolower($status)] ?? 'secondary';
}

function getStatusIcon($status) {
    $statusIcons = [
        'pending' => 'fas fa-clock',
        'confirmed' => 'fas fa-check-circle',
        'processing' => 'fas fa-cog fa-spin',
        'shipped' => 'fas fa-shipping-fast',
        'delivered' => 'fas fa-box-open'
    ];
    return $statusIcons[strtolower($status)] ?? 'fas fa-info-circle';
}

function getTrackingProgress($status) {
    $statusOrder = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    $currentIndex = array_search(strtolower($status), $statusOrder);
    return $currentIndex !== false ? (($currentIndex + 1) / count($statusOrder)) * 100 : 0;
}

include '../header.php';
?>

<link rel="stylesheet" href="../css/tracking.css">
<script src="../js/userProduct.js" defer></script>

<body data-user-id="<?= $userID ?>">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-truck"></i> Track Your Orders</h2>
        <a href="../index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back 
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <i class="fas fa-truck"></i>
            <h4>No Active Orders</h4>
            <p>You don't have any orders to track at the moment.</p>                       
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="tracking-card">
                <!-- Header -->
                <div class="tracking-header">
                    <div>
                        <h5>Order #<?= htmlspecialchars($order['orderID']) ?></h5>
                        <small class="text-muted">Ordered: <?= formatDate($order['orderDate']) ?></small>
                    </div>
                    <div class="order-status">
                        <span class="badge bg-<?= getStatusBadgeClass($order['status']) ?>">
                            <i class="<?= getStatusIcon($order['status']) ?>"></i>
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Progress + Timeline -->
                <div class="tracking-progress">
                    <div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-<?= getStatusBadgeClass($order['status']) ?>" 
                                role="progressbar" 
                                style="width: <?= getTrackingProgress($order['status']) ?>%">
                                <?= round(getTrackingProgress($order['status'])) ?>%
                            </div>
                        </div>
                    </div>
                    <div class="status-timeline">
                        <?php 
                        $allStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
                        $currentStatusIndex = array_search(strtolower($order['status']), $allStatuses);
                        ?>
                        <?php foreach ($allStatuses as $index => $status): ?>
                            <div class="timeline-step <?= $index <= $currentStatusIndex ? 'completed' : 'pending' ?>">
                                <div class="timeline-icon">
                                    <i class="<?= getStatusIcon($status) ?>"></i>
                                </div>
                                <div class="timeline-label"><?= ucfirst($status) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Delivery History -->
                <?php if (!empty($deliveryHistory[$order['orderID']])): ?>
                    <div class="delivery-history">
                        <button class="btn btn-sm btn-outline-info toggle-history" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#history-<?= $order['orderID'] ?>">
                            <i class="fas fa-history"></i> View Tracking History
                        </button>
                        
                        <div class="collapse mt-3" id="history-<?= $order['orderID'] ?>">
                            <?php foreach ($deliveryHistory[$order['orderID']] as $history): ?>
                                <div class="history-item">
                                    <div class="history-status">
                                        <strong><?= $history['status'] ?></strong>
                                        <span class="history-time"><?= formatDate($history['updated_at']) ?></span>
                                    </div>
                                    <?php if (!empty($history['current_location'])): ?>
                                        <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($history['current_location']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($history['courier'])): ?>
                                        <div><i class="fas fa-user"></i> Courier: <?= htmlspecialchars($history['courier']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($history['notes'])): ?>
                                        <div><em><?= htmlspecialchars($history['notes']) ?></em></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Items -->
                <?php if ($order['item_count'] > 1): ?>
                    <button class="btn btn-sm btn-outline-primary toggle-items mt-3" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#items-<?= $order['orderID'] ?>">
                        <i class="fas fa-chevron-down"></i> View Items (<?= $order['total_items'] ?>)
                    </button>
                <?php endif; ?>

                <div class="order-items <?= $order['item_count'] > 1 ? 'collapse' : '' ?> mt-3" 
                     id="items-<?= $order['orderID'] ?>">
                    <?php if (isset($orderItems[$order['orderID']])): ?>
                        <div class="items-list">
                            <?php foreach ($orderItems[$order['orderID']] as $item): ?>
                                <div class="item-row">
                                    <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>"
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="item-image">
                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <?php if (!empty($item['product_color'])): ?>
                                            <div>Color: <?= htmlspecialchars($item['product_color']) ?></div>
                                        <?php endif; ?>
                                        <div>$<?= number_format($item['price'], 2) ?> × <?= $item['qty'] ?></div>
                                    </div>
                                    <div class="item-total">
                                        <strong>$<?= number_format($item['price'] * $item['qty'], 2) ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="order-actions">
                    <div>
                        <strong>Total: <?= money($order['total']) ?></strong><br>
                        <small class="text-muted"><?= $order['total_items'] ?> item(s)</small>
                    </div>
                    <div class="action-buttons">
                        <a href="../order/order_details.php?id=<?= $order['orderID'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($order['status'] === 'delivered'): ?>
                            <form method="POST" action="../order/reorder.php" style="display:inline;">
                                <input type="hidden" name="orderID" value="<?= $order['orderID'] ?>">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-redo"></i> Reorder
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/shipping.js"></script>
</body>
<?php include '../footer.php'; ?>
