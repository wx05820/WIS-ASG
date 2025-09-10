<?php
require_once '../_base.php';
include '../lib/SimplePager.php';
include '../header.php';

// Check if user is banned (only if logged in)
if (isset($_SESSION['user_id'])) {
    checkUserStatus();
}

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
    WHERE p.status IS NULL";

$params = [];

if (!empty($query)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$query%";
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

// Determine order direction (ASC/DESC) from separate parameter; default to ASC
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if ($order !== 'ASC' && $order !== 'DESC') $order = 'ASC';

// Validate sort column and build ORDER BY using the column plus order direction
$allowed_sorts = ['name' => 'p.name', 'price' => 'p.price', 'qty' => 'p.qty'];
if (!empty($sort) && isset($allowed_sorts[$sort])) {
    $sql .= " ORDER BY " . $allowed_sorts[$sort] . " $order";
} else {
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

// Get wishlist status for all products if user is logged in
$wishlistItems = [];
if ($user_id && isset($_db) && $_db !== null) {
    try {
        $wishlistStmt = $_db->prepare("SELECT prodID FROM wishlist WHERE userID = ?");
        $wishlistStmt->execute([$user_id]);
        $wishlistItems = array_column($wishlistStmt->fetchAll(PDO::FETCH_ASSOC), 'prodID');
    } catch (Exception $e) {
        error_log("Wishlist query error: " . $e->getMessage());
        $wishlistItems = [];
    }
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
<link rel="stylesheet" href="../css/userproduct.css">
<script src="../js/cart.js" defer></script>
<script src="../js/userproduct.js" defer></script>
<script>
// Initialize product list functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product list page loaded');
    console.log('Checking for cart.js...');
    
    // Check if cart.js functions are available
    if (typeof initializeFormHandlers === 'function') {
        console.log('✅ cart.js functions available');
    } else {
        console.log('❌ cart.js functions NOT available');
    }
    
    // Let cart.js handle all form submissions
    console.log('Delegating form handling to cart.js');
    
    // Cart functionality is working, no test button needed
});
</script>

<body class="product-list-main" data-user-id="<?= htmlspecialchars($user_id ?? ''); ?>">
    <main class="product-list">
        <?php if($pager->count > 0): ?>
            <?php foreach ($products as $p): ?>
                <div class="product-card" data-id="<?= $p['prodID']; ?>" onclick="window.location.href='product_detail.php?prodID=<?= urlencode($p['prodID']); ?>'" style="cursor: pointer;">
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
                                        <form action="../order/cart_add.php" method="POST" class="cart-form" id="btn-add-<?= $p['prodID']; ?>" data-action="../order/cart_add.php" data-prod-id="<?= $p['prodID']; ?>">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <div class="qty-selector" style="display:flex;align-items:center;gap:8px;margin:8px 0;">
                                                <label for="list-qty-<?= $p['prodID']; ?>" style="min-width:60px;">Qty</label>
                                                <div class="qty-control" style="display:flex;align-items:center;gap:6px;">
                                                    <button type="button" class="qty-btn" data-target="#list-qty-<?= $p['prodID']; ?>" aria-label="Decrease quantity" style="padding:6px 10px;">−</button>
                                                    <input id="list-qty-<?= $p['prodID']; ?>" type="number" value="1" min="1" max="<?= (int)$p['qty']; ?>" style="width:80px;padding:6px;">
                                                    <button type="button" class="qty-btn" data-target="#list-qty-<?= $p['prodID']; ?>" data-op="plus" aria-label="Increase quantity" style="padding:6px 10px;">+</button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="qty" value="1">
                                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI']; ?>">
                                            <button type="submit" class="btn-add" id="add-btn-<?= $p['prodID']; ?>">Add to Cart</button>
                                        </form>
                                        
                                        <!-- Buy Now Form -->
                                        <form action="../order/checkout.php" method="POST" class="checkout-form" id="buy-now-<?= $p['prodID']; ?>" onsubmit="return setQtyForBuyNow(this)">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <input type="hidden" name="buy_now" value="1">
                                            <input type="hidden" name="qty" value="1">
                                            <button type="submit" class="btn-checkout" id="buy-btn-<?= $p['prodID']; ?>">Buy Now</button>
                                        </form>

                                        <!-- Add to Wishlist -->
                                        <form action="../user/wishlist.php" method="POST" class="wishlist-form" id="wishlist-<?= $p['prodID']; ?>" data-action="../user/wishlist.php" style="margin-top:6px;">
                                            <input type="hidden" name="action" value="<?= in_array($p['prodID'], $wishlistItems) ? 'remove' : 'add'; ?>">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <button type="submit" class="btn-secondary btn-wishlist <?= in_array($p['prodID'], $wishlistItems) ? 'in-wishlist' : ''; ?>" id="wishlist-btn-<?= $p['prodID']; ?>">
                                                <i class="fas fa-heart" style="<?= in_array($p['prodID'], $wishlistItems) ? 'color: #e74c3c;' : ''; ?>"></i> 
                                                <span class="wishlist-text"><?= in_array($p['prodID'], $wishlistItems) ? 'In Wishlist' : 'Wishlist'; ?></span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn-add" onclick="showLoginPrompt()">
                                            Add to Cart
                                        </button>
                                        <button type="button" class="btn-checkout" onclick="showLoginPrompt()">
                                            Buy Now
                                        </button>
                                        <button type="button" class="btn-secondary btn-wishlist" onclick="showLoginPrompt()" style="margin-top:6px;">
                                            <i class="fas fa-heart"></i> Wishlist
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

<script>
function showLoginPrompt() {
    // Create a more prominent login prompt
    const loginModal = document.createElement('div');
    loginModal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
    `;
    
    modalContent.innerHTML = `
        <div style="font-size: 48px; color: #8B4513; margin-bottom: 20px;">
            <i class="fas fa-lock"></i>
        </div>
        <h3 style="color: #8B4513; margin-bottom: 15px;">Login Required</h3>
        <p style="color: #666; margin-bottom: 25px; line-height: 1.5;">
            Please log in to add items to your cart or make a purchase.
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button onclick="window.location.href='../user/login.php'" 
                    style="background: #8B4513; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button onclick="closeLoginModal()" 
                    style="background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer;">
                Cancel
            </button>
        </div>
    `;
    
    // Add CSS animations if not already present
    if (!document.querySelector('#login-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'login-modal-styles';
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideIn {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    loginModal.appendChild(modalContent);
    document.body.appendChild(loginModal);
    
    // Close modal when clicking outside
    loginModal.addEventListener('click', function(e) {
        if (e.target === loginModal) {
            closeLoginModal();
        }
    });
    
    // Store reference for closing
    window.currentLoginModal = loginModal;
}

function closeLoginModal() {
    if (window.currentLoginModal) {
        window.currentLoginModal.remove();
        window.currentLoginModal = null;
    }
}

function setQtyForBuyNow(form) {
    const qtyInput = form.querySelector('input[id*="qty"]:not([name="qty"])');
    const hiddenQty = form.querySelector('input[name="qty"]');
    if (qtyInput && hiddenQty) {
        const val = parseInt(qtyInput.value) || 1;
        hiddenQty.value = Math.max(1, val);
    }
    return true;
}
</script>

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

<?php include '../footer.php'; ?>