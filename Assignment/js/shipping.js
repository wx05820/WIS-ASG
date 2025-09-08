// Shipping and Tracking Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Shipping page loaded');
    
    // Initialize all interactive elements
    initializeToggleButtons();
    initializeProgressBars();
    initializeTimelineAnimations();
    // Note: Shipping dropdown is handled by script.js globally
    
    // Auto-refresh tracking data every 30 seconds
    setInterval(refreshTrackingData, 30000);
});

// Initialize toggle buttons for collapsible content
function initializeToggleButtons() {
    const toggleButtons = document.querySelectorAll('.toggle-items, .toggle-history');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-bs-target');
            const target = document.querySelector(targetId);
            const icon = this.querySelector('i');
            
            if (target && icon) {
                // Toggle icon rotation
                if (target.classList.contains('show')) {
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    icon.style.transform = 'rotate(180deg)';
                }
            }
        });
    });
}

// Initialize progress bars with animation
function initializeProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        // Animate progress bar
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 500);
    });
}

// Initialize timeline animations
function initializeTimelineAnimations() {
    const timelineSteps = document.querySelectorAll('.timeline-step');
    
    timelineSteps.forEach((step, index) => {
        // Add staggered animation delay
        step.style.animationDelay = `${index * 0.2}s`;
        step.classList.add('animate-in');
    });
}

// Initialize shipping dropdown functionality
// Shipping dropdown is now handled by script.js globally

// Refresh tracking data (placeholder for future AJAX implementation)
function refreshTrackingData() {
    console.log('Refreshing tracking data...');
    
    // This would typically make an AJAX call to update order statuses
    // For now, we'll just log the action
    const trackingCards = document.querySelectorAll('.tracking-card');
    
    trackingCards.forEach(card => {
        const orderId = card.querySelector('h5')?.textContent?.replace('Order #', '');
        if (orderId) {
            console.log(`Checking status for order ${orderId}`);
        }
    });
}

// Update order status (for admin use or future implementation)
function updateOrderStatus(orderId, newStatus) {
    console.log(`Updating order ${orderId} to status: ${newStatus}`);
    
    // This would typically make an AJAX call to update the order status
    // For now, we'll just show a notification
    showNotification(`Order ${orderId} status updated to ${newStatus}`, 'success');
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Track order button functionality
function trackOrder(orderId) {
    console.log(`Tracking order: ${orderId}`);
    
    // Redirect to tracking details page
    window.location.href = `tracking_details.php?id=${orderId}`;
}

// Reorder functionality
function reorderItems(orderId) {
    console.log(`Reordering items from order: ${orderId}`);
    
    // Show confirmation dialog
    if (confirm('Are you sure you want to reorder these items?')) {
        // This would typically make an AJAX call to add items to cart
        showNotification('Items added to cart for reorder', 'success');
    }
}

// Cancel order functionality
function cancelOrder(orderId) {
    console.log(`Cancelling order: ${orderId}`);
    
    // Show confirmation dialog
    if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
        // This would typically make an AJAX call to cancel the order
        showNotification('Order cancellation request submitted', 'warning');
    }
}

// View order details
function viewOrderDetails(orderId) {
    console.log(`Viewing details for order: ${orderId}`);
    window.location.href = `history_details.php?id=${orderId}`;
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    .timeline-step.animate-in {
        animation: slideInUp 0.6s ease-out forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    @keyframes slideInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .progress-bar {
        position: relative;
        overflow: hidden;
    }
    
    .progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(255, 255, 255, 0.3),
            transparent
        );
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    .tracking-card {
        transition: all 0.3s ease;
    }
    
    .tracking-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }
    
    .item-row {
        transition: all 0.3s ease;
    }
    
    .item-row:hover {
        transform: translateX(8px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
`;
document.head.appendChild(style);
