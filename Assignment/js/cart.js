// Consolidated Cart functionality JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeCart();
    initializeQuantityControls();
    initializeFormHandlers();
    initializeCartNotifications();
    
    // Refresh cart count on page load
    refreshCartCount();
});

function initializeCart() {
    // Cart count will be updated by refreshCartCount() called in DOMContentLoaded
}

function initializeQuantityControls() {
    // Quantity control buttons
    const qtyButtons = document.querySelectorAll('.qty-btn');
    
    qtyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.querySelector(targetId);
            const operation = this.getAttribute('data-op');
            
            if (targetInput) {
                let currentValue = parseInt(targetInput.value) || 1;
                const min = parseInt(targetInput.getAttribute('min')) || 1;
                const max = parseInt(targetInput.getAttribute('max')) || 999;
                
                if (operation === 'plus') {
                    currentValue = Math.min(currentValue + 1, max);
                } else {
                    currentValue = Math.max(currentValue - 1, min);
                }
                
                targetInput.value = currentValue;
                
                // Update the data-qty attribute for order summary calculation
                const prodId = this.getAttribute('data-prod-id');
                if (prodId) {
                    const checkbox = document.querySelector(`input[data-prod-id="${prodId}"].item-checkbox`);
                    if (checkbox) {
                        checkbox.dataset.qty = currentValue;
                    }
                }
                
                // Trigger change event
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Update order summary if this item is selected
                if (typeof updateOrderSummary === 'function') {
                    updateOrderSummary();
                }
            }
        });
    });
    
    // Quantity input validation
    const qtyInputs = document.querySelectorAll('input[type="number"][name="qty"], input[id*="qty"]');
    
    qtyInputs.forEach(input => {
        input.addEventListener('change', function() {
            const value = parseInt(this.value);
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            
            if (isNaN(value) || value < min) {
                this.value = min;
            } else if (value > max) {
                this.value = max;
            }
            
            // Update the data-qty attribute for order summary calculation
            const prodId = this.getAttribute('data-prod-id');
            if (prodId) {
                const checkbox = document.querySelector(`input[data-prod-id="${prodId}"].item-checkbox`);
                if (checkbox) {
                    checkbox.dataset.qty = this.value;
                }
            }
            
            // Update order summary if this item is selected
            if (typeof updateOrderSummary === 'function') {
                updateOrderSummary();
            }
        });
        
        input.addEventListener('input', function() {
            // Real-time validation as user types
            const value = parseInt(this.value);
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            
            if (this.value && (isNaN(value) || value < min || value > max)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '';
            }
            
            // Update the data-qty attribute for order summary calculation
            const prodId = this.getAttribute('data-prod-id');
            if (prodId) {
                const checkbox = document.querySelector(`input[data-prod-id="${prodId}"].item-checkbox`);
                if (checkbox) {
                    checkbox.dataset.qty = this.value;
                }
            }
            
            // Update order summary if this item is selected
            if (typeof updateOrderSummary === 'function') {
                updateOrderSummary();
            }
        });
    });
}

function initializeFormHandlers() {
    // Enhanced form submission handling with AJAX support
    const cartForms = document.querySelectorAll('form[action*="cart_add"], .cart-form');
    
    cartForms.forEach((form, index) => {
        
        // Store the original action URL
        const originalAction = form.getAttribute('action') || '../order/cart_add.php';
        
        // Remove any existing event listeners
        form.onsubmit = null;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            e.stopPropagation();
            
            // Always use the stored original action to avoid [object HTMLInputElement] issues
            handleFormSubmitWithAction(form, originalAction);
            
            return false;
        });
    });
    
    // Buy Now forms should submit normally without JavaScript interference
    const buyNowForms = document.querySelectorAll('form[action*="checkout"]');
    
    // Wishlist form handling
    const wishlistForms = document.querySelectorAll('form[action*="wishlist"]');
    
    wishlistForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const formData = new FormData(this);
            
            if (submitBtn && !submitBtn.disabled) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                
                // Add AJAX header
                formData.append('ajax', '1');
                
                const actionUrl = this.getAttribute('action') || this.getAttribute('data-action') || '../user/wishlist.php';
                
                if (!actionUrl || actionUrl.includes('[object') || typeof actionUrl !== 'string') {
                    console.error('Invalid action URL detected:', actionUrl);
                    showCartNotification('Form error: Invalid action URL', 'error');
                    return;
                }
                
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
                        showCartNotification(data.message, 'success');
                        updateWishlistButton(submitBtn, data.message.includes('Added'));
                    } else {
                        showCartNotification(data.message || 'Failed to update wishlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Wishlist add error:', error);
                    showCartNotification('Failed to add to wishlist. Please try again.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                });
            }
        });
    });
}

