<?php
require_once '../_base.php';
include '../lib/SimplePager.php';
include '../header.php';

// Get search & filter inputs
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$room = isset($_GET['room']) ? trim($_GET['room']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// SQL base
$sql = "SELECT p.*, c.name AS category_name 
        FROM product p 
        LEFT JOIN category c ON p.catID = c.catID 
        WHERE 1=1";   

$params = [];

if (!empty($query)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
}
if (!empty($category)) {
    $sql .= " AND c.name = ?";
    $params[] = $category;
}
if (!empty($room)) {
    $sql .= " AND p.description LIKE ?";
    $params[] = "%$room%";
}

switch($sort) {
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'stock_asc':
        $sql .= " ORDER BY p.qty ASC";
        break;
    case 'stock_desc':
        $sql .= " ORDER BY p.qty DESC";
        break;
    default:
        $sql .= " ORDER BY p.prodID ASC";
}

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// Get current page
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
$pager = new SimplePager($sql, $params, 12, $page);
$products = $pager->result;

// Convert BLOB image to Base64
foreach ($products as &$p) {
    $p['img'] = !empty($p['image1'])
        ? 'data:image/jpeg;base64,' . base64_encode($p['image1'])
        : '/images/placeholder.jpg';
}
unset($p);
?>

<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/productList.css">
<script src="../js/cart.js" defer></script>
<script src="../js/userProduct.js" defer></script>

<body data-user-id="<?= htmlspecialchars($user_id ?? ''); ?>">
    <main class="product-list">
        <?php if($pager->count > 0): ?>
            <?php foreach ($products as $p): ?>
                <div class="product-card" data-id="<?= $p['prodID']; ?>">
                    <div class="product-img">
                        <img src="<?= htmlspecialchars($p['img']); ?>" 
                            alt="<?= htmlspecialchars($p['name']); ?>" 
                            class="product-img" loading="lazy"
                            onerror="this.src='/images/placeholder.jpg'">
                    </div>
                    
                    <div class="product-info">
                        <h3><?= htmlspecialchars($p['name']); ?></h3>
                        <p class="category">Category: <?= htmlspecialchars($p['category_name']); ?></p>
                        <p class="price"><?= money($p['price']); ?></p>
                        <p class="stock <?= ($p['qty'] > 0 ? 'in-stock' : 'out-stock'); ?>">
                            <?= $p['qty'] > 0 ? "In Stock: {$p['qty']}" : "Out of Stock"; ?>
                        </p>
                        <div class="actions" onclick="event.stopPropagation();">
                            <?php if ($p['qty'] > 0): ?>
                                <?php if ($user_id): ?>
                                        <!-- Add to Cart Form -->
                                        <form action="../order/cart_add.php" method="POST" class="cart-form" onsubmit="return handleFormSubmit(this)" id="btn-add">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <input type="hidden" name="qty" value="1">
                                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI']; ?>">
                                            <button type="submit" class="btn-add">Add to Cart</button>
                                        </form>
                                        
                                        <!-- Buy Now Form -->
                                        <form action="../order/checkout.php" method="POST" class="checkout-form" onsubmit="return handleFormSubmit(this)">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <input type="hidden" name="buy_now" value="1">
                                            <button type="submit" class="btn-checkout">Buy Now</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn-add" onclick="showLoginPrompt()">
                                            Add to Cart
                                        </button>
                                        <button type="button" class="btn-checkout" onclick="showLoginPrompt()">
                                            Buy Now
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-disabled" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <h3>No products found</h3>
                <p>Try adjusting your search criteria or browse all products.</p>
            </div>
        <?php endif; ?>
    </main>
</body>

<?php
// Pager links (always show if more than 1 page)
$params_array = [
    'query'    => $query ?? null,
    'category' => $category ?? null,
    'room'     => $room ?? null,
    'sort'     => $sort ?? null
];
$params_array = array_filter($params_array); // drop empty ones
$href = http_build_query($params_array);

echo $pager->html($href);
?>

<script>
    function handleFormSubmit(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            
            if (submitBtn.classList.contains('btn-add')) {
                submitBtn.textContent = 'Adding...';
            } else if (submitBtn.classList.contains('btn-checkout')) {
                submitBtn.textContent = 'Processing...';
            }
            
            // Re-enable after 3 seconds in case of error
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }, 3000);
        }
        return true;
    }

    function showLoginPrompt() {
        showError('Please log in to add items to your cart');
        setTimeout(() => {
            window.location.href = '../user/login.php';
        }, 2000);
    }

    function showError(message) {
        showNotification(message, 'error');
    }

    function showSuccess(message) {
        showNotification(message, 'success');
    }

    function showNotification(message, type = 'error') {
        // Remove any existing notifications
        const existing = document.querySelector('.list-notification');
        if (existing) {
            existing.remove();
        }
        
        const notificationDiv = document.createElement('div');
        notificationDiv.className = 'list-notification';
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

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initAddToCartButtons === "function") {
            initAddToCartButtons();
        }

        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('form')) {
                    e.stopPropagation();
                    return;
                }
                const prodID = this.dataset.id;
                window.location.href = `product_detail.php?prodID=${prodID}`;
            });
        });
    });
</script>

<?php include '../footer.php'; ?>