<?php
require_once '../_base.php';


if (!isStaffAdmin()) {
    redirect('../loginstaff.php');
    exit;
}


$pdo = $_db;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_ids = $_POST['_bulk'] ?? [];
    $bulk_status = req('bulk_status');
    
    // Validate inputs
    if (empty($order_ids) || !is_array($order_ids)) {
        temp('error', 'No orders selected');
        redirect('shipping.php');
        exit;
    }
    
    if (empty($bulk_status)) {
        temp('error', 'Please select a status');
        redirect('shipping.php');
        exit;
    }
    
    // Validate status value
    $valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Refunded'];
    if (!in_array($bulk_status, $valid_statuses)) {
        temp('error', 'Invalid status value');
        redirect('shipping.php');
        exit;
    }
    
    try {
        // Prepare placeholders for the IN clause
        $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
        
        // Update the order statuses
        $sql = "UPDATE `order` SET status = ? WHERE orderID IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        
        // Combine status and order IDs for execution
        $params = array_merge([$bulk_status], $order_ids);
        $result = $stmt->execute($params);
        
        if ($result) {
            $count = count($order_ids);
            temp('success', "Successfully updated {$count} order(s) to {$bulk_status}");
        } else {
            temp('error', 'Failed to update order statuses');
        }
    } catch (PDOException $e) {
        error_log("Bulk status update error: " . $e->getMessage());
        temp('error', 'Database error occurred while updating statuses');
    }
} else {
    temp('error', 'Invalid request method');
}

// Redirect back to shipping page
redirect('shipping.php');
?>
