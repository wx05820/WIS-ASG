<?php
require_once '../_base.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    redirect('/order/checkout.php');
}

$orderData = $_POST;

//

// Idempotency guard using a per-checkout token stored in session
$idem_key = $_POST['idem_key'] ?? null;
if (!isset($_SESSION['order_idem_tokens']) || !is_array($_SESSION['order_idem_tokens'])) {
    $_SESSION['order_idem_tokens'] = [];
}

if (!$idem_key || !array_key_exists($idem_key, $_SESSION['order_idem_tokens'])) {
    // Invalid or missing token -> prevent processing and send user back
    $_SESSION['error'] = "Your session expired. Please try checkout again.";
    redirect('/order/checkout.php');
}

if ($_SESSION['order_idem_tokens'][$idem_key] === 'used') {
    // Duplicate submission detected, redirect to the most recent order (if any)
    $stmt = $_db->prepare("SELECT orderID FROM `order` WHERE userID = ? ORDER BY orderDate DESC, orderID DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $existing_order_id = $stmt->fetchColumn();
    if ($existing_order_id) {
        redirect("/order/success.php?order_id=" . $existing_order_id);
    }
    $_SESSION['error'] = "This order was already processed.";
    redirect('/order/checkout.php');
}

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

// Get selected items from form (prodID are strings like P000123)
// Keep as strings and trim; optionally validate allowed format
$selected_items = array_values(array_filter(array_map('trim', $selected_items), function($id) {
    return $id !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $id);
}));

if (empty($selected_items)) {
    $_SESSION['error'] = "Please select items to checkout";
    redirect('/order/cart_page.php');
}

// Store selected items in session for checkout page
$_SESSION['checkout_items'] = $selected_items;

$address_id = $_POST['address_id'] ?? ($_POST['selected_address'] ?? null);
$shipping_method = $_POST['shipping_method'] ?? null;
$pay_method = $_POST['payment_method'] ?? null;

//Check if user has addresses and validate address selection
$stmt = $_db->prepare("SELECT COUNT(*) FROM user_address WHERE userID = ?");
$stmt->execute([$user_id]);
$has_addresses = $stmt->fetchColumn() > 0;

