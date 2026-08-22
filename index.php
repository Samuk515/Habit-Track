<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /modules/dashboard/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Habit Track — Build better habits</title>
<link rel="stylesheet" href="landing.css">
</head>
<body>
  <header class="landing-header">
    <div class="landing-logo">
      <?php require __DIR__ . '/includes/logo.php'; ?>
    </div>
    <nav class="landing-nav">
      <a href="modules/auth/login.php" class="btn-nav-login">Log In</a>
      <a href="modules/auth/register.php" class="btn-nav-signup">Sign Up</a>
    </nav>
  </header>

  <main>
    <section class="hero">
      <h1>Build better habits,<br>one small step at a time.</h1>
      <p class="hero-subtitle">Track habits, break them into manageable subtasks, and see your progress build day by day.</p>
      <div class="hero-actions">
        <a href="modules/auth/register.php" class="btn-hero-primary">Get Started — It's Free</a>
        <a href="modules/auth/login.php" class="btn-hero-secondary">I already have an account →</a>
      </div>
    </section>

    <section class="features">
      <div class="feature-card">
        <div class="feature-icon feature-icon--category">C</div>
        <h3>Organize with Categories</h3>
        <p>Group your habits by Health, Study, Finance, or anything else that matters to you.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon feature-icon--habit">H</div>
        <h3>Break Habits into Subtasks</h3>
        <p>Split a big habit like "Gym" into Chest Day, Leg Day, Cardio — and track each one on its own.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon feature-icon--reminder">R</div>
        <h3>Never Miss a Beat</h3>
        <p>Set reminders tied to specific subtasks, so you always know when it's time.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon feature-icon--progress">P</div>
        <h3>See Your Progress</h3>
        <p>A calendar view of everything you've logged — built automatically, nothing to enter by hand.</p>
      </div>
    </section>

    <section class="how-it-works">
      <h2 class="hiw-title">How it works</h2>
      <div class="hiw-flow">
        <div class="hiw-step">
          <div class="hiw-badge hiw-badge--cat">Category</div>
          <p class="hiw-label">Health</p>
        </div>
        <div class="hiw-arrow" aria-hidden="true">→</div>
        <div class="hiw-step">
          <div class="hiw-badge hiw-badge--hab">Habit</div>
          <p class="hiw-label">Gym</p>
        </div>
        <div class="hiw-arrow" aria-hidden="true">→</div>
        <div class="hiw-step">
          <div class="hiw-badge hiw-badge--sub">Subtask</div>
          <p class="hiw-label">Chest Day</p>
        </div>
      </div>
    </section>
  </main>

  <footer class="landing-footer">
    <p>&copy; <?php echo date('Y'); ?> Habit Track</p>
  </footer>
</body>
</html>
