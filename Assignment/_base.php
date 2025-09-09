<?php
require_once __DIR__ . '/config.php';


date_default_timezone_set('Asia/Kuala_Lumpur');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

try {
    $_db = new PDO('mysql:host=localhost;dbname=aikun;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$_err = [];

$public_pages = ['login.php', 'register.php', 'forgot-password.php', 'index.php'];
// Only check remember me token if user explicitly chose to be remembered
// TEMPORARILY DISABLED AUTO-LOGIN TO TEST LOGOUT ISSUE
// This prevents automatic login when user didn't check "Remember Me"
if (false && !isLoggedIn() && !in_array(basename($_SERVER['PHP_SELF']), $public_pages)) {
    // Debug: Log what's happening
    error_log("Auto-login check: remember_token=" . (isset($_COOKIE['remember_token']) ? 'exists' : 'not set') . 
              ", remember_me_opted_in=" . (isset($_COOKIE['remember_me_opted_in']) ? 'exists' : 'not set'));
    
    // Check if there's a remember me token AND the user previously opted in
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_me_opted_in'])) {
        error_log("Attempting auto-login with remember me token");
        $autoLoginUser = checkRememberMeToken();
        if ($autoLoginUser) {
            error_log("Auto-login successful for user: " . $autoLoginUser->username);
            $display_name = !empty($autoLoginUser->name) ? $autoLoginUser->name : $autoLoginUser->username;
            temp('info', 'Welcome back, ' . htmlspecialchars($display_name) . '!');
        } else {
            error_log("Auto-login failed - invalid token");
        }
    }
}

function authenticateUser($loginInput, $password) {
    global $_db;
    try {
        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            $stm = $_db->prepare('SELECT * FROM user WHERE email = ? AND role = "Customer"');
        } else {
            $stm = $_db->prepare('SELECT * FROM user WHERE username = ? AND role = "Customer"');
        }
        $stm->execute([$loginInput]);
        $user = $stm->fetch();
        if (!$user) return false;
        if (password_verify($password, $user->password)) {
            if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $_db->prepare('UPDATE user SET password = ? WHERE userID = ?');
                $update->execute([$newHash, $user->userID]);
            }
            return $user;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

function loginUser($user) {
    global $_db;
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => $user->userID,
        'username' => $user->username,
        'email' => $user->email,
        'name' => $user->name ?? '',
        'user_role' => $user->role ?? 'Customer',
        'login_time' => time(),
        'logged_in' => true,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'status' => $user->status ?? 'Active'
    ];
    try {
        $forceUpdate = $_db->prepare("UPDATE user SET last_login = NOW() WHERE userID = ?");
        $forceUpdate->execute([$user->userID]);
    } catch (Exception $e) {
    }
    return true;
}

function isLoggedIn() {
    // Debug: Log session state (commented out to prevent output issues)
    // error_log("isLoggedIn() called - Session data: " . print_r($_SESSION, true));
    
    // Check if session is valid and user is logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        // error_log("isLoggedIn() returning false - logged_in not set or false");
        return false;
    }
    if (!isset($_SESSION['user_id'])) {
        // error_log("isLoggedIn() returning false - user_id not set");
        return false;
    }
    
    // Check for session timeout (2 hours)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
        // error_log("isLoggedIn() returning false - session timeout");
        logoutUser();
        return false;
    }
    
    // Additional check: if we have a user_id but no logged_in flag, user is not logged in
    if (isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
        // error_log("isLoggedIn() returning false - user_id exists but logged_in is false");
        return false;
    }
    
    // error_log("isLoggedIn() returning true");
    return true;
}

