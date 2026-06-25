<?php
// ── DB connection ─────────────────────────────────────────
$host = "localhost";
$user = "radeonrp_1";
$pass = "Armin11_138590";
$db   = "radeonrp_1";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    session_start();
    $_SESSION["error"] = "Connection Error: " . $conn->connect_error;
    header("Location: " . (strpos($_SERVER['PHP_SELF'], '/users/') !== false ? '../login' : 'login'));
    exit;
}

$conn->set_charset('utf8mb4');

// ── Activity logger ───────────────────────────────────────
function log_activity(mysqli $conn, string $username, string $action, string $details = ''): void {
    $ip   = $_SERVER['HTTP_X_FORWARDED_FOR']
          ?? $_SERVER['HTTP_X_REAL_IP']
          ?? $_SERVER['REMOTE_ADDR']
          ?? '0.0.0.0';
    $ip   = trim(explode(',', $ip)[0]);

    $stmt = $conn->prepare(
        "INSERT INTO activity_log (username, action, details, ip) VALUES (?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('ssss', $username, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// ── Permission helper ─────────────────────────────────────
function get_user_categories(mysqli $conn, string $username): array|null {
    // Admin sees everything — return null (= all)
    if ($username === 'armin11') return null;

    $stmt = $conn->prepare(
        "SELECT up.category
           FROM user_permissions up
           JOIN users u ON u.id = up.user_id
          WHERE u.username = ?"
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res  = $stmt->get_result();
    $cats = [];
    while ($row = $res->fetch_assoc()) {
        $cats[] = $row['category'];
    }
    $stmt->close();
    return $cats; // empty array = no access
}