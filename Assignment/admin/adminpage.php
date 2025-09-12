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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-dashboard {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: linear-gradient(135deg, #D2B48C, #DEB887, #F5DEB3);
            color: #654321;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .dashboard-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        .dashboard-header p {
            margin: 0;
            font-size: 1.2em;
            opacity: 0.9;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .action-btn {
            display: block;
            padding: 20px;
            background: linear-gradient(135deg, #FAF0E6, #FDF5E6);
            color: #8B4513;
            text-decoration: none;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid #D2B48C;
        }
        .action-btn:hover {
            background: linear-gradient(135deg, #DEB887, #D2B48C);
            color: #654321;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(210, 180, 140, 0.4);
            border-color: #CD853F;
        }
        .action-btn i {
            display: block;
            font-size: 2em;
            margin-bottom: 10px;
        }
        .action-btn span {
            font-size: 1.1em;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php include 'adminheader.php'; ?>

    <div class="admin-dashboard">
        <!-- Notification Messages -->
        <?php if ($success_msg = get_temp('success')): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_msg = get_temp('info')): ?>
            <div class="alert alert-info" style="margin-bottom: 20px; padding: 15px; background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 8px;">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($info_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($warning_msg = get_temp('warning')): ?>
            <div class="alert alert-warning" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; border-radius: 8px;">
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
        <div class="stock-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div class="stock-card" style="background: linear-gradient(135deg, #e8f5e8, #d4edda); padding: 20px; border-radius: 10px; text-align: center; border: 2px solid #c3e6cb;">
                <i class="fas fa-check-circle" style="font-size: 2em; color: #28a745; margin-bottom: 10px;"></i>
                <h3 style="margin: 0; color: #155724;">Stock Status</h3>
                <p style="margin: 5px 0; color: #155724;">System Active</p>
            </div>
            
            <?php if ($lowStockCount > 0): ?>
            <div class="stock-card" style="background: linear-gradient(135deg, #fff3cd, #ffeaa7); padding: 20px; border-radius: 10px; text-align: center; border: 2px solid #ffeaa7;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2em; color: #856404; margin-bottom: 10px;"></i>
                <h3 style="margin: 0; color: #856404;">Low Stock</h3>
                <p style="margin: 5px 0; color: #856404;"><?php echo $lowStockCount; ?> Products</p>
                <a href="stock_monitor.php" style="color: #856404; text-decoration: underline; font-size: 0.9em;">View Details</a>
            </div>
            <?php endif; ?>
            
            <?php if ($outOfStockCount > 0): ?>
            <div class="stock-card" style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); padding: 20px; border-radius: 10px; text-align: center; border: 2px solid #f5c6cb;">
                <i class="fas fa-times-circle" style="font-size: 2em; color: #721c24; margin-bottom: 10px;"></i>
                <h3 style="margin: 0; color: #721c24;">Out of Stock</h3>
                <p style="margin: 5px 0; color: #721c24;"><?php echo $outOfStockCount; ?> Products</p>
                <a href="stock_monitor.php" style="color: #721c24; text-decoration: underline; font-size: 0.9em;">Restock Now</a>
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
            <a href="shipping.php" class="action-btn">
            <i class="fas fa-truck"></i>
            <span>Shipping</span>
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
            <a href="usermanage/adduser.php" class="action-btn">
                <i class="fas fa-user-plus"></i>
                <span>Add Staff</span>
            </a>
            <a href="voucher_list.php" class="action-btn">
            <i class="fas fa-ticket"></i>
            <span>Voucher</span>
            </a>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>