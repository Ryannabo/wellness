<?php
session_start();
require __DIR__ . '/db.php';

if ($_SESSION['role'] !== 'manager') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'];
    $csrf_token = $_POST['csrf_token'];

    if ($csrf_token !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    // Update the task status to "completed"
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET status_id = (SELECT id FROM task_statuses WHERE value = 'completed' LIMIT 1)
        WHERE id = ?
    ");
    $stmt->execute([$task_id]);

    // Validate ownership
    $stmt = $pdo->prepare("
        SELECT t.id 
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        WHERE t.id = ? AND u.manager_id = ?
    ");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    if ($stmt->rowCount() === 0) {
        die("Unauthorized: Task does not belong to your direct report.");
    }


    header("Location: manager_dashboard.php?approved=1");
    exit;
}

?>
