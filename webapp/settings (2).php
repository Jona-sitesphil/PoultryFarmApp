<?php
session_start();
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
    <title>P.O.U.L.T.R.Y. Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=2.4">
</head>

<body>

    <div class="container">
        <div class="left-side">
            <div class="overlay"></div>
            <div class="content">
                <div class="logo-container">
                    <img src="image/Logo.jpg" class="logo-crop" alt="POULTRY Logo">
                </div>
                <h1>P.O.U.L.T.R.Y.</h1>
                <p>Proactive Observation and Unified Livestock Temperature Regulation for Yard-scale Systems</p>
                <div class="info-box">
                    <i class="fa-solid fa-circle-nodes"></i>
                    <span>Smart Monitoring. Healthy Poultry. Better Productivity.</span>
                </div>
            </div>
        </div>

        <div class="right-side">
            <div class="login-box">
                <h2>Create Account</h2>
                <p class="subtitle">Sign up to get started with P.O.U.L.T.R.Y.</p>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert-message alert-error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <form id="registerForm" action="signup_process.php" method="POST" autocomplete="off">
                    <label for="reg_username">Username</label>
                    <div class="input-box">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="reg_username" name="reg_user_xyz" placeholder="Choose a username"
                            required autocomplete="new-password">
                    </div>

                    <label for="reg_email">Email Address</label>
                    <div class="input-box">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="reg_email" name="reg_email_xyz" placeholder="Enter your email" required
                            autocomplete="new-password">
                    </div>

                    <label for="reg_password">Password</label>
                    <div class="input-box">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="reg_password" name="reg_pass_xyz" placeholder="Create a password"
                            required autocomplete="new-password">
                        <i class="fa-solid fa-eye toggle-password" id="togglePasswordReg"></i>
                    </div>

                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-box">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_pass_xyz"
                            placeholder="Confirm your password" required autocomplete="new-password">
                        <i class="fa-solid fa-eye toggle-password" id="togglePasswordConfirm"></i>
                    </div>

                    <button type="submit" class="login-btn">
                        <i class="fa-solid fa-user-plus"></i> REGISTER
                    </button>
                </form>

                <div class="divider">
                    <span>or</span>
                </div>

                <a href="index.php" class="request-account-btn">
                    <i class="fa-solid fa-right-to-bracket"></i> Back to Login
                </a>

                <div class="footer">
                    © 2026 P.O.U.L.T.R.Y. System
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>