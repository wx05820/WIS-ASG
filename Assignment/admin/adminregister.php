<?php
include '../_base.php';

// Check if user is admin (you should add this check)
// if (!is_admin()) {
//     redirect('index.php');
// }

// Get existing tokens for display
$stm = $_db->prepare('
    SELECT id, email, expire, created_at 
    FROM staffregistertoken 
    WHERE expire > NOW() 
    ORDER BY created_at DESC
');
$stm->execute();
$active_tokens = $stm->fetchAll();

// Delete expired tokens
$_db->query('DELETE FROM staffregistertoken WHERE expire < NOW()');

if (is_post()) {
    $email = req('email');
    $expire_hours = (int)req('expire_hours') ?: 24; // Default 24 hours
    
    // Validate expire hours (between 1-168 hours = 1 week max)
    if ($expire_hours < 1 || $expire_hours > 168) {
        $expire_hours = 24;
    }

    // Validate email
    if (!$email) {
        $_err['email'] = 'Required';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else {
        // Check if email already exists as a user
        $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE email = ?');
        $stm->execute([$email]);
        if ($stm->fetchColumn() > 0) {
            $_err['email'] = 'Email already registered as user';
        }
        
        // Check if there's already an active token for this email
        $stm = $_db->prepare('SELECT COUNT(*) FROM staffregistertoken WHERE email = ? AND expire > NOW()');
        $stm->execute([$email]);
        if ($stm->fetchColumn() > 0) {
            $_err['email'] = 'Active invitation already exists for this email';
        }
    }

    if (!$_err) {
        try {
            // Generate secure token ID
            $id = bin2hex(random_bytes(16));
            
            // Calculate expiry time
            $expire_time = date('Y-m-d H:i:s', strtotime("+{$expire_hours} hours"));

            // DELETE old tokens for this email (cleanup)
            $stm = $_db->prepare('DELETE FROM staffregistertoken WHERE email = ?');
            $stm->execute([$email]);

            // INSERT new token
            $stm = $_db->prepare('
                INSERT INTO staffregistertoken (id, expire, email, created_at, created_by)
                VALUES (?, ?, ?, NOW(), ?)
            ');
            // Assuming you have admin user ID in session
            $admin_id = $_SESSION['user_id'] ?? 1; // Replace with actual admin ID
            $stm->execute([$id, $expire_time, $email, $admin_id]);

            // Generate token URL
            $url = base("admin/adminregister.php?id=$id");

            // Enhanced email content
            $m = get_mail();
            $m->addAddress($email);
            $m->isHTML(true);
            $m->Subject = 'AiKUN Furniture - Staff Account Invitation';
            $m->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 28px;'>🪑 AiKUN Furniture</h2>
                    <h3 style='margin: 10px 0 0 0; font-size: 18px; font-weight: normal;'>Staff Account Invitation</h3>
                </div>
                <div style='padding: 30px; background-color: #f8f9fa;'>
                    <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>Hello,</p>
                    <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>
                        You have been invited to join AiKUN Furniture as a staff member. 
                        Please click the button below to create your staff account.
                    </p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$url' style='
                            display: inline-block;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 15px 30px;
                            text-decoration: none;
                            border-radius: 8px;
                            font-weight: bold;
                            font-size: 16px;
                        '>Create Staff Account</a>
                    </div>
                    
                    <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0;'>
                        <p style='color: #856404; margin: 0; font-size: 14px;'>
                            <strong>⏰ Important:</strong> This invitation link will expire in <strong>$expire_hours hours</strong>.
                        </p>
                    </div>
                    
                    <p style='font-size: 14px; color: #666; margin-bottom: 10px;'>
                        If the button doesn't work, copy and paste this link into your browser:
                    </p>
                    <p style='font-size: 12px; color: #999; word-break: break-all; background: #f1f1f1; padding: 10px; border-radius: 4px;'>
                        $url
                    </p>
                    
                    <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                    <p style='color: #999; font-size: 12px; text-align: center; margin: 0;'>
                        If you didn't expect this invitation, please ignore this email.<br>
                        This is an automated email from AiKUN Furniture. Please do not reply to this message.
                    </p>
                </div>
            </div>";
            
            // Plain text version
            $m->AltBody = "AiKUN Furniture - Staff Account Invitation\n\n" .
                         "You have been invited to join AiKUN Furniture as a staff member.\n\n" .
                         "Please visit the following link to create your staff account:\n$url\n\n" .
                         "This invitation will expire in $expire_hours hours.\n\n" .
                         "If you didn't expect this invitation, please ignore this email.\n\n" .
                         "Best regards,\nAiKUN Furniture Team";

            if ($m->send()) {
                temp('success', "Staff invitation sent successfully to $email! Link expires in $expire_hours hours.");
                redirect('adduser.php'); // Refresh to show updated token list
            } else {
                $_err['general'] = 'Failed to send invitation email. Please try again.';
            }
            
        } catch (Exception $e) {
            $_err['general'] = 'An error occurred while sending the invitation.';
            error_log("Staff invitation error: " . $e->getMessage());
        }
    }
}

// Handle token deletion
if (isset($_GET['delete_token'])) {
    $token_id = $_GET['delete_token'];
    $stm = $_db->prepare('DELETE FROM staffregistertoken WHERE id = ?');
    $stm->execute([$token_id]);
    temp('info', 'Invitation token deleted successfully.');
    redirect('adduser.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff Member - AiKUN Furniture</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/loginRegister.css">
    <style>
        .admin-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }
        
        .expire-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .expire-select {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: white;
        }
        
        .tokens-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .tokens-table th,
        .tokens-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .tokens-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-expired {
            color: #dc3545;
            font-weight: 600;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
    </style>
</head>
<body class="bodylr">
    <div class="admin-container">
        <div class="card">
            <div class="login-header">
                <h1><i class="fas fa-user-plus"></i> Send Staff Invitation</h1>
                <p>Send an email invitation to create a staff account</p>
            </div>

            <?php if ($success_msg = get_temp('success')): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg = get_temp('error')): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($info_msg = get_temp('info')): ?>
                <div class="alert alert-info" style="background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($info_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_err['general'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_err['general']); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="form" novalidate>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input <?php echo isset($_err['email']) ? 'error' : ''; ?>" 
                        maxlength="100"
                        placeholder="Enter staff member's email"
                        value="<?php echo htmlspecialchars(req('email')); ?>"
                        required
                    >
                    <?php if (isset($_err['email'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($_err['email']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="expire_hours"><i class="fas fa-clock"></i> Invitation Expires In</label>
                    <div class="expire-group">
                        <select name="expire_hours" id="expire_hours" class="expire-select">
                            <option value="1">1 Hour</option>
                            <option value="6">6 Hours</option>
                            <option value="12">12 Hours</option>
                            <option value="24" selected>24 Hours (1 Day)</option>
                            <option value="48">48 Hours (2 Days)</option>
                            <option value="72">72 Hours (3 Days)</option>
                            <option value="168">168 Hours (1 Week)</option>
                        </select>
                        <i class="fas fa-info-circle" title="Choose how long the invitation link will remain valid"></i>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Invitation
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-eraser"></i> Clear
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Invitations -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Active Invitations</h2>
            <?php if (empty($active_tokens)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>No active invitations found</p>
                </div>
            <?php else: ?>
                <table class="tokens-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Sent</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_tokens as $token): ?>
                            <?php 
                            $is_expired = strtotime($token['expire']) < time();
                            $time_left = $is_expired ? 0 : strtotime($token['expire']) - time();
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($token['email']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($token['created_at'])); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($token['expire'])); ?></td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="status-expired">Expired</span>
                                    <?php else: ?>
                                        <span class="status-active">
                                            Active (<?php echo human_time_diff($time_left); ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?delete_token=<?php echo urlencode($token['id']); ?>" 
                                       class="btn btn-danger btn-small"
                                       onclick="return confirm('Are you sure you want to delete this invitation?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="links">
            <a href="adminpage.php"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
    </div>

    <script>
        // Clear error styling when user starts typing
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        this.classList.remove('error');
                        const errorMsg = this.parentNode.querySelector('.error-message');
                        if (errorMsg) {
                            errorMsg.style.display = 'none';
                        }
                    }
                });
            });
        });

        // Auto-refresh active invitations every 30 seconds
        setInterval(function() {
            // Only refresh if user is not actively typing
            if (!document.querySelector('input:focus')) {
                const url = new URL(window.location);
                url.searchParams.set('refresh', '1');
                if (url.toString() !== window.location.toString()) {
                    window.location.reload();
                }
            }
        }, 30000);
    </script>
</body>
</html>

<?php
// Helper function for human readable time difference
function human_time_diff($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm';
    if ($seconds < 86400) return floor($seconds / 3600) . 'h';
    return floor($seconds / 86400) . 'd';
}
?>