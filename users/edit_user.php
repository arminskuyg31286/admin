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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: users_list");
  exit;
}

$id = (int) $_GET['id'];
if (empty($_SESSION['csrf_token']))
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  header("Location: users_list");
  exit;
}

$user = $result->fetch_assoc();
$stmt->close();
$success = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $error = "Invalid request.";
  } else {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    if ($username === '')
      $error = "Username cannot be empty.";
    elseif (strlen($username) < 3 || strlen($username) > 50)
      $error = "Username must be 3–50 characters.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
      $error = "Username can only contain letters, numbers and _.";
    elseif ($password !== '' && strlen($password) < 6)
      $error = "Password must be at least 6 characters.";
    else {
      $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
      $check->bind_param("si", $username, $id);
      $check->execute();
      if ($check->get_result()->num_rows > 0)
        $error = "Username already taken by another user.";
      else {
        if ($password !== '') {
          $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
          $update = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
          $update->bind_param("ssi", $username, $hash, $id);
        } else {
          $update = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
          $update->bind_param("si", $username, $id);
        }
        if ($update->execute()) {
          $success = "User updated successfully.";
          $user['username'] = $username;
          $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else
          $error = "Error updating user.";
        $update->close();
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
  <title>Edit User — Radeon Admin</title>
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
            class="cf-breadcrumb-sep">/</span><span>Edit</span></div>
        <h1 class="cf-form-title" style="margin:0;">Edit User</h1>
      </div>
    </div>
    <div
      style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--cf-surface-2);border:1px solid var(--cf-border-1);border-radius:var(--r-lg);margin-bottom:20px;">
      <div class="cf-user-avatar" style="width:32px;height:32px;font-size:13px;">
        <?php echo mb_strtoupper(mb_substr($user['username'], 0, 1)); ?></div>
      <div>
        <div style="font-size:13px;font-weight:600;color:var(--cf-text-1);">
          <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="cf-mono" style="font-size:11px;">ID #<?php echo $id; ?></div>
      </div>
    </div>
    <?php if ($success): ?>
      <div class="cf-alert cf-alert-success"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"
          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
          <polyline points="20 6 9 17 4 12" />
        </svg><span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="cf-alert cf-alert-error"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"
          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
          <circle cx="12" cy="12" r="10" />
          <line x1="15" y1="9" x2="9" y2="15" />
          <line x1="9" y1="9" x2="15" y2="15" />
        </svg><span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span></div>
    <?php endif; ?>
    <form method="POST" class="cf-form-body" autocomplete="off">
      <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <div class="cf-input-group">
        <label class="cf-label" for="username">Username</label>
        <input class="cf-input" type="text" id="username" name="username"
          value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>"
          placeholder="letters, numbers, underscore" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+"
          autocomplete="off">
      </div>
      <div class="cf-input-group">
        <label class="cf-label" for="password">New Password <span style="color:var(--cf-text-4);font-weight:400;">(leave
            blank to keep current)</span></label>
        <input class="cf-input" type="password" id="password" name="password" placeholder="Minimum 6 characters"
          minlength="6" autocomplete="new-password">
      </div>
      <button type="submit" class="cf-btn cf-btn-primary cf-btn-lg cf-btn-full">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
          <polyline points="17 21 17 13 7 13 7 21" />
          <polyline points="7 3 7 8 15 8" />
        </svg>
        Save Changes
      </button>
    </form>
    <a href="users_list" class="cf-btn cf-btn-secondary cf-btn-full"
      style="margin-top:10px;justify-content:center;">Back to Users</a>
  </div>
</body>
</html>