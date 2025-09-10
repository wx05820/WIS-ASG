<?php
session_start();
require_once '../_base.php';

// Generate CSRF token using the proper function
$csrfToken = generateCSRFToken();

$userID = $_SESSION['user_id'] ?? null;
checkLogin();

$orderID = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($orderID)) {
    $_SESSION['error'] = "Invalid order ID";
    header('Location: history.php');
    exit();
}

// Get order details with payment info
$orderQuery = "SELECT o.*, u.username as customer_name, u.email as customer_email,
                      p.payMethod, p.payStatus, p.payDate, p.amount as payment_amount,
                      p.transaction_id, p.payment_details
               FROM `order` o 
               JOIN user u ON o.userID = u.userID 
               LEFT JOIN payment p ON o.payID = p.payID
               WHERE o.orderID = ? AND o.userID = ?";

$orderStmt = $_db->prepare($orderQuery);
$orderStmt->execute([$orderID, $userID]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);


if (!$order) {
    header('Location: history.php');
    exit();
}

// Get order items
$itemsQuery = "SELECT oi.*, p.name, p.price, p.image1, p.prodID
               FROM order_items oi 
               JOIN product p ON oi.prodID = p.prodID 
               WHERE oi.orderID = ?
               ORDER BY oi.order_item_id";

$itemsStmt = $_db->prepare($itemsQuery);
$itemsStmt->execute([$orderID]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);


function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info', 
        'processing' => 'badge-primary',
        'shipped' => 'badge-secondary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    
    $class = $badges[$status] ?? 'badge-secondary';
    return "<span class='badge {$class}'>" . ucfirst($status) . "</span>";
}

function formatDate($date) {
    return date('M d, Y - H:i A', strtotime($date));
}

function formatAddress($order) {
    $address = [];
    if (!empty($order['unitNo'])) $address[] = $order['unitNo'];
    if (!empty($order['address_line_1'])) $address[] = $order['address_line_1'];
    if (!empty($order['address_line_2'])) $address[] = $order['address_line_2'];
    if (!empty($order['city'])) $address[] = $order['city'];
    if (!empty($order['postcode'])) $address[] = $order['postcode'];
    if (!empty($order['state'])) $address[] = $order['state'];
    
    return implode('<br>', $address);
}

if (isset($_SESSION['success'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            showSuccess("'.htmlspecialchars($_SESSION['success']).'");
        });
        </script>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            showError("'.htmlspecialchars($_SESSION['error']).'");
        });
        </script>';
    unset($_SESSION['error']);
}

include '../header.php';
?>

<link rel="stylesheet" href="../css/history.css">
<link rel="stylesheet" href="../css/order_details.css">

