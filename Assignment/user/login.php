<?php
include '../_base.php';

$show_captcha = false;

if (is_post()) {
    $login_input = req('login_input');
    $password = req('password');
    $captcha_response = req('captcha');

    if (isIPBlocked()) {
        $_err['general'] = 'Too many failed attempts from this IP. Please try again later.';
    }
    else {
        if (empty($login_input)) {
            $_err['login_input'] = 'Email or username is required';
        }
        else if (!is_valid_login_input($login_input)) {
            $_err['login_input'] = 'Please enter a valid email address or username';
            logFailedLoginAttempt($login_input, 'Invalid email/username format');
        }

        if (empty($password)) {
            $_err['password'] = 'Password is required';
        }

        if (!$_err && isAccountLocked($login_input)) {
            $_err['general'] = 'Account temporarily locked due to multiple failed attempts. Please try again in 15 minutes.';
            logFailedLoginAttempt($login_input, 'Account locked - too many failed attempts');
        }

        $show_captcha = shouldShowCaptcha($login_input);
        if (!$_err && $show_captcha && !verifyCaptcha($captcha_response)) {
            $_err['captcha'] = 'Incorrect security answer. Please try again.';
            logFailedLoginAttempt($login_input, 'CAPTCHA verification failed');
        }

        if (!$_err) {
            $user = authenticateUser($login_input, $password);
            
            if ($user) {
                // Login successful
                loginUser($user);
                
                // Clear failed attempts
                $clear_attempts = $_db->prepare("DELETE FROM failed_attempts WHERE email = ?");
                $clear_attempts->execute([$login_input]);
        
                // Handle remember me
                if (isset($_POST['remember_me'])) {
                    setRememberMeCookie($user->userID); // Use object notation
                }
        
                // Success message
                $display_name = !empty($user->name) ? $user->name : $user->username;
                temp('success', 'Login successful! Welcome back, ' . htmlspecialchars($display_name) . '!');
                
                // Redirect
                $redirect_to = isset($_SESSION['intended_url']) ? $_SESSION['intended_url'] : 'index.php';
                unset($_SESSION['intended_url']);
                redirect($redirect_to);
            } else {
                $_err['password'] = 'Invalid email/username or password';
                logFailedLoginAttempt($login_input, 'Invalid credentials');
            }
        }
    }

    if ($_err && isset($login_input)) {
        $show_captcha = shouldShowCaptcha($login_input);
    }
}

$page_title = 'Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link rel="stylesheet" href="../css/loginRegister.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <button class="back-btn" onclick="window.location.href='index.php'">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1>Welcome Back</h1>
            <p>Please login to your account</p>
        </div>

        <?php if ($success_msg = get_temp('success')): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg = get_temp('error')): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_err['general'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_err['general']); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <div class="form-group">
                <label for="login_input">Email or Username</label>
                <input 
                    type="text" 
                    id="login_input" 
                    name="login_input" 
                    class="form-input <?php echo isset($_err['login_input']) ? 'error' : ''; ?>" 
                    maxlength="100"
                    placeholder="Enter your email or username"
                    value="<?php echo htmlspecialchars(req('login_input')); ?>"
                    required
                    autocomplete="username"
                >
                <?php if (isset($_err['login_input'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['login_input']); ?></div>
                <?php endif; ?>
                <small class="input-help">You can use either your email address or username</small>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input <?php echo isset($_err['password']) ? 'error' : ''; ?>" 
                        maxlength="100"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="eye-icon show fas fa-eye"></i>
                    </button>
                </div>
                <?php if (isset($_err['password'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['password']); ?></div>
                <?php endif; ?>
            </div>

            <?php if ($show_captcha): ?>
            <!-- Security CAPTCHA (shown after failed attempts) -->
            <div class="form-group">
                <label for="captcha">Security Check</label>
                <div class="captcha-container">
                    <div class="captcha-question">
                        <?php echo generateCaptcha(); ?>
                    </div>
                    <input 
                        type="number" 
                        id="captcha" 
                        name="captcha" 
                        class="form-input captcha-input <?php echo isset($_err['captcha']) ? 'error' : ''; ?>" 
                        placeholder="Answer"
                        required
                    >
                </div>
                <?php if (isset($_err['captcha'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['captcha']); ?></div>
                <?php endif; ?>
                <small class="security-notice">🛡️ Security verification required due to previous failed attempts</small>
            </div>
            <?php endif; ?>

            <div class="form-options">
                <label class="checkbox-container">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <span class="checkmark"></span>
                    Remember me
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span>Log In</span>
                    <div class="btn-loading" style="display: none;">
                        <div class="spinner"></div>
                        Logging in...
                    </div>
                </button>
                <button type="reset" class="btn btn-secondary">
                    Clear
                </button>
            </div>
        </form>

        <div class="links">
            <a href="forgot-password.php">Forgot Password?</a>
            <a href="register.php">Create Account</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="/js/loginRegister.js"></script>
</body>
</html>