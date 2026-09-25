<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = $_POST['message'] ?? '';

    if (empty($userMessage)) {
        echo json_encode(['reply' => 'Mag-type po kayo ng tanong para matulungan ko kayo.']);
        exit;
    }

    // 1. Kunin ang pinakabagong sensor data mula sa database para magsilbing "Context" ng AI
    $temp = 'N/A';
    $hum = 'N/A';
    $status = 'NORMAL';
    $fan = 'OFF';
    $heater = 'OFF';

    $result = $conn->query("SELECT * FROM sensor_readings ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $temp = $row['temperature'] ?? 'N/A';
        $hum = $row['humidity'] ?? 'N/A';
        $status = $row['status'] ?? 'NORMAL';
        $fan = $row['fan_status'] ?? 'OFF';
        $heater = $row['bulb_status'] ?? 'OFF';
    }

    // 2. Google Gemini API Setup gamit ang iyong API key
    // Configure GEMINI_API_KEY in the server environment; do not commit API keys.
    $apiKey = getenv('GEMINI_API_KEY') ?: '';
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

    $systemPrompt = "Ikaw ang PoultyAI, isang matalinong AI assistant para sa P.O.U.L.T.R.Y Guard system. " .
        "Ang kasalukuyang live status sa manukan ngayon ay: Temperatura: {$temp}°C, Humidity: {$hum}%, " .
        "Status: {$status}, Exhaust Fan: {$fan}, Heater: {$heater}. " .
        "Sumagot ka sa wikang Taglish, maikli, direkta, at may malasakit sa mga alagang pugo/manok.";

    $postData = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $systemPrompt . "\n\nTanong ng user: " . $userMessage]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        echo json_encode(['reply' => 'cURL Error: ' . $error_msg]);
        exit;
    }

    curl_close($ch);

    if ($response) {
        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            $aiReply = 'Gemini Error: ' . ($responseData['error']['message'] ?? 'Unknown error');
        } else {
            $aiReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Pasensya na, may problema sa pagkonekta sa AI ngayon.';
        }

        echo json_encode(['reply' => trim($aiReply)]);
    } else {
        echo json_encode(['reply' => 'Hindi maabot ang AI server. Subukan muli mamaya.']);
    }
    exit;
} else {
    echo json_encode(['reply' => 'Invalid request method.']);
}
