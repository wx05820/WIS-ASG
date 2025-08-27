<?php
require_once __DIR__ . '/../_base.php';

$user_id = $_SESSION['user_id'];
checkLoginAndPrompt('../login.php');

// Fetch cart items from DB
function get_cart($user_id){
    global $_db;
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
                'img' => !empty($row['image1']) ? 'data:image/jpeg;base64,'.base64_encode($row['image1']) : '',
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
    $itemCount = 0;

    foreach($cart as $row){
        $subtotal += $row['product']['price'] * $row['qty'];
        $itemCount += $row['qty'];  
    }

    $shipping = $subtotal > 0 ? 10.00 : 0.00;
    $discount = 0.00;
    $total = max($subtotal + $shipping - $discount, 0);

    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'discount' => $discount,
        'total' => $total,
        'itemCount' => $itemCount
    ];
}

$action = $_GET['action'] ?? '';
// Read JSON from frontend, turn into PHP array 
$payload = json_decode(file_get_contents("php://input"), true) ?? [];

// Ensure cart exists
$stmt = $_db->prepare("SELECT cartID FROM cart WHERE userID=?");
$stmt->execute([$user_id]);
$cartID = $stmt->fetchColumn();

if(!$cartID){
    $stmt = $_db->prepare("INSERT INTO cart(userID) VALUES(?)");
    $stmt->execute([$user_id]);
    $cartID = $_db->lastInsertId();
}

// Actions
switch($action){
    case "add":
        $prodID = $payload['id'];
        $qty = max(1, (int)$payload['qty']);

        // Get cartID for current user
        $stmt = $_db->prepare("SELECT cartID FROM cart WHERE userID=?");
        $stmt->execute([$user_id]);
        $cartID = $stmt->fetchColumn();
        if (!$cartID) {
            // create cart if not exists
            $stmt = $_db->prepare("INSERT INTO cart(userID) VALUES(?)");
            $stmt->execute([$user_id]);
            $cartID = $_db->lastInsertId();
        }

        // check product exits and stock
        $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
        $stmt->execute([$prodID]);
        $stock = (int)$stmt->fetchColumn();
        if ($stock <= 0) exit(json_encode(['error'=>"Out of stock"]));

        // check if already in cart
        $stmt = $_db->prepare("SELECT qty FROM cart_items WHERE cartID=? AND prodID=?");
        $stmt->execute([$cartID, $prodID]);
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $newQty = min($current + $qty, $stock);
            $stmt = $_db->prepare("UPDATE cart_items SET qty=? WHERE cartID=? AND prodID=?");
            $stmt->execute([$newQty, $cartID, $prodID]);
        } else {
            $stmt = $_db->prepare("INSERT INTO cart_items(cartID, prodID, qty) VALUES(?,?,?)");
            $stmt->execute([$cartID, $prodID, min($qty, $stock)]);
        }
        break;

    case "update_qty":
        $prodID = $payload['id'];
        $qty = max(1, (int)$payload['qty']);

        // Get stock
        $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID=?");
        $stmt->execute([$prodID]);
        $stock = (int)$stmt->fetchColumn();
        $qty = min($qty, $stock);

        $stmt = $_db->prepare("UPDATE cart_items SET qty=? WHERE cartID=? AND prodID=?");
        $stmt->execute([$qty, $cartID, $prodID]);
        break;

    case "remove":
        $prodID = $payload['id'];
        $stmt = $_db->prepare("DELETE FROM cart_items WHERE cartID=? AND prodID=?");
        $stmt->execute([$cartID, $prodID]);
        break;

    case "clear_all":
        $stmt = $_db->prepare("DELETE FROM cart_items WHERE cartID=?");
        $stmt->execute([$cartID]);
        break;
}

// Return JSON
$cart = get_cart($user_id);
$totals = cartTotals($cart);