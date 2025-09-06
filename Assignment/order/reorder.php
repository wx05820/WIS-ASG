<?php
session_start();
require_once '../_base.php';

//debug msg
error_log("DEBUG REORDER - Session contents: " . print_r($_SESSION, true));
error_log("DEBUG REORDER - POST data: " . print_r($_POST, true));

$userID = $_SESSION['user_id'] ?? null;
checkLogin();


// Additional debug
error_log("DEBUG REORDER - Final userID: " . ($userID ?? 'NULL'));
error_log("DEBUG REORDER - Order ID from POST: " . ($_POST['orderID'] ?? 'NOT SET'));

// Check request method and required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['orderID'])) {
    $_SESSION['error'] = "Invalid request";
    header('Location: order_history.php');
    exit();
}

if (isset($_SESSION['success'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            showSuccess("'.htmlspecialchars($_SESSION['success']).'");
        });
        </script>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            showError("'.htmlspecialchars($_SESSION['error']).'");
        });
        </script>';
    unset($_SESSION['error']);
}

$orderID = intval($_POST['orderID']);
$redirect = $_POST['redirect'] ?? 'order_history.php';

error_log("DEBUG REORDER - Processing order ID: $orderID for user ID: $userID");

try {
    // Verify the order belongs to the user and get order details
    $verifyQuery = "SELECT o.orderID, o.status FROM `order` o WHERE o.orderID = ? AND o.userID = ?";
    $verifyStmt = $_db->prepare($verifyQuery);
    $verifyStmt->execute([$orderID, $userID]);
    $orderData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orderData) {
        $_SESSION['error'] = "Order not found or access denied";
        header("Location: $redirect");
        exit();
    }
    
    // Get order items with product information
    $itemsQuery = "SELECT oi.prodID, oi.qty, oi.product_name, oi.product_color, oi.price,
                          p.name, p.price as current_price, p.qty as stock_qty, p.status as product_status,
                          p.color as product_color
                   FROM order_items oi 
                   LEFT JOIN product p ON oi.prodID = p.prodID 
                   WHERE oi.orderID = ?";

    $itemsStmt = $_db->prepare($itemsQuery);
    $itemsStmt->execute([$orderID]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        $_SESSION['error'] = "No items found in this order";
        header("Location: $redirect");
        exit();
    }
    
    // Start transaction
    $_db->beginTransaction();
    
    // Ensure cart exists for user
    $stmt = $_db->prepare("SELECT cartID FROM cart WHERE userID=?");
    $stmt->execute([$userID]);
    $cartID = $stmt->fetchColumn();

    if (!$cartID) {
        $stmt = $_db->prepare("INSERT INTO cart(userID) VALUES(?)");
        $stmt->execute([$userID]);
        $cartID = $_db->lastInsertId();

        if (!$cartID) {
            throw new Exception('Failed to create cart for user');
        }
    }

    $addedItems = [];
    $skippedItems = [];
    
    foreach ($items as $item) {
        $prodID = $item['prodID'];
        $requestedQty = $item['qty'];
        $productName = $item['product_name'] ?: $item['name'];
        $productColor = $item['product_color'];
        $availableStock = $item['stock_qty'] ?? 0;
        $productStatus = $item['product_status'];
        
        // Skip if product is inactive or doesn't exist
        if (empty($prodID) || $productStatus === 'removed') {
            $skippedItems[] = $productName . " (no longer available)";
            continue;
        }
        
        // Skip if no stock
        if ($availableStock <= 0) {
            $skippedItems[] = $productName . " (out of stock)";
            continue;
        }
        
        // Check if item is already in cart
        $cartCheckQuery = "SELECT qty FROM cart_items WHERE cartID = ? AND prodID = ?";
        $cartCheckStmt = $_db->prepare($cartCheckQuery);
        $cartCheckStmt->execute([$cartID, $prodID]);
        $cartResult = $cartCheckStmt->fetchColumn();
        
        if ($cartResult !== false) {
            $newQty = min((int)$currentQty + $requestedQty, $availableStock);
            
            if ($newQty > $availableStock) {
                $skippedItems[] = $productName . " (not enough stock available)";
                continue;
            }

            if ($newQty > $currentQty) {
                $stmt = $_db->prepare("UPDATE cart_items SET qty=? WHERE cartID=? AND prodID=?");
                if ($stmt->execute([$newQty, $cartID, $prodID])) {
                    $addedQty = $newQty - $currentQty;
                    $colorText = $productColor ? " ($productColor)" : "";
                    $addedItems[] = $productName . $colorText . " (+" . $addedQty . ")";
                    
                    if ($newQty < $currentQty + $requestedQty) {
                        $skippedItems[] = $productName . $colorText . " (limited stock - partial add)";
                    }
                } else {
                    $skippedItems[] = $productName . " (failed to update)";
                }
            } else {
                $colorText = $productColor ? " ($productColor)" : "";
                $skippedItems[] = $productName . $colorText . " (already at maximum in cart)";
            }
        } else {
            // Add new cart item
            $addQty = min($requestedQty, $availableStock);
            
            if ($addQty > $availableStock) {
                $skippedItems[] = $productName . " (not enough stock available)";
                continue;
            }
            
            if ($addQty > 0) {
                $stmt = $_db->prepare("INSERT INTO cart_items(cartID, prodID, qty) VALUES(?,?,?)");
                if ($stmt->execute([$cartID, $prodID, $addQty])) {
                    $colorText = $productColor ? " ($productColor)" : "";
                    $addedItems[] = $productName . $colorText . " (" . $addQty . ")";
                    
                    if ($addQty < $requestedQty) {
                        $skippedItems[] = $productName . $colorText . " (limited stock - partial add)";
                    }
                } else {
                    $skippedItems[] = $productName . " (failed to add)";
                }
            } else {
                $skippedItems[] = $productName . " (out of stock)";
            }
        }
    }
    
    // Commit transaction
    $_db->commit();
    
    // Prepare success message
    if (!empty($addedItems)) {
        $itemCount = count($addedItems);
        $message = "$itemCount item(s) added to cart successfully";
        
        if (!empty($skippedItems)) {
            $skippedCount = count($skippedItems);
            $message .= ". $skippedCount item(s) were skipped due to limitations.";
        }
        
        $_SESSION['success'] = $message;
        
        // Redirect to cart if requested
        if (isset($_POST['goto_cart']) && $_POST['goto_cart'] == '1') {
            header('Location: cart_page.php');
        } else {
            header("Location: $redirect");
        }
    } else {
        $_SESSION['error'] = "No items could be added to cart. All items may be unavailable or out of stock.";
        header("Location: $redirect");
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($_db->inTransaction()) {
        $_db->rollback();
    }
    
    error_log("Reorder error: " . $e->getMessage());
    
    $_SESSION['error'] = "Failed to add items to cart. Please try again.";
    header("Location: $redirect");
}

header("Location: $redirect");
exit();
?>