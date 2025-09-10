<?php
include '../config.php';

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

$prodID = $_GET['prodID'] ?? '';
$message = '';
$errorMsg = '';
$product = null;

// Fetch product details
if ($prodID) {
    $sql = "SELECT * FROM product WHERE prodID = ?";
    $stmt = $_db->prepare($sql);
    $stmt->execute([$prodID]);
    $product = $stmt->fetch();
    if (!$product) {
        $errorMsg = "Product not found.";
    }
} else {
    $errorMsg = "No product ID provided.";
}

// Get categories for dropdown
$cat_sql = "SELECT catID, name FROM category ORDER BY name";
$cat_stmt = $_db->prepare($cat_sql);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errorMsg) {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $qty = $_POST['qty'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = $_POST['color'] ?? '';
    $measurement = $_POST['measurement'] ?? '';
    $material = $_POST['material'] ?? '';
    $catID = $_POST['catID'] ?? '';
    
    // Keep existing images by default
    $image1 = $product['image1'];
    $image2 = $product['image2'];
    $image3 = $product['image3'];

    if (empty($name) || $price === '' || $qty === '' || empty($description) || empty($color) || empty($measurement) || empty($material) || empty($catID)) {
        $errorMsg = "All fields are required.";
    }

    // Check if user selected "Add New Category"
    if ($catID === 'new' && !empty($_POST['newCategory'])) {
        $newCatName = trim($_POST['newCategory']);

        // Generate next catID
        $cat_sql = "SELECT MAX(CAST(SUBSTRING(catID, 2) AS UNSIGNED)) FROM category";
        $cat_stmt = $_db->prepare($cat_sql);
        $cat_stmt->execute();
        $maxCatID = $cat_stmt->fetchColumn();
        $nextCatID = 'C' . str_pad(($maxCatID ? $maxCatID + 1 : 1), 4, '0', STR_PAD_LEFT);

        // Insert new category
        $insert_cat_sql = "INSERT INTO category (catID, name) VALUES (?, ?)";
        $insert_cat_stmt = $_db->prepare($insert_cat_sql);
        $insert_cat_stmt->execute([$nextCatID, $newCatName]);

        // Use the new catID for the product update
        $catID = $nextCatID;
    }

    // Handle individual image uploads (only if no error)
    if ($errorMsg === '') {
        $targetDir = '../bin/';
        
        // Handle image1
        if (isset($_FILES['image1']) && $_FILES['image1']['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['image1']['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Only allow JPG/JPEG files
            if ($fileExtension !== 'jpg' && $fileExtension !== 'jpeg') {
                $errorMsg = "Only JPG/JPEG image files are allowed for Image 1.";
            } else {
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES['image1']['tmp_name'], $targetFile)) {
                    $image1 = $fileName;
                }
            }
        }
        
        // Handle image2
        if (isset($_FILES['image2']) && $_FILES['image2']['error'] === UPLOAD_ERR_OK && $errorMsg === '') {
            $fileName = basename($_FILES['image2']['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Only allow JPG/JPEG files
            if ($fileExtension !== 'jpg' && $fileExtension !== 'jpeg') {
                $errorMsg = "Only JPG/JPEG image files are allowed for Image 2.";
            } else {
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES['image2']['tmp_name'], $targetFile)) {
                    $image2 = $fileName;
                }
            }
        }
        
        // Handle image3
        if (isset($_FILES['image3']) && $_FILES['image3']['error'] === UPLOAD_ERR_OK && $errorMsg === '') {
            $fileName = basename($_FILES['image3']['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Only allow JPG/JPEG files
            if ($fileExtension !== 'jpg' && $fileExtension !== 'jpeg') {
                $errorMsg = "Only JPG/JPEG image files are allowed for Image 3.";
            } else {
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES['image3']['tmp_name'], $targetFile)) {
                    $image3 = $fileName;
                }
            }
        }

        // Update product in database
        $sql = "UPDATE product SET name=?, price=?, qty=?, description=?, color=?, measurement=?, material=?, image1=?, image2=?, image3=?, catID=? WHERE prodID=?";
        $stmt = $_db->prepare($sql);
        if ($stmt->execute([$name, $price, $qty, $description, $color, $measurement, $material, $image1, $image2, $image3, $catID, $prodID])) {
            $message = "Product updated successfully!";
            echo '<script>setTimeout(function(){ window.location.href = "list.php"; }, 1000);</script>';
        } else {
            $errorMsg = "Failed to update product.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body main class="update-product-main" style="background: #fff;">

    <?php include '../admin/adminheader.php'; ?>
    <div class="container">
        <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../css/products.css' : 'css/products.css'; ?>">

        <!-- Page Header -->
        <div class="page-header">
            <h1>Update Products</h1>
            <p>Handcrafted with quality materials and timeless designs</p>
        </div>

        <!-- Update Product -->
        <?php if ($errorMsg): ?>
            <div class="message" style="color: red; background: #fff; border: 2px solid red; margin-bottom: 1rem; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message" id="form-message"> <?php echo htmlspecialchars($message); ?> </div>
            <script>
                setTimeout(function() {
                    var msg = document.getElementById('form-message');
                    if (msg) { msg.style.display = 'none'; }
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if ($product): ?>
            <form class="addproduct-form" method="POST" enctype="multipart/form-data">

                <label>Product Name:
                    <?php if (!empty($errorMsg)): ?>
                        <span style="color: red; font-weight: bold;">&#33;</span>
                    <?php endif; ?>
                </label>
                <input type="text" name="name" required style="padding: 0.5rem 0.7rem" value="<?php echo htmlspecialchars($product['name']); ?>">

                <div style="max-width: 200px;">
                    <label>Price:
                        <?php if (!empty($errorMsg)): ?>
                            <span style="color: red; font-weight: bold;">&#33;</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" step="0.01" name="price" min="0" required style="width: 100px; font-size: 0.95rem; padding: 0.5rem 0.7rem;" value="<?php echo htmlspecialchars($product['price']); ?>">
                    <label style="margin-top: 0.5rem;">Stock Quantity:
                        <?php if (!empty($errorMsg)): ?>
                            <span style="color: red; font-weight: bold;">&#33;</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" name="qty" min="0" required style="width: 100px; font-size: 0.95rem; padding: 0.5rem 0.7rem;" value="<?php echo htmlspecialchars($product['qty']); ?>">
                </div>

                <label>Description:
                    <?php if (!empty($errorMsg)): ?>
                        <span style="color: red; font-weight: bold;">&#33;</span>
                    <?php endif; ?>
                </label>
                <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>

                <label>Color:
                    <?php if (!empty($errorMsg)): ?>
                        <span style="color: red; font-weight: bold;">&#33;</span>
                    <?php endif; ?>
                </label>
                <input type="text" name="color" required style="width: 200px; padding: 0.5rem 0.7rem" value="<?php echo htmlspecialchars($product['color']); ?>">

                <label>Measurement:
                    <?php if (!empty($errorMsg)): ?>
                        <span style="color: red; font-weight: bold;">&#33;</span>
                    <?php endif; ?>
                </label>
                <textarea name="measurement"><?php echo htmlspecialchars($product['measurement']); ?></textarea>

                <label>Material:
                    <?php if (!empty($errorMsg)): ?>
                        <span style="color: red; font-weight: bold;">&#33;</span>
                    <?php endif; ?>
                </label>
                <input type="text" name="material" required style="width: 500px; padding: 0.5rem 0.7rem" value="<?php echo htmlspecialchars($product['material']); ?>">

                <label>Category:</label>
                <select name="catID" required style="width: 300px; padding: 0.5rem 0.7rem" id="catID">
                    <option value="">Select Category</option>
                    <option value="new">+ Add New Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['catID']; ?>" <?php echo ($product['catID'] == $cat['catID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="new-category-div" style="display: none; margin-top: 10px;">
                    <label for="newCategory">New Category Name:</label>
                    <input type="text" name="newCategory" id="newCategory" style="width: 300px; padding: 0.5rem 0.7rem">
                </div>

                <script>
                    const catSelect = document.getElementById('catID');
                    const newCatDiv = document.getElementById('new-category-div');
                    const newCatInput = document.getElementById('newCategory');
                    const addProductForm = document.querySelector('.addproduct-form');

                    catSelect.addEventListener('change', function() {
                        if (this.value === 'new') {
                            newCatDiv.style.display = 'block';
                            newCatInput.setAttribute('required', 'required');
                        } else {
                            newCatDiv.style.display = 'none';
                            newCatInput.removeAttribute('required');
                        }
                    });

                    addProductForm.addEventListener('submit', function(e) {
                        if (catSelect.value === 'new' && newCatInput.value.trim() === '') {
                            alert('Please enter a new category name.');
                            newCatInput.focus();
                            e.preventDefault();
                        }
                    });
                </script>

                <label>Product Images (JPG/JPEG only):</label>
                <div class="product-images-container">
                    <!-- Image 1 -->
                    <div class="image-upload-box">
                        <input type="file" id="image1-input" name="image1" accept=".jpg,.jpeg" onchange="previewImage(this, 'preview1')">
                        <div id="preview1" class="image-preview">
                            <?php if (!empty($product['image1'])): ?>
                                <?php $imageSrc = getImageSrc($product['image1']); ?>
                                <?php if (!empty($imageSrc)): ?>
                                    <img src="<?php echo $imageSrc; ?>" alt="Product Image 1" onclick="document.getElementById('image1-input').click();">
                                <?php else: ?>
                                    <div onclick="document.getElementById('image1-input').click();" class="image-upload-placeholder">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Image 1 not found</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div onclick="document.getElementById('image1-input').click();" class="image-upload-placeholder">
                                    <i class="fas fa-plus"></i>
                                    <span>Add Image 1</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Image 2 -->
                    <div class="image-upload-box">
                        <input type="file" id="image2-input" name="image2" accept=".jpg,.jpeg" onchange="previewImage(this, 'preview2')">
                        <div id="preview2" class="image-preview">
                            <?php if (!empty($product['image2'])): ?>
                                <?php $imageSrc = getImageSrc($product['image2']); ?>
                                <?php if (!empty($imageSrc)): ?>
                                    <img src="<?php echo $imageSrc; ?>" alt="Product Image 2" onclick="document.getElementById('image2-input').click();">
                                <?php else: ?>
                                    <div onclick="document.getElementById('image2-input').click();" class="image-upload-placeholder">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Image 2 not found</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div onclick="document.getElementById('image2-input').click();" class="image-upload-placeholder">
                                    <i class="fas fa-plus"></i>
                                    <span>Add Image 2</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Image 3 -->
                    <div class="image-upload-box">
                        <input type="file" id="image3-input" name="image3" accept=".jpg,.jpeg" onchange="previewImage(this, 'preview3')">
                        <div id="preview3" class="image-preview">
                            <?php if (!empty($product['image3'])): ?>
                                <?php $imageSrc = getImageSrc($product['image3']); ?>
                                <?php if (!empty($imageSrc)): ?>
                                    <img src="<?php echo $imageSrc; ?>" alt="Product Image 3" onclick="document.getElementById('image3-input').click();">
                                <?php else: ?>
                                    <div onclick="document.getElementById('image3-input').click();" class="image-upload-placeholder">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Image 3 not found</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div onclick="document.getElementById('image3-input').click();" class="image-upload-placeholder">
                                    <i class="fas fa-plus"></i>
                                    <span>Add Image 3</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <script>
                    function previewImage(input, previewId) {
                        if (input.files && input.files[0]) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const previewBox = document.getElementById(previewId);
                                previewBox.innerHTML = '';
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = 'Preview';
                                img.onclick = function() { input.click(); };
                                previewBox.appendChild(img);
                            };
                            reader.readAsDataURL(input.files[0]);
                        }
                    }
                </script>

                <?php if (!empty($errorMsg)): ?>
                    <textarea readonly style="color: red; background: #fff; border: none; width: 100%;">Error: <?php echo htmlspecialchars($errorMsg); ?></textarea>
                <?php endif; ?>

                <div>
                    <button type="submit">Update Product</button>
                    <a href="removeproduct.php?prodID=<?php echo $product['prodID']; ?>" title="Delete Product" style="color: red; font-size: 1.5em; margin-left: 15px " onclick="return confirm('Are you sure you want to delete this product?');">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>

            </form>
        <?php endif; ?>
    </div>
</body>
</html>