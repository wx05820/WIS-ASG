// Checkout page JavaScript functionality

// Global variables
let checkoutItems = [];
let availableVouchers = [];
let availableVouchers = [];
let selectedVoucher = null;
let appliedVoucher = null;
let currentTab = 'all';
let appliedVoucher = null;
let currentTab = 'all';

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

    if (typeof initializeVoucherSystem === 'function') {
        initializeVoucherSystem();
    }
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
    const discount = calculateDiscount();
    const total = Math.max(0, subtotal + shippingFee - discount);
    const discount = calculateDiscount();
    const total = Math.max(0, subtotal + shippingFee - discount);
    
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
        if (discountRow) discountRow.style.display = 'flex';
        if (discountRow) discountRow.style.display = 'flex';
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
    
    // Validate form first
    if (!validateCheckoutForm()) {
        return;
    }
    
    // Get form data
    const formData = new FormData(form);
    
    // Add additional data
    formData.append('action', 'place_order_ajax'); // Distinguish from normal form submission
    formData.append('ajax', '1'); // Flag for AJAX handling

    // Add voucher data to form submission
    const voucherData = getVoucherDataForSubmission();
    if (voucherData) {
        console.log('Adding voucher data to form:', voucherData);
        Object.entries(voucherData).forEach(([key, value]) => {
            formData.append(key, value);
        });
    }
    
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
    
    // Submit via AJAX with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
    
    fetch('place_order.php', {
        method: 'POST',
        body: formData,
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        }
        
        // If redirected, follow the redirect
        if (response.redirected) {
            window.location.href = response.url;
            return;
        }
        
        // Handle non-JSON response (fallback)
        return response.text().then(text => {
            console.error('Unexpected response format:', text);
            throw new Error('Server returned unexpected response format');
        });
    })
    .then(data => {
        if (!data) return; // Already redirected
        
        if (data.success) {
            // Success response
            showSuccess(data.message || 'Order placed successfully!');
            
            // Redirect to success page
            setTimeout(() => {
                window.location.href = data.redirect_url || 'success.php';
            }, 1000);
        } else {
            // Error response
            showError(data.message || 'Order processing failed. Please try again.');
            
            // Re-enable button
            enableSubmitButton();
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error placing order:', error);
        
        if (error.name === 'AbortError') {
            showError('Request timed out. Please check your connection and try again.');
        } else {
            showError('Network error. Please check your connection and try again.');
        }
        
        // Re-enable button
        enableSubmitButton();
    });
}

function enableSubmitButton() {
    const submitBtn = document.querySelector('.place-order-btn');
    if (submitBtn) {
        submitBtn.disabled = false;
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        if (btnText && btnLoading) {
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        }
    }
}

// Initialize voucher system
function initializeVoucherSystem() {
    // Set up voucher modal event listeners
    const modal = document.getElementById('voucherModal');
    if (modal) {
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === modal) {
                closeVoucherModal();
            }
        }
    }
    
    // Initialize voucher data if available
    if (typeof window.availableVouchers !== 'undefined') {
        availableVouchers = window.availableVouchers;
    }
}

// Show voucher modal
function showVoucherModal() {
    const modal = document.getElementById('voucherModal');
    if (modal) {
        modal.style.display = 'block';
        loadVouchers();
    } else {
        // Fallback if modal doesn't exist yet
        showNotification('Voucher selection feature is loading...', 'info');
    }
}

// Close voucher modal
function closeVoucherModal() {
    const modal = document.getElementById('voucherModal');
    if (modal) {
        modal.style.display = 'none';
    }
    selectedVoucher = null;
    updateSelectedVoucherDisplay();
}

// Close modal if user clicks outside the box
window.onclick = function(event) {
    const modal = document.getElementById('voucherModal');
    if (event.target === modal) {
        closeVoucherModal();
    }
}

