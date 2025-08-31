<?php
include '../../_base.php';

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

// Get all users with pagination (excluding current staff member)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count (excluding current staff member)
$stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE userID != ?');
$stm->execute([$current_staff_id]);
$total_users = $stm->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Get users for current page (excluding current staff member)
$stm = $_db->prepare('
    SELECT userID, username, photo, role, status, last_login, created_at, email, name
    FROM user 
    WHERE userID != ?
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
');
$stm->execute([$current_staff_id, $limit, $offset]);
$users = $stm->fetchAll();

$success_msg = get_temp('success');
$error_msg = get_temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - AiKUN Furniture</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/userlist.css">
    <style>
        .clear-filters-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(255, 107, 107, 0.2);
            white-space: nowrap;
        }
        
        .clear-filters-btn:hover {
            background: linear-gradient(135deg, #ff5252, #e53935);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(255, 107, 107, 0.3);
        }
        
        .clear-filters-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(255, 107, 107, 0.2);
        }
        
        .clear-filters-btn i {
            font-size: 12px;
        }
        
        .search-counter {
            text-align: right;
            margin: 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input-group {
            flex: 1;
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <p>Manage all registered users in the system</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>



        <!-- Search and Filter Bar -->
        <div class="search-bar">
            <div class="search-filters">
                <!-- Search Input -->
                <div class="search-input-group">
                    <input type="text" class="search-input" placeholder="Search by ID, username, email, or name..." id="searchInput">
                </div>
                
                <!-- Role Filter -->
                <div class="filter-group">
                    <label for="roleFilter">Role:</label>
                    <select id="roleFilter" class="filter-select">
                        <option value="all">All Roles</option>
                        <option value="Admin">Admin</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Customer">Customer</option>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Ban</option>
                    </select>
                </div>
                
                <!-- Clear Button -->
                <button type="button" class="clear-filters-btn" id="clearFiltersBtn">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>

        <!-- Users Cards -->
        <div class="users-section">
            <div class="section-header">
                <i class="fas fa-users"></i> User List
            </div>  
            
            <?php if (empty($users)): ?>
                <div class="no-users">
                    <i class="fas fa-user-slash"></i>
                    <h3>No record found</h3>
                    <p>There are no users in the database.</p>
                </div>
            <?php else: ?>
                <div class="users-grid" id="usersGrid">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card user-row" 
                             data-userid="<?php echo htmlspecialchars($user->userID); ?>"
                             data-username="<?php echo htmlspecialchars(strtolower($user->username)); ?>" 
                             data-email="<?php echo htmlspecialchars(strtolower($user->email)); ?>" 
                             data-name="<?php echo htmlspecialchars(strtolower($user->name)); ?>"
                             data-role="<?php echo htmlspecialchars($user->role); ?>"
                             data-status="<?php echo htmlspecialchars($user->status); ?>">
                            <!-- Profile Picture -->
                            <div class="user-photo-container">
                                <?php if ($user->photo && file_exists('../../' . $user->photo)): ?>
                                    <img src="../../<?php echo htmlspecialchars($user->photo); ?>" alt="Profile" class="user-photo">
                                <?php else: ?>
                                    <div class="user-photo">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- User Information -->
                            <div class="user-info">
                                <div class="user-field">
                                    <span class="user-label">ID:</span>
                                    <span class="user-value"><?php echo htmlspecialchars($user->userID); ?></span>
                                </div>
                                
                                <div class="user-field">
                                    <span class="user-label">Username:</span>
                                    <span class="user-value"><?php echo htmlspecialchars($user->username); ?></span>
                                </div>
                                
                                <div class="user-field">
                                    <span class="user-label">Email:</span>
                                    <span class="user-value"><?php echo htmlspecialchars($user->email); ?></span>
                                </div>
                                
                                <div class="user-field">
                                    <span class="user-label">Role:</span>
                                    <span class="user-value">
                                        <span class="role-badge role-<?php echo strtolower($user->role); ?>">
                                            <?php echo htmlspecialchars($user->role); ?>
                                        </span>
                                    </span>
                                </div>
                                
                                <div class="user-additional-details">
                                    <div class="user-field">
                                        <span class="user-label">Last Login:</span>
                                        <span class="user-value">
                                            <?php if ($user->last_login): ?>
                                                <?php echo date('M j, Y g:i A', strtotime($user->last_login)); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Never</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="user-field">
                                        <span class="user-label">Create Date:</span>
                                        <span class="user-value">
                                            <?php echo date('M j, Y', strtotime($user->created_at)); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            
                            <div class="user-action">
                                
                                <div class="status-indicator-large">
                                    <?php if ($user->status === 'Active'): ?>
                                        <span class="status-active-large">Active</span>
                                    <?php else: ?>
                                        <span class="status-ban-large">Ban</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Button -->
                                <?php if ($user->status === 'Active'): ?>
                                    <?php if ($user->role === 'Admin' || $user->role === 'Supervisor'): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="action-btn remove" onclick="return confirm('Are you sure you want to remove this staff member?')">
                                                Remove
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="action-btn ban" onclick="return confirm('Are you sure you want to ban this user?')">
                                                Ban
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user->userID; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="action-btn" onclick="return confirm('Are you sure you want to activate this user?')">
                                            Active
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="page-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Search and filter functionality
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const userCards = document.querySelectorAll('.user-card');
            const usersGrid = document.getElementById('usersGrid');
            
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
            
            // Handle "No record found" message
            let noUsersDiv = document.querySelector('.no-users-filtered');
            
            if (visibleCount === 0) {
                if (!noUsersDiv) {
                    // Create and insert the no-users message after the users-grid
                    const noUsersHTML = `
                        <div class="no-users no-users-filtered" style="display: block;">
                            <i class="fas fa-user-slash"></i>
                            <h3>No record found</h3>
                            <p>No users match the current search and filter criteria.</p>
                        </div>
                    `;
                    usersGrid.insertAdjacentHTML('afterend', noUsersHTML);
                } else {
                    noUsersDiv.style.display = 'block';
                }
            } else {
                if (noUsersDiv) {
                    noUsersDiv.style.display = 'none';
                }
            }
        }
        
        // Clear search and filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('roleFilter').value = 'all';
            document.getElementById('statusFilter').value = 'all';
            filterUsers();
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').addEventListener('input', filterUsers);
            document.getElementById('roleFilter').addEventListener('change', filterUsers);
            document.getElementById('statusFilter').addEventListener('change', filterUsers);
            document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
        });
        
        // Enhanced search with better matching
        function enhancedSearch(searchTerm, text) {
            if (!searchTerm) return true;
            
            // Direct match
            if (text.includes(searchTerm)) return true;
            
            // Split search term for multiple word search
            const searchWords = searchTerm.split(' ').filter(word => word.length > 0);
            if (searchWords.length > 1) {
                return searchWords.every(word => text.includes(word));
            }
            
            return false;
        }
        
        // Real-time search counter
        function updateSearchCounter() {
            const visibleCards = document.querySelectorAll('.user-card[style=""], .user-card:not([style*="display: none"])');
            const totalCards = document.querySelectorAll('.user-card').length;
            
            let counterDiv = document.querySelector('.search-counter');
            if (!counterDiv) {
                counterDiv = document.createElement('div');
                counterDiv.className = 'search-counter';
                counterDiv.style.cssText = 'text-align: right; margin: 10px 0; color: #666; font-size: 14px;';
                document.querySelector('.search-bar').appendChild(counterDiv);
            }
            
            const visibleCount = Array.from(visibleCards).filter(card => 
                card.style.display !== 'none'
            ).length;
            
            counterDiv.textContent = `Showing ${visibleCount} of ${totalCards} users`;
        }
        
        // Update the filterUsers function to include counter
        const originalFilterUsers = filterUsers;
        filterUsers = function() {
            originalFilterUsers();
            updateSearchCounter();
        };
    </script>
</body>
</html>