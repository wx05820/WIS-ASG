<?php
include '../_base.php';

// ================= SECURITY HEADERS ==================
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ================= CONFIRM PAGE ==================
if (!isset($_GET['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Logout - AiKUN Furniture</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #8B4513 0%, #D2691E 50%, #CD853F 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                position: relative;
                overflow: hidden;
            }

            body::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="wood" patternUnits="userSpaceOnUse" width="20" height="20"><rect width="20" height="20" fill="%238B4513"/><path d="M0 0L20 0L20 20L0 20Z" fill="%23A0522D" opacity="0.3"/></pattern></defs><rect width="100" height="100" fill="url(%23wood)"/></svg>');
                opacity: 0.1;
                z-index: 1;
            }

            .logout-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 24px;
                padding: 40px;
                box-shadow: 
                    0 20px 40px rgba(0, 0, 0, 0.1),
                    0 0 0 1px rgba(255, 255, 255, 0.2);
                width: 100%;
                max-width: 480px;
                text-align: center;
                position: relative;
                z-index: 2;
                border: 2px solid rgba(139, 69, 19, 0.2);
            }

            .logout-header {
                margin-bottom: 30px;
            }

            .logout-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #DC143C, #B22222);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                box-shadow: 0 8px 24px rgba(220, 20, 60, 0.3);
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }

            .logout-icon i {
                font-size: 36px;
                color: white;
            }

            .logout-title {
                color: #2C1810;
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 12px;
            }

            .logout-subtitle {
                color: #6B7280;
                font-size: 16px;
                line-height: 1.5;
            }

            .logout-info {
                background: rgba(139, 69, 19, 0.05);
                border: 1px solid rgba(139, 69, 19, 0.1);
                border-radius: 16px;
                padding: 20px;
                margin: 24px 0;
                text-align: left;
            }

            .info-item {
                display: flex;
                align-items: center;
                margin-bottom: 12px;
                color: #4B5563;
                font-size: 14px;
            }

            .info-item:last-child {
                margin-bottom: 0;
            }

            .info-item i {
                color: #8B4513;
                margin-right: 12px;
                width: 16px;
                text-align: center;
            }

            .logout-actions {
                display: flex;
                gap: 16px;
                margin-top: 32px;
            }

            .btn {
                flex: 1;
                padding: 16px 24px;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                position: relative;
                overflow: hidden;
            }

            .btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
            }

            .btn:hover::before {
                left: 100%;
            }

            .btn-logout {
                background: linear-gradient(135deg, #DC143C, #B22222);
                color: white;
                box-shadow: 0 4px 16px rgba(220, 20, 60, 0.3);
            }

            .btn-logout:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(220, 20, 60, 0.4);
            }

            .btn-cancel {
                background: #F3F4F6;
                color: #6B7280;
                border: 2px solid #E5E7EB;
            }

            .btn-cancel:hover {
                background: #E5E7EB;
                color: #4B5563;
                transform: translateY(-2px);
            }

            .security-notice {
                margin-top: 24px;
                padding: 16px;
                background: rgba(34, 197, 94, 0.1);
                border: 1px solid rgba(34, 197, 94, 0.2);
                border-radius: 12px;
                color: #166534;
                font-size: 13px;
                line-height: 1.4;
            }

            .security-notice i {
                margin-right: 8px;
                color: #16A34A;
            }

            @media (max-width: 480px) {
                .logout-container {
                    padding: 30px 20px;
                    margin: 20px;
                }
                
                .logout-actions {
                    flex-direction: column;
                }
                
                .logout-title {
                    font-size: 24px;
                }
            }

            /* Furniture-themed decorative elements */
            .logout-container::before {
                content: '';
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                background: linear-gradient(45deg, #8B4513, #D2691E, #CD853F, #8B4513);
                border-radius: 24px;
                z-index: -1;
                opacity: 0.3;
            }

            .logout-container::after {
                content: '';
                position: absolute;
                top: 8px;
                left: 8px;
                right: 8px;
                bottom: 8px;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
                border-radius: 20px;
                z-index: -1;
            }
        </style>
    </head>
    <body>
        <div class="logout-container">
            <div class="logout-header">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h1 class="logout-title">Confirm Logout</h1>
                <p class="logout-subtitle">Are you sure you want to end your session?</p>
            </div>

            <div class="logout-info">
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your session will be securely terminated</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <span>All remember-me tokens will be cleared</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-history"></i>
                    <span>Activity will be logged for security</span>
                </div>
            </div>

            <div class="logout-actions">
                <a href="logout.php?confirm=1<?php echo isset($_GET['timeout']) ? '&timeout=1' : ''; ?><?php echo isset($_GET['force']) ? '&force=1' : ''; ?>" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Yes, Logout
                </a>
                <a href="../index.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
            </div>

            <div class="security-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Security Note:</strong> Logging out will immediately invalidate your session and clear all authentication tokens.
            </div>
        </div>

        <!-- Play confirmation sound -->
        <audio autoplay>
            <source src="images/beforelogOut.mp3" type="audio/mpeg">
        </audio>
    </body>
    </html>
    <?php
    exit;
}

