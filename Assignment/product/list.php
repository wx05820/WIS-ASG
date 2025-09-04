<?php
include '../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle bulk operations
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['selected_products'])) {
    $selected_ids = $_POST['selected_products'];
    $operation = $_POST['bulk_operation'] ?? '';
    
    try {
        switch ($operation) {
            case 'delete':
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $sql = "UPDATE product SET status = 'removed' WHERE prodID IN ($placeholders)";
                $stmt = $_db->prepare($sql);
                if ($stmt->execute($selected_ids)) {
                    $message = count($selected_ids) . " products deleted successfully.";
                }
                break;
                
            case 'set_category':
                $new_category = $_POST['new_category'] ?? '';
                $new_category_name = $_POST['new_category_name'] ?? '';
                
                // Debug information (remove in production)
                if (isset($_GET['debug'])) {
                    $debug_info = "Debug POST data: ";
                    foreach ($_POST as $key => $value) {
                        if (is_array($value)) {
                            $debug_info .= $key . "=[" . implode(',', $value) . "] ";
                        } else {
                            $debug_info .= $key . "='" . $value . "' ";
                        }
                    }
                    $message = $debug_info;
                    break;
                }
                
                // If creating a new category
                if ($new_category === 'new_category' && !empty($new_category_name)) {
                    $newCatName = trim($new_category_name);
                    
                    // Generate next catID (same format as addproduct.php)
                    $cat_sql = "SELECT MAX(CAST(SUBSTRING(catID, 2) AS UNSIGNED)) FROM category";
                    $cat_stmt = $_db->prepare($cat_sql);
                    $cat_stmt->execute();
                    $maxCatID = $cat_stmt->fetchColumn();
                    $nextCatID = 'C' . str_pad(($maxCatID ? $maxCatID + 1 : 1), 4, '0', STR_PAD_LEFT);
                    
                    // Insert new category with generated ID
                    $insert_cat_sql = "INSERT INTO category (catID, name) VALUES (?, ?)";
                    $insert_cat_stmt = $_db->prepare($insert_cat_sql);
                    if ($insert_cat_stmt->execute([$nextCatID, $newCatName])) {
                        // Update products with the new category
                        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                        $sql = "UPDATE product SET catID = ? WHERE prodID IN ($placeholders)";
                        $params = array_merge([$nextCatID], $selected_ids);
                        $stmt = $_db->prepare($sql);
                        if ($stmt->execute($params)) {
                            $message = count($selected_ids) . " products updated with new category '" . htmlspecialchars($newCatName) . "' successfully.";
                        }
                    } else {
                        $message = "Failed to create new category.";
                    }
                }
                // If using existing category
                elseif (!empty($new_category) && $new_category !== 'new_category' && $new_category !== '') {
                    // Verify the catID exists in category table
                    $verify_cat_sql = "SELECT catID FROM category WHERE catID = ?";
                    $verify_cat_stmt = $_db->prepare($verify_cat_sql);
                    $verify_cat_stmt->execute([$new_category]);
                    
                    if ($verify_cat_stmt->fetch()) {
                        // Category exists, proceed with update
                        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                        $sql = "UPDATE product SET catID = ? WHERE prodID IN ($placeholders)";
                        $params = array_merge([$new_category], $selected_ids);
                        $stmt = $_db->prepare($sql);
                        if ($stmt->execute($params)) {
                            $message = count($selected_ids) . " products category updated successfully.";
                        } else {
                            $message = "Error: Failed to update products with selected category.";
                        }
                    } else {
                        $message = "Error: Selected category does not exist in category table.";
                    }
                } else {
                    // If no valid category was selected
                    if (empty($new_category) || $new_category === '') {
                        $message = "Error: Please select a category.";
                    } else {
                        $message = "Error: No valid category operation detected.";
                    }
                }
                break;
                
            case 'set_price':
                $new_price = $_POST['new_price'] ?? '';
                if (!empty($new_price) && is_numeric($new_price)) {
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $sql = "UPDATE product SET price = ? WHERE prodID IN ($placeholders)";
                    $params = array_merge([$new_price], $selected_ids);
                    $stmt = $_db->prepare($sql);
                    if ($stmt->execute($params)) {
                        $message = count($selected_ids) . " products price updated successfully.";
                    }
                }
                break;
                
            case 'set_stock':
                $new_stock = $_POST['new_stock'] ?? '';
                if (!empty($new_stock) && is_numeric($new_stock)) {
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $sql = "UPDATE product SET qty = ? WHERE prodID IN ($placeholders)";
                    $params = array_merge([$new_stock], $selected_ids);
                    $stmt = $_db->prepare($sql);
                    if ($stmt->execute($params)) {
                        $message = count($selected_ids) . " products stock updated successfully.";
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Initialize variables
$products = [];
$categories = [];
$total_products = 0;
$total_pages = 0;
$error_message = '';

// Get filter parameters with proper sanitization
$category = isset($_GET['category']) && !empty($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['query']) && !empty($_GET['query']) ? trim($_GET['query']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
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

// Check database connection
if (!isset($_db) || $_db === null) {
    $error_message = "Database connection not available. Please check your configuration.";
} else {
    try {
        // Test database connection
        $_db->query("SELECT 1");
        
        // Build the WHERE clause
        $where_conditions = [];
        $params = [];

        if (!empty($category)) {
            $where_conditions[] = "p.catID = ?";
            $params[] = $category;
        }

        // Always exclude removed products
        $where_conditions[] = "(p.status IS NULL OR p.status != 'removed')";

        if (!empty($search)) {
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count for pagination - Simplified query first
        $count_sql = "SELECT COUNT(*) as total FROM product p $where_clause";
        $count_stmt = $_db->prepare($count_sql);
        if ($count_stmt === false) {
            throw new Exception("Failed to prepare count query");
        }
        
        $count_stmt->execute($params);
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_products = $count_result ? (int)$count_result['total'] : 0;
        $total_pages = $total_products > 0 ? ceil($total_products / $per_page) : 1;

        // Ensure page is within valid range
        if ($page < 1) $page = 1;
        if ($page > $total_pages) $page = $total_pages;
        $offset = ($page - 1) * $per_page;
        if ($offset < 0) $offset = 0;

        // Get products with pagination - Fixed query structure
        $sql = "SELECT p.prodID, p.name, p.price, p.qty, p.color, p.material, p.image1, p.description, 
                       p.catID as productCatID, c.catID as categoryCatID,
                       COALESCE(c.name, 'Uncategorized') as categoryName
                FROM product p 
                LEFT JOIN category c ON p.catID = c.catID
                $where_clause
                ORDER BY p.$sort $order 
                LIMIT $per_page OFFSET $offset";

        $stmt = $_db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare products query");
        }
        
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get categories for filter dropdown
        $cat_sql = "SELECT catID, name as categoryName FROM category ORDER BY name";
        $cat_stmt = $_db->prepare($cat_sql);
        if ($cat_stmt === false) {
            throw new Exception("Failed to prepare categories query");
        }
        
        $cat_stmt->execute();
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Handle PDO database errors
        error_log("PDO Database error in product list: " . $e->getMessage());
        $error_message = "Database error: " . $e->getMessage();
        $products = [];
        $categories = [];
        $total_products = 0;
        $total_pages = 0;
    } catch (Exception $e) {
        // Handle other errors
        error_log("Error in product list: " . $e->getMessage());
        $error_message = "Error loading products: " . $e->getMessage();
        $products = [];
        $categories = [];
        $total_products = 0;
        $total_pages = 0;
    }
}

$page_title = "Products";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userlist.css">
    <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../css/products.css' : 'css/products.css'; ?>">
</head>

<body class="product-list-main" style="margin-top:0; padding-top:0;">

    <?php include '../admin/adminheader.php'; ?>
    <script src="../js/adminProductList.js"></script>
    
    <script>
        function toggleProductSelect(item, event) {
            // Don't toggle if clicking on links or form elements
            if (event.target.tagName === 'A' || event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON') {
                return;
            }
            
            const checkbox = item.querySelector('input[type=checkbox]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateBulkOperations();
            }
        }
        
        function updateBulkOperations() {
            const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
            const count = checkboxes.length;
            const bulkPanel = document.getElementById('bulk-operations');
            const countSpan = document.getElementById('selected-count');
            
            if (count > 0) {
                bulkPanel.style.display = 'block';
                countSpan.textContent = count;
            } else {
                bulkPanel.style.display = 'none';
            }
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            updateBulkOperations();
        }
        
        function toggleNewCategoryInput() {
            const select = document.getElementById('category-select');
            const input = document.getElementById('new-category-input');
            
            if (select.value === 'new_category') {
                input.style.display = 'block';
                input.focus();
            } else {
                input.style.display = 'none';
                input.value = '';
            }
        }
        
        // Add event listeners when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', updateBulkOperations);
            });
        });
    </script>
 
    <div class="container">
        <!-- Display error message if any -->
        <?php if (!empty($error_message)): ?>
            <div class="error-message" style="background-color: #fee; color: #c33; padding: 1rem; margin: 1rem 0; border-radius: 4px; border: 1px solid #fcc;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <br><small>Please check the browser console for more details or contact your administrator.</small>
            </div>
        <?php endif; ?>

        <!-- Debug Information (remove in production) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <div class="debug-info" style="background: #f0f0f0; padding: 1rem; margin: 1rem 0; border-radius: 4px; font-family: monospace; font-size: 0.9em;">
                <strong>Debug Information:</strong><br>
                Database Connection: <?php echo isset($_db) ? 'Connected' : 'Not Connected'; ?><br>
                Total Products Found: <?php echo $total_products; ?><br>
                Current Page: <?php echo $page; ?><br>
                Products Array Count: <?php echo count($products); ?><br>
                Categories Array Count: <?php echo count($categories); ?><br>
                Search Query: "<?php echo htmlspecialchars($search); ?>"<br>
                Category Filter: <?php echo htmlspecialchars($category); ?><br>
                Sort: <?php echo htmlspecialchars($sort); ?> <?php echo htmlspecialchars($order); ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons Outside Filter Bar -->
            <div class="product-action-bar" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
                <button type="button" class="adduser-btn sortby-add" onclick="window.location.href='addproduct.php'">
                    <i class="fas fa-plus"></i>
                    <span class="action-btn-label">Add Product</span>
                </button>
                <button type="button" class="restore-btn sortby-restore" onclick="window.location.href='restoreproduct.php'">
                    <i class="fas fa-undo"></i>
                    <span class="action-btn-label">Restore Removed Products</span>
                </button>
            </div>

        <!-- Filters and Search Section -->
        <div class="filters-section">
            <div class="filters-container">

                <!-- Search Bar -->
                <div class="search-filter">
                    <form method="GET" action="" class="filter-form" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
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
                                        <?php echo ($category == $cat['catID']) ? 'selected' : ''; ?> >
                                    <?php echo htmlspecialchars($cat['categoryName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Sort Options -->
                <div class="sort-filter" style="display: flex; gap: 10px; align-items: center;">
                    <select name="sort" onchange="updateSort(this.value)" class="filter-select sortby-select">
                        <option value="name" <?php echo ($sort === 'name') ? 'selected' : ''; ?>>Sort by Name</option>
                        <option value="price" <?php echo ($sort === 'price') ? 'selected' : ''; ?>>Sort by Price</option>
                        <option value="qty" <?php echo ($sort === 'qty') ? 'selected' : ''; ?>>Sort by Stock</option>
                    </select>
                    <button type="button" onclick="toggleOrder()" class="order-btn sortby-order" title="Toggle sort order">
                        <i class="fas fa-sort-<?php echo ($order === 'ASC') ? 'up' : 'down'; ?>"></i>
                    </button>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="results-summary">
                <p>Showing <?php echo ($total_products > 0) ? (($page - 1) * $per_page + 1) : 0; ?> - 
                   <?php echo min($page * $per_page, $total_products); ?> of <?php echo $total_products; ?> products</p>
            </div>
        </div>

        <!-- Products List -->
        <?php if ($message): ?>
            <div id="success-message" class="message" style="color: green; background: #fff; border: 2px solid green; margin-bottom: 1rem; text-align: center; font-weight: bold; padding: 10px; border-radius: 5px; transition: opacity 0.5s ease-out;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($products)): ?>
            <form method="post" action="" id="bulk-operations-form">
                <div class="products-list">
                    <?php foreach ($products as $product): ?>
                        <div class="product-list-item" style="align-items: flex-start;" onclick="toggleProductSelect(this, event)">
                            <input type="checkbox" name="selected_products[]" value="<?php echo htmlspecialchars($product['prodID']); ?>" 
                                   style="margin-right:12px; margin-top:8px; width: 18px; height: 18px; transform: scale(1.1); cursor: pointer;" 
                                   onclick="event.stopPropagation();">
                            
                            <div class="product-image" style="margin-right: 15px;">
                                <a href="detail.php?id=<?php echo urlencode($product['prodID']); ?>" onclick="event.stopPropagation();">
                                    <?php if (!empty($product['image1'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($product['image1']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             loading="lazy"
                                             style="object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <div class="no-image" style="width:80px; height:80px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; border-radius:8px; color:#aaa;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>

                        <div class="product-details">
                            <div class="product-main-info">
                                <h3 class="product-name" style="margin:0 0 8px 0; font-size:1.2em;">
                                    <a href="detail.php?id=<?php echo urlencode($product['prodID']); ?>" style="text-decoration:none; color:#c33;">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h3>
                                <p class="product-id" style="margin:0; color:#666; font-size:0.9em;">
                                    Product ID: <?php echo htmlspecialchars($product['prodID']); ?>
                                </p>
                            </div>
                            
                            <div class="product-meta" style="display:flex; gap:20px; margin:12px 0; flex-wrap:wrap;">
                                <span class="category" style="background:#e8f4f8; padding:4px 8px; border-radius:4px; font-size:0.85em;">
                                    <?php echo htmlspecialchars($product['categoryName']); ?>
                                </span>
                                <?php if (!empty($product['color'])): ?>
                                    <span class="color" style="color:#666; font-size:0.9em;">
                                        Color: <?php echo htmlspecialchars($product['color']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($product['material'])): ?>
                                    <span class="material" style="color:#666; font-size:0.9em;">
                                        Material: <?php echo htmlspecialchars($product['material']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-pricing" style="display:flex; justify-content:space-between; align-items:center;">
                                <div class="price-stock">
                                    <span class="price" style="font-size:1.1em; font-weight:600; color:#2c5530;">
                                        RM <?php echo number_format((float)$product['price'], 2); ?>
                                    </span>
                                    <span class="stock <?php echo ($product['qty'] > 0) ? 'in-stock' : 'out-of-stock'; ?>" 
                                          style="margin-left:15px; padding:4px 8px; border-radius:4px; font-size:0.85em; <?php echo ($product['qty'] > 0) ? 'background:#e8f5e8; color:#2c5530;' : 'background:#fee; color:#c33;'; ?>">
                                        <?php if ($product['qty'] > 0): ?>
                                            In Stock (<?php echo (int)$product['qty']; ?>)
                                        <?php else: ?>
                                            Out of Stock
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="updateproduct.php?prodID=<?php echo urlencode($product['prodID']); ?>" 
                                       title="Edit Product" 
                                       style="color: #666; font-size: 1.3em; text-decoration:none; padding:8px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Bulk Operations Panel -->
            <div id="bulk-operations" style="display:none; margin-top:20px; padding:20px; background:#f8f9fa; border-radius:8px; border-left:4px solid #007bff;">
                <h4 style="margin:0 0 15px 0; color:#333;">Bulk Operations (<span id="selected-count">0</span> selected)</h4>
                
                <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
                    <!-- Set Category -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-weight:600; color:#000;">Category:</label>
                        <select name="new_category" id="category-select" onchange="toggleNewCategoryInput()" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px;">
                            <option value="">Select Category</option>
                            <option value="new_category">+ Add New Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['catID']); ?>">
                                    <?php echo htmlspecialchars($cat['categoryName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="new_category_name" id="new-category-input" placeholder="Enter category name" 
                               style="display:none; width:150px; padding:6px 10px; border:1px solid #ddd; border-radius:4px;">
                        <button type="submit" name="bulk_operation" value="set_category" 
                                style="background:#8B4513; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer;">
                            Update
                        </button>
                    </div>
                    
                    <!-- Set Price -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-weight:600; color:#000;">Price (RM):</label>
                        <input type="number" name="new_price" step="0.01" min="0" 
                               style="width:100px; padding:6px 10px; border:1px solid #ddd; border-radius:4px;" placeholder="0.00">
                        <button type="submit" name="bulk_operation" value="set_price" 
                                style="background:#8B4513; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer;">
                            Update
                        </button>
                    </div>
                    
                    <!-- Set Stock -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-weight:600; color:#000;">Stock:</label>
                        <input type="number" name="new_stock" min="0" 
                               style="width:80px; padding:6px 10px; border:1px solid #ddd; border-radius:4px;" placeholder="0">
                        <button type="submit" name="bulk_operation" value="set_stock" 
                                style="background:#8B4513; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer;">
                            Update
                        </button>
                    </div>
                    
                    <!-- Clear Selection -->
                    <button type="button" onclick="clearSelection()" 
                            style="background:#6c757d; color:white; padding:8px 16px; border:none; border-radius:5px; cursor:pointer; margin-left:auto;">
                        <i class="fas fa-times"></i> Clear Selected
                    </button>

                    <!-- Delete Selected -->
                    <button type="submit" name="bulk_operation" value="delete" 
                            style="background:#dc3545; color:white; padding:8px 16px; border:none; border-radius:5px; cursor:pointer;"
                            onclick="return confirm('Are you sure you want to delete selected products?')">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            </form>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <!-- First Page -->
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                        class="page-link">⏮ First</a>
                    <?php else: ?>
                        <span class="page-link disabled">⏮ First</span>
                    <?php endif; ?>
                    
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                        class="page-link">◀ Previous</a>
                    <?php else: ?>
                        <span class="page-link disabled">◀ Previous</span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    // Calculate the range of pages to show
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    // Ensure we show at least 5 pages if available
                    if ($end_page - $start_page < 4) {
                        if ($start_page == 1) {
                            $end_page = min($total_pages, $start_page + 4);
                        } else {
                            $start_page = max(1, $end_page - 4);
                        }
                    }
                    ?>
        
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="page-link active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                            class="page-link"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                
                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                        class="page-link">Next ▶</a>
                    <?php else: ?>
                        <span class="page-link disabled">Next ▶</span>
                    <?php endif; ?>
                    
                    <!-- Last Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                        class="page-link">Last ⏭</a>
                    <?php else: ?>
                        <span class="page-link disabled">Last ⏭</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php else: ?>
                <div class="no-products" style="text-align: center; padding: 3rem 1rem; color: #666;">
                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3>No products found</h3>
                    <?php if (!empty($search) || !empty($category)): ?>
                        </p>
                        <a href="?" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #8B4513; color: white; text-decoration: none; border-radius: 4px; margin-top: 1rem;">Clear Filters</a>
                    <?php else: ?>
                        <p>No products are available at the moment.</p>
                        <button type="button" class="btn btn-primary" onclick="window.location.reload()" style="padding: 10px 20px; background: #8B4513; color: white; border: none; border-radius: 4px; margin-top: 1rem; cursor: pointer;">
                            <i class="fas fa-refresh"></i> Refresh Page
                        </button>
                    <?php endif; ?>
            
                </div>
            <?php endif; ?>
    </div>
</body>
</html>

<?php include '../footer.php'; ?>