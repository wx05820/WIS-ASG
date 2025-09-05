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
$buyNow = false;

// Handle Buy Now from product page
if (isset($_POST['buy_now']) && isset($_POST['prodID'])) {
    $prodID = $_POST['prodID'];
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
    $selected_product_ids = [$prodID];
    $buyNow = true;
    $_SESSION['checkout_items'] = $selected_product_ids;
    $_SESSION['buyNow'] = true;
    $_SESSION['buyNow_qty'] = $qty;
    
    // Debug logging
    error_log("Buy Now: prodID=$prodID, qty=$qty, selected_items=" . json_encode($selected_product_ids));
    
    // Set a debug session variable
    $_SESSION['debug_buy_now'] = "Buy now processed: prodID=$prodID, qty=$qty";
}
// Handle selected items from cart page
elseif (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
    // Product IDs are strings (e.g., P000100). Keep as strings.
    $selected_product_ids = array_values(array_filter(array_map('trim', $_POST['selected_items'])));
    $buyNow = false;
    $_SESSION['checkout_items'] = $selected_product_ids;
    $_SESSION['buyNow'] = false;
}
// Try to get from session if direct access
elseif (isset($_SESSION['checkout_items']) && is_array($_SESSION['checkout_items'])) {
    $selected_product_ids = $_SESSION['checkout_items'];
    $buyNow = $_SESSION['buyNow'] ?? false;
}

if (empty($selected_product_ids)) {
    error_log("Checkout: No items selected, redirecting to cart");
    $_SESSION['error'] = "No items selected for checkout. Please select items from your cart.";
    redirect('cart_page.php');
}

