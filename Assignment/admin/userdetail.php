<?php
include '../_base.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

// Display success/error messages
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

$user_id = isset($_GET['userID']) ? trim($_GET['userID']) : '';

if ($user_id === '') {
    echo '<main class="user-detail"><p class="no-results">Invalid user ID.</p></main>';
    include '../footer.php';
    exit;
}

// Get user information with additional details
$sql = "SELECT u.*, 
               COUNT(DISTINCT o.orderID) as total_orders,
               COALESCE(SUM(ci.qty), 0) as cart_items,
               COUNT(DISTINCT w.prodID) as wishlist_items,
               COALESCE(SUM(CASE WHEN o.status IN ('Completed', 'Pending', 'Processing', 'Shipped') THEN o.total ELSE 0 END), 0) as total_spent,
               MAX(o.orderDate) as last_order_date
        FROM user u
        LEFT JOIN `order` o ON u.userID = o.userID
        LEFT JOIN cart c ON u.userID = c.userID
        LEFT JOIN cart_items ci ON c.cartID = ci.cartID
        LEFT JOIN wishlist w ON u.userID = w.userID
        WHERE u.userID = ?
        GROUP BY u.userID";

$stmt = $_db->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Let's also get a simple count of orders and their total
$debug_sql = "SELECT COUNT(*) as order_count, SUM(total) as order_total FROM `order` WHERE userID = ?";
$debug_stmt = $_db->prepare($debug_sql);
$debug_stmt->execute([$user_id]);
$debug_data = $debug_stmt->fetch(PDO::FETCH_ASSOC);

