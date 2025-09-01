<?php
require_once '../_base.php';
include 'cart.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

$selected_product_ids = [];

// Product page
if (isset($_POST['buy_now']) && isset($_POST['prodID'])) {
    $prodID = $_POST['prodID'];
    $selected_product_ids = [$prodID];
    $_SESSION['checkout_items'] = $selected_product_ids;
}
// Cart page
elseif (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
    $selected_product_ids = array_map('intval', $_POST['selected_items']);
    $_SESSION['checkout_items'] = $selected_product_ids;
}

if (empty($selected_product_ids)) {
    $_SESSION['error'] = "No items selected for checkout. Please select items from your cart.";
    redirect('cart_page.php');
}

if($user_id){
    // Get user address
    $stmt = $_db->prepare("
        SELECT ID, recipient_name, phoneNo, unitNo, address_line_1, address_line_2, 
            postcode, city, state, isDefault 
        FROM user_address
        WHERE userID = ? 
        ORDER BY isDefault DESC
    ");
    $stmt->execute([$user_id]);
    $address = $stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
    $address = [];
}

$stmt = $_db->prepare("SELECT name, email FROM user WHERE userID = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cart items for checkout
$placeholders = str_repeat('?,', count($selected_product_ids) - 1) . '?';

if(isset($_POST['buy_now'])){
    $sql = "SELECT p.prodID, 1 as qty, p.name, p.price, p.image1, p.color, p.qty as stock
            FROM product p WHERE p.prodID = ?";
    $params = [$prodID];
}else{
    $sql = "SELECT ci.prodID, ci.qty, p.name, p.price, p.image1, p.color
            FROM cart_items ci
            JOIN cart c ON ci.cartID = c.cartID
            JOIN product p ON ci.prodID = p.prodID
            WHERE c.userID = ? AND ci.prodID IN ($placeholders)";

    $params = array_merge([$user_id], $selected_product_ids);
}

$stmt = $_db->prepare($sql);
$stmt->execute($params);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    $_SESSION['error'] = "Selected items not found in your cart. Please try again.";
    redirect('cart_page.php');
}

// Check stock availability
$stock_errors = [];
foreach ($cart_items as $item) {
    if ($item['stock'] < $item['qty']) {
        $stock_errors[] = htmlspecialchars($item['name']) . " - Only {$item['stock']} available, but {$item['qty']} requested";
    }
}

if (!empty($stock_errors)) {
    $_SESSION['error'] = "Stock issues: " . implode(', ', $stock_errors);
    redirect('cart_page.php');
}

$subtotal = 0;
$item_count = 0;
foreach ($cart_items as $item) {
    $item_subtotal = $item['price'] * $item['qty'];
    $subtotal += $item_subtotal;
    $item_count += $item['qty'];

    $item['subtotal'] = $item_subtotal;
    $item['image'] = !empty($item['image1']) ? 'data:image/jpeg;base64,' . base64_encode($item['image1']) : '/images/placeholder.jpg';
}
unset($item);

$shipping_fee = 8.00; 

include '../header.php'; 
?>

<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/checkout.css">
<script src="../js/checkout.js" defer></script>

<main class="container-checkout">
    <?php if (isset($_SESSION['checkout_error'])): ?>
        <div class="error-banner">
            <?= htmlspecialchars($_SESSION['checkout_error']) ?>
        </div>
        <?php unset($_SESSION['checkout_error']); ?>
    <?php endif; ?>

    <section class="checkout-card">
        <h1 class="checkout-title">Checkout</h1>
        
        <form action="place_order.php" method="POST" id="checkout-form">
            <?php foreach ($selected_product_ids as $prodID): ?>
                <input type="hidden" name="selected_items[]" value="<?= htmlspecialchars($prodID) ?>">
            <?php endforeach; ?>    
        
            <?php if (isset($_POST['buy_now'])): ?>
                <input type="hidden" name="buy_now" value="1">
            <?php endif; ?>

            <!-- Delivery Address Section -->
            <div class="checkout-section">
                <h2 class="section-title">🚚 Delivery Address</h2>
                <div class="address-section">
                    <?php if (empty($address)): ?>
                        <div class="selection-box address-selection" onclick="showAddAddressModal()">
                            <div class="selection-content">
                                <div class="selection-icon">📍</div>
                                <div class="selection-text">
                                    <h3>Add Delivery Address</h3>
                                    <p>Click here to add your delivery address</p>
                                </div>
                                <div class="selection-arrow">›</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="address-list">
                            <?php foreach ($address as $index => $addr): ?>
                                <div class="address-item">
                                    <input type="radio" id="addr_<?= $addr['addressID'] ?>" 
                                        name="selected_address" 
                                        value="<?= $addr['addressID'] ?>" 
                                        <?= $index === 0 ? 'checked' : '' ?> required>
                                    <label for="addr_<?= $addr['addressID'] ?>" class="address-label">
                                        <div class="address-header">
                                            <strong><?= htmlspecialchars($addr['recipient_name']) ?></strong>
                                            <span class="phone"><?= htmlspecialchars($addr['phoneNo']) ?></span>
                                            <?php if ($addr['isDefault']): ?>
                                                <span class="default-badge">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="address-details">
                                            <?= htmlspecialchars($addr['unitNo']) ?>, <?= htmlspecialchars($addr['address_line_1']) ?>
                                            <?php if ($addr['address_line_2']): ?>
                                                <br><?= htmlspecialchars($addr['address_line_2']) ?>
                                            <?php endif; ?>
                                            <br><?= htmlspecialchars($addr['postcode']) ?> <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-link" onclick="showAddAddressModal()">+ Add New Address</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Voucher Section -->
            <div class="checkout-section">
                <h2 class="section-title">🎟️ Vouchers & Discounts</h2>
                <div class="voucher-section">
                    <div class="selection-box voucher-selection" onclick="showVoucherModal()">
                        <div class="selection-content">
                            <div class="selection-icon">🏷️</div>
                            <div class="selection-text">
                                <h3>Select Voucher</h3>
                                <p id="voucher-text">Click here to select available vouchers</p>
                            </div>
                            <div class="selection-arrow">›</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items Section -->
            <div class="checkout-section">
                <h2 class="section-title">📦 Order Items</h2>
                <div id="checkout-items" class="order-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="order-item">
                            <img src="<?= htmlspecialchars($item['image']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="item-image" 
                                 onerror="this.src='/images/placeholder.jpg'">
                            <div class="item-details">
                                <h4><?= htmlspecialchars($item['name']) ?></h4>
                                <?php if ($item['color']): ?>
                                    <p class="item-color">Color: <?= htmlspecialchars($item['color']) ?></p>
                                <?php endif; ?>
                                <p class="item-price"><?= money($item['price']) ?> × <?= $item['qty'] ?></p>
                            </div>
                            <div class="item-subtotal">
                                <?= money($item['subtotal']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="back-to-cart">
                    <a href="cart_page.php" class="btn-link">← Back to Cart to Change Selection</a>
                </div>
            </div>

            <!-- Shipping & Payment Section -->
            <div class="checkout-section">
                <h2 class="section-title">🚛 Shipping & Payment</h2>
                
                <!-- Shipping Options -->
                <div class="shipping-options">
                    <h3>Shipping Method</h3>
                    <div class="shipping-item">
                        <input type="radio" id="standard" name="shipping_method" value="standard" checked required>
                        <label for="standard">
                            <div class="shipping-info">
                                <strong>Standard Delivery</strong>
                                <span class="shipping-time">3-5 business days</span>
                            </div>
                            <div class="shipping-price">RM 8.00</div>
                        </label>
                    </div>
                    <div class="shipping-item">
                        <input type="radio" id="express" name="shipping_method" value="express">
                        <label for="express">
                            <div class="shipping-info">
                                <strong>Express Delivery</strong>
                                <span class="shipping-time">1-2 business days</span>
                            </div>
                            <div class="shipping-price">RM 15.00</div>
                        </label>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="payment-methods">
                    <h3>Payment Method</h3>
                    <div class="payment-item">
                        <input type="radio" id="card" name="pay_method" value="card" checked required>
                        <label for="card">
                            <span class="payment-icon">💳</span>
                            Credit/Debit Card
                        </label>
                    </div>
                    <div class="payment-item">
                        <input type="radio" id="online_banking" name="pay_method" value="online_banking">
                        <label for="online_banking">
                            <span class="payment-icon">🏦</span>
                            Online Banking
                        </label>
                    </div>
                    <div class="payment-item">
                        <input type="radio" id="ewallet" name="pay_method" value="ewallet">
                        <label for="ewallet">
                            <span class="payment-icon">📱</span>
                            E-Wallet (GrabPay, Touch 'n Go)
                        </label>
                    </div>
                    <div class="payment-item">
                        <input type="radio" id="cod" name="pay_method" value="cod">
                        <label for="cod">
                            <span class="payment-icon">💰</span>
                            Cash on Delivery
                        </label>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="checkout-section">
                <div class="order-summary">
                    <h2 class="section-title">📋 Order Summary</h2>
                    <div class="summary-row">
                        <span>Subtotal (<span id="items-count"><?= $item_count ?></span> items)</span>
                        <span><span id="subtotal-amount"><?= money($subtotal) ?></span></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <span><span id="shipping-fee"><?= money($shipping_fee) ?></span></span>
                    </div>
                    <div class="summary-row discount-row" id="discount-row" style="display: none;">
                        <span>Discount</span>
                        <span>- <span id="discount-amount">0.00</span></span>
                    </div>
                    <hr>
                    <div class="summary-row total-row">
                        <span><strong>Total</strong></span>
                        <span><strong><span id="total-amount"><?= money($subtotal + $shipping_fee) ?></span></strong></span>
                    </div>
                </div>
            </div>

            <!-- Place Order Button -->
            <div class="checkout-actions">
                <button type="button" class="btn-primary btn-large place-order-btn" onclick="placeOrder()" <?= empty($address) ? 'disabled' : '' ?>>
                    <span class="btn-text">Place Order</span>
                    <span class="btn-loading" style="display: none;">Processing...</span>
                </button>
                <a href="cart_page.php" class="btn-secondary">Back to Cart</a>
            </div>
        </form>
    </section>
</main>

<script>
    // Initialize checkout data
    window.checkoutData = {
        hasAddresses: <?= !empty($addresses) ? 'true' : 'false' ?>,
        subtotal: <?= $subtotal ?>,
    };

    // Prevent double form submission
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('.place-order-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }
        
        // Validate required selections
        const selectedAddress = document.querySelector('input[name="selected_address"]:checked');
        const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
        const selectedPayment = document.querySelector('input[name="pay_method"]:checked');
        
        if (!selectedAddress && window.checkoutData.hasAddresses) {
            showError('Please select a delivery address');
            return;
        }
        
        if (!selectedShipping) {
            showError('Please select a shipping method');
            return;
        }
        
        if (!selectedPayment) {
            showError('Please select a payment method');
            return;
        }

        // Show loading state
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        submitBtn.disabled = true;

        this.submit();
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                placeOrder();
            });
        }
    });

function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
    // Remove any existing notifications
    const existing = document.querySelector('.detail-notification');
    if (existing) {
        existing.remove();
    }
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = 'detail-notification';
    const bgColor = type === 'error' ? '#ff4444' : '#4CAF50';
    notificationDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease-in-out;
    `;
    notificationDiv.textContent = message;
    
    document.body.appendChild(notificationDiv);
    
    // Animate in
    setTimeout(() => {
        notificationDiv.style.opacity = '1';
        notificationDiv.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (notificationDiv.parentNode) {
            notificationDiv.style.opacity = '0';
            notificationDiv.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notificationDiv.parentNode) {
                    notificationDiv.parentNode.removeChild(notificationDiv);
                }
            }, 300);
        }
    }, 4000);
}
</script>

<?php include '../footer.php'; ?>