function handleFormSubmitWithAction(form, actionUrl) {
    // Sync quantity from visible input to form
    // First look inside the form, then look outside the form
    let qtyInput = form.querySelector('input[type="number"]:not([type="hidden"])');
    
    // If not found inside form, look for common quantity input IDs outside the form
    if (!qtyInput) {
        qtyInput = document.getElementById('detail-qty-loggedin') || 
                  document.getElementById('detail-qty-guest') ||
                  document.querySelector('input[id*="qty"]:not([type="hidden"])');
    }
    
    if (qtyInput) {
        const qty = qtyInput.value || 1;
        
        // Update hidden qty field
        const hiddenQty = form.querySelector('input[name="qty"][type="hidden"]');
        if (hiddenQty) {
            hiddenQty.value = qty;
        }
    }
    
    // Get form data
    const formData = new FormData(form);
    formData.append('ajax', '1');
    
    // Add unique request ID to prevent duplicate processing
    const requestId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    formData.append('request_id', requestId);
    
    // Get submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    
    // Disable button to prevent double submission
    if (submitBtn) {
                submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';
    }
    
    // Make AJAX request with explicit action URL
    fetch(actionUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to see what we're actually receiving
        return response.text().then(text => {
        // Check if response is empty
        if (!text || text.trim() === '') {
            throw new Error('Server returned empty response');
        }
        
        // Check if it's a login required response
        if (text.includes('Please log in to add items to cart')) {
            // Show login modal instead of error
            showLoginModal();
            return null; // Don't process further
        }
            
            // Try to parse as JSON
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                // If it's HTML, it might be an error page
                if (text.includes('<!doctype') || text.includes('<html')) {
                    throw new Error('Server returned HTML error page instead of JSON');
                }
                
                throw new Error('Server returned invalid response format');
            }
        });
    })
    .then(data => {
        // If data is null (login required), don't process further
        if (data === null) {
            return;
        }
        
        if (data.success) {
            // Show success message
            showCartNotification(data.message, 'success');
            
            // Update cart count
            if (data.data && data.data.cart_count !== undefined) {
                updateCartCountDisplay(data.data.cart_count);
            } else if (data.cart_count !== undefined) {
                updateCartCountDisplay(data.cart_count);
            } else {
                // Fallback: refresh cart count from server
                refreshCartCount();
            }
            
            // Update product button
            updateProductButton(form, 'added');
            
            // Preserve quantity input value after successful add to cart
            preserveQuantityInput(form);
        } else {
            // Show error message
            // Check if it's a stock-related error
            if (data.message && data.message.includes('Cannot add more items')) {
                showCartNotification(data.message, 'warning');
            } else {
                showCartNotification(data.message || 'Unknown error occurred', 'error');
            }
        }
    })
    .catch(error => {
        // Show specific error message
        let errorMessage = 'Failed to add item to cart. ';
        if (error.message.includes('empty response')) {
            errorMessage += 'Server returned empty response. Please try again.';
        } else if (error.message.includes('HTML error page')) {
            errorMessage += 'Server error occurred. Please try again.';
        } else if (error.message.includes('invalid response')) {
            errorMessage += 'Server returned invalid response. Please try again.';
        } else {
            errorMessage += 'Please try again.';
        }
        
        showCartNotification(errorMessage, 'error');
    })
    .finally(() => {
        // Re-enable button
        if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
            }
    });
}

// Removed handleFormSubmit function - always use handleFormSubmitWithAction to avoid [object HTMLInputElement] issues

