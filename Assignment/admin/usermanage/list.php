<?php
include '../../_base.php';
include '../../lib/SimplePager.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../loginstaff.php');
}

// Get current staff ID to exclude from list
$current_staff_id = $_SESSION['staff_id'] ?? null;

// Handle user deletion only (status changes moved to chgstatus.php)
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = req('user_id');
    $stm = $_db->prepare('DELETE FROM user WHERE userID = ?');
    $stm->execute([$user_id]);
    temp('success', 'User deleted successfully');
    redirect($_SERVER['REQUEST_URI']);
}

// Determine ID sort order
$id_order = isset($_GET['id_order']) && strtoupper($_GET['id_order']) === 'ASC' ? 'ASC' : 'DESC';

// Get current page
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;

// Prepare SQL query for SimplePager
$sql = '
    SELECT userID, username, photo, role, status, last_login, created_at, email, name
    FROM user 
    WHERE userID != ?
    ORDER BY userID ' . $id_order;

$params = [$current_staff_id];

// Create SimplePager instance
$pager = new SimplePager($sql, $params, 10, $page);
$users = $pager->result;

// Convert result to objects for compatibility with existing code
foreach ($users as &$user) {
    $user = (object) $user;
}
unset($user);

// Set variables for backward compatibility
$total_users = $pager->item_count;
$total_pages = $pager->page_count;

$success_msg = get_temp('success');
$error_msg = get_temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - AiKUN Furniture</title>
    <link rel="stylesheet" href="../../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/userlist.css">
    <link rel="stylesheet" href="../../css/products.css">
