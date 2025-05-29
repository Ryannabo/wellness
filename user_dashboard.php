<?php
session_start();
require __DIR__ . '/db.php';

// Check database connection
if (!$pdo) {
    die("Database connection failed!");
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle task submission for approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_for_approval'])) {
    $task_id = $_POST['task_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // CSRF token validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }
        
        // Verify task belongs to user and is in pending or in_progress status
        $verify_stmt = $pdo->prepare("
            SELECT t.*, u.name as manager_name 
            FROM tasks t 
            JOIN users u ON t.created_by = u.id 
            WHERE t.id = ? AND t.assigned_to = ? AND (t.status_id = 1 OR t.status_id = 2)
        ");
        $verify_stmt->execute([$task_id, $user_id]);
        $task = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            // Update task status to pending approval (status_id = 4)
            $update_stmt = $pdo->prepare("UPDATE tasks SET status_id = 4 WHERE id = ?");
            $update_result = $update_stmt->execute([$task_id]);
            
            if ($update_result) {
                $_SESSION['success'] = "Task '{$task['title']}' has been submitted for approval to {$task['manager_name']}.";
            } else {
                throw new Exception("Failed to submit task for approval");
            }
        } else {
            $_SESSION['error'] = "Task not found or cannot be submitted for approval.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to submit task: " . $e->getMessage();
    }
    
    header("Location: user_dashboard.php");
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get tasks assigned to the current user with proper status join
    $task_stmt = $pdo->prepare("
        SELECT t.*, u.name as assigner_name, ts.value as status_name
        FROM tasks t
        JOIN users u ON t.created_by = u.id
        JOIN task_statuses ts ON t.status_id = ts.id
        WHERE t.assigned_to = ?
        AND ts.value != 'completed'
        ORDER BY t.due_date ASC
    ");
    $task_stmt->execute([$user_id]);
    $tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attendance records
    $attendance_stmt = $pdo->prepare("
        SELECT a.*, ast.status as status_name
        FROM attendance a
        LEFT JOIN attendance_statuses ast ON a.status_id = ast.id
        WHERE a.user_id = ? 
        ORDER BY a.check_in DESC 
        LIMIT 10
    ");
    $attendance_stmt->execute([$user_id]);
    $attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get notifications
    $notif_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $notif_stmt->execute([$user_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Task statistics with proper joins
    $total_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tasks t
        JOIN task_statuses ts ON t.status_id = ts.id
        WHERE t.assigned_to = ? 
        AND ts.value != 'completed'
    ");
    $total_stmt->execute([$user_id]);
    $total_tasks = $total_stmt->fetchColumn();

    $completed_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tasks t
        JOIN task_statuses ts ON t.status_id = ts.id
        WHERE t.assigned_to = ? 
        AND ts.value = 'completed'
    ");
    $completed_stmt->execute([$user_id]);
    $completed_tasks = $completed_stmt->fetchColumn();

    $overdue_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tasks t
        JOIN task_statuses ts ON t.status_id = ts.id
        WHERE t.assigned_to = ? 
        AND ts.value != 'completed' 
        AND t.due_date < CURDATE()
    ");
    $overdue_stmt->execute([$user_id]);
    $overdue_tasks = $overdue_stmt->fetchColumn();

    // Task categories by status with proper join
    $cat_stmt = $pdo->prepare("
        SELECT ts.value as status, COUNT(*) as count 
        FROM tasks t
        JOIN task_statuses ts ON t.status_id = ts.id
        WHERE t.assigned_to = ? 
        AND ts.value != 'completed'
        GROUP BY ts.value
    ");
    $cat_stmt->execute([$user_id]);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all task statuses for dropdown
    $status_stmt = $pdo->prepare("SELECT * FROM task_statuses ORDER BY id");
    $status_stmt->execute();
    $all_statuses = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle session messages
$success = '';
$error = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | Wellness System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #8b5cf6;
            --secondary: #06b6d4;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-800);
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            z-index: -1;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }

        /* Header Styles */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            background: var(--gradient-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: var(--shadow-lg);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .brand h1 {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-menu {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .nav-item {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .nav-item:hover::before {
            left: 100%;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .notification-badge {
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
            animation: bounce 1s infinite;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-5px); }
            60% { transform: translateY(-3px); }
        }

        /* Main Container */
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Analytics Grid */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .analytic-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .analytic-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .analytic-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .analytic-card h3 {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .analytic-value {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytic-card.success .analytic-value {
            background: var(--gradient-success);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytic-card.danger .analytic-value {
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytic-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 48px;
            height: 48px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.25rem;
        }

        /* Task Grid */
        .task-grid {
            display: grid;
            gap: 1.5rem;
        }

        .task-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .task-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .task-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .status-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            margin-top: 0.25rem;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
            animation: statusPulse 2s infinite;
        }

        @keyframes statusPulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3); }
            50% { box-shadow: 0 0 0 6px rgba(255, 255, 255, 0.1); }
        }

        .status-pending { background: var(--warning); }
        .status-in_progress { background: var(--info); }
        .status-completed { background: var(--success); }
        .status-pending_approval { background: #8b5cf6; }

        .task-header h3 {
            color: var(--gray-800);
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.4;
            flex: 1;
        }

        .task-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .task-meta-item i {
            color: var(--primary);
        }

        .task-description {
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: rgba(99, 102, 241, 0.05);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
            color: var(--gray-700);
            line-height: 1.6;
        }

        .task-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .status-select {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            background: white;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            transition: var(--transition);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .update-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .update-btn:active {
            transform: translateY(0);
        }

        .submit-approval-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-approval-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .task-status-message {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: var(--border-radius);
            color: #7c3aed;
            font-weight: 500;
            margin-top: 1rem;
        }

        .approval-result {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .approval-result.approved {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
        }

        .approval-result.rejected {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }

        /* Sidebar */
        .dashboard-sidebar {
            display: grid;
            gap: 1.5rem;
            height: fit-content;
        }

        .sidebar-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .sidebar-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .sidebar-card h3 {
            color: var(--gray-800);
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .category-list {
            display: grid;
            gap: 0.75rem;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(99, 102, 241, 0.05);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .category-item:hover {
            background: rgba(99, 102, 241, 0.1);
            transform: translateX(5px);
        }

        .category-count {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--primary);
            font-size: 2rem;
        }

        .empty-state h2 {
            color: var(--gray-800);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray-500);
            font-size: 1rem;
        }

        /* Success/Error Messages */
        .success-message {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .nav-menu {
                gap: 0.25rem;
            }
            
            .nav-item {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .task-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .task-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .update-btn, .loading .submit-approval-btn {
            background: var(--gray-400);
        }

        /* Scroll Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Notification Dropdown Styles */
        .notification-container {
            position: relative;
            display: inline-block;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            width: 320px;
            margin-top: 0.5rem;
            display: none;
            overflow: hidden;
            z-index: 1000;
            animation: slideDown 0.3s ease-out;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(99, 102, 241, 0.05);
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: rgba(99, 102, 241, 0.05);
        }

        .notification-item.unread {
            background-color: rgba(99, 102, 241, 0.1);
            font-weight: 500;
            border-left: 4px solid var(--primary);
        }

        .notification-footer {
            padding: 1rem;
            text-align: center;
            background: rgba(99, 102, 241, 0.05);
        }

        .mark-all-read {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .mark-all-read:hover {
            background: var(--primary-dark);
        }

        /* Mobile responsive for dropdown */
        @media (max-width: 768px) {
            .notification-dropdown {
                width: 280px;
                right: -50px;
            }
        }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand">
                <div class="brand-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h1>Employee Dashboard</h1>
            </div>
            <nav class="nav-menu">
                <a href="wellness_evaluation.php" class="nav-item">
                    <i class="fas fa-heart"></i> Wellness
                </a>
                <a href="evaluation.php" class="nav-item">
                    <i class="fas fa-chart-line"></i> Productivity
                </a>
                <a href="attendance_dashboard.php" class="nav-item">
                    <i class="fas fa-clock"></i> Attendance
                </a>
                <a href="promotion_create.php" class="nav-item">
                    <i class="fas fa-trophy"></i> Promotion
                </a>
                <div class="notification-container">
                    <a href="#" class="nav-item" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if(!empty($notifications)): ?>
                            <span class="notification-badge"><?= count($notifications) ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <?php if(!empty($notifications)): ?>
                                <button class="mark-all-read" id="markAllRead">Mark all read</button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-list">
                            <?php if(empty($notifications)): ?>
                                <div class="notification-item">
                                    <span style="color: var(--gray-500); font-style: italic;">No notifications yet</span>
                                </div>
                            <?php else: ?>
                                <?php foreach($notifications as $notification): ?>
                                    <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" 
                                         data-id="<?= $notification['id'] ?>">
                                        <div style="font-size: 0.875rem; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-footer">
                            <a href="all_notifications.php" style="color: var(--primary); text-decoration: none; font-size: 0.875rem;">
                                View all notifications
                            </a>
                        </div>
                    </div>
                </div>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </header>

    <div class="dashboard-container">
        <main class="task-grid">
            <!-- Success/Error Messages -->
            <?php if($success): ?>
            <div class="success-message fade-in">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="error-message fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="analytics-grid">
                <div class="analytic-card fade-in">
                    <div class="analytic-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Active Tasks</h3>
                    <div class="analytic-value"><?= $total_tasks ?></div>
                </div>
                <div class="analytic-card success fade-in">
                    <div class="analytic-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Completed</h3>
                    <div class="analytic-value"><?= $completed_tasks ?></div>
                </div>
                <div class="analytic-card danger fade-in">
                    <div class="analytic-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Overdue</h3>
                    <div class="analytic-value"><?= $overdue_tasks ?></div>
                </div>
            </div>

            <?php if(empty($tasks)): ?>
                <div class="empty-state fade-in">
                    <div class="empty-state-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h2>All Caught Up!</h2>
                    <p>You currently have no assigned tasks. Great job staying on top of your work!</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $index => $task): ?>
                <div class="task-card fade-in" style="animation-delay: <?= $index * 0.1 ?>s;">
                    <div class="task-header">
                        <span class="status-indicator status-<?= htmlspecialchars($task['status_name']) ?>"></span>
                        <h3><?= htmlspecialchars($task['title']) ?></h3>
                    </div>
                    <div class="task-meta">
                        <div class="task-meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Due: <?= date('M j, Y', strtotime($task['due_date'])) ?></span>
                        </div>
                        <div class="task-meta-item">
                            <i class="fas fa-user"></i>
                            <span>Assigned by: <?= htmlspecialchars($task['assigner_name']) ?></span>
                        </div>
                        <?php if(!empty($task['department'])): ?>
                        <div class="task-meta-item">
                            <i class="fas fa-building"></i>
                            <span><?= htmlspecialchars($task['department']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if(!empty($task['description'])): ?>
                    <div class="task-description">
                        <?= nl2br(htmlspecialchars($task['description'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($task['status_name'] === 'pending' || $task['status_name'] === 'in_progress'): ?>
                        <form method="POST" class="task-actions">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <input type="hidden" name="submit_for_approval" value="1">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="submit-approval-btn" onclick="return confirm('Are you sure you want to submit this task for approval?')">
                                <i class="fas fa-paper-plane"></i> Submit Task for Approval
                            </button>
                        </form>
                    <?php elseif ($task['status_name'] === 'pending_approval'): ?>
                        <div class="task-status-message">
                            <i class="fas fa-hourglass-half"></i> Awaiting Manager Approval
                        </div>
                    <?php elseif ($task['status_name'] === 'completed'): ?>
                        <div class="approval-result approved">
                            <i class="fas fa-check-circle"></i> Task Approved & Completed
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <aside class="dashboard-sidebar">
            <div class="sidebar-card fade-in">
                <h3>
                    <i class="fas fa-chart-pie"></i>
                    Task Distribution
                </h3>
                <div class="category-list">
                    <?php if(empty($categories)): ?>
                        <div class="category-item">
                            <span>No active tasks</span>
                            <span class="category-count">0</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                        <div class="category-item">
                            <span><?= ucfirst(str_replace('_', ' ', $category['status'])) ?></span>
                            <span class="category-count"><?= $category['count'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(!empty($notifications)): ?>
            <div class="sidebar-card fade-in">
                <h3>
                    <i class="fas fa-bell"></i>
                    Recent Notifications
                </h3>
                <div class="category-list">
                    <?php foreach(array_slice($notifications, 0, 3) as $notification): ?>
                    <div class="category-item">
                        <span style="font-size: 0.875rem;"><?= htmlspecialchars(substr($notification['message'], 0, 50)) ?>...</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Smooth scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.task-card, .analytic-card, .sidebar-card').forEach(el => {
                observer.observe(el);
            });

            // Form submission loading state
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    this.classList.add('loading');
                    const btn = this.querySelector('.submit-approval-btn, .update-btn');
                    if (btn) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        btn.disabled = true;
                        
                        // Re-enable after 3 seconds as fallback
                        setTimeout(() => {
                            this.classList.remove('loading');
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }, 3000);
                    }
                });
            });

            // Auto-hide success/error messages
            const successMessage = document.querySelector('.success-message');
            const errorMessage = document.querySelector('.error-message');
            
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 300);
                }, 5000);
            }
            
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    setTimeout(() => errorMessage.remove(), 300);
                }, 7000);
            }

            // Notification dropdown functionality
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            let isDropdownOpen = false;

            if (notificationBell && notificationDropdown) {
                notificationBell.addEventListener('click', function(e) {
                    e.preventDefault();
                    isDropdownOpen = !isDropdownOpen;
                    
                    if (isDropdownOpen) {
                        notificationDropdown.classList.add('show');
                    } else {
                        notificationDropdown.classList.remove('show');
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (isDropdownOpen && !notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                        isDropdownOpen = false;
                    }
                });

                // Mark notification as read when clicked
                document.querySelectorAll('.notification-item[data-id]').forEach(item => {
                    item.addEventListener('click', function() {
                        const notificationId = this.getAttribute('data-id');
                        
                        fetch('get_notifications.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=mark_read&notification_id=${notificationId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.remove('unread');
                                
                                // Update badge count
                                const badge = document.querySelector('.notification-badge');
                                if (badge) {
                                    const currentCount = parseInt(badge.textContent);
                                    if (currentCount > 1) {
                                        badge.textContent = currentCount - 1;
                                    } else {
                                        badge.remove();
                                    }
                                }
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    });
                });

                // Mark all as read
                const markAllReadBtn = document.getElementById('markAllRead');
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', function() {
                        fetch('get_notifications.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=mark_all_read'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove all unread classes
                                document.querySelectorAll('.notification-item.unread').forEach(item => {
                                    item.classList.remove('unread');
                                });
                                
                                // Remove badge
                                const badge = document.querySelector('.notification-badge');
                                if (badge) {
                                    badge.remove();
                                }
                                
                                // Hide mark all read button
                                this.style.display = 'none';
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    });
                }
            }
        });
    </script>
</body>
</html>