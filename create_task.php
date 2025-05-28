<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: login.php");
    exit();
}

// Database connection check
if (!isset($pdo)) {
    die("Database connection failed!");
}

// Initialize variables
$error = '';
$users = [];
$tasks = [];
$form_data = [
    'title' => '',
    'description' => '',
    'assigned_to' => '',
    'due_date' => date('Y-m-d')
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security verification failed!");
    }

    if (isset($_POST['create_task'])) {
        try {
            // Sanitize inputs
            $form_data = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description']),
                'assigned_to' => (int)$_POST['assigned_to'],
                'due_date' => $_POST['due_date']
            ];

            // Validate required fields
            if (empty($form_data['title']) || empty($form_data['description']) || empty($form_data['due_date'])) {
                throw new Exception("All fields marked with * are required");
            }

            // Validate employee exists (updated query)
            $stmt = $pdo->prepare("
                SELECT users.id 
                FROM users 
                JOIN roles ON users.role_id = roles.id 
                WHERE users.id = ? 
                AND roles.value = 'employee'
                AND users.status_id = 1
            ");
            $stmt->execute([$form_data['assigned_to']]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid employee selected");
            }

            // Get the status_id for 'pending'
            $statusStmt = $pdo->prepare("SELECT id FROM task_statuses WHERE value = 'pending'");
            $statusStmt->execute();
            $status = $statusStmt->fetch(PDO::FETCH_ASSOC);

            if (!$status) {
                throw new Exception("Status 'pending' not found in task_statuses table");
            }

            $status_id = $status['id'];


            // Insert task (fixed column order)
            $stmt = $pdo->prepare("
                INSERT INTO tasks 
                (title, description, assigned_to, created_by, due_date, status_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                htmlspecialchars($form_data['title']), 
                htmlspecialchars($form_data['description']), 
                $form_data['assigned_to'],
                $_SESSION['user_id'],
                $form_data['due_date'],
                $status_id
            ]);
            
            
            // Regenerate CSRF token and redirect
            unset($_SESSION['csrf_token']);
            $_SESSION['success'] = "Task created successfully!";
            header("Location: manager_dashboard.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch data
try {
    // Get active employees (updated query)
    $stmt = $pdo->prepare("
        SELECT users.id, users.name, users.email 
        FROM users 
        JOIN roles ON users.role_id = roles.id 
        WHERE roles.value = 'employee' 
        AND users.status_id = 1
        ORDER BY users.name ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent tasks
    $stmt = $pdo->prepare("
        SELECT tasks.*, users.name as assignee_name 
        FROM tasks 
        JOIN users ON tasks.assigned_to = users.id
        WHERE tasks.created_by = ?
        ORDER BY tasks.due_date DESC
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!-- Keep the HTML part exactly as it was -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Task - Task Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg">
            <div class="p-4 border-b">
                <h2 class="text-xl font-bold text-gray-800">Task Manager</h2>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($_SESSION['role']) ?> Portal</p>
            </div>
            <nav class="mt-4">
                <a href="manager_dashboard.php" class="block p-4 hover:bg-blue-50 text-gray-700">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="create_task.php" class="block p-4 bg-blue-50 text-blue-700">
                    <i class="fas fa-plus-circle mr-2"></i> Create Task
                </a>
                <a href="manage_users.php" class="block p-4 hover:bg-blue-50 text-gray-700">
                    <i class="fas fa-users-cog mr-2"></i> Manage Team
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
                <?php if(!empty($error)): ?>
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Create New Task</h1>
                    <button onclick="toggleForm()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all">
                        <i class="fas fa-plus mr-2"></i> <?= empty($tasks) ? 'Create First Task' : 'New Task' ?>
                    </button>
                </div>

                <!-- Task Creation Form -->
                <div id="taskForm" class="task-form bg-white rounded-lg shadow mb-8">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Task Title *</label>
                            <input type="text" name="title" 
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($form_data['title']) ?>"
                                   required
                                   placeholder="Enter task title">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Description *</label>
                            <textarea name="description" rows="4"
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   required
                                   placeholder="Describe the task details"><?= htmlspecialchars($form_data['description']) ?></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Assign to *</label>
                            <select name="assigned_to" 
                                    class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                    required>
                                <option value="" disabled <?= $form_data['assigned_to'] === '' ? 'selected' : '' ?>>Select an employee</option>
                                <option value="all" <?= $form_data['assigned_to'] === 'all' ? 'selected' : '' ?>>All Employees</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>"
                                        <?= $form_data['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Due Date *</label>
                            <input type="date" name="due_date" 
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($form_data['due_date']) ?>"
                                   min="<?= date('Y-m-d') ?>" 
                                   required>
                        </div>

                        <div class="flex justify-end gap-4 mt-6">
                            <button type="button" onclick="toggleForm()" 
                                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition-all">
                                Cancel
                            </button>
                            <button type="submit" name="create_task" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-all">
                                <i class="fas fa-save mr-2"></i> Create Task
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Tasks -->
                <?php if(!empty($tasks)): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Tasks</h3>
                    </div>
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
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><?= htmlspecialchars($task['title']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($task['assignee_name']) ?></td>
                                <td class="px-6 py-4">
                                    <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                    <?php if (strtotime($task['due_date']) < time() && $task['status_id'] !== 'completed'): ?>
                                        <span class="text-red-500 ml-2"><i class="fas fa-exclamation-triangle"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="status-badge <?= $task['status_id'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $task['status_id'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="edit_task.php?id=<?= $task['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 mr-2">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_task.php?id=<?= $task['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        class="text-red-600 hover:text-red-800"
                                        onclick="return confirm('Are you sure you want to delete this task?\nThis action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Form toggle functionality
    function toggleForm() {
        const form = document.getElementById('taskForm');
        form.classList.toggle('active');
    }

    // Automatically show form if there's an error
    <?php if(!empty($error)): ?>
        document.getElementById('taskForm').classList.add('active');
    <?php endif; ?>

    // Set minimum date for date picker
    document.querySelector('input[type="date"]').min = new Date().toISOString().split("T")[0];
    </script>
</body>
</html>