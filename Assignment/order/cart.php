<?php
require_once __DIR__ . '/../_base.php';

$user_id = $_SESSION['user_id'] ?? null;

checkLogin();

// Fetch cart items from DB
function get_cart($user_id){
    global $_db;

    if (!$user_id) return [];

    $stmt = $_db->prepare("SELECT ci.cartID, ci.prodID, ci.qty AS cartQty, p.name, p.price, p.image1, p.color, p.qty AS stock
                            FROM cart_items ci
                            JOIN cart c ON ci.cartID = c.cartID
                            JOIN product p ON ci.prodID = p.prodID
                            WHERE c.userID = ?");
    $stmt->execute([$user_id]);

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[$row['prodID']] = [
            'id' => $row['prodID'],
            'product' => [
                'title' => $row['name'],
                'color' => $row['color'] ?? '',
                'price' => (float)$row['price'],
                'img' => !empty($row['image1']) ? 'data:image/jpeg;base64,'.base64_encode($row['image1']) : '/images/placeholder.jpg',
                'stock' => (int)$row['stock']
            ],
            'qty' => (int)$row['cartQty'],
            'selected' => true
        ];
    }
    return $items;
}

function cartTotals($cart){
    $subtotal = 0;
    $total = 0;
    $itemCount = 0;

    foreach($cart as $row){
        $subtotal += $row['product']['price'] * $row['qty'];
        $itemCount += $row['qty']; 
    }

    $discount = 0.00;
    $total = max($subtotal - $discount, 0);

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'itemCount' => $itemCount
    ];
}

// Ensure cart exists for logged in user
function ensureCart($user_id) {
    global $_db;
    
    if (!$user_id) return null;
    
    $stmt = $_db->prepare("SELECT cartID FROM cart WHERE userID=?");
    $stmt->execute([$user_id]);
    $cartID = $stmt->fetchColumn();

    if (!$cartID) {
        $stmt = $_db->prepare("INSERT INTO cart(userID) VALUES(?)");
        $stmt->execute([$user_id]);
        $cartID = $_db->lastInsertId();
    }
    
    return $cartID;
}

if(isset($_GET['action']) && $_GET['action'] === 'count') {
    header('Content-Type: text/plain');
    echo getCartCount($user_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_url = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/order/cart_page.php';
    
    try {
        switch($action){
            case "update_qty":
                $prodID = $_POST['id'] ?? $_GET['prodID'] ?? null;
                $qtyRaw = (int)($_POST['qty'] ?? $_GET['qty'] ?? 1);
                $qty = $qtyRaw;

                if (!$prodID) {
                    $_SESSION['error'] = "Invalid product ID";
                    redirect('/order/cart_page.php');
                }

                // Get stock
                $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
                $stmt->execute([$prodID]);
                $stock = (int) $stmt->fetchColumn();
                
                if ($stock === false) {
                    $_SESSION['error'] = "Product not found";
                    redirect('/order/cart_page.php');
                }
                
                // If new qty is 0, remove the item
                if ($qty <= 0) {
                    $cartID = ensureCart($user_id);
                    if ($cartID) {
                        $stmt = $_db->prepare("DELETE FROM cart_items WHERE cartID=? AND prodID=?");
                        $stmt->execute([$cartID, $prodID]);
                        $_SESSION['success'] = "Item removed from cart";
                    }
                    redirect('/order/cart_page.php');
                }

                if ($stock <= 0) {
                    $_SESSION['error'] = "Product is out of stock";
                    redirect('/order/cart_page.php');
                }
                
                if ($qty > $stock) {
                    $_SESSION['error'] = "Not enough stock available. Available: " . $stock;
                    redirect('/order/cart_page.php');
                }

                // Get cartID
                $cartID = ensureCart($user_id);
                if (!$cartID) {
                    $_SESSION['error'] = "Cart not found";
                    redirect('/order/cart_page.php');
                }

                // Check if item exists in cart
                $stmt = $_db->prepare("SELECT qty FROM cart_items WHERE cartID=? AND prodID=?");
                $stmt->execute([$cartID, $prodID]);
                
                if ($stmt->fetchColumn() === false) {
                    $_SESSION['error'] = "Item not found in cart";
                    redirect('/order/cart_page.php');
                }

                // Update quantity
                $stmt = $_db->prepare("UPDATE cart_items SET qty=? WHERE cartID=? AND prodID=?");
                $stmt->execute([$qty, $cartID, $prodID]);

                $_SESSION['success'] = "Cart updated successfully";
                redirect('/order/cart_page.php');
                break;

            case "remove":
                $prodID = $_POST['id'] ?? $_GET['prodID'] ?? null;

                if (!$prodID) {
                    $_SESSION['error'] = "Invalid product ID";
                    redirect('/order/cart_page.php');
                }

                $cartID = ensureCart($user_id);
                if (!$cartID) {
                    $_SESSION['error'] = "Cart not found";
                    redirect('/order/cart_page.php');
                }

                $stmt = $_db->prepare("DELETE FROM cart_items WHERE cartID=? AND prodID=?");
                $stmt->execute([$cartID, $prodID]);
                
                if ($stmt->rowCount() === 0) {
                    $_SESSION['error'] = "Item not found in cart";
                    redirect('/order/cart_page.php');
                }

                $_SESSION['success'] = "Item removed from cart";
                redirect('/order/cart_page.php');
                break;
        }

        $cart = get_cart($user_id);
        $totals = cartTotals($cart);

    } catch (Exception $e) {
        error_log("Cart error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing your request";
        redirect('/order/cart_page.php');
    }
}

// Handle GET requests (for count only)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'count') {
        if (!isset($_SESSION['user_id'])) {
            echo "0";
            exit;
        }
        $cartID = ensureCart($_SESSION['user_id']);
        $stmt = $_db->prepare("SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cartID=?");
        $stmt->execute([$cartID]);
        echo (int)$stmt->fetchColumn();
        exit;
    }
}

// For direct access, redirect to cart page
if (basename($_SERVER['PHP_SELF']) === 'cart.php') {
    redirect('/order/cart_page.php');
}
?>