function getCurrentUser() {
    global $_db;
    if (!isLoggedIn()) return false;
    try {
        $stm = $_db->prepare('SELECT * FROM user WHERE userID = ?');
        $stm->execute([$_SESSION['user_id']]);
        return $stm->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function logoutUser() {
    clearRememberMeCookie();
    // Clear the remember me opted in flag
    setcookie('remember_me_opted_in', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    
    // Explicitly set logged_in to false before clearing session
    $_SESSION['logged_in'] = false;
    $_SESSION = [];
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function setRememberMeCookie($user_id) {
    global $_db;
    try {
        $token = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $token);
        $expires = time() + (30 * 24 * 60 * 60);
        $stm = $_db->prepare("INSERT INTO remember_tokens (userID, token, expires_at) VALUES (?, ?, ?)");
        $stm->execute([$user_id, $hashed_token, date('Y-m-d H:i:s', $expires)]);
        setcookie('remember_token', $token, $expires, '/', '', isset($_SERVER['HTTPS']), true);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function checkRememberMeToken() {
    global $_db;
    if (!isset($_COOKIE['remember_token'])) {
        error_log("checkRememberMeToken: No remember_token cookie found");
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    $hashed_token = hash('sha256', $token);
    error_log("checkRememberMeToken: Checking token hash: " . substr($hashed_token, 0, 10) . "...");
    
    try {
        $stmt = $_db->prepare("SELECT rt.userID, rt.expires_at, u.* FROM remember_tokens rt JOIN user u ON rt.userID = u.userID WHERE rt.token = ? AND rt.expires_at > NOW()");
        $stmt->execute([$hashed_token]);
        $result = $stmt->fetch();
        
        if ($result) {
            error_log("checkRememberMeToken: Valid token found for user " . $result->username);
            loginUser($result);
            refreshRememberToken($result->userID, $hashed_token);
            return $result;
        } else {
            error_log("checkRememberMeToken: No valid token found in database");
            clearRememberMeCookie();
            return false;
        }
    } catch (Exception $e) {
        error_log("checkRememberMeToken: Database error: " . $e->getMessage());
        clearRememberMeCookie();
        return false;
    }
}

function refreshRememberToken($userID, $oldHashedToken) {
    global $_db;
    try {
        $newToken = bin2hex(random_bytes(32));
        $newHashedToken = hash('sha256', $newToken);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $_db->prepare("UPDATE remember_tokens SET token = ?, expires_at = ? WHERE userID = ? AND token = ?");
        $stmt->execute([$newHashedToken, $expires, $userID, $oldHashedToken]);
        $cookie_expires = time() + (30 * 24 * 60 * 60);
        setcookie('remember_token', $newToken, $cookie_expires, '/', '', isset($_SERVER['HTTPS']), true);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function clearRememberMeCookie() {
    global $_db;
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $hashed_token = hash('sha256', $token);
        try {
            $stmt = $_db->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$hashed_token]);
        } catch (Exception $e) {
        }
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    // Also clear the opted-in flag
    setcookie('remember_me_opted_in', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

function clearAllRememberTokens($user_id) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
    }
}

function cleanupExpiredTokens() {
    global $_db;
    try {
        $stmt = $_db->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

function isIPBlocked() {
    global $_db;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    try {
        $stm = $_db->prepare('SELECT COUNT(*) FROM failed_attempts WHERE ip_address = ? AND created_at > ?');
        $stm->execute([$ip, $one_hour_ago]);
        $count = $stm->fetchColumn();
        return $count >= 10;
    } catch (Exception $e) {
        error_log("IP block check error: " . $e->getMessage());
        return false;
    }
}

function isAccountLocked($login_input) {
    global $_db;
    try {
        $stm = $_db->prepare("SELECT COUNT(*) FROM failed_attempts WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stm->execute([$login_input]);
        return $stm->fetchColumn() >= 5;
    } catch (Exception $e) {
        error_log("Account lock check error: " . $e->getMessage());
        return false;
    }
}

function shouldShowCaptcha($login_input) {
    global $_db;
    try {
        $stm = $_db->prepare("SELECT COUNT(*) FROM failed_attempts WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stm->execute([$login_input]);
        return $stm->fetchColumn() >= 3;
    } catch (Exception $e) {
        error_log("CAPTCHA check error: " . $e->getMessage());
        return false;
    }
}

function logFailedLoginAttempt($login_input, $reason) {
    global $_db;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stm = $_db->prepare("INSERT INTO failed_attempts (email, ip_address, attempt_type, details, created_at) VALUES (?, ?, 'login_failed', ?, NOW())");
        $stm->execute([$login_input, $ip, $reason]);
    } catch (Exception $e) {
        error_log("Log failed attempt error: " . $e->getMessage());
    }
}

function logFailedAttempt($email, $attempt_type, $details = '') {
    global $_db;
    try {
        $stm = $_db->prepare('INSERT INTO failed_attempts (email, attempt_type, details, created_at, ip_address) VALUES (?, ?, ?, NOW(), ?)');
        $stm->execute([$email, $attempt_type, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        error_log("Log failed attempt error: " . $e->getMessage());
    }
}

function is_valid_login_input($input) {
    if (empty($input)) return false;
    if (filter_var($input, FILTER_VALIDATE_EMAIL)) return true;
    if (preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $input)) return true;
    return false;
}

function validatePasswordStrength($password) {
    if (strlen($password) < 8) return 'Password must be at least 8 characters long';
    if (strlen($password) > 128) return 'Password must be less than 128 characters';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    return true;
}

function checkRateLimit($email) {
    global $_db;
    try {
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $stm = $_db->prepare('SELECT COUNT(*) FROM otp_requests WHERE email = ? AND created_at > ?');
        $stm->execute([$email, $one_hour_ago]);
        $count = $stm->fetchColumn();
        return $count < 3;
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return true;
    }
}

function logOTPRequest($email, $success = true) {
    global $_db;
    try {
        $stm = $_db->prepare('INSERT INTO otp_requests (email, success, created_at, ip_address) VALUES (?, ?, NOW(), ?)');
        $stm->execute([$email, $success ? 1 : 0, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        error_log("Log OTP request error: " . $e->getMessage());
    }
}

function isRepeatOTPRequest($email) {
    return isset($_SESSION['last_otp_email']) && $_SESSION['last_otp_email'] === $email;
}

function generateCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $operators = ['+', '-', '*'];
    $operator = $operators[array_rand($operators)];
    switch ($operator) {
        case '+': $answer = $num1 + $num2; break;
        case '-':
            if ($num1 < $num2) list($num1, $num2) = [$num2, $num1];
            $answer = $num1 - $num2;
            break;
        case '*':
            $num1 = rand(1, 5);
            $num2 = rand(1, 5);
            $answer = $num1 * $num2;
            break;
    }
    $_SESSION['captcha_answer'] = $answer;
    return "$num1 $operator $num2 = ?";
}

function verifyCaptcha($response) {
    if (!isset($_SESSION['captcha_answer']) || !isset($response)) return false;
    $result = (int)$response === (int)$_SESSION['captcha_answer'];
    unset($_SESSION['captcha_answer']);
    return $result;
}

function get_mail() {
    require_once 'lib/PHPMailer.php';
    require_once 'lib/SMTP.php';
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->SMTPAuth = true;
    $m->Host = 'smtp.gmail.com';
    $m->Port = 587;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Username = 'zhtan392@gmail.com';
    $m->Password = 'qrcg ijnw qggs ipok';
    $m->CharSet = 'utf-8';
    $m->setFrom($m->Username, 'AiKUN Furniture');
    return $m;
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function base($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "$protocol://$host/admin/";
    return $baseUrl . ltrim($path, '/');
}

function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}

function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

function req($key) {
    if (!isset($_POST[$key])) return '';
    if (is_array($_POST[$key])) return array_map('trim', $_POST[$key]);
    return trim((string)$_POST[$key]);
}

function is_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function temp($type, $message) {
    $_SESSION['temp_' . $type] = $message;
}

function get_temp($type) {
    if (isset($_SESSION['temp_' . $type])) {
        $message = $_SESSION['temp_' . $type];
        unset($_SESSION['temp_' . $type]);
        return $message;
    }
    return null;
}

function redirect($url = null) {
    $url = $url ?? $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

function generateRandomUsername($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $username = '';
    for ($i = 0; $i < $length; $i++) {
        $username .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $username;
}

function generateUniquePhoneNumber($db = null) {
    if (!$db) {
        global $_db;
        $db = $_db;
    }
    
    // Malaysian phone number format: 6XXXXXXXXX (10 digits starting with 6)
    // Staff prefix: 6X9XXXXXXX (starting with 6, second digit 9 for staff)
    $prefix = '69'; // Staff prefix
    $maxAttempts = 100;
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        // Generate 8 random digits
        $randomDigits = '';
        for ($i = 0; $i < 8; $i++) {
            $randomDigits .= rand(0, 9);
        }
        
        $phoneNumber = $prefix . $randomDigits;
        
        // Check if this phone number already exists
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE phoneNo = ?");
            $stmt->execute([$phoneNumber]);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                return $phoneNumber;
            }
        } catch (PDOException $e) {
            error_log("Error checking phone number uniqueness: " . $e->getMessage());
            // If database error, return a timestamp-based number as fallback
            return '6' . date('YmdHis') . rand(100, 999);
        }
    }
    
    // Fallback: if we can't find a unique number, use timestamp-based approach
    return '6' . date('YmdHis') . rand(100, 999);
}

function err($key) {
    global $_err;
    if (isset($_err[$key]) && $_err[$key]) {
        echo "<span class='err'>" . htmlspecialchars($_err[$key]) . "</span>";
    } else {
        echo '<span></span>';
    }
}

function getRemainingCooldownTime($email) {
    global $_db;
    try {
        $stm = $_db->prepare('SELECT created_at FROM otp_requests WHERE email = ? ORDER BY created_at DESC LIMIT 1');
        $stm->execute([$email]);
        $last_request = $stm->fetchColumn();
        if ($last_request) {
            $next_allowed = strtotime($last_request) + (60 * 20);
            $remaining = $next_allowed - time();
            if ($remaining > 0) {
                if ($remaining > 60) return floor($remaining / 60) . ' minutes';
                else return $remaining . ' seconds';
            }
        }
        return '0 seconds';
    } catch (Exception $e) {
        error_log("Get cooldown time error: " . $e->getMessage());
        return '0 seconds';
    }
}

function get_login_input_type($input) {
    if (filter_var($input, FILTER_VALIDATE_EMAIL)) return 'email';
    return 'username';
}

function login($user, $url = '/') {
    loginUser($user);
    redirect($url);
}

function logout($url = '/') {
    logoutUser();
    redirect($url);
}

function is_logged_in() {
    return isLoggedIn();
}

if (rand(1, 100) === 1) {
    cleanupExpiredTokens();
}

function getRandomProfilePhoto() {
    // Web paths for storage
    $profilePhotos = [
        'profilePhoto/profile1.jpg',
        'profilePhoto/profile2.jpg',
        'profilePhoto/profile3.jpg',
        'profilePhoto/profile4.jpg',
        'profilePhoto/profile5.jpg',
        'profilePhoto/profile6.jpg',
        'profilePhoto/profile7.jpg',
    ];

    // Resolve absolute filesystem paths for reliable existence checks
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
    $availablePhotos = [];
    foreach ($profilePhotos as $webPath) {
        $fsPath = $baseDir . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $webPath);
        if (file_exists($fsPath)) {
            $availablePhotos[] = $webPath;
        }
    }

    if (!empty($availablePhotos)) {
        $randomIndex = array_rand($availablePhotos);
        return $availablePhotos[$randomIndex];
    }

    // Fallback to default
    $defaultWeb = 'profilePhoto/default-profile.jpg';
    $defaultFs = $baseDir . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $defaultWeb);
    return file_exists($defaultFs) ? $defaultWeb : '';
}

function generateCSRFToken($expiration = 3600) {
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > $expiration) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token, $expiration = 3600) {
    return isset($_SESSION['csrf_token']) &&
           isset($_SESSION['csrf_token_time']) &&
           (time() - $_SESSION['csrf_token_time']) <= $expiration &&
           hash_equals($_SESSION['csrf_token'], $token);
}

function sendContactReplyEmail($message_id, $reply_message) {
    global $_db;
    
    try {
        // Get the original message details
        $stmt = $_db->prepare("
            SELECT cm.*, u.name as customer_name 
            FROM contact_messages cm
            LEFT JOIN user u ON cm.email = u.email
            WHERE cm.id = ?
        ");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch();
        
        if (!$message) {
            return false;
        }
        
        // Get staff member who replied
        $stmt = $_db->prepare("SELECT name, username FROM user WHERE userID = ?");
        $stmt->execute([$_SESSION['staff_id']]);
        $staff = $stmt->fetch();
        
        $mail = get_mail();
        $mail->addAddress($message->email, $message->customer_name ?: $message->name);
        $mail->Subject = 'Re: ' . $message->subject . ' - AiKUN Furniture';
        
        // Add company logo as embedded image
        $logo_path = __DIR__ . '/images/logo.png';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'company_logo', 'logo.png');
        }
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:company_logo' alt='AiKUN Furniture' style='max-width: 150px; height: auto; margin-bottom: 15px;'>
                </div>
                <h2>Reply from AiKUN Furniture</h2>
            <p>Dear " . htmlspecialchars($message->name) . ",</p>
            
            <p>Thank you for contacting us. Here is our reply to your message:</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #8B4513; margin: 20px 0;'>
                <strong>Your original message:</strong><br>
                Subject: " . htmlspecialchars($message->subject) . "<br>
                Message: " . nl2br(htmlspecialchars($message->message)) . "
            </div>
            
            <div style='background: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                <strong>Our reply:</strong><br>
                " . nl2br(htmlspecialchars($reply_message)) . "
            </div>
            
            <p>If you have any further questions, please don't hesitate to contact us.</p>
            
            <p>Best regards,<br>
            " . htmlspecialchars($staff->name ?: $staff->username) . "<br>
            AiKUN Furniture Team</p>
            
            <hr>
            <p style='font-size: 12px; color: #666;'>
                This is an automated reply. Please do not reply to this email directly.
                If you need to contact us, please use our contact form or call us directly.
            </p>
            </div>
        ";
        
        $mail->isHTML(true);
        $result = $mail->send();
        
        if ($result) {
            error_log("Contact reply email sent successfully to: " . $message->email);
        } else {
            error_log("Failed to send contact reply email: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Contact reply email error: " . $e->getMessage());
        return false;
    }
}

function sanitizeInput($input, $type = 'text') {
    $input = trim($input);
    switch ($type) {
        case 'email': return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'phone': return preg_replace('/[^+0-9\s\-\(\)]/', '', $input);
        case 'username': return preg_replace('/[^a-zA-Z0-9_-]/', '', $input);
        case 'name': return preg_replace('/[^a-zA-Z\s\-\.\']/', '', $input);
        default: return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

function validateImageUpload($file, $options = []) {
    $errors = [];
    $defaults = [
        'max_size' => 5 * 1024 * 1024,
        'min_width' => 50,
        'min_height' => 50,
        'max_width' => 2000,
        'max_height' => 2000,
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
    ];
    $options = array_merge($defaults, $options);
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit.',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit.',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];
        $errors[] = $upload_errors[$file['error']] ?? 'Unknown upload error.';
        return $errors;
    }
    if ($file['size'] > $options['max_size']) $errors[] = "File size must be less than " . formatBytes($options['max_size']) . ".";
    if ($file['size'] < 1024) $errors[] = "File is too small (minimum 1KB).";
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $options['allowed_extensions'])) $errors[] = "File extension not allowed. Allowed: " . implode(', ', $options['allowed_extensions']);
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_type, $options['allowed_types'])) $errors[] = "Invalid file type detected.";
    }
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) $errors[] = "Invalid image file.";
    else {
        $width = $image_info[0];
        $height = $image_info[1];
        if ($width > $options['max_width'] || $height > $options['max_height']) $errors[] = "Image dimensions too large. Maximum {$options['max_width']}x{$options['max_height']} pixels.";
        if ($width < $options['min_width'] || $height < $options['min_height']) $errors[] = "Image too small. Minimum {$options['min_width']}x{$options['min_height']} pixels.";
        if ($image_info['channels'] ?? 0 > 4) $errors[] = "Suspicious image format detected.";
    }
    $file_content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if (preg_match('/<\?php|<\?=|<script|javascript:/i', $file_content)) $errors[] = "File contains suspicious content.";
    return $errors;
}

function generateSecureFilename($original_name, $user_id) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    return "{$user_id}_{$timestamp}_{$random}.{$extension}";
}

