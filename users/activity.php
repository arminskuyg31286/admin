<?php
function logActivity(mysqli $conn, string $username, string $action): void {
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0])
        ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ip = substr($ip, 0, 45);
    $stmt = $conn->prepare(
        "INSERT INTO activity_log (username, action, ip) VALUES (?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param("sss", $username, $action, $ip);
        $stmt->execute();
        $stmt->close();
    }
}