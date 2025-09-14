<?php
require_once '../_base.php';
// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

$page_title = 'Admin Dashboard';

// Auto stock monitoring check (only once per session to avoid spam)
if (!isset($_SESSION['stock_check_done']) || $_SESSION['stock_check_done'] !== date('Y-m-d')) {
    try {
        $stockData = checkLowStockProducts(5);
        if (!empty($stockData['low_stock']) || !empty($stockData['out_of_stock'])) {
            $lowCount = count($stockData['low_stock']);
            $outCount = count($stockData['out_of_stock']);
            
            // Send stock alert email (auto-send on admin dashboard access)
            $emailSent = sendLowStockAlert($stockData);
            if ($emailSent) {
                temp('warning', "Stock Alert: $lowCount products are low in stock, $outCount products are out of stock. Alert email has been sent. Check the Stock Monitor for details.");
            } else {
                temp('warning', "Stock Alert: $lowCount products are low in stock, $outCount products are out of stock. Check the Stock Monitor for details.");
            }
        }
        $_SESSION['stock_check_done'] = date('Y-m-d'); // Mark as checked for today
    } catch (Exception $e) {
        error_log("Dashboard stock check failed: " . $e->getMessage());
    }
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
    <link rel="stylesheet" href="../css/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'adminheader.php'; ?>

    <div class="admin-dashboard">
        <!-- Notification Messages -->
        <?php if ($success_msg = get_temp('success')): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_msg = get_temp('info')): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($info_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($warning_msg = get_temp('warning')): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($warning_msg); ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Welcome to AiKUN Furniture Admin Panel</p>
        </div>

        <!-- Stock Status Summary -->
        <?php
        try {
            $quickStockData = checkLowStockProducts(5);
            $lowStockCount = count($quickStockData['low_stock']);
            $outOfStockCount = count($quickStockData['out_of_stock']);
        } catch (Exception $e) {
            $lowStockCount = 0;
            $outOfStockCount = 0;
        }
        ?>
        <div class="stock-summary">
            <div class="stock-card status-active">
                <i class="fas fa-check-circle"></i>
                <h3>Stock Status</h3>
                <p>System Active</p>
            </div>
            
            <?php if ($lowStockCount > 0): ?>
            <div class="stock-card low-stock">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Low Stock</h3>
                <p><?php echo $lowStockCount; ?> Products</p>
                <a href="stock_monitor.php">View Details</a>
            </div>
            <?php endif; ?>
            
            <?php if ($outOfStockCount > 0): ?>
            <div class="stock-card out-of-stock">
                <i class="fas fa-times-circle"></i>
                <h3>Out of Stock</h3>
                <p><?php echo $outOfStockCount; ?> Products</p>
                <a href="stock_monitor.php">Restock Now</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="../product/list.php" class="action-btn">
                <i class="fas fa-box"></i>
                <span>Manage Products</span>
            </a>
            <a href="usermanage/list.php" class="action-btn">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="contact_messages.php" class="action-btn">
                <i class="fas fa-envelope"></i>
                <span>Contact Messages</span>
            </a>
            <a href="report.php" class="action-btn">
                <i class="fas fa-chart-bar"></i>
                <span>View Reports</span>
            </a>
            <a href="../product/addproduct.php" class="action-btn">
                <i class="fas fa-plus"></i>
                <span>Add Product</span>
            </a>
            <?php if (!isStaffSuperAdmin()):?>
            <a href="usermanage/adduser.php" class="action-btn">
                <i class="fas fa-user-plus"></i>
                <span>Add Staff</span>
            </a>
            <?php endif; ?>
            <a href="voucher_list.php" class="action-btn">
            <i class="fas fa-ticket"></i>
            <span>Voucher</span>
            </a>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>