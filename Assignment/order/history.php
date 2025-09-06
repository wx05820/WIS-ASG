<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

// Only show orders with specific statuses
$allowedStatuses = ['Delivered', 'Cancelled', 'Refunded'];
$statusShow = implode(',', array_fill(0, count($allowedStatuses), '?'));

// Get orders
$ordersQuery = "SELECT o.*, 
                COUNT(oi.orderID) as item_count,
                SUM(oi.qty) as total_items
                FROM `order` o 
                LEFT JOIN order_items oi ON o.orderID = oi.orderID 
                WHERE o.userID = ? AND o.status IN ($statusShow)
                GROUP BY o.orderID 
                ORDER BY o.orderDate DESC, o.orderID DESC";

$ordersStmt = $_db->prepare($ordersQuery);
$ordersStmt->execute(array_merge([$userID], $allowedStatuses));
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get order items for each order
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

function formatDate($date) {
    return date('M d, Y - H:i', strtotime($date));
}

include '../header.php';
?>

<link rel="stylesheet" href="../css/history.css">
<script src="../js/userProduct.js" defer></script>

<body data-user-id="<?= $userID ?>">
    <div class="container mt-4">
        <div class="row">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-history"></i> Order History</h2>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back 
                    </a>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-shopping-bag"></i>
                        <h4>No Orders Found</h4>
                        <p>You don't have any completed orders yet.</p>                       
                    </div>
                <?php else: ?>
                    
                    <!-- Orders List -->
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <strong>Order #<?= htmlspecialchars($order['orderID']) ?></strong><br>
                                    <small class="text-muted"><?= formatDate($order['orderDate']) ?></small>
                                </div>
                                <div class="order-status">
                                    <span class="badge bg-<?= strtolower($order['status']) ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </div>
                                <div class="item-count">                                        
                                    <small class="text-muted">Total items: <?= $order['total_items'] ?> item(s)</small>
                                </div>                                                                   
                            </div>

                            <!-- Show View Items button only if more than 1 items -->
                            <?php if ($order['item_count'] > 1): ?>
                                <div class="items-toggle">
                                    <button class="btn btn-sm btn-outline-primary toggle-items" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#items-<?= $order['orderID'] ?>"
                                            aria-expanded="false">
                                        <i class="fas fa-chevron-down"></i> View Items (<?= $order['total_items'] ?>)
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- Order Items -->
                            <div class="order-items <?= $order['item_count'] > 1 ? 'collapse' : '' ?>" 
                                id="items-<?= $order['orderID'] ?>">
                                <?php if (isset($orderItems[$order['orderID']])): ?>
                                    <div class="items-list">
                                        <?php foreach ($orderItems[$order['orderID']] as $item): ?>
                                            <div class="item-row">
                                                <div class="item-image-container">
                                                    <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>" 
                                                            alt="<?= htmlspecialchars($item['name']) ?>" 
                                                            class="item-image">
                                                </div>
                                                <div class="item-details">
                                                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                                    <?php if (!empty($item['product_color'])): ?>
                                                        <div class="item-color">Color: <?= htmlspecialchars($item['product_color']) ?></div>
                                                    <?php endif; ?>
                                                    <div class="item-price">
                                                        $<?= number_format($item['price'], 2) ?> each
                                                        <span class="item-qty">× <?= $item['qty'] ?></span>
                                                    </div>
                                                </div>
                                                <div class="item-total">
                                                    <strong>$<?= number_format($item['price'] * $item['qty'], 2) ?></strong>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No items found for this order.</p>
                                <?php endif; ?>
                            </div>


                            <!-- Order Actions -->
                            <div class="order-actions">
                                <div class="row">
                                    <div class="total">
                                        <strong><?= money($order['total']) ?></strong><br>
                                    </div>                                    
                                    <div class="action-buttons">
                                        <a href="order_details.php?id=<?= $order['orderID'] ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <?php if ($order['status'] === 'Delivered'): ?>
                                            <form method="POST" action="reorder.php" style="display: inline;">
                                                <input type="hidden" name="orderID" value="<?= $order['orderID'] ?>">
                                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary reorder-btn" 
                                                        data-order="<?= $order['orderID'] ?>">
                                                    <i class="fas fa-redo"></i> Reorder
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="cancel_order.php" style="display: inline;">
                                            <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger cancel-order-btn" 
                                                    data-order="<?= htmlspecialchars($order['orderID']) ?>"
                                                    onclick="return confirm('Are you sure you want to cancel this order?')">
                                                <i class="fas fa-times"></i> Cancel Order
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/history.js"></script>
</body>
<?php include '../footer.php'; ?>