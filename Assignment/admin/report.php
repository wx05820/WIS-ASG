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
    // Sales Report
    $stmt = $_db->prepare("
        SELECT 
            DATE(created_at) as order_date,
            COUNT(*) as order_count,
            SUM(total_amount) as total_sales,
            AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        GROUP BY DATE(created_at)
        ORDER BY order_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_data = $stmt->fetchAll();

    // Product Performance
    $stmt = $_db->prepare("
        SELECT 
            p.prodName,
            p.price,
            SUM(oi.qty) as total_sold,
            SUM(oi.qty * oi.price) as total_revenue,
            p.qty as current_stock
        FROM order_items oi
        JOIN product p ON oi.prodID = p.prodID
        JOIN orders o ON oi.orderID = o.orderID
        WHERE o.created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        GROUP BY p.prodID, p.prodName, p.price, p.qty
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $product_performance = $stmt->fetchAll();

    // Customer Analysis
    $stmt = $_db->prepare("
        SELECT 
            u.username,
            u.name,
            u.email,
            COUNT(o.orderID) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order
        FROM user u
        LEFT JOIN orders o ON u.userID = o.userID 
            AND o.created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        WHERE u.role = 'Customer'
        GROUP BY u.userID, u.username, u.name, u.email
        HAVING order_count > 0
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $customer_analysis = $stmt->fetchAll();

    // Contact Messages Report
    $stmt = $_db->prepare("
        SELECT 
            priority,
            status,
            COUNT(*) as count
        FROM contact_messages
        WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        GROUP BY priority, status
        ORDER BY priority, status
    ");
    $stmt->execute([$start_date, $end_date]);
    $contact_report = $stmt->fetchAll();

    // Summary Statistics
    $stmt = $_db->prepare("
        SELECT 
            COUNT(DISTINCT o.orderID) as total_orders,
            SUM(o.total_amount) as total_revenue,
            AVG(o.total_amount) as avg_order_value,
            COUNT(DISTINCT o.userID) as unique_customers
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ? + INTERVAL 1 DAY
    ");
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetch();

} catch (Exception $e) {
    error_log("Report error: " . $e->getMessage());
    $sales_data = $product_performance = $customer_analysis = $contact_report = [];
    $summary = (object)['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'unique_customers' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AiKUN Furniture</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/adminheader.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .reports-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .reports-header {
            margin-bottom: 30px;
        }
        .reports-header h1 {
            color: #8B4513;
            margin-bottom: 10px;
        }
        .date-filter {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .date-filter form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #666;
        }
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 8px 20px;
            background: #8B4513;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #A0522D;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #8B4513;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .summary-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #8B4513;
            margin: 0;
        }
        .report-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .report-section h3 {
            margin: 0 0 20px 0;
            color: #8B4513;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #666;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
        .priority-high { color: #dc3545; font-weight: bold; }
        .priority-medium { color: #ffc107; font-weight: bold; }
        .priority-low { color: #28a745; }
        .status-new { color: #dc3545; font-weight: bold; }
        .status-in_progress { color: #ffc107; font-weight: bold; }
        .status-replied { color: #17a2b8; }
        .status-closed { color: #6c757d; }
        @media (max-width: 768px) {
            .date-filter form {
                flex-direction: column;
                align-items: stretch;
            }
            .table {
                font-size: 14px;
            }
            .table th,
            .table td {
                padding: 8px;
            }
        }
    </style>
</head>

<body>
    <?php include 'adminheader.php'; ?>

    <div class="reports-container">
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
                <a href="report.php" class="btn" style="background: #6c757d;">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Orders</h3>
                <p class="number"><?php echo number_format($summary->total_orders); ?></p>
            </div>
            <div class="summary-card">
                <h3>Total Revenue</h3>
                <p class="number">RM <?php echo number_format($summary->total_revenue, 2); ?></p>
            </div>
            <div class="summary-card">
                <h3>Average Order Value</h3>
                <p class="number">RM <?php echo number_format($summary->avg_order_value, 2); ?></p>
            </div>
            <div class="summary-card">
                <h3>Unique Customers</h3>
                <p class="number"><?php echo number_format($summary->unique_customers); ?></p>
            </div>
        </div>

        <!-- Sales Report -->
        <div class="report-section">
            <h3><i class="fas fa-chart-line"></i> Daily Sales Report</h3>
            <?php if (empty($sales_data)): ?>
                <div class="no-data">No sales data found for the selected period.</div>
            <?php else: ?>
                <table class="table">
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
                <table class="table">
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
                <table class="table">
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
                <table class="table">
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
