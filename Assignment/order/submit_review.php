<?php
session_start();
require_once '../_base.php';

// Check if user is logged in
$userID = $_SESSION['user_id'] ?? null;

// Fallback: try to get user_id from form if session is not available
if (!$userID && isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $userID = (int)$_POST['user_id'];
    error_log("Using user_id from form: " . $userID);
}

if (!$userID) {
    error_log("No user ID found in session or form. Session data: " . print_r($_SESSION, true));
    $_SESSION['error'] = "Please log in to submit a review.";
    header('Location: ../user/login.php');
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header('Location: history.php');
    exit();
}

// Get and validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['error'] = "Invalid security token. Please try again.";
    header('Location: history.php');
    exit();
}

// Get form data
$orderID = $_POST['order_id'] ?? '';
$productID = $_POST['product_id'] ?? '';
$rating = (int)($_POST['rating'] ?? 0);
$title = trim($_POST['title'] ?? '');
$reviewText = trim($_POST['review_text'] ?? '');
$qualityRating = !empty($_POST['quality_rating']) ? (int)$_POST['quality_rating'] : null;
$deliveryRating = !empty($_POST['delivery_rating']) ? (int)$_POST['delivery_rating'] : null;
$valueRating = !empty($_POST['value_rating']) ? (int)$_POST['value_rating'] : null;

// Debug logging
error_log("Review submission debug:");
error_log("User ID: " . $userID);
error_log("Order ID: " . $orderID);
error_log("Product ID: " . $productID);
error_log("Rating: " . $rating);
error_log("Title: " . $title);
error_log("Review Text: " . $reviewText);


// Validate required fields
if (empty($orderID) || empty($productID) || $rating < 1 || $rating > 5 || empty($title) || empty($reviewText)) {
    $_SESSION['error'] = "Please fill in all required fields and provide a rating.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
}

// Verify the order belongs to the user and is in a reviewable status
$orderQuery = "SELECT orderID, status FROM `order` WHERE orderID = ? AND userID = ? AND status IN ('Received', 'Delivered')";
$orderStmt = $_db->prepare($orderQuery);
$orderStmt->execute([$orderID, $userID]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found, access denied, or order is not in a reviewable status.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
}

// Check if review already exists
$existingReviewQuery = "SELECT review_id FROM product_reviews WHERE order_id = ? AND product_id = ? AND user_id = ?";
$existingReviewStmt = $_db->prepare($existingReviewQuery);
$existingReviewStmt->execute([$orderID, $productID, $userID]);
$existingReview = $existingReviewStmt->fetch(PDO::FETCH_ASSOC);

if ($existingReview) {
    $_SESSION['error'] = "You have already reviewed this product for this order.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
}

// Handle image uploads
$uploadedImages = [];
if (!empty($_FILES['review_images']['name'][0])) {
    $uploadDir = '../uploads/reviews/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $maxFiles = 5;
    
    $fileCount = count($_FILES['review_images']['name']);
    if ($fileCount > $maxFiles) {
        $_SESSION['error'] = "You can upload a maximum of $maxFiles images.";
        header('Location: history_details.php?id=' . urlencode($orderID));
        exit();
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['review_images']['error'][$i] === UPLOAD_ERR_OK) {
            $fileType = $_FILES['review_images']['type'][$i];
            $fileSize = $_FILES['review_images']['size'][$i];
            $fileName = $_FILES['review_images']['name'][$i];
            
            // Validate file type and size
            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['error'] = "Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.";
                header('Location: history_details.php?id=' . urlencode($orderID));
                exit();
            }
            
            if ($fileSize > $maxFileSize) {
                $_SESSION['error'] = "File size too large. Maximum size is 5MB per image.";
                header('Location: history_details.php?id=' . urlencode($orderID));
                exit();
            }
            
            // Generate unique filename
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = uniqid('review_', true) . '_' . $i . '.' . $fileExtension;
            $filePath = $uploadDir . $uniqueFileName;
            
            if (move_uploaded_file($_FILES['review_images']['tmp_name'][$i], $filePath)) {
                $uploadedImages[] = [
                    'path' => 'uploads/reviews/' . $uniqueFileName,
                    'name' => $fileName,
                    'size' => $fileSize,
                    'type' => $fileType
                ];
            }
        }
    }
}

try {
    $_db->beginTransaction();
    
    // Insert review
    $reviewQuery = "INSERT INTO product_reviews 
                    (order_id, user_id, product_id, rating, title, review_text, review_images, 
                     quality_rating, delivery_rating, value_rating, is_verified_purchase, is_approved) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, FALSE)";
    
    $reviewStmt = $_db->prepare($reviewQuery);
    $reviewImagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;
    
    $reviewStmt->execute([
        $orderID,
        (int)$userID,  // Ensure integer
        (int)$productID,  // Ensure integer
        $rating,
        $title,
        $reviewText,
        $reviewImagesJson,
        $qualityRating,
        $deliveryRating,
        $valueRating
    ]);
    
    $reviewID = $_db->lastInsertId();
    
    // Insert individual image records if using separate table approach
    if (!empty($uploadedImages)) {
        $imageQuery = "INSERT INTO review_images (review_id, image_path, image_name, image_size, image_type) VALUES (?, ?, ?, ?, ?)";
        $imageStmt = $_db->prepare($imageQuery);
        
        foreach ($uploadedImages as $image) {
            $imageStmt->execute([
                $reviewID,
                $image['path'],
                $image['name'],
                $image['size'],
                $image['type']
            ]);
        }
    }
    
    $_db->commit();
    
    $_SESSION['success'] = "Thank you for your review! It has been submitted and is pending approval.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
    
} catch (Exception $e) {
    $_db->rollback();
    error_log("Review submission error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while submitting your review. Please try again.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
}
?>

<script src="../js/loginRegister.js"></script>