<?php
include '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['query']) ? $_GET['query'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;

// Validate sort and order parameters
$allowed_sorts = ['name', 'price', 'qty', 'catID'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sorts)) {
    $sort = 'name';
}
if (!in_array($order, $allowed_orders)) {
    $order = 'ASC';
}

// Build the WHERE clause
$where_conditions = [];
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

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM product p JOIN category c ON p.catID = c.catID $where_clause";
$count_stmt = $_db->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch()['total'];
$total_pages = ceil($total_products / $per_page);

// Ensure page is within valid range
if ($total_pages < 1) {
    $page = 1;
    $offset = 0;
} else {
    if ($page < 1) $page = 1;
    if ($page > $total_pages) $page = $total_pages;
    $offset = ($page - 1) * $per_page;
    if ($offset < 0) $offset = 0;
}

// Get products with pagination (temporarily without category join)
$sql = "SELECT p.*, c.name as categoryName
    FROM product p
    JOIN category c ON p.catID = c.catID
    $where_clause
    ORDER BY p.$sort $order 
    LIMIT $per_page OFFSET $offset";

$stmt = $_db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter dropdown (temporarily simplified)
$cat_sql = "SELECT c.catID, c.name as categoryName FROM category c ORDER BY c.name";
$cat_stmt = $_db->prepare($cat_sql);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

$page_title = "Products";
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<header class="wooden-header">
    <div class="header-container">
        <!-- Logo and Company Name -->
        <div class="logo-section">
            <a href="../admin/adminpage.php">
                <img src="../images/logo.png" alt="AiKUN Furniture Logo" class="logo">
                <span class="company-name">AiKUN</span>
            </a>
        </div>

        <!-- Product Management Buttons -->
        <div class="product-management" style="display: flex; gap: 10px; align-items: center;">
            <a href="addproduct.php" class="btn btn-success" style="padding: 6px 12px; border-radius: 4px; background: #28a745; color: #fff; text-decoration: none;">Add Product</a>
            <a href="removeproduct.php" class="btn btn-danger" style="padding: 6px 12px; border-radius: 4px; background: #dc3545; color: #fff; text-decoration: none;">Remove Product</a>
        </div>
    </div>
</header>

<body main class="product-list-main">

    <div class="container">
        <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../css/products.css' : 'css/products.css'; ?>">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1>All Products</h1>
            <p>Handcrafted with quality materials and timeless designs</p>
        </div>

        <!-- Filters and Search Section -->
        <div class="filters-section">
            <div class="filters-container">

                <!-- Search Bar -->
                <div class="search-filter">
                    <form method="GET" action="" class="filter-form">
                        <input type="text" 
                               name="query" 
                               placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <select name="category" onchange="this.form.submit()" class="filter-select">
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

                <!-- Sort Options -->
                <div class="sort-filter">
                    <select name="sort" onchange="updateSort(this.value)" class="filter-select">
                        <option value="name" <?php echo ($sort === 'name') ? 'selected' : ''; ?>>Sort by Name</option>
                        <option value="price" <?php echo ($sort === 'price') ? 'selected' : ''; ?>>Sort by Price</option>
                        <option value="qty" <?php echo ($sort === 'qty') ? 'selected' : ''; ?>>Sort by Stock</option>
                    </select>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="results-summary">
                <p>Showing <?php echo ($total_products > 0) ? (($page - 1) * $per_page + 1) : 0; ?> - 
                   <?php echo min($page * $per_page, $total_products); ?> of <?php echo $total_products; ?> products</p>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="detail.php?id=<?php echo $product['prodID']; ?>">
                                <?php if (!empty($product['image1'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($product['image1']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    loading="lazy">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-image"></i>
                                        <span>No Image</span>
                                    </div>
                                <?php endif; ?>
                            </a>
                            
                            <!-- Quick Actions -->
                            <div class="quick-actions">
                                <button type="button" 
                                        onclick="quickView(<?php echo $product['prodID']; ?>)" 
                                        class="quick-view-btn" 
                                        title="Quick View">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="detail.php?id=<?php echo $product['prodID']; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            
                            <div class="product-meta">
                                <span class="category">Category: <?php echo htmlspecialchars($product['categoryName']); ?></span>
                                <span class="stock <?php echo $product['qty'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                    <?php if ($product['qty'] > 0): ?>
                                        In Stock (<?php echo $product['qty']; ?>)
                                    <?php else: ?>
                                        Out of Stock
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="product-price">
                                <span class="price">RM <?php echo number_format($product['price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="page-link">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="page-link">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-products">
                <i class="fas fa-search"></i>
                <h3>No products found</h3>
                <p>Try adjusting your search criteria or browse all products.</p>
                <a href="list.php" class="btn btn-primary">View All Products</a>
            </div>
        <?php endif; ?>
    </div>
</body>

<!-- Quick View Modal -->
<div id="quickViewModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="quickViewContent"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeProductList();
});

function initializeProductList() {
    // Initialize quick view modal
    const modal = document.getElementById('quickViewModal');
    const closeBtn = modal.querySelector('.close');
    
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}

function updateSort(sortValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    urlParams.set('page', '1'); // Reset to first page
    window.location.href = '?' + urlParams.toString();
}

function quickView(productId) {
    // Fetch product details for quick view
    fetch(`quick-view.php?id=${productId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('quickViewContent').innerHTML = html;
            document.getElementById('quickViewModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error loading quick view:', error);
        });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>