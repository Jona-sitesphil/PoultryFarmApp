<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // header('Location: index.php');
    // exit();
}
$username = $_SESSION['username'] ?? 'admin';
$user_role = $_SESSION['role'] ?? 'Viewer';
$is_viewer = (strcasecmp($user_role, 'Viewer') === 0);

require_once 'db.php';

// Handle AJAX Requests (Save o Toggle o Fetch Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // BLOCK VIEWERS SA BACKEND
    if (strcasecmp($_SESSION['role'] ?? 'Viewer', 'Viewer') === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Access Denied: Viewers have read-only access.']);
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'update_thresholds') {
        $max_temp = floatval($_POST['max_temp'] ?? 30.5);
        $max_hum = intval($_POST['max_hum'] ?? 60);

        if ($stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")) {
            $key_temp = 'max_temp';
            $val_temp = (string) $max_temp;
            $stmt->bind_param("ss", $val_temp, $key_temp);
            $stmt->execute();

            $key_hum = 'max_hum';
            $val_hum = (string) $max_hum;
            $stmt->bind_param("ss", $val_hum, $key_hum);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Thresholds updated successfully!']);
        exit;
    }

    if ($action === 'update_mode') {
        $mode = $_POST['mode'] ?? 'automatic';

        if ($stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'system_mode'")) {
            $stmt->bind_param("s", $mode);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['status' => 'success', 'mode' => $mode]);
        exit;
    }

    if ($action === 'toggle_device') {
        $device = $_POST['device'] ?? '';
        $state = $_POST['state'] ?? 'OFF';

        // Tinanggal na ang cooling_mist sa allowed devices
        $allowed_devices = ['exhaust_fan', 'heater'];
        if (in_array($device, $allowed_devices)) {
            if ($stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")) {
                $stmt->bind_param("ss", $state, $device);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['status' => 'success', 'device' => $device, 'state' => $state]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid device']);
        }
        exit;
    }
}

// AJAX Endpoint para sa pag-fetch ng live status para sa ESP32 at UI Polling
if (isset($_GET['action']) && $_GET['action'] === 'get_device_status') {
    header('Content-Type: application/json');
    $settings = [];
    $result = $conn->query("SELECT * FROM system_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    echo json_encode($settings);
    exit;
}

// Kunin ang current settings mula sa database para mai-load sa UI
$settings = [];
$result = $conn->query("SELECT * FROM system_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$system_mode = $settings['system_mode'] ?? 'automatic';
$max_temp = $settings['max_temp'] ?? '30.5';
$max_hum = $settings['max_hum'] ?? '60';
$exhaust_state = $settings['exhaust_fan'] ?? 'OFF';
$heater_state = $settings['heater'] ?? 'OFF';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automated Control - P.O.U.L.T.R.Y.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        min-height: 100vh;
        overflow-x: hidden;
    }

    .sidebar {
        width: 260px;
        background-color: var(--sidebar-bg);
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e0e0e0;
        flex-shrink: 0;
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

    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 20px 30px;
        gap: 15px;
        width: 100%;
        overflow-y: auto;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .header-left h2 {
        font-size: 1.5rem;
        color: #222;
        font-weight: 700;
    }

    .header-left p {
        font-size: 0.82rem;
        color: var(--text-muted);
    }

    .user-info-box {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: var(--text-main);
    }

    .user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
    }

    .system-mode-banner {
        background-color: var(--accent-green);
        color: white;
        padding: 18px 22px;
        border-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        flex-wrap: wrap;
        gap: 15px;
    }

    .system-mode-banner h3 {
        font-size: 1.15rem;
        margin-bottom: 3px;
    }

    .system-mode-banner p {
        font-size: 0.82rem;
        opacity: 0.9;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
        flex-shrink: 0;
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
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }

    input:checked+.slider {
        background-color: #22c55e;
    }

    input:checked+.slider:before {
        transform: translateX(24px);
    }

    input:disabled+.slider {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .control-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .card {
        background-color: var(--card-bg);
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 0.98rem;
        font-weight: 600;
        margin-bottom: 4px;
        color: var(--text-main);
    }

    .section-desc {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    .form-group {
        margin-bottom: 14px;
    }

    .form-group label {
        display: block;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 5px;
    }

    .input-group {
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }

    .input-group input {
        flex: 1;
        border: none;
        padding: 9px 12px;
        font-size: 0.88rem;
        outline: none;
        width: 100%;
    }

    .input-group input:disabled {
        background-color: #f1f5f9;
        cursor: not-allowed;
    }

    .input-group span {
        background: #f8fafc;
        padding: 9px 14px;
        font-size: 0.82rem;
        color: var(--text-muted);
        border-left: 1px solid var(--border-color);
    }

    .btn-save {
        background-color: var(--accent-green);
        color: white;
        border: none;
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        margin-top: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: background 0.2s;
    }

    .btn-save:hover {
        background-color: #1b5e20;
    }

    .btn-save:disabled {
        background-color: #94a3b8;
        cursor: not-allowed;
    }

    .manual-devices-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .device-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8fafc;
        border: 1px solid var(--border-color);
        padding: 12px 15px;
        border-radius: 8px;
        gap: 10px;
    }

    .device-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .device-icon {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .device-icon.fan {
        background-color: #e8f5e9;
        color: var(--accent-green);
    }

    .device-icon.heater {
        background-color: #ffebee;
        color: var(--accent-red);
    }

    .device-name {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-main);
    }

    .device-status {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    #alert-box {
        display: none;
        padding: 10px 14px;
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
        border-radius: 6px;
        font-size: 0.82rem;
        margin-bottom: 10px;
    }

    /* Responsive adjustments for Mobile Phones */
    @media (max-width: 768px) {
        body {
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar {
            width: 100%;
            height: auto;
            border-right: none;
            border-bottom: 1px solid #e0e0e0;
        }

        .nav-menu {
            display: flex;
            overflow-x: auto;
            padding: 10px;
            gap: 5px;
        }

        .nav-item {
            white-space: nowrap;
            margin-bottom: 0;
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .sidebar-footer {
            display: none;
        }

        .main-content {
            padding: 15px;
        }

        .control-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-logo"><img src="image/logo.jpg" alt="Logo"></div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="sensor_monitoring.php" class="nav-item"><i class="fa-solid fa-satellite-dish"></i> Sensor Monitoring</a>
            <a href="automated.php" class="nav-item active"><i class="fa-solid fa-microchip"></i> Automated Control</a>
            <a href="alerts.php" class="nav-item"><i class="fa-regular fa-bell"></i> Alerts</a>
            <a href="reports.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i> Reports</a>
            <a href="users.php" class="nav-item"><i class="fa-solid fa-users"></i> Users</a>
            <a href="settings.php" class="nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
            <a href="logout.php" class="nav-item" style="color: var(--accent-red);">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
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

    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <h2>Automated Control</h2>
                <p>Manage environmental thresholds and override IoT devices manually.</p>
            </div>
            <div class="user-info-box">
                <img src="image/logo.jpg" alt="User Avatar" class="user-avatar">
                <span><strong><?php echo htmlspecialchars($username); ?></strong>
                    (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <?php if ($is_viewer): ?>
            <div style="background: #fff3cd; color: #856404; padding: 12px 16px; border-radius: 8px; border: 1px solid #ffeeba; font-size: 0.88rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.1rem;"></i>
                <div><strong>Viewer Mode:</strong> You are logged in as a Viewer. Controls are disabled for read-only access.</div>
            </div>
        <?php endif; ?>

        <div class="system-mode-banner">
            <div>
                <h3>System Mode: <span id="mode-text"><?php echo ($system_mode === 'automatic') ? 'Automatic' : 'Manual Override'; ?></span></h3>
                <p>When set to automatic, devices operate based on predefined threshold rules.</p>
            </div>
            <label class="switch">
                <input type="checkbox" id="systemModeToggle"
                    <?php echo ($system_mode === 'automatic') ? 'checked' : ''; ?>
                    <?php echo $is_viewer ? 'disabled' : ''; ?>>
                <span class="slider"></span>
            </label>
        </div>

        <div id="alert-box">Settings updated successfully to database!</div>

        <div class="control-grid">
            <div class="card">
                <div class="section-title"><i class="fa-solid fa-sliders"></i> Environmental Thresholds</div>
                <div class="section-desc">Set the ideal limits. System will automatically trigger devices if values exceed these thresholds.</div>

                <form id="thresholdForm">
                    <div class="form-group">
                        <label>Target Temperature (Max)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" id="max_temp"
                                value="<?php echo htmlspecialchars($max_temp); ?>"
                                <?php echo $is_viewer ? 'disabled' : ''; ?> required>
                            <span>°C</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Target Humidity (Max)</label>
                        <div class="input-group">
                            <input type="number" step="1" id="max_hum" value="<?php echo htmlspecialchars($max_hum); ?>"
                                <?php echo $is_viewer ? 'disabled' : ''; ?> required>
                            <span>%</span>
                        </div>
                    </div>

                    <?php if (!$is_viewer): ?>
                        <button type="submit" class="btn-save">
                            <i class="fa-solid fa-floppy-disk"></i> Save Thresholds
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <div class="section-title"><i class="fa-solid fa-power-off"></i> Device Manual Overrides</div>
                <div class="section-desc">Manually switch devices on or off. (Disabled if System Mode is Automatic or Viewer).</div>

                <div class="manual-devices-grid">
                    <!-- Exhaust Fan -->
                    <div class="device-box">
                        <div class="device-info">
                            <div class="device-icon fan"><i class="fa-solid fa-fan"></i></div>
                            <div>
                                <div class="device-name">Exhaust Fan</div>
                                <div class="device-status" id="status-fan">
                                    <?php echo $exhaust_state; ?>
                                    (<?php echo ($system_mode === 'automatic') ? 'Auto' : 'Manual'; ?>)
                                </div>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" class="manual-override" id="toggle-exhaust_fan"
                                data-device="exhaust_fan" <?php echo ($exhaust_state === 'ON') ? 'checked' : ''; ?>
                                <?php echo ($system_mode === 'automatic' || $is_viewer) ? 'disabled' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <!-- Brooder / Heater -->
                    <div class="device-box">
                        <div class="device-info">
                            <div class="device-icon heater"><i class="fa-solid fa-fire"></i></div>
                            <div>
                                <div class="device-name">Brooder / Heater</div>
                                <div class="device-status" id="status-heater">
                                    <?php echo $heater_state; ?>
                                    (<?php echo ($system_mode === 'automatic') ? 'Auto' : 'Manual'; ?>)
                                </div>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" class="manual-override" id="toggle-heater" data-device="heater"
                                <?php echo ($heater_state === 'ON') ? 'checked' : ''; ?>
                                <?php echo ($system_mode === 'automatic' || $is_viewer) ? 'disabled' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const isViewer = <?php echo json_encode($is_viewer); ?>;

    $(document).ready(function() {
        function fetchAutomatedStatus() {
            $.ajax({
                url: 'automated.php?action=get_device_status',
                method: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (!res) return;

                    let mode = res.system_mode || 'automatic';
                    let isAuto = (mode === 'automatic');

                    if (!isViewer && $('#systemModeToggle').is(':checked') !== isAuto) {
                        $('#systemModeToggle').prop('checked', isAuto);
                        if (isAuto) {
                            $('#mode-text').text('Automatic');
                            $('.manual-override').prop('disabled', true);
                        } else {
                            $('#mode-text').text('Manual Override');
                            $('.manual-override').prop('disabled', false);
                        }
                    }

                    updateDeviceUI('exhaust_fan', res.exhaust_fan, '#status-fan', '#toggle-exhaust_fan', isAuto);
                    updateDeviceUI('heater', res.heater, '#status-heater', '#toggle-heater', isAuto);
                }
            });
        }

        function updateDeviceUI(deviceName, state, statusSelector, toggleSelector, isAuto) {
            let currentState = state || 'OFF';
            let modeLabel = isAuto ? 'Auto' : 'Manual';
            let textVal = currentState + ' (' + modeLabel + ')';

            if ($(statusSelector).text() !== textVal) {
                $(statusSelector).text(textVal);
            }

            let shouldBeChecked = (currentState === 'ON');
            if ($(toggleSelector).prop('checked') !== shouldBeChecked) {
                $(toggleSelector).prop('checked', shouldBeChecked);
            }

            if (isViewer) {
                $(toggleSelector).prop('disabled', true);
            }
        }

        setInterval(fetchAutomatedStatus, 3000);

        $('#systemModeToggle').on('change', function() {
            if (isViewer) return;
            const isAuto = $(this).is(':checked');
            const mode = isAuto ? 'automatic' : 'manual';

            $.ajax({
                url: 'automated.php',
                method: 'POST',
                data: {
                    action: 'update_mode',
                    mode: mode
                },
                success: function(res) {
                    if (res.status === 'error') {
                        alert(res.message);
                        return;
                    }
                    if (isAuto) {
                        $('#mode-text').text('Automatic');
                        $('.manual-override').prop('disabled', true);
                    } else {
                        $('#mode-text').text('Manual Override');
                        $('.manual-override').prop('disabled', false);
                    }
                    $('#alert-box').text('System mode updated to ' + mode).fadeIn().delay(2000).fadeOut();
                    fetchAutomatedStatus();
                }
            });
        });

        $('#thresholdForm').on('submit', function(logEvent) {
            logEvent.preventDefault();
            if (isViewer) return;
            $.ajax({
                url: 'automated.php',
                method: 'POST',
                data: {
                    action: 'update_thresholds',
                    max_temp: $('#max_temp').val(),
                    max_hum: $('#max_hum').val()
                },
                success: function(response) {
                    if (response.status === 'error') {
                        alert(response.message);
                        return;
                    }
                    $('#alert-box').text('Thresholds saved to database successfully!').fadeIn().delay(2000).fadeOut();
                }
            });
        });

        $('.manual-override').on('change', function() {
            if (isViewer) return;
            const deviceName = $(this).data('device');
            const state = $(this).is(':checked') ? 'ON' : 'OFF';
            const statusElem = $(this).closest('.device-box').find('.device-status');

            statusElem.text(state + ' (Manual)');

            $.ajax({
                url: 'automated.php',
                method: 'POST',
                data: {
                    action: 'toggle_device',
                    device: deviceName,
                    state: state
                },
                success: function(res) {
                    if (res.status === 'error') {
                        alert(res.message);
                        return;
                    }
                    console.log('Device status saved to DB:', res);
                }
            });
        });
    });
    </script>
</body>

</html>