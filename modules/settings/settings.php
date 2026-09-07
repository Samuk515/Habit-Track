<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/auth.php';
requireLogin();
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/email_verification.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

$stmt = mysqli_prepare($conn, 'SELECT name, email, password FROM USER WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    header('Location: ../auth/logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['current_password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!password_verify($password, $user['password'])) {
        $errors[] = 'The current password is incorrect.';
    } elseif (strcasecmp($email, $user['email']) === 0) {
        $errors[] = 'Enter a different email address.';
    }

    if (empty($errors)) {
        $duplicateStmt = mysqli_prepare($conn, 'SELECT user_id FROM USER WHERE email = ? AND user_id <> ?');
        mysqli_stmt_bind_param($duplicateStmt, 'si', $email, $userId);
        mysqli_stmt_execute($duplicateStmt);
        $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($duplicateStmt));
        mysqli_stmt_close($duplicateStmt);

        if ($duplicate) {
            $errors[] = 'That email address is already in use.';
        }
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        try {
            $updateStmt = mysqli_prepare(
                $conn,
                'UPDATE USER SET email = ?, email_verified_at = NULL WHERE user_id = ?'
            );
            mysqli_stmt_bind_param($updateStmt, 'si', $email, $userId);
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new RuntimeException('Could not update the email address.');
            }
            mysqli_stmt_close($updateStmt);

            $token = createEmailVerificationToken($conn, $userId);
            if (!sendVerificationEmail($email, $user['name'], $token)) {
                throw new RuntimeException('Could not send the verification email.');
            }

            mysqli_commit($conn);

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();

            header('Location: ../auth/login.php?email_changed=1');
            exit;
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            error_log('Email change failed: ' . $exception->getMessage());
            $errors[] = 'We could not change your email address. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings — Habit Track</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="settings.css">
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
    <main class="main-content settings-content">
      <div class="page-header">
        <h1>Settings</h1>
      </div>
      <section class="settings-panel">
        <h2>Change email address</h2>
        <p class="settings-note">Changing your email signs you out and requires verification before you can log in again.</p>

        <?php if (!empty($errors)): ?>
          <div class="error-box">
            <?php foreach ($errors as $error): ?>
              <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
          <label for="email">New email address</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>" required>

          <label for="current_password">Current password</label>
          <input type="password" id="current_password" name="current_password" required>

          <button type="submit" class="btn-primary">Change email</button>
        </form>
      </section>
    </main>
  </div>
</body>
</html>