</head>
<body class="product-list-main" style="margin-top:0; padding-top:0;">

    <?php include '../adminheader.php'; ?>
    <script src="../../js/adminProductList.js"></script>

    <div class="container">

        <!-- Display success/error messages -->
        <?php if ($success_msg): ?>
            <div class="error-message" style="background-color: #e8f5e8; color: #2c5530; padding: 1rem; margin: 1rem 0; border-radius: 4px; border: 1px solid #c8e6c9;">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="error-message" style="background-color: #fee; color: #c33; padding: 1rem; margin: 1rem 0; border-radius: 4px; border: 1px solid #fcc;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>



        <!-- Action Buttons Outside Filter Bar -->
        <div class="product-action-bar" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
            <button type="button" class="adduser-btn sortby-add" onclick="window.location.href='adduser.php'">
                <i class="fas fa-user-plus"></i>
                <span class="action-btn-label">Add Staff</span>
            </button>
            <?php
            $toggle_order = $id_order === 'ASC' ? 'DESC' : 'ASC';
            $toggle_label = $id_order === 'ASC' ? 'ID Desc' : 'ID Asc';
            $toggle_icon = $id_order === 'ASC' ? 'fa-sort-numeric-down' : 'fa-sort-numeric-up';
            ?>
                         <button type="button" class="restore-btn sortby-restore" onclick="handleSortToggle('<?php echo $toggle_order; ?>')">
                 <i class="fas <?php echo $toggle_icon; ?>"></i>
                 <span class="action-btn-label"><?php echo $toggle_label; ?></span>
             </button>
        </div>

        <!-- Filters and Search Section -->
        <div class="filters-section">
            <div class="filters-container">

                <!-- Search Bar -->
                <div class="search-filter">
                    <div class="filter-form" style="display: flex; gap: 10px; align-items: center;">
                        <div style="position:relative; display:inline-block;">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Search by ID, username or email" 
                                   class="search-input"
                                   style="width: 350px; max-width: 100%; padding: 0.5rem 1rem; font-size: 1.1rem; padding-right:2.4rem;">
                            <button type="button" id="clearSearchBtn" title="Clear search"
                                    style="position:absolute; right:10px; top:50%; transform:translateY(-50%); border:none; background:transparent; font-size:1.2rem; cursor:pointer; color:#888; display:none;">
                                &times;
                            </button>
                        </div>

                        <button type="button" class="search-btn" id="searchBtn" style="padding: 0.5rem 1rem; font-size: 1.1rem;">
                            <i class="fas fa-search"></i>
                        </button>
                        
                        <select id="roleFilter" class="filter-select" style="width: 180px; padding: 0.5rem 0.7rem; font-size: 1rem;">
                            <option value="all">All Roles</option>
                            <option value="Admin">Admin</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Customer">Customer</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Options -->
                <div class="sort-filter" style="display: flex; gap: 10px; align-items: center;">
                    <select id="statusFilter" class="filter-select sortby-select">
                        <option value="all">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Ban</option>
                    </select>
                    <button type="button" class="order-btn sortby-order" id="clearFiltersBtn" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="results-summary">
                <p>Showing <span id="showing-start"><?php echo ($total_users > 0) ? (($pager->page - 1) * $pager->limit + 1) : 0; ?></span> - 
                    <span id="showing-end"><?php echo min($pager->page * $pager->limit, $total_users); ?></span> of <span id="total-users"><?php echo $total_users; ?></span> 
                users
                </p>
            </div>
        </div>


        <!-- Users List -->
        <?php if (!empty($users)): ?>
            <div class="users-table-container">
                <?php foreach ($users as $user): ?>
                    <div class="user-row user-card clickable-user-card" 
                         data-userid="<?php echo $user->userID; ?>"
                         data-username="<?php echo strtolower($user->username); ?>" 
                         data-email="<?php echo strtolower($user->email); ?>" 
                         data-name="<?php echo strtolower($user->name); ?>"
                         data-role="<?php echo $user->role; ?>"
                         data-status="<?php echo $user->status; ?>"
                         onclick="navigateToUserDetail('<?php echo $user->userID; ?>')"
                         title="Click to view user details">
                        
                        <!-- Profile Picture -->
                        <div class="user-image">
                            <?php if ($user->photo && file_exists('../../' . $user->photo)): ?>
                                <img src="../../<?php echo $user->photo; ?>" 
                                     alt="Profile" 
                                     loading="lazy">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- User Info -->
                        <div class="user-info">
                            <div class="user-info-header">
                                <h3><?php echo $user->username; ?></h3>
                                <span class="role-badge role-<?php echo strtolower($user->role); ?>"><?php echo $user->role; ?></span>
                                <span class="status-badge <?php echo ($user->status === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user->status; ?>
                                </span>
                            </div>
                            <div class="user-id-info">
                                <span class="label">User ID:</span> <?php echo $user->userID; ?>
                            </div>
                            <div class="user-email">
                                <?php echo $user->email; ?>
                            </div>
                        </div>

                        <!-- Date Info -->
                        <div class="user-dates">
                            <div class="date-row">
                                <span class="date-label">Created:</span> <?php echo date('M j, Y', strtotime($user->created_at)); ?>
                            </div>
                            <div class="date-row">
                                <span class="date-label">Last Login:</span> 
                                <?php if ($user->last_login): ?>
                                    <?php echo date('M j, Y', strtotime($user->last_login)); ?>
                                <?php else: ?>
                                    <span class="never">Never</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="user-actions" onclick="event.stopPropagation();">
                            <?php if ($user->status === 'Active'): ?>
                                <?php if ($user->role === 'Admin' || $user->role === 'Supervisor'): ?>
                                    <?php if (isStaffSuperAdmin()): ?>
                                        <button type="button" class="action-btn remove" onclick="openRemoveModal('<?php echo $user->userID; ?>', '<?php echo htmlspecialchars($user->username); ?>')">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="action-btn ban" onclick="openBanModal('<?php echo $user->userID; ?>', '<?php echo htmlspecialchars($user->username); ?>')">
                                            <i class="fas fa-ban"></i> Ban
                                        </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="post" action="chgstatus.php" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                    <input type="hidden" name="action" value="activate">
                                    <?php foreach ($_GET as $key => $value): ?>
                                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" class="action-btn" onclick="return confirm('Are you sure you want to activate this user?')">
                                        <i class="fas fa-check"></i> Activate
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="no-users-database">
                <i class="fas fa-user-slash"></i>
                <h3>No users found</h3>
                <p>There are no users in the database.</p>
            </div>
        <?php endif; ?>
        
        <!-- No filtered results message (hidden by default, shown by JavaScript) -->
        <div class="no-users-filtered" style="display: none;">
            <i class="fas fa-search"></i>
            <h3>No users found</h3>
            <p>No users match the current search and filter criteria.</p>
        </div>

        <!-- Pagination -->
        <?php
        // Build query parameters for pagination links (preserve all current filters)
        $params_array = [
            'id_order' => $id_order,
            'search'   => $_GET['search'] ?? null,
            'role'     => $_GET['role'] ?? null,
            'status'   => $_GET['status'] ?? null
        ];
        $params_array = array_filter($params_array); // Remove empty values
        $href = http_build_query($params_array);
        
        // Output SimplePager HTML
        echo $pager->html($href, 'class="pagination"');
        ?>
    </div>
    <?php include '../../footer.php'; ?>

    <!-- Admin JavaScript -->
    <script src="../../js/admin.js"></script>
    
    <!-- Consolidated List/Shipping JavaScript -->
    <script src="../../js/listusershipping.js"></script>
    
    <script>
        // User list specific functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Additional event listeners for user list specific elements
            const searchBtn = document.getElementById('searchBtn');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    ListUserShipping.filterItems();
                });
            }
        });
    </script>

    <!-- Ban User Modal -->
    <div id="banModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ban User</h3>
                <span class="close" onclick="closeBanModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to ban <strong id="banUsername"></strong>?</p>
                <form id="banForm" method="POST" action="../ban_user.php">
                    <input type="hidden" name="user_id" id="banUserId">
                    <div class="form-group">
                        <label for="banReason">Reason for ban (optional):</label>
                        <textarea name="reason" id="banReason" class="form-control" rows="3" placeholder="Enter reason for banning this user..."></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-danger">Ban User</button>
                        <button type="button" class="btn btn-secondary" onclick="closeBanModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Staff Modal -->
    <div id="removeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Remove Staff Member</h3>
                <span class="close" onclick="closeRemoveModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="removeUsername"></strong> from staff?</p>
                <p style="color: #dc3545; font-weight: bold;">⚠️ This action cannot be undone!</p>
                <form id="removeForm" method="POST" action="../remove_staff.php">
                    <input type="hidden" name="staff_id" id="removeStaffId">
                    <div class="form-group">
                        <label for="removeReason">Reason for removal (optional):</label>
                        <textarea name="reason" id="removeReason" class="form-control" rows="3" placeholder="Enter reason for removing this staff member..."></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-danger">Remove Staff</button>
                        <button type="button" class="btn btn-secondary" onclick="closeRemoveModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openBanModal(userId, username) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banUsername').textContent = username;
            document.getElementById('banModal').style.display = 'block';
        }

        function closeBanModal() {
            document.getElementById('banModal').style.display = 'none';
            document.getElementById('banForm').reset();
        }

        function openRemoveModal(staffId, username) {
            document.getElementById('removeStaffId').value = staffId;
            document.getElementById('removeUsername').textContent = username;
            document.getElementById('removeModal').style.display = 'block';
        }

        function closeRemoveModal() {
            document.getElementById('removeModal').style.display = 'none';
            document.getElementById('removeForm').reset();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const banModal = document.getElementById('banModal');
            const removeModal = document.getElementById('removeModal');
            
            if (event.target === banModal) {
                closeBanModal();
            }
            if (event.target === removeModal) {
                closeRemoveModal();
            }
        }
    </script>
</body>
</html>