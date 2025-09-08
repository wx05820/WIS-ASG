<?php
require_once '../_base.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$userID = $_SESSION['user_id'];
$orderID = $_GET['id'] ?? '';

if (empty($orderID)) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect('tracking.php');
}

// Get order details
try {
    $orderQuery = "SELECT o.*, u.username, u.email 
                   FROM `order` o 
                   JOIN user u ON o.userID = u.userID 
                   WHERE o.orderID = ? AND o.userID = ?";
    $orderStmt = $_db->prepare($orderQuery);
    $orderStmt->execute([$orderID, $userID]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        redirect('tracking.php');
    }
    
    // Check if order is pending
    if ($order['status'] !== 'Pending') {
        $_SESSION['error'] = 'You can only change the address for pending orders.';
        redirect('tracking_details.php?id=' . $orderID);
    }
    
} catch (Exception $e) {
    error_log("Error fetching order: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading order details.';
    redirect('tracking.php');
}

// Get user's addresses
$userAddresses = [];
try {
    $addressQuery = "SELECT ID, recipient_name, phoneNo, unitNo, address_line_1, address_line_2, city, state, postcode, isDefault 
                     FROM user_address 
                     WHERE userID = ? 
                     ORDER BY isDefault DESC";
    $addressStmt = $_db->prepare($addressQuery);
    $addressStmt->execute([$userID]);
    $userAddresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user addresses: " . $e->getMessage());
}

// Handle form submission
if (is_post()) {
    $addressID = $_POST['addressID'] ?? '';
    
    if (empty($addressID)) {
        $_SESSION['error'] = 'Please select an address.';
    } else {
        try {
            // Get selected address details
            $selectedAddressQuery = "SELECT * FROM user_address WHERE ID = ? AND userID = ?";
            $selectedAddressStmt = $_db->prepare($selectedAddressQuery);
            $selectedAddressStmt->execute([$addressID, $userID]);
            $selectedAddress = $selectedAddressStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$selectedAddress) {
                $_SESSION['error'] = 'Selected address not found.';
            } else {
                // Update order with new address
                $updateQuery = "UPDATE `order` SET 
                               recipient_name = ?, 
                               phoneNo = ?, 
                               unitNo = ?, 
                               address_line_1 = ?, 
                               address_line_2 = ?, 
                               city = ?, 
                               state = ?, 
                               postcode = ? 
                               WHERE orderID = ? AND userID = ?";
                $updateStmt = $_db->prepare($updateQuery);
                $updateStmt->execute([
                    $selectedAddress['recipient_name'],
                    $selectedAddress['phoneNo'],
                    $selectedAddress['unitNo'],
                    $selectedAddress['address_line_1'],
                    $selectedAddress['address_line_2'],
                    $selectedAddress['city'],
                    $selectedAddress['state'],
                    $selectedAddress['postcode'],
                    $orderID,
                    $userID
                ]);
                
                $_SESSION['success'] = 'Delivery address updated successfully!';
                redirect('tracking_details.php?id=' . $orderID);
            }
        } catch (Exception $e) {
            error_log("Error updating address: " . $e->getMessage());
            $_SESSION['error'] = 'Error updating address. Please try again.';
        }
    }
}

function formatAddress($order) {
    $address = [];
    if (!empty($order['unitNo'])) $address[] = $order['unitNo'];
    if (!empty($order['address_line_1'])) $address[] = $order['address_line_1'];
    if (!empty($order['address_line_2'])) $address[] = $order['address_line_2'];
    if (!empty($order['city'])) $address[] = $order['city'];
    if (!empty($order['postcode'])) $address[] = $order['postcode'];
    if (!empty($order['state'])) $address[] = $order['state'];
    
    return implode('<br>', $address);
}

include '../header.php';
?>

<link rel="stylesheet" href="../css/change_address.css">
<link rel="stylesheet" href="../css/order_details.css">

