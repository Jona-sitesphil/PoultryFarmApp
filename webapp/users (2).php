<?php
// send_notifications.php

// ---------------------------------------------------------
// 1. FUNCTION PARA MAG-SEND NG EMAIL
// ---------------------------------------------------------
function sendEmailAlert($toEmail, $subject, $bodyMessage)
{
    // TANDAAN: Para sa aktwal na pagpapadala gamit ang Gmail SMTP, 
    // siguraduhing naka-install ang PHPMailer sa project mo via Composer o manual include.

    /* 
    require 'vendor/autoload.php'; // o require 'path/to/PHPMailer/PHPMailer.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_gmail_account@gmail.com'; // Gmail Username
        $mail->Password   = 'your_app_password';             // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your_gmail_account@gmail.com', 'P.O.U.L.T.R.Y Guard System');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyMessage;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Sending Failed: " . $mail->ErrorInfo);
        return false;
    }
    */

    // TEST/SIMULATION MODE (Ilo-log lang sa file para sa testing):
    $logMsg = date('[Y-m-d H:i:s]') . " [EMAIL SENT TO: $toEmail] Subject: $subject | Body: $bodyMessage\n";
    file_put_contents('notification_log.txt', $logMsg, FILE_APPEND);
    return true;
}

// ---------------------------------------------------------
// 2. FUNCTION PARA MAG-SEND NG SMS
// ---------------------------------------------------------
function sendSMSAlert($phoneNumber, $message)
{
    // Halimbawa gamit ang Semaphore API (PH SMS Gateway)
    /*
    $ch = curl_init();
    $parameters = array(
        'apikey' => 'YOUR_SEMAPHORE_API_KEY', // Ilagay ang API Key mo rito
        'number' => $phoneNumber,
        'message' => $message,
        'sendername' => 'POULTRY'
    );
    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
    */

    // TEST/SIMULATION MODE (Ilo-log lang sa file para sa testing):
    $logMsg = date('[Y-m-d H:i:s]') . " [SMS SENT TO: $phoneNumber] Message: $message\n";
    file_put_contents('notification_log.txt', $logMsg, FILE_APPEND);
    return true;
}
?>