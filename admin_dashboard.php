<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/db.php';

// Initialize variables
$error = '';
$success = '';
$users = [];
$tasks = [];
$roles = [];
$csrf_token = bin2hex(random_bytes(32));

if (!isset($pdo)) {
    die("Database connection failed!");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = $csrf_token;
}

try {
    // Handle POST submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Security verification failed!");
        }

        // Handle 'create' form submission from Add New User section
        if (isset($_POST['create'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $contact_number = trim($_POST['contact_number']);
            $emergency_number = trim($_POST['emergency_number']);
            $role_id = (int)$_POST['role_id'];
            $gender_id = (int)$_POST['gender_id'];
            $email = trim($_POST['email']);
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);
            $birthday = $_POST["birthday"];
            

            //gender
            if (empty($username) || empty($password) || empty($contact_number) || empty($emergency_number) || empty($role_id) || empty($gender_id)) {
                throw new Exception("All fields are required to create a new user.");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            // Basic validation
            if (empty($username) || empty($password) || empty($contact_number) || empty($emergency_number) || empty($role_id)) {
                throw new Exception("All fields are required to create a new user.");
            }

            // Optionally add: regex check for 11-digit numbers
            if (!preg_match('/^\d{11}$/', $contact_number) || !preg_match('/^\d{11}$/', $emergency_number)) {
                throw new Exception("Contact and emergency numbers must be exactly 11 digits.");
            }

            // Check for duplicate username
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Username already exists.");
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user (you need to include these fields in the DB)
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, name, address, birthday, contact_number, emergency_number, role_id, gender_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $passwordHash, $email, $name, $address,$birthday, $contact_number, $emergency_number, $role_id, $gender_id]);


            $_SESSION['success'] = "User created successfully!";
            header("Location: admin_dashboard.php");
            exit();
        }


        // Delete user
        if (isset($_POST['delete_user'])) {
            $user_id = (int)$_POST['user_id'];
            if ($user_id === (int)$_SESSION['user_id']) {
                throw new Exception("You cannot delete your own account!");
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success'] = "User deleted successfully!";
            header("Location: admin_dashboard.php");
            exit();
        }

        // Update task status
        if (isset($_POST['update_task'])) {
            $taskId = (int)$_POST['task_id'];
            $statusId = $_POST['status_id'];
            $stmt = $pdo->prepare("UPDATE tasks SET status_id = ? WHERE id = ?");
            $stmt->execute([$statusId, $taskId]);
            $_SESSION['success'] = "Task status updated successfully!";
            header("Location: admin_dashboard.php");
            exit();
        }
    }

    // Fetch users with roles joined
    $users = $pdo->query("SELECT users.*, roles.value AS role FROM users LEFT JOIN roles ON users.role_id = roles.id")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch roles for add user dropdown
    $roles = $pdo->query("SELECT * FROM roles ORDER BY roles.value ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch tasks with assignee name
    $taskQuery = $pdo->query("SELECT tasks.*, users.username as assignee_name 
                             FROM tasks JOIN users ON tasks.assigned_to = users.id");
    $tasks = $taskQuery ? $taskQuery->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

$users = $users ?? [];
$tasks = $tasks ?? [];
$roles = $roles ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
<style>
    :root {
        --primary: #6366f1;
        --secondary: #4f46e5;
        --success: #22c55e;
        --danger: #ef4444;
        --warning: #eab308;
        --light: #f8fafc;
        --dark: #0f172a;
        --background: #f1f5f9;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--background);
        min-height: 100vh;
        margin: 0;
    }

    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: var(--primary);
        color: white;
        padding: 12px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    .sidebar {
        width: 280px;
        height: 100vh;
        position: fixed;
        left: -280px;
        transition: 0.3s;
        background: white;
        box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        z-index: 1000;
    }

    .sidebar.active {
        left: 0;
    }

    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.875rem 1.5rem;
        color: #64748b;
        border-radius: 8px;
        margin: 0 1rem;
        transition: all 0.2s;
        text-decoration: none;
    }

    .nav-link:hover {
        background: #f1f5f9;
        color: var(--primary);
    }

    .nav-link.active {
        background: #eef2ff;
        color: var(--primary);
        font-weight: 500;
    }

    .main-content {
        margin-left: 0;
        padding: 2rem;
        transition: 0.3s;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .data-table {
        width: 100%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .data-table th,
    .data-table td {
        padding: 1rem 1.5rem;
        text-align: left;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-table th {
        background: #f8fafc;
        font-weight: 600;
        color: var(--dark);
    }

    .btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        border: none;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--secondary);
        transform: translateY(-1px);
    }

    .btn-danger {
        background: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }

    .form-section {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 1px 5px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .form-section h2 {
        margin-top: 0;
        margin-bottom: 1rem;
        color: var(--dark);
    }

    form label {
        display: block;
        margin-bottom: 0.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    form input[type="text"],
    form input[type="email"],
    form input[type="password"],
    form select {
        width: 100%;
        padding: 0.5rem 0.75rem;
        margin-bottom: 1rem;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 1rem;
        color: var(--dark);
        box-sizing: border-box;
    }

    form button[type="submit"] {
        background: var(--primary);
        color: white;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        border-radius: 12px;
        border: none;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    form button[type="submit"]:hover {
        background: var(--secondary);
    }

    .alert {
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        font-weight: 600;
    }

    .alert-error {
        background: var(--danger);
        color: white;
    }

    .alert-success {
        background: var(--success);
        color: white;
    }

    @media (min-width: 768px) {
        .main-content {
            margin-left: 280px;
        }

        .mobile-menu-btn {
            display: none;
        }

        .sidebar {
            left: 0 !important;
        }
    }

    @media (max-width: 767px) {
        .mobile-menu-btn {
            display: block;
        }
    }
</style>
</head>
<body>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Admin Panel</h2>
    </div>
    <nav>
        <a href="admin_dashboard.php" class="nav-link active"><i class="fas fa-users"></i> Users</a>
        
        <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <h1>Admin Dashboard</h1>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])) : ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Add User Toggle Button -->
    <button id="toggleAddUserBtn" class="btn btn-primary" style="margin-bottom: 1rem;">Add New User</button>

    <!-- Add User Form (Hidden by default) -->
    <div id="addUserForm" class="form-section" style="display:none;">
        <h2>Add New User</h2>
                <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <!-- Common fields -->
            <input type="text" name="name" required placeholder="name">
            <input type="text" name="address" required placeholder="address">
            <input type="date" name="birthday" required placeholder="birthday">
            <input type="text" name="username" required placeholder="Username">
            <input type="password" name="password" required placeholder="Password">
            <input type="email" name="email" required placeholder="Email Address">
            <input type="text" name="contact_number" required placeholder="Contact Number (11 digits)">
            <input type="text" name="emergency_number" required placeholder="Emergency Number (11 digits)">

            <!-- Gender dropdown -->
            <select name="gender_id" required>
                <option value="">Select Gender</option>
                <?php
                $genderStmt = $pdo->query("SELECT id, value FROM genders ORDER BY value ASC");
                while ($gender = $genderStmt->fetch()) {
                    echo "<option value='{$gender['id']}'>{$gender['value']}</option>";
                }
                ?>
            </select>

            <!--Roles dropdown -->

            <select name="role_id" required>
                <option value="">Select Role</option>
                <?php
                $stmt = $pdo->query("SELECT id, value FROM roles");
                while ($role = $stmt->fetch()) {
                    echo "<option value='{$role['id']}'>{$role['value']}</option>";
                }
                ?>
            </select>

            <button type="submit" name="create">Create User</button>
        </form>
    </div>

    <section>
        <h2>Users</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) === 0): ?>
                <tr><td colspan="6">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= htmlspecialchars($user['created_at']) ?></td>
                            <td>
                                <?php if ($user['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="delete_user" value="1" />
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
                                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                                <?php else: ?>
                                <em>Current User</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section style="margin-top:3rem;">
        <h2>Tasks</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Assignee</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tasks) === 0): ?>
                <tr><td colspan="6">No tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?= htmlspecialchars($task['id']) ?></td>
                            <td><?= htmlspecialchars($task['title']) ?></td>
                            <td><?= htmlspecialchars($task['assignee_name']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($task['status_id'])) ?></td>
                            <td><?= htmlspecialchars($task['due_date']) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="update_task" value="1" />
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>" />
                                    <select name="status_id" onchange="this.form.submit()">
                                        <option value="1" <?= $task['status_id'] == 1 ? 'selected' : '' ?>>Pending</option>
                                        <option value="2" <?= $task['status_id'] == 2 ? 'selected' : '' ?>>In Progress</option>
                                        <option value="3" <?= $task['status_id'] == 3 ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<script>
    const toggleBtn = document.getElementById('toggleAddUserBtn');
    const formDiv = document.getElementById('addUserForm');

    toggleBtn.addEventListener('click', () => {
        if (formDiv.style.display === 'none') {
            formDiv.style.display = 'block';
            toggleBtn.textContent = 'Hide Add User Form';
        } else {
            formDiv.style.display = 'none';
            toggleBtn.textContent = 'Add New User';
        }
    });

    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    mobileMenuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
</script>
</body>
</html>
