<?php
session_start();
require __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure only managers/admins can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID.";
    header("Location: manager_dashboard.php");
    exit();
}

$task_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];
$error = '';
$task = null;
$employees = [];

// Fetch task & verify ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND created_by = ?");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch();

    if (!$task) {
        $_SESSION['error'] = "Task not found or access denied.";
        header("Location: manager_dashboard.php");
        exit();
    }

    // Get employee list
    $stmt = $pdo->prepare("
        SELECT users.id, users.name, users.email 
        FROM users 
        JOIN roles ON users.role_id = roles.id 
        WHERE roles.value = 'employee' AND users.status_id = 1
        ORDER BY users.name ASC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $assigned_to = (int)$_POST['assigned_to'];
        $due_date = $_POST['due_date'];

        if (!$title || !$description || !$due_date || !$assigned_to) {
            throw new Exception("All fields are required.");
        }

        // Update task
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, assigned_to = ?, due_date = ?
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$title, $description, $assigned_to, $due_date, $task_id, $user_id]);

        $_SESSION['success'] = "Task updated successfully.";
        header("Location: manager_dashboard.php");
        exit();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
} catch (PDOException $e) {
    error_log("Edit Task DB Error: " . $e->getMessage());
    $error = "A database error occurred.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Task</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-xl mx-auto bg-white p-8 rounded shadow">
        <h1 class="text-2xl font-bold mb-6">Edit Task</h1>

        <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block font-medium">Title</label>
                <input type="text" name="title" class="w-full p-2 border rounded" value="<?= htmlspecialchars($task['title']) ?>" required>
            </div>

            <div>
                <label class="block font-medium">Description</label>
                <textarea name="description" class="w-full p-2 border rounded" required><?= htmlspecialchars($task['description']) ?></textarea>
            </div>

            <div>
                <label class="block font-medium">Assign To</label>
                <select name="assigned_to" class="w-full p-2 border rounded" required>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $task['assigned_to'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-medium">Due Date</label>
                <input type="date" name="due_date" class="w-full p-2 border rounded" value="<?= htmlspecialchars($task['due_date']) ?>" required>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="manager_dashboard.php" class="px-4 py-2 bg-gray-300 rounded">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</body>
</html>
