<?php
require_once '../_base.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    $_SESSION['error'] = "Invalid order reference";
    redirect('/index.php');
}

// Get order details
$stmt = $_db->prepare("
    SELECT o.*, p.payMethod, p.payStatus, p.payDate, p.amount as payment_amount
    FROM `order` o
    LEFT JOIN payment p ON o.payID = p.payID
    WHERE o.orderID = ? AND o.userID = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found";
    redirect('/index.php');
}

// Get order items
$stmt = $_db->prepare("
    SELECT oi.*, p.image1
    FROM order_items oi
    JOIN product p ON oi.prodID = p.prodID
    WHERE oi.orderID = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$payStatus = $order['payStatus'] ?? '';
if ($payStatus === 'Pending') {
    $badgeClass = 'status-pending';
} elseif (in_array($payStatus, ['Success', 'Completed'])) {
    $badgeClass = 'status-success';
} elseif ($payStatus === 'Failed') {
    $badgeClass = 'status-failed';
}

$page_title = 'Order Confirmation';
//$skip_fontawesome = true; // Skip Font Awesome since this page doesn't use icons
$skip_jquery = true; // Skip jQuery since this page doesn't use it
include '../header.php';
?>

<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/success.css">

<main class="container-checkout">
    <div class="checkout-card">
        <div class="success-header">
            <div class="success-icon">✅</div>
            <h1 class="success-title">Order Placed Successfully!</h1>
            <p class="success-subtitle">Thank you for your purchase. Your order has been confirmed.</p>
        </div>

        <div class="order-details">
            <div class="order-info">
                <h2>Order Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Order ID:</strong>
                        <span><?= htmlspecialchars($order['orderID']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Order Date:</strong>
                        <span><?= date('F j, Y', strtotime($order['orderDate'])) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Status:</strong>
                        <span class="status-badge status-<?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Payment Method:</strong>
                        <span><?= htmlspecialchars($order['payMethod']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Payment Status:</strong>
                        <span class="status-badge <?= $badgeClass ?>"> <?= htmlspecialchars($order['payStatus']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Shipping Method:</strong>
                        <span><?= ucfirst(htmlspecialchars($order['shipping_method'])) ?> Delivery</span>
                    </div>
                </div>
            </div>

            <div class="shipping-address">
                <h2>Shipping Address</h2>
                <div class="address-display">
                    <strong><?= htmlspecialchars($order['recipient_name']) ?></strong><br>
                    <?= htmlspecialchars($order['phoneNo']) ?><br>
                    <?php if (!empty($order['unitNo'])): ?>
                        <?= htmlspecialchars($order['unitNo']) ?>, 
                    <?php endif; ?>
                    <?= htmlspecialchars($order['address_line_1']) ?><br>
                    <?php if (!empty($order['address_line_2'])): ?>
                        <?= htmlspecialchars($order['address_line_2']) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($order['postcode']) ?> <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?>
                </div>
            </div>

            <div class="order-items">
                <h2>Order Items</h2>
                <div class="items-list">
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,' . base64_encode($item['image1']) : '/images/placeholder.jpg' ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                 class="item-image"
                                 onerror="this.src='/images/placeholder.jpg'">
                            <div class="item-details">
                                <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                <?php if (!empty($item['product_color'])): ?>
                                    <p class="item-color">Color: <?= htmlspecialchars($item['product_color']) ?></p>
                                <?php endif; ?>
                                <p class="item-price">RM <?= number_format($item['price'], 2) ?> × <?= $item['qty'] ?></p>
                            </div>
                            <div class="item-subtotal">
                                RM <?= number_format($item['price'] * $item['qty'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="order-summary">
                <h2>Order Summary</h2>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>RM <?= number_format($order['subtotal'], 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping Fee</span>
                    <span>RM <?= number_format($order['shipping_fee'], 2) ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                    <div class="summary-row">
                        <span>Discount</span>
                        <span>- RM <?= number_format($order['discount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <hr>
                <div class="summary-row total-row">
                    <span><strong>Total</strong></span>
                    <span><strong>RM <?= number_format($order['total'], 2) ?></strong></span>
                </div>
            </div>
        </div>

        <div class="success-actions">
            <a href="/order/history.php" class="btn-primary">View Order History</a>
            <a href="/userProduct/productList.php" class="btn-secondary">Continue Shopping</a>
        </div>

        <?php if ($order['payMethod'] === 'COD'): ?>
            <div class="cod-notice">
                <h3>💰 Cash on Delivery</h3>
                <p>Your order will be delivered to the address above. Please have the exact amount ready for payment upon delivery.</p>
                <p><strong>Amount to pay: <?= money($order['total']) ?></strong></p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../footer.php'; ?>