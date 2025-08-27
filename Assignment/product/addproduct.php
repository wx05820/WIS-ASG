<?php
include '../config.php';

$catID = $_POST['catID'] ?? ($_GET['catID'] ?? '');
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = $_POST['name'] ?? '';
	$price = $_POST['price'] ?? '';
	$qty = $_POST['qty'] ?? '';
	$description = $_POST['description'] ?? '';
	$color = $_POST['color'] ?? '';
	$measurement = $_POST['measurement'] ?? '';
	$material = $_POST['material'] ?? '';
	$image1 = '';
	$image2 = '';
	$image3 = '';

	$errorMsg = '';
	if (empty($name) || $price === '' || $qty === '' || empty($description) || empty($color) || empty($measurement) || empty($material) || empty($catID)) {
		$errorMsg = "All fields are required.";
	}

	if ($errorMsg === '') {
		// Auto-generate prodID=
		// Check for existing product with same name and category
		$exist_sql = "SELECT prodID FROM product WHERE name = ? AND catID = ? ORDER BY prodID ASC LIMIT 1";
		$exist_stmt = $_db->prepare($exist_sql);
		$exist_stmt->execute([$name, $catID]);
		$existProd = $exist_stmt->fetch();
		if ($existProd && isset($existProd['prodID'])) {
			// Use existing base prodID
			$baseStr = substr($existProd['prodID'], 1, 4);
			// Find max color code for this base
			$color_sql = "SELECT MAX(CAST(RIGHT(prodID,2) AS UNSIGNED)) as maxColor FROM product WHERE SUBSTRING(prodID,2,4) = ? AND catID = ?";
			$color_stmt = $_db->prepare($color_sql);
			$color_stmt->execute([$baseStr, $catID]);
			$maxColor = $color_stmt->fetchColumn();
			$newColor = str_pad((int)$maxColor + 1, 2, '0', STR_PAD_LEFT);
			$prodID = 'P' . $baseStr . $newColor;
		} else {
			$base_sql = "
				SELECT prodID 
				FROM product 
				WHERE prodID LIKE 'P____01' 
				ORDER BY CAST(SUBSTRING(prodID, 2, 4) AS UNSIGNED) DESC 
				LIMIT 1
			";
			$base_stmt = $_db->prepare($base_sql);
			$base_stmt->execute();
			$lastProd = $base_stmt->fetch();
 
			if ($lastProd && isset($lastProd['prodID'])) {
				// Get numeric part, increment by 1
				$base = (int)substr($lastProd['prodID'], 1, 4) + 1;
				$baseStr = str_pad($base, 4, '0', STR_PAD_LEFT);
			} else {
				$baseStr = '0001'; // First product
			}

			// New ID based on largest number
			$prodID = 'P' . $baseStr . '01';
		}

		// Handle multiple image uploads
		$image1 = $image2 = $image3 = '';
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

		// Insert product with prodID
		$sql = "INSERT INTO product (prodID, name, price, qty, description, color, measurement, material, image1, image2, image3, catID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$stmt = $_db->prepare($sql);
		if ($stmt->execute([$prodID, $name, $price, $qty, $description, $color, $measurement, $material, $image1, $image2, $image3, $catID])) {
			$message = 'Product added successfully!';
			// Clear form values after success
			$name = $price = $qty = $description = $color = $measurement = $material = $catID = '';
		} else {
			$message = 'Failed to add product.';
		}
	} else {
		$message = '';
	}
}

// Get categories for dropdown
$cat_sql = "SELECT catID, name FROM category ORDER BY name";
$cat_stmt = $_db->prepare($cat_sql);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();
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

