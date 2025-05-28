<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config.php';

// Database credentials
    $host = 'localhost';
    $dbname = 'wellness';
    $user = 'root';
    $pass = ''; // or your DB password

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.value as role_name, us.value as status_name
            FROM users u
            JOIN roles r ON u.role_id = r.id 
            JOIN user_statuses us ON u.status_id = us.id
            WHERE u.username = ? OR u.email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status_name'] !== 'active') {
                $_SESSION['error'] = "Your account is " . $user['status_name'] . ". Please contact administrator.";
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];

                switch ($_SESSION['role']) {
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'manager':
                        header("Location: manager_dashboard.php");
                        break;
                    case 'hr':
                        header("Location: hr\dashboard.php");
                        break;
                    default:
                        header("Location: user_dashboard.php");
                }
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid username/email or password.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Login error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Wellness System | Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --wellness-primary: #6366f1;
            --wellness-secondary: #8b5cf6;
            --wellness-accent: #06b6d4;
            --wellness-success: #10b981;
            --wellness-warning: #f59e0b;
            --wellness-danger: #ef4444;
            --wellness-sage: #84cc16;
            --wellness-mint: #14b8a6;
            --wellness-lavender: #a855f7;
            --wellness-peach: #fb7185;
            --wellness-cream: #fef3c7;
            --wellness-forest: #059669;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --wellness-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #a8e6cf 100%);
            --wellness-card-gradient: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.8) 100%);
            --shadow-wellness: 0 20px 40px rgba(102, 126, 234, 0.15);
            --shadow-wellness-lg: 0 25px 50px rgba(102, 126, 234, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--wellness-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Wellness-themed floating elements */
        .wellness-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .floating-leaf {
            position: absolute;
            opacity: 0.1;
            animation: floatLeaf 15s ease-in-out infinite;
        }

        .floating-leaf:nth-child(1) {
            top: 10%;
            left: 10%;
            font-size: 2rem;
            color: var(--wellness-sage);
            animation-delay: 0s;
        }

        .floating-leaf:nth-child(2) {
            top: 20%;
            right: 15%;
            font-size: 1.5rem;
            color: var(--wellness-mint);
            animation-delay: 3s;
        }

        .floating-leaf:nth-child(3) {
            bottom: 30%;
            left: 20%;
            font-size: 1.8rem;
            color: var(--wellness-forest);
            animation-delay: 6s;
        }

        .floating-leaf:nth-child(4) {
            bottom: 15%;
            right: 25%;
            font-size: 2.2rem;
            color: var(--wellness-sage);
            animation-delay: 9s;
        }

        .floating-leaf:nth-child(5) {
            top: 50%;
            left: 5%;
            font-size: 1.3rem;
            color: var(--wellness-mint);
            animation-delay: 12s;
        }

        @keyframes floatLeaf {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                opacity: 0.1; 
            }
            50% { 
                transform: translateY(-20px) rotate(10deg); 
                opacity: 0.2; 
            }
        }

        /* Main container */
        .wellness-container {
            width: 100%;
            max-width: 460px;
            position: relative;
        }

        .wellness-card {
            background: var(--wellness-card-gradient);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            padding: 40px;
            box-shadow: var(--shadow-wellness);
            position: relative;
            overflow: hidden;
            animation: cardSlideIn 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes cardSlideIn {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Wellness accent border */
        .wellness-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--wellness-sage), var(--wellness-mint), var(--wellness-accent));
            border-radius: 24px 24px 0 0;
        }

        /* Header section */
        .wellness-header {
            text-align: center;
            margin-bottom: 35px;
            position: relative;
        }

        .wellness-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--wellness-primary), var(--wellness-secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
            animation: logoBreath 4s ease-in-out infinite;
        }

        @keyframes logoBreath {
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
            }
            50% { 
                transform: scale(1.05);
                box-shadow: 0 12px 35px rgba(99, 102, 241, 0.4);
            }
        }

        .wellness-logo::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: linear-gradient(45deg, var(--wellness-sage), var(--wellness-mint), var(--wellness-accent), var(--wellness-lavender));
            border-radius: 50%;
            z-index: -1;
            opacity: 0.7;
            animation: logoGlow 3s ease-in-out infinite;
        }

        @keyframes logoGlow {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }

        .wellness-logo i {
            font-size: 2rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .wellness-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--wellness-primary), var(--wellness-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wellness-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 400;
            margin-bottom: 15px;
        }

        .wellness-tagline {
            background: linear-gradient(135deg, var(--wellness-sage), var(--wellness-mint));
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(132, 204, 22, 0.3);
        }

        /* Alert styling */
        .wellness-alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 25px;
            color: var(--wellness-danger);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            animation: alertSlide 0.5s ease-out;
        }

        @keyframes alertSlide {
            0% {
                opacity: 0;
                transform: translateX(-10px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Demo credentials */
        .wellness-demo {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(20, 184, 166, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .wellness-demo::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: demoShimmer 3s ease-in-out infinite;
        }

        @keyframes demoShimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .demo-title {
            color: var(--wellness-forest);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-grid {
            display: grid;
            gap: 10px;
        }

        .demo-item {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .demo-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border-color: var(--wellness-mint);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.2);
        }

        .demo-role {
            color: var(--wellness-forest);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .demo-creds {
            color: var(--gray-600);
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.7);
            padding: 4px 8px;
            border-radius: 6px;
        }

        /* Form styling */
        .wellness-form {
            position: relative;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px 16px 55px;
            background: rgba(255, 255, 255, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 14px;
            color: var(--gray-800);
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-input::placeholder {
            color: var(--gray-500);
            font-weight: 400;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--wellness-mint);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--wellness-primary);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus + .input-icon {
            color: var(--wellness-mint);
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--wellness-secondary);
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--wellness-mint);
            transform: translateY(-50%) scale(1.1);
        }

        /* Form options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0;
        }

        .remember-option {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray-600);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remember-option:hover {
            color: var(--wellness-primary);
        }

        .custom-check {
            width: 18px;
            height: 18px;
            border: 2px solid var(--wellness-primary);
            border-radius: 4px;
            position: relative;
            transition: all 0.3s ease;
        }

        .custom-check input {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .custom-check input:checked + .check-mark {
            opacity: 1;
            transform: scale(1);
        }

        .custom-check input:checked ~ .custom-check {
            background: var(--wellness-mint);
            border-color: var(--wellness-mint);
        }

        .check-mark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            color: white;
            font-size: 10px;
            font-weight: bold;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .forgot-link {
            color: var(--wellness-primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .forgot-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--wellness-primary), var(--wellness-mint));
            transition: width 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--wellness-mint);
        }

        .forgot-link:hover::after {
            width: 100%;
        }

        /* Wellness button */
        .wellness-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--wellness-primary), var(--wellness-secondary));
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .wellness-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .wellness-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .wellness-btn:hover::before {
            left: 100%;
        }

        .wellness-btn:active {
            transform: translateY(0);
        }

        .wellness-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        /* Loading spinner */
        .wellness-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: wellnessSpin 1s linear infinite;
        }

        @keyframes wellnessSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer */
        .wellness-footer {
            text-align: center;
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .wellness-footer a {
            color: var(--wellness-primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .wellness-footer a:hover {
            color: var(--wellness-mint);
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .wellness-card {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .wellness-title {
                font-size: 1.8rem;
            }
            
            .wellness-logo {
                width: 60px;
                height: 60px;
            }
            
            .wellness-logo i {
                font-size: 1.5rem;
            }
            
            .form-input {
                padding: 14px 16px 14px 45px;
            }
            
            .input-icon {
                left: 16px;
            }
            
            .password-toggle {
                right: 16px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        /* Success animation */
        .success-glow {
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { 
                background: rgba(255, 255, 255, 0.5);
                transform: scale(1);
            }
            50% { 
                background: rgba(16, 185, 129, 0.2);
                transform: scale(1.02);
            }
            100% { 
                background: rgba(255, 255, 255, 0.5);
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="wellness-bg">
        <div class="floating-leaf"><i class="fas fa-leaf"></i></div>
        <div class="floating-leaf"><i class="fas fa-seedling"></i></div>
        <div class="floating-leaf"><i class="fas fa-spa"></i></div>
        <div class="floating-leaf"><i class="fas fa-leaf"></i></div>
        <div class="floating-leaf"><i class="fas fa-seedling"></i></div>
    </div>

    <div class="wellness-container">
        <div class="wellness-card">
            <div class="wellness-header">
                <div class="wellness-logo">
                    <i class="fas fa-spa"></i>
                </div>
                <h1 class="wellness-title">Welcome Back</h1>
                <p class="wellness-subtitle">Continue your wellness journey</p>
                <div class="wellness-tagline">
                    <i class="fas fa-heart"></i>
                    Your wellbeing matters
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="wellness-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>



            <form method="POST" class="wellness-form" id="wellnessForm">
                <div class="form-group">
                    <input 
                        type="text" 
                        name="username" 
                        class="form-input" 
                        placeholder="Username or Email" 
                        required 
                        id="username"
                    />
                    <i class="fas fa-user input-icon"></i>
                </div>

                <div class="form-group">
                    <input 
                        type="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Password" 
                        required 
                        id="password"
                    />
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>

                <div class="form-options">
                    <label class="remember-option">
                        <div class="custom-check">
                            <input type="checkbox" name="remember">
                            <div class="check-mark">✓</div>
                        </div>
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="wellness-btn" id="wellnessButton">
                    <div class="btn-content">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </div>
                </button>
            </form>

            <div class="wellness-footer">
                New to our wellness community? <a href="register.php">Join us today</a>
            </div>
        </div>
    </div>

    <script>
        // Demo credentials functionality
        document.querySelectorAll('.demo-item').forEach(item => {
            item.addEventListener('click', function() {
                const username = this.dataset.username;
                const password = this.dataset.password;
                
                document.getElementById('username').value = username;
                document.getElementById('password').value = password;
                
                // Success animation
                this.classList.add('success-glow');
                setTimeout(() => this.classList.remove('success-glow'), 600);
                
                // Focus username field
                document.getElementById('username').focus();
            });
        });

        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submission
        document.getElementById('wellnessForm').addEventListener('submit', function() {
            const button = document.getElementById('wellnessButton');
            const btnContent = button.querySelector('.btn-content');
            
            button.disabled = true;
            btnContent.innerHTML = `
                <div class="wellness-spinner"></div>
                <span>Signing in...</span>
            `;
        });

        // Input focus effects
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.01)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Gentle hover effect on card
        document.querySelector('.wellness-card').addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = 'var(--shadow-wellness-lg)';
        });

        document.querySelector('.wellness-card').addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'var(--shadow-wellness)';
        });
    </script>
</body>
</html>