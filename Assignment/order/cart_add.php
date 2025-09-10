<?php
// Improved cart_add.php with better error handling
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Function to send JSON response - moved to top
function sendJsonResponse($success, $message, $data = []) {
    // Clear any output buffer completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Ensure no previous output
    if (headers_sent($file, $line)) {
        error_log("Headers already sent in $file on line $line");
        return;
    }
    
    // Set headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // Create response
    $response = json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    
    if ($response === false) {
        error_log("JSON encoding failed: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'JSON encoding error']);
    } else {
        echo $response;
    }
    
    exit();
}

// Simplified error handler
set_error_handler(function($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    if (!headers_sent()) {
        sendJsonResponse(false, 'Server error occurred', ['error' => $message]);
    }
});

set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    if (!headers_sent()) {
        sendJsonResponse(false, 'Server exception occurred', ['error' => $exception->getMessage()]);
    }
});

// Include base file
try {
    require_once '../_base.php';
} catch (Exception $e) {
    error_log("Failed to include _base.php: " . $e->getMessage());
    sendJsonResponse(false, 'Configuration error');
}

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Ensure we have required functions and database connection
if (!function_exists('isLoggedIn') || !isset($_db)) {
    error_log("Missing required functions or database connection");
    if ($isAjax) {
        sendJsonResponse(false, 'Server configuration error');
    } else {
        die('Server configuration error');
    }
}

// Check if user is logged in
if (!isLoggedIn()) {
    if ($isAjax) {
        sendJsonResponse(false, "Please log in to add items to cart", [
            'redirect' => '../user/login.php'
        ]);
    } else {
        $_SESSION['error'] = "Please log in to add items to cart";
        header('Location: ../user/login.php');
        exit();
    }
}

// Check if user is banned
checkUserStatus();

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        sendJsonResponse(false, "Invalid request method");
    } else {
        header('Location: ../userProduct/productList.php');
        exit();
    }
}

$userID = $_SESSION['user_id'];
$prodID = trim($_POST['prodID'] ?? '');
$qty = intval($_POST['qty'] ?? 1);
$requestId = $_POST['request_id'] ?? '';

// Debug: Log received data
error_log("Cart add request - Product ID: $prodID, Quantity: $qty, User ID: $userID");
error_log("POST data received: " . print_r($_POST, true));

// Input validation
if (empty($prodID)) {
    sendJsonResponse(false, "Invalid product ID");
}

if ($qty <= 0) {
    sendJsonResponse(false, "Quantity must be greater than 0");
}

// Duplicate request check
if (!empty($requestId)) {
    $duplicateKey = "cart_add_" . $requestId;
    if (isset($_SESSION[$duplicateKey])) {
        sendJsonResponse(false, "Duplicate request detected");
    }
    $_SESSION[$duplicateKey] = true;
}

try {
    // Test database connection first
    if (!$_db) {
        throw new Exception("Database connection not available");
    }
    
    // Check product exists and stock
    $productQuery = "SELECT prodID, name, price, qty FROM product WHERE prodID = ? AND (status IS NULL OR status != 'removed')";
    $productStmt = $_db->prepare($productQuery);
    
    if (!$productStmt) {
        throw new Exception("Failed to prepare product query: " . implode(', ', $_db->errorInfo()));
    }
    
    $productStmt->execute([$prodID]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendJsonResponse(false, "Product not found or not available");
    }
    
    if ($product['qty'] < $qty) {
        sendJsonResponse(false, "Insufficient stock. Available: " . $product['qty']);
    }
    
    // Get or create cart
    $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
    $cartStmt = $_db->prepare($cartQuery);
    $cartStmt->execute([$userID]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cart) {
        // Create new cart - let the database trigger handle cartID generation
        $createCartQuery = "INSERT INTO cart (userID) VALUES (?)";
        $createCartStmt = $_db->prepare($createCartQuery);
        $createCartStmt->execute([$userID]);
        
        // Get the generated cartID
        $cartID = $_db->lastInsertId();
        
        // If lastInsertId doesn't work, get the cartID from the database
        if (!$cartID) {
            $getCartQuery = "SELECT cartID FROM cart WHERE userID = ? ORDER BY cartID DESC LIMIT 1";
            $getCartStmt = $_db->prepare($getCartQuery);
            $getCartStmt->execute([$userID]);
            $cartResult = $getCartStmt->fetch(PDO::FETCH_ASSOC);
            $cartID = $cartResult['cartID'];
        }
    } else {
        $cartID = $cart['cartID'];
    }
    
    // Check existing item
    $existingQuery = "SELECT * FROM cart_items WHERE cartID = ? AND prodID = ?";
    $existingStmt = $_db->prepare($existingQuery);
    $existingStmt->execute([$cartID, $prodID]);
    $existingItem = $existingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingItem) {
        // Update existing item
        $newQty = $existingItem['qty'] + $qty;
        if ($newQty > $product['qty']) {
            sendJsonResponse(false, "Cannot add more items. Available: " . $product['qty'] . ", In cart: " . $existingItem['qty']);
        }
        
        $updateQuery = "UPDATE cart_items SET qty = ? WHERE cartID = ? AND prodID = ?";
        $updateStmt = $_db->prepare($updateQuery);
        $updateStmt->execute([$newQty, $cartID, $prodID]);
        $message = "Updated " . $product['name'] . " quantity in cart (was " . $existingItem['qty'] . ", now " . $newQty . ")";
        error_log("Updated cart item: Product $prodID, old qty: " . $existingItem['qty'] . ", added: $qty, new qty: $newQty");
    } else {
        // Add new item - let the database trigger handle cart_item_id generation
        $addQuery = "INSERT INTO cart_items (cartID, prodID, qty) VALUES (?, ?, ?)";
        $addStmt = $_db->prepare($addQuery);
        $addStmt->execute([$cartID, $prodID, $qty]);
        $message = "Added " . $product['name'] . " to cart (qty: $qty)";
        error_log("Added new cart item: Product $prodID, qty: $qty");
    }
    
    // Get updated cart count
    $countQuery = "SELECT SUM(qty) as total FROM cart_items WHERE cartID = ?";
    $countStmt = $_db->prepare($countQuery);
    $countStmt->execute([$cartID]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
    $cartTotal = $count['total'] ?? 0;
    
    error_log("Cart count calculation: Cart ID $cartID, Total items: $cartTotal");
    
    $_SESSION['cart_count'] = $cartTotal;
    
    // Send success response
    sendJsonResponse(true, $message, [
        'cart_count' => $cartTotal,
        'product_name' => $product['name'],
        'updated_qty' => $newQty ?? $qty
    ]);
    
} catch (Exception $e) {
    error_log("Cart add error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    sendJsonResponse(false, "Failed to add item to cart: " . $e->getMessage());
}
?>