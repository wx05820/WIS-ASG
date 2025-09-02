function showLoginPrompt() {
    showError('Please log in to add items to your cart');
    setTimeout(() => {
        window.location.href = '../user/login.php';
    }, 2000);
}

async function addToCart(productId, qty = 1) {
    try {
        const userId = document.body.dataset.userId || document.querySelector('.container')?.dataset.userId;
        if (!userId) {
            showLoginPrompt();
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('prodID', productId);
        formData.append('qty', qty);

        // Resolve endpoint from the form's action attribute (avoid collision with input[name="action"]) 
        const cartForm = document.querySelector('form.cart-form');
        const endpoint = (cartForm && cartForm.getAttribute('action')) || '/order/cart_add.php';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        // If server redirects to login, follow it
        if (response.redirected && response.url.includes('/user/login.php')) {
            window.location.href = response.url;
            return;
        }

        if (!response.ok) {
            throw new Error('Unable to add item to your cart');
        }

        showSuccess('Added item to your cart successfully');
        
        // Update mini cart if function exists
        if (typeof updateMiniCart === 'function') {
            updateMiniCart();
        }

    } catch (error) {
        console.error('Add to cart error:', error);
        showError('Unable to add item to your cart');
    }
}

async function buyNow(productId) {
    try {
        const userId = document.body.dataset.userId || document.querySelector('.container')?.dataset.userId;
        if (!userId) {
            showError("Please log in to make a purchase");
            setTimeout(() => { window.location.href = "/user/login.php"; }, 2000);
            return;
        }

        // Create form for buy now
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/order/checkout.php';
        
        const prodInput = document.createElement('input');
        prodInput.type = 'hidden';
        prodInput.name = 'prodID';
        prodInput.value = productId;
        
        const buyNowInput = document.createElement('input');
        buyNowInput.type = 'hidden';
        buyNowInput.name = 'buy_now';
        buyNowInput.value = '1';
        
        form.appendChild(prodInput);
        form.appendChild(buyNowInput);
        document.body.appendChild(form);
        form.submit();

    } catch (error) {
        console.error('Buy now error:', error);
        showError('Unable to proceed to checkout');
    }
}

function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
    // Remove any existing notifications
    const existing = document.querySelector('.user-notification');
    if (existing) {
        existing.remove();
    }
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = 'user-notification';
    const bgColor = type === 'error' ? '#ff4444' : '#4CAF50';
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

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-to-cart, .btn-add').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const formEl = this.closest('form');
            let productId = this.dataset.productId || this.closest('.product-card')?.dataset.id || formEl?.querySelector('input[name="prodID"]').value;
            
            if (!productId) {
                showError('Unable to find product information');
                return;
            }
            
            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Adding...';
            
            try {
                const userId = document.body.dataset.userId;
                if (!userId) {
                    showLoginPrompt();
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('prodID', productId);
                // Prefer quantity from associated input if present
                const qtyInput = formEl?.querySelector('input[name="qty"], input[id^="list-qty-"], #detail-qty');
                const qtyVal = qtyInput ? parseInt(qtyInput.value || '1', 10) : 1;
                formData.append('qty', Math.max(1, qtyVal));

                const endpoint = (formEl && formEl.getAttribute('action')) ? formEl.getAttribute('action') : '/order/cart_add.php';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                // Handle redirect to login
                if (response.redirected && response.url.includes('/user/login.php')) {
                    window.location.href = response.url;
                    return;
                }

                if (!response.ok) {
                    throw new Error('Unable to add item to your cart');
                }

                showSuccess('Added item to your cart successfully');
                
                if (typeof updateMiniCart === 'function') {
                    updateMiniCart();
                }

            } catch (error) {
                console.error('Add to cart error:', error);
                showError('Unable to add item to your cart');
            } finally {
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });

    // AJAX wishlist add/remove on product pages/lists
    document.querySelectorAll('.wishlist-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            // Confirm remove
            const action = (this.querySelector('input[name="action"]')?.value || '').toLowerCase();
            if (action === 'remove') {
                const title = this.closest('.product-card')?.querySelector('.product-name')?.textContent?.trim() || 'this item';
                if (!confirm(`Remove ${title} from wishlist?`)) {
                    return;
                }
            }
            const userId = document.body.dataset.userId;
            if (!userId) {
                showError("Please log in to use wishlist");
                setTimeout(() => { window.location.href = "/user/login.php"; }, 1200);
                return;
            }
            const btn = this.querySelector('button[type="submit"]');
            const actionInput = this.querySelector('input[name="action"]');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-heart"></i> Saving...';
            try {
                const formData = new FormData(this);
                const res = await fetch(this.getAttribute('action'), { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} });
                if (!res.ok) throw new Error('Wishlist request failed');
                // Toggle UI/state
                if (formData.get('action') === 'add') {
                    btn.classList.add('added');
                    btn.innerHTML = '<i class="fas fa-heart"></i> Added';
                    if (actionInput) actionInput.value = 'remove';
                } else {
                    btn.classList.remove('added');
                    btn.innerHTML = '<i class="fas fa-heart"></i> Wishlist';
                    if (actionInput) actionInput.value = 'add';
                }
                showSuccess('Wishlist updated');
            } catch (err) {
                console.error(err);
                showError('Unable to update wishlist');
            } finally {
                btn.disabled = false;
            }
        });
    });

    // AJAX wishlist add/remove on product pages/lists
    document.querySelectorAll('.wishlist-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            // Confirm remove
            const action = (this.querySelector('input[name="action"]')?.value || '').toLowerCase();
            if (action === 'remove') {
                const title = this.closest('.product-card')?.querySelector('.product-name')?.textContent?.trim() || 'this item';
                if (!confirm(`Remove ${title} from wishlist?`)) {
                    return;
                }
            }
            const userId = document.body.dataset.userId;
            if (!userId) {
                showError("Please log in to use wishlist");
                setTimeout(() => { window.location.href = "/user/login.php"; }, 1200);
                return;
            }
            const btn = this.querySelector('button[type="submit"]');
            const actionInput = this.querySelector('input[name="action"]');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-heart"></i> Saving...';
            try {
                const formData = new FormData(this);
                const res = await fetch(this.getAttribute('action'), { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} });
                if (!res.ok) throw new Error('Wishlist request failed');
                // Toggle UI/state
                if (formData.get('action') === 'add') {
                    btn.classList.add('added');
                    btn.innerHTML = '<i class="fas fa-heart"></i> Added';
                    if (actionInput) actionInput.value = 'remove';
                } else {
                    btn.classList.remove('added');
                    btn.innerHTML = '<i class="fas fa-heart"></i> Wishlist';
                    if (actionInput) actionInput.value = 'add';
                }
                showSuccess('Wishlist updated');
            } catch (err) {
                console.error(err);
                showError('Unable to update wishlist');
            } finally {
                btn.disabled = false;
            }
        });
    });

    // Buy now buttons
    document.querySelectorAll('.btn-checkout').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            // If inside a form, let the form handle submission
            if (this.closest('form')) {
                return; 
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            let productId = this.dataset.productId || this.closest('.product-card')?.dataset.id || this.closest('form')?.querySelector('input[name="prodID"]')?.value;
            
            if (!productId) {
                showError('Unable to find product information');
                return;
            }
            
            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Processing...';
            
            try {
                await buyNow(productId);
            } finally {
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });

    // Quantity +/- controls
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetSel = this.getAttribute('data-target');
            const input = document.querySelector(targetSel);
            if (!input) return;
            const max = parseInt(input.getAttribute('max') || '9999', 10);
            const min = parseInt(input.getAttribute('min') || '1', 10);
            let val = parseInt(input.value || '1', 10);
            const isPlus = this.getAttribute('data-op') === 'plus';
            val = isPlus ? Math.min(max, val + 1) : Math.max(min, val - 1);
            input.value = val;
            // Trigger change for any listeners
            input.dispatchEvent(new Event('change'));
        });
    });

    // Handle form submissions for buy now and add to cart
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Skip for add address form or other specific forms
        if (form.id === 'addAddressForm') return;
        
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.textContent || submitBtn.value;
                submitBtn.disabled = true;
                
                if (submitBtn.classList.contains('btn-add') || submitBtn.classList.contains('add-to-cart')) {
                    submitBtn.textContent = 'Adding...';
                } else if (submitBtn.classList.contains('btn-checkout')) {
                    submitBtn.textContent = 'Processing...';
                } else {
                    submitBtn.textContent = 'Processing...';
                }
                
                // Re-enable after 3 seconds in case of error
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 3000);
            }
        });
    });
});