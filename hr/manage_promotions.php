<?php
session_start();
require __DIR__ . '/../db.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Handle promotion approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $promotion_id = $_POST['promotion_id'];
    $action = $_POST['action'];
    $comments = $_POST['comments'] ?? '';
    
    try {
        if ($action === 'approve') {
            $status_id = 2; // Approved
            $promotion_date = date('Y-m-d');
        } else {
            $status_id = 3; // Rejected
            $promotion_date = null;
        }
        
        $stmt = $pdo->prepare("
            UPDATE promotions 
            SET status_id = ?, promotion_date = ?, evaluation_comments = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status_id, $promotion_date, $comments, $promotion_id]);
        
        $success = "Promotion " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
    } catch (PDOException $e) {
        $error = "Error updating promotion: " . $e->getMessage();
    }
}

// Get all promotions with user details - Fixed SQL query with correct column names
try {
    $stmt = $pdo->query("
        SELECT p.*, u.name, u.email, ps.value as status_name, r.value as user_role,
               p.current_position, p.created_at, p.updated_at
        FROM promotions p
        JOIN users u ON p.user_id = u.id
        JOIN promotion_statuses ps ON p.status_id = ps.id
        JOIN roles r ON u.role_id = r.id
        ORDER BY p.created_at DESC
    ");
    $promotions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Promotions - HR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
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
            background: var(--gradient-primary);
            min-height: 100vh;
            color: var(--gray-800);
            overflow-x: hidden;
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
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

        .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table th {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-secondary {
            background: var(--gray-500);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .status-approved { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-rejected { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
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
            box-shadow: var(--shadow-xl);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
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

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .table-responsive {
                font-size: 0.875rem;
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
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand">
                <div class="brand-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h1>HR Panel</h1>
            </div>
            <nav class="nav-menu">
                <a class="nav-item" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-item active" href="manage_promotions.php">
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
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-arrow-up"></i> Manage Promotions
            </h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Promotions Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> All Promotion Requests (<?php echo count($promotions); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($promotions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <h3>No Promotion Requests</h3>
                        <p>There are currently no promotion requests to review.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employee</th>
                                    <th>Current Position</th>
                                    <th>Current Role</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotions as $promotion): ?>
                                    <tr>
                                        <td><?php echo $promotion['id']; ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($promotion['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($promotion['email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($promotion['current_position'] ?? 'Not specified'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($promotion['user_role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($promotion['status_name']); ?>">
                                                <?php echo $promotion['status_name']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $promotion['created_at'] ? date('M d, Y', strtotime($promotion['created_at'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php if ($promotion['status_name'] === 'Pending'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="showPromotionModal(<?php echo $promotion['id']; ?>, 'approve', '<?php echo htmlspecialchars($promotion['name']); ?>')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="showPromotionModal(<?php echo $promotion['id']; ?>, 'reject', '<?php echo htmlspecialchars($promotion['name']); ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <?php echo $promotion['status_name']; ?>
                                                    <?php if ($promotion['promotion_date']): ?>
                                                        <br><?php echo date('M d, Y', strtotime($promotion['promotion_date'])); ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Promotion Action Modal -->
    <div class="modal" id="promotionModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Promotion Action</h5>
                        <button type="button" class="btn-close" onclick="closeModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="promotion_id" id="promotionId">
                        <input type="hidden" name="action" id="actionType">
                        
                        <div class="text-center mb-4">
                            <i id="modalIcon" class="fas fa-3x mb-3"></i>
                            <p id="modalMessage" class="lead"></p>
                        </div>
                        
                        <div class="form-group">
                            <label for="comments" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" name="comments" id="comments" rows="3" 
                                    placeholder="Add any comments about this decision..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showPromotionModal(promotionId, action, employeeName) {
            document.getElementById('promotionId').value = promotionId;
            document.getElementById('actionType').value = action;
            
            const modal = document.getElementById('promotionModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const icon = document.getElementById('modalIcon');
            const confirmBtn = document.getElementById('confirmBtn');
            
            if (action === 'approve') {
                title.textContent = 'Approve Promotion';
                message.textContent = `Are you sure you want to approve the promotion for ${employeeName}?`;
                icon.className = 'fas fa-check-circle fa-3x mb-3 text-success';
                confirmBtn.textContent = 'Approve';
                confirmBtn.className = 'btn btn-success';
            } else {
                title.textContent = 'Reject Promotion';
                message.textContent = `Are you sure you want to reject the promotion for ${employeeName}?`;
                icon.className = 'fas fa-times-circle fa-3x mb-3 text-danger';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn btn-danger';
            }
            
            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('promotionModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('promotionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.1}s`;
                row.style.animation = 'fadeInUp 0.6s ease-out both';
            });
        });

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>