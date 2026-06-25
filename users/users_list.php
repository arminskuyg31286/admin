<?php
session_start();
require "db.php";
if (!isset($_SESSION['login'])||$_SESSION['login']!==true||$_SESSION['user']!=='armin11') { header("Location: ../"); exit; }
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
header("X-Content-Type-Options: nosniff"); header("X-Frame-Options: DENY"); header("X-XSS-Protection: 1; mode=block"); header("Referrer-Policy: strict-origin-when-cross-origin");
if (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on') header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$users  = $conn->query("SELECT id, username FROM users ORDER BY id DESC");
$cssVer = file_exists('../assets/index.css') ? filemtime('../assets/index.css') : time();
$success = $_SESSION['success'] ?? null; $error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
$permCounts = [];
$pcRes = $conn->query("SELECT user_id, COUNT(*) AS cnt FROM user_permissions GROUP BY user_id");
if ($pcRes) { while($pc=$pcRes->fetch_assoc()) $permCounts[(int)$pc['user_id']]=(int)$pc['cnt']; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Users — Radeon Admin</title>
  <link rel="stylesheet" href="../assets/index.css?v=<?php echo $cssVer; ?>">
</head>
<body class="cf-users-page">
<!-- Header bar -->
<header class="cf-users-header-bar">
  <div style="display:flex;align-items:center;gap:14px;">
    <a href="/" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
      <div class="cf-logo-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
      <span style="font-size:15px;font-weight:700;color:var(--cf-text-1);letter-spacing:-0.02em;">Radeon</span>
    </a>
    <div style="width:1px;height:18px;background:var(--cf-border-2);"></div>
    <span style="font-size:13px;color:var(--cf-text-3);">User Management</span>
  </div>
  <a href="/" class="cf-btn cf-btn-secondary cf-btn-sm">
    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Dashboard
  </a>
</header>
<div class="cf-users-content">
  <!-- Page header -->
  <div class="cf-page-header-row" style="margin-bottom:24px;">
    <div>
      <div class="cf-breadcrumb">
        <a href="/">Dashboard</a><span class="cf-breadcrumb-sep">/</span><span>Users</span>
      </div>
      <h1 class="cf-page-title">Users</h1>
      <p class="cf-page-sub">Manage user accounts and log permissions</p>
    </div>
    <div class="cf-page-actions">
      <span class="cf-badge cf-badge-blue"><?php echo $users ? $users->num_rows : 0; ?> total</span>
      <a href="create_user" class="cf-btn cf-btn-success">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New User
      </a>
    </div>
  </div>
  <?php if ($success): ?>
  <div class="cf-alert cf-alert-success" role="alert">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="cf-alert cf-alert-error" role="alert">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
  </div>
  <?php endif; ?>

  <div class="cf-card">
    <div class="cf-table-wrap">
      <table class="cf-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Log Access</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($users && $users->num_rows > 0): ?>
          <?php while ($u = $users->fetch_assoc()): ?>
          <tr>
            <td><span class="cf-mono">#<?php echo (int)$u['id']; ?></span></td>
            <td>
              <div class="cf-table-user">
                <span class="cf-table-avatar"><?php echo mb_strtoupper(mb_substr($u['username'],0,1)); ?></span>
                <span style="color:var(--cf-text-1);font-weight:500;"><?php echo htmlspecialchars($u['username'],ENT_QUOTES,'UTF-8'); ?></span>
                <?php if ($u['username']==='armin11'): ?>
                  <span class="cf-protected" style="margin-left:8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Admin
                  </span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($u['username']==='armin11'): ?>
                <span class="cf-badge cf-badge-indigo">All categories</span>
              <?php else: ?>
                <?php $pc=$permCounts[(int)$u['id']]??0; ?>
                <span class="cf-badge <?php echo $pc>0?'cf-badge-blue':'cf-badge-gray'; ?>">
                  <?php echo $pc; ?> cat<?php echo $pc===1?'':'s'; ?>
                </span>
              <?php endif; ?>
            </td>
            <td>
              <div class="cf-table-actions">
                <a class="cf-btn cf-btn-indigo cf-btn-sm" href="edit_user?id=<?php echo (int)$u['id']; ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </a>
                <?php if ($u['username']!=='armin11'): ?>
                  <a class="cf-btn cf-btn-secondary cf-btn-sm" href="user_permissions?id=<?php echo (int)$u['id']; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Permissions
                  </a>
                  <form method="POST" action="delete_user" style="display:inline;margin:0"
                    onsubmit="return confirm(<?php echo json_encode('Delete user «'.$u['username'].'»? This action cannot be undone.'); ?>)">
                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                    <button type="submit" class="cf-btn cf-btn-danger cf-btn-sm">
                      <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                      Delete
                    </button>
                  </form>
                <?php else: ?>
                  <span class="cf-protected">Protected</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4" style="text-align:center;padding:48px;color:var(--cf-text-3);">No users found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>