<?php
include '../config.php';

$message = '';

if (isset($_GET['prodID'])) {
    $prodID = $_GET['prodID'];
    // Soft delete: set status to 'removed'
    $sql = "UPDATE product SET status = 'removed' WHERE prodID = ?";
    $stmt = $_db->prepare($sql);
    if ($stmt->execute([$prodID])) {
        $message = 'Product removed.';
        // Optionally redirect or show message
        // header('Location: list.php?msg=Product+removed');
        // exit;
    } else {
        $message = 'Failed to remove product.';
    }
} else {
    $message = 'Invalid product ID.';
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