function handleSecureUpload($file, $upload_dir, $user_id, $options = []) {
    $result = ['success' => false, 'message' => '', 'filename' => '', 'path' => ''];
    $errors = validateImageUpload($file, $options);
    if (!empty($errors)) {
        $result['message'] = implode(' ', $errors);
        return $result;
    }
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $result['message'] = 'Failed to create upload directory.';
            return $result;
        }
    }
    $secure_filename = generateSecureFilename($file['name'], $user_id);
    $upload_path = $upload_dir . $secure_filename;
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        chmod($upload_path, 0644);
        $result['success'] = true;
        $result['filename'] = $secure_filename;
        $result['path'] = $upload_path;
        $result['message'] = 'File uploaded successfully.';
        error_log("File uploaded successfully: {$upload_path} for user: {$user_id}");
    } else {
        $result['message'] = 'Failed to move uploaded file.';
    }
    return $result;
}

function deleteFileSecurely($file_path, $user_id = '') {
    if (empty($file_path) || !file_exists($file_path)) return true;
    $allowed_dirs = ['uploads/profiles/', 'uploads/documents/'];
    $is_allowed = false;
    foreach ($allowed_dirs as $dir) {
        if (strpos($file_path, $dir) === 0) {
            $is_allowed = true;
            break;
        }
    }
    if (!$is_allowed) {
        error_log("Attempt to delete file outside allowed directories: {$file_path}");
        return false;
    }
    if (unlink($file_path)) {
        error_log("File deleted: {$file_path}" . ($user_id ? " by user: {$user_id}" : ''));
        return true;
    }
    return false;
}

