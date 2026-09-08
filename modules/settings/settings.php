<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/email_verification.php';
require __DIR__ . '/../../includes/csrf.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_name') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'UPDATE USER SET name = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $name, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Keep the session's cached name in sync — dashboard.php
            // reads $_SESSION['name'] directly for the welcome message.
            $_SESSION['name'] = $name;

            header('Location: settings.php?success=name');
            exit;
        }
    }

    if ($action === 'change_email') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password_email'] ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        $userStmt = mysqli_prepare($conn, 'SELECT password FROM USER WHERE user_id = ?');
        mysqli_stmt_bind_param($userStmt, 'i', $userId);
        mysqli_stmt_execute($userStmt);
        $userResult = mysqli_stmt_get_result($userStmt);
        $userRow = mysqli_fetch_assoc($userResult);
        mysqli_stmt_close($userStmt);

        if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (empty($errors)) {
            $checkStmt = mysqli_prepare($conn, 'SELECT user_id FROM USER WHERE email = ? AND user_id != ?');
            mysqli_stmt_bind_param($checkStmt, 'si', $newEmail, $userId);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            if (mysqli_fetch_assoc($checkResult)) {
                $errors[] = 'That email is already in use by another account.';
            }
            mysqli_stmt_close($checkStmt);
        }

        if (empty($errors)) {
            // Changing email resets verification — the new address
            // hasn't been proven yet, so email_verified_at goes back
            // to NULL, same state a brand-new registration starts in.
            $stmt = mysqli_prepare($conn, 'UPDATE USER SET email = ?, email_verified_at = NULL WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $newEmail, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $nameForEmail = $_SESSION['name'] ?? '';
            $token = createEmailVerificationToken($conn, $userId);
            sendVerificationEmail($newEmail, $nameForEmail, $token);

            // Force logout — leaving the session active would let the
            // account keep working on an unverified email indefinitely,
            // which defeats the purpose of verification entirely.
            $_SESSION = [];
            session_destroy();
            header('Location: /modules/auth/login.php?email_changed=1');
            exit;
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userStmt = mysqli_prepare($conn, 'SELECT password FROM USER WHERE user_id = ?');
        mysqli_stmt_bind_param($userStmt, 'i', $userId);
        mysqli_stmt_execute($userStmt);
        $userResult = mysqli_stmt_get_result($userStmt);
        $userRow = mysqli_fetch_assoc($userResult);
        mysqli_stmt_close($userStmt);

        if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'UPDATE USER SET password = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $hashedPassword, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: settings.php?success=password');
            exit;
        }
    }

    if ($action === 'update_preferences') {
        $notificationsEnabled = isset($_POST['notifications_enabled']) ? 1 : 0;

        $stmt = mysqli_prepare($conn, 'UPDATE USER SET notifications_enabled = ? WHERE user_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $notificationsEnabled, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: settings.php?success=preferences');
        exit;
    }

      if ($action === 'delete_account') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
          $errors[] = 'Your session expired. Please reload the page and try again.';
        }

        if (empty($errors)) {
          $currentPassword = $_POST['delete_password'] ?? '';
          $passwordStmt = mysqli_prepare($conn, 'SELECT password FROM USER WHERE user_id = ?');
          mysqli_stmt_bind_param($passwordStmt, 'i', $userId);
          mysqli_stmt_execute($passwordStmt);
          $passwordResult = mysqli_stmt_get_result($passwordStmt);
          $passwordRow = mysqli_fetch_assoc($passwordResult);
          mysqli_stmt_close($passwordStmt);

          if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password'])) {
            $errors[] = 'Current password is incorrect.';
          }
        }

        if (empty($errors)) {
          mysqli_begin_transaction($conn);
          $reminderStmt = mysqli_prepare($conn, 'DELETE FROM REMINDER WHERE user_id = ?');
          mysqli_stmt_bind_param($reminderStmt, 'i', $userId);
          mysqli_stmt_execute($reminderStmt);
          mysqli_stmt_close($reminderStmt);

          $deleteStmt = mysqli_prepare($conn, 'DELETE FROM USER WHERE user_id = ?');
          mysqli_stmt_bind_param($deleteStmt, 'i', $userId);
          mysqli_stmt_execute($deleteStmt);
          $deleted = mysqli_stmt_affected_rows($deleteStmt) === 1;
          mysqli_stmt_close($deleteStmt);

          if ($deleted) {
            mysqli_commit($conn);
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
              $params = session_get_cookie_params();
              setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            session_destroy();
            header('Location: /modules/auth/login.php?account_deleted=1');
            exit;
          }

          mysqli_rollback($conn);
          $errors[] = 'Unable to delete your account. Please try again.';
        }
      }
}

