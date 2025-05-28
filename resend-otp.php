<?php
session_start();
require 'db.php';
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['verify_email'])) {
    die(json_encode(['success' => false, 'error' => 'Session expired']));
}

$email = $_SESSION['verify_email'];

try {
    // Rate limiting: 3 attempts every 10 minutes
    if (!isset($_SESSION['resend_attempts'])) {
        $_SESSION['resend_attempts'] = 1;
        $_SESSION['first_attempt'] = time();
    } else {
        $time_elapsed = time() - $_SESSION['first_attempt'];
        if ($time_elapsed < 600) { // 10 minutes
            if ($_SESSION['resend_attempts'] >= 3) {
                throw new Exception("Too many attempts. Try again later.");
            }
            $_SESSION['resend_attempts']++;
        } else {
            $_SESSION['resend_attempts'] = 1;
            $_SESSION['first_attempt'] = time();
        }
    }

    // Generate new OTP
    // In resend-otp.php, update OTP generation:
    $new_otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Update database
    $conn->beginTransaction();
    $stmt = $conn->prepare("UPDATE temp_users SET otp = ?, otp_expiry = ? WHERE email = ?");
    $stmt->execute([$new_otp, $otp_expiry, $email]);

    // Send email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'villanuevagerry213@gmail.com'; // Replace with actual email
    $mail->Password = 'dqcgkuxhszukiuiu';    // Replace with app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('villanuevagerry213@gmail.com', 'DYCI MANAGEMENT');
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    $mail->Subject = 'New Verification Code';
    $mail->Body = "
        <h3>New Verification Code</h3>
        <p>Your new code is: <strong>$new_otp</strong></p>
        <p>Valid for 10 minutes</p>
    ";

    if (!$mail->send()) {
        throw new Exception("Failed to send new code");
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'remaining' => 3 - $_SESSION['resend_attempts']
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}