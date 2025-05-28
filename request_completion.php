<?php
session_start();
require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token.");
}

$task_id = $_POST['task_id'];
$user_id = $_SESSION['user_id'];

// Update task to 'pending_approval' (Assuming id = 4 in task_statuses)
$stmt = $pdo->prepare("UPDATE tasks SET status_id = 4 WHERE id = ? AND assigned_to = ?");
$stmt->execute([$task_id, $user_id]);

header("Location: user_dashboard.php");
exit();
?>
