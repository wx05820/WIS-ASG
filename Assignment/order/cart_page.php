<?php
require_once '../_base.php';

// Handle AJAX cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON header for AJAX requests
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Ensure session is started for AJAX requests
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in for AJAX requests
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Please log in to update your cart'
        ]);
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    $prodID = trim($_POST['prodID'] ?? '');
    $qty = intval($_POST['qty'] ?? 1);
    
    // Additional cleaning for product ID
    $prodID = preg_replace('/\s+/', '', $prodID); // Remove all whitespace
    $prodID = trim($prodID, " \t\n\r\0\x0B"); // Trim various whitespace types
    
    // Validate required parameters
    if (empty($action)) {
        echo json_encode([
            'success' => false,
            'message' => 'Action parameter is required'
        ]);
        exit();
    }
    
    if ($action === 'update' && (empty($prodID) || $qty < 1)) {
        echo json_encode([
            'success' => false,
            'message' => 'Product ID and quantity are required for update action'
        ]);
        exit();
    }
    
    try {
        $userID = $_SESSION['user_id'];
        
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
                if (!empty($prodID) && $qty > 0) {
                    // Check stock availability
                    $stockQuery = "SELECT qty FROM product WHERE prodID = ? AND (status IS NULL OR status != 'removed')";
                    $stockStmt = $_db->prepare($stockQuery);
                    $stockStmt->execute([$prodID]);
                    $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$stock) {
                        throw new Exception("Product not found or unavailable");
                    }
                    
                    if ($qty > $stock['qty']) {
                        throw new Exception("Insufficient stock. Available: " . $stock['qty'] . " items, requested: " . $qty . " items");
                    }
                    
                    // Find the cart item by prodID and get its cart_item_id
                    $itemQuery = "SELECT cart_item_id FROM cart_items WHERE cartID = ? AND prodID = ?";
                    $itemStmt = $_db->prepare($itemQuery);
                    $itemStmt->execute([$cartID, $prodID]);
                    $cartItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cartItem) {
                        throw new Exception("Item not found in cart");
                    }
                    
                    $cartItemId = $cartItem['cart_item_id'];
                    
                    // Update quantity using cart_item_id
                    $updateQuery = "UPDATE cart_items SET qty = ? WHERE cart_item_id = ?";
                    $updateStmt = $_db->prepare($updateQuery);
                    $updateStmt->execute([$qty, $cartItemId]);
                    
                    if ($updateStmt->rowCount() > 0) {
                        $message = "Quantity updated successfully";
                    } else {
                        // Try alternative update method using prodID directly
                        $altUpdateQuery = "UPDATE cart_items SET qty = ? WHERE cartID = ? AND prodID = ?";
                        $altUpdateStmt = $_db->prepare($altUpdateQuery);
                        $altUpdateStmt->execute([$qty, $cartID, $prodID]);
                        
                        if ($altUpdateStmt->rowCount() > 0) {
                            $message = "Quantity updated successfully";
                        } else {
                            throw new Exception("Failed to update cart item - item may not exist or be locked");
                        }
                    }
                } else {
                    throw new Exception("Invalid product ID or quantity");
                }
                break;
                
            case 'remove':
                if (!empty($prodID)) {
                    // Find the cart item by prodID and get its cart_item_id
                    $itemQuery = "SELECT cart_item_id FROM cart_items WHERE cartID = ? AND prodID = ?";
                    $itemStmt = $_db->prepare($itemQuery);
                    $itemStmt->execute([$cartID, $prodID]);
                    $cartItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cartItem) {
                        throw new Exception("Item not found in cart");
                    }
                    
                    $cartItemId = $cartItem['cart_item_id'];
                    
                    // Remove item using cart_item_id
                    $removeQuery = "DELETE FROM cart_items WHERE cart_item_id = ?";
                    $removeStmt = $_db->prepare($removeQuery);
                    $removeStmt->execute([$cartItemId]);
                    
                    if ($removeStmt->rowCount() > 0) {
                        $message = "Item removed from cart";
                    } else {
                        throw new Exception("Item not found in cart");
                    }
                } else {
                    throw new Exception("Invalid product ID");
                }
                break;
                
            case 'clear':
                $clearQuery = "DELETE FROM cart_items WHERE cartID = ?";
                $clearStmt = $_db->prepare($clearQuery);
                $clearStmt->execute([$cartID]);
                $message = "Cart cleared successfully";
                break;
                
            default:
                throw new Exception("Invalid action");
        }
        
        // Get updated cart data
        $cartData = get_cart($userID);
        $totals = cartTotals($cartData);
        
        // Update cart count in session
        $_SESSION['cart_count'] = $totals['itemCount'];
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => [
                'cart' => $cartData,
                'totals' => $totals
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    exit();
}

