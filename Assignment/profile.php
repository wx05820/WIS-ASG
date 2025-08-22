<?php
include '_base.php';

// Check if user is logged in
if (!isLoggedIn()) {
    temp('error', 'Please login to access your profile.');
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$current_user = getCurrentUser();

if (!$current_user) {
    temp('error', 'Unable to load user profile.');
    redirect('login.php');
}

// Handle form submissions
if (is_post()) {
    $csrf_token = req('csrf_token');
    
    // Verify CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $_err['general'] = 'Security token mismatch. Please try again.';
    } else {
        $action = req('action');
        
        switch ($action) {
            case 'update_profile':
                handleProfileUpdate($current_user, $_db);
                break;
                
            case 'change_password':
                handlePasswordChange($current_user, $_db);
                break;
                
            case 'update_photo':
                handlePhotoUpload($current_user, $_db);
                break;
                
            case 'remove_photo':
                handlePhotoRemoval($current_user, $_db);
                break;
        }
    }
    
    // Refresh user data after updates
    $current_user = getCurrentUser();
}

// Helper functions for form handling
function handleProfileUpdate($user, $db) {
    global $_err;
    
    $name = sanitizeInput(req('name'), 'name');
    $username = sanitizeInput(req('username'), 'username');
    $phoneNo = sanitizeInput(req('phoneNo'), 'phone');
    $birthday = sanitizeInput(req('birthday'));
    
    // Validate inputs
    $name_validation = validateName($name);
    if (!$name_validation['valid']) {
        $_err['name'] = $name_validation['message'];
    }
    
    $username_validation = validateUsername($username, $user->userID, $db);
    if (!$username_validation['valid']) {
        $_err['username'] = $username_validation['message'];
    }
    
    $phone_validation = validatePhoneNumber($phoneNo);
    if (!$phone_validation['valid']) {
        $_err['phoneNo'] = $phone_validation['message'];
    }
    
    $birthday_validation = validateBirthday($birthday);
    if (!$birthday_validation['valid']) {
        $_err['birthday'] = $birthday_validation['message'];
    }
    
    if (empty($_err)) {
        try {
            $stmt = $db->prepare("UPDATE user SET name = ?, username = ?, phoneNo = ?, birthday = ?, updated_at = NOW() WHERE userID = ?");
            $stmt->execute([$name, $username, $phoneNo, $birthday ?: null, $user->userID]);
            
            // Update session data
            $_SESSION['name'] = $name;
            $_SESSION['username'] = $username;
            
            logProfileActivity($user->userID, 'profile_updated', [
                'name' => $name,
                'username' => $username,
                'phoneNo' => $phoneNo,
                'birthday' => $birthday
            ], $db);
            
            temp('success', 'Profile updated successfully!');
            
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $_err['general'] = 'Error updating profile. Please try again.';
        }
    }
}

function handlePasswordChange($user, $db) {
    global $_err;
    
    $current_password = req('current_password');
    $new_password = req('new_password');
    $confirm_password = req('confirm_password');
    
    // Validate current password
    if (empty($current_password)) {
        $_err['current_password'] = 'Current password is required';
    } elseif (!password_verify($current_password, $user->password)) {
        $_err['current_password'] = 'Current password is incorrect';
        logFailedAttempt($user->email, 'password_change', 'Incorrect current password');
    }
    
    // Validate new password
    if (empty($new_password)) {
        $_err['new_password'] = 'New password is required';
    } else {
        $password_validation = validatePasswordStrength($new_password);
        if ($password_validation !== true) {
            $_err['new_password'] = $password_validation;
        }
    }
    
    // Validate password confirmation
    if (empty($confirm_password)) {
        $_err['confirm_password'] = 'Please confirm your new password';
    } elseif ($new_password !== $confirm_password) {
        $_err['confirm_password'] = 'Passwords do not match';
    }
    
    // Check if new password is different from current
    if (empty($_err) && password_verify($new_password, $user->password)) {
        $_err['new_password'] = 'New password must be different from current password';
    }
    
    if (empty($_err)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE user SET password = ?, updated_at = NOW() WHERE userID = ?");
            $stmt->execute([$hashed_password, $user->userID]);
            
            // Clear all remember tokens for security
            clearAllRememberTokens($user->userID);
            
            logProfileActivity($user->userID, 'password_changed', ['ip' => $_SERVER['REMOTE_ADDR']], $db);
            temp('success', 'Password changed successfully! Please login again for security.');
            redirect('login.php');
            
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            $_err['password_general'] = 'Error updating password. Please try again.';
        }
    }
}

