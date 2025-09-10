<?php
require_once '../_base.php';
include '../header.php';

$user_id = $_SESSION['user_id'] ?? null;

// Check if user is banned (only if logged in)
if ($user_id) {
    checkUserStatus();
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

$id = isset($_GET['prodID']) ? trim($_GET['prodID']) : '';

if ($id === '') {
    echo '<main class="product-detail"><p class="no-results">Invalid product ID.</p></main>';
    include '../footer.php';
    exit;
}

$sql = "SELECT p.*, c.name AS category_name,
        COALESCE(AVG(pr.rating), 0) as avg_rating,
        COUNT(DISTINCT pr.review_id) as review_count
        FROM product p 
        JOIN category c ON p.catID = c.catID 
        LEFT JOIN product_reviews pr ON p.prodID = pr.product_id AND pr.user_id > 0 AND pr.product_id > 0
        WHERE p.prodID = ?
        GROUP BY p.prodID, c.name";
$stmt = $_db->prepare($sql);
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if product is in wishlist (after $product is defined)
$inWishlist = false;
if ($user_id && $product) {
    $wishlistStmt = $_db->prepare("SELECT COUNT(*) FROM wishlist WHERE userID = ? AND prodID = ?");
    $wishlistStmt->execute([$user_id, $product['prodID']]);
    $inWishlist = $wishlistStmt->fetchColumn() > 0;
}

if ($product) {
    // Prepare all images
    $product['images'] = [];
    for ($i = 1; $i <= 3; $i++) {
        $imgField = "image$i";
        if (!empty($product[$imgField])) {
            $product['images'][] = 'data:image/jpeg;base64,' . base64_encode($product[$imgField]);
        }
    }
    if (empty($product['images'])) {
        $product['images'][] = '../images/placeholder.jpg';
    }
}
?>

<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/product_details.css">
<script src="../js/userProduct.js" defer></script>

<main class="product-detail">
    <?php if (!$product): ?>
        <div class="error-container">
            <p class="no-results">Product not found.</p>
        </div>
    <?php else: ?>
        <div class="product-page">
            <!-- Product Image Section at Top -->
            <div class="gallery-section">
                <div class="main-img" id="mainImageContainer">
                    <img id="mainProductImg" 
                        src="<?= htmlspecialchars($product['images'][0]); ?>" 
                        alt="<?= htmlspecialchars($product['name']); ?>">
                    <div class="zoom-overlay" id="zoomOverlay">
                        <div class="zoom-lens" id="zoomLens"></div>
                    </div>
                </div>
                <div class="zoom-window" id="zoomWindow">
                    <img id="zoomImg" src="<?= htmlspecialchars($product['images'][0]); ?>" alt="Zoomed view">
                </div>
                <?php if (count($product['images']) > 1): ?>
                <div class="thumbs-horizontal">
                    <?php foreach ($product['images'] as $idx => $img): ?>
                        <img class="thumb-img" src="<?= htmlspecialchars($img); ?>"
                            alt="Thumbnail <?= $idx+1 ?>"
                            onclick="changeMainImage('<?= htmlspecialchars($img); ?>')">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Information Section -->
            <div class="product-info">
                <h2><?= htmlspecialchars($product['name']); ?></h2>
                <p class="category">Category: <?= htmlspecialchars($product['category_name']); ?></p>
                <p class="price"><?= money($product['price']); ?></p>
                
                <!-- Rating Display -->
                <div class="product-rating <?= $product['review_count'] == 0 ? 'no-rating' : ''; ?>" 
                     onclick="showRatingPopup('<?= $product['prodID']; ?>', <?= round($product['avg_rating'], 1); ?>, <?= (int)$product['review_count']; ?>)">
                    <?php 
                    $avgRating = round($product['avg_rating'], 1);
                    $reviewCount = (int)$product['review_count'];
                    $fullStars = floor($avgRating);
                    ?>
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $fullStars ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-text">
                        <?php if ($avgRating > 0): ?>
                            <?= $avgRating; ?><?= $reviewCount > 0 ? " ({$reviewCount})" : ''; ?>
                        <?php else: ?>
                            <span class="no-rating-text">No rating yet</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <p class="stock <?= ($product['qty'] > 0 ? 'in-stock' : 'out-stock'); ?>">
                    <?= $product['qty'] > 0 ? "In Stock: {$product['qty']}" : "❌ Out of Stock"; ?>
                </p>
                
                <?php if (!empty($product['description'])): ?>
                    <div class="description">
                        <h3>Description</h3>
                        <p class="desc"><?= nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Quantity Selection at Bottom -->
                <?php if ($product['qty'] > 0): ?>
                    <div class="qty-section">
                        <div class="qty-selector">
                            <label for="detail-qty-loggedin">Quantity</label>
                            <div class="qty-control">
                                <button type="button" class="qty-btn" data-target="#detail-qty-loggedin" aria-label="Decrease quantity">◀</button>
                                <input id="detail-qty-loggedin" name="visible_qty" type="number" value="1" min="1" max="<?= (int)$product['qty']; ?>">
                                <button type="button" class="qty-btn" data-target="#detail-qty-loggedin" data-op="plus" aria-label="Increase quantity">▶</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons Row -->
            <div class="action-buttons-row">
                <?php if ($product['qty'] > 0): ?>
                    <?php if ($user_id): ?>
                        <!-- Add to Cart Form -->
                        <form action="../order/cart_add.php" method="POST" class="action-form cart-form" id="detail-cart-form" onsubmit="return setQtyForAddToCart(this)">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="prodID" value="<?= $product['prodID']; ?>">
                            <input type="hidden" name="qty" value="1">
                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI']; ?>">
                            <button type="submit" class="btn-add" id="detail-add-btn">Add to Cart</button>
                        </form>
                        
                        <!-- Buy Now Form -->
                        <form action="../order/checkout.php" method="POST" class="action-form" id="detail-buy-now-form" onsubmit="return setQtyForBuyNow(this)">
                            <input type="hidden" name="prodID" value="<?= $product['prodID']; ?>">
                            <input type="hidden" name="buy_now" value="1">
                            <input type="hidden" name="qty" value="1">
                            <button type="submit" class="btn-checkout" id="detail-buy-btn">Buy Now</button>
                        </form>

                        <!-- Add to Wishlist -->
                        <form action="../user/wishlist.php" method="POST" class="action-form wishlist-form" id="detail-wishlist-form">
                            <input type="hidden" name="action" value="<?= $inWishlist ? 'remove' : 'add'; ?>">
                            <input type="hidden" name="prodID" value="<?= $product['prodID']; ?>">
                            <button type="submit" class="btn-wishlist <?= $inWishlist ? 'in-wishlist' : ''; ?>" id="detail-wishlist-btn">
                                <i class="fas fa-heart"></i> 
                                <span class="wishlist-text"><?= $inWishlist ? 'In Wishlist' : 'Add to Wishlist'; ?></span>
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn-add" onclick="showLoginPrompt()">Add to Cart</button>
                        <button type="button" class="btn-checkout" onclick="showLoginPrompt()">Buy Now</button>
                        <button type="button" class="btn-wishlist" onclick="showLoginPrompt()">
                            <i class="fas fa-heart"></i> 
                            <span class="wishlist-text">Add to Wishlist</span>
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn-disabled" disabled>Out of Stock</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function setQtyForBuyNow(form){
    const qtyInput = document.getElementById('detail-qty-loggedin') || document.getElementById('detail-qty-guest');
    const hiddenQty = form.querySelector('input[name="qty"]');
    if(qtyInput && hiddenQty){
        const val = parseInt(qtyInput.value) || 1;
        hiddenQty.value = Math.max(1, val);
        console.log('Buy Now - Updated hidden qty from visible input:', qtyInput.value, '-> hidden:', hiddenQty.value);
    } else {
        console.log('Buy Now - Missing qty input or hidden field');
    }
    return true;
}

