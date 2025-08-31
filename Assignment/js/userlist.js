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