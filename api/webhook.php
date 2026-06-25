<?php
$API_KEY = "b6a612ae1125c114d16b7c77fbf07f3ab4a641f7fb6851dd2beefeff3f2f6c8c";
$file = "messages.json";

function renderPage($title, $status, $message) {
    http_response_code($status);
    header("Content-Type: text/html; charset=utf-8");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: no-referrer");
    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>' . $title . '</title>
<style>:root{--bg:#0a0b0f;--card:#0f1220;--accent:#4cc9f0;--accent2:#7209b7;--text:#e6e9ff;--muted:#a3a8c3;}body{margin:0;font-family:Inter,system-ui;background:radial-gradient(1200px 800px at 20% -10%,#1a1c2e,#0a0b0f);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;user-select:none;}.wrap{width:min(920px,92vw);background:linear-gradient(180deg,rgba(255,255,255,0.03),rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:40px;position:relative;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.6);}.glow{position:absolute;inset:-40%;background:radial-gradient(circle at 30% 20%,rgba(76,201,240,.2),transparent 40%),radial-gradient(circle at 80% 60%,rgba(114,9,183,.25),transparent 40%);filter:blur(40px);z-index:0;}.content{position:relative;z-index:1;}.badge{display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(76,201,240,.15);color:var(--accent);font-weight:600;font-size:.85rem;border:1px solid rgba(76,201,240,.3);}h1{font-size:2.3rem;margin:16px 0 10px;}p{color:var(--muted);line-height:1.7;}.code{margin-top:18px;padding:14px 16px;background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.06);border-radius:12px;color:#c9d4ff;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}</style>
</head>
<body>
<div class="wrap">
    <div class="glow"></div>
    <div class="content">
        <span class="badge">Radeon API</span>
        <h1>' . $title . '</h1>
        <p>' . $message . '</p>
        <div class="code">Status: ' . $status . '</div>
    </div>
</div>
</body>
</html>';
    exit;
}

$headers = getallheaders();
if (!isset($headers['x-api-key']) || $headers['x-api-key'] !== $API_KEY) {
    renderPage("API Gateway", 200, "Access to this endpoint is restricted.");
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    renderPage("API Gateway", 400, "Access to this endpoint is restricted.");
}

$fp = fopen($file, "c+");
flock($fp, LOCK_EX);

$json = stream_get_contents($fp);
$decoded = json_decode($json, true);
$messages = is_array($decoded) ? $decoded : [];
$new = [
    "id" => uniqid(),
    "category" => $data["category"] ?? "general",
    "message" => $data["message"] ?? "",
    "time" => time()
];

array_unshift($messages, $new);

rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(["status" => "ok"]);