<?php
require_once '../_base.php';

echo "<h2>Report Functionality Test - Updated Queries</h2>";

$start_date = date('Y-m-01');
$end_date = date('Y-m-d');

// Test each updated query individually
echo "<h3>1. Sales Report Query Test</h3>";
try {
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
        LIMIT 3
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_data = $stmt->fetchAll();
    
    echo "<p style='color:green'>✓ Sales query successful - " . count($sales_data) . " records found</p>";
    if (!empty($sales_data)) {
        echo "<pre>";
        print_r($sales_data[0]);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Sales query error: " . $e->getMessage() . "</p>";
}

echo "<h3>2. Product Performance Query Test</h3>";
try {
    $stmt = $_db->prepare("
        SELECT 
            p.name as prodName,
            p.price,
            SUM(oi.qty) as total_sold,
            SUM(oi.qty * oi.price) as total_revenue,
            p.qty as current_stock
        FROM order_item oi
        JOIN product p ON oi.prodID = p.prodID
        JOIN `order` o ON oi.orderID = o.orderID
        WHERE o.orderDate BETWEEN ? AND ? + INTERVAL 1 DAY
            AND o.status NOT IN ('Cancelled', 'Refunded')
        GROUP BY p.prodID, p.name, p.price, p.qty
        ORDER BY total_sold DESC
        LIMIT 3
    ");
    $stmt->execute([$start_date, $end_date]);
    $product_performance = $stmt->fetchAll();
    
    echo "<p style='color:green'>✓ Product performance query successful - " . count($product_performance) . " records found</p>";
    if (!empty($product_performance)) {
        echo "<pre>";
        print_r($product_performance[0]);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Product performance query error: " . $e->getMessage() . "</p>";
}

echo "<h3>3. Summary Statistics Query Test</h3>";
try {
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
    $summary = $stmt->fetch();
    
    echo "<p style='color:green'>✓ Summary statistics query successful</p>";
    echo "<pre>";
    print_r($summary);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Summary statistics query error: " . $e->getMessage() . "</p>";
}

echo "<h3>4. Customer Analysis Query Test</h3>";
try {
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
        LIMIT 3
    ");
    $stmt->execute([$start_date, $end_date]);
    $customer_analysis = $stmt->fetchAll();
    
    echo "<p style='color:green'>✓ Customer analysis query successful - " . count($customer_analysis) . " records found</p>";
    if (!empty($customer_analysis)) {
        echo "<pre>";
        print_r($customer_analysis[0]);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Customer analysis query error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='report.php'>← Test Updated Report Page</a></p>";
?>
