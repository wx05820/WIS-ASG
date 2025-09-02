<?php
require_once '../_base.php';
include 'cart.php';

checkLogin();
$user_id = $_SESSION['user_id'] ?? null;

$cart = get_cart($user_id);
$totals = cartTotals($cart);

$cartByProductId = [];
foreach ($cart as $item) {
    $cartByProductId[$item['id']] = $item;
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

include '../header.php'; ?>

<script src="../js/cart.js" defer></script>
<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/cart.css">

<main class="container-cart" data-user-id="<?= htmlspecialchars($user_id) ?>">
    <section class="cart-card">
        <h1 class="cart-title">Your Shopping Cart</h1>
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
                        $stmStock = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
                        $stmStock->execute([$prodID]);
                        $stock = $stmStock->fetchColumn() ?: 0;
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
                                    <p class="out-of-stock">❌ Out of stock</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="qty">
                            <button type="button" class="dec" data-id="<?= htmlspecialchars($prodID)?>" aria-label="Decrease quantity">-</button>
                            <input type="number" value="<?= $row['qty']?>" class="qty-input" min="0" max="<?= $stock ?>" data-id="<?= htmlspecialchars($prodID)?>">
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
            <section class="cart-summary-section">
                <div class="cart-summary">
                    <div id="totals" class="totals">
                        <div class="totals-row">    
                            <span>Total Items: <strong><?= $totals['itemCount'] ?></strong></span>
                        </div>
                        <div class="totals-row total">
                            <span>Total: <strong><?= money($totals['total'], 2) ?></strong></span>
                        </div>
                    </div>
                    
                    <div class="cart-checkout">
                        <button class="btn-primary btn-large checkout-btn" onclick="proceedToCheckout()"<?= empty($cart) ? 'disabled' : '' ?>>
                            Checkout
                        </button>
                    </div>
                </div>
            </section>
        <?php endif ?>
    </section>
</main>

<body data-user-id="<?= $user_id ?>">

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Cart page loaded, user ID:', '<?= $user_id ?>');

    // Initialize cart actions and button handlers
    if (typeof initCartActions === 'function') {
        initCartActions();
    }
    
    if (typeof addCartItemEventListeners === 'function') {
        addCartItemEventListeners();
    }

    // Load initial cart state
    updateSubtotal();
    updateButtonStates();
});

document.addEventListener('DOMContentLoaded', function() {
    // Handle quantity buttons
    function postUpdateQty(prodID, qty) {
        const formData = new FormData();
        formData.append('action', 'update_qty');
        formData.append('prodID', prodID);
        formData.append('qty', qty);
        fetch('/order/cart.php?action=update_qty', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(() => window.location.reload());
    }

    document.querySelectorAll('.dec').forEach(button => {
        button.addEventListener('click', function() {
            const qtyInput = this.parentElement.querySelector('.qty-input');
            const currentQty = parseInt(qtyInput.value || '0', 10);
            const prodID = this.dataset.id;
            if (currentQty > 1) {
                postUpdateQty(prodID, currentQty - 1);
            } else {
                // Going from 1 to 0 will remove the item – ask for confirmation
                const prodName = this.closest('.cart-row')?.querySelector('.title')?.textContent?.trim() || 'this item';
                if (confirm(`Remove ${prodName} from cart?`)) {
                    postUpdateQty(prodID, 0);
                }
            }
        });
    });

    document.querySelectorAll('.inc').forEach(button => {
        button.addEventListener('click', function() {
            const qtyInput = this.parentElement.querySelector('.qty-input');
            const maxQty = parseInt(qtyInput.max);
            const currentQty = parseInt(qtyInput.value);
            const prodID = this.dataset.id;
            if (currentQty < maxQty) {
                postUpdateQty(prodID, currentQty + 1);
            }
        });
    });

    // Confirm when user manually types 0
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            let val = parseInt(this.value || '0', 10);
            const max = parseInt(this.getAttribute('max') || '9999', 10);
            const min = parseInt(this.getAttribute('min') || '0', 10);
            const prodID = this.dataset.id;

            // Clamp between min & max
            if (val > max) val = max;
            if (val < min) val = min;

            if (val === 0) {
                const prodName = this.closest('.cart-row')?.querySelector('.title')?.textContent?.trim() || 'this item';
                if (!confirm(`Remove ${prodName} from cart?`)) {
                    this.value = 1;
                    return;
                }
            }
            postUpdateQty(prodID, val);
        });
    });

    // Handle select all
    const selectAllBtn = document.getElementById('select-all');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.item-check');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
            this.textContent = !allChecked ? 'Unselect All' : 'Select All';
            updateTotals();
        });
    }

    // Handle checkbox changes
    document.querySelectorAll('.item-check').forEach(checkbox => {
        checkbox.addEventListener('change', updateTotals);
    });

    updateTotals(); // Initial calculation
});

function updateTotals() {
    const checkboxes = document.querySelectorAll('.item-check:checked');
    let totalItems = 0;
    let totalAmount = 0;

    checkboxes.forEach(cb => {
        const row = cb.closest('.cart-row');
        const qty = parseInt(row.querySelector('.qty-input').value);
        const price = parseFloat(row.querySelector('.price').textContent.replace(/[^\d.]/g, ''));
        
        totalItems += qty;
        totalAmount += price * qty;
    });

    document.getElementById('total-items').textContent = totalItems;
    document.getElementById('total-amount').textContent = totalAmount.toFixed(2);
    
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.disabled = totalItems === 0;
    }
}

function proceedToCheckout() {
    const selectedItems = [...document.querySelectorAll(".cart-row")].filter(row => row.querySelector(".item-check")?.checked);
    
    if (selectedItems.length === 0) {
        alert('Please select items to checkout');
        return;
    }

    // Check if any selected items are out of stock
    const outOfStock = selectedItems.some(row => {
        const qtyInput = row.querySelector(".qty-input");
        const stock = parseInt(qtyInput.max) || 0;
        const qty = parseInt(qtyInput.value) || 0;
        return stock === 0 || qty > stock;
    });
    
    if (outOfStock) {
        showError("Some selected items are out of stock in your cart");
        return;
    }

    // Store selected items properly for checkout page
    const selectedData = selectedItems.map(row => {
        const qtyInput = row.querySelector('.qty-input');
        const priceEl = row.querySelector('.price');
        return {
            id: row.dataset.id,
            qty: parseInt(qtyInput.value) || 1,
            price: parseFloat(priceEl.textContent.replace(/[^\d.]/g, '')) || 0
        };
    });
    
    // Store in window object for checkout page to access
    window.checkoutSelectedItems = selectedData;
    
    // Also send via form POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/order/checkout.php';
    
    selectedData.forEach((item, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `selected_items[${index}]`;
        input.value = item.id;
        form.appendChild(input);
    });

    /* // Create form with selected items
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/order/checkout.php';
    
    selectedItems.forEach((cb, index) => {
        const row = cb.closest('.cart-row');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `selected_items[${index}]`;
        input.value = row.dataset.id;
        form.appendChild(input);
    }); */
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include '../footer.php';