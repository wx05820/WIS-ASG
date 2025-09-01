function showLoginPrompt() {
    showError('Please log in to add items to your cart');
    setTimeout(() => {
        window.location.href = '../user/login.php';
    }, 2000);
}

async function addToCart(productId, qty = 1) {
    try {
        // Check if user is logged in first
        const userId = document.body.dataset.userId || document.querySelector('.container')?.dataset.userId;
        if (!userId) {
            showError("Please log in to add items to your cart");
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('prodID', productId);
        formData.append('qty', qty);

        const response = await fetch('/order/cart_add.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

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

// CHANGED: Improved buy now function with proper validation
async function buyNow(productId) {
    try {
        // Check if user is logged in first
        const userId = document.body.dataset.userId || document.querySelector('.container')?.dataset.userId;
        if (!userId) {
            showError("Please log in to make a purchase");
            setTimeout(() => { window.location.href = "/user/login.php"; }, 2000);
            return;
        }

        // Create form for buy now (this needs to be a form submission for proper checkout flow)
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../order/checkout.php';
        
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

// CHANGED: Added notification functions for better user feedback
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
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Skip for add address form
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

    document.querySelectorAll('.add-to-cart, .btn-add').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            let productId = this.dataset.productId || this.closest('.product-card')?.dataset.id || this.closest('form')?.querySelector('input[name="prodID"]')?.value;
            
            if (!productId) {
                showError('Unable to find product information');
                return;
            }
            
            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Adding...';
            
            try {
                // Check if user is logged in
                const userId = document.body.dataset.userId;
                if (!userId) {
                    showError("Please log in to add items to your cart");
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('prodID', productId);
                formData.append('qty', 1);

                const response = await fetch('/order/cart_add.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

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
            } finally {
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });

    // Handle buy now buttons
    document.querySelectorAll('.btn-checkout').forEach(btn => {
        btn.addEventListener('click', async function(e) {

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
});