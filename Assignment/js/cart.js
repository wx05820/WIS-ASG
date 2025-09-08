// Consolidated Cart functionality JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Cart JS loaded');
    initializeCart();
    initializeQuantityControls();
    initializeFormHandlers();
    initializeCartNotifications();
    
    // Refresh cart count on page load
    refreshCartCount();
});

function initializeCart() {
    // Update cart count in header if element exists
    updateCartCountDisplay();
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
    console.log('Found cart forms:', cartForms.length);
    
    cartForms.forEach((form, index) => {
        console.log(`Setting up form ${index}:`, form);
        
        // Store the original action URL
        const originalAction = form.getAttribute('action') || '../order/cart_add.php';
        
        // Remove any existing event listeners
        form.onsubmit = null;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            console.log('Form submitted:', form);
            console.log('Form action property:', form.action);
            console.log('Form action type:', typeof form.action);
            
            // Always use the stored original action to avoid [object HTMLInputElement] issues
            console.log('Using stored original action:', originalAction);
            handleFormSubmitWithAction(form, originalAction);
        });
    });
    
    // Buy Now forms should submit normally without JavaScript interference
    const buyNowForms = document.querySelectorAll('form[action*="checkout"]');
    console.log('Found buy now forms:', buyNowForms.length);
    console.log('Buy Now forms will submit normally to checkout.php');
    
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
    console.log('Handling form submit with explicit action URL:', actionUrl);
    
    // Sync quantity from visible input to form
    const qtyInput = form.querySelector('input[type="number"]:not([type="hidden"])');
    if (qtyInput) {
        const qty = qtyInput.value || 1;
        console.log('Syncing quantity from visible input:', qty);
        
        // Update hidden qty field
        const hiddenQty = form.querySelector('input[name="qty"][type="hidden"]');
        if (hiddenQty) {
            hiddenQty.value = qty;
            console.log('Updated hidden qty to:', hiddenQty.value);
        }
    } else {
        console.log('No visible quantity input found in form, using default qty=1');
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
        console.log('Response status:', response.status);
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to see what we're actually receiving
        return response.text().then(text => {
            console.log('Raw response:', text);
            console.log('Response length:', text.length);
            console.log('Response type:', typeof text);
            
        // Check if response is empty
        if (!text || text.trim() === '') {
            console.error('Empty response detected');
            throw new Error('Server returned empty response');
        }
        
        // Check if it's a login required response
        if (text.includes('Please log in to add items to cart')) {
            console.log('Login required response detected');
            // Show login modal instead of error
            showLoginModal();
            return null; // Don't process further
        }
            
            // Try to parse as JSON
            try {
                const data = JSON.parse(text);
                console.log('Parsed JSON data:', data);
                console.log('Success status:', data.success);
                console.log('Message:', data.message);
                return data;
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was not valid JSON:', text.substring(0, 200));
                
                // If it's HTML, it might be an error page
                if (text.includes('<!doctype') || text.includes('<html')) {
                    throw new Error('Server returned HTML error page instead of JSON');
                }
                
                throw new Error('Server returned invalid response format');
            }
        });
    })
    .then(data => {
        console.log('Response data:', data);
        
        // If data is null (login required), don't process further
        if (data === null) {
            return;
        }
        
        if (data.success) {
            console.log('Success response received');
            // Show success message
            showCartNotification(data.message, 'success');
            
            // Update cart count
            if (data.data && data.data.cart_count !== undefined) {
                console.log('Updating cart count to:', data.data.cart_count);
                updateCartCountDisplay(data.data.cart_count);
            } else if (data.cart_count !== undefined) {
                console.log('Updating cart count to:', data.cart_count);
                updateCartCountDisplay(data.cart_count);
            } else {
                // Fallback: refresh cart count from server
                console.log('No cart count in response, refreshing from server...');
                refreshCartCount();
            }
            
            // Update product button
            updateProductButton(form, 'added');
        } else {
            console.log('Error response received:', data);
            // Show error message
            console.error('Server error:', data);
            
            // Check if it's a stock-related error
            if (data.message && data.message.includes('Cannot add more items')) {
                showCartNotification(data.message, 'warning');
            } else {
                showCartNotification(data.message || 'Unknown error occurred', 'error');
            }
        }
    })
    .catch(error => {
        console.error('AJAX error:', error);
        
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
    
    if (count !== null) {
        cartCountElements.forEach((element, index) => {
            console.log(`Updating element ${index}:`, element, 'to count:', count);
            element.textContent = count;
        });
    }
}

// Function to refresh cart count from server
function refreshCartCount() {
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
            updateCartCountDisplay(data.cart_count);
        }
    })
    .catch(error => {
        console.error('Error refreshing cart count:', error);
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
    const qtyInput = form.querySelector('input[name="qty"]');
    if (qtyInput) {
        const qty = parseInt(qtyInput.value) || 1;
        const hiddenQty = form.querySelector('input[type="hidden"][name="qty"]');
        if (hiddenQty) {
            hiddenQty.value = qty;
        }
    }
    return true;
}

// Set quantity for buy now forms
function setQtyForBuyNow(form) {
    const qtyInput = form.querySelector('input[name="qty"]');
    if (qtyInput) {
        const qty = parseInt(qtyInput.value) || 1;
        const hiddenQty = form.querySelector('input[type="hidden"][name="qty"]');
        if (hiddenQty) {
            hiddenQty.value = qty;
        }
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
});