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

<body class="product-list-main" data-user-id="<?= htmlspecialchars($user_id ?? ''); ?>">
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
                                            <div class="qty-selector" style="display:flex;align-items:center;gap:8px;margin:8px 0;">
                                                <label for="list-qty-<?= $p['prodID']; ?>" style="min-width:60px;">Qty</label>
                                                <div class="qty-control" style="display:flex;align-items:center;gap:6px;">
                                                    <button type="button" class="qty-btn" data-target="#list-qty-<?= $p['prodID']; ?>" aria-label="Decrease quantity" style="padding:6px 10px;">−</button>
                                                    <input id="list-qty-<?= $p['prodID']; ?>" name="qty" type="number" value="1" min="1" max="<?= (int)$p['qty']; ?>" style="width:80px;padding:6px;">
                                                    <button type="button" class="qty-btn" data-target="#list-qty-<?= $p['prodID']; ?>" data-op="plus" aria-label="Increase quantity" style="padding:6px 10px;">+</button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI']; ?>">
                                            <button type="submit" class="btn-add">Add to Cart</button>
                                        </form>
                                        
                                        <!-- Buy Now Form -->
                                        <form action="../order/checkout.php" method="POST" class="checkout-form" onsubmit="return handleFormSubmit(this)">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <input type="hidden" name="buy_now" value="1">
                                            <button type="submit" class="btn-checkout">Buy Now</button>
                                        </form>

                                        <!-- Add to Wishlist -->
                                        <form action="../user/wishlist.php" method="POST" class="wishlist-form" onsubmit="return handleFormSubmit(this)" style="margin-top:6px;">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <button type="submit" class="btn-secondary btn-wishlist"><i class="fas fa-heart"></i> Wishlist</button>
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

<?php include '../footer.php'; ?>