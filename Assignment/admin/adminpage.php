<?php
require_once '../_base.php';
// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

$page_title = 'Admin Dashboard';
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
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Welcome to AiKUN Furniture Admin Panel</p>
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
            <a href="addvoucher.php" class="action-btn">
            <i class="fas fa-ticket"></i>
            <span>Add Voucher</span>
            </a>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>