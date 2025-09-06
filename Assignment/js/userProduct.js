// User Product functionality JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize product page functionality
    initializeProductPage();
    initializeProductList();
});

function initializeProductPage() {
    // Product detail page specific functionality
    const productDetailPage = document.querySelector('.product-detail');
    if (!productDetailPage) return;
    
    // Image gallery functionality
    const mainImage = document.querySelector('.main-image img');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    if (mainImage && thumbnails.length > 0) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const newSrc = this.src;
                if (mainImage.src !== newSrc) {
                    mainImage.src = newSrc;
                    
                    // Update active thumbnail
                    thumbnails.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    }
    
    // Quantity controls for product detail page
    const qtyInput = document.getElementById('detail-qty');
    if (qtyInput) {
        // Ensure quantity input works properly
        qtyInput.addEventListener('change', function() {
            const value = parseInt(this.value);
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            
            if (isNaN(value) || value < min) {
                this.value = min;
            } else if (value > max) {
                this.value = max;
            }
        });
    }
}

function initializeProductList() {
    // Product list page specific functionality
    const productListPage = document.querySelector('.product-list');
    if (!productListPage) return;
    
    // Search functionality
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // Real-time search could be implemented here
            console.log('Search query:', this.value);
        });
    }
    
    // Filter functionality
    const filterSelects = document.querySelectorAll('select[name="category"], select[name="sort"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Auto-submit form when filter changes
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Product card hover effects
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

// Utility functions
function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.product-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `product-notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        padding: 12px 20px;
        border-radius: 5px;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease-out;
        max-width: 300px;
        word-wrap: break-word;
    `;
    
    // Add animation CSS if not already present
    if (!document.querySelector('#product-notification-styles')) {
        const style = document.createElement('style');
        style.id = 'product-notification-styles';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
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

// Form validation
function validateProductForm(form) {
    const qtyInput = form.querySelector('input[name="qty"]');
    if (qtyInput) {
        const value = parseInt(qtyInput.value);
        const min = parseInt(qtyInput.getAttribute('min')) || 1;
        const max = parseInt(qtyInput.getAttribute('max')) || 999;
        
        if (isNaN(value) || value < min || value > max) {
            showError(`Please enter a valid quantity between ${min} and ${max}`);
            return false;
        }
    }
    
    return true;
}

// Wishlist functionality
function toggleWishlist(productId) {
    // This would make an AJAX call to toggle wishlist
    console.log('Toggle wishlist for product:', productId);
}

// Quick view functionality
function showQuickView(productId) {
    // This would show a quick view modal
    console.log('Show quick view for product:', productId);
}

// Login prompt functionality
function showLoginPrompt() {
    // Create a more prominent login prompt
    const loginModal = document.createElement('div');
    loginModal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
    `;
    
    modalContent.innerHTML = `
        <div style="font-size: 48px; color: #8B4513; margin-bottom: 20px;">
            <i class="fas fa-lock"></i>
        </div>
        <h3 style="color: #8B4513; margin-bottom: 15px;">Login Required</h3>
        <p style="color: #666; margin-bottom: 25px; line-height: 1.5;">
            Please log in to add items to your cart or make a purchase.
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button onclick="window.location.href='../user/login.php'" 
                    style="background: #8B4513; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button onclick="closeLoginModal()" 
                    style="background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer;">
                Cancel
            </button>
        </div>
    `;
    
    // Add CSS animations if not already present
    if (!document.querySelector('#login-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'login-modal-styles';
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideIn {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    loginModal.appendChild(modalContent);
    document.body.appendChild(loginModal);
    
    // Close modal when clicking outside
    loginModal.addEventListener('click', function(e) {
        if (e.target === loginModal) {
            closeLoginModal();
        }
    });
    
    // Store reference for closing
    window.currentLoginModal = loginModal;
}

function closeLoginModal() {
    if (window.currentLoginModal) {
        window.currentLoginModal.remove();
        window.currentLoginModal = null;
    }
}
