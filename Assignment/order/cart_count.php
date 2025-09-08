<?php
// Start output buffering first to prevent headers already sent errors
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include base file first (it handles session)
require_once '../_base.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = []) {
    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Send response
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    
    // Exit immediately
    exit();
}

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(false, "User not logged in", ['cart_count' => 0]);
}

$userID = $_SESSION['user_id'];

try {
    // Get user's cart
    $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
    $cartStmt = $_db->prepare($cartQuery);
    $cartStmt->execute([$userID]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cart) {
        // No cart exists, count is 0
        sendJsonResponse(true, "Cart count retrieved", ['cart_count' => 0]);
    }
    
    // Get total quantity of items in cart
    $cartCountQuery = "SELECT SUM(qty) as total FROM cart_items WHERE cartID = ?";
    $cartCountStmt = $_db->prepare($cartCountQuery);
    $cartCountStmt->execute([$cart['cartID']]);
    $cartCount = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
    $cartCountTotal = $cartCount['total'] ?? 0;
    
    // Update session cart count
    $_SESSION['cart_count'] = $cartCountTotal;
    
    sendJsonResponse(true, "Cart count retrieved", ['cart_count' => $cartCountTotal]);
    
} catch (Exception $e) {
    error_log("Cart count error: " . $e->getMessage());
    sendJsonResponse(false, "Error retrieving cart count", ['cart_count' => 0]);
}
?>