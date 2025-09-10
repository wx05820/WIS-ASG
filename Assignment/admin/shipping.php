<?php
// Debug: Log that the script is starting
error_log("=== SHIPPING PAGE START ===");

include '../_base.php';
include '../lib/SimplePager.php';

// Debug: Log after includes
error_log("Includes loaded successfully");

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    error_log("User not authorized, redirecting to login");
    redirect('/admin/loginstaff.php');
}


// Determine ID sort order
$id_order = isset($_GET['id_order']) && strtoupper($_GET['id_order']) === 'ASC' ? 'ASC' : 'DESC';

// Get current page
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;


// Handle search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';

// Debug: Log filter parameters
error_log("Filter parameters - Search: '$search', Status: '$status_filter', Date: '$date_filter'");

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
try {
    $pager = new SimplePager($sql, $params, 10, $page);
    $orders = $pager->result;
    error_log("Database query successful, got " . count($orders) . " orders");
} catch (Exception $e) {
    error_log("Database query failed: " . $e->getMessage());
    $orders = [];
}



// Convert result to objects for compatibility with existing code
foreach ($orders as &$order) {
    $order = (object) $order;
}
unset($order);

// Set variables for backward compatibility
$total_orders = $pager->item_count;
$total_pages = $pager->page_count;

// Get order status statistics for pie chart - only shipping-related statuses
$status_stats_sql = "
    SELECT status, COUNT(*) as count 
    FROM `order` 
    WHERE status IN ('Pending', 'Processing', 'Shipped', 'Delivered')
    GROUP BY status 
    ORDER BY count DESC
";
$status_stats_stmt = $_db->prepare($status_stats_sql);
$status_stats_stmt->execute();
$status_stats = $status_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js - Donut Chart
$chart_labels = [];
$chart_data = [];
$chart_colors = [];

$status_color_map = [
    'Pending' => '#FF6384',    // Red/Pink
    'Processing' => '#36A2EB', // Blue  
    'Shipped' => '#FFCE56',    // Yellow
    'Delivered' => '#4BC0C0'   // Teal
];

foreach ($status_stats as $stat) {
    $chart_labels[] = $stat['status'];
    $chart_data[] = (int)$stat['count'];
    $chart_colors[] = $status_color_map[$stat['status']];
}

