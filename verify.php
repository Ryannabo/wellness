<?php
session_start();
require 'db.php';
require 'config.php';

$error = '';
$email = '';

if (!isset($_SESSION['verify_email'])) {
    header('Location: register.php');
    exit();
}

$email = $_SESSION['verify_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['otp'])) {
            throw new Exception("Please enter the verification code!");
        }

        $entered_otp = preg_replace('/[^0-9]/', '', $_POST['otp']);

        // Get OTP record
        $stmt = $conn->prepare("SELECT * FROM temp_users 
                              WHERE email = ? 
                              ORDER BY created_at DESC 
                              LIMIT 1");
        $stmt->execute([$email]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otp_record) {
            throw new Exception("Verification session expired!");
        }

        // Check expiration
        $current_time = date('Y-m-d H:i:s');
        if ($current_time > $otp_record['otp_expiry']) {
            throw new Exception("Verification code has expired!");
        }

        // Verify OTP
        if ($entered_otp !== $otp_record['otp']) {
            throw new Exception("Invalid verification code!");
        }

        // Start transaction
        $conn->beginTransaction();

        // First, get the gender_id from the gender string
        $gender_stmt = $conn->prepare("SELECT id FROM genders WHERE value = ?");
        $gender_stmt->execute([$otp_record['gender']]);
        $gender_result = $gender_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gender_result) {
            throw new Exception("Invalid gender value!");
        }

        $gender_id = $gender_result['id'];

        // Also need to add role_id (default to employee role)
        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE value = 'employee'");
        $role_stmt->execute();
        $role_result = $role_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role_result) {
            throw new Exception("Default role not found!");
        }

        $role_id = $role_result['id'];

        // Updated INSERT query with correct field names
        $stmt = $conn->prepare("INSERT INTO users 
            (name, username, email, gender_id, contact_number, 
            emergency_number, address, birthday, password, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $user_data = [
            $otp_record['name'],
            $otp_record['username'],
            $otp_record['email'],
            $gender_id, // Now using gender_id instead of gender string
            $otp_record['contact_number'],
            $otp_record['emergency_number'],
            $otp_record['address'],
            $otp_record['birthday'],
            $otp_record['password'],
            $role_id // Adding required role_id field
        ];

        if (!$stmt->execute($user_data)) {
            throw new Exception("Failed to create user account!");
        }

        $user_id = $conn->lastInsertId();

        // Delete temporary record
        $stmt = $conn->prepare("DELETE FROM temp_users WHERE id = ?");
        if (!$stmt->execute([$otp_record['id']])) {
            throw new Exception("Failed to clean temporary data!");
        }

        $conn->commit();

        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['logged_in'] = true;
        unset($_SESSION['verify_email']);
        
        // Regenerate session ID
        session_regenerate_id(true);

        // Redirect to dashboard
        header('Location: Login.php');
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | Wellness System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --accent: #ec4899;
        --light: rgba(255, 255, 255, 0.9);
        --dark: #1e293b;
        --success: #22c55e;
        --error: #ef4444;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        background: linear-gradient(45deg, #e0f2fe 0%, #fae8ff 100%);
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 20px;
    }

    .glass-container {
        width: 100%;
        max-width: 500px;
        background: var(--light);
        border-radius: 24px;
        backdrop-filter: blur(16px);
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
        overflow: hidden;
        animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .gradient-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 2.5rem;
        text-align: center;
        position: relative;
    }

    .gradient-header::after {
        content: '';
        position: absolute;
        bottom: -30px;
        left: 0;
        right: 0;
        height: 60px;
        background: inherit;
        transform: skewY(-3deg);
        z-index: -1;
    }

    h1 {
        font-weight: 600;
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .content {
        padding: 2.5rem;
    }

    .otp-group {
        position: relative;
        margin-bottom: 2rem;
    }

    .otp-label {
        display: block;
        margin-bottom: 1.5rem;
        color: var(--dark);
        font-weight: 500;
        text-align: center;
    }

    .email-display {
        color: var(--primary);
        font-weight: 600;
        word-break: break-all;
        display: block;
        margin-top: 0.75rem;
    }

    .otp-inputs {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .otp-input {
        width: 100%;
        height: 60px;
        text-align: center;
        font-size: 1.5rem;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background: rgba(248, 250, 252, 0.8);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .otp-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        transform: translateY(-2px);
    }

    .verify-btn {
        width: 100%;
        padding: 1.25rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        position: relative;
        overflow: hidden;
    }

    .verify-btn::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.15));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .verify-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.25);
    }

    .verify-btn:hover::after {
        opacity: 1;
    }

    .verify-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        background: linear-gradient(135deg, #a5b4fc, #818cf8);
    }

    .loading-spinner {
        width: 24px;
        height: 24px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: none;
    }

    .resend-text {
        text-align: center;
        margin-top: 1.5rem;
        color: #64748b;
    }

    .resend-link {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
        position: relative;
    }

    .resend-link::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--primary);
        transition: width 0.3s ease;
    }

    .resend-link:hover::after {
        width: 100%;
    }

    .error-card {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid rgba(239, 68, 68, 0.2);
        animation: shake 0.4s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(40px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        75% { transform: translateX(8px); }
    }

    @media (max-width: 480px) {
        .glass-container {
            margin: 1rem;
            border-radius: 20px;
        }
        
        .gradient-header {
            padding: 2rem;
        }
        
        .content {
            padding: 2rem;
        }
        
        .otp-inputs {
            gap: 0.75rem;
        }
        
        .otp-input {
            height: 50px;
            font-size: 1.25rem;
        }
    }
    </style>
</head>
<body>
    <div class="glass-container">
        <div class="gradient-header">
            <h1>Secure Verification</h1>
            <p>Your wellness journey starts here</p>
        </div>
        
        <div class="content">
            <?php if(!empty($error)): ?>
                <div class="error-card">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="verifyForm">
                <div class="otp-group">
                    <label class="otp-label">
                        Enter verification code sent to
                        <span class="email-display"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <div class="otp-inputs">
                        <input type="text" name="otp1" maxlength="1" class="otp-input" autofocus>
                        <input type="text" name="otp2" maxlength="1" class="otp-input">
                        <input type="text" name="otp3" maxlength="1" class="otp-input">
                        <input type="text" name="otp4" maxlength="1" class="otp-input">
                        <input type="text" name="otp5" maxlength="1" class="otp-input">
                        <input type="text" name="otp6" maxlength="1" class="otp-input">
                    </div>
                </div>
                
                <button type="submit" class="verify-btn" id="verifyBtn">
                    <span id="btnText">Verify Now</span>
                    <div class="loading-spinner" id="spinner"></div>
                </button>
            </form>

            <p class="resend-text">
                Didn't receive the code? 
                <a href="resend-otp.php" class="resend-link">Resend Code</a>
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputs = document.querySelectorAll('.otp-input');
        const form = document.getElementById('verifyForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('btnText');

        // OTP input auto-focus
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && index > 0 && !e.target.value) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Form submission handling
        form.addEventListener('submit', (e) => {
            const otp = Array.from(inputs).map(input => input.value).join('');
            if (otp.length !== 6) {
                e.preventDefault();
                inputs[0].focus();
                return;
            }
            
            // Add hidden input with full OTP
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'otp';
            hiddenInput.value = otp;
            form.appendChild(hiddenInput);

            // Show loading state
            verifyBtn.disabled = true;
            btnText.textContent = 'Verifying...';
            spinner.style.display = 'block';
        });

        // Paste handler
        form.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text');
            const pasteDigits = pasteData.replace(/\D/g,'').slice(0,6).split('');
            inputs.forEach((input, index) => {
                input.value = pasteDigits[index] || '';
            });
            inputs[5].focus();
        });
    });
    </script>
</body>