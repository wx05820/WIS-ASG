<?php
include '../../_base.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../loginstaff.php');
}

// Handle user status updates
if (is_post() && isset($_POST['action'])) {
    $user_id = req('user_id');
    
    if ($_POST['action'] === 'activate') {
        $stm = $_db->prepare('UPDATE user SET status = "Active" WHERE userID = ?');
        $stm->execute([$user_id]);
        temp('success', 'User activated successfully');








        
    } elseif ($_POST['action'] === 'deactivate') {
        $stm = $_db->prepare('UPDATE user SET status = "Inactive" WHERE userID = ?');
        $stm->execute([$user_id]);
        temp('success', 'User deactivated successfully');
    }
    
    // Redirect back to the user list with all current parameters preserved
    $redirect_url = 'list.php?' . http_build_query($_GET);
    redirect($redirect_url);
}

// If accessed directly without POST, redirect to list
redirect('list.php');
?>