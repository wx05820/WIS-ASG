<?php
require_once '../_base.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();
checkUserStatus(); // Check if user is banned

// Check if this is an AJAX request
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

try {
    // Validate form data
    if (empty($_POST['selected_address']) || empty($_POST['shipping_method']) || empty($_POST['payment_method'])) {
        throw new Exception('Please fill in all required fields');
    }

    // Check idempotency token to prevent double submission
    $idem_key = $_POST['idem_key'] ?? '';
    if (empty($idem_key) || !isset($_SESSION['order_idem_tokens'][$idem_key]) || $_SESSION['order_idem_tokens'][$idem_key] !== 'new') {
        throw new Exception('Invalid or expired order token. Please refresh and try again.');
    }

    // Mark token as used
    $_SESSION['order_idem_tokens'][$idem_key] = 'used';

    // Get form data
    $address_id = $_POST['selected_address'];
    $shipping_method = $_POST['shipping_method'];
    $payment_method = $_POST['payment_method'];
    $voucher_id = $_POST['voucher_id'] ?? null;
    $voucher_code = $_POST['voucher_code'] ?? null;
    $discount_amount = (float)($_POST['discount_amount'] ?? 0);

    // Determine if this is buy now or cart checkout
    $is_buy_now = isset($_POST['buy_now']) && $_POST['buy_now'] === '1';

    // Get items to order
    $order_items = [];
    if ($is_buy_now) {
        // Buy now checkout
        $prod_id = $_POST['prodID'];
        $qty = (int)$_POST['qty'];
        
        // Get product details
        $stmt = $_db->prepare("SELECT prodID, name, price, qty as stock, color FROM product WHERE prodID = ?");
        $stmt->execute([$prod_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product || $product['stock'] < $qty) {
            throw new Exception('Product not available or insufficient stock');
        }
        
        $order_items[] = [
            'prodID' => $prod_id,
            'qty' => $qty,
            'price' => $product['price'],
            'name' => $product['name'],
            'color' => $product['color'] ?? ''
        ];
    } else {
        // Cart checkout
        $selected_items = $_POST['selected_items'] ?? [];
        if (empty($selected_items)) {
            throw new Exception('No items selected for checkout');
        }

        // Get cart items
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $stmt = $_db->prepare("
            SELECT ci.prodID, ci.qty, p.name, p.price, p.qty as stock, p.color
            FROM cart_items ci
            JOIN cart c ON ci.cartID = c.cartID
            JOIN product p ON ci.prodID = p.prodID
            WHERE c.userID = ? AND ci.prodID IN ($placeholders)
        ");
        $params = array_merge([$user_id], $selected_items);
        $stmt->execute($params);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Validate stock
        foreach ($cart_items as $item) {
            if ($item['stock'] < $item['qty']) {
                throw new Exception("Insufficient stock for {$item['name']}");
            }
            $order_items[] = $item;
        }
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }

    $shipping_fee = ($shipping_method === 'express') ? 15.00 : 8.00;
    $total = $subtotal + $shipping_fee - $discount_amount;

    // Start database transaction
    $_db->beginTransaction();

    // Get address details
    $stmt = $_db->prepare("
        SELECT recipient_name, phoneNo, unitNo, address_line_1, address_line_2, city, postcode, state
        FROM user_address 
        WHERE ID = ? AND userID = ?
    ");
    $stmt->execute([$address_id, $user_id]);
    $address_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$address_details) {
        throw new Exception('Invalid shipping address selected');
    }

    // Insert payment
    $stmt = $_db->prepare("INSERT INTO payment (payMethod, payStatus, payDate, amount) VALUES (?, ?, NOW(), ?)");
    $payment_result = $stmt->execute([
        $payment_method,
        'Success',
        $total
    ]);
    
    if (!$payment_result) {
        $error = $stmt->errorInfo();
        throw new Exception('Failed to create payment record: ' . $error[2]);
    }
    
    // Get the auto-generated payID
    $stmt = $_db->prepare("SELECT payID FROM payment ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $payment_check = $stmt->fetch(PDO::FETCH_ASSOC);

    $payID = $payment_check['payID'];
    
    if (!$payID) {
        throw new Exception('Failed to retrieve auto-generated payment ID');
    }

    // Create order 
    $stmt = $_db->prepare("
        INSERT INTO `order` (orderDate, userID, status, shipping_method, subtotal, shipping_fee, discount, total, payID, addressID, 
                        recipient_name, phoneNo, unitNo, address_line_1, address_line_2, city, postcode, state)
        VALUES (NOW(), ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $order_result = $stmt->execute([
        $user_id, $shipping_method, $subtotal, $shipping_fee, 
        $discount_amount, $total, $payID, $address_id,
        $address_details['recipient_name'], $address_details['phoneNo'], $address_details['unitNo'],
        $address_details['address_line_1'], $address_details['address_line_2'], 
        $address_details['city'], $address_details['postcode'], $address_details['state']
    ]);

    if (!$order_result) {
        $error = $stmt->errorInfo();
        throw new Exception('Failed to create order record: ' . $error[2]);
    }

    // Get the auto-generated orderID
    $stmt = $_db->prepare("SELECT orderID FROM `order` WHERE payID = ? ORDER BY orderDate DESC LIMIT 1");
    $stmt->execute([$payID]);
    $order_id = $stmt->fetchColumn();

    if (!$order_id) {
        throw new Exception('Failed to retrieve auto-generated order ID');
    }
    
    // Add order items
    $stmt = $_db->prepare("
        INSERT INTO order_items (orderID, prodID, qty, price, product_name, product_color) 
        VALUES (?, ?, ?, ?, ?, ?)
    "); 

    foreach ($order_items as $item) {
        $stmt->execute([
            $order_id, $item['prodID'], $item['qty'], 
            $item['price'], $item['name'], $item['color'] ?? ''
        ]);
    }

    // Update product stock
    $stmt = $_db->prepare("UPDATE product SET qty = qty - ? WHERE prodID = ?");
    foreach ($order_items as $item) {
        $stmt->execute([$item['qty'], $item['prodID']]);
    }

    // Remove items from cart if cart checkout
    if (!$is_buy_now) {
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $stmt = $_db->prepare("
            DELETE ci FROM cart_items ci 
            JOIN cart c ON ci.cartID = c.cartID 
            WHERE c.userID = ? AND ci.prodID IN ($placeholders)
        ");
        $params = array_merge([$user_id], $selected_items);
        $stmt->execute($params);
    }

    // Mark voucher as used if applicable
    if ($voucher_id) {
        $stmt = $_db->prepare("
            INSERT INTO voucher_user (voucher_id, user_id, order_id, used_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$voucher_id, $user_id, $order_id]);
    }

    // Commit transaction
    $_db->commit();

    // Clear session data
    unset($_SESSION['checkout_items']);
    unset($_SESSION['buyNow']);
    unset($_SESSION['buyNow_qty']);

    // Handle response based on request type
    if ($is_ajax) {
        // AJAX response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully!',
            'order_id' => $order_id,
            'redirect_url' => "success.php?order_id=" . urlencode($order_id)
        ]);
        exit;
    } else {
        // Normal form submission - redirect
        $_SESSION['success'] = 'Order placed successfully!';
        header("Location: success.php?order_id=" . urlencode($order_id));
        exit;
    }

} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($_db->inTransaction()) {
        $_db->rollback();
    }

    // Handle error response
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    } else {
        $_SESSION['error'] = $e->getMessage();
        header("Location: checkout.php");
        exit;
    }
}
?>