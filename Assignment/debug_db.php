<?php
require_once '_base.php';

echo "<h2>Database Debug Information</h2>";

try {
    echo "<p><strong>Database connection:</strong> " . (isset($_db) ? 'OK' : 'FAILED') . "</p>";
    
    if (isset($_db)) {
        // Check total products
        $stmt = $_db->prepare("SELECT COUNT(*) as total FROM product");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Total products in database:</strong> " . $result['total'] . "</p>";
        
        // Check active products
        $stmt2 = $_db->prepare("SELECT COUNT(*) as active FROM product WHERE status != 'removed'");
        $stmt2->execute();
        $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Active products:</strong> " . $result2['active'] . "</p>";
        
        // Check low stock products
        $stmt3 = $_db->prepare("SELECT COUNT(*) as low_stock FROM product WHERE qty <= 5 AND status != 'removed'");
        $stmt3->execute();
        $result3 = $stmt3->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Products with stock <= 5:</strong> " . $result3['low_stock'] . "</p>";
        
        // Get sample products
        $stmt4 = $_db->prepare("SELECT prodID, name, qty, status FROM product LIMIT 10");
        $stmt4->execute();
        $products = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>All Products in Database:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Product ID</th><th>Name</th><th>Quantity</th><th>Status</th></tr>";
        foreach ($products as $product) {
            $style = $product['qty'] == 0 ? "background-color: #ffcccc;" : "";
            echo "<tr style='$style'>";
            echo "<td>" . htmlspecialchars($product['prodID']) . "</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . $product['qty'] . "</td>";
            echo "<td>" . htmlspecialchars($product['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check specifically for zero quantity products
        $stmt5 = $_db->prepare("SELECT prodID, name, qty, status FROM product WHERE qty = 0");
        $stmt5->execute();
        $zeroProducts = $stmt5->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Products with 0 Quantity (Total: " . count($zeroProducts) . "):</h3>";
        if (count($zeroProducts) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Product ID</th><th>Name</th><th>Quantity</th><th>Status</th></tr>";
            foreach ($zeroProducts as $product) {
                $style = $product['status'] == 'removed' ? "background-color: #ffdddd;" : "background-color: #ffffcc;";
                echo "<tr style='$style'>";
                echo "<td>" . htmlspecialchars($product['prodID']) . "</td>";
                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                echo "<td>" . $product['qty'] . "</td>";
                echo "<td>" . htmlspecialchars($product['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p><em>Yellow background = Active, Red background = Removed</em></p>";
        } else {
            echo "<p>No products found with 0 quantity.</p>";
        }
        
        // Test the exact query used by the stock monitor
        echo "<h3>Stock Monitor Query Test:</h3>";
        $stmt6 = $_db->prepare("SELECT prodID, name, qty, price, category FROM product WHERE qty = 0 AND status != 'removed' ORDER BY name ASC");
        $stmt6->execute();
        $stockMonitorResults = $stmt6->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Out of stock query result:</strong> " . count($stockMonitorResults) . " products</p>";
        if (count($stockMonitorResults) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Product ID</th><th>Name</th><th>Quantity</th><th>Price</th><th>Category</th></tr>";
            foreach ($stockMonitorResults as $product) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($product['prodID']) . "</td>";
                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                echo "<td>" . $product['qty'] . "</td>";
                echo "<td>" . $product['price'] . "</td>";
                echo "<td>" . htmlspecialchars($product['category']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Test the runStockMonitoring function
        echo "<h3>Stock Monitoring Function Test:</h3>";
        if (function_exists('runStockMonitoring')) {
            echo "<p>Function exists: YES</p>";
            $report = runStockMonitoring(5);
            echo "<pre>";
            print_r($report);
            echo "</pre>";
        } else {
            echo "<p>Function exists: NO</p>";
        }
    }
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
