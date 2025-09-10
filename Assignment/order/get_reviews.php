<?php
header('Content-Type: application/json');
require_once '../_base.php';

$product_id = $_GET['product_id'] ?? '';

if (empty($product_id)) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

try {
    // Debug logging
    error_log("Getting reviews for product_id: " . $product_id);
    
    // Get detailed reviews for the product
    $sql = "SELECT pr.*, u.username, pr.created_at as review_date
            FROM product_reviews pr
            JOIN user u ON pr.user_id = u.userID
            WHERE pr.product_id = ?
            ORDER BY pr.created_at DESC";
    
    $stmt = $_db->prepare($sql);
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($reviews) . " reviews for product " . $product_id);
    
    // Format the reviews for display
    $formatted_reviews = [];
    foreach ($reviews as $review) {
        $formatted_reviews[] = [
            'username' => htmlspecialchars($review['username']),
            'rating' => (int)$review['rating'],
            'title' => htmlspecialchars($review['title'] ?? ''),
            'content' => htmlspecialchars($review['review_text']),
            'date' => date('M d, Y', strtotime($review['review_date']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $formatted_reviews
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reviews'
    ]);
}
?>
