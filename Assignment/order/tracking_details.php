<?php
session_start();
require_once '../_base.php';

// Generate CSRF token using the proper function
$csrfToken = generateCSRFToken();

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

$orderID = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($orderID)) {
    $_SESSION['error'] = "Invalid order ID";
    header('Location: tracking.php');
    exit();
}

// Get order details with payment info
$orderQuery = "SELECT o.*, u.username as customer_name, u.email as customer_email,
                      p.payMethod, p.payStatus, p.payDate, p.amount as payment_amount,
                      p.transaction_id, p.payment_details
               FROM `order` o 
               JOIN user u ON o.userID = u.userID 
               LEFT JOIN payment p ON o.payID = p.payID
               WHERE o.orderID = ? AND o.userID = ?";

$orderStmt = $_db->prepare($orderQuery);
$orderStmt->execute([$orderID, $userID]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);


if (!$order) {
    $_SESSION['error'] = "Order not found or access denied.";
    header('Location: tracking.php');
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
$tableCheck = $_db->query("SHOW TABLES LIKE 'deliverystatus'");
if ($tableCheck->rowCount() > 0) {
    $historyQuery = "SELECT status, courier, notes, current_location, updated_at 
                     FROM deliverystatus 
                     WHERE orderID = ? 
                     ORDER BY updated_at DESC";
    
    $historyStmt = $_db->prepare($historyQuery);
    $historyStmt->execute([$orderID]);
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Create sample delivery history if table doesn't exist
    $statusHistory[] = [
        'status' => $order['status'],
        'courier' => 'System',
        'notes' => 'Order status: ' . $order['status'],
        'current_location' => 'Processing Center',
        'updated_at' => $order['orderDate']
    ];
}

function formatDate($date) {
    return date('M d, Y - H:i', strtotime($date));
}

function getStatusBadge($status) {
    $statusClasses = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'primary',
        'shipped' => 'secondary',
        'delivered' => 'success'
    ];
    $class = $statusClasses[strtolower($status)] ?? 'secondary';
    
    $statusIcons = [
        'pending' => 'fas fa-clock',
        'confirmed' => 'fas fa-check-circle',
        'processing' => 'fas fa-cog',
        'shipped' => 'fas fa-shipping-fast',
        'delivered' => 'fas fa-box-open'
    ];
    $icon = $statusIcons[strtolower($status)] ?? 'fas fa-info-circle';
    
    return "<span class='badge badge-{$class}'><i class='{$icon}'></i> " . ucfirst($status) . "</span>";
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

// Store messages for later display
$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;
if ($successMessage) unset($_SESSION['success']);
if ($errorMessage) unset($_SESSION['error']);

// Get user's addresses for change address functionality
$userAddresses = [];
if ($order['status'] === 'Pending') {
    try {
        $addressQuery = "SELECT ID, recipient_name, address_line_1, address_line_2, city, state, postcode, isDefault 
                         FROM user_address 
                         WHERE userID = ? 
                         ORDER BY isDefault DESC";
        $addressStmt = $_db->prepare($addressQuery);
        $addressStmt->execute([$userID]);
        $userAddresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching user addresses: " . $e->getMessage());
    }
}

include '../header.php';
?>

<link rel="stylesheet" href="../css/tracking.css">
<link rel="stylesheet" href="../css/order_details.css">

<body data-user-id="<?= $userID ?>">
    <div class="modern-container">
        <!-- Enhanced Status Timeline -->
        <div class="enhanced-timeline-section">
            <div class="enhanced-timeline <?= strtolower($order['status']) ?>">
                <?php
                // Define timeline steps with their corresponding order statuses
                $timelineSteps = [
                    ['status' => 'Pending', 'icon' => 'fa-hourglass-half', 'label' => 'Order Placed'],
                    ['status' => 'Confirmed', 'icon' => 'fa-check', 'label' => 'Confirmed'],
                    ['status' => 'Processing', 'icon' => 'fa-cogs', 'label' => 'Processing'],
                    ['status' => 'Shipped', 'icon' => 'fa-truck', 'label' => 'Shipped'],
                    ['status' => 'Delivered', 'icon' => 'fa-box', 'label' => 'Delivered']
                ];
                
                // Define status progression order
                $statusOrder = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered'];
                $currentStatusIndex = array_search($order['status'], $statusOrder);
                
                foreach ($timelineSteps as $index => $step):
                    $stepStatus = $step['status'];
                    $stepIndex = array_search($stepStatus, $statusOrder);
                    
                    // Determine if this step is completed, active, or pending
                    if ($stepIndex < $currentStatusIndex) {
                        $stepClass = 'completed';
                    } elseif ($stepIndex == $currentStatusIndex) {
                        $stepClass = 'active';
                    } else {
                        $stepClass = 'pending';
                    }
                ?>
                <div class="timeline-step <?= $stepClass ?>">
                    <div class="timeline-icon-container">
                        <i class="fas <?= $step['icon'] ?> timeline-icon"></i>
                    </div>
                    <div class="timeline-dot"></div>
                    <div class="timeline-label"><?= $step['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order Header Card -->
        <div class="order-header-card">
            <div class="order-header-content">
                <div class="order-info">
                    <h1 class="order-title">Order #<?= $order['orderID'] ?></h1>
                    <p class="order-date"><?= formatDate($order['orderDate']) ?></p>
                </div>
                <div class="order-status-section">
                    <div class="status-badge status-<?= strtolower($order['status']) ?>">
                        <i class="fas fa-<?= strtolower($order['status']) === 'delivered' ? 'check-circle' : (strtolower($order['status']) === 'processing' ? 'cog' : (strtolower($order['status']) === 'cancelled' ? 'times-circle' : 'info-circle')) ?>"></i>
                        <?= htmlspecialchars($order['status']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="main-content-grid">
            <!-- Left Column - Order Items -->
            <div class="left-column">
                <!-- Order Items Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shopping-bag"></i>
                            Order Items (<?= count($items) ?>)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($items as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>">
                                </div>
                                <div class="item-details">
                                    <h4 class="item-name"><?= htmlspecialchars($item['name']) ?></h4>
                                    <p class="item-id">Product ID: #<?= $item['prodID'] ?></p>
                                    <?php if (!empty($item['product_color'])): ?>
                                        <p class="item-color">Color: <?= htmlspecialchars($item['product_color']) ?></p>
                                    <?php endif; ?>
                                    <div class="item-price-section">
                                        <span class="item-price"><?= money($item['price']) ?></span>
                                        <span class="item-quantity">× <?= $item['qty'] ?></span>
                                    </div>
                                </div>
                                <div class="item-total">
                                    <span class="total-price"><?= money($item['price'] * $item['qty']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Order Totals -->
                        <div class="order-totals">
                            <div class="total-row">
                                <span class="total-label">Subtotal:</span>
                                <span class="total-value"><?= money($order['subtotal']) ?></span>
                            </div>
                            <?php if ($order['shipping_fee'] > 0): ?>
                                <div class="total-row">
                                    <span class="total-label">Shipping Fee:</span>
                                    <span class="total-value"><?= money($order['shipping_fee']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($order['discount'] > 0): ?>
                                <div class="total-row discount">
                                    <span class="total-label">Discount:</span>
                                    <span class="total-value">-<?= money($order['discount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="total-row final-total">
                                <span class="total-label">Total Amount:</span>
                                <span class="total-value"><?= money($order['total']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Order Info -->
            <div class="right-column">
                <!-- Shipping Address Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shipping-fast"></i>
                            Shipping Address
                        </h3>
                        <?php if ($order['status'] === 'Pending'): ?>
                            <a href="change_address_page.php?id=<?= $order['orderID'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit"></i> Change Address
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="address-info">
                            <h4><?= htmlspecialchars($order['recipient_name']) ?></h4>
                            <p class="address-text"><?= formatAddress($order) ?></p>
                            <?php if (!empty($order['notes'])): ?>
                                <div class="order-notes">
                                    <strong>Notes:</strong> <?= htmlspecialchars($order['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Information Card -->
                <?php if (!empty($order['payMethod'])): ?>
                    <div class="modern-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card"></i>
                                Payment Details
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="payment-info">
                                <div class="info-row">
                                    <span class="info-label">Method:</span>
                                    <span class="info-value"><?= ucfirst($order['payMethod']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value status-<?= strtolower($order['payStatus']) ?>"><?= ucfirst($order['payStatus']) ?></span>
                                </div>
                                <?php if (!empty($order['payDate'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Date:</span>
                                        <span class="info-value"><?= formatDate($order['payDate']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Transaction ID:</span>
                                        <span class="info-value"><?= htmlspecialchars($order['transaction_id']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">Amount:</span>
                                    <span class="info-value amount"><?= money($order['payment_amount'] ?? $order['total']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Delivery Status Timeline Card -->
                <?php if (!empty($statusHistory)): ?>
                    <div class="modern-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-truck"></i>
                                Delivery Status
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="status-timeline">
                                <?php foreach ($statusHistory as $index => $history): ?>
                                    <div class="timeline-item <?= $index === 0 ? 'active' : '' ?>">
                                        <div class="timeline-marker">
                                            <div class="timeline-dot"></div>
                                            <?php if ($index < count($statusHistory) - 1): ?>
                                                <div class="timeline-line"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-status">
                                                <strong><?= ucfirst($history['status']) ?></strong>
                                                <span class="timeline-time"><?= formatDate($history['updated_at']) ?></span>
                                            </div>
                                            <?php if (!empty($history['courier'])): ?>
                                                <div class="timeline-detail">
                                                    <i class="fas fa-truck"></i>
                                                    <span>Courier: <?= htmlspecialchars($history['courier']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($history['current_location'])): ?>
                                                <div class="timeline-detail">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <span>Location: <?= htmlspecialchars($history['current_location']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($history['notes'])): ?>
                                                <div class="timeline-detail">
                                                    <i class="fas fa-info-circle"></i>
                                                    <span><?= htmlspecialchars($history['notes']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Actions Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cog"></i>
                            Actions
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($order['status'] === 'Delivered'): ?>
                                <form method="POST" action="mark_received.php" class="action-form" onsubmit="return confirm('Are you sure you want to mark this order as received?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-success action-btn" id="mark-received-btn">
                                        <i class="fas fa-check-circle"></i> Mark as Received
                                    </button>
                                </form>
                                
                                <form method="POST" action="request_refund.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-outline-warning action-btn" 
                                            onclick="return confirm('Are you sure you want to request a refund for this order? This will require admin approval.')">
                                        <i class="fas fa-undo"></i> Request Refund
                                    </button>
                                </form>
                                
                            <?php elseif ($order['status'] === 'Received'): ?>
                                <form method="POST" action="request_refund.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-outline-warning action-btn" 
                                            onclick="return confirm('Are you sure you want to request a refund for this order? This will require admin approval.')">
                                        <i class="fas fa-undo"></i> Request Refund
                                    </button>
                                </form>
                                
                            <?php elseif ($order['status'] === 'Pending'): ?>
                                <form method="POST" action="cancel_order.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-danger action-btn" 
                                            onclick="return confirm('Are you sure you want to cancel this order? This action cannot be undone.')">
                                        <i class="fas fa-times-circle"></i> Cancel Order
                                    </button>
                                </form>
                                
                            <?php elseif ($order['status'] === 'Processing'): ?>
                                <form method="POST" action="cancel_refund_request.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-warning action-btn" 
                                            onclick="return confirm('Are you sure you want to cancel your refund request?')">
                                        <i class="fas fa-times"></i> Cancel Refund Request
                                    </button>
                                </form>
                            <?php elseif ($order['status'] === 'Refunded'): ?>
                                <div class="action-btn disabled">
                                    <i class="fas fa-check"></i> Refund Successful
                                </div>
                            <?php endif; ?>
                            
                            <!-- Reorder button - available for all statuses -->
                            <form method="POST" action="reorder.php" class="action-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                <button type="submit" class="btn btn-primary action-btn">
                                    <i class="fas fa-redo"></i> Reorder Items
                                </button>
                            </form>
                            
                            <button onclick="window.print()" class="btn btn-outline-secondary action-btn">
                                <i class="fas fa-print"></i> Print Order
                            </button>
                            
                            
                            
                            
                            
                            
                            <a href="tracking.php" class="btn btn-outline-primary action-btn">
                                <i class="fas fa-arrow-left"></i> Back to Tracking
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Enhanced notification functions
    function showSuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            createNotification(message, 'success');
        }
    }

    function showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: message,
                confirmButtonText: 'OK'
            });
        } else {
            createNotification(message, 'error');
        }
    }

    function createNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

    // Simple message display without form interference
    document.addEventListener('DOMContentLoaded', function() {
        // Display session messages after a short delay to ensure functions are loaded
        <?php if ($successMessage): ?>
        setTimeout(() => {
            if (typeof showSuccess === 'function') {
                showSuccess("<?= htmlspecialchars($successMessage) ?>");
            } else {
                alert("<?= htmlspecialchars($successMessage) ?>");
            }
        }, 100);
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        setTimeout(() => {
            if (typeof showError === 'function') {
                showError("<?= htmlspecialchars($errorMessage) ?>");
            } else {
                alert("<?= htmlspecialchars($errorMessage) ?>");
            }
        }, 100);
        <?php endif; ?>
    });
    </script>
</body>
<?php include '../footer.php'; ?>
