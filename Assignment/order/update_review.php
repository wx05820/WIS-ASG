<?php
session_start();
require_once '../_base.php';

// Check if user is logged in
$userID = $_SESSION['user_id'] ?? null;

// Fallback: try to get user_id from form if session is not available
if (!$userID && isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $userID = (int)$_POST['user_id'];
}

if (!$userID) {
    $_SESSION['error'] = "Please log in to update a review.";
    header('Location: ../user/login.php');
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header('Location: history.php');
    exit();
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['error'] = "Invalid security token. Please try again.";
    header('Location: history.php');
    exit();
}

// Get form data
$reviewID = $_POST['review_id'] ?? '';
$orderID = $_POST['order_id'] ?? '';
$productID = $_POST['product_id'] ?? '';
$rating = (int)($_POST['rating'] ?? 0);
$title = trim($_POST['title'] ?? '');
$reviewText = trim($_POST['review_text'] ?? '');
$qualityRating = !empty($_POST['quality_rating']) ? (int)$_POST['quality_rating'] : null;
$deliveryRating = !empty($_POST['delivery_rating']) ? (int)$_POST['delivery_rating'] : null;
$valueRating = !empty($_POST['value_rating']) ? (int)$_POST['value_rating'] : null;

// Validate required fields
if (empty($reviewID) || empty($orderID) || empty($productID) || $rating < 1 || $rating > 5 || empty($title) || empty($reviewText)) {
    $_SESSION['error'] = "Please fill in all required fields and provide a rating.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
}

// Verify the review belongs to the user
$reviewQuery = "SELECT review_id, order_id, product_id, user_id, review_images FROM product_reviews WHERE review_id = ? AND user_id = ?";
$reviewStmt = $_db->prepare($reviewQuery);
$reviewStmt->execute([(int)$reviewID, (int)$userID]);
$existingReview = $reviewStmt->fetch(PDO::FETCH_ASSOC);

if (!$existingReview) {
    $_SESSION['error'] = "Review not found or access denied.";
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
                    'filename' => $uniqueFileName,
                    'path' => 'uploads/reviews/' . $uniqueFileName,
                    'original_name' => $fileName
                ];
            }
        }
    }
}

try {
    $_db->beginTransaction();
    
    // Update the review
    $updateQuery = "UPDATE product_reviews SET 
                    rating = ?, title = ?, review_text = ?, review_images = ?, 
                    quality_rating = ?, delivery_rating = ?, value_rating = ?, updated_at = NOW() 
                    WHERE review_id = ? AND user_id = ?";
    
    // Handle images - keep existing if no new ones uploaded
    $reviewImagesJson = $existingReview['review_images']; // Keep existing images
    if (!empty($uploadedImages)) {
        // If new images uploaded, replace existing ones
        $reviewImagesJson = json_encode($uploadedImages);
    }
    
    $updateStmt = $_db->prepare($updateQuery);
    $updateStmt->execute([
        $rating,
        $title,
        $reviewText,
        $reviewImagesJson,
        $qualityRating,
        $deliveryRating,
        $valueRating,
        (int)$reviewID,
        (int)$userID
    ]);
    
    $_db->commit();
    
    $_SESSION['success'] = "Your review has been updated successfully!";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
    
} catch (Exception $e) {
    $_db->rollback();
    error_log("Review update error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while updating your review. Please try again.";
    header('Location: history_details.php?id=' . urlencode($orderID));
    exit();
}
?>

