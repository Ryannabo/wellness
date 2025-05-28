<?php
session_start();
require __DIR__ . '/db.php';

// Only allow access if logged in and role is manager or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid task ID.");
}

$task_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT tasks.*, 
               u1.name AS assignee_name, 
               u2.name AS creator_name,
               s.value AS status_name 
        FROM tasks 
        JOIN users u1 ON tasks.assigned_to = u1.id
        JOIN users u2 ON tasks.created_by = u2.id
        JOIN task_statuses s ON tasks.status_id = s.id
        WHERE tasks.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        die("Task not found.");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching the task.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Task</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex justify-center items-center p-6">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">Task Details</h1>

            <div class="mb-4">
                <strong class="text-gray-700">Title:</strong>
                <p class="text-gray-900"><?= htmlspecialchars($task['title']) ?></p>
            </div>

            <div class="mb-4">
                <strong class="text-gray-700">Description:</strong>
                <p class="text-gray-900 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
            </div>

            <div class="mb-4">
                <strong class="text-gray-700">Assigned To:</strong>
                <p class="text-gray-900"><?= htmlspecialchars($task['assignee_name']) ?></p>
            </div>

            <div class="mb-4">
                <strong class="text-gray-700">Created By:</strong>
                <p class="text-gray-900"><?= htmlspecialchars($task['creator_name']) ?></p>
            </div>

            <div class="mb-4">
                <strong class="text-gray-700">Due Date:</strong>
                <p class="text-gray-900"><?= htmlspecialchars(date('F j, Y', strtotime($task['due_date']))) ?></p>
            </div>

            <div class="mb-4">
                <strong class="text-gray-700">Status:</strong>
                <span class="inline-block px-3 py-1 rounded-full bg-blue-100 text-blue-700">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $task['status_name']))) ?>
                </span>
            </div>

            <div class="mt-6">
                <a href="manager_dashboard.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
