<?php
session_start();
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
require "db.php";
require "activity.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || $_SESSION['user'] !== 'armin11') {
  header("Location: ../");
  exit;
}

if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id'])) {
  header("Location: users_list");
  exit;
}

$id = (int) $_GET['id'];
if (empty($_SESSION['csrf_token']))
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  header("Location: users_list");
  exit;
}

if ($row['username'] === 'armin11') {
  header("Location: users_list");
  exit;
}

$msgFile = __DIR__ . '/../api/messages.json';
$allCats = [];
if (file_exists($msgFile)) {
  $msgs = json_decode(file_get_contents($msgFile), true);
  if (is_array($msgs)) {
    foreach ($msgs as $m) {
      $cat = trim($m['category'] ?? '');
      if ($cat !== '')
        $allCats[$cat] = true;
    }
    ksort($allCats);
    $allCats = array_keys($allCats);
  }
}

$pStmt = $conn->prepare("SELECT category FROM user_permissions WHERE user_id = ?");
$pStmt->bind_param("i", $id);
$pStmt->execute();
$pRes = $pStmt->get_result();
$current = [];
while ($p = $pRes->fetch_assoc())
$current[$p['category']] = true;
$pStmt->close();
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
    $error = "Invalid request.";
  else {
    $selected = isset($_POST['categories']) && is_array($_POST['categories']) ? array_filter(array_map('trim', $_POST['categories']), fn($c) => $c !== '') : [];
    $del = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
    if (!empty($selected)) {
      $ins = $conn->prepare("INSERT IGNORE INTO user_permissions (user_id, category) VALUES (?, ?)");
      foreach ($selected as $cat) {
        $ins->bind_param("is", $id, $cat);
        $ins->execute();
      }
      $ins->close();
    }
    $current = array_flip($selected);
    $count = count($selected);
    $success = "Permissions saved — $count " . ($count === 1 ? 'category' : 'categories') . " assigned.";
    logActivity($conn, 'armin11', "Updated log permissions for user '{$row['username']}' → $count categories");
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
  <title>Permissions — <?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="../assets/index.css?v=<?php echo $cssVer; ?>">
</head>
<body class="cf-perm-page">
  <div class="cf-perm-card">
    <div class="cf-perm-header">
      <div class="cf-perm-avatar"><?php echo mb_strtoupper(mb_substr($row['username'], 0, 1)); ?></div>
      <div>
        <div class="cf-perm-title">Log Permissions</div>
        <div class="cf-perm-sub">
          User: <strong
            style="color:var(--cf-blue-bright);"><?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
          &nbsp;·&nbsp; ID #<?php echo $id; ?>
          &nbsp;·&nbsp; <span id="selCount" class="cf-badge cf-badge-blue"><?php echo count($current); ?></span>
          selected
        </div>
      </div>
      <a href="users_list" class="cf-btn cf-btn-secondary cf-btn-sm" style="margin-left:auto;">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12" />
          <polyline points="12 19 5 12 12 5" />
        </svg>
        Back
      </a>
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
    <form method="POST" id="permForm">
      <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <div class="cf-cats-header">
        <span class="cf-cats-label">
          Available Categories
          <?php if (!empty($allCats)): ?><span
              style="color:var(--cf-text-4);font-weight:400;margin-left:5px;">(<?php echo count($allCats); ?>
              total)</span><?php endif; ?>
        </span>
        <?php if (!empty($allCats)): ?>
          <div class="cf-select-actions">
            <button type="button" class="cf-btn cf-btn-secondary cf-btn-sm" onclick="selAll()">Select All</button>
            <button type="button" class="cf-btn cf-btn-secondary cf-btn-sm" onclick="selNone()">Clear</button>
          </div>
        <?php endif; ?>
      </div>
      <?php if (empty($allCats)): ?>
        <div class="cf-no-cats">
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" style="opacity:.2;display:block;margin:0 auto 12px;">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <p>No log categories found.<br><span style="font-size:11px;opacity:.7;">Categories appear automatically once
              logs are received via webhook.</span></p>
        </div>
      <?php else: ?>
        <div class="cf-cats-grid" id="catsGrid">
          <?php foreach ($allCats as $cat):
            $checked = isset($current[$cat]); ?>
            <label class="cf-cat-item <?php echo $checked ? 'checked' : ''; ?>">
              <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $checked ? 'checked' : ''; ?> onchange="upd(this)">
              <span class="cf-cat-item-name"
                title="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($allCats)): ?>
        <div class="cf-perm-footer">
          <button type="submit" class="cf-btn cf-btn-primary cf-btn-lg cf-btn-full">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            Save Permissions
          </button>
        </div>
      <?php endif; ?>
    </form>
  </div>
  <script>
    function upd(cb) { const l = cb.closest('.cf-cat-item'); cb.checked ? l.classList.add('checked') : l.classList.remove('checked'); updateCount() }
    function updateCount() { const n = document.querySelectorAll('#catsGrid input:checked').length; document.getElementById('selCount').textContent = n }
    function selAll() { document.querySelectorAll('#catsGrid input').forEach(cb => { cb.checked = true; cb.closest('.cf-cat-item').classList.add('checked') }); updateCount() }
    function selNone() { document.querySelectorAll('#catsGrid input').forEach(cb => { cb.checked = false; cb.closest('.cf-cat-item').classList.remove('checked') }); updateCount() }
  </script>
</body>
</html>