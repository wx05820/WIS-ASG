<?php
include '../config.php';
include '../_base.php';

// Helper function to handle image display (filename or binary data)
function getImageSrc($imageData) {
    if (empty($imageData)) {
        return '';
    }
    
    // Check if image data is a filename or binary data
    $isFilename = false;
    if (strlen($imageData) < 500) {
        // Check for common image file extensions
        $imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif', '.bmp'];
        foreach ($imageExtensions as $ext) {
            if (strpos($imageData, $ext) !== false) {
                $isFilename = true;
                break;
            }
        }
        
        // Additional check: if it looks like a filename and file exists
        if (!$isFilename && preg_match('/^[a-zA-Z0-9._\-\s]+\.[a-zA-Z]{2,4}$/', $imageData)) {
            if (file_exists('../bin/' . $imageData)) {
                $isFilename = true;
            }
        }
    }
    
    if ($isFilename) {
        // Image data contains a filename, load from file system
        $imagePath = '../bin/' . $imageData;
        if (file_exists($imagePath)) {
            $imageContent = file_get_contents($imagePath);
            // Determine MIME type based on file extension
            $extension = strtolower(pathinfo($imageData, PATHINFO_EXTENSION));
            $mimeType = 'image/jpeg'; // default
            switch ($extension) {
                case 'png': $mimeType = 'image/png'; break;
                case 'gif': $mimeType = 'image/gif'; break;
                case 'webp': $mimeType = 'image/webp'; break;
                case 'avif': $mimeType = 'image/avif'; break;
            }
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);
        } else {
            return '';
        }
    } else {
        // Image data contains binary data
        return 'data:image/jpeg;base64,' . base64_encode($imageData);
    }
}

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../admin/loginstaff.php');
}

// Get product ID from URL
$prodID = $_GET['id'] ?? '';

if (empty($prodID)) {
    redirect('list.php');
}

// Fetch product details
try {
    $sql = "SELECT p.*, c.name as categoryName 
            FROM product p 
            LEFT JOIN category c ON p.catID = c.catID 
            WHERE p.prodID = ? AND (p.status IS NULL OR p.status != 'removed')";
    $stmt = $_db->prepare($sql);
    $stmt->execute([$prodID]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        redirect('list.php');
    }
} catch (PDOException $e) {
    redirect('list.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Product Details</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../css/products.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="product-detail-main" style="margin-top:0; padding-top:0;">
    <?php include '../admin/adminheader.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            <p>Product Details</p>
        </div>

        <div class="product-detail-container">
            <div class="product-images">
                <?php if (!empty($product['image1'])): ?>
                    <?php $imageSrc = getImageSrc($product['image1']); ?>
                    <?php if (!empty($imageSrc)): ?>
                        <img src="<?php echo $imageSrc; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             style="max-width: 400px; border-radius: 8px;">
                    <?php else: ?>
                        <div class="no-image" style="width:400px; height:300px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; border-radius:8px; color:#aaa; flex-direction:column;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <span>Image not found</span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image" style="width:400px; height:300px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; border-radius:8px; color:#aaa;">
                        <i class="fas fa-image" style="font-size: 4rem;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <table class="product-details-table" style="width: 100%; margin-top: 2rem;">
                    <tr>
                        <td><strong>Product ID:</strong></td>
                        <td><?php echo htmlspecialchars($product['prodID']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Price:</strong></td>
                        <td>RM <?php echo number_format((float)$product['price'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Stock:</strong></td>
                        <td><?php echo (int)$product['qty']; ?> units</td>
                    </tr>
                    <tr>
                        <td><strong>Category:</strong></td>
                        <td><?php echo htmlspecialchars($product['categoryName'] ?? 'Uncategorized'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Color:</strong></td>
                        <td><?php echo htmlspecialchars($product['color']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Material:</strong></td>
                        <td><?php echo htmlspecialchars($product['material']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Measurement:</strong></td>
                        <td><?php echo nl2br(htmlspecialchars($product['measurement'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Description:</strong></td>
                        <td><?php echo nl2br(htmlspecialchars($product['description'])); ?></td>
                    </tr>
                </table>

                <div class="action-buttons" style="margin-top: 2rem;">
                    <a href="updateproduct.php?prodID=<?php echo urlencode($product['prodID']); ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Product
                    </a>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
</body>
</html>