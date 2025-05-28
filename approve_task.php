<?php
session_start();
require __DIR__ . '/db.php';

// Check user logged in and is manager
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $task_id = $_POST['task_id'] ?? null;
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!$task_id || !$csrf_token) {
        die("Missing required data.");
    }

    // Check CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("Invalid CSRF token.");
    }

    // Validate task ownership: the task must be assigned to an employee who reports to this manager
        $stmt = $pdo->prepare("
        SELECT id 
        FROM tasks 
        WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        die("Unauthorized: Task does not belong to you.");
    }


    // Get the "completed" status id from task_statuses table
    $status_stmt = $pdo->prepare("SELECT id FROM task_statuses WHERE value = 'completed' LIMIT 1");
    $status_stmt->execute();
    $completed_status = $status_stmt->fetchColumn();

    if (!$completed_status) {
        die("Completed status not found in database.");
    }

    // Update task status to completed
    $update_stmt = $pdo->prepare("UPDATE tasks SET status_id = ? WHERE id = ?");
    $update_stmt->execute([$completed_status, $task_id]);

    // Redirect back with a success message (optional)
    $_SESSION['success'] = "Task approved successfully.";
    header("Location: manager_dashboard.php");
    exit;
}

// If not POST, deny access
die("Invalid request method.");
