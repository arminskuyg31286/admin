<?php
session_start();

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    $username = $_SESSION['user'] ?? 'unknown';
    require_once "users/db.php";
    require_once "users/activity.php";
    logActivity($conn, $username, "Logged out");
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header("Location: login");
exit;
