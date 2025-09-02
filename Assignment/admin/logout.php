<?php
require_once '../_base.php';

logoutUserStaff();

// If called via background beacon (POST), return no content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json');
	http_response_code(204);
	exit;
}

// Default: normal browser navigation
redirect('loginstaff.php');
?>


