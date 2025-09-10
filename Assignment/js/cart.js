// Cart functionality JavaScript for the working cart system
document.addEventListener('DOMContentLoaded', function() {
    initializeCart();
});

function initializeCart() {
    // Load saved checkbox states
    loadCheckboxStates();
    
    // Checkbox change handlers
    document.querySelectorAll('.item-check').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            saveCheckboxStates();
            updateSelectedSummary();
        });
    });
    
    // Quantity control buttons
    document.querySelectorAll('.inc, .dec').forEach(button => {
        button.addEventListener('click', function() {
            const prodID = this.getAttribute('data-id');
            const qtyInput = this.parentElement.querySelector('.qty-input');
            const currentQty = parseInt(qtyInput.value) || 1;
            const maxQty = parseInt(qtyInput.getAttribute('max')) || 999;
            
            let newQty = currentQty;
            
            if (this.classList.contains('inc')) {
                if (currentQty >= maxQty) {
                    showNotification(`Cannot increase quantity. Only ${maxQty} items available in stock.`, 'error');
                    return;
                }
                newQty = Math.min(currentQty + 1, maxQty);
            } else if (this.classList.contains('dec')) {
                newQty = Math.max(currentQty - 1, 1);
            }
            
            if (newQty !== currentQty) {
                qtyInput.value = newQty;
                updateCartItem(prodID, newQty);
                // Update selected summary after quantity change
                updateSelectedSummary();
            }
        });
    });
    
    // Quantity input change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const prodID = this.getAttribute('data-id');
            const qty = parseInt(this.value) || 1;
            const maxQty = parseInt(this.getAttribute('max')) || 999;
            
            if (qty > maxQty) {
                this.value = maxQty;
                showNotification(`Cannot increase quantity. Only ${maxQty} items available in stock.`, 'error');
            } else if (qty < 1) {
                this.value = 1;
                showNotification('Quantity must be at least 1', 'warning');
            }
            
            updateCartItem(prodID, this.value);
            // Update selected summary after quantity change
            updateSelectedSummary();
        });
    });
    
    // Remove buttons
    document.querySelectorAll('.remove').forEach(button => {
        button.addEventListener('click', function() {
            const prodID = this.getAttribute('data-id');
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                removeCartItem(prodID);
            }
        });
    });
    
    // Select all functionality
    const selectAllBtn = document.getElementById('select-all');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const isChecked = this.getAttribute('data-checked') === 'true';
            const checkboxes = document.querySelectorAll('.item-check');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !isChecked;
            });
            
            this.setAttribute('data-checked', !isChecked);
            this.textContent = !isChecked ? 'Deselect All' : 'Select All';
            
            // Save checkbox states and update selected summary
            saveCheckboxStates();
            updateSelectedSummary();
        });
    }
    
    // Clear selected functionality
    const clearSelectedBtn = document.getElementById('clear-selected');
    if (clearSelectedBtn) {
        clearSelectedBtn.addEventListener('click', function() {
            const selectedItems = document.querySelectorAll('.item-check:checked');
            if (selectedItems.length === 0) {
                showNotification('No items selected', 'warning');
                return;
            }
            
            if (confirm('Are you sure you want to remove all selected items?')) {
                selectedItems.forEach(checkbox => {
                    const cartRow = checkbox.closest('.cart-row');
                    const prodID = cartRow.getAttribute('data-id');
                    removeCartItem(prodID);
                });
            }
        });
    }
}

