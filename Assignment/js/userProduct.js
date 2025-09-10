// User Product functionality JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize product page functionality
    initializeProductPage();
    initializeProductList();
    initializeWishlistForms();
});

// Also run on window load to ensure everything is ready
window.addEventListener('load', function() {
    console.log('Window loaded, re-initializing quantity controls...');
    initializeQuantityControls();
});

function initializeProductPage() {
    // Product detail page specific functionality
    const productDetailPage = document.querySelector('.product-detail');
    if (!productDetailPage) return;
    
    // Image gallery functionality
    const mainImage = document.querySelector('.main-image img');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    if (mainImage && thumbnails.length > 0) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const newSrc = this.src;
                if (mainImage.src !== newSrc) {
                    mainImage.src = newSrc;
                    
                    // Update active thumbnail
                    thumbnails.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    }
    
    // Quantity controls for product detail page
    const qtyInput = document.getElementById('detail-qty');
    if (qtyInput) {
        // Ensure quantity input works properly
        qtyInput.addEventListener('change', function() {
            const value = parseInt(this.value);
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            
            if (isNaN(value) || value < min) {
                this.value = min;
            } else if (value > max) {
                this.value = max;
            }
        });
    }
    
    // Add to Cart form handling for product detail page
    initializeAddToCartForms();
}

function initializeProductList() {
    // Product list page specific functionality
    const productListPage = document.querySelector('.product-list');
    if (!productListPage) return;
    
    // Search functionality
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // Real-time search could be implemented here
            console.log('Search query:', this.value);
        });
    }
    
    // Filter functionality
    const filterSelects = document.querySelectorAll('select[name="category"], select[name="sort"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Auto-submit form when filter changes
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Product card hover effects
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add to Cart form handling
    initializeAddToCartForms();
    
    // Initialize quantity controls for product list
    initializeQuantityControls();
}

// Sync function for Buy Now forms
function setQtyForBuyNow(form) {
    // Look for visible quantity input within the form first (for product list)
    let qtyInput = form.querySelector('input[type="number"]:not([name="qty"])');
    
    // If not found within form, look for quantity input in the associated Add to Cart form
    if (!qtyInput) {
        // Get the product ID from the Buy Now form
        const prodID = form.querySelector('input[name="prodID"]').value;
        // Look for the Add to Cart form with the same product ID
        const addToCartForm = document.getElementById('btn-add-' + prodID);
        if (addToCartForm) {
            qtyInput = addToCartForm.querySelector('input[type="number"]:not([name="qty"])');
        }
    }
    
    // If still not found, look for product detail page quantity input
    if (!qtyInput) {
        qtyInput = document.getElementById('detail-qty-loggedin');
    }
    
    const hiddenQty = form.querySelector('input[name="qty"]');
    
    console.log('setQtyForBuyNow - qtyInput:', qtyInput);
    console.log('setQtyForBuyNow - hiddenQty:', hiddenQty);
    
    if (qtyInput && hiddenQty) {
        const val = parseInt(qtyInput.value) || 1;
        hiddenQty.value = Math.max(1, val);
        console.log('setQtyForBuyNow: synced', val, 'to hidden input');
        return true;
    } else {
        console.error('setQtyForBuyNow: Could not find qtyInput or hiddenQty');
        console.log('Available inputs in form:', form.querySelectorAll('input'));
    }
    return false;
}

// Sync function similar to setQtyForBuyNow
function setQtyForAddToCart(form) {
    // Look for visible quantity input within the form first (for product list)
    let qtyInput = form.querySelector('input[type="number"]:not([name="qty"])');
    
    // If not found within form, look for product detail page quantity input
    if (!qtyInput) {
        qtyInput = document.getElementById('detail-qty-loggedin');
    }
    
    const hiddenQty = form.querySelector('input[name="qty"]');
    
    console.log('setQtyForAddToCart - qtyInput:', qtyInput);
    console.log('setQtyForAddToCart - hiddenQty:', hiddenQty);
    
    if (qtyInput && hiddenQty) {
        const val = parseInt(qtyInput.value) || 1;
        hiddenQty.value = Math.max(1, val);
        console.log('setQtyForAddToCart: synced', val, 'to hidden input');
        return true;
    } else {
        console.error('setQtyForAddToCart: Could not find qtyInput or hiddenQty');
        console.log('Available inputs in form:', form.querySelectorAll('input'));
    }
    return false;
}

