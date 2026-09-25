<?php
session_start();

// I-redirect sa login page kung hindi naka-login (i-uncomment kung gagamitin)
if (!isset($_SESSION['user_id'])) {
    // header('Location: login.php');
    // exit();
}
$username = $_SESSION['username'] ?? 'Administrator';

// Database Configuration
$host = 'localhost';
$db = 'thesis_poultry_system_db';
$user = 'root';
$pass = '';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
}

// =====================================================================
// 1. INCLUDE PHPMAILER CLASSES (Mula sa folder na inilagay mo sa root)
// =====================================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// =====================================================================
// HELPER FUNCTIONS FOR EMAIL AT SMS NOTIFICATIONS
// =====================================================================

// LIVE EMAIL SENDING VIA GMAIL SMTP
function sendEmailAlert($toEmail, $subject, $bodyMessage)
{
    $mail = new PHPMailer(true);

    try {
        // Server Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // GMAIL CREDENTIALS
        $mail->Username = 'fajutaganaramsey08@gmail.com';
        $mail->Password = 'pjvkzprjujrdknrb'; // Ang App Password mo para sa thesis_poultry_system

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender at Receiver Setup
        $mail->setFrom('fajutaganaramsey08@gmail.com', 'P.O.U.L.T.R.Y Guard System');
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyMessage;
        $mail->AltBody = strip_tags($bodyMessage);

        $mail->send();

        // Success Logging
        $logMsg = date('[Y-m-d H:i:s]') . " [EMAIL SUCCESS TO: $toEmail] Subject: $subject\n";
        file_put_contents('notification_log.txt', $logMsg, FILE_APPEND);

        return true;
    } catch (Exception $e) {
        // Error Logging
        $logMsg = date('[Y-m-d H:i:s]') . " [EMAIL FAILED] Error: {$mail->ErrorInfo}\n";
        file_put_contents('notification_log.txt', $logMsg, FILE_APPEND);

        return false;
    }
}

// SMS SIMULATION / MOCK LOGGING
function sendSMSAlert($phoneNumber, $message)
{
    $logMsg = date('[Y-m-d H:i:s]') . " [SMS LOGGED TO: $phoneNumber] Message: $message\n";
    file_put_contents('notification_log.txt', $logMsg, FILE_APPEND);
    return true;
}

