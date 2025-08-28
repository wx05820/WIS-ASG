<?php
include '../_base.php';

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

// Get available default photos from profilePhoto directory
function getDefaultPhotos() {
    $photos = [];
    $directory = '../profilePhoto/';
    
    if (is_dir($directory)) {
        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && 
                in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $photos[] = '/' . $directory . $file;
            }
        }
    }
    
    return $photos;
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'update_profile':
            handleProfileUpdate();
            break;
            
        case 'update_photo':
            handlePhotoUpdate();
            break;
            
        case 'get_default_photos':
            echo json_encode(['success' => true, 'photos' => getDefaultPhotos()]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function handleProfileUpdate() {
    global $current_user, $_db;
    
    $name = sanitizeInput($_POST['name'] ?? '', 'name');
    $username = sanitizeInput($_POST['username'] ?? '', 'username');
    $phone = sanitizeInput($_POST['phone'] ?? '', 'phone');
    $birthday = sanitizeInput($_POST['birthday'] ?? '');
    
    $errors = [];
    
    // Validate name
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors['name'] = 'Name must be between 2-100 characters';
    } elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $name)) {
        $errors['name'] = 'Name contains invalid characters';
    }
    
    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $errors['username'] = 'Username must be between 3-30 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $errors['username'] = 'Username can only contain letters, numbers, underscore and hyphen';
    } else {
        // Check if username is already taken (excluding current user)
        $stmt = $_db->prepare('SELECT userID FROM user WHERE username = ? AND userID != ?');
        $stmt->execute([$username, $current_user->userID]);
        if ($stmt->rowCount() > 0) {
            $errors['username'] = 'Username is already taken';
        }
    }
    
    // Validate phone
    if (!empty($phone)) {
        if (!preg_match('/^\+?[0-9\s\-()]{8,20}$/', $phone)) {
            $errors['phone'] = 'Please enter a valid phone number';
        }
    }
    
    // Validate birthday
    if (!empty($birthday)) {
        $birth_date = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$birth_date) {
            $errors['birthday'] = 'Please enter a valid date';
        } else {
            $age = (new DateTime())->diff($birth_date)->y;
            if ($age < 13) {
                $errors['birthday'] = 'Must be at least 13 years old';
            } elseif ($age > 120) {
                $errors['birthday'] = 'Please enter a valid birthday';
            }
        }
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }
    
    try {
        $stmt = $_db->prepare("
            UPDATE user 
            SET name = ?, username = ?, phoneNo = ?, birthday = ?, updated_at = NOW() 
            WHERE userID = ?
        ");
        $stmt->execute([
            $name, 
            $username, 
            $phone ?: null, 
            $birthday ?: null, 
            $current_user->userID
        ]);
        
        // Update session data
        $_SESSION['name'] = $name;
        $_SESSION['username'] = $username;
        
        // Log activity
        logProfileActivity($current_user->userID, 'profile_updated', [
            'name' => $name,
            'username' => $username,
            'phone' => $phone,
            'birthday' => $birthday
        ], $_db);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile updated successfully!'
        ]);
        
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error occurred. Please try again.'
        ]);
    }
}

function handlePhotoUpdate() {
    global $current_user, $_db;
    
    $photo_type = $_POST['photo_type'] ?? '';
    $photo_data = $_POST['photo_data'] ?? '';
    
    try {
        $new_photo_path = null;
        
        switch ($photo_type) {
            case 'default':
                // Validate default photo path
                $default_photos = getDefaultPhotos();
                if (in_array($photo_data, $default_photos)) {
                    $new_photo_path = $photo_data;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid default photo selected']);
                    return;
                }
                break;
                
            case 'upload':
                // Handle file upload
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = handleSecurePhotoUpload($_FILES['photo'], $current_user->userID);
                    if ($upload_result['success']) {
                        $new_photo_path = $upload_result['path'];
                        
                        // Delete old custom photo if exists
                        if (!empty($current_user->photo) && 
                            strpos($current_user->photo, 'uploads/profiles/') === 0 &&
                            file_exists($_SERVER['DOCUMENT_ROOT'] . $current_user->photo)) {
                            unlink($current_user->photo);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => $upload_result['message']]);
                        return;
                    }
                }
                break;
                
            case 'camera':
                // Handle base64 camera capture
                $upload_result = handleBase64PhotoUpload($photo_data, $current_user->userID);
                if ($upload_result['success']) {
                    $new_photo_path = $upload_result['path'];
                    
                    // Delete old custom photo if exists
                    if (!empty($current_user->photo) && 
                        strpos($current_user->photo, 'uploads/profiles/') === 0 &&
                        file_exists($current_user->photo)) {
                        unlink($current_user->photo);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => $upload_result['message']]);
                    return;
                }
                break;
                
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid photo type']);
                return;
        }
        
        // Update database
        $stmt = $_db->prepare("UPDATE user SET photo = ?, updated_at = NOW() WHERE userID = ?");
        $stmt->execute([$new_photo_path, $current_user->userID]);
        
        // Log activity
        logProfileActivity($current_user->userID, 'profile_photo_updated', [
            'type' => $photo_type,
            'path' => $new_photo_path
        ], $_db);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile photo updated successfully!',
            'photo_path' => $new_photo_path
        ]);
        
    } catch (Exception $e) {
        error_log("Photo update error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error updating profile photo. Please try again.'
        ]);
    }
}

