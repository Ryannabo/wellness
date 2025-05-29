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

            function logAction($employee_id, $type, $action, $conn) {
            $stmt = $conn->prepare("INSERT INTO audit_logs (employee_id, type, action) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $employee_id, $type, $action);
            $stmt->execute();
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #6366f1;
            --primary-hover: #5855eb;
            --secondary-color: #f8fafc;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--bg-primary);
            border: none;
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-md);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .mobile-menu-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar nav {
            padding: 1.5rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
        }

        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .main-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 2rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid;
            backdrop-filter: blur(10px);
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--danger-color);
            color: #dc2626;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: var(--success-color);
            color: #059669;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .form-section form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .form-group {
            grid-column: 1 / -1;
        }

        .form-section input,
        .form-section select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--bg-primary);
        }

        .form-section input:focus,
        .form-section select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-section label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-section button[type="submit"] {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }

        .form-section button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .data-table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table select {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-primary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .data-table select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 5rem 1rem 2rem;
            }

            .main-content h1 {
                font-size: 2rem;
            }

            .form-section form {
                grid-template-columns: 1fr;
            }

            .data-table {
                font-size: 0.875rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
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
            <a href="audit_logs.php" class="nav-link"><i class="fa-solid fa-clipboard"></i> Audit Logs</a>
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
                <div class="form-group">
                    <label for="birthday">Birthday</label>
                    <input type="date" name="birthday" id="birthday" required>
                </div>
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