function initializeAddToCartForms() {
    // Prevent multiple initializations
    if (window.addToCartInitialized) {
        console.log('Add to cart forms already initialized, skipping...');
        return;
    }
    window.addToCartInitialized = true;
    
    // Handle all cart forms (both product list and product detail)
    const cartForms = document.querySelectorAll('.cart-form');
    console.log('Initializing', cartForms.length, 'cart forms');
    
    cartForms.forEach(form => {
        // Remove any existing event listeners to prevent duplicates
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        newForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            e.stopImmediatePropagation(); // Prevent other event listeners
            
            console.log('Form submit event triggered');
            
            // Use the same sync function as Buy Now
            const syncResult = setQtyForAddToCart(this);
            console.log('Sync result:', syncResult);
            
            // Force sync visible input with hidden input before processing
            const visibleQtyInput = this.querySelector('input[type="number"]');
            const hiddenQtyInput = this.querySelector('input[name="qty"]');
            
            if (visibleQtyInput && hiddenQtyInput) {
                const visibleValue = parseInt(visibleQtyInput.value) || 1;
                hiddenQtyInput.value = visibleValue;
                console.log('Force synced hidden input with visible input:', visibleValue);
            }
            
            const formData = new FormData(this);
            const prodID = formData.get('prodID');
            
            // Get quantity from the visible input first, then fallback to hidden input
            let qty = 1;
            
            if (visibleQtyInput) {
                qty = parseInt(visibleQtyInput.value) || 1;
            } else {
                qty = parseInt(formData.get('qty')) || 1;
            }
            
            const button = this.querySelector('button[type="submit"]');
            
            if (!prodID) {
                showError('Product ID is missing');
                return;
            }
            
            // Get the action URL properly
            let actionUrl = this.getAttribute('action');
            if (!actionUrl) {
                actionUrl = this.action;
            }
            
            // Debug logging
            console.log('Form action URL:', actionUrl);
            console.log('Product ID:', prodID);
            console.log('Quantity:', qty);
            console.log('Visible input value:', visibleQtyInput ? visibleQtyInput.value : 'N/A');
            console.log('Hidden input value:', hiddenQtyInput ? hiddenQtyInput.value : 'N/A');
            console.log('Form data qty:', formData.get('qty'));
            
            // Log all form data
            console.log('All form data:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }
            
            
            if (!actionUrl || actionUrl === 'undefined' || actionUrl.includes('[object')) {
                showError('Invalid form action URL');
                return;
            }
            
            // Check if already processing to prevent duplicate requests
            if (this.dataset.processing === 'true') {
                console.log('Form already processing, ignoring duplicate submission');
                return;
            }
            
            // Mark form as processing
            this.dataset.processing = 'true';
            
            // Disable button and show loading state
            if (button) {
                button.disabled = true;
                button.textContent = 'Adding...';
            }
            
            // Add request ID to prevent duplicates
            const requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            formData.append('request_id', requestId);
            
            // Make AJAX request
            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    updateCartCount(data.data.cart_count);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('Add to cart error:', error);
                showError('Failed to add item to cart. Please try again.');
            })
            .finally(() => {
                // Reset processing flag
                this.dataset.processing = 'false';
                
                // Re-enable button
                if (button) {
                    button.disabled = false;
                    button.textContent = 'Add to Cart';
                }
            });
        });
    });
}

