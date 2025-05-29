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
                'assigned_to' => $_POST['assigned_to'], // keep as string
                'due_date' => $_POST['due_date']
            ];

            // Validate required fields
            if (empty($form_data['title']) || empty($form_data['description']) || empty($form_data['due_date'])) {
                throw new Exception("All fields marked with * are required");
            }

            // Get the status_id for 'pending'
            $statusStmt = $pdo->prepare("SELECT id FROM task_statuses WHERE value = 'pending'");
            $statusStmt->execute();
            $status = $statusStmt->fetch(PDO::FETCH_ASSOC);

            if (!$status) {
                throw new Exception("Status 'pending' not found in task_statuses table");
            }

            $status_id = $status['id'];

            // Check if assigning to all employees
            if ($form_data['assigned_to'] === 'all') {
                // Get all active employees
                $stmt = $pdo->prepare("
                    SELECT users.id 
                    FROM users 
                    JOIN roles ON users.role_id = roles.id 
                    WHERE roles.value = 'employee' 
                    AND users.status_id = 1
                ");
                $stmt->execute();
                $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($employees)) {
                    throw new Exception("No active employees found to assign the task");
                }

                // Insert a task for each employee
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (title, description, assigned_to, created_by, due_date, status_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($employees as $emp_id) {
                    $stmt->execute([
                        htmlspecialchars($form_data['title']),
                        htmlspecialchars($form_data['description']),
                        $emp_id,
                        $_SESSION['user_id'],
                        $form_data['due_date'],
                        $status_id
                    ]);
                }
            } else {
                // Single employee validation
                $stmt = $pdo->prepare("
                    SELECT users.id 
                    FROM users 
                    JOIN roles ON users.role_id = roles.id 
                    WHERE users.id = ? 
                    AND roles.value = 'employee'
                    AND users.status_id = 1
                ");
                $stmt->execute([(int)$form_data['assigned_to']]);
                if (!$stmt->fetch()) {
                    throw new Exception("Invalid employee selected");
                }

                // Insert task for single employee
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (title, description, assigned_to, created_by, due_date, status_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    htmlspecialchars($form_data['title']),
                    htmlspecialchars($form_data['description']),
                    (int)$form_data['assigned_to'],
                    $_SESSION['user_id'],
                    $form_data['due_date'],
                    $status_id
                ]);
            }

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
    // Get active employees
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Task - Task Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .task-form {
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 0;
            overflow: hidden;
        }
        
        .task-form.active {
            transform: translateY(0);
            opacity: 1;
            max-height: 800px;
            padding: 2rem;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(229, 231, 235, 0.8);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: rgba(255, 255, 255, 1);
        }
        
        .input-group label {
            position: absolute;
            top: -10px;
            left: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
            font-size: 14px;
            padding: 0 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: rgba(107, 114, 128, 0.1);
            color: #374151;
            padding: 12px 24px;
            border-radius: 12px;
            border: 1px solid rgba(107, 114, 128, 0.2);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(107, 114, 128, 0.2);
            transform: translateY(-1px);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .pending { 
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }
        .in_progress { 
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        .completed { 
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            margin: 0.5rem 1rem;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(4px);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .nav-item:hover::before {
            left: 100%;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
            border-left: 4px solid #059669;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .table-container {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .table-row:hover {
            background: rgba(102, 126, 234, 0.05);
            transform: translateX(4px);
        }
        
        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .floating-action:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }
        
        .header-gradient {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
        }
        
        .animate-pulse-soft {
            animation: pulse-soft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse-soft {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            margin-right: 12px;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="w-64 sidebar">
            <div class="p-6 border-b border-gray-700">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">TaskFlow</h2>
                        <p class="text-xs text-gray-300"><?= htmlspecialchars($_SESSION['role']) ?> Portal</p>
                    </div>
                </div>
            </div>
            <nav class="mt-6">
                <a href="manager_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="create_task.php" class="nav-item active">
                    <i class="fas fa-plus-circle mr-3"></i>
                    <span>Create Task</span>
                </a>
                <div class="mt-8 pt-8 border-t border-gray-700">
                    <a href="logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="max-w-6xl mx-auto">
                <!-- Alerts -->
                <?php if(!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                    <?php unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-2">Create New Task</h1>
                        <p class="text-gray-200 opacity-80">Assign tasks to your team members</p>
                    </div>
                    <button onclick="toggleForm()" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>
                        <?= empty($tasks) ? 'Create First Task' : 'New Task' ?>
                    </button>
                </div>

                <!-- Task Creation Form -->
                <div id="taskForm" class="task-form card rounded-2xl mb-8">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label>Task Title *</label>
                                <input type="text" name="title" 
                                       value="<?= htmlspecialchars($form_data['title']) ?>"
                                       placeholder="Enter task title" 
                                       required>
                            </div>
                            
                            <div class="input-group">
                                <label>Due Date *</label>
                                <input type="date" name="due_date" 
                                       value="<?= htmlspecialchars($form_data['due_date']) ?>"
                                       min="<?= date('Y-m-d') ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Description *</label>
                            <textarea name="description" rows="4" 
                                      placeholder="Describe the task details" 
                                      required><?= htmlspecialchars($form_data['description']) ?></textarea>
                        </div>

                        <div class="input-group">
                            <label>Assign to *</label>
                            <select name="assigned_to" required>
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

                        <div class="flex justify-end gap-4 pt-6">
                            <button type="button" onclick="toggleForm()" class="btn-secondary">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </button>
                            <button type="submit" name="create_task" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>
                                Create Task
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Tasks -->
                <?php if(!empty($tasks)): ?>
                <div class="card rounded-2xl table-container">
                    <div class="header-gradient p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-semibold text-white">Recent Tasks</h3>
                                <p class="text-gray-300 text-sm mt-1">Track your team's progress</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse-soft"></span>
                                <span class="text-gray-300 text-sm">Live Updates</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Task</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Assignee</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Due Date</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($tasks as $task): ?>
                                <tr class="table-row transition-all duration-200">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($task['title']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($task['description'], 0, 60)) ?><?= strlen($task['description']) > 60 ? '...' : '' ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <?php 
                                            $initials = '';
                                            $name_parts = explode(' ', $task['assignee_name']);
                                            foreach ($name_parts as $part) {
                                                $initials .= strtoupper(substr($part, 0, 1));
                                            }
                                            $colors = ['from-blue-400 to-purple-500', 'from-green-400 to-blue-500', 'from-purple-400 to-pink-500', 'from-red-400 to-yellow-500', 'from-indigo-400 to-purple-500'];
                                            $colorIndex = crc32($task['assignee_name']) % count($colors);
                                            ?>
                                            <div class="user-avatar bg-gradient-to-br <?= $colors[$colorIndex] ?>">
                                                <?= substr($initials, 0, 2) ?>
                                            </div>
                                            <span class="text-gray-900"><?= htmlspecialchars($task['assignee_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-900">
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
                                        <div class="flex items-center space-x-3">
                                            <a href="edit_task.php?id=<?= $task['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-800 transition-colors">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_task.php?id=<?= $task['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                class="text-red-600 hover:text-red-800 transition-colors"
                                                onclick="return confirm('Are you sure you want to delete this task?\nThis action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Floating Action Button -->
    <button class="floating-action" onclick="toggleForm()">
        <i class="fas fa-plus"></i>
    </button>

    <script>
        // Form toggle functionality
        function toggleForm() {
            const form = document.getElementById('taskForm');
            form.classList.toggle('active');
            
            // Animate floating action button
            const fab = document.querySelector('.floating-action');
            if (form.classList.contains('active')) {
                fab.innerHTML = '<i class="fas fa-times"></i>';
                fab.style.transform = 'scale(1.1) rotate(135deg)';
            } else {
                fab.innerHTML = '<i class="fas fa-plus"></i>';
                fab.style.transform = 'scale(1) rotate(0deg)';
            }
        }

        // Automatically show form if there's an error
        <?php if(!empty($error)): ?>
            document.getElementById('taskForm').classList.add('active');
        <?php endif; ?>

        // Set minimum date for date picker
        document.querySelector('input[type="date"]').min = new Date().toISOString().split("T")[0];

        // Add interactive feedback to form elements
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Animate table rows on load
        setTimeout(() => {
            document.querySelectorAll('.table-row').forEach((row, index) => {
                row.style.animation = `slideIn 0.4s ease-out ${index * 0.1}s both`;
            });
        }, 100);
    </script>
</body>
</html>