<?php
include '_base.php';

// Check if user is already logged in
if (isLoggedIn()) {
    temp('success', 'You are already logged in.');
    redirect('index.php');
}

$show_captcha = false;
$step = 'email'; // email, otp, new_password
$email = '';
$reset_token = '';

if (is_post()) {
    $action = req('action') ?? 'request_reset';
    
    switch ($action) {
        case 'request_reset':
            handlePasswordResetRequest();
            break;
            
        case 'verify_otp':
            handleOTPVerification();
            break;
            
        case 'reset_password':
            handlePasswordReset();
            break;
            
        default:
            $_err['general'] = 'Invalid action';
    }
}

function handlePasswordResetRequest() {
    global $_err, $email, $show_captcha, $_db;
    
    $email = trim(req('email'));
    $captcha_response = req('captcha');
    
    // Validate email
    if (empty($email)) {
        $_err['email'] = 'Email address is required';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address';
    } else {
        // Check if email exists in database
        $stmt = $_db->prepare("SELECT userID, name, username FROM user WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$user) {
            $_err['email'] = 'No account found with this email address';
        }
    }
    
    // Check CAPTCHA if needed
    $show_captcha = shouldShowCaptcha($email);
    if (!$_err && $show_captcha && !verifyCaptcha($captcha_response)) {
        $_err['captcha'] = 'Incorrect security answer. Please try again.';
    }
    
    if (!$_err) {
        // Generate 6-digit OTP
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store SHA1 hash of OTP in database for security (user receives readable OTP)
        $reset_token_hash = sha1($otp);
        $stmt = $_db->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()");
        $stmt->execute([$email, $reset_token_hash, $expires_at, $reset_token_hash, $expires_at]);
        
        // Send reset email with readable 6-digit OTP (not the hash)
        if (sendPasswordResetEmail($email, $otp, $user->name ?? $user->username)) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_step'] = 'otp';
            temp('success', 'Password reset instructions have been sent to your email address.');
            redirect('forgot-password.php');
        } else {
            $_err['general'] = 'Failed to send reset email. Please try again.';
        }
    }
}

function handleOTPVerification() {
    global $_err, $email, $_db;
    
    $email = $_SESSION['reset_email'] ?? '';
    $otp = req('otp');
    
    if (empty($email)) {
        $_err['general'] = 'Invalid reset session. Please start over.';
        return;
    }
    
    if (empty($otp)) {
        $_err['otp'] = 'Please enter the verification code';
    } else {
        // Hash the OTP input to compare with stored SHA1 hash
        $otp_hash = sha1($otp);
        
        // Verify OTP hash from database
        $stmt = $_db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email, $otp_hash]);
        $reset_record = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$reset_record) {
            $_err['otp'] = 'Invalid or expired verification code';
        } else {
            $_SESSION['reset_token'] = $otp_hash;
            $_SESSION['reset_step'] = 'new_password';
            redirect('forgot-password.php');
        }
    }
}

