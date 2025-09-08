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
$redirect = $_POST['redirect'] ?? 'tracking.php';
$addressID = $_POST['addressID'] ?? '';

if (empty($orderID)) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: $redirect");
    exit();
}

if (empty($addressID)) {
    $_SESSION['error'] = "Please select an address";
    header("Location: $redirect");
    exit();
}

// Get the selected address details
$addressQuery = "SELECT * FROM user_address WHERE ID = ? AND userID = ?";
$addressStmt = $_db->prepare($addressQuery);
$addressStmt->execute([$addressID, $userID]);
$selectedAddress = $addressStmt->fetch(PDO::FETCH_ASSOC);

if (!$selectedAddress) {
    $_SESSION['error'] = "Selected address not found or access denied";
    header("Location: $redirect");
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
        header("Location: $redirect");
        exit();
    }
    
    // Update order address with selected address
    $updateQuery = "UPDATE `order` SET 
                    recipient_name = ?, 
                    phoneNo = ?, 
                    unitNo = ?, 
                    address_line_1 = ?, 
                    address_line_2 = ?, 
                    city = ?, 
                    state = ?, 
                    postcode = ?,
                    updated_at = NOW()
                    WHERE orderID = ?";
    $updateStmt = $_db->prepare($updateQuery);
    $updateStmt->execute([
        $selectedAddress['recipient_name'],
        $selectedAddress['phoneNo'] ?? '',
        $selectedAddress['unitNo'] ?? '',
        $selectedAddress['address_line_1'],
        $selectedAddress['address_line_2'] ?? '',
        $selectedAddress['city'],
        $selectedAddress['state'],
        $selectedAddress['postcode'],
        $orderID
    ]);
    
    // Add to delivery status history
    $historyQuery = "INSERT INTO deliverystatus (orderID, status, courier, notes, current_location, updated_at) 
                     VALUES (?, 'Pending', 'Customer', 'Delivery address updated by customer', 'Address Updated', NOW())";
    $historyStmt = $_db->prepare($historyQuery);
    $historyStmt->execute([$orderID]);
    
    $_SESSION['success'] = "Order #$orderID delivery address has been updated successfully.";
    
} catch (PDOException $e) {
    error_log("Change address error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while updating the address. Please try again.";
}

header("Location: $redirect");
exit();
?>





