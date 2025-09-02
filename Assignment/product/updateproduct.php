<?php
include '../config.php';

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

$catID = $_POST['catID'] ?? ($_GET['catID'] ?? '');
$message = '';

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
	$image1 = $product['image1'];
    $image2 = $product['image2'];
    $image3 = $product['image3'];

	if (empty($name) || $price === '' || $qty === '' || empty($description) || empty($color) || empty($measurement) || empty($material) || empty($catID)) {
        $errorMsg = "All fields are required.";
    }

    // Handle image uploads (replace only if new file uploaded)
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $targetDir = '../bin/';
        for ($i = 0; $i < min(3, count($_FILES['images']['name'])); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = basename($_FILES['images']['name'][$i]);
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetFile)) {
                    if ($i === 0) $image1 = $fileName;
                    if ($i === 1) $image2 = $fileName;
                    if ($i === 2) $image3 = $fileName;
                }
            }
        }
    }

	if ($errorMsg === '') {
        $sql = "UPDATE product SET name=?, price=?, qty=?, description=?, color=?, measurement=?, material=?, image1=?, image2=?, image3=?, catID=? WHERE prodID=?";
        $stmt = $_db->prepare($sql);
        if ($stmt->execute([$name, $price, $qty, $description, $color, $measurement, $material, $image1, $image2, $image3, $catID, $prodID])) {
            $message = "Product updated successfully!";
            echo '<script>setTimeout(function(){ window.location.href = "list.php"; }, 2000);</script>';
            exit;
        } else {
            $errorMsg = "Failed to update product.";
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

			// Use the new catID for the product insert
			$catID = $nextCatID;
		}
	} else {
		$message = '';
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

<header class="wooden-header">
    <div class="header-container">
        <!-- Logo and Company Name -->
        <div class="logo-section">
            <a href="../admin/adminpage.php">
                <img src="../images/logo.png" alt="AiKUN Furniture Logo" class="logo">
                <span class="company-name">AiKUN</span>
            </a>
        </div>

        <!-- Product Management Buttons -->
		<div class="product-management" style="display: flex; gap: 15px; align-items: center;">
			<a href="list.php" title="All Product" style="color: white; font-size: 1.5em;">
				<i class="fas fa-list"></i>
			</a>
            <a href="../admin/adminpage.php" title="Home" style="color: white; font-size: 1.5em;">
				<i class="fas fa-home"></i>
			</a>
		</div>
    </div>
</header>

<body main class="update-product-main" style="background: #fff;">

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
                    <select name="catID" required style="width: 300px; padding: 0.5rem 0.7rem" id="catID">>
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

                <label>Product Images (upload to replace):</label>
                <input type="file" name="images[]" accept="image/*" multiple>

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