// =====================================================================
// BAHAGI 0: AJAX ENDPOINT PARA SA SAVING AT FETCHING PREFERENCES
// =====================================================================
if (isset($_POST['action']) && $_POST['action'] === 'save_preference') {
    header('Content-Type: application/json');
    if ($pdo) {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '0';

        if (in_array($key, ['email_notifications', 'sms_notifications'])) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
            echo json_encode(['status' => 'success', 'message' => 'Preference updated successfully']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Failed to update preference']);
    exit;
}

// =====================================================================
// BAHAGI 1: HIWALAY NA PAGSUSURI NG TEMP AT HUMIDITY PARA SA MGA ALERTS
// =====================================================================
$pref_email = 1;
$pref_sms = 1;

if ($pdo) {
    try {
        // 1. Kunin ang pinakahuling readings
        $sensorStmt = $pdo->query("SELECT temperature, humidity, reading_time FROM sensor_readings ORDER BY reading_time DESC LIMIT 1");
        $latestReading = $sensorStmt->fetch(PDO::FETCH_ASSOC);

        // 2. Kunin ang thresholds at preferences mula sa system_settings
        $threshold_max_temp = 30.0;
        $threshold_max_hum = 78.0;

        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'max_temp') {
                $threshold_max_temp = (float) $row['setting_value'];
            }
            if ($row['setting_key'] === 'max_hum') {
                $threshold_max_hum = (float) $row['setting_value'];
            }
            if ($row['setting_key'] === 'email_notifications') {
                $pref_email = (int) $row['setting_value'];
            }
            if ($row['setting_key'] === 'sms_notifications') {
                $pref_sms = (int) $row['setting_value'];
            }
        }

        if ($latestReading && isset($latestReading['temperature'])) {
            $current_temp = (float) $latestReading['temperature'];
            $current_hum = (float) ($latestReading['humidity'] ?? 0);
            $warning_temp = $threshold_max_temp - 1.5;

            // ==========================================
            // 3. CHECK PARA SA TEMPERATURE ALERTS
            // ==========================================
            $temp_alert_type = '';
            $temp_title = '';
            $temp_desc = '';
            $temp_icon = '';
            $temp_interval = 1;

            if ($current_temp > $threshold_max_temp) {
                $temp_alert_type = 'critical';
                $temp_title = 'High Temperature Alert! Threshold Exceeded';
                $temp_desc = 'Temperature reached ' . $current_temp . ' °C, exceeding the maximum limit of ' . $threshold_max_temp . ' °C.';
                $temp_icon = 'fa-triangle-exclamation';
                $temp_interval = 1;
            } elseif ($current_temp >= $warning_temp && $current_temp <= $threshold_max_temp) {
                $temp_alert_type = 'warning';
                $temp_title = 'Temperature Warning! Approaching Limit';
                $temp_desc = 'Temperature is rising at ' . $current_temp . ' °C, nearing the set maximum limit of ' . $threshold_max_temp . ' °C.';
                $temp_icon = 'fa-triangle-exclamation';
                $temp_interval = 5;
            }

            if (!empty($temp_alert_type)) {
                $checkTempStmt = $pdo->prepare("SELECT id FROM alerts WHERE alert_type = ? AND title = ? AND created_at >= NOW() - INTERVAL ? MINUTE LIMIT 1");
                $checkTempStmt->execute([$temp_alert_type, $temp_title, $temp_interval]);

                if ($checkTempStmt->rowCount() === 0) {
                    $insertTempStmt = $pdo->prepare("INSERT INTO alerts (alert_type, title, description, location, sensor_name, icon, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $insertTempStmt->execute([
                        $temp_alert_type,
                        $temp_title,
                        $temp_desc,
                        'House 1 - Zone A',
                        'Environmental Sensor (ENV-01)',
                        $temp_icon
                    ]);

                    if ($pref_email == 1) {
                        sendEmailAlert(
                            'fajutaganaramsey08@gmail.com',
                            "POULTRY GUARD ALERT: " . $temp_title,
                            "<h2>P.O.U.L.T.R.Y Guard System Alert</h2><p><b>Warning!</b> " . $temp_desc . "</p><p>Location: House 1 - Zone A</p>"
                        );
                    }
                    if ($pref_sms == 1) {
                        sendSMSAlert(
                            '09123456789',
                            "POULTRY ALERT: " . $temp_title . " - " . $temp_desc
                        );
                    }
                }
            }

            // ==========================================
            // 4. CHECK PARA SA HUMIDITY ALERTS
            // ==========================================
            $hum_alert_type = '';
            $hum_title = '';
            $hum_desc = '';
            $hum_icon = '';
            $hum_interval = 5;

            if ($current_hum > $threshold_max_hum) {
                $hum_alert_type = 'warning';
                $hum_title = 'High Humidity Warning!';
                $hum_desc = 'Humidity has reached ' . $current_hum . '%, exceeding the safe limit of ' . $threshold_max_hum . '%.';
                $hum_icon = 'fa-droplet';
                $hum_interval = 5;
            }

            if (!empty($hum_alert_type)) {
                $checkHumStmt = $pdo->prepare("SELECT id FROM alerts WHERE alert_type = ? AND title = ? AND created_at >= NOW() - INTERVAL ? MINUTE LIMIT 1");
                $checkHumStmt->execute([$hum_alert_type, $hum_title, $hum_interval]);

                if ($checkHumStmt->rowCount() === 0) {
                    $insertHumStmt = $pdo->prepare("INSERT INTO alerts (alert_type, title, description, location, sensor_name, icon, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $insertHumStmt->execute([
                        $hum_alert_type,
                        $hum_title,
                        $hum_desc,
                        'House 1 - Zone A',
                        'Environmental Sensor (ENV-01)',
                        $hum_icon
                    ]);

                    if ($pref_email == 1) {
                        sendEmailAlert(
                            'fajutaganaramsey08@gmail.com',
                            "POULTRY GUARD ALERT: " . $hum_title,
                            "<h2>P.O.U.L.T.R.Y Guard System Alert</h2><p><b>Warning!</b> " . $hum_desc . "</p><p>Location: House 1 - Zone A</p>"
                        );
                    }
                    if ($pref_sms == 1) {
                        sendSMSAlert(
                            '09123456789',
                            "POULTRY ALERT: " . $hum_title . " - " . $hum_desc
                        );
                    }
                }
            }

            // ==========================================
            // 5. KUNG NORMAL ANG LAHAT
            // ==========================================
            if (empty($temp_alert_type) && empty($hum_alert_type)) {
                $info_title = 'System Operating Normally';
                $info_desc = 'Temperature (' . $current_temp . '°C) and Humidity (' . $current_hum . '%) are within safe operating limits.';

                $checkInfoStmt = $pdo->prepare("SELECT id FROM alerts WHERE alert_type = 'info' AND created_at >= NOW() - INTERVAL 30 MINUTE LIMIT 1");
                $checkInfoStmt->execute();

                if ($checkInfoStmt->rowCount() === 0) {
                    $insertInfoStmt = $pdo->prepare("INSERT INTO alerts (alert_type, title, description, location, sensor_name, icon, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $insertInfoStmt->execute([
                        'info',
                        $info_title,
                        $info_desc,
                        'House 1 - Zone A',
                        'Environmental Sensor (ENV-01)',
                        'fa-circle-info'
                    ]);
                }
            }

        }
    } catch (\Exception $e) {
        error_log("Alert Logic Error: " . $e->getMessage());
    }
}

