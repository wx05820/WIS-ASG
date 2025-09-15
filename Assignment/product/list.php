<?php
include '../config.php';
include '../_base.php';
include '../lib/SimplePager.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../admin/loginstaff.php');
}

// Initialize message variable
$message = '';

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $deleteCatID = $_POST['delete_category'];
    
    // Check if category is being used by any products
    $check_sql = "SELECT COUNT(*) FROM product WHERE catID = ?";
    $check_stmt = $_db->prepare($check_sql);
    $check_stmt->execute([$deleteCatID]);
    $productCount = $check_stmt->fetchColumn();
    
    if ($productCount > 0) {
        $message = "Cannot delete category. It is being used by $productCount product(s).";
    } else {
        // Delete the category
        $delete_sql = "DELETE FROM category WHERE catID = ?";
        $delete_stmt = $_db->prepare($delete_sql);
        if ($delete_stmt->execute([$deleteCatID])) {
            $message = "Category deleted successfully.";
        } else {
            $message = "Failed to delete category.";
        }
    }
}

// Handle category name update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $updateCatID = $_POST['update_category'];
    $newCategoryName = trim($_POST['new_category_name_edit'] ?? '');
    
    if (empty($newCategoryName)) {
        $message = "Category name cannot be empty.";
    } else {
        // Check if the new name already exists (excluding current category)
        $check_name_sql = "SELECT COUNT(*) FROM category WHERE name = ? AND catID != ?";
        $check_name_stmt = $_db->prepare($check_name_sql);
        $check_name_stmt->execute([$newCategoryName, $updateCatID]);
        $nameExists = $check_name_stmt->fetchColumn();
        
        if ($nameExists > 0) {
            $message = "Category name '{$newCategoryName}' already exists. Please choose a different name.";
        } else {
            // Update the category name
            $update_sql = "UPDATE category SET name = ? WHERE catID = ?";
            $update_stmt = $_db->prepare($update_sql);
            if ($update_stmt->execute([$newCategoryName, $updateCatID])) {
                $message = "Category name updated to '{$newCategoryName}' successfully.";
            } else {
                $message = "Failed to update category name.";
            }
        }
    }
}