function handlePasswordReset() {
    global $_err, $email, $_db;
    
    $email = $_SESSION['reset_email'] ?? '';
    $reset_token = $_SESSION['reset_token'] ?? '';
    $new_password = req('new_password');
    $confirm_password = req('confirm_password');
    
    if (empty($email) || empty($reset_token)) {
        $_err['general'] = 'Invalid reset session. Please start over.';
        return;
    }
    
    // Validate password
    if (empty($new_password)) {
        $_err['new_password'] = 'New password is required';
    } elseif (strlen($new_password) < 8) {
        $_err['new_password'] = 'Password must be at least 8 characters';
    } elseif (strlen($new_password) > 128) {
        $_err['new_password'] = 'Password must be less than 128 characters';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $_err['new_password'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $_err['new_password'] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $_err['new_password'] = 'Password must contain at least one number';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $_err['new_password'] = 'Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    }
    
    if (empty($confirm_password)) {
        $_err['confirm_password'] = 'Please confirm your new password';
    } elseif ($new_password !== $confirm_password) {
        $_err['confirm_password'] = 'Passwords do not match';
    }
    
    if (!$_err) {
        // Verify reset token hash again
        $stmt = $_db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email, $reset_token]);
        $reset_record = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$reset_record) {
            $_err['general'] = 'Invalid or expired reset token. Please start over.';
            return;
        }
        
        // Check if new password is different from current password
        $stmt = $_db->prepare("SELECT password FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $current_user = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($current_user && password_verify($new_password, $current_user->password)) {
            $_err['new_password'] = 'New password must be different from your current password';
            return;
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $_db->prepare("UPDATE user SET password = ?, updated_at = NOW() WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);
        
        // Mark reset token as used
        $stmt = $_db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?");
        $stmt->execute([$reset_record->id]);
        
        // Clear all remember tokens for security
        $stmt = $_db->prepare("SELECT userID FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);
        if ($user) {
            clearAllRememberTokens($user->userID);
        }
        
        // Log activity
        if ($user) {
            logProfileActivity($user->userID, 'password_reset', [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'method' => 'forgot_password'
            ], $_db);
        }
        
        // Clear session
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_token']);
        
        temp('success', 'Password has been reset successfully! You can now login with your new password.');
        redirect('login.php');
    }
}

function sendPasswordResetEmail($email, $token, $name) {
    try {
        require_once 'lib/PHPMailer.php';
        require_once 'lib/SMTP.php';
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Username = 'zhtan392@gmail.com';
        $mail->Password = 'qrcg ijnw qggs ipok';
        $mail->CharSet = 'utf-8';
        
        // Set sender and recipient
        $mail->setFrom($mail->Username, 'AiKUN Furniture');
        $mail->addAddress($email, $name);
        
        // Set email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - AiKUN Furniture';
        
        // Email body
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #8B4513;'>Password Reset Request</h2>
            <p>Hello " . htmlspecialchars($name) . ",</p>
            <p>We received a request to reset your password for your AiKUN Furniture account.</p>
            <p>Your 6-digit password reset verification code is: <strong style='font-size: 24px; color: #D4AF37; letter-spacing: 3px;'>" . $token . "</strong></p>
            <p><em>Please enter this 6-digit code in the verification form.</em></p>
            <p>This code will expire in 1 hour for security reasons.</p>
            <p>If you didn't request this password reset, please ignore this email and your password will remain unchanged.</p>
            <p>Best regards,<br>AiKUN Furniture Team</p>
        </div>";
        
        // Plain text version
        $mail->AltBody = "Password Reset Request\n\nHello " . $name . ",\n\nWe received a request to reset your password for your AiKUN Furniture account.\n\nYour 6-digit password reset verification code is: " . $token . "\n\nPlease enter this 6-digit code in the verification form.\n\nThis code will expire in 1 hour for security reasons.\n\nIf you didn't request this password reset, please ignore this email and your password will remain unchanged.\n\nBest regards,\nAiKUN Furniture Team";
        
        // Send the email
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Password reset email error: " . $e->getMessage());
        return false;
    }
}

// Get current step from session
$step = $_SESSION['reset_step'] ?? 'email';
$email = $_SESSION['reset_email'] ?? '';

$page_title = 'Forgot Password';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link rel="stylesheet" href="css/loginRegister.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="login-container">
                <div class="form-header">
                    <h1><i class="fas fa-key"></i> Forgot Password</h1>
                    <p>Reset your password to access your account</p>
                </div>

                <?php if ($success_msg = get_temp('success')): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_err['general'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_err['general']); ?>
                    </div>
                <?php endif; ?>

                <!-- Progress Bar -->
                <div class="progress-bar">
                    <div class="progress-step <?php echo $step === 'email' ? 'active' : ''; ?>">1</div>
                    <div class="progress-line <?php echo $step === 'otp' || $step === 'new_password' ? 'active' : ''; ?>"></div>
                    <div class="progress-step <?php echo $step === 'otp' ? 'active' : ''; ?>">2</div>
                    <div class="progress-line <?php echo $step === 'new_password' ? 'active' : ''; ?>"></div>
                    <div class="progress-step <?php echo $step === 'new_password' ? 'active' : ''; ?>">3</div>
                </div>

                <!-- Step 1: Email Input -->
                <?php if ($step === 'email'): ?>
                    <form method="POST" id="reset-form">
                        <input type="hidden" name="action" value="request_reset">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="step-content">
                            <h3><i class="fas fa-envelope"></i> Enter Your Email</h3>
                            <p>We'll send you a verification code to reset your password</p>
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input <?php echo isset($_err['email']) ? 'error' : ''; ?>" 
                                placeholder="Enter your email address"
                                value="<?php echo htmlspecialchars($email); ?>"
                                required
                            >
                            <?php if (isset($_err['email'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['email']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($show_captcha): ?>
                            <div class="captcha-container">
                                <div class="captcha-question">
                                    <?php echo generateCaptcha(); ?>
                                </div>
                                <input 
                                    type="text" 
                                    name="captcha" 
                                    class="form-input captcha-input" 
                                    placeholder="Answer"
                                    required
                                >
                            </div>
                            <?php if (isset($_err['captcha'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['captcha']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Reset Code
                            </button>
                            <a href="login.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Step 2: OTP Verification -->
                <?php if ($step === 'otp'): ?>
                    <form method="POST" id="otp-form">
                        <input type="hidden" name="action" value="verify_otp">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="step-content">
                            <h3><i class="fas fa-shield-alt"></i> Verify Your Email</h3>
                            <p>We've sent a verification code to <strong><?php echo htmlspecialchars($email); ?></strong></p>
                        </div>

                        <div class="form-group">
                            <label for="otp">
                                <i class="fas fa-key"></i> Verification Code
                            </label>
                            <input 
                                type="text" 
                                id="otp" 
                                name="otp" 
                                class="form-input <?php echo isset($_err['otp']) ? 'error' : ''; ?>" 
                                placeholder="Enter the 6-digit code"
                                maxlength="6"
                                required
                            >
                            <?php if (isset($_err['otp'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['otp']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Verify Code
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resendCode()">
                                <i class="fas fa-redo"></i> Resend Code
                            </button>
                        </div>

                        <div class="links">
                            <a href="forgot-password.php">Start Over</a>
                            <a href="login.php">Back to Login</a>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Step 3: New Password -->
                <?php if ($step === 'new_password'): ?>
                    <form method="POST" id="new-password-form">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="step-content">
                            <h3><i class="fas fa-lock"></i> Create New Password</h3>
                            <p>Choose a strong password for your account</p>
                        </div>

                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-lock"></i> New Password
                            </label>
                            <div class="password-input-container">
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="form-input <?php echo isset($_err['new_password']) ? 'error' : ''; ?>" 
                                    placeholder="Enter new password"
                                    required
                                >
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                    <i class="eye-icon show fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($_err['new_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['new_password']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="password-requirements">
                                <small>Password must contain:</small>
                                <ul>
                                    <li id="req-length">8-128 characters</li>
                                    <li id="req-uppercase">One uppercase letter</li>
                                    <li id="req-lowercase">One lowercase letter</li>
                                    <li id="req-number">One number</li>
                                    <li id="req-special">One special character (!@#$%^&*()_+-=[]{}|;:,.<>?)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-check"></i> Confirm New Password
                            </label>
                            <div class="password-input-container">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-input <?php echo isset($_err['confirm_password']) ? 'error' : ''; ?>" 
                                    placeholder="Confirm new password"
                                    required
                                >
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="eye-icon show fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($_err['confirm_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['confirm_password']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Reset Password
                            </button>
                        </div>

                        <div class="links">
                            <a href="forgot-password.php">Start Over</a>
                            <a href="login.php">Back to Login</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggleButton = input.parentNode.querySelector('.password-toggle');
            const eyeIcon = toggleButton.querySelector('.eye-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.classList.remove('show', 'fas', 'fa-eye');
                eyeIcon.classList.add('hide', 'fas', 'fa-eye-slash');
                eyeIcon.classList.add('state-change');
                setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
            } else {
                input.type = 'password';
                eyeIcon.classList.remove('hide', 'fas', 'fa-eye-slash');
                eyeIcon.classList.add('show', 'fas', 'fa-eye');
                eyeIcon.classList.add('state-change');
                setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
            }
        }

        // Password strength validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    validatePassword(this.value);
                });
            }
        });

        function validatePassword(password) {
            const requirements = {
                length: password.length >= 8 && password.length <= 128,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            // Update requirement indicators
            document.getElementById('req-length').className = requirements.length ? 'valid' : 'invalid';
            document.getElementById('req-uppercase').className = requirements.uppercase ? 'valid' : 'invalid';
            document.getElementById('req-lowercase').className = requirements.lowercase ? 'valid' : 'invalid';
            document.getElementById('req-number').className = requirements.number ? 'valid' : 'invalid';
            document.getElementById('req-special').className = requirements.special ? 'valid' : 'invalid';
        }

        // Resend code functionality
        function resendCode() {
            const form = document.getElementById('otp-form');
            const formData = new FormData();
            formData.append('action', 'request_reset');
            formData.append('email', '<?php echo htmlspecialchars($email); ?>');
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

            fetch('forgot-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    alert('Verification code has been resent to your email.');
                } else {
                    alert('Failed to resend code. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('.form-input');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>
