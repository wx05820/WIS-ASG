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

$sql = "SELECT p.*, c.name AS category_name 
        FROM product p 
        JOIN category c ON p.catID = c.catID 
        WHERE p.prodID = ?";
$stmt = $_db->prepare($sql);
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

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
<script src="../js/cart.js" defer></script>
<script src="../js/userProduct.js" defer></script>

<main class="product-detail">
    <?php if (!$product): ?>
        <div class="error-container">
            <p class="no-results">Product not found.</p>
        </div>
    <?php else: ?>
        <div class="product-page">
            <div class="gallery-section">
                <div class="main-img">
                    <img id="mainProductImg" 
                        src="<?= htmlspecialchars($product['images'][0]); ?>" 
                        alt="<?= htmlspecialchars($product['name']); ?>">
                </div>
                <div class="zoom-window">
                    <img id="zoomImg" src="<?= htmlspecialchars($product['images'][0]); ?>" alt="Zoomed view">
                </div>
                <?php if (count($product['images']) > 1): ?>
                <div class="thumbs-horizontal">
                    <?php foreach ($product['images'] as $idx => $img): ?>
                        <img class="thumb-img" src="<?= htmlspecialchars($img); ?>"
                            alt="Thumbnail <?= $idx+1 ?>"
                            onclick="document.getElementById('mainProductImg').src=this.src;
                                     document.getElementById('zoomImg').src=this.src;">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <h2><?= htmlspecialchars($product['name']); ?></h2>
                <p class="category">Category: <?= htmlspecialchars($product['category_name']); ?></p>
                <p class="price"><?= money($product['price']); ?></p>
                <p class="stock <?= ($product['qty'] > 0 ? 'in-stock' : 'out-stock'); ?>">
                    <?= $product['qty'] > 0 ? "In Stock: {$product['qty']}" : "❌ Out of Stock"; ?>
                </p>
                <?php if (!empty($product['description'])): ?>
                    <div class="description">
                        <h3>Description</h3>
                        <p class="desc"><?= nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>           <!-- convert new lines to <br> for HTML -->
                <?php endif; ?>

                <div class="actions">
                    <?php if ($product['qty'] > 0): ?>
                        <?php if ($user_id): ?>
                            <!-- Add to Cart Form -->
                            <form action="../order/cart_add.php" method="POST" class="action-form cart-form" onsubmit="return handleFormSubmit(this)">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="prodID" value="<?= $product['prodID']; ?>">
                                <input type="hidden" name="qty" value="1">
                                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI']; ?>">
                                <button type="submit" class="btn-add">Add to Cart</button>
                            </form>
                            
                            <!-- Buy Now Form -->
                            <form action="../order/checkout.php" method="POST" class="action-form" onsubmit="return handleFormSubmit(this)">
                                <input type="hidden" name="prodID" value="<?= $product['prodID']; ?>">
                                <input type="hidden" name="buy_now" value="1">
                                <button type="submit" class="btn-checkout">Buy Now</button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn-add" onclick="showLoginPrompt()">Add to Cart</button>
                            <button type="button" class="btn-checkout" onclick="showLoginPrompt()">Buy Now</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn-disabled" disabled>Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function handleFormSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn && !submitBtn.disabled) {
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        
        if (submitBtn.classList.contains('btn-add')) {
            submitBtn.textContent = 'Adding...';
        } else if (submitBtn.classList.contains('btn-checkout')) {
            submitBtn.textContent = 'Processing...';
        }
        
        // Re-enable after 3 seconds in case of error
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }, 3000);
    }
    return true;
}

function showLoginPrompt() {
    showError('Please log in to add items to your cart');
    setTimeout(() => {
        window.location.href = '../user/login.php';
    }, 2000);
}

function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
    // Remove any existing notifications
    const existing = document.querySelector('.c');
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
});

const mainImg = document.getElementById("mainProductImg");
const zoomWindow = document.querySelector(".zoom-window");
const zoomImg = document.getElementById("zoomImg");

mainImg.addEventListener("mouseenter", () => {
    zoomWindow.style.display = "block";
});
mainImg.addEventListener("mouseleave", () => {
    zoomWindow.style.display = "none";
});
mainImg.addEventListener("mousemove", function(e) {
    const rect = this.getBoundingClientRect();
    const x = e.clientX - rect.left; // mouse x inside image
    const y = e.clientY - rect.top;  // mouse y inside image

    const percentX = x / rect.width;
    const percentY = y / rect.height;

    const zoomWidth = zoomImg.offsetWidth - zoomWindow.offsetWidth;
    const zoomHeight = zoomImg.offsetHeight - zoomWindow.offsetHeight;

    const moveX = -percentX * zoomWidth;
    const moveY = -percentY * zoomHeight;

    zoomImg.style.transform = `translate(${moveX}px, ${moveY}px)`;
});
</script>

<?php include '../footer.php'; ?>