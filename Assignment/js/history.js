document.addEventListener('DOMContentLoaded', function() {
    initializeOrderHistory();
});

function initializeOrderHistory() {
    initToggleButtons();
    initFormHandlers();
    initUtilityFunctions();
    checkRefundButtonStates();
}

function initToggleButtons() {
    document.querySelectorAll('.toggle-items').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const icon = this.querySelector('i');
            const isExpanded = this.getAttribute('aria-expanded') === 'true';

            // Update icon and text based on current state
            setTimeout(() => {
                if (this.getAttribute('aria-expanded') === 'true') {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                    this.innerHTML = this.innerHTML.replace('View Items', 'Hide Items');
                } else {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                    this.innerHTML = this.innerHTML.replace('Hide Items', 'View Items');
                }
            }, 100);
        });
    });
}

function initFormHandlers() {
    // Handle reorder buttons
    document.querySelectorAll('.reorder-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const orderID = this.dataset.order;
            
            /* if (!confirm('This will add all items from this order to your cart. Continue?')) {
                return;
            } */
            
            const gotoCart = confirm('Items will be added to your cart. Go to cart after adding?');

            // Show loading state
            showButtonLoading(this, 'Adding...');
            
            // Submit form
            submitReorderForm(this, orderID, gotoCart);
        });
    });

    // Handle other form submissions with loading states (excluding all refund-related buttons)
    document.querySelectorAll('form button[type="submit"]:not(.reorder-btn)').forEach(btn => {
        btn.addEventListener('click', function() {
            // Don't interfere with refund-related buttons - let PHP handle the display
            const form = this.closest('form');
            if (form && (form.action.includes('request_refund.php') || form.action.includes('cancel_refund_request.php'))) {
                // Let the form submit naturally without JavaScript interference
                return;
            }
            showButtonLoading(this, 'Processing');
        });
    });

    // Handle cancel order buttons
    document.querySelectorAll('.cancel-order-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const orderID = this.dataset.order;
            
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                return;
            }
            
            // Show loading state ONLY after confirmation
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            
            // Submit the form
            const form = this.closest('form');
            if (form) {
                form.submit();
            } else {
                // Fallback: create form dynamically
                const newForm = document.createElement('form');
                newForm.method = 'POST';
                newForm.action = '../order/cancel_order.php';
                
                const orderInput = document.createElement('input');
                orderInput.type = 'hidden';
                orderInput.name = 'orderID';
                orderInput.value = orderID;
                
                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect';
                redirectInput.value = window.location.pathname + window.location.search;
                
                newForm.appendChild(orderInput);
                newForm.appendChild(redirectInput);
                
                document.body.appendChild(newForm);
                newForm.submit();
            }
        });
    });

    // Handle other form submissions with loading states
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]:not(.reorder-btn):not(.cancel-order-btn), input[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalText = submitBtn.textContent || submitBtn.value;
                
                // Small delay to ensure the loading state shows
                setTimeout(() => {
                    submitBtn.disabled = true;
                    
                    if (submitBtn.textContent !== undefined) {
                        submitBtn.innerHTML = 'Processing';
                    } else {
                        submitBtn.value = 'Processing';
                    }
                }, 10);
                
                // Re-enable after 15 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    if (submitBtn.textContent !== undefined) {
                        submitBtn.innerHTML = originalText;
                    } else {
                        submitBtn.value = originalText;
                    }
                }, 15000);
            }
        });
    });
}

function initUtilityFunctions() {
    // Add keyboard navigation for order cards
    document.querySelectorAll('.order-card').forEach(card => {
        card.setAttribute('tabindex', '0');
        
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const toggleBtn = this.querySelector('.toggle-items');
                if (toggleBtn) {
                    e.preventDefault();
                    toggleBtn.click();
                }
            }
        });
    });
}

function checkRefundButtonStates() {
    // Don't modify buttons that are already in Processing state
    // The PHP already handles the correct button display based on order status
    console.log('Refund button states checked - PHP handles the display based on order status');
    
    // Debug: Check what buttons exist
    const refundButtons = document.querySelectorAll('.refund-btn');
    const cancelButtons = document.querySelectorAll('form[action*="cancel_refund_request"] button');
    console.log('Found refund buttons:', refundButtons.length);
    console.log('Found cancel buttons:', cancelButtons.length);
}

function showButtonLoading(button, text = 'Processing') {
    // Don't interfere with refund-related buttons
    const form = button.closest('form');
    if (form && (form.action.includes('request_refund.php') || form.action.includes('cancel_refund_request.php'))) {
        return;
    }
    
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = text;
    
    // Store original content for potential restoration
    button.dataset.originalContent = originalContent;
    
    // Only restore if it's not a refund request (which should remain permanent)
    if (!button.classList.contains('refund-btn')) {
        // Fallback restoration after 15 seconds for non-refund buttons
        setTimeout(() => {
            if (button.disabled) {
                button.disabled = false;
                button.innerHTML = originalContent;
            }
        }, 15000);
    }
}

function submitReorderForm(button, orderID, gotoCart = false) {
    const form = button.closest('form');
    
    if (form) {
        // Add goto_cart parameter if requested
        if (gotoCart) {
            const gotoCartInput = document.createElement('input');
            gotoCartInput.type = 'hidden';
            gotoCartInput.name = 'goto_cart';
            gotoCartInput.value = '1';
            form.appendChild(gotoCartInput);
        }
        form.submit();
    } else {
        // Create form dynamically if not found
        const newForm = createActionForm('../order/reorder.php', {
            orderID: orderID,
            goto_cart: gotoCart ? '1' : '0'
        });
        
        document.body.appendChild(newForm);
        newForm.submit();
    }
}

// Utility function to create temporary forms for actions
function createActionForm(action, data, method = 'POST') {
    const form = document.createElement('form');
    form.method = method;
    form.action = action;
    form.style.display = 'none';
    
    // Add current page as redirect
    const redirectInput = document.createElement('input');
    redirectInput.type = 'hidden';
    redirectInput.name = 'redirect';
    redirectInput.value = window.location.pathname + window.location.search;
    form.appendChild(redirectInput);
    
    // Add data fields
    Object.keys(data).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    return form;
}

// Export utilities for other scripts
window.OrderHistoryUtils = {
    createActionForm,
    showButtonLoading
};