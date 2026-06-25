<?php
session_start();

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

require "db.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || $_SESSION["user"] !== "armin11") {
    header("Location: ../");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: users_list");
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['error'] = "درخواست نامعتبر است";
    header("Location: users_list");
    exit;
}

if (!isset($_POST['id']) || !ctype_digit((string)$_POST['id'])) {
    header("Location: users_list");
    exit;
}

$id = (int)$_POST['id'];

// Fetch user
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "کاربر یافت نشد";
    header("Location: users_list");
    exit;
}

$user = $result->fetch_assoc();
if ($user['username'] === 'armin11') {
    $_SESSION['error'] = "این اکانت قابل حذف نیست";
    header("Location: users_list");
    exit;
}

$delete = $conn->prepare("DELETE FROM users WHERE id = ?");
$delete->bind_param("i", $id);

if ($delete->execute()) {
    $_SESSION['success'] = "کاربر «" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . "» با موفقیت حذف شد";
} else {
    $_SESSION['error'] = "خطا در حذف کاربر";
}

$delete->close();
header("Location: users_list");
exit;