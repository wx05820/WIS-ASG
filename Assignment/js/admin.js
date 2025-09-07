
function autoHideAlerts(duration = 5000) {
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert, .error-message, .success-message');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, duration);
        });
    });
}


function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => { 
        notification.classList.remove('show'); 
        setTimeout(() => notification.remove(), 300); 
    }, 3000);
}

/**
 * Clear form input errors on input
 */
function clearInputErrors() {
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.form-input, input[type="text"], input[type="email"], input[type="password"]');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    this.classList.remove('error');
                    const errorMsg = this.parentNode.querySelector('.error-message');
                    if (errorMsg) errorMsg.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Password toggle functionality
 */
function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    if (!passwordField) return;

    const toggleButton = passwordField.parentNode.querySelector('.password-toggle');
    const eyeIcon = toggleButton.querySelector('.eye-icon');

    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('show', 'fas', 'fa-eye');
        eyeIcon.classList.add('hide', 'fas', 'fa-eye-slash');
        eyeIcon.classList.add('state-change');
        setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('hide', 'fas', 'fa-eye-slash');
        eyeIcon.classList.add('show', 'fas', 'fa-eye');
        eyeIcon.classList.add('state-change');
        setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
    }
}

// ========================================
// SEARCH AND FILTER FUNCTIONS
// ========================================

/**
 * Generic filter function for cards/items
 */
function filterItems(options = {}) {
    const {
        searchInputId = 'searchInput',
        searchFields = ['userid', 'username', 'email', 'name'],
        roleFilterId = 'roleFilter',
        statusFilterId = 'statusFilter',
        itemSelector = '.user-card',
        containerSelector = '.users-table-container',
        noResultsSelector = '.no-users-filtered',
        resultsCounter = true
    } = options;

    const searchTerm = document.getElementById(searchInputId)?.value.toLowerCase().trim() || '';
    const roleFilter = document.getElementById(roleFilterId)?.value || 'all';
    const statusFilter = document.getElementById(statusFilterId)?.value || 'all';
    const items = document.querySelectorAll(itemSelector);
    
    let visibleCount = 0;
    
    items.forEach(item => {
        const searchData = {};
        searchFields.forEach(field => {
            searchData[field] = item.dataset[field]?.toString().toLowerCase() || '';
        });
        
        const role = item.dataset.role || '';
        const status = item.dataset.status || '';
        
        // Check search term
        let matchesSearch = true;
        if (searchTerm) {
            matchesSearch = searchFields.some(field => 
                searchData[field].includes(searchTerm)
            );
        }
        
        // Check role filter
        const matchesRole = roleFilter === 'all' || role === roleFilter;
        
        // Check status filter
        const matchesStatus = statusFilter === 'all' || status === statusFilter;
        
        // Show/hide item based on all filters
        if (matchesSearch && matchesRole && matchesStatus) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Handle no results message
    const container = document.querySelector(containerSelector);
    const noResults = document.querySelector(noResultsSelector);
    
    if (visibleCount === 0) {
        if (container) container.style.display = 'none';
        if (noResults) noResults.style.display = 'block';
    } else {
        if (container) container.style.display = '';
        if (noResults) noResults.style.display = 'none';
    }
    
    // Update results counter
    if (resultsCounter) {
        updateResultsCounter(visibleCount, items.length);
    }
    
    return visibleCount;
}

/**
 * Update results counter
 */
function updateResultsCounter(visibleCount, totalCount) {
    const showingStart = document.getElementById('showing-start');
    const showingEnd = document.getElementById('showing-end');
    const totalSpan = document.getElementById('total-users') || document.getElementById('total-orders');
    
    if (showingStart && showingEnd) {
        if (visibleCount === 0) {
            showingStart.textContent = '0';
            showingEnd.textContent = '0';
        } else {
            showingStart.textContent = '1';
            showingEnd.textContent = visibleCount.toString();
        }
    }
    
    if (totalSpan) {
        totalSpan.textContent = totalCount.toString();
    }
}

/**
 * Clear all filters
 */
function clearFilters(options = {}) {
    const {
        searchInputId = 'searchInput',
        roleFilterId = 'roleFilter',
        statusFilterId = 'statusFilter',
        dateFilterId = null,
        filterFunction = null
    } = options;

    // Clear input values
    const searchInput = document.getElementById(searchInputId);
    const roleFilter = document.getElementById(roleFilterId);
    const statusFilter = document.getElementById(statusFilterId);
    const dateFilter = dateFilterId ? document.getElementById(dateFilterId) : null;

    if (searchInput) searchInput.value = '';
    if (roleFilter) roleFilter.value = 'all';
    if (statusFilter) statusFilter.value = 'all';
    if (dateFilter) dateFilter.value = 'all';
    
    // Clear URL parameters
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.delete('search');
    currentParams.delete('role');
    currentParams.delete('status');
    if (dateFilterId) currentParams.delete('date');
    currentParams.set('page', '1');
    
    // Update URL
    const newURL = window.location.pathname + (currentParams.toString() ? '?' + currentParams.toString() : '');
    window.history.replaceState({}, '', newURL);
    
    // Call filter function if provided
    if (filterFunction) {
        filterFunction();
    }
}


/**
 * Handle sort toggle while preserving filters
 */
function handleSortToggle(newSortOrder, options = {}) {
    const {
        searchInputId = 'searchInput',
        roleFilterId = 'roleFilter',
        statusFilterId = 'statusFilter',
        dateFilterId = null,
        sortParam = 'id_order'
    } = options;

    const currentParams = new URLSearchParams(window.location.search);
    
    // Preserve current filter values
    const searchValue = document.getElementById(searchInputId)?.value.trim() || '';
    const roleValue = document.getElementById(roleFilterId)?.value || 'all';
    const statusValue = document.getElementById(statusFilterId)?.value || 'all';
    const dateValue = dateFilterId ? (document.getElementById(dateFilterId)?.value || 'all') : null;
    
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
    
    if (dateValue && dateValue !== 'all') {
        currentParams.set('date', dateValue);
    } else if (dateValue) {
        currentParams.delete('date');
    }
    
    // Update sort order
    currentParams.set(sortParam, newSortOrder);
    currentParams.set('page', '1'); // Reset to first page
    
    // Navigate to new URL
    const newURL = window.location.pathname + '?' + currentParams.toString();
    window.location.href = newURL;
}



/**
 * Navigate to user detail page
 */
function navigateToUserDetail(userID) {
    window.location.href = '../userdetail.php?userID=' + encodeURIComponent(userID);
}

/**
 * Navigate to order detail page
 */
function navigateToOrderDetail(orderID) {
    window.location.href = 'order_detail.php?orderID=' + encodeURIComponent(orderID);
}

/**
 * View order details (placeholder)
 */
function viewOrderDetails(orderID) {
    // You can implement order details view here
    alert('View order details for: ' + orderID);
    // Or redirect to order details page
    // window.location.href = 'order_details.php?orderID=' + orderID;
}


/**
 * Create enhanced donut chart
 */
function createDonutChart(canvasId, data, options = {}) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    const defaultOptions = {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [{
                data: data.values || [],
                backgroundColor: data.colors || [],
                borderColor: '#fff',
                borderWidth: 4,
                cutout: '65%',
                hoverOffset: 10,
                hoverBorderWidth: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#fff',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${value} orders (${percentage}%)`;
                        }
                    }
                }
            },
            elements: {
                arc: {
                    borderWidth: 4,
                    borderAlign: 'inner'
                }
            }
        }
    };

    return new Chart(ctx, { ...defaultOptions, ...options });
}


