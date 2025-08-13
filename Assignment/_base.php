<?php

date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

$_db = new PDO('mysql:dbname=aikun', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function req($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

function is_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function temp($type, $message) {
    $_SESSION['temp_' . $type] = $message;
}

function get_temp($type) {
    if (isset($_SESSION['temp_' . $type])) {
        $message = $_SESSION['temp_' . $type];
        unset($_SESSION['temp_' . $type]);
        return $message;
    }
    return null;
}

function redirect($url = null) {
    $url ??= $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

function login($user, $url = '/') {
    $_SESSION['user'] = $user;
    redirect($url);
}

function logout($url = '/') {
    unset($_SESSION['user']);
    redirect($url);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

$_err = [];

function err($key) {
    global $_err;
    if ($_err[$key] ?? false) {
        echo "<span class='err'>$_err[$key]</span>";
    }
    else {
        echo '<span></span>';
    }
}