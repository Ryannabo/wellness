<?php
session_start();
require __DIR__ . '/db.php';

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validate session
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF token validation failed");
}

// Validate inputs
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action');

if (!$request_id || !in_array($action, ['Approved', 'Rejected'])) {
    die("Invalid request parameters");
}

try {
    // Update leave request
    $stmt = $pdo->prepare("
        UPDATE leave_requests 
        SET status = :status,
            manager_id = :manager_id,
            action_timestamp = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':status' => $action,
        ':manager_id' => $_SESSION['user_id'],
        ':id' => $request_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("No rows affected - invalid leave request ID");
    }
    
    $_SESSION['success'] = "Leave request $action successfully";
    
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error'] = "Database error: " . $e->getMessage();
} catch(Exception $e) {
    error_log("Approval Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

header("Location: manager_dashboard.php");
exit();
?>