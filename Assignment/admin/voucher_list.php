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
    <link rel="stylesheet" href="../css/voucherlist.css">
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
        <button type="button" class="order-btn sortby-order" id="multiSelectBtn" title="Multi-select vouchers" style="background-color: transparent; color: #8B4513; border: 2px solid #8B4513;">
            <i class="fas fa-check-square"></i>
            <span class="action-btn-label">Multi-Select</span>
        </button>
        <button type="button" class="order-btn sortby-order" id="bulkVoucherBtn" title="Change selected vouchers status" style="display: none; background-color: transparent; color: #8B4513; border: 2px solid #8B4513;">
            <i class="fas fa-edit"></i>
            <span class="action-btn-label">Bulk Status</span>
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
                        
                        <!-- Multi-select checkbox (hidden by default) -->
                        <div class="voucher-checkbox" style="display: none;">
                            <input type="checkbox" class="voucher-select-checkbox" value="<?php echo $voucher->voucher_id; ?>" id="voucher_<?php echo $voucher->voucher_id; ?>">
                            <label for="voucher_<?php echo $voucher->voucher_id; ?>"></label>
                        </div>
                        
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
                            <div class="voucher-usage">
                                <span class="usage-label">Usage:</span>
                                <span class="usage-value"><?php echo $voucher->usage_count; ?>/<?php echo $voucher->max_usage; ?></span>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="voucher-right-column">
                            <button class="status-toggle-btn status-<?php echo strtolower($voucher->is_active); ?>" 
                                    data-voucher-id="<?php echo $voucher->voucher_id; ?>"
                                    data-current-status="<?php echo $voucher->is_active; ?>">
                                <?php echo $voucher->is_active; ?>
                            </button>
                            <div class="voucher-dates">
                                <div class="voucher-date">
                                    <span class="date-label">Start:</span>
                                    <span class="date-value"><?php echo date('M j, Y', strtotime($voucher->start_date)); ?></span>
                                </div>
                                <div class="voucher-date">
                                    <span class="date-label">End:</span>
                                    <span class="date-value"><?php echo date('M j, Y', strtotime($voucher->end_date)); ?></span>
                                </div>
                            </div>
                            <div class="voucher-created">
                                <span class="created-label">Created:</span>
                                <span class="created-value"><?php echo date('M j, Y', strtotime($voucher->created_at)); ?></span>
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

