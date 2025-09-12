<?php
include '../_base.php';
include '../lib/SimplePager.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('/admin/loginstaff.php');
}

// Determine ID sort order
$id_order = isset($_GET['id_order']) && strtoupper($_GET['id_order']) === 'ASC' ? 'ASC' : 'DESC';

// Get current page
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;

// Handle search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];

// Status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "is_active = ?";
    $params[] = ($status_filter === 'active') ? 'Active' : 'Inactive';
}

if (!empty($search)) {
    $where_conditions[] = "(code LIKE ? OR discount_type LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "discount_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Update SQL query with filters
$sql = "
    SELECT voucher_id, code as voucher_code, discount_type as voucher_type, value, start_date, end_date, 
           is_active, created_at, current_usage as usage_count, usage_limit as max_usage, description
    FROM voucher 
    $where_clause
    ORDER BY voucher_id $id_order, created_at DESC
";

// Create SimplePager instance with filtered parameters
$pager = null;
$vouchers = [];
$total_vouchers = 0;
$total_pages = 0;

try {
    $pager = new SimplePager($sql, $params, 10, $page);
    $vouchers = $pager->result;
    
    // Convert result to objects for compatibility with existing code
    foreach ($vouchers as &$voucher) {
        $voucher = (object) $voucher;
    }
    unset($voucher);

    // Set variables for backward compatibility
    $total_vouchers = $pager->item_count;
    $total_pages = $pager->page_count;
} catch (Exception $e) {
    $vouchers = [];
    $total_vouchers = 0;
    $total_pages = 0;
}


$page_title = 'Voucher Management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Management - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userlist.css">
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="../css/shipping.css">
    <link rel="stylesheet" href="../css/voucher_list.css">
    <style>
        /* Voucher page specific styles to match shipping page */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Voucher-specific card styling */
        .voucher-card {
            display: flex !important;
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            margin-bottom: 0.5rem;
        }

        .voucher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        /* Left Column */
        .voucher-left-column {
            flex: 1;
            padding-right: 1rem;
            border-right: 1px solid #e5e7eb;
        }

        .voucher-code-large {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.25rem;
            line-height: 1.1;
        }

        .voucher-type, .voucher-value {
            color: #6b7280;
            font-size: 0.75rem;
            margin-bottom: 0.15rem;
            font-weight: 500;
        }

        .voucher-dates {
            margin-top: 0.5rem;
            padding-top: 0.25rem;
            border-top: 1px solid #e5e7eb;
        }

        .date-label {
            color: #6b7280;
            font-size: 0.75rem;
            margin-right: 0.25rem;
            font-weight: 500;
        }

        .date-value {
            font-size: 0.7rem;
            font-weight: 500;
            color: #374151;
        }

        /* Right Column */
        .voucher-right-column {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding-left: 1rem;
        }

        /* Status Form */
        .status-form {
            margin-bottom: 0.15rem;
        }

        .status-select {
            align-self: flex-start;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #d97706;
            background-color: #fef3c7;
            color: #92400e;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 0.8rem;
            padding-right: 1.5rem;
        }

        .status-select:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .status-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        /* Status-specific colors */
        .status-select.status-active {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .status-select.status-inactive {
            background-color: #fecaca;
            color: #dc2626;
            border-color: #ef4444;
        }

        .voucher-usage {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 0.25rem;
        }

        .usage-label {
            color: #6b7280;
            font-size: 0.65rem;
            font-weight: 500;
            min-width: fit-content;
        }

        .usage-value {
            color: #374151;
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Add Voucher Button */
        .add-voucher-btn {
            background: var(--wood-primary);
            color: var(--text-light);
            border: 2px solid var(--wood-primary);
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Georgia', serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .add-voucher-btn:hover {
            background: var(--gold-accent);
            color: var(--wood-dark);
            border-color: var(--gold-accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }
    </style>
</head>
<body class="product-list-main" style="margin-top:0; padding-top:0;">
    <?php include 'adminheader.php'; ?>

<div class="container">


    <!-- Action Buttons -->
    <div class="product-action-bar" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
        <a href="addvoucher.php" class="add-voucher-btn">
            <i class="fas fa-plus"></i>
            <span class="action-btn-label">Add Voucher</span>
        </a>
        <?php
        $toggle_order = $id_order === 'ASC' ? 'DESC' : 'ASC';
        $toggle_label = $id_order === 'ASC' ? 'ID Desc' : 'ID Asc';
        $toggle_icon = $id_order === 'ASC' ? 'fa-sort-numeric-down' : 'fa-sort-numeric-up';
        ?>
        <button type="button" class="restore-btn sortby-restore" onclick="handleSortToggle('<?php echo $toggle_order; ?>')">
            <i class="fas <?php echo $toggle_icon; ?>"></i>
            <span class="action-btn-label"><?php echo $toggle_label; ?></span>
        </button>
        <button type="button" class="restore-btn sortby-restore" onclick="window.location.href='voucher_list.php'">
            <i class="fas fa-refresh"></i>
            <span class="action-btn-label">Refresh</span>
        </button>
    </div>

    <!-- Vouchers List -->
    <div class="orders-section">
        <div class="section-header">
            <h2><i class="fas fa-ticket-alt"></i> Vouchers List</h2>
            <div class="section-actions">
                <span class="total-count">Showing <span id="showing-start">1</span>-<span id="showing-end"><?php echo min(10, $total_vouchers); ?></span> of <span id="total-vouchers"><?php echo $total_vouchers; ?></span> vouchers</span>
            </div>
        </div>
        
        <!-- Filters and Search Section -->
        <div class="filters-section">
            <div class="filters-container">
                <!-- Search Bar -->
                <div class="search-filter">
                    <form method="GET" id="searchForm" style="display: flex; gap: 10px; align-items: center;">
                        <div style="position:relative; display:inline-block;">
                            <input type="text" 
                                   name="search"
                                   id="searchInput"
                                   placeholder="Search by Voucher Code or Type..." 
                                   class="search-input"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="width: 350px; max-width: 100%; padding: 0.5rem 1rem; font-size: 1.1rem; padding-right:2.4rem;">
                            <button type="button" id="clearSearchBtn" title="Clear search"
                                    style="position:absolute; right:10px; top:50%; transform:translateY(-50%); border:none; background:transparent; font-size:1.2rem; cursor:pointer; color:#888; display:none;">
                                &times;
                            </button>
                        </div>

                        <button type="submit" class="search-btn" id="searchBtn" style="padding: 0.5rem 1rem; font-size: 1.1rem;">
                            <i class="fas fa-search"></i>
                        </button>
                        
                        <select id="statusFilter" class="filter-select" style="width: 180px; padding: 0.5rem 0.7rem; font-size: 1rem;">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        
                        <select id="typeFilter" class="filter-select sortby-select">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="Percentage" <?php echo $type_filter === 'Percentage' ? 'selected' : ''; ?>>Percentage</option>
                            <option value="Fixed" <?php echo $type_filter === 'Fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                            <option value="Free Shipping" <?php echo $type_filter === 'Free Shipping' ? 'selected' : ''; ?>>Free Shipping</option>
                        </select>
                        
                        <input type="hidden" name="id_order" value="<?php echo $id_order; ?>">
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>

                <!-- Filter Options -->
                <div class="sort-filter" style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="order-btn sortby-order" id="clearFiltersBtn" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- No Data Message (hidden by default) -->
        <div class="no-data" id="noDataMessage" style="display: none;">
            <i class="fas fa-ticket-alt"></i>
            <p>No vouchers found</p>
        </div>


        <?php if (empty($vouchers)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('noDataMessage').style.display = 'block';
                });
            </script>
        <?php else: ?>
            <div class="orders-table-container">
                <?php foreach ($vouchers as $voucher): ?>
                    <div class="voucher-card" 
                         data-voucherid="<?php echo $voucher->voucher_id; ?>"
                         data-code="<?php echo strtolower($voucher->voucher_code); ?>" 
                         data-type="<?php echo strtolower($voucher->voucher_type); ?>"
                         data-status="<?php echo strtolower($voucher->is_active); ?>">
                        
                        <!-- Left Column -->
                        <div class="voucher-left-column">
                            <div class="voucher-code-large"><?php echo $voucher->voucher_code; ?></div>
                            <div class="voucher-type">Type: <?php echo $voucher->voucher_type; ?></div>
                            <div class="voucher-value">Value: <?php 
                                if ($voucher->voucher_type === 'Percentage') {
                                    echo $voucher->value . '%';
                                } elseif ($voucher->voucher_type === 'Free Shipping') {
                                    echo 'Free Shipping';
                                } else {
                                    echo 'RM ' . number_format($voucher->value, 2);
                                }
                            ?></div>
                            <div class="voucher-dates">
                                <div style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.15rem;">
                                    <span class="date-label">Start:</span>
                                    <span class="date-value"><?php echo date('M j, Y', strtotime($voucher->start_date)); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.25rem;">
                                    <span class="date-label">End:</span>
                                    <span class="date-value"><?php echo date('M j, Y', strtotime($voucher->end_date)); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="voucher-right-column">
                            <form class="status-form" method="POST" action="update_voucher_status.php">
                                <input type="hidden" name="voucher_id" value="<?php echo $voucher->voucher_id; ?>">
                                <select name="new_status" class="status-select status-<?php echo strtolower($voucher->is_active); ?>" onchange="this.form.submit()">
                                    <option value="Active" <?php echo $voucher->is_active === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $voucher->is_active === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </form>
                            <div class="voucher-usage">
                                <span class="usage-label">Usage:</span>
                                <span class="usage-value"><?php echo $voucher->usage_count; ?>/<?php echo $voucher->max_usage; ?></span>
                            </div>
                            <div class="voucher-usage">
                                <span class="usage-label">Created:</span>
                                <span class="usage-value"><?php echo date('M j, Y', strtotime($voucher->created_at)); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&id_order=<?php echo $id_order; ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="page-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&id_order=<?php echo $id_order; ?>" class="page-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Admin JavaScript -->
<script src="../js/admin.js"></script>

<script>

// Voucher-specific functionality
document.addEventListener('DOMContentLoaded', function() {
    // Real-time filtering with AJAX
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const ordersContainer = document.querySelector('.orders-table-container');
    const noDataMessage = document.getElementById('noDataMessage');
    const showingStart = document.getElementById('showing-start');
    const showingEnd = document.getElementById('showing-end');
    const totalVouchers = document.getElementById('total-vouchers');
    
    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Fetch filtered data from server
    function fetchFilteredData() {
        const search = searchInput ? searchInput.value.trim() : '';
        const status = statusFilter ? statusFilter.value : 'all';
        const type = typeFilter ? typeFilter.value : 'all';
        const idOrder = '<?php echo $id_order; ?>';
        
        // Show loading state
        if (ordersContainer) {
            ordersContainer.innerHTML = '<div class="loading">Loading vouchers...</div>';
        }
        
        // Make AJAX request
        const url = `ajax_voucher_filter.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&type=${encodeURIComponent(type)}&id_order=${idOrder}&page=1`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                // Update vouchers display
                updateVouchersDisplay(data.vouchers);
                
                // Update counters
                if (showingStart) showingStart.textContent = '1';
                if (showingEnd) showingEnd.textContent = Math.min(10, data.total_vouchers);
                if (totalVouchers) totalVouchers.textContent = data.total_vouchers;
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                if (ordersContainer) {
                    ordersContainer.innerHTML = '<div class="error">Error loading vouchers. Please try again.</div>';
                }
            });
    }
    
    // Update vouchers display
    function updateVouchersDisplay(vouchers) {
        if (!ordersContainer) return;
        
        if (vouchers.length === 0) {
            if (noDataMessage) noDataMessage.style.display = 'block';
            ordersContainer.innerHTML = '';
        } else {
            if (noDataMessage) noDataMessage.style.display = 'none';
            ordersContainer.innerHTML = generateVouchersHTML(vouchers);
        }
    }
    
    // Generate vouchers HTML
    function generateVouchersHTML(vouchers) {
        let html = '';
        vouchers.forEach(voucher => {
            html += `
                <div class="voucher-card" 
                     data-voucherid="${voucher.voucher_id}"
                     data-code="${voucher.voucher_code.toLowerCase()}" 
                     data-type="${voucher.voucher_type.toLowerCase()}"
                     data-status="${voucher.is_active.toLowerCase()}">
                    
                    <!-- Left Column -->
                    <div class="voucher-left-column">
                        <div class="voucher-code-large">${voucher.voucher_code}</div>
                        <div class="voucher-type">Type: ${voucher.voucher_type}</div>
                        <div class="voucher-value">Value: ${voucher.voucher_type === 'Percentage' ? voucher.value + '%' : voucher.voucher_type === 'Free Shipping' ? 'Free Shipping' : 'RM ' + parseFloat(voucher.value).toFixed(2)}</div>
                        <div class="voucher-dates">
                            <div style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.15rem;">
                                <span class="date-label">Start:</span>
                                <span class="date-value">${new Date(voucher.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.25rem;">
                                <span class="date-label">End:</span>
                                <span class="date-value">${new Date(voucher.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="voucher-right-column">
                        <form class="status-form" method="POST" action="update_voucher_status.php">
                            <input type="hidden" name="voucher_id" value="${voucher.voucher_id}">
                            <select name="new_status" class="status-select status-${voucher.is_active.toLowerCase()}" onchange="this.form.submit()">
                                <option value="Active" ${voucher.is_active === 'Active' ? 'selected' : ''}>Active</option>
                                <option value="Inactive" ${voucher.is_active === 'Inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                        </form>
                        <div class="voucher-usage">
                            <span class="usage-label">Usage:</span>
                            <span class="usage-value">${voucher.usage_count}/${voucher.max_usage}</span>
                        </div>
                        <div class="voucher-usage">
                            <span class="usage-label">Created:</span>
                            <span class="usage-value">${new Date(voucher.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        return html;
    }
    
    
    // Set up event listeners - with fallback to page reload
    const enableAJAX = true; // Set to false to disable AJAX and use page reload instead
    
    if (enableAJAX) {
        if (searchInput) {
            searchInput.addEventListener('input', debounce(fetchFilteredData, 300));
        }
        
        if (statusFilter) {
            statusFilter.addEventListener('change', fetchFilteredData);
        }
        
        if (typeFilter) {
            typeFilter.addEventListener('change', fetchFilteredData);
        }
    } else {
        // Fallback: Use form submission instead of AJAX
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                this.submit();
            });
        }
    }
    
    // Status forms now use direct form submission (like update_status.php)
    // No AJAX needed - forms submit directly and redirect back to voucher_list.php
    
    // Clear filters functionality
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            window.location.href = 'voucher_list.php';
        });
    }
    
    // Clear search functionality
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    
    if (searchInput && clearSearchBtn) {
        searchInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                clearSearchBtn.style.display = 'block';
            } else {
                clearSearchBtn.style.display = 'none';
            }
        });
        
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            this.style.display = 'none';
            searchInput.focus();
            fetchFilteredData();
        });
    }
});

// Sort toggle functionality
function handleSortToggle(order) {
    const url = new URL(window.location);
    url.searchParams.set('id_order', order);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>

</body>
</html>

<?php include '../footer.php'; ?>
