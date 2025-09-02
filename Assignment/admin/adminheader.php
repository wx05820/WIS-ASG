<?php
require_once __DIR__ . '/../_base.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Only for staff: Admin/Supervisor/SuperAdmin
$staff_user = null;
if (isset($_SESSION['staff_id'])) {
	try {
		$stm = $_db->prepare('SELECT username, photo, role, email FROM user WHERE userID = ?');
		$stm->execute([$_SESSION['staff_id']]);
		$staff_user = $stm->fetch();
	} catch (PDOException $e) {
		error_log('adminheader fetch error: ' . $e->getMessage());
	}
}

$current_path = $_SERVER['PHP_SELF'];
$is_in_subdirectory = (strpos($current_path, '/admin/') !== false) || (strpos($current_path, '/product/') !== false);
$image_base_path = $is_in_subdirectory ? '../' : '';
?>

	<header class="wooden-header">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/adminheader.css">
		<div class="header-container">
			<div class="logo-section">
				<a href="/admin/adminpage.php">
					<img src="/images/logo.png" alt="AiKUN Furniture Logo" class="logo">
					<span class="company-name">AiKUN Admin</span>
				</a>
			</div>

			<div class="user-section">
				<div class="user-actions">
					<?php if ($staff_user): ?>
						<div class="user-dropdown">
							<button class="user-profile-btn" aria-label="Staff menu" aria-expanded="false">
								<img src="<?php echo !empty($staff_user->photo) ? $image_base_path . $staff_user->photo : $image_base_path . 'images/default-avatar.png'; ?>" class="profile-photo-small">
								<span class="username-display"><?php echo htmlspecialchars($staff_user->username); ?></span>
								<i class="fas fa-chevron-down dropdown-arrow"></i>
							</button>
							<div class="dropdown-content" role="menu">
								<div class="dropdown-header">
										<img src="<?php echo !empty($staff_user->photo) ? $image_base_path . $staff_user->photo : $image_base_path . 'images/default-avatar.png'; ?>" class="profile-photo-large">
									<div class="user-info">
										<h4><?php echo htmlspecialchars($staff_user->username); ?></h4>
										<p class="user-email"><?php echo htmlspecialchars($staff_user->email); ?></p>
										<p class="user-role"><?php echo htmlspecialchars($staff_user->role); ?></p>
									</div>
								</div>
								<hr class="dropdown-divider">
								<a href="../user/profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
								<a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
							</div>
						</div>
					<?php else: ?>
						<a href="/admin/loginstaff.php" class="user-icon" aria-label="Staff Login">
							<i class="fas fa-user-shield"></i>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</header>
