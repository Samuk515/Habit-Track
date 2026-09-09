<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/email_verification.php';
require_once __DIR__ . '/../../includes/csrf.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($action === 'verify' && $userId > 0) {
            $stmt = mysqli_prepare($conn, 'UPDATE USER SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $message = mysqli_stmt_affected_rows($stmt) > 0 ? 'User marked as verified.' : 'User was already verified or was not found.';
            mysqli_stmt_close($stmt);
        }

        if ($action === 'resend' && $userId > 0) {
            $stmt = mysqli_prepare($conn, 'SELECT name, email, email_verified_at FROM USER WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$user) {
                $error = 'User was not found.';
            } elseif ($user['email_verified_at'] !== null) {
                $error = 'This user is already verified.';
            } else {
                $token = createEmailVerificationToken($conn, $userId);
                $message = sendVerificationEmail($user['email'], $user['name'], $token)
                    ? 'Verification email sent.'
                    : 'Email delivery failed. Check Mailpit or the application logs.';
            }
        }
    }
}

$users = [];
$result = mysqli_query($conn, 'SELECT user_id, name, email, email_verified_at, created_at FROM USER ORDER BY created_at DESC');
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Habit Track</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
  .admin-content { padding: 32px; max-width: 1200px; }
  .admin-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
  .admin-links { display: flex; gap: 12px; flex-wrap: wrap; }
  .admin-link, .admin-action { display: inline-block; border: 0; border-radius: 6px; padding: 10px 14px; background: #087cc1; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
  .admin-link.secondary { background: #e7eef7; color: #1f2a44; }
  .admin-table { width: 100%; border-collapse: collapse; background: #fff; }
  .admin-table th, .admin-table td { padding: 14px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
  .admin-table th { color: #38506a; font-size: 0.9rem; }
  .admin-status { font-weight: 600; color: #078b65; }
  .admin-status.pending { color: #b26b00; }
  .admin-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .admin-action.verify { background: #078b65; }
  .admin-action.resend { background: #087cc1; }
  .admin-notice { padding: 14px 16px; border-radius: 6px; margin-bottom: 18px; background: #e0f5ed; color: #075c48; }
  .admin-notice.error { background: #fde7e7; color: #8b2020; }
  @media (max-width: 760px) { .admin-content { padding: 20px 14px; overflow-x: auto; } .admin-toolbar { align-items: flex-start; flex-direction: column; } .admin-table { min-width: 720px; } }
</style>
</head>
<body>
<div class="app-layout">
  <div class="sidebar">
    <?php require __DIR__ . '/../../includes/logo.php'; ?>
    <a href="../dashboard/dashboard.php" class="nav-item">User dashboard</a>
    <a href="dashboard.php" class="nav-item active">Admin</a>
    <div class="sidebar-footer"><a href="logout.php" class="nav-item">Admin logout</a></div>
  </div>
  <main class="main-content admin-content">
    <div class="admin-toolbar">
      <div><h1>Admin email verification</h1><p>Review accounts and manage local verification email delivery.</p></div>
      <div class="admin-links"><a class="admin-link" href="http://localhost:8025" target="_blank" rel="noopener">Open Mailpit inbox</a><a class="admin-link secondary" href="logout.php">Log out</a></div>
    </div>

    <?php if ($message !== ''): ?><div class="admin-notice"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="admin-notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <table class="admin-table">
      <thead><tr><th>Name</th><th>Email</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= htmlspecialchars($user['name']) ?></td>
          <td><?= htmlspecialchars($user['email']) ?></td>
          <td><?= htmlspecialchars($user['created_at']) ?></td>
          <td><span class="admin-status <?= $user['email_verified_at'] ? '' : 'pending' ?>"><?= $user['email_verified_at'] ? 'Verified' : 'Pending' ?></span></td>
          <td><div class="admin-actions">
            <?php if (!$user['email_verified_at']): ?>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>"><input type="hidden" name="action" value="verify"><input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>"><button class="admin-action verify" type="submit">Verify email</button></form>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>"><input type="hidden" name="action" value="resend"><input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>"><button class="admin-action resend" type="submit">Resend email</button></form>
            <?php else: ?>
            <span>Completed</span>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </main>
</div>
</body>
</html>