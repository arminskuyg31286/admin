<?php
session_start();
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

require "db.php";
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || $_SESSION["user"] !== "armin11") {
  header("Location: ../");
  exit;
}

if (empty($_SESSION['csrf_token']))
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $error = "Invalid request.";
  } else {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    if ($username === '' || $password === '')
      $error = "Username and password are required.";
    elseif (strlen($username) < 3 || strlen($username) > 50)
      $error = "Username must be 3–50 characters.";
    elseif (strlen($password) < 6)
      $error = "Password must be at least 6 characters.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
      $error = "Username can only contain letters, numbers and _.";
    else {
      $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
      $check->bind_param("s", $username);
      $check->execute();
      if ($check->get_result()->num_rows > 0)
        $error = "Username already taken.";
      else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hash);
        if ($stmt->execute()) {
          $success = "Account created successfully.";
          $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else
          $error = "Error creating account.";
        $stmt->close();
      }
      $check->close();
    }
  }
}

$cssVer = file_exists('../assets/index.css') ? filemtime('../assets/index.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>New User — Radeon Admin</title>
  <link rel="stylesheet" href="../assets/index.css?v=<?php echo $cssVer; ?>">
</head>
<body class="cf-form-page">
  <div class="cf-form-card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
      <a href="users_list" class="cf-btn cf-btn-ghost cf-btn-sm" style="padding:6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12" />
          <polyline points="12 19 5 12 12 5" />
        </svg>
      </a>
      <div>
        <div class="cf-breadcrumb" style="margin-bottom:2px;"><a href="/">Dashboard</a><span
            class="cf-breadcrumb-sep">/</span><a href="users_list">Users</a><span
            class="cf-breadcrumb-sep">/</span><span>New</span></div>
        <h1 class="cf-form-title" style="margin:0;">Create New User</h1>
      </div>
    </div>
      <?php if ($success): ?>
      <div class="cf-alert cf-alert-success" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" style="flex-shrink:0">
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div class="cf-alert cf-alert-error" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" style="flex-shrink:0">
          <circle cx="12" cy="12" r="10" />
          <line x1="15" y1="9" x2="9" y2="15" />
          <line x1="9" y1="9" x2="15" y2="15" />
        </svg>
        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <?php endif; ?>
    <form method="POST" class="cf-form-body" autocomplete="off">
      <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <div class="cf-input-group">
        <label class="cf-label" for="username">Username</label>
        <input class="cf-input" type="text" id="username" name="username"
          placeholder="letters, numbers, underscore only" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+"
          autocomplete="off"
          value="<?php echo $success ? '' : htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="cf-input-group">
        <label class="cf-label" for="password">Password</label>
        <input class="cf-input" type="password" id="password" name="password" placeholder="Minimum 6 characters"
          required minlength="6" autocomplete="new-password">
      </div>
      <button type="submit" class="cf-btn cf-btn-primary cf-btn-lg cf-btn-full">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
          <line x1="12" y1="11" x2="12" y2="17" />
          <line x1="9" y1="14" x2="15" y2="14" />
        </svg>
        Create Account
      </button>
    </form>
    <a href="users_list" class="cf-btn cf-btn-secondary cf-btn-full"
      style="margin-top:10px;justify-content:center;">Back to Users</a>
  </div>
</body>
</html>