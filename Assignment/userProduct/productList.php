<?php
require_once '../_base.php';
include '../lib/SimplePager.php';
include '../header.php';

// Get search & filter inputs
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// SQL base

$sql = "SELECT p.*, c.name AS category_name,
        COALESCE(AVG(pr.rating), 0) as avg_rating,
        COUNT(DISTINCT pr.review_id) as review_count
    FROM product p 
    LEFT JOIN category c ON p.catID = c.catID 
    LEFT JOIN product_reviews pr ON p.prodID = pr.product_id AND pr.user_id != '0' AND pr.product_id != '0'
    WHERE p.status IS NULL";

$params = [];

if (!empty($query)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
    $params[] = "%$query%";
}
if (!empty($category)) {
    $sql .= " AND c.name = ?";
    $params[] = $category;
}
if ($min_price !== null && $min_price > 0) {
    $sql .= " AND p.price >= ?";
    $params[] = $min_price;
}
if ($max_price !== null && $max_price > 0) {
    $sql .= " AND p.price <= ?";
    $params[] = $max_price;
}

// Add GROUP BY clause after all WHERE conditions
$sql .= " GROUP BY p.prodID, c.name";

// Determine order direction (ASC/DESC) from separate parameter; default to ASC
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if ($order !== 'ASC' && $order !== 'DESC') $order = 'ASC';

// Validate sort column and build ORDER BY using the column plus order direction
$allowed_sorts = ['name' => 'p.name', 'price' => 'p.price', 'qty' => 'p.qty'];
if (!empty($sort) && isset($allowed_sorts[$sort])) {
    $sql .= " ORDER BY " . $allowed_sorts[$sort] . " $order";
} else {
    $sql .= " ORDER BY p.prodID ASC";
}

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// Active filters display removed as requested

// Get wishlist status for all products if user is logged in
$wishlistItems = [];
if ($user_id && isset($_db) && $_db !== null) {
    try {
        $wishlistStmt = $_db->prepare("SELECT prodID FROM wishlist WHERE userID = ?");
        $wishlistStmt->execute([$user_id]);
        $wishlistItems = array_column($wishlistStmt->fetchAll(PDO::FETCH_ASSOC), 'prodID');
        error_log("ProductList - User $user_id wishlist items: " . json_encode($wishlistItems));
    } catch (Exception $e) {
        error_log("Wishlist query error: " . $e->getMessage());
        $wishlistItems = [];
    }
} else {
    error_log("ProductList - No user ID or database connection");
}

// Get current page
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
$pager = new SimplePager($sql, $params, 12, $page);
$products = $pager->result;

// Convert BLOB image to Base64
foreach ($products as &$p) {
    $p['img'] = !empty($p['image1'])
        ? 'data:image/jpeg;base64,' . base64_encode($p['image1'])
        : '/images/placeholder.jpg';
}
unset($p);
?>

<link rel="stylesheet" href="../css/index.css">
<link rel="stylesheet" href="../css/userproduct.css">
<script src="../js/userProduct.js" defer></script>
<script>
// Initialize product list functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product list page loaded');
    console.log('Checking for cart.js...');
    
    // Check if cart.js functions are available
    if (typeof initializeFormHandlers === 'function') {
        console.log('✅ cart.js functions available');
    } else {
        console.log('❌ cart.js functions NOT available');
    }
    
    // Let cart.js handle all form submissions
    console.log('Delegating form handling to cart.js');
    
    // Cart functionality is working, no test button needed
});
</script>