<body main class="add-product-main" style="background: #fff;">

	<div class="container">
        <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/product/') !== false ? '../css/products.css' : 'css/products.css'; ?>">

        <!-- Page Header -->
        <div class="page-header">
            <h1>Add Products</h1>
            <p>Handcrafted with quality materials and timeless designs</p>
        </div>

		<!-- Add Product -->
		<?php if (!empty($errorMsg)): ?>
			<div class="message" style="color: red; background: #fff; border: 2px solid red; margin-bottom: 1rem; text-align: center; font-weight: bold;">
				<?php echo htmlspecialchars($errorMsg); ?>
			</div>
		<?php endif; ?>
		<?php if ($message): ?>
			<div class="message" id="form-message"> <?php echo htmlspecialchars($message); ?> </div>
			<script>
				setTimeout(function() {
					var msg = document.getElementById('form-message');
					if (msg) { msg.style.display = 'none'; }
				}, 2000);
			</script>
		<?php endif; ?>
		<form class="addproduct-form" method="POST" enctype="multipart/form-data">


			<label>Product Name:
				<?php if (!empty($errorMsg) && empty($name)): ?>
					<span style="color: red; font-weight: bold;">&#33;</span>
				<?php endif; ?>
			</label>
			<input type="text" name="name" required style="padding: 0.5rem 0.7rem" value="<?php echo htmlspecialchars($name ?? ''); ?>">

			<div style="max-width: 200px;">
				<label>Price:
					<?php if (!empty($errorMsg) && $price === ''): ?>
						<span style="color: red; font-weight: bold;">&#33;</span>
					<?php endif; ?>
				</label>
				<input type="number" step="0.01" name="price" min="0" required style="width: 100px; font-size: 0.95rem; padding: 0.5rem 0.7rem;" value="<?php echo htmlspecialchars($price ?? ''); ?>">
				<label style="margin-top: 0.5rem;">Stock Quantity:
					<?php if (!empty($errorMsg) && $qty === ''): ?>
						<span style="color: red; font-weight: bold;">&#33;</span>
					<?php endif; ?>
				</label>
				<input type="number" name="qty" min="0" required style="width: 100px; font-size: 0.95rem; padding: 0.5rem 0.7rem;" value="<?php echo htmlspecialchars($qty ?? ''); ?>">
			</div>


			<label>Description:
				<?php if (!empty($errorMsg) && empty($description)): ?>
					<span style="color: red; font-weight: bold;">&#33;</span>
				<?php endif; ?>
			</label>
			<textarea name="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>


			<label>Color:
				<?php if (!empty($errorMsg) && empty($color)): ?>
					<span style="color: red; font-weight: bold;">&#33;</span>
				<?php endif; ?>
			</label>
			<input type="text" name="color" required style="width: 200px; padding: 0.5rem 0.7rem" value="<?php echo htmlspecialchars($color ?? ''); ?>">


			<label>Measurement:
				<?php if (!empty($errorMsg) && empty($measurement)): ?>
					<span style="color: red; font-weight: bold;">&#33;</span>
				<?php endif; ?>
			</label>
			<textarea name="measurement"><?php echo htmlspecialchars($measurement ?? ''); ?></textarea>


			<label>Material:
				<?php if (!empty($errorMsg) && empty($material)): ?>
					<span style="color: red; font-weight: bold;">&#33;</span>
				<?php endif; ?>
			</label>
			<input type="text" name="material" required style="width: 500px; padding: 0.5rem 0.7rem" value="<?php echo htmlspecialchars($material ?? ''); ?>">


			<label>Category:
				<?php if (!empty($errorMsg) && empty($catID)): ?>
					<span style="color: red; font-weight: bold;">&#33;</span>
				<?php endif; ?>
			</label>
			<select name="catID" required style="width: 300px; padding: 0.5rem 0.7rem">
				<option value="">Select Category</option>
				<?php foreach ($categories as $cat): ?>
					<option value="<?php echo $cat['catID']; ?>" <?php echo ($catID == $cat['catID']) ? 'selected' : ''; ?>> <?php echo htmlspecialchars($cat['name']); ?> </option>
				<?php endforeach; ?>
			</select>

			<label>Product Images:</label>
			<input type="file" name="images[]" style="width: 400px" accept="image/*" multiple>

			<?php if (!empty($errorMsg)): ?>
				<textarea readonly style="color: red; background: #fff; border: none; width: 100%;">Error: <?php echo htmlspecialchars($errorMsg); ?></textarea>
			<?php endif; ?>

			<div>
				<button type="submit">Add Product</button>
			</div>
		</form>
	</div>
</body>

</html>