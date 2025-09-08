// Checkout page JavaScript functionality

// Global variables
let checkoutItems = [];
let selectedVoucher = null;
let appliedDiscount = 0;

// Initialize checkout when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize checkout items if available
    if (typeof window.checkoutSelectedItems !== 'undefined') {
        checkoutItems = window.checkoutSelectedItems;
    }
    
    // Initialize checkout data if available
    if (typeof window.checkoutData !== 'undefined') {
        updateOrderSummary();
    }
    
    // Set up event listeners
    setupEventListeners();
});

// Set up event listeners for form changes
function setupEventListeners() {
    // Listen for shipping method changes
    const shippingInputs = document.querySelectorAll('input[name="shipping_method"]');
    shippingInputs.forEach(input => {
        input.addEventListener('change', updateOrderSummary);
    });
    
    // Listen for payment method changes
    const paymentInputs = document.querySelectorAll('input[name="payment_method"]');
    paymentInputs.forEach(input => {
        input.addEventListener('change', updateOrderSummary);
    });
    
    // Listen for address selection changes
    const addressInputs = document.querySelectorAll('input[name="selected_address"]');
    addressInputs.forEach(input => {
        input.addEventListener('change', updateOrderSummary);
    });
}

// Display checkout items (if needed for dynamic updates)
function displayCheckoutItems() {
    const itemsContainer = document.getElementById('checkout-items');
    if (!itemsContainer || !checkoutItems.length) return;
    
    // Items are already rendered server-side, this is for dynamic updates if needed
    console.log('Checkout items loaded:', checkoutItems);
}

// Update order summary when shipping/payment methods change
function updateOrderSummary() {
    if (typeof window.checkoutData === 'undefined') return;
    
    const subtotal = window.checkoutData.subtotal || 0;
    const standardShipping = window.checkoutData.standardShipping || 8.00;
    const expressShipping = 15.00;
    
    // Get selected shipping method
    const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
    const shippingFee = selectedShipping && selectedShipping.value === 'express' ? expressShipping : standardShipping;
    
    // Calculate totals
    const discount = appliedDiscount || 0;
    const total = subtotal + shippingFee - discount;
    
    // Update display
    const subtotalElement = document.getElementById('subtotal-amount');
    const shippingElement = document.getElementById('shipping-fee');
    const totalElement = document.getElementById('total-amount');
    const discountElement = document.getElementById('discount-amount');
    const discountRow = document.getElementById('discount-row');
    
    if (subtotalElement) subtotalElement.textContent = subtotal.toFixed(2);
    if (shippingElement) shippingElement.textContent = shippingFee.toFixed(2);
    if (totalElement) totalElement.textContent = total.toFixed(2);
    
    // Show/hide discount row
    if (discount > 0) {
        if (discountElement) discountElement.textContent = discount.toFixed(2);
        if (discountRow) discountRow.style.display = 'block';
    } else {
        if (discountRow) discountRow.style.display = 'none';
    }
}

// Place order function for cart checkout (AJAX)
function placeOrder() {
    const form = document.getElementById('checkout-form');
    if (!form) {
        showError('Checkout form not found');
        return;
    }
    
    // Get form data
    const formData = new FormData(form);
    
    // Add additional data if needed
    formData.append('action', 'place_order');
    
    // Show loading state
    const submitBtn = document.querySelector('.place-order-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        if (btnText && btnLoading) {
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
        }
    }
    
    // Submit via AJAX
    fetch('place_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.redirected) {
            // If redirected, follow the redirect
            window.location.href = response.url;
            return;
        }
        return response.text();
    })
    .then(data => {
        if (data) {
            // If we get data back, it might be an error page
            if (data.includes('error') || data.includes('Error')) {
                showError('Order processing failed. Please try again.');
                // Re-enable button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    const btnText = submitBtn.querySelector('.btn-text');
                    const btnLoading = submitBtn.querySelector('.btn-loading');
                    if (btnText && btnLoading) {
                        btnText.style.display = 'inline';
                        btnLoading.style.display = 'none';
                    }
                }
            } else {
                // Success - redirect to success page
                window.location.href = data;
            }
        }
    })
    .catch(error => {
        console.error('Error placing order:', error);
        showError('Network error. Please check your connection and try again.');
        
        // Re-enable button
        if (submitBtn) {
            submitBtn.disabled = false;
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            if (btnText && btnLoading) {
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
            }
        }
    });
}

// Show voucher modal (placeholder)
function showVoucherModal() {
    // This would open a voucher selection modal
    // For now, just show a message
    showNotification('Voucher selection not yet implemented', 'info');
}

// Show error notification
function showError(message) {
    showNotification(message, 'error');
}

// Show success notification
function showSuccess(message) {
    showNotification(message, 'success');
}

// Show notification
function showNotification(message, type = 'error') {
    // Remove any existing notifications
    const existing = document.querySelector('.detail-notification');
    if (existing) {
        existing.remove();
    }
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = 'detail-notification';
    const bgColor = type === 'error' ? '#ff4444' : (type === 'success' ? '#4CAF50' : '#2196F3');
    notificationDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease-in-out;
    `;
    notificationDiv.textContent = message;
    
    document.body.appendChild(notificationDiv);
    
    // Animate in
    setTimeout(() => {
        notificationDiv.style.opacity = '1';
        notificationDiv.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (notificationDiv.parentNode) {
            notificationDiv.style.opacity = '0';
            notificationDiv.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notificationDiv.parentNode) {
                    notificationDiv.parentNode.removeChild(notificationDiv);
                }
            }, 300);
        }
    }, 4000);
}

// Confirm add address
function confirmAddAddress() {
    const confirmed = confirm(
        'You need to add a delivery address to complete your order.\n\n' +
        'Would you like to go to the address page to add one?'
    );
    
    if (confirmed) {
        window.location.href = '/user/addresses.php?from=checkout';
    }
}

// Handle form submission
function handleFormSubmit(event) {
    // Validate form first
    if (!validateCheckoutForm()) {
        event.preventDefault();
        return false;
    }
    
    // Disable submit button to prevent double submission
    const submitBtn = document.querySelector('.place-order-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        if (btnText && btnLoading) {
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
        }
    }
    
    // For buy now, allow default form submission
    const isBuyNow = window.checkoutData && window.checkoutData.isBuyNow;
    if (!isBuyNow) {
        event.preventDefault(); // Prevent default form submission for cart checkout
        placeOrder();
        return false;
    }
    
    // For buy now, let the form submit normally
    return true;
}

// Validate checkout form
function validateCheckoutForm() {
    // Check all address radio buttons
    const addressInputs = document.querySelectorAll('input[name="selected_address"]');
    
    let selectedAddress = null;
    for (let i = 0; i < addressInputs.length; i++) {
        if (addressInputs[i].checked) {
            selectedAddress = addressInputs[i];
            break;
        }
    }
    
    if (!selectedAddress) {
        alert('Please select a delivery address');
        return false;
    }
    
    // Check if shipping method is selected
    const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
    if (!selectedShipping) {
        alert('Please select a shipping method');
        return false;
    }
    
    // Check if payment method is selected
    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedPayment) {
        alert('Please select a payment method');
        return false;
    }
    
    return true;
}