function validateUsername($username, $current_user_id = null, $db = null) {
    $result = ['valid' => false, 'message' => ''];
    if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $username)) {
        $result['message'] = 'Username must be 3-30 characters and contain only letters, numbers, underscore, and hyphen.';
        return $result;
    }
    if (strpos($username, '@') !== false) {
        $result['message'] = 'Username cannot contain @ symbol.';
        return $result;
    }
    $reserved = ['admin', 'administrator', 'root', 'system', 'api', 'support', 'help', 'null', 'undefined', 'www', 'mail', 'ftp', 'blog', 'shop', 'store', 'test', 'demo', 'guest', 'user', 'users', 'account', 'accounts'];
    if (in_array(strtolower($username), $reserved)) {
        $result['message'] = 'This username is reserved.';
        return $result;
    }
    $profanity = ['fuck', 'shit', 'damn', 'bitch', 'ass', 'hell'];
    foreach ($profanity as $word) {
        if (stripos($username, $word) !== false) {
            $result['message'] = 'Username contains inappropriate content.';
            return $result;
        }
    }
    if ($db && $db instanceof PDO) {
        try {
            $sql = "SELECT userID FROM user WHERE username = ?";
            $params = [$username];
            if ($current_user_id) {
                $sql .= " AND userID != ?";
                $params[] = $current_user_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $result['message'] = 'Username already exists.';
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Username validation database error: " . $e->getMessage());
            $result['message'] = 'Error checking username availability.';
            return $result;
        }
    }
    $result['valid'] = true;
    $result['message'] = 'Username is valid.';
    return $result;
}

function validateEmail($email, $current_user_id = null, $db = null) {
    $result = ['valid' => false, 'message' => ''];
    if (empty($email)) {
        $result['message'] = 'Email is required.';
        return $result;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['message'] = 'Invalid email format.';
        return $result;
    }
    if (strlen($email) > 255) {
        $result['message'] = 'Email address too long.';
        return $result;
    }
    $disposable_domains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com', 'throwaway.email'];
    $domain = substr(strrchr($email, '@'), 1);
    if (in_array(strtolower($domain), $disposable_domains)) {
        $result['message'] = 'Disposable email addresses are not allowed.';
        return $result;
    }
    if ($db && $db instanceof PDO) {
        try {
            $sql = "SELECT userID FROM user WHERE email = ?";
            $params = [$email];
            if ($current_user_id) {
                $sql .= " AND userID != ?";
                $params[] = $current_user_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $result['message'] = 'Email already exists.';
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Email validation database error: " . $e->getMessage());
            $result['message'] = 'Error checking email availability.';
            return $result;
        }
    }
    $result['valid'] = true;
    $result['message'] = 'Email is valid.';
    return $result;
}

function validateName($name) {
    $result = ['valid' => false, 'message' => ''];
    if (empty($name)) {
        $result['message'] = 'Name is required.';
        return $result;
    }
    if (strlen($name) > 100) {
        $result['message'] = 'Name must be less than 100 characters.';
        return $result;
    }
    if (strlen($name) < 2) {
        $result['message'] = 'Name must be at least 2 characters.';
        return $result;
    }
    if (!preg_match("/^[a-zA-Z\s\-\.'\x{00C0}-\x{017F}]+$/u", $name)) {
        $result['message'] = 'Name can only contain letters, spaces, hyphens, dots, apostrophes, and accented characters.';
        return $result;
    }
    if (preg_match('/(.)\1{4,}/', $name)) {
        $result['message'] = 'Name contains suspicious pattern.';
        return $result;
    }
    $result['valid'] = true;
    $result['message'] = 'Name is valid.';
    return $result;
}

function validatePhoneNumber($phone) {
    $result = ['valid' => false, 'message' => ''];
    if (empty($phone)) {
        $result['valid'] = true;
        return $result;
    }
    $phone_digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
        $result['message'] = 'Phone number must be between 10-15 digits.';
        return $result;
    }
    if (!preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone)) {
        $result['message'] = 'Invalid phone number format.';
        return $result;
    }
    $result['valid'] = true;
    $result['message'] = 'Phone number is valid.';
    return $result;
}

