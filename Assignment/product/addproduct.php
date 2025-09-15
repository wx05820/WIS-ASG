<?php
include '../config.php';
include '../_base.php';
// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../admin/loginstaff.php');
}

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
		// Auto-generate prodID
		$exist_sql = "SELECT prodID FROM product WHERE name = ? AND catID = ? ORDER BY prodID ASC LIMIT 1";
		$exist_stmt = $_db->prepare($exist_sql);
		$exist_stmt->execute([$name, $catID]);
		$existProd = $exist_stmt->fetch(PDO::FETCH_ASSOC);

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
			// Get last base number regardless of color code
			$base_sql = "SELECT MAX(CAST(SUBSTRING(prodID, 2, 4) AS UNSIGNED)) AS maxBase FROM product";
			$base_stmt = $_db->prepare($base_sql);
			$base_stmt->execute();
			$maxBase = $base_stmt->fetchColumn();

			$nextBase = $maxBase ? $maxBase + 1 : 1;
			$baseStr = str_pad($nextBase, 4, '0', STR_PAD_LEFT);

			// Always start new base with color code 01
			$prodID = 'P' . $baseStr . '01';
		}

		// Handle multiple image uploads
		$image1 = $image2 = $image3 = '';
		if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
			$targetDir = '../bin/';
			for ($i = 0; $i < min(3, count($_FILES['images']['name'])); $i++) {
				if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
					$fileName = basename($_FILES['images']['name'][$i]);
					$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
					
					// Only allow JPG/JPEG files
					if ($fileExtension !== 'jpg' && $fileExtension !== 'jpeg') {
						$errorMsg = "Only JPG/JPEG image files are allowed.";
						break;
					}
					
					$targetFile = $targetDir . $fileName;
					if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetFile)) {
						if ($i === 0) $image1 = $fileName;
						if ($i === 1) $image2 = $fileName;
						if ($i === 2) $image3 = $fileName;
					}
				}
			}
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

		// Insert product with prodID - only if no image validation errors
		if (empty($errorMsg)) {
			$sql = "INSERT INTO product (prodID, name, price, qty, description, color, measurement, material, image1, image2, image3, catID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt = $_db->prepare($sql);
			if ($stmt->execute([$prodID, $name, $price, $qty, $description, $color, $measurement, $material, $image1, $image2, $image3, $catID])) {
				$message = 'Product added successfully!';
				// Clear form values after success
				$name = $price = $qty = $description = $color = $measurement = $material = $catID = '';
			} else {
				$message = 'Failed to add product.';
			}
		}
	} else {
		$message = '';
	}
}

// Get categories for dropdown
$categories = [];
try {
    $cat_sql = "SELECT catID, name FROM category ORDER BY name";
    $cat_stmt = $_db->prepare($cat_sql);
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if categories are loaded
    if (empty($categories)) {
        error_log("No categories found in database");
    }
} catch (PDOException $e) {
    error_log("Error loading categories: " . $e->getMessage());
    $errorMsg = "Could not load categories from database.";
}

