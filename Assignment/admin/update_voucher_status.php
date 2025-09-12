<?php
require_once '../_base.php';

// Check if user is logged in and has admin privileges
if (!isLoggedInStaff()) {
    error_log("Update voucher status: User not logged in");
    redirect('loginstaff.php');
    exit;
}

if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    error_log("Update voucher status: User doesn't have required role. Role: " . ($_SESSION['staff_role'] ?? 'not set'));
    redirect('loginstaff.php');
    exit;
}

// Get database connection
$pdo = $_db;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Update voucher status: POST received. Data: " . print_r($_POST, true));
    
    $voucher_id = req('voucher_id');
    $new_status = req('new_status');
    
    error_log("Update voucher status: voucher_id=$voucher_id, new_status=$new_status");
    
    // Validate inputs
    if (empty($voucher_id) || empty($new_status)) {
        error_log("Update voucher status: Invalid voucher ID or status");
        temp('error', 'Invalid voucher ID or status');
        redirect('voucher_list.php');
        exit;
    }
    
    // Validate status value - only "Active" or "Inactive"
    $valid_statuses = ['Active', 'Inactive'];
    if (!in_array($new_status, $valid_statuses)) {
        temp('error', 'Invalid status value');
        redirect('voucher_list.php');
        exit;
    }
    
    try {
        // First check if voucher exists
        $check_sql = "SELECT voucher_id, is_active FROM voucher WHERE voucher_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$voucher_id]);
        $voucher = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$voucher) {
            error_log("Update voucher status: Voucher not found with ID: $voucher_id");
            temp('error', 'Voucher not found');
            redirect('voucher_list.php');
            exit;
        }
        
        // Check if status is already the same
        if ($voucher['is_active'] == $new_status) {
            temp('info', "Voucher status is already set to {$new_status}");
            redirect('voucher_list.php');
            exit;
        }
        
        // Update the voucher status
        $sql = "UPDATE voucher SET is_active = ? WHERE voucher_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$new_status, $voucher_id]);
        
        if ($result) {
            temp('success', "Voucher status updated to {$new_status} successfully");
        } else {
            temp('error', 'Failed to update voucher status');
        }
    } catch (PDOException $e) {
        error_log("Voucher status update error: " . $e->getMessage());
        temp('error', 'Database error occurred while updating voucher status');
    }
} else {
    temp('error', 'Invalid request method');
}

// Redirect back to voucher list page
redirect('voucher_list.php');
?>
