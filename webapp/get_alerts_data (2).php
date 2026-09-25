<?php
// Isama ang database connection
require_once 'db.php';

// I-set ang headers para maging downloadable CSV file sa browser
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=POULTRY_Sensor_Logs_' . date('Y-m-d_H-i') . '.csv');

// Buksan ang PHP output stream
$output = fopen('php://output', 'w');

// Header row para sa Excel file
fputcsv($output, ['ID', 'Date & Time', 'Temperature (°C)', 'Humidity (%)', 'Heat Index (°C)', 'Fan Status', 'System Status']);

// Query para kunin ang lahat ng sensor logs mula sa database
$sql = "SELECT * FROM sensor_readings ORDER BY id DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $raw_time = $row['reading_time'] ?? $row['created_at'] ?? '';

        // I-format ang Date & Time para malinis at standardized (e.g. 2026-07-28 18:06:29)
        $formatted_time = !empty($raw_time) ? date('Y-m-d H:i:s', strtotime($raw_time)) : '';

        $temp = floatval($row['temperature'] ?? 0);
        $hum = floatval($row['humidity'] ?? 0);
        $heatIndex = floatval($row['heat_index'] ?? ($temp + 1.2));
        $fan = $row['fan_status'] ?? ($temp >= 30.0 ? 'ON' : 'OFF');
        $status = $row['status'] ?? ($temp >= 33.0 ? 'CRITICAL' : 'NORMAL');

        // Format: Temperature = 1 decimal, Humidity = Whole number (0 decimals)
        fputcsv($output, [
            $row['id'],
            $formatted_time,
            number_format($temp, 1),
            number_format($hum, 0),
            number_format($heatIndex, 1),
            $fan,
            $status
        ]);
    }
}

fclose($output);
$conn->close();
exit();
?>