// ================= ENHANCED LOGOUT PROCESS ==================
function logLogoutActivity($user_id, $user_email, $logout_type = 'manual', $additional_data = []) {
    global $_db;
    try {
        $stm = $_db->prepare('
            INSERT INTO user_activity_log (user_id, email, activity_type, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $details = array_merge([
            'logout_type' => $logout_type,
            'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0,
            'logout_timestamp' => date('Y-m-d H:i:s'),
            'session_id' => session_id(),
            'logout_reason' => $logout_type,
            'browser_info' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
                'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'unknown'
            ]
        ], $additional_data);
        
        $stm->execute([
            $user_id,
            $user_email,
            'logout',
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to log logout activity: " . $e->getMessage());
        return false;
    }
}

function clearRememberMeTokens($user_id) {
    global $_db;
    try {
        $stm = $_db->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stm->execute([$user_id]);
        
        // Also clear any session tokens
        $stm = $_db->prepare('DELETE FROM user_sessions WHERE user_id = ?');
        $stm->execute([$user_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to clear remember tokens: " . $e->getMessage());
        return false;
    }
}

function clearUserSessions($user_id) {
    global $_db;
    try {
        // Clear any active sessions for this user
        $stm = $_db->prepare('UPDATE user_sessions SET active = 0, ended_at = NOW() WHERE user_id = ? AND active = 1');
        $stm->execute([$user_id]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to clear user sessions: " . $e->getMessage());
        return false;
    }
}

function performSecureLogout($logout_type = 'manual') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_data = null;
    $logout_success = false;
    
    try {
        // Capture user data before clearing session
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
            $user_data = [
                'user_id' => $_SESSION['user_id'],
                'user_email' => $_SESSION['user_email'],
                'user_name' => $_SESSION['user_name'] ?? 'Unknown',
                'user_role' => $_SESSION['user_role'] ?? 'Unknown',
                'login_time' => $_SESSION['login_time'] ?? time()
            ];
            
            // Log logout activity
            $additional_data = [
                'user_role' => $user_data['user_role'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'logout_method' => 'secure_logout',
                'session_data' => [
                    'session_id' => session_id(),
                    'session_name' => session_name(),
                    'session_status' => session_status()
                ]
            ];
            
            logLogoutActivity($user_data['user_id'], $user_data['user_email'], $logout_type, $additional_data);
            
            // Clear remember-me tokens
            clearRememberMeTokens($user_data['user_id']);
            
            // Clear user sessions
            clearUserSessions($user_data['user_id']);
            
            $logout_success = true;
        }
        
        // Secure session destruction
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Start new session for temporary messages
        session_start();
        session_regenerate_id(true);
        
        // Set security headers for new session
        $_SESSION['security_token'] = bin2hex(random_bytes(32));
        $_SESSION['session_created'] = time();
        
        return [
            'success' => $logout_success,
            'user_data' => $user_data,
            'logout_type' => $logout_type,
            'timestamp' => time()
        ];
        
    } catch (Exception $e) {
        error_log("Error during secure logout: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'user_data' => $user_data,
            'logout_type' => $logout_type,
            'timestamp' => time()
        ];
    }
}

// ================= MAIN LOGOUT EXECUTION ==================
// Detect logout type and reason
$logout_type = 'manual';
$logout_reason = 'User initiated logout';

if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $logout_type = 'timeout';
    $logout_reason = 'Session timeout';
} elseif (isset($_GET['force']) && $_GET['force'] == '1') {
    $logout_type = 'force';
    $logout_reason = 'Administrator forced logout';
} elseif (isset($_GET['security']) && $_GET['security'] == '1') {
    $logout_type = 'security';
    $logout_reason = 'Security violation detected';
} elseif (isset($_GET['inactive']) && $_GET['inactive'] == '1') {
    $logout_type = 'inactive';
    $logout_reason = 'User inactivity';
}

// Perform secure logout
$logout_result = performSecureLogout($logout_type);

// Set appropriate message based on logout type and result
switch ($logout_type) {
    case 'timeout':
        $message = 'Your session has expired due to inactivity. Please log in again to continue.';
        $message_type = 'warning';
        $icon = 'fas fa-clock';
        break;
    case 'force':
        $message = 'You have been logged out by an administrator for security reasons.';
        $message_type = 'error';
        $icon = 'fas fa-user-shield';
        break;
    case 'security':
        $message = 'You have been logged out due to a security violation. Please contact support if this was unexpected.';
        $message_type = 'error';
        $icon = 'fas fa-shield-alt';
        break;
    case 'inactive':
        $message = 'You have been logged out due to inactivity. Please log in again to continue.';
        $message_type = 'warning';
        $icon = 'fas fa-hourglass-half';
        break;
    default:
        if ($logout_result['success']) {
            $message = $logout_result['user_data'] ? 
                'You have been logged out successfully. Thank you for using AiKUN Furniture!' : 
                'Logged out successfully.';
            $message_type = 'success';
            $icon = 'fas fa-check-circle';
        } else {
            $message = 'Logout completed with some issues. Please contact support if you experience problems.';
            $message_type = 'warning';
            $icon = 'fas fa-exclamation-triangle';
        }
        break;
}

// Set temporary message
$_SESSION['temp_' . $message_type] = $message;

// Redirect URL
$redirect_url = 'index.php';

// Show enhanced logout success page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Complete - AiKUN Furniture</title>
    <meta http-equiv="refresh" content="3;url=<?php echo $redirect_url; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 50%, #CD853F 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="wood" patternUnits="userSpaceOnUse" width="20" height="20"><rect width="20" height="20" fill="%238B4513"/><path d="M0 0L20 0L20 20L0 20Z" fill="%23A0522D" opacity="0.3"/></pattern></defs><rect width="100" height="100" fill="url(%23wood)"/></svg>');
            opacity: 0.1;
            z-index: 1;
        }

        .success-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 480px;
            text-align: center;
            position: relative;
            z-index: 2;
            border: 2px solid rgba(139, 69, 19, 0.2);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, 
                <?php echo $message_type === 'success' ? '#10B981' : ($message_type === 'warning' ? '#F59E0B' : '#EF4444'); ?>, 
                <?php echo $message_type === 'success' ? '#059669' : ($message_type === 'warning' ? '#D97706' : '#DC2626'); ?>);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon i {
            font-size: 36px;
            color: white;
        }

        .success-title {
            color: #2C1810;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        .success-message {
            color: #6B7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        .logout-details {
            background: rgba(139, 69, 19, 0.05);
            border: 1px solid rgba(139, 69, 19, 0.1);
            border-radius: 16px;
            padding: 20px;
            margin: 24px 0;
            text-align: left;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        .detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #4B5563;
            font-size: 14px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-item i {
            color: #8B4513;
            margin-right: 12px;
            width: 16px;
            text-align: center;
        }

        .redirect-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin: 24px 0;
            color: #1E40AF;
            font-size: 14px;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }

        .redirect-info i {
            margin-right: 8px;
            color: #3B82F6;
        }

        .countdown {
            font-weight: 600;
            color: #1E40AF;
        }

        .manual-redirect {
            margin-top: 24px;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #8B4513, #D2691E);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(139, 69, 19, 0.3);
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(139, 69, 19, 0.4);
        }

        @media (max-width: 480px) {
            .success-container {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .success-title {
                font-size: 20px;
            }
        }

        /* Furniture-themed decorative elements */
        .success-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #8B4513, #D2691E, #CD853F, #8B4513);
            border-radius: 24px;
            z-index: -1;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="<?php echo $icon; ?>"></i>
        </div>
        
        <h1 class="success-title">Logout Complete</h1>
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>

        <?php if ($logout_result['success'] && $logout_result['user_data']): ?>
        <div class="logout-details">
            <div class="detail-item">
                <i class="fas fa-user"></i>
                <span>User: <?php echo htmlspecialchars($logout_result['user_data']['user_name']); ?></span>
            </div>
            <div class="detail-item">
                <i class="fas fa-envelope"></i>
                <span>Email: <?php echo htmlspecialchars($logout_result['user_data']['user_email']); ?></span>
            </div>
            <div class="detail-item">
                <i class="fas fa-clock"></i>
                <span>Session Duration: <?php echo gmdate('H:i:s', $logout_result['user_data']['session_duration']); ?></span>
            </div>
            <div class="detail-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout Type: <?php echo ucfirst(htmlspecialchars($logout_type)); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="redirect-info">
            <i class="fas fa-arrow-right"></i>
            <span>Redirecting to homepage in <span class="countdown" id="countdown">3</span> seconds...</span>
        </div>

        <div class="manual-redirect">
            <a href="<?php echo $redirect_url; ?>" class="btn-home">
                <i class="fas fa-home"></i>
                Go to Homepage
            </a>
        </div>
    </div>

    <!-- Play logout success sound -->
    <audio autoplay>
        <source src="images/logOutSuccess.mp3" type="audio/mpeg">
    </audio>

    <script>
        // Countdown timer
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '<?php echo $redirect_url; ?>';
            }
        }, 1000);

        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add click sound effect to buttons
            const buttons = document.querySelectorAll('.btn-home');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    // Add a subtle click effect
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>
<?php
exit;
?>
