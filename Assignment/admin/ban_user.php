<?php
require_once '../_base.php';

// Check if user is admin
if (!isStaffAdmin() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
    exit;
}

// Handle ban user action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = req('user_id');
    $reason = req('reason');
    
    // Validate inputs
    if (empty($user_id)) {
        temp('error', 'Invalid user ID');
        redirect('usermanage/list.php');
        exit;
    }
    
    try {
        // Get user details before banning
        $stmt = $_db->prepare('SELECT userID, username, email, name, role FROM user WHERE userID = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            temp('error', 'User not found');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Check if trying to ban another admin (only SuperAdmin can ban Admin)
        if ($user->role === 'Admin' && !isStaffSuperAdmin()) {
            temp('error', 'Only SuperAdmin can ban Admin users');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Check if trying to ban SuperAdmin
        if ($user->role === 'SuperAdmin') {
            temp('error', 'Cannot ban SuperAdmin users');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Check if trying to ban yourself
        if ($user_id === $_SESSION['staff_id']) {
            temp('error', 'Cannot ban your own account');
            redirect('usermanage/list.php');
            exit;
        }
        
        // Update user status to Banned
        $stmt = $_db->prepare('UPDATE user SET status = "Banned" WHERE userID = ?');
        $result = $stmt->execute([$user_id]);
        
        if ($result) {
            // Cancel all pending orders for this user
            $stmt = $_db->prepare('UPDATE `order` SET status = "Cancelled" WHERE userID = ? AND status IN ("Pending", "Processing", "Shipped")');
            $stmt->execute([$user_id]);
            
            // Send ban notification email
            $email_sent = sendBanNotificationEmail($user->email, $user->name, $reason);
            
            if ($email_sent) {
                temp('success', "User {$user->username} has been banned and notification email sent");
            } else {
                temp('success', "User {$user->username} has been banned (email notification failed)");
            }
        } else {
            temp('error', 'Failed to ban user');
        }
        
    } catch (PDOException $e) {
        error_log("Ban user error: " . $e->getMessage());
        temp('error', 'Database error occurred while banning user');
    }
} else {
    temp('error', 'Invalid request method');
}

// Redirect back to user list
redirect('usermanage/list.php');
?>