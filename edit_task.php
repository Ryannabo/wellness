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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'glow': 'glow 2s ease-in-out infinite alternate'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 20px rgba(59, 130, 246, 0.3)' },
                            '100%': { boxShadow: '0 0 30px rgba(59, 130, 246, 0.6)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .ripple {
            position: relative;
            overflow: hidden;
        }
        
        .ripple::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transition: width 0.6s, height 0.6s;
            transform: translate(-50%, -50%);
        }
        
        .ripple:active::before {
            width: 300px;
            height: 300px;
        }
    </style>
</head>
<body class="min-h-screen gradient-bg p-4 md:p-8">
    <!-- Background decoration -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-1/2 -right-1/2 w-96 h-96 bg-white opacity-5 rounded-full animate-pulse"></div>
        <div class="absolute -bottom-1/2 -left-1/2 w-96 h-96 bg-white opacity-5 rounded-full animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="relative z-10 max-w-2xl mx-auto animate-fade-in">
        <!-- Header -->
        <div class="text-center mb-8 animate-slide-up">
            <h1 class="text-4xl font-bold text-white mb-2">Edit Task</h1>
            <p class="text-blue-100">Update task details and assignments</p>
        </div>

        <!-- Main Form Card -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8 animate-slide-up" style="animation-delay: 0.2s;">
            <!-- Error Alert -->
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg animate-slide-up">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-red-700 font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <!-- Title Field -->
                <div class="relative group">
                    <div class="relative">
                        <input type="text" 
                               id="title" 
                               name="title" 
                               value="<?= htmlspecialchars($task['title']) ?>"
                               class="w-full px-4 py-4 bg-white/70 border-2 border-gray-200 rounded-xl text-gray-800 placeholder-transparent focus:border-indigo-500 focus:outline-none transition-all duration-300 input-focus peer"
                               placeholder="Task Title"
                               required>
                        <label for="title" 
                               class="absolute left-4 top-4 text-gray-500 pointer-events-none transition-all duration-300 peer-placeholder-shown:text-gray-400 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-600 -translate-y-6 scale-75 text-indigo-600">
                            Task Title
                        </label>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 transform scale-x-0 group-focus-within:scale-x-100 transition-transform duration-300"></div>
                </div>

                <!-- Description Field -->
                <div class="relative group">
                    <div class="relative">
                        <textarea id="description" 
                                  name="description" 
                                  rows="4"
                                  class="w-full px-4 py-4 bg-white/70 border-2 border-gray-200 rounded-xl text-gray-800 placeholder-transparent focus:border-indigo-500 focus:outline-none transition-all duration-300 input-focus peer resize-none"
                                  placeholder="Task Description"
                                  required><?= htmlspecialchars($task['description']) ?></textarea>
                        <label for="description" 
                               class="absolute left-4 top-4 text-gray-500 pointer-events-none transition-all duration-300 peer-placeholder-shown:text-gray-400 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-600 -translate-y-6 scale-75 text-indigo-600">
                            Description
                        </label>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 transform scale-x-0 group-focus-within:scale-x-100 transition-transform duration-300"></div>
                </div>

                <!-- Assign To Field -->
                <div class="relative group">
                    <div class="relative">
                        <select id="assigned_to" 
                                name="assigned_to" 
                                class="w-full px-4 py-4 bg-white/70 border-2 border-gray-200 rounded-xl text-gray-800 focus:border-indigo-500 focus:outline-none transition-all duration-300 input-focus appearance-none cursor-pointer"
                                required>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $task['assigned_to'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="absolute left-4 -top-2 bg-white/70 px-2 text-sm text-indigo-600 font-medium">
                            Assign To
                        </label>
                        <svg class="absolute right-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 transform scale-x-0 group-focus-within:scale-x-100 transition-transform duration-300"></div>
                </div>

                <!-- Due Date Field -->
                <div class="relative group">
                    <div class="relative">
                        <input type="date" 
                               id="due_date" 
                               name="due_date" 
                               value="<?= htmlspecialchars($task['due_date']) ?>"
                               class="w-full px-4 py-4 bg-white/70 border-2 border-gray-200 rounded-xl text-gray-800 focus:border-indigo-500 focus:outline-none transition-all duration-300 input-focus"
                               required>
                        <label class="absolute left-4 -top-2 bg-white/70 px-2 text-sm text-indigo-600 font-medium">
                            Due Date
                        </label>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 transform scale-x-0 group-focus-within:scale-x-100 transition-transform duration-300"></div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6">
                    <a href="manager_dashboard.php" 
                       class="flex-1 px-6 py-4 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all duration-300 transform hover:scale-105 active:scale-95 text-center">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Cancel
                        </span>
                    </a>
                    <button type="submit" 
                            class="flex-1 px-6 py-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-semibold hover:from-indigo-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl ripple">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Changes
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add floating label effects
        document.querySelectorAll('input:not([type="date"]), textarea').forEach(input => {
            if (input.value) {
                input.classList.add('has-value');
            }
            
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
                if (this.value) {
                    this.classList.add('has-value');
                } else {
                    this.classList.remove('has-value');
                }
            });
        });

        // Form submission with loading state
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            submitButton.innerHTML = `
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Saving...
                </span>
            `;
            
            submitButton.disabled = true;
        });

        // Add subtle parallax effect to background elements
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.fixed div');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.5;
                const xMove = (x - 0.5) * speed;
                const yMove = (y - 0.5) * speed;
                
                shape.style.transform = `translate(${xMove}px, ${yMove}px)`;
            });
        });
    </script>
</body>
</html>