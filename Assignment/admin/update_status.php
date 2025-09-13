<?php
require_once '../_base.php';

// Check if user is logged in and has admin privileges
if (!isStaffAdmin()) {
    redirect('../loginstaff.php');
    exit;
}

// Get database connection
$pdo = $_db;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = req('order_id');
    $new_status = req('new_status');
    
    // Validate inputs
    if (empty($order_id) || empty($new_status)) {
        temp('error', 'Invalid order ID or status');
        redirect('shipping.php');
        exit;
    }
    
    // Validate status value
    $valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Refunded'];
    if (!in_array($new_status, $valid_statuses)) {
        temp('error', 'Invalid status value');
        redirect('shipping.php');
        exit;
    }
    
    try {
        // Update the order status
        $sql = "UPDATE `order` SET status = ? WHERE orderID = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$new_status, $order_id]);
        
        if ($result) {
            temp('success', "Order status updated to {$new_status} successfully");
        } else {
            temp('error', 'Failed to update order status');
        }
    } catch (PDOException $e) {
        error_log("Status update error: " . $e->getMessage());
        temp('error', 'Database error occurred while updating status');
    }
} else {
    temp('error', 'Invalid request method');
}

// Redirect back to shipping page
redirect('shipping.php');
?>
