<?php
// Booking handler: sends WhatsApp via Twilio if credentials are set, otherwise falls back to email via PHPMailer.

// Include PHPMailer for fallback email
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name    = isset($_POST["name"])    ? trim($_POST["name"])    : "";
    $email   = isset($_POST["email"])   ? filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL) : "";
    $phone   = isset($_POST["phone"])   ? trim($_POST["phone"])   : "";
    $date    = isset($_POST["date"])    ? trim($_POST["date"])    : "";
    $time    = isset($_POST["time"])    ? trim($_POST["time"])    : "";
    $message = isset($_POST["message"]) ? trim($_POST["message"]) : "";

    if ( empty($name) || empty($phone) || !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
        http_response_code(400);
        echo "Please complete the form and try again.";
        exit;
    }

    // Compose plain text booking summary
    $booking_text = "New booking request:\n";
    $booking_text .= "Name: $name\n";
    $booking_text .= "Email: $email\n";
    $booking_text .= "Phone: $phone\n";
    if (!empty($date)) $booking_text .= "Date: $date\n";
    if (!empty($time)) $booking_text .= "Time: $time\n";
    if (!empty($message)) $booking_text .= "Message: $message\n";

    // Target WhatsApp number (from user)
    $whatsapp_to = "+2348166295770"; // destination

    // Attempt to send via Twilio if env vars are present
    $twilio_sid  = getenv('TWILIO_ACCOUNT_SID');
    $twilio_token = getenv('TWILIO_AUTH_TOKEN');
    $twilio_from = getenv('TWILIO_WHATSAPP_FROM'); // e.g. +1415... (Twilio WhatsApp-enabled number)

    if ($twilio_sid && $twilio_token && $twilio_from) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/$twilio_sid/Messages.json";
        $post = http_build_query([
            'From' => 'whatsapp:'.$twilio_from,
            'To'   => 'whatsapp:'.$whatsapp_to,
            'Body' => $booking_text
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $twilio_sid . ':' . $twilio_token);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($curl_err || $http_status >= 400) {
            // Twilio failed, fall back to email below
            error_log("Twilio send failed: $curl_err status=$http_status result=$result");
        } else {
            http_response_code(200);
            echo "Thank You! Your booking has been sent via WhatsApp.";
            exit;
        }
    }

    // Fallback: send an email notification to site admin
    $recipient = "youremail@gmail.com"; // Change to your admin email

    $email_content = "<html><body>";
    $email_content .= "<h2>New Booking Request</h2>";
    $email_content .= "<p><strong>Name:</strong> {$name}</p>";
    $email_content .= "<p><strong>Email:</strong> {$email}</p>";
    $email_content .= "<p><strong>Phone:</strong> {$phone}</p>";
    if (!empty($date)) $email_content .= "<p><strong>Date:</strong> {$date}</p>";
    if (!empty($time)) $email_content .= "<p><strong>Time:</strong> {$time}</p>";
    if (!empty($message)) $email_content .= "<p><strong>Message:</strong><br>".nl2br(htmlspecialchars($message))."</p>";
    $email_content .= "</body></html>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.yourhosting.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'yourname@yourdomain.com';
        $mail->Password   = 'your_email_password_here';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('yourname@yourdomain.com', 'Website Booking');
        $mail->addAddress($recipient);
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "New booking from $name";
        $mail->Body    = $email_content;
        $mail->AltBody = strip_tags($email_content);

        $mail->send();
        http_response_code(200);
        echo "Thank You! Your booking has been sent.";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }

} else {
    http_response_code(403);
    echo "There was a problem with your submission, please try again.";
}

?>
