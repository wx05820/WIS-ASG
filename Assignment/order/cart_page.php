<?php
require_once '../_base.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "Please log in to view your cart";
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../user/login.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $prodID = trim($_POST['prodID'] ?? '');
    $qty = intval($_POST['qty'] ?? 1);
    
    try {
        // Get user's cart
        $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
        $cartStmt = $_db->prepare($cartQuery);
        $cartStmt->execute([$userID]);
        $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cart) {
            throw new Exception("Cart not found");
        }
        
        $cartID = $cart['cartID'];
        
        switch ($action) {
            case 'update':
                if ($prodID > 0 && $qty > 0) {
                    // Check stock availability
                    $stockQuery = "SELECT qty FROM product WHERE prodID = ? AND (status IS NULL OR status != 'removed')";
                    $stockStmt = $_db->prepare($stockQuery);
                    $stockStmt->execute([$prodID]);
                    $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$stock) {
                        throw new Exception("Product not found or unavailable");
                    }
                    
                    if ($qty > $stock['qty']) {
                        throw new Exception("Insufficient stock. Available: " . $stock['qty']);
                    }
                    
                    // Update quantity
                    $updateQuery = "UPDATE cart_items SET qty = ? WHERE cartID = ? AND prodID = ?";
                    $updateStmt = $_db->prepare($updateQuery);
                    $updateStmt->execute([$qty, $cartID, $prodID]);
                    
                    if ($updateStmt->rowCount() > 0) {
                        $_SESSION['success'] = "Quantity updated successfully";
                    } else {
                        throw new Exception("Item not found in cart");
                    }
                }
                break;
                
            case 'remove':
                if ($prodID > 0) {
                    $removeQuery = "DELETE FROM cart_items WHERE cartID = ? AND prodID = ?";
                    $removeStmt = $_db->prepare($removeQuery);
                    $removeStmt->execute([$cartID, $prodID]);
                    
                    if ($removeStmt->rowCount() > 0) {
                        $_SESSION['success'] = "Item removed from cart";
                    } else {
                        throw new Exception("Item not found in cart");
                    }
                }
                break;
                
            case 'clear':
                $clearQuery = "DELETE FROM cart_items WHERE cartID = ?";
                $clearStmt = $_db->prepare($clearQuery);
                $clearStmt->execute([$cartID]);
                $_SESSION['success'] = "Cart cleared successfully";
                break;
        }
        
        // Update cart count in session
        $cartCountQuery = "SELECT SUM(qty) as total FROM cart_items WHERE cartID = ?";
        $cartCountStmt = $_db->prepare($cartCountQuery);
        $cartCountStmt->execute([$cartID]);
        $cartCount = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['cart_count'] = $cartCount['total'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Cart action error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header('Location: cart_page.php');
    exit();
}

