
<?php
require_once '../_base.php';
// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

?>













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

<body style="margin:0; padding:0; width:100vw; min-height:100vh; box-sizing:border-box;">

    <main class="main-content">
        <section class="hero">
            <div class="hero-content">
                <h1>Premium Malaysian Furniture</h1>
                <p>Handcrafted with quality materials and timeless designs</p>
            </div>            
        </section> 
    </main>

</body>
<?php include '../footer.php'; ?>