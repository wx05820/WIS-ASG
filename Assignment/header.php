<?php
require_once __DIR__ . '/_base.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize user data
$user_profile_photo = null;
$username = null;
$user_email = null;
$cart_count = 0;

// Define base path for images based on current directory
// Check if we're in any subdirectory (product/, order/, user/, etc.)
$current_path = $_SERVER['PHP_SELF'];
$is_in_subdirectory = (strpos($current_path, '/product/') !== false || 
                      strpos($current_path, '/order/') !== false || 
                      strpos($current_path, '/user/') !== false ||
                      strpos($current_path, '/userProduct/') !== false ||
                      preg_match('/\/[^\/]+\/[^\/]+\.php$/', $current_path)); // Any subdirectory pattern

$image_base_path = $is_in_subdirectory ? '../' : '';

if (isset($_SESSION['user_id'])) {
    try {
        // Fetch user profile photo, username, and email from database
        $stm = $_db->prepare('SELECT username, photo, email FROM user WHERE userID = ?');
        $stm->execute([$_SESSION['user_id']]);
        $user_data = $stm->fetch();
        
        if ($user_data) {
            $username = $user_data->username;
            $user_email = $user_data->email;
            // Use default avatar if no photo is set
            $user_profile_photo = !empty($user_data->photo) ? 
                                 $image_base_path . $user_data->photo : 
                                 $image_base_path . 'images/default-avatar.png';
        }
        
        // Get cart count for logged-in user
        $cart_stm = $_db->prepare('SELECT SUM(ci.qty) as count FROM cart_items  ci LEFT JOIN cart c ON ci.cartID = c.cartID WHERE c.userID = ?');
        $cart_stm->execute([$_SESSION['user_id']]);
        $cart_data = $cart_stm->fetch();
        $cart_count = $cart_data ? (int)$cart_data->count : 0;
        
    } catch (PDOException $e) {
        // Log error and continue with defaults
        error_log("Header database error: " . $e->getMessage());
        $cart_count = 0;
    }
} else {
    $cart_count = 0;
}

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AiKUN Furniture - Premium quality Malaysian furniture for your home and office. Browse our collection of sofas, desks, dining tables, chairs, and more.">
    <meta name="keywords" content="furniture, Malaysian furniture, home decor, office furniture, sofa, desk, dining table, chair, cabinet">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>AiKUN Furniture - Premium Malaysian Furniture Store</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $image_base_path; ?>images/favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $image_base_path; ?>css/index.css">
</head>

