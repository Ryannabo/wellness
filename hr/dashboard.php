<?php
session_start();
require __DIR__ . '/../db.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Get dashboard statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $total_users = $stmt->fetch()['total_users'];
    
    // Pending promotions
    $stmt = $pdo->query("SELECT COUNT(*) as pending_promotions FROM promotions WHERE status_id = 1");
    $pending_promotions = $stmt->fetch()['pending_promotions'];
    
    // Pending leave requests
    $stmt = $pdo->query("SELECT COUNT(*) as pending_leaves FROM leave_requests WHERE status_id = 1");
    $pending_leaves = $stmt->fetch()['pending_leaves'];
    
    // Pending account approvals
    $stmt = $pdo->query("SELECT COUNT(*) as pending_accounts FROM temp_users");
    $pending_accounts = $stmt->fetch()['pending_accounts'];
    
    // Recent activities
    $stmt = $pdo->query("
        SELECT u.name, 'Promotion Request' as activity, p.created_at as date
        FROM promotions p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status_id = 1
        UNION ALL
        SELECT u.name, 'Leave Request' as activity, lr.request_date as date
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.id 
        WHERE lr.status_id = 1
        ORDER BY date DESC 
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Wellness System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: var(--gradient-primary);
            min-height: 100vh;
            color: var(--gray-800);
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%);
            z-index: -1;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
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
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .dashboard-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            transition: var(--transition);
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
        }

        .stat-card.primary::before { background: var(--primary); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.info::before { background: var(--info); }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.primary { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.1); color: var(--info); }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .main-content, .sidebar-content {
            display: grid;
            gap: 1.5rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            background: rgba(99, 102, 241, 0.05);
        }

        .card-header h5 {
            color: var(--gray-800);
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
            background: rgba(99, 102, 241, 0.05);
            margin-bottom: 0.75rem;
        }

        .activity-item:hover {
            background: rgba(99, 102, 241, 0.1);
            transform: translateX(5px);
        }

        .activity-info strong {
            color: var(--gray-800);
        }

        .activity-type {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .activity-date {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .quick-actions {
            display: grid;
            gap: 0.75rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: rgba(99, 102, 241, 0.05);
            border: 2px solid rgba(99, 102, 241, 0.1);
            border-radius: var(--border-radius);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .action-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--primary);
        }

        .action-btn i {
            color: var(--primary);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-menu {
                flex-wrap: wrap;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand">
                <div class="brand-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h1>HR Dashboard</h1>
            </div>
            <nav class="nav-menu">
                <a class="nav-item active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-item" href="hr_promotions.php">
                    <i class="fas fa-arrow-up"></i> Promotions
                </a>
                <a class="nav-item" href="view_records.php">
                    <i class="fas fa-file-alt"></i> Records
                </a>
                <a class="nav-item" href="approve_accounts.php">
                    <i class="fas fa-user-check"></i> Accounts
                </a>
                <a class="nav-item" href="leave_management.php">
                    <i class="fas fa-calendar-times"></i> Leaves
                </a>
                <a class="nav-item" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h2>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-title">Total Users</div>
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-title">Pending Promotions</div>
                    <div class="stat-icon warning">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $pending_promotions; ?></div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-title">Pending Leaves</div>
                    <div class="stat-icon success">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $pending_leaves; ?></div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-header">
                    <div class="stat-title">Pending Accounts</div>
                    <div class="stat-icon info">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $pending_accounts; ?></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <div class="main-content">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activities)): ?>
                            <div class="empty-state">
                                <p>No recent activities</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <strong><?php echo htmlspecialchars($activity['name']); ?></strong>
                                        <div class="activity-type"><?php echo htmlspecialchars($activity['activity']); ?></div>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo date('M d, Y H:i', strtotime($activity['date'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-content">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="hr_promotions.php" class="action-btn">
                                <i class="fas fa-arrow-up"></i>
                                <span>Review Promotions</span>
                            </a>
                            <a href="approve_accounts.php" class="action-btn">
                                <i class="fas fa-user-check"></i>
                                <span>Approve Accounts</span>
                            </a>
                            <a href="view_records.php" class="action-btn">
                                <i class="fas fa-file-alt"></i>
                                <span>View All Records</span>
                            </a>
                            <a href="leave_management.php" class="action-btn">
                                <i class="fas fa-calendar-times"></i>
                                <span>Manage Leaves</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>