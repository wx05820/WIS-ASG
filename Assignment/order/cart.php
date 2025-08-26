<?php

session_start();

// Make sure cart exist
if(!isset($_SESSION['cart'])){
    $_SESSION['cart']=[];
}

function cartTotal(){
    $subtotal = 0.0;
    $selectedCount = 0;
    $itemCount = 0;

    foreach ($_SESSION['cart'] as $row){
        $itemCount += $row['qty'];
        if(!empty($row['selected'])){
            $subtotal += $row['product']['price'] * $row['qty'];
            $selectedCount += $row['qty'];
        }
    }

    $shipping = $subtotal > 0 ? 10.00 : 0.00; 
    $discount = 0.00; 
    $total = max($subtotal + $shipping - $discount, 0);  // to make sure no negative value
    return compact('subtotal', 'shipping', 'discount', 'total', 'selectedCount', 'itemCount');
}

$action = $_GET['action'] ?? '';
// Read JSON from frontend, turn into PHP array 
$payload = json_decode(file_get_contents("php://input"), true) ?? [];

switch($action){
    case "add":
        $id = (int) $payload['id'];
        $qty = max(1, (int)$payload['qty']);

        // Get product from DB
        $stmt = $conn->prepare("SELECT prodID, name, price, image1 FROM products WHERE prodID=?");
        $stmt->bind_param("s", $id);  // "s"->string, attaches actual values to the ?
        $stmt->execute();
        $result = $stmt->get_result();
        if($p = $result->fetch_assoc()){
            if(!isset($_SESSION['cart'][$id])){
                $_SESSION['cart'][$id]=[
                    'id' => $p['prodID'],
                    'title' => $p['name'],
                    'price' => $p['price'],
                    'qty' => $p['qty'],
                    'img' => !empty($p['image1']) ? 'data:image/jpeg;base64,'.base64_encode($p['image1']) : ''
                ];
            }else{
                $_SESSION['cart'][$id]['qty'] += $qty;
            }
        }break;

    case "update_qty":
        $id = (int)$payload['id'];
        $qty = max(1, (int)$payload['qty']);

        if(isset($_SESSION['cart'][$id])){
            $_SESSION['cart'][$id]['qty']=$qty;
        }break;

    case "toggle":
        $id = (int)$payload['id'];
        $selected = (bool)$payload['selected'];

        if(isset($_SESSION['cart'][$id])){
            $_SESSION['cart'][$id]['selected']=$selected;
        }break;

    case "remove":
        $id = (int)$payload['id'];
        unset($_SESSION['cart'][$id]);
        break;

    case "clear_sel":
        foreach ($_SESSION['cart'] as $id => $row) {
            if(!empty($row['selected'])){
                unset($_SESSION['cart'][$id]);
            }
        }break;

    case "select_all":
        $selected = (bool)$payload['selected'];
        foreach($_SESSION['cart'] as &$row ){
            $row['selected'] = $selected;
        }break;
}

echo json_encode([
    'cart'=>array_values($_SESSION['cart'] ?? []),
    'totals'=>cartTotal()]
);
?>