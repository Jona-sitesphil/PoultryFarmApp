<?php
require_once 'db.php';

// Kunin ang raw data kung galing sa ESP32
$raw_data = file_get_contents('php://input');
if (empty($_POST) && !empty($raw_data)) {
    parse_str($raw_data, $_POST);
}

// Gamitin ang $_REQUEST para masigurong masasalo kahit anong paraan ng pagpasa
if (isset($_REQUEST['temperature']) && isset($_REQUEST['humidity'])) {

    $temperature = floatval($_REQUEST['temperature']);
    $humidity = floatval($_REQUEST['humidity']);
    $heat_index = isset($_REQUEST['heat_index']) ? floatval($_REQUEST['heat_index']) : 0.0;
    $status = isset($_REQUEST['status']) ? $conn->real_escape_string($_REQUEST['status']) : 'NORMAL';

    // Explicit na kunin ang fan_status at bulb_status galing sa request
    $fan_status = isset($_REQUEST['fan_status']) ? $conn->real_escape_string($_REQUEST['fan_status']) : 'OFF';
    $bulb_status = isset($_REQUEST['bulb_status']) ? $conn->real_escape_string($_REQUEST['bulb_status']) : 'OFF';

    // SQL Query para ipasok sa database kasama ang bagong status at real-time timestamp (NOW)
    $sql = "INSERT INTO sensor_readings (temperature, humidity, heat_index, status, fan_status, bulb_status, reading_time) 
            VALUES ('$temperature', '$humidity', '$heat_index', '$status', '$fan_status', '$bulb_status', NOW())";

    if ($conn->query($sql) === TRUE) {
        echo "SUCCESS: Data recorded successfully.";
    } else {
        echo "DB ERROR: " . $conn->error;
    }

} else {
    echo "ERROR: Walang natanggap na temperature at humidity data.";
}

$conn->close();
?>