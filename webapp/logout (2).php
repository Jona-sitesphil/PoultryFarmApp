<?php
session_start();

// Generate CSRF token if not yet existing
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>P.O.U.L.T.R.Y Guard - Login</title>
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- External CSS -->
  <link rel="stylesheet" href="style.css?v=2.2">
</head>

<body>

  <div class="container">
    <!-- LEFT SIDE -->
    <div class="left-side">
      <div class="overlay"></div>
      <div class="content">
        <div class="logo-container">
          <img src="image/quail_background.png" alt="Logo" class="logo-crop">
        </div>
        <h1>P.O.U.L.T.R.Y GUARD</h1>
        <p>Proactive Observation and Unified Livestock Temperature Regulation for Yard-scale sYstems</p>
        <div class="info-box">
          <i class="fa-solid fa-temperature-arrow-up"></i>
          <span>Smart Monitoring & Temperature Control System</span>
        </div>
      </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right-side">
      <div class="login-box">
        <h2>Welcome Back</h2>
        <p class="subtitle">Please sign in to your account</p>

        <!-- Error Alert Box -->
        <?php if (isset($_GET['error'])): ?>
          <div class="alert-message alert-error"
            style="background: #fdf2f2; color: #e74c3c; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
          </div>
        <?php endif; ?>

        <!-- Success Alert Box -->
        <?php if (isset($_GET['success'])): ?>
          <div class="alert-message alert-success"
            style="background-color: #e8f5e9; color: var(--success-color); border: 1px solid #c8e6c9; padding: 10px; border-radius: 8px; font-size: 0.82rem; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-circle-check"></i>
            <?php echo htmlspecialchars($_GET['success']); ?>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
          <!-- CSRF Token Security -->
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

          <label for="username">Username / Email</label>
          <div class="input-box">
            <i class="fa-solid fa-user"></i>
            <input type="text" id="username" name="username" placeholder="Enter your username" required
              autocomplete="off">
          </div>

          <label for="password">Password</label>
          <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Enter your password" required
              autocomplete="current-password">
            <i class="fa-solid fa-eye toggle-password" id="togglePasswordLogin"></i>
          </div>

          <div class="options">
            <label><input type="checkbox" name="remember"> Remember me</label>
            <a href="forgot_password.php">Forgot password?</a>
          </div>

          <button type="submit" class="login-btn">
            <i class="fa-solid fa-right-to-bracket"></i> Sign In
          </button>
        </form>

        <div class="divider">or</div>

        <a href="register.php" class="request-account-btn">
          <i class="fa-solid fa-user-plus"></i> Request Account
        </a>

        <div class="footer">
          &copy; 2026 P.O.U.L.T.R.Y Guard System
        </div>
      </div>
    </div>
  </div>

  <!-- External JavaScript -->
  <script src="script.js"></script>
</body>

</html>