// Get user addresses
$address_sql = "SELECT * FROM user_address WHERE userID = ? ORDER BY isDefault DESC, created_at DESC";
$address_stmt = $_db->prepare($address_sql);
$address_stmt->execute([$user_id]);
$addresses = $address_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders
$orders_sql = "SELECT * FROM `order` WHERE userID = ? ORDER BY orderDate DESC LIMIT 5";
$orders_stmt = $_db->prepare($orders_sql);
$orders_stmt->execute([$user_id]);
$recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status change
if (is_post() && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'change_status') {
        $new_status = $_POST['status'];
        $update_sql = "UPDATE user SET status = ?, updated_at = NOW() WHERE userID = ?";
        $update_stmt = $_db->prepare($update_sql);
        $update_stmt->execute([$new_status, $user_id]);
        
        if ($new_status === 'Active') {
            temp('success', 'User activated successfully');
        } else {
            temp('success', 'User banned successfully');
        }
        redirect($_SERVER['REQUEST_URI']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userdetail.css">
    <link rel="stylesheet" href="../css/product_details.css">
</head>
<body>
    <?php include 'adminheader.php'; ?>
    
    <main class="user-detail">
        <?php if (!$user): ?>
            <div class="error-container">
                <p class="no-results">User not found.</p>
            </div>
        <?php else: ?>
            <div class="user-detail-header">
                <h1>User Details</h1>
                <a href="usermanage/list.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to User List
                </a>
            </div>
            
            <div class="user-detail-content">
                <!-- User Information Card -->
                <div class="user-info-card">
                    <div class="user-profile-section">
                        <?php if ($user['photo'] && file_exists('../' . $user['photo'])): ?>
                            <img src="../<?php echo $user['photo']; ?>" 
                                 alt="Profile Picture" 
                                 class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="user-basic-info">
                            <h2><?php echo $user['name'] ?: $user['username']; ?></h2>
                            <div class="user-info-header">
                                <span class="role-badge role-<?php echo strtolower($user['role']); ?>"><?php echo $user['role']; ?></span>
                                <span class="status-badge <?php echo ($user['status'] === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['status']; ?>
                                </span>
                            </div>
                            <div class="user-id-info">
                                <span class="label">User ID:</span> <?php echo $user['userID']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Username</span>
                            <span class="info-value"><?php echo $user['username']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo $user['email']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value <?php echo empty($user['phoneNo']) ? 'empty' : ''; ?>">
                                <?php echo !empty($user['phoneNo']) ? $user['phoneNo'] : 'Not provided'; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Birthday</span>
                            <span class="info-value <?php echo empty($user['birthday']) || $user['birthday'] === '0000-00-00' ? 'empty' : ''; ?>">
                                <?php 
                                if (!empty($user['birthday']) && $user['birthday'] !== '0000-00-00') {
                                    echo date('M j, Y', strtotime($user['birthday']));
                                    if ($user['age'] > 0) {
                                        echo " ({$user['age']} years old)";
                                    }
                                } else {
                                    echo 'Not provided';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Member Since</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Last Login</span>
                            <span class="info-value <?php echo empty($user['last_login']) ? 'empty' : ''; ?>">
                                <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- User Statistics -->
                    <div class="stats-section">
                        <h3 class="section-title">Account Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($user['total_orders']); ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo money($user['total_spent'] ?: 0); ?></div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($user['wishlist_items']); ?></div>
                                <div class="stat-label">Wishlist Items</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $user['cart_items'] ?: 0; ?></div>
                                <div class="stat-label">Cart Items</div>
                            </div>
                        </div>
                        
                        <!-- Debug Information (remove this after testing) -->
                        <div style="margin-top: 1rem; padding: 1rem; background: #f0f0f0; border-radius: 5px; font-size: 0.9rem;">
                            <strong>Debug Info:</strong><br>
                            Complex Query Total Spent: <?php echo $user['total_spent']; ?><br>
                            Simple Query Total Spent: <?php echo $debug_data['order_total']; ?><br>
                            Order Count: <?php echo $debug_data['order_count']; ?><br>
                            Cart Items (complex): <?php echo $user['cart_items']; ?><br>
                        </div>
                    </div>
                </div>
                
                <!-- Addresses Card -->
                <div class="user-info-card">
                    <h3 class="section-title">Addresses</h3>
                    <?php if (!empty($addresses)): ?>
                        <?php foreach ($addresses as $address): ?>
                            <div class="address-card <?php echo $address['isDefault'] ? 'address-default' : ''; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <strong><?php echo $address['recipient_name']; ?></strong>
                                    <?php if ($address['isDefault']): ?>
                                        <span class="status-badge status-active">Default</span>
                                    <?php endif; ?>
                                </div>
                                <div class="info-value">
                                        <?php echo $address['unitNo'] . ', ' . $address['address_line_1']; ?>
                                    <?php if (!empty($address['address_line_2'])): ?>
                                        <br><?php echo $address['address_line_2']; ?>
                                    <?php endif; ?>
                                    <br><?php echo $address['postcode'] . ' ' . $address['city'] . ', ' . $address['state']; ?>
                                </div>
                                <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                                    Phone: <?php echo $address['phoneNo']; ?> | 
                                    Type: <?php echo $address['type']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">No addresses found</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Orders Section -->
            <div class="user-info-card">
                <h3 class="section-title">Recent Orders</h3>
                <?php if (!empty($recent_orders)): ?>
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="order-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong>Order #<?php echo $order['orderID']; ?></strong>
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </div>
                            <div class="info-value">
                                Date: <?php echo date('M j, Y g:i A', strtotime($order['orderDate'])); ?><br>
                                Total: <?php echo money($order['total']); ?><br>
                                Shipping: <?php echo $order['shipping_method']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No orders found</div>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($user['status'] === 'Active'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="status" value="Inactive">
                        <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to ban this user?')">
                            <i class="fas fa-ban"></i> Ban User
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="status" value="Active">
                        <button type="submit" class="btn-success" onclick="return confirm('Are you sure you want to activate this user?')">
                            <i class="fas fa-check"></i> Activate User
                        </button>
                    </form>
                <?php endif; ?>
                
                <a href="usermanage/list.php" class="btn-primary">
                    <i class="fas fa-list"></i> Back to List
                </a>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Admin JavaScript -->
    <script src="../js/admin.js"></script>
    
    <?php include '../footer.php'; ?>
</body>
</html>