function initializeCartNotifications() {
    // Add notification styles if not already present
    if (!document.querySelector('#cart-notification-styles')) {
        const style = document.createElement('style');
        style.id = 'cart-notification-styles';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .cart-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease-out;
                max-width: 300px;
                font-weight: 500;
            }
            .cart-notification.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .cart-notification.warning {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            .cart-notification.error {
                background: #dc3545;
                color: white;
            }
            .btn-wishlist.in-wishlist {
                background: #e8f5e8 !important;
                border-color: #28a745 !important;
                color: #28a745 !important;
            }
            .btn-wishlist.in-wishlist i {
                color: #e74c3c !important;
            }
        `;
        document.head.appendChild(style);
    }
}

// Utility functions for cart operations
function updateCartCountDisplay(count = null) {
    // Update cart count display in various locations
    const cartCountElements = document.querySelectorAll('#cart-count, .cart-count, .header-cart-count, .item-count');
    
    console.log('updateCartCountDisplay called with count:', count);
    console.log('Found cart count elements:', cartCountElements.length);
    
    if (count !== null && count !== undefined) {
        cartCountElements.forEach((element, index) => {
            console.log(`Updating element ${index}:`, element, 'to count:', count);
            element.textContent = count;
        });
    } else {
        // If no count provided, refresh from server (but only if not already refreshing)
        if (!isRefreshingCartCount) {
            console.log('No count provided, refreshing from server...');
            // Add a small delay to prevent rapid successive calls
            cartCountRefreshTimeout = setTimeout(() => {
                refreshCartCount();
            }, 100);
        } else {
            console.log('Cart count refresh already in progress, skipping updateCartCountDisplay call');
        }
    }
}

// Flag to prevent multiple simultaneous cart count requests
let isRefreshingCartCount = false;
let cartCountRefreshTimeout = null;

// Function to refresh cart count from server
function refreshCartCount() {
    // Prevent multiple simultaneous requests
    if (isRefreshingCartCount) {
        console.log('Cart count refresh already in progress, skipping...');
        return;
    }
    
    // Clear any pending timeout
    if (cartCountRefreshTimeout) {
        clearTimeout(cartCountRefreshTimeout);
        cartCountRefreshTimeout = null;
    }
    
    isRefreshingCartCount = true;
    console.log('Refreshing cart count from server...');
    
    // Determine the correct path based on current location
    let cartCountUrl = '../order/cart_count.php';
    if (window.location.pathname.includes('/userProduct/')) {
        cartCountUrl = '../order/cart_count.php';
    } else if (window.location.pathname.includes('/order/')) {
        cartCountUrl = 'cart_count.php';
    } else if (window.location.pathname.includes('/user/')) {
        cartCountUrl = '../order/cart_count.php';
    }
    
    console.log('Using cart count URL:', cartCountUrl);
    
    fetch(cartCountUrl, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Cart count response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Cart count response data:', data);
        if (data.success) {
            const cartCount = data.data ? data.data.cart_count : data.cart_count;
            updateCartCountDisplay(cartCount);
        }
    })
    .catch(error => {
        console.error('Error refreshing cart count:', error);
    })
    .finally(() => {
        isRefreshingCartCount = false;
    });
}

function updateProductButton(form, action) {
    // Update product button state after adding to cart
    const addBtn = form.querySelector('.btn-add');
    if (addBtn) {
        const originalText = addBtn.textContent;
        addBtn.textContent = action === 'added' ? 'Added!' : 'Add to Cart';
        addBtn.style.background = action === 'added' ? '#28a745' : '';
        
        setTimeout(() => {
            addBtn.textContent = originalText;
            addBtn.style.background = '';
        }, 2000);
    }
}

function updateWishlistButton(button, added) {
    // Update wishlist button state
    const wishlistText = button.querySelector('.wishlist-text');
    const heartIcon = button.querySelector('i');
    const form = button.closest('form');
    const actionInput = form.querySelector('input[name="action"]');
    
    if (added) {
        // Item was added to wishlist
        if (wishlistText) {
            wishlistText.textContent = 'In Wishlist';
        }
        if (heartIcon) {
            heartIcon.style.color = '#e74c3c';
        }
        button.classList.add('in-wishlist');
        button.style.background = '#e8f5e8';
        button.style.borderColor = '#28a745';
        
        // Update action to remove for next click
        if (actionInput) {
            actionInput.value = 'remove';
        }
    } else {
        // Item was removed from wishlist
        if (wishlistText) {
            wishlistText.textContent = 'Wishlist';
        }
        if (heartIcon) {
            heartIcon.style.color = '';
        }
        button.classList.remove('in-wishlist');
        button.style.background = '';
        button.style.borderColor = '';
        
        // Update action to add for next click
        if (actionInput) {
            actionInput.value = 'add';
        }
    }
}

function showCartNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.cart-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `cart-notification ${type}`;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
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

// Cart management functions for cart page
function updateCartItem(prodID, qty) {
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('prodID', prodID);
    formData.append('qty', qty);
    
    fetch('cart_page.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Reload page to show updated cart
        window.location.reload();
    })
    .catch(error => {
        console.error('Update cart error:', error);
        showCartNotification('Failed to update cart item', 'error');
    });
}

function removeCartItem(prodID) {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('prodID', prodID);
    
    fetch('cart_page.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Reload page to show updated cart
        window.location.reload();
    })
    .catch(error => {
        console.error('Remove cart error:', error);
        showCartNotification('Failed to remove cart item', 'error');
    });
}

function clearCart() {
    const formData = new FormData();
    formData.append('action', 'clear');
    
    fetch('cart_page.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Reload page to show empty cart
        window.location.reload();
    })
    .catch(error => {
        console.error('Clear cart error:', error);
        showCartNotification('Failed to clear cart', 'error');
    });
}

// Set quantity from input for cart forms
function setQtyFromInput(form) {
    // Look for visible quantity input first (not hidden)
    const qtyInput = form.querySelector('input[name="qty"]:not([type="hidden"])') || 
                     form.querySelector('input[id*="qty"]:not([type="hidden"])') ||
                     document.getElementById('detail-qty-loggedin') ||
                     document.getElementById('detail-qty-guest');
    
    if (qtyInput) {
        const qty = parseInt(qtyInput.value) || 1;
        const hiddenQty = form.querySelector('input[type="hidden"][name="qty"]');
        if (hiddenQty) {
            hiddenQty.value = qty;
            console.log('Updated hidden qty from visible input:', qtyInput.value, '-> hidden:', hiddenQty.value);
        }
    } else {
        console.log('No visible quantity input found in form');
    }
    return true;
}

// Set quantity for buy now forms
function setQtyForBuyNow(form) {
    // Look for visible quantity input first (not hidden)
    const qtyInput = form.querySelector('input[name="qty"]:not([type="hidden"])') || 
                     form.querySelector('input[id*="qty"]:not([type="hidden"])') ||
                     document.getElementById('detail-qty-loggedin') ||
                     document.getElementById('detail-qty-guest');
    
    if (qtyInput) {
        const qty = parseInt(qtyInput.value) || 1;
        const hiddenQty = form.querySelector('input[type="hidden"][name="qty"]');
        if (hiddenQty) {
            hiddenQty.value = qty;
            console.log('Updated hidden qty for buy now from visible input:', qtyInput.value, '-> hidden:', hiddenQty.value);
        }
    } else {
        console.log('No visible quantity input found in buy now form');
    }
    return true;
}

// Cart page checkbox functionality
function initializeCartCheckboxes() {
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const selectedCountSpan = document.getElementById('selected-count');
    const checkoutCountSpan = document.getElementById('checkout-count');
    const checkoutBtn = document.getElementById('checkout-selected');
    
    if (!selectAllCheckbox || !itemCheckboxes.length) return;
    
    // Update selected count and order summary
    function updateSelectedCount() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        const count = selectedItems.length;
        
        if (selectedCountSpan) {
            selectedCountSpan.textContent = count;
        }
        if (checkoutCountSpan) {
            checkoutCountSpan.textContent = count;
        }
        
        // Enable/disable checkout button
        if (checkoutBtn) {
            checkoutBtn.disabled = count === 0;
            if (count === 0) {
                checkoutBtn.classList.add('disabled');
            } else {
                checkoutBtn.classList.remove('disabled');
            }
        }
        
        // Update order summary
        updateOrderSummary();
    }
    
    // Update order summary based on selected items
    function updateOrderSummary() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        let subtotal = 0;
        let itemCount = 0;
        
        selectedItems.forEach(checkbox => {
            const cartItem = checkbox.closest('.cart-item');
            const price = parseFloat(checkbox.dataset.price) || 0;
            const qty = parseInt(checkbox.dataset.qty) || 1;
            
            subtotal += price * qty;
            itemCount += qty;
        });
        
        // Get shipping fee from the summary
        const shippingElement = document.getElementById('summary-shipping');
        const shippingText = shippingElement ? shippingElement.textContent : 'RM 8.00';
        const shippingFee = parseFloat(shippingText.replace('RM ', '').replace(',', '')) || 8.00;
        
        const total = subtotal + shippingFee;
        
        // Update summary display
        const summaryItemCount = document.getElementById('summary-item-count');
        const summaryItemPlural = document.getElementById('summary-item-plural');
        const summarySubtotal = document.getElementById('summary-subtotal');
        const summaryTotal = document.getElementById('summary-total');
        
        if (summaryItemCount) {
            summaryItemCount.textContent = itemCount;
            summaryItemPlural.textContent = itemCount !== 1 ? 's' : '';
        }
        
        if (summarySubtotal) {
            summarySubtotal.textContent = formatMoney(subtotal);
        }
        
        if (summaryTotal) {
            summaryTotal.textContent = formatMoney(total);
        }
    }
    
    // Format money for display
    function formatMoney(amount) {
        return 'RM ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // Select all functionality
    selectAllCheckbox.addEventListener('change', function() {
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkbox functionality
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Update cart item visual state
            const cartItem = this.closest('.cart-item');
            if (this.checked) {
                cartItem.classList.add('selected');
            } else {
                cartItem.classList.remove('selected');
            }
            
            // Check if all items are selected
            const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
            const noneChecked = Array.from(itemCheckboxes).every(cb => !cb.checked);
            
            if (allChecked) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (noneChecked) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
            
            updateSelectedCount();
        });
    });
    
    // Checkout selected items
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            const selectedItems = Array.from(document.querySelectorAll('.item-checkbox:checked'));
            
            if (selectedItems.length === 0) {
                alert('Please select at least one item to checkout.');
                return;
            }
            
            // Prepare selected items data
            const selectedData = selectedItems.map(checkbox => ({
                prodID: checkbox.dataset.prodId,
                qty: parseInt(checkbox.dataset.qty) || 1,
                price: parseFloat(checkbox.dataset.price) || 0
            }));
            
            // Set the selected items in the hidden form
            const selectedItemsInput = document.getElementById('selected-items');
            if (selectedItemsInput) {
                selectedItemsInput.value = JSON.stringify(selectedData);
            }
            
            // Submit the checkout form
            const checkoutForm = document.getElementById('checkout-form');
            if (checkoutForm) {
                checkoutForm.submit();
            }
        });
    }
    
    // Initialize selected state for all items (default: unselected)
    itemCheckboxes.forEach(checkbox => {
        const cartItem = checkbox.closest('.cart-item');
        checkbox.checked = false; // Ensure all items start unselected
        cartItem.classList.remove('selected');
    });
    
    // Initialize count (should be 0)
    updateSelectedCount();
    
    // Check if cart items are scrollable
    const cartItems = document.querySelector('.cart-items');
    if (cartItems) {
        function checkScrollable() {
            if (cartItems.scrollHeight > cartItems.clientHeight) {
                cartItems.classList.add('scrollable');
            } else {
                cartItems.classList.remove('scrollable');
            }
        }
        
        // Check on load
        checkScrollable();
        
        // Check on resize
        window.addEventListener('resize', checkScrollable);
        
        // Check when items are added/removed
        const observer = new MutationObserver(checkScrollable);
        observer.observe(cartItems, { childList: true, subtree: true });
    }
}

// Initialize cart checkboxes when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCartCheckboxes();
    restoreQuantityInputs();
});

// Preserve quantity input value after successful add to cart
function preserveQuantityInput(form) {
    // Find the quantity input that was used
    let qtyInput = form.querySelector('input[type="number"]:not([type="hidden"])');
    
    // If not found inside form, look for common quantity input IDs outside the form
    if (!qtyInput) {
        qtyInput = document.getElementById('detail-qty-loggedin') || 
                  document.getElementById('detail-qty-guest') ||
                  document.querySelector('input[id*="qty"]:not([type="hidden"])');
    }
    
    if (qtyInput) {
        const currentValue = qtyInput.value;
        
        // Store the current value in sessionStorage for persistence across page refreshes
        const productId = form.querySelector('input[name="prodID"]')?.value;
        if (productId) {
            const storageKey = `qty_${productId}`;
            sessionStorage.setItem(storageKey, currentValue);
        }
        
        // Keep the current value in the input field
        qtyInput.value = currentValue;
    }
}

// Restore quantity input values on page load
function restoreQuantityInputs() {
    // Find all quantity inputs
    const qtyInputs = document.querySelectorAll('input[type="number"]:not([type="hidden"])');
    
    qtyInputs.forEach(input => {
        // Try to find associated product ID
        const form = input.closest('form');
        let productId = null;
        
        if (form) {
            const prodIdInput = form.querySelector('input[name="prodID"]');
            if (prodIdInput) {
                productId = prodIdInput.value;
            }
        }
        
        // If we can't find product ID from form, try to extract from input ID
        if (!productId && input.id) {
            const match = input.id.match(/qty-(\w+)/);
            if (match) {
                productId = match[1];
            }
        }
        
        if (productId) {
            const storageKey = `qty_${productId}`;
            const storedValue = sessionStorage.getItem(storageKey);
            
            if (storedValue && storedValue !== '1') {
                input.value = storedValue;
            }
        }
    });
}