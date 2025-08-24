<?php

include '_base.php';

function sendOTPEmail($email, $otp) {
    try {
        // Use your existing get_mail() function
        $mail = get_mail();
        
        // Set recipient
        $mail->addAddress($email);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'AiKUN Furniture - Registration Verification';
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd;'>
            <div style='background-color: #007bff; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>🪑 AiKUN Furniture</h2>
                <h3 style='margin: 10px 0 0 0;'>Email Verification</h3>
            </div>
            <div style='padding: 30px; background-color: #f8f9fa;'>
                <p style='font-size: 16px; color: #333;'>Hello,</p>
                <p style='font-size: 16px; color: #333;'>Thank you for registering with AiKUN Furniture! Please use the following verification code to complete your registration:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='background-color: #007bff; color: white; font-size: 32px; font-weight: bold; padding: 20px; display: inline-block; letter-spacing: 5px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
                        $otp
                    </div>
                </div>
                
                <p style='font-size: 16px; color: #d63384;'><strong>⏰ This code will expire in 10 minutes.</strong></p>
                <p style='font-size: 14px; color: #666;'>If you didn't request this verification, please ignore this email.</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='color: #999; font-size: 12px; text-align: center;'>
                    This is an automated email from AiKUN Furniture. Please do not reply to this message.
                </p>
            </div>
        </div>";
        
        // Plain text version for email clients that don't support HTML
        $mail->AltBody = "AiKUN Furniture - Email Verification\n\n" .
                        "Your verification code is: $otp\n\n" .
                        "This code will expire in 10 minutes.\n\n" .
                        "If you didn't request this verification, please ignore this email.\n\n" .
                        "Thank you for choosing AiKUN Furniture!";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if (is_post()) {
    // Check if IP is blocked first
    if (isIPBlocked()) {
        $_err['general'] = 'Too many failed attempts. Please try again later.';
    }
    else if ($step == 1) {
        // Step 1: Email validation and OTP sending
        $email = req('email');
        $captcha_response = req('captcha');
        $is_repeat = isRepeatOTPRequest($email);
        
        // Verify CAPTCHA first
        if (!verifyCaptcha($captcha_response)) {
            $_err['captcha'] = 'Incorrect answer. Please try again.';
            logFailedAttempt($email, 'captcha_failed', 'Math captcha failed');
        }
        
        // Validate email
        if ($email == '') {
            $_err['email'] = 'Required';
        }
        else if (!is_email($email)) {
            $_err['email'] = 'Invalid email';
            logFailedAttempt($email, 'invalid_email', 'Invalid email format');
        }
        else {
            // Check if email already exists
            $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE email = ?');
            $stm->execute([$email]);
            if ($stm->fetchColumn()) {
                $_err['email'] = 'Email already registered';
                logFailedAttempt($email, 'duplicate_email', 'Email already exists');
            }
        }
        
        // Check rate limiting
        if (!$_err && !checkRateLimit($email)) {
            $_err['email'] = 'Too many OTP requests. Please wait before requesting again.';
            logFailedAttempt($email, 'rate_limit', 'Exceeded OTP rate limit');
        }
        
        if (!$_err) {
            // Generate OTP
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in session or temporary table
            $_SESSION['registration_email'] = $email;
            $_SESSION['registration_otp'] = $otp;
            $_SESSION['registration_otp_expiry'] = $otp_expiry;
            $_SESSION['last_otp_email'] = $email;
            
            // Send OTP email
            if (sendOTPEmail($email, $otp)) {
                // Log successful OTP request
                logOTPRequest($email, true);

                if($is_repeat) {
                    $_SESSION['temp_success'] = 'New verification code sent to your email.';                
                }

                redirect('register.php?step=2');

            } else {
                $_err['email'] = 'Failed to send OTP. Please try again.';
                logOTPRequest($email, false);
                logFailedAttempt($email, 'email_failed', 'Failed to send OTP email');
            }
        }
    } else if ($step == 2) {
    $email = $_SESSION['registration_email'] ?? '';

    // Ensure we actually have an email from Step 1
    if (!$email) {
        $_SESSION['temp_error'] = 'Session expired. Please start again.';
        redirect('register.php?step=1');
    }

    // If the user clicked "Resend OTP"
    if (isset($_POST['resend'])) {
        if (checkRateLimit($email)) {
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $_SESSION['registration_otp'] = $otp;
            $_SESSION['registration_otp_expiry'] = $otp_expiry;

            if (sendOTPEmail($email, $otp)) {
                logOTPRequest($email, true);
                $_SESSION['temp_success'] = 'A new verification code has been sent.';
            } else {
                $_SESSION['temp_error'] = 'Failed to resend the verification code. Please try again.';
            }
        } else {
            $_SESSION['temp_error'] = 'Too many OTP requests. Please wait before trying again.';
        }
        redirect('register.php?step=2');
    }

    // Handle OTP verification
    $otp_input = '';
    
    // Check if OTP is submitted as array (from individual inputs) or single field
    if (isset($_POST['otp'])) {
        if (is_array($_POST['otp'])) {
            // Join individual digit inputs
            $otp_input = implode('', array_map('trim', $_POST['otp']));
        } else {
            $otp_input = trim($_POST['otp']);
        }
    }

    if ($otp_input == '') {
        $_err['otp'] = 'Required';
    } elseif (!isset($_SESSION['registration_otp'])) {
        $_err['otp'] = 'Session expired. Please start again.';
        logFailedAttempt($email, 'session_expired', 'OTP session expired');
    } elseif (strtotime($_SESSION['registration_otp_expiry']) < time()) {
        $_err['otp'] = 'OTP expired. Please start again.';
        logFailedAttempt($email, 'otp_expired', 'OTP code expired');
        unset($_SESSION['registration_email'], $_SESSION['registration_otp'], $_SESSION['registration_otp_expiry']);
        redirect('register.php?step=1');
    } elseif ($otp_input != $_SESSION['registration_otp']) {
        $_err['otp'] = 'Invalid OTP';
        logFailedAttempt($email, 'invalid_otp', 'Incorrect OTP entered');
        
        // Debug info (remove in production)
        error_log("OTP Debug - Input: '$otp_input', Session: '{$_SESSION['registration_otp']}'");
    }

    // If OTP verified
        if (empty($_err)) {
            redirect('register.php?step=3');
        }
    }
    else if ($step == 3) {
    $email = $_SESSION['registration_email'] ?? '';
    $password = req('password');
    $confirm_password = req('confirm_password');
    
    if (!$email) {
        $_SESSION['temp_error'] = 'Session expired. Please start again.';
        redirect('register.php?step=1');
    }
    
    // Password validation
    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 8) {
        $_err['password'] = 'Password must be at least 8 characters';
    }
    else if (!preg_match('/[A-Z]/', $password)) {
        $_err['password'] = 'Password must contain at least one uppercase letter';
    }
    else if (!preg_match('/[a-z]/', $password)) {
        $_err['password'] = 'Password must contain at least one lowercase letter';
    }
    else if (!preg_match('/[0-9]/', $password)) {
        $_err['password'] = 'Password must contain at least one number';
    }
    
    if ($confirm_password == '') {
        $_err['confirm_password'] = 'Required';
    }
    else if ($password != $confirm_password) {
        $_err['confirm_password'] = 'Passwords do not match';
    }
    
    if (!$_err) {
        // Generate random username
        do {
            $username = generateRandomUsername();
            $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE username = ?');
            $stm->execute([$username]);
        } while ($stm->fetchColumn() > 0);

        // Get random profile photo
        $randomProfilePhoto = getRandomProfilePhoto();
    
        // Hash the password before storing
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Register user with profile photo
        $stm = $_db->prepare('
            INSERT INTO user (username, email, password, photo, role, created_at)   
            VALUES (?, ?, ?, ?, "Customer", NOW())
        ');
        $stm->execute([$username, $email, $password_hash, $randomProfilePhoto, $role]);
        
        if ($stm->rowCount()) {
            // Clear session data
            unset($_SESSION['registration_email'], $_SESSION['registration_otp'], $_SESSION['registration_otp_expiry']);
            
            // Set success message and redirect to login
            $_SESSION['temp_success'] = 'Registration successful! You can now log in with your credentials.';
            redirect('login.php');
            
        } else {
            $_err['general'] = 'Registration failed. Please try again.';
            logFailedAttempt($email, 'registration_failed', 'Database insert failed');
        }
    }
}
}

// Set page title based on step
$page_titles = [
    1 => 'Register - Step 1: Email',
    2 => 'Register - Step 2: Verify OTP', 
    3 => 'Register - Step 3: Create Password'
];
$page_title = $page_titles[$step] ?? 'Register';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/loginRegister.css">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create Your Account</h1>
            <p>Step <?php echo $step; ?> of 3</p>
            
            <!-- Progress bar -->
            <div class="progress-bar">
                <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="progress-line <?php echo $step >= 2 ? 'active' : ''; ?>"></div>
                <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
                <div class="progress-line <?php echo $step >= 3 ? 'active' : ''; ?>"></div>
                <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
            </div>
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
            <?php if ($step == 1): ?>
                <!-- Step 1: Email Input -->
                <div class="step-content">
                    <h3>Enter Your Email Address</h3>
                    <p>We'll send you a verification code</p>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input <?php echo isset($_err['email']) ? 'error' : ''; ?>" 
                            maxlength="100"
                            placeholder="Enter your email"
                            value="<?php echo htmlspecialchars(req('email')); ?>"
                            required
                        >
                        <?php if (isset($_err['email'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_err['email']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Math CAPTCHA -->
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
                    </div>
                    
                    <div class="security-notice">
                        <p><i>🛡️ Rate limited: Maximum 3 OTP requests per hour</i></p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Send OTP
                    </button>
                </div>
                
            <?php elseif ($step == 2): ?>
    <!-- Step 2: OTP Verification -->
    <div class="step-content">
        <h3>Verify Your Email</h3>
        <p>Enter the 6-digit code sent to 
            <strong><?php echo htmlspecialchars($_SESSION['registration_email'] ?? ''); ?></strong>
        </p>

        <div class="form-group">
            <label>Verification Code</label>
            <div class="otp-container">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input 
                        type="text" 
                        name="otp[]" 
                        class="form-input otp-input <?php echo isset($_err['otp']) ? 'error' : ''; ?>" 
                        maxlength="1" 
                        pattern="[0-9]"
                        data-index="<?php echo $i; ?>"
                        required
                    >
                <?php endfor; ?>
            </div>
            <?php if (isset($_err['otp'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($_err['otp']); ?></div>
            <?php endif; ?>
        </div>

        <div class="otp-info">
            <p class="countdown" id="countdown">
                Code expires in: <span id="timer"></span>
            </p>
        </div>
    </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" name="verify">
                        Verify Code
                    </button>
                    <button type="submit" class="btn btn-secondary" name="resend">
                        Resend Code
                    </button>
                    <a href="register.php?step=1" class="btn btn-light">Start Over</a>
                </div>
                
            <?php elseif ($step == 3): ?>
                <!-- Step 3: Set Password -->
                <div class="step-content">
                    <h3>Create Your Password</h3>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input-container">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input <?php echo isset($_err['password']) ? 'error' : ''; ?>"
                                required
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <span id="toggle-text-1">Show</span>
                            </button>
                        </div>
                        <div id="password-strength">
                            <div id="strength-bar"></div>
                        </div>
                        <small id="strength-text"></small>
                        <?php if (isset($_err['password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_err['password']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-input-container">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input <?php echo isset($_err['confirm_password']) ? 'error' : ''; ?>"
                                required
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <span id="toggle-text-2">Show</span>
                            </button>
                        </div>
                        <?php if (isset($_err['confirm_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_err['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Password Requirements -->
                    <ul id="password-requirements" class="password-requirements">
                        <li id="length" class="invalid">❌ At least 8 characters</li>
                        <li id="uppercase" class="invalid">❌ At least one uppercase letter</li>
                        <li id="lowercase" class="invalid">❌ At least one lowercase letter</li>
                        <li id="number" class="invalid">❌ At least one number</li>
                        <li id="match" class="invalid">❌ Passwords match</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" name="finish">Complete Registration</button>
                </div>

            <?php endif; ?>
        </form>

        <div class="links">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="js/loginRegister.js"></script>
    
    <?php if ($step == 2 && isset($_SESSION['registration_otp_expiry'])): ?>
    <script>
        // Countdown timer for OTP expiry
        const expiryTime = new Date('<?php echo $_SESSION['registration_otp_expiry']; ?>').getTime();
        
        const countdown = setInterval(function() {
            const now = new Date().getTime();
            const distance = expiryTime - now;
            
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('timer').innerHTML = minutes + "m " + seconds + "s ";
            
            if (distance < 0) {
                clearInterval(countdown);
                document.getElementById('timer').innerHTML = "EXPIRED";
                document.querySelector('.btn-primary').disabled = true;
                document.querySelector('.btn-primary').innerHTML = "Code Expired";
            }
        }, 1000);
    </script>
    <?php endif; ?>

    <?php if ($step == 3): ?>
    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');

        const requirements = {
            length: document.getElementById('length'),
            uppercase: document.getElementById('uppercase'),
            lowercase: document.getElementById('lowercase'),
            number: document.getElementById('number'),
            match: document.getElementById('match'),
        };

        function checkPasswordRequirements() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            // Check requirements
            const lengthValid = password.length >= 8;
            const uppercaseValid = /[A-Z]/.test(password);
            const lowercaseValid = /[a-z]/.test(password);
            const numberValid = /[0-9]/.test(password);
            const matchValid = password === confirm && password !== '';

            // Update UI
            updateRequirement(requirements.length, lengthValid);
            updateRequirement(requirements.uppercase, uppercaseValid);
            updateRequirement(requirements.lowercase, lowercaseValid);
            updateRequirement(requirements.number, numberValid);
            updateRequirement(requirements.match, matchValid);

            // Update strength bar
            let strength = 0;
            if (lengthValid) strength++;
            if (uppercaseValid) strength++;
            if (lowercaseValid) strength++;
            if (numberValid) strength++;

            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            const colors = ["red", "orange", "yellow", "lightgreen", "green"];
            const texts = ["Very Weak", "Weak", "Fair", "Good", "Strong"];

            strengthBar.style.width = (strength * 25) + "%";
            strengthBar.style.background = colors[strength];
            strengthText.textContent = texts[strength];
        }

        function updateRequirement(element, isValid) {
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                element.textContent = '✅ ' + element.textContent.replace(/✅ |❌ /, '');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                element.textContent = '❌ ' + element.textContent.replace(/✅ |❌ /, '');
            }
        }

        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleText = document.getElementById(fieldId === 'password' ? 'toggle-text-1' : 'toggle-text-2');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleText.textContent = 'Hide';
            } else {
                passwordField.type = 'password';
                toggleText.textContent = 'Show';
            }
        }

        passwordInput.addEventListener('input', checkPasswordRequirements);
        confirmInput.addEventListener('input', checkPasswordRequirements);
    </script>
    <?php endif; ?>
</body>
</html>