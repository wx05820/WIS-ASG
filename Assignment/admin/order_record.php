<?php
require_once '../_base.php';
require_once '../lib/SimplePager.php';

// Check if user is admin/staff
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

$page_title = 'Order History - Admin';

$selectedOrder = null;
$orders = [];

// pagination
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$pagerHtml = '';

// helper to normalize various column names between schemas
function normalize_order_row($row) {
    if (!$row) return null;
    $get = function($keys) use ($row) {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== null) return $row[$k];
        }
        return null;
    };

    return [
        'orderID' => $get(['orderID', 'order_id', 'id']),
        'orderDate' => $get(['orderDate', 'created_at', 'orderDate']),
        'userID' => $get(['userID', 'user_id', 'customerID']),
        'status' => $get(['status']),
        'received_date' => $get(['received_date', 'receivedDate']),
        'shipping_method' => $get(['shipping_method', 'shippingMethod']),
        'subtotal' => $get(['subtotal', 'sub_total', 'amount_subtotal']),
        'shipping_fee' => $get(['shipping_fee', 'shippingFee', 'delivery_fee']),
        'discount' => $get(['discount']),
        'total' => $get(['total', 'grand_total', 'amount_total']),
        'payID' => $get(['payID', 'pay_id', 'payment_id']),
        'addressID' => $get(['addressID', 'address_id']),
        'recipient_name' => $get(['recipient_name', 'recipientName', 'name']),
        'phoneNo' => $get(['phoneNo', 'phone', 'phone_no']),
        'unitNo' => $get(['unitNo', 'unit_no']),
        'address_line_1' => $get(['address_line_1', 'address1', 'line1']),
        'address_line_2' => $get(['address_line_2', 'address2', 'line2']),
        'city' => $get(['city']),
        'postcode' => $get(['postcode', 'postal_code', 'postcode']),
        'state' => $get(['state']),
        'notes' => $get(['notes', 'note', 'order_notes']),
    ];
}

