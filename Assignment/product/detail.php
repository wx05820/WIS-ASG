<?php
include '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// get product id
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header('Location: list.php');
    exit;
}

try {
    // get product details
    $sql = "SELECT p.*, c.categoryName 
            FROM product p 
            LEFT JOIN category c ON p.catID = c.catID 
            WHERE p.prodID = ?";
    
    $stmt = $_db->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: list.php');
        exit;
    }
    
    $related_sql = "SELECT p.*, c.categoryName 
                    FROM product p 
                    LEFT JOIN category c ON p.catID = c.catID 
                    WHERE p.catID = ? AND p.prodID != ? 
                    ORDER BY RAND() 
                    LIMIT 4";
    
    $related_stmt = $_db->prepare($related_sql);
    $related_stmt->execute([$product['catID'], $product_id]);
    $related_products = $related_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: list.php');
    exit;
}

$page_title = $product['name'];
include '../header.php';
?>

<main class="product-detail-main">
    <div class="container">
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="../index.php">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="list.php">Products</a>
                </li>
                <?php if (!empty($product['categoryName'])): ?>
                    <li class="breadcrumb-item">
                        <a href="list.php?category=<?php echo urlencode($product['catID']); ?>">
                            <?php echo htmlspecialchars($product['categoryName']); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo htmlspecialchars($product['name']); ?>
                </li>
            </ol>
        </nav>

        <div class="product-detail-container">
            <!-- product images -->
            <div class="product-images-section">
                <div class="main-image-container">
                    <div class="main-image" id="main-image">
                        <?php if (!empty($product['image1'])): ?>
                            <img src="../<?php echo htmlspecialchars($product['image1']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 id="main-product-image">
                        <?php else: ?>
                            <div class="no-image-large">
                                <i class="fas fa-image"></i>
                                <span>No Image Available</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Image Navigation Arrows -->
                    <?php if (!empty($product['image2']) || !empty($product['image3'])): ?>
                        <button class="image-nav-btn prev-btn" onclick="changeImage(-1)" aria-label="Previous image">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="image-nav-btn next-btn" onclick="changeImage(1)" aria-label="Next image">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Thumbnail Images -->
                <?php if (!empty($product['image1']) || !empty($product['image2']) || !empty($product['image3'])): ?>
                    <div class="thumbnail-images">
                        <?php 
                        $images = array_filter([$product['image1'], $product['image2'], $product['image3']]);
                        foreach ($images as $index => $image): 
                        ?>
                            <div class="thumbnail <?php echo ($index === 0) ? 'active' : ''; ?>" 
                                 onclick="setMainImage(<?php echo $index; ?>)">
                                <img src="../<?php echo htmlspecialchars($image); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Information -->
            <div class="product-info-section">
                <div class="product-header">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <?php if (!empty($product['categoryName'])): ?>
                        <div class="product-category">
                            <span class="category-tag"><?php echo htmlspecialchars($product['categoryName']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo ($i <= 4) ? 'filled' : 'empty'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-text">4.0 (12 reviews)</span>
                    </div>
                </div>

                <div class="product-price-section">
                    <div class="price-container">
                        <span class="current-price">RM <?php echo number_format($product['price'], 2); ?></span>
                        <?php if ($product['qty'] <= 0): ?>
                            <span class="out-of-stock-badge">Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stock-info">
                        <i class="fas fa-boxes"></i>
                        <span><?php echo $product['qty']; ?> units available</span>
                    </div>
                </div>

                <!-- Product Actions -->
                <div class="product-actions">
                    <?php if ($product['qty'] > 0): ?>
                        <div class="quantity-selector">
                            <label for="quantity">Quantity:</label>
                            <div class="quantity-controls">
                                <button type="button" onclick="changeQuantity(-1)" class="qty-btn">-</button>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['qty']; ?>">
                                <button type="button" onclick="changeQuantity(1)" class="qty-btn">+</button>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="add-to-cart-btn primary" onclick="addToCart(<?php echo $product['prodID']; ?>)">
                                <i class="fas fa-shopping-cart"></i>
                                Add to Cart
                            </button>
                            
                            <button class="buy-now-btn" onclick="buyNow(<?php echo $product['prodID']; ?>)">
                                <i class="fas fa-bolt"></i>
                                Buy Now
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="out-of-stock-actions">
                            <button class="notify-stock-btn" onclick="notifyWhenInStock(<?php echo $product['prodID']; ?>)">
                                <i class="fas fa-bell"></i>
                                Notify When In Stock
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="secondary-actions">
                        <button class="wishlist-btn" onclick="toggleWishlist(<?php echo $product['prodID']; ?>)" title="Add to Wishlist">
                            <i class="fas fa-heart"></i>
                            <span>Add to Wishlist</span>
                        </button>
                        
                        <button class="share-btn" onclick="shareProduct()" title="Share Product">
                            <i class="fas fa-share-alt"></i>
                            <span>Share</span>
                        </button>
                    </div>
                </div>

                <!-- Product Description -->
                <div class="product-description">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Product Specifications -->
        <div class="product-specifications">
            <h2>Product Specifications</h2>
            <div class="specs-grid">
                <?php if (!empty($product['color'])): ?>
                    <div class="spec-item">
                        <span class="spec-label">Color:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['color']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['measurement'])): ?>
                    <div class="spec-item">
                        <span class="spec-label">Dimensions:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['measurement']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['material'])): ?>
                    <div class="spec-item">
                        <span class="spec-label">Material:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['material']); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="spec-item">
                    <span class="spec-label">Product ID:</span>
                    <span class="spec-value"><?php echo htmlspecialchars($product['prodID']); ?></span>
                </div>
                
                <div class="spec-item">
                    <span class="spec-label">Category:</span>
                    <span class="spec-value"><?php echo htmlspecialchars($product['categoryName'] ?? 'Uncategorized'); ?></span>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="related-products">
                <h2>Related Products</h2>
                <div class="related-products-grid">
                    <?php foreach ($related_products as $related): ?>
                        <div class="related-product-card">
                            <div class="related-product-image">
                                <a href="detail.php?id=<?php echo $related['prodID']; ?>">
                                    <?php if (!empty($related['image1'])): ?>
                                        <img src="../<?php echo htmlspecialchars($related['image1']); ?>" 
                                             alt="<?php echo htmlspecialchars($related['name']); ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="no-image-small">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            
                            <div class="related-product-info">
                                <h4 class="related-product-name">
                                    <a href="detail.php?id=<?php echo $related['prodID']; ?>">
                                        <?php echo htmlspecialchars($related['name']); ?>
                                    </a>
                                </h4>
                                
                                <div class="related-product-price">
                                    RM <?php echo number_format($related['price'], 2); ?>
                                </div>
                                
                                <div class="related-product-category">
                                    <?php echo htmlspecialchars($related['categoryName'] ?? 'Uncategorized'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Share Modal -->
<div id="share-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Share This Product</h3>
        <div class="share-options">
            <button class="share-option facebook" onclick="shareOnFacebook()">
                <i class="fab fa-facebook-f"></i>
                Facebook
            </button>
            <button class="share-option twitter" onclick="shareOnTwitter()">
                <i class="fab fa-twitter"></i>
                Twitter
            </button>
            <button class="share-option whatsapp" onclick="shareOnWhatsApp()">
                <i class="fab fa-whatsapp"></i>
                WhatsApp
            </button>
            <button class="share-option email" onclick="shareViaEmail()">
                <i class="fas fa-envelope"></i>
                Email
            </button>
            <button class="share-option copy-link" onclick="copyProductLink()">
                <i class="fas fa-link"></i>
                Copy Link
            </button>
        </div>
    </div>
</div>

<!-- JavaScript for Product Detail Functionality -->
<script>
let currentImageIndex = 0;
const productImages = <?php echo json_encode(array_filter([$product['image1'], $product['image2'], $product['image3']])); ?>;

document.addEventListener('DOMContentLoaded', function() {
    initializeProductDetail();
});

function initializeProductDetail() {
    // Initialize share modal
    const modal = document.getElementById('share-modal');
    const closeBtn = modal.querySelector('.close');
    
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}

function changeImage(direction) {
    if (productImages.length <= 1) return;
    
    currentImageIndex = (currentImageIndex + direction + productImages.length) % productImages.length;
    updateMainImage();
    updateThumbnails();
}

function setMainImage(index) {
    currentImageIndex = index;
    updateMainImage();
    updateThumbnails();
}

function updateMainImage() {
    const mainImage = document.getElementById('main-product-image');
    if (mainImage && productImages[currentImageIndex]) {
        mainImage.src = '../' + productImages[currentImageIndex];
        mainImage.alt = '<?php echo addslashes($product['name']); ?> - Image ' + (currentImageIndex + 1);
    }
}

function updateThumbnails() {
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach((thumb, index) => {
        thumb.classList.toggle('active', index === currentImageIndex);
    });
}

function changeQuantity(change) {
    const quantityInput = document.getElementById('quantity');
    const newValue = parseInt(quantityInput.value) + change;
    const maxQty = <?php echo $product['qty']; ?>;
    
    if (newValue >= 1 && newValue <= maxQty) {
        quantityInput.value = newValue;
    }
}

function addToCart(productId) {
    const quantity = parseInt(document.getElementById('quantity').value);
    
    fetch('../add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            if (typeof updateCartCount === 'function') {
                updateCartCount(data.cart_count);
            }
            
            showNotification('Product added to cart successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to add product to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        showNotification('Error adding product to cart', 'error');
    });
}