function validateBirthday($birthday) {
    $result = ['valid' => false, 'message' => '', 'age' => null];
    if (empty($birthday)) {
        $result['valid'] = true;
        return $result;
    }
    $birth_date = DateTime::createFromFormat('Y-m-d', $birthday);
    if (!$birth_date || $birth_date->format('Y-m-d') !== $birthday) {
        $result['message'] = 'Invalid birthday format.';
        return $result;
    }
    $today = new DateTime();
    $age = $birth_date->diff($today)->y;
    if ($birth_date > $today) {
        $result['message'] = 'Birthday cannot be in the future.';
        return $result;
    }
    if ($age > 120) {
        $result['message'] = 'Invalid birthday - age cannot exceed 120 years.';
        return $result;
    }
    if ($age < 13) {
        $result['message'] = 'You must be at least 13 years old to use this service.';
        return $result;
    }
    $result['valid'] = true;
    $result['age'] = $age;
    $result['message'] = 'Birthday is valid.';
    return $result;
}

function calculateAge($birthday) {
    if (empty($birthday)) return null;
    try {
        $birth_date = new DateTime($birthday);
        $today = new DateTime('today');
        return $birth_date->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
    return round($bytes, $precision) . ' ' . $units[$i];
}

function generateAvatarInitials($name) {
    if (empty($name)) return '?';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: '?';
}

function logProfileActivity($user_id, $action, $details = [], $db = null) {
    $log_data = [
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("Profile Activity: " . json_encode($log_data));
    if ($db && $db instanceof PDO) {
        try {
            $stmt = $db->prepare("INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $action, json_encode($details), $log_data['ip_address'], $log_data['user_agent']]);
        } catch (PDOException $e) {
            error_log("Failed to log activity to database: " . $e->getMessage());
        }
    }
}

function cleanOldUploads($directory, $max_age = 2592000) {
    if (!is_dir($directory)) return 0;
    
    $deleted = 0;
    $current_time = time();
    
    $files = glob($directory . '*');
    foreach ($files as $file) {
        if (is_file($file) && ($current_time - filemtime($file)) > $max_age) {
            if (unlink($file)) {
                $deleted++;
                error_log("Cleaned old upload: {$file}");
            }
        }
    }
    
    return $deleted;
}

/**
 * Validate and process profile data
 * @param array $data Form data
 * @param string $user_id Current user ID
 * @param PDO $db Database connection
 * @return array Processing result
 */
function processProfileData($data, $user_id, $db) {
    $result = ['success' => false, 'errors' => [], 'data' => []];
    
    // Sanitize inputs
    $processed_data = [
        'name' => sanitizeInput($data['name'] ?? '', 'name'),
        'email' => sanitizeInput($data['email'] ?? '', 'email'),
        'username' => sanitizeInput($data['username'] ?? '', 'username'),
        'phoneNo' => sanitizeInput($data['phoneNo'] ?? '', 'phone'),
        'birthday' => sanitizeInput($data['birthday'] ?? '', 'text')
    ];
    
    // Validate each field
    $name_validation = validateName($processed_data['name']);
    if (!$name_validation['valid']) {
        $result['errors'][] = $name_validation['message'];
    }
    
    $email_validation = validateEmail($processed_data['email'], $user_id, $db);
    if (!$email_validation['valid']) {
        $result['errors'][] = $email_validation['message'];
    }
    
    $username_validation = validateUsername($processed_data['username'], $user_id, $db);
    if (!$username_validation['valid']) {
        $result['errors'][] = $username_validation['message'];
    }
    
    $phone_validation = validatePhoneNumber($processed_data['phoneNo']);
    if (!$phone_validation['valid']) {
        $result['errors'][] = $phone_validation['message'];
    }
    
    $birthday_validation = validateBirthday($processed_data['birthday']);
    if (!$birthday_validation['valid']) {
        $result['errors'][] = $birthday_validation['message'];
    }
    
    if (empty($result['errors'])) {
        $result['success'] = true;
        $result['data'] = $processed_data;
        $result['age'] = $birthday_validation['age'];
    }
    
    return $result;
}

function updateUserProfile($data, $user_id, $db) {
    $result = ['success' => false, 'message' => ''];
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE user SET name = ?, email = ?, username = ?, phoneNo = ?, birthday = ?, updated_at = NOW() WHERE userID = ?";
        $params = [
            $data['name'],
            $data['email'],
            $data['username'],
            $data['phoneNo'],
            $data['birthday'] ?: null,
            $user_id
        ];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['name'] = $data['name'];
            $_SESSION['email'] = $data['email'];
            $_SESSION['username'] = $data['username'];
            $_SESSION['phoneNo'] = $data['phoneNo'];
            $_SESSION['birthday'] = $data['birthday'];
            
            logProfileActivity($user_id, 'profile_updated', $data, $db);
            
            $db->commit();
            $result['success'] = true;
            $result['message'] = 'Profile updated successfully!';
        } else {
            $db->rollBack();
            $result['message'] = 'No changes were made to your profile.';
        }
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Profile update error: " . $e->getMessage());
        $result['message'] = 'Error updating profile. Please try again.';
    }
    
    return $result;
}

function getUserProfile($user_id, $db) {
    try {
        $stmt = $db->prepare("SELECT * FROM user WHERE userID = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user profile: " . $e->getMessage());
        return false;
    }
}

function money($n) {
    return 'RM '.number_format($n, 2);
}

// Role checking helper functions
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

function isCustomer() {
    return hasRole('Customer');
}

function isAdmin() {
    return hasRole('Admin');
}

function isSupervisor() {
    return hasRole('Supervisor');
}

function isSuperAdmin() {
    return hasRole('SuperAdmin');
}
// Staff authentication functions
function authenticateStaff($loginInput, $password) {
    global $_db;
    try {
        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            $stm = $_db->prepare('SELECT * FROM user WHERE email = ? AND role IN ("Admin", "Supervisor", "SuperAdmin")');
        } else {
            $stm = $_db->prepare('SELECT * FROM user WHERE username = ? AND role IN ("Admin", "Supervisor", "SuperAdmin")');
        }
        $stm->execute([$loginInput]);
        $user = $stm->fetch();
        if (!$user) return false;
        if (password_verify($password, $user->password)) {
            if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $_db->prepare('UPDATE user SET password = ? WHERE userID = ?');
                $update->execute([$newHash, $user->userID]);
                $forceUpdate = $_db->prepare("UPDATE user SET last_login = NOW() WHERE userID = ?");
                $forceUpdate->execute([$user->userID]);
            }
            return $user;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Staff authentication error: " . $e->getMessage());
        return false;
    }
}

