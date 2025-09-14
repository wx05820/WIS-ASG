
<?php
include '../_base.php';
include '../lib/SimplePager.php';

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

// Get pagination parameters for orders
$orders_page = isset($_GET['orders_page']) && ctype_digit($_GET['orders_page']) ? (int)$_GET['orders_page'] : 1;

// Get user information with additional details
$stmt = $_db->prepare("SELECT u.*, 
               COUNT(DISTINCT o.orderID) as total_orders,
               COALESCE(SUM(ci.qty), 0) as cart_items,
               COUNT(DISTINCT w.prodID) as wishlist_items,
               COALESCE(SUM(CASE WHEN o.status NOT IN ('Refunded', 'Cancelled') THEN o.total ELSE 0 END), 0) as total_spent,
               MAX(o.orderDate) as last_order_date
        FROM user u
        LEFT JOIN `order` o ON u.userID = o.userID
        LEFT JOIN cart c ON u.userID = c.userID
        LEFT JOIN cart_items ci ON c.cartID = ci.cartID
        LEFT JOIN wishlist w ON u.userID = w.userID
        WHERE u.userID = ?
        GROUP BY u.userID");

$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);




// Get user addresses
$address_sql = "SELECT * FROM user_address WHERE userID = ? ORDER BY isDefault DESC, created_at DESC";
$address_stmt = $_db->prepare($address_sql);
$address_stmt->execute([$user_id]);
$addresses = $address_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_status') {
        $new_status = $_POST['status'] ?? '';
        if (in_array($new_status, ['Active', 'Inactive'])) {
            try {
                $update_sql = "UPDATE user SET status = ? WHERE userID = ?";
                $update_stmt = $_db->prepare($update_sql);
                $update_stmt->execute([$new_status, $user_id]);
                
                $_SESSION['success'] = "User status updated successfully to " . $new_status;
                header("Location: userdetail.php?userID=" . $user_id);
                exit;
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating user status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'remove_staff') {
        try {
            $update_sql = "UPDATE user SET role = 'Customer' WHERE userID = ?";
            $update_stmt = $_db->prepare($update_sql);
            $update_stmt->execute([$user_id]);
            
            $_SESSION['success'] = "Staff member removed successfully. Role changed to Customer.";
            header("Location: userdetail.php?userID=" . $user_id);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error removing staff member: " . $e->getMessage();
        }
    }
}

// Get orders with pagination
$orders_sql = "SELECT * FROM `order` WHERE userID = ? ORDER BY orderDate DESC";
$orders_params = [$user_id];

// Create SimplePager instance for orders
$orders_pager = new SimplePager($orders_sql, $orders_params, 5, $orders_page);
$recent_orders = $orders_pager->result;

// Set pagination variables
$total_orders = $orders_pager->item_count;
$total_orders_pages = $orders_pager->page_count;


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
    <link rel="stylesheet" href="../css/ban_form.css">
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
                    
                    </div>
                </div>
                


@@ -247,97 +250,59 @@
            
            <!-- Recent Orders Section -->
            <div class="user-info-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 class="section-title">
                        Recent Orders 
                        <span style="font-size: 0.8rem; font-weight: normal; color: #666; margin-left: 0.5rem;">
                            (<?php echo $total_orders; ?> total)
                        </span>
                    </h3>
                    <span style="font-size: 0.9rem; color: #666;">
                        Page <?php echo $orders_page; ?> of <?php echo $total_orders_pages; ?>
                    </span>
                </div>
                
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
                    
                    <!-- Orders Pagination -->
                    <?php if ($total_orders_pages > 1): ?>
                        <div class="pagination" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                            <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
                                <?php if ($orders_page > 1): ?>
                                    <a href="?userID=<?php echo $user_id; ?>&orders_page=<?php echo $orders_page - 1; ?>" 
                                       class="page-btn" style="padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem;">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <span class="page-info" style="padding: 0.5rem 1rem; background: #f8f9fa; border-radius: 4px; font-size: 0.9rem;">
                                    Page <?php echo $orders_page; ?> of <?php echo $total_orders_pages; ?>
                                </span>
                                
                                <?php if ($orders_page < $total_orders_pages): ?>
                                    <a href="?userID=<?php echo $user_id; ?>&orders_page=<?php echo $orders_page + 1; ?>" 
                                       class="page-btn" style="padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem;">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Page Numbers -->
                            <div style="display: flex; justify-content: center; gap: 0.25rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                <?php
                                $start_page = max(1, $orders_page - 2);
                                $end_page = min($total_orders_pages, $orders_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?userID=<?php echo $user_id; ?>&orders_page=<?php echo $i; ?>" 
                                       class="page-number <?php echo $i === $orders_page ? 'active' : ''; ?>"
                                       style="padding: 0.4rem 0.8rem; background: <?php echo $i === $orders_page ? '#007bff' : '#f8f9fa'; ?>; 
                                              color: <?php echo $i === $orders_page ? 'white' : '#333'; ?>; 
                                              text-decoration: none; border-radius: 4px; font-size: 0.85rem; 
                                              border: 1px solid <?php echo $i === $orders_page ? '#007bff' : '#dee2e6'; ?>;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-data">No orders found</div>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <?php if (isStaffSupervisor() || isStaffSuperAdmin()): ?>
            <div class="action-buttons">
                <?php if ($user['status'] === 'Active'): ?>
                    <button type="button" class="btn-danger btn-large" onclick="openBanModal('<?php echo $user['userID']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')">
                        <i class="fas fa-ban"></i> Ban User
                    </button>
                <?php else: ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="status" value="Active">
                        <button type="submit" class="btn-success btn-large" onclick="return confirm('Are you sure you want to activate this user?')">
                            <i class="fas fa-check"></i> Activate User
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($user['role'] === 'Staff' || $user['role'] === 'Supervisor' || $user['role'] === 'SuperAdmin'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="remove_staff">
                        <button type="submit" class="btn-warning btn-large" onclick="return confirm('Are you sure you want to remove this staff member? This will change their role to Customer.')">
                            <i class="fas fa-user-minus"></i> Remove Staff
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Ban User Modal -->
            <div id="banModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Ban User</h3>
                        <span class="close" onclick="closeBanModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to ban <strong id="banUsername"></strong>?</p>
                        <form id="banForm" method="POST" action="ban_user.php">
                            <input type="hidden" name="user_id" id="banUserId">
                            <div class="form-group">
                                <label for="banReason">Reason for ban (optional):</label>
                                <textarea name="reason" id="banReason" class="form-control" rows="3" placeholder="Enter reason for banning this user..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-danger">Ban User</button>
                                <button type="button" class="btn btn-secondary" onclick="closeBanModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </main>
    

    <!-- Admin JavaScript -->
    <script src="../js/admin.js"></script>
    <script>
        // Ban User Modal Functions
        function openBanModal(userId, username) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banUsername').textContent = username;
            document.getElementById('banModal').style.display = 'block';
        }

        function closeBanModal() {
            document.getElementById('banModal').style.display = 'none';
            document.getElementById('banForm').reset();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const banModal = document.getElementById('banModal');
            if (event.target === banModal) {
                closeBanModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeBanModal();
            }
        });
    </script>
    
    <?php include '../footer.php'; ?>
</body>
</html>