function showLoginPrompt() {
    // Create a more prominent login prompt
    const loginModal = document.createElement('div');
    loginModal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
    `;
    
    modalContent.innerHTML = `
        <div style="font-size: 48px; color: #8B4513; margin-bottom: 20px;">
            <i class="fas fa-lock"></i>
        </div>
        <h3 style="color: #8B4513; margin-bottom: 15px;">Login Required</h3>
        <p style="color: #666; margin-bottom: 25px; line-height: 1.5;">
            Please log in to add items to your cart or make a purchase.
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button onclick="window.location.href='../user/login.php'" 
                    style="background: #8B4513; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button onclick="closeLoginModal()" 
                    style="background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer;">
                Cancel
            </button>
        </div>
    `;
    
    // Add CSS animations if not already present
    if (!document.querySelector('#login-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'login-modal-styles';
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideIn {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    loginModal.appendChild(modalContent);
    document.body.appendChild(loginModal);
    
    // Close modal when clicking outside
    loginModal.addEventListener('click', function(e) {
        if (e.target === loginModal) {
            closeLoginModal();
        }
    });
    
    // Store reference for closing
    window.currentLoginModal = loginModal;
}

function closeLoginModal() {
    if (window.currentLoginModal) {
        window.currentLoginModal.remove();
        window.currentLoginModal = null;
    }
}

function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
    // Remove any existing notifications
    const existing = document.querySelector('.detail-notification');
    if (existing) {
        existing.remove();
    }
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = 'detail-notification';
    const bgColor = type === 'error' ? '#ff4444' : '#4CAF50';
    notificationDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease-in-out;
    `;
    notificationDiv.textContent = message;
    
    document.body.appendChild(notificationDiv);
    
    // Animate in
    setTimeout(() => {
        notificationDiv.style.opacity = '1';
        notificationDiv.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (notificationDiv.parentNode) {
            notificationDiv.style.opacity = '0';
            notificationDiv.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notificationDiv.parentNode) {
                    notificationDiv.parentNode.removeChild(notificationDiv);
                }
            }, 300);
        }
    }, 4000);
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Add debugging for quantity input
    const qtyInput = document.getElementById('detail-qty-loggedin');
    if (qtyInput) {
        qtyInput.addEventListener('change', function() {
            console.log('Quantity input changed to:', this.value);
        });
        
        qtyInput.addEventListener('input', function() {
            console.log('Quantity input value:', this.value);
        });
    }
});

