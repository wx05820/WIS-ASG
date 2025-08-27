<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
<link rel="stylesheet" href="../css/index.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

    <header class="wooden-header">
        <div class="header-container">
            <!-- Logo and Company Name -->
            <div class="logo-section">
                <a href="../admin/adminpage.php">
                    <img src="../images/logo.png" alt="AiKUN Furniture Logo" class="logo">
                    <span class="company-name">AiKUN</span>
                </a>
            </div>
            
                <!-- Page Header -->
                <div class="page-header">
                    <h1 style="text-align: center; margin: 0 auto;">Admin Page</h1>
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
                </div>
            </div>
        </div>
        
        <!-- Main Navigation -->
        <nav class="main-navigation">
            <ul>
                <li><a href="../product/list.php">All Products</a></li>
                <li><a href="products.php">Users</a></li>
                <li><a href="about.php">Staff</a></li>
                <li><a href="adduser.php">AddUsers</a></li>
            
            <!-- Mobile Menu Toggle -->
            <div class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
        
        <!-- Mobile Navigation -->
        <div class="mobile-navigation">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="../product/list.php">All Products</a></li>
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


<body>













</body>
    <?php include '../footer.php'; ?>