<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ----------------------
   LOAD .ENV (CORECT)
---------------------- */
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

/* ----------------------
   HANDLE POST
---------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $service_id = $_POST['service_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $message = $_POST['message'];

    $mail = new PHPMailer(true);

    try {

        /* ---------------- SMTP ---------------- */
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'];
        $mail->Port = $_ENV['SMTP_PORT'];

        /* ---------------- EMAIL ---------------- */
        $mail->setFrom($_ENV['SMTP_USER'], "Auto Service");
        $mail->addAddress($_ENV['MAIL_TO']);

        $mail->isHTML(true);
        $mail->Subject = "New message (Service ID: $service_id)";
        $mail->Body = "
            <h3>New Contact Request</h3>
            <p><b>Name:</b> {$name}</p>
            <p><b>Email:</b> {$email}</p>
            <p><b>Message:</b><br>{$message}</p>
        ";

        $mail->send();
        echo "OK";

    } catch (Exception $e) {
        echo "ERROR: " . $mail->ErrorInfo;
    }
}