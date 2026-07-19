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
      <a href="dashboard.php" class="nav-item active">Dashboard</a>
      <a href="habits.php" class="nav-item">Habits</a>
      <a href="categories.php" class="nav-item">Categories</a>
      <div style="margin-top:auto;">
        <a href="logout.php" class="nav-item">Logout</a>
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
