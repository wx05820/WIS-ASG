<?php
require_once '../_base.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    redirect('/order/checkout.php');
}

$orderData = $_POST;

$selected_items = [];
if (isset($orderData['selected_items']) && is_array($orderData['selected_items'])) {
    $selected_items = $orderData['selected_items'];
} 
elseif (isset($orderData['selected_items']) && is_string($orderData['selected_items'])) {
    $selected_items = explode(',', $orderData['selected_items']);
} 
elseif (isset($_SESSION['checkout_items'])) {
    $selected_items = $_SESSION['checkout_items'];
}

// Get selected items from form
$selected_items = array_filter(array_map('intval', $selected_items));

if (empty($selected_items)) {
    $_SESSION['error'] = "Please select items to checkout";
    redirect('/order/cart.php');
}

// Store selected items in session for checkout page
$_SESSION['checkout_items'] = $selected_items;

$address_id = $_POST['address_id'] ?? null;
$shipping_method = $_POST['shipping_method'] ?? null;
$pay_method = $_POST['payment_method'] ?? null;

//Check if user has addresses and validate address selection
$stmt = $_db->prepare("SELECT COUNT(*) FROM user_address WHERE userID = ?");
$stmt->execute([$user_id]);
$has_addresses = $stmt->fetchColumn() > 0;

if ($has_addresses && (empty($address_id) || !is_numeric($address_id))) {
    $_SESSION['error'] = "Please select a delivery address";
    redirect('/order/checkout.php');
}

// If user has addresses, validate the selected address belongs to them
if ($has_addresses) {
    $stmt = $_db->prepare("SELECT ID FROM user_address WHERE userID = ? AND ID = ?");
    $stmt->execute([$user_id, $address_id]);
    $valid_address = $stmt->fetchColumn();
    
    if (!$valid_address) {
        $_SESSION['error'] = "Invalid delivery address selected";
        redirect('/order/checkout.php');
    }
}

if (!$shipping_method || !$pay_method) {
    $_SESSION['error'] = "Please select shipping method and payment method";
    redirect('/order/checkout.php');
}

try {
    $_db->beginTransaction();

    // Convert array to comma-separated string for SQL IN clause
    $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';

    $sql = "SELECT ci.prodID, ci.qty, p.name, p.price 
            FROM cart_items ci
            JOIN cart c ON ci.cartID = c.cartID
            JOIN product p ON ci.prodID = p.prodID
            WHERE c.userID = ? AND ci.prodID IN ($placeholders)";

    $params = array_merge([$user_id], $selected_items);
    $stmt = $_db->prepare($sql);
    $stmt->execute($params);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        $_SESSION['error'] = "Your cart is empty";
        redirect('/order/checkout.php');
    }

    // Verify stock availability for selected items
    foreach ($cart_items as $item) {
        $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID = ?");
        $stmt->execute([$item['prodID']]);
        $available_stock = $stmt->fetchColumn();
        
        if ($available_stock < $item['qty']) {
            $_SESSION['error'] = "Insufficient stock for " . htmlspecialchars($item['name']) . ". Available: " . $available_stock;
            redirect('/order/checkout.php');
        }
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }

    $shipping_fee = (float)($orderData['shipping_fee'] ?? ($shipping_method === 'express' ? 15.00 : 8.00));
    $discount = (float)($orderData['discount'] ?? 0);
    $total = $subtotal + $shipping_fee - $discount;

    // Set order status based on payment method
    $status = ($pay_method === 'cod') ? 'pending' : 'confirmed';

    //Insert payment
    $payStatus = ($pay_method === 'cod') ? 'pending' : 'completed';
    $stmt = $_db->prepare("INSERT INTO payment (payMethod, payStatus, payDate, amount) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([
        $pay_method,
        $payStatus,
        $total
    ]);

    // Get the last inserted payment ID
    $payID = $_db->lastInsertId();

    if ($has_addresses && $address_id) {
        // Include address_id if user has addresses
        $stmt = $_db->prepare("INSERT INTO `order` 
            (orderDate, userID, status, shipping_method, subtotal, shipping_fee, discount, total)/*, recipient_name, phoneNo, unitNo, address_line_1, address_line_2, city)*/
            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $status,
            $shipping_method,
            $subtotal,
            $shipping_fee,
            $discount,
            $total,
        ]);
    }
    
    $order_id = $_db->lastInsertId();

    // Insert order items
    $stmt = $_db->prepare("INSERT INTO order_items (orderID, prodID, qty, price) VALUES (?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $stmt->execute([
            $order_id,
            $item['prodID'],
            $item['qty'],
            $item['price']
        ]);

        // Update product stock
        $stmt_stock = $_db->prepare("UPDATE product SET qty = qty - ? WHERE prodID = ?");
        $stmt_stock->execute([$item['qty'], $item['prodID']]);
    }

    // Remove only the selected items from cart
    $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
    $sql = "DELETE FROM cart_items WHERE cartID = (SELECT cartID FROM cart WHERE userID = ?) AND prodID IN ($placeholders)";
    $params = array_merge([$user_id], $selected_items);
    $stmt = $_db->prepare($sql);
    $stmt->execute($params);

    // Clear checkout items from session
    unset($_SESSION['checkout_items']);

    $_db->commit();

    $_SESSION['success'] = "Order placed successfully! Order ID: #" . $order_id;
    redirect("/order/success.php?order_id=" . $order_id);

} catch (Exception $e) {
    if ($_db->inTransaction()) $_db->rollBack();
    error_log("Order placement error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to place order. Please try again.";
    redirect('/order/checkout.php');
}

// Send order confirmation email
function sendOrderConfirmationEmail($user_id, $order_id) {
    
}
?>