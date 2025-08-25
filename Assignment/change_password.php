<?php
include '_base.php';

// Check if user is logged in
if (!isLoggedIn()) {
    temp('error', 'Please login to change your password.');
    redirect('login.php');
}

$current_user = getCurrentUser();

if (!$current_user) {
    temp('error', 'Unable to load user profile.');
    redirect('login.php');
}

// Handle password change request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit;
    }
    
    handlePasswordChange($current_user);
    exit;
}

function handlePasswordChange($current_user) {
    global $_db;
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate current password
    if (empty($current_password)) {
        $errors['current_password'] = 'Current password is required';
    } elseif (!password_verify($current_password, $current_user->password)) {
        $errors['current_password'] = 'Current password is incorrect';
        logFailedAttempt($current_user->email, 'password_change', 'Incorrect current password');
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($new_password) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters';
    } elseif (strlen($new_password) > 128) {
        $errors['new_password'] = 'Password must be less than 128 characters';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $errors['new_password'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $errors['new_password'] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $errors['new_password'] = 'Password must contain at least one number';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $errors['new_password'] = 'Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    } elseif (password_verify($new_password, $current_user->password)) {
        $errors['new_password'] = 'New password must be different from current password';
    }
    
    // Validate password confirmation
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your new password';
    } elseif ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }
    
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $_db->prepare("UPDATE user SET password = ?, updated_at = NOW() WHERE userID = ?");
        $stmt->execute([$hashed_password, $current_user->userID]);
        
        // Clear all remember tokens for security
        clearAllRememberTokens($current_user->userID);
        
        // Log activity
        logProfileActivity($current_user->userID, 'password_changed', [
            'ip' => $_SERVER['REMOTE_ADDR']
        ], $_db);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Password changed successfully!'
        ]);
        
    } catch (PDOException $e) {
        error_log("Password change error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error occurred. Please try again.'
        ]);
    }
}

$page_title = 'Change Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/profile.css">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="password-container">
        <div class="password-header">
            <button class="back-btn" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1><i class="fas fa-key"></i> Change Password</h1>
            <p>Secure your account with a new password</p>
        </div>

        <div class="password-content">
            <!-- Success/Error Messages -->
            <?php if ($success_msg = get_temp('success')): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg = get_temp('error')): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form id="password-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="current-password">
                        <i class="fas fa-key"></i> Current Password
                    </label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="current-password" 
                            name="current_password" 
                            class="form-input" 
                            placeholder="Enter current password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('current-password')">
                            <i class="eye-icon show fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="current-password-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new-password">
                        <i class="fas fa-lock"></i> New Password
                    </label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="new-password" 
                            name="new_password" 
                            class="form-input" 
                            placeholder="Enter new password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('new-password')">
                            <i class="eye-icon show fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="new-password-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span></span>
                    </div>
                    <small style="color: #6c757d;">Must be at least 8 characters with uppercase, lowercase, and numbers</small>
                </div>

                <div class="form-group">
                    <label for="confirm-password">
                        <i class="fas fa-check"></i> Confirm New Password
                    </label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="confirm-password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Confirm new password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm-password')">
                            <i class="eye-icon show fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="confirm-password-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span></span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        <span>Change Password</span>
                        <div class="spinner"></div>
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // JavaScript functions for the password change page
    function goBack() {
        window.history.back();
    }
    
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
    
    function showFieldError(inputElement, errorElement, message) {
        inputElement.style.borderColor = '#dc3545';
        errorElement.querySelector('span').textContent = message;
        errorElement.style.display = 'flex';
    }
    
    function clearFieldErrors() {
        document.querySelectorAll('.error-message').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.form-input').forEach(input => {
            input.style.borderColor = '#e9ecef';
        });
    }
    
    function showAlert(type, message) {
        // Remove existing alerts
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            ${message}
        `;
        
        document.querySelector('.password-content').prepend(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
    
    // Form submission handler
    document.getElementById('password-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('span');
        const spinner = submitBtn.querySelector('.spinner');
        
        // Show loading
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        spinner.style.display = 'inline-block';
        
        // Clear previous errors
        clearFieldErrors();
        
        fetch('change_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                this.reset();
            } else {
                if (data.errors) {
                    // Show field-specific errors
                    Object.keys(data.errors).forEach(field => {
                        const errorElement = document.getElementById(field + '-error');
                        const inputElement = document.getElementById(field);
                        if (errorElement && inputElement) {
                            showFieldError(inputElement, errorElement, data.errors[field]);
                        }
                    });
                } else {
                    showAlert('error', data.message || 'An error occurred');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Network error occurred. Please try again.');
        })
        .finally(() => {
            // Reset button
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            spinner.style.display = 'none';
        });
    });
    
    // Initialize form validation
    document.addEventListener('DOMContentLoaded', function() {
        // Add input event listeners to clear errors when typing
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '#e9ecef';
                const errorElement = document.getElementById(this.id + '-error');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            });
        });
    });
    </script>
</body>
</html>