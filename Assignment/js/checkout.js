// Checkout functionality
let selectedVoucher = null;
let checkoutItems = [];
let shippingFee = 8.00;

let checkoutData = {
    subtotal: 0,
    standardShipping: 8.00,
    hasAddresses: false
};

// Load checkout items from cart
async function loadCheckoutItems() {
    try {
        // First try to get items from window object (set by cart page)
        if (window.checkoutSelectedItems && window.checkoutSelectedItems.length > 0) {
            checkoutItems = window.checkoutSelectedItems;
            displayCheckoutItems();
            updateOrderSummary();
            return;
        }

        // If no stored items, fetch all cart items
        const response = await fetch('/order/cart.php?action=get_all', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error('Unable to load cart items. Please try again.');
        }

        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        if (!data.cart || Object.keys(data.cart).length === 0) {
            showError('Your cart is empty. Please add items before checkout.');
            setTimeout(() => {
                window.location.href = '/index.php';
            }, 2000);
            return;
        }

        // Convert cart items to checkout format
        const cartItems = Array.isArray(data.cart) ? data.cart : Object.values(data.cart);
        checkoutItems = cartItems.map(item => ({
            id: item.id,
            title: item.product.title || 'Product',
            price: parseFloat(item.product.price) || 0,
            qty: parseInt(item.qty) || 1,
            image: item.product.img || '/images/placeholder.jpg',
            color: item.product.color || '',
            subtotal: (parseFloat(item.product.price) || 0) * (parseInt(item.qty) || 1)
        }));

        displayCheckoutItems();
        updateOrderSummary();

    } catch (error) {
        console.error('Error loading checkout items:', error);
        showError('Failed to load checkout items: ' + error.message);
    }
}

function displayCheckoutItems() {
    const container = document.getElementById('checkout-items');
    if (!container) return;

    if (checkoutItems.length === 0) {
        container.innerHTML = `
            <div class="empty-items">
                <p>No items to checkout</p>
                <a href="/order/cart/" class="btn-primary">Back to Cart</a>
            </div>
        `;
        return;
    }

    container.innerHTML = checkoutItems.map(item => `
        <div class="order-item">
            <img src="${item.image}" alt="${item.title}" class="item-image" 
                 onerror="this.src='/images/placeholder.jpg'">
            <div class="item-details">
                <h4>${item.title}</h4>
                ${item.color ? `<p class="item-color">${item.color}</p>` : ''}
                <p class="item-price">RM ${item.price.toFixed(2)} × ${item.qty}</p>
            </div>
            <div class="item-subtotal">
                RM ${item.subtotal.toFixed(2)}
            </div>
        </div>
    `).join('');
}

function updateOrderSummary() {
    const itemsCount = checkoutItems.reduce((sum, item) => sum + item.qty, 0);
    const subtotal = checkoutItems.reduce((sum, item) => sum + item.subtotal, 0);
    const discount = selectedVoucher ? selectedVoucher.discount : 0;
    const total = subtotal + shippingFee - discount;

    // null checks for elements
    const itemsCountEl = document.getElementById('items-count');
    const subtotalEl = document.getElementById('subtotal-amount');
    const shippingEl = document.getElementById('shipping-fee');
    const discountEl = document.getElementById('discount-amount');
    const totalEl = document.getElementById('total-amount');

    if (itemsCountEl) itemsCountEl.textContent = itemsCount;
    if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);
    if (shippingEl) shippingEl.textContent = shippingFee.toFixed(2);
    if (discountEl) discountEl.textContent = discount.toFixed(2);
    if (totalEl) totalEl.textContent = total.toFixed(2);

    // Show/hide discount row
    const discountRow = document.getElementById('discount-row');
    if (discountRow) {
        if (discount > 0) {
            discountRow.style.display = 'flex';
        } else {
            discountRow.style.display = 'none';
        }
    }

    // Enable place order button if we have items and address
    const placeOrderBtn = document.querySelector('.place-order-btn');
    const hasAddresses = window.checkoutData?.hasAddresses;
    const hasAddress = !hasAddresses || document.querySelector('input[name="selected_address"]:checked');
    
    if (placeOrderBtn) {
        placeOrderBtn.disabled = itemsCount === 0 || !hasAddress;
    }
}

// Shipping method change handler
document.addEventListener('change', function(e) {
    if (e.target.name === 'shipping_method') {
        shippingFee = e.target.value === 'express' ? 15.00 : 8.00;
        updateOrderSummary();
    }
});

// Voucher modal functions
function showVoucherModal() {
    const modal = document.getElementById('voucherModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        loadVouchers();
    }
}

