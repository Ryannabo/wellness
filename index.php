<?php 
session_start();

// Redirect logged-in users to their respective dashboards based on role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'manager':
            header('Location: manager_dashboard.php');
            break;
        case 'hr':
            header('Location: hr/dashboard.php');
            break;
        case 'employee':
        default:
            header('Location: user_dashboard.php');
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Wellness And Productivity Monitoring System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
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
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --gradient-hr: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            color: var(--gray-800);
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            z-index: -1;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        .particle:nth-child(1) { width: 20px; height: 20px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 30px; height: 30px; left: 20%; animation-delay: 2s; }
        .particle:nth-child(3) { width: 25px; height: 25px; left: 30%; animation-delay: 4s; }
        .particle:nth-child(4) { width: 35px; height: 35px; left: 40%; animation-delay: 6s; }
        .particle:nth-child(5) { width: 15px; height: 15px; left: 50%; animation-delay: 8s; }
        .particle:nth-child(6) { width: 40px; height: 40px; left: 60%; animation-delay: 10s; }
        .particle:nth-child(7) { width: 20px; height: 20px; left: 70%; animation-delay: 12s; }
        .particle:nth-child(8) { width: 30px; height: 30px; left: 80%; animation-delay: 14s; }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
        }

        .container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            padding: 3rem;
            max-width: 1200px;
            width: 100%;
            box-shadow: var(--shadow-xl);
            text-align: center;
            animation: slideUp 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        .main-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .logo-section {
            margin-bottom: 2rem;
            animation: fadeIn 1s ease-out 0.3s both;
        }

        .logo-icon {
            width: 120px;
            height: 120px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 3rem;
            box-shadow: var(--shadow-xl);
            animation: pulse 2s infinite;
            position: relative;
        }

        .logo-icon::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: var(--gradient-primary);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
            animation: ripple 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes ripple {
            0% { transform: scale(1); opacity: 0.3; }
            100% { transform: scale(1.2); opacity: 0; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            margin-bottom: 3rem;
            animation: fadeIn 1s ease-out 0.5s both;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .header .subtitle {
            font-size: 1.25rem;
            color: var(--gray-600);
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
            font-weight: 400;
        }

        .login-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
            animation: fadeIn 1s ease-out 0.7s both;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius-lg);
            padding: 2.5rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .login-card.employee::before { background: var(--gradient-primary); }
        .login-card.manager::before { background: var(--gradient-secondary); }
        .login-card.hr::before { background: var(--gradient-hr); }
        .login-card.admin::before { background: var(--gradient-warning); }

        .login-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .login-card.employee .card-icon { background: var(--gradient-primary); }
        .login-card.manager .card-icon { background: var(--gradient-secondary); }
        .login-card.hr .card-icon { background: var(--gradient-hr); }
        .login-card.admin .card-icon { background: var(--gradient-warning); }

        .login-card:hover .card-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 1rem;
        }

        .card-description {
            color: var(--gray-600);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .login-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            color: white;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-card.employee .login-btn { background: var(--gradient-primary); }
        .login-card.manager .login-btn { background: var(--gradient-secondary); }
        .login-card.hr .login-btn { background: var(--gradient-hr); }
        .login-card.admin .login-btn { background: var(--gradient-warning); }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .features-section {
            margin-top: 4rem;
            padding-top: 3rem;
            border-top: 1px solid var(--gray-200);
            animation: fadeIn 1s ease-out 0.9s both;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .feature-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background: rgba(99, 102, 241, 0.05);
            border: 1px solid rgba(99, 102, 241, 0.1);
            transition: var(--transition);
        }

        .feature-item:hover {
            background: rgba(99, 102, 241, 0.1);
            transform: translateY(-5px);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .feature-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .feature-description {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
            color: var(--gray-500);
            font-size: 0.875rem;
            animation: fadeIn 1s ease-out 1.1s both;
        }

        .footer-content {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .footer-link {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--primary-dark);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .main-content {
                padding: 2rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .header .subtitle {
                font-size: 1rem;
            }
            
            .login-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.75rem;
            }
            
            .login-card {
                padding: 2rem;
            }
        }

        /* Loading animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .login-btn {
            background: var(--gray-400) !important;
        }
    </style>
</head>
<body>
    <!-- Floating particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-leaf"></i>
                </div>
            </div>
            
            <header class="header">
                <h1>Employee Wellness & Productivity</h1>
                <p class="subtitle">
                    A comprehensive platform to monitor and enhance employee wellness and productivity through 
                    data-driven insights, wellness programs, and intelligent analytics.
                </p>
            </header>
            
            <div class="login-grid">
                <div class="login-card employee">
                    <div class="card-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="card-title">Employee Portal</h3>
                    <p class="card-description">
                        Access your personal dashboard, wellness tools, tasks, and track your productivity metrics.
                    </p>
                    <a href="login.php" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login as Employee</span>
                    </a>
                </div>
                
                <div class="login-card manager">
                    <div class="card-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h3 class="card-title">Manager Portal</h3>
                    <p class="card-description">
                        Access team analytics, assign tasks, manage evaluations, and monitor team wellness.
                    </p>
                    <a href="manager_login.php" class="login-btn">
                        <i class="fas fa-chart-line"></i>
                        <span>Login as Manager</span>
                    </a>
                </div>
                
                <div class="login-card hr">
                    <div class="card-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="card-title">HR Portal</h3>
                    <p class="card-description">
                        Manage promotions, approve accounts, view all records, and oversee leave requests.
                    </p>
                    <a href="hr_login.php" class="login-btn">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Login as HR</span>
                    </a>
                </div>
                
                <div class="login-card admin">
                    <div class="card-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <h3 class="card-title">Admin Portal</h3>
                    <p class="card-description">
                        Full system access, reports, user management, and complete administrative control.
                    </p>
                    <a href="admin_login.php" class="login-btn">
                        <i class="fas fa-cog"></i>
                        <span>Login as Admin</span>
                    </a>
                </div>
            </div>

            <div class="features-section">
                <h2 style="color: var(--gray-800); margin-bottom: 1rem; font-weight: 600;">Platform Features</h2>
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-heart-pulse"></i>
                        </div>
                        <h4 class="feature-title">Wellness Monitoring</h4>
                        <p class="feature-description">Track and improve employee wellness through comprehensive health metrics and programs.</p>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4 class="feature-title">Productivity Analytics</h4>
                        <p class="feature-description">Monitor productivity trends and identify areas for improvement with detailed analytics.</p>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4 class="feature-title">Task Management</h4>
                        <p class="feature-description">Efficient task assignment, tracking, and completion monitoring for better workflow.</p>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4 class="feature-title">Time Tracking</h4>
                        <p class="feature-description">Accurate time tracking and attendance management with detailed reporting.</p>
                    </div>
                </div>
            </div>

            <footer class="footer">
                <div class="footer-content">
                    <span>&copy; <?php echo date("Y"); ?> Employee Wellness System.</span>
                    <span>All rights reserved.</span>
                    <a href="#" class="footer-link">Privacy Policy</a>
                    <a href="#" class="footer-link">Terms of Service</a>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Add loading animation to login buttons
        document.querySelectorAll('.login-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                this.closest('.login-card').classList.add('loading');
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Loading...</span>';
                
                // Simulate loading (remove this in production)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.closest('.login-card').classList.remove('loading');
                }, 1000);
            });
        });

        // Add smooth scroll effect for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Stagger animation for login cards
            const cards = document.querySelectorAll('.login-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${0.7 + (index * 0.1)}s`;
                card.style.animation = 'fadeIn 0.6s ease-out both';
            });
        });

        // Add hover sound effect (optional)
        document.querySelectorAll('.login-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                // You can add a subtle sound effect here if desired
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>