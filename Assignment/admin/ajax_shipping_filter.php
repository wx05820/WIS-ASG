<?php
include '../_base.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
$id_order = isset($_GET['id_order']) && strtoupper($_GET['id_order']) === 'ASC' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];

// Status filter - if 'all' is selected, show all shipping-related statuses, otherwise filter by specific status
if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
} else {
    // Show all shipping-related statuses when 'all' is selected
    $where_conditions[] = "status IN ('Pending', 'Processing', 'Shipped', 'Delivered')";
}

if (!empty($search)) {
    $where_conditions[] = "(orderID LIKE ? OR userID LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(orderDate) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "orderDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "orderDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Update SQL query with filters
$sql = "
    SELECT orderID, orderDate, userID, status, shipping_method, subtotal, 
           shipping_fee, discount, total, phoneNo, recipient_name, unitNo, 
           address_line_1, address_line_2, city, postcode, state
    FROM `order` 
    $where_clause
    ORDER BY orderID $id_order, orderDate DESC
";

// Create SimplePager instance with filtered parameters
include '../lib/SimplePager.php';
$pager = new SimplePager($sql, $params, 10, $page);
$orders = $pager->result;

// Convert result to objects for compatibility with existing code
foreach ($orders as &$order) {
    $order = (object) $order;
}
unset($order);

// Get order status statistics for pie chart - only shipping-related statuses
$status_stats_sql = "
    SELECT status, COUNT(*) as count 
    FROM `order` 
    WHERE status IN ('Pending', 'Processing', 'Shipped', 'Delivered')
    GROUP BY status 
    ORDER BY 
        CASE status 
            WHEN 'Pending' THEN 1 
            WHEN 'Processing' THEN 2 
            WHEN 'Shipped' THEN 3 
            WHEN 'Delivered' THEN 4 
        END
";

$status_stats_stmt = $_db->prepare($status_stats_sql);
$status_stats_stmt->execute();
$status_stats = $status_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare response
$response = [
    'orders' => $orders,
    'total_orders' => $pager->item_count,
    'total_pages' => $pager->page_count,
    'current_page' => $page,
    'status_stats' => $status_stats
];

// Set content type to JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
