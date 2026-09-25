<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    // header('Location: index.php');
    // exit();
}

// 0. GET SELECTED DATE RANGE (DEFAULT: 7 DAYS)
$range_days = isset($_GET['range']) ? (int)$_GET['range'] : 7;
if (!in_array($range_days, [7, 15, 30])) {
    $range_days = 7; // Fallback sa 7 days kapag may nag-type ng invalid value
}

$username = $_SESSION['username'] ?? 'Juan Dela Cruz';
$user_role = $_SESSION['role'] ?? 'Administrator';

// 1. KUNIN ANG TOP STATS BASE SA $range_days
$stats_sql = "SELECT 
                ROUND(AVG(temperature), 1) AS avg_temp,
                ROUND(AVG(humidity), 1) AS avg_hum,
                COUNT(*) AS total_readings,
                SUM(CASE WHEN UPPER(fan_status) = 'ON' THEN 1 ELSE 0 END) AS fan_on_count
              FROM sensor_readings 
              WHERE reading_time >= NOW() - INTERVAL {$range_days} DAY";

$stats_res = $conn->query($stats_sql);
$stats = $stats_res ? $stats_res->fetch_assoc() : [];

$avg_temp = $stats['avg_temp'] ?? 0;
$avg_hum  = $stats['avg_hum'] ?? 0;
$total_readings = $stats['total_readings'] ?? 0;
$fan_on_count   = $stats['fan_on_count'] ?? 0;

// Compute Fan Runtime Percentage
$fan_runtime_pct = ($total_readings > 0) ? round(($fan_on_count / $total_readings) * 100, 1) : 0;


// 2. KUNIN ANG DATA SUMMARY (MIN, MAX, AVG) BASE SA $range_days
$summary_sql = "SELECT 
                  MIN(temperature) AS min_temp, MAX(temperature) AS max_temp, ROUND(AVG(temperature), 1) AS avg_temp,
                  MIN(humidity) AS min_hum, MAX(humidity) AS max_hum, ROUND(AVG(humidity), 1) AS avg_hum
                FROM sensor_readings 
                WHERE reading_time >= NOW() - INTERVAL {$range_days} DAY";

$summary_res = $conn->query($summary_sql);
$summary = $summary_res ? $summary_res->fetch_assoc() : [];


// 3. KUNIN ANG DAILY OVERVIEW & CHART DATA BASE SA $range_days
$daily_sql = "SELECT 
                DATE(sr.reading_time) AS log_date,
                DATE_FORMAT(sr.reading_time, '%b %d, %Y') AS formatted_date,
                ROUND(AVG(sr.temperature), 1) AS avg_temp,
                ROUND(AVG(sr.humidity), 1) AS avg_hum,
                COUNT(sr.id) AS total_count,
                SUM(CASE WHEN UPPER(sr.fan_status) = 'ON' THEN 1 ELSE 0 END) AS fan_on,
                COALESCE(a.alert_count, 0) AS alert_count
              FROM sensor_readings sr
              LEFT JOIN (
                  SELECT DATE(created_at) AS a_date, COUNT(*) AS alert_count 
                  FROM alerts 
                  GROUP BY DATE(created_at)
              ) a ON DATE(sr.reading_time) = a.a_date
              WHERE sr.reading_time >= NOW() - INTERVAL {$range_days} DAY
              GROUP BY DATE(sr.reading_time)
              ORDER BY log_date DESC 
              LIMIT {$range_days}";

$daily_res = $conn->query($daily_sql);

$daily_rows = [];
$chart_dates = [];
$chart_temps = [];
$chart_hums  = [];

if ($daily_res && $daily_res->num_rows > 0) {
    while ($r = $daily_res->fetch_assoc()) {
        $tot = $r['total_count'];
        $fon = $r['fan_on'];
        $r['fan_pct'] = ($tot > 0) ? round(($fon / $tot) * 100, 1) : 0;
        
        $daily_rows[] = $r;

        // Push data to arrays (chronologically left-to-right sa Chart)
        array_unshift($chart_dates, date('M d', strtotime($r['log_date'])));
        array_unshift($chart_temps, $r['avg_temp']);
        array_unshift($chart_hums, $r['avg_hum']);
    }
}


