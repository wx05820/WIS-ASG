<?php
require_once '../_base.php';
include 'cart.php';

checkLoginAndPrompt('../login.php');

$user_id = $_SESSION['user_id'];
$current_user = getCurrentUser();

if (!$current_user) {
    temp('error', 'Unable to load user profile.');
    redirect('login.php');
}

$stmt = $_db->prepare("
    SELECT ci.prodID, ci.qty, p.name, p.price, p.image1, p.color
    FROM cart_items ci
    JOIN cart c ON ci.cartID = c.cartID
    JOIN product p ON ci.prodID = p.prodID
    WHERE c.userID =? "
);

$stmt->execute([$user_id]);
$cartdb = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cart = get_cart($user_id);
$totals = cartTotals($cart);

include '../header.php'; ?>

<script src="/js/cart.js" defer></script>
<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/cart.css">

<main class="container-cart">
    <section class="cart-card">
        <h1 class="cart-title">Shopping Cart</h1>
        <div class="cart-actions">
            <button id="select-all" data-checked="false">Select All</button>
            <button id="clear-selected">Clear Selected</button>
        </div>

        <div id="cart-items">
            <?php if(empty($cart)): ?>
                <p class="empty">Your cart is empty.</p>
            <?php else: ?>                
                <?php foreach($cart as $prodID=>$row): ?>
                        <?php 
                            $p=$row['product']; 
                            $stmStock = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
                            $stmStock->execute([$prodID]);
                            $stock = $stmStock->fetchColumn() ?: 0;
                        ?>

                <div class="cart-row" data-id="<?= htmlspecialchars($prodID)?>">
                        <input type="checkbox" class="item-check" checked>
                        <img src="<?= htmlspecialchars($p['img'])?>" alt="<?= htmlspecialchars($p['title'])?>" class="imgCart">
                        <div class="title"><?= htmlspecialchars($p['title'])?></div>
                        <div class="color"><?= htmlspecialchars($p['color'])?></div>
                        <div class="price"><?= money($p['price'])?></div>
                        <div class="qty">
                            <button class="dec">-</button>
                            <input type="number" value="<?= $row['qty']?>" class="qty-input" min="1" max="<?= $stock ?>">
                            <button class="inc">+</button>
                        </div>
                        <button class="remove" data-id="<?= htmlspecialchars($prodID)?>">Remove</button>
                </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
        <div class="totals">
            <strong>Total Items: <?= $totals['itemCount'] ?></strong><br>
            <strong>Subtotal: <?= money($totals['subtotal']) ?></strong><br>
            <strong>Total: <?= money($totals['total']) ?></strong>
        </div>
    </section>
</main>

<?php include '../footer.php';