<?php
session_start();
require __DIR__ . '/db.php';

// Ensure only managers or admins can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header("Location: login.php");
    exit();
}

// Optional: CSRF protection (uncomment if you're passing CSRF tokens in the delete URL)

if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: manager_dashboard.php");
    exit();
}


// Validate task ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID.";
    header("Location: manager_dashboard.php");
    exit();
}

$task_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Verify task ownership: only delete if the manager created it
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND created_by = ?");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch();

    if (!$task) {
        $_SESSION['error'] = "Task not found or you do not have permission to delete it.";
        header("Location: manager_dashboard.php");
        exit();
    }

    // Delete the task
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);

    $_SESSION['success'] = "Task deleted successfully.";
    header("Location: manager_dashboard.php");
    exit();

} catch (PDOException $e) {
    error_log("Delete Task Error: " . $e->getMessage());
    $_SESSION['error'] = "A database error occurred while deleting the task.";
    header("Location: manager_dashboard.php");
    exit();
}
