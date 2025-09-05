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
$is_in_subdirectory = (strpos($current_path, '/product/') !== false || 
                      strpos($current_path, '/order/') !== false || 
                      strpos($current_path, '/user/') !== false ||
                      strpos($current_path, '/userProduct/') !== false ||
                      preg_match('/\/[^\/]+\/[^\/]+\.php$/', $current_path)); // Any subdirectory pattern

$image_base_path = $is_in_subdirectory ? '/../' : '';
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
								<a href="/user/profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
								<a href="/admin/logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

		<!-- Main Navigation -->
		<nav class="main-navigation" role="navigation" aria-label="Main navigation">
			<ul>
				<li><a href="/product/list.php">Product Manage</a></li>
				<li><a href="/admin/usermanage/list.php">User Manage</a></li>
				<li><a href="/product/orderhistory.php">Order History</a></li>
			</ul>
			
			<!-- Mobile Menu Toggle -->
			<div class="mobile-menu-toggle" aria-label="Toggle mobile menu">
				<i class="fas fa-bars"></i>
			</div>
		</nav>
	</header>

	<style>
	.asc-desc-toggle {
		width: 44px;
		height: 44px;
		border-radius: 50%;
		background: var(--wood-primary, #8B5A2B);
		color: white;
		border: none;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 1.2rem;
		margin-left: 8px;
		transition: background 0.3s, color 0.3s, transform 0.2s;
		box-shadow: 0 2px 8px rgba(212, 175, 55, 0.08);
	}
	.asc-desc-toggle.asc { background: var(--wood-secondary, #A67C52); }
	.asc-desc-toggle.desc { background: var(--wood-dark, #5D4037); }
	.asc-desc-toggle:hover {
		background: var(--gold-accent, #D4AF37);
		color: var(--wood-dark, #5D4037);
		transform: scale(1.08);
	}
	</style>

	<script>
	function onAdminSortChange(selectEl) {
		var p = new URLSearchParams(window.location.search);
		if (selectEl.name && selectEl.value) p.set(selectEl.name, selectEl.value);
		var existingOrder = p.get('order');
		if (existingOrder) p.set('order', existingOrder.toUpperCase() === 'ASC' ? 'ASC' : 'DESC');
		p.set('page', '1');
		window.location.search = p.toString();
	}

	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('select.sortby-select').forEach(function(sel) {
			sel.addEventListener('change', function() { onAdminSortChange(this); });
		});
	});
	</script>