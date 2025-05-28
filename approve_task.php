<?php
session_start();
require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'];
    $action = $_POST['action'];

    // Set new status based on action
    $new_status_id = $action === 'approve' ? 3 : 5; // 3 = completed, 5 = rejected

    $stmt = $pdo->prepare("UPDATE tasks SET status_id = ? WHERE id = ?");
    $stmt->execute([$new_status_id, $task_id]);

    header("Location: manager_dashboard.php");
    exit();
}
?>
