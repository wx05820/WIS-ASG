<?php
require_once '../_base.php';
include 'cart.php';

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
            <button id="select-all" data-checked="false"<?=empty($cart) ? ' disabled' : ''?>>Select All</button>
            <button id="clear-selected" <?= empty($cart) ? 'disabled' : '' ?>>Clear Selected</button>
        </div>

        <div id="cart-items">
            <?php if(empty($cart)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="../products/" class="btn-primary">Continue Shopping</a>
                </div>
            <?php else: ?>                
                <?php foreach($cartByProductId as $prodID => $row): ?>
                    <?php 
                        $p = $row['product']; 
                        $stmStock = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
                        $stmStock->execute([$prodID]);
                        $stock = $stmStock->fetchColumn() ?: 0;
                    ?>

                    <div class="cart-row" data-id="<?= htmlspecialchars($prodID)?>">
                        <input type="checkbox" class="item-check" checked>

                        <div class="cart-item-image">
                            <img src="<?= htmlspecialchars($p['img'])?>" alt="<?= htmlspecialchars($p['title'])?>" class="imgCart" loading="lazy" onerror="this.src='/images/placeholder.jpg'">
                        </div>
                        
                        <div class="cart-item-details">
                            <div class="title"><?= htmlspecialchars($p['title'])?></div>
                            <div class="color"><?= htmlspecialchars($p['color'])?></div>
                            <div class="price">RM <?= number_format($p['price'], 2)?></div>

                            <!-- Stock warnings -->
                            <?php if ($stock <= 5 && $stock > 0): ?>
                                <p class="stock-warning">Only <?= $stock ?> left in stock</p>
                            <?php elseif ($stock === 0): ?>
                                <p class="out-of-stock">Out of stock</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="qty">
                            <button type="button" class="dec" data-id="<?= htmlspecialchars($prodID)?>" <?= $row['qty'] <= 1 ? 'disabled' : '' ?> aria-label="Decrease quantity">-</button>
                            <input type="number" value="<?= $row['qty']?>" class="qty-input" min="1" max="<?= $stock ?>" data-id="<?= htmlspecialchars($prodID)?>">
                            <button type="button" class="inc" data-id="<?= htmlspecialchars($prodID)?>" <?= $row['qty'] >= $stock ? 'disabled' : '' ?> aria-label="Increase quantity">+</button>
                        </div>
                        
                        <button type="button" class="remove" data-id="<?= htmlspecialchars($prodID)?>" aria-label="Remove <?= htmlspecialchars($p['title']) ?> from cart">Remove</button>
                        
                        <?php $rowSubtotal = $p['price'] * $row['qty']; ?>
                        <div class="subtotal">RM <?= number_format($rowSubtotal, 2)?></div>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
        
        <?php if (!empty($cart)): ?>
            <div class="cart-summary">
                <div id="totals" class="totals">
                    <div class="totals-row">
                        <span>Total Items:</span>
                        <strong><?= $totals['itemCount'] ?></strong>
                    </div>
                    <div class="totals-row subtotal">
                        <span>Total:</span>
                        <strong>RM <?= number_format($totals['total'], 2) ?></strong>
                    </div>
                </div>
                
                <div class="cart-checkout">
                    <button class="btn-primary btn-large checkout-btn" 
                            onclick="proceedToCheckout()"
                            <?= empty($cart) ? 'disabled' : '' ?>>
                        Proceed to Checkout
                    </button>
                    <a href="../products/" class="btn-secondary btn-continue">Continue Shopping</a>
                </div>
            </div>
        <?php endif ?>
    </section>
</main>

<body data-user-id="<?= $user_id ?>">
<!-- Move this script to initialize properly -->
<script>
// Initialize cart functionality once DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Cart page loaded, user ID:', '<?= $user_id ?>');
});
</script>

<?php include '../footer.php';