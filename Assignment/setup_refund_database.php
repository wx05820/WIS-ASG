<?php
// Script to create the refund_requests table and clear any test data
require_once '_base.php';

try {
    // Create the refund_requests table
    $sql = "
    CREATE TABLE IF NOT EXISTS `refund_requests` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `orderID` varchar(20) NOT NULL,
      `userID` varchar(10) NOT NULL,
      `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
      `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
      `reason` text DEFAULT NULL,
      `admin_notes` text DEFAULT NULL,
      `processed_date` timestamp NULL DEFAULT NULL,
      `processed_by` varchar(10) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_order_refund` (`orderID`),
      KEY `idx_userID` (`userID`),
      KEY `idx_status` (`status`),
      KEY `idx_request_date` (`request_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $_db->exec($sql);
    echo "✅ refund_requests table created successfully!<br>";
    
    // Clear any existing test data
    $clear_sql = "DELETE FROM refund_requests WHERE status = 'pending'";
    $result = $_db->exec($clear_sql);
    echo "✅ Cleared existing pending refund requests<br>";
    
    // Reset any orders that were in Processing status back to Delivered
    $reset_orders = "UPDATE `order` SET status = 'Delivered' WHERE status = 'Processing'";
    $result = $_db->exec($reset_orders);
    echo "✅ Reset Processing orders back to Delivered status<br>";
    
    echo "<br>🎉 Database setup complete! Ready for real refund requests.<br>";
    echo "<a href='admin/contact_messages.php'>Go to Contact Messages</a>";
    
} catch (Exception $e) {
    echo "❌ Error setting up database: " . $e->getMessage();
}
?>
