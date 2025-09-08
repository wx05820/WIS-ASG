<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: tracking.php');
    exit();
}

$orderID = $_POST['orderID'] ?? '';
$redirect = $_POST['redirect'] ?? 'tracking_details.php?id=' . $orderID;
$csrfToken = $_POST['csrf_token'] ?? '';

// Validate CSRF token
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['error'] = "Invalid security token. Please refresh the page and try again.";
    $safeRedirect = !empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : "tracking_details.php?id=" . urlencode($orderID);
    header("Location: $safeRedirect");
    exit();
}

if (empty($orderID)) {
    $_SESSION['error'] = "Invalid order ID";
    $safeRedirect = !empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : "tracking_details.php";
    header("Location: $safeRedirect");
    exit();
}

try {
    // Verify the order belongs to the user
    $checkQuery = "SELECT orderID FROM `order` WHERE orderID = ? AND userID = ?";
    $checkStmt = $_db->prepare($checkQuery);
    
    if (!$checkStmt) {
        throw new Exception("Failed to prepare order check query: " . implode(', ', $_db->errorInfo()));
    }
    
    $checkResult = $checkStmt->execute([$orderID, $userID]);
    
    if (!$checkResult) {
        throw new Exception("Failed to execute order check query: " . implode(', ', $checkStmt->errorInfo()));
    }
    
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error'] = "Order not found or access denied. Please check the order details.";
        $safeRedirect = !empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : "tracking_details.php?id=" . urlencode($orderID);
        header("Location: $safeRedirect");
        exit();
    }
    
     // Get all items from the original order
     $itemsQuery = "SELECT oi.prodID, oi.qty, oi.product_color, p.name, p.price, p.qty as stock_quantity
                    FROM order_items oi 
                    JOIN product p ON oi.prodID = p.prodID 
                    WHERE oi.orderID = ?";
     $itemsStmt = $_db->prepare($itemsQuery);
     
     if (!$itemsStmt) {
         throw new Exception("Failed to prepare items query: " . implode(', ', $_db->errorInfo()));
     }
     
     $itemsResult = $itemsStmt->execute([$orderID]);
     
     if (!$itemsResult) {
         throw new Exception("Failed to execute items query: " . implode(', ', $itemsStmt->errorInfo()));
     }
     
     $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
     
     if (empty($items)) {
         $_SESSION['error'] = "No items found in the original order. Cannot reorder empty order.";
         $safeRedirect = !empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : "tracking_details.php?id=" . urlencode($orderID);
         header("Location: $safeRedirect");
         exit();
     }
     
     // Get or create cart for user
     $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
     $cartStmt = $_db->prepare($cartQuery);
     
     if (!$cartStmt) {
         throw new Exception("Failed to prepare cart query: " . implode(', ', $_db->errorInfo()));
     }
     
     $cartResult = $cartStmt->execute([$userID]);
     
     if (!$cartResult) {
         throw new Exception("Failed to execute cart query: " . implode(', ', $cartStmt->errorInfo()));
     }
     
     $cartID = $cartStmt->fetchColumn();
     
     if (!$cartID) {
         // Create new cart
         $newCartQuery = "INSERT INTO cart (userID) VALUES (?)";
         $newCartStmt = $_db->prepare($newCartQuery);
         
         if (!$newCartStmt) {
             throw new Exception("Failed to prepare new cart query: " . implode(', ', $_db->errorInfo()));
         }
         
         $newCartResult = $newCartStmt->execute([$userID]);
         
         if (!$newCartResult) {
             throw new Exception("Failed to execute new cart query: " . implode(', ', $newCartStmt->errorInfo()));
         }
         
         $cartID = $_db->lastInsertId();
         
         if (!$cartID) {
             throw new Exception("Failed to get new cart ID");
         }
     }
     
     $addedCount = 0;
     $skippedItems = [];
     
     // Add each item to cart
     foreach ($items as $item) {
         // Check if product is still available and has sufficient stock
         if ($item['stock_quantity'] >= $item['qty']) {
             // Check if item already exists in cart
             $cartCheckQuery = "SELECT cart_item_id, qty FROM cart_items WHERE cartID = ? AND prodID = ?";
             $cartCheckStmt = $_db->prepare($cartCheckQuery);
             $cartCheckStmt->execute([$cartID, $item['prodID']]);
             $existingCart = $cartCheckStmt->fetch(PDO::FETCH_ASSOC);
             
             if ($existingCart) {
                 // Update quantity in existing cart item
                 $newQty = $existingCart['qty'] + $item['qty'];
                 if ($newQty <= $item['stock_quantity']) {
                     $updateCartQuery = "UPDATE cart_items SET qty = ? WHERE cart_item_id = ?";
                     $updateCartStmt = $_db->prepare($updateCartQuery);
                     $updateCartStmt->execute([$newQty, $existingCart['cart_item_id']]);
                     $addedCount++;
                 } else {
                     $skippedItems[] = $item['name'] . " (insufficient stock)";
                 }
             } else {
                 // Add new item to cart
                 $addCartQuery = "INSERT INTO cart_items (cartID, prodID, qty) VALUES (?, ?, ?)";
                 $addCartStmt = $_db->prepare($addCartQuery);
                 $addCartStmt->execute([$cartID, $item['prodID'], $item['qty']]);
                 $addedCount++;
             }
         } else {
             $skippedItems[] = $item['name'] . " (out of stock)";
         }
     }
     
     // Set success/warning messages
     if ($addedCount > 0) {
         $message = "$addedCount item(s) added to cart successfully!";
         if (!empty($skippedItems)) {
             $message .= " Note: " . implode(', ', $skippedItems) . " could not be added.";
         }
         $_SESSION['success'] = $message;
     } else {
         $_SESSION['error'] = "No items could be added to cart. " . implode(', ', $skippedItems);
     }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect back to the specified page
$safeRedirect = !empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : "tracking_details.php?id=" . urlencode($orderID);
header("Location: $safeRedirect");
exit();
?>