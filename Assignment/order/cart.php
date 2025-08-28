<?php
require_once __DIR__ . '/../_base.php';

$user_id = $_SESSION['user_id'] ?? null;

function jsonResponse($payload, $status = 200) {
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($payload);
    exit;
}

// Read JSON from frontend, turn into PHP array 
$payload = json_decode(file_get_contents("php://input"), true) ?? [];

// Also check GET parameters for action
$action = $payload['action'] ?? ($_GET['action'] ?? '');

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

// Handle AJAX requests
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    // If no user is logged in, return error for AJAX requests
    if (!$user_id) {
        jsonResponse(["error" => "Please log in"], 401);
    }
    
    try{
        error_log("Cart API - Action: " . $action . ", Payload: " . json_encode($payload));

        switch($action){
            case "add":
                $prodID = $payload['id'] ?? null;
                $qty = max(1, (int)($payload['qty'] ?? 1));

                if (!$prodID) {
                    jsonResponse(['error' => 'Invalid product ID'], 400);
                }
                
                $cartID = ensureCart($user_id);
                if (!$cartID) {
                    jsonResponse(['error' => 'Unable to create cart'], 500);
                }

                // Check product exists and get stock
                $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
                $stmt->execute([$prodID]);
                $stock = $stmt->fetchColumn();
                
                if ($stock === false) {
                    jsonResponse(['error' => 'Product not found'], 404);
                }
                
                $stock = (int)$stock;
                if ($stock <= 0) {
                    jsonResponse(['error' => 'Out of stock'], 400);
                }

                // Check if already in cart
                $stmt = $_db->prepare("SELECT qty FROM cart_items WHERE cartID=? AND prodID=?");
                $stmt->execute([$cartID, $prodID]);
                $current = $stmt->fetchColumn();

                if ($current !== false) {
                    // Update existing item
                    $newQty = min((int)$current + $qty, $stock);
                    if ($newQty > $stock) {
                        jsonResponse(['error' => 'Not enough stock available'], 400);
                    }

                    $stmt = $_db->prepare("UPDATE cart_items SET qty=? WHERE cartID=? AND prodID=?");
                    $stmt->execute([$newQty, $cartID, $prodID]);
                } else {
                    // Add new item
                    if ($qty > $stock) {
                        jsonResponse(['error' => 'Not enough stock available'], 400);
                    }

                    $stmt = $_db->prepare("INSERT INTO cart_items(cartID, prodID, qty) VALUES(?,?,?)");
                    $stmt->execute([$cartID, $prodID, $qty]);
                }
                break;

            case "update_qty":
                $prodID = $payload['id'] ?? null;
                $qty = max(1, (int)($payload['qty'] ?? 1));

                if (!$prodID) {
                    jsonResponse(['error' => 'Invalid product ID'], 400);
                }

                // Get stock
                $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
                $stmt->execute([$prodID]);
                $stock = $stmt->fetchColumn();
                
                if ($stock === false) {
                    jsonResponse(['error' => 'Product not found'], 404);
                }
                
                $stock = (int)$stock;
                if ($stock <= 0) {
                    jsonResponse(['error' => 'Product is out of stock'], 400);
                }
                
                if ($qty > $stock) {
                    jsonResponse(['error' => 'Not enough stock available. Available: ' . $stock], 400);
                }

                // Get cartID
                $cartID = ensureCart($user_id);
                if (!$cartID) {
                    jsonResponse(['error' => 'Cart not found'], 404);
                }

                // Check if item exists in cart
                $stmt = $_db->prepare("SELECT qty FROM cart_items WHERE cartID=? AND prodID=?");
                $stmt->execute([$cartID, $prodID]);
                
                if ($stmt->fetchColumn() === false) {
                    jsonResponse(['error' => 'Item not found in cart'], 404);
                }

                // Update quantity
                $stmt = $_db->prepare("UPDATE cart_items SET qty=? WHERE cartID=? AND prodID=?");
                $stmt->execute([$qty, $cartID, $prodID]);
                break;

            case "remove":
                $prodID = $payload['id'] ?? null;

                if (!$prodID) {
                    jsonResponse(['error' => 'Invalid product ID'], 400);
                }

                $cartID = ensureCart($user_id);
                if (!$cartID) {
                    jsonResponse(['error' => 'Cart not found'], 404);
                }

                $stmt = $_db->prepare("DELETE FROM cart_items WHERE cartID=? AND prodID=?");
                $stmt->execute([$cartID, $prodID]);
                
                if ($stmt->rowCount() === 0) {
                    jsonResponse(['error' => 'Item not found in cart'], 404);
                }
                break;

            case "clear_all":
                $cartID = ensureCart($user_id);
                if ($cartID) {
                    $stmt = $_db->prepare("DELETE FROM cart_items WHERE cartID=?");
                    $stmt->execute([$cartID]);
                }
                break;

            case "count":
                $cart = get_cart($user_id);
                $totals = cartTotals($cart);
                jsonResponse([
                    "success" => true,
                    "totals" => $totals,
                    "count" => $totals['itemCount']
                ]);
                break;

            case "":
                // Just return current cart data
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }

        // Always return updated cart data after any operation
        $cart = get_cart($user_id);
        $totals = cartTotals($cart);
        
        jsonResponse([
            "success" => true,
            "cart" => $cart,
            "totals" => $totals
        ]);

    } catch (Exception $e) {
        error_log("Cart error: " . $e->getMessage());
        jsonResponse(['error' => 'An error occurred while processing your request'], 500);
    }
}

// For non-AJAX requests, just return current cart data
$cart = get_cart($user_id);
$totals = cartTotals($cart);

// If this is a direct access (not included), show cart data
if (basename($_SERVER['PHP_SELF']) === 'cart.php') {
    jsonResponse([
        "success" => true,
        "cart" => $cart,
        "totals" => $totals
    ]);
}
?>