<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // Para sa testing, i-comment out muna kung wala pang session
    // header('Location: index.php');
    // exit();
}
$username = $_SESSION['username'] ?? 'admin';

// --- KINOKONEK ANG DB.PHP ---
require_once 'db.php';

// 1. Kunin ang PINAKABAGONG record para sa top cards
$latest_temp = '28.9';
$latest_hum = '72'; // Whole number
$latest_fan = 'OFF';
$latest_bulb = 'OFF'; // Variable para sa Bulb Status

$sql_latest = "SELECT * FROM sensor_readings ORDER BY id DESC LIMIT 1";
$result_latest = $conn->query($sql_latest);

if ($result_latest && $result_latest->num_rows > 0) {
    $row = $result_latest->fetch_assoc();
    $latest_temp = number_format(floatval($row['temperature'] ?? $latest_temp), 1);
    $latest_hum = number_format(floatval($row['humidity'] ?? $latest_hum), 0);
    $latest_fan = $row['fan_status'] ?? ($row['temperature'] >= 35 ? 'ON' : 'OFF');
    $latest_bulb = $row['bulb_status'] ?? 'OFF';
}

// Status indicators para sa top cards
$temp_status_text = (floatval($latest_temp) >= 35) ? 'High Temp' : 'Normal';
$temp_status_color = (floatval($latest_temp) >= 35) ? '#d32f2f' : '#4caf50';

// Helper Function para sa Line Chart Data (Today & Yesterday)
function getLineChartData($conn, $date_condition)
{
    $labels = [];
    $temps = [];
    $hums = [];

    $sql = "SELECT * FROM (SELECT * FROM sensor_readings WHERE $date_condition ORDER BY id DESC LIMIT 10) sub ORDER BY id ASC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $labels[] = isset($row['reading_time']) ? date('g:i:s A', strtotime($row['reading_time'])) : date('g:i:s A');
            $temps[] = floatval($row['temperature']);
            $hums[] = round(floatval($row['humidity']));
        }
    }
    return ['labels' => $labels, 'temps' => $temps, 'hums' => $hums];
}

// 2. Kunin ang data ng TODAY at YESTERDAY para sa Line Charts
$today_chart = getLineChartData($conn, "DATE(reading_time) = CURDATE()");
if (empty($today_chart['labels'])) {
    $today_chart = getLineChartData($conn, "1=1");
}

$yesterday_chart = getLineChartData($conn, "DATE(reading_time) = SUBDATE(CURDATE(), 1)");
if (empty($yesterday_chart['labels'])) {
    $yesterday_chart = [
        'labels' => ['08:00 AM', '10:00 AM', '12:00 PM', '02:00 PM', '04:00 PM', '06:00 PM', '08:00 PM'],
        'temps' => [28.2, 29.5, 32.1, 33.4, 31.2, 30.0, 28.9],
        'hums' => [75, 68, 60, 54, 62, 70, 72]
    ];
}

// Helper Function para sa Weekly Overview Data
function getWeeklyOverviewData($conn, $where_clause)
{
    $days_map = [
        'Monday' => 0,
        'Tuesday' => 0,
        'Wednesday' => 0,
        'Thursday' => 0,
        'Friday' => 0,
        'Saturday' => 0,
        'Sunday' => 0
    ];

    $sql = "SELECT 
                DAYNAME(reading_time) as day_name, 
                ROUND(AVG(temperature), 1) as avg_temp 
            FROM sensor_readings 
            WHERE $where_clause
            GROUP BY DAYNAME(reading_time)";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $day = $row['day_name'];
            if (array_key_exists($day, $days_map)) {
                $days_map[$day] = floatval($row['avg_temp']);
            }
        }
    }

    $temps = array_values($days_map);
    $active_days = array_filter($temps, function ($val) {
        return $val > 0;
    });
    $avg = count($active_days) > 0 ? number_format(array_sum($active_days) / count($active_days), 1) : '0.0';

    return ['temps' => $temps, 'avg' => $avg];
}

