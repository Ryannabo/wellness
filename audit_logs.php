<?php
session_start();
require __DIR__ . '\db.php';


// Get filter parameters
$record_type = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$user_filter = $_GET['user'] ?? '';

try {
    // Get all users for filter dropdown
    $stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
    $all_users = $stmt->fetchAll();
    
    // Build query based on record type
    $records = [];
    
    if ($record_type === 'all' || $record_type === 'attendance') {
        // Get attendance records
        $query = "
            SELECT 'Attendance' as record_type, u.name, a.check_in, a.check_out, 
                   a.duration, COALESCE(ast.status, 'Present') as status, a.check_in as date_field
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN attendance_statuses ast ON a.status_id = ast.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($date_from) {
            $query .= " AND DATE(a.check_in) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND DATE(a.check_in) <= ?";
            $params[] = $date_to;
        }
        if ($user_filter) {
            $query .= " AND u.id = ?";
            $params[] = $user_filter;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $attendance_records = $stmt->fetchAll();
        $records = array_merge($records, $attendance_records);
    }
    
    if ($record_type === 'all' || $record_type === 'tasks') {
        // Get task records
        $query = "
            SELECT 'Task' as record_type, u.name, t.title, t.description, 
                   ts.value as status, t.due_date, t.created_at as date_field,
                   creator.name as created_by_name
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            JOIN users creator ON t.created_by = creator.id
            JOIN task_statuses ts ON t.status_id = ts.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($date_from) {
            $query .= " AND DATE(t.created_at) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND DATE(t.created_at) <= ?";
            $params[] = $date_to;
        }
        if ($user_filter) {
            $query .= " AND u.id = ?";
            $params[] = $user_filter;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $task_records = $stmt->fetchAll();
        $records = array_merge($records, $task_records);
    }
    
    if ($record_type === 'all' || $record_type === 'leaves') {
        // Get leave records
        $query = "
            SELECT 'Leave' as record_type, u.name, lt.value as leave_type, 
                   lr.leave_start_date, lr.leave_end_date, lr.leave_reason,
                   lrs.value as status, lr.request_date as date_field
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            JOIN leave_request_statuses lrs ON lr.status_id = lrs.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($date_from) {
            $query .= " AND DATE(lr.request_date) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND DATE(lr.request_date) <= ?";
            $params[] = $date_to;
        }
        if ($user_filter) {
            $query .= " AND u.id = ?";
            $params[] = $user_filter;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $leave_records = $stmt->fetchAll();
        $records = array_merge($records, $leave_records);
    }
    
    if ($record_type === 'all' || $record_type === 'evaluations') {
        // Get evaluation records
        $query = "
            SELECT 'Evaluation' as record_type, evaluator.name as evaluator_name,
                   target.name as target_name, e.evaluation_type, e.criteria,
                   e.created_at as date_field
            FROM evaluations e
            JOIN users evaluator ON e.evaluator_id = evaluator.id
            LEFT JOIN users target ON e.target_id = target.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($date_from) {
            $query .= " AND DATE(e.created_at) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND DATE(e.created_at) <= ?";
            $params[] = $date_to;
        }
        if ($user_filter) {
            $query .= " AND (e.evaluator_id = ? OR e.target_id = ?)";
            $params[] = $user_filter;
            $params[] = $user_filter;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $evaluation_records = $stmt->fetchAll();
        $records = array_merge($records, $evaluation_records);
    }
    
    // Sort records by date
    usort($records, function($a, $b) {
        return strtotime($b['date_field']) - strtotime($a['date_field']);
    });
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Records - HR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
/* HR System Audit Log Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: #333;
    line-height: 1.6;
}

.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    min-height: 100vh;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    background: rgba(255, 255, 255, 0.95);
    padding: 1.5rem 2rem;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-title i {
    color: #667eea;
    font-size: 2rem;
}

.back-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: white;
    text-decoration: none;
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.alert-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

/* Statistics Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    padding: 2rem;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
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
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1rem;
    color: #718096;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Card Styles */
.card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #f7fafc, #edf2f7);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
}

.card-header h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.card-body {
    padding: 2rem;
}

/* Form Styles */
.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: #4a5568;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control, .form-select {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Button Styles */
.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
}

.btn-outline-secondary {
    background: transparent;
    color: #718096;
    border: 2px solid #e2e8f0;
}

.btn-outline-secondary:hover {
    background: #f7fafc;
    color: #4a5568;
    text-decoration: none;
}

/* Records Grid */
.records-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.record-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
}

.record-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, transparent, rgba(102, 126, 234, 0.02));
    pointer-events: none;
}

