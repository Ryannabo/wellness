<?php
session_start();
require __DIR__ . '/db.php';

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security verification failed!";
    header("Location: manager_dashboard.php");
    exit();
}

// Verify user is logged in as manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

try {
    // Sanitize inputs
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['status'];

    // Validate status value
    $allowed_statuses = ['pending', 'in_progress', 'completed'];
    if (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['error'] = "Invalid task status";
        header("Location: manager_dashboard.php");
        exit();
    }

    // Update task status only for tasks created by this manager
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET status = :status 
        WHERE id = :task_id 
        AND created_by = :manager_id
    ");
    
    $stmt->execute([
        ':status' => $new_status,
        ':task_id' => $task_id,
        ':manager_id' => $_SESSION['user_id']
    ]);

    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Task status updated to " . ucfirst(str_replace('_', ' ', $new_status));
    } else {
        $_SESSION['error'] = "Task not found or you don't have permission";
    }

    header("Location: manager_dashboard.php");
    exit();

} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Error updating task status";
    header("Location: manager_dashboard.php");
    exit();
}