// Get user address
if($user_id){
    $stmt = $_db->prepare("
        SELECT ID, recipient_name, phoneNo, unitNo, address_line_1, address_line_2, 
            postcode, city, state, isDefault 
        FROM user_address
        WHERE userID = ? 
        ORDER BY isDefault DESC
    ");
    $stmt->execute([$user_id]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
    $addresses = [];
}

$stmt = $_db->prepare("SELECT name, email FROM user WHERE userID = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$cart_items = [];

if($buyNow){
    $buyNow_qty = $_SESSION['buyNow_qty'] ?? 1;
    $stmt = $_db->prepare("SELECT prodID, name, price, image1, color, qty as stock FROM product WHERE prodID = ?");
    $stmt->execute([$selected_product_ids[0]]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $cart_items[] = [
            'prodID' => $product['prodID'],
            'qty' => $buyNow_qty, 
            'name' => $product['name'],
            'price' => $product['price'],
            'image1' => $product['image1'],
            'color' => $product['color'],
            'stock' => $product['stock']
        ];
    }
}else{
    // For Cart checkout: get selected items from cart
    $placeholders = str_repeat('?,', count($selected_product_ids) - 1) . '?';
    $sql = "SELECT ci.prodID, ci.qty, p.name, p.price, p.image1, p.color, p.qty as stock
            FROM cart_items ci
            JOIN cart c ON ci.cartID = c.cartID
            JOIN product p ON ci.prodID = p.prodID
            WHERE c.userID = ? AND ci.prodID IN ($placeholders)";

    $params = array_merge([$user_id], $selected_product_ids);
    $stmt = $_db->prepare($sql);
    $stmt->execute($params);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter out invalid items
    $selected_product_ids = array_intersect($selected_product_ids, $cart_items);
    
    if (empty($selected_product_ids)) {
        $_SESSION['error'] = "Selected items are no longer in your cart. Please refresh and try again.";
        redirect('cart_page.php');
    }
}

if (empty($cart_items)) {
    $_SESSION['error'] = "Selected items not found. Please try again.";
    redirect($buyNow ? '/userProduct/productList.php' : 'cart_page.php');
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
    redirect($buyNow ? '/userProduct/productList.php' : 'cart_page.php');
}

$subtotal = 0;
$item_count = 0;
$checkout_items_js = [];

foreach ($cart_items as $item) {
    $item_subtotal = $item['price'] * $item['qty'];
    $subtotal += $item_subtotal;
    $item_count += $item['qty'];

    $item['subtotal'] = $item_subtotal;
    $item['image'] = !empty($item['image1']) ? 'data:image/jpeg;base64,' . base64_encode($item['image1']) : '/images/placeholder.jpg';

    // Prepare JS data
    $checkout_items_js[] = [
        'id' => $item['prodID'],
        'title' => $item['name'],
        'price' => (float)$item['price'],
        'qty' => (int)$item['qty'],
        'image' => $item['image'],
        'color' => $item['color'] ?? '',
        'subtotal' => $item_subtotal
    ];
}

$shipping_fee = 8.00; 
$is_buy_now = $buyNow;

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
    
    <?php if (isset($_SESSION['debug_buy_now'])): ?>
        <div class="debug-banner" style="background: #e3f2fd; border: 1px solid #2196f3; color: #1976d2; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
            <strong>Debug:</strong> <?= htmlspecialchars($_SESSION['debug_buy_now']) ?>
        </div>
        <?php unset($_SESSION['debug_buy_now']); ?>
    <?php endif; ?>

    <section class="checkout-card">
        <h1 class="checkout-title">Checkout</h1>
        
        <form action="place_order.php" method="POST" id="checkout-form">
            <?php foreach ($selected_product_ids as $prodID): ?>
                <input type="hidden" name="selected_items[]" value="<?= htmlspecialchars($prodID) ?>">
            <?php endforeach; ?>    
        
            <?php if ($buyNow): ?>
                <input type="hidden" name="buy_now" value="1">
                <input type="hidden" name="prodID" value="<?= htmlspecialchars($selected_product_ids[0]) ?>">
                <input type="hidden" name="qty" value="<?= htmlspecialchars($_SESSION['buyNow_qty'] ?? 1) ?>">
            <?php endif; ?>

            <!-- Delivery Address Section -->
            <div class="checkout-section">
                <h2 class="section-title">🚚 Delivery Address</h2>
                <div class="address-section">
                    <?php if (empty($addresses)): ?>
                        <div class="selection-box address-selection" onclick="confirmAddAddress()">
                            <div class="selection-content">
                                <div class="selection-icon">📍</div>
                                <div class="selection-text">
                                    <h3>Add Delivery Address</h3>
                                    <p>You need to add a delivery address to complete your order</p>
                                </div>
                                <div class="selection-arrow">›</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="address-list">
                            <?php foreach ($addresses as $index => $addr): ?>
                                <div class="address-item">
                                    <input type="radio" id="addr_<?= $addr['ID'] ?>" 
                                        name="selected_address" 
                                        value="<?= $addr['ID'] ?>" 
                                        <?= $index === 0 ? 'checked' : '' ?> required>
                                    <label for="addr_<?= $addr['ID'] ?>" class="address-label">
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
                        <button type="button" class="btn-link" onclick="window.location.href='/user/addresses.php'">+ Add New Address</button>
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
                        <input type="radio" id="card" name="payment_method" value="card" checked required>
                        <label for="card">
                            <span class="payment-icon">💳</span>
                            Credit/Debit Card
                        </label>
                    </div>
                    <div class="payment-item">
                        <input type="radio" id="online_banking" name="payment_method" value="online_banking">
                        <label for="online_banking">
                            <span class="payment-icon">🏦</span>
                            Online Banking
                        </label>
                    </div>
                    <div class="payment-item">
                        <input type="radio" id="ewallet" name="payment_method" value="ewallet">
                        <label for="ewallet">
                            <span class="payment-icon">📱</span>
                            E-Wallet (GrabPay, Touch 'n Go)
                        </label>
                    </div>
                    <div class="payment-item">
                        <input type="radio" id="cod" name="payment_method" value="cod">
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
                        <span>RM <span id="subtotal-amount"><?= number_format($subtotal, 2) ?></span></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <span>RM <span id="shipping-fee"><?= number_format($shipping_fee, 2) ?></span></span>
                    </div>
                    <div class="summary-row discount-row" id="discount-row" style="display: none;">
                        <span>Discount</span>
                        <span>- RM <span id="discount-amount">0.00</span></span>
                    </div>
                    <hr>
                    <div class="summary-row total-row">
                        <span><strong>Total</strong></span>
                        <span><strong>RM <span id="total-amount"><?= number_format($subtotal + $shipping_fee, 2) ?></span></strong></span>
                    </div>
                </div>
            </div>

            <!-- Place Order Button -->
            <div class="checkout-actions">
                <button type="submit" class="btn-primary btn-large place-order-btn" <?= empty($addresses) ? 'disabled' : '' ?>>
                    <span class="btn-text">Place Order</span>
                    <span class="btn-loading" style="display: none;">Processing...</span>
                </button>
                <a href="<?= $buyNow ? '/userProduct/productList.php' : 'cart_page.php' ?>" class="btn-secondary">Back to <?= $buyNow ? 'Products' : 'Cart' ?></a>
            </div>
        </form>
    </section>
</main>

<script>
    // Pre-populate checkout items to avoid AJAX call
    window.checkoutSelectedItems = <?= json_encode($checkout_items_js) ?>;

    // Initialize checkout data
    window.checkoutData = {
        hasAddresses: <?= !empty($addresses) ? 'true' : 'false' ?>,
        subtotal: <?= $subtotal ?>,
        standardShipping: 8.00,
        isBuyNow: <?= $is_buy_now ? 'true' : 'false' ?>
    };

    // Initialize checkout items array for checkout.js
    checkoutItems = window.checkoutSelectedItems || [];

    document.addEventListener('DOMContentLoaded', function() {
        displayCheckoutItems();
        updateOrderSummary();
        
        // Listen for address selection changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'selected_address') {
                updateOrderSummary();
            }
        });

        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(e) {
                // For buy now, allow default form submission to ensure selected_items are sent
                const isBuyNow = <?= $buyNow ? 'true' : 'false' ?>;
                if (!isBuyNow) {
                    e.preventDefault(); // Prevent default form submission for cart checkout
                    placeOrder();
                }
                // For buy now, let the form submit normally with hidden inputs
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

function confirmAddAddress() {
    const confirmed = confirm(
        'You need to add a delivery address to complete your order.\n\n' +
        'Would you like to go to the address page to add one?'
    );
    
    if (confirmed) {
        window.location.href = '/user/addresses.php?from=checkout';
    }
}
</script>

<?php include '../footer.php'; ?>