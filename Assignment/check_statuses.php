<?php
include 'config.php';

try {
    $stmt = $_db->query('SELECT DISTINCT status, COUNT(*) as count FROM `order` WHERE status IS NOT NULL GROUP BY status ORDER BY count DESC');
    echo "Current order statuses in database:" . PHP_EOL;
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['status'] . ': ' . $row['count'] . ' orders' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
