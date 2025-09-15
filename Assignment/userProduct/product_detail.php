<?php
require_once '../_base.php';
include '../header.php';

$user_id = $_SESSION['user_id'] ?? null;

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
        LEFT JOIN product_reviews pr ON p.prodID = pr.product_id AND pr.user_id != '0' AND pr.product_id != '0'
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
                    <div class="zoom-image" id="zoomImage"></div>
                </div>
                
                <!-- Mobile Zoom Overlay -->
                <div class="zoom-overlay-mobile" id="mobileZoomOverlay">
                    <img id="mobileZoomImg" src="<?= htmlspecialchars($product['images'][0]); ?>" alt="Zoomed view">
                    <button class="zoom-close-btn" id="mobileZoomClose" onclick="closeMobileZoom()">×</button>
                </div>
                <?php if (count($product['images']) > 1): ?>
                <div class="thumbs-horizontal">
                    <!-- Left Arrow -->
                    <button class="nav-arrow nav-arrow-left" id="prevImageBtn" onclick="previousImage()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <!-- Thumbnail Images -->
                    <div class="thumbnail-list">
                    <?php foreach ($product['images'] as $idx => $img): ?>
                        <img class="thumb-img" src="<?= htmlspecialchars($img); ?>"
                            alt="Thumbnail <?= $idx+1 ?>"
                            onclick="changeMainImage('<?= htmlspecialchars($img); ?>')">
                    <?php endforeach; ?>
                    </div>
                    
                    <!-- Right Arrow -->
                    <button class="nav-arrow nav-arrow-right" id="nextImageBtn" onclick="nextImage()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
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

// Enhanced Touch-Friendly Zoom Functionality
const mainImg = document.getElementById("mainProductImg");
const mainImgContainer = document.getElementById("mainImageContainer");
const zoomWindow = document.getElementById("zoomWindow");
const zoomImage = document.getElementById("zoomImage");
const zoomLens = document.getElementById("zoomLens");
const mobileZoomOverlay = document.getElementById("mobileZoomOverlay");
const mobileZoomImg = document.getElementById("mobileZoomImg");

let isZoomActive = false;
let isMouseOver = false;
let touchStartDistance = 0;
let touchStartScale = 1;
let currentScale = 1;
let lastTouchTime = 0;
let isMobile = window.innerWidth <= 768;

// Image navigation variables
let currentImageIndex = 0;
let productImages = <?= json_encode($product['images']); ?>;


// Initialize zoom functionality
function initZoom() {
    console.log('Initializing zoom functionality...');
    
    // Set up the zoom image using background-image with 4x magnification
    if (zoomImage && mainImg) {
        zoomImage.style.backgroundImage = `url(${mainImg.src})`;
        zoomImage.style.backgroundSize = '400% 400%'; // 4x magnification
        zoomImage.style.backgroundPosition = '0% 0%';
        zoomImage.style.backgroundRepeat = 'no-repeat';
        
        console.log('Zoom image setup complete:', {
            backgroundImage: zoomImage.style.backgroundImage,
            backgroundSize: zoomImage.style.backgroundSize,
            backgroundPosition: zoomImage.style.backgroundPosition
        });
    } else {
        console.error('Zoom image or main image element not found!');
    }
}

// Image change function
function changeMainImage(newSrc) {
    mainImg.src = newSrc;
    mobileZoomImg.src = newSrc;
    
    // Update zoom image background
    if (zoomImage) {
        zoomImage.style.backgroundImage = `url(${newSrc})`;
    }
    
    // Reset zoom and magnifier when changing images
    resetZoom();
    resetMagnifier();
}

// Reset magnifier to initial state
function resetMagnifier() {
    if (zoomImage) {
        zoomImage.style.backgroundPosition = '0% 0%';
    }
    if (zoomLens) {
        zoomLens.style.left = '0px';
        zoomLens.style.top = '0px';
    }
}

// Navigation functions
function nextImage() {
    if (productImages.length <= 1) return;
    
    currentImageIndex = (currentImageIndex + 1) % productImages.length;
    const newSrc = productImages[currentImageIndex];
    changeMainImage(newSrc);
    
    console.log('Next image:', currentImageIndex, newSrc);
}

function previousImage() {
    if (productImages.length <= 1) return;
    
    currentImageIndex = (currentImageIndex - 1 + productImages.length) % productImages.length;
    const newSrc = productImages[currentImageIndex];
    changeMainImage(newSrc);
    
    console.log('Previous image:', currentImageIndex, newSrc);
}


// Reset zoom to initial state
function resetZoom() {
    currentScale = 1;
    mainImg.style.transform = 'scale(1)';
    mainImg.style.cursor = 'zoom-in';
    mainImg.classList.remove('zoomed');
    isZoomActive = false;
    hideZoomWindow();
    hideMobileZoom();
}