// Update cart item function
function updateCartItem(prodID, qty) {
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'update');
    formData.append('prodID', prodID);
    formData.append('qty', qty);
    
    // Show loading state
    const cartRow = document.querySelector(`[data-id="${prodID}"]`);
    if (cartRow) {
        cartRow.classList.add('updating');
    }
    
    fetch('cart_page.php', {
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
            // Update cart display
            updateCartDisplay(data.data);
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
            // Revert quantity input if update failed
            const qtyInput = document.querySelector(`[data-id="${prodID}"] .qty-input`);
            if (qtyInput) {
                qtyInput.value = qtyInput.dataset.originalValue || 1;
            }
        }
    })
    .catch(error => {
        console.error('Update cart error:', error);
        showNotification('Failed to update cart item. Please try again.', 'error');
        // Revert quantity input if update failed
        const qtyInput = document.querySelector(`[data-id="${prodID}"] .qty-input`);
        if (qtyInput) {
            qtyInput.value = qtyInput.dataset.originalValue || 1;
        }
    })
    .finally(() => {
        // Remove loading state
        if (cartRow) {
            cartRow.classList.remove('updating');
        }
    });
}

// Remove cart item function
function removeCartItem(prodID) {
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'remove');
    formData.append('prodID', prodID);
    
    fetch('cart_page.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Remove the item from the display
            const cartRow = document.querySelector(`[data-id="${prodID}"]`);
            if (cartRow) {
                cartRow.remove();
            }
            // Clear saved selection for removed item
            const savedStates = localStorage.getItem('cart_selections');
            if (savedStates) {
                const states = JSON.parse(savedStates);
                delete states[prodID];
                localStorage.setItem('cart_selections', JSON.stringify(states));
            }
            // Update cart display
            updateCartDisplay(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Remove cart error:', error);
        showNotification('Failed to remove item from cart', 'error');
    });
}

// Update cart display function
function updateCartDisplay(data) {
    if (!data) return;
    
    // Update cart count in header
    updateCartCountDisplay(data.totals.itemCount);
    
    // Update individual item subtotals and button states
    if (data.cart) {
        Object.keys(data.cart).forEach(prodID => {
            const item = data.cart[prodID];
            const cartRow = document.querySelector(`[data-id="${prodID}"]`);
            if (cartRow) {
                // Update subtotal
                const subtotalElement = cartRow.querySelector('.subtotal');
                if (subtotalElement) {
                    subtotalElement.textContent = 'RM ' + formatMoney(item.product.price * item.qty);
                }
                
                // Update quantity input and button states
                const qtyInput = cartRow.querySelector('.qty-input');
                const incButton = cartRow.querySelector('.inc');
                const decButton = cartRow.querySelector('.dec');
                
                if (qtyInput) {
                    qtyInput.value = item.qty;
                    // Update max attribute based on current stock
                    qtyInput.setAttribute('max', item.product.stock || 999);
                }
                
                // Update button states
                if (incButton) {
                    incButton.disabled = item.qty >= (item.product.stock || 999);
                }
                if (decButton) {
                    decButton.disabled = item.qty <= 1;
                }
            }
        });
    }
    
    // If cart is empty, show empty cart message
    if (!data.cart || Object.keys(data.cart).length === 0) {
        showEmptyCart();
    }
    
    // Update selected summary after cart changes
    updateSelectedSummary();
}

// Update cart count display
function updateCartCountDisplay(count) {
    const cartCountElements = document.querySelectorAll('#cart-count, .cart-count, .header-cart-count, .item-count');
    
    if (count !== null && count !== undefined) {
        cartCountElements.forEach((element) => {
            element.textContent = count;
        });
    }
}

// Update order summary
function updateOrderSummary(totals) {
    const totalsElement = document.getElementById('totals');
    if (totalsElement) {
        totalsElement.innerHTML = `
            <div class="totals-row">
                <span>Total Items: <strong>${totals.itemCount}</strong></span>
            </div>
            <div class="totals-row total">
                <span>Total: <strong>RM ${formatMoney(totals.total)}</strong></span>
            </div>
        `;
    }
}

