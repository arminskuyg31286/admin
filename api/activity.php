<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || $_SESSION['user'] !== 'armin11') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache');
require_once __DIR__ . '/../users/db.php';

$limit = min(max((int) ($_GET['limit'] ?? 50), 1), 200);
$offset = max((int) ($_GET['offset'] ?? 0), 0);
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';
if ($filter === 'login') {
    $where[] = "action LIKE ?";
    $params[] = '%logged in%';
    $types .= 's';
}

if ($filter === 'logout') {
    $where[] = "action LIKE ?";
    $params[] = '%logged out%';
    $types .= 's';
}

if ($filter === 'delete') {
    $where[] = "action LIKE ?";
    $params[] = '%delet%';
    $types .= 's';
}

if ($filter === 'perm') {
    $where[] = "(action LIKE ? OR action LIKE ?)";
    $params[] = '%permission%';
    $params[] = '%perm%';
    $types .= 'ss';
}

if ($search !== '') {
    $where[] = "(username LIKE ? OR action LIKE ? OR details LIKE ? OR ip LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $types .= 'ssss';
}

$whereSQL = implode(' AND ', $where);
$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM activity_log WHERE $whereSQL");
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();
$rowStmt = $conn->prepare("SELECT username, action, details, ip, created_at FROM activity_log WHERE $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
if (!empty($params)) {
    $rowStmt->bind_param($types, ...$params);
}

$rowStmt->execute();
$rows = $rowStmt->get_result();
$logs = [];
while ($row = $rows->fetch_assoc())
$logs[] = $row;
$rowStmt->close();

echo json_encode(['logs' => $logs, 'total' => $total]);