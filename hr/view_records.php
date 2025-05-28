<?php
session_start();
require __DIR__ . '/../db.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

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
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
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
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
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
            flex-wrap: wrap;
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
            position: relative;
            overflow: hidden;
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

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
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
            animation: slideInDown 0.6s ease-out;
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-title i {
            background: var(--gradient-success);
            padding: 0.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
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
            position: relative;
            overflow: hidden;
        }

        .back-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .back-btn:hover::before {
            left: 100%;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: var(--transition);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gradient-primary);
        }

        .card-header h5 {
            color: var(--gray-800);
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header h5 i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            transform: translateY(-1px);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .btn-outline-secondary {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-300);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
        }

        .record-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .record-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            transition: width 0.3s ease;
        }

        .record-card.attendance::before { background: var(--gradient-primary); }
        .record-card.task::before { background: var(--gradient-success); }
        .record-card.leave::before { background: var(--gradient-warning); }
        .record-card.evaluation::before { background: var(--gradient-secondary); }

        .record-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        .record-card:hover::before {
            width: 8px;
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .record-type-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .record-type-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .record-type-badge:hover::before {
            left: 100%;
        }

        .record-type-badge.attendance { 
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%); 
            color: var(--primary); 
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        .record-type-badge.task { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%); 
            color: var(--success); 
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .record-type-badge.leave { 
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0.1) 100%); 
            color: var(--warning); 
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .record-type-badge.evaluation { 
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.2) 0%, rgba(168, 85, 247, 0.1) 100%); 
            color: #a855f7; 
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        .record-date {
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
        }

        .record-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .record-details {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .record-detail {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--gray-600);
            padding: 0.5rem;
            background: var(--gray-50);
            border-radius: 8px;
            transition: var(--transition);
        }

        .record-detail:hover {
            background: var(--gray-100);
            transform: translateX(5px);
        }

        .record-detail strong {
            color: var(--gray-800);
            font-weight: 600;
        }

        .record-detail i {
            color: var(--primary);
            width: 16px;
            text-align: center;
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid;
        }

        .status-badge.info { 
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.1) 100%); 
            color: var(--info); 
            border-color: rgba(59, 130, 246, 0.3);
        }
        .status-badge.success { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%); 
            color: var(--success); 
            border-color: rgba(16, 185, 129, 0.3);
        }
        .status-badge.warning { 
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0.1) 100%); 
            color: var(--warning); 
            border-color: rgba(245, 158, 11, 0.3);
        }
        .status-badge.secondary { 
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.2) 0%, rgba(107, 114, 128, 0.1) 100%); 
            color: var(--gray-600); 
            border-color: rgba(107, 114, 128, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--primary);
            font-size: 3rem;
            border: 2px solid rgba(99, 102, 241, 0.2);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            animation: slideInRight 0.5s ease-out;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-color: var(--danger);
            color: var(--danger);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            
            .page-title {
                font-size: 2rem;
                text-align: center;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .records-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-menu {
                justify-content: center;
            }
            
            .nav-item {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
                <a class="nav-item" href="manage_promotions.php">
                    <i class="fas fa-arrow-up"></i> Promotions
                </a>
                <a class="nav-item active" href="view_records.php">
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
                <i class="fas fa-file-alt"></i> View All Records
            </h2>
            <a href="dashboard.php" class="back-btn">
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