<!-- Bulk Status Change Modal -->
<div id="bulkVoucherModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change Status for Selected Vouchers</h3>
            <span class="close" id="closeVoucherModal">&times;</span>
        </div>
        <div class="modal-body">
            <p>You have selected <span id="selectedVoucherCount">0</span> voucher(s).</p>
            <form id="bulkVoucherForm" method="POST" action="bulk_voucher_update.php">
                <div class="form-group">
                    <label for="bulkVoucherStatus">New Status:</label>
                    <select name="bulk_status" id="bulkVoucherStatus" class="form-control" required>
                        <option value="">Select Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" id="cancelVoucherBulk">Cancel</button>
                </div>
            </form>
        </div>
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
            setupStatusToggleButtons(); // Set up status toggle buttons for new content
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
                    
                    <!-- Multi-select checkbox (hidden by default) -->
                    <div class="voucher-checkbox" style="display: none;">
                        <input type="checkbox" class="voucher-select-checkbox" value="${voucher.voucher_id}" id="voucher_${voucher.voucher_id}">
                        <label for="voucher_${voucher.voucher_id}"></label>
                    </div>
                    
                    <!-- Left Column -->
                    <div class="voucher-left-column">
                        <div class="voucher-code-large">${voucher.voucher_code}</div>
                        <div class="voucher-type">Type: ${voucher.voucher_type}</div>
                        <div class="voucher-value">Value: ${voucher.voucher_type === 'Percentage' ? voucher.value + '%' : voucher.voucher_type === 'Free Shipping' ? 'Free Shipping' : 'RM ' + parseFloat(voucher.value).toFixed(2)}</div>
                        <div class="voucher-usage">
                            <span class="usage-label">Usage:</span>
                            <span class="usage-value">${voucher.usage_count}/${voucher.max_usage}</span>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="voucher-right-column">
                        <button class="status-toggle-btn status-${voucher.is_active.toLowerCase()}" 
                                data-voucher-id="${voucher.voucher_id}"
                                data-current-status="${voucher.is_active}">
                            ${voucher.is_active}
                        </button>
                        <div class="voucher-dates">
                            <div class="voucher-date">
                                <span class="date-label">Start:</span>
                                <span class="date-value">${new Date(voucher.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                            </div>
                            <div class="voucher-date">
                                <span class="date-label">End:</span>
                                <span class="date-value">${new Date(voucher.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                            </div>
                        </div>
                        <div class="voucher-created">
                            <span class="created-label">Created:</span>
                            <span class="created-value">${new Date(voucher.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        return html;
    }
    
    // Status toggle button functionality
    function setupStatusToggleButtons() {
        const statusButtons = document.querySelectorAll('.status-toggle-btn');
        
        statusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const voucherId = this.getAttribute('data-voucher-id');
                const currentStatus = this.getAttribute('data-current-status');
                const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
                
                // Disable button and show loading state
                this.disabled = true;
                this.textContent = 'Updating...';
                
                // Make AJAX request to update status
                fetch('update_voucher_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `voucher_id=${voucherId}&new_status=${newStatus}`
                })
                .then(response => response.text())
                .then(data => {
                    // Update button appearance and data
                    this.setAttribute('data-current-status', newStatus);
                    this.textContent = newStatus;
                    this.className = `status-toggle-btn status-${newStatus.toLowerCase()}`;
                    this.disabled = false;
                    
                    // Show success message (optional)
                    console.log(`Voucher ${voucherId} status updated to ${newStatus}`);
                })
                .catch(error => {
                    console.error('Error updating voucher status:', error);
                    this.disabled = false;
                    this.textContent = currentStatus; // Revert text
                    alert('Error updating voucher status. Please try again.');
                });
            });
        });
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
    
    // Set up status toggle buttons
    setupStatusToggleButtons();
    
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
    
    // Multi-select functionality (matching shipping page)
    const multiSelectBtn = document.getElementById('multiSelectBtn');
    const bulkVoucherBtn = document.getElementById('bulkVoucherBtn');
    const bulkVoucherModal = document.getElementById('bulkVoucherModal');
    const closeVoucherModal = document.getElementById('closeVoucherModal');
    const cancelVoucherBulk = document.getElementById('cancelVoucherBulk');
    const selectedVoucherCount = document.getElementById('selectedVoucherCount');
    const bulkVoucherForm = document.getElementById('bulkVoucherForm');
    
    let multiSelectMode = false;
    
    // Toggle multi-select mode
    if (multiSelectBtn) {
        multiSelectBtn.addEventListener('click', function() {
            multiSelectMode = !multiSelectMode;
            const checkboxes = document.querySelectorAll('.voucher-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.style.display = multiSelectMode ? 'flex' : 'none';
            });
            
            if (multiSelectMode) {
                this.classList.add('active');
                this.innerHTML = '<i class="fas fa-times"></i><span class="action-btn-label">Cancel</span>';
            } else {
                this.classList.remove('active');
                this.innerHTML = '<i class="fas fa-check-square"></i><span class="action-btn-label">Multi-Select</span>';
                bulkVoucherBtn.style.display = 'none';
                
                // Uncheck all checkboxes
                checkboxes.forEach(checkbox => {
                    const input = checkbox.querySelector('.voucher-select-checkbox');
                    if (input) input.checked = false;
                });
            }
        });
    }
    
    // Handle checkbox changes
    function handleCheckboxChange() {
        const checkedBoxes = document.querySelectorAll('.voucher-select-checkbox:checked');
        
        if (checkedBoxes.length > 0) {
            bulkVoucherBtn.style.display = 'inline-block';
        } else {
            bulkVoucherBtn.style.display = 'none';
        }
    }
    
    // Add event listeners to checkboxes
    function addCheckboxListeners() {
        const checkboxes = document.querySelectorAll('.voucher-select-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', handleCheckboxChange);
        });
    }
    
    // Initial setup
    addCheckboxListeners();
    
    // Re-add listeners after AJAX updates
    const originalUpdateVouchersDisplay = updateVouchersDisplay;
    updateVouchersDisplay = function(vouchers) {
        originalUpdateVouchersDisplay(vouchers);
        addCheckboxListeners();
    };
    
    // Bulk action button click
    if (bulkVoucherBtn) {
        bulkVoucherBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.voucher-select-checkbox:checked');
            selectedVoucherCount.textContent = checkedBoxes.length;
            bulkVoucherModal.style.display = 'block';
        });
    }
    
    // Close modal
    if (closeVoucherModal) {
        closeVoucherModal.addEventListener('click', function() {
            bulkVoucherModal.style.display = 'none';
        });
    }
    
    if (cancelVoucherBulk) {
        cancelVoucherBulk.addEventListener('click', function() {
            bulkVoucherModal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === bulkVoucherModal) {
            bulkVoucherModal.style.display = 'none';
        }
    });
    
    // Handle form submission
    if (bulkVoucherForm) {
        bulkVoucherForm.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.voucher-select-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one voucher.');
                return;
            }
            
            // Add selected voucher IDs to form
            checkedBoxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = '_bulk[]';
                hiddenInput.value = checkbox.value;
                this.appendChild(hiddenInput);
            });
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