.record-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

.record-card.attendance {
    border-left-color: #4299e1;
}

.record-card.task {
    border-left-color: #48bb78;
}

.record-card.leave {
    border-left-color: #ed8936;
}

.record-card.evaluation {
    border-left-color: #9f7aea;
}

/* Record Card Elements */
.record-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.record-type-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.record-type-badge.attendance {
    background: rgba(66, 153, 225, 0.1);
    color: #2b6cb0;
}

.record-type-badge.task {
    background: rgba(72, 187, 120, 0.1);
    color: #2f855a;
}

.record-type-badge.leave {
    background: rgba(237, 137, 54, 0.1);
    color: #c05621;
}

.record-type-badge.evaluation {
    background: rgba(159, 122, 234, 0.1);
    color: #7c3aed;
}

.record-date {
    font-size: 0.85rem;
    color: #718096;
    font-weight: 500;
}

.record-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.record-details {
    margin-bottom: 1rem;
}

.record-detail {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #4a5568;
}

.record-detail i {
    width: 16px;
    color: #718096;
}

/* Status Badges */
.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.info {
    background: rgba(66, 153, 225, 0.1);
    color: #2b6cb0;
}

.status-badge.success {
    background: rgba(72, 187, 120, 0.1);
    color: #2f855a;
}

.status-badge.warning {
    background: rgba(237, 137, 54, 0.1);
    color: #c05621;
}

