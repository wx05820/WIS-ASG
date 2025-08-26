<?php
require_once '../_base.php';

checkLoginAndRedirect('../login.php');

$user_id = $_SESSION['user_id'];
$current_user = getCurrentUser();

if (!$current_user) {
    temp('error', 'Unable to load user profile.');
    redirect('login.php');
}

if (is_post()){
    $btn = req('btn');
    if ($btn == 'clear'){
        set_cart();
        redirect('?');
    }

    $id = req('id');
    $qty = req('qty');
    update_cart($id, $qty);
    redirect();
}
 
$cart = $_SESSION['cart'] ?? [];

include '../header.php'; ?>

<script src="/js/cart.js" defer></script>
<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/cart.css">

<main class="container-cart">
    <section class="cart-card">
        <h1 class="cart-title">Shopping Cart</h1>
        <div id="cart-items">
            <?php if(empty($cart)): ?>
                <p class="empty">Your cart is empty.</p>
            <?php else: ?>
                <?php 
                    foreach($cart as $id=>$row):
                        $p=$row['product'];
                ?>

                <div class="cart-row" data-id="<?= $id?>">
                        <input type="checkbox" class="item-check" <?= !empty($row['selected'])?'checked':''?>>
                        <img src="<?= $p['img']?>" alt="<?= htmlspecialchars($p['title'])?>">
                        <div class="title"><?= htmlspecialchars($p['title'])?></div>
                        <div class="colour"><?= htmlspecialchars($p['colour'])?></div>
                        <div class="price"><?= money($p['price'])?></div>
                        <div class="qty">
                            <button class="dec">-</button>
                            <input type="number" value="<?= $row['qty']?>" class="qty-input">
                            <button class="inc">+</button>
                        </div>
                        <button class="remove">Remove</button>
                </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
        <div class="totals">
            <strong>Total: <span id="grant-total">RM 0.00</span></strong>
        </div>
    </section>
</main>

<?php include '../footer.php';