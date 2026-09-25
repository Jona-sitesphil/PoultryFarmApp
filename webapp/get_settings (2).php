<?php
session_start();
// Isama ang database connection dito (Hal. include 'db_connection.php');

// Kunin ang huling mga datos mula sa database para sa Environmental Chart (Hal. Temperature, Humidity, Ammonia sa nakalipas na oras)
// At para sa Daily Overview (Hal. Average Temp, Total Feed Consumption, Water Usage, etc.)

$response = [
    'daily_overview' => [
        'avg_temp' => '28.4 °C',
        'avg_humidity' => '65.2%',
        'ammonia_level' => '12.5 ppm',
        'mortality_rate' => '0.2%'
    ],
    'environmental_chart' => [
        'labels' => ['8:00 AM', '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM'],
        'temperature' => [27.5, 28.0, 29.2, 31.5, 30.8],
        'humidity' => [70, 68, 65, 60, 62]
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
?>