$page_title = 'Shipping Management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Management - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userlist.css">
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="../css/shipping.css">
    <style>
        /* Shipping page specific styles to match stock monitor */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
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
                <canvas id="statusChart" width="400" height="300"></canvas>
            </div>
            
            <!-- Color Legend -->
            <div class="chart-legend">
                <h4>All Status</h4>
                <div class="legend-grid">
                    <?php 
                    // Use the same color mapping as the chart - only shipping statuses
                    $legend_colors = [
                        'Pending' => ['color' => '#FF6384', 'bg' => '#fff3cd', 'border' => '#ffeaa7'],
                        'Processing' => ['color' => '#36A2EB', 'bg' => '#cce5ff', 'border' => '#74c0fc'],
                        'Shipped' => ['color' => '#FFCE56', 'bg' => '#fff3cd', 'border' => '#ffeaa7'],
                        'Delivered' => ['color' => '#4BC0C0', 'bg' => '#d4edda', 'border' => '#c3e6cb']
                    ];
                    
                    // Show legend items in the same order as the chart data
                    foreach ($status_stats as $stat):
                        $status = $stat['status'];
                        $count = $stat['count'];
                        $colors = $legend_colors[$status];
                    ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: <?php echo $colors['color']; ?>;"></div>
                        <div class="legend-info">
                            <span class="legend-label"><?php echo $status; ?></span>
                            <span class="legend-count"><?php echo $count; ?> orders</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

        <!-- Action Buttons -->
    <div class="product-action-bar" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
        <button type="button" class="order-btn sortby-order" id="multiSelectBtn" title="Multi-select orders" >
         <i class="fas fa-check-square"></i>
        <span class="action-btn-label">Multi-Select</span>
        </button>
        <button type="button" class="order-btn sortby-order" id="bulkStatusBtn" title="Change selected orders status" style="display: none; background-color: #007bff; color: white;">
            <i class="fas fa-edit"></i>
            <span class="action-btn-label">Bulk Status</span>
        </button>
        <?php
        $toggle_order = $id_order === 'ASC' ? 'DESC' : 'ASC';
        $toggle_label = $id_order === 'ASC' ? 'ID Desc' : 'ID Asc';
        $toggle_icon = $id_order === 'ASC' ? 'fa-sort-numeric-down' : 'fa-sort-numeric-up';
        ?>
        <button type="button" class="restore-btn sortby-restore" onclick="handleSortToggle('<?php echo $toggle_order; ?>')">
            <i class="fas <?php echo $toggle_icon; ?>"></i>
            <span class="action-btn-label"><?php echo $toggle_label; ?></span>
        </button>
        <button type="button" class="restore-btn sortby-restore" onclick="window.location.href='shipping.php'">
            <i class="fas fa-refresh"></i>
            <span class="action-btn-label">Refresh</span>
        </button>
    </div>

    <!-- Orders List -->
    <div class="orders-section">
        <div class="section-header">
            <h2><i class="fas fa-list"></i> Orders List</h2>
            <div class="section-actions">
                <span class="total-count">Showing <span id="showing-start">1</span>-<span id="showing-end"><?php echo min(10, $total_orders); ?></span> of <span id="total-orders"><?php echo $total_orders; ?></span> orders</span>
            </div>
        </div>
    <!-- Filters and Search Section -->
    <div class="filters-section">
        <div class="filters-container">
            <!-- Search Bar -->
            <div class="search-filter">
                <form method="GET" id="searchForm" style="display: flex; gap: 10px; align-items: center;">
                    <div style="position:relative; display:inline-block;">
                        <input type="text" 
                               name="search"
                               id="searchInput"
                               placeholder="Search by Order ID or User ID..." 
                               class="search-input"
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="width: 350px; max-width: 100%; padding: 0.5rem 1rem; font-size: 1.1rem; padding-right:2.4rem;">
                        <button type="button" id="clearSearchBtn" title="Clear search"
                                style="position:absolute; right:10px; top:50%; transform:translateY(-50%); border:none; background:transparent; font-size:1.2rem; cursor:pointer; color:#888; display:none;">
                            &times;
                        </button>
                    </div>

                    <button type="submit" class="search-btn" id="searchBtn" style="padding: 0.5rem 1rem; font-size: 1.1rem;">
                        <i class="fas fa-search"></i>
                    </button>
                    
                    <select id="statusFilter" class="filter-select" style="width: 180px; padding: 0.5rem 0.7rem; font-size: 1rem;">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                    </select>
                    
                    <select id="dateFilter" class="filter-select sortby-select">
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                    
                    <input type="hidden" name="id_order" value="<?php echo $id_order; ?>">
                    <input type="hidden" name="page" value="1">
                </form>
            </div>

            <!-- Filter Options -->
            <div class="sort-filter" style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="order-btn sortby-order" id="clearFiltersBtn" title="Clear filters">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>



        <!-- No Data Message (hidden by default) -->
        <div class="no-data" id="noDataMessage" style="display: none;">
            <i class="fas fa-inbox"></i>
            <p>No record found</p>
        </div>

        <?php if (empty($orders)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('noDataMessage').style.display = 'block';
                });
            </script>
        <?php else: ?>
            <div class="orders-table-container">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card" 
                         data-orderid="<?php echo $order->orderID; ?>"
                         data-userid="<?php echo $order->userID; ?>" 
                         data-recipient="<?php echo strtolower($order->recipient_name); ?>"
                         data-phone="<?php echo $order->phoneNo; ?>"
                         data-status="<?php echo $order->status; ?>"
                         data-date="<?php echo date('Y-m-d', strtotime($order->orderDate)); ?>">
                        
                        <!-- Multi-select checkbox (hidden by default) -->
                        <div class="order-checkbox" style="display: none;">
                            <input type="checkbox" class="order-select-checkbox" value="<?php echo $order->orderID; ?>" id="order_<?php echo $order->orderID; ?>">
                            <label for="order_<?php echo $order->orderID; ?>"></label>
                        </div>
                        
                        <!-- Left Column -->
                        <div class="order-left-column">
                            <div class="order-id-large"><?php echo $order->orderID; ?></div>
                            <div class="order-user-id">User ID: <?php echo $order->userID; ?></div>
                            <div class="order-phone">Phone: <?php echo $order->phoneNo; ?></div>
                            <div class="order-total">
                                <span class="total-label">Total:</span>
                                <span class="total-amount">RM <?php echo number_format($order->total, 2); ?></span>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="order-right-column">
                        <form class="status-form" method="POST" action="update_status.php">
                            <input type="hidden" name="order_id" value="<?php echo $order->orderID; ?>">
                            <select name="new_status" class="status-select status-<?php echo strtolower($order->status); ?>">
                                <option value="Pending" <?php echo $order->status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo $order->status === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Shipped" <?php echo $order->status === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="Delivered" <?php echo $order->status === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            </select>
                        </form>
                            <div class="order-shipping-method">
                                <span class="shipping-label">Shipping Method:</span>
                                <span class="shipping-value"><?php echo $order->shipping_method; ?></span>
                            </div>
                            <div class="order-date">
                                <span class="date-label">Order Date:</span>
                                <span class="date-value"><?php echo date('M j, Y', strtotime($order->orderDate)); ?></span>
                            </div>
                            <div class="order-address">
                                <span class="address-label">Address:</span>
                                <span class="address-value">
                                    <?php 
                                    $address_parts = array_filter([
                                        $order->city,
                                        $order->state
                                    ]);
                                    echo htmlspecialchars(implode(', ', $address_parts));
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="page-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="page-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Status Change Modal -->
<div id="bulkStatusModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change Status for Selected Orders</h3>
            <span class="close" id="closeModal">&times;</span>
        </div>
        <div class="modal-body">
            <p>You have selected <span id="selectedCount">0</span> order(s).</p>
            <form id="bulkStatusForm" method="POST" action="bulk_update_status.php">
                <div class="form-group">
                    <label for="bulkStatus">New Status:</label>
                    <select name="bulk_status" id="bulkStatus" class="form-control" required>
                        <option value="">Select Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Delivered">Delivered</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" id="cancelBulk">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Admin JavaScript -->