function handleSecurePhotoUpload($file, $user_id) {
    // Validate file exists
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'No file selected.'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];
        
        return ['success' => false, 'message' => $error_messages[$file['error']] ?? 'Unknown upload error.'];
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.'];
    }
    
    if ($file['size'] > 2 * 1024 * 1024) // 2MB limit
        return ['success' => false, 'message' => 'File size must be less than 2MB.'];
    
    // Verify it's actually an image
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        return ['success' => false, 'message' => 'Invalid image file.'];
    }
    
    // Check dimensions
    if ($image_info[0] < 100 || $image_info[1] < 100) {
        return ['success' => false, 'message' => 'Image must be at least 100x100 pixels.'];
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Optimize image (resize if too large)
        optimizeProfileImage($filepath);
        
        return [
            'success' => true, 
            'path' => $filepath,
            'filename' => $filename
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save uploaded file.'];
    }
}

function handleBase64PhotoUpload($base64_data, $user_id) {
    // Validate base64 data
    if (strpos($base64_data, 'data:image/') !== 0) {
        return ['success' => false, 'message' => 'Invalid image data format.'];
    }
    
    // Extract image data
    list($type, $data) = explode(';', $base64_data);
    list(, $data) = explode(',', $data);
    $image_data = base64_decode($data);
    
    if (!$image_data) {
        return ['success' => false, 'message' => 'Failed to decode image data.'];
    }
    
    // Validate image type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid image type.'];
    }
    
    // Check file size (2MB limit)
    if (strlen($image_data) > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Image size must be less than 2MB.'];
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = $mime_type === 'image/jpeg' ? 'jpg' : 
                ($mime_type === 'image/png' ? 'png' : 
                ($mime_type === 'image/gif' ? 'gif' : 'webp'));
    
    $filename = $user_id . '_camera_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Save image
    if (file_put_contents($filepath, $image_data)) {
        // Optimize image
        optimizeProfileImage($filepath);
        
        return [
            'success' => true, 
            'path' => $filepath,
            'filename' => $filename
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save captured image.'];
    }
}

function optimizeProfileImage($filepath) {
    $image_info = getimagesize($filepath);
    if (!$image_info) return;
    
    $mime_type = $image_info['mime'];
    $width = $image_info[0];
    $height = $image_info[1];
    
    // Skip if image is already small enough
    if ($width <= 500 && $height <= 500) return;
    
    // Calculate new dimensions (max 500x500 while maintaining aspect ratio)
    $max_size = 500;
    if ($width > $height) {
        $new_width = $max_size;
        $new_height = intval(($height * $max_size) / $width);
    } else {
        $new_height = $max_size;
        $new_width = intval(($width * $max_size) / $height);
    }
    
    // Create image resource based on type
    switch ($mime_type) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($filepath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($filepath);
            break;
        default:
            return;
    }
    
    if (!$source) return;
    
    // Create new image with new dimensions
    $resized = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefill($resized, 0, 0, $transparent);
    }
    
    // Resize image
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Save optimized image
    switch ($mime_type) {
        case 'image/jpeg':
            imagejpeg($resized, $filepath, 85);
            break;
        case 'image/png':
            imagepng($resized, $filepath, 8);
            break;
        case 'image/gif':
            imagegif($resized, $filepath);
            break;
        case 'image/webp':
            imagewebp($resized, $filepath, 85);
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($resized);
}

$page_title = 'Edit Profile';
$default_photos = getDefaultPhotos();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/profile.css">
    <title><?php echo htmlspecialchars($page_title); ?> - AiKUN Furniture</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body>
    <div class="profile-container fade-in-up">
        <!-- Header -->
        <div class="profile-header">
            <button class="back-btn" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Personalize your account settings</p>
        </div>

        <div class="profile-content">
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

            <!-- Photo Management Section -->
            <div class="photo-management-section">
                <h2><i class="fas fa-camera"></i> Profile Photo</h2>
                
                <div class="current-photo-display">
                    <?php
                    // Prepare photo path for file_exists and for <img src>
                    $photo_url = '';
                    $photo_fs_path = '';
                    if (!empty($current_user->photo)) {
                        if ($current_user->photo[0] === '/') {
                            $photo_url = $current_user->photo;
                            $photo_fs_path = $_SERVER['DOCUMENT_ROOT'] . $current_user->photo;
                        } else {
                            $photo_url = '/' . $current_user->photo;
                            $photo_fs_path = __DIR__ . '/../' . $current_user->photo;
                        }
                    }
                    ?>
                    <?php if (!empty($photo_url) && file_exists($photo_fs_path)): ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" class="current-photo" alt="Profile Photo" id="current-photo-display">
                    <?php else: ?>
                        <div class="photo-placeholder" id="current-photo-display">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <p style="margin-top: 15px; color: #6c757d;">Current Profile Photo</p>
                </div>

                <div class="photo-actions">
                    <div class="photo-action-card" onclick="toggleDefaultPhotos()">
                        <i class="fas fa-images"></i>
                        <h3>Choose Default</h3>
                        <p>Select from our collection</p>
                    </div>
                    
                    <!-- Replace the upload photo action card with this -->
                    <div class="photo-action-card" id="upload-card" onclick="document.getElementById('file-upload').click()">
                        <i class="fas fa-upload"></i>
                        <h3>Upload Photo</h3>
                        <p>From your device</p>
                    </div>
                    
                    <div class="photo-action-card" onclick="openCamera()">
                        <i class="fas fa-camera"></i>
                        <h3>Take Photo</h3>
                        <p>Use camera directly</p>
                    </div>
                </div>

                <!-- Default Photos Grid -->
                <div class="default-photos-grid" id="default-photos-grid">
                    <?php foreach ($default_photos as $photo): ?>
                        <div class="default-photo-option" onclick="selectDefaultPhoto('<?php echo htmlspecialchars($photo); ?>', this)">
                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="Default Avatar" 
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjNGE5MGUyIi8+CjxjaXJjbGUgY3g9IjQwIiBjeT0iMzIiIHI9IjEyIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMjAgNjBjMC04IDgtMTYgMjAtMTZzMjAgOCAyMCAxNiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+'">
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hidden forms -->
                <form id="photo-upload-form" style="display: none;" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_photo">
                    <input type="hidden" name="photo_type" value="upload">
                    <input type="file" id="file-upload" name="photo" accept="image/*" onchange="handleFileUpload(this)" style="display: none;">
                </form>
            </div>

            <!-- Camera Modal -->
            <div class="camera-modal" id="camera-modal">
                <div class="camera-container">
                    <button class="close-camera" onclick="closeCamera()">&times;</button>
                    <h3><i class="fas fa-camera"></i> Take a Photo</h3>
                    <video id="video" autoplay></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                    <div id="photo-preview"></div>
                    
                    <div class="camera-controls">
                        <button class="btn btn-success" id="capture-btn" onclick="capturePhoto()">
                            <i class="fas fa-camera"></i> Capture
                        </button>
                        <button class="btn btn-primary" id="use-photo-btn" onclick="usePhoto()" style="display: none;">
                            <i class="fas fa-check"></i> Use Photo
                        </button>
                        <button class="btn btn-secondary" id="retake-btn" onclick="retakePhoto()" style="display: none;">
                            <i class="fas fa-redo"></i> Retake
                        </button>
                        <button class="btn btn-danger" onclick="closeCamera()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Profile Information Form -->
            <form id="profile-form" class="form-section">
                <h2><i class="fas fa-user"></i> Profile Information</h2>
                
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
                            class="form-input" 
                            placeholder="Enter your full name"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($current_user->name ?? ''); ?>">
                        <div class="error-message" id="name-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-at"></i> Username
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="Enter your username"
                            maxlength="30"
                            value="<?php echo htmlspecialchars($current_user->username ?? ''); ?>"
                            required
                        >
                        <div class="error-message" id="username-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars($current_user->email ?? ''); ?>"
                            readonly
                            style="background: #f8f9fa; cursor: not-allowed;"
                        >
                        <small style="color: #6c757d;">Email cannot be changed for security reasons</small>
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-input" 
                            placeholder="+60123456789"
                            maxlength="20"
                            value="<?php echo htmlspecialchars($current_user->phoneNo ?? ''); ?>"
                        >
                        <div class="error-message" id="phone-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="birthday">
                            <i class="fas fa-birthday-cake"></i> Birthday
                        </label>
                        <input 
                            type="date" 
                            id="birthday" 
                            name="birthday" 
                            class="form-input"
                            value="<?php echo htmlspecialchars($current_user->birthday ?? ''); ?>"
                            max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>"
                        >
                        <div class="error-message" id="birthday-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span></span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Update Profile</span>
                        <div class="spinner"></div>
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset Changes
                    </button>
                    <a href="change_password.php" class="btn btn-success">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Initialize with PHP data
    const currentUser = <?php echo json_encode([
        'name' => $current_user->name ?? '',
        'username' => $current_user->username ?? '',
        'email' => $current_user->email ?? '',
        'phone' => $current_user->phoneNo ?? '',
        'birthday' => $current_user->birthday ?? '',
        'photo' => $current_user->photo ?? null
    ]); ?>;
    
    // Global variables for camera functionality
    let stream = null;
    let capturedPhotoData = null;
    
    // Toggle default photos grid
    function toggleDefaultPhotos() {
        const grid = document.getElementById('default-photos-grid');
        grid.classList.toggle('active');
    }
    
    // Select a default photo
    function selectDefaultPhoto(photoPath, element) {
        // Remove selected class from all options
        document.querySelectorAll('.default-photo-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Add selected class to clicked option
        element.classList.add('selected');
        
        // Update profile photo via AJAX
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
        formData.append('action', 'update_photo');
        formData.append('photo_type', 'default');
        formData.append('photo_data', photoPath);
        
        fetch('profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the displayed photo
                updateProfilePhotoDisplay(data.photo_path);
                showAlert('success', data.message);
                // Close the default photos grid
                document.getElementById('default-photos-grid').classList.remove('active');
            } else {
                showAlert('error', data.message || 'Failed to update photo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Network error occurred. Please try again.');
        });
    }
    
    // Handle file upload - FIXED VERSION
    function handleFileUpload(input) {
        if (!input.files || input.files.length === 0) {
            showAlert('error', 'No file selected.');
            return;
        }
        
        const file = input.files[0];
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showAlert('error', `Invalid file type: ${file.type}. Please upload JPEG, PNG, GIF, or WebP images.`);
            return;
        }
        
        // Validate file size (max 2MB)
        const maxSize = 2 * 1024 * 1024;
        if (file.size > maxSize) {
            showAlert('error', `File too large: ${Math.round(file.size / 1024)} KB. Maximum size is 2MB.`);
            return;
        }
        
        // Show loading state
        const uploadCard = document.getElementById('upload-card');
        const originalContent = uploadCard.innerHTML;
        uploadCard.innerHTML = '<i class="fas fa-spinner fa-spin"></i><p>Uploading...</p>';
        uploadCard.classList.add('uploading');
        
        // Create FormData and append the file
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
        formData.append('action', 'update_photo');
        formData.append('photo_type', 'upload');
        formData.append('photo', file);
        
        // Send the request
        fetch('profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProfilePhotoDisplay(data.photo_path);
                showAlert('success', data.message);
                // Reset the file input
                input.value = '';
            } else {
                showAlert('error', data.message || 'Failed to upload photo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Network error occurred. Please try again.');
        })
        .finally(() => {
            // Restore original content
            uploadCard.innerHTML = originalContent;
            uploadCard.classList.remove('uploading');
        });
    }
    
    // Open camera
    function openCamera() {
        const modal = document.getElementById('camera-modal');
        modal.classList.add('active');
        
        // Reset camera controls
        document.getElementById('capture-btn').style.display = 'block';
        document.getElementById('use-photo-btn').style.display = 'none';
        document.getElementById('retake-btn').style.display = 'none';
        document.getElementById('photo-preview').style.display = 'none';
        document.getElementById('video').style.display = 'block';
        
        // Access camera
        navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then(function(cameraStream) {
            stream = cameraStream;
            const video = document.getElementById('video');
            video.srcObject = stream;
        })
        .catch(function(error) {
            console.error('Error accessing camera:', error);
            showAlert('error', 'Cannot access camera: ' + error.message);
            closeCamera();
        });
    }
    
    // Close camera
    function closeCamera() {
        const modal = document.getElementById('camera-modal');
        modal.classList.remove('active');
        
        // Stop camera stream
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    }
    
    // Capture photo
    function capturePhoto() {
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');
        
        // Set canvas dimensions to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Draw current video frame to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to data URL
        capturedPhotoData = canvas.toDataURL('image/png');
        
        // Show preview
        const preview = document.getElementById('photo-preview');
        preview.innerHTML = `<img src="${capturedPhotoData}" alt="Captured Photo">`;
        preview.style.display = 'block';
        
        // Hide video and show controls for using/retaking
        video.style.display = 'none';
        document.getElementById('capture-btn').style.display = 'none';
        document.getElementById('use-photo-btn').style.display = 'block';
        document.getElementById('retake-btn').style.display = 'block';
    }
    
    // Retake photo
    function retakePhoto() {
        const video = document.getElementById('video');
        const preview = document.getElementById('photo-preview');
        
        // Show video and hide preview
        video.style.display = 'block';
        preview.style.display = 'none';
        
        // Show capture button and hide use/retake buttons
        document.getElementById('capture-btn').style.display = 'block';
        document.getElementById('use-photo-btn').style.display = 'none';
        document.getElementById('retake-btn').style.display = 'none';
    }
    
    // Use captured photo
    function usePhoto() {
        if (!capturedPhotoData) return;
        
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
        formData.append('action', 'update_photo');
        formData.append('photo_type', 'camera');
        formData.append('photo_data', capturedPhotoData);
        
        fetch('profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProfilePhotoDisplay(data.photo_path);
                showAlert('success', data.message);
                closeCamera();
            } else {
                showAlert('error', data.message || 'Failed to update photo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Network error occurred. Please try again.');
        });
    }
    
    // Update profile photo display
    function updateProfilePhotoDisplay(photoPath) {
        const display = document.getElementById('current-photo-display');
        
        if (photoPath) {
            // Create a new image element
            const img = document.createElement('img');
            img.src = photoPath + '?t=' + new Date().getTime(); // Cache busting
            img.className = 'current-photo';
            img.alt = 'Profile Photo';
            img.onload = function() {
                // Replace the current display with the new image
                if (display.tagName === 'IMG') {
                    display.parentNode.replaceChild(img, display);
                } else {
                    // If it's a placeholder div, replace it
                    display.parentNode.replaceChild(img, display);
                }
            };
        } else {
            // If no photo path, show placeholder
            const placeholder = document.createElement('div');
            placeholder.className = 'photo-placeholder';
            placeholder.innerHTML = '<i class="fas fa-user"></i>';
            
            if (display.tagName === 'IMG') {
                display.parentNode.replaceChild(placeholder, display);
            } else {
                // If it's already a placeholder, just update the ID
                display.id = 'current-photo-display';
            }
        }
    }
    
    // Show field error
    function showFieldError(inputElement, errorElement, message) {
        inputElement.style.borderColor = '#dc3545';
        errorElement.querySelector('span').textContent = message;
        errorElement.style.display = 'flex';
    }
    
    // Clear field errors
    function clearFieldErrors() {
        document.querySelectorAll('.error-message').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.form-input').forEach(input => {
            input.style.borderColor = '#e9ecef';
        });
    }
    
    // Show alert message
    function showAlert(type, message) {
        // Remove existing alerts
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            ${message}
        `;
        
        document.querySelector('.profile-content').prepend(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
    
    // Go back to previous page
    function goBack() {
        window.history.back();
    }
    
    // Form submission handlers
    document.getElementById('profile-form').addEventListener('submit', function(e) {
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
        
        fetch('profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
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
        
        // Add drag and drop functionality for file uploads
        const uploadCard = document.getElementById('upload-card');
        const fileInput = document.getElementById('file-upload');
        
        uploadCard.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        uploadCard.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        uploadCard.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                handleFileUpload(fileInput);
            }
        });
    });
</script>
</body>
</html>