if ($has_addresses && (empty($address_id))) {
    // Fallback to user's default address (or first address) if none posted
    $stmt = $_db->prepare("SELECT ID FROM user_address WHERE userID = ? ORDER BY isDefault DESC, created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $address_id = $stmt->fetchColumn();
    if (!$address_id) {
        $_SESSION['error'] = "Please select a delivery address";
        redirect('/order/checkout.php');
    }
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

    // Determine if this is a Buy Now flow
    $is_buy_now = (!empty($orderData['buy_now'])) || (!empty($_SESSION['buyNow']));

    if ($is_buy_now) {
        // Build cart_items from the product table directly (not from cart)
        $buy_now_prod_id = $orderData['prodID'] ?? ($selected_items[0] ?? null);
        $buy_now_qty = isset($orderData['qty']) ? (int)$orderData['qty'] : (int)($_SESSION['buyNow_qty'] ?? 1);

        if (!$buy_now_prod_id) {
            $_SESSION['error'] = "Invalid Buy Now request (missing product)";
            redirect('/order/checkout.php');
        }

        $stmt = $_db->prepare("SELECT prodID, name, price, color, qty FROM product WHERE prodID = ?");
        $stmt->execute([$buy_now_prod_id]);
        $product_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product_row) {
            $_SESSION['error'] = "Selected product not found";
            redirect('/order/checkout.php');
        }

        $cart_items = [[
            'prodID' => $product_row['prodID'],
            'qty' => max(1, $buy_now_qty),
            'name' => $product_row['name'],
            'price' => (float)$product_row['price'],
            'color' => $product_row['color'] ?? ''
        ]];
    } else {
        // Convert array to comma-separated string for SQL IN clause
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';

        $sql = "SELECT ci.prodID, ci.qty, p.name, p.price, p.color 
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

    // Map payment method values to database enum values
    $payment_method_map = [
        'cod' => 'COD',
        'card' => 'Credit/Debit Card',
        'online_banking' => 'DuitNow',
        'ewallet' => 'TNG'
    ];
    
    $db_pay_method = $payment_method_map[$pay_method] ?? 'COD';
    
    // Set order status based on payment method
    $status = ($pay_method === 'cod') ? 'Pending' : 'Pending'; // All orders start as pending for now
    
    // Set payment status based on payment method
    $pay_status_map = [
        'cod' => 'Pending',
        'card' => 'Success', // Assuming card payments are successful for demo
        'online_banking' => 'Success',
        'ewallet' => 'Success'
    ];
    
    $payStatus = $pay_status_map[$pay_method] ?? 'Pending';

    //Insert payment
    $stmt = $_db->prepare("INSERT INTO payment (payMethod, payStatus, payDate, amount) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([
        $db_pay_method,
        $payStatus,
        $total
    ]);

    // Get the last inserted payment ID
    $payID = (int)$_db->lastInsertId();

    // Get shipping address details for the order
    $address_details = null;
    if ($has_addresses && $address_id) {
        $stmt = $_db->prepare("
            SELECT recipient_name, phoneNo, unitNo, address_line_1, address_line_2, city, postcode, state
            FROM user_address 
            WHERE ID = ? AND userID = ?
        ");
        $stmt->execute([$address_id, $user_id]);
        $address_details = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$address_details) {
        $_SESSION['error'] = "Invalid shipping address selected";
        redirect('/order/checkout.php');
    }

    // Ensure phone number fits in char(12) field
    $phone_no = substr($address_details['phoneNo'], 0, 12);

    // Insert order record with individual address fields
    $stmt = $_db->prepare("INSERT INTO `order` 
        (orderDate, userID, status, shipping_method, subtotal, shipping_fee, discount, total, payID, addressID, 
         recipient_name, phoneNo, unitNo, address_line_1, address_line_2, city, postcode, state)
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $status,
        $shipping_method,
        $subtotal,
        $shipping_fee,
        $discount,
        $total,
        $payID,
        (int)$address_id,
        $address_details['recipient_name'],
        $phone_no,
        $address_details['unitNo'],
        $address_details['address_line_1'],
        $address_details['address_line_2'],
        $address_details['city'],
        $address_details['postcode'],
        $address_details['state']
    ]);
    
    // Get the generated order ID (since it's generated by trigger)
    $stmt = $_db->prepare("SELECT orderID FROM `order` WHERE userID = ? ORDER BY orderDate DESC, orderID DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $order_id = $stmt->fetchColumn();

    // Insert order items with product details
    $stmt = $_db->prepare("INSERT INTO order_items (orderID, prodID, qty, price, product_name, product_color) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $stmt->execute([
            $order_id,
            $item['prodID'],
            $item['qty'],
            $item['price'],
            $item['name'],
            $item['color'] ?? ''
        ]);

        // Update product stock
        $stmt_stock = $_db->prepare("UPDATE product SET qty = qty - ? WHERE prodID = ?");
        $stmt_stock->execute([$item['qty'], $item['prodID']]);
    }

    // Remove only the selected items from cart (skip if Buy Now)
    if (!$is_buy_now) {
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $sql = "DELETE FROM cart_items WHERE cartID = (SELECT cartID FROM cart WHERE userID = ?) AND prodID IN ($placeholders)";
        $params = array_merge([$user_id], $selected_items);
        $stmt = $_db->prepare($sql);
        $stmt->execute($params);
    }

    // Clear checkout items from session
    unset($_SESSION['checkout_items']);
    unset($_SESSION['buyNow']);
    unset($_SESSION['buyNow_qty']);

    // Create initial delivery status record
    $stmt = $_db->prepare("INSERT INTO deliverystatus (orderID, status, courier, notes, current_location, updated_at) VALUES (?, 'Order Picked Up', 'System', 'Order has been placed and is being prepared for shipment', 'Warehouse', NOW())");
    $stmt->execute([$order_id]);

    $_db->commit();

    //
    
    // Mark idempotency token as used to block repeat submissions
    $_SESSION['order_idem_tokens'][$idem_key] = 'used';

    $_SESSION['success'] = "Order placed successfully! Order ID: #" . $order_id;
    redirect("/order/success.php?order_id=" . $order_id);

} catch (Exception $e) {
    if ($_db->inTransaction()) $_db->rollBack();
    $_SESSION['error'] = "Failed to place order: " . $e->getMessage();
    redirect('/order/checkout.php');
}

// Send order confirmation email
function sendOrderConfirmationEmail($user_id, $order_id) {
    
}
?>