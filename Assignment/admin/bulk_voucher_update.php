<?php
require_once '../_base.php';

// Check if user has permission (Admin, Supervisor, or SuperAdmin)
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

$pdo = $_db;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucher_ids = $_POST['_bulk'] ?? [];
    $bulk_status = req('bulk_status');
    
    // Validate inputs
    if (empty($voucher_ids) || !is_array($voucher_ids)) {
        temp('error', 'No vouchers selected');
        redirect('voucher_list.php');
        exit;
    }
    
    if (empty($bulk_status)) {
        temp('error', 'Please select a status');
        redirect('voucher_list.php');
        exit;
    }
    
    // Validate status value - only Active or Inactive
    $valid_statuses = ['Active', 'Inactive'];
    if (!in_array($bulk_status, $valid_statuses)) {
        temp('error', 'Invalid status value');
        redirect('voucher_list.php');
        exit;
    }
    
    try {
        // Prepare placeholders for the IN clause
        $placeholders = str_repeat('?,', count($voucher_ids) - 1) . '?';
        
        // Update the voucher statuses
        $sql = "UPDATE voucher SET is_active = ? WHERE voucher_id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        
        // Combine status and voucher IDs for execution
        $params = array_merge([$bulk_status], $voucher_ids);
        $result = $stmt->execute($params);
        
        if ($result) {
            $count = count($voucher_ids);
            temp('success', "Successfully updated {$count} voucher(s) to {$bulk_status}");
        } else {
            temp('error', 'Failed to update voucher statuses');
        }
    } catch (PDOException $e) {
        error_log("Bulk voucher status update error: " . $e->getMessage());
        temp('error', 'Database error occurred while updating voucher statuses');
    }
} else {
    temp('error', 'Invalid request method');
}

// Redirect back to voucher list page
redirect('voucher_list.php');
?>