// Enhanced Zoom Functionality
const mainImg = document.getElementById("mainProductImg");
const mainImgContainer = document.getElementById("mainImageContainer");
const zoomWindow = document.getElementById("zoomWindow");
const zoomImg = document.getElementById("zoomImg");
const zoomLens = document.getElementById("zoomLens");

// Image change function
function changeMainImage(newSrc) {
    mainImg.src = newSrc;
    zoomImg.src = newSrc;
}

// Zoom functionality
mainImgContainer.addEventListener("mouseenter", () => {
    zoomWindow.style.display = "block";
    zoomLens.style.display = "block";
});

mainImgContainer.addEventListener("mouseleave", () => {
    zoomWindow.style.display = "none";
    zoomLens.style.display = "none";
});

mainImgContainer.addEventListener("mousemove", function(e) {
    const rect = this.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    // Position the lens
    const lensSize = 100;
    const lensX = Math.max(0, Math.min(x - lensSize/2, rect.width - lensSize));
    const lensY = Math.max(0, Math.min(y - lensSize/2, rect.height - lensSize));
    
    zoomLens.style.left = lensX + "px";
    zoomLens.style.top = lensY + "px";

    // Calculate zoom
    const percentX = x / rect.width;
    const percentY = y / rect.height;

    const zoomWidth = zoomImg.offsetWidth - zoomWindow.offsetWidth;
    const zoomHeight = zoomImg.offsetHeight - zoomWindow.offsetHeight;

    const moveX = -percentX * zoomWidth;
    const moveY = -percentY * zoomHeight;

    zoomImg.style.transform = `translate(${moveX}px, ${moveY}px)`;
});

