<?php
include '../config.php';

$message = '';

// Get product ID from query string
$productID = $_GET['id'] ?? null;

if ($productID) {
    // Delete product from database
    $sql = "DELETE FROM product WHERE prodID = ?";
    $stmt = $_db->prepare($sql);
    if ($stmt->execute([$productID])) {
        $message = 'Product removed successfully!';
    } else {
        $message = 'Failed to remove product.';
    }
} else {
    $message = 'No product specified.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/products.css">
    <title>Remove Product</title>
</head>
<body main class="delete-product-main" style="background: #fff;">
    
    <div class="container">
        <div class="page-header">
            <h1>Remove Product</h1>
        </div>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <a href="list.php" class="btn btn-primary">Back to Product List</a>
    </div>
    
</body>
</html>
