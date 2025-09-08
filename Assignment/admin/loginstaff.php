<?php
include '../_base.php';

$show_captcha = false;

if (is_post()) {
    $login_input = req('login_input');
    $password = req('password');

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

        if (!$_err) {
            $user = authenticateStaff($login_input, $password);
            
            if ($user) {
                // Login successful
                loginUserStaff($user);

                if ($user->status === 'Inactive') {
                    logoutUser(); // Clear session
                    logFailedLoginAttempt($login_input, 'Attempt to login to inactive account');
                    // Set temp message after logout to avoid session clearing
                    session_start(); // Restart session after logout
                    temp('error', 'Your account is inactive. Please contact support.');
                    redirect('login.php');
                }
                
                // Clear failed attempts
                $clear_attempts = $_db->prepare("DELETE FROM failed_attempts WHERE email = ?");
                $clear_attempts->execute([$login_input]);
                
                // Redirect to admin dashboard
                redirect('adminpage.php');
            } else {
                $_err['password'] = 'Invalid email/username or password';
                logFailedLoginAttempt($login_input, 'Invalid credentials');
            }
        }
    }
}

$page_title = 'Staff Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AiKUN Furniture</title>
    <link rel="stylesheet" href="../css/loginRegister.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <button class="back-btn" onclick="window.location.href='adminpage.php'">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1>Staff Login</h1>
               <p>Access restricted to Staff only</p>
        </div>

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
                <label for="login_input">Email</label>
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

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span>Staff Login</span>
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
            <a href="../user/forgot_password.php?status=1">Forgot Password?</a>
            <a href="../user/login.php">Customer Login</a>
        </div>

        <div class="security-notice">
            <div class="notice-content">
                <h3>Staff Access Only</h3>
                <p>This login is restricted to authorized staff members only</p>
                <p>If you need customer access, please use the <a href="../user/login.php">Customer Login</a>.</p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../js/loginRegister.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
