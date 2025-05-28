<?php
// Add to config.php
date_default_timezone_set('Asia/Manila'); // Set to your timezone
define('BASE_URL', 'http://localhost/new_project/reset_password.php');


// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wellness');
define('DB_USER', 'root');
define('DB_PASS', '');

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'villanuevagerry213@gmail.com');
define('SMTP_PASS', 'dqcg kuxh szuk iuiu');
define('SITE_EMAIL', 'WELLNESS MANAGEMENT@yourdomain.com');
define('SITE_NAME', 'Wellness System');

// Initialize database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}