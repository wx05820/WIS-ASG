/**
 * Order History JavaScript
 * Handles order history page functionality including form submissions, 
 * star ratings, and interactive elements
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeOrderHistory();
});

function initializeOrderHistory() {
    initToggleButtons();
    initStarRatings();
    initFormHandlers();
    initUtilityFunctions();
    checkRefundButtonStates();
}

/**
 * Initialize toggle buttons for showing/hiding order items
 */
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

/**
 * Initialize star rating functionality for product reviews
 */
function initStarRatings() {
    // Add delay to ensure DOM is fully loaded
    setTimeout(function() {
        // Handle both regular and small star ratings
        document.querySelectorAll('.star-rating, .star-rating-small').forEach(ratingContainer => {
            const stars = ratingContainer.querySelectorAll('.star, .star-small');
            
            if (stars.length === 0) return;
            
            // Find the associated form and product ID
            const form = ratingContainer.closest('form');
            if (!form) return;
            
            const productIdInput = form.querySelector('input[name="product_id"]');
            if (!productIdInput) return;
            
            const productId = productIdInput.value;
            
            // For regular star ratings, look for the rating input
            let ratingInput = null;
            if (ratingContainer.classList.contains('star-rating')) {
                // Try different ways to find the rating input
                ratingInput = document.getElementById('rating_' + productId) || 
                             form.querySelector('input[name="rating"]');
            } else {
                // For small star ratings, look for the specific rating input
                const ratingType = ratingContainer.closest('.form-group').querySelector('label').textContent.toLowerCase();
                if (ratingType.includes('quality')) {
                    ratingInput = form.querySelector('input[name="quality_rating"]');
                } else if (ratingType.includes('value')) {
                    ratingInput = form.querySelector('input[name="value_rating"]');
                } else if (ratingType.includes('delivery')) {
                    ratingInput = form.querySelector('input[name="delivery_rating"]');
                }
            }
            
            if (!ratingInput) return;
            
            stars.forEach((star, index) => {
                // Click handler
                star.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const rating = parseInt(this.getAttribute('data-value'));
                    
                    // Update visual stars
                    updateStarVisuals(stars, rating);
                    
                    // Update hidden input and container attribute
                    ratingInput.value = rating;
                    ratingContainer.setAttribute('data-rating', rating);
                    
                    console.log('Rating set for product ' + productId + ':', rating);
                });
                
                // Hover effects
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-value'));
                    updateStarVisuals(stars, rating);
                });
                
                star.addEventListener('mouseout', function() {
                    const currentRating = parseInt(ratingContainer.getAttribute('data-rating')) || 0;
                    updateStarVisuals(stars, currentRating);
                });
            });
        });
    }, 500);
}

/**
 * Update visual appearance of stars based on rating
 */
function updateStarVisuals(stars, rating) {
    stars.forEach((star, index) => {
        if (index + 1 <= rating) {
            star.style.color = '#ffc107';
        } else {
            star.style.color = '#ddd';
        }
    });
}

/**
 * Initialize form handlers for various order actions
 */
function initFormHandlers() {
    // Handle reorder buttons
    initReorderButtons();
    
    // Handle cancel order buttons
    initCancelOrderButtons();
    
    // Handle general form submissions (exclude review forms)
    initGeneralFormSubmissions();
    
    // Handle review form submissions separately
    initReviewFormSubmissions();
}

/**
 * Initialize reorder button functionality
 */
function initReorderButtons() {
    document.querySelectorAll('.reorder-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const orderID = this.dataset.order;
            
            if (!orderID) {
                console.error('No order ID found for reorder button');
                return;
            }
            
            const gotoCart = confirm('Items will be added to your cart. Go to cart after adding?');

            // Show loading state
            showButtonLoading(this, 'Adding...');
            
            // Submit form
            submitReorderForm(this, orderID, gotoCart);
        });
    });
}

/**
 * Initialize cancel order button functionality
 */
function initCancelOrderButtons() {
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
                const newForm = createActionForm('../order/cancel_order.php', {
                    orderID: orderID
                });
                newForm.submit();
            }
        });
    });
}

/**
 * Initialize general form submissions (excluding review forms)
 */
function initGeneralFormSubmissions() {
    setTimeout(() => {
        document.querySelectorAll('form').forEach(form => {
            // Skip review forms and specific action forms
            if (isReviewForm(form) || isSpecialActionForm(form)) {
                return;
            }
            
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]:not(.reorder-btn):not(.cancel-order-btn), input[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    const originalText = submitBtn.textContent || submitBtn.value;
                    
                    setTimeout(() => {
                        submitBtn.disabled = true;
                        
                        if (submitBtn.textContent !== undefined) {
                            submitBtn.innerHTML = 'Processing...';
                        } else {
                            submitBtn.value = 'Processing...';
                        }
                    }, 10);
                    
                    // Re-enable after 15 seconds as fallback
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.disabled = false;
                            if (submitBtn.textContent !== undefined) {
                                submitBtn.innerHTML = originalText;
                            } else {
                                submitBtn.value = originalText;
                            }
                        }
                    }, 15000);
                }
            });
        });
    }, 600);
}

/**
 * Initialize review form submissions with validation
 */
