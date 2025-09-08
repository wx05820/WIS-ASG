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
    $_SESSION['error'] = "Invalid security token. Please try again.";
    header("Location: tracking.php");
    exit();
}

if (empty($orderID)) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: tracking.php");
    exit();
}

try {
    // Verify the order belongs to the user and is in Pending status
    $checkQuery = "SELECT orderID, status FROM `order` WHERE orderID = ? AND userID = ? AND status = 'Pending'";
    $checkStmt = $_db->prepare($checkQuery);
    $checkStmt->execute([$orderID, $userID]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error'] = "Order not found, access denied, or order is not in Pending status";
        header("Location: tracking.php");
        exit();
    }
    
    // Start transaction
    $_db->beginTransaction();
    
    // Update order status to Cancelled
    $updateOrderQuery = "UPDATE `order` SET status = 'Cancelled' WHERE orderID = ?";
    $updateOrderStmt = $_db->prepare($updateOrderQuery);
    $updateOrderStmt->execute([$orderID]);
    
    // Restore product quantities
    $restoreQuery = "UPDATE product p 
                     INNER JOIN order_items oi ON p.prodID = oi.prodID 
                     SET p.qty = p.qty + oi.qty 
                     WHERE oi.orderID = ?";
    $restoreStmt = $_db->prepare($restoreQuery);
    $restoreStmt->execute([$orderID]);
    
    // Add to delivery status history
    $historyQuery = "INSERT INTO deliverystatus (orderID, status, courier, notes, current_location, updated_at) 
                     VALUES (?, 'Cancelled', 'System', 'Order cancelled by customer', 'Cancelled', NOW())";
    $historyStmt = $_db->prepare($historyQuery);
    $historyStmt->execute([$orderID]);
    
    // Commit transaction
    $_db->commit();
    
    $_SESSION['success'] = "Order #$orderID has been cancelled successfully. Product quantities have been restored.";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($_db->inTransaction()) {
        $_db->rollback();
    }
    
    error_log("Cancel order error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while cancelling the order. Please try again.";
}

header("Location: tracking.php");
exit();
?>