// Handle bulk operations
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
                
            case 'update_all':
                // Handle updating all fields at once
                $updates = [];
                $params = [];
                $updateMessages = [];
                
                // Check for category update
                $new_category = $_POST['new_category'] ?? '';
                $new_category_name = $_POST['new_category_name'] ?? '';
                
                if ($new_category === 'new_category' && !empty($new_category_name)) {
                    // Create new category
                    $newCatName = trim($new_category_name);
                    $cat_sql = "SELECT MAX(CAST(SUBSTRING(catID, 2) AS UNSIGNED)) FROM category";
                    $cat_stmt = $_db->prepare($cat_sql);
                    $cat_stmt->execute();
                    $maxCatID = $cat_stmt->fetchColumn();
                    $nextCatID = 'C' . str_pad(($maxCatID ? $maxCatID + 1 : 1), 4, '0', STR_PAD_LEFT);
                    
                    $insert_cat_sql = "INSERT INTO category (catID, name) VALUES (?, ?)";
                    $insert_cat_stmt = $_db->prepare($insert_cat_sql);
                    if ($insert_cat_stmt->execute([$nextCatID, $newCatName])) {
                        $updates[] = "catID = ?";
                        $params[] = $nextCatID;
                        $updateMessages[] = "category to '{$newCatName}'";
                    }
                } elseif (!empty($new_category) && $new_category !== 'new_category') {
                    // Use existing category
                    $verify_cat_sql = "SELECT catID FROM category WHERE catID = ?";
                    $verify_cat_stmt = $_db->prepare($verify_cat_sql);
                    $verify_cat_stmt->execute([$new_category]);
                    if ($verify_cat_stmt->fetch()) {
                        $updates[] = "catID = ?";
                        $params[] = $new_category;
                        $updateMessages[] = "category";
                    }
                }
                
                // Check for price update
                $new_price = $_POST['new_price'] ?? '';
                if (!empty($new_price) && is_numeric($new_price)) {
                    $updates[] = "price = ?";
                    $params[] = $new_price;
                    $updateMessages[] = "price to RM " . number_format($new_price, 2);
                }
                
                // Check for stock update
                $new_stock = $_POST['new_stock'] ?? '';
                if (!empty($new_stock) && is_numeric($new_stock)) {
                    $updates[] = "qty = ?";
                    $params[] = $new_stock;
                    $updateMessages[] = "stock to {$new_stock}";
                }
                
                // Execute update if there are any updates to make
                if (!empty($updates)) {
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $sql = "UPDATE product SET " . implode(', ', $updates) . " WHERE prodID IN ($placeholders)";
                    $params = array_merge($params, $selected_ids);
                    $stmt = $_db->prepare($sql);
                    if ($stmt->execute($params)) {
                        $updateText = implode(', ', $updateMessages);
                        $message = count($selected_ids) . " products updated with {$updateText} successfully.";
                    } else {
                        $message = "Error updating products.";
                    }
                } else {
                    $message = "No valid updates specified. Please fill in at least one field to update.";
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
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Build SQL for SimplePager
        $sql = "SELECT p.prodID, p.name, p.price, p.qty, p.color, p.material, p.image1, p.description, 
                       p.catID as productCatID, c.catID as categoryCatID,
                       COALESCE(c.name, 'Uncategorized') as categoryName
                FROM product p 
                LEFT JOIN category c ON p.catID = c.catID
                $where_clause
                ORDER BY p.$sort $order";

    // Use SimplePager for pagination
    $pager = new SimplePager($sql, $params, $per_page, $page);
    $products = $pager->result;

    // Backward-compatible variables for older templates
    $total_products = $pager->item_count;
    $total_pages = $pager->page_count;

        // Get categories for filter dropdown
        $cat_sql = "SELECT catID, name as categoryName FROM category ORDER BY name";
        $cat_stmt = $_db->prepare($cat_sql);
        if ($cat_stmt === false) {
            throw new Exception("Failed to prepare categories query");
        }
        
        $cat_stmt->execute();
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        $products = [];
        $categories = [];
        $total_products = 0;
        $total_pages = 0;
    } catch (Exception $e) {
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
    <script src="../js/adminproductlist.js"></script>
 
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
                Total Products Found: <?php echo $pager->item_count; ?><br>
                Current Page: <?php echo $pager->page; ?><br>
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
                    <span class="action-btn-label">Restore Products</span>
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
                        <div style="position:relative; display:inline-block;">
                            <input type="text" 
                                   name="query" 
                                   placeholder="Search products..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   id="search-query-input"
                                   class="search-input"
                                   style="width: 350px; max-width: 100%; padding: 0.5rem 1rem; font-size: 1.1rem; padding-right:2.4rem;">
                            <button type="button" id="clear-search-btn" title="Clear search"
                                    style="position:absolute; right:10px; top:50%; transform:translateY(-50%); border:none; background:transparent; font-size:1.2rem; cursor:pointer; color:#888; display: <?php echo empty($search) ? 'none' : 'inline-block'; ?>;">
                                &times;
                            </button>
                        </div>
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
                        
                        <button type="button" id="manageCategoriesBtn" style="padding: 0.5rem; background: #959595ff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; margin-left: 10px; width: 40px; height: 40px;" title="Manage Categories">
                            <i class="fas fa-cog"></i>
                        </button>
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
                        <div class="product-list-item" style="align-items: flex-start; cursor: pointer;" 
                             onclick="window.location.href='detail.php?id=<?php echo urlencode($product['prodID']); ?>'">
                            <input type="checkbox" name="selected_products[]" value="<?php echo htmlspecialchars($product['prodID']); ?>" 
                                   class="green-checkbox"
                                   style="margin-right:10px; margin-top:8px; width: 24px; height: 24px; transform: scale(1.3); cursor: pointer;" 
                                   onclick="event.stopPropagation();">
                            
                            <div class="product-image" style="margin-right: 15px;">
                                <div style="pointer-events: none;">
                                    <?php if (!empty($product['image1'])): ?>
                                        <?php
                                        // Check if image1 is a filename or binary data
                                        $imageData = $product['image1'];
                                        
                                        // More robust filename detection
                                        $isFilename = false;
                                        if (strlen($imageData) < 500) {
                                            // Check for common image file extensions
                                            $imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif', '.bmp'];
                                            foreach ($imageExtensions as $ext) {
                                                if (strpos($imageData, $ext) !== false) {
                                                    $isFilename = true;
                                                    break;
                                                }
                                            }
                                            
                                            // Additional check: if it looks like a filename and file exists
                                            if (!$isFilename && preg_match('/^[a-zA-Z0-9._\-\s]+\.[a-zA-Z]{2,4}$/', $imageData)) {
                                                if (file_exists('../bin/' . $imageData)) {
                                                    $isFilename = true;
                                                }
                                            }
                                        }
                                        
                                        if ($isFilename) {
                                            // Image1 contains a filename, load from file system
                                            $imagePath = '../bin/' . $imageData;
                                            if (file_exists($imagePath)) {
                                                $imageContent = file_get_contents($imagePath);
                                                // Determine MIME type based on file extension
                                                $extension = strtolower(pathinfo($imageData, PATHINFO_EXTENSION));
                                                $mimeType = 'image/jpeg'; // default
                                                switch ($extension) {
                                                    case 'png': $mimeType = 'image/png'; break;
                                                    case 'gif': $mimeType = 'image/gif'; break;
                                                    case 'webp': $mimeType = 'image/webp'; break;
                                                    case 'avif': $mimeType = 'image/avif'; break;
                                                }
                                                $imageSrc = 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);
                                            } else {
                                                $imageSrc = '';
                                            }
                                        } else {
                                            // Image1 contains binary data
                                            $imageSrc = 'data:image/jpeg;base64,' . base64_encode($imageData);
                                        }
                                        ?>
                                        <?php if (!empty($imageSrc)): ?>
                                            <img src="<?php echo $imageSrc; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 loading="lazy"
                                                 style="object-fit: cover; border-radius: 8px;">
                                        <?php else: ?>
                                            <div class="no-image" style="width:80px; height:80px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; border-radius:8px; color:#aaa;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="no-image" style="width:80px; height:80px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; border-radius:8px; color:#aaa;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <div class="product-details">
                            <div class="product-main-info">
                                <h3 class="product-name" style="margin:0 0 8px 0; font-size:1.2em; pointer-events: none;">
                                    <span style="text-decoration:none; color:#c33;">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </span>
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
                                       style="color: #666; font-size: 1.3em; text-decoration:none; padding:8px;"
                                       onclick="event.stopPropagation();">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Floating Toggle Button for Bulk Operations -->
            <button id="bulk-toggle-btn" type="button" title="Open bulk operations" style="display:none; position:fixed; bottom:20px; right:20px; width:52px; height:52px; border-radius:50%; background:#8B4513; color:#fff; border:none; box-shadow:0 8px 20px rgba(0,0,0,0.18); z-index:10000; cursor:pointer; align-items:center; justify-content:center;">
                <i class="fas fa-sliders-h"></i>
            </button>

            <!-- Bulk Operations Panel (appears below button) -->
            <div id="bulk-operations" style="display:none; position:fixed; bottom:90px; right:20px; transform: translateY(20px); transition: transform 0.3s ease, opacity 0.3s ease; opacity:0; width:260px; max-width:calc(100% - 40px); padding:12px; background:#f8f9fa; border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,0.12); z-index:9999;">
                <h4 class="bulk-ops-title">Bulk Operations <div>(<span id="selected-count">0</span> selected)</div></h4>
                
                <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
                    <!-- Set Category -->
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <label style="font-weight:600; color:#000;">Category:</label>
                            <select name="new_category" id="category-select" onchange="toggleNewCategoryInput()" style="padding:4px 8px; border:1px solid #ddd; border-radius:4px; font-size:0.9em; width:70px;">
                                <option value="">Select Category</option>
                                <option value="new_category">+ Add New Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['catID']); ?>">
                                        <?php echo htmlspecialchars($cat['categoryName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="bulk_operation" value="set_category" class="bulk-update-btn">
                                Update
                            </button>
                        </div>
                        <input type="text" name="new_category_name" id="new-category-input" placeholder="Enter category name" 
                               style="display:none; width:100%; padding:4px 8px; border:1px solid #ddd; border-radius:4px; font-size:0.9em;">
                    </div>
                    
                    <!-- Set Price -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-weight:600; color:#000;">Price (RM):</label>
                        <input type="number" name="new_price" step="0.01" min="0" 
                               style="width:100px; padding:6px 10px; border:1px solid #ddd; border-radius:4px;" placeholder="0.00">
                        <button type="submit" name="bulk_operation" value="set_price" class="bulk-update-btn">
                            Update
                        </button>
                    </div>
                    
                    <!-- Set Stock -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-weight:600; color:#000;">Stock:</label>
                        <input type="number" name="new_stock" min="0" 
                               style="width:100px; padding:6px 10px; border:1px solid #ddd; border-radius:4px;" placeholder="0">
                        <button type="submit" name="bulk_operation" value="set_stock" class="bulk-update-btn">
                            Update
                        </button>
                    </div>
                    
                    <!-- Update All Button -->
                    <div style="width: 100%; text-align: center; margin: 15px 0;">
                        <button type="submit" name="bulk_operation" value="update_all" class="bulk-action-btn update-all-btn" 
                                style="background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 6px; justify-content: center; margin: 0 auto;"
                                onclick="return confirm('This will update all selected products with the values entered above. Continue?')">
                            <i class="fas fa-sync-alt"></i>
                            <span>Update All Selected</span>
                        </button>
                        <small style="color: #666; font-size: 0.8em; display: block; margin-top: 4px;">Updates all filled fields for selected products</small>
                    </div>
                    
                    <!-- Clear Selection -->
                    <button type="button" onclick="clearSelection()" class="bulk-action-btn clear-btn right">
                        <i class="fas fa-times"></i>
                        <span>Clear</span>
                    </button>

                    <!-- Delete Selected -->
                    <button type="submit" name="bulk_operation" value="delete" class="bulk-action-btn delete-btn" onclick="return confirm('Are you sure you want to delete selected products?')">
                        <i class="fas fa-trash"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </div>
            </form>

            <!-- Pagination -->
            <?php
            // Build query parameters for pagination links (preserve all current filters and sort)
            $params_array = [
                'sort'     => $_GET['sort'] ?? null,
                'order'    => $_GET['order'] ?? null,
                'query'    => $_GET['query'] ?? null,
                'category' => $_GET['category'] ?? null
            ];
            $params_array = array_filter($params_array); // Remove empty values
            $href = http_build_query($params_array);

            // Output SimplePager HTML with pagination class (matches usermanage)
            echo $pager->html($href, 'class="pagination"');
            ?>

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
    
    <!-- Category Management Modal -->
    <div id="categoryModal" style="display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: none; width: 500px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333;">Manage Categories</h3>
                <span id="closeModal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            
            <?php if (!empty($message)): ?>
                <div style="padding: 10px; margin-bottom: 15px; background: #f8f9fa; border-left: 4px solid #007bff; color: #333;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <?php 
                        // Check if category is being used by any products
                        $check_sql = "SELECT COUNT(*) FROM product WHERE catID = ? AND (status IS NULL OR status != 'removed')";
                        $check_stmt = $_db->prepare($check_sql);
                        $check_stmt->execute([$cat['catID']]);
                        $productCount = $check_stmt->fetchColumn();
                        $isUsed = $productCount > 0;
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee;">
                            <div style="flex: 1;">
                                <div id="category-name-<?php echo $cat['catID']; ?>">
                                    <strong style="color: #000;"><?php echo htmlspecialchars($cat['categoryName']); ?></strong>
                                </div>
                                <div id="category-edit-<?php echo $cat['catID']; ?>" style="display: none;">
                                    <form method="POST" style="display: flex; gap: 8px; align-items: center;" onsubmit="return confirm('Update category name?')">
                                        <input type="hidden" name="update_category" value="<?php echo htmlspecialchars($cat['catID']); ?>">
                                        <input type="text" name="new_category_name_edit" 
                                               value="<?php echo htmlspecialchars($cat['categoryName']); ?>"
                                               style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; width: 200px;"
                                               required>
                                        <button type="submit" 
                                                style="padding: 4px 8px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">
                                            <i class="fas fa-check"></i> Save
                                        </button>
                                        <button type="button" onclick="cancelEdit('<?php echo $cat['catID']; ?>')"
                                                style="padding: 4px 8px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                                <div style="font-size: 0.85em; margin-top: 2px;">
                                    <?php if ($isUsed): ?>
                                        <span style="color: #28a745; font-weight: 500;">
                                            <i class="fas fa-check-circle"></i> In use (<?php echo $productCount; ?> product<?php echo $productCount > 1 ? 's' : ''; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-weight: 500;">
                                            <i class="fas fa-circle"></i> Not used
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 5px;">
                                <button type="button" onclick="startEdit('<?php echo $cat['catID']; ?>')"
                                        style="padding: 5px 8px; background: #ffc107; color: #212529; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;"
                                        title="Edit category name">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($cat['categoryName']); ?>', <?php echo $isUsed ? 'true' : 'false'; ?>, <?php echo $productCount; ?>)">
                                    <input type="hidden" name="delete_category" value="<?php echo htmlspecialchars($cat['catID']); ?>">
                                    <button type="submit" 
                                            style="padding: 5px 8px; background: <?php echo $isUsed ? '#6c757d' : '#dc3545'; ?>; color: white; border: none; border-radius: 3px; cursor: <?php echo $isUsed ? 'not-allowed' : 'pointer'; ?>; font-size: 12px;"
                                            <?php echo $isUsed ? 'disabled title="Cannot delete category that is in use"' : ''; ?>>
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #666;">
                        No categories available
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
    <script>
        // Call the function from adminproductlist.js to auto-hide success message
        if (typeof showSuccessMessage === 'function') {
            showSuccessMessage();
        }
    </script>
    <?php endif; ?>
</body>
</html>

<?php include '../footer.php'; ?>