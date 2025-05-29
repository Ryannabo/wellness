<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Initialize promotion manager
require_once __DIR__ . '/../PromotionManager.php';
$promotionManager = new PromotionManager($pdo);

// Get data for the dashboard
$eligible_employees = $promotionManager->getEligibleEmployees();
$pending_promotions = $promotionManager->getPendingPromotions();
$all_promotions = $promotionManager->getAllPromotions();
$stats = $promotionManager->getPromotionStats();

// Handle success/error messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions Management - Wellness System</title>
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

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
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

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-right: 0.5rem;
            transition: var(--transition);
        }

        .nav-tabs .nav-link.active {
            background: rgba(255, 255, 255, 0.95);
            color: var(--gray-800);
        }

        .tab-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 2rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .table th {
            background: rgba(99, 102, 241, 0.05);
            font-weight: 600;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge.bg-warning { background: var(--warning); color: white; }
        .badge.bg-success { background: var(--success); color: white; }
        .badge.bg-danger { background: var(--danger); color: white; }

        .promotion-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            background: white;
            border-radius: var(--border-radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-family: inherit;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .mb-3 {
            margin-bottom: 1rem;
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
                    <i class="fas fa-arrow-up"></i>
                </div>
                <h1>Promotions Management</h1>
            </div>
            <nav class="nav-menu">
                <a class="nav-item" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-item active" href="hr_promotions.php">
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="color: white; font-size: 2rem; font-weight: 700;">Promotions Overview</h2>
            <a href="create_promotion.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Promotion Candidate
            </a>
        </div>

        <!-- Display messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <?php if ($stats): ?>
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-title">Total Promotions</div>
                    <div class="stat-icon primary">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['total_promotions']; ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-title">Pending</div>
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['pending_count']; ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-title">Approved</div>
                    <div class="stat-icon success">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['approved_count']; ?></div>
            </div>
            <div class="stat-card info">
                <div class="stat-header">
                    <div class="stat-title">Rejected</div>
                    <div class="stat-icon info">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['rejected_count']; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav-tabs">
            <li>
                <a class="nav-link active" href="#" onclick="showTab('pending')">
                    Pending Promotions (<?php echo count($pending_promotions); ?>)
                </a>
            </li>
            <li>
                <a class="nav-link" href="#" onclick="showTab('eligible')">
                    Eligible Employees (<?php echo count($eligible_employees); ?>)
                </a>
            </li>
            <li>
                <a class="nav-link" href="#" onclick="showTab('all')">
                    All Promotions (<?php echo count($all_promotions); ?>)
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Pending Promotions Tab -->
            <div id="pending-tab" class="tab-pane">
                <?php if (empty($pending_promotions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No pending promotions at this time.
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_promotions as $promotion): ?>
                    <div class="promotion-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h5 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($promotion['employee_name']); ?></h5>
                                <p style="margin-bottom: 0.5rem;">
                                    <strong>Username:</strong> <?php echo htmlspecialchars($promotion['username']); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($promotion['email']); ?><br>
                                    <strong>Current Position:</strong> <?php echo htmlspecialchars($promotion['current_position']); ?><br>
                                    <strong>Requested:</strong> <?php echo date('M d, Y', strtotime($promotion['created_at'])); ?>
                                </p>
                                <?php if ($promotion['evaluation_comments']): ?>
                                <p><strong>Comments:</strong> <?php echo htmlspecialchars($promotion['evaluation_comments']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge bg-warning" style="margin-bottom: 1rem; display: block;">Pending Review</span>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button class="btn btn-success btn-sm" onclick="showApprovalModal(<?php echo $promotion['id']; ?>, '<?php echo htmlspecialchars($promotion['employee_name']); ?>')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="showRejectionModal(<?php echo $promotion['id']; ?>, '<?php echo htmlspecialchars($promotion['employee_name']); ?>')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Eligible Employees Tab -->
            <div id="eligible-tab" class="tab-pane" style="display: none;">
                <?php if (empty($eligible_employees)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No eligible employees found.
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eligible_employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['username']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                <td>
                                    <?php if ($employee['promotion_status'] == 'Has Pending Promotion'): ?>
                                        <span class="badge bg-warning">Has Pending Promotion</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Eligible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($employee['promotion_status'] == 'Eligible'): ?>
                                        <a href="create_promotion.php?user_id=<?php echo $employee['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Create Promotion
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--gray-500);">Promotion Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- All Promotions Tab -->
            <div id="all-tab" class="tab-pane" style="display: none;">
                <?php if (empty($all_promotions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No promotion records found.
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>Promotion Date</th>
                                <th>Created</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_promotions as $promotion): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($promotion['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($promotion['current_position']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = '';
                                    switch($promotion['status_id']) {
                                        case 1: $badge_class = 'bg-warning'; break;
                                        case 2: $badge_class = 'bg-success'; break;
                                        case 3: $badge_class = 'bg-danger'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($promotion['status']); ?></span>
                                </td>
                                <td>
                                    <?php echo $promotion['promotion_date'] ? date('M d, Y', strtotime($promotion['promotion_date'])) : '-'; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($promotion['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($promotion['evaluation_comments']) ?: '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div class="modal" id="approvalModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h5>Approve Promotion</h5>
                <button type="button" onclick="closeModal('approvalModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <form action="process_promotion.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="promotion_id" id="approve_promotion_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    
                    <p>Are you sure you want to approve the promotion for <strong id="approve_employee_name"></strong>?</p>
                    <p style="color: var(--info); margin: 1rem 0;"><i class="fas fa-info-circle"></i> This will promote the employee from Employee to Manager role.</p>
                    
                    <div class="mb-3">
                        <label for="approve_comments" class="form-label">Evaluation Comments (Optional)</label>
                        <textarea class="form-control" id="approve_comments" name="evaluation_comments" rows="3" placeholder="Add any evaluation comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('approvalModal')" style="background: var(--gray-200); color: var(--gray-700);">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Promotion</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal" id="rejectionModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h5>Reject Promotion</h5>
                <button type="button" onclick="closeModal('rejectionModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <form action="process_promotion.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="promotion_id" id="reject_promotion_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    
                    <p>Are you sure you want to reject the promotion for <strong id="reject_employee_name"></strong>?</p>
                    
                    <div class="mb-3">
                        <label for="reject_comments" class="form-label">Reason for Rejection <span style="color: var(--danger);">*</span></label>
                        <textarea class="form-control" id="reject_comments" name="evaluation_comments" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('rejectionModal')" style="background: var(--gray-200); color: var(--gray-700);">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Promotion</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Add active class to clicked nav link
            event.target.classList.add('active');
        }

        function showApprovalModal(promotionId, employeeName) {
            document.getElementById('approve_promotion_id').value = promotionId;
            document.getElementById('approve_employee_name').textContent = employeeName;
            document.getElementById('approvalModal').classList.add('show');
        }

        function showRejectionModal(promotionId, employeeName) {
            document.getElementById('reject_promotion_id').value = promotionId;
            document.getElementById('reject_employee_name').textContent = employeeName;
            document.getElementById('rejectionModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>