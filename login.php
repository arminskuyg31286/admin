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

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
  header("Location: /");
  exit;
}

if (empty($_SESSION['csrf_token']))
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$now = time();
$_SESSION['login_attempts'] = array_values(array_filter($_SESSION['login_attempts'] ?? [], fn($t) => ($now - $t) < 300));
$attempts = count($_SESSION['login_attempts']);
$locked = $attempts >= 5;
$lockLeft = $locked ? 300 - ($now - min($_SESSION['login_attempts'])) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: login");
    exit;
  }
  if ($locked) {
    $_SESSION['error'] = "Too many failed attempts. Try again in {$lockLeft}s.";
    header("Location: login");
    exit;
  }
  require "users/db.php";
  require "users/activity.php";
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');
  if ($username === '' || $password === '') {
    $_SESSION['error'] = "Username and password are required.";
    header("Location: login");
    exit;
  }
  $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows === 1) {
    $stmt->bind_result($hash);
    $stmt->fetch();
    if (password_verify($password, $hash)) {
      $_SESSION['login_attempts'] = [];
      session_regenerate_id(true);
      $_SESSION['login'] = true;
      $_SESSION['user'] = $username;
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      logActivity($conn, $username, "Logged in");
      header("Location: /");
      exit;
    }
  }
  $stmt->close();
  $_SESSION['login_attempts'][] = $now;
  $rem = 5 - count($_SESSION['login_attempts']);
  $_SESSION['error'] = $rem > 0 ? "Invalid credentials. ($rem attempt" . ($rem === 1 ? '' : 's') . " left)" : "Account locked for 5 minutes.";
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  header("Location: login");
  exit;
}

$css_ver = file_exists('assets/index.css') ? filemtime('assets/index.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#3b82f6">
  <meta name="robots" content="noindex, nofollow">
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <title>Sign In — Radeon Admin</title>
  <link rel="stylesheet" href="assets/index.css?v=<?php echo $css_ver; ?>">
</head>
<body class="cf-login-page">
  <div class="cf-login-card">
    <div class="cf-login-logo">
      <div class="cf-login-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
          stroke="var(--cf-blue-bright)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
      </div>
      <h1>Radeon Admin</h1>
      <p>Sign in to your account</p>
    </div>
      <?php if ($locked): ?>
      <div class="cf-alert cf-alert-warning" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>Account locked — <?php echo $lockLeft; ?>s remaining</span>
      </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
      <div class="cf-alert cf-alert-error" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px">
          <circle cx="12" cy="12" r="10" />
          <line x1="15" y1="9" x2="9" y2="15" />
          <line x1="9" y1="9" x2="15" y2="15" />
        </svg>
        <span><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
        unset($_SESSION['error']); ?></span>
      </div>
      <?php endif; ?>
    <form method="POST" action="login" class="cf-login-form" autocomplete="off">
      <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <div class="cf-input-group">
        <label class="cf-label" for="username">Username</label>
        <input class="cf-input" type="text" id="username" name="username" placeholder="Enter your username" required
          minlength="3" maxlength="50" autocomplete="username" <?php echo $locked ? 'disabled' : ''; ?>>
      </div>
      <div class="cf-input-group">
        <label class="cf-label" for="password">Password</label>
        <input class="cf-input" type="password" id="password" name="password" placeholder="Enter your password" required
          minlength="6" autocomplete="current-password" <?php echo $locked ? 'disabled' : ''; ?>>
      </div>
      <button type="submit" class="cf-btn cf-btn-primary cf-btn-lg cf-btn-full" <?php echo $locked ? 'disabled' : ''; ?>>
        Sign In
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <line x1="5" y1="12" x2="19" y2="12" />
          <polyline points="12 5 19 12 12 19" />
        </svg>
      </button>
    </form>
    <div class="cf-login-footer">&copy; <?php echo date('Y'); ?> Radeon RP &middot; Admin Panel</div>
  </div>
  <script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');</script>
</body>
</html>