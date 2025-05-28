<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/db.php';

// Generate CSRF token immediately
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Strict manager authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Database connection check
if (!isset($pdo)) {
    die("Database connection failed!");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security verification failed!");
    }

    if (isset($_POST['create_task'])) {
        try {
            $title = htmlspecialchars($_POST['title']);
            $description = htmlspecialchars($_POST['description']);
            $assigned_to = (int)$_POST['assigned_to'];
            $due_date = $_POST['due_date'];
            $assigned_by = $_SESSION['user_id'];

            // Validate manager exists
            $user_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'manager'");
            $user_check->execute([$assigned_by]);
            if (!$user_check->fetch()) {
                throw new Exception("Invalid manager credentials!");
            }

            // Insert task
            $stmt = $pdo->prepare("INSERT INTO tasks 
                (title, description, assigned_to, due_date, status_id, assigned_by) 
                VALUES (?, ?, ?, ?, 'pending', ?)");
                
            $stmt->execute([$title, $description, $assigned_to, $due_date, $assigned_by]);
            
            $_SESSION['success'] = "Task created successfully!";
            header("Location: task_manager.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch manager-specific data
try {
    $manager_id = $_SESSION['user_id'];
    
    // Get manager's team members
    $users = $pdo->prepare("SELECT id, username FROM users WHERE manager_id = ?");
    $users->execute([$manager_id]);
    $users = $users->fetchAll(PDO::FETCH_ASSOC);
    
    // Get manager's tasks
    $tasks = $pdo->prepare("SELECT tasks.*, users.username as assignee_name 
                          FROM tasks 
                          JOIN users ON tasks.assigned_to = users.id
                          WHERE assigned_by = ?");
    $tasks->execute([$manager_id]);
    $tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Task Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .task-form {
            transition: all 0.3s ease;
            max-height: 0;
            overflow: hidden;
        }
        .task-form.active {
            max-height: 1000px;
            padding: 2rem;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .pending { background-color: #fef3c7; color: #d97706; }
        .in_progress { background-color: #dbeafe; color: #1d4ed8; }
        .completed { background-color: #dcfce7; color: #15803d; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Manager Sidebar -->
        <aside class="w-64 bg-white shadow-lg">
            <div class="p-4">
                <h2 class="text-xl font-bold text-gray-800">Manager Panel</h2>
            </div>
            <nav class="mt-4">
                <a href="manager.php" class="block p-4 hover:bg-blue-50 text-gray-700">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="task_manager.php" class="block p-4 bg-blue-50 text-blue-700">
                    <i class="fas fa-tasks mr-2"></i> Tasks
                </a>
                <a href="logout.php" class="block p-4 hover:bg-blue-50 text-gray-700">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Alerts -->
                <?php if(isset($error)): ?>
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                    <?= $_SESSION['success'] ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Task Management</h1>
                    <button onclick="toggleForm()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i> New Task
                    </button>
                </div>

                <!-- Task Creation Form -->
                <div id="taskForm" class="task-form bg-white rounded-lg shadow mb-8">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Task Title</label>
                            <input type="text" name="title" class="w-full p-2 border rounded" required>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Description</label>
                            <textarea name="description" class="w-full p-2 border rounded" rows="3" required></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Assign to Team Member</label>
                            <select name="assigned_to" class="w-full p-2 border rounded" required>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Due Date</label>
                            <input type="date" name="due_date" class="w-full p-2 border rounded" required>
                        </div>

                        <div class="flex justify-end gap-4">
                            <button type="button" onclick="toggleForm()" 
                                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                                Cancel
                            </button>
                            <button type="submit" name="create_task"                        
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                Create Task
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Task List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-gray-600">Title</th>
                                <th class="px-6 py-3 text-left text-gray-600">Assignee</th>
                                <th class="px-6 py-3 text-left text-gray-600">Due Date</th>
                                <th class="px-6 py-3 text-left text-gray-600">Status</th>
                                <th class="px-6 py-3 text-left text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td class="px-6 py-4"><?= htmlspecialchars($task['title']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($task['assignee_name']) ?></td>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($task['due_date'])) ?></td>
                                <td class="px-6 py-4">
                                    <span class="status-badge <?= $task['status_id'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $task['status_id'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="edit_task.php?id=<?= $task['id'] ?>&manager=1" 
                                       class="text-blue-600 hover:text-blue-800 mr-2">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="delete_task.php" method="POST" class="inline">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
    function toggleForm() {
        const form = document.getElementById('taskForm');
        form.classList.toggle('active');
    }
    </script>
</body>
</html>