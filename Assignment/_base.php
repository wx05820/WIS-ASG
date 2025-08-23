<?php
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
    $_db = new PDO('mysql:dbname=aikun', 'root', '', [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

$_err = [];

$public_pages = ['login.php', 'register.php', 'forgot-password.php', 'index.php'];
if (!isLoggedIn() && !in_array(basename($_SERVER['PHP_SELF']), $public_pages)) {
    $autoLoginUser = checkRememberMeToken();
    if ($autoLoginUser) {
        $display_name = !empty($autoLoginUser->name) ? $autoLoginUser->name : $autoLoginUser->username;
        temp('info', 'Welcome back, ' . htmlspecialchars($display_name) . '!');
    }
}

function authenticateUser($loginInput, $password) {
    global $_db;
    try {
        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            $stm = $_db->prepare('SELECT * FROM user WHERE email = ?');
        } else {
            $stm = $_db->prepare('SELECT * FROM user WHERE username = ?');
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
        error_log("Authentication error: " . $e->getMessage());
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
        'login_time' => time(),
        'logged_in' => true,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    try {
        $forceUpdate = $_db->prepare("UPDATE user SET last_login = NOW() WHERE userID = ?");
        $forceUpdate->execute([$user->userID]);
    } catch (Exception $e) {
        error_log("Force update failed: " . $e->getMessage());
    }
    return true;
}

function isLoggedIn() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) return false;
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
        logoutUser();
        return false;
    }
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
        error_log("Get current user error: " . $e->getMessage());
        return false;
    }
}

function logoutUser() {
    clearRememberMeCookie();
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
        $stm = $_db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stm->execute([$user_id, $hashed_token, date('Y-m-d H:i:s', $expires)]);
        setcookie('remember_token', $token, $expires, '/', '', isset($_SERVER['HTTPS']), true);
        return true;
    } catch (Exception $e) {
        error_log("Set remember me cookie error: " . $e->getMessage());
        return false;
    }
}

function checkRememberMeToken() {
    global $_db;
    if (!isset($_COOKIE['remember_token'])) return false;
    $token = $_COOKIE['remember_token'];
    $hashed_token = hash('sha256', $token);
    try {
        $stmt = $_db->prepare("SELECT rt.user_id, rt.expires_at, u.* FROM remember_tokens rt JOIN user u ON rt.user_id = u.userID WHERE rt.token = ? AND rt.expires_at > NOW()");
        $stmt->execute([$hashed_token]);
        $result = $stmt->fetch();
        if ($result) {
            loginUser($result);
            refreshRememberToken($result->user_id, $hashed_token);
            return $result;
        } else {
            clearRememberMeCookie();
            return false;
        }
    } catch (Exception $e) {
        error_log("Remember me token check error: " . $e->getMessage());
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
        $stmt = $_db->prepare("UPDATE remember_tokens SET token = ?, expires_at = ? WHERE user_id = ? AND token = ?");
        $stmt->execute([$newHashedToken, $expires, $userID, $oldHashedToken]);
        $cookie_expires = time() + (30 * 24 * 60 * 60);
        setcookie('remember_token', $newToken, $cookie_expires, '/', '', isset($_SERVER['HTTPS']), true);
        return true;
    } catch (Exception $e) {
        error_log("Remember token refresh error: " . $e->getMessage());
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
            error_log("Clear remember token error: " . $e->getMessage());
        }
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

function clearAllRememberTokens($userID) {
    global $_db;
    try {
        $stmt = $_db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$userID]);
        return true;
    } catch (Exception $e) {
        error_log("Clear all remember tokens error: " . $e->getMessage());
        return false;
    }
}

function cleanupExpiredTokens() {
    global $_db;
    try {
        $stmt = $_db->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Cleanup expired tokens error: " . $e->getMessage());
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
        $stm = $_db->prepare("INSERT INTO failed_attempts (email, ip_address, reason, created_at) VALUES (?, ?, ?, NOW())");
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
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number';
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
    $profilePhotos = [
        'profilePhoto/profile1.jpg',
        'profilePhoto/profile2.jpg',
        'profilePhoto/profile3.jpg',
        'profilePhoto/profile4.jpg',
        'profilePhoto/profile5.jpg',
        'profilePhoto/profile6.jpg',
        'profilePhoto/profile7.jpg',
    ];
    $availablePhotos = array_filter($profilePhotos, function($photo) {
        return file_exists($photo);
    });
    if (!empty($availablePhotos)) {
        $randomIndex = array_rand($availablePhotos);
        return $availablePhotos[$randomIndex];
    } else {
        return 'profilePhoto/default.jpg';
    }
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


?>