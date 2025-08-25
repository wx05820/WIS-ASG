<?php
include 'config.php';
include '_base.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize user data
$user_profile_photo = null;
$username = null;
$user_email = null;
$cart_count = 0;

if (isset($_SESSION['user_id'])) {
    try {
        // Fetch user profile photo, username, and email from database
        $stm = $_db->prepare('SELECT username, photo, email FROM user WHERE userID = ?');
        $stm->execute([$_SESSION['user_id']]);
        $user_data = $stm->fetch();
        
        if ($user_data) {
            $username = $user_data->username;
            $user_email = $user_data->email;
            $user_profile_photo = !empty($user_data->photo) ? $user_data->photo : (strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../profilePhoto/default.jpg' : 'profilePhoto/default.jpg');
        }
        
        // Get cart count for logged-in user
        $cart_stm = $_db->prepare('SELECT COUNT(*) as count FROM cart WHERE userID = ?');
        $cart_stm->execute([$_SESSION['user_id']]);
        $cart_data = $cart_stm->fetch();
        $cart_count = $cart_data ? $cart_data->count : 0;
        
    } catch (PDOException $e) {
        // Log error and continue with defaults
        error_log("Header database error: " . $e->getMessage());
    }
} else {
    // For non-logged-in users, check session cart
    $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
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
    <link rel="icon" type="image/x-icon" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../images/favicon.ico' : 'images/favicon.ico'; ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../css/style.css' : 'css/style.css'; ?>">
    <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../css/products.css' : 'css/products.css'; ?>">
</head>
<body>
    <header class="wooden-header">
        <div class="header-container">
            <!-- Logo and Company Name -->
            <div class="logo-section">
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../index.php' : 'index.php'; ?>" aria-label="AiKUN Furniture Homepage">
                    <img src="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../images/logo.png' : 'images/logo.png'; ?>" alt="AiKUN Furniture Logo" class="logo">
                    <span class="company-name">AiKUN</span>
                </a>
            </div>
            
            <!-- Search Bar and Filters -->
            <div class="search-section">
                <form action="search.php" method="GET" class="search-form" role="search">
                    <div class="search-input-container">
                        <input type="text" 
                               name="query" 
                               placeholder="Search for furniture..." 
                               class="search-input"
                               value="<?php echo isset($_GET['query']) ? htmlspecialchars($_GET['query']) : ''; ?>"
                               aria-label="Search furniture">
                        <button type="submit" class="search-button" aria-label="Submit search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                <div class="filter-options">
                    <select name="category" class="filter-select" aria-label="Filter by category" onchange="applyFilters()">
                        <option value="">All Categories</option>
                        <option value="sofa" <?php echo (isset($_GET['category']) && $_GET['category'] === 'sofa') ? 'selected' : ''; ?>>Sofa</option>
                        <option value="desk" <?php echo (isset($_GET['category']) && $_GET['category'] === 'desk') ? 'selected' : ''; ?>>Desk</option>
                        <option value="dining-table" <?php echo (isset($_GET['category']) && $_GET['category'] === 'dining-table') ? 'selected' : ''; ?>>Dining Table</option>
                        <option value="chair" <?php echo (isset($_GET['category']) && $_GET['category'] === 'chair') ? 'selected' : ''; ?>>Chair</option>
                        <option value="cabinet" <?php echo (isset($_GET['category']) && $_GET['category'] === 'cabinet') ? 'selected' : ''; ?>>Cabinet</option>
                        <option value="tv-cabinet" <?php echo (isset($_GET['category']) && $_GET['category'] === 'tv-cabinet') ? 'selected' : ''; ?>>TV Cabinet</option>
                        <option value="children-furniture" <?php echo (isset($_GET['category']) && $_GET['category'] === 'children-furniture') ? 'selected' : ''; ?>>Children's Furniture</option>
                    </select>
                    
                    <select name="room" class="filter-select" aria-label="Filter by room" onchange="applyFilters()">
                        <option value="">All Rooms</option>
                        <option value="living-room" <?php echo (isset($_GET['room']) && $_GET['room'] === 'living-room') ? 'selected' : ''; ?>>Living Room</option>
                        <option value="bedroom" <?php echo (isset($_GET['room']) && $_GET['room'] === 'bedroom') ? 'selected' : ''; ?>>Bedroom</option>
                        <option value="kitchen" <?php echo (isset($_GET['room']) && $_GET['room'] === 'kitchen') ? 'selected' : ''; ?>>Kitchen</option>
                        <option value="dining" <?php echo (isset($_GET['room']) && $_GET['room'] === 'dining') ? 'selected' : ''; ?>>Dining Area</option>
                        <option value="office" <?php echo (isset($_GET['room']) && $_GET['room'] === 'office') ? 'selected' : ''; ?>>Home Office</option>
                        <option value="outdoor" <?php echo (isset($_GET['room']) && $_GET['room'] === 'outdoor') ? 'selected' : ''; ?>>Outdoor</option>
                    </select>
                </div>
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
                                     onerror="this.src='<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../profilePhoto/default.jpg' : 'profilePhoto/default.jpg'; ?>'">
                                <span class="username-display"><?php echo htmlspecialchars($username); ?></span>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </button>
                            <div class="dropdown-content" role="menu">
                                <div class="dropdown-header">
                                    <img src="<?php echo htmlspecialchars($user_profile_photo); ?>" 
                                         alt="Profile Photo" 
                                         class="profile-photo-large"
                                         onerror="this.src='<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../profilePhoto/default.jpg' : 'profilePhoto/default.jpg'; ?>'">
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($username); ?></h4>
                                        <p class="user-email"><?php echo htmlspecialchars($user_email); ?></p>
                                    </div>
                                </div>
                                <hr class="dropdown-divider">
                                <a href="profile.php" class="dropdown-item" role="menuitem">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </a>
                                <hr class="dropdown-divider">
                                <a href="logout.php" class="dropdown-item logout-item" role="menuitem">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Login/Register for non-logged-in users -->
                        <div class="auth-buttons">
                            <a href="login.php" class="user-icon" aria-label="Login">
                                <i class="fas fa-user"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Cart Icon -->
                    <a href="cart.php" class="cart-icon" aria-label="Shopping cart (<?php echo $cart_count; ?> items)">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count" id="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    
                    <!-- Shipping Dropdown -->
                    <div class="shipping-dropdown">
                        <button class="shipping-icon" aria-label="Shipping options" aria-expanded="false">
                            <i class="fas fa-truck"></i>
                        </button>
                        <div class="dropdown-content" role="menu">
                            <a href="tracking.php" role="menuitem">
                                <i class="fas fa-search"></i> Track Shipping
                            </a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="orders.php" role="menuitem">
                                    <i class="fas fa-history"></i> Order History
                                </a>
                            <?php endif; ?>
                            <a href="shipping-info.php" role="menuitem">
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
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../index.php' : 'index.php'; ?>" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">Home</a></li>
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../product/list.php' : 'products.php'; ?>" class="<?php echo ($current_page === 'products.php') ? 'active' : ''; ?>">All Products</a></li>
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../about.php' : 'about.php'; ?>" class="<?php echo ($current_page === 'about.php') ? 'active' : ''; ?>">About Us</a></li>
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../contact.php' : 'contact.php'; ?>" class="<?php echo ($current_page === 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
            </ul>
            
            <!-- Mobile Menu Toggle -->
            <div class="mobile-menu-toggle" aria-label="Toggle mobile menu">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
        
        <!-- Mobile Navigation -->
        <div class="mobile-navigation" id="mobile-nav">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">All Products</a></li>
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
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="mobile-auth">
                        <a href="login.php">Login</a>
                    </li>
                    <li class="mobile-auth">
                        <a href="register.php">Register</a>
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

    <!-- JavaScript for Header Functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeHeader();
    });

    function initializeHeader() {
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mobileNav = document.querySelector('.mobile-navigation');
        
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', function() {
                mobileNav.classList.toggle('active');
                this.querySelector('i').classList.toggle('fa-bars');
                this.querySelector('i').classList.toggle('fa-times');
            });
        }

        // Dropdown toggles
        initializeDropdowns();
        
        // Chat modal
        initializeChatModal();
        
        // Search functionality
        initializeSearch();
    }

    function initializeDropdowns() {
        // User dropdown
        const userDropdown = document.querySelector('.user-dropdown');
        if (userDropdown) {
            const button = userDropdown.querySelector('.user-profile-btn');
            const content = userDropdown.querySelector('.dropdown-content');
            
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                content.classList.toggle('show');
                this.setAttribute('aria-expanded', content.classList.contains('show'));
            });
        }

        // Shipping dropdown
        const shippingDropdown = document.querySelector('.shipping-dropdown');
        if (shippingDropdown) {
            const button = shippingDropdown.querySelector('.shipping-icon');
            const content = shippingDropdown.querySelector('.dropdown-content');
            
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                content.classList.toggle('show');
                this.setAttribute('aria-expanded', content.classList.contains('show'));
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-content.show').forEach(dropdown => {
                dropdown.classList.remove('show');
                const button = dropdown.parentElement.querySelector('button');
                if (button) button.setAttribute('aria-expanded', 'false');
            });
        });
    }

    function initializeChatModal() {
        const modal = document.getElementById('header-chat-modal');
        const openBtn = document.getElementById('open-chat-header');
        const closeBtn = document.querySelector('.close-chat');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input-field');

        if (openBtn && modal) {
            openBtn.addEventListener('click', function() {
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                input.focus();
            });
        }

        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            });
        }

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const message = input.value.trim();
                if (message) {
                    sendChatMessage(message);
                    input.value = '';
                }
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function sendChatMessage(message) {
        const chatBody = document.getElementById('chat-messages');
        
        // Add user message
        const userMessage = document.createElement('div');
        userMessage.className = 'chat-message user-message';
        userMessage.innerHTML = `
            <div class="message-content">
                <p>${escapeHtml(message)}</p>
            </div>
        `;
        chatBody.appendChild(userMessage);
        
        // Scroll to bottom
        chatBody.scrollTop = chatBody.scrollHeight;
        
        // Here you would normally send the message to your AI backend
        // For now, we'll show a simple response
        setTimeout(() => {
            const aiResponse = document.createElement('div');
            aiResponse.className = 'chat-message ai-message';
            aiResponse.innerHTML = `
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>Thank you for your message! I'm currently being enhanced to provide better furniture recommendations. Please contact our customer service for immediate assistance.</p>
                </div>
            `;
            chatBody.appendChild(aiResponse);
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 1000);
    }

    function sendQuickMessage(message) {
        sendChatMessage(message);
    }

    function applyFilters() {
        const categorySelect = document.querySelector('select[name="category"]');
        const roomSelect = document.querySelector('select[name="room"]');
        const searchInput = document.querySelector('input[name="query"]');
        
        if (categorySelect && roomSelect) {
            const params = new URLSearchParams();
            
            if (searchInput && searchInput.value) params.append('query', searchInput.value);
            if (categorySelect.value) params.append('category', categorySelect.value);
            if (roomSelect.value) params.append('room', roomSelect.value);
            
            window.location.href = 'products.php' + (params.toString() ? '?' + params.toString() : '');
        }
    }

    function updateCartCount(count) {
        const cartCountElement = document.getElementById('cart-count');
        if (cartCountElement) {
            cartCountElement.textContent = count;
            cartCountElement.parentElement.setAttribute('aria-label', `Shopping cart (${count} items)`);
        }
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    </script>