// Load vouchers from server
async function loadVouchers() {
    const loadingEl = document.getElementById('vouchersLoading');
    const listEl = document.getElementById('voucherList');

    if (loadingEl) loadingEl.style.display = 'block';
    if (listEl) listEl.innerHTML = '';

    try {
        if (window.availableVouchers && window.availableVouchers.length > 0) {
            availableVouchers = window.availableVouchers;
        } else {
            // fallback: AJAX fetch
            const response = await fetch('voucher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_vouchers',
                    user_id: window.currentUserId || null,
                    subtotal: window.checkoutData ? window.checkoutData.subtotal : 0
                })
            });

            const data = await response.json();
            availableVouchers = data.vouchers || [];
        }

        if (loadingEl) loadingEl.style.display = 'none';
        displayVouchers();

        if (availableVouchers.length > 0) {
            updateOrderSummary();
        }
    } catch (err) {
        console.error('Voucher load error:', err);
        if (loadingEl) loadingEl.style.display = 'none';
        if (listEl) listEl.innerHTML = `<p>Failed to load vouchers</p>`;
    }
}

// Display vouchers in the modal
function displayVouchers() {
    const listEl = document.getElementById('voucherList');
    if (!listEl) return;

    const filteredVouchers = availableVouchers || [];

    if (filteredVouchers.length === 0) {
        listEl.innerHTML = `
            <div class="no-vouchers">
                <div class="no-vouchers-icon">🎟️</div>
                <h3>No vouchers found</h3>
                <p>Try refreshing the page or check back later.</p>
            </div>
        `;
        return;
    }

    listEl.innerHTML = filteredVouchers.map(voucher => createVoucherHTML(voucher)).join('');
}

// Switch voucher tabs
function switchTab(tabType) {
    currentTab = tabType;
    
    // Update tab active states
    const tabs = document.querySelectorAll('.voucher-tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.tab === tabType) {
            tab.classList.add('active');
        }
    });
    
    displayVouchers();
}

// Create HTML for a voucher
function createVoucherHTML(voucher) {
    const isAvailable = voucher.status === 'available' && voucher.meets_min_order;
    const discountDisplay = voucher.discount_type === 'percentage' 
        ? `${voucher.value}% OFF` 
        : `RM ${voucher.value.toFixed(2)} OFF`;
    
    const potentialSavings = isAvailable ? voucher.potential_discount.toFixed(2) : '0.00';
    const expiryDate = new Date(voucher.end_date).toLocaleDateString();
    
    return `
        <div class="voucher-item ${isAvailable ? 'available' : 'unavailable'}" 
             data-voucher-id="${voucher.voucher_id}"
             onclick="${isAvailable ? `selectVoucher(${voucher.voucher_id})` : ''}">
            <div class="voucher-left">
                <div class="voucher-discount">${discountDisplay}</div>
                <div class="voucher-code">${voucher.code}</div>
            </div>
            <div class="voucher-middle">
                <h4 class="voucher-title">${voucher.description}</h4>
                <p class="voucher-details">
                    Min spend: RM ${voucher.minOrderAmount.toFixed(2)}
                    ${voucher.maxDiscountAmount > 0 ? ` • Max discount: RM ${voucher.maxDiscountAmount.toFixed(2)}` : ''}
                </p>
                <p class="voucher-expiry">Expires: ${expiryDate}</p>
                ${!isAvailable && voucher.unavailable_reason ? 
                    `<p class="voucher-error">${voucher.unavailable_reason}</p>` : ''}
            </div>
            <div class="voucher-right">
                ${isAvailable ? 
                    `<div class="voucher-savings">Save RM ${potentialSavings}</div>` :
                    `<div class="voucher-unavailable">Unavailable</div>`
                }
                <div class="voucher-radio">
                    <input type="radio" name="selected_voucher" value="${voucher.voucher_id}" 
                           ${isAvailable ? '' : 'disabled'}>
                </div>
            </div>
        </div>
    `;
}

// Select a voucher
function selectVoucher(voucherId) {
    const voucher = availableVouchers.find(v => v.voucher_id == voucherId);
    if (!voucher) return;
    
    selectedVoucher = voucher;
    
    // Update radio button
    const radioBtn = document.querySelector(`input[name="selected_voucher"][value="${voucherId}"]`);
    if (radioBtn) {
        radioBtn.checked = true;
    }
    
    // Update visual selection
    document.querySelectorAll('.voucher-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    const selectedItem = document.querySelector(`[data-voucher-id="${voucherId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('selected');
    }
    
    updateSelectedVoucherDisplay();
}