function handlePhotoUpload($user, $db) {
    global $_err;
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_options = [
            'max_size' => 2 * 1024 * 1024,
            'min_width' => 100,
            'min_height' => 100,
            'max_width' => 1000,
            'max_height' => 1000
        ];
        
        $upload_result = handleSecureUpload($_FILES['profile_photo'], 'uploads/profiles/', $user->userID, $upload_options);
        
        if ($upload_result['success']) {
            try {
                // Delete old profile photo if exists
                if (!empty($user->profile_photo) && $user->profile_photo !== 'profilePhoto/default.jpg') {
                    deleteFileSecurely($user->profile_photo, $user->userID);
                }
                
                // Update database
                $photo_path = 'uploads/profiles/' . $upload_result['filename'];
                $stmt = $db->prepare("UPDATE user SET profile_photo = ?, updated_at = NOW() WHERE userID = ?");
                $stmt->execute([$photo_path, $user->userID]);
                
                logProfileActivity($user->userID, 'profile_photo_updated', [
                    'filename' => $upload_result['filename']
                ], $db);
                
                temp('success', 'Profile photo updated successfully!');
                
            } catch (PDOException $e) {
                error_log("Profile photo update error: " . $e->getMessage());
                $_err['photo'] = 'Error updating profile photo. Please try again.';
            }
        } else {
            $_err['photo'] = $upload_result['message'];
        }
    } else {
        $_err['photo'] = 'Please select a valid photo to upload.';
    }
}

