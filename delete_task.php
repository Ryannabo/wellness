<?php
session_start();
require __DIR__ . '/db.php';
require_once 'NotificationManager.php';

// Strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enhanced session validation
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Database connection check
if (!$pdo) {
    die("Database connection failed!");
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrf_token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

// Validate CSRF token
if (empty($csrf_token) || $csrf_token !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid security token. Please try again.";
    header("Location: manager_dashboard.php");
    exit();
}

// Validate task ID
if ($task_id <= 0) {
    $_SESSION['error'] = "Invalid task ID.";
    header("Location: manager_dashboard.php");
    exit();
}

try {
    // First, verify that the task belongs to this manager
    $verify_stmt = $pdo->prepare("
        SELECT id, title, assigned_to 
        FROM tasks 
        WHERE id = ? AND created_by = ?
    ");
    $verify_stmt->execute([$task_id, $user_id]);
    $task = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        throw new Exception("Task not found or you don't have permission to delete it.");
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete related notifications first (to maintain referential integrity)
    $delete_notifications_stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE task_id = ?
    ");
    $delete_notifications_stmt->execute([$task_id]);
    
    // Delete the task
    $delete_task_stmt = $pdo->prepare("
        DELETE FROM tasks 
        WHERE id = ? AND created_by = ?
    ");
    $delete_task_stmt->execute([$task_id, $user_id]);
    
    // Check if the task was actually deleted
    if ($delete_task_stmt->rowCount() === 0) {
        throw new Exception("Failed to delete task. It may have been already deleted or you don't have permission.");
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Create a notification for the employee that their task was deleted
    if (isset($task['assigned_to'])) {
        $notificationManager = new NotificationManager($pdo);
        $notificationManager->createNotification(
            $task['assigned_to'],
            'task_deleted',
            'info',
            "Task '{$task['title']}' has been deleted by your manager.",
            null
        );
    }
    
    $_SESSION['success'] = "Task '{$task['title']}' has been successfully deleted.";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "Error deleting task: " . $e->getMessage();
    error_log("Task deletion error: " . $e->getMessage());
}

// Redirect back to dashboard
header("Location: manager_dashboard.php");
exit();
?>