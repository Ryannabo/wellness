<?php session_start();

// Redirect logged-in users to their respective pages
if (isset($_SESSION['user_id'])) {
        header('Location: admin_dashboard.php');
        exit;
    }
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Wellness And Productivity Monitoring System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            text-align: center;
            padding: 2rem;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
        }
        
        header {
            margin-bottom: 2.5rem;
        }
        
        header h2 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .description {
            color: #7f8c8d;
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto 2rem;
            line-height: 1.6;
        }
        
        .login-options {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .login-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            width: 300px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .login-card h3 {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
        }
        
        .login-btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 0.8rem 1.8rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
        }
        
        .login-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .manager-btn {
            background-color: var(--dark-color);
        }
        
        .manager-btn:hover {
            background-color: #34495e;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin-bottom: 1.5rem;
        }
        
        footer {
            margin-top: 3rem;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .login-options {
                flex-direction: column;
                align-items: center;
            }
            
            header h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- You can add a logo here -->
        <!-- <img src="logo.png" alt="Company Logo" class="logo"> -->
        
        <header>
            <h2>Employee Wellness And Productivity Monitoring System</h2>
            <p class="description">
                A comprehensive platform to monitor and enhance employee wellness and productivity through 
                data-driven insights and wellness programs.
            </p>
        </header>
        
        <div class="login-options">
            <div class="login-card">
                <h3>Employee Login</h3>
                <p>Access your personal dashboard and wellness tools</p>
                <a href="login.php" class="login-btn">Login as Employee</a>
            </div>
            
            <div class="login-card">
                <h3>Manager Login</h3>
                <p>Access team analytics and management tools</p>
                <a href="manager_login.php" class="login-btn manager-btn">Login as Manager</a>
            </div>
        </div>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Employee Wellness System. All rights reserved.
        </footer>
    </div>
</body>
</html>