<?php
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

// Track shipping statuses (include Delivered but exclude Received - those go to history)
$trackingStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered'];
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

// Debug: Log order count and user ID
error_log("Tracking page - User ID: " . $userID);
error_log("Tracking page - Orders found: " . count($orders));
if (!empty($orders)) {
    error_log("Tracking page - First order: " . print_r($orders[0], true));
}

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

$deliveryHistory = [];
if (!empty($orders)) {
    $orderIDs = array_column($orders, 'orderID');
    $placeholders = str_repeat('?,', count($orderIDs) - 1) . '?';
    
    // Check if deliverystatus table exists
    $tableCheck = $_db->query("SHOW TABLES LIKE 'deliverystatus'");
    if ($tableCheck->rowCount() > 0) {
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
    } else {
        // Create sample delivery history if table doesn't exist
        foreach ($orders as $order) {
            $deliveryHistory[$order['orderID']][] = [
                'status' => $order['status'],
                'courier' => 'System',
                'notes' => 'Order status: ' . $order['status'],
                'current_location' => 'Processing Center',
                'updated_at' => $order['orderDate']
            ];
        }
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

// Set page-specific CSS
$page_css = '../css/tracking.css';
$page_js = '../js/shipping.js';

// Debug: Check if CSS file exists
if (!file_exists('../css/tracking.css')) {
    error_log('Tracking CSS file not found: ../css/tracking.css');
}

include '../header.php';
?>

<body class="tracking-page" data-user-id="<?= $userID ?>">
<!-- Fallback CSS link to ensure tracking styles are loaded -->
<link rel="stylesheet" href="../css/tracking.css">
<link rel="stylesheet" href="../css/index.css">


<div class="container mt-4" style="max-width: 1200px; margin: 0 auto; padding: 2rem 1rem;">
    <div class="tracking-banner">
        <div class="banner-content">
            <div class="banner-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="banner-text">
                <h2>Track Your Orders</h2>
            </div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div class="no-orders" style="background: white; padding: 4rem 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; border: 1px solid #e9ecef;">
            <i class="fas fa-truck" style="font-size: 4rem; color: #d4af8c; margin-bottom: 1rem;"></i>
            <h4 style="color: #8B4513; font-weight: 600; margin-bottom: 1rem;">No Active Orders</h4>
            <p style="color: #666; font-size: 1.1rem;">You don't have any orders to track at the moment.</p>
            <div style="margin-top: 2rem;">
                <a href="../userProduct/productList.php" class="btn btn-primary" style="background: #8B4513; border-color: #8B4513; color: white; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 500;">
                    <i class="fas fa-shopping-cart"></i> Start Shopping
                </a>
            </div>
        </div>
        
        <!-- Debug: Show sample order for testing -->
        <div style="margin-top: 2rem;">
            <h3 style="color: #8B4513; text-align: center; margin-bottom: 1rem;">Sample Order (For Testing)</h3>
            <div class="tracking-card" style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 2rem; border: 1px solid #e9ecef; overflow: hidden; transition: all 0.3s ease;">
                <div class="tracking-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 1px solid #dee2e6;">
                    <div>
                        <h5 style="color: #8B4513; font-weight: 700; margin: 0; font-size: 1.3rem;">Order #TEST001</h5>
                        <small style="color: #6c757d; font-size: 0.9rem;">Ordered: <?= date('M d, Y - H:i') ?></small>
                    </div>
                    <div class="order-status">
                        <span class="badge" style="font-size: 0.9rem; padding: 0.6rem 1.2rem; border-radius: 20px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; background: #ffc107; color: #212529;">
                            <i class="fas fa-clock"></i>
                            Pending
                        </span>
                    </div>
                </div>
                
                <div class="tracking-progress" style="padding: 1.5rem; background: white;">
                    <div style="margin-bottom: 1rem;">
                        <div class="progress" style="height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden;">
                            <div class="progress-bar" style="width: 20%; background: #ffc107; transition: width 0.6s ease; font-size: 0.7rem; font-weight: 600; display: flex; align-items: center; justify-content: center;">
                                20%
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-timeline" style="display: flex; justify-content: space-between; align-items: center; position: relative; margin-top: 1rem;">
                        <div style="position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #e9ecef; z-index: 1;"></div>
                        <div class="timeline-step" style="display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; background: white; padding: 0.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 0.5rem; background: #28a745; color: white; box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #28a745; text-align: center;">Pending</div>
                        </div>
                        <div class="timeline-step" style="display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; background: white; padding: 0.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 0.5rem; background: #e9ecef; color: #6c757d;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #6c757d; text-align: center;">Confirmed</div>
                        </div>
                        <div class="timeline-step" style="display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; background: white; padding: 0.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 0.5rem; background: #e9ecef; color: #6c757d;">
                                <i class="fas fa-cog fa-spin"></i>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #6c757d; text-align: center;">Processing</div>
                        </div>
                        <div class="timeline-step" style="display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; background: white; padding: 0.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 0.5rem; background: #e9ecef; color: #6c757d;">
                                <i class="fas fa-shipping-fast"></i>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #6c757d; text-align: center;">Shipped</div>
                        </div>
                        <div class="timeline-step" style="display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; background: white; padding: 0.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 0.5rem; background: #e9ecef; color: #6c757d;">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #6c757d; text-align: center;">Delivered</div>
                        </div>
                    </div>
                </div>
                
                <div class="order-actions" style="padding: 1.5rem; background: #f8f9fa; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="font-size: 1.3rem; color: #8B4513; font-weight: 700;">Total: RM 299.00</strong><br>
                        <small style="color: #6c757d; font-size: 0.9rem;">1 item(s)</small>
                    </div>
                    <div class="action-buttons" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="#" class="btn btn-sm btn-primary" style="background: #8B4513; border-color: #8B4513; color: white; padding: 0.5rem 1rem; font-size: 0.9rem; border-radius: 6px; font-weight: 500; text-decoration: none;">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
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
                            <i class="<?= getStatusIcon($order['status']) ?>"></i>
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
                    <a href="tracking_details.php?id=<?= htmlspecialchars($order['orderID']) ?>" class="btn btn-primary view-details-btn">
                        <i class="fas fa-info-circle"></i> View Details
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
