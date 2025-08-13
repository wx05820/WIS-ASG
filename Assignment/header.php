<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
</head>
<body>
    <header class="wooden-header">
        <div class="header-container">
            <!-- Logo and Company Name -->
            <div class="logo-section">
                <a href="index.php">
                    <img src="images/logo.png" alt="AiKUN Furniture Logo" class="logo">
                    <span class="company-name">AiKUN</span>
                </a>
            </div>
            
            <!-- Search Bar and Filters -->
            <div class="search-section">
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="query" placeholder="Search for furniture..." class="search-input">
                    <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                </form>
                <div class="filter-options">
                    <select name="category" class="filter-select">
                        <option value="">Filter by Category</option>
                        <option value="bed">Bed Frame</option>
                        <option value="desk">Desktop</option>
                        <option value="chair">Chair</option>
                        <option value="table">Table</option>
                        <option value="sofa">Sofa</option>
                        <option value="cabinet">Cabinet</option>
                    </select>
                    <select name="room" class="filter-select">
                        <option value="">Filter by Room</option>
                        <option value="living">Living Room</option>
                        <option value="bedroom">Bedroom</option>
                        <option value="kitchen">Kitchen</option>
                        <option value="dining">Dining Area</option>
                        <option value="office">Home Office</option>
                        <option value="outdoor">Outdoor</option>
                    </select>
                </div>
            </div>
            
            <!-- User Icons Section -->
            <div class="user-section">
                <div class="user-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="user-dropdown">
                            <button class="user-icon"><i class="fas fa-user"></i></button>
                            <div class="dropdown-content">
                                <a href="account.php">My Account</a>
                                <a href="logout.php">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="user-icon"><i class="fas fa-user"></i></a>
                    <?php endif; ?>
                    
                    <a href="cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    
                    <div class="shipping-dropdown">
                        <button class="shipping-icon"><i class="fas fa-truck"></i></button>
                        <div class="dropdown-content">
                            <a href="tracking.php">Track Shipping</a>
                            <a href="history.php">Purchase History</a>
                        </div>
                    </div>
                    
                    <button class="ai-chat-icon" id="open-chat-header"><i class="fas fa-robot"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Main Navigation -->
        <nav class="main-navigation">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">All Products</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
            
            <!-- Mobile Menu Toggle -->
            <div class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
        
        <!-- Mobile Navigation -->
        <div class="mobile-navigation">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">All Products</a></li>
                <li>
                    <a href="javascript:void(0)" class="mobile-dropdown-toggle">Categories <i class="fas fa-chevron-down"></i></a>
                    <ul class="mobile-dropdown-content">
                        <li><a href="category.php?type=bed">Bed Frames</a></li>
                        <li><a href="category.php?type=desk">Desktops</a></li>
                        <li><a href="category.php?type=chair">Chairs</a></li>
                        <li><a href="category.php?type=table">Tables</a></li>
                    </ul>
                </li>
                <li>
                    <a href="javascript:void(0)" class="mobile-dropdown-toggle">Rooms <i class="fas fa-chevron-down"></i></a>
                    <ul class="mobile-dropdown-content">
                        <li><a href="room.php?type=living">Living Room</a></li>
                        <li><a href="room.php?type=bedroom">Bedroom</a></li>
                        <li><a href="room.php?type=dining">Dining Area</a></li>
                        <li><a href="room.php?type=kitchen">Kitchen</a></li>
                    </ul>
                </li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </div>
    </header>

    <!-- AI Chatbox (Simplified version that will be in header) -->
    <div id="header-chat-modal" class="chat-modal">
        <div class="chat-modal-content">
            <span class="close-chat">&times;</span>
            <div class="chat-header">
                <h3>AiKUN Furniture Assistant</h3>
            </div>
            <div class="chat-body">
                <div class="chat-message ai-message">
                    <p>Hello! How can I help you with your furniture needs today?</p>
                </div>
            </div>
            <div class="chat-input">
                <input type="text" placeholder="Ask me about furniture...">
                <button><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
