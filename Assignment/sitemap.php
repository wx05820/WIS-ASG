<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Map | AiKUN Furniture</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="floating-elements"></div>
    
    <div class="privacy-container">
        <div class="privacy-box">
            <div class="privacy-header">
                <div class="brand-section">
                    <img src="images/logo.png" alt="AiKUN Logo" class="brand-logo">
                    <div class="brand-name">AiKUN</div>
                </div>
                <h1>Site Map</h1>
                <p>Last Updated: <?php echo date('Y'); ?></p>
            </div>

            <section class="sitemap-section">
                <h2>Main Pages</h2>
                <ul class="sitemap-list">
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="products.php"><i class="fas fa-couch"></i> Products</a></li>
                    <li><a href="collections.php"><i class="fas fa-layer-group"></i> Collections</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                    <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </section>

            <section class="sitemap-section">
                <h2>Account</h2>
                <ul class="sitemap-list">
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                    <li><a href="account.php"><i class="fas fa-user-circle"></i> My Account</a></li>
                    <li><a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
                    <li><a href="order-history.php"><i class="fas fa-history"></i> Order History</a></li>
                </ul>
            </section>

            <section class="sitemap-section">
                <h2>Information</h2>
                <ul class="sitemap-list">
                    <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a></li>
                    <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
                    <li><a href="shipping.php"><i class="fas fa-truck"></i> Shipping Policy</a></li>
                    <li><a href="returns.php"><i class="fas fa-exchange-alt"></i> Return Policy</a></li>
                    <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                </ul>
            </section>
            <a href="index.php" class="back-to-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>