// Get cart items
try {
    // First, let's check if the user has a cart
    $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
    $cartStmt = $_db->prepare($cartQuery);
    $cartStmt->execute([$userID]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    
    $cart_items = [];
    $subtotal = 0;
    $total_items = 0;
    
    if ($cart) {
        $cartID = $cart['cartID'];
        
        // Debug: Check if cart_items table exists and has data
        $debugQuery = "SELECT COUNT(*) as count FROM cart_items WHERE cartID = ?";
        $debugStmt = $_db->prepare($debugQuery);
        $debugStmt->execute([$cartID]);
        $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Cart items count for cartID $cartID: " . $debugResult['count']);
        
        // Get cart items with product details - simplified query first
        $itemsQuery = "SELECT ci.*, p.name, p.price, p.qty as stock, p.image1, p.color, p.material
                      FROM cart_items ci
                      JOIN product p ON ci.prodID = p.prodID
                      WHERE ci.cartID = ? AND (p.status IS NULL OR p.status != 'removed')";
        $itemsStmt = $_db->prepare($itemsQuery);
        $itemsStmt->execute([$cartID]);
        $cart_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Cart items fetched: " . count($cart_items));
        
        // Calculate totals
        foreach ($cart_items as $item) {
            $item_subtotal = ($item['price'] ?? 0) * ($item['qty'] ?? 0);
            $subtotal += $item_subtotal;
            $total_items += ($item['qty'] ?? 0);
        }
    } else {
        error_log("No cart found for userID: $userID");
    }
    
} catch (Exception $e) {
    error_log("Cart fetch error: " . $e->getMessage());
    error_log("Cart fetch error details: " . print_r($e, true));
    $_SESSION['error'] = "Failed to load cart. Please try again. Error: " . $e->getMessage();
    $cart_items = [];
    $subtotal = 0;
    $total_items = 0;
}

$shipping_fee = 8.00;
$total = $subtotal + $shipping_fee;
?>

<?php include '../header.php'; ?>

<link rel="stylesheet" href="../css/cart.css">
<script src="../js/cart.js" defer></script>

<main class="cart-main">
    <div class="cart-container">
        <div class="cart-header">
            <h1>Shopping Cart</h1>
            <div class="cart-summary">
                <span class="item-count"><?= $total_items ?> item<?= $total_items != 1 ? 's' : '' ?></span>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="../userProduct/productList.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i>
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="cart-content">
                <div class="cart-controls">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all" class="select-all-checkbox">
                        <label for="select-all" class="select-all-label">
                            <span class="checkmark"></span>
                            Select All Items
                        </label>
                    </div>
                    <div class="selected-info">
                        <span id="selected-count">0</span> item(s) selected
                    </div>
                </div>
                
                <div class="cart-items-section">
                    <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-prod-id="<?= $item['prodID'] ?>">
                            <div class="item-selection">
                                <input type="checkbox" 
                                       class="item-checkbox" 
                                       id="item-<?= $item['prodID'] ?>" 
                                       data-prod-id="<?= $item['prodID'] ?>"
                                       data-price="<?= $item['price'] ?? 0 ?>"
                                       data-qty="<?= $item['qty'] ?? 1 ?>"
                                       >
                                <label for="item-<?= $item['prodID'] ?>" class="checkbox-label">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                            
                            <div class="item-image">
                                <?php if (!empty($item['image1'])): ?>
                                    <img src="data:image/jpeg;base64,<?= base64_encode($item['image1']) ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>"
                                         onclick="window.location.href='../userProduct/product_detail.php?prodID=<?= $item['prodID'] ?>'">
                                <?php else: ?>
                                    <img src="../images/placeholder.jpg" 
                                         alt="<?= htmlspecialchars($item['name']) ?>"
                                         onclick="window.location.href='../userProduct/product_detail.php?prodID=<?= $item['prodID'] ?>'">
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <h3 class="item-name">
                                    <a href="../userProduct/product_detail.php?prodID=<?= $item['prodID'] ?>">
                                        <?= htmlspecialchars($item['name'] ?? 'Unknown Product') ?>
                                    </a>
                                </h3>
                                <p class="item-category"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></p>
                                
                                <?php if (!empty($item['color'])): ?>
                                    <p class="item-color"><strong>Color:</strong> <?= htmlspecialchars($item['color']) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['material'])): ?>
                                    <p class="item-material"><strong>Material:</strong> <?= htmlspecialchars($item['material']) ?></p>
                                <?php endif; ?>
                                
                                <div class="item-stock">
                                    <?php if ($item['stock'] > 0): ?>
                                        <?php if ($item['stock'] < 10): ?>
                                            <span class="stock-status low-stock">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Low Stock - Only <?= $item['stock'] ?> available
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="stock-status out-of-stock">
                                            <i class="fas fa-times-circle"></i>
                                            Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="item-quantity">
                                <label for="qty-<?= $item['prodID'] ?>">Quantity:</label>
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn qty-decrease" 
                                            data-prod-id="<?= $item['prodID'] ?>" 
                                            data-max="<?= $item['stock'] ?>">−</button>
                                    <input type="number" 
                                           id="qty-<?= $item['prodID'] ?>" 
                                           class="qty-input" 
                                           value="<?= $item['qty'] ?? 1 ?>" 
                                           min="1" 
                                           max="<?= $item['stock'] ?? 999 ?>"
                                           data-prod-id="<?= $item['prodID'] ?>">
                                    <button type="button" class="qty-btn qty-increase" 
                                            data-prod-id="<?= $item['prodID'] ?>" 
                                            data-max="<?= $item['stock'] ?>">+</button>
                                </div>
                            </div>
                            
                            <div class="item-price">
                                <div class="price-per-unit">
                                    <?= money($item['price'] ?? 0) ?> each
                                </div>
                                <div class="price-total">
                                    <?= money(($item['price'] ?? 0) * ($item['qty'] ?? 0)) ?>
                                </div>
                            </div>
                            
                            <div class="item-actions">
                                <button type="button" class="btn-remove" 
                                        data-prod-id="<?= $item['prodID'] ?>"
                                        title="Remove from cart">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-summary-section">
                    <div class="summary-card">
                        <h3>Order Summary</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal (<span id="summary-item-count">0</span> item<span id="summary-item-plural">s</span>)</span>
                            <span id="summary-subtotal">RM 0.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span id="summary-shipping"><?= money($shipping_fee) ?></span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="summary-total">RM 0.00</span>
                        </div>
                        
                        <div class="summary-actions">
                            <a href="../userProduct/productList.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Continue Shopping
                            </a>
                            
                            <button type="button" class="btn btn-danger" id="clear-cart-btn">
                                <i class="fas fa-trash"></i>
                                Clear Cart
                            </button>
                            
                            <button type="button" class="btn btn-primary btn-checkout disabled" id="checkout-selected">
                                <i class="fas fa-credit-card"></i>
                                Proceed to Checkout (<span id="checkout-count">0</span> items)
                            </button>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Hidden forms for cart actions -->