/**
 * Initialize search clear button
 */
function initializeSearchClearButton(searchInputId = 'searchInput', clearButtonId = 'clearSearchBtn') {
    const searchInput = document.getElementById(searchInputId);
    const clearButton = document.getElementById(clearButtonId);
    
    if (!searchInput || !clearButton) return;
    
    // Show/hide clear button based on input value
    clearButton.style.display = searchInput.value.trim() ? 'inline-block' : 'none';
    
    searchInput.addEventListener('input', function() {
        clearButton.style.display = searchInput.value.trim() ? 'inline-block' : 'none';
    });
    
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.focus();
        clearButton.style.display = 'none';
        
        // Trigger filter if function exists
        if (window.filterUsers) {
            window.filterUsers();
        } else if (window.filterOrders) {
            window.filterOrders();
        }
    });
}


/**
 * Initialize common admin functionality
 */
function initializeAdmin() {
    // Auto-hide alerts
    autoHideAlerts();
    
    // Clear input errors
    clearInputErrors();
    
    // Initialize search clear buttons
    initializeSearchClearButton('searchInput', 'clearSearchBtn');
    
    // Restore filters from URL on page load
    setTimeout(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const searchValue = urlParams.get('search');
        const roleValue = urlParams.get('role');
        const statusValue = urlParams.get('status');
        const dateValue = urlParams.get('date');
        
        if (searchValue) {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.value = searchValue;
        }
        
        if (roleValue) {
            const roleFilter = document.getElementById('roleFilter');
            if (roleFilter) roleFilter.value = roleValue;
        }
        
        if (statusValue) {
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) statusFilter.value = statusValue;
        }
        
        if (dateValue) {
            const dateFilter = document.getElementById('dateFilter');
            if (dateFilter) dateFilter.value = dateValue;
        }
        
        // Trigger filter if function exists
        if (window.filterUsers) {
            window.filterUsers();
        } else if (window.filterOrders) {
            window.filterOrders();
        }
    }, 100);
}



// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeAdmin();
});

// Export functions for global use
window.AdminJS = {
    autoHideAlerts,
    showNotification,
    clearInputErrors,
    togglePassword,
    filterItems,
    updateResultsCounter,
    clearFilters,
    handleSortToggle,
    navigateToUserDetail,
    navigateToOrderDetail,
    viewOrderDetails,
    createDonutChart,
    initializeSearchClearButton,
    initializeAdmin
};
