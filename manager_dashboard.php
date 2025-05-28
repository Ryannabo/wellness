<?php
session_start();
require __DIR__ . '/db.php';

// Strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enhanced session validation
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Database connection check
if (!$pdo) {
    die("Database connection failed!");
}

// Session data with fallbacks
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'unknown';
$user_name = $_SESSION['name'] ?? 'Manager'; // Default value if name not set

// Initialize variables
$error = '';
$success = '';
$tasks = [];
$statistics = [
    'total' => 0,
    'completed' => 0,
    'overdue' => 0,
    'team_size' => 0
];

try {
    // Fetch Manager's Tasks with JOIN
    $task_stmt = $pdo->prepare("
        SELECT t.*, 
               u.name as assignee_name,
               u.email as assignee_email,
               DATE_FORMAT(t.due_date, '%M %e, %Y') as formatted_due_date,
               CASE 
                   WHEN t.status_id = 'completed' THEN 'completed'
                   WHEN t.due_date < CURDATE() THEN 'overdue'
                   ELSE t.status_id
               END as task_state
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        WHERE t.created_by = ?
        ORDER BY t.due_date ASC
    ");
    $task_stmt->execute([$user_id]);
    $tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Statistics with parameterized query
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_id = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status_id != 'completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
            (SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'employee')) as team_size
        FROM tasks
        WHERE created_by = ?
    ");
    $stats_stmt->execute([$user_id]);
    $statistics = $stats_stmt->fetch(PDO::FETCH_ASSOC);

 $approval_stmt = $pdo->prepare("
    SELECT t.*, u.name AS employee_name
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    JOIN roles ur ON u.id = ur.id
    WHERE t.status_id = 4
      AND ur.id = 3
");
$approval_stmt->execute();
$pending_approvals = $approval_stmt->fetchAll(PDO::FETCH_ASSOC);




} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Database Error: " . $e->getMessage();  // Show actual error on page (temporary)
} catch(Exception $e) {
    error_log("General error: " . $e->getMessage());
    $error = "Error: " . $e->getMessage();          // Show actual error on page (temporary)
}


// Handle session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .pending { background-color: #f59e0b; }
        .in_progress { background-color: #3b82f6; }
        .completed { background-color: #10b981; }
        .overdue { background-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header Section -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Manager Dashboard</h1>
                    <p class="text-gray-600">Welcome, <?= htmlspecialchars($user_name) ?></p>
                </div>
                <nav class="flex items-center space-x-4">
                <a href="manager_productivity.php" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i>  Productivity Evaluation
                    </a>
                    <a href="manager_wellness.php" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i>  Wellness Evaluation
                    </a>
                
                    <a href="create_task.php" 
                       class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i> New Task
                    </a>
                    <a href="logout.php" 
                       class="text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 py-8">
            <!-- Notifications -->
            <?php if($success): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <?php $statColors = ['total' => 'gray', 'completed' => 'green', 'overdue' => 'red', 'team_size' => 'blue']; ?>
                <?php foreach (['total', 'completed', 'overdue', 'team_size'] as $stat): ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <dt class="text-sm font-medium text-gray-500"><?= ucfirst(str_replace('_', ' ', $stat)) ?></dt>
                    <dd class="mt-1 text-3xl font-semibold text-<?= $statColors[$stat] ?>-600">
                        <?= htmlspecialchars($statistics[$stat] ?? 0) ?>
                    </dd>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Task List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Managed Tasks</h3>
                </div>
                

                <?php if (empty($tasks)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-check-circle fa-2x mb-4 text-gray-300"></i>
                        <p class="text-lg">No pending leave requests</p>
                    </div>
                <?php else: ?>


                    <div class="overflow-x-auto">
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
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <?= htmlspecialchars($task['title']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= htmlspecialchars($task['assignee_name']) ?>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($task['assignee_email']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= htmlspecialchars($task['formatted_due_date']) ?>
                                        <?php if ($task['task_state'] === 'overdue'): ?>
                                        <span class="ml-2 text-red-500">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <form method="POST" action="update_status.php" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="update_task" value="1" />
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>" />
                                            <select name="status_id" onchange="this.form.submit()">
                                                <option value="1" <?= $task['status_id'] == 1 ? 'selected' : '' ?>>Pending</option>
                                                <option value="2" <?= $task['status_id'] == 2 ? 'selected' : '' ?>>In Progress</option>
                                                <option value="3" <?= $task['status_id'] == 3 ? 'selected' : '' ?>>Completed</option>
                                            </select>
                                        </form>

                                        <h2>Pending Task Approvals</h2>
                                        <?php if (empty($pending_approvals)): ?>
                                            <p>No completion requests at the moment.</p>
                                        <?php else: ?>
                                            <?php foreach ($pending_approvals as $task): ?>
                                                <div class="task-card">
                                                    <form method="POST" action="approve_task.php" style="display:inline;">
                                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-success">Approve</button>
                                                    </form>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-4">
                                            <a href="view_task.php?id=<?= $task['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-800"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_task.php?id=<?= $task['id'] ?>" 
                                               class="text-green-600 hover:text-green-800"
                                               title="Edit Task">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_task.php?id=<?= $task['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    class="text-red-600 hover:text-red-800"
                                                    onclick="return confirm('Are you sure you want to delete this task?\nThis action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h2>Pending Task Approvals</h2>
<?php if (empty($pending_approvals)): ?>
    <p>No completion requests at the moment.</p>
<?php else: ?>
    <?php foreach ($pending_approvals as $task): ?>
        <div class="task-card">...</div>
    <?php endforeach; ?>
<?php endif; ?>
<!-- Pending Approvals Section -->
<div class="mt-8 bg-white rounded-lg shadow">
    <div class="px-6 py-4 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Pending Task Approvals</h3>
    </div>
    <div class="p-6">
        <?php if (empty($pending_approvals)): ?>
            <p class="text-gray-600">No completion requests at the moment.</p>
        <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($pending_approvals as $approval): ?>
                    <li class="bg-gray-50 p-4 rounded shadow-sm flex justify-between items-center">
                        <div>
                            <p class="font-semibold"><?= htmlspecialchars($approval['title']) ?></p>
                            <p class="text-sm text-gray-500">Requested by: <?= htmlspecialchars($approval['employee_name']) ?></p>
                        </div>
                        <div class="space-x-2">
                            <form method="POST" action="approve_task.php" class="inline">
                                <input type="hidden" name="task_id" value="<?= $approval['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="approve_task.php" class="inline">
                                <input type="hidden" name="task_id" value="<?= $approval['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">
                                    Reject
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

            </div>
        </main>
    </div>
</body>
</html>