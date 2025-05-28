<?php
session_start();
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim username and password to remove any accidental extra spaces
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Debug: Log the login attempt
    error_log("Login attempt with username: '$username'");

    try {
        // Prepare and execute the query to fetch the user and their role
        $stmt = $pdo->prepare("
            SELECT users.*, roles.value as role_value 
            FROM users 
            JOIN roles ON users.role_id = roles.id 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Debug: Log the fetched user data
        error_log("Fetched user details: " . print_r($user, true));

        // Verify that a user was found and that the password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Debug: Password verification succeeded
            error_log("Password verified for user '$username'.");

            // Restrict login to only allowed roles (admin and manager)
            $allowed_roles = ['admin', 'manager'];
            error_log("User role found: " . $user['role_value']);
            if (!in_array($user['role_value'], $allowed_roles)) {
                throw new Exception("Access denied. Only managers and admins can log in here.");
            }

            // Regenerate session ID for security and set session variables
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_value'];
            $_SESSION['username'] = $user['username'];

            // Debug: Logging successful login with the role set
            error_log("User '$username' with role '{$_SESSION['role']}' logged in successfully.");

            // Redirect based on the user's role
            if ($_SESSION['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($_SESSION['role'] === 'manager') {
                header("Location: manager_dashboard.php");
            }
            exit();
        } else {
            throw new Exception("Invalid username or password.");
        }
    } catch (Exception $e) {
        // Debug: Log the error message
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: manager_login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager/Admin Login</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 50px; 
            background: #f0f4f8; 
        }
        form { 
            max-width: 400px; 
            margin: auto; 
            background: #fff; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        input { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border-radius: 5px; 
            border: 1px solid #ccc; 
        }
        button { 
            width: 100%; 
            padding: 12px;  
            background: #4f46e5; 
            color: #fff; 
            font-weight: bold; 
            border: none; 
            border-radius: 5px; 
        }
        .error { 
            color: red; 
            text-align: center; 
        }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Admin / Manager Login</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?= htmlspecialchars($_SESSION['error']) ?></p>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <input type="text" name="username" placeholder="Username" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
    </form>
</body>
</html>