<body class="product-list-main" data-user-id="<?= htmlspecialchars($user_id ?? ''); ?>">
    <main class="product-list">
        <?php if($pager->count > 0): ?>
            <?php foreach ($products as $p): ?>
                <div class="product-card" data-id="<?= $p['prodID']; ?>" onclick="window.location.href='product_detail.php?prodID=<?= urlencode($p['prodID']); ?>'" style="cursor: pointer;">
                    <div class="product-img">
                        <?php
                        // Prepare all images for this product
                        $productImages = [];
                        for ($i = 1; $i <= 3; $i++) {
                            $imgField = "image$i";
                            if (!empty($p[$imgField])) {
                                $productImages[] = 'data:image/jpeg;base64,' . base64_encode($p[$imgField]);
                            }
                        }
                        if (empty($productImages)) {
                            $productImages[] = '../images/placeholder.jpg';
                        }
                        ?>
                        <img src="<?= htmlspecialchars($productImages[0]); ?>" 
                            alt="<?= htmlspecialchars($p['name']); ?>" 
                            class="product-img" loading="lazy"
                            data-images='<?= json_encode($productImages); ?>'
                            onerror="this.src='../images/placeholder.jpg'">
                    </div>
                    
                    <div class="product-info">
                        <h3><?= htmlspecialchars($p['name']); ?>
                            <i class="fas fa-qrcode qr-icon" 
                                onclick="event.stopPropagation(); showProductQR('<?= $p['prodID']; ?>', '<?= addslashes($p['name']); ?>', '<?= money($p['price']); ?>')" 
                                style="cursor: pointer; margin-left: 10px; color: #8B4513; font-size: 16px;" 
                                title="Show QR Code">
                            </i>
                        </h3>
                        <p class="category">Category: <?= htmlspecialchars($p['category_name']); ?></p>
                        <p class="price"><?= money($p['price']); ?></p>
                        
                        <!-- Rating Display -->
                        <?php 
                        $avgRating = round($p['avg_rating'], 1);
                        $reviewCount = (int)$p['review_count'];
                        $fullStars = floor($avgRating);
                        ?>
                        <div class="product-rating <?= $p['review_count'] == 0 ? 'no-rating' : ''; ?>" 
                             onclick="event.stopPropagation(); showRatingPopup('<?= $p['prodID']; ?>', <?= $avgRating; ?>, <?= $reviewCount; ?>)">
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
                        
                        <p class="stock <?= ($p['qty'] > 0 ? 'in-stock' : 'out-stock'); ?>">
                            <?= $p['qty'] > 0 ? "In Stock: {$p['qty']}" : "Out of Stock"; ?>
                        </p>
                        <div class="actions" onclick="event.stopPropagation();">
                            <?php if ($p['qty'] > 0): ?>
                                <?php if ($user_id): ?>
                                        <!-- Add to Cart Form -->
                                        <form action="../order/cart_add.php" method="POST" class="cart-form" id="btn-add-<?= $p['prodID']; ?>" data-action="../order/cart_add.php" data-prod-id="<?= $p['prodID']; ?>" onsubmit="return setQtyForAddToCart(this)">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <div class="qty-selector" style="display:flex;align-items:center;gap:8px;margin:8px 0;">
                                                <label for="list-qty-<?= $p['prodID']; ?>" style="min-width:60px;">Qty</label>
                                                <div class="qty-control" style="display:flex;align-items:center;gap:6px;">
                                                    <button type="button" class="qty-btn" data-target="#list-qty-<?= $p['prodID']; ?>" aria-label="Decrease quantity" style="padding:6px 10px;">◀</button>
                                                    <input id="list-qty-<?= $p['prodID']; ?>" name="visible_qty" type="number" value="1" min="1" max="<?= (int)$p['qty']; ?>" style="width:80px;padding:6px;">
                                                    <button type="button" class="qty-btn" data-target="#list-qty-<?= $p['prodID']; ?>" data-op="plus" aria-label="Increase quantity" style="padding:6px 10px;">▶</button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="qty" value="1">
                                            <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI']; ?>">
                                            <button type="submit" class="btn-add" id="add-btn-<?= $p['prodID']; ?>">Add to Cart</button>
                                        </form>
                                        
                                        <!-- Buy Now Form -->
                                        <form action="../order/checkout.php" method="POST" class="checkout-form" id="buy-now-<?= $p['prodID']; ?>" onsubmit="return setQtyForBuyNow(this)">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <input type="hidden" name="buy_now" value="1">
                                            <input type="hidden" name="qty" value="1">
                                            <button type="submit" class="btn-checkout" id="buy-btn-<?= $p['prodID']; ?>">Buy Now</button>
                                        </form>

                                        <!-- Add to Wishlist -->
                                        <form action="../user/wishlist.php" method="POST" class="wishlist-form" id="wishlist-<?= $p['prodID']; ?>" data-action="../user/wishlist.php" style="margin-top:6px;">
                                            <input type="hidden" name="action" value="<?= in_array($p['prodID'], $wishlistItems) ? 'remove' : 'add'; ?>">
                                            <input type="hidden" name="prodID" value="<?= $p['prodID']; ?>">
                                            <button type="submit" class="btn-secondary btn-wishlist <?= in_array($p['prodID'], $wishlistItems) ? 'in-wishlist' : ''; ?>" id="wishlist-btn-<?= $p['prodID']; ?>">
                                                <i class="fas fa-heart"></i> 
                                                <span class="wishlist-text"><?= in_array($p['prodID'], $wishlistItems) ? 'In Wishlist' : 'Wishlist'; ?></span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn-add" onclick="showLoginPrompt()">
                                            Add to Cart
                                        </button>
                                        <button type="button" class="btn-checkout" onclick="showLoginPrompt()">
                                            Buy Now
                                        </button>
                                        <button type="button" class="btn-secondary btn-wishlist" onclick="showLoginPrompt()" style="margin-top:6px;">
                                            <i class="fas fa-heart"></i> Wishlist
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-disabled" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <h3>No products found</h3>
                <p>Try adjusting your search criteria or browse all products.</p>
            </div>
        <?php endif; ?>
    </main>