<body data-user-id="<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''; ?>">
    <header class="wooden-header">
        <div class="header-container">
            <!-- Logo and Company Name -->
            <div class="logo-section">
                <a href="/index.php" aria-label="AiKUN Furniture Homepage">
                    <img src="<?php echo $image_base_path; ?>images/logo.png" alt="AiKUN Furniture Logo" class="logo">
                    <span class="company-name">AiKUN</span>
                </a>
            </div>
            
            <!-- Search Bar and Filters -->
            <div class="search-section" style="margin-top: 15px;">
                <form action="/userProduct/productList.php" method="GET" class="search-filter-form" role="search" id="searchFilterForm">
                    <input type="hidden" name="order" id="search-order" value="<?php echo isset($_GET['order']) ? htmlspecialchars(strtoupper($_GET['order'])) : 'ASC'; ?>">
                    <div class="search-input-container">
                        <input type="search" 
                               name="query" 
                               placeholder="Search for furniture..." 
                               class="search-input"
                               value="<?php echo isset($_GET['query']) ? htmlspecialchars($_GET['query']) : ''; ?>"
                               aria-label="Search furniture">
                        <button type="submit" class="search-button" aria-label="Submit search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="filter-options">
                        <select name="category" class="filter-select" aria-label="Filter by category" onchange="(function(){document.getElementById('search-order').value = (new URLSearchParams(window.location.search).get('order') || document.getElementById('search-order').value); document.getElementById('searchFilterForm').submit()})()">
                            <option value="">All Categories</option>
                            <option value="Sofa" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Sofa') ? 'selected' : ''; ?>>Sofa</option>
                            <option value="Desk" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Desk') ? 'selected' : ''; ?>>Desk</option>
                            <option value="Dining Table" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Dining Table') ? 'selected' : ''; ?>>Dining Table</option>
                            <option value="Chair" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Chair') ? 'selected' : ''; ?>>Chair</option>
                            <option value="Cabinet" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Cabinet') ? 'selected' : ''; ?>>Cabinet</option>
                            <option value="Wardrobe" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Wardrobe') ? 'selected' : ''; ?>>Wardrobe</option>
                            <option value="TV Cabinet" <?php echo (isset($_GET['category']) && $_GET['category'] === 'TV Cabinet') ? 'selected' : ''; ?>>TV Cabinet</option>
                            <option value="Children's small furniture" <?php echo (isset($_GET['category']) && $_GET['category'] === 'Children\'s small furniture') ? 'selected' : ''; ?>>Children's Furniture</option>
                        </select>
                        <select name="room" class="filter-select" aria-label="Filter by room" onchange="(function(){document.getElementById('search-order').value = (new URLSearchParams(window.location.search).get('order') || document.getElementById('search-order').value); document.getElementById('searchFilterForm').submit()})()">
                            <option value="">All Rooms</option>
                            <option value="living-room" <?php echo (isset($_GET['room']) && $_GET['room'] === 'living-room') ? 'selected' : ''; ?>>Living Room</option>
                            <option value="bedroom" <?php echo (isset($_GET['room']) && $_GET['room'] === 'bedroom') ? 'selected' : ''; ?>>Bedroom</option>
                            <option value="kitchen" <?php echo (isset($_GET['room']) && $_GET['room'] === 'kitchen') ? 'selected' : ''; ?>>Kitchen</option>
                            <option value="dining" <?php echo (isset($_GET['room']) && $_GET['room'] === 'dining') ? 'selected' : ''; ?>>Dining Area</option>
                            <option value="office" <?php echo (isset($_GET['room']) && $_GET['room'] === 'office') ? 'selected' : ''; ?>>Home Office</option>
                            <option value="outdoor" <?php echo (isset($_GET['room']) && $_GET['room'] === 'outdoor') ? 'selected' : ''; ?>>Outdoor</option>
                        </select>

                        <select name="sort" class="filter-select sortby-select" aria-label="Sort products" onchange="updateSort(this.value)">
                            <option value="">Sort by...</option>
                            <option value="name" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'name') ? 'selected' : ''; ?>>Name</option>
                            <option value="price" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price') ? 'selected' : ''; ?>>Price</option>
                            <option value="qty" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'qty') ? 'selected' : ''; ?>>Stock</option>
                        </select>

                        <button type="button" onclick="toggleOrder()" class="order-btn sortby-order" title="Toggle sort order">
                            <i class="fas fa-sort-<?php echo (isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC') ? 'down' : 'up'; ?>"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- User Icons Section -->
            <div class="user-section">
                <div class="user-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Logged-in User Dropdown -->
                        <div class="user-dropdown">
                            <button class="user-profile-btn" aria-label="User menu" aria-expanded="false">
                                <img src="<?php echo htmlspecialchars($user_profile_photo); ?>" 
                                     alt="<?php echo htmlspecialchars($username); ?>'s profile photo" 
                                     class="profile-photo-small"
                                     onerror="this.onerror=null; this.src='<?php echo $image_base_path; ?>images/default-avatar.png'">
                                <span class="username-display"><?php echo htmlspecialchars($username); ?></span>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </button>
                            <div class="dropdown-content" role="menu">
                                <div class="dropdown-header">
                                    <img src="<?php echo htmlspecialchars($user_profile_photo); ?>" 
                                         alt="Profile Photo" 
                                         class="profile-photo-large"
                                         onerror="this.onerror=null; this.src='<?php echo $image_base_path; ?>images/default-avatar.png'">
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($username); ?></h4>
                                        <p class="user-email"><?php echo htmlspecialchars($user_email); ?></p>
                                    </div>
                                </div>
                                <hr class="dropdown-divider">
                                <a href="/user/profile.php" class="dropdown-item" role="menuitem">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </a>
                                <a href="/user/wishlist.php" class="dropdown-item" role="menuitem">
                                    <i class="fas fa-heart"></i> Wishlist
                                </a>
                                <a href="/user/addresses.php" class="dropdown-item" role="menuitem">
                                    <i class="fas fa-map-marker-alt"></i> Addresses
                                </a>
                                <hr class="dropdown-divider">
                                <a href="/user/logout.php" class="dropdown-item logout-item" role="menuitem">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Login/Register for non-logged-in users -->
                        <div class="auth-buttons">
                            <a href="/user/login.php" class="user-icon" aria-label="Login">
                                <i class="fas fa-user"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Cart Icon -->                        
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/order/cart_page.php" class="cart-icon" aria-label="Shopping cart (<?php echo $cart_count; ?> items)" id="mini-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cart-count"><?php echo $cart_count; ?></span>
                        </a>
                    <?php else: ?>
                        <button class="cart-icon" aria-label="Shopping cart - Login required" id="mini-cart" onclick="checkLogin()">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cart-count">0</span>
                        </button>
                    <?php endif; ?>
                    
                    <!-- Shipping Dropdown -->
                    <div class="shipping-dropdown">
                        <button class="shipping-icon" aria-label="Shipping options" aria-expanded="false">
                            <i class="fas fa-truck"></i>
                        </button>
                        <div class="dropdown-content" role="menu">
                            <a href="/tracking.php" role="menuitem">
                                <i class="fas fa-search"></i> Track Shipping
                            </a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="/orders.php" role="menuitem">
                                    <i class="fas fa-history"></i> Order History
                                </a>
                            <?php endif; ?>
                            <a href="/shipping-info.php" role="menuitem">
                                <i class="fas fa-info-circle"></i> Shipping Info
                            </a>
                        </div>
                    </div>
                    
                    <!-- AI Chat Button -->
                    <button class="ai-chat-icon" id="open-chat-header" aria-label="Open AI furniture assistant">
                        <i class="fas fa-robot"></i>
                        <span class="ai-chat-label">AI Help</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Navigation -->
        <nav class="main-navigation" role="navigation" aria-label="Main navigation">
            <ul>
                <li><a href="/index.php" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">Home</a></li>
                <li><a href="/userProduct/productList.php" class="<?php echo ($current_page === '/userProduct/productList.php' || $current_page === 'list.php') ? 'active' : ''; ?>">All Products</a></li>
                <li><a href="/about.php" class="<?php echo ($current_page === 'about.php') ? 'active' : ''; ?>">About Us</a></li>
                <li><a href="/contact.php" class="<?php echo ($current_page === 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
            </ul>
            
            <!-- Mobile Menu Toggle -->
            <div class="mobile-menu-toggle" aria-label="Toggle mobile menu">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
        
        <!-- Mobile Navigation -->
        <div class="mobile-navigation" id="mobile-nav">
            <ul>
                <li><a href="/index.php">Home</a></li>
                <li><a href="/product/productList.php">All Products</a></li>
                <li>
                    <a href="javascript:void(0)" class="mobile-dropdown-toggle">
                        Categories <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="mobile-dropdown-content">
                        <li><a href="category.php?type=sofa">Sofas</a></li>
                        <li><a href="category.php?type=desk">Desks</a></li>
                        <li><a href="category.php?type=chair">Chairs</a></li>
                        <li><a href="category.php?type=dining-table">Dining Tables</a></li>
                        <li><a href="category.php?type=cabinet">Cabinets</a></li>
                        <li><a href="category.php?type=tv-cabinet">TV Cabinets</a></li>
                    </ul>
                </li>
                <li>
                    <a href="javascript:void(0)" class="mobile-dropdown-toggle">
                        Rooms <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="mobile-dropdown-content">
                        <li><a href="room.php?type=living-room">Living Room</a></li>
                        <li><a href="room.php?type=bedroom">Bedroom</a></li>
                        <li><a href="room.php?type=dining">Dining Area</a></li>
                        <li><a href="room.php?type=kitchen">Kitchen</a></li>
                        <li><a href="room.php?type=office">Home Office</a></li>
                        <li><a href="room.php?type=outdoor">Outdoor</a></li>
                    </ul>
                </li>
                <li><a href="/about.php">About Us</a></li>
                <li><a href="/contact.php">Contact</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="mobile-auth">
                        <a href="/user/login.php">Login</a>
                    </li>
                    <li class="mobile-auth">
                        <a href="/user/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- AI Chatbox Modal -->
    <div id="header-chat-modal" class="chat-modal" role="dialog" aria-labelledby="chat-title" aria-hidden="true">
        <div class="chat-modal-content">
            <div class="chat-header">
                <h3 id="chat-title">
                    <i class="fas fa-robot"></i>
                    AiKUN Furniture Assistant
                </h3>
                <button class="close-chat" aria-label="Close chat">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="chat-body" id="chat-messages">
                <div class="chat-message ai-message">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <p>Hello! I'm your AiKUN furniture assistant. How can I help you find the perfect furniture for your home today?</p>
                        <div class="quick-actions">
                            <button class="quick-action" onclick="sendQuickMessage('Show me popular sofas')">Popular Sofas</button>
                            <button class="quick-action" onclick="sendQuickMessage('What\'s new this week?')">New Arrivals</button>
                            <button class="quick-action" onclick="sendQuickMessage('Help me choose a dining table')">Dining Tables</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chat-input">
                <form id="chat-form">
                    <input type="text" 
                           id="chat-input-field" 
                           placeholder="Ask me about furniture..." 
                           maxlength="500"
                           aria-label="Chat message input">
                    <button type="submit" aria-label="Send message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="/js/script.js"></script>