<?php
// Shipping Information Page for AiKUN Furniture
?>

<div class="shipping-info-container">
    <div class="shipping-header">
        <h2><i class="fas fa-shipping-fast"></i> Shipping & Delivery Information</h2>
        <p class="shipping-subtitle">Fast, reliable delivery to your doorstep</p>
    </div>

    <div class="shipping-content">
        <!-- Delivery Options -->
        <div class="shipping-section">
            <h3><i class="fas fa-truck"></i> Delivery Options</h3>
            <div class="delivery-options">
                <div class="delivery-option">
                    <div class="delivery-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="delivery-details">
                        <h4>Standard Delivery</h4>
                        <p class="delivery-time">3-5 business days</p>
                        <p class="delivery-price">Free on orders over RM200</p>
                        <p class="delivery-description">Delivered to your doorstep during business hours</p>
                    </div>
                </div>
                
                <div class="delivery-option">
                    <div class="delivery-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="delivery-details">
                        <h4>Express Delivery</h4>
                        <p class="delivery-time">1-2 business days</p>
                        <p class="delivery-price">RM25</p>
                        <p class="delivery-description">Priority delivery with tracking updates</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking Information -->
        <div class="shipping-section">
            <h3><i class="fas fa-search"></i> Track Your Order</h3>
            <div class="tracking-section">
                <div class="tracking-form">
                    <form action="order/tracking.php" method="GET">
                        <div class="form-group">
                            <label for="tracking-number">Enter your tracking number:</label>
                            <div class="input-group">
                                <input type="text" 
                                       id="tracking-number" 
                                       name="tracking_number" 
                                       placeholder="e.g., AK123456789" 
                                       required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Track
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="shipping-section">
            <h3><i class="fas fa-headset"></i> Need Help?</h3>
            <div class="contact-info">
                <div class="contact-method">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <h4>Phone Support</h4>
                        <p>+60 3-1234 5678</p>
                        <p>Mon-Fri: 9AM-6PM</p>
                    </div>
                </div>
                
                <div class="contact-method">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <h4>Email Support</h4>
                        <p>shipping@aikun.com</p>
                        <p>Response within 2 hours</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>