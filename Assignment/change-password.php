<?php
require_once '_base.php';

// Check if user is logged in
if (!isLoggedIn()) {
    temp('error', 'Please log in to change your password.');
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if (is_post()) {
    $current_password = req('current_password');
    $new_password = req('new_password');
    $confirm_password = req('confirm_password');
    
    // Validation
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }
    
    if (empty($confirm_password)) {
        $errors[] = "Please confirm your new password.";
    }
    
    // Validate new password strength
    if (!empty($new_password)) {
        $passwordValidation = validatePasswordStrength($new_password);
        if ($passwordValidation !== true) {
            $errors[] = $passwordValidation;
        }
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    // Verify current password
    if (!empty($current_password)) {
        try {
            $stmt = $_db->prepare("SELECT password FROM user WHERE userID = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user->password)) {
                $errors[] = "Current password is incorrect.";
                logFailedAttempt($_SESSION['email'], 'password_change', 'Incorrect current password');
            }
        } catch (PDOException $e) {
            error_log("Password verification error: " . $e->getMessage());
            $errors[] = "Error verifying current password.";
        }
    }
    
    // If no errors, update the password
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $_db->prepare("UPDATE user SET password = ? WHERE userID = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Clear all remember me tokens for security
            clearAllRememberTokens($user_id);
            
            $success_message = "Password changed successfully! For security, you've been logged out of all other devices.";
            
            // Log successful password change
            error_log("Password changed successfully for user ID: " . $user_id);
            
        } catch(PDOException $e) {
            error_log("Error updating password: " . $e->getMessage());
            $error_message = "Error updating password. Please try again.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

$page_title = 'Change Password';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .security-notice::before {
            content: "🔒";
            font-size: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 1rem;
            display: block;
        }
        
        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-family: inherit;
        }
        
        input:focus {
            outline: none;
            border-color: #ff6b6b;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 5px;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .password-requirements ul {
            margin: 10px 0 0 20px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-family: inherit;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 15px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .navigation {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .nav-link {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: #ff5252;
        }
        
        .strength-meter {
            height: 5px;
            background: #e1e1e1;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
        
        .strength-weak { background: #ff6b6b; width: 25%; }
        .strength-fair { background: #feca57; width: 50%; }
        .strength-good { background: #48dbfb; width: 75%; }
        .strength-strong { background: #1dd1a1; width: 100%; }
        
        .strength-text {
            font-size: 0.9rem;
            margin-top: 5px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-secondary {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Change Password</h1>
            <p>Update your account password for security</p>
        </div>
        
        <div class="form-container">
            <div class="navigation">
                <a href="profile.php" class="nav-link">← Back to Profile</a>
            </div>
            
            <div class="security-notice">
                For your security, changing your password will log you out of all other devices.
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password <span style="color: #dc3545;">*</span></label>
                    <div class="password-input-container">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                            Show
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password <span style="color: #dc3545;">*</span></label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            Show
                        </button>
                    </div>
                    <div class="strength-meter">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="strength-text" id="strength-text"></div>
                    
                    <div class="password-requirements">
                        <strong>Password must contain:</strong>
                        <ul>
                            <li>At least 8 characters</li>
                            <li>At least one uppercase letter (A-Z)</li>
                            <li>At least one lowercase letter (a-z)</li>
                            <li>At least one number (0-9)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span style="color: #dc3545;">*</span></label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            Show
                        </button>
                    </div>
                    <div id="password-match-feedback" style="margin-top: 8px; font-size: 0.9rem;"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <a href="profile.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const passwordField = document.getElementById(inputId);
            const toggleButton = passwordField.nextElementSibling;
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = 'Hide';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = 'Show';
            }
        }
        
        // Password strength meter
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            if (!password) {
                strengthBar.className = 'strength-bar';
                strengthText.textContent = '';
                return;
            }
            
            let score = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) score++;
            else feedback.push('at least 8 characters');
            
            // Uppercase check
            if (/[A-Z]/.test(password)) score++;
            else feedback.push('an uppercase letter');
            
            // Lowercase check
            if (/[a-z]/.test(password)) score++;
            else feedback.push('a lowercase letter');
            
            // Number check
            if (/[0-9]/.test(password)) score++;
            else feedback.push('a number');
            
            // Update strength meter
            strengthBar.className = 'strength-bar';
            if (score === 1) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#ff6b6b';
            } else if (score === 2) {
                strengthBar.classList.add('strength-fair');
                strengthText.textContent = 'Fair';
                strengthText.style.color = '#feca57';
            } else if (score === 3) {
                strengthBar.classList.add('strength-good');
                strengthText.textContent = 'Good';
                strengthText.style.color = '#48dbfb';
            } else if (score === 4) {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#1dd1a1';
            }
            
            if (feedback.length > 0) {
                strengthText.textContent += ' - Missing: ' + feedback.join(', ');
            }
        });
        
        // Password match feedback
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const feedback = document.getElementById('password-match-feedback');
            
            if (!confirmPassword) {
                feedback.textContent = '';
                return;
            }
            
            if (newPassword === confirmPassword) {
                feedback.textContent = '✓ Passwords match';
                feedback.style.color = '#1dd1a1';
            } else {
                feedback.textContent = '✗ Passwords do not match';
                feedback.style.color = '#ff6b6b';
            }
        }
        
        document.getElementById('new_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword) {
                alert('Please enter your current password.');
                e.preventDefault();
                return;
            }
            
            if (!newPassword) {
                alert('Please enter a new password.');
                e.preventDefault();
                return;
            }
            
            if (newPassword.length < 8) {
                alert('New password must be at least 8 characters long.');
                e.preventDefault();
                return;
            }
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match.');
                e.preventDefault();
                return;
            }
            
            // Additional strength validation
            if (!/[A-Z]/.test(newPassword)) {
                alert('Password must contain at least one uppercase letter.');
                e.preventDefault();
                return;
            }
            
            if (!/[a-z]/.test(newPassword)) {
                alert('Password must contain at least one lowercase letter.');
                e.preventDefault();
                return;
            }
            
            if (!/[0-9]/.test(newPassword)) {
                alert('Password must contain at least one number.');
                e.preventDefault();
                return;
            }
            
            // Confirm action
            if (!confirm('Are you sure you want to change your password? This will log you out of all other devices.')) {
                e.preventDefault();
                return;
            }
        });
        
        // Auto-hide success message after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.remove();
                }, 300);
            }, 5000);
        }
        
        // Auto-focus on current password field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('current_password').focus();
        });
    </script>
</body>
</html>