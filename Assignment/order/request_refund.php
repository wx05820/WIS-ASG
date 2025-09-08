<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orderID'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        header("Location: ../order/history.php");
        exit;
    }
    
    $orderID = $_POST['orderID'];
    $redirect = $_POST['redirect'] ?? '../order/history.php';
    
    try {
        // Verify the order belongs to the user and is delivered or received
        $orderQuery = "SELECT * FROM `order` WHERE orderID = ? AND userID = ? AND (status = 'Delivered' OR status = 'Received')";
        $orderStmt = $_db->prepare($orderQuery);
        $orderStmt->execute([$orderID, $userID]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $_SESSION['error'] = 'Order not found or not eligible for refund.';
            header("Location: $redirect");
            exit;
        }
        
        // Check if order is already in refund process
        if ($order['status'] === 'Processing' || $order['status'] === 'Refunded') {
            $_SESSION['error'] = 'Refund request already exists for this order.';
            header("Location: $redirect");
            exit;
        }
        
        // Try to create refund request in database (if table exists)
        try {
            $refundQuery = "INSERT INTO refund_requests (orderID, userID, request_date, status, reason, admin_notes) 
                           VALUES (?, ?, NOW(), 'pending', 'Customer requested refund', '')";
            $refundStmt = $_db->prepare($refundQuery);
            $refundStmt->execute([$orderID, $userID]);
            error_log("DEBUG REFUND - Refund request inserted into database");
        } catch (Exception $e) {
            // If table doesn't exist, just log it and continue
            error_log("DEBUG REFUND - Could not insert into refund_requests table (table may not exist): " . $e->getMessage());
        }
        
        // Update order status to 'Processing' (waiting for admin approval)
        $updateOrderQuery = "UPDATE `order` SET status = 'Processing' WHERE orderID = ?";
        $updateOrderStmt = $_db->prepare($updateOrderQuery);
        $updateOrderStmt->execute([$orderID]);
        
        $_SESSION['success'] = 'Refund request submitted successfully. Waiting for admin approval.';
        
    } catch (Exception $e) {
        error_log("Refund request error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to submit refund request. Please try again.';
    }
    
    header("Location: $redirect");
    exit;
}

// If not POST request, redirect to history
header("Location: ../order/history.php");
exit;
?>
