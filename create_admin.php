<?php
require __DIR__ . '/db.php';

// SECURITY WARNING: DELETE THIS FILE IMMEDIATELY AFTER USE

try {
    // Create admin user
    $username = 'admin3';        // Change this
    $password = '12345';  // Change this
    $email = 'admin@example.com';      // Change this
    $name = 'System Administrator';
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, name, role_id) 
                          VALUES (?, ?, ?, ?, 'admin')");
    $stmt->execute([$username, $hashed_password, $email, $name]);
    
    echo "Admin user created successfully! DELETE THIS FILE NOW!";
    
} catch (PDOException $e) {
    die("Error creating admin: " . $e->getMessage());
}