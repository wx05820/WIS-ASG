<?php
require_once '../_base.php';

// Set execution time limit for long-running script
set_time_limit(300); // 5 minutes

// Check if user is admin
$isWebRequest = !empty($_SERVER['HTTP_HOST']);
if ($isWebRequest) {
    if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
        die("Access denied. Admin privileges required.");
    }
}

// Get threshold from parameter or use default
$threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 5;
$threshold = max(1, min(100, $threshold)); // Ensure reasonable range

// Check if email should be sent (force send when accessing from header)
$forceEmail = isset($_GET['send_email']) || !isset($_GET['threshold']);

// Run stock monitoring
$report = runStockMonitoring($threshold, $forceEmail);

// If this is a web request, show results
if ($isWebRequest) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Stock Monitoring Report - AiKUN Furniture</title>
        <link rel="stylesheet" href="../style.css">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../css/userlist.css">
        <link rel="stylesheet" href="../css/products.css">
    </head>
    <body class="product-list-main" style="margin-top:0; padding-top:0;">
        <?php include 'adminheader.php'; ?>
        
        <div class="container">
            <!-- Email Success Message -->
            <?php if (isset($_GET['send_email']) && $report['email_sent']): ?>
            <div class="email-success-message">
                <div class="success-alert">
                    <i class="fas fa-check-circle"></i>
                    <strong>Email Sent Successfully!</strong>
                    <p>Stock monitoring report has been sent to your email address.</p>
                </div>
            </div>
            <?php elseif (isset($_GET['send_email']) && !$report['email_sent']): ?>
            <div class="email-error-message">
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Email Failed to Send</strong>
                    <p>There was an issue sending the email. Please check your email configuration or try again later.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="report-header">
                <h1><i class="fas fa-chart-line"></i> Stock Monitoring Report</h1>
                <p>Generated on <?php echo $report['timestamp']; ?> | Threshold: <?php echo $report['threshold']; ?> items</p>
            </div>
            
            <!-- Summary Section -->
            <div class="report-section">
                <h2><i class="fas fa-info-circle"></i> Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card out-of-stock">
                        <h3><?php echo $report['out_of_stock_count']; ?></h3>
                        <p>Out of Stock</p>
                    </div>
                    <div class="summary-card low-stock">
                        <h3><?php echo $report['low_stock_count']; ?></h3>
                        <p>Low Stock</p>
                    </div>
                    <div class="summary-card email-status <?php echo $report['email_sent'] ? 'sent' : 'not-sent'; ?>">
                        <h3><?php echo $report['email_sent'] ? 'YES' : 'NO'; ?></h3>
                        <p>Email Alert Sent</p>
                    </div>
                </div>
            </div>
            
            <!-- Out of Stock Products -->
            <?php if (!empty($report['products']['out_of_stock'])): ?>
            <div class="report-section alert-critical">
                <h2><i class="fas fa-times-circle"></i> Out of Stock Products (<?php echo count($report['products']['out_of_stock']); ?>)</h2>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price (RM)</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['products']['out_of_stock'] as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['prodID']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo number_format($product['price'], 2); ?></td>
                            <td><span class="stock out-of-stock">0</span></td>
                            <td>
                                <a href="../product/updateproduct.php?prodID=<?php echo urlencode($product['prodID']); ?>" class="btn">Edit Product</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Low Stock Products -->
            <?php if (!empty($report['products']['low_stock'])): ?>
            <div class="report-section alert-warning">
                <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Products (<?php echo count($report['products']['low_stock']); ?>)</h2>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price (RM)</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['products']['low_stock'] as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['prodID']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo number_format($product['price'], 2); ?></td>
                            <td><span class="stock low-stock"><?php echo $product['qty']; ?></span></td>
                            <td>
                                <a href="../product/updateproduct.php?prodID=<?php echo urlencode($product['prodID']); ?>" class="btn">Edit Product</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- No Issues -->
            <?php if (empty($report['products']['out_of_stock']) && empty($report['products']['low_stock'])): ?>
            <div class="report-section alert-success">
                <h2><i class="fas fa-check-circle"></i> All Products Have Adequate Stock</h2>
                <p>No products found with stock levels below the threshold of <?php echo $report['threshold']; ?> items.</p>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="report-section">
                <h2><i class="fas fa-tools"></i> Actions</h2>
                <a href="stock_monitor.php?send_email=1&threshold=<?php echo $threshold; ?>" class="btn btn-email">
                    <i class="fas fa-envelope"></i> Send Email Alert
                </a>
                <a href="stock_monitor.php?threshold=5" class="btn">Run Check (Threshold: 5)</a>
                <a href="stock_monitor.php?threshold=10" class="btn">Run Check (Threshold: 10)</a>
                <a href="stock_monitor.php?threshold=20" class="btn">Run Check (Threshold: 20)</a>
                <a href="../product/list.php" class="btn btn-secondary">Manage Products</a>
                <a href="adminpage.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
        
        <?php include '../footer.php'; ?>
    </body>
    </html>
    <?php
} else {
    // Command line output
    echo "Stock Monitoring Report - " . $report['timestamp'] . "\n";
    echo str_repeat("=", 50) . "\n";
    echo "Low Stock Products: " . $report['low_stock_count'] . "\n";
    echo "Out of Stock Products: " . $report['out_of_stock_count'] . "\n";
    echo "Email Alert Sent: " . ($report['email_sent'] ? 'YES' : 'NO') . "\n";
    echo "Threshold: " . $report['threshold'] . " items\n";
    echo str_repeat("=", 50) . "\n";
    
    if (!empty($report['products']['out_of_stock'])) {
        echo "\nOUT OF STOCK PRODUCTS:\n";
        foreach ($report['products']['out_of_stock'] as $product) {
            echo "- {$product['prodID']}: {$product['name']} (Stock: 0)\n";
        }
    }
    
    if (!empty($report['products']['low_stock'])) {
        echo "\nLOW STOCK PRODUCTS:\n";
        foreach ($report['products']['low_stock'] as $product) {
            echo "- {$product['prodID']}: {$product['name']} (Stock: {$product['qty']})\n";
        }
    }
    
    if (empty($report['products']['out_of_stock']) && empty($report['products']['low_stock'])) {
        echo "\nAll products have adequate stock levels.\n";
    }
}
?>
