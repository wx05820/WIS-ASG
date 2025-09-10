<?php
require_once '../_base.php';

checkLogin();

$user_id = $_SESSION['user_id'];

// Handle add/remove actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $prodID = trim($_POST['prodID'] ?? '');

    if ($prodID !== '') {
        if ($action === 'add') {
            $stm = $_db->prepare('SELECT COUNT(*) FROM wishlist WHERE userID=? AND prodID=?');
            $stm->execute([$user_id, $prodID]);
            $count = $stm->fetchColumn();
            
            // Debug logging
            error_log("Wishlist add check - User: $user_id, Product: $prodID, Count: $count");
            
            if (!$count) {
                $ins = $_db->prepare('INSERT INTO wishlist(userID, prodID, created_at) VALUES(?, ?, NOW())');
                $ins->execute([$user_id, $prodID]);
                $_SESSION['success'] = 'Added to wishlist';
                $response = ['ok' => true, 'message' => 'Added to wishlist', 'action' => 'add'];
            } else {
                // Item already exists, automatically switch to remove mode
                $del = $_db->prepare('DELETE FROM wishlist WHERE userID=? AND prodID=?');
                $del->execute([$user_id, $prodID]);
                $_SESSION['success'] = 'Removed from wishlist';
                $response = ['ok' => true, 'message' => 'Removed from wishlist (was already added)', 'action' => 'remove', 'auto_switched' => true];
            }
        } elseif ($action === 'remove') {
            $del = $_db->prepare('DELETE FROM wishlist WHERE userID=? AND prodID=?');
            $del->execute([$user_id, $prodID]);
            $_SESSION['success'] = 'Removed from wishlist';
            $response = ['ok' => true, 'message' => 'Removed from wishlist', 'action' => 'remove'];
        } elseif ($action === 'check_status') {
            $productIds = json_decode($_POST['product_ids'] ?? '[]', true);
            error_log("Wishlist check_status - User: $user_id, Product IDs: " . json_encode($productIds));
            
            if (is_array($productIds) && !empty($productIds)) {
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $stm = $_db->prepare("SELECT prodID FROM wishlist WHERE userID = ? AND prodID IN ($placeholders)");
                $stm->execute(array_merge([$user_id], $productIds));
                $wishlistItems = array_column($stm->fetchAll(PDO::FETCH_ASSOC), 'prodID');
                error_log("Wishlist check_status - Found items: " . json_encode($wishlistItems));
                $response = ['ok' => true, 'wishlist_items' => $wishlistItems];
            } else {
                error_log("Wishlist check_status - No product IDs provided");
                $response = ['ok' => true, 'wishlist_items' => []];
            }
        }
    }
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response ?? ['ok' => false, 'message' => 'Invalid action']);
        exit;
    }
    redirect('/user/wishlist.php');
}

// Fetch wishlist
$sql = "SELECT w.*, p.name, p.price, p.image1, p.qty AS stock
        FROM wishlist w
        LEFT JOIN product p ON w.prodID = p.prodID
        WHERE w.userID = ?
        ORDER BY w.created_at DESC";
$stm = $_db->prepare($sql);
$stm->execute([$user_id]);
$items = $stm->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Wishlist';
include '../header.php';
?>

<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/wishlist.css">
<main class="container" style="padding:20px 0;">
    <h1>My Wishlist</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p>Your wishlist is empty.</p>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($items as $it): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if (!empty($it['image1'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($it['image1']); ?>" alt="<?php echo htmlspecialchars($it['name'] ?? 'Unavailable Product'); ?>" loading="lazy" onerror="this.src='../images/placeholder.jpg'">
                        <?php else: ?>
                            <img src="../images/placeholder.jpg" alt="No image">
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars($it['name'] ?? 'Product removed by admin'); ?></h3>
                        <div class="product-meta">
                            <span class="stock <?php echo ($it['stock'] ?? 0) > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo ($it['stock'] ?? 0) > 0 ? 'In Stock' : 'Unavailable'; ?>
                            </span>
                        </div>
                        <div class="product-price" style="display:flex;align-items:center;justify-content:space-between;">
                            <span class="price"><?php echo isset($it['price']) ? 'RM ' . number_format($it['price'], 2) : '-'; ?></span>
                            <form method="post" class="wishlist-form" onsubmit="return confirmRemoveWishlist(this)">
                                <input type="hidden" name="prodID" value="<?php echo htmlspecialchars($it['prodID']); ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="btn btn-secondary"><i class="fas fa-trash"></i> Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../footer.php';