// Update selected voucher display
function updateSelectedVoucherDisplay() {
    const selectedText = document.getElementById('selectedVoucherText');
    const applyBtn = document.getElementById('applyVoucherBtn');
    
    if (appliedVoucher) {
        const discountDisplay = appliedVoucher.discount_type === 'percentage' 
            ? `${appliedVoucher.value}% OFF applied` 
            : `RM ${appliedVoucher.value.toFixed(2)} OFF applied`;
        
        if (selectedText) selectedText.textContent = discountDisplay;
        if (applyBtn) applyBtn.disabled = false;
    } else if (selectedVoucher) {
        const discountDisplay = selectedVoucher.discount_type === 'percentage' 
            ? `${selectedVoucher.value}% OFF` 
            : `RM ${selectedVoucher.value.toFixed(2)} OFF`;
        
        if (selectedText) selectedText.textContent = `${selectedVoucher.code} - ${discountDisplay}`;
        if (applyBtn) applyBtn.disabled = false;
    } else {
        if (selectedText) selectedText.textContent = 'No voucher selected';
        if (applyBtn) applyBtn.disabled = true;
    }
}

// Apply selected voucher
function applySelectedVoucher() {
    if (!selectedVoucher) {
        showError('Please select a voucher first');
        return;
    }
    
    appliedVoucher = selectedVoucher;

    const idInput = document.getElementById('voucherIdInput');
    const codeInput = document.getElementById('voucherCodeInput');
    const discInput = document.getElementById('voucherDiscountInput');

    const voucherId = appliedVoucher.voucher_id || appliedVoucher.id || '';
    const voucherCode = appliedVoucher.code || '';
    const discountAmount = calculateDiscount().toFixed(2);

    if (idInput) idInput.value = voucherId;
    if (codeInput) codeInput.value = voucherCode;
    if (discInput) discInput.value = discountAmount;
    
    console.log('Voucher applied:', { voucherId, voucherCode, discountAmount });
    
    // Update the voucher selection display
    const voucherSelection = document.querySelector('.voucher-selection');
    if (voucherSelection) {
        const discountDisplay = appliedVoucher.discount_type === 'percentage' 
            ? `${appliedVoucher.value}% OFF` 
            : `RM ${appliedVoucher.value.toFixed(2)} OFF`;
        
        voucherSelection.innerHTML = `
            <div class="selected-voucher">
                <div class="selected-voucher-info">
                    <strong>${appliedVoucher.code}</strong>
                    <span>${discountDisplay}</span>
                </div>
                <button type="button" class="remove-voucher" onclick="removeVoucher()">×</button>
            </div>
        `;
        voucherSelection.classList.add('has-voucher');
    }
    
    // Close modal
    closeVoucherModal();
    
    // Update order summary
    updateOrderSummary();
    
    showSuccess(`Voucher ${appliedVoucher.code} applied successfully!`);
}

// Remove applied voucher
function removeVoucher() {
    appliedVoucher = null;
    
    document.getElementById('voucherIdInput').value = '';
    document.getElementById('voucherCodeInput').value = '';
    document.getElementById('voucherDiscountInput').value = '';

    // Reset voucher selection display
    const voucherSelection = document.querySelector('.voucher-selection');
    if (voucherSelection) {
        voucherSelection.innerHTML = '<span>Select a voucher</span>';
        voucherSelection.classList.remove('has-voucher');
    }
    
    // Update order summary
    updateOrderSummary();
    
    showSuccess('Voucher removed');
}

// Calculate discount from applied voucher
function calculateDiscount() {
    if (!appliedVoucher || !window.checkoutData) {
        return 0;
    }
    
    const subtotal = window.checkoutData.subtotal;
    let discount = 0;
    
    if (appliedVoucher.discount_type === 'percentage') {
        discount = (subtotal * appliedVoucher.value) / 100;
        if (appliedVoucher.maxDiscountAmount > 0) {
            discount = Math.min(discount, appliedVoucher.maxDiscountAmount);
        }
    } else {
        discount = appliedVoucher.value;
    }
    
    // Ensure discount doesn't exceed subtotal
    return Math.min(discount, subtotal);
}

// Get voucher data for form submission
function getVoucherDataForSubmission() {
    if (!appliedVoucher) {
        return null;
    }
    
    return {
        'voucher_id': appliedVoucher.voucher_id || appliedVoucher.id,
        'voucher_code': appliedVoucher.code,
        'discount_amount': calculateDiscount()
    };
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

// Handle form submission (this function is defined in checkout.php inline script)
// This is kept for reference but the actual implementation is in checkout.php

// Validate checkout form (this function is defined in checkout.php inline script)
// This is kept for reference but the actual implementation is in checkout.php


