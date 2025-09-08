<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

// Only show orders with "Received" status (completed orders)
$allowedStatuses = ['Received'];
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

// Set page-specific CSS
$page_css = '../css/history.css';
$page_js = '../js/history.js';

// Debug: Check if CSS file exists
if (!file_exists('../css/history.css')) {
    error_log('History CSS file not found: ../css/history.css');
}

include '../header.php';
?>

<body class="history-page" data-user-id="<?= $userID ?>">
<!-- Fallback CSS link to ensure history styles are loaded -->
<link rel="stylesheet" href="../css/history.css">

    <div class="container mt-4">
        <!-- Banner -->
        <div class="history-banner">
            <div class="banner-content">
                <div class="banner-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="banner-text">
                    <h2>Order History</h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div>

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

                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-shopping-bag"></i>
                        <h4>No Orders Found</h4>
                        <p>You don't have any completed orders yet.</p>                       
                    </div>
                <?php else: ?>
                    
                    <!-- Orders List -->
                    <?php foreach ($orders as $order): ?>
                        <div class="tracking-card">
                            <!-- Order Header -->
                            <div class="order-header">
                                <div class="order-info">
                                    <h2>Order #<?= htmlspecialchars($order['orderID']) ?></h2>
                                    <p class="order-date"><?= formatDate($order['orderDate']) ?></p>
                                </div>
                                <div class="order-status">
                                    <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                        <i class="fas fa-<?= strtolower($order['status']) === 'delivered' ? 'check-circle' : (strtolower($order['status']) === 'processing' ? 'cog' : (strtolower($order['status']) === 'cancelled' ? 'times-circle' : 'info-circle')) ?>"></i>
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Product Section -->
                            <div class="product-section">
                                <?php if (isset($orderItems[$order['orderID']])): ?>
                                    <?php $firstItem = $orderItems[$order['orderID']][0]; ?>
                                    <div class="first-item">
                                        <div class="item-image">
                                            <img src="<?= !empty($firstItem['image1']) ? 'data:image/jpeg;base64,'.base64_encode($firstItem['image1']) : '../images/placeholder.jpg' ?>"
                                                 alt="<?= htmlspecialchars($firstItem['name']) ?>">
                                        </div>
                                        <div class="item-details">
                                            <div class="item-info">
                                                <p class="item-id">Product ID: #<?= $firstItem['prodID'] ?></p>
                                                <h4><?= htmlspecialchars($firstItem['name']) ?></h4>
                                                <?php if (!empty($firstItem['product_color'])): ?>
                                                    <p class="item-color">Color: <?= htmlspecialchars($firstItem['product_color']) ?></p>
                                                <?php endif; ?>
                                                <p class="item-price-qty">Price: <?= money($firstItem['price']) ?> × <?= $firstItem['qty'] ?></p>
                                            </div>
                                            <div class="item-subtotal">
                                                <strong><?= money($firstItem['price'] * $firstItem['qty']) ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (count($orderItems[$order['orderID']]) > 1): ?>
                                        <div class="view-more-section">
                                            <button class="btn btn-outline-primary view-more-btn" onclick="toggleAllItems('<?= $order['orderID'] ?>')">
                                                <i class="fas fa-chevron-down"></i> View More Products (<?= count($orderItems[$order['orderID']]) - 1 ?> more)
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- All Items (Hidden by default) -->
                                    <div class="all-items" id="allItems_<?= $order['orderID'] ?>" style="display: none;">
                                        <?php foreach ($orderItems[$order['orderID']] as $index => $item): ?>
                                            <?php if ($index > 0): ?>
                                                <div class="item-card">
                                                    <div class="item-image">
                                                        <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>" 
                                                             alt="<?= htmlspecialchars($item['name']) ?>">
                                                    </div>
                                                    <div class="item-details">
                                                        <div class="item-info">
                                                            <p class="item-id">Product ID: #<?= $item['prodID'] ?></p>
                                                            <h6><?= htmlspecialchars($item['name']) ?></h6>
                                                            <?php if (!empty($item['product_color'])): ?>
                                                                <p class="item-color">Color: <?= htmlspecialchars($item['product_color']) ?></p>
                                                            <?php endif; ?>
                                                            <p class="item-price-qty">Price: <?= money($item['price']) ?> × <?= $item['qty'] ?></p>
                                                        </div>
                                                        <div class="item-subtotal">
                                                            <strong><?= money($item['price'] * $item['qty']) ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- View Details Button -->
                            <div class="view-details-section">
                                <a href="history_details.php?id=<?= htmlspecialchars($order['orderID']) ?>" class="btn btn-primary view-details-btn">
                                    <i class="fas fa-info-circle"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
    function toggleAllItems(orderID) {
        const allItems = document.getElementById('allItems_' + orderID);
        const viewMoreBtn = document.querySelector(`[onclick="toggleAllItems('${orderID}')"]`);
        const icon = viewMoreBtn.querySelector('i');
        
        if (allItems.style.display === 'none') {
            allItems.style.display = 'block';
            icon.className = 'fas fa-chevron-up';
            viewMoreBtn.innerHTML = '<i class="fas fa-chevron-up"></i> View Less';
        } else {
            allItems.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
            const itemCount = allItems.querySelectorAll('.item-card').length;
            viewMoreBtn.innerHTML = `<i class="fas fa-chevron-down"></i> View More Products (${itemCount} more)`;
        }
    }
</script>

</body>
<?php include '../footer.php'; ?>