// Show zoom window (desktop)
function showZoomWindow() {
    if (!isMobile) {
        zoomWindow.style.display = "block";
        zoomLens.style.display = "block";
        
        // Initialize the zoom image
        initZoom();
        
        // Test: Set a specific background position to see if it works
        setTimeout(() => {
            if (zoomImage) {
                zoomImage.style.backgroundPosition = "50% 50%"; // Center the image
                console.log('Test: Set background position to center');
            }
        }, 200);
        
        // Test: Position lens at center of image
        setTimeout(() => {
            if (zoomLens) {
                const mainImgRect = mainImg.getBoundingClientRect();
                const centerX = mainImgRect.width / 2 - 50; // 50 is half of lens size
                const centerY = mainImgRect.height / 2 - 50;
                zoomLens.style.left = centerX + "px";
                zoomLens.style.top = centerY + "px";
                console.log('Test: Positioned lens at center');
            }
        }, 300);
        
        console.log('Zoom window shown');
    }
}

// Hide zoom window (desktop)
function hideZoomWindow() {
    zoomWindow.style.display = "none";
    zoomLens.style.display = "none";
}

// Show mobile zoom overlay
function showMobileZoom() {
    if (isMobile) {
        mobileZoomOverlay.style.display = "flex";
        document.body.style.overflow = "hidden"; // Prevent background scrolling
    }
}

// Hide mobile zoom overlay
function hideMobileZoom() {
    mobileZoomOverlay.style.display = "none";
    document.body.style.overflow = ""; // Restore scrolling
}

// Close mobile zoom (called by button)
function closeMobileZoom() {
    hideMobileZoom();
    resetZoom();
}

// Toggle zoom on tap/double-tap
function toggleZoom() {
    if (isZoomActive) {
        resetZoom();
    } else {
        currentScale = 2;
        mainImg.style.transform = 'scale(2)';
        mainImg.style.cursor = 'zoom-out';
        mainImg.classList.add('zoomed');
        isZoomActive = true;
        
        if (isMobile) {
            showMobileZoom();
        } else {
            showZoomWindow();
        }
    }
}

// Mouse events for desktop
mainImgContainer.addEventListener("mouseenter", () => {
    isMouseOver = true;
    if (!isZoomActive) {
        showZoomWindow();
    }
});

mainImgContainer.addEventListener("mouseleave", () => {
    isMouseOver = false;
    if (!isZoomActive) {
        hideZoomWindow();
    }
});

mainImgContainer.addEventListener("mousemove", function(e) {
    if (!isMouseOver || isZoomActive) return;
    
    const rect = this.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    // Position the lens correctly - center it on the cursor
    const lensSize = 100;
    const lensX = x - lensSize/2; // Center the lens on cursor
    const lensY = y - lensSize/2; // Center the lens on cursor
    
    // Keep lens within bounds
    const boundedLensX = Math.max(0, Math.min(lensX, rect.width - lensSize));
    const boundedLensY = Math.max(0, Math.min(lensY, rect.height - lensSize));
    
    zoomLens.style.left = boundedLensX + "px";
    zoomLens.style.top = boundedLensY + "px";

        // Calculate the center of the lens area
        const lensCenterX = boundedLensX + lensSize/2;
        const lensCenterY = boundedLensY + lensSize/2;
        
        // For 4x magnification, we need to calculate the background position correctly
        // The background image is 400% size (4x larger than the zoom window)
        // We want the lens center to appear at the center of the zoom window
        
        // Convert lens center to percentage of the main image
        const lensPercentX = lensCenterX / rect.width;
        const lensPercentY = lensCenterY / rect.height;
        
        // For 4x magnification, we need to calculate the background position correctly
        // The background image is 400% size (4x larger than the zoom window)
        // We want the lens center to appear at the center of the zoom window
        
        // Image uses object-fit: contain, so it may not fill the entire frame
        // We need to calculate the actual image area within the frame
        const img = mainImg;
        const imgRect = img.getBoundingClientRect();
        const containerRect = rect;
        
        // Calculate the actual image dimensions within the container
        const imgAspectRatio = img.naturalWidth / img.naturalHeight;
        const containerAspectRatio = containerRect.width / containerRect.height;
        
        let actualImgWidth, actualImgHeight, imgOffsetX, imgOffsetY;
        
        if (imgAspectRatio > containerAspectRatio) {
            // Image is wider - fits to width
            actualImgWidth = containerRect.width;
            actualImgHeight = containerRect.width / imgAspectRatio;
            imgOffsetX = 0;
            imgOffsetY = (containerRect.height - actualImgHeight) / 2;
        } else {
            // Image is taller - fits to height
            actualImgHeight = containerRect.height;
            actualImgWidth = containerRect.height * imgAspectRatio;
            imgOffsetX = (containerRect.width - actualImgWidth) / 2;
            imgOffsetY = 0;
        }
        
        // Check if cursor is within the actual image area
        const relativeX = x - imgOffsetX;
        const relativeY = y - imgOffsetY;
        
        if (relativeX >= 0 && relativeX <= actualImgWidth && relativeY >= 0 && relativeY <= actualImgHeight) {
            // Cursor is within image area - calculate position relative to image
            const imgPercentX = relativeX / actualImgWidth;
            const imgPercentY = relativeY / actualImgHeight;
            
            const bgX = imgPercentX * 100;
            const bgY = imgPercentY * 100;
            
            // Apply the background position
            if (zoomImage) {
                zoomImage.style.backgroundPosition = `${bgX}% ${bgY}%`;
            }
        }
    
        console.log('Complete Image Magnifier:', {
            cursor: { x, y },
            lens: { x: boundedLensX, y: boundedLensY, centerX: lensCenterX, centerY: lensCenterY },
            imageArea: { 
                width: actualImgWidth, 
                height: actualImgHeight, 
                offsetX: imgOffsetX, 
                offsetY: imgOffsetY 
            },
            relativePosition: { x: relativeX, y: relativeY },
            magnification: '4x',
            method: 'object-fit contain - complete image visible'
        });
});

// Touch events for mobile
let touchStartX = 0;
let touchStartY = 0;

mainImgContainer.addEventListener("touchstart", function(e) {
    if (e.touches.length === 1) {
        // Single touch - prepare for swipe navigation
        lastTouchTime = Date.now();
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }
});

// Touch move - only for magnifier, no pinch zoom

mainImgContainer.addEventListener("touchend", function(e) {
    if (e.touches.length === 0) {
        const currentTime = Date.now();
        const timeDiff = currentTime - lastTouchTime;
        
        if (timeDiff < 300) {
            // Check if it's a swipe
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;
            
            // If horizontal swipe is greater than vertical swipe
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                if (deltaX > 0) {
                    // Swipe right - previous image
                    previousImage();
                } else {
                    // Swipe left - next image
                    nextImage();
                }
            }
        }
    }
});

// Click events removed - only magnifier functionality

// Handle window resize
window.addEventListener("resize", function() {
    const wasMobile = isMobile;
    isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        hideZoomWindow();
        if (isZoomActive) {
            showMobileZoom();
        }
    } else {
        hideMobileZoom();
        if (isZoomActive) {
            showZoomWindow();
        }
    }
});

// Add smooth transitions
mainImg.style.transition = 'transform 0.3s ease-in-out';

// Initialize zoom image size
function initializeZoomImage() {
    if (!mainImg || !zoomImg) return;
    
    const mainImgRect = mainImg.getBoundingClientRect();
    const zoomImgWidth = 1200; // 2x magnification
    const zoomImgHeight = (1200 * mainImgRect.height) / mainImgRect.width;
    
    // Set the zoom image size
    zoomImg.style.width = zoomImgWidth + 'px';
    zoomImg.style.height = zoomImgHeight + 'px';
    
    // Ensure the image is visible
    zoomImg.style.display = 'block';
    zoomImg.style.opacity = '1';
    
    console.log('Zoom image initialized:', {
        width: zoomImgWidth,
        height: zoomImgHeight,
        src: zoomImg.src
    });
}

// Wait for images to load before initializing
function waitForImageLoad() {
    if (mainImg.complete && zoomImg.complete) {
        initializeZoomImage();
    } else {
        mainImg.addEventListener('load', initializeZoomImage);
        zoomImg.addEventListener('load', initializeZoomImage);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking elements...');
    console.log('Main image:', mainImg);
    console.log('Zoom image:', zoomImg);
    console.log('Zoom window:', zoomWindow);
    
    initZoom();
    
    // Also try after images load
    setTimeout(initZoom, 500);
    setTimeout(initZoom, 1000);
    
    
    // Test: Try to force show the image
    setTimeout(() => {
        if (zoomImg) {
            zoomImg.style.border = '5px solid yellow';
            zoomImg.style.background = 'blue';
            console.log('Added yellow border and blue background to zoom image');
        }
    }, 2000);
});

// Re-initialize when window resizes
window.addEventListener('resize', function() {
    setTimeout(initializeZoomImage, 100);
});

// Add visual feedback for zoom interactions
mainImgContainer.addEventListener("mouseenter", function() {
    if (!isZoomActive) {
        this.style.cursor = 'zoom-in';
    }
});

mainImgContainer.addEventListener("mouseleave", function() {
    if (!isZoomActive) {
        this.style.cursor = 'default';
    }
});

// Add keyboard support for accessibility
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape" && isZoomActive) {
        resetZoom();
    }
    
    // Arrow key navigation
    if (e.key === "ArrowLeft") {
        e.preventDefault();
        previousImage();
    } else if (e.key === "ArrowRight") {
        e.preventDefault();
        nextImage();
    }
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