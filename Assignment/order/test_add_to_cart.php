<?php
session_start();
require_once '../_base.php';

echo "<h2>Add to Cart Test</h2>";

// Check if user is logged in
if (!isLoggedIn()) {
    echo "<p style='color: red;'>❌ User is NOT logged in</p>";
    echo "<p><a href='../user/login.php'>Login here</a></p>";
    exit;
}

$userID = $_SESSION['user_id'];
echo "<p style='color: green;'>✅ User is logged in: $userID</p>";

// Test the cart add functionality
if (isset($_POST['test_add'])) {
    $prodID = intval($_POST['prodID'] ?? 1);
    $qty = intval($_POST['qty'] ?? 1);
    
    echo "<h3>Testing Add to Cart with Product ID: $prodID, Quantity: $qty</h3>";
    
    try {
        // Simulate the cart_add.php logic
        $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
        $cartStmt = $_db->prepare($cartQuery);
        $cartStmt->execute([$userID]);
        $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cart) {
            $createCartQuery = "INSERT INTO cart (userID) VALUES (?)";
            $createCartStmt = $_db->prepare($createCartQuery);
            $createCartStmt->execute([$userID]);
            $cartID = $_db->lastInsertId();
            echo "<p>✅ Created cart with ID: $cartID</p>";
        } else {
            $cartID = $cart['cartID'];
            echo "<p>✅ Using existing cart with ID: $cartID</p>";
        }
        
        // Check if product exists
        $productQuery = "SELECT prodID, name, price, qty FROM product WHERE prodID = ? AND (status IS NULL OR status != 'removed')";
        $productStmt = $_db->prepare($productQuery);
        $productStmt->execute([$prodID]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo "<p>✅ Product found: " . $product['name'] . " - Price: " . $product['price'] . " - Stock: " . $product['qty'] . "</p>";
            
            // Check if item already exists in cart
            $existingItemQuery = "SELECT * FROM cart_items WHERE cartID = ? AND prodID = ?";
            $existingItemStmt = $_db->prepare($existingItemQuery);
            $existingItemStmt->execute([$cartID, $prodID]);
            $existingItem = $existingItemStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingItem) {
                // Update existing item
                $newQty = $existingItem['qty'] + $qty;
                $updateItemQuery = "UPDATE cart_items SET qty = ? WHERE cartID = ? AND prodID = ?";
                $updateItemStmt = $_db->prepare($updateItemQuery);
                $updateItemStmt->execute([$newQty, $cartID, $prodID]);
                echo "<p>✅ Updated existing cart item. New quantity: $newQty</p>";
            } else {
                // Add new item to cart
                $addItemQuery = "INSERT INTO cart_items (cartID, prodID, qty, price) VALUES (?, ?, ?, ?)";
                $addItemStmt = $_db->prepare($addItemQuery);
                $addItemStmt->execute([$cartID, $prodID, $qty, $product['price']]);
                echo "<p>✅ Added new item to cart</p>";
            }
            
            // Update cart count in session
            $cartCountQuery = "SELECT SUM(qty) as total FROM cart_items WHERE cartID = ?";
            $cartCountStmt = $_db->prepare($cartCountQuery);
            $cartCountStmt->execute([$cartID]);
            $cartCount = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
            $cartCountTotal = $cartCount['total'] ?? 0;
            $_SESSION['cart_count'] = $cartCountTotal;
            
            echo "<p>✅ Cart count updated: $cartCountTotal</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Product not found or unavailable</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// Show current cart contents
try {
    $cartQuery = "SELECT cartID FROM cart WHERE userID = ?";
    $cartStmt = $_db->prepare($cartQuery);
    $cartStmt->execute([$userID]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cart) {
        $itemsQuery = "SELECT ci.*, p.name, p.price FROM cart_items ci JOIN product p ON ci.prodID = p.prodID WHERE ci.cartID = ?";
        $itemsStmt = $_db->prepare($itemsQuery);
        $itemsStmt->execute([$cart['cartID']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Current Cart Contents:</h3>";
        if (count($items) > 0) {
            echo "<ul>";
            foreach ($items as $item) {
                echo "<li>{$item['name']} - Qty: {$item['qty']} - Price: {$item['price']} - Total: " . ($item['qty'] * $item['price']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Cart is empty</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading cart: " . $e->getMessage() . "</p>";
}

// Test form
echo "<h3>Test Add to Cart:</h3>";
echo "<form method='POST'>";
echo "<p>Product ID: <input type='number' name='prodID' value='1' min='1'></p>";
echo "<p>Quantity: <input type='number' name='qty' value='1' min='1'></p>";
echo "<p><button type='submit' name='test_add' value='1'>Test Add to Cart</button></p>";
echo "</form>";

echo "<p><a href='../userProduct/productList.php'>Go to Product List</a></p>";
echo "<p><a href='cart_page.php'>View Cart Page</a></p>";
?>

