<?php
include 'config.php';
try {
    $stmt = $_db->prepare('SELECT prodID, name, status FROM product WHERE name LIKE ?');
    $stmt->execute(['%eowufhweufg%']);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "No products found with name containing 'eowufhweufg'\n";
        
        // Show all products to see what exists
        echo "\nAll products in database:\n";
        $stmt = $_db->prepare('SELECT prodID, name, status FROM product ORDER BY name LIMIT 10');
        $stmt->execute();
        $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_products as $p) {
            echo sprintf("ID: %s, Name: %s, Status: %s\n", $p['prodID'], $p['name'], $p['status'] ?? 'NULL');
        }
    } else {
        echo "Found products:\n";
        foreach ($products as $p) {
            echo sprintf("ID: %s, Name: %s, Status: %s\n", $p['prodID'], $p['name'], $p['status'] ?? 'NULL');
        }
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
