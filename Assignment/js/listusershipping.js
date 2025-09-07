/**
 * Consolidated JavaScript for User List and Shipping Pages
 * Combines common functionality from admin/usermanage/list.php and admin/shipping.php
 */

// Common utility functions
const ListUserShipping = {
    
    // Initialize the page based on type
    init: function(pageType = 'users') {
        this.pageType = pageType;
        this.setupEventListeners();
        this.initializeFilters();
    },
    
    // Setup common event listeners
    setupEventListeners: function() {
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        
        // Search input events
        if (searchInput) {
            searchInput.addEventListener('input', () => this.filterItems());
        }
        
        // Clear search button
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => this.clearSearch());
            
            // Show/hide clear button based on input value
            if (searchInput) {
                clearSearchBtn.style.display = searchInput.value.trim() ? 'inline-block' : 'none';
                searchInput.addEventListener('input', function() {
                    clearSearchBtn.style.display = this.value.trim() ? 'inline-block' : 'none';
                });
            }
        }
        
        // Clear filters button
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => this.clearFilters());
        }
        
        // Filter change events
        const statusFilter = document.getElementById('statusFilter');
        const roleFilter = document.getElementById('roleFilter');
        const dateFilter = document.getElementById('dateFilter');
        
        if (statusFilter) statusFilter.addEventListener('change', () => this.filterItems());
        if (roleFilter) roleFilter.addEventListener('change', () => this.filterItems());
        if (dateFilter) dateFilter.addEventListener('change', () => this.filterItems());
    },
    
    // Initialize filters from URL parameters
    initializeFilters: function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Restore search input
        const searchValue = urlParams.get('search') || '';
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = searchValue;
        }
        
        // Restore role filter (users only)
        if (this.pageType === 'users') {
            const roleValue = urlParams.get('role') || 'all';
            const roleFilter = document.getElementById('roleFilter');
            if (roleFilter) {
                roleFilter.value = roleValue;
            }
        }
        
        // Restore status filter
        const statusValue = urlParams.get('status') || 'all';
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.value = statusValue;
        }
        
        // Restore date filter (shipping only)
        if (this.pageType === 'shipping') {
            const dateValue = urlParams.get('date') || 'all';
            const dateFilter = document.getElementById('dateFilter');
            if (dateFilter) {
                dateFilter.value = dateValue;
            }
        }
        
        // Apply filters if any values were restored
        if (searchValue || statusValue !== 'all' || (this.pageType === 'users' && roleValue !== 'all') || (this.pageType === 'shipping' && dateValue !== 'all')) {
            setTimeout(() => {
                this.filterItems();
            }, 100);
        }
    },
    
    // Generic filter function
    filterItems: function() {
        if (this.pageType === 'users') {
            this.filterUsers();
        } else if (this.pageType === 'shipping') {
            this.filterOrders();
        }
    },
    
    // Filter users (from list.php)
    filterUsers: function() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
        const roleFilter = document.getElementById('roleFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const userCards = document.querySelectorAll('.user-card');
        
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
        const usersContainer = document.querySelector('.users-table-container');
        const noUsersFiltered = document.querySelector('.no-users-filtered');
        
        if (visibleCount === 0) {
            if (usersContainer) usersContainer.style.display = 'none';
            if (noUsersFiltered) noUsersFiltered.style.display = 'block';
        } else {
            if (usersContainer) usersContainer.style.display = '';
            if (noUsersFiltered) noUsersFiltered.style.display = 'none';
        }
        
        this.updateResultsCounter(visibleCount, 'users');
    },
    
    // Filter orders (from shipping.php)
    filterOrders: function() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
        const statusFilter = document.getElementById('statusFilter').value;
        const dateFilter = document.getElementById('dateFilter').value;
        const orderCards = document.querySelectorAll('.order-card');
        
        let visibleCount = 0;
        
        orderCards.forEach(card => {
            const orderid = card.dataset.orderid.toLowerCase();
            const userid = card.dataset.userid.toLowerCase();
            const status = card.dataset.status;
            const date = card.dataset.date;
            
            // Check search term (only Order ID and User ID)
            let matchesSearch = true;
            if (searchTerm) {
                matchesSearch = orderid.includes(searchTerm) || 
                               userid.includes(searchTerm);
            }
            
            // Check status filter
            let matchesStatus = true;
            if (statusFilter !== 'all') {
                matchesStatus = status === statusFilter;
            }
            
            // Check date filter
            let matchesDate = true;
            if (dateFilter !== 'all') {
                const orderDate = new Date(date);
                const today = new Date();
                const daysDiff = Math.floor((today - orderDate) / (1000 * 60 * 60 * 24));
                
                switch (dateFilter) {
                    case 'today':
                        matchesDate = daysDiff === 0;
                        break;
                    case 'week':
                        matchesDate = daysDiff <= 7;
                        break;
                    case 'month':
                        matchesDate = daysDiff <= 30;
                        break;
                }
            }
            
            // Show/hide card based on all filters
            if (matchesSearch && matchesStatus && matchesDate) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        const noOrdersDiv = document.getElementById('noDataMessage');
        const ordersContainer = document.querySelector('.orders-table-container');
        
        if (visibleCount === 0) {
            if (noOrdersDiv) noOrdersDiv.style.display = 'block';
            if (ordersContainer) ordersContainer.style.display = 'none';
        } else {
            if (noOrdersDiv) noOrdersDiv.style.display = 'none';
            if (ordersContainer) ordersContainer.style.display = 'grid';
        }
        
        this.updateResultsCounter(visibleCount, 'shipping');
    },
    
    // Update results counter
    updateResultsCounter: function(visibleCount, pageType) {
        const totalItems = pageType === 'users' ? 
            document.querySelectorAll('.user-card').length : 
            document.querySelectorAll('.order-card').length;
            
        const showingStart = document.getElementById('showing-start');
        const showingEnd = document.getElementById('showing-end');
        const totalItemsSpan = document.getElementById(pageType === 'users' ? 'total-users' : 'total-orders');
        
        if (showingStart && showingEnd && totalItemsSpan) {
            if (visibleCount === 0) {
                showingStart.textContent = '0';
                showingEnd.textContent = '0';
            } else {
                showingStart.textContent = '1';
                showingEnd.textContent = visibleCount.toString();
            }
            totalItemsSpan.textContent = totalItems.toString();
        }
    },
    
    // Clear search only
    clearSearch: function() {
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        
        if (searchInput) {
            searchInput.value = '';
            if (clearSearchBtn) clearSearchBtn.style.display = 'none';
            this.filterItems();
        }
    },
    
    // Clear all filters
    clearFilters: function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const roleFilter = document.getElementById('roleFilter');
        const dateFilter = document.getElementById('dateFilter');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = 'all';
        if (roleFilter) roleFilter.value = 'all';
        if (dateFilter) dateFilter.value = 'all';
        if (clearSearchBtn) clearSearchBtn.style.display = 'none';
        
        this.filterItems();
    },
    
    // Handle sort toggle (URL-based for server-side sorting)
    handleSortToggle: function(newSortOrder) {
        const currentParams = new URLSearchParams(window.location.search);
        
        // Preserve current filter values from form inputs
        const searchValue = document.getElementById('searchInput').value.trim();
        const statusValue = document.getElementById('statusFilter').value;
        
        // Update filter parameters
        if (searchValue) {
            currentParams.set('search', searchValue);
        } else {
            currentParams.delete('search');
        }
        
        if (statusValue !== 'all') {
            currentParams.set('status', statusValue);
        } else {
            currentParams.delete('status');
        }
        
        // Add role filter for users page
        if (this.pageType === 'users') {
            const roleValue = document.getElementById('roleFilter').value;
            if (roleValue !== 'all') {
                currentParams.set('role', roleValue);
            } else {
                currentParams.delete('role');
            }
        }
        
        // Add date filter for shipping page
        if (this.pageType === 'shipping') {
            const dateValue = document.getElementById('dateFilter').value;
            if (dateValue !== 'all') {
                currentParams.set('date', dateValue);
            } else {
                currentParams.delete('date');
            }
        }
        
        // Update sort order
        currentParams.set('id_order', newSortOrder);
        currentParams.set('page', '1'); // Reset to first page
        
        // Navigate to new URL
        const newURL = window.location.pathname + '?' + currentParams.toString();
        window.location.href = newURL;
    },
    
    // Navigate to user detail page
    navigateToUserDetail: function(userID) {
        window.location.href = '../userdetail.php?userID=' + encodeURIComponent(userID);
    }
};

// Make functions globally available
window.handleSortToggle = function(newSortOrder) {
    ListUserShipping.handleSortToggle(newSortOrder);
};

window.navigateToUserDetail = function(userID) {
    ListUserShipping.navigateToUserDetail(userID);
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Determine page type based on URL or specific elements
    const pageType = window.location.pathname.includes('shipping.php') ? 'shipping' : 'users';
    ListUserShipping.init(pageType);
});
