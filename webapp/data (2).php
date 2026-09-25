<?php
session_start();
require_once __DIR__ . '/db.php';

// 1. Hanapin natin ang admin account sa database para makuha ang tamang ID nito
$query = "SELECT id, username FROM users WHERE username = 'admin' LIMIT 1";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) === 1) {
    $user = mysqli_fetch_assoc($result);

    // 2. Manu-manong i-set ang session na parang nakapag-login ka nang tama
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    mysqli_close($conn);

    // 3. I-redirect ka agad sa dashboard!
    echo "<h3>Bypassing security checks... Redirecting you to Dashboard...</h3>";
    header('Refresh: 1; URL=dashboard.php');
    exit();
} else {
    echo "❌ Error: Walang 'admin' account na mahanap sa users table ng 'thesis_poultry_system_db'.";
    mysqli_close($conn);
}
?>