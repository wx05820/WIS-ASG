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
        // Verify the order belongs to the user and is in Processing status
        $orderQuery = "SELECT * FROM `order` WHERE orderID = ? AND userID = ? AND status = 'Processing'";
        $orderStmt = $_db->prepare($orderQuery);
        $orderStmt->execute([$orderID, $userID]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $_SESSION['error'] = 'Order not found or not eligible for cancellation.';
            header("Location: $redirect");
            exit;
        }
        
        // Update refund request status to cancelled if table exists
        try {
            $refundQuery = "UPDATE refund_requests SET status = 'cancelled', updated_at = NOW() WHERE orderID = ? AND userID = ?";
            $refundStmt = $_db->prepare($refundQuery);
            $refundStmt->execute([$orderID, $userID]);
        } catch (Exception $e) {
            error_log("Could not update refund_requests table: " . $e->getMessage());
        }
        
        // Update order status back to 'Delivered'
        $updateOrderQuery = "UPDATE `order` SET status = 'Delivered' WHERE orderID = ?";
        $updateStmt = $_db->prepare($updateOrderQuery);
        $updateStmt->execute([$orderID]);
        
        $_SESSION['success'] = 'Refund request cancelled successfully.';
        
    } catch (Exception $e) {
        error_log("Cancel refund request error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to cancel refund request. Please try again.';
    }
    
    header("Location: $redirect");
    exit;
}

// If not POST request, redirect to history
header("Location: ../order/history.php");
exit;
?>