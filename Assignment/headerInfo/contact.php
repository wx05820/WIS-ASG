<?php
$page_title = "Contact Us";
require_once '../header.php';
?>

<link rel="stylesheet" href="../css/contact.css">
    <div class="contact-container">
        <!-- Hero Section -->
        <section class="contact-hero">
            <div class="hero-content">
                <h1>Get in Touch</h1>
                <p class="hero-subtitle">We're here to help you create your perfect space</p>
                <div class="hero-divider"></div>
            </div>
        </section>

        <!-- Contact Information Section -->
        <section class="contact-info-section">
            <div class="container">
                <div class="section-header">
                    <h2>Contact Information</h2>
                    <div class="section-divider"></div>
                </div>
                
                <div class="contact-grid">
                    <!-- Phone Contact -->
                    <div class="contact-card">
                        <div class="card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <h3>Call Us</h3>
                        <p class="contact-detail">+60 12-345 6789</p>
                        <p class="contact-hours">Customer Service<br>Mon-Fri: 9AM-6PM</p>
                        <div class="contact-actions">
                            <a href="tel:+60123456789" class="action-btn call-btn">
                                <i class="fas fa-phone"></i>
                                Call Now
                            </a>
                        </div>
                    </div>

                    <!-- WhatsApp Contact -->
                    <div class="contact-card">
                        <div class="card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                            </svg>
                        </div>
                        <h3>WhatsApp Us</h3>
                        <p class="contact-detail">+60 12-345 6789</p>
                        <p class="contact-hours">Quick Response<br>Available 24/7</p>
                        <div class="contact-actions">
                             <a href="https://wa.me/60123456789?text=Hello%20AiKUN%20Furniture,%20I%20would%20like%20to%20inquire%20about%20your%20products." target="_blank" class="action-btn whatsapp-btn">
                                <i class="fab fa-whatsapp"></i>
                                Chat Now
                            </a>
                        </div>
                    </div>

                    <!-- Email Contact -->
                    <div class="contact-card">
                        <div class="card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <h3>Email Us</h3>
                        <p class="contact-detail">info@aikunfurniture.com</p>
                        <p class="contact-hours">We'll respond within<br>24 hours</p>
                        <div class="contact-actions">
                             <a href="mailto:info@aikunfurniture.com?subject=Inquiry%20from%20Website&body=Hello%20AiKUN%20Furniture,%0D%0A%0D%0AI%20would%20like%20to%20inquire%20about%20your%20products.%0D%0A%0D%0APlease%20contact%20me%20at%20your%20earliest%20convenience.%0D%0A%0D%0AThank%20you!" class="action-btn email-btn">
                                <i class="fas fa-envelope"></i>
                                Send Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Form Section -->
        <section class="contact-form-section">
            <div class="container">
                <div class="form-container">
                    <div class="form-header">
                        <h2>Send us a Message</h2>
                        <p>Have a question about our furniture? Need help with your order? We'd love to hear from you!</p>
                        
                        <?php if (isset($_SESSION['contact_success'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo htmlspecialchars($_SESSION['contact_success']); unset($_SESSION['contact_success']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['contact_error'])): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($_SESSION['contact_error']); unset($_SESSION['contact_error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['contact_errors']) && !empty($_SESSION['contact_errors'])): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ($_SESSION['contact_errors'] as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php unset($_SESSION['contact_errors']); ?>
                        <?php endif; ?>
                    </div>
                    
                    <form class="contact-form" action="../user/contact_submit.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <select id="subject" name="subject" required>
                                    <option value="">Select a subject</option>
                                    <option value="general" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'general') ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="order" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'order') ? 'selected' : ''; ?>>Order Support</option>
                                    <option value="product" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'product') ? 'selected' : ''; ?>>Product Question</option>
                                    <option value="delivery" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'delivery') ? 'selected' : ''; ?>>Delivery Information</option>
                                    <option value="return" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'return') ? 'selected' : ''; ?>>Returns & Exchanges</option>
                                    <option value="custom" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'custom') ? 'selected' : ''; ?>>Custom Furniture</option>
                                    <option value="other" <?php echo (($_SESSION['contact_form_data']['subject'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" placeholder="Tell us how we can help you..." required><?php echo htmlspecialchars($_SESSION['contact_form_data']['message'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="newsletter" value="1" <?php echo (($_SESSION['contact_form_data']['newsletter'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Subscribe to our newsletter for furniture tips and exclusive offers
                            </label>
                        </div>
                        
                        <button type="submit" class="submit-btn">
                            <span>Send Message</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22,2 15,22 11,13 2,9 22,2"></polygon>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq-section">
            <div class="container">
                <div class="section-header">
                    <h2>Frequently Asked Questions</h2>
                    <div class="section-divider"></div>
                </div>
                
                <div class="faq-grid">
                    <div class="faq-item">
                        <h3>What is your delivery timeframe?</h3>
                        <p>We typically deliver within 3-7 business days for in-stock items. Custom pieces may take 2-4 weeks. You'll receive tracking updates throughout the process.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>Do you offer assembly services?</h3>
                        <p>Yes! We provide professional assembly services for an additional fee. Our skilled team ensures your furniture is perfectly assembled and positioned.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>What is your return policy?</h3>
                        <p>We offer a 30-day return policy for unused items in original packaging. Custom pieces and special orders are final sale. See our full return policy for details.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>Can I customize furniture pieces?</h3>
                        <p>Absolutely! We specialize in custom furniture design. Contact us to discuss your vision, and we'll create a unique piece tailored to your space and style.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>Do you offer interior design consultation?</h3>
                        <p>Yes, our design team offers complimentary consultation services to help you choose the perfect furniture for your space. Book a consultation through our contact form.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>What materials do you use?</h3>
                        <p>We use only premium materials including solid wood, high-quality veneers, and durable hardware. All materials are sustainably sourced and built to last generations.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Map Section -->
        <section class="map-section">
            <div class="container">
                <div class="map-container">
                    <h2>Find Our Showroom</h2>
                    <div class="map-placeholder">
                        <div class="map-content">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <h3>25, Jalan Kepong, Jinjang Selatan</h3>
                            <p>52000 Kuala Lumpur, Malaysia</p>
                            <div class="map-buttons">
                                <button class="map-btn" onclick="getUserLocation()">
                                    <i class="fas fa-location-arrow"></i>
                                    Get Directions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="../js/contact.js"></script>

<?php require_once '../footer.php'; ?>




