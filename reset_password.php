<?php
session_start();

// Database connection using PDO
$servername = "localhost";
$username = "root";
$password = "password";
$dbname = "wellness"; // <- Change this to your actual DB name

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = '';
$success = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token with user join
    $stmt = $conn->prepare("SELECT pr.*, u.email 
                            FROM password_resets pr
                            JOIN users u ON pr.user_id = u.id
                            WHERE pr.token = ? AND pr.expires_at > NOW()");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING) ?? '';
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING) ?? '';
    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING) ?? '';

    try {
        if (empty($password) || empty($confirm_password) || empty($token)) {
            throw new Exception("All fields are required.");
        }

        $stmt = $conn->prepare("SELECT pr.*, u.email 
                                FROM password_resets pr
                                JOIN users u ON pr.user_id = u.id
                                WHERE pr.token = ? AND pr.expires_at > NOW()");
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch();

        if (!$reset_request) {
            throw new Exception("Invalid or expired token.");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters.");
        }

        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
            throw new Exception("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.");
        }

        $conn->beginTransaction();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $reset_request['user_id']]);

        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);

        $conn->commit();

        $_SESSION['success'] = "Password updated successfully!";
        header('Location: login.php');
        exit;

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
    <title>Reset Password | Wellness System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Original UI Styles */
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
            --accent: #ec4899;
            --light: rgba(255, 255, 255, 0.95);
            --dark: #1e293b;
            --error: #ef4444;
            --success: #22c55e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(45deg, #6366f1, #ec4899);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .glass-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: var(--light);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
        }

        input {
            width: 100%;
            padding: 1rem 1rem 1rem 2.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--light);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--light);
            background: rgba(255, 255, 255, 0.15);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .login-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-footer a {
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-footer a:hover {
            color: var(--accent);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        @media (max-width: 480px) {
            .glass-container {
                padding: 1.5rem;
            }
            
            .login-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="glass-container">
        <div class="login-header">
            <h1>New Password</h1>
            <p>Create your new password</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
            
            <div class="form-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" placeholder="New Password" required>
            </div>
            
            <div class="form-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <button type="submit" class="login-btn">Reset Password</button>
        </form>

        <div class="form-footer">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const btn = document.querySelector('.login-btn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;
    });
    </script>
</body>
</html>