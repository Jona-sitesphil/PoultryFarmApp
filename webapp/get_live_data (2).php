<?php
// Disable error reporting display para hindi masira ang JSON output kung may PHP notices
error_reporting(0);
header('Content-Type: application/json');

require_once 'db.php';

// Base Response Structure (Swak sa kahit anong frontend script structure)
$response = [
    'success' => false,
    'latest_temp' => '0.0',
    'latest_hum' => '0',
    'latest_fan' => 'OFF',
    'heat_index' => '0.0',
    'status' => 'NORMAL',
    'latest' => [
        'temperature' => '0.0',
        'humidity' => '0',
        'fan_status' => 'OFF',
        'status' => 'NORMAL',
        'heat_index' => '0.0'
    ],
    'table_rows' => [],
    'chart' => [
        'labels' => [],
        'temperature' => [],
        'temperatures' => [],
        'humidity' => [],
        'humidities' => []
    ],
    'alerts' => []
];

// Kunin ang limit mula sa GET request (Default: 10 readings)
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;

// Query: Kunin ang huling $limit readings
$sql = "SELECT * FROM sensor_readings ORDER BY id DESC LIMIT ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // Safe timestamp check (reading_time o created_at)
        $raw_time = $row['reading_time'] ?? $row['created_at'] ?? null;

        // Pinalitang mas maikli ('h:i A' -> 10:45 PM) para hindi magsumpakan ang labels sa Line Chart
        $formatted_time = $raw_time ? date('h:i A', strtotime($raw_time)) : '';
        $full_time = $raw_time ? date('h:i:s A', strtotime($raw_time)) : '';

        $row['formatted_time'] = $full_time;
        $row['chart_time'] = $formatted_time;

        $rows[] = $row;
    }
    $stmt->close();

    // Kapag may nakuha tayong records mula sa DB
    if (!empty($rows)) {
        $response['success'] = true;
        $response['table_rows'] = $rows;

        // 1. PINAKABAGONG RECORD (Index 0 ang pinakabago dahil ORDER BY id DESC)
        $latestRow = $rows[0];

        $temp = floatval($latestRow['temperature'] ?? 0);
        $hum = floatval($latestRow['humidity'] ?? 0);
        $heatIndex = floatval($latestRow['heat_index'] ?? ($temp + 1.2)); // Fallback sa HI kung wala sa DB
        $statusVal = $latestRow['status'] ?? ($temp >= 33.0 ? 'CRITICAL' : 'NORMAL');
        $fanStatus = $latestRow['fan_status'] ?? ($temp >= 30.0 ? 'ON' : 'OFF');

        $formattedTemp = number_format($temp, 1);
        $formattedHum = number_format($hum, 0);
        $formattedHI = number_format($heatIndex, 1);

        // Flat Keys (para sa direct JS calls)
        $response['latest_temp'] = $formattedTemp;
        $response['latest_hum'] = $formattedHum;
        $response['latest_fan'] = $fanStatus;
        $response['heat_index'] = $formattedHI;
        $response['status'] = $statusVal;

        // Nested Keys (para sa structured JS calls)
        $response['latest'] = [
            'temperature' => $formattedTemp,
            'humidity' => $formattedHum,
            'fan_status' => $fanStatus,
            'status' => $statusVal,
            'heat_index' => $formattedHI
        ];

        // Dynamic System Alerts
        if ($temp >= 35.0) {
            $response['alerts'][] = ['type' => 'alert-red', 'message' => "Critical: High Temperature Detected! ({$formattedTemp}°C)"];
        } else if ($temp >= 30.0) {
            $response['alerts'][] = ['type' => 'alert-yellow', 'message' => "Warning: Elevated Temperature ({$formattedTemp}°C)"];
        } else {
            $response['alerts'][] = ['type' => 'alert-green', 'message' => "System Normal ({$formattedTemp}°C)"];
        }

        if ($hum < 50 || $hum > 80) {
            $response['alerts'][] = ['type' => 'alert-yellow', 'message' => "Humidity Alert ({$formattedHum}%)"];
        }

        // 2. LINE CHART HISTORY DATA (Naka-reverse para lumabas mula pinakaluma papuntang pinakabago: Left -> Right)
        $chartRows = array_reverse($rows);
        foreach ($chartRows as $c_row) {
            $timeLabel = $c_row['chart_time'] ?? '';
            $tempVal = floatval($c_row['temperature'] ?? 0);
            $humVal = floatval($c_row['humidity'] ?? 0);

            $response['chart']['labels'][] = $timeLabel;
            $response['chart']['temperature'][] = $tempVal;
            $response['chart']['temperatures'][] = $tempVal;
            $response['chart']['humidity'][] = $humVal;
            $response['chart']['humidities'][] = $humVal;
        }
    }
}

// Fallback Data kapag walang records sa database
if (!$response['success']) {
    $response['chart']['labels'] = ['12:00 PM', '01:00 PM', '02:00 PM'];
    $response['chart']['temperature'] = [28.0, 31.0, 29.0];
    $response['chart']['temperatures'] = [28.0, 31.0, 29.0];
    $response['chart']['humidity'] = [50, 60, 62];
    $response['chart']['humidities'] = [50, 60, 62];
}

$conn->close();

echo json_encode($response);
?>