function initializeQuantityControls() {
    // Prevent multiple initializations
    if (window.quantityControlsInitialized) {
        console.log('Quantity controls already initialized, skipping...');
        return;
    }
    window.quantityControlsInitialized = true;
    
    console.log('Initializing quantity controls...');
    
    // Use event delegation to handle dynamically added buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('qty-btn')) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('Quantity button clicked via delegation:', e.target);
            
            const targetId = e.target.getAttribute('data-target');
            const qtyInput = document.querySelector(targetId);
            
            console.log('Target ID:', targetId);
            console.log('Quantity input found:', qtyInput);
            
            if (!qtyInput) {
                console.error('Quantity input not found for target:', targetId);
                return false;
            }
            
            const currentValue = parseInt(qtyInput.value) || 1;
            const min = parseInt(qtyInput.getAttribute('min')) || 1;
            const max = parseInt(qtyInput.getAttribute('max')) || 999;
            const operation = e.target.getAttribute('data-op');
            
            console.log('Current value:', currentValue, 'Min:', min, 'Max:', max, 'Operation:', operation);
            
            let newValue = currentValue;
            
            if (operation === 'plus') {
                newValue = Math.min(currentValue + 1, max);
            } else {
                newValue = Math.max(currentValue - 1, min);
            }
            
            console.log('New value:', newValue);
            
            if (newValue !== currentValue) {
                qtyInput.value = newValue;
                
                // Update the hidden quantity input in the same form
                const form = qtyInput.closest('form');
                if (form) {
                    const hiddenQtyInput = form.querySelector('input[name="qty"]');
                    if (hiddenQtyInput) {
                        hiddenQtyInput.value = newValue;
                        console.log('Updated hidden input to:', newValue);
                    } else {
                        console.error('Hidden qty input not found in form');
                    }
                } else {
                    console.error('Form not found for qty input');
                }
            }
            
            return false;
        }
    });
    
    // Also handle direct button clicks as backup
    const qtyButtons = document.querySelectorAll('.qty-btn');
    console.log('Found quantity buttons:', qtyButtons.length);
    
    qtyButtons.forEach((button, index) => {
        console.log(`Button ${index}:`, button);
        
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('Quantity button clicked directly:', this);
            
            const targetId = this.getAttribute('data-target');
            const qtyInput = document.querySelector(targetId);
            
            console.log('Target ID:', targetId);
            console.log('Quantity input found:', qtyInput);
            
            if (!qtyInput) {
                console.error('Quantity input not found for target:', targetId);
                return false;
            }
            
            const currentValue = parseInt(qtyInput.value) || 1;
            const min = parseInt(qtyInput.getAttribute('min')) || 1;
            const max = parseInt(qtyInput.getAttribute('max')) || 999;
            const operation = this.getAttribute('data-op');
            
            console.log('Current value:', currentValue, 'Min:', min, 'Max:', max, 'Operation:', operation);
            
            let newValue = currentValue;
            
            if (operation === 'plus') {
                newValue = Math.min(currentValue + 1, max);
            } else {
                newValue = Math.max(currentValue - 1, min);
            }
            
            console.log('New value:', newValue);
            
            if (newValue !== currentValue) {
                qtyInput.value = newValue;
                
                // Update the hidden quantity input in the same form
                const form = qtyInput.closest('form');
                if (form) {
                    const hiddenQtyInput = form.querySelector('input[name="qty"]');
                    if (hiddenQtyInput) {
                        hiddenQtyInput.value = newValue;
                        console.log('Updated hidden input to:', newValue);
                    } else {
                        console.error('Hidden qty input not found in form');
                    }
                } else {
                    console.error('Form not found for qty input');
                }
            }
            
            return false;
        });
    });
    
    // Handle quantity input changes
    const qtyInputs = document.querySelectorAll('input[type="number"]');
    qtyInputs.forEach(input => {
        input.addEventListener('change', function() {
            const value = parseInt(this.value) || 1;
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            
            if (value < min) {
                this.value = min;
            } else if (value > max) {
                this.value = max;
            }
            
            // Update the hidden quantity input in the same form
            const form = this.closest('form');
            if (form) {
                const hiddenQtyInput = form.querySelector('input[name="qty"]');
                if (hiddenQtyInput) {
                    hiddenQtyInput.value = this.value;
                    console.log('Updated hidden input via change event to:', this.value);
                } else {
                    console.error('Hidden qty input not found in form for change event');
                }
            } else {
                console.error('Form not found for qty input change event');
            }
        });
        
        // Also handle input event for real-time updates
        input.addEventListener('input', function() {
            const form = this.closest('form');
            if (form) {
                const hiddenQtyInput = form.querySelector('input[name="qty"]');
                if (hiddenQtyInput) {
                    hiddenQtyInput.value = this.value;
                    console.log('Updated hidden input via input event to:', this.value);
                }
            }
        });
    });
}

function updateCartCount(count) {
    // Update cart count in header
    const cartCountElements = document.querySelectorAll('#cart-count, .cart-count, .header-cart-count, .item-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
    });
}

// Utility functions
function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.product-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `product-notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        padding: 12px 20px;
        border-radius: 5px;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease-out;
        max-width: 300px;
        word-wrap: break-word;
    `;
    
    // Add animation CSS if not already present
    if (!document.querySelector('#product-notification-styles')) {
        const style = document.createElement('style');
        style.id = 'product-notification-styles';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    // Remove after 4 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 4000);
}

// Form validation
function validateProductForm(form) {
    const qtyInput = form.querySelector('input[name="qty"]');
    if (qtyInput) {
        const value = parseInt(qtyInput.value);
        const min = parseInt(qtyInput.getAttribute('min')) || 1;
        const max = parseInt(qtyInput.getAttribute('max')) || 999;
        
        if (isNaN(value) || value < min || value > max) {
            showError(`Please enter a valid quantity between ${min} and ${max}`);
            return false;
        }
    }
    
    return true;
}