try {
    // Use the singular `order` table as confirmed
    $usedTable = "`order`";
    
    // Get order status statistics for pie chart - only show refunded, received, cancelled
    $status_stats_sql = "
        SELECT status, COUNT(*) as count 
        FROM `order` 
        WHERE status IN ('Received', 'Cancelled', 'Refunded')
        GROUP BY status 
        ORDER BY count DESC
    ";
    $status_stats_stmt = $_db->prepare($status_stats_sql);
    $status_stats_stmt->execute();
    $status_stats = $status_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for Chart.js - Pie Chart
    $chart_labels = [];
    $chart_data = [];
    $chart_colors = [];

    $status_color_map = [
        'delivered' => '#28A745',  // Green (legacy)
        'cancelled' => '#DC3545',  // Red (legacy)
        'shipped' => '#FFC107',    // Yellow/Orange (legacy)
        'Delivered' => '#28A745',  // Green (legacy)
        'Cancelled' => '#DC3545',  // Red
        'Shipped' => '#FFC107',    // Yellow/Orange (legacy)
        'received' => '#28A745',   // Green
        'Received' => '#28A745',   // Green - delivered orders
        'pending' => '#17A2B8',    // Blue
        'Pending' => '#17A2B8',    // Blue - pending orders
        'processing' => '#FFC107', // Orange
        'Processing' => '#FFC107', // Orange - shipped/processing orders
        'refunded' => '#6C757D',   // Gray
        'Refunded' => '#6C757D'    // Gray - refunded orders
    ];

    foreach ($status_stats as $stat) {
        $chart_labels[] = ucfirst(strtolower($stat['status']));
        $chart_data[] = (int)$stat['count'];
        // Use predefined color or default gray for unknown statuses
        $chart_colors[] = $status_color_map[$stat['status']] ?? '#6C757D';
    }
    
    // Handle filter parameters
    $filter_orderID = isset($_GET['orderID']) ? trim($_GET['orderID']) : '';
    $filter_userID = isset($_GET['userID']) ? trim($_GET['userID']) : '';
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
    $filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $filter_price_min = isset($_GET['price_min']) ? $_GET['price_min'] : '';
    $filter_price_max = isset($_GET['price_max']) ? $_GET['price_max'] : '';
    
    // Build WHERE clause with filters
    // Build WHERE clause with filters - only show refunded, received, cancelled orders
    $where_conditions = ["status IN ('Received', 'Cancelled', 'Refunded')"]; // Only show completed/final statuses
    $params = [];
    
    if (!empty($filter_orderID)) {
        $where_conditions[] = "orderID LIKE ?";
        $params[] = '%' . $filter_orderID . '%';
    }
    
    if (!empty($filter_userID)) {
        $where_conditions[] = "userID LIKE ?";
        $params[] = '%' . $filter_userID . '%';
    }
    
    if (!empty($filter_status)) {
        $where_conditions[] = "LOWER(status) = LOWER(?)";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_date_from)) {
        $where_conditions[] = "DATE(orderDate) >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $where_conditions[] = "DATE(orderDate) <= ?";
        $params[] = $filter_date_to;
    }
    
    if (!empty($filter_price_min)) {
        $where_conditions[] = "total >= ?";
        $params[] = $filter_price_min;
    }
    
    if (!empty($filter_price_max)) {
        $where_conditions[] = "total <= ?";
        $params[] = $filter_price_max;
    }
    
    // build paged query with filters
    $sql = "SELECT
            orderID,
            orderDate,
            userID,
            status,
            received_date,
            shipping_method,
            subtotal,
            shipping_fee,
            discount,
            total,
            payID,
            addressID,
            recipient_name,
            phoneNo,
            unitNo,
            address_line_1,
            address_line_2,
            city,
            postcode,
            state,
            notes
        FROM `order`
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY orderDate DESC";

    // create pager
    $pager = new SimplePager($sql, $params, $limit, $page);
    $rawOrders = $pager->result;
    foreach ($rawOrders as $r) {
        $orders[] = normalize_order_row($r);
    }

    // pager html (preserve filter parameters)
    $qs = $_GET;
    unset($qs['page']);
    $href = http_build_query($qs);
    ob_start();
    $pager->html($href);
    $pagerHtml = ob_get_clean();

    // If a specific orderID requested, fetch it from the same table
    if (!empty($_GET['orderID'])) {
        $orderID = $_GET['orderID'];
        $stmt = $_db->prepare("SELECT * FROM `order` WHERE orderID = ? LIMIT 1");
        $stmt->execute([$orderID]);
        $sel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sel) $selectedOrder = normalize_order_row($sel);
    }

} catch (Exception $e) {
    error_log('Order history error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userlist.css">
    <link rel="stylesheet" href="../css/products.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Order Record specific styles */
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .orders-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .orders-table th, .orders-table td { padding: 12px 15px; border-bottom: 1px solid #e6e6e6; text-align: left; }
        .orders-table th { background: #f8f9fa; font-weight: 600; color: #333; }
        .orders-table td { color: #000; }
        .orders-table tr:hover { background: #f8f9fa; }
        .orders-actions a { margin-right: 8px; }
        
        /* Status Color Coding */
        .status-shipped { color: #007BFF !important; font-weight: 600; } /* Legacy */
        .status-delivered { color: #28A745 !important; font-weight: 600; } /* Legacy */
        .status-cancelled { color: #DC3545 !important; font-weight: 600; }
        .status-processing { color: #FFC107 !important; font-weight: 600; } /* Shipped orders */
        .status-received { color: #28A745 !important; font-weight: 600; } /* Delivered orders */
        .status-pending { color: #17A2B8 !important; font-weight: 600; } /* Pending orders */
        .status-refunded { color: #6C757D !important; font-weight: 600; } /* Refunded orders */
        .order-detail { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px; }
        .order-detail h2 { color: #333; }
        .order-detail h3 { color: #333; }
        .order-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .order-grid > div:nth-child(1) .label { color: #d81515a2; }
        .order-grid > div:nth-child(2) .label { color: #28b193fa; }
        .order-grid > div:nth-child(3) .label { color: #e87400d2; }
        .order-row { margin-bottom: 10px; }
        .label { color: #666; font-weight: 600; font-size: 0.95rem; }
        .value { color: #333; }
        .no-data { background: white; padding: 40px; text-align: center; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: #666; }
        .pager { display: flex; gap: 8px; justify-content: center; align-items: center; margin: 20px 0; }
        .pager a { padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; background: white; }
        .pager a:hover { background: #f5f5f5; }
        .pager a.active { background: #8B4513; color: white; border-color: #8B4513; }
        .btn { background: #8B4513; color: white; padding: 6px 12px; border: none; border-radius: 4px; text-decoration: none; font-size: 0.9rem; }
        .btn:hover { background: #A0522D; color: white; }
        
        /* Chart Styles */
        .chart-section { margin-bottom: 30px; }
        .chart-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .chart-container h3 { color: #333; margin-bottom: 20px; font-size: 1.3rem; }
        .chart-wrapper { max-width: 300px; margin: 0 auto; }
        .chart-legend { margin-top: 20px; }
        .chart-legend h4 { color: #666; margin-bottom: 15px; font-size: 1rem; }
        .legend-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .legend-item { display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 4px; background: #f8f9fa; }
        .legend-color { width: 16px; height: 16px; border-radius: 50%; }
        .legend-info { display: flex; flex-direction: column; }
        .legend-label { font-weight: 600; color: #333; font-size: 0.9rem; }
        .legend-count { color: #666; font-size: 0.8rem; }
        
        /* Filter Styles */
        .filter-section { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-section h3 { color: #444; margin-bottom: 10px; font-size: 1.1rem; }
        .filter-form { display: flex; flex-direction: column; gap: 12px; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: 600; color: #333; margin-bottom: 4px; font-size: 0.85rem; }
        .filter-group input, .filter-group select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.85rem; }
        .filter-actions { display: flex; gap: 8px; justify-content: flex-end; align-items: flex-end; }
        .filter-btn { background: #8B4513; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
        .filter-btn:hover { background: #A0522D; }
        .filter-btn.reset { background: #6C757D; }
        .filter-btn.reset:hover { background: #5A6268; }
        .price-range { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px; align-items: center; }
        .price-range span { text-align: center; color: #666; font-size: 0.75rem; }
        
        @media (max-width: 768px) { 
            .order-grid { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="product-list-main" style="margin-top:0; padding-top:0;">
    <?php include 'adminheader.php'; ?>

    <div class="container">

        <!-- Pie Chart Section -->
        <div class="chart-section">
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Order Status Distribution</h3>
                <div class="chart-wrapper">
                    <canvas id="statusChart" width="300" height="250"></canvas>
                </div>
                
                <!-- Color Legend -->
                <div class="chart-legend">
                    <h4>Status Summary</h4>
                    <div class="legend-grid">
                        <?php foreach ($status_stats as $stat): 
                            $status = $stat['status'];
                            $count = $stat['count'];
                            $color = isset($status_color_map[$status]) ? $status_color_map[$status] : '#6C757D';
                        ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: <?php echo $color; ?>;"></div>
                            <div class="legend-info">
                                <span class="legend-label"><?php echo ucfirst(strtolower($status)); ?></span>
                                <span class="legend-count"><?php echo $count; ?> orders</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="combined-section" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 30px;">
            <h3 style="color: #656565ff;"><i class="fas fa-filter" style="color: #656565ff;"></i> Filter Orders</h3>
            <form method="GET" class="filter-form" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 20px; background: #f9fafb;">
                <!-- First Row: Order ID, User ID, Status -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="orderID">Order ID</label>
                        <input type="text" id="orderID" name="orderID" value="<?php echo htmlspecialchars($filter_orderID); ?>" placeholder="Enter Order ID">
                    </div>
                    
                    <div class="filter-group">
                        <label for="userID">User ID</label>
                        <input type="text" id="userID" name="userID" value="<?php echo htmlspecialchars($filter_userID); ?>" placeholder="Enter User ID">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="Received" <?php echo $filter_status === 'Received' ? 'selected' : ''; ?>>Received (Delivered)</option>
                            <option value="Cancelled" <?php echo $filter_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="Refunded" <?php echo $filter_status === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                </div>
                
                <!-- Second Row: Date Range -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                </div>
                
                <!-- Third Row: Price Range and Actions -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Price Range (RM)</label>
                        <div class="price-range">
                            <input type="number" name="price_min" value="<?php echo htmlspecialchars($filter_price_min); ?>" placeholder="Min" step="0.01" min="0">
                            <span>to</span>
                            <input type="number" name="price_max" value="<?php echo htmlspecialchars($filter_price_max); ?>" placeholder="Max" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="order_record.php" class="filter-btn reset">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Results Summary -->
            <div style="background: #8B4513; padding: 10px 15px; border-radius: 4px; margin-top: 15px; margin-bottom: 15px; border-left: 4px solid #8B4513;">
                <span style="color: #F5F5F5; font-size: 0.9rem;">
                    <i class="fas fa-info-circle" style="color: #F5F5F5;"></i> 
                    Found <?php echo count($orders); ?> orders
                    <?php if ($pager->item_count > count($orders)): ?>
                        (showing <?php echo count($orders); ?> of <?php echo $pager->item_count; ?> total)
                    <?php endif; ?>
                    
                    <?php if (!empty($filter_orderID) || !empty($filter_userID) || !empty($filter_status) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_price_min) || !empty($filter_price_max)): ?>
                        <span style="color: #D4AF37; font-weight: 600;">with active filters</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($orders)): ?>
                <div class="no-data">No orders found.</div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>User ID</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($o['orderID']); ?></td>
                                <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($o['orderDate']))); ?></td>
                                <td><?php echo htmlspecialchars($o['userID']); ?></td>
                                <td class="status-<?php echo strtolower($o['status']); ?>"><?php echo htmlspecialchars($o['status']); ?></td>
                                <td>RM <?php echo number_format($o['total'], 2); ?></td>
                                <td class="orders-actions">
                                    <a href="order_record.php?orderID=<?php echo urlencode($o['orderID']); ?>" class="btn">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($pagerHtml)): ?>
                    <div style="margin-top: 20px;">
                        <?php echo $pagerHtml; ?>
                    </div>
                <?php endif; ?>
            
            <?php endif; ?>
        </div>

        <?php if (!empty($selectedOrder)): ?>
            <div class="order-detail">
                <h2>Order #<?php echo htmlspecialchars($selectedOrder['orderID']); ?></h2>
                <div class="order-grid">
                    <div>
                        <div class="order-row"><span class="label">Order Date:</span> <span class="value"><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($selectedOrder['orderDate']))); ?></span></div>
                        <div class="order-row"><span class="label">User ID:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['userID']); ?></span></div>
                        <div class="order-row"><span class="label">Status:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['status']); ?></span></div>
                        <div class="order-row"><span class="label">Received Date:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['received_date']); ?></span></div>
                        <div class="order-row"><span class="label">Shipping Method:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['shipping_method']); ?></span></div>
                        <div class="order-row"><span class="label">Payment ID:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['payID']); ?></span></div>
                    </div>
                    <div>
                        <div class="order-row"><span class="label">Subtotal:</span> <span class="value">RM <?php echo number_format($selectedOrder['subtotal'], 2); ?></span></div>
                        <div class="order-row"><span class="label">Shipping Fee:</span> <span class="value">RM <?php echo number_format($selectedOrder['shipping_fee'], 2); ?></span></div>
                        <div class="order-row"><span class="label">Discount:</span> <span class="value">RM <?php echo number_format($selectedOrder['discount'], 2); ?></span></div>
                        <div class="order-row"><span class="label">Total:</span> <span class="value">RM <?php echo number_format($selectedOrder['total'], 2); ?></span></div>
                        <div class="order-row"><span class="label">Address ID:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['addressID']); ?></span></div>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <h3>Recipient & Shipping Address</h3>
                        <div class="order-row"><span class="label">Recipient Name:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['recipient_name']); ?></span></div>
                        <div class="order-row"><span class="label">Phone:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['phoneNo']); ?></span></div>
                        <div class="order-row"><span class="label">Unit No:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['unitNo']); ?></span></div>
                        <div class="order-row"><span class="label">Address Line 1:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['address_line_1']); ?></span></div>
                        <div class="order-row"><span class="label">Address Line 2:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['address_line_2']); ?></span></div>
                        <div class="order-row"><span class="label">City:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['city']); ?></span></div>
                        <div class="order-row"><span class="label">Postcode:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['postcode']); ?></span></div>
                        <div class="order-row"><span class="label">State:</span> <span class="value"><?php echo htmlspecialchars($selectedOrder['state']); ?></span></div>
                        <div class="order-row"><span class="label">Notes:</span> <span class="value"><?php echo nl2br(htmlspecialchars($selectedOrder['notes'])); ?></span></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../footer.php'; ?>
    
    <script>
    // Pie Chart for Order Status Distribution
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('statusChart').getContext('2d');
        
        const chartData = {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: <?php echo json_encode($chart_colors); ?>,
                borderColor: '#fff',
                borderWidth: 2,
                hoverBorderWidth: 3
            }]
        };

        const statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%', // This creates the ring effect (60% hollow center)
                plugins: {
                    legend: {
                        display: false // We use custom legend
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} orders (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });
    });
    </script>
</body>
</html>
