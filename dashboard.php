<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();
session_regenerate_id(true);
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
  header("Location: login");
  exit;
}

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self';");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$css_ver = file_exists("assets/index.css") ? filemtime("assets/index.css") : time();
$js_ver = file_exists("assets/index.js") ? filemtime("assets/index.js") : time();
if (empty($_SESSION['csrf_token']))
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$isAdmin = ($_SESSION['user'] ?? '') === 'armin11';
$username = htmlspecialchars($_SESSION['user'] ?? '', ENT_QUOTES, 'UTF-8');
$initial = mb_strtoupper(mb_substr($_SESSION['user'] ?? 'A', 0, 1));

$logCount = 0;
$logFile = __DIR__ . '/api/messages.json';
if (file_exists($logFile)) {
  $logs = json_decode(file_get_contents($logFile), true) ?: [];
  $logCount = count($logs);
}

$userCount = null;
$actCount = 0;
if ($isAdmin) {
  require_once "users/db.php";
  $userCount = (int) $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
  $actCount = (int) $conn->query("SELECT COUNT(*) AS c FROM activity_log")->fetch_assoc()['c'];
}
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
  <title>Radeon Admin</title>
  <link rel="stylesheet" href="assets/index.css?v=<?php echo $css_ver; ?>">
</head>
<body data-css-version="<?php echo $css_ver; ?>" data-js-version="<?php echo $js_ver; ?>">

  <!-- Update bar -->
  <div id="cfUpdateBar" class="cf-update-bar">
    <div class="cf-update-inner">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
        stroke="var(--cf-blue-bright)" stroke-width="2" style="animation:cf-spin 2s linear infinite;flex-shrink:0">
        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
      </svg>
      <span>New version available</span>
      <button id="cfUpdateReload" class="cf-btn cf-btn-primary cf-btn-sm">Update now</button>
      <button id="cfUpdateDismiss" class="cf-btn cf-btn-ghost cf-btn-sm"
        style="width:28px;height:28px;padding:0;border-radius:50%;">✕</button>
    </div>
  </div>

  <!-- Topbar -->
  <header class="cf-topbar">
    <div class="cf-topbar-left">
      <a href="/" class="cf-logo">
        <div class="cf-logo-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
            fill="none" stroke="#fff" stroke-width="2.5">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
          </svg></div>
        <span class="cf-logo-text">Radeon</span>
        <span class="cf-logo-badge">Admin</span>
      </a>
      <div class="cf-topbar-divider"></div>
      <nav class="cf-top-nav">
        <?php if ($isAdmin): ?>
          <a href="users/users_list" class="cf-top-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <span>Users</span>
          </a>
        <?php endif; ?>
      </nav>
    </div>
    <div class="cf-topbar-right">
      <button class="cf-theme-btn" id="cfThemeBtn" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="5" />
          <line x1="12" y1="1" x2="12" y2="3" />
          <line x1="12" y1="21" x2="12" y2="23" />
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
          <line x1="1" y1="12" x2="3" y2="12" />
          <line x1="21" y1="12" x2="23" y2="12" />
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
        </svg>
      </button>
      <div class="cf-user-chip">
        <div class="cf-user-avatar"><?php echo $initial; ?></div>
        <span class="cf-user-name"><?php echo $username; ?></span>
      </div>
      <a href="logout" class="cf-top-link danger">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
        <span>Logout</span>
      </a>
    </div>
  </header>

  <!-- Left sidebar -->
  <aside class="cf-sidebar">
    <?php if ($isAdmin): ?>
      <div class="cf-sidebar-label">Overview</div>
      <div style="display:flex;flex-direction:column;gap:6px;padding:4px 2px 12px;">
        <div
          style="display:flex;justify-content:space-between;align-items:center;padding:6px 8px;border-radius:var(--r-md);background:var(--cf-surface-2);border:1px solid var(--cf-border-1);">
          <span style="font-size:11px;color:var(--cf-text-3);">Users</span>
          <span
            style="font-family:var(--font-mono);font-size:12px;font-weight:600;color:var(--cf-blue-bright);"><?php echo $userCount; ?></span>
        </div>
        <div
          style="display:flex;justify-content:space-between;align-items:center;padding:6px 8px;border-radius:var(--r-md);background:var(--cf-surface-2);border:1px solid var(--cf-border-1);">
          <span style="font-size:11px;color:var(--cf-text-3);">Total logs</span>
          <span
            style="font-family:var(--font-mono);font-size:12px;font-weight:600;color:var(--cf-blue-bright);"><?php echo number_format($logCount); ?></span>
        </div>
      </div>
    <?php endif; ?>
    <div class="cf-sidebar-label">Navigation</div>
    <button class="cf-nav-item active" data-nav="logs">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2">
        <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8l6 6v12a2 2 0 0 1-2 2z" />
        <path d="M14 2v5a1 1 0 0 0 1 1h5M10 9H8M16 13H8M16 17H8" />
      </svg>
      Log Monitor
      <span class="cf-nav-badge" id="cfNavLogsBadge"><?php echo number_format($logCount); ?></span>
    </button>
    <button class="cf-nav-item" data-nav="search">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8" />
        <path d="m21 21-4.35-4.35" />
      </svg>
      Global Search
    </button>
    <?php if ($isAdmin): ?>
      <button class="cf-nav-item" data-nav="activity">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
        Activity Log
        <span class="cf-nav-badge"><?php echo number_format($actCount); ?></span>
      </button>
      <div class="cf-sidebar-label" style="margin-top:16px;">Management</div>
      <a href="users/users_list" class="cf-nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        Manage Users
      </a>
      <a href="users/create_user" class="cf-nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
          <line x1="12" y1="11" x2="12" y2="17" />
          <line x1="9" y1="14" x2="15" y2="14" />
        </svg>
        New User
      </a>
    <?php endif; ?>
  </aside>

  <!-- Main -->
  <main class="cf-main" id="cfMain"></main>

  <!-- Mobile tab bar -->
  <nav class="cf-tab-bar">
    <button class="cf-tab-item active" data-nav="logs">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2">
        <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8l6 6v12a2 2 0 0 1-2 2z" />
        <path d="M14 2v5a1 1 0 0 0 1 1h5" />
      </svg>
      Logs
    </button>
    <button class="cf-tab-item" data-nav="search">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8" />
        <path d="m21 21-4.35-4.35" />
      </svg>
      Search
    </button>
    <?php if ($isAdmin): ?>
      <button class="cf-tab-item" data-nav="activity">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
        Activity
      </button>
    <?php endif; ?>
  </nav>

  <!-- Log Modal -->
  <div id="cfModal" class="cf-modal-overlay" role="dialog" aria-modal="true">
    <div class="cf-modal">
      <div class="cf-modal-head">
        <div class="cf-modal-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="var(--cf-blue-bright)" stroke-width="2">
            <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8l6 6v12a2 2 0 0 1-2 2z" />
            <path d="M14 2v5a1 1 0 0 0 1 1h5" />
          </svg>
          <span id="cfModalTitle">Logs</span>
        </div>
        <div class="cf-modal-controls">
          <div class="cf-search-wrap" style="flex:1;">
            <svg class="cf-search-icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.35-4.35" />
            </svg>
            <input type="text" id="cfModalSearch" class="cf-input" placeholder="Search in category…"
              style="font-size:13px;padding:7px 12px 7px 32px;">
          </div>
          <button id="cfModalClose" class="cf-modal-close" aria-label="Close">✕</button>
        </div>
      </div>
      <div id="cfModalBody" class="cf-modal-body"></div>
    </div>
  </div>
  <script>
    window.CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>';
    window.IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
  </script>
  <script src="assets/index.js?v=<?php echo $js_ver; ?>"></script>
  <script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');</script>
</body>
</html>