<script src="../js/admin.js"></script>

<script>
// Enhanced Donut Chart for Order Status Distribution
const chartData = {
    labels: <?php echo json_encode($chart_labels); ?>,
    values: <?php echo json_encode($chart_data); ?>,
    colors: <?php echo json_encode($chart_colors); ?>
};

window.statusChart = AdminJS.createDonutChart('statusChart', chartData);

// Shipping-specific functionality
document.addEventListener('DOMContentLoaded', function() {
    // Real-time filtering with AJAX
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const ordersContainer = document.querySelector('.orders-table-container');
    const noDataMessage = document.getElementById('noDataMessage');
    const showingStart = document.getElementById('showing-start');
    const showingEnd = document.getElementById('showing-end');
    const totalOrders = document.getElementById('total-orders');
    
    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Fetch filtered data from server
    function fetchFilteredData() {
        const search = searchInput ? searchInput.value.trim() : '';
        const status = statusFilter ? statusFilter.value : 'all';
        const date = dateFilter ? dateFilter.value : 'all';
        const idOrder = '<?php echo $id_order; ?>';
        
        // Show loading state
        if (ordersContainer) {
            ordersContainer.innerHTML = '<div class="loading">Loading orders...</div>';
        }
        
        // Make AJAX request
        fetch(`ajax_shipping_filter.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&date=${encodeURIComponent(date)}&id_order=${idOrder}&page=1`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                // Update orders display
                updateOrdersDisplay(data.orders);
                
                // Update counters
                if (showingStart) showingStart.textContent = '1';
                if (showingEnd) showingEnd.textContent = Math.min(10, data.total_orders);
                if (totalOrders) totalOrders.textContent = data.total_orders;
                
                // Update chart if needed
                if (data.status_stats) {
                    updateChart(data.status_stats);
                }
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                if (ordersContainer) {
                    ordersContainer.innerHTML = '<div class="error">Error loading orders. Please try again.</div>';
                }
            });
    }
    
    // Update orders display
    function updateOrdersDisplay(orders) {
        if (!ordersContainer) return;
        
        if (orders.length === 0) {
            if (noDataMessage) noDataMessage.style.display = 'block';
            ordersContainer.innerHTML = '';
        } else {
            if (noDataMessage) noDataMessage.style.display = 'none';
            ordersContainer.innerHTML = generateOrdersHTML(orders);
        }
    }
    
    // Generate orders HTML
    function generateOrdersHTML(orders) {
        let html = '';
        orders.forEach(order => {
            html += `
                <div class="order-card" 
                     data-orderid="${order.orderID}"
                     data-userid="${order.userID}" 
                     data-recipient="${order.recipient_name.toLowerCase()}"
                     data-phone="${order.phoneNo}"
                     data-status="${order.status}"
                     data-date="${order.orderDate.split(' ')[0]}">
                    
                    <!-- Multi-select checkbox (hidden by default) -->
                    <div class="order-checkbox" style="display: none;">
                        <input type="checkbox" class="order-select-checkbox" value="${order.orderID}" id="order_${order.orderID}">
                        <label for="order_${order.orderID}"></label>
                    </div>
                    
                    <!-- Left Column -->
                    <div class="order-left-column">
                        <div class="order-id-large">${order.orderID}</div>
                        <div class="order-user-id">User ID: ${order.userID}</div>
                        <div class="order-phone">Phone: ${order.phoneNo}</div>
                        <div class="order-total">
                            <span class="total-label">Total:</span>
                            <span class="total-amount">RM ${parseFloat(order.total).toFixed(2)}</span>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="order-right-column">
                        <form class="status-form" method="POST" action="update_status.php">
                            <input type="hidden" name="order_id" value="${order.orderID}">
                            <select name="new_status" class="status-select status-${order.status.toLowerCase()}">
                                <option value="Pending" ${order.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Processing" ${order.status === 'Processing' ? 'selected' : ''}>Processing</option>
                                <option value="Shipped" ${order.status === 'Shipped' ? 'selected' : ''}>Shipped</option>
                                <option value="Delivered" ${order.status === 'Delivered' ? 'selected' : ''}>Delivered</option>
                            </select>
                        </form>
                        <div class="order-shipping-method">
                            <span class="shipping-label">Shipping Method:</span>
                            <span class="shipping-value">${order.shipping_method}</span>
                        </div>
                        <div class="order-date">
                            <span class="date-label">Order Date:</span>
                            <span class="date-value">${new Date(order.orderDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </div>
                        <div class="order-address">
                            <span class="address-label">Address:</span>
                            <span class="address-value">${order.unitNo} ${order.address_line_1}, ${order.address_line_2}, ${order.city} ${order.postcode}, ${order.state}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        return html;
    }
    
    // Update chart
    function updateChart(statusStats) {
        console.log('updateChart called with:', statusStats);
        
        if (!statusStats || statusStats.length === 0) {
            console.log('No status stats to update chart');
            return;
        }
        
        // Update chart data with correct format for AdminJS
        const chartData = {
            labels: statusStats.map(stat => stat.status),
            values: statusStats.map(stat => stat.count),
            colors: statusStats.map(stat => {
                const colorMap = {
                    'Pending': '#FF6384',    // Red/Pink
                    'Processing': '#36A2EB', // Blue  
                    'Shipped': '#FFCE56',    // Yellow
                    'Delivered': '#4BC0C0'   // Teal
                };
                return colorMap[stat.status] || '#6c757d';
            })
        };
        
        console.log('Chart data prepared:', chartData);
        console.log('window.statusChart exists:', !!window.statusChart);
        console.log('AdminJS exists:', typeof AdminJS !== 'undefined');
        
        // Update the existing chart if it exists
        if (window.statusChart && typeof window.statusChart.update === 'function') {
            console.log('Updating existing chart');
            // Update chart data
            window.statusChart.data.labels = chartData.labels;
            window.statusChart.data.datasets[0].data = chartData.values;
            window.statusChart.data.datasets[0].backgroundColor = chartData.colors;
            window.statusChart.update();
        } else {
            console.log('Creating new chart');
            // Create new chart if it doesn't exist
            if (typeof AdminJS !== 'undefined' && AdminJS.createDonutChart) {
                window.statusChart = AdminJS.createDonutChart('statusChart', chartData);
            } else {
                console.error('AdminJS or createDonutChart not available');
            }
        }
        
        // Update the status legend
        updateStatusLegend(statusStats);
        
        console.log('Chart update completed');
    }
    
    // Update status legend
    function updateStatusLegend(statusStats) {
        const legendContainer = document.querySelector('.chart-legend .legend-grid');
        if (!legendContainer) {
            console.log('Legend container not found');
            return;
        }
        
        // Clear existing legend items
        legendContainer.innerHTML = '';
        
        // Create legend items for each status
        statusStats.forEach(stat => {
            const colorMap = {
                'Pending': '#FF6384',    // Red/Pink
                'Processing': '#36A2EB', // Blue  
                'Shipped': '#FFCE56',    // Yellow
                'Delivered': '#4BC0C0'   // Teal
            };
            
            const color = colorMap[stat.status] || '#6c757d';
            
            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';
            legendItem.style.cssText = `
                display: flex;
                align-items: center;
                margin-bottom: 8px;
                font-size: 14px;
            `;
            
            legendItem.innerHTML = `
                <div class="legend-color" style="
                    width: 16px;
                    height: 16px;
                    background-color: ${color};
                    border-radius: 50%;
                    margin-right: 8px;
                    border: 2px solid #fff;
                    box-shadow: 0 0 0 1px rgba(0,0,0,0.1);
                "></div>
                <div class="legend-info">
                    <span class="legend-label">${stat.status}</span>
                    <span class="legend-count">${stat.count} orders</span>
                </div>
            `;
            
            legendContainer.appendChild(legendItem);
        });
        
        console.log('Legend updated with stats:', statusStats);
    }
    
    // Set up event listeners
    if (searchInput) {
        searchInput.addEventListener('input', debounce(fetchFilteredData, 300));
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', fetchFilteredData);
    }
    
    if (dateFilter) {
        dateFilter.addEventListener('change', fetchFilteredData);
    }
    
    // Handle status form submission with AJAX
    function handleStatusFormSubmission(form) {
        const formData = new FormData(form);
        const orderId = formData.get('order_id');
        const newStatus = formData.get('new_status');
        const statusSelect = form.querySelector('.status-select');
        
        // Show loading state
        const originalValue = statusSelect.value;
        statusSelect.disabled = true;
        statusSelect.style.opacity = '0.6';
        
        // Make AJAX request
        fetch('update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('success') || data.includes('Status updated')) {
                // Update the status select to show new status
                statusSelect.value = newStatus;
                statusSelect.className = `status-select status-${newStatus.toLowerCase()}`;
                
                // Show success feedback
                showStatusUpdateFeedback(orderId, newStatus, true);
                
                // Refresh the filtered data to get updated order list and chart
                fetchFilteredData();
            } else {
                // Show error feedback
                showStatusUpdateFeedback(orderId, originalValue, false);
                statusSelect.value = originalValue;
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            showStatusUpdateFeedback(orderId, originalValue, false);
            statusSelect.value = originalValue;
        })
        .finally(() => {
            statusSelect.disabled = false;
            statusSelect.style.opacity = '1';
        });
    }
    
    // Show status update feedback
    function showStatusUpdateFeedback(orderId, status, success) {
        const orderCard = document.querySelector(`[data-orderid="${orderId}"]`);
        if (orderCard) {
            const feedback = document.createElement('div');
            feedback.className = `status-feedback ${success ? 'success' : 'error'}`;
            feedback.textContent = success ? `Status updated to ${status}` : `Failed to update status`;
            feedback.style.cssText = `
                position: absolute;
                top: 10px;
                right: 10px;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                z-index: 1000;
                ${success ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'}
            `;
            
            orderCard.style.position = 'relative';
            orderCard.appendChild(feedback);
            
            // Remove feedback after 3 seconds
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.parentNode.removeChild(feedback);
                }
            }, 3000);
        }
    }
    
    // Set up event delegation for status forms (including dynamically generated ones)
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('status-select')) {
            e.preventDefault();
            const form = e.target.closest('.status-form');
            if (form) {
                handleStatusFormSubmission(form);
            }
        }
    });
    
    // Legacy form submission handling (fallback)
    const statusForms = document.querySelectorAll('.status-form');
    statusForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const select = this.querySelector('.status-select');
            const originalValue = select.dataset.originalValue || select.value;
            
            // Show loading state
            select.style.opacity = '0.6';
            select.disabled = true;
            
            // Add loading text
            const loadingText = document.createElement('span');
            loadingText.textContent = 'Updating...';
            loadingText.style.fontSize = '0.6rem';
            loadingText.style.color = '#666';
            this.appendChild(loadingText);
        });
    });
    
    // Multi-select functionality
    let multiSelectMode = false;
    const multiSelectBtn = document.getElementById('multiSelectBtn');
    const bulkStatusBtn = document.getElementById('bulkStatusBtn');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const orderSelectCheckboxes = document.querySelectorAll('.order-select-checkbox');
    const bulkStatusModal = document.getElementById('bulkStatusModal');
    const selectedCountSpan = document.getElementById('selectedCount');
    const closeModal = document.getElementById('closeModal');
    const cancelBulk = document.getElementById('cancelBulk');
    
    // Toggle multi-select mode
    if (multiSelectBtn) {
        multiSelectBtn.addEventListener('click', function() {
            multiSelectMode = !multiSelectMode;
            
            if (multiSelectMode) {
                // Show checkboxes
                orderCheckboxes.forEach(checkbox => {
                    checkbox.style.display = 'block';
                });
                this.innerHTML = '<i class="fas fa-times"></i><span class="action-btn-label">Exit Multi-Select</span>';
                this.style.backgroundColor = '#dc3545';
            } else {
                // Hide checkboxes and clear selections
                orderCheckboxes.forEach(checkbox => {
                    checkbox.style.display = 'none';
                });
                orderSelectCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                if (bulkStatusBtn) bulkStatusBtn.style.display = 'none';
                this.innerHTML = '<i class="fas fa-check-square"></i><span class="action-btn-label">Multi-Select</span>';
                this.style.backgroundColor = '';
            }
        });
    }
    
    // Handle checkbox changes
    orderSelectCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkStatusButton();
        });
    });
    
    // Update bulk status button visibility
    function updateBulkStatusButton() {
        const checkedBoxes = document.querySelectorAll('.order-select-checkbox:checked');
        if (checkedBoxes.length > 0 && bulkStatusBtn) {
            bulkStatusBtn.style.display = 'inline-flex';
            if (selectedCountSpan) selectedCountSpan.textContent = checkedBoxes.length;
        } else if (bulkStatusBtn) {
            bulkStatusBtn.style.display = 'none';
        }
    }
    
    // Show bulk status modal
    if (bulkStatusBtn) {
        bulkStatusBtn.addEventListener('click', function() {
            if (bulkStatusModal) bulkStatusModal.style.display = 'block';
        });
    }
    
    // Close modal
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            if (bulkStatusModal) bulkStatusModal.style.display = 'none';
        });
    }
    
    if (cancelBulk) {
        cancelBulk.addEventListener('click', function() {
            if (bulkStatusModal) bulkStatusModal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === bulkStatusModal) {
            bulkStatusModal.style.display = 'none';
        }
    });
    
    // Handle bulk status form submission
    const bulkStatusForm = document.getElementById('bulkStatusForm');
    if (bulkStatusForm) {
        bulkStatusForm.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.order-select-checkbox:checked');
            const selectedOrderIds = Array.from(checkedBoxes).map(cb => cb.value);
            
            // Add hidden inputs for selected order IDs using $_bulk[] array
            selectedOrderIds.forEach(orderId => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = '_bulk[]';
                hiddenInput.value = orderId;
                this.appendChild(hiddenInput);
            });
        });
    }
});
</script>

</body>
</html>

<?php include '../footer.php'; ?>
