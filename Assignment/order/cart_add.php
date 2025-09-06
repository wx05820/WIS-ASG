<?php
session_start();
require_once '../_base.php';

// Set content type for AJAX responses
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $isAjax = true;
} else {
    $isAjax = false;
}

// Function to send JSON response
function sendJsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in
if (!isLoggedIn()) {
    if ($isAjax) {
        sendJsonResponse(false, "Please log in to add items to cart", ['redirect' => '../user/login.php']);
    } else {
        $_SESSION['error'] = "Please log in to add items to cart";
        $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php';
        header('Location: ../user/login.php');
        exit();
    }
}

$userID = $_SESSION['user_id'];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        sendJsonResponse(false, "Invalid request method");
    } else {
        $_SESSION['error'] = "Invalid request method";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
        exit();
    }
}

// Get and validate input
$prodID = intval($_POST['prodID'] ?? 0);
$qty = intval($_POST['qty'] ?? 1);
$action = $_POST['action'] ?? 'add';

if ($prodID <= 0) {
    if ($isAjax) {
        sendJsonResponse(false, "Invalid product ID");
    } else {
        $_SESSION['error'] = "Invalid product ID";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
        exit();
    }
}

if ($qty <= 0) {
    if ($isAjax) {
        sendJsonResponse(false, "Quantity must be greater than 0");
    } else {
        $_SESSION['error'] = "Quantity must be greater than 0";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
        exit();
    }
}

try {
    // Check if product exists and has sufficient stock
    $productQuery = "SELECT prodID, name, price, qty FROM product WHERE prodID = ? AND (status IS NULL OR status != 'removed')";
    $productStmt = $_db->prepare($productQuery);
    $productStmt->execute([$prodID]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        if ($isAjax) {
            sendJsonResponse(false, "Product not found or not available");
        } else {
            $_SESSION['error'] = "Product not found or not available";
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
            exit();
        }
    }
    
    if ($product['qty'] < $qty) {
        if ($isAjax) {
            sendJsonResponse(false, "Insufficient stock. Available: " . $product['qty']);
        } else {
            $_SESSION['error'] = "Insufficient stock. Available: " . $product['qty'];
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
            exit();
        }
    }
    
    // Get or create cart for user
    $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
    $cartStmt = $_db->prepare($cartQuery);
    $cartStmt->execute([$userID]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cart) {
        // Create new cart
        $createCartQuery = "INSERT INTO cart (userID) VALUES (?)";
        $createCartStmt = $_db->prepare($createCartQuery);
        $createCartStmt->execute([$userID]);
        $cartID = $_db->lastInsertId();
    } else {
        $cartID = $cart['cartID'];
    }
    
    // Check if item already exists in cart
    $existingItemQuery = "SELECT * FROM cart_items WHERE cartID = ? AND prodID = ?";
    $existingItemStmt = $_db->prepare($existingItemQuery);
    $existingItemStmt->execute([$cartID, $prodID]);
    $existingItem = $existingItemStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingItem) {
        // Update existing item quantity
        $newQty = $existingItem['qty'] + $qty;
        
        // Check if new quantity exceeds stock
        if ($newQty > $product['qty']) {
            if ($isAjax) {
                sendJsonResponse(false, "Cannot add more items. Available stock: " . $product['qty'] . ", Already in cart: " . $existingItem['qty']);
            } else {
                $_SESSION['error'] = "Cannot add more items. Available stock: " . $product['qty'] . ", Already in cart: " . $existingItem['qty'];
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
                exit();
            }
        }
        
        // Update existing item - use the first column as primary key
        $updateItemQuery = "UPDATE cart_items SET qty = ? WHERE cartID = ? AND prodID = ?";
        $updateItemStmt = $_db->prepare($updateItemQuery);
        $updateItemStmt->execute([$newQty, $cartID, $prodID]);
        
        $message = "Updated quantity for " . $product['name'] . " in cart";
        $action_type = "updated";
    } else {
        // Add new item to cart
        $addItemQuery = "INSERT INTO cart_items (cartID, prodID, qty, price) VALUES (?, ?, ?, ?)";
        $addItemStmt = $_db->prepare($addItemQuery);
        $addItemStmt->execute([$cartID, $prodID, $qty, $product['price']]);
        
        $message = "Added " . $product['name'] . " to cart";
        $action_type = "added";
    }
    
    // Update cart count in session
    $cartCountQuery = "SELECT SUM(qty) as total FROM cart_items WHERE cartID = ?";
    $cartCountStmt = $_db->prepare($cartCountQuery);
    $cartCountStmt->execute([$cartID]);
    $cartCount = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
    $cartCountTotal = $cartCount['total'] ?? 0;
    $_SESSION['cart_count'] = $cartCountTotal;
    
    // Get updated cart item details for AJAX response
    $itemDetailsQuery = "SELECT ci.*, p.name, p.price, p.image1 
                        FROM cart_items ci 
                        JOIN product p ON ci.prodID = p.prodID 
                        WHERE ci.cartID = ? AND ci.prodID = ?";
    $itemDetailsStmt = $_db->prepare($itemDetailsQuery);
    $itemDetailsStmt->execute([$cartID, $prodID]);
    $itemDetails = $itemDetailsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($isAjax) {
        sendJsonResponse(true, $message, [
            'cart_count' => $cartCountTotal,
            'action_type' => $action_type,
            'item' => $itemDetails,
            'product_name' => $product['name']
        ]);
    } else {
        $_SESSION['success'] = $message;
    }
    
} catch (Exception $e) {
    error_log("Cart add error: " . $e->getMessage());
    $errorMessage = "Failed to add item to cart. Please try again.";
    
    if ($isAjax) {
        sendJsonResponse(false, $errorMessage);
    } else {
        $_SESSION['error'] = $errorMessage;
    }
}

// Redirect back to previous page (only for non-AJAX requests)
if (!$isAjax) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../userProduct/productList.php'));
    exit();
}
?>
