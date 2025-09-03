<?php
include '../config.php';

// Restore logic
$message = '';
if (isset($_GET['restore']) && !empty($_GET['restore'])) {
    $prodID = $_GET['restore'];
    $sql = "UPDATE product SET status = NULL WHERE prodID = ?";
    $stmt = $_db->prepare($sql);
    if ($stmt->execute([$prodID])) {
        $message = "Product restored successfully.";
    } else {
        $message = "Failed to restore product.";
    }
}

// Fetch removed products

// Get filter/search/sort parameters
$search = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Get categories for filter dropdown
$cat_sql = "SELECT catID, name as categoryName FROM category ORDER BY name";
$cat_stmt = $_db->prepare($cat_sql);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE clause for removed products
$where_conditions = ["status = 'removed'"];
$params = [];
if (!empty($category)) {
    $where_conditions[] = "p.catID = ?";
    $params[] = $category;
}
if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';


$sql = "SELECT p.prodID, p.name, p.price, p.qty, p.description, p.color, p.catID, 
    COALESCE(c.name, 'Uncategorized') as categoryName
    FROM product p 
    LEFT JOIN category c ON p.catID = c.catID
    $where_clause 
    ORDER BY p.$sort $order";
$stmt = $_db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$page_title = "Restore Removed Products";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../css/products.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="product-list-main" style="margin-top:0; padding-top:0;">

    <?php include '../admin/adminheader.php'; ?>
    <div class="container">
        <?php if ($message): ?>
            <div class="message" style="color: green; background: #fff; border: 2px solid green; margin-bottom: 1rem; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <!-- Filters and Search Section (copied from list.php) -->
        <div class="filters-section">
            <div class="filters-container">
                <!-- Search Bar -->
                <div class="search-filter">
                    <form method="GET" action="" class="filter-form" style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" 
                               name="query" 
                               placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="search-input"
                               style="width: 350px; max-width: 100%; padding: 0.5rem 1rem; font-size: 1.1rem;">
                        <button type="submit" class="search-btn" style="padding: 0.5rem 1rem; font-size: 1.1rem;">
                            <i class="fas fa-search"></i>
                        </button>
                        <select name="category" onchange="this.form.submit()" class="filter-select" style="width: 180px; padding: 0.5rem 0.7rem; font-size: 1rem;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['catID']); ?>" 
                                        <?php echo ($category == $cat['catID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['categoryName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!empty($products)): ?>
            <div class="products-list">
                <?php foreach ($products as $product): ?>
                    <div class="product-list-item" style="align-items: flex-start;">
                        <div class="product-details">
                            <h3 class="product-name" style="margin:0 0 8px 0; font-size:1.2em; color:#c33;">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            <p class="product-id" style="margin:0; color:#666; font-size:0.9em;">
                                Product ID: <?php echo htmlspecialchars($product['prodID']); ?>
                            </p>
                            <p style="margin:0; color:#666; font-size:0.9em;">Price: RM <?php echo number_format((float)$product['price'], 2); ?></p>
                            <p style="margin:0; color:#666; font-size:0.9em;">Stock: <?php echo (int)$product['qty']; ?></p>
                            <p style="margin:0; color:#666; font-size:0.9em;">Description: <?php echo htmlspecialchars($product['description']); ?></p>
                            <p style="margin:0; color:#666; font-size:0.9em;">Category: <span style="color:#007bff;font-weight:600;"><?php echo htmlspecialchars($product['categoryName']); ?></span></p>
                            <p style="margin:0; color:#666; font-size:0.9em;">Color: <span style="display:inline-block;width:18px;height:18px;border-radius:4px;vertical-align:middle;margin-right:6px;background:<?php echo htmlspecialchars($product['color']); ?>;border:1px solid #ccc;"></span> <?php echo htmlspecialchars($product['color']); ?></p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <a href="?restore=<?php echo urlencode($product['prodID']); ?>" class="btn btn-primary" style="background: #28a745; color: white; border-radius: 20px; padding: 0.5rem 1.2rem; text-decoration: none; font-weight: 600; font-size: 1rem;">
                                <i class="fas fa-undo"></i> Restore
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products" style="text-align: center; padding: 3rem 1rem; color: #666;">
                <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <h3>No removed products found</h3>
            </div>
        <?php endif; ?>

        <div>
            <a href="list.php" class="btn btn-primary" style="margin-top: 1rem; display: inline-block; background: var(--wood-primary); color: white; padding: 0.7rem 1.5rem; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 1rem;">
                <i class="fas fa-arrow-left"></i> Back to Product List
            </a>
        </div>
    </div>
    <?php include '../footer.php'; ?>
</body>
</html>
