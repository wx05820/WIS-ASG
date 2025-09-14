
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

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Check if email should be sent (only when explicitly requested)
$forceEmail = isset($_GET['send_email']) && $_GET['send_email'] == '1';

// Run stock monitoring with pagination
$report = runStockMonitoringWithPagination($threshold, $forceEmail, $limit, $offset);

// Calculate total pages
$totalLowStock = isset($report['total_low_stock']) ? $report['total_low_stock'] : 0;
$totalOutOfStock = isset($report['total_out_of_stock']) ? $report['total_out_of_stock'] : 0;
$totalPages = max(1, ceil(max($totalLowStock, $totalOutOfStock) / $limit));

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
    </head>
    <body class="product-list-main" style="margin-top:0; padding-top:0;">
        <?php include 'adminheader.php'; ?>
        
        <div class="container stock-monitor-container">
            <!-- Email Success Message -->
            <?php if (isset($_GET['send_email'])): ?>
                <?php 
                $totalIssues = (isset($report['total_out_of_stock']) ? $report['total_out_of_stock'] : $report['out_of_stock_count']) + 
                              (isset($report['total_low_stock']) ? $report['total_low_stock'] : $report['low_stock_count']);
                ?>
                <?php if ($totalIssues == 0): ?>
                <div class="email-info-message">
                    <div class="success-alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>No Stock Issues Found</strong>
                        <p>All products have adequate stock levels. No email alert was sent as there are no stock issues to report.</p>
                    </div>
                </div>
                <?php elseif ($report['email_sent']): ?>
                <div class="email-success-message">
                    <div class="success-alert">
                        <i class="fas fa-check-circle"></i>
                        <strong>Stock Alert Email Sent Successfully!</strong>
                        <p>Stock monitoring report with <?php echo $totalIssues; ?> stock issues has been sent to admin email addresses.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="email-error-message">
                    <div class="error-alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Stock Alert Email Failed</strong>
                        <p>There was an issue sending the stock alert email. Please check your email configuration or try again later.</p>
                    </div>
                </div>
                <?php endif; ?>
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
                        <h3><?php echo isset($report['total_out_of_stock']) ? $report['total_out_of_stock'] : $report['out_of_stock_count']; ?></h3>
                        <p>Out of Stock (Total)</p>
                    </div>
                    <div class="summary-card low-stock">
                        <h3><?php echo isset($report['total_low_stock']) ? $report['total_low_stock'] : $report['low_stock_count']; ?></h3>
                        <p>Low Stock (Total)</p>
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
                <h2><i class="fas fa-times-circle"></i> Out of Stock Products 
                (Showing <?php echo count($report['products']['out_of_stock']); ?> of <?php echo isset($report['total_out_of_stock']) ? $report['total_out_of_stock'] : count($report['products']['out_of_stock']); ?>)</h2>
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
                <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Products 
                (Showing <?php echo count($report['products']['low_stock']); ?> of <?php echo isset($report['total_low_stock']) ? $report['total_low_stock'] : count($report['products']['low_stock']); ?>)</h2>
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1 && (!empty($report['products']['out_of_stock']) || !empty($report['products']['low_stock']))): ?>
            <div class="report-section">
                <div class="pagination">
                    <?php
                    // Build base URL for pagination
                    $baseUrl = "stock_monitor.php?threshold=" . $threshold;
                    if (isset($_GET['send_email'])) {
                        $baseUrl .= "&send_email=" . $_GET['send_email'];
                    }
                    
                    // Previous button
                    if ($page > 1): ?>
                        <a href="<?php echo $baseUrl; ?>&page=<?php echo ($page - 1); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <?php
                    // Page numbers
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="<?php echo $baseUrl; ?>&page=1">1</a>
                        <?php if ($startPage > 2): ?>
                            <span>...</span>
                        <?php endif;
                    endif;
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif;
                    endfor;
                    
                    if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span>...</span>
                        <?php endif; ?>
                        <a href="<?php echo $baseUrl; ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                    <?php endif; ?>
                    
                    <!-- Next button -->
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo $baseUrl; ?>&page=<?php echo ($page + 1); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; margin-top: 10px; color: #666;">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                    (<?php echo $limit; ?> items per page)
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="report-section">
                <h2><i class="fas fa-tools"></i> Actions</h2>
                <div class="product-action-bar">
                    <a href="stock_monitor.php?send_email=1&threshold=<?php echo $threshold; ?>&page=<?php echo $page; ?>" class="btn btn-email">
                        <i class="fas fa-envelope"></i> Send Stock Alert Email
                    </a>
                    <a href="stock_monitor.php?threshold=5&page=1" class="btn">Run Check (Threshold: 5)</a>
                    <a href="stock_monitor.php?threshold=10&page=1" class="btn">Run Check (Threshold: 10)</a>
                    <a href="stock_monitor.php?threshold=20&page=1" class="btn">Run Check (Threshold: 20)</a>
                </div>
                <div class="product-action-bar">
                    <a href="../product/list.php" class="btn btn-secondary">Manage Products</a>
                    <a href="adminpage.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
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