// Show empty cart message
function showEmptyCart() {
    const cartItems = document.getElementById('cart-items');
    if (cartItems) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items to your cart yet.</p>
            </div>
        `;
    }
}

// Show notification
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.cart-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `cart-notification ${type}`;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>${message}</span>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        color: white;
        font-weight: bold;
        z-index: 1000;
        animation: slideIn 0.3s ease-out;
        background-color: ${type === 'success' ? '#4CAF50' : type === 'warning' ? '#FF9800' : '#F44336'};
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

// Format money for display
function formatMoney(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Update selected items summary
function updateSelectedSummary() {
    const checkedItems = document.querySelectorAll('.item-check:checked');
    const selectedSummary = document.getElementById('selected-summary');
    const selectedCount = document.getElementById('selected-count');
    const selectedTotal = document.getElementById('selected-total');
    
    if (checkedItems.length === 0) {
        // Hide selected summary if no items selected
        selectedSummary.style.display = 'none';
        return;
    }
    
    // Show selected summary
    selectedSummary.style.display = 'block';
    
    let totalItems = 0;
    let totalAmount = 0;
    
    checkedItems.forEach(checkbox => {
        const cartRow = checkbox.closest('.cart-row');
        const prodID = cartRow.getAttribute('data-id');
        const qtyInput = cartRow.querySelector('.qty-input');
        const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
        const subtotalElement = cartRow.querySelector('.subtotal');
        
        if (subtotalElement) {
            // Extract price from subtotal text (e.g., "RM 400.00" or "RM RM 400.00")
            let subtotalText = subtotalElement.textContent;
            // Remove all "RM " occurrences and commas
            subtotalText = subtotalText.replace(/RM\s*/g, '').replace(/,/g, '');
            const subtotal = parseFloat(subtotalText) || 0;
            
            totalItems += qty;
            totalAmount += subtotal;
        }
    });
    
    // Update display
    selectedCount.textContent = totalItems;
    selectedTotal.textContent = `RM ${formatMoney(totalAmount)}`;
}

// Proceed to checkout
function proceedToCheckout() {
    console.log('proceedToCheckout called');
    
    // Get all checked items
    const checkedItems = document.querySelectorAll('.item-check:checked');
    const selectedItems = [];
    
    checkedItems.forEach(checkbox => {
        const cartRow = checkbox.closest('.cart-row');
        const prodID = cartRow.getAttribute('data-id');
        const qtyInput = cartRow.querySelector('.qty-input');
        const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
        
        selectedItems.push({
            prodID: prodID,
            qty: qty
        });
    });
    
    console.log('Selected items for checkout:', selectedItems);
    
    if (selectedItems.length === 0) {
        alert('Please select at least one item to checkout.');
        return;
    }
    
    // Create a form and submit it to checkout
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'checkout.php';
    
    const selectedItemsInput = document.createElement('input');
    selectedItemsInput.type = 'hidden';
    selectedItemsInput.name = 'selected_items';
    selectedItemsInput.value = JSON.stringify(selectedItems);
    
    form.appendChild(selectedItemsInput);
    document.body.appendChild(form);
    form.submit();
}

// Save checkbox states to localStorage
function saveCheckboxStates() {
    const checkboxes = document.querySelectorAll('.item-check');
    const states = {};
    
    checkboxes.forEach(checkbox => {
        const cartRow = checkbox.closest('.cart-row');
        const prodID = cartRow.getAttribute('data-id');
        states[prodID] = checkbox.checked;
    });
    
    localStorage.setItem('cart_selections', JSON.stringify(states));
}

// Load checkbox states from localStorage
function loadCheckboxStates() {
    const savedStates = localStorage.getItem('cart_selections');
    if (!savedStates) return;
    
    try {
        const states = JSON.parse(savedStates);
        const checkboxes = document.querySelectorAll('.item-check');
        
        checkboxes.forEach(checkbox => {
            const cartRow = checkbox.closest('.cart-row');
            const prodID = cartRow.getAttribute('data-id');
            
            if (states.hasOwnProperty(prodID)) {
                checkbox.checked = states[prodID];
            }
        });
        
        // Update selected summary after loading states
        updateSelectedSummary();
    } catch (error) {
        console.error('Error loading checkbox states:', error);
    }
}

// Clear saved selections (useful for checkout completion)
function clearSavedSelections() {
    localStorage.removeItem('cart_selections');
}