.status-badge.secondary {
    background: rgba(113, 128, 150, 0.1);
    color: #4a5568;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    font-size: 4rem;
    color: #cbd5e0;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: #4a5568;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #718096;
    font-size: 1rem;
    max-width: 400px;
    margin: 0 auto;
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Fade In Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.record-card {
    animation: fadeInUp 0.6s ease forwards;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .filter-form {
        grid-template-columns: 1fr;
    }
    
    .records-grid {
        grid-template-columns: 1fr;
    }
    
    .card-body, .card-header {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .record-card {
        padding: 1rem;
    }
    
    .card-body, .card-header {
        padding: 1rem;
    }
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.5);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(102, 126, 234, 0.7);
}

/* Print styles */
@media print {
    body {
        background: white;
        color: black;
    }
    
    .dashboard-container {
        max-width: none;
        padding: 0;
    }
    
    .back-btn, .btn {
        display: none;
    }
    
    .card, .stat-card, .record-card {
        box-shadow: none;
        border: 1px solid #e2e8f0;
    }
    
    .page-header {
        background: white;
        border-bottom: 2px solid #2d3748;
    }
}
    </style>
</head>
<body>

    <div class="dashboard-container">
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-file-alt"></i> Audit Log
            </h2>
            <a href="admin_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($records); ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($records, fn($r) => $r['record_type'] === 'Attendance')); ?></div>
                <div class="stat-label">Attendance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($records, fn($r) => $r['record_type'] === 'Task')); ?></div>
                <div class="stat-label">Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($records, fn($r) => $r['record_type'] === 'Leave')); ?></div>
                <div class="stat-label">Leaves</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Advanced Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="type" class="form-label">Record Type</label>
                        <select class="form-select" name="type" id="type">
                            <option value="all" <?php echo $record_type === 'all' ? 'selected' : ''; ?>>All Records</option>
                            <option value="attendance" <?php echo $record_type === 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                            <option value="tasks" <?php echo $record_type === 'tasks' ? 'selected' : ''; ?>>Tasks</option>
                            <option value="leaves" <?php echo $record_type === 'leaves' ? 'selected' : ''; ?>>Leave Requests</option>
                            <option value="evaluations" <?php echo $record_type === 'evaluations' ? 'selected' : ''; ?>>Evaluations</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="form-group">
                        <label for="user" class="form-label">Employee</label>
                        <select class="form-select" name="user" id="user">
                            <option value="">All Employees</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="view_records.php" class="btn btn-outline-secondary" style="margin-top: 0.5rem;">
                            <i class="fas fa-times"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Records Display -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h5><i class="fas fa-list"></i> Records Overview (<?php echo count($records); ?> found)</h5>
                <button class="btn btn-success" onclick="exportRecords()">
                    <i class="fas fa-download"></i> Export Data
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($records)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>No Records Found</h3>
                        <p>No records match your current filter criteria. Try adjusting your search parameters or clearing all filters.</p>
                    </div>
                <?php else: ?>
                    <div class="records-grid">
                        <?php foreach ($records as $index => $record): ?>
                            <div class="record-card <?php echo strtolower($record['record_type']); ?>" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="record-header">
                                    <span class="record-type-badge <?php echo strtolower($record['record_type']); ?>">
                                        <?php echo $record['record_type']; ?>
                                    </span>
                                    <span class="record-date">
                                        <?php echo date('M d, Y H:i', strtotime($record['date_field'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($record['record_type'] === 'Attendance'): ?>
                                    <h6 class="record-title"><?php echo htmlspecialchars($record['name']); ?></h6>
                                    <div class="record-details">
                                        <div class="record-detail">
                                            <i class="fas fa-clock"></i>
                                            <span><strong>Check In:</strong> <?php echo date('H:i', strtotime($record['check_in'])); ?></span>
                                        </div>
                                        <?php if ($record['check_out']): ?>
                                            <div class="record-detail">
                                                <i class="fas fa-clock"></i>
                                                <span><strong>Check Out:</strong> <?php echo date('H:i', strtotime($record['check_out'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($record['duration']): ?>
                                            <div class="record-detail">
                                                <i class="fas fa-hourglass-half"></i>
                                                <span><strong>Duration:</strong> <?php echo $record['duration']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge info"><?php echo $record['status']; ?></span>
                                
                                <?php elseif ($record['record_type'] === 'Task'): ?>
                                    <h6 class="record-title"><?php echo htmlspecialchars($record['title']); ?></h6>
                                    <div class="record-details">
                                        <div class="record-detail">
                                            <i class="fas fa-user"></i>
                                            <span><strong>Assigned to:</strong> <?php echo htmlspecialchars($record['name']); ?></span>
                                        </div>
                                        <div class="record-detail">
                                            <i class="fas fa-user-cog"></i>
                                            <span><strong>Created by:</strong> <?php echo htmlspecialchars($record['created_by_name']); ?></span>
                                        </div>
                                        <div class="record-detail">
                                            <i class="fas fa-calendar"></i>
                                            <span><strong>Due:</strong> <?php echo date('M d, Y', strtotime($record['due_date'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge success"><?php echo ucfirst($record['status']); ?></span>
                                
                                <?php elseif ($record['record_type'] === 'Leave'): ?>
                                    <h6 class="record-title"><?php echo htmlspecialchars($record['name']); ?> - <?php echo ucfirst($record['leave_type']); ?> Leave</h6>
                                    <div class="record-details">
                                        <div class="record-detail">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><strong>Period:</strong> 
                                                <?php echo date('M d', strtotime($record['leave_start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($record['leave_end_date'])); ?>
                                            </span>
                                        </div>
                                        <div class="record-detail">
                                            <i class="fas fa-comment"></i>
                                            <span><strong>Reason:</strong> <?php echo htmlspecialchars(substr($record['leave_reason'], 0, 50)); ?>...</span>
                                        </div>
                                    </div>
                                    <span class="status-badge warning"><?php echo $record['status']; ?></span>
                                
                                <?php elseif ($record['record_type'] === 'Evaluation'): ?>
                                    <h6 class="record-title"><?php echo ucfirst($record['evaluation_type']); ?> Evaluation</h6>
                                    <div class="record-details">
                                        <div class="record-detail">
                                            <i class="fas fa-user-check"></i>
                                            <span><strong>Evaluator:</strong> <?php echo htmlspecialchars($record['evaluator_name']); ?></span>
                                        </div>
                                        <?php if ($record['target_name']): ?>
                                            <div class="record-detail">
                                                <i class="fas fa-bullseye"></i>
                                                <span><strong>Target:</strong> <?php echo htmlspecialchars($record['target_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge secondary">Evaluation</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function exportRecords() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            // Show loading state
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> Exporting...';
            btn.disabled = true;
            
            // Simulate export process
            setTimeout(() => {
                window.location.href = 'export_records.php?' + params.toString();
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 1000);
        }

        // Add smooth animations on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all record cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.record-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            // Add search functionality
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search records...';
            searchInput.className = 'form-control';
            searchInput.style.marginBottom = '1rem';
            
            const recordsContainer = document.querySelector('.records-grid');
            if (recordsContainer) {
                recordsContainer.parentNode.insertBefore(searchInput, recordsContainer);
                
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const cards = document.querySelectorAll('.record-card');
                    
                    cards.forEach(card => {
                        const text = card.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            card.style.display = 'block';
                            card.style.animation = 'fadeInUp 0.3s ease';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });

        // Auto-refresh functionality
        let autoRefresh = false;
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            if (autoRefresh) {
                setInterval(() => {
                    if (autoRefresh) {
                        location.reload();
                    }
                }, 30000); // Refresh every 30 seconds
            }
        }
    </script>
</body>
</html>