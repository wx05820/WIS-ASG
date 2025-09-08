document.addEventListener('DOMContentLoaded', function() {
    initializeShippingTracking();
});

function initializeShippingTracking() {
    initToggleButtons();
    initFormHandlers();
    animateProgressBars();
    initUtilityFunctions();
}

function initToggleButtons() {
    // Handle Bootstrap collapse toggle buttons for tracking history
    document.querySelectorAll('.toggle-history').forEach(btn => {
        btn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Update icon and text based on current state
            setTimeout(() => {
                if (this.getAttribute('aria-expanded') === 'true') {
                    icon.classList.remove('fa-history');
                    icon.classList.add('fa-eye-slash');
                    this.innerHTML = this.innerHTML.replace('View Tracking History', 'Hide Tracking History');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-history');
                    this.innerHTML = this.innerHTML.replace('Hide Tracking History', 'View Tracking History');
                }
            }, 100);
        });
    });

    // Handle Bootstrap collapse toggle buttons for order items
    document.querySelectorAll('.toggle-items').forEach(btn => {
        btn.addEventListener('click', function() {
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
    // Handle reorder buttons with confirmation and loading state
    document.querySelectorAll('.reorder-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const orderID = this.dataset.order;
            
            // Show confirmation dialog
            const confirmed = confirm('This will add all items from this order to your cart. Continue?');
            if (!confirmed) return;
            
            // Ask if user wants to go to cart
            const gotoCart = confirm('Go to cart after adding items?');
            
            // Show loading state
            showButtonLoading(this, 'Adding...');
            
            // Submit form
            submitReorderForm(this, orderID, gotoCart);
        });
    });

    // Handle other form submissions with loading states
    document.querySelectorAll('form button[type="submit"]:not(.reorder-btn)').forEach(btn => {
        btn.addEventListener('click', function() {
            showButtonLoading(this, 'Processing');
        });
    });
}

function animateProgressBars() {
    // Animate progress bars on page load
    const progressBars = document.querySelectorAll('.progress-bar');
    
    // Small delay to ensure DOM is ready
    setTimeout(() => {
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            // Animate to target width
            setTimeout(() => {
                bar.style.width = width;
            }, 200);
        });
    }, 100);

    // Animate timeline steps
    const timelineSteps = document.querySelectorAll('.timeline-step.completed');
    timelineSteps.forEach((step, index) => {
        setTimeout(() => {
            step.style.transform = 'scale(1.1)';
            setTimeout(() => {
                step.style.transform = 'scale(1)';
            }, 200);
        }, index * 150);
    });
}

function initUtilityFunctions() {
    // Add smooth scroll behavior
    document.documentElement.style.scrollBehavior = 'smooth';
    
    // Add keyboard navigation for tracking cards
    document.querySelectorAll('.tracking-card').forEach(card => {
        card.setAttribute('tabindex', '0');
        
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const toggleBtn = this.querySelector('.toggle-history, .toggle-items');
                if (toggleBtn) {
                    e.preventDefault();
                    toggleBtn.click();
                }
            }
        });
    });

    // Auto-refresh tracking status every 5 minutes (optional)
    if (window.location.search.includes('auto_refresh=1')) {
        setInterval(() => {
            // Only refresh if user is still active
            if (document.hasFocus()) {
                location.reload();
            }
        }, 5 * 60 * 1000); // 5 minutes
    }

    // Add hover effects to tracking cards
    addTrackingCardEffects();
}

function addTrackingCardEffects() {
    document.querySelectorAll('.tracking-card').forEach(card => {
        // Add pulse animation to active orders
        const statusBadge = card.querySelector('.badge');
        if (statusBadge && (statusBadge.textContent.toLowerCase().includes('processing') || 
                           statusBadge.textContent.toLowerCase().includes('shipped'))) {
            card.classList.add('active-order');
            
            // Add subtle pulse animation
            const pulseAnimation = setInterval(() => {
                if (document.contains(card)) {
                    card.style.boxShadow = '0 4px 15px rgba(0, 123, 255, 0.3)';
                    setTimeout(() => {
                        if (document.contains(card)) {
                            card.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
                        }
                    }, 1000);
                } else {
                    clearInterval(pulseAnimation);
                }
            }, 3000);
        }
    });
}

// Utility Functions
function showButtonLoading(button, text = 'Processing') {
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = text;
    
    // Store original content for potential restoration
    button.dataset.originalContent = originalContent;
    
    // Fallback restoration after 15 seconds
    setTimeout(() => {
        if (button.disabled) {
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    }, 15000);
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
    
    return form;
}

// Show success/error messages if they exist in session
function showMessage(message, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${icon}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
}

// Update tracking progress dynamically (for real-time updates)
function updateTrackingProgress(orderID, newStatus) {
    const card = document.querySelector(`[data-order-id="${orderID}"]`);
    if (!card) return;
    
    const statusBadge = card.querySelector('.badge');
    const progressBar = card.querySelector('.progress-bar');
    const timelineSteps = card.querySelectorAll('.timeline-step');
    
    // Update status badge
    if (statusBadge) {
        statusBadge.className = `badge bg-${getStatusBadgeClass(newStatus)}`;
        statusBadge.innerHTML = `<i class="${getStatusIcon(newStatus)}"></i> ${ucfirst(newStatus)}`;
    }
    
    // Update progress bar
    if (progressBar) {
        const progress = getTrackingProgress(newStatus);
        progressBar.style.width = `${progress}%`;
        progressBar.className = `progress-bar bg-${getStatusBadgeClass(newStatus)}`;
    }
    
    // Update timeline steps
    const statusOrder = ['Pending', 'Confirmed', 'Processing', 'Shipped'];
    const currentStatusIndex = statusOrder.indexOf(newStatus.toLowerCase());
    
    timelineSteps.forEach((step, index) => {
        if (index <= currentStatusIndex) {
            step.classList.add('Completed');
            step.classList.remove('Pending');
        } else {
            step.classList.add('Pending');
            step.classList.remove('Completed');
        }
    });
}

// Helper functions (mirror PHP functions in JavaScript)
function getStatusBadgeClass(status) {
    const statusClasses = {
        'Pending': 'warning',
        'Confirmed': 'info',
        'Processing': 'primary',
        'Shipped': 'secondary'
    };
    return statusClasses[status.toLowerCase()] || 'secondary';
}

function getStatusIcon(status) {
    const statusIcons = {
        'Pending': 'fas fa-clock',
        'Confirmed': 'fas fa-check-circle',
        'Processing': 'fas fa-cog fa-spin',
        'Shipped': 'fas fa-shipping-fast'
    };
    return statusIcons[status.toLowerCase()] || 'fas fa-info-circle';
}

function getTrackingProgress(status) {
    const statusOrder = ['Pending', 'Confirmed', 'Processing', 'Shipped'];
    const currentIndex = statusOrder.indexOf(status);
    return currentIndex !== -1 ? ((currentIndex + 1) / statusOrder.length) * 100 : 0;
}

// Export utilities for other scripts
window.ShippingTrackingUtils = {
    createActionForm,
    showMessage,
    showButtonLoading,
    updateTrackingProgress
};