// Debug: Add temporary debug output (remove in production)
if (isset($_GET['debug'])) {
    echo "<pre>Debug Info:\n";
    echo "Categories count: " . count($categories) . "\n";
    echo "Categories data: " . print_r($categories, true) . "\n";
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* Drag and Drop Styling */
    .image-upload-container {
        margin: 10px 0;
    }
    
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        background: #fafafa;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        max-width: 500px;
        margin: 10px 0;
    }
    
    .drop-zone:hover {
        border-color: #8B4513;
        background: #f5f5f5;
    }
    
    .drop-zone.drag-over {
        border-color: #28a745;
        background: #f8fff8;
        transform: scale(1.02);
    }
    
    .drop-zone-content {
        pointer-events: none;
    }
    
    .file-preview {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .file-item {
        position: relative;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 150px;
    }
    
    .file-item img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 5px;
    }
    
    .file-item .file-name {
        font-size: 0.8em;
        color: #666;
        word-break: break-all;
        margin-bottom: 5px;
    }
    
    .file-item .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .file-item .remove-btn:hover {
        background: #c82333;
    }
    
    .file-error {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
        background-color: #fee;
        border: 1px solid #fcc;
        border-radius: 5px;
        color: #c33;
        margin: 5px 0;
        font-size: 14px;
    }

    .file-error i {
        color: #e74c3c;
    }

    @media (max-width: 768px) {
        .drop-zone {
            padding: 30px 15px;
        }
        
        .file-preview {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .file-item img {
            height: 80px;
        }
    }
</style>
</head>
<?php include '../admin/adminheader.php'; ?>
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
			<select name="catID" required style="width: 300px; padding: 0.5rem 0.7rem" id="catID">
				<option value="">Select Category</option>
				<option value="new">+ Add New Category</option>
				<?php if (!empty($categories)): ?>
					<?php foreach ($categories as $cat): ?>
						<option value="<?php echo htmlspecialchars($cat['catID']); ?>" 
								<?php echo ($catID == $cat['catID']) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($cat['name']); ?>
						</option>
					<?php endforeach; ?>
				<?php else: ?>
					<option value="" disabled>No categories available</option>
				<?php endif; ?>
			</select>
			<div id="new-category-div" style="display: none; margin-top: 10px;">
				<label for="newCategory">New Category Name:</label>
				<input type="text" name="newCategory" id="newCategory" style="width: 300px; padding: 0.5rem 0.7rem">
			</div>

			<label>Product Images (JPG/JPEG only):</label>
			<div class="image-upload-container">
				<div id="drop-zone" class="drop-zone">
					<div class="drop-zone-content">
						<i class="fas fa-cloud-upload-alt" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
						<p style="margin: 10px 0; color: #666; font-size: 1.1em;">
							<strong>Drag & Drop images here</strong>
						</p>
						<p style="margin: 5px 0; color: #999; font-size: 0.9em;">
							or click to browse files
						</p>
						<p style="margin: 10px 0; color: #999; font-size: 0.8em;">
							JPG/JPEG files only • Max 3 images
						</p>
					</div>
					<input type="file" name="images[]" id="file-input" accept=".jpg,.jpeg" multiple style="display: none;">
				</div>
				<div id="file-preview" class="file-preview"></div>
			</div>

			<?php if (!empty($errorMsg)): ?>
				<textarea readonly style="color: red; background: #fff; border: none; width: 100%;">Error: <?php echo htmlspecialchars($errorMsg); ?></textarea>
			<?php endif; ?>

			<div>
				<button type="submit">Add Product</button>
			</div>
		</form>
	</div>

	<script>
		// Drag and Drop Functionality
		document.addEventListener('DOMContentLoaded', function() {
			const dropZone = document.getElementById('drop-zone');
			const fileInput = document.getElementById('file-input');
			const filePreview = document.getElementById('file-preview');
			let selectedFiles = new DataTransfer();

			// Click to browse files
			dropZone.addEventListener('click', function() {
				fileInput.click();
			});

			// File input change handler
			fileInput.addEventListener('change', function(e) {
				handleFiles(e.target.files);
			});

			// Drag events
			dropZone.addEventListener('dragover', function(e) {
				e.preventDefault();
				dropZone.classList.add('drag-over');
			});

			dropZone.addEventListener('dragleave', function(e) {
				e.preventDefault();
				dropZone.classList.remove('drag-over');
			});

			dropZone.addEventListener('drop', function(e) {
				e.preventDefault();
				dropZone.classList.remove('drag-over');
				handleFiles(e.dataTransfer.files);
			});

			function handleFiles(files) {
				// Clear previous files and start fresh
				selectedFiles = new DataTransfer();
				filePreview.innerHTML = '';

				// Limit to 3 files
				const filesToProcess = Math.min(files.length, 3);
				let validFiles = 0;

				for (let i = 0; i < filesToProcess; i++) {
					const file = files[i];
					
					// Validate file type
					if (!file.type.match('image/jpeg') && !file.name.toLowerCase().endsWith('.jpg') && !file.name.toLowerCase().endsWith('.jpeg')) {
						showFileError(file.name, 'Only JPG/JPEG files are allowed');
						continue;
					}

					// Validate file size (optional - 5MB limit)
					if (file.size > 5 * 1024 * 1024) {
						showFileError(file.name, 'File size must be less than 5MB');
						continue;
					}

					selectedFiles.items.add(file);
					validFiles++;
					displayFilePreview(file);
				}

				// Update file input with selected files
				fileInput.files = selectedFiles.files;

				// Show message if too many files
				if (files.length > 3) {
					showFileError('', 'Only first 3 files will be processed');
				}
			}

			function displayFilePreview(file) {
				const fileItem = document.createElement('div');
				fileItem.className = 'file-item';

				const img = document.createElement('img');
				img.src = URL.createObjectURL(file);
				img.onload = function() {
					URL.revokeObjectURL(img.src); // Clean up memory
				};

				const fileName = document.createElement('div');
				fileName.className = 'file-name';
				fileName.textContent = file.name;

				const removeBtn = document.createElement('button');
				removeBtn.className = 'remove-btn';
				removeBtn.innerHTML = '×';
				removeBtn.type = 'button';
				removeBtn.addEventListener('click', function() {
					removeFile(file, fileItem);
				});

				fileItem.appendChild(img);
				fileItem.appendChild(fileName);
				fileItem.appendChild(removeBtn);
				filePreview.appendChild(fileItem);
			}

			function removeFile(fileToRemove, fileItemElement) {
				// Remove from DataTransfer
				const newFiles = new DataTransfer();
				for (let i = 0; i < selectedFiles.files.length; i++) {
					const file = selectedFiles.files[i];
					if (file !== fileToRemove) {
						newFiles.items.add(file);
					}
				}
				selectedFiles = newFiles;
				fileInput.files = selectedFiles.files;

				// Remove from preview
				fileItemElement.remove();
			}

			function showFileError(fileName, message) {
				const errorDiv = document.createElement('div');
				errorDiv.className = 'file-error';
				errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${fileName ? fileName + ': ' : ''}${message}`;
				filePreview.appendChild(errorDiv);
				
				// Remove error after 5 seconds
				setTimeout(() => {
					if (errorDiv.parentNode) {
						errorDiv.remove();
					}
				}, 5000);
			}
		});
	</script>

	<script src="../js/adminproductlist.js"></script>
</body>
<?php include '../footer.php'; ?>
</html>