function buyNow(productId) {
    const quantity = parseInt(document.getElementById('quantity').value);
    
    // Add to cart first, then redirect to checkout
    fetch('../add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to checkout
            window.location.href = '../checkout.php';
        } else {
            showNotification(data.message || 'Failed to add product to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        showNotification('Error adding product to cart', 'error');
    });
}

function notifyWhenInStock(productId) {
    // Implementation for stock notification
    showNotification('You will be notified when this product is back in stock!', 'success');
}

function toggleWishlist(productId) {
    fetch('../toggle-wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const btn = event.target.closest('.wishlist-btn');
            if (data.in_wishlist) {
                btn.classList.add('active');
                btn.querySelector('span').textContent = 'Remove from Wishlist';
                showNotification('Added to wishlist!', 'success');
            } else {
                btn.classList.remove('active');
                btn.querySelector('span').textContent = 'Add to Wishlist';
                showNotification('Removed from wishlist!', 'success');
            }
        } else {
            showNotification(data.message || 'Failed to update wishlist', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating wishlist:', error);
        showNotification('Error updating wishlist', 'error');
    });
}

function shareProduct() {
    document.getElementById('share-modal').style.display = 'block';
}

function shareOnFacebook() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('<?php echo addslashes($product['name']); ?>');
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}`, '_blank');
}

function shareOnTwitter() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('Check out this amazing furniture: <?php echo addslashes($product['name']); ?>');
    window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank');
}

function shareOnWhatsApp() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('Check out this amazing furniture: <?php echo addslashes($product['name']); ?>');
    window.open(`https://wa.me/?text=${text}%20${url}`, '_blank');
}

function shareViaEmail() {
    const subject = encodeURIComponent('Check out this furniture: <?php echo addslashes($product['name']); ?>');
    const body = encodeURIComponent(`I found this amazing furniture that you might like:\n\n<?php echo addslashes($product['name']); ?>\n\nView it here: ${window.location.href}`);
    window.open(`mailto:?subject=${subject}&body=${body}`);
}

function copyProductLink() {
    navigator.clipboard.writeText(window.location.href).then(function() {
        showNotification('Product link copied to clipboard!', 'success');
    }).catch(function() {
        showNotification('Failed to copy link', 'error');
    });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php include '../footer.php'; ?>