// =====================================================================
// BAHAGI 2: AJAX ENDPOINT PARA SA LIVE DATA FETCHING
// =====================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_alerts_data') {
    header('Content-Type: application/json');

    $stats = ['critical' => 0, 'warning' => 0, 'info' => 0, 'total' => 0];
    $alerts = [];

    if ($pdo) {
        $stmtStats = $pdo->query("SELECT alert_type, COUNT(*) as count FROM alerts GROUP BY alert_type");
        while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
            $type = strtolower($row['alert_type']);
            if ($type === 'info' || $type === 'informational') {
                $stats['info'] += (int) $row['count'];
            } elseif (isset($stats[$type])) {
                $stats[$type] = (int) $row['count'];
            }
        }
        $stats['total'] = $stats['critical'] + $stats['warning'] + $stats['info'];

        $filter = $_GET['filter'] ?? 'all';
        $sql = "SELECT * FROM alerts WHERE 1=1";

        if ($filter !== 'all') {
            if ($filter === 'info') {
                $sql .= " AND (alert_type = 'info' OR alert_type = 'informational')";
            } else {
                $sql .= " AND alert_type = " . $pdo->quote($filter);
            }
        }
        $sql .= " ORDER BY created_at DESC LIMIT 10";

        $stmtAlerts = $pdo->query($sql);
        while ($row = $stmtAlerts->fetch(PDO::FETCH_ASSOC)) {
            $dbType = strtolower($row['alert_type']);
            $uiType = ($dbType === 'info' || $dbType === 'informational') ? 'info' : $dbType;

            $alerts[] = [
                'type' => $uiType,
                'icon' => $row['icon'] ?? 'fa-triangle-exclamation',
                'title' => $row['title'],
                'desc' => $row['description'],
                'loc' => $row['location'] ?? 'N/A',
                'sensor' => $row['sensor_name'] ?? 'N/A',
                'time' => date('h:i A', strtotime($row['created_at'])),
                'badge' => ucfirst($dbType)
            ];
        }
    }

    echo json_encode(['stats' => $stats, 'alerts' => $alerts]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts & Notifications - P.O.U.L.T.R.Y. Guard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg-color: #f3f4f6;
            --sidebar-bg: #fdfdfd;
            --sidebar-active: #1b5e20;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #666666;
            --accent-green: #2e7d32;
            --accent-blue: #1976d2;
            --accent-red: #d32f2f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* --- SIDEBAR (Standard Dashboard Style) --- */
        .sidebar {
            width: 260px;
            min-width: 260px;
            background-color: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e0e0e0;
        }

        .sidebar-logo {
            text-align: center;
            padding: 20px 0;
            background-color: #ebdccc;
        }

        .sidebar-logo img {
            width: 120px;
            border-radius: 50%;
        }

        .nav-menu {
            flex: 1;
            padding: 20px 10px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-main);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .nav-item:hover {
            background-color: #f0f0f0;
        }

        .nav-item.active {
            background-color: var(--sidebar-active);
            color: white;
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #eee;
        }

        .farm-selector {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }

        .farm-selector p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .farm-illustration {
            height: 80px;
            background: url('image/quail farm.png') no-repeat center bottom;
            background-size: contain;
            opacity: 0.8;
        }

        /* --- MAIN CONTENT AREA --- */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            padding: 20px 30px;
        }

        .top-nav-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-title h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #222222;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title p {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-mark-read {
            background: white;
            border: 1px solid #d1d5db;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-notif-settings {
            background: var(--sidebar-active);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            padding: 15px 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            border: 1px solid #eaeaea;
        }

        .stat-box.red {
            background-color: #fef2f2;
        }

        .stat-box.yellow {
            background-color: #fffbeb;
        }

        .stat-box.blue {
            background-color: #eff6ff;
        }

        .stat-box.green {
            background-color: #f0fdf4;
        }

        .stat-icon-lrg {
            font-size: 1.8rem;
        }

        .stat-box.red .stat-icon-lrg {
            color: #ef4444;
        }

        .stat-box.yellow .stat-icon-lrg {
            color: #f59e0b;
        }

        .stat-box.blue .stat-icon-lrg {
            color: #3b82f6;
        }

        .stat-box.green .stat-icon-lrg {
            color: #22c55e;
        }

        .stat-details h4 {
            font-size: 0.8rem;
            color: #4b5563;
            margin-bottom: 2px;
        }

        .stat-details h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #111;
            margin-bottom: 2px;
        }

        .stat-details p {
            font-size: 0.72rem;
            color: #6b7280;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card-panel {
            background: white;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            margin-bottom: 15px;
            border: 1px solid #eaeaea;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .panel-title {
            font-weight: 600;
            font-size: 1rem;
            color: #222;
        }

        .filter-select {
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 0.82rem;
            background: white;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .alert-item.critical {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
        }

        .alert-item.warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
        }

        .alert-item.info,
        .alert-item.informational {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
        }

        .alert-icon {
            font-size: 1.1rem;
            margin-top: 2px;
        }

        .alert-item.critical .alert-icon {
            color: #ef4444;
        }

        .alert-item.warning .alert-icon {
            color: #f59e0b;
        }

        .alert-item.info .alert-icon,
        .alert-item.informational .alert-icon {
            color: #22c55e;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #111;
            margin-bottom: 2px;
        }

        .alert-desc {
            font-size: 0.8rem;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .alert-meta {
            display: flex;
            gap: 12px;
            font-size: 0.75rem;
            color: #6b7280;
        }

        .alert-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            height: 100%;
            gap: 12px;
        }

        .alert-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .alert-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.68rem;
            font-weight: 600;
        }

        .badge-critical {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge-warning {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-info,
        .badge-informational {
            background: #dcfce7;
            color: #15803d;
        }

        .no-alerts {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .pref-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 0.85rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .pref-row:last-child {
            border-bottom: none;
        }

        .pref-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #1b5e20;
        }

        input:checked+.slider:before {
            transform: translateX(16px);
        }

        .chart-box-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chart-small {
            width: 110px;
            height: 110px;
            position: relative;
        }

        .chart-legend {
            flex: 1;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .legend-item {
            display: flex;
            justify-content: space-between;
        }

        .btn-view-report {
            display: block;
            width: 100%;
            text-align: center;
            padding: 8px;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.82rem;
            color: #374151;
            text-decoration: none;
            margin-top: 12px;
            font-weight: 500;
        }

        .footer {
            text-align: center;
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: auto;
            padding-top: 10px;
        }

        #toast-msg {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #113f1e;
            color: white;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 0.85rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 9999;
        }
    </style>
</head>

<body>

    <!-- SIDEBAR (Unified with Dashboard & Reports) -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="image/logo.jpg" alt="Logo">
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="sensor_monitoring.php" class="nav-item"><i class="fa-solid fa-satellite-dish"></i> Sensor
                Monitoring</a>
            <a href="automated.php" class="nav-item"><i class="fa-solid fa-microchip"></i> Automated Control</a>
            <a href="alerts.php" class="nav-item active"><i class="fa-regular fa-bell"></i> Alerts & Notifications</a>
            <a href="reports.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i> Reports & Analytics</a>
            <a href="users.php" class="nav-item"><i class="fa-solid fa-users"></i> User Management</a>
            <a href="settings.php" class="nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
            <a href="logout.php" class="nav-item" style="color: var(--accent-red); margin-top: 20px;">
                <i class="fa-solid fa-right-from-bracket"></i> Log Out
            </a>
        </div>
        <div class="sidebar-footer">
            <div class="farm-selector">
                <p>Bolbok Poultry Farm</p>
                <p style="font-weight: bold;">House 1</p>
            </div>
            <div class="farm-illustration"></div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-nav-bar">
            <div class="header-title">
                <h2><i class="fa-solid fa-bars" style="color: #6b7280; font-size: 1.1rem; cursor: pointer;"></i> Alerts
                    & Notifications</h2>
                <p>View and manage system alerts and notifications in real-time.</p>
            </div>
            <div class="top-actions">
                <button class="btn-mark-read">Mark all as read</button>
                <button class="btn-notif-settings"><i class="fa-solid fa-gear"></i> Notification Settings</button>
                <div class="user-profile">
                    <img src="image/logo.jpg" alt="User Avatar">
                    <span>
                        <?php echo htmlspecialchars($username); ?> <br>
                        <span style="font-size: 0.7rem; color: #6b7280; font-weight: normal;">Administrator</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-box red">
                <i class="fa-solid fa-triangle-exclamation stat-icon-lrg"></i>
                <div class="stat-details">
                    <h4>Critical Alerts</h4>
                    <h2 id="stat-critical">0</h2>
                    <p>Requires immediate action</p>
                </div>
            </div>
            <div class="stat-box yellow">
                <i class="fa-solid fa-triangle-exclamation stat-icon-lrg"></i>
                <div class="stat-details">
                    <h4>Warnings</h4>
                    <h2 id="stat-warning">0</h2>
                    <p>Need attention</p>
                </div>
            </div>
            <div class="stat-box blue">
                <i class="fa-solid fa-circle-info stat-icon-lrg"></i>
                <div class="stat-details">
                    <h4>Informational</h4>
                    <h2 id="stat-info">0</h2>
                    <p>For your information</p>
                </div>
            </div>
            <div class="stat-box green">
                <i class="fa-solid fa-circle-check stat-icon-lrg"></i>
                <div class="stat-details">
                    <h4>Total Alerts</h4>
                    <h2 id="stat-total">0</h2>
                    <p>System Logs</p>
                </div>
            </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">
            <!-- LEFT COLUMN: Recent Alerts -->
            <div>
                <div class="card-panel">
                    <div class="panel-header">
                        <span class="panel-title">Recent Alerts (Live)</span>
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #4b5563;">
                            Filter:
                            <select class="filter-select" id="alert-filter">
                                <option value="all">All Alerts</option>
                                <option value="critical">Critical</option>
                                <option value="warning">Warning</option>
                                <option value="info">Informational</option>
                            </select>
                        </div>
                    </div>

                    <div id="alerts-container">
                        <!-- Dynamic Database Alerts will be injected here -->
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Preferences & Statistics -->
            <div>
                <!-- ALERT PREFERENCES (Email at SMS) -->
                <div class="card-panel">
                    <div class="panel-header">
                        <span class="panel-title">Alert Preferences</span>
                    </div>
                    <div class="pref-row">
                        <div class="pref-label"><i class="fa-solid fa-envelope"></i> Email Notifications</div>
                        <label class="switch">
                            <input type="checkbox" id="pref-email" data-key="email_notifications" <?php echo ($pref_email == 1) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="pref-row">
                        <div class="pref-label"><i class="fa-solid fa-comment-sms"></i> SMS Notifications</div>
                        <label class="switch">
                            <input type="checkbox" id="pref-sms" data-key="sms_notifications" <?php echo ($pref_sms == 1) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="card-panel">
                    <div class="panel-header">
                        <span class="panel-title">Alert Statistics</span>
                    </div>
                    <div class="chart-box-container">
                        <div class="chart-small">
                            <canvas id="alertsChart"></canvas>
                        </div>
                        <div class="chart-legend" id="chart-legend-text">
                            <div class="legend-item"><span><span style="color:#ef4444">■</span> Critical</span> <strong
                                    id="legend-crit">0 (0.0%)</strong></div>
                            <div class="legend-item"><span><span style="color:#f59e0b">■</span> Warning</span> <strong
                                    id="legend-warn">0 (0.0%)</strong></div>
                            <div class="legend-item"><span><span style="color:#3b82f6">■</span> Informational</span>
                                <strong id="legend-info">0 (0.0%)</strong></div>
                        </div>
                    </div>
                    <a href="reports.php" class="btn-view-report"><i class="fa-solid fa-chart-bar"></i> View Full
                        Report</a>
                </div>
            </div>
        </div>

        <div class="footer">© 2026 P.O.U.L.T.R.Y System. All rights reserved.</div>
    </div>

    <!-- Toast Message Container -->
    <div id="toast-msg">Preference saved!</div>

    <!-- SCRIPT -->
    <script>
        const ctx = document.getElementById('alertsChart').getContext('2d');
        const alertsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Critical', 'Warning', 'Informational'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });

        function fetchLiveAlerts() {
            let filterVal = $('#alert-filter').val();

            $.ajax({
                url: 'alerts.php?action=get_alerts_data&filter=' + filterVal,
                method: 'GET',
                dataType: 'json',
                success: function (data) {
                    $('#stat-critical').text(data.stats.critical);
                    $('#stat-warning').text(data.stats.warning);
                    $('#stat-info').text(data.stats.info);
                    $('#stat-total').text(data.stats.total);

                    alertsChart.data.datasets[0].data = [data.stats.critical, data.stats.warning, data.stats.info];
                    alertsChart.update();

                    let total = data.stats.total > 0 ? data.stats.total : 1;
                    let critPct = ((data.stats.critical / total) * 100).toFixed(1);
                    let warnPct = ((data.stats.warning / total) * 100).toFixed(1);
                    let infoPct = ((data.stats.info / total) * 100).toFixed(1);

                    $('#legend-crit').text(`${data.stats.critical} (${critPct}%)`);
                    $('#legend-warn').text(`${data.stats.warning} (${warnPct}%)`);
                    $('#legend-info').text(`${data.stats.info} (${infoPct}%)`);

                    let container = $('#alerts-container');
                    container.empty();

                    if (data.alerts.length === 0) {
                        container.html('<div class="no-alerts"><i class="fa-regular fa-circle-check" style="font-size: 2rem; color: #22c55e; margin-bottom: 8px;"></i><p>No active alerts found for this filter.</p></div>');
                    } else {
                        data.alerts.forEach(alert => {
                            let badgeClass = 'badge-info';
                            if (alert.type === 'critical') badgeClass = 'badge-critical';
                            else if (alert.type === 'warning') badgeClass = 'badge-warning';

                            container.append(`
                                <div class="alert-item ${alert.type}">
                                    <div class="alert-icon"><i class="fa-solid ${alert.icon}"></i></div>
                                    <div class="alert-content">
                                        <div class="alert-title">${alert.title}</div>
                                        <div class="alert-desc">${alert.desc}</div>
                                        <div class="alert-meta">
                                            <span><i class="fa-solid fa-location-dot"></i> ${alert.loc}</span>
                                            <span><i class="fa-solid fa-satellite-dish"></i> ${alert.sensor}</span>
                                        </div>
                                    </div>
                                    <div class="alert-right">
                                        <div class="alert-time">${alert.time}</div>
                                        <span class="alert-badge ${badgeClass}">${alert.badge}</span>
                                    </div>
                                </div>
                            `);
                        });
                    }
                }
            });
        }

        // Live Toggle para sa Alert Preferences (Email at SMS)
        $('#pref-email, #pref-sms').on('change', function () {
            let key = $(this).data('key');
            let val = $(this).is(':checked') ? 1 : 0;

            $.ajax({
                url: 'alerts.php',
                method: 'POST',
                data: {
                    action: 'save_preference',
                    key: key,
                    value: val
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        $('#toast-msg').fadeIn().delay(1500).fadeOut();
                    }
                }
            });
        });

        $('#alert-filter').on('change', function () {
            fetchLiveAlerts();
        });

        $(document).ready(function () {
            fetchLiveAlerts();
            setInterval(fetchLiveAlerts, 5000);
        });
    </script>
</body>

</html>