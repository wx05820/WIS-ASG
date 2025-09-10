<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

// Get filtering and sorting parameters
$sortBy = $_GET['sort'] ?? 'date';
$sortOrder = $_GET['order'] ?? 'desc';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Validate sort parameters
$allowedSorts = ['status', 'date', 'total'];
$allowedOrders = ['asc', 'desc'];

if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'date';
}
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'desc';
}

// Show orders with "Received", "Cancelled", and "Refunded" statuses
$allowedStatuses = ['Received', 'Cancelled', 'Refunded'];

// Apply status filter if specified
if (!empty($statusFilter) && in_array($statusFilter, $allowedStatuses)) {
    $allowedStatuses = [$statusFilter];
}

$statusShow = implode(',', array_fill(0, count($allowedStatuses), '?'));

// Build WHERE clause for date filtering
$whereConditions = ["o.userID = ?", "o.status IN ($statusShow)"];
$queryParams = [$userID];

// Add status parameters
$queryParams = array_merge($queryParams, $allowedStatuses);

// Add date filtering
if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(o.orderDate) >= ?";
    $queryParams[] = $dateFrom;
}
if (!empty($dateTo)) {
    $whereConditions[] = "DATE(o.orderDate) <= ?";
    $queryParams[] = $dateTo;
}

$whereClause = implode(' AND ', $whereConditions);

// Build ORDER BY clause based on sort parameters
$orderByClause = '';
switch ($sortBy) {
    case 'status':
        $orderByClause = "ORDER BY FIELD(o.status, 'Received', 'Refunded', 'Cancelled')";
        if ($sortOrder === 'desc') {
            $orderByClause = "ORDER BY FIELD(o.status, 'Cancelled', 'Refunded', 'Received')";
        }
        $orderByClause .= ", o.orderDate DESC, o.orderID DESC";
        break;
    case 'date':
        $orderByClause = "ORDER BY o.orderDate " . strtoupper($sortOrder) . ", o.orderID DESC";
        break;
    case 'total':
        $orderByClause = "ORDER BY o.total " . strtoupper($sortOrder) . ", o.orderDate DESC, o.orderID DESC";
        break;
}

// Get orders
$ordersQuery = "SELECT o.*, 
                COUNT(oi.orderID) as item_count,
                SUM(oi.qty) as total_items
                FROM `order` o 
                LEFT JOIN order_items oi ON o.orderID = oi.orderID 
                WHERE $whereClause
                GROUP BY o.orderID 
                $orderByClause";

$ordersStmt = $_db->prepare($ordersQuery);
$ordersStmt->execute($queryParams);
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

                <!-- Filtering Controls -->
                <div class="filter-controls" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                    <form method="GET" id="filterForm" style="display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label for="status" style="margin-right: 0.5rem; color: #495057; font-weight: 500; min-width: 80px;">Status:</label>
                            <select id="status" name="status" style="padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; background: white; min-width: 150px;">
                                <option value="">All Statuses</option>
                                <option value="Received" <?= $statusFilter === 'Received' ? 'selected' : '' ?>>Received</option>
                                <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="Refunded" <?= $statusFilter === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                            </select>
                        </div>
                        
                        <!-- Date Range Filter -->
                        <div class="filter-group">
                            <label for="date_from" style="margin-right: 0.5rem; color: #495057; font-weight: 500; min-width: 80px;">From Date:</label>
                            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; background: white;">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to" style="margin-right: 0.5rem; color: #495057; font-weight: 500; min-width: 80px;">To Date:</label>
                            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; background: white;">
                        </div>
                        
                        <!-- Hidden sort parameters (using defaults) -->
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
                        
                        <!-- Filter Buttons -->
                        <div class="filter-group" style="margin-left: auto; display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.5rem 1rem; background: #007bff; color: white; border: none; border-radius: 4px; text-decoration: none; font-weight: 500;">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <button type="button" onclick="clearFilters()" class="btn btn-outline-secondary btn-sm" style="padding: 0.5rem 1rem; border: 1px solid #6c757d; color: #6c757d; background: white; border-radius: 4px; text-decoration: none; font-weight: 500;">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>
                    </form>
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

                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-shopping-bag"></i>
                        <h4>No Orders Found</h4>
                        <p>You don't have any completed, cancelled, or refunded orders yet.</p>                       
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
                                        <i class="fas fa-<?= strtolower($order['status']) === 'received' ? 'check-circle' : (strtolower($order['status']) === 'cancelled' ? 'times-circle' : (strtolower($order['status']) === 'refunded' ? 'undo' : 'info-circle')) ?>"></i>
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

    function autoApplyFilters() {
        // Auto-submit the form when any filter changes
        document.getElementById('filterForm').submit();
    }

    function clearFilters() {
        // Ask for confirmation before clearing filters
        if (confirm('Are you sure you want to clear all filters?')) {
            // Clear all form fields
            document.getElementById('status').value = '';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            
            // Submit the form to reset all filters
            document.getElementById('filterForm').submit();
        }
    }
</script>

</body>
<?php include '../footer.php'; ?>
