<?php
session_start();
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $task_id = $_POST['task_id'] ?? null;

    if (!$task_id || !is_numeric($task_id)) {
        die("Invalid task ID.");
    }

    try {
        // Optional: Ensure status_id for "rejected" exists
        $status_stmt = $pdo->prepare("SELECT id FROM task_statuses WHERE value = 'rejected' LIMIT 1");
        $status_stmt->execute();
        $rejected_status = $status_stmt->fetchColumn();

        if (!$rejected_status) {
            die("Rejected status not found in database.");
        }

        // Update the task's status to rejected
        $update_stmt = $pdo->prepare("UPDATE tasks SET status_id = ? WHERE id = ?");
        $update_stmt->execute([$rejected_status, $task_id]);

        $_SESSION['success'] = "Task rejected successfully.";
        header("Location: manager_dashboard.php");
        exit();

    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        die("Database error.");
    }

} else {
    die("Invalid request method.");
}
