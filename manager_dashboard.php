<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
            SUM(CASE WHEN status_id = '3' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status_id != '3' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
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
        WHERE t.status_id = 4
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        },
                        success: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857'
                        },
                        warning: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706'
                        },
                        danger: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .stat-card {
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .task-approval-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .task-approval-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
            border-radius: 16px 16px 0 0;
        }

        .task-approval-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: white;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .table-row {
            transition: all 0.2s ease;
        }

        .table-row:hover {
            background-color: #f8fafc;
            transform: scale(1.01);
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .notification-enter {
            animation: slideInDown 0.5s ease-out;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .dashboard-grid {
            display: grid;
            gap: 2rem;
            grid-template-columns: 1fr;
        }

        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }

        .section-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
    </style>
</head>
<body>
    <div class="min-h-screen">
        <!-- Header Section -->
        <header class="glass-effect border-b border-white/20 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center text-white font-bold text-xl">
                            <?= strtoupper(substr($user_name, 0, 1)) ?>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Manager Dashboard</h1>
                            <p class="text-gray-600 text-sm">Welcome back, <?= htmlspecialchars($user_name) ?></p>
                        </div>
                    </div>
                    
                    <nav class="flex items-center space-x-2">
                        <a href="manager_productivity.php" 
                           class="nav-link btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-chart-line mr-2"></i>Productivity
                        </a>
                        <a href="manager_wellness.php" 
                           class="nav-link btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-heart mr-2"></i>Wellness
                        </a>
                        <a href="promotion_recommendation.php" 
                           class="nav-link btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-star mr-2"></i>Promotion
                        </a>
                        <a href="create_task.php" 
                           class="nav-link btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>New Task
                        </a>
                        <a href="logout.php" 
                           class="nav-link text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-white/50">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-6 py-8">
            <!-- Notifications -->
            <?php if($success): ?>
            <div class="notification-enter mb-6 p-4 bg-success-50 border border-success-200 text-success-700 rounded-xl flex items-center">
                <div class="w-8 h-8 bg-success-500 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-check text-white text-sm"></i>
                </div>
                <span class="font-medium"><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="notification-enter mb-6 p-4 bg-danger-50 border border-danger-200 text-danger-700 rounded-xl flex items-center">
                <div class="w-8 h-8 bg-danger-500 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                </div>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php 
                $statData = [
                    'total' => ['icon' => 'fas fa-tasks', 'label' => 'Total Tasks', 'color' => 'primary', 'gradient' => '--gradient-start: #667eea; --gradient-end: #764ba2;'],
                    'completed' => ['icon' => 'fas fa-check-circle', 'label' => 'Completed', 'color' => 'success', 'gradient' => '--gradient-start: #10b981; --gradient-end: #059669;'],
                    'overdue' => ['icon' => 'fas fa-exclamation-triangle', 'label' => 'Overdue', 'color' => 'danger', 'gradient' => '--gradient-start: #ef4444; --gradient-end: #dc2626;'],
                    'team_size' => ['icon' => 'fas fa-users', 'label' => 'Team Members', 'color' => 'primary', 'gradient' => '--gradient-start: #3b82f6; --gradient-end: #1d4ed8;']
                ];
                ?>
                <?php foreach (['total', 'completed', 'overdue', 'team_size'] as $stat): ?>
                <div class="stat-card glass-effect rounded-xl p-6 card-hover" style="<?= $statData[$stat]['gradient'] ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium"><?= $statData[$stat]['label'] ?></p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">
                                <?= htmlspecialchars($statistics[$stat] ?? 0) ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-<?= $statData[$stat]['color'] ?>-500 rounded-xl flex items-center justify-center">
                            <i class="<?= $statData[$stat]['icon'] ?> text-white"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="dashboard-grid">
                <!-- Task List Section -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <div class="section-header px-6 pt-6">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-list-check mr-3 text-primary-500"></i>
                            Managed Tasks
                        </h3>
                    </div>
                    
                    <?php if (empty($tasks)): ?>
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-clipboard-list text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 text-lg font-medium">No tasks assigned yet</p>
                            <p class="text-gray-400 text-sm mt-1">Create your first task to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50/50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Task</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Assignee</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Due Date</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($tasks as $task): ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900"><?= htmlspecialchars($task['title']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                                                    <?= strtoupper(substr($task['assignee_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($task['assignee_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($task['assignee_email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <span class="text-gray-900"><?= htmlspecialchars($task['formatted_due_date']) ?></span>
                                                <?php if ($task['task_state'] === 'overdue'): ?>
                                                <span class="ml-2 text-danger-500">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <form method="POST" action="update_status.php" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="update_task" value="1" />
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>" />
                                                <select name="status_id" onchange="this.form.submit()" class="status-badge border-0 bg-transparent cursor-pointer">
                                                    <option value="1" <?= $task['status_id'] == 1 ? 'selected' : '' ?>>Pending</option>
                                                    <option value="2" <?= $task['status_id'] == 2 ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="3" <?= $task['status_id'] == 3 ? 'selected' : '' ?>>Completed</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-2">
                                                <a href="view_task.php?id=<?= $task['id'] ?>" 
                                                   class="action-btn bg-primary-100 text-primary-600 hover:bg-primary-200"
                                                   title="View Details">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </a>
                                                <a href="edit_task.php?id=<?= $task['id'] ?>" 
                                                   class="action-btn bg-success-100 text-success-600 hover:bg-success-200"
                                                   title="Edit Task">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                                <a href="delete_task.php?id=<?= $task['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                   class="action-btn bg-danger-100 text-danger-600 hover:bg-danger-200"
                                                   onclick="return confirm('Are you sure you want to delete this task?\nThis action cannot be undone.')"
                                                   title="Delete Task">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Approvals Section -->
                <div class="glass-effect rounded-xl p-6">
                    <div class="section-header">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-clock mr-3 text-warning-500"></i>
                            Pending Task Approvals
                            <?php if (!empty($pending_approvals)): ?>
                            <span class="ml-2 bg-warning-100 text-warning-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <?= count($pending_approvals) ?>
                            </span>
                            <?php endif; ?>
                        </h3>
                    </div>

                    <?php if (empty($pending_approvals)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-success-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-check-circle text-success-500 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 text-lg font-medium">All caught up!</p>
                            <p class="text-gray-400 text-sm mt-1">No completion requests at the moment</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($pending_approvals as $task): ?>
                                <div class="task-approval-card">
                                    <div class="flex items-start justify-between mb-4">
                                        <h4 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($task['title']) ?></h4>
                                        <span class="status-badge bg-warning-100 text-warning-800">
                                            <i class="fas fa-clock mr-1"></i>
                                            Pending
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-3 mb-6">
                                        <div class="flex items-center text-sm">
                                            <i class="fas fa-user text-gray-400 w-4 mr-3"></i>
                                            <span class="font-medium text-gray-900"><?= htmlspecialchars($task['employee_name']) ?></span>
                                        </div>
                                        <div class="flex items-start text-sm">
                                            <i class="fas fa-align-left text-gray-400 w-4 mr-3 mt-0.5"></i>
                                            <p class="text-gray-600 leading-relaxed"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <i class="fas fa-calendar text-gray-400 w-4 mr-3"></i>
                                            <span class="text-gray-600">Due: <?= htmlspecialchars($task['due_date']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex space-x-3">
                                        <form method="post" action="approve_task.php" class="flex-1">
                                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <button type="submit" name="approve" 
                                                    class="w-full bg-success-500 hover:bg-success-600 text-white font-medium py-2.5 px-4 rounded-lg transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-check mr-2"></i>
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="reject_task.php" class="flex-1">
                                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <button type="submit" 
                                                    class="w-full bg-danger-500 hover:bg-danger-600 text-white font-medium py-2.5 px-4 rounded-lg transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-times mr-2"></i>
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Add smooth scrolling and enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.card-hover, .task-approval-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Enhanced table row interactions
            const tableRows = document.querySelectorAll('.table-row');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8fafc';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            // Status badge styling
            const statusSelects = document.querySelectorAll('select[name="status_id"]');
            statusSelects.forEach(select => {
                updateStatusBadge(select);
                select.addEventListener('change', function() {
                    updateStatusBadge(this);
                });
            });

            function updateStatusBadge(select) {
                const value = select.value;
                select.className = 'status-badge border-0 bg-transparent cursor-pointer ';
                
                switch(value) {
                    case '1':
                        select.className += 'status-pending';
                        break;
                    case '2':
                        select.className += 'status-progress';
                        break;
                    case '3':
                        select.className += 'status-completed';
                        break;
                    default:
                        select.className += 'status-pending';
                }
            }

            // Auto-hide notifications
            const notifications = document.querySelectorAll('.notification-enter');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.transition = 'all 0.5s ease';
                    notification.style.transform = 'translateY(-20px)';
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.remove();
                    }, 500);
                }, 5000);
            });

            // Add loading states to forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const button = this.querySelector('button[type="submit"]');
                    if (button) {
                        const originalText = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                        button.disabled = true;
                        
                        // Re-enable after 3 seconds as fallback
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }, 3000);
                    }
                });
            });

            // Real-time clock
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                const dateString = now.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                // Add clock to header if it doesn't exist
                if (!document.getElementById('live-clock')) {
                    const header = document.querySelector('header .flex.items-center.justify-between > div:first-child');
                    const clockDiv = document.createElement('div');
                    clockDiv.id = 'live-clock';
                    clockDiv.className = 'text-xs text-gray-500 mt-1';
                    clockDiv.innerHTML = `${dateString} • ${timeString}`;
                    header.appendChild(clockDiv);
                } else {
                    document.getElementById('live-clock').innerHTML = `${dateString} • ${timeString}`;
                }
            }
            
            updateClock();
            setInterval(updateClock, 1000);

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Alt + N for New Task
                if (e.altKey && e.key === 'n') {
                    e.preventDefault();
                    window.location.href = 'create_task.php';
                }
                
                // Alt + D for Dashboard refresh
                if (e.altKey && e.key === 'd') {
                    e.preventDefault();
                    window.location.reload();
                }
            });

            // Add tooltips for keyboard shortcuts
            const newTaskLink = document.querySelector('a[href="create_task.php"]');
            if (newTaskLink) {
                newTaskLink.title = 'New Task (Alt+N)';
            }

            // Smooth scroll for any anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Progressive Enhancement - Add advanced features if supported
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fadeIn');
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.stat-card, .task-approval-card').forEach(el => {
                observer.observe(el);
            });
        }

        // Add CSS animation class
        const style = document.createElement('style');
        style.textContent = `
            .animate-fadeIn {
                animation: fadeIn 0.6s ease-out forwards;
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>