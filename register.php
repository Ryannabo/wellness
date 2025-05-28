<?php
session_start();
require 'db.php';
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = ['name', 'username', 'email', 'gender', 
                    'contact_number', 'emergency_number', 
                    'address', 'birthday', 'password'];
        
        foreach ($required as $field) {
            if (empty(trim($_POST[$field]))) {
                throw new Exception("All fields are required!");
            }
        }

        // Sanitize inputs
        $name = htmlspecialchars(trim($_POST['name']));
        $username = htmlspecialchars(trim($_POST['username']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $gender = in_array($_POST['gender'], ['Male', 'Female', 'Other']) ? $_POST['gender'] : '';
        $contact_number = preg_replace('/[^0-9]/', '', $_POST['contact_number']);
        $emergency_number = preg_replace('/[^0-9]/', '', $_POST['emergency_number']);
        $address = htmlspecialchars(trim($_POST['address']));
        $birthday = $_POST['birthday'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Validate password strength
        if (!preg_match('/^(?=.*[A-Z])(?=.*[\W_]).{6,}$/', $_POST['password'])) {
            throw new Exception("Password must be at least 6 characters long, include at least one uppercase letter and one special character.");
        }


        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format!");
        }

        // Validate phone numbers
        if (strlen($contact_number) !== 11) {
            throw new Exception("Contact number must be 11 digits!");
        }

        if (strlen($emergency_number) !== 11) {
            throw new Exception("Emergency number must be 11 digits!");
        }

        // Check email existence
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already registered!");
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Database transaction
        $conn->beginTransaction();
        
        // Store in temp_users
        $stmt = $conn->prepare("INSERT INTO temp_users 
            (name, username, email, gender, contact_number, emergency_number, 
            address, birthday, password, otp, otp_expiry) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        if (!$stmt->execute([
            $name, $username, $email, $gender,
            $contact_number, $emergency_number,
            $address, $birthday, $password,
            $otp, $otp_expiry
        ])) {
            throw new Exception("Failed to create temporary user record!");
        }

        // Configure PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'villanuevagerry213@gmail.com';
        $mail->Password = 'dqcgkuxhszukiuiu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0; // Change to 2 for debugging

        $mail->setFrom('villanuevagerry213@gmail.com', 'WELLNESS SYSTEM');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your Verification Code';
        $mail->Body = "
            <h2>Welcome to " . SITE_NAME . "!</h2>
            <p>Your verification code is: <strong>$otp</strong></p>
            <p>This code will expire in 10 minutes.</p>
        ";

        if (!$mail->send()) {
            throw new Exception("Failed to send verification email!");
        }

        $conn->commit();
        
        // Successful registration - redirect
        $_SESSION['verify_email'] = $email;
        session_write_close();
        header('Location: verify.php');
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
    <title>Register | Wellness System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --error-color: #f72585;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        .register-header {
            background: var(--primary-color);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .register-header h2 {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .register-header p {
            opacity: 0.9;
            font-weight: 300;
        }
        
        .register-body {
            padding: 30px;
        }
        
        .error-message {
            background-color: #fee2e2;
            color: var(--error-color);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message i {
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f8fafc;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            background-color: white;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #94a3b8;
            font-size: 18px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 25px;
            color: #64748b;
            font-size: 14px;
        }
        
        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
            color: var(--secondary-color);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        select.form-control {
            padding: 14px 16px;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
        }
        
        textarea.form-control {
            padding: 14px 16px;
            min-height: 100px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .register-container {
                max-width: 100%;
            }
            
            .register-header {
                padding: 25px 20px;
            }
            
            .register-body {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2>Create Your Account</h2>
            <p>Join our wellness program today</p>
        </div>
        
        <div class="register-body">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                   placeholder="John Doe" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <i class="fas fa-at input-icon"></i>
                            <input type="text" id="username" name="username" class="form-control"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                   placeholder="johndoe123" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                           placeholder="john@example.com" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="" disabled selected>Select gender</option>
                                <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="birthday">Birthday</label>
                            <i class="fas fa-calendar input-icon"></i>
                            <input type="date" id="birthday" name="birthday" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['birthday'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <i class="fas fa-phone input-icon"></i>
                            <input type="text" id="contact_number" name="contact_number" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" 
                                   placeholder="09123456789" required maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="emergency_number">Emergency Number</label>
                            <i class="fas fa-phone-alt input-icon"></i>
                            <input type="text" id="emergency_number" name="emergency_number" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['emergency_number'] ?? '') ?>" 
                                   placeholder="09123456789" required maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <i class="fas fa-map-marker-alt input-icon"></i>
                    <textarea id="address" name="address" class="form-control" 
                              placeholder="Enter your complete address" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                    placeholder="Create a password" required
                    pattern="(?=.*[A-Z])(?=.*[\W_]).{6,}"
                    title="Must be at least 6 characters, include an uppercase letter and a special character">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Register Account
                </button>
            </form>
            
            <div class="form-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>

    <script>
        // Enhanced phone number validation
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInputs = document.querySelectorAll('input[type="text"][pattern="[0-9]{11}"]');
            
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    const newValue = this.value.replace(/[^0-9]/g, '').slice(0,11);
                    if (newValue !== this.value) {
                        this.value = newValue;
                        this.reportValidity();
                    }
                });
                
                input.addEventListener('blur', function(e) {
                    if (this.value.length !== 11) {
                        this.setCustomValidity('Please enter exactly 11 digits');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            });
        });
    </script>
</body>
</html>