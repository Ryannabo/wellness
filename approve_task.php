<?php
session_start();
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../NotificationManager.php';

// Initialize notification manager
$notificationManager = new NotificationManager($pdo);

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Get HR user ID
$hr_user_id = $_SESSION['user_id'];

// Handle account approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $temp_user_id = $_POST['temp_user_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            // Get temp user data
            $stmt = $pdo->prepare("SELECT * FROM temp_users WHERE id = ?");
            $stmt->execute([$temp_user_id]);
            $temp_user = $stmt->fetch();
            
            if ($temp_user) {
                // Get gender ID
                $stmt = $pdo->prepare("SELECT id FROM genders WHERE value = ?");
                $stmt->execute([$temp_user['gender']]);
                $gender_result = $stmt->fetch();
                $gender_id = $gender_result ? $gender_result['id'] : 1; // Default to first gender if not found
                
                // Insert into users table
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, username, email, gender_id, contact_number, 
                                     emergency_number, address, birthday, password, role_id, status_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $temp_user['name'],
                    $temp_user['username'],
                    $temp_user['email'],
                    $gender_id,
                    $temp_user['contact_number'],
                    $temp_user['emergency_number'],
                    $temp_user['address'],
                    $temp_user['birthday'],
                    $temp_user['password'],
                    2, // employee role
                    1  // active status
                ]);
                
                $new_user_id = $pdo->lastInsertId();
                
                // 🔔 SEND NOTIFICATION - Account Approved
                try {
                    $notification_result = $notificationManager->notifyAccountApproval($new_user_id, $hr_user_id, 'approved');
                    $notification_status = $notification_result ? "User has been notified!" : "Notification failed to send.";
                } catch (Exception $e) {
                    $notification_status = "Notification failed: " . $e->getMessage();
                    error_log("Notification error: " . $e->getMessage());
                }
                
                // Delete from temp_users
                $stmt = $pdo->prepare("DELETE FROM temp_users WHERE id = ?");
                $stmt->execute([$temp_user_id]);
                
                $success = "Account approved successfully! " . htmlspecialchars($temp_user['name']) . " can now login. " . $notification_status;
            }
        } else {
            // Reject - get user info first
            $stmt = $pdo->prepare("SELECT email, name FROM temp_users WHERE id = ?");
            $stmt->execute([$temp_user_id]);
            $temp_user = $stmt->fetch();
            
            if ($temp_user) {
                // Delete from temp_users
                $stmt = $pdo->prepare("DELETE FROM temp_users WHERE id = ?");
                $stmt->execute([$temp_user_id]);
                
                $success = "Account for " . htmlspecialchars($temp_user['name']) . " has been rejected and removed from pending list. Consider sending an email to {$temp_user['email']} with rejection reason.";
            }
        }
    } catch (PDOException $e) {
        $error = "Error processing account: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all pending accounts
try {
    $stmt = $pdo->query("SELECT * FROM temp_users ORDER BY created_at DESC");
    $pending_accounts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Accounts - HR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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

        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .account-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--gray-200);
            position: relative;
        }

        .account-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .account-card:hover::before {
            transform: scaleX(1);
        }

        .account-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        .account-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
            border-bottom: 1px solid var(--gray-200);
        }

        .account-header h6 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .account-header h6 i {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            padding: 0.5rem;
            border-radius: 8px;
        }

        .account-date {
            color: var(--gray-500);
            font-size: 0.875rem;
            background: var(--gray-100);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            display: inline-block;
        }

        .account-body {
            padding: 1.5rem;
        }

        .account-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
            transition: var(--transition);
        }

        .account-detail:last-child {
            border-bottom: none;
        }

        .account-detail:hover {
            background: var(--gray-50);
            margin: 0 -1.5rem;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            border-radius: 8px;
        }

        .account-detail strong {
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .account-detail span {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .account-footer {
            padding: 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
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

        .btn-success {
            background: var(--gradient-success);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--gray-500);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .btn-grid {
            display: grid;
            gap: 0.75rem;
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
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--success);
            font-size: 3rem;
            border: 2px solid rgba(16, 185, 129, 0.2);
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
            margin-bottom: 1rem;
        }

        .modal-warning {
            text-align: center;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: var(--gray-50);
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

        .notification-badge {
            background: var(--success);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
            animation: pulse 2s infinite;
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
            
            .accounts-grid {
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

        /* Keep all your existing CSS - it's beautifully designed! */
        /* I'm truncating this for brevity, but use all your existing styles */

        .notification-badge {
            background: var(--success);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body>
    <!-- Keep all your existing HTML structure -->
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
                <a class="nav-item active" href="approve_accounts.php">
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
                <i class="fas fa-user-check"></i> Approve Accounts
            </h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <?php if (strpos($success, 'approved') !== false): ?>
                    <span class="notification-badge">
                        <i class="fas fa-bell"></i> User Notified
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Keep all your existing statistics and account cards HTML -->
        <!-- Just update the button text to show notification info -->
        
        <!-- Statistics Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($pending_accounts); ?></div>
                <div class="stat-label">Pending Accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($pending_accounts, fn($a) => strtotime($a['created_at']) > strtotime('-7 days'))); ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($pending_accounts, fn($a) => strtotime($a['created_at']) > strtotime('-24 hours'))); ?></div>
                <div class="stat-label">Today</div>
            </div>
        </div>

        <!-- Pending Accounts -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-clock"></i> Pending Account Approvals (<?php echo count($pending_accounts); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_accounts)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>All Caught Up!</h3>
                        <p>No pending account registrations to review. All applications have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="accounts-grid">
                        <?php foreach ($pending_accounts as $index => $account): ?>
                            <div class="account-card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="account-header">
                                    <h6>
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($account['name']); ?>
                                    </h6>
                                    <span class="account-date">
                                        Applied: <?php echo date('M d, Y H:i', strtotime($account['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="account-body">
                                    <div class="account-detail">
                                        <strong>Username:</strong>
                                        <span><?php echo htmlspecialchars($account['username']); ?></span>
                                    </div>
                                    <div class="account-detail">
                                        <strong>Email:</strong>
                                        <span><?php echo htmlspecialchars($account['email']); ?></span>
                                    </div>
                                    <div class="account-detail">
                                        <strong>Gender:</strong>
                                        <span><?php echo htmlspecialchars($account['gender']); ?></span>
                                    </div>
                                    <div class="account-detail">
                                        <strong>Contact:</strong>
                                        <span><?php echo htmlspecialchars($account['contact_number']); ?></span>
                                    </div>
                                    <div class="account-detail">
                                        <strong>Emergency:</strong>
                                        <span><?php echo htmlspecialchars($account['emergency_number']); ?></span>
                                    </div>
                                    <div class="account-detail">
                                        <strong>Birthday:</strong>
                                        <span><?php echo date('M d, Y', strtotime($account['birthday'])); ?></span>
                                    </div>
                                    <div class="account-detail">
                                        <strong>Address:</strong>
                                        <span><?php echo htmlspecialchars($account['address']); ?></span>
                                    </div>
                                </div>
                                <div class="account-footer">
                                    <div class="btn-grid">
                                        <button class="btn btn-success" 
                                                onclick="showApprovalModal(<?php echo $account['id']; ?>, 'approve', '<?php echo htmlspecialchars($account['name']); ?>')">
                                            <i class="fas fa-check"></i> Approve & Notify
                                        </button>
                                        <button class="btn btn-danger" 
                                                onclick="showApprovalModal(<?php echo $account['id']; ?>, 'reject', '<?php echo htmlspecialchars($account['name']); ?>')">
                                            <i class="fas fa-times"></i> Reject Account
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Keep your existing modal HTML -->
    <!-- Approval Modal -->
    <div class="modal" id="approvalModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Account Action</h5>
                        <button type="button" class="btn-close" onclick="closeModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="temp_user_id" id="tempUserId">
                        <input type="hidden" name="action" id="actionType">
                        
                        <div class="text-center">
                            <div class="modal-icon" id="modalIcon">
                                <i id="modalIconSymbol"></i>
                            </div>
                            <p class="modal-message" id="modalMessage"></p>
                            <p class="modal-warning">This action cannot be undone.</p>
                            <div id="notificationInfo" style="margin-top: 1rem; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px; display: none;">
                                <i class="fas fa-bell" style="color: var(--success);"></i>
                                <strong style="color: var(--success);">The user will be automatically notified via the system.</strong>
                            </div>
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

    <!-- Keep your existing JavaScript -->
    <script>
        function showApprovalModal(tempUserId, action, userName) {
            document.getElementById('tempUserId').value = tempUserId;
            document.getElementById('actionType').value = action;
            
            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const icon = document.getElementById('modalIcon');
            const iconSymbol = document.getElementById('modalIconSymbol');
            const confirmBtn = document.getElementById('confirmBtn');
            const notificationInfo = document.getElementById('notificationInfo');
            
            if (action === 'approve') {
                title.textContent = 'Approve Account';
                message.textContent = `Are you sure you want to approve the account for ${userName}?`;
                icon.className = 'modal-icon success';
                iconSymbol.className = 'fas fa-check-circle';
                confirmBtn.textContent = 'Approve & Notify';
                confirmBtn.className = 'btn btn-success';
                notificationInfo.style.display = 'block';
            } else {
                title.textContent = 'Reject Account';
                message.textContent = `Are you sure you want to reject the account for ${userName}?`;
                icon.className = 'modal-icon danger';
                iconSymbol.className = 'fas fa-times-circle';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn btn-danger';
                notificationInfo.style.display = 'none';
            }
            
            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('approvalModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Keep your existing animation code
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

        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.account-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>