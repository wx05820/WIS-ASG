<?php
// API endpoint to retrieve refund requests for staff
session_start();
require_once '../_base.php';

// Check if user is staff or admin
if (!isset($_SESSION['user_id']) || (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'staff'))) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'all';
    
    switch ($action) {
        case 'pending':
            // Get pending refund requests
            $query = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email, u.phone
                     FROM `order` o 
                     JOIN user u ON o.userID = u.userID 
                     WHERE o.status = 'Processing' 
                     ORDER BY o.orderDate DESC";
            break;
            
        case 'processed':
            // Get processed refund requests
            $query = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email, u.phone
                     FROM `order` o 
                     JOIN user u ON o.userID = u.userID 
                     WHERE o.status = 'Refunded' 
                     ORDER BY o.orderDate DESC";
            break;
            
        case 'all':
        default:
            // Get all refund requests
            $query = "SELECT o.orderID, o.total, o.orderDate, o.status, u.username, u.email, u.phone
                     FROM `order` o 
                     JOIN user u ON o.userID = u.userID 
                     WHERE o.status IN ('Processing', 'Refunded') 
                     ORDER BY o.orderDate DESC";
            break;
    }
    
    $stmt = $_db->prepare($query);
    $stmt->execute();
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order items for each refund request
    if (!empty($refunds)) {
        $orderIDs = array_column($refunds, 'orderID');
        $placeholders = str_repeat('?,', count($orderIDs) - 1) . '?';
        
        $itemsQuery = "SELECT oi.*, p.name, p.price, p.image1, p.prodID
                       FROM order_items oi 
                       JOIN product p ON oi.prodID = p.prodID 
                       WHERE oi.orderID IN ($placeholders)
                       ORDER BY oi.orderID, oi.order_item_id";
        
        $itemsStmt = $_db->prepare($itemsQuery);
        $itemsStmt->execute($orderIDs);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group items by orderID
        $orderItems = [];
        foreach ($items as $item) {
            $orderItems[$item['orderID']][] = $item;
        }
        
        // Add items to each refund request
        foreach ($refunds as &$refund) {
            $refund['items'] = $orderItems[$refund['orderID']] ?? [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($refunds),
        'data' => $refunds
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