function loginUserStaff($user) {
    global $_db;
    session_regenerate_id(true);
    $_SESSION = [
        'staff_id' => $user->userID,
        'username' => $user->username,
        'email' => $user->email,
        'name' => $user->name,
        'staff_role' => $user->role,
        'login_time' => time(),
        'staff_logged_in' => true,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    try {
        $forceUpdate = $_db->prepare("UPDATE user SET last_login = NOW() WHERE userID = ?");
        $forceUpdate->execute([$user->userID]);
    } catch (Exception $e) {
    }
    return true;
}

function isLoggedInStaff() {
    if (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in']) return false;
    if (!isset($_SESSION['staff_id'])) return false;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
        logoutUserStaff();
        return false;
    }
    return true;
}

function getCurrentStaff() {
    global $_db;
    if (!isLoggedInStaff()) return false;
    try {
        $stm = $_db->prepare('SELECT * FROM user WHERE userID = ?');
        $stm->execute([$_SESSION['staff_id']]);
        return $stm->fetch();
    } catch (PDOException $e) {
        error_log("Get current staff error: " . $e->getMessage());
        return false;
    }
}

function logoutUserStaff() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function hasStaffRole($role) {
    if (!isLoggedInStaff()) return false;
    return $_SESSION['staff_role'] === $role;
}

function isStaffAdmin() {
    return hasStaffRole('Admin');
}

function isStaffSupervisor() {
    return hasStaffRole('Supervisor');
}

function isStaffSuperAdmin() {
    return hasStaffRole('SuperAdmin');
}

function isAnyStaff() {
    return isLoggedInStaff();
}

function checkLogin(){
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Please log in to continue";
        $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? '/product/productList.php';
        header('Location: /user/login.php');
        exit;
    }
}

