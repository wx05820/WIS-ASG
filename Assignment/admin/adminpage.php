<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
<link rel="stylesheet" href="../css/index.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<?php include 'adminheader.php'; ?>
</head>

    <header class="wooden-header">
     
        <!-- Main Navigation -->
        <nav class="main-navigation">
            <ul>
                <li><a href="../product/list.php">All Products</a></li>
                <li><a href="usermanage/list.php">Users</a></li>
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
        </section>
    </main>



</body>
    <?php include '../footer.php'; ?>