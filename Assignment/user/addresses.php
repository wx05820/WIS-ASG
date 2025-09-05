<?php
require_once '../_base.php';

checkLogin();
$user_id = $_SESSION['user_id'];

// Helpers for Malaysian phone formatting/validation
function formatMsPhone($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if ($digits === '') return '';
    if (strpos($digits, '60') === 0) {
        $rest = substr($digits, 2);
    } else if ($digits[0] === '0') {
        $rest = substr($digits, 1);
    } else {
        $rest = $digits;
    }
    return '+60' . $rest;
}

// Create/Update/Delete address
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'update') {
        $recipient = trim($_POST['recipient_name'] ?? '');
        $phone = trim($_POST['phoneNo'] ?? '');
        $unitNo = trim($_POST['unitNo'] ?? '');
        $line1 = trim($_POST['address_line_1'] ?? '');
        $line2 = trim($_POST['address_line_2'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $type = in_array($_POST['type'] ?? 'Residential', ['Residential','Commercial']) ? $_POST['type'] : 'Residential';
        $isDefault = isset($_POST['isDefault']) ? 1 : 0;

        // Normalize phone for DB: keep digits only (user_address.phoneNo is int)
        $phone = preg_replace('/\D+/', '', (string)$phone);
        $phone = substr($phone, 0, 12);

        // Basic validation
        if ($recipient === '' || $phone === '' || $line1 === '' || $postcode === '' || $city === '' || $state === '') {
            $_SESSION['error'] = 'Please fill in all required fields.';
            redirect('/user/addresses.php');
        }
        // Accept either +60 format or digits-only; we store digits-only
        if (!preg_match('/^\d{9,12}$/', $phone)) {
            $_SESSION['error'] = 'Please enter a valid Malaysian phone number (9-12 digits, e.g. 60123456789).';
            redirect('/user/addresses.php');
        }
        if (!preg_match('/^\d{5}$/', $postcode)) {
            $_SESSION['error'] = 'Postcode must be 5 digits.';
            redirect('/user/addresses.php');
        }
        if (strlen($recipient) > 50) {
            $_SESSION['error'] = 'Recipient name is too long (max 50).';
            redirect('/user/addresses.php');
        }
        if (strlen($line1) > 255 || strlen($line2) > 255) {
            $_SESSION['error'] = 'Address lines are too long (max 255).';
            redirect('/user/addresses.php');
        }

        if ($isDefault) {
            $stm = $_db->prepare('UPDATE user_address SET isDefault=0 WHERE userID=?');
            $stm->execute([$user_id]);
        }

        if ($action === 'add') {
            // Generate new address ID like A00001
            $res = $_db->query("SELECT MAX(CAST(SUBSTRING(ID, 2) AS UNSIGNED)) FROM user_address");
            $maxNum = (int)$res->fetchColumn();
            $nextNum = $maxNum + 1;
            $newId = 'A' . str_pad((string)$nextNum, 5, '0', STR_PAD_LEFT);

            $stm = $_db->prepare('INSERT INTO user_address (ID, recipient_name, phoneNo, unitNo, address_line_1, address_line_2, postcode, city, state, isDefault, userID, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stm->execute([$newId, $recipient, $phone, $unitNo, $line1, $line2, $postcode, $city, $state, $isDefault, $user_id, $type]);
            $_SESSION['success'] = 'Address added.';
            
            // If user came from checkout, redirect back to checkout
            if (isset($_GET['from']) && $_GET['from'] === 'checkout') {
                redirect('/order/checkout.php');
            }
        } else {
            $id = $_POST['ID'] ?? '';
            $stm = $_db->prepare('UPDATE user_address SET recipient_name=?, phoneNo=?, unitNo=?, address_line_1=?, address_line_2=?, postcode=?, city=?, state=?, isDefault=?, type=? WHERE ID=? AND userID=?');
            $stm->execute([$recipient, $phone, $unitNo, $line1, $line2, $postcode, $city, $state, $isDefault, $type, $id, $user_id]);
            $_SESSION['success'] = 'Address updated.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['ID'] ?? '';
        $stm = $_db->prepare('DELETE FROM user_address WHERE ID=? AND userID=?');
        $stm->execute([$id, $user_id]);
        $_SESSION['success'] = 'Address removed.';
    }
    redirect('/user/addresses.php');
}

// Fetch addresses
$stm = $_db->prepare('SELECT * FROM user_address WHERE userID=? ORDER BY isDefault DESC');
$stm->execute([$user_id]);
$addresses = $stm->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Addresses';
include '../header.php';

// Check if user came from checkout
$from_checkout = isset($_GET['from']) && $_GET['from'] === 'checkout';
?>

<link rel="stylesheet" href="../css/index.css">
<main class="container" style="padding:20px 0;">
  <h1>My Addresses</h1>
  
  <?php if ($from_checkout): ?>
    <div class="alert alert-info" style="background: #e3f2fd; border: 1px solid #2196f3; color: #1976d2; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
      <strong>📦 Checkout Notice:</strong> You need to add a delivery address to complete your order. 
      After adding an address, you can return to checkout.
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <section style="margin:20px 0;">
    <h3>Add New Address</h3>
    <form id="addAddressForm" method="post">
      <input type="hidden" name="action" value="add">
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:700px;">
        <input class="form-input" name="recipient_name" placeholder="Recipient Name" required>
        <input class="form-input" name="phoneNo" placeholder="Phone (e.g. 0123456789)" required>
        <input class="form-input" name="unitNo" placeholder="Unit/House No (optional)">
        <select class="form-input" name="type"><option>Residential</option><option>Commercial</option></select>
        <input class="form-input" name="address_line_1" placeholder="Address Line 1" required style="grid-column:1/3;">
        <input class="form-input" name="address_line_2" placeholder="Address Line 2 (optional)" style="grid-column:1/3;">
        <input class="form-input" name="postcode" placeholder="Postcode" required>
        <input class="form-input" name="city" placeholder="City" required>
        <select class="form-input" name="state" required>
          <?php
            $states = ['Johor','Kedah','Kelantan','Malacca','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'];
            foreach ($states as $st) echo '<option value="'.htmlspecialchars($st).'">'.htmlspecialchars($st).'</option>';
          ?>
        </select>
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="isDefault" value="1"> Set as default
        </label>
      </div>
      <div style="margin-top:12px;">
        <button class="btn btn-primary" type="submit">Add Address</button>
        <?php if ($from_checkout): ?>
          <a href="/order/checkout.php" class="btn btn-secondary" style="margin-left: 10px;">Return to Checkout</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section>
    <h3>Saved Addresses</h3>
    <?php if (empty($addresses)): ?>
      <p>No addresses yet.</p>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
        <?php foreach ($addresses as $addr): ?>
          <div class="card" style="padding:12px;border:1px solid #eee;border-radius:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <strong><?php echo htmlspecialchars($addr['recipient_name']); ?></strong>
              <?php if ($addr['isDefault']): ?><span class="badge">Default</span><?php endif; ?>
            </div>
            <div style="margin:6px 0;">
              <div><?php echo htmlspecialchars(formatMsPhone($addr['phoneNo'])); ?></div>
              <div><?php echo htmlspecialchars($addr['unitNo']); ?></div>
              <div><?php echo htmlspecialchars($addr['address_line_1']); ?></div>
              <?php if (!empty($addr['address_line_2'])): ?><div><?php echo htmlspecialchars($addr['address_line_2']); ?></div><?php endif; ?>
              <div><?php echo htmlspecialchars($addr['postcode'].' '.$addr['city'].', '.$addr['state']); ?></div>
              <div>Type: <?php echo htmlspecialchars($addr['type']); ?></div>
            </div>
            <div style="display:flex;gap:8px;">
              <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="ID" value="<?php echo htmlspecialchars($addr['ID']); ?>">
                <button class="btn btn-secondary" type="submit" onclick="return confirm('Delete this address?')">Delete</button>
              </form>
              <?php if (!$addr['isDefault']): ?>
                <form method="post">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="ID" value="<?php echo htmlspecialchars($addr['ID']); ?>">
                  <input type="hidden" name="recipient_name" value="<?php echo htmlspecialchars($addr['recipient_name']); ?>">
                  <input type="hidden" name="phoneNo" value="<?php echo htmlspecialchars($addr['phoneNo']); ?>">
                  <input type="hidden" name="unitNo" value="<?php echo htmlspecialchars($addr['unitNo']); ?>">
                  <input type="hidden" name="address_line_1" value="<?php echo htmlspecialchars($addr['address_line_1']); ?>">
                  <input type="hidden" name="address_line_2" value="<?php echo htmlspecialchars($addr['address_line_2']); ?>">
                  <input type="hidden" name="postcode" value="<?php echo htmlspecialchars($addr['postcode']); ?>">
                  <input type="hidden" name="city" value="<?php echo htmlspecialchars($addr['city']); ?>">
                  <input type="hidden" name="state" value="<?php echo htmlspecialchars($addr['state']); ?>">
                  <input type="hidden" name="type" value="<?php echo htmlspecialchars($addr['type']); ?>">
                  <input type="hidden" name="isDefault" value="1">
                  <button class="btn btn-primary" type="submit">Set Default</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php include '../footer.php';