function checkUserStatus(){
    global $_db;
    
    if (!isset($_SESSION['user_id'])) {
        return; // Let checkLogin() handle this
    }
    
    try {
        $stmt = $_db->prepare('SELECT status FROM user WHERE userID = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user->status === 'Banned') {
            // Clear session data
            session_destroy();
            session_start();
            
            $_SESSION['error'] = "Your account has been suspended due to a violation of our terms of service. Please contact our support team for assistance.";
            header('Location: /user/login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("User status check error: " . $e->getMessage());
        // Don't block user if there's a database error
    }
}

function getCartCount($user_id) {
    global $_db;
    
    if (!$user_id) {
        return 0;
    }
    
    try {
        $stmt = $_db->prepare("
            SELECT SUM(ci.qty) as total_count
            FROM cart_items ci
            LEFT JOIN cart c ON c.cartID = ci.cartID
            WHERE c.userID = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total_count'] ?? 0);
    } catch (Exception $e) {
        error_log("Cart count error: " . $e->getMessage());
        return 0;
    }
}

// Function to refresh cart count after operations
function refreshCartCount($user_id) {
    if (!$user_id) {
        return 0;
    }
    
    // Clear any cached cart data if you have any
    return getCartCount($user_id);
}

// ========================================
// STOCK TRACKING FUNCTIONS
// ========================================

/**
 * Check for low stock products and send email alerts to admins
 * @param int $threshold - Stock level threshold (default: 10)
 * @return array - Array of low stock products
 */
function checkLowStockProducts($threshold = 5) {
    global $_db;
    
    try {
        // Simple approach - just get basic info that we know works
        // Get products with stock below threshold (but not zero)
        $stmt = $_db->prepare("
            SELECT prodID, name, qty
            FROM product 
            WHERE qty <= ? AND qty > 0 AND (status != 'removed' OR status IS NULL OR status = '')
            ORDER BY qty ASC
        ");
        $stmt->execute([$threshold]);
        $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get out of stock products (qty = 0)
        $stmt = $_db->prepare("
            SELECT prodID, name, qty
            FROM product 
            WHERE qty = 0 AND (status != 'removed' OR status IS NULL OR status = '')
            ORDER BY name ASC
        ");
        $stmt->execute();
        $outOfStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add default values for missing columns
        foreach ($lowStockProducts as &$product) {
            if (!isset($product['price'])) $product['price'] = 0;
            if (!isset($product['category'])) $product['category'] = 'Unknown';
        }
        
        foreach ($outOfStockProducts as &$product) {
            if (!isset($product['price'])) $product['price'] = 0;
            if (!isset($product['category'])) $product['category'] = 'Unknown';
        }
        
        // Debug logging
        error_log("checkLowStockProducts - Threshold: $threshold, Low stock found: " . count($lowStockProducts) . ", Out of stock found: " . count($outOfStockProducts));
        
        return [
            'low_stock' => $lowStockProducts,
            'out_of_stock' => $outOfStockProducts,
            'threshold' => $threshold
        ];
        
    } catch (PDOException $e) {
        error_log("Stock check PDO error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['low_stock' => [], 'out_of_stock' => [], 'threshold' => $threshold, 'error' => $e->getMessage()];
    } catch (Exception $e) {
        error_log("Stock check general error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['low_stock' => [], 'out_of_stock' => [], 'threshold' => $threshold, 'error' => $e->getMessage()];
    }
}

/**
 * Send low stock alert email to all admin users
 * @param array $stockData - Data from checkLowStockProducts()
 * @return bool - Success status
 */
function sendLowStockAlert($stockData) {
    global $_db;
    
    try {
        // Get all admin email addresses
        $stmt = $_db->prepare("
            SELECT email, username 
            FROM user 
            WHERE role IN ('Admin', 'SuperAdmin') AND email IS NOT NULL AND email != ''
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($admins)) {
            error_log("No admin emails found for stock alert");
            return false;
        }
        
        $lowStockProducts = $stockData['low_stock'];
        $outOfStockProducts = $stockData['out_of_stock'];
        $threshold = $stockData['threshold'];
        
        // Don't send email if no low stock products
        if (empty($lowStockProducts) && empty($outOfStockProducts)) {
            return true;
        }
        
        // Prepare email content
        $subject = "🚨 Low Stock Alert - AiKUN Furniture";
        $emailBody = generateStockAlertEmail($lowStockProducts, $outOfStockProducts, $threshold);
        
        // Send email to each admin
        $successCount = 0;
        foreach ($admins as $admin) {
            if (sendStockAlertEmail($admin['email'], $admin['username'], $subject, $emailBody)) {
                $successCount++;
            }
        }
        
        // Log stock alert activity
        logStockAlert($lowStockProducts, $outOfStockProducts, $successCount);
        
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("Stock alert error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate HTML email content for stock alert
 */
function generateStockAlertEmail($lowStockProducts, $outOfStockProducts, $threshold) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .header { background: #8B4513; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
            .content { padding: 20px; }
            .alert-section { margin-bottom: 25px; }
            .alert-title { color: #DC3545; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            .warning-title { color: #FFC107; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            .product-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .product-table th, .product-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .product-table th { background-color: #f8f9fa; font-weight: bold; }
            .stock-critical { color: #DC3545; font-weight: bold; }
            .stock-warning { color: #FFC107; font-weight: bold; }
            .footer { background: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🚨 Stock Alert - AiKUN Furniture</h1>
                <p>Immediate attention required for inventory management</p>
            </div>
            <div class="content">
                <p>Dear Admin,</p>
                <p>This is an automated alert regarding low stock levels in your inventory. Please review the following products that require immediate attention:</p>';
    
    // Out of stock products
    if (!empty($outOfStockProducts)) {
        $html .= '
                <div class="alert-section">
                    <div class="alert-title">🔴 OUT OF STOCK (0 items)</div>
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price (RM)</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($outOfStockProducts as $product) {
            $html .= '
                            <tr>
                                <td>' . htmlspecialchars($product['prodID']) . '</td>
                                <td>' . htmlspecialchars($product['name']) . '</td>
                                <td>' . htmlspecialchars($product['category']) . '</td>
                                <td>' . number_format($product['price'], 2) . '</td>
                                <td class="stock-critical">0</td>
                            </tr>';
        }
        
        $html .= '
                        </tbody>
                    </table>
                </div>';
    }
    
    // Low stock products
    if (!empty($lowStockProducts)) {
        $html .= '
                <div class="alert-section">
                    <div class="warning-title">⚠️ LOW STOCK (≤ ' . $threshold . ' items)</div>
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price (RM)</th>
                                <th>Current Stock</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($lowStockProducts as $product) {
            $html .= '
                            <tr>
                                <td>' . htmlspecialchars($product['prodID']) . '</td>
                                <td>' . htmlspecialchars($product['name']) . '</td>
                                <td>' . htmlspecialchars($product['category']) . '</td>
                                <td>' . number_format($product['price'], 2) . '</td>
                                <td class="stock-warning">' . $product['qty'] . '</td>
                            </tr>';
        }
        
        $html .= '
                        </tbody>
                    </table>
                </div>';
    }
    
    $html .= '
                <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    <strong>📋 Recommended Actions:</strong>
                    <ul>
                        <li>Review and replenish out-of-stock items immediately</li>
                        <li>Consider reordering low-stock products</li>
                        <li>Update product availability on your website</li>
                        <li>Notify sales team about inventory limitations</li>
                    </ul>
                </div>
                
                <p style="margin-top: 20px;">
                    Best regards,<br>
                    <strong>AiKUN Furniture Inventory System</strong>
                </p>
            </div>
            <div class="footer">
                This is an automated message sent on ' . date('F j, Y \a\t g:i A') . ' (Malaysia Time)<br>
                Please do not reply to this email. Contact your system administrator for assistance.
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send individual stock alert email using PHPMailer
 */
function sendStockAlertEmail($toEmail, $toName, $subject, $htmlBody) {
    require_once __DIR__ . '/lib/PHPMailer.php';
    require_once __DIR__ . '/lib/SMTP.php';
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aikun.furniture@gmail.com'; // Your Gmail address
        $mail->Password = 'your-app-password'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('aikun.furniture@gmail.com', 'AiKUN Furniture System');
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        
        // Alternative plain text version
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Stock alert email error for {$toEmail}: " . $e->getMessage());
        return false;
    }
}

/**
 * Log stock alert activity
 */
function logStockAlert($lowStockProducts, $outOfStockProducts, $emailsSent) {
    $logMessage = sprintf(
        "[STOCK ALERT] %s - Low Stock: %d products, Out of Stock: %d products, Emails sent: %d",
        date('Y-m-d H:i:s'),
        count($lowStockProducts),
        count($outOfStockProducts),
        $emailsSent
    );
    error_log($logMessage);
}

/**
 * Main function to run stock monitoring (can be called via cron or manually)
 * @param int $threshold - Stock threshold level
 * @return array - Report of the stock check
 */
function runStockMonitoring($threshold = 5) {
    $stockData = checkLowStockProducts($threshold);
    $emailSent = false;
    
    // Only send email if there are low stock or out of stock products
    // TEMPORARILY DISABLED for testing - enable after email configuration
    if (false && (!empty($stockData['low_stock']) || !empty($stockData['out_of_stock']))) {
        $emailSent = sendLowStockAlert($stockData);
    }
    
    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'low_stock_count' => count($stockData['low_stock']),
        'out_of_stock_count' => count($stockData['out_of_stock']),
        'email_sent' => $emailSent,
        'threshold' => $threshold,
        'products' => $stockData
    ];
}

function sendBanNotificationEmail($user_email, $user_name, $reason = '') {
    try {
        $mail = get_mail();
        $mail->addAddress($user_email, $user_name);
        $mail->Subject = 'Account Suspension Notice - AiKUN Furniture';
        
        // Add company logo as embedded image
        $logo_path = __DIR__ . '/images/logo.png';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'company_logo', 'logo.png');
        }
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:company_logo' alt='AiKUN Furniture' style='max-width: 150px; height: auto; margin-bottom: 15px;'>
                    <h1 style='color: #dc3545; margin: 0;'>⚠️ Account Suspended</h1>
                    <p style='color: #666; margin: 10px 0;'>AiKUN Furniture</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                    <h2 style='color: #333; margin-top: 0;'>Dear " . htmlspecialchars($user_name) . ",</h2>
                    
                    <p>We regret to inform you that your account has been suspended due to a violation of our terms of service.</p>
                    
                    " . (!empty($reason) ? "
                    <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0;'>
                        <strong>Reason for suspension:</strong><br>
                        " . htmlspecialchars($reason) . "
                    </div>
                    " : "") . "
                    
                    <p><strong>What this means:</strong></p>
                    <ul>
                        <li>You will no longer be able to access your account</li>
                        <li>All pending orders have been cancelled</li>
                        <li>Your account data will be retained for security purposes</li>
                    </ul>
                    
                    <p>If you believe this suspension is in error, please contact our support team immediately.</p>
                </div>
                
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <h3 style='color: #007bff; margin-top: 0;'>Contact Support</h3>
                    <p>Email: support@aikunfurniture.com<br>
                    Phone: +60 12-345-6789<br>
                    Hours: Monday - Friday, 9:00 AM - 6:00 PM</p>
                </div>
                
                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <p style='color: #666; font-size: 12px; margin: 0;'>
                        This is an automated notification. Please do not reply to this email directly.
                    </p>
                    <p style='color: #666; font-size: 12px; margin: 5px 0 0 0;'>
                        © " . date('Y') . " AiKUN Furniture. All rights reserved.
                    </p>
                </div>
            </div>
        ";
        
        $mail->isHTML(true);
        $result = $mail->send();
        
        if ($result) {
            error_log("Ban notification email sent successfully to: " . $user_email);
        } else {
            error_log("Failed to send ban notification email: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Ban notification email error: " . $e->getMessage());
        return false;
    }
}

function sendStaffRemovalEmail($staff_email, $staff_name, $reason = '') {
    try {
        $mail = get_mail();
        $mail->addAddress($staff_email, $staff_name);
        $mail->Subject = 'Staff Account Removal Notice - AiKUN Furniture';
        
        // Add company logo as embedded image
        $logo_path = __DIR__ . '/images/logo.png';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'company_logo', 'logo.png');
        }
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:company_logo' alt='AiKUN Furniture' style='max-width: 150px; height: auto; margin-bottom: 15px;'>
                    <h1 style='color: #dc3545; margin: 0;'>👋 Staff Account Removed</h1>
                    <p style='color: #666; margin: 10px 0;'>AiKUN Furniture</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                    <h2 style='color: #333; margin-top: 0;'>Dear " . htmlspecialchars($staff_name) . ",</h2>
                    
                    <p>We are writing to inform you that your staff account has been removed from our system.</p>
                    
                    " . (!empty($reason) ? "
                    <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0;'>
                        <strong>Reason for removal:</strong><br>
                        " . htmlspecialchars($reason) . "
                    </div>
                    " : "") . "
                    
                    <p><strong>What this means:</strong></p>
                    <ul>
                        <li>You no longer have access to the staff portal</li>
                        <li>All administrative privileges have been revoked</li>
                        <li>Your staff account data has been removed from our system</li>
                    </ul>
                    
                    <p>If you have any questions about this decision, please contact the system administrator.</p>
                </div>
                
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <h3 style='color: #007bff; margin-top: 0;'>Contact Administrator</h3>
                    <p>Email: admin@aikunfurniture.com<br>
                    Phone: +60 12-345-6789<br>
                    Hours: Monday - Friday, 9:00 AM - 6:00 PM</p>
                </div>
                
                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <p style='color: #666; font-size: 12px; margin: 0;'>
                        This is an automated notification. Please do not reply to this email directly.
                    </p>
                    <p style='color: #666; font-size: 12px; margin: 5px 0 0 0;'>
                        © " . date('Y') . " AiKUN Furniture. All rights reserved.
                    </p>
                </div>
            </div>
        ";
        
        $mail->isHTML(true);
        $result = $mail->send();
        
        if ($result) {
            error_log("Staff removal email sent successfully to: " . $staff_email);
        } else {
            error_log("Failed to send staff removal email: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Staff removal email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate voucher code and return voucher details if valid
 * @param string $code Voucher code to validate
 * @param float $order_amount Order amount to check against minimum requirements
 * @return array|false Returns voucher array if valid, false if invalid
 */
function validateVoucher($code, $order_amount = 0) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("
            SELECT * FROM voucher 
            WHERE code = ? 
            AND is_active = 1 
            AND start_date <= CURDATE() 
            AND end_date >= CURDATE()
            AND (usage_limit IS NULL OR current_usage < usage_limit)
        ");
        $stmt->execute([$code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$voucher) {
            return false;
        }
        
        // Check minimum order amount
        if ($order_amount < $voucher['minOrderAmount']) {
            return false;
        }
        
        return $voucher;
        
    } catch (PDOException $e) {
        error_log("Voucher validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate discount amount based on voucher
 * @param array $voucher Voucher details
 * @param float $order_amount Order amount
 * @return float Discount amount
 */
function calculateVoucherDiscount($voucher, $order_amount) {
    $discount = 0;
    
    switch ($voucher['discount_type']) {
        case 'Percentage':
            $discount = ($order_amount * $voucher['value']) / 100;
            // Apply maximum discount limit if set
            if ($voucher['maxDiscountAmount'] && $discount > $voucher['maxDiscountAmount']) {
                $discount = $voucher['maxDiscountAmount'];
            }
            break;
            
        case 'Fixed':
            $discount = $voucher['value'];
            break;
            
        case 'Free Shipping':
            // This would be handled separately in shipping calculation
            $discount = 0;
            break;
    }
    
    return $discount;
}

/**
 * Apply voucher usage (increment current_usage)
 * @param int $voucher_id Voucher ID
 * @return bool Success status
 */
function applyVoucherUsage($voucher_id) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("UPDATE voucher SET current_usage = current_usage + 1 WHERE voucher_id = ?");
        $stmt->execute([$voucher_id]);
        return true;
        
    } catch (PDOException $e) {
        error_log("Voucher usage update error: " . $e->getMessage());
        return false;
    }
}