function hideVoucherModal() {
    const modal = document.getElementById('voucherModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

async function loadVouchers() {
    const container = document.getElementById('voucher-list');
    if (!container) return;
    
    try {
        const response = await fetch('/api/vouchers.php', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) throw new Error('Unable to load vouchers');
        
        const data = await response.json();
        
        if (data.error) throw new Error(data.error);
        
        if (!data.vouchers || data.vouchers.length === 0) {
            container.innerHTML = '<div class="no-vouchers">No vouchers available</div>';
            return;
        }
        
        container.innerHTML = data.vouchers.map(voucher => `
            <div class="voucher-item ${selectedVoucher && selectedVoucher.id === voucher.id ? 'selected' : ''}" 
                 onclick="selectVoucher(${JSON.stringify(voucher).replace(/"/g, '&quot;')})">
                <div class="voucher-info">
                    <h4>${voucher.title}</h4>
                    <p>${voucher.description}</p>
                    <div class="voucher-details">
                        <span class="voucher-discount">-RM ${voucher.discount.toFixed(2)}</span>
                        <span class="voucher-expiry">Valid until ${voucher.expiry_date}</span>
                    </div>
                </div>
                ${selectedVoucher && selectedVoucher.id === voucher.id ? 
                  '<div class="voucher-selected">✓</div>' : ''}
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error loading vouchers:', error);
        container.innerHTML = '<div class="error">Unable to load vouchers</div>';
    }
}

function selectVoucher(voucher) {
    selectedVoucher = voucher;
    
    // Update voucher selection display
    const voucherText = document.getElementById('voucher-text');
    if (voucherText) {
        voucherText.innerHTML = `
            <strong>${voucher.title}</strong><br>
            <small>Save RM ${voucher.discount.toFixed(2)}</small>
        `;
    }
    
    updateOrderSummary();
    hideVoucherModal();
}

// Form validation helpers
function clearFormErrors() {
    document.querySelectorAll('.error-message').forEach(el => {
        el.textContent = '';
        el.style.display = 'none';
    });
    document.querySelectorAll('.form-group').forEach(group => {
        group.classList.remove('error');
    });
}

function showFieldError(fieldName, message) {
    const errorEl = document.getElementById(fieldName + '_error');
    const field = document.querySelector(`#${fieldName}`);
    const fieldGroup = field ? field.closest('.form-group') : null;
    
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
    if (fieldGroup) {
        fieldGroup.classList.add('error');
    }
}

function validateForm(formData) {
    clearFormErrors();
    let isValid = true;
    
    // Required fields validation
    const required = ['recipient_name', 'phoneNo', 'unitNo', 'address_line_1', 'city', 'postcode', 'state'];
    required.forEach(field => {
        if (!formData.get(field) || formData.get(field).trim() === '') {
            showFieldError(field, 'This field is required');
            isValid = false;
        }
    });
    
    // Phone validation
    const phone = formData.get('phoneNo');
    if (phone && !/^[0-9+\-\s()]{10,15}$/.test(phone)) {
        showFieldError('phoneNo', 'Please enter a valid phone number');
        isValid = false;
    }
    
    // Postal code validation
    const postalCode = formData.get('postcode');
    if (postalCode && !/^[0-9]{5}$/.test(postalCode)) {
        showFieldError('postcode', 'Please enter a valid 5-digit postal code');
        isValid = false;
    }
    
    return isValid;
}

// CHANGED: Fixed form submission with proper field names
document.addEventListener('DOMContentLoaded', function() {
    const addAddressForm = document.getElementById('addAddressForm');
    if (addAddressForm) {
        addAddressForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            if (!validateForm(formData)) {
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            // Show loading state
            if (btnText && btnLoading) {
                btnText.style.display = 'none';
                btnLoading.style.display = 'inline';
            }
            submitBtn.disabled = true;
            
            try {
                const payload = {
                    recipient_name: formData.get('recipient_name'),
                    phoneNo: formData.get('phoneNo'),
                    unitNo: formData.get('unitNo'),
                    address_line_1: formData.get('address_line_1'),
                    address_line_2: formData.get('address_line_2') || '',
                    city: formData.get('city'),
                    postcode: formData.get('postcode'),
                    state: formData.get('state'),
                    isDefault: formData.has('isDefault')
                };
                
                const response = await fetch('/api/add_address.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                
                if (!response.ok || data.error) {
                    throw new Error(data.error || 'Unable to add address');
                }
                
                showSuccess('Address added successfully');
                hideAddAddressModal();
                
                // Reload page to show new address
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
                
            } catch (error) {
                console.error('Error adding address:', error);
                showError('Unable to add address: ' + error.message);
            } finally {
                // Reset button state
                if (btnText && btnLoading) {
                    btnText.style.display = 'inline';
                    btnLoading.style.display = 'none';
                }
                submitBtn.disabled = false;
            }
        });
    }
});

async function placeOrder() {
    const placeOrderBtn = document.querySelector('.place-order-btn');
    if (!placeOrderBtn) return;
    
    const btnText = placeOrderBtn.querySelector('.btn-text');
    const btnLoading = placeOrderBtn.querySelector('.btn-loading');
    
    // Validate required selections
    const selectedAddress = document.querySelector('input[name="selected_address"]:checked');
    const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
    
    const hasAddresses = window.checkoutData?.hasAddresses;
    if (hasAddresses && !selectedAddress) {
        showError('Please select a delivery address');
        return;
    }

    // Check if addresses are required
    if (window.checkoutData?.hasAddresses && !selectedAddress) {
        showError('Please select a delivery address');
        return;
    }
    
    if (!selectedShipping) {
        showError('Please select a shipping method');
        return;
    }
    
    if (!selectedPayment) {
        showError('Please select a payment method');
        return;
    }
    
    if (!checkoutItems || checkoutItems.length === 0) {
        showError('No items to checkout');
        return;
    }
    
    // Show loading state
    if (btnText && btnLoading) {
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
    }
    placeOrderBtn.disabled = true;
    
    try {
        const formData = new FormData();
        
        if (hasAddresses && selectedAddress) {
            formData.append('address_id', selectedAddress.value);
        }

        // Add address if selected
        if (selectedAddress) {
            formData.append('address_id', selectedAddress.value);
        }
        
        formData.append('shipping_method', selectedShipping.value);
        formData.append('payment_method', selectedPayment.value);
        
        // Add order totals for verification
        const subtotal = checkoutItems.reduce((sum, item) => sum + item.subtotal, 0);
        const discount = selectedVoucher ? selectedVoucher.discount : 0;
        
        formData.append('subtotal', subtotal.toFixed(2));
        formData.append('shipping_fee', shippingFee.toFixed(2));
        formData.append('discount', discount.toFixed(2));
        
        // Add selected items properly
        checkoutItems.forEach((item, index) => {
            formData.append(`selected_items[${index}]`, item.id);
        });
        
        const response = await fetch('/order/place_order.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Handle response
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            showSuccess('Order placed successfully!');
            
            // Clear checkout items from memory
            checkoutItems = [];
            selectedVoucher = null;
            
            setTimeout(() => {
                window.location.href = `/order/success.php?order_id=${data.order_id}`;
            }, 1500);
        } else {
            // Handle HTML response or redirect
            const text = await response.text();
            if (text.includes('success') || response.redirected) {
                showSuccess('Order placed successfully!');
                
                // Clear checkout items
                checkoutItems = [];
                selectedVoucher = null;
                
                setTimeout(() => {
                    window.location.href = response.url || '/order/success.php';
                }, 1500);
            } else {
                // Check if it's an error page
                if (text.includes('error') || text.includes('Error')) {
                    throw new Error('Order processing failed');
                }
                throw new Error('Unexpected response format');
            }
        }
        
    } catch (error) {
        console.error('Error placing order:', error);
        showError('Unable to place order: ' + error.message);
    } finally {
        // Reset button state
        if (btnText && btnLoading) {
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        }
        placeOrderBtn.disabled = false;
    }
}
function showError(message) {
    showMessage(message, 'error');
}

function showSuccess(message) {
    showMessage(message, 'success');
}

function showMessage(message, type = 'error') {
    // Remove any existing notifications
    const existing = document.querySelector('.notification-message');
    if (existing) {
        existing.remove();
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'notification-message';
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'error' ? '#ff4444' : '#4CAF50'};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease-in-out;
    `;
    messageDiv.textContent = message;
    
    document.body.appendChild(messageDiv);
    
    // Animate in
    setTimeout(() => {
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.style.opacity = '0';
            messageDiv.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        }
    }, 4000);
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        if (e.target.id === 'addAddressModal') hideAddAddressModal();
        if (e.target.id === 'voucherModal') hideVoucherModal();
    }
});

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadCheckoutItems();
    
    // Listen for address selection changes
    document.addEventListener('change', function(e) {
        if (e.target.name === 'selected_address') {
            updateOrderSummary();
        }
    });
    
    // Auto-update order summary when page loads
    updateOrderSummary();
});