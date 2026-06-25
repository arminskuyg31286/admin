<?php
session_start();

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$username = $_SESSION['user'] ?? '';
$isAdmin = ($username === 'armin11');
$file = __DIR__ . '/messages.json';
if (!file_exists($file)) {
    echo json_encode([]);
    exit;
}

$fp = fopen($file, 'r');
flock($fp, LOCK_SH);
$raw = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

$logs = json_decode($raw, true);
if (!is_array($logs)) {
    echo json_encode([]);
    exit;
}

if ($isAdmin) {
    echo json_encode($logs);
    exit;
}

require_once __DIR__ . '/../users/db.php';

$stmt = $conn->prepare("
    SELECT p.category
    FROM   user_permissions p
    JOIN   users u ON u.id = p.user_id
    WHERE  u.username = ?
");
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();

$permitted = [];
while ($row = $res->fetch_assoc()) {
    $permitted[$row['category']] = true;
}
$stmt->close();

$filtered = array_values(array_filter(
    $logs,
    fn($log) => isset($permitted[$log['category'] ?? ''])
));

echo json_encode($filtered);