// Wishlist functionality
function toggleWishlist(productId) {
    // This would make an AJAX call to toggle wishlist
    console.log('Toggle wishlist for product:', productId);
}

// Initialize wishlist forms with AJAX
function initializeWishlistForms() {
    const wishlistForms = document.querySelectorAll('.wishlist-form');
    
    wishlistForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleWishlistAction(this);
        });
    });
    
    // Sync wishlist states on page load
    syncWishlistStates();
}

// Sync wishlist button states with actual database state
function syncWishlistStates() {
    const wishlistForms = document.querySelectorAll('.wishlist-form');
    const productIds = Array.from(wishlistForms).map(form => form.querySelector('input[name="prodID"]').value);
    
    console.log('Syncing wishlist states for products:', productIds);
    
    if (productIds.length === 0) return;
    
    // Check current wishlist status for all products
    fetch('../user/wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=check_status&product_ids=' + encodeURIComponent(JSON.stringify(productIds))
    })
    .then(response => response.json())
    .then(data => {
        console.log('Wishlist sync response:', data);
        if (data.ok && data.wishlist_items) {
            console.log('Items in wishlist:', data.wishlist_items);
            wishlistForms.forEach(form => {
                const productId = form.querySelector('input[name="prodID"]').value;
                const isInWishlist = data.wishlist_items.includes(productId);
                const button = form.querySelector('.btn-wishlist');
                const icon = button.querySelector('i');
                const text = button.querySelector('.wishlist-text');
                const actionInput = form.querySelector('input[name="action"]');
                
                console.log(`Product ${productId}: isInWishlist = ${isInWishlist}`);
                
                if (isInWishlist) {
                    button.classList.add('in-wishlist');
                    button.classList.remove('btn-secondary');
                    icon.style.color = '#ffffff';
                    text.textContent = 'In Wishlist';
                    if (actionInput) actionInput.value = 'remove';
                    console.log(`Updated product ${productId} to IN wishlist state`);
                } else {
                    button.classList.remove('in-wishlist');
                    button.classList.add('btn-secondary');
                    icon.style.color = '';
                    text.textContent = 'Wishlist';
                    if (actionInput) actionInput.value = 'add';
                    console.log(`Updated product ${productId} to NOT in wishlist state`);
                }
            });
        }
    })
    .catch(error => {
        console.log('Could not sync wishlist states:', error);
    });
}

// Handle wishlist add/remove via AJAX
function handleWishlistAction(form) {
    const formData = new FormData(form);
    const productId = formData.get('prodID');
    const action = formData.get('action');
    const button = form.querySelector('.btn-wishlist');
    const icon = button.querySelector('i');
    const text = button.querySelector('.wishlist-text');
    
    // Prevent duplicate submissions
    if (button.disabled) {
        console.log('Button already processing, ignoring duplicate click');
        return;
    }
    
    // Debug logging
    console.log('Wishlist form action:', form.action);
    console.log('Form action attribute:', form.getAttribute('action'));
    console.log('Product ID:', productId);
    console.log('Action:', action);
    
    // Check if action input exists
    const actionInput = form.querySelector('input[name="action"]');
    console.log('Action input found:', !!actionInput);
    if (actionInput) {
        console.log('Current action input value:', actionInput.value);
    }
    
    // Show loading state
    button.disabled = true;
    button.style.opacity = '0.7';
    const originalText = text.textContent;
    text.textContent = action === 'add' ? 'Adding...' : 'Removing...';
    
    // Add processing class to prevent multiple clicks
    button.classList.add('processing');
    
    // Make AJAX request
    const actionUrl = form.getAttribute('action') || form.action || '../user/wishlist.php';
    console.log('Using action URL:', actionUrl);
    fetch(actionUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            // Update button state based on the actual action performed
            const actualAction = data.action || action;
            
            if (actualAction === 'add') {
                button.classList.add('in-wishlist');
                button.classList.remove('btn-secondary');
                icon.style.color = '#ffffff';
                text.textContent = 'In Wishlist';
                
                // Update form action for next click
                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput) {
                    actionInput.value = 'remove';
                    console.log('Updated action to remove for product:', productId);
                }
            } else {
                button.classList.remove('in-wishlist');
                button.classList.add('btn-secondary');
                icon.style.color = '';
                text.textContent = 'Wishlist';
                
                // Update form action for next click
                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput) {
                    actionInput.value = 'add';
                    console.log('Updated action to add for product:', productId);
                }
            }
            
            // Show success message
            showNotification(data.message, 'success');
            
            // Add visual feedback
            button.style.transform = 'scale(1.1)';
            setTimeout(() => {
                button.style.transform = 'scale(1)';
            }, 200);
            
            // Add a small delay to prevent rapid successive clicks
            setTimeout(() => {
                button.disabled = false;
                button.classList.remove('processing');
            }, 1000);
            
        } else {
            // Handle error case - check if we should auto-switch to remove
            if (data.suggested_action === 'remove' && action === 'add') {
                // Item is already in wishlist, switch to remove mode
                button.classList.add('in-wishlist');
                button.classList.remove('btn-secondary');
                icon.style.color = '#ffffff';
                text.textContent = 'In Wishlist';
                
                // Update form action for next click
                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput) {
                    actionInput.value = 'remove';
                    console.log('Auto-switched to remove mode for product:', productId);
                }
                
                showNotification('Item is already in your wishlist', 'info');
            } else {
                // Show error message
                showNotification(data.message, 'error');
                text.textContent = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Wishlist error:', error);
        showNotification('An error occurred. Please try again.', 'error');
        text.textContent = originalText;
    })
    .finally(() => {
        button.disabled = false;
        button.style.opacity = '1';
        button.classList.remove('processing');
    });
}