// Regular cart page display (non-AJAX)
checkLogin();

$user_id = $_SESSION['user_id'] ?? null;

$cart = get_cart($user_id);
$totals = cartTotals($cart);

$cartByProductId = [];
foreach ($cart as $item) {
    $cartByProductId[$item['id']] = $item;
}

include '../header.php'; ?>

<script src="../js/cart.js" defer></script>
<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/cart.css">


<main class="container-cart" data-user-id="<?= $user_id ?>">
    <section class="cart-card">
        <h1 class="cart-title">Shopping Cart</h1>
        <div class="cart-actions">
            <button type="button" id="select-all" data-checked="false"<?=empty($cart) ? ' disabled' : ''?>>Select All</button>
            <button type="button" id="clear-selected" <?= empty($cart) ? 'disabled' : '' ?>>Clear Selected</button>
        </div>

        <div id="cart-items">
            <?php if(empty($cart)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                </div>
            <?php else: ?>                
                <?php foreach($cartByProductId as $prodID => $row): ?>
                    <?php 
                        $p = $row['product']; 
                        $stock = $p['stock'] ?? 0;
                    ?>

                    <div class="cart-row" data-id="<?= htmlspecialchars($prodID)?>">
                        <input type="checkbox" class="item-check">

                        <div class="cart-item-image">
                            <img src="<?= htmlspecialchars($p['img'])?>" alt="<?= htmlspecialchars($p['title'])?>" class="imgCart" loading="lazy" onerror="this.src='/images/placeholder.jpg'">
                        </div>
                        
                        <div class="cart-item-details">
                            <div class="cart-title-color">
                                <div class="title"><?= htmlspecialchars($p['title'])?></div>
                                <div class="color"><?= htmlspecialchars($p['color'])?></div>
                            </div>

                            <div class="price-stock">
                                <div class="price"><?= money($p['price'])?></div>

                                <!-- Stock warnings -->
                                <?php if ($stock <= 10 && $stock > 0): ?>
                                    <p class="stock-warning">Only <?= $stock ?> left in stock</p>
                                <?php elseif ($stock === 0): ?>
                                    <p class="out-of-stock">Out of stock</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="qty">
                            <button type="button" class="dec" data-id="<?= htmlspecialchars($prodID)?>" <?= $row['qty'] <= 1 ? 'disabled' : '' ?> aria-label="Decrease quantity">-</button>
                            <input type="number" value="<?= $row['qty']?>" class="qty-input" min="1" max="<?= $stock ?>" data-id="<?= htmlspecialchars($prodID)?>">
                            <button type="button" class="inc" data-id="<?= htmlspecialchars($prodID)?>" <?= $row['qty'] >= $stock ? 'disabled' : '' ?> aria-label="Increase quantity">+</button>
                        </div>
                                                
                        <?php $rowSubtotal = $p['price'] * $row['qty']; ?>
                        <div id="subtotal" class="subtotal"><?= money($rowSubtotal)?></div>
                        <button type="button" class="remove" data-id="<?= htmlspecialchars($prodID)?>" aria-label="Remove <?= htmlspecialchars($p['title']) ?> from cart">Remove</button>

                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
        
        <?php if (!empty($cart)): ?>
            <!-- Selected items summary row (hidden by default) -->
            <div id="selected-summary" class="selected-summary" style="display: none;">
                <div class="selected-info">
                    <div class="selected-quantity">
                        <span class="label">Quantity:</span>
                        <span id="selected-count">0</span>
                    </div>
                    <div class="selected-subtotal">
                        <span class="label">Subtotal:</span>
                        <span id="selected-total">RM 0.00</span>
                    </div>
                </div>
            </div>
            
            <!-- Checkout button row -->
            <section class="cart-checkout-section">
                <div class="cart-checkout">
                    <button class="btn-primary btn-large checkout-btn" 
                            onclick="proceedToCheckout()"
                            <?= empty($cart) ? 'disabled' : '' ?>>
                        Checkout
                    </button>
                </div>
            </section>
        <?php endif ?>
    </section>
</main>

<body data-user-id="<?= $user_id ?>">

<script>
// Initialize cart functionality once DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Cart page loaded, user ID:', '<?= $user_id ?>');
});
</script>

<?php include '../footer.php'; ?>
