<?php
require '../includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard — Habit Track</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-layout">
    <div class="sidebar">
      <?php require '../includes/logo.php'; ?>
      <div class="nav-item active">Dashboard</div>
      <div class="nav-item">Habits</div>
      <div class="nav-item">Categories</div>
      <div style="margin-top:auto;">
        <div class="nav-item"><a href="logout.php" style="color:inherit;text-decoration:none;">Logout</a></div>
      </div>
    </div>
    <div class="main-content">
      <div class="page-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
      </div>
      <div class="empty-state">
        <p>No habits yet — this is where your habit cards will appear once Iteration 2 is built.</p>
      </div>
    </div>
  </div>
</body>
</html>