<?php
$host = "localhost";
$user = "root";
$pass = "";
// Eto ang eksaktong database name galing sa phpMyAdmin mo:
$dbname = "thesis_poultry_system_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>