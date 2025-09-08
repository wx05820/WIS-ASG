<?php
session_start();
require_once '../_base.php';

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orderID'])) {
    $orderID = $_POST['orderID'];
    $csrfToken = $_POST['csrf_token'] ?? '';
    $redirect = $_POST['redirect'] ?? '../order/history.php';
    
    // Validate CSRF token
    if (!validateCSRFToken($csrfToken)) {
        $_SESSION['error'] = "Invalid security token. Please refresh the page and try again.";
        $safeRedirect = !empty($redirect) ? $redirect : "../order/history.php";
        header("Location: $safeRedirect");
        exit();
    }
    
    try {
        // Verify the order belongs to the user and is in Processing status
        $orderQuery = "SELECT * FROM `order` WHERE orderID = ? AND userID = ? AND status = 'Processing'";
        $orderStmt = $_db->prepare($orderQuery);
        $orderStmt->execute([$orderID, $userID]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $_SESSION['error'] = 'Order not found or not eligible for refund cancellation.';
            $safeRedirect = !empty($redirect) ? $redirect : "../order/history.php";
            header("Location: $safeRedirect");
            exit;
        }
        
        // Determine the appropriate status to restore to based on context
        // If coming from history page, assume it was 'Received', otherwise 'Delivered'
        $restoreStatus = (strpos($redirect, 'history') !== false) ? 'Received' : 'Delivered';
        
        // Update order status back to the appropriate status
        $updateOrderQuery = "UPDATE `order` SET status = ? WHERE orderID = ?";
        $updateOrderStmt = $_db->prepare($updateOrderQuery);
        $updateOrderStmt->execute([$restoreStatus, $orderID]);
        
        // Try to delete from refund_requests table (if it exists)
        try {
            $deleteRefundQuery = "DELETE FROM refund_requests WHERE orderID = ? AND userID = ?";
            $deleteRefundStmt = $_db->prepare($deleteRefundQuery);
            $deleteRefundStmt->execute([$orderID, $userID]);
        } catch (Exception $e) {
            // If table doesn't exist, just log it and continue
            error_log("DEBUG REFUND - Could not delete from refund_requests table (table may not exist): " . $e->getMessage());
        }
        
        $_SESSION['success'] = 'Refund request cancelled successfully.';
        
    } catch (Exception $e) {
        error_log("Cancel refund request error: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to cancel refund request.';
    }
    
    $safeRedirect = !empty($redirect) ? $redirect : "../order/history.php";
    header("Location: $safeRedirect");
    exit;
} else {
    header('Location: ../order/history.php');
    exit;
}
?>

