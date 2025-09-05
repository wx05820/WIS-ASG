<?php
include '../../_base.php';
include '../../lib/SimplePager.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../loginstaff.php');
}

// Get current staff ID to exclude from list
$current_staff_id = $_SESSION['staff_id'] ?? null;

// Handle user status updates
if (is_post() && isset($_POST['action'])) {
    $user_id = req('user_id');
    
    if ($_POST['action'] === 'activate') {
        $stm = $_db->prepare('UPDATE user SET status = "Active" WHERE userID = ?');
        $stm->execute([$user_id]);
        temp('success', 'User activated successfully');
    } elseif ($_POST['action'] === 'deactivate') {
        $stm = $_db->prepare('UPDATE user SET status = "Inactive" WHERE userID = ?');
        $stm->execute([$user_id]);
        temp('success', 'User deactivated successfully');
    } elseif ($_POST['action'] === 'delete') {
        $stm = $_db->prepare('DELETE FROM user WHERE userID = ?');
        $stm->execute([$user_id]);
        temp('success', 'User deleted successfully');
    }
    
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
                                   placeholder="Search by ID, username, email, or name..." 
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
                    <div class="user-row user-card" 
                         data-userid="<?php echo htmlspecialchars($user->userID); ?>"
                         data-username="<?php echo htmlspecialchars(strtolower($user->username)); ?>" 
                         data-email="<?php echo htmlspecialchars(strtolower($user->email)); ?>" 
                         data-name="<?php echo htmlspecialchars(strtolower($user->name)); ?>"
                         data-role="<?php echo htmlspecialchars($user->role); ?>"
                         data-status="<?php echo htmlspecialchars($user->status); ?>">
                        
                        <!-- Profile Picture -->
                        <div class="user-image">
                            <?php if ($user->photo && file_exists('../../' . $user->photo)): ?>
                                <img src="../../<?php echo htmlspecialchars($user->photo); ?>" 
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
                                <h3><?php echo htmlspecialchars($user->username); ?></h3>
                                <span class="role-badge"><?php echo htmlspecialchars($user->role); ?></span>
                                <span class="status-badge <?php echo ($user->status === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars($user->status); ?>
                                </span>
                            </div>
                            <div class="user-id-info">
                                <span class="label">User ID:</span> <?php echo htmlspecialchars($user->userID); ?>
                            </div>
                            <div class="user-email">
                                <?php echo htmlspecialchars($user->email); ?>
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
                        <div class="user-actions">
                            <?php if ($user->status === 'Active'): ?>
                                <?php if ($user->role === 'Admin' || $user->role === 'Supervisor'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn remove" onclick="return confirm('Are you sure you want to remove this staff member?')">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="action-btn ban" onclick="return confirm('Are you sure you want to ban this user?')">
                                            <i class="fas fa-ban"></i> Ban
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                    <input type="hidden" name="action" value="activate">
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

    <script>
        // Search and filter functionality
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const userCards = document.querySelectorAll('.user-card');
            const productsList = document.querySelector('.products-list');
            const noProductsDiv = document.querySelector('.no-products');
            
            let visibleCount = 0;
            
            userCards.forEach(card => {
                const userid = card.dataset.userid.toString().toLowerCase();
                const username = card.dataset.username;
                const email = card.dataset.email;
                const name = card.dataset.name;
                const role = card.dataset.role;
                const status = card.dataset.status;
                
                // Check search term (ID, username, email, or name)
                let matchesSearch = true;
                if (searchTerm) {
                    matchesSearch = userid.includes(searchTerm) || 
                                   username.includes(searchTerm) || 
                                   email.includes(searchTerm) || 
                                   name.includes(searchTerm);
                }
                
                // Check role filter
                const matchesRole = roleFilter === 'all' || role === roleFilter;
                
                // Check status filter
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                // Show/hide card based on all filters
                if (matchesSearch && matchesRole && matchesStatus) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Handle "No record found" message and users list visibility
            const usersContainer = document.querySelector('.users-table-container');
            const noUsersFiltered = document.querySelector('.no-users-filtered');
            
            if (visibleCount === 0) {
                // Hide the users container and show the "no filtered results" message
                if (usersContainer) usersContainer.style.display = 'none';
                if (noUsersFiltered) noUsersFiltered.style.display = 'block';
            } else {
                // Show the users container and hide the "no filtered results" message
                if (usersContainer) usersContainer.style.display = '';
                if (noUsersFiltered) noUsersFiltered.style.display = 'none';
            }
            
            // Update results counter
            updateResultsCounter(visibleCount);
        }
        
        // Update results counter
        function updateResultsCounter(visibleCount) {
            const totalUsers = document.querySelectorAll('.user-card').length;
            const showingStart = document.getElementById('showing-start');
            const showingEnd = document.getElementById('showing-end');
            const totalUsersSpan = document.getElementById('total-users');
            
            if (showingStart && showingEnd && totalUsersSpan) {
                if (visibleCount === 0) {
                    showingStart.textContent = '0';
                    showingEnd.textContent = '0';
                } else {
                    showingStart.textContent = '1';
                    showingEnd.textContent = visibleCount.toString();
                }
                totalUsersSpan.textContent = totalUsers.toString();
            }
        }
        
                 // Update URL with current filter values
         function updateURLWithFilters() {
             const searchValue = document.getElementById('searchInput').value.trim();
             const roleValue = document.getElementById('roleFilter').value;
             const statusValue = document.getElementById('statusFilter').value;
             
             const currentParams = new URLSearchParams(window.location.search);
             
             // Update or remove parameters based on values
             if (searchValue) {
                 currentParams.set('search', searchValue);
             } else {
                 currentParams.delete('search');
             }
             
             if (roleValue !== 'all') {
                 currentParams.set('role', roleValue);
             } else {
                 currentParams.delete('role');
             }
             
             if (statusValue !== 'all') {
                 currentParams.set('status', statusValue);
             } else {
                 currentParams.delete('status');
             }
             
             // Reset to first page when filters change
             currentParams.set('page', '1');
             
             // Update URL without reloading the page
             const newURL = window.location.pathname + (currentParams.toString() ? '?' + currentParams.toString() : '');
             window.history.replaceState({}, '', newURL);
         }
         
         // Restore filter values from URL parameters on page load
         function restoreFiltersFromURL() {
             const urlParams = new URLSearchParams(window.location.search);
             
             // Restore search input
             const searchValue = urlParams.get('search') || '';
             const searchInput = document.getElementById('searchInput');
             if (searchInput) {
                 searchInput.value = searchValue;
             }
             
             // Restore role filter
             const roleValue = urlParams.get('role') || 'all';
             const roleFilter = document.getElementById('roleFilter');
             if (roleFilter) {
                 roleFilter.value = roleValue;
             }
             
             // Restore status filter
             const statusValue = urlParams.get('status') || 'all';
             const statusFilter = document.getElementById('statusFilter');
             if (statusFilter) {
                 statusFilter.value = statusValue;
             }
             
             // Apply filters if any values were restored
             if (searchValue || roleValue !== 'all' || statusValue !== 'all') {
                 setTimeout(function() {
                     filterUsers();
                 }, 100);
             }
         }
         
         // Handle sort toggle while preserving all current filters
         function handleSortToggle(newSortOrder) {
             const currentParams = new URLSearchParams(window.location.search);
             
             // Preserve current filter values from form inputs
             const searchValue = document.getElementById('searchInput').value.trim();
             const roleValue = document.getElementById('roleFilter').value;
             const statusValue = document.getElementById('statusFilter').value;
             
             // Update filter parameters
             if (searchValue) {
                 currentParams.set('search', searchValue);
             } else {
                 currentParams.delete('search');
             }
             
             if (roleValue !== 'all') {
                 currentParams.set('role', roleValue);
             } else {
                 currentParams.delete('role');
             }
             
             if (statusValue !== 'all') {
                 currentParams.set('status', statusValue);
             } else {
                 currentParams.delete('status');
             }
             
             // Update sort order
             currentParams.set('id_order', newSortOrder);
             currentParams.set('page', '1'); // Reset to first page
             
             // Navigate to new URL
             const newURL = window.location.pathname + '?' + currentParams.toString();
             window.location.href = newURL;
         }
         
         // Clear search and filters
         function clearFilters() {
             document.getElementById('searchInput').value = '';
             document.getElementById('roleFilter').value = 'all';
             document.getElementById('statusFilter').value = 'all';
             
             // Clear URL parameters
             const currentParams = new URLSearchParams(window.location.search);
             currentParams.delete('search');
             currentParams.delete('role');
             currentParams.delete('status');
             currentParams.set('page', '1');
             
             // Update URL
             const newURL = window.location.pathname + (currentParams.toString() ? '?' + currentParams.toString() : '');
             window.history.replaceState({}, '', newURL);
             
             filterUsers();
         }
        
        // Modified filter function to update URL
        const originalFilterUsers = filterUsers;
        filterUsers = function() {
            originalFilterUsers();
            updateURLWithFilters();
        };
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event listeners
            var searchInput = document.getElementById('searchInput');
            var clearSearchBtn = document.getElementById('clearSearchBtn');

            // Show/hide clear button based on input value
            if (searchInput && clearSearchBtn) {
                clearSearchBtn.style.display = searchInput.value.trim() ? 'inline-block' : 'none';
                searchInput.addEventListener('input', function() {
                    clearSearchBtn.style.display = searchInput.value.trim() ? 'inline-block' : 'none';
                });
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    searchInput.focus();
                    clearSearchBtn.style.display = 'none';
                    filterUsers();
                });
            }

            if (searchInput) searchInput.addEventListener('input', filterUsers);
            document.getElementById('searchBtn').addEventListener('click', filterUsers);
            document.getElementById('roleFilter').addEventListener('change', filterUsers);
            document.getElementById('statusFilter').addEventListener('change', filterUsers);
            document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
            
            // Restore filters from URL on page load
            setTimeout(function() {
                restoreFiltersFromURL();
            }, 50);
        });
    </script>
</body>
</html>