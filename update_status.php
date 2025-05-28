<?php
session_start();
require __DIR__ . '/db.php';

// CSRF token check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token.");
}

// Input validation
if (!isset($_POST['task_id'], $_POST['status_id'])) {
    die("Missing data.");
}

$task_id = intval($_POST['task_id']);
$status_id = intval($_POST['status_id']);

// Only managers can update
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    die("Unauthorized.");
}

try {
    $stmt = $pdo->prepare("UPDATE tasks SET status_id = ? WHERE id = ?");
    $stmt->execute([$status_id, $task_id]);

    $_SESSION['success'] = "Task status updated.";
} catch (PDOException $e) {
    error_log("Failed to update status: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update task.";
}

header("Location: manager_dashboard.php");
exit();