<form id="update-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="prodID" id="update-prod-id">
    <input type="hidden" name="qty" id="update-qty">
</form>

<form id="remove-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="prodID" id="remove-prod-id">
</form>

<form id="clear-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="clear">
</form>

<form id="checkout-form" method="POST" action="checkout.php" style="display: none;">
    <input type="hidden" name="selected_items" id="selected-items">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quantity controls
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const prodID = this.dataset.prodId;
            const input = document.getElementById(`qty-${prodID}`);
            const max = parseInt(this.dataset.max);
            let newQty = parseInt(input.value);
            
            if (this.classList.contains('qty-increase')) {
                newQty = Math.min(newQty + 1, max);
            } else if (this.classList.contains('qty-decrease')) {
                newQty = Math.max(newQty - 1, 1);
            }
            
            if (newQty !== parseInt(input.value)) {
                input.value = newQty;
                updateCartItem(prodID, newQty);
            }
        });
    });
    
    // Quantity input change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const prodID = this.dataset.prodId;
            const max = parseInt(this.dataset.max);
            let qty = parseInt(this.value);
            
            if (qty < 1) qty = 1;
            if (qty > max) qty = max;
            
            this.value = qty;
            updateCartItem(prodID, qty);
        });
    });
    
    // Remove item buttons
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', function() {
            const prodID = this.dataset.prodId;
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                removeCartItem(prodID);
            }
        });
    });
    
    // Clear cart button
    document.getElementById('clear-cart-btn').addEventListener('click', function() {
        if (confirm('Are you sure you want to clear your entire cart?')) {
            document.getElementById('clear-form').submit();
        }
    });
    
    function updateCartItem(prodID, qty) {
        document.getElementById('update-prod-id').value = prodID;
        document.getElementById('update-qty').value = qty;
        document.getElementById('update-form').submit();
    }
    
    function removeCartItem(prodID) {
        document.getElementById('remove-prod-id').value = prodID;
        document.getElementById('remove-form').submit();
    }
});
</script>

<?php include '../footer.php'; ?>
