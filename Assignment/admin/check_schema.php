<?php
require_once '../_base.php';

echo "<h2>Database Schema Analysis</h2>";

// Check what tables actually exist
try {
    $stmt = $_db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Available Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check order-related tables specifically
    $order_tables = array_filter($tables, function($table) {
        return stripos($table, 'order') !== false;
    });
    
    echo "<h3>Order-related Tables:</h3>";
    foreach ($order_tables as $table) {
        echo "<h4>Table: $table</h4>";
        $stmt = $_db->query("SHOW COLUMNS FROM `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . "</li>";
        }
        echo "</ul>";
        
        // Show sample data
        $stmt = $_db->query("SELECT * FROM `$table` LIMIT 2");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($samples)) {
            echo "<p><strong>Sample data:</strong></p>";
            echo "<pre>";
            print_r($samples[0]);
            echo "</pre>";
        }
    }
    
    // Check user table
    if (in_array('user', $tables)) {
        echo "<h3>User Table Structure:</h3>";
        $stmt = $_db->query("SHOW COLUMNS FROM user");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . "</li>";
        }
        echo "</ul>";
    }
    
    // Check if contact_messages exists
    if (in_array('contact_messages', $tables)) {
        echo "<h3>Contact Messages Table:</h3>";
        $stmt = $_db->query("SHOW COLUMNS FROM contact_messages");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>" . $col['Field'] . "</strong> - " . $col['Type'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange'>⚠️ contact_messages table not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
