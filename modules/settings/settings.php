<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/db.php';
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

            $_SESSION['name'] = $name;

            header('Location: settings.php?success=name');
            exit;
        }
    }

    if ($action === 'add_secondary_email') {
        $secondaryEmail = trim($_POST['secondary_email'] ?? '');

        if (!filter_var($secondaryEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        $primaryStmt = mysqli_prepare($conn, 'SELECT email FROM USER WHERE user_id = ?');
        mysqli_stmt_bind_param($primaryStmt, 'i', $userId);
        mysqli_stmt_execute($primaryStmt);
        $primaryResult = mysqli_stmt_get_result($primaryStmt);
        $primaryRow = mysqli_fetch_assoc($primaryResult);
        mysqli_stmt_close($primaryStmt);

        if ($primaryRow && strcasecmp($primaryRow['email'], $secondaryEmail) === 0) {
            $errors[] = 'Secondary email must be different from your primary email.';
        }

        if (empty($errors)) {
            $checkStmt = mysqli_prepare($conn, 'SELECT user_id FROM USER WHERE (email = ? OR secondary_email = ?) AND user_id != ?');
            mysqli_stmt_bind_param($checkStmt, 'ssi', $secondaryEmail, $secondaryEmail, $userId);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            if (mysqli_fetch_assoc($checkResult)) {
                $errors[] = 'That email is already in use.';
            }
            mysqli_stmt_close($checkStmt);
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, 'UPDATE USER SET secondary_email = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $secondaryEmail, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: settings.php?success=secondary_email');
            exit;
        }
    }

      if ($action === 'make_secondary_primary') {
        $stmt = mysqli_prepare($conn, 'SELECT email, secondary_email FROM USER WHERE user_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $emailRow = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$emailRow || empty($emailRow['secondary_email'])) {
          $errors[] = 'Add a secondary email before making it primary.';
        } else {
          mysqli_begin_transaction($conn);
          $newPrimary = $emailRow['secondary_email'];
          $newSecondary = $emailRow['email'];
          $swapStmt = mysqli_prepare($conn, 'UPDATE USER SET email = ?, secondary_email = ? WHERE user_id = ?');
          mysqli_stmt_bind_param($swapStmt, 'ssi', $newPrimary, $newSecondary, $userId);
          $swapOk = mysqli_stmt_execute($swapStmt);
          mysqli_stmt_close($swapStmt);

          if ($swapOk) {
            mysqli_commit($conn);
            header('Location: settings.php?success=primary_email');
            exit;
          }

          mysqli_rollback($conn);
          $errors[] = 'Unable to make the secondary email primary. Please try again.';
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

    if ($action === 'deactivate_account') {
        $currentPassword = $_POST['deactivate_password'] ?? '';

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session expired. Please reload the page and try again.';
        }

        if (empty($errors)) {
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
            $cascadeOk = true;

            $steps = [
                'DELETE FROM CALENDAR_EVENT WHERE user_id = ?',
                'DELETE FROM REMINDER WHERE user_id = ?',
                'DELETE FROM Bad_Habit_Progress WHERE log_id IN (
                    SELECT log_id FROM HABIT_LOG WHERE habit_id IN (
                        SELECT habit_id FROM HABIT WHERE category_id IN (
                            SELECT category_id FROM CATEGORY WHERE user_id = ?
                        )
                    )
                )',
                'DELETE FROM SUBTASK WHERE habit_id IN (
                    SELECT habit_id FROM HABIT WHERE category_id IN (
                        SELECT category_id FROM CATEGORY WHERE user_id = ?
                    )
                )',
                'DELETE FROM HABIT_LOG WHERE habit_id IN (
                    SELECT habit_id FROM HABIT WHERE category_id IN (
                        SELECT category_id FROM CATEGORY WHERE user_id = ?
                    )
                )',
                'DELETE FROM STREAK WHERE habit_id IN (
                    SELECT habit_id FROM HABIT WHERE category_id IN (
                        SELECT category_id FROM CATEGORY WHERE user_id = ?
                    )
                )',
                'DELETE FROM HABIT WHERE category_id IN (
                    SELECT category_id FROM CATEGORY WHERE user_id = ?
                )',
                'DELETE FROM CATEGORY WHERE user_id = ?',
                'DELETE FROM EMAIL_VERIFICATION WHERE user_id = ?',
            ];

            foreach ($steps as $sql) {
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                if (!mysqli_stmt_execute($stmt)) {
                    $cascadeOk = false;
                }
                mysqli_stmt_close($stmt);
                if (!$cascadeOk) {
                    break;
                }
            }

            if ($cascadeOk) {
                $finalStmt = mysqli_prepare($conn, 'DELETE FROM USER WHERE user_id = ?');
                mysqli_stmt_bind_param($finalStmt, 'i', $userId);
                $cascadeOk = mysqli_stmt_execute($finalStmt) && mysqli_stmt_affected_rows($finalStmt) === 1;
                mysqli_stmt_close($finalStmt);
            }

            if ($cascadeOk) {
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
            $errors[] = 'Unable to deactivate your account. Please try again.';
        }
    }
}

$userStmt = mysqli_prepare($conn, 'SELECT name, email, secondary_email, email_verified_at FROM USER WHERE user_id = ?');
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
            Primary: <?php echo htmlspecialchars($user['email']); ?>
            <?php if ($user['email_verified_at']): ?>
              <span class="badge-good">Verified</span>
            <?php else: ?>
              <span class="badge-bad">Not verified</span>
            <?php endif; ?>
          </p>
          <?php if (!empty($user['secondary_email'])): ?>
            <p class="settings-current-value">Secondary: <?php echo htmlspecialchars($user['secondary_email']); ?></p>
            <form method="POST" action="settings.php" class="inline-form">
              <input type="hidden" name="action" value="make_secondary_primary">
              <button type="submit" class="btn-secondary">Make secondary primary</button>
            </form>
          <?php endif; ?>
          <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="add_secondary_email">
            <div class="field">
              <label class="settings-label">Secondary email</label>
              <input type="email" name="secondary_email" value="<?php echo htmlspecialchars($user['secondary_email'] ?? ''); ?>" required>
            </div>
            <button type="submit" class="btn-primary">Save secondary email</button>
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

      <div class="settings-section settings-danger-section">
        <h2 class="section-heading">Delete Account</h2>
        <div class="auth-card settings-card">
          <p class="settings-hint">This permanently deletes your account, habits, logs, reminders, and calendar activity.</p>
          <form method="POST" action="settings.php" onsubmit="return confirm('Deactivate your account? This cannot be undone.');">
            <input type="hidden" name="action" value="deactivate_account">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <div class="field">
              <label class="settings-label" for="deactivate_password">Current password</label>
              <input type="password" name="deactivate_password" id="deactivate_password" required>
            </div>
            <button type="submit" class="btn-primary btn-danger">Deactivate my account</button>
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