$userStmt = mysqli_prepare($conn, 'SELECT name, email, notifications_enabled, email_verified_at FROM USER WHERE user_id = ?');
mysqli_stmt_bind_param($userStmt, 'i', $userId);
mysqli_stmt_execute($userStmt);
$userResult = mysqli_stmt_get_result($userStmt);
$user = mysqli_fetch_assoc($userResult);
mysqli_stmt_close($userStmt);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Settings — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="settings.css?v=20260801-1">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require __DIR__ . '/../../includes/logo.php'; ?>
      <a href="../dashboard/dashboard.php" class="nav-item">Dashboard</a>
      <a href="../habits/habits.php" class="nav-item">Habits</a>
      <a href="../categories/categories.php" class="nav-item">Categories</a>
      <a href="../reminders/reminders.php" class="nav-item">Reminders</a>
      <a href="../calendar/calendar.php" class="nav-item">Calendar</a>
      <a href="settings.php" class="nav-item active">Settings</a>
      <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">Logout</a>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header"><h1>Settings</h1></div>

      <?php foreach ($errors as $err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <div class="settings-section">
        <h2 class="section-heading">Profile</h2>
        <div class="auth-card settings-card">
          <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_name">
            <div class="field">
              <label class="settings-label">Name</label>
              <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <button type="submit" class="btn-primary">Save name</button>
          </form>
        </div>
      </div>

      <div class="settings-section">
        <h2 class="section-heading">Email</h2>
        <div class="auth-card settings-card">
          <p class="settings-current-value">
            Current: <?php echo htmlspecialchars($user['email']); ?>
            <?php if ($user['email_verified_at']): ?>
              <span class="badge-good">Verified</span>
            <?php else: ?>
              <span class="badge-bad">Not verified</span>
            <?php endif; ?>
          </p>
          <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="change_email">
            <div class="field">
              <label class="settings-label">New email</label>
              <input type="email" name="email" required>
            </div>
            <div class="field">
              <label class="settings-label">Current password (to confirm this change)</label>
              <input type="password" name="current_password_email" required>
            </div>
            <p class="settings-hint">Changing your email will sign you out — you'll need to verify the new address before logging back in.</p>
            <button type="submit" class="btn-primary btn-danger">Change email</button>
          </form>
        </div>
      </div>

      <div class="settings-section">
        <h2 class="section-heading">Change Password</h2>
        <div class="auth-card settings-card">
          <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="change_password">
            <div class="field">
              <label class="settings-label">Current password</label>
              <input type="password" name="current_password" required>
            </div>
            <div class="field">
              <label class="settings-label">New password</label>
              <input type="password" name="new_password" required minlength="8">
            </div>
            <div class="field">
              <label class="settings-label">Confirm new password</label>
              <input type="password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" class="btn-primary">Change password</button>
          </form>
        </div>
      </div>

      <div class="settings-section">
        <h2 class="section-heading">Preferences</h2>
        <div class="auth-card settings-card">
          <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_preferences">
            <div class="field field-checkbox">
              <input type="checkbox" name="notifications_enabled" id="notifications_enabled" class="checkbox-input" <?php echo $user['notifications_enabled'] ? 'checked' : ''; ?>>
              <label for="notifications_enabled" class="checkbox-label">Enable reminder notifications</label>
            </div>
            <p class="settings-hint">Turning this off stops reminder notifications even if your browser has permission granted.</p>
            <button type="submit" class="btn-primary">Save preferences</button>
          </form>
        </div>
      </div>

      <div class="settings-section settings-danger-section">
        <h2 class="section-heading">Delete Account</h2>
        <div class="auth-card settings-card">
          <p class="settings-hint">This permanently deletes your account, habits, logs, reminders, and calendar activity.</p>
          <form method="POST" action="settings.php" onsubmit="return confirm('Delete your account and all of its data permanently?');">
            <input type="hidden" name="action" value="delete_account">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <div class="field">
              <label class="settings-label" for="delete_password">Current password</label>
              <input type="password" name="delete_password" id="delete_password" required>
            </div>
            <button type="submit" class="btn-primary btn-danger">Delete my account</button>
          </form>
        </div>
      </div>

    </div>
  </div>

  <script>window.SERVER_ERRORS = <?php echo json_encode($errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/assets/js/toast.js"></script>
</body>
</html>