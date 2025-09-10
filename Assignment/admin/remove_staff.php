<?php
require_once '../_base.php';

// Check if user is SuperAdmin only
if (!isStaffSuperAdmin()) {
    redirect('loginstaff.php');
    exit;
}

// Handle remove staff action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = req('staff_id');
    $reason = req('reason');
    
    // Validate inputs
    if (empty($staff_id)) {
        temp('error', 'Invalid staff ID');
        redirect('usermanage/list.php');
        exit;
    }
    
    try {
        // Get staff details before removal
        $stmt = $_db->prepare('SELECT userID, username, email, name, role FROM user WHERE userID = ? AND role IN ("Admin", "Supervisor", "SuperAdmin")');
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        
        if (!$staff) {
            temp('error', 'Staff member not found');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Check if trying to remove yourself
        if ($staff_id === $_SESSION['staff_id']) {
            temp('error', 'Cannot remove your own account');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Check if trying to remove another SuperAdmin
        if ($staff->role === 'SuperAdmin') {
            temp('error', 'Cannot remove SuperAdmin users');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Send removal notification email before deleting
        $email_sent = sendStaffRemovalEmail($staff->email, $staff->name, $reason);
        
        // Delete the staff account
        $stmt = $_db->prepare('DELETE FROM user WHERE userID = ?');
        $result = $stmt->execute([$staff_id]);
        
        if ($result) {
            if ($email_sent) {
                temp('success', "Staff member {$staff->username} has been removed and notification email sent");
            } else {
                temp('success', "Staff member {$staff->username} has been removed (email notification failed)");
            }
        } else {
            temp('error', 'Failed to remove staff member');
        }
        
    } catch (PDOException $e) {
        error_log("Remove staff error: " . $e->getMessage());
        temp('error', 'Database error occurred while removing staff member');
    }
} else {
    temp('error', 'Invalid request method');
}

// Redirect back to user list
redirect('usermanage/list.php');
?>