// 3. DYNAMIC DATA: Weekly Overview
$this_week_data = getWeeklyOverviewData($conn, "YEARWEEK(reading_time, 1) = YEARWEEK(NOW(), 1)");
$last_week_data = getWeeklyOverviewData($conn, "YEARWEEK(reading_time, 1) = YEARWEEK(NOW() - INTERVAL 1 WEEK, 1)");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P.O.U.L.T.R.Y. Dashboard</title>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js Core & DataLabels Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- CSS STYLING -->
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
            --accent-orange: #f57c00;
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

        /* --- SIDEBAR --- */
        .sidebar {
            width: 260px;
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

        .nav-item.active {
            background-color: var(--sidebar-active);
            color: white;
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

        .farm-illustration {
            height: 80px;
            background: url('image/quail farm.png') no-repeat center bottom;
            background-size: contain;
            opacity: 0.8;
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h2 {
            font-size: 1.5rem;
            color: #222;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info-box {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.88rem;
            color: var(--text-main);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid #dcdcdc;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .realtime-box {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .realtime-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #00a86b;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #00a86b;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 rgba(0, 168, 107, 0.4);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 168, 107, 0.7);
            }

            70% {
                box-shadow: 0 0 0 7px rgba(0, 168, 107, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(0, 168, 107, 0);
            }
        }

        .last-updated-text {
            font-size: 0.78rem;
            color: #666;
        }

        .btn-export {
            background-color: #1b5e20;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
        }

        .btn-export:hover {
            background-color: #144417;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid #eef0f2;
        }

        .top-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .stat-icon.temp {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .stat-icon.hum {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .stat-icon.fan {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .stat-icon.bulb {
            background-color: #fff3e0;
            color: var(--accent-orange);
        }

        .stat-info p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #222;
        }

        .stat-subtext {
            font-size: 0.75rem;
            color: #4caf50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .middle-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1.3fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .chart-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #222;
        }

        .icon-badge {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .icon-badge.temp {
            background: #ffebee;
            color: #d32f2f;
        }

        .icon-badge.hum {
            background: #e3f2fd;
            color: #1976d2;
        }

        .filter-dropdown {
            background: #f4f5f7;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 500;
            color: #555;
            outline: none;
            cursor: pointer;
        }

        .unit-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 2px;
        }

        .chart-wrapper {
            height: 180px;
            width: 100%;
            position: relative;
        }

        .alert-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }

        .alert-item {
            padding: 10px 12px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-green {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1.8fr 1.2fr;
            gap: 20px;
        }

        .device-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 10px;
        }

        .device-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .device-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .toggle-switch {
            width: 36px;
            height: 20px;
            background-color: #ccc;
            border-radius: 12px;
            position: relative;
            display: inline-block;
        }

        .toggle-switch.active {
            background-color: #4caf50;
        }

        .toggle-switch::after {
            content: '';
            width: 14px;
            height: 14px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
            top: 3px;
            left: 3px;
            transition: 0.2s;
        }

        .toggle-switch.active::after {
            left: 19px;
        }

        .weekly-footer {
            margin-top: 8px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--accent-green);
            background: #e8f5e9;
            padding: 6px;
            border-radius: 6px;
        }

        .summary-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #444;
        }

        .summary-label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-label i {
            color: var(--accent-green);
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-logo"><img src="image/logo.jpg" alt="Logo"></div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="sensor_monitoring.php" class="nav-item"><i class="fa-solid fa-satellite-dish"></i> Sensor
                Monitoring</a>
            <a href="automated.php" class="nav-item"><i class="fa-solid fa-microchip"></i> Automated Control</a>
            <a href="alerts.php" class="nav-item"><i class="fa-regular fa-bell"></i> Alerts & Notifications</a>
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
        <div class="header">
            <h2>Dashboard</h2>
            <div class="header-right">
                <div class="user-info-box">
                    <img src="image/logo.jpg" alt="Logo Avatar" class="user-avatar">
                    <span>Welcome, <strong>
                            <?php echo htmlspecialchars($username); ?>
                        </strong></span>
                </div>

                <div class="realtime-box">
                    <div class="realtime-status">
                        <span class="pulse-dot"></span> Real-Time Updates
                    </div>
                    <div class="last-updated-text">
                        Last updated: <span id="last-updated-time">--:--:-- --</span>
                    </div>
                </div>

                <a href="export_csv.php" class="btn-export">
                    <i class="fa-solid fa-download"></i> Export Data
                </a>
            </div>
        </div>

        <!-- TOP CARDS -->
        <div class="top-row">
            <div class="card stat-card">
                <div class="stat-icon temp"><i class="fa-solid fa-temperature-half"></i></div>
                <div class="stat-info">
                    <p>Temperature</p>
                    <h3 id="val-temp">
                        <?php echo htmlspecialchars($latest_temp); ?> °C
                    </h3>
                    <div class="stat-subtext" id="status-temp" style="color: <?php echo $temp_status_color; ?>;">
                        <i class="fa-solid fa-circle" style="font-size:6px;"></i>
                        <?php echo $temp_status_text; ?>
                    </div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon hum"><i class="fa-solid fa-droplet"></i></div>
                <div class="stat-info">
                    <p>Humidity</p>
                    <h3 id="val-hum">
                        <?php echo htmlspecialchars($latest_hum); ?> %
                    </h3>
                    <div class="stat-subtext"><i class="fa-solid fa-circle" style="font-size:6px;"></i> Normal</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon fan"><i class="fa-solid fa-fan"></i></div>
                <div class="stat-info">
                    <p>Fan Status</p>
                    <h3 id="val-fan">
                        <?php echo htmlspecialchars($latest_fan); ?>
                    </h3>
                    <div class="stat-subtext"><i class="fa-solid fa-circle" style="font-size:6px;"></i> Running</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="stat-icon bulb"><i class="fa-solid fa-lightbulb"></i></div>
                <div class="stat-info">
                    <p>Bulb Status</p>
                    <h3 id="val-bulb">
                        <?php echo htmlspecialchars($latest_bulb); ?>
                    </h3>
                    <div class="stat-subtext"><i class="fa-solid fa-circle" style="font-size:6px;"></i> Active</div>
                </div>
            </div>
        </div>

        <!-- MIDDLE ROW -->
        <div class="middle-row">
            <div class="card">
                <div class="chart-header">
                    <div class="chart-title">
                        <span class="icon-badge temp"><i class="fa-solid fa-temperature-half"></i></span>
                        <span id="temp-title-text">Temperature (Today)</span>
                    </div>
                    <select class="filter-dropdown" id="temp-filter" onchange="updateTempChartFilter(this.value)">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                    </select>
                </div>
                <div class="unit-label">°C</div>
                <div class="chart-wrapper">
                    <canvas id="tempChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="chart-header">
                    <div class="chart-title">
                        <span class="icon-badge hum"><i class="fa-solid fa-droplet"></i></span>
                        <span id="hum-title-text">Humidity (Today)</span>
                    </div>
                    <select class="filter-dropdown" id="hum-filter" onchange="updateHumChartFilter(this.value)">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                    </select>
                </div>
                <div class="unit-label">%</div>
                <div class="chart-wrapper">
                    <canvas id="humChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="chart-header">
                    <span style="font-weight: 600;">Recent Alerts</span>
                </div>
                <div class="alert-list" id="recent-alerts-container">
                    <div class="alert-item alert-green">
                        <i class="fa-solid fa-circle-check"></i>
                        <div>
                            <div style="font-weight:600;">System Operating Normally</div>
                            <div style="font-size:0.75rem; opacity:0.85;">All climate systems are functioning well.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM ROW -->
        <div class="bottom-row">
            <div class="card">
                <div class="chart-header">
                    <span style="font-weight:600;">Device Status</span>
                </div>
                <div class="device-list">
                    <div class="device-item">
                        <div class="device-title"><i class="fa-solid fa-fan" style="color:#2e7d32;"></i> Exhaust Fan
                        </div>
                        <div class="toggle-switch" id="toggle-fan"></div>
                    </div>
                    <div class="device-item">
                        <div class="device-title"><i class="fa-solid fa-lightbulb" style="color:#f57c00;"></i> Heating
                            Bulb</div>
                        <div class="toggle-switch" id="toggle-bulb"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="chart-header">
                    <span style="font-weight:600;"><i class="fa-solid fa-chart-column"></i> Weekly Overview</span>
                    <select class="filter-dropdown" id="weekly-filter" onchange="updateWeeklyChartFilter(this.value)">
                        <option value="this_week">This Week</option>
                        <option value="last_week">Last Week</option>
                    </select>
                </div>
                <div style="height: 135px; position: relative;">
                    <canvas id="weeklyChart"></canvas>
                </div>
                <div class="weekly-footer" id="weekly-avg-display">
                    Average Temperature:
                    <?php echo $this_week_data['avg']; ?> °C
                </div>
            </div>

            <div class="card">
                <div class="chart-header">
                    <span style="font-weight:600;">Farm Summary</span>
                </div>
                <div class="summary-list">
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-microchip"></i> Total Sensors</span>
                        <strong>1</strong>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-wifi"></i> Active Sensors</span>
                        <strong id="sum-active-sensors" style="color:#2e7d32;">1</strong>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-plug-circle-xmark"></i> Offline Sensors</span>
                        <strong id="sum-offline-sensors">0</strong>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-bell"></i> Total Alerts (Today)</span>
                        <strong id="sum-total-alerts">0</strong>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-gauge-high"></i> System Uptime</span>
                        <strong style="color:#2e7d32;">99.8%</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Chatbot Floating Widget Button -->
    <div id="ai-chat-container"
        style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <!-- Toggle Button -->
        <button id="ai-toggle-btn"
            style="background: #2e7d32; color: white; border: none; border-radius: 50px; padding: 12px 20px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-robot"></i> PoultyAI Assistant
        </button>

        <!-- Chat Box Window (Nakatago muna) -->
        <div id="ai-window"
            style="display: none; width: 320px; height: 420px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; flex-direction: column; overflow: hidden; margin-bottom: 10px;">
            <!-- Header -->
            <div
                style="background: #2e7d32; color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 0.95rem;">
                <span><i class="fa-solid fa-robot"></i> PoultyAI Farm Advisor</span>
                <button id="ai-close-btn"
                    style="background: none; border: none; color: white; cursor: pointer; font-size: 1rem;"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <!-- Messages Area -->
            <div id="ai-messages"
                style="flex: 1; padding: 12px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem;">
                <div
                    style="background: #e2e8f0; padding: 8px 12px; border-radius: 8px; align-self: flex-start; max-width: 80%;">
                    Hello! Ako ang iyong PoultyAI assistant. May gusto ka bang malaman tungkol sa temperatura o sa
                    kalagayan ng iyong farm ngayon?
                </div>
            </div>

            <!-- Input Area -->
            <div style="padding: 10px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 6px;">
                <input type="text" id="ai-input" placeholder="Magtanong dito..."
                    style="flex: 1; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; outline: none;">
                <button id="ai-send-btn"
                    style="background: #2e7d32; color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer;"><i
                        class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        Chart.register(ChartDataLabels);

        const lineChartStore = {
            today: <?php echo json_encode($today_chart); ?>,
            yesterday: <?php echo json_encode($yesterday_chart); ?>
        };

        const weeklyChartStore = {
            this_week: <?php echo json_encode($this_week_data); ?>,
            last_week: <?php echo json_encode($last_week_data); ?>
        };

        const tempCtx = document.getElementById('tempChart').getContext('2d');
        const tempGradient = tempCtx.createLinearGradient(0, 0, 0, 160);
        tempGradient.addColorStop(0, 'rgba(46, 125, 50, 0.25)');
        tempGradient.addColorStop(1, 'rgba(46, 125, 50, 0.0)');

        const tempChart = new Chart(tempCtx, {
            type: 'line',
            data: {
                labels: lineChartStore.today.labels,
                datasets: [{
                    label: 'Temperature',
                    data: lineChartStore.today.temps,
                    borderColor: '#2e7d32',
                    backgroundColor: tempGradient,
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2e7d32',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        align: 'top',
                        anchor: 'end',
                        offset: 3,
                        formatter: (val) => (val !== null && val !== undefined) ? Number(val).toFixed(1) + '°C' : '',
                        font: { size: 9, weight: 'bold' },
                        color: '#2e7d32'
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: '#ffffff',
                        titleColor: '#333333',
                        bodyColor: '#2e7d32',
                        bodyFont: { size: 12, weight: 'bold' },
                        borderColor: 'rgba(0,0,0,0.1)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 8,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => Number(ctx.parsed.y).toFixed(1) + ' °C'
                        }
                    }
                },
                scales: {
                    y: {
                        suggestedMin: 26,
                        suggestedMax: 32,
                        ticks: { stepSize: 1, color: '#777', font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.03)' }
                    },
                    x: {
                        ticks: { color: '#777', font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 },
                        grid: { display: false }
                    }
                }
            }
        });

        const humCtx = document.getElementById('humChart').getContext('2d');
        const humGradient = humCtx.createLinearGradient(0, 0, 0, 160);
        humGradient.addColorStop(0, 'rgba(25, 118, 210, 0.25)');
        humGradient.addColorStop(1, 'rgba(25, 118, 210, 0.0)');

        const humChart = new Chart(humCtx, {
            type: 'line',
            data: {
                labels: lineChartStore.today.labels,
                datasets: [{
                    label: 'Humidity',
                    data: lineChartStore.today.hums,
                    borderColor: '#1976d2',
                    backgroundColor: humGradient,
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#1976d2',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: '#ffffff',
                        titleColor: '#333333',
                        bodyColor: '#1976d2',
                        bodyFont: { size: 12, weight: 'bold' },
                        borderColor: 'rgba(0,0,0,0.1)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 8,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => Math.round(ctx.parsed.y) + ' %'
                        }
                    }
                },
                scales: {
                    y: {
                        suggestedMin: 50,
                        suggestedMax: 90,
                        ticks: { stepSize: 10, color: '#777', font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.03)' }
                    },
                    x: {
                        ticks: { color: '#777', font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 },
                        grid: { display: false }
                    }
                }
            }
        });

        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    data: weeklyChartStore.this_week.temps,
                    backgroundColor: '#4caf50',
                    borderRadius: 4,
                    barThickness: 16
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        align: 'end',
                        anchor: 'end',
                        offset: 1,
                        formatter: (val) => (val > 0) ? Number(val).toFixed(1) + '°C' : '',
                        font: { size: 8, weight: 'bold' },
                        color: '#333'
                    }
                },
                scales: {
                    y: { display: false, min: 0, max: 40 },
                    x: { ticks: { color: '#666', font: { size: 9 }, maxRotation: 0 }, grid: { display: false } }
                }
            }
        });

        function updateTempChartFilter(value) {
            const data = lineChartStore[value];
            if (data) {
                tempChart.data.labels = data.labels;
                tempChart.data.datasets[0].data = data.temps;
                tempChart.update();
                $('#temp-title-text').text(value === 'today' ? 'Temperature (Today)' : 'Temperature (Yesterday)');
            }
        }

        function updateHumChartFilter(value) {
            const data = lineChartStore[value];
            if (data) {
                humChart.data.labels = data.labels;
                humChart.data.datasets[0].data = data.hums;
                humChart.update();
                $('#hum-title-text').text(value === 'today' ? 'Humidity (Today)' : 'Humidity (Yesterday)');
            }
        }

        function updateWeeklyChartFilter(value) {
            const data = weeklyChartStore[value];
            if (data) {
                weeklyChart.data.datasets[0].data = data.temps;
                weeklyChart.update();
                $('#weekly-avg-display').text('Average Temperature: ' + data.avg + ' °C');
            }
        }

        function updateLiveClock() {
            const now = new Date();
            const formattedTime = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            $('#last-updated-time').text(formattedTime);
        }

        function updateDashboardData() {
            $.getJSON('get_live_data.php', function (data) {
                if (data.success) {
                    const latestTemp = data.latest ? data.latest.temperature : data.latest_temp;
                    const latestHum = data.latest ? data.latest.humidity : data.latest_hum;
                    const latestFan = data.latest ? data.latest.fan_status : data.latest_fan;
                    const latestBulb = data.latest ? data.latest.bulb_status : (data.latest_bulb || 'OFF');

                    $('#val-temp').text(parseFloat(latestTemp).toFixed(1) + ' °C');
                    $('#val-hum').text(Math.round(latestHum) + ' %');
                    $('#val-fan').text(latestFan);
                    $('#val-bulb').text(latestBulb);

                    const tempVal = parseFloat(latestTemp);
                    if (tempVal >= 35) {
                        $('#status-temp').html('<i class="fa-solid fa-circle" style="font-size:6px;"></i> High Temp').css('color', '#d32f2f');
                    } else {
                        $('#status-temp').html('<i class="fa-solid fa-circle" style="font-size:6px;"></i> Normal').css('color', '#4caf50');
                    }

                    if (data.chart && data.chart.labels) {
                        const chartTemps = data.chart.temperatures || data.chart.temperature;
                        const chartHums = (data.chart.humidities || data.chart.humidity).map(v => Math.round(v));

                        lineChartStore.today.labels = data.chart.labels;
                        lineChartStore.today.temps = chartTemps;
                        lineChartStore.today.hums = chartHums;

                        if ($('#temp-filter').val() === 'today') {
                            tempChart.data.labels = data.chart.labels;
                            tempChart.data.datasets[0].data = chartTemps;
                            tempChart.update();
                        }
                        if ($('#hum-filter').val() === 'today') {
                            humChart.data.labels = data.chart.labels;
                            humChart.data.datasets[0].data = chartHums;
                            humChart.update();
                        }
                    }

                    // Toggle switches sync batay sa live status
                    if (latestFan === 'ON') {
                        $('#toggle-fan').addClass('active');
                    } else {
                        $('#toggle-fan').removeClass('active');
                    }

                    if (latestBulb === 'ON') {
                        $('#toggle-bulb').addClass('active');
                    } else {
                        $('#toggle-bulb').removeClass('active');
                    }
                }
            }).fail(function () {
                $('#sum-active-sensors').text('0').css('color', '#666');
                $('#sum-offline-sensors').text('1').css('color', '#d32f2f');
            });
        }

        // JavaScript para sa AI Chatbot Widget
        $(document).ready(function () {
            updateLiveClock();
            updateDashboardData();

            setInterval(updateLiveClock, 1000);
            setInterval(updateDashboardData, 2000);

            // AI Chat Widget Logic
            $('#ai-toggle-btn').on('click', function () {
                $('#ai-window').fadeToggle(200);
            });

            $('#ai-close-btn').on('click', function () {
                $('#ai-window').fadeOut(200);
            });

            function sendQuery() {
                let msg = $('#ai-input').val().trim();
                if (!msg) return;

                // I-display ang mensahe ng user
                $('#ai-messages').append(`<div style="background: #2e7d32; color: white; padding: 8px 12px; border-radius: 8px; align-self: flex-end; max-width: 80%;">${msg}</div>`);
                $('#ai-input').val('');
                $('#ai-messages').scrollTop($('#ai-messages')[0].scrollHeight);

                // Maglagay ng loading indicator
                let loadingId = 'loading_' + Date.now();
                $('#ai-messages').append(`<div id="${loadingId}" style="background: #e2e8f0; color: #666; padding: 8px 12px; border-radius: 8px; align-self: flex-start;">Nagiisip si PoultyAI...</div>`);
                $('#ai-messages').scrollTop($('#ai-messages')[0].scrollHeight);

                // I-send sa PHP backend (`ai_handler.php`)
                $.ajax({
                    url: 'ai_handler.php',
                    method: 'POST',
                    data: { message: msg },
                    dataType: 'json',
                    success: function (res) {
                        $('#' + loadingId).remove();
                        $('#ai-messages').append(`<div style="background: #e2e8f0; color: #333; padding: 8px 12px; border-radius: 8px; align-self: flex-start; max-width: 80%;">${res.reply}</div>`);
                        $('#ai-messages').scrollTop($('#ai-messages')[0].scrollHeight);
                    },
                    error: function () {
                        $('#' + loadingId).remove();
                        $('#ai-messages').append(`<div style="background: #fee2e2; color: #dc2626; padding: 8px 12px; border-radius: 8px; align-self: flex-start;">May error sa koneksyon sa server.</div>`);
                    }
                });
            }

            $('#ai-send-btn').on('click', sendQuery);
            $('#ai-input').on('keypress', function (e) {
                if (e.which === 13) {
                    sendQuery();
                }
            });
        });
    </script>
</body>

</html>