function initReviewFormSubmissions() {
    document.querySelectorAll('.review-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const productIdInput = this.querySelector('input[name="product_id"]');
            if (!productIdInput) return;
            
            const productId = productIdInput.value;
            // Fix: Look for rating input within the form, not globally
            const ratingInput = this.querySelector('input[name="rating"]');
            const titleInput = this.querySelector('input[name="title"]');
            const reviewTextInput = this.querySelector('textarea[name="review_text"]');
            
            
            // Validate required fields
            if (!ratingInput || !ratingInput.value || ratingInput.value < 1) {
                e.preventDefault();
                alert('Please select a rating for this product.');
                return false;
            }
            
            if (!titleInput || !titleInput.value.trim()) {
                e.preventDefault();
                alert('Please enter a title for your review.');
                titleInput?.focus();
                return false;
            }
            
            if (!reviewTextInput || !reviewTextInput.value.trim()) {
                e.preventDefault();
                alert('Please enter your review text.');
                reviewTextInput?.focus();
                return false;
            }
            
            // Show loading state for review submission
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                showButtonLoading(submitBtn, '<i class="fas fa-paper-plane"></i> Submitting...');
            }
            
        });
    });
}

/**
 * Check if form is a review form
 */
function isReviewForm(form) {
    return form.classList.contains('review-form') ||
           form.action.includes('submit_review') ||
           form.querySelector('input[name="product_id"]') !== null;
}

/**
 * Check if form is a special action form that should be handled separately
 */
function isSpecialActionForm(form) {
    return form.action.includes('request_refund.php') ||
           form.action.includes('cancel_refund_request.php') ||
           form.action.includes('reorder.php') ||
           form.action.includes('cancel_order.php');
}

/**
 * Initialize utility functions and keyboard navigation
 */
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
    
    // Initialize print functionality
    const printButtons = document.querySelectorAll('[onclick="window.print()"]');
    printButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    });
}

/**
 * Check and update refund button states
 */
function checkRefundButtonStates() {
    // The PHP handles the correct button display based on order status
    console.log('Refund button states checked - PHP handles the display based on order status');
    
    // Debug: Check what buttons exist
    const refundButtons = document.querySelectorAll('.refund-btn, [action*="request_refund"] button');
    const cancelButtons = document.querySelectorAll('form[action*="cancel_refund_request"] button');
    console.log('Found refund-related buttons:', refundButtons.length);
    console.log('Found cancel refund buttons:', cancelButtons.length);
}

/**
 * Show loading state on button with optional restoration
 */
function showButtonLoading(button, text = 'Processing...', shouldRestore = true) {
    if (!button) return;
    
    // Don't interfere with refund-related buttons - PHP handles their state
    const form = button.closest('form');
    if (form && (form.action.includes('request_refund.php') || form.action.includes('cancel_refund_request.php'))) {
        return;
    }
    
    const originalContent = button.innerHTML;
    const originalDisabled = button.disabled;
    
    button.disabled = true;
    button.innerHTML = text;
    
    // Store original content for potential restoration
    button.dataset.originalContent = originalContent;
    button.dataset.originalDisabled = originalDisabled;
    
    // Only restore for non-permanent actions
    if (shouldRestore && !button.classList.contains('refund-btn')) {
        setTimeout(() => {
            if (button.disabled && button.innerHTML === text) {
                button.disabled = originalDisabled;
                button.innerHTML = originalContent;
            }
        }, 15000);
    }
}

/**
 * Submit reorder form with optional cart redirect
 */
function submitReorderForm(button, orderID, gotoCart = false) {
    const form = button.closest('form');
    
    if (form) {
        // Add goto_cart parameter if requested
        if (gotoCart) {
            let gotoCartInput = form.querySelector('input[name="goto_cart"]');
            if (!gotoCartInput) {
                gotoCartInput = document.createElement('input');
                gotoCartInput.type = 'hidden';
                gotoCartInput.name = 'goto_cart';
                form.appendChild(gotoCartInput);
            }
            gotoCartInput.value = '1';
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

/**
 * Utility function to create temporary forms for actions
 */
function createActionForm(action, data, method = 'POST') {
    const form = document.createElement('form');
    form.method = method;
    form.action = action;
    form.style.display = 'none';
    
    // Add CSRF token if available
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfMeta.getAttribute('content');
        form.appendChild(csrfInput);
    }
    
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




/**
 * Utility function to show success messages
 */
function showSuccess(message) {
    // Implementation depends on your notification system
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        alert('Success: ' + message);
    }
}

/**
 * Utility function to show error messages
 */
function showError(message) {
    // Implementation depends on your notification system
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message,
            confirmButtonText: 'OK'
        });
    } else {
        alert('Error: ' + message);
    }
}

/**
 * Export utilities for other scripts
 */
window.OrderHistoryUtils = {
    createActionForm,
    showButtonLoading,
    updateStarVisuals,
    showSuccess,
    showError
};

// Debug helper
window.OrderHistoryDebug = {
    logFormElements: function() {
        console.log('Review forms:', document.querySelectorAll('.review-form').length);
        console.log('Star ratings:', document.querySelectorAll('.star-rating').length);
        console.log('All forms:', document.querySelectorAll('form').length);
    },
    testStarRating: function(productId, rating) {
        const ratingInput = document.getElementById('rating_' + productId);
        const starContainer = document.querySelector(`form input[name="product_id"][value="${productId}"]`)?.closest('form')?.querySelector('.star-rating');
        
        if (ratingInput && starContainer) {
            ratingInput.value = rating;
            starContainer.setAttribute('data-rating', rating);
            const stars = starContainer.querySelectorAll('.star');
            updateStarVisuals(stars, rating);
            console.log('Test rating set for product', productId, ':', rating);
        } else {
            console.log('Could not find rating elements for product', productId);
        }
    }
};