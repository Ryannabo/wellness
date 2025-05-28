<?php
session_start();
require __DIR__ . '/../db.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    $action = $_POST['action'];
    $comments = $_POST['comments'] ?? '';
    
    try {
        $status_id = ($action === 'approve') ? 2 : 3; // 2 = Approved, 3 = Rejected
        
        $stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET status_id = ?, action_timestamp = NOW(), manager_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$status_id, $_SESSION['user_id'], $leave_id]);
        
        $success = "Leave request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
    } catch (PDOException $e) {
        $error = "Error updating leave request: " . $e->getMessage();
    }
}

// Get all leave requests with statistics
try {
    $stmt = $pdo->query("
        SELECT lr.*, u.name, u.email, lt.value as leave_type, lrs.value as status_name
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        JOIN leave_request_statuses lrs ON lr.status_id = lrs.id
        ORDER BY lr.request_date DESC
    ");
    $leave_requests = $stmt->fetchAll();
    
    // Calculate statistics
    $pending_count = count(array_filter($leave_requests, fn($l) => $l['status_name'] === 'Pending'));
    $approved_count = count(array_filter($leave_requests, fn($l) => $l['status_name'] === 'Approved'));
    $rejected_count = count(array_filter($leave_requests, fn($l) => $l['status_name'] === 'Rejected'));
    $this_month_count = count(array_filter($leave_requests, fn($l) => date('Y-m', strtotime($l['request_date'])) === date('Y-m')));
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - HR System</title>
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
            background: var(--gradient-warning);
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
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
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
            background: var(--gradient-warning);
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
            color: var(--warning);
            font-size: 1.5rem;
        }

        .card-body {
            padding: 2rem;
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
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            transition: transform 0.3s ease;
        }

        .stat-card.pending::before { background: var(--gradient-warning); }
        .stat-card.approved::before { background: var(--gradient-success); }
        .stat-card.rejected::before { background: var(--gradient-danger); }
        .stat-card.total::before { background: var(--gradient-primary); }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            transform: scaleX(1.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card.pending .stat-number { color: var(--warning); }
        .stat-card.approved .stat-number { color: var(--success); }
        .stat-card.rejected .stat-number { color: var(--danger); }
        .stat-card.total .stat-number { color: var(--primary); }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table th {
            background: var(--gradient-warning);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .table th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.3), transparent);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
            transition: var(--transition);
        }

        .table tbody tr {
            transition: var(--transition);
            position: relative;
        }

        .table tbody tr::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.1), transparent);
            transition: width 0.3s ease;
        }

        .table tbody tr:hover::before {
            width: 100%;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
            transform: translateX(5px);
        }

        .employee-info {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .employee-email {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .leave-type-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.1) 100%);
            color: var(--info);
            border: 1px solid rgba(59, 130, 246, 0.3);
            position: relative;
            overflow: hidden;
        }

        .leave-type-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .leave-type-badge:hover::before {
            left: 100%;
        }

        .leave-period {
            font-size: 0.875rem;
            color: var(--gray-700);
            font-weight: 500;
        }

        .leave-days {
            font-weight: 600;
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .leave-reason {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--gray-600);
            font-size: 0.875rem;
            cursor: help;
            transition: var(--transition);
        }

        .leave-reason:hover {
            color: var(--gray-800);
            background: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid;
            position: relative;
            overflow: hidden;
        }

        .status-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .status-badge:hover::before {
            left: 100%;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0.1) 100%);
            color: var(--warning);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .status-badge.approved {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%);
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .status-badge.rejected {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.1) 100%);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-badge i {
            font-size: 0.625rem;
            animation: pulse 2s infinite;
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

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: var(--gray-500);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-timestamp {
            color: var(--gray-500);
            font-size: 0.75rem;
            font-style: italic;
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

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-color: var(--danger);
            color: var(--danger);
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
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--warning);
            font-size: 3rem;
            border: 2px solid rgba(245, 158, 11, 0.2);
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
            animation: fadeIn 0.3s ease;
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
            animation: scaleIn 0.3s ease;
        }

        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
        }

        .btn-close:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .modal-icon.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%);
            color: var(--success);
        }

        .modal-icon.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.1) 100%);
            color: var(--danger);
        }

        .modal-message {
            text-align: center;
            font-size: 1.125rem;
            color: var(--gray-700);
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
            background: white;
            resize: vertical;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: var(--gray-50);
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
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .nav-menu {
                justify-content: center;
            }
            
            .nav-item {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
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
                <a class="nav-item" href="view_records.php">
                    <i class="fas fa-file-alt"></i> Records
                </a>
                <a class="nav-item" href="approve_accounts.php">
                    <i class="fas fa-user-check"></i> Accounts
                </a>
                <a class="nav-item active" href="leave_management.php">
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
                <i class="fas fa-calendar-times"></i> Leave Management
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

        <!-- Statistics Row -->
        <div class="stats-row">
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card total">
                <div class="stat-number"><?php echo $this_month_count; ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> All Leave Requests (<?php echo count($leave_requests); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($leave_requests)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>No Leave Requests</h3>
                        <p>There are currently no leave requests to review. All employees are present and accounted for!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Period</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_requests as $index => $leave): ?>
                                    <?php
                                    $start_date = new DateTime($leave['leave_start_date']);
                                    $end_date = new DateTime($leave['leave_end_date']);
                                    $days = $start_date->diff($end_date)->days + 1;
                                    ?>
                                    <tr style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                        <td><strong>#<?php echo $leave['id']; ?></strong></td>
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-name"><?php echo htmlspecialchars($leave['name']); ?></div>
                                                <div class="employee-email"><?php echo htmlspecialchars($leave['email']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="leave-type-badge">
                                                <?php echo ucfirst($leave['leave_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="leave-period">
                                                <?php echo date('M d', strtotime($leave['leave_start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($leave['leave_end_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="leave-days">
                                                <?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="leave-reason" title="<?php echo htmlspecialchars($leave['leave_reason']); ?>">
                                                <?php echo htmlspecialchars($leave['leave_reason']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($leave['status_name']); ?>">
                                                <i class="fas fa-circle"></i> <?php echo $leave['status_name']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($leave['request_date'])); ?></div>
                                            <div class="action-timestamp">
                                                <?php echo date('H:i', strtotime($leave['request_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($leave['status_name'] === 'Pending'): ?>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="showLeaveModal(<?php echo $leave['id']; ?>, 'approve', '<?php echo htmlspecialchars($leave['name']); ?>')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="showLeaveModal(<?php echo $leave['id']; ?>, 'reject', '<?php echo htmlspecialchars($leave['name']); ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small">
                                                    <div><?php echo $leave['status_name']; ?></div>
                                                    <?php if ($leave['action_timestamp']): ?>
                                                        <div class="action-timestamp">
                                                            <?php echo date('M d, Y H:i', strtotime($leave['action_timestamp'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Leave Action Modal -->
    <div class="modal" id="leaveModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Leave Action</h5>
                        <button type="button" class="btn-close" onclick="closeModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="leave_id" id="leaveId">
                        <input type="hidden" name="action" id="actionType">
                        
                        <div class="text-center">
                            <div class="modal-icon" id="modalIcon">
                                <i id="modalIconSymbol"></i>
                            </div>
                            <p class="modal-message" id="modalMessage"></p>
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
        function showLeaveModal(leaveId, action, employeeName) {
            document.getElementById('leaveId').value = leaveId;
            document.getElementById('actionType').value = action;
            
            const modal = document.getElementById('leaveModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const icon = document.getElementById('modalIcon');
            const iconSymbol = document.getElementById('modalIconSymbol');
            const confirmBtn = document.getElementById('confirmBtn');
            
            if (action === 'approve') {
                title.textContent = 'Approve Leave Request';
                message.textContent = `Are you sure you want to approve the leave request for ${employeeName}?`;
                icon.className = 'modal-icon success';
                iconSymbol.className = 'fas fa-check-circle';
                confirmBtn.textContent = 'Approve';
                confirmBtn.className = 'btn btn-success';
            } else {
                title.textContent = 'Reject Leave Request';
                message.textContent = `Are you sure you want to reject the leave request for ${employeeName}?`;
                icon.className = 'modal-icon danger';
                iconSymbol.className = 'fas fa-times-circle';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn btn-danger';
            }
            
            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('leaveModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('leaveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

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

        // Observe all table rows
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(30px)';
                row.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(row);
            });

            // Add search functionality
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search leave requests...';
            searchInput.className = 'form-control';
            searchInput.style.marginBottom = '1rem';
            
            const tableContainer = document.querySelector('.table-responsive');
            if (tableContainer) {
                tableContainer.parentNode.insertBefore(searchInput, tableContainer);
                
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            row.style.display = 'table-row';
                            row.style.animation = 'fadeInUp 0.3s ease';
                        } else {
                            row.style.display = 'none';
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
                }, 60000); // Refresh every minute
            }
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                toggleAutoRefresh();
            }
        });
    </script>
</body>
</html>