<body data-user-id="<?= $userID ?>">
    <div class="modern-container">
        <div class="order-header-card">
            <div class="order-header-content">
                <div class="order-info">
                    <h1 class="order-title">Order #<?= $order['orderID'] ?></h1>
                    <p class="order-date"><?= formatDate($order['orderDate']) ?></p>
                </div>
                <div class="order-status-section">
                    <div class="status-badge status-<?= strtolower($order['status']) ?>">
                        <i class="fas fa-<?= strtolower($order['status']) === 'delivered' ? 'check-circle' : (strtolower($order['status']) === 'processing' ? 'cog' : (strtolower($order['status']) === 'cancelled' ? 'times-circle' : 'info-circle')) ?>"></i>
                        <?= htmlspecialchars($order['status']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="main-content-grid">
            <!-- Left Column - Order Items -->
            <div class="left-column">
                <!-- Order Items Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shopping-bag"></i>
                            Order Items (<?= count($items) ?>)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($items as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="<?= !empty($item['image1']) ? 'data:image/jpeg;base64,'.base64_encode($item['image1']) : '../images/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>">
                                </div>
                                <div class="item-details">
                                    <h4 class="item-name"><?= htmlspecialchars($item['name']) ?></h4>
                                    <p class="item-id">Product ID: #<?= $item['prodID'] ?></p>
                                    <?php if (!empty($item['product_color'])): ?>
                                        <p class="item-color">Color: <?= htmlspecialchars($item['product_color']) ?></p>
                                    <?php endif; ?>
                                    <div class="item-price-section">
                                        <span class="item-price"><?= money($item['price']) ?></span>
                                        <span class="item-quantity">× <?= $item['qty'] ?></span>
                                    </div>
                                </div>
                                <div class="item-total">
                                    <span class="total-price"><?= money($item['price'] * $item['qty']) ?></span>
                                </div>
                            </div>
                            
                            <!-- Product Review Section -->
                            <?php if (in_array($order['status'], ['Received', 'Delivered'])): ?>
                                <div class="product-review-section" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <h5 style="color: #8B4513; margin-bottom: 1rem;">
                                        <i class="fas fa-star"></i> Rate & Review This Product
                                    </h5>
                                    
                                    <!-- Check if review already exists -->
                                    <?php
                                    $reviewQuery = "SELECT * FROM product_reviews WHERE order_id = ? AND product_id = ? AND user_id = ?";
                                    $reviewStmt = $_db->prepare($reviewQuery);
                                    $reviewStmt->execute([$orderID, $item['prodID'], $userID]);
                                    $existingReview = $reviewStmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <?php if ($existingReview): ?>
                                        <!-- Show existing review -->
                                        <div class="existing-review" 
                                             data-product-id="<?= htmlspecialchars($item['prodID']) ?>"
                                             data-review-id="<?= htmlspecialchars($existingReview['review_id']) ?>"
                                             data-order-id="<?= htmlspecialchars($orderID) ?>"
                                             data-rating="<?= htmlspecialchars($existingReview['rating']) ?>"
                                             data-title="<?= htmlspecialchars($existingReview['title']) ?>"
                                             data-review-text="<?= htmlspecialchars($existingReview['review_text']) ?>"
                                             data-quality-rating="<?= htmlspecialchars($existingReview['quality_rating'] ?? '') ?>"
                                             data-delivery-rating="<?= htmlspecialchars($existingReview['delivery_rating'] ?? '') ?>"
                                             data-value-rating="<?= htmlspecialchars($existingReview['value_rating'] ?? '') ?>"
                                             style="background: linear-gradient(135deg, #faf8f3 0%, #f5f1e8 100%); padding: 1.25rem; border-radius: 8px; border: 2px solid #d4c4a8; box-shadow: 0 2px 8px rgba(139, 69, 19, 0.1);">
                                            <div class="review-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                <div class="review-rating" style="display: flex; align-items: center; gap: 2px;">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span style="font-size: 1.2rem; color: <?= $i <= $existingReview['rating'] ? '#ffc107' : '#ddd' ?>;">★</span>
                                                    <?php endfor; ?>
                                                    <span style="margin-left: 8px; font-weight: 600; color: #8B4513;"><?= $existingReview['rating'] ?>/5</span>
                                                </div>
                                                <small style="color: #6c757d; font-size: 0.875rem;"><?= formatDate($existingReview['created_at']) ?></small>
                                            </div>
                                            <h6 class="review-title" style="color: #8B4513; font-weight: 600; margin-bottom: 0.5rem;"><?= htmlspecialchars($existingReview['title']) ?></h6>
                                            <p class="review-text" style="color: #495057; line-height: 1.5; margin-bottom: 0;"><?= htmlspecialchars($existingReview['review_text']) ?></p>
                                            
                                            <?php if (!empty($existingReview['review_images'])): ?>
                                                <div class="review-images" style="margin-top: 0.5rem;">
                                                    <?php 
                                                    $images = json_decode($existingReview['review_images'], true);
                                                    if (is_array($images)):
                                                    ?>
                                                        <?php foreach ($images as $image): ?>
                                                            <img src="<?= htmlspecialchars($image) ?>" alt="Review Image" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 0.5rem;">
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="review-actions" style="margin-top: 0.5rem;">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-review-btn" data-product-id="<?= htmlspecialchars($item['prodID']) ?>" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 0.375rem 0.75rem; border-radius: 0.25rem; cursor: pointer; z-index: 10; position: relative;">
                                                    <i class="fas fa-edit"></i> Edit Review
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Review Form -->
                                        <form method="POST" action="submit_review.php" class="review-form" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderID) ?>">
                                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['prodID']) ?>">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($userID) ?>">
                                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                            
                                            <div class="review-form-content">
                                                <!-- Overall Rating (Required) -->
                                                <div class="form-group mb-3">
                                                    <label class="form-label fw-bold">Overall Rating *</label>
                                                    <div class="star-rating" data-rating="0">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <span class="star" data-value="<?= $i ?>" style="font-size: 2rem; color: #ddd; cursor: pointer; margin: 2px; display: inline-block; transition: all 0.2s;">★</span>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <input type="hidden" name="rating" id="rating_<?= $item['prodID'] ?>" required>
                                                    <small class="text-muted">Click stars to rate (1-5 stars)</small>
                                                </div>
                                                
                                                <!-- Detailed Ratings -->
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="form-label">Quality Rating</label>
                                                            <div class="star-rating-small" data-rating="0">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <span class="star-small" data-value="<?= $i ?>" style="font-size: 1.25rem; color: #ddd; cursor: pointer; margin: 1px; display: inline-block; transition: all 0.2s;">★</span>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <input type="hidden" name="quality_rating" id="quality_rating_<?= $item['prodID'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="form-label">Value Rating</label>
                                                            <div class="star-rating-small" data-rating="0">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <span class="star-small" data-value="<?= $i ?>" style="font-size: 1.25rem; color: #ddd; cursor: pointer; margin: 1px; display: inline-block; transition: all 0.2s;">★</span>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <input type="hidden" name="value_rating" id="value_rating_<?= $item['prodID'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="form-label">Delivery Rating</label>
                                                            <div class="star-rating-small" data-rating="0">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <span class="star-small" data-value="<?= $i ?>" style="font-size: 1.25rem; color: #ddd; cursor: pointer; margin: 1px; display: inline-block; transition: all 0.2s;">★</span>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <input type="hidden" name="delivery_rating" id="delivery_rating_<?= $item['prodID'] ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Review Title -->
                                                <div class="form-group mb-3">
                                                    <label for="title_<?= $item['prodID'] ?>" class="form-label">Review Title *</label>
                                                    <input type="text" class="form-control" id="title_<?= $item['prodID'] ?>" name="title" 
                                                           placeholder="Summarize your review" required maxlength="255">
                                                </div>
                                                
                                                <!-- Description -->
                                                <div class="form-group mb-3">
                                                    <label for="review_text_<?= $item['prodID'] ?>" class="form-label">Description *</label>
                                                    <textarea class="form-control" id="review_text_<?= $item['prodID'] ?>" name="review_text" 
                                                              rows="4" placeholder="Share your detailed experience with this product..." required></textarea>
                                                </div>
                                                
                                                <!-- Image Upload -->
                                                <div class="form-group mb-3">
                                                    <label for="review_images_<?= $item['prodID'] ?>" class="form-label">Upload Photos (Optional)</label>
                                                    <input type="file" class="form-control" id="review_images_<?= $item['prodID'] ?>" name="review_images[]" 
                                                           multiple accept="image/*" onchange="previewImages(this, <?= $item['prodID'] ?>)">
                                                    <small class="text-muted">You can upload up to 5 images (JPEG, PNG, GIF, WebP). Max 5MB per image.</small>
                                                    
                                                    <!-- Image Preview Container -->
                                                    <div id="image-preview-<?= $item['prodID'] ?>" class="image-preview-container mt-3" style="display: none;">
                                                        <div class="row" id="preview-row-<?= $item['prodID'] ?>"></div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Action Buttons -->
                                                <div class="form-group d-flex gap-3 justify-content-center">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-paper-plane"></i> Submit Review
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary" onclick="clearReviewForm(<?= $item['prodID'] ?>)">
                                                        <i class="fas fa-eraser"></i> Clear
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Order Totals -->
                        <div class="order-totals">
                            <div class="total-row">
                                <span class="total-label">Subtotal:</span>
                                <span class="total-value"><?= money($order['subtotal']) ?></span>
                            </div>
                            <?php if ($order['shipping_fee'] > 0): ?>
                                <div class="total-row">
                                    <span class="total-label">Shipping Fee:</span>
                                    <span class="total-value"><?= money($order['shipping_fee']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($order['discount'] > 0): ?>
                                <div class="total-row discount">
                                    <span class="total-label">Discount:</span>
                                    <span class="total-value">-<?= money($order['discount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="total-row final-total">
                                <span class="total-label">Total Amount:</span>
                                <span class="total-value"><?= money($order['total']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Order Info -->
            <div class="right-column">
                <!-- Shipping Address Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shipping-fast"></i>
                            Shipping Address
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="address-info">
                            <h4><?= htmlspecialchars($order['recipient_name']) ?></h4>
                            <p class="address-text"><?= formatAddress($order) ?></p>
                            <?php if (!empty($order['notes'])): ?>
                                <div class="order-notes">
                                    <strong>Notes:</strong> <?= htmlspecialchars($order['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Information Card -->
                <?php if (!empty($order['payMethod'])): ?>
                    <div class="modern-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card"></i>
                                Payment Details
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="payment-info">
                                <div class="info-row">
                                    <span class="info-label">Method:</span>
                                    <span class="info-value"><?= ucfirst($order['payMethod']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value status-<?= strtolower($order['payStatus']) ?>"><?= ucfirst($order['payStatus']) ?></span>
                                </div>
                                <?php if (!empty($order['payDate'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Date:</span>
                                        <span class="info-value"><?= formatDate($order['payDate']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Transaction ID:</span>
                                        <span class="info-value"><?= htmlspecialchars($order['transaction_id']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">Amount:</span>
                                    <span class="info-value amount"><?= money($order['payment_amount'] ?? $order['total']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Actions Card -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cog"></i>
                            Actions
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($order['status'] === 'Delivered'): ?>
                                <form method="POST" action="request_refund.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-outline-warning action-btn" 
                                            onclick="return confirm('Are you sure you want to request a refund for this order? This will require admin approval.')">
                                        <i class="fas fa-undo"></i> Request Refund
                                    </button>
                                </form>
                                
                            <?php elseif ($order['status'] === 'Received'): ?>
                                <form method="POST" action="request_refund.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-outline-warning action-btn" 
                                            onclick="return confirm('Are you sure you want to request a refund for this order? This will require admin approval.')">
                                        <i class="fas fa-undo"></i> Request Refund
                                    </button>
                                </form>
                                
                            <?php elseif ($order['status'] === 'Pending'): ?>
                                <form method="POST" action="cancel_order.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-danger action-btn" 
                                            onclick="return confirm('Are you sure you want to cancel this order? This action cannot be undone.')">
                                        <i class="fas fa-times-circle"></i> Cancel Order
                                    </button>
                                </form>
                                
                            <?php elseif ($order['status'] === 'Processing'): ?>
                                <form method="POST" action="cancel_refund_request.php" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-warning action-btn" 
                                            onclick="return confirm('Are you sure you want to cancel your refund request?')">
                                        <i class="fas fa-times"></i> Cancel Refund Request
                                    </button>
                                </form>
                            <?php elseif ($order['status'] === 'Refunded'): ?>
                                <div class="action-btn disabled">
                                    <i class="fas fa-check"></i> Refund Successful
                                </div>
                            <?php endif; ?>
                            
                            <!-- Reorder button - available for all statuses -->
                            <form method="POST" action="reorder.php" class="action-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['orderID']) ?>">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                <button type="submit" class="btn btn-primary action-btn">
                                    <i class="fas fa-redo"></i> Reorder Items
                                </button>
                            </form>
                            
                            <button onclick="window.print()" class="btn btn-outline-secondary action-btn">
                                <i class="fas fa-print"></i> Print Order
                            </button>
                            
                            <a href="history.php" class="btn btn-outline-primary action-btn">
                                <i class="fas fa-arrow-left"></i> Back to History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/history.js"></script>
    <script src="../js/userProduct.js"></script>
    
    <style>
    .edit-review-btn {
        pointer-events: auto !important;
        z-index: 1000 !important;
        position: relative !important;
        cursor: pointer !important;
    }
    
    .edit-review-btn:hover {
        background: #0056b3 !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .edit-review-btn:active {
        transform: translateY(0);
    }
    </style>
    
    <script>
    // Enhanced star rating functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a bit for other scripts to finish
        setTimeout(function() {
            // Setup overall rating stars
            document.querySelectorAll('.star-rating .star').forEach((star, index) => {
                setupStarRating(star, 'rating');
            });
            
            // Setup detailed rating stars (quality, value, delivery)
            document.querySelectorAll('.star-rating-small .star-small').forEach((star, index) => {
                const container = star.closest('.star-rating-small');
                const label = container.previousElementSibling;
                let ratingType = 'quality'; // default
                
                if (label && label.textContent) {
                    const labelText = label.textContent.toLowerCase();
                    if (labelText.includes('quality')) ratingType = 'quality';
                    else if (labelText.includes('value')) ratingType = 'value';
                    else if (labelText.includes('delivery')) ratingType = 'delivery';
                }
                
                setupStarRating(star, ratingType);
            });
        }, 500);
        
        function setupStarRating(star, ratingType) {
            star.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const rating = parseInt(this.getAttribute('data-value'));
                const container = this.parentElement;
                const form = this.closest('form');
                
                if (!form) return;
                
                const productId = form.querySelector('input[name="product_id"]').value;
                const inputId = ratingType + '_rating_' + productId;
                const ratingInput = document.getElementById(inputId);
                
                if (!ratingInput) return;
                
                // Update visual stars
                const allStars = container.querySelectorAll('.star, .star-small');
                allStars.forEach((s, starIndex) => {
                    if (starIndex + 1 <= rating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
                
                // Update data-rating attribute
                container.setAttribute('data-rating', rating);
                
                // Update hidden input
                ratingInput.value = rating;
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-value'));
                const container = this.parentElement;
                const allStars = container.querySelectorAll('.star, .star-small');
                allStars.forEach((s, starIndex) => {
                    if (starIndex + 1 <= rating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                const container = this.parentElement;
                const currentRating = parseInt(container.getAttribute('data-rating')) || 0;
                const allStars = container.querySelectorAll('.star, .star-small');
                allStars.forEach((s, starIndex) => {
                    if (starIndex + 1 <= currentRating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        }
        
        // Add form exclusion logic to prevent "Processing" issue
        // This ensures review forms are not processed by the generic form handler
        setTimeout(() => {
            document.querySelectorAll('form').forEach(form => {
                // Skip review forms and refund-related forms
                if (form.action.includes('submit_review') || 
                    form.action.includes('request_refund.php') || 
                    form.action.includes('cancel_refund_request.php') ||
                    form.classList.contains('review-form') ||
                    form.querySelector('input[name="product_id"]')) { // Skip any form with product_id (review forms)
                    
                    // Add debug logging for review forms
                    form.addEventListener('submit', function(e) {
                        console.log('Review form submitting...');
                        console.log('Form data:', new FormData(this));
                        console.log('User ID from session:', '<?= $_SESSION['user_id'] ?? 'NOT SET' ?>');
                        console.log('Product ID:', this.querySelector('input[name="product_id"]').value);
                        console.log('Order ID:', this.querySelector('input[name="order_id"]').value);
                        console.log('Rating:', this.querySelector('input[name="rating"]').value);
                    });
                    
                    return; // Skip this form
                }
                
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]:not(.reorder-btn):not(.cancel-order-btn), input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        const originalText = submitBtn.textContent || submitBtn.value;
                        
                        // Small delay to ensure the loading state shows
                        setTimeout(() => {
                            submitBtn.disabled = true;
                            
                            if (submitBtn.textContent !== undefined) {
                                submitBtn.innerHTML = 'Processing';
                            } else {
                                submitBtn.value = 'Processing';
                            }
                        }, 10);
                        
                        // Re-enable after 15 seconds as fallback
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            if (submitBtn.textContent !== undefined) {
                                submitBtn.innerHTML = originalText;
                            } else {
                                submitBtn.value = originalText;
                            }
                        }, 15000);
                    }
                });
            });
        }, 600); // Run after star rating setup
    });
    
    
    // Image preview function
    function previewImages(input, productId) {
        const previewContainer = document.getElementById('image-preview-' + productId);
        const previewRow = document.getElementById('preview-row-' + productId);
        
        // Clear previous previews
        previewRow.innerHTML = '';
        
        if (input.files && input.files.length > 0) {
            previewContainer.style.display = 'block';
            
            // Limit to 5 images
            const files = Array.from(input.files).slice(0, 5);
            
            files.forEach((file, index) => {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select only image files.');
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB: ' + file.name);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const col = document.createElement('div');
                    col.className = 'col-md-3 col-sm-4 col-6 mb-3';
                    
                    col.innerHTML = `
                        <div class="image-preview-item position-relative">
                            <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 120px; object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                    onclick="removeImagePreview(this, ${productId})" title="Remove image">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    
                    previewRow.appendChild(col);
                };
                reader.readAsDataURL(file);
            });
        } else {
            previewContainer.style.display = 'none';
        }
    }
    
    // Remove image preview function
    function removeImagePreview(button, productId) {
        const previewItem = button.closest('.image-preview-item');
        const previewRow = document.getElementById('preview-row-' + productId);
        const previewContainer = document.getElementById('image-preview-' + productId);
        
        previewItem.remove();
        
        // Hide container if no images left
        if (previewRow.children.length === 0) {
            previewContainer.style.display = 'none';
        }
        
        // Reset file input
        const fileInput = document.getElementById('review_images_' + productId);
        fileInput.value = '';
    }
    
    // Clear review form function
    function clearReviewForm(productId) {
        const form = document.querySelector(`input[name="product_id"][value="${productId}"]`).closest('form');
        if (!form) return;
        
        // Reset overall rating
        const overallRating = form.querySelector('.star-rating');
        if (overallRating) {
            overallRating.setAttribute('data-rating', '0');
            const overallStars = overallRating.querySelectorAll('.star');
            overallStars.forEach(star => {
                star.style.color = '#ddd';
            });
            const overallInput = document.getElementById('rating_' + productId);
            if (overallInput) overallInput.value = '0';
        }
        
        // Reset detailed ratings
        const detailedRatings = form.querySelectorAll('.star-rating-small');
        detailedRatings.forEach(container => {
            container.setAttribute('data-rating', '0');
            const stars = container.querySelectorAll('.star-small');
            stars.forEach(star => {
                star.style.color = '#ddd';
            });
        });
        
        // Reset hidden inputs
        const qualityInput = document.getElementById('quality_rating_' + productId);
        const valueInput = document.getElementById('value_rating_' + productId);
        const deliveryInput = document.getElementById('delivery_rating_' + productId);
        
        if (qualityInput) qualityInput.value = '';
        if (valueInput) valueInput.value = '';
        if (deliveryInput) deliveryInput.value = '';
        
        // Reset form fields
        const titleInput = form.querySelector('input[name="title"]');
        const reviewTextInput = form.querySelector('textarea[name="review_text"]');
        const fileInput = form.querySelector('input[name="review_images[]"]');
        
        if (titleInput) titleInput.value = '';
        if (reviewTextInput) reviewTextInput.value = '';
        if (fileInput) fileInput.value = '';
        
        // Clear image previews
        const previewContainer = document.getElementById('image-preview-' + productId);
        const previewRow = document.getElementById('preview-row-' + productId);
        if (previewContainer) previewContainer.style.display = 'none';
        if (previewRow) previewRow.innerHTML = '';
        
        // Show confirmation
        alert('Review form cleared successfully!');
    }
    </script>
</body>
<?php include '../footer.php'; ?>