// Rating popup functionality
function showRatingPopup(productId, avgRating, reviewCount) {
    if (reviewCount === 0) {
        alert('No reviews available for this product yet.');
        return;
    }
    
    // Show loading state
    const modal = document.createElement('div');
    modal.className = 'rating-modal-overlay';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    `;
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'rating-modal-content';
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        max-width: 700px;
        width: 95%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
        border: 3px solid #8B4513;
    `;
    
    const fullStars = Math.floor(avgRating);
    const hasHalfStar = avgRating % 1 >= 0.5;
    
    modalContent.innerHTML = `
        <div style="margin-bottom: 20px;">
            <h3 style="color: #8B4513; margin-bottom: 15px; font-size: 1.5rem;">Product Reviews</h3>
            <div style="display: flex; justify-content: center; align-items: center; gap: 5px; margin-bottom: 10px;">
                ${Array.from({length: 5}, (_, i) => 
                    `<span style="font-size: 2rem; color: ${i < fullStars ? '#FFD700' : (i === fullStars && hasHalfStar ? '#FFD700' : '#ddd')};">★</span>`
                ).join('')}
            </div>
            <p style="font-size: 1.2rem; color: #8B4513; font-weight: bold; margin: 0;">
                ${avgRating.toFixed(1)} out of 5 stars
            </p>
            <p style="color: #666; margin: 5px 0 0 0;">
                Based on ${reviewCount} review${reviewCount !== 1 ? 's' : ''}
            </p>
        </div>
        <div id="reviews-container" style="text-align: left; margin: 20px 0;">
            <div style="text-align: center; color: #666;">Loading reviews...</div>
        </div>
        <div style="margin-top: 20px;">
            <button onclick="closeRatingModal()" 
                    style="background: #8B4513; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 1rem;">
                Close
            </button>
        </div>
    `;
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Fetch detailed reviews
    fetchReviews(productId, modalContent);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeRatingModal();
        }
    });
}

// Fetch detailed reviews
function fetchReviews(productId, modalContent) {
    fetch(`../order/get_reviews.php?product_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            const reviewsContainer = modalContent.querySelector('#reviews-container');
            if (data.success && data.reviews.length > 0) {
                // Update the review count in the header to match actual reviews
                const reviewCountElement = modalContent.querySelector('p:last-of-type');
                if (reviewCountElement) {
                    reviewCountElement.innerHTML = `Based on ${data.reviews.length} review${data.reviews.length !== 1 ? 's' : ''}`;
                }
                
                reviewsContainer.innerHTML = data.reviews.map(review => `
                    <div style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; background: #fafafa;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <strong style="color: #8B4513; font-size: 1.1rem;">${review.username}</strong>
                                <div style="display: flex; gap: 2px;">
                                    ${Array.from({length: 5}, (_, i) => 
                                        `<span style="color: ${i < review.rating ? '#FFD700' : '#ddd'}; font-size: 1rem;">★</span>`
                                    ).join('')}
                                </div>
                            </div>
                            <span style="color: #666; font-size: 0.9rem;">${review.date}</span>
                        </div>
                        ${review.title ? `<h4 style="color: #5D4037; margin: 5px 0; font-size: 1rem;">${review.title}</h4>` : ''}
                        <p style="color: #333; line-height: 1.5; margin: 0;">${review.content}</p>
                    </div>
                `).join('');
            } else {
                reviewsContainer.innerHTML = '<div style="text-align: center; color: #666;">No detailed reviews available.</div>';
            }
        })
        .catch(error => {
            console.error('Error fetching reviews:', error);
            const reviewsContainer = modalContent.querySelector('#reviews-container');
            reviewsContainer.innerHTML = '<div style="text-align: center; color: #e74c3c;">Error loading reviews. Please try again.</div>';
        });
}

function closeRatingModal() {
    const modal = document.querySelector('.rating-modal-overlay');
    if (modal) {
        modal.remove();
    }
}

// Add CSS animations if not already present
if (!document.querySelector('#rating-modal-styles')) {
    const style = document.createElement('style');
    style.id = 'rating-modal-styles';
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php include '../footer.php'; ?>