<body class="change-address-page" data-user-id="<?= $userID ?>">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">

                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2><i class="fas fa-map-marker-alt"></i> Change Delivery Address</h2>
                            <p class="text-muted order-id">Order ID: <?= htmlspecialchars($order['orderID']) ?></p>
                        </div>
                        <a href="tracking_details.php?id=<?= $orderID ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Order Details
                        </a>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="row">
                    <!-- Current Address -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-home"></i> Current Delivery Address
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="address-info">
                                    <h6><i class="fas fa-user"></i> <?= htmlspecialchars($order['recipient_name']) ?></h6>
                                    <p class="address-text"><?= formatAddress($order) ?></p>
                                    <?php if (!empty($order['phoneNo'])): ?>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($order['phoneNo']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($order['notes'])): ?>
                                        <div class="order-notes">
                                            <strong><i class="fas fa-sticky-note"></i> Notes:</strong> <?= htmlspecialchars($order['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Change Address Form -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit"></i> Select New Address
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($userAddresses)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>No addresses found!</strong> You need to add an address first before you can change the delivery address.
                                    </div>
                                    <div class="text-center">
                                        <a href="../user/addresses.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add New Address
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" id="changeAddressForm">
                                        <div class="mb-3">
                                            <label for="addressID" class="form-label">Select Delivery Address *</label>
                                            <select class="form-select" id="addressID" name="addressID" required>
                                                <option value="">Choose an address...</option>
                                                <?php foreach ($userAddresses as $address): ?>
                                                    <option value="<?= htmlspecialchars($address['ID']) ?>" 
                                                            <?= $address['isDefault'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($address['recipient_name']) ?> - 
                                                        <?= htmlspecialchars($address['address_line_1']) ?><?= $address['address_line_2'] ? ', ' . htmlspecialchars($address['address_line_2']) : '' ?>, 
                                                        <?= htmlspecialchars($address['postcode']) ?> <?= htmlspecialchars($address['city']) ?>, 
                                                        <?= htmlspecialchars($address['state']) ?>
                                                        <?= $address['isDefault'] ? ' (Default)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div id="selectedAddressPreview" class="card mt-3" style="display: none;">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-map-marker-alt"></i> Selected Address Preview</h6>
                                            </div>
                                            <div class="card-body">
                                                <div id="addressPreviewContent"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Note:</strong> You can only change the delivery address for orders with "Pending" status. 
                                            Once the order is confirmed, address changes may not be possible.
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <a href="../user/addresses.php" class="btn btn-outline-primary">
                                                <i class="fas fa-plus"></i> Manage Addresses
                                            </a>
                                            <div>
                                                <a href="tracking_details.php?id=<?= $orderID ?>" class="btn btn-secondary me-2">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="fas fa-save"></i> Update Address
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Address preview functionality
    document.addEventListener('DOMContentLoaded', function() {
        const addressSelect = document.getElementById('addressID');
        const previewDiv = document.getElementById('selectedAddressPreview');
        const previewContent = document.getElementById('addressPreviewContent');
        const form = document.getElementById('changeAddressForm');
        
        if (addressSelect && previewDiv && previewContent) {
            addressSelect.addEventListener('change', function() {
                if (this.value) {
                    const selectedOption = this.options[this.selectedIndex];
                    const addressText = selectedOption.textContent;
                    
                    // Show preview
                    previewContent.innerHTML = `
                        <div class="address-preview">
                            <strong>${addressText}</strong>
                        </div>
                    `;
                    previewDiv.style.display = 'block';
                } else {
                    previewDiv.style.display = 'none';
                }
            });
            
            // Show preview for default selected address
            if (addressSelect.value) {
                addressSelect.dispatchEvent(new Event('change'));
            }
        }
        
        // Form submission with loading state
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    submitBtn.disabled = true;
                }
            });
        }
    });
    </script>
</body>
<?php include '../footer.php'; ?>
