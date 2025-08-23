<?php
include '_base.php';

function logLogoutActivity($user_id, $user_email, $logout_type = 'manual') {
    global $_db;
    
    try {
        $stm = $_db->prepare('
            INSERT INTO user_activity_log (user_id, email, activity_type, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $details = json_encode([
            'logout_type' => $logout_type,
            'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $stm->execute([
            $user_id,
            $user_email,
            'logout',
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

function clearRememberMeTokens($user_id) {
    global $_db;
    
    try {
        $stm = $_db->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stm->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Failed to clear remember tokens: " . $e->getMessage());
    }
}

function performLogout($logout_type = 'manual') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_data = null;
    
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
        $user_data = [
            'user_id' => $_SESSION['user_id'],
            'user_email' => $_SESSION['user_email'],
            'user_name' => $_SESSION['user_name'] ?? 'Unknown'
        ];
        
        logLogoutActivity($user_data['user_id'], $user_data['user_email'], $logout_type);
        
        clearRememberMeTokens($user_data['user_id']);
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    session_start();
    session_regenerate_id(true);
    
    return $user_data;
}

$logout_type = 'manual';

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $user_data = performLogout('ajax');
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => 'login.php'
    ]);
    exit;
}

if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $logout_type = 'timeout';
}

if (isset($_GET['force']) && $_GET['force'] == '1') {
    $logout_type = 'force';
}

$user_data = performLogout($logout_type);

switch ($logout_type) {
    case 'timeout':
        $message = 'Your session has expired. Please log in again.';
        $message_type = 'warning';
        break;
    case 'force':
        $message = 'You have been logged out by an administrator.';
        $message_type = 'error';
        break;
    default:
        $message = $user_data ? 'You have been logged out successfully. Thank you for using AiKUN Furniture!' : 'Logged out successfully.';
        $message_type = 'success';
        break;
}

$_SESSION['temp_' . $message_type] = $message;

$redirect_url = 'login.php';

if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $allowed_redirects = ['login.php', 'index.php', 'home.php'];
    $requested_redirect = $_GET['redirect'];
    
    if (in_array($requested_redirect, $allowed_redirects)) {
        $redirect_url = $requested_redirect;
    }
}

redirect($redirect_url);
?>