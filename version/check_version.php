<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$input = json_decode(file_get_contents('php://input'), true);
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$clientCssVersion = $input['css_version'] ?? '';
$clientJsVersion = $input['js_version'] ?? '';
$serverCssVersion = filemtime(__DIR__ . '/../assets/index.css');
$serverJsVersion = filemtime(__DIR__ . '/../assets/index.js');
$updateAvailable = ($clientCssVersion != $serverCssVersion) || ($clientJsVersion != $serverJsVersion);
echo json_encode([
    'update_available' => $updateAvailable,
    'server_css_version' => $serverCssVersion,
    'server_js_version' => $serverJsVersion
]);