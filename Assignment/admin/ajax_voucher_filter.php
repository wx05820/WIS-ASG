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
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$id_order = isset($_GET['id_order']) && strtoupper($_GET['id_order']) === 'ASC' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];

// Status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "is_active = ?";
    $params[] = ($status_filter === 'active') ? 'Active' : 'Inactive';
}

if (!empty($search)) {
    $where_conditions[] = "(code LIKE ? OR discount_type LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "discount_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Update SQL query with filters
$sql = "
    SELECT voucher_id, code as voucher_code, discount_type as voucher_type, value, start_date, end_date, 
           is_active, created_at, current_usage as usage_count, usage_limit as max_usage, description
    FROM voucher 
    $where_clause
    ORDER BY voucher_id $id_order, created_at DESC
";

// Create SimplePager instance with filtered parameters
include '../lib/SimplePager.php';
$pager = new SimplePager($sql, $params, 10, $page);
$vouchers = $pager->result;

// Convert result to objects for compatibility with existing code
foreach ($vouchers as &$voucher) {
    $voucher = (object) $voucher;
}
unset($voucher);

// Prepare response
$response = [
    'vouchers' => $vouchers,
    'total_vouchers' => $pager->item_count,
    'total_pages' => $pager->page_count,
    'current_page' => $page
];

// Set content type to JSON
header('Content-Type: application/json');
echo json_encode($response);
?>