// 4. KUNIN ANG REPORT HISTORY (SAFE WITH TRY-CATCH)
$history_rows = [];
try {
    $history_sql = "SELECT * FROM report_history ORDER BY id DESC LIMIT 5";
    $history_res = $conn->query($history_sql);

    if ($history_res && $history_res->num_rows > 0) {
        while ($h = $history_res->fetch_assoc()) {
            $history_rows[] = $h;
        }
    }
} catch (Exception $e) {
    // Static fallback kung wala pa ang table sa DB
    $history_rows = [
        ['report_name' => 'Weekly Report', 'date_range' => 'May 6 - May 15, 2024', 'generated_on' => 'May 16, 2024 10:30 AM', 'generated_by' => $username],
        ['report_name' => 'Weekly Report', 'date_range' => 'May 5 - May 8, 2024', 'generated_on' => 'May 2, 2024 12:34 AM', 'generated_by' => $username],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - P.O.U.L.T.R.Y.</title>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-color: #f3f4f6;
            --sidebar-bg: #fdfdfd;
            --sidebar-active: #1b5e20;
            --card-bg: #ffffff;
            --text-main: #2d3748;
            --text-muted: #718096;
            --accent-green: #1b5e20;
            --accent-green-light: #e8f5e9;
            --accent-blue: #2563eb;
            --accent-red: #e11d48;
            --border-color: #e2e8f0;
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

        /* SIDEBAR */
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
            color: #333;
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

        /* MAIN CONTENT AREA */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title-area h2 {
            font-size: 1.45rem;
            color: #1a202c;
            font-weight: 700;
        }

        .header-title-area p {
            color: var(--text-muted);
            font-size: 0.82rem;
            margin-top: 2px;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-export {
            background-color: var(--accent-green);
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }

        .user-profile-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 8px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }

        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2d3748;
        }

        /* 3 STAT CARDS */
        .stats-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .stat-card-item {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon-wrapper {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .stat-icon-wrapper.temp { background-color: #dcfce7; color: #16a34a; }
        .stat-icon-wrapper.hum { background-color: #e0f2fe; color: #0284c7; }
        .stat-icon-wrapper.fan { background-color: #dcfce7; color: #15803d; }

        .stat-value-text {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1a202c;
            margin: 2px 0;
        }

        /* CONTENT GRID */
        .grid-2-cols {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .chart-container-box {
            position: relative;
            height: 200px;
            width: 100%;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            text-align: left;
        }

        .custom-table th {
            color: var(--text-muted);
            font-weight: 600;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            background-color: #f8fafc;
        }

        .custom-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #edf2f7;
            color: #2d3748;
        }

        .badge-alert-num {
            background-color: #16a34a;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .badge-alert-num.has-alert {
            background-color: #f59e0b;
        }

        .reports-bottom-grid {
            display: grid;
            grid-template-columns: 2fr 1.1fr;
            gap: 15px;
        }

        .report-item-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .btn-icon-action {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a5568;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-logo"><img src="image/logo.jpg" alt="Logo"></div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="sensor_monitoring.php" class="nav-item"><i class="fa-solid fa-satellite-dish"></i> Sensor Monitoring</a>
            <a href="automated.php" class="nav-item"><i class="fa-solid fa-microchip"></i> Automated Control</a>
            <a href="alerts.php" class="nav-item"><i class="fa-regular fa-bell"></i> Alerts & Notifications</a>
            <a href="reports.php" class="nav-item active"><i class="fa-solid fa-chart-simple"></i> Reports & Analytics</a>
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

    <!-- MAIN CONTENT AREA -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="header">
            <div class="header-title-area">
                <h2>Reports & Analytics</h2>
                <p>View Historical data, analyze trends, and generate reports.</p>
            </div>
            <div class="header-controls">

                <!-- DYNAMIC DATE RANGE DROPDOWN -->
                <div style="position: relative; display: inline-block;">
                    <i class="fa-regular fa-calendar" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #4a5568; pointer-events: none;"></i>
                    <select onchange="location = this.value;" style="
                        background: #ffffff;
                        border: 1px solid var(--border-color);
                        padding: 8px 12px 8px 34px;
                        border-radius: 6px;
                        font-size: 0.8rem;
                        font-weight: 600;
                        color: #4a5568;
                        cursor: pointer;
                        outline: none;
                    ">
                        <option value="reports.php?range=7" <?= ($range_days == 7) ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="reports.php?range=15" <?= ($range_days == 15) ? 'selected' : '' ?>>Last 15 Days</option>
                        <option value="reports.php?range=30" <?= ($range_days == 30) ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>

                <a href="export_csv.php?range=<?= $range_days ?>" class="btn-export">
                    <i class="fa-solid fa-download"></i> Export Report
                </a>

                <div class="user-profile-box">
                    <img src="image/logo.jpg" alt="Avatar" class="user-avatar">
                    <div style="line-height:1.2;">
                        <div style="font-size:0.82rem; font-weight:700;"><?= htmlspecialchars($username) ?></div>
                        <div style="font-size:0.72rem; color:var(--text-muted);"><?= htmlspecialchars($user_role) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIVE TOP STATS CARDS -->
        <div class="stats-grid-3">
            <div class="stat-card-item">
                <div class="stat-icon-wrapper temp"><i class="fa-solid fa-temperature-half"></i></div>
                <div>
                    <span style="font-size:0.75rem; color:var(--text-muted);">Average Temperature</span>
                    <div class="stat-value-text"><?= $avg_temp ?> °C</div>
                    <small style="color:#16a34a;"><i class="fa-solid fa-check"></i> Live Data (Last <?= $range_days ?> Days)</small>
                </div>
            </div>

            <div class="stat-card-item">
                <div class="stat-icon-wrapper hum"><i class="fa-solid fa-droplet"></i></div>
                <div>
                    <span style="font-size:0.75rem; color:var(--text-muted);">Average Humidity</span>
                    <div class="stat-value-text"><?= $avg_hum ?> %</div>
                    <small style="color:#16a34a;"><i class="fa-solid fa-check"></i> Live Data (Last <?= $range_days ?> Days)</small>
                </div>
            </div>

            <div class="stat-card-item">
                <div class="stat-icon-wrapper fan"><i class="fa-solid fa-fan"></i></div>
                <div>
                    <span style="font-size:0.75rem; color:var(--text-muted);">Fan Runtime Ratio</span>
                    <div class="stat-value-text"><?= $fan_runtime_pct ?> %</div>
                    <small style="color:#16a34a;"><i class="fa-solid fa-check"></i> Live Data (Last <?= $range_days ?> Days)</small>
                </div>
            </div>
        </div>

        <!-- CHART & SUMMARY GRID -->
        <div class="grid-2-cols">
            <div class="card">
                <div class="card-header-flex">
                    <span class="card-title">Environmental Trends (Last <?= $range_days ?> Days)</span>
                </div>
                <div class="chart-container-box">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom: 12px;">Data Summary</div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Min</th>
                            <th>Max</th>
                            <th>Avg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="fa-solid fa-temperature-half" style="color:#e11d48;"></i> Temp (°C)</td>
                            <td><?= $summary['min_temp'] ?? '0' ?></td>
                            <td><?= $summary['max_temp'] ?? '0' ?></td>
                            <td><?= $summary['avg_temp'] ?? '0' ?></td>
                        </tr>
                        <tr>
                            <td><i class="fa-solid fa-droplet" style="color:#0284c7;"></i> Humidity (%)</td>
                            <td><?= $summary['min_hum'] ?? '0' ?></td>
                            <td><?= $summary['max_hum'] ?? '0' ?></td>
                            <td><?= $summary['avg_hum'] ?? '0' ?></td>
                        </tr>
                        <tr>
                            <td><i class="fa-solid fa-fan" style="color:#15803d;"></i> Fan Runtime (%)</td>
                            <td>--</td>
                            <td>--</td>
                            <td><?= $fan_runtime_pct ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DAILY OVERVIEW & QUICK REPORTS -->
        <div class="reports-bottom-grid">
            <div class="card">
                <div class="card-title" style="margin-bottom: 12px;">Daily Overview (Last <?= $range_days ?> Days)</div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Avg Temp (°C)</th>
                            <th>Avg Humidity (%)</th>
                            <th>Fan Runtime (%)</th>
                            <th>Alerts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daily_rows) > 0): ?>
                            <?php foreach ($daily_rows as $d): ?>
                                <tr>
                                    <td><?= $d['formatted_date'] ?></td>
                                    <td><?= $d['avg_temp'] ?></td>
                                    <td><?= $d['avg_hum'] ?></td>
                                    <td><?= $d['fan_pct'] ?>%</td>
                                    <td>
                                        <span class="badge-alert-num <?= ($d['alert_count'] > 0) ? 'has-alert' : '' ?>">
                                            <?= $d['alert_count'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;">No sensor readings found for the selected range.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom: 12px;">Reports</div>
                <div class="report-item-card">
                    <div>
                        <div style="font-size:0.82rem; font-weight:700;">Daily Report</div>
                        <div style="font-size:0.72rem; color:var(--text-muted);">Summary of today's parameters</div>
                    </div>
                    <a href="export_csv.php?type=daily" class="btn-icon-action"><i class="fa-solid fa-download"></i></a>
                </div>
                <div class="report-item-card">
                    <div>
                        <div style="font-size:0.82rem; font-weight:700;">Weekly Report</div>
                        <div style="font-size:0.72rem; color:var(--text-muted);">Last 7 days parameters</div>
                    </div>
                    <a href="export_csv.php?type=weekly" class="btn-icon-action"><i class="fa-solid fa-download"></i></a>
                </div>
            </div>
        </div>

    </div>

    <!-- LIVE CHART.JS INTEGRATION -->
    <script>
        const ctxTrends = document.getElementById('trendsChart').getContext('2d');
        new Chart(ctxTrends, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_dates); ?>,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: <?php echo json_encode($chart_temps); ?>,
                        borderColor: '#e11d48',
                        backgroundColor: '#e11d48',
                        borderWidth: 2,
                        tension: 0.2
                    },
                    {
                        label: 'Humidity (%)',
                        data: <?php echo json_encode($chart_hums); ?>,
                        borderColor: '#0284c7',
                        backgroundColor: '#0284c7',
                        borderWidth: 2,
                        tension: 0.2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: false }
                }
            }
        });
    </script>

</body>

</html>