function handlePhotoRemoval($user, $db) {
    global $_err;
    
    try {
        // Delete current photo file
        if (!empty($user->profile_photo) && $user->profile_photo !== 'profilePhoto/default.jpg') {
            deleteFileSecurely($user->profile_photo, $user->userID);
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE user SET profile_photo = NULL, updated_at = NOW() WHERE userID = ?");
        $stmt->execute([$user->userID]);
        
        logProfileActivity($user->userID, 'profile_photo_removed', [], $db);
        temp('success', 'Profile photo removed successfully!');
        
    } catch (PDOException $e) {
        error_log("Profile photo removal error: " . $e->getMessage());
        $_err['photo'] = 'Error removing profile photo. Please try again.';
    }
}

$page_title = 'My Profile';
$current_age = calculateAge($current_user->birthday ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f5f7f9;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #4a90e2, #2c6aa8);
            color: white;
            padding: 20px 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .profile-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .profile-content {
            padding: 30px;
        }
        
        .profile-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-header {
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            font-size: 22px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .photo-section {
            text-align: center;
        }
        
        .photo-container {
            margin-bottom: 20px;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e9ecef;
        }
        
        .profile-photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a90e2, #2c6aa8);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 4px solid #e9ecef;
        }
        
        .initials {
            font-size: 40px;
            font-weight: bold;
            color: white;
        }
        
        .photo-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .photo-requirements {
            color: #6c757d;
            font-size: 14px;
            display: block;
            text-align: center;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        .form-input.error {
            border-color: #e74c3c;
        }
        
        .form-group small {
            color: #6c757d;
            font-size: 14px;
            display: block;
            margin-top: 5px;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .password-requirements ul {
            margin: 10px 0 0 20px;
            color: #6c757d;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #4a90e2;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a80d2;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .security-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .security-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Navigation/Header -->
        <div class="profile-header">
            <div class="header-content">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
                <h1>My Profile</h1>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

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

        <div class="profile-content">
            <!-- Profile Photo Section -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-camera"></i> Profile Photo</h2>
                </div>
                
                <div class="photo-section">
                    <div class="photo-container">
                        <?php if (!empty($current_user->profile_photo) && file_exists($current_user->profile_photo)): ?>
                            <img src="<?php echo htmlspecialchars($current_user->profile_photo); ?>" alt="Profile Photo" class="profile-photo">
                        <?php else: ?>
                            <div class="profile-photo-placeholder">
                                <div class="initials">
                                    <?php echo htmlspecialchars(generateAvatarInitials($current_user->name ?: $current_user->username)); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="photo-actions">
                        <form method="post" enctype="multipart/form-data" class="photo-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_photo">
                            
                            <label for="profile_photo" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Choose Photo
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display: none;" onchange="previewAndSubmit(this)">
                        </form>
                        
                        <?php if (!empty($current_user->profile_photo)): ?>
                        <form method="post" class="photo-form" onsubmit="return confirm('Are you sure you want to remove your profile photo?')">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="remove_photo">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($_err['photo'])): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($_err['photo']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <small class="photo-requirements">
                        Maximum 2MB • JPEG, PNG, GIF, WebP • Minimum 100x100 pixels
                    </small>
                </div>
            </div>

            <!-- Profile Information Section -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> Profile Information</h2>
                </div>
                
                <?php if (isset($_err['general'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_err['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="profile-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                class="form-input <?php echo isset($_err['name']) ? 'error' : ''; ?>" 
                                maxlength="100"
                                value="<?php echo htmlspecialchars($current_user->name ?? ''); ?>"
                                required
                            >
                            <small>Your display name (2-100 characters)</small>
                            <?php if (isset($_err['name'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="username">
                                <i class="fas fa-at"></i> Username
                            </label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input <?php echo isset($_err['username']) ? 'error' : ''; ?>" 
                                maxlength="30"
                                value="<?php echo htmlspecialchars($current_user->username ?? ''); ?>"
                                required
                            >
                            <small>3-30 characters, letters, numbers, underscore and hyphen only</small>
                            <?php if (isset($_err['username'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['username']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="phoneNo">
                                <i class="fas fa-phone"></i> Phone Number
                            </label>
                            <input 
                                type="tel" 
                                id="phoneNo" 
                                name="phoneNo" 
                                class="form-input <?php echo isset($_err['phoneNo']) ? 'error' : ''; ?>" 
                                maxlength="20"
                                value="<?php echo htmlspecialchars($current_user->phoneNo ?? ''); ?>"
                                placeholder="+60123456789"
                            >
                            <small>Optional - Include country code</small>
                            <?php if (isset($_err['phoneNo'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['phoneNo']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="birthday">
                                <i class="fas fa-birthday-cake"></i> Birthday
                            </label>
                            <input 
                                type="date" 
                                id="birthday" 
                                name="birthday" 
                                class="form-input <?php echo isset($_err['birthday']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($current_user->birthday ?? ''); ?>"
                                max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>"
                            >
                            <small>
                                <?php if ($current_age): ?>
                                    Current age: <?php echo $current_age; ?> years old
                                <?php else: ?>
                                    Optional - Must be 13 or older
                                <?php endif; ?>
                            </small>
                            <?php if (isset($_err['birthday'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($_err['birthday']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Account Security Section -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-shield-alt"></i> Account Security</h2>
                </div>
                
                <div class="security-info">
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Last login: <?php echo $current_user->last_login ? date('M j, Y g:i A', strtotime($current_user->last_login)) : 'Never'; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-plus"></i>
                        <span>Member since: <?php echo date('M j, Y', strtotime($current_user->created_at ?? 'now')); ?></span>
                    </div>
                </div>

                <!-- Change Password Form -->
                <?php if (isset($_err['password_general'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_err['password_general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="password-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <h3><i class="fas fa-key"></i> Change Password</h3>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-input-container">
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="form-input <?php echo isset($_err['current_password']) ? 'error' : ''; ?>" 
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (isset($_err['current_password'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo htmlspecialchars($_err['current_password']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-container">
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-input <?php echo isset($_err['new_password']) ? 'error' : ''; ?>" 
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
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
                                <li>At least 8 characters</li>
                                <li>One uppercase letter</li>
                                <li>One lowercase letter</li>
                                <li>One number</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-container">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input <?php echo isset($_err['confirm_password']) ? 'error' : ''; ?>" 
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
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
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Changing your password will log you out from all devices. Continue?')">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>

                <!-- Additional Security Actions -->
                <div class="security-actions">
                    <h3><i class="fas fa-cog"></i> Security Actions</h3>
                    <div class="action-buttons">
                        <a href="logout.php?action=logout_all" class="btn btn-warning" onclick="return confirm('This will log you out from all devices. Continue?')">
                            <i class="fas fa-sign-out-alt"></i> Logout All Devices
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function previewAndSubmit(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Basic validation
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    input.value = '';
                    return;
                }
                
                if (!file.type.match('image.*')) {
                    alert('Please select an image file');
                    input.value = '';
                    return;
                }
                
                // Submit form automatically
                if (confirm('Upload this image as your profile photo?')) {
                    input.form.submit();
                } else {
                    input.value = '';
                }
            }
        }

        // Real-time validation feedback
        document.getElementById('username').addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[a-zA-Z0-9_-]{3,30}$/.test(value);
            
            this.style.borderColor = value.length === 0 ? '' : (isValid ? '#22c55e' : '#ef4444');
        });

        document.getElementById('new_password').addEventListener('input', function() {
            const value = this.value;
            const requirements = [
                value.length >= 8,
                /[A-Z]/.test(value),
                /[a-z]/.test(value),
                /[0-9]/.test(value)
            ];
            
            const isValid = requirements.every(req => req);
            this.style.borderColor = value.length === 0 ? '' : (isValid ? '#22c55e' : '#ef4444');
        });

        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const isValid = newPassword === confirmPassword && confirmPassword.length > 0;
            
            this.style.borderColor = confirmPassword.length === 0 ? '' : (isValid ? '#22c55e' : '#ef4444');
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>