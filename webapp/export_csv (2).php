<?php
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "poultry_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Kunin ang huling 10 readings mula sa database
$sql = "SELECT timestamp, temperature FROM sensor_readings ORDER BY timestamp ASC LIMIT 10";
$result = mysqli_query($conn, $sql);

$data = array();
foreach ($result as $row) {
    $data[] = $row;
}

// I-output bilang JSON
echo json_encode($data);
mysqli_close($conn);
?>