<script>
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

function setQtyForBuyNow(form) {
    const qtyInput = form.querySelector('input[id*="qty"]:not([name="qty"])');
    const hiddenQty = form.querySelector('input[name="qty"]');
    if (qtyInput && hiddenQty) {
        const val = parseInt(qtyInput.value) || 1;
        hiddenQty.value = Math.max(1, val);
    }
    return true;
}
</script>

<script src="../js/userProduct.js"></script>

    <script>
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
            .price-range-container input:focus {
                outline: 2px solid #8B4513;
                outline-offset: 2px;
            }
            .price-range-container input:invalid {
                border-color: #e74c3c;
            }
            .price-range-container input:focus {
                outline: 2px solid #8B4513;
                outline-offset: 2px;
            .price-range-container input:invalid {
                border-color: #e74c3c;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Price range validation and real-time auto-filter
    document.addEventListener('DOMContentLoaded', function() {
        const minPriceInput = document.querySelector('input[name="min_price"]');
        const maxPriceInput = document.querySelector('input[name="max_price"]');
        
        if (minPriceInput && maxPriceInput) {
            // Add validation
            function validatePriceRange() {
                const minPrice = parseFloat(minPriceInput.value);
                const maxPrice = parseFloat(maxPriceInput.value);
                
                if (minPrice && maxPrice && minPrice > maxPrice) {
                    minPriceInput.setCustomValidity('Minimum price cannot be greater than maximum price');
                    maxPriceInput.setCustomValidity('Maximum price cannot be less than minimum price');
                } else {
                    minPriceInput.setCustomValidity('');
                    maxPriceInput.setCustomValidity('');
                }
            }
            
            // Real-time auto-filter as user types
            function autoFilter() {
                const minPrice = minPriceInput.value.trim();
                const maxPrice = maxPriceInput.value.trim();
                
                // Clear existing timeout
                clearTimeout(window.priceRangeTimeout);
                
                // Only filter if at least one field has a value
                if (minPrice || maxPrice) {
                    // Short delay to prevent too many requests while typing
                    window.priceRangeTimeout = setTimeout(() => {
                        // Validate before submitting
                        const minVal = parseFloat(minPrice);
                        const maxVal = parseFloat(maxPrice);
                        
                        if ((!minPrice || minVal >= 0) && (!maxPrice || maxVal >= 0) && 
                            (!minPrice || !maxPrice || minVal <= maxVal)) {
                            document.querySelector('form').submit();
                        }
                    }, 500); // Reduced delay for faster response
                } else {
                    // If both fields are empty, clear the filter
                    if (minPriceInput.value === '' && maxPriceInput.value === '') {
                        document.querySelector('form').submit();
                    }
                }
            }
            
            // Add event listeners for real-time filtering
            minPriceInput.addEventListener('input', validatePriceRange);
            maxPriceInput.addEventListener('input', validatePriceRange);
            
            minPriceInput.addEventListener('input', autoFilter);
            maxPriceInput.addEventListener('input', autoFilter);
            
            // Also filter on blur for immediate response
            minPriceInput.addEventListener('blur', autoFilter);
            maxPriceInput.addEventListener('blur', autoFilter);
        }
    });
    
    // Clear price range function
    function clearPriceRange() {
        const minPriceInput = document.querySelector('input[name="min_price"]');
        const maxPriceInput = document.querySelector('input[name="max_price"]');
        
        if (minPriceInput) minPriceInput.value = '';
        if (maxPriceInput) maxPriceInput.value = '';
        
        // Clear validation messages
        if (minPriceInput) minPriceInput.setCustomValidity('');
        if (maxPriceInput) maxPriceInput.setCustomValidity('');
        
        // Submit form to clear the filter (will trigger autoFilter)
        document.querySelector('form').submit();
    }
    </script>

    <script>
    // Auto-switch functionality for product list
    let autoSwitchIntervals = new Map();
    
    function startProductAutoSwitch(imgElement) {
        const images = JSON.parse(imgElement.dataset.images || '[]');
        if (images.length <= 1) return;
        
        let currentIndex = 0;
        
        const intervalId = setInterval(() => {
            currentIndex = (currentIndex + 1) % images.length;
            imgElement.src = images[currentIndex];
        }, 5000); // 5 second interval
        
        autoSwitchIntervals.set(imgElement, intervalId);
    }
    
    function resetToFirstImage(imgElement) {
        const images = JSON.parse(imgElement.dataset.images || '[]');
        if (images.length > 0) {
            imgElement.src = images[0];
        }
    }
    
    function stopProductAutoSwitch(imgElement) {
        const intervalId = autoSwitchIntervals.get(imgElement);
        if (intervalId) {
            clearInterval(intervalId);
            autoSwitchIntervals.delete(imgElement);
        }
    }
    
    // Start auto-switch only when hovering over a product image
    document.addEventListener('mouseover', function(e) {
        if (e.target.classList.contains('product-img')) {
            const images = JSON.parse(e.target.dataset.images || '[]');
            if (images.length > 1) {
                startProductAutoSwitch(e.target);
            }
        }
    });
    
    // Stop auto-switch and reset to first image when leaving the product image
    document.addEventListener('mouseout', function(e) {
        if (e.target.classList.contains('product-img')) {
            stopProductAutoSwitch(e.target);
            resetToFirstImage(e.target);
        }
    });
<<<<<<< Updated upstream
=======
    
    // QR Code Functions
    function showProductQR(productId, productName, price) {
        // Create well-formatted text that's easy to copy and share
        const formattedText = `🪑 AIKUN FURNITURE 🪑
━━━━━━━━━━━━━━━━━━━━━━

📦 PRODUCT DETAILS:
${productName}
💰 Price: ${price}
🆔 Product ID: ${productId}

━━━━━━━━━━━━━━━━━━━━━━
📞 CONTACT TO ORDER:

📱 Call/SMS: +60 12-345-6789
💬 WhatsApp: wa.me/60123456789
✉️ Email: sales@aikun-furniture.com
🌐 Website: aikun-furniture.com

━━━━━━━━━━━━━━━━━━━━━━
🚚 FREE DELIVERY AVAILABLE
🔧 Professional Assembly Included
⭐ Premium Malaysian Furniture

━━━━━━━━━━━━━━━━━━━━━━
📱 Scanned: ${new Date().toLocaleDateString()}

TO ORDER:
1️⃣ Copy this text
2️⃣ Contact us via phone/WhatsApp
3️⃣ Quote Product ID: ${productId}

Thank you for choosing AiKUN Furniture!`;

        // Generate QR code with the formatted text
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${encodeURIComponent(formattedText)}&format=png&margin=15&ecc=M`;
        
        // Create modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        `;
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            max-width: 500px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
            border: 3px solid #8B4513;
        `;
        
        modalContent.innerHTML = `
            <div style="margin-bottom: 20px;">
                <h3 style="color: #8B4513; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 1.4rem;">
                    <i class="fas fa-qrcode"></i> Product Information QR Code
                </h3>
                <div style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%); padding: 12px; border-radius: 10px; border-left: 4px solid #4caf50; margin-bottom: 15px;">
                    <p style="color: #2e7d32; font-size: 0.95rem; margin: 0; font-weight: bold;">
                        <i class="fas fa-copy"></i> Scan to Copy Complete Product Info
                    </p>
                    <p style="color: #388e3c; font-size: 0.85rem; margin: 5px 0 0 0;">
                        Customer can easily copy all details and contact information
                    </p>
                </div>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 2px solid #8B4513;">
                <h4 style="color: #8B4513; margin-bottom: 8px; font-size: 1.1rem;">${productName}</h4>
                <p style="color: #666; font-size: 1.1rem; font-weight: bold; margin: 0;">${price}</p>
                <p style="color: #888; font-size: 0.85rem; margin: 5px 0 0 0;">Product ID: ${productId}</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="color: #8B4513; margin-bottom: 10px;">📱 QR Code</h4>
                <div style="padding: 15px; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-radius: 15px; border: 2px dashed #8B4513;">
                    <img src="${qrUrl}" 
                         alt="Product Information QR Code" 
                         style="max-width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 10px;"
                         onload="console.log('Copyable QR code loaded')"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjE1MCIgeT0iMTUwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjE0IiBmaWxsPSIjNjY2Ij5RUiBFcnJvcjwvdGV4dD48L3N2Zz4=';">
                </div>
            </div>
            
            <div style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 15px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #ff9800;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-mobile-alt" style="color: #e65100;"></i>
                    <strong style="color: #e65100; font-size: 0.95rem;">Customer Experience:</strong>
                </div>
                <div style="color: #bf360c; font-size: 0.85rem; text-align: left; line-height: 1.4;">
                    1️⃣ Customer scans QR code with phone camera<br>
                    2️⃣ Phone shows formatted product information<br>
                    3️⃣ Customer can copy all text at once<br>
                    4️⃣ Easy to paste into messages or notes<br>
                    5️⃣ All contact info included for ordering
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-bottom: 15px;">
                <button onclick="downloadQRCode('${qrUrl}', '${productName.replace(/'/g, "\\'")}_QR')" 
                        style="background: #2196f3; color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; font-weight: bold;">
                    <i class="fas fa-download"></i> Download QR
                </button>
                <button onclick="closeQRModal()" 
                        style="background: #8B4513; color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: bold; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            
            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 12px; border-radius: 8px; font-size: 0.85rem; color: #1976d2; border-left: 4px solid #2196f3;">
                <i class="fas fa-lightbulb"></i> 
                <strong>Usage Tips:</strong> Perfect for product catalogs, business cards, brochures, and social media. Customer gets complete information and can easily contact you with product details.
            </div>
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        // Close when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeQRModal();
            }
        });
        
        // Store reference
        window.currentQRModal = modal;
    }

    // Download QR code
    function downloadQRCode(qrUrl, filename) {
        const link = document.createElement('a');
        link.href = qrUrl;
        link.download = `${filename.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('QR Code downloaded successfully!', 'success');
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        const bgColor = type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3';
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 10002;
            font-size: 0.9rem;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i> ${message}`;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3500);
    }

    function closeQRModal() {
        if (window.currentQRModal) {
            window.currentQRModal.remove();
            window.currentQRModal = null;
        }
    }
>>>>>>> Stashed changes
    </script>
</body>

<?php
// Pager links (always show if more than 1 page)
$params_array = [
    'query'     => $query ?? null,
    'category'  => $category ?? null,
    'min_price' => $min_price ?? null,
    'max_price' => $max_price ?? null,
    'sort'      => $sort ?? null
];
$params_array = array_filter($params_array); // drop empty ones
$href = http_build_query($params_array);

echo $pager->html($href);
?>

<?php include '../footer.php'; ?>