// Enhanced Cart functionality JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeCart();
    initializeQuantityControls();
    initializeFormHandlers();
    initializeCartNotifications();
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
                
                // Trigger change event
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
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
        });
    });
}

function initializeFormHandlers() {
    // Enhanced form submission handling with AJAX support
    const cartForms = document.querySelectorAll('form[action*="cart_add"]');
    
    cartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Update hidden qty field from visible qty input
            const qtyInput = this.querySelector('input[id*="qty"]:not([name="qty"])') || 
                           document.getElementById('detail-qty-loggedin') || 
                           document.getElementById('detail-qty-guest');
            const hiddenQty = this.querySelector('input[name="qty"]');
            if (qtyInput && hiddenQty) {
                const val = parseInt(qtyInput.value) || 1;
                hiddenQty.value = Math.max(1, val);
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const formData = new FormData(this);
            
            if (submitBtn && !submitBtn.disabled) {
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                
                if (submitBtn.classList.contains('btn-add')) {
                    submitBtn.textContent = 'Adding...';
                }
                
                // Add AJAX header
                formData.append('ajax', '1');
                
                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showCartNotification(data.message, 'success');
                        updateCartCountDisplay(data.data.cart_count);
                        
                        // Update cart count in header
                        const headerCartCount = document.querySelector('.header-cart-count');
                        if (headerCartCount) {
                            headerCartCount.textContent = data.data.cart_count || 0;
                        }
                        
                        // If on product list page, update the specific product button
                        const productCard = form.closest('.product-card');
                        if (productCard && data.data.action_type === 'added') {
                            updateProductButton(productCard, 'added');
                        }
                    } else {
                        showCartNotification(data.message, 'error');
                        if (data.data && data.data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.data.redirect;
                            }, 2000);
                        }
                    }
                })
                .catch(error => {
                    console.error('Cart add error:', error);
                    showCartNotification('Failed to add item to cart. Please try again.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    submitBtn.textContent = originalText;
                });
            }
        });
    });
    
    // Regular form submission for checkout and other forms
    const otherForms = document.querySelectorAll('form[action*="checkout"]:not([action*="cart_add"])');
    
    otherForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                
                if (submitBtn.classList.contains('btn-checkout')) {
                    submitBtn.textContent = 'Processing...';
                }
                
                // Re-enable after 5 seconds in case of error
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 5000);
            }
        });
    });
}

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
                background: #28a745;
                color: white;
            }
            .cart-notification.error {
                background: #dc3545;
                color: white;
            }
        `;
        document.head.appendChild(style);
    }
}

// Utility functions for cart operations
function updateCartCountDisplay(count = null) {
    // Update cart count display in various locations
    const cartCountElements = document.querySelectorAll('.cart-count, .header-cart-count, .item-count');
    
    if (count !== null) {
        cartCountElements.forEach(element => {
            element.textContent = count;
        });
    }
}

function updateProductButton(productCard, action) {
    // Update product button state after adding to cart
    const addBtn = productCard.querySelector('.btn-add');
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

function showCartNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.cart-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `cart-notification ${type}`;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
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

// Handle form submission with better UX
function handleFormSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn && !submitBtn.disabled) {
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        if (submitBtn.classList.contains('btn-add')) {
            submitBtn.textContent = 'Adding...';
        } else if (submitBtn.classList.contains('btn-checkout')) {
            submitBtn.textContent = 'Processing...';
        }
        
        // Re-enable after 5 seconds in case of error
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            submitBtn.textContent = originalText;
        }, 5000);
    }
    return true;
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
    return handleFormSubmit(form);
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
    return handleFormSubmit(form);
}
