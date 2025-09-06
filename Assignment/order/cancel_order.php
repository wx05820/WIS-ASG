<?php
session_start();
require_once '../_base.php';

// Debug session variables
error_log("DEBUG CANCEL - Session contents: " . print_r($_SESSION, true));
error_log("DEBUG CANCEL - POST data: " . print_r($_POST, true));

checkLogin();
$userID = $_SESSION['user_id'] ?? null;

// Check request method and required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['orderID'])) {
    $_SESSION['error'] = "Invalid request";
    header('Location: order_history.php');
    exit();
}

$orderID = intval($_POST['orderID']);
$redirect = $_POST['redirect'] ?? 'order_history.php';

try {
    // Get order details and verify ownership
    $orderQuery = "SELECT o.orderID, o.status, o.total, o.payID, p.payStatus 
                   FROM `order` o 
                   LEFT JOIN payment p ON o.payID = p.payID
                   WHERE o.orderID = ? AND o.userID = ?";
    $orderStmt = $_db->prepare($orderQuery);
    $orderStmt->execute([$orderID, $userID]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error'] = "Order not found or access denied";
        header("Location: $redirect");
        exit();
    }
    
    // Check if order can be cancelled
    $cancellableStatuses = ['Shipped'];
    if (!in_array($order['status'], $cancellableStatuses)) {
        $_SESSION['error'] = "Order cannot be cancelled. Current status: " . ucfirst($order['status']);
        header("Location: $redirect");
        exit();
    }
    
    // Start transaction
    $_db->beginTransaction();
    
    // Get order items to restore stock
    $itemsQuery = "SELECT prodID, qty FROM order_items WHERE orderID = ?";
    $itemsStmt = $_db->prepare($itemsQuery);
    $itemsStmt->execute([$orderID]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Restore stock for each item
    foreach ($items as $item) {
        $updateStockQuery = "UPDATE product SET qty = qty + ? WHERE prodID = ?";
        $updateStockStmt = $_db->prepare($updateStockQuery);
        
        if (!$updateStockStmt->execute([$item['qty'], $item['prodID']])) {
            throw new Exception("Failed to restore stock for product ID: " . $item['prodID']);
        }

        // Verify stock was updated
        if ($updateStockStmt->rowCount() === 0) {
            error_log("Warning: No rows affected when restoring stock for product ID: " . $item['prodID']);
        }
    }
    
    // Update order status to cancelled
    $updateOrderQuery = "UPDATE `order` SET status = 'Cancelled' WHERE orderID = ?";
    $updateOrderStmt = $_db->prepare($updateOrderQuery);
    
    if (!$updateOrderStmt->execute([$orderID])) {
        throw new Exception("Failed to update order status");
    }
    
    // Verify order status was updated
    if ($updateOrderStmt->rowCount() === 0) {
        throw new Exception("Order status was not updated - no rows affected");
    }

    // Update payment status if payment exists and is not completed
    if (!empty($order['payID']) && in_array($order['payStatus'], ['pending', 'processing'])) {
        $updatePaymentQuery = "UPDATE payment SET payStatus = 'Cancelled', updated_at = NOW() WHERE payID = ?";
        $updatePaymentStmt = $_db->prepare($updatePaymentQuery);
        
        if (!$updatePaymentStmt->execute([$order['payID']])) {
            error_log("Warning: Failed to update payment status for payment ID: " . $order['payID']);
        }
    }
    
    // Add delivery status record for cancellation
    $deliveryStatusQuery = "INSERT INTO deliverystatus (orderID, status, notes, updated_at) 
                           VALUES (?, 'Cancelled', 'Order cancelled by customer', NOW())";
    $deliveryStatusStmt = $_db->prepare($deliveryStatusQuery);
    $deliveryStatusStmt->execute([$orderID]);
    
    // Commit transaction
    $_db->commit();
    
    // Send cancellation notification email if function exists
    if (function_exists('sendOrderCancellationEmail')) {
        try {
            sendOrderCancellationEmail($userID, $orderID, $order['total']);
        } catch (Exception $e) {
            error_log("Failed to send cancellation email: " . $e->getMessage());
        }
    }
    
    $_SESSION['success'] = "Order #$orderID has been cancelled successfully. Stock has been restored and any pending payments have been cancelled.";
    header("Location: $redirect");
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($_db->inTransaction()) {
        $_db->rollback();
    }
    
    error_log("Cancel order error: " . $e->getMessage());
    error_log("Cancel order error details - Order ID: $orderID, User ID: $userID");

    $_SESSION['error'] = "Failed to cancel order. Please try again or contact support.";
    header("Location: $redirect");
}
exit();
?>