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
$csrfToken = $_POST['csrf_token'] ?? '';
$redirect = $_POST['redirect'] ?? '';

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
    // Verify the order belongs to the user and is in Delivered status for marking as received
    $checkQuery = "SELECT orderID, status FROM `order` WHERE orderID = ? AND userID = ? AND status = 'Delivered'";
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
        $_SESSION['error'] = "Order not found, access denied, or order status is not valid for marking as received.";
        $safeRedirect = !empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : "tracking_details.php?id=" . urlencode($orderID);
        header("Location: $safeRedirect");
        exit();
    }
    
    // Update order status to received and set received_date
    $updateQuery = "UPDATE `order` SET status = 'Received', received_date = NOW() WHERE orderID = ? AND userID = ?";
    $updateStmt = $_db->prepare($updateQuery);
    
    if (!$updateStmt) {
        throw new Exception("Failed to prepare update query: " . implode(', ', $_db->errorInfo()));
    }
    
    $updateResult = $updateStmt->execute([$orderID, $userID]);
    $rowsAffected = $updateStmt->rowCount();
    
    if (!$updateResult) {
        throw new Exception("Failed to execute update query: " . implode(', ', $updateStmt->errorInfo()));
    }
    
    if ($rowsAffected > 0) {
        $_SESSION['success'] = "Order #$orderID marked as received successfully! It has been moved to your order history.";
        
        // Add to delivery status history
        try {
            $historyQuery = "INSERT INTO deliverystatus (orderID, status, courier, notes, current_location, updated_at) 
                            VALUES (?, 'Received', 'Customer', 'Order confirmed as received by customer', 'Delivered', NOW())";
            $historyStmt = $_db->prepare($historyQuery);
            
            if (!$historyStmt) {
                throw new Exception("Failed to prepare history query: " . implode(', ', $_db->errorInfo()));
            }
            
            $historyResult = $historyStmt->execute([$orderID]);
            
            if (!$historyResult) {
                throw new Exception("Failed to execute history query: " . implode(', ', $historyStmt->errorInfo()));
            }
        } catch (Exception $e) {
            // Log history insert errors but don't fail the main operation
            error_log("History insert error: " . $e->getMessage());
        }
        
    } else {
        $_SESSION['error'] = "Failed to update order status. The order may have already been processed or there was a database error.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to tracking page since order is now marked as received
header("Location: tracking.php");
exit();
?>
