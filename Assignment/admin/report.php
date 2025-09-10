<?php
require_once '../_base.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

$page_title = 'Reports - Admin Dashboard';

// Get date range from URL parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

try {
    // Sales Report - using 'order' table with common column names
    $stmt = $_db->prepare("
        SELECT 
            DATE(orderDate) as order_date,
            COUNT(*) as order_count,
            SUM(total) as total_sales,
            AVG(total) as avg_order_value
        FROM `order` 
        WHERE orderDate BETWEEN ? AND ? + INTERVAL 1 DAY
            AND status NOT IN ('Cancelled', 'Refunded')
        GROUP BY DATE(orderDate)
        ORDER BY order_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Product Performance - try different possible table names
    $product_performance = [];
    $possible_tables = ['order_item', 'orderitem', 'order_detail', 'orderdetail', 'order_items'];
    
    foreach ($possible_tables as $table_name) {
        try {
            $stmt = $_db->prepare("
                SELECT 
                    p.name as prodName,
                    p.price,
                    SUM(oi.qty) as total_sold,
                    SUM(oi.qty * oi.price) as total_revenue,
                    p.qty as current_stock
                FROM `$table_name` oi
                JOIN product p ON oi.prodID = p.prodID
                JOIN `order` o ON oi.orderID = o.orderID
                WHERE o.orderDate BETWEEN ? AND ? + INTERVAL 1 DAY
                    AND o.status NOT IN ('Cancelled', 'Refunded')
                GROUP BY p.prodID, p.name, p.price, p.qty
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $product_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break; // If successful, break out of the loop
        } catch (Exception $e) {
            // Continue to next table name
            continue;
        }
    }
    
    // If no order items table found, create a simplified report from order table only
    if (empty($product_performance)) {
        try {
            $stmt = $_db->prepare("
                SELECT 
                    'No detailed order items data available' as prodName,
                    0 as price,
                    0 as total_sold,
                    0 as total_revenue,
                    0 as current_stock
                LIMIT 1
            ");
            $stmt->execute();
            $product_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $product_performance = [];
        }
    }

    // Customer Analysis
    $stmt = $_db->prepare("
        SELECT 
            u.username,
            u.name,
            u.email,
            COUNT(o.orderID) as order_count,
            SUM(CASE WHEN o.status NOT IN ('Refunded', 'Cancelled') THEN o.total ELSE 0 END) as total_spent,
            MAX(o.orderDate) as last_order
        FROM user u
        LEFT JOIN `order` o ON u.userID = o.userID 
            AND o.orderDate BETWEEN ? AND ? + INTERVAL 1 DAY
        WHERE u.role = 'Customer'
        GROUP BY u.userID, u.username, u.name, u.email
        HAVING order_count > 0
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $customer_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contact Messages Report - simplified version or skip if table doesn't exist
    try {
        $stmt = $_db->prepare("
            SELECT 
                'High' as priority,
                'New' as status,
                COUNT(*) as count
            FROM contact 
            WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY
            GROUP BY priority, status
            ORDER BY priority, status
        ");
        $stmt->execute([$start_date, $end_date]);
        $contact_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // If contact table doesn't exist, create empty array
        $contact_report = [];
    }

    // Summary Statistics
    $stmt = $_db->prepare("
        SELECT 
            COUNT(DISTINCT o.orderID) as total_orders,
            SUM(CASE WHEN o.status NOT IN ('Cancelled', 'Refunded') THEN o.total ELSE 0 END) as total_revenue,
            AVG(CASE WHEN o.status NOT IN ('Cancelled', 'Refunded') THEN o.total ELSE NULL END) as avg_order_value,
            COUNT(DISTINCT o.userID) as unique_customers
        FROM `order` o
        WHERE o.orderDate BETWEEN ? AND ? + INTERVAL 1 DAY
    ");
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC); // Changed to fetch as associative array

} catch (Exception $e) {
    error_log("Report error: " . $e->getMessage());
    $sales_data = $product_performance = $customer_analysis = $contact_report = [];
    $summary = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'unique_customers' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userlist.css">
    <link rel="stylesheet" href="../css/products.css">
</head>

<body class="product-list-main" style="margin-top:0; padding-top:0;">

    <?php include 'adminheader.php'; ?>
    <div class="container">
        <div class="reports-header">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <p>Comprehensive business insights and performance metrics</p>
        </div>

        <!-- Date Filter -->
        <div class="date-filter">
            <form method="GET">
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <a href="report.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Orders</h3>
                <p class="number"><?php echo number_format($summary['total_orders'] ?? 0); ?></p>
            </div>
            <div class="summary-card">
                <h3>Total Revenue</h3>
                <p class="number">RM <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></p>
            </div>
            <div class="summary-card">
                <h3>Average Order Value</h3>
                <p class="number">RM <?php echo number_format($summary['avg_order_value'] ?? 0, 2); ?></p>
            </div>
            <div class="summary-card">
                <h3>Unique Customers</h3>
                <p class="number"><?php echo number_format($summary['unique_customers'] ?? 0); ?></p>
            </div>
        </div>

        <!-- Sales Report -->
        <div class="report-section">
            <h3><i class="fas fa-chart-line"></i> Daily Sales Report</h3>
            <?php if (empty($sales_data)): ?>
                <div class="no-data">No sales data found for the selected period.</div>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Total Sales</th>
                            <th>Average Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $day): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($day['order_date'])); ?></td>
                                <td><?php echo number_format($day['order_count']); ?></td>
                                <td>RM <?php echo number_format($day['total_sales'], 2); ?></td>
                                <td>RM <?php echo number_format($day['avg_order_value'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Product Performance -->
        <div class="report-section">
            <h3><i class="fas fa-box"></i> Top Performing Products</h3>
            <?php if (empty($product_performance)): ?>
                <div class="no-data">No product sales data found for the selected period.</div>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($product_performance as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['prodName']); ?></td>
                                <td>RM <?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo number_format($product['total_sold']); ?></td>
                                <td>RM <?php echo number_format($product['total_revenue'], 2); ?></td>
                                <td><?php echo number_format($product['current_stock']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Customer Analysis -->
        <div class="report-section">
            <h3><i class="fas fa-users"></i> Top Customers</h3>
            <?php if (empty($customer_analysis)): ?>
                <div class="no-data">No customer data found for the selected period.</div>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_analysis as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['name'] ?: $customer['username']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo number_format($customer['order_count']); ?></td>
                                <td>RM <?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($customer['last_order'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Contact Messages Report -->
        <div class="report-section">
            <h3><i class="fas fa-envelope"></i> Contact Messages Summary</h3>
            <?php if (empty($contact_report)): ?>
                <div class="no-data">No contact messages found for the selected period.</div>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contact_report as $report): ?>
                            <tr>
                                <td><span class="priority-<?php echo strtolower($report['priority']); ?>"><?php echo ucfirst($report['priority']); ?></span></td>
                                <td><span class="status-<?php echo strtolower($report['status']); ?>"><?php echo ucfirst($report['status']); ?></span></td>
                                <td><?php echo number_format($report['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