// Show notification messages
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    // Set background color based on type
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
    } else if (type === 'error') {
        notification.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
    } else {
        notification.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
    }
    
    // Add to page
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Quick view functionality
function showQuickView(productId) {
    // This would show a quick view modal
    console.log('Show quick view for product:', productId);
}

// Debug function to check wishlist states
function debugWishlistStates() {
    console.log('=== WISHLIST DEBUG ===');
    const wishlistForms = document.querySelectorAll('.wishlist-form');
    console.log('Found wishlist forms:', wishlistForms.length);
    
    wishlistForms.forEach((form, index) => {
        const productId = form.querySelector('input[name="prodID"]').value;
        const action = form.querySelector('input[name="action"]').value;
        const button = form.querySelector('.btn-wishlist');
        const text = button.querySelector('.wishlist-text').textContent;
        const hasInWishlistClass = button.classList.contains('in-wishlist');
        
        console.log(`Form ${index + 1}:`, {
            productId,
            action,
            text,
            hasInWishlistClass,
            buttonClasses: button.className
        });
    });
    
    // Force sync
    console.log('Forcing wishlist sync...');
    syncWishlistStates();
}

// Make debug function available globally
window.debugWishlistStates = debugWishlistStates;

// Function to force clear all wishlist states (for debugging)
function clearWishlistStates() {
    console.log('Clearing all wishlist states...');
    const wishlistForms = document.querySelectorAll('.wishlist-form');
    
    wishlistForms.forEach(form => {
        const button = form.querySelector('.btn-wishlist');
        const icon = button.querySelector('i');
        const text = button.querySelector('.wishlist-text');
        const actionInput = form.querySelector('input[name="action"]');
        
        button.classList.remove('in-wishlist');
        button.classList.add('btn-secondary');
        icon.style.color = '';
        text.textContent = 'Wishlist';
        if (actionInput) actionInput.value = 'add';
    });
    
    console.log('All wishlist states cleared. Now syncing...');
    syncWishlistStates();
}

// Make clear function available globally
window.clearWishlistStates = clearWishlistStates;

// Login prompt functionality
function showLoginPrompt() {
    // Create a more prominent login prompt
    const loginModal = document.createElement('div');
    loginModal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
    `;
    
    modalContent.innerHTML = `
        <div style="font-size: 48px; color: #8B4513; margin-bottom: 20px;">
            <i class="fas fa-lock"></i>
        </div>
        <h3 style="color: #8B4513; margin-bottom: 15px;">Login Required</h3>
        <p style="color: #666; margin-bottom: 25px; line-height: 1.5;">
            Please log in to add items to your cart or make a purchase.
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button onclick="window.location.href='../user/login.php'" 
                    style="background: #8B4513; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button onclick="closeLoginModal()" 
                    style="background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer;">
                Cancel
            </button>
        </div>
    `;
    
    // Add CSS animations if not already present
    if (!document.querySelector('#login-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'login-modal-styles';
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideIn {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    loginModal.appendChild(modalContent);
    document.body.appendChild(loginModal);
    
    // Close modal when clicking outside
    loginModal.addEventListener('click', function(e) {
        if (e.target === loginModal) {
            closeLoginModal();
        }
    });
    
    // Store reference for closing
    window.currentLoginModal = loginModal;
}

function closeLoginModal() {
    if (window.currentLoginModal) {
        window.currentLoginModal.remove();
        window.currentLoginModal = null;
    }
}
