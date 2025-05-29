<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "wellness";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Create tables if not exists
$conn->query("CREATE TABLE IF NOT EXISTS attendance_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(50) UNIQUE NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    check_in DATETIME NOT NULL,
    check_out DATETIME DEFAULT NULL,
    duration VARCHAR(20) DEFAULT NULL,
    status_id INT NOT NULL,
    FOREIGN KEY (status_id) REFERENCES attendance_statuses(id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Insert statuses if not exist
$conn->query("INSERT IGNORE INTO attendance_statuses (status) VALUES ('checked-in'), ('present')");

// Session Management
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = bin2hex(random_bytes(16));
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
    session_regenerate_id(true);
}
$user_id = $_SESSION['user_id'];

// Handle Leave Request submission
if (isset($_POST['submit_leave_request'])) {
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    // Validate inputs
    $errors = [];
    if (empty($leave_type)) $errors[] = "Leave type is required";
    if (empty($start_date)) $errors[] = "Start date is required";
    if (empty($end_date)) $errors[] = "End date is required";
    if (empty($reason)) $errors[] = "Reason is required";
    
    if (!empty($start_date) && !empty($end_date)) {
        if (strtotime($end_date) < strtotime($start_date)) {
            $errors[] = "End date cannot be before start date";
        }
    }

    if (empty($errors)) {
    // Get leave_type_id from leave_types table
    $stmt = $conn->prepare("SELECT id FROM leave_types WHERE value = ?");
    $stmt->bind_param("s", $leave_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        // Optional: insert the leave type if not exists
        $insert_stmt = $conn->prepare("INSERT INTO leave_types (value) VALUES (?)");
        $insert_stmt->bind_param("s", $leave_type);
        $insert_stmt->execute();
        $leave_type_id = $insert_stmt->insert_id;
        $insert_stmt->close();
    } else {
        $leave_type_id = $row['id'];
    }
    $stmt->close();

    // Now insert the leave request using leave_type_id
    $stmt = $conn->prepare("INSERT INTO leave_requests 
        (user_id, leave_type_id, leave_start_date, leave_end_date, leave_reason, request_date) 
        VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $user_id, $leave_type_id, $start_date, $end_date, $reason);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Leave request submitted successfully!";
    } else {
        $_SESSION['error'] = "Error submitting request: " . $stmt->error;
    }
    $stmt->close();
}
}

// Handle Time In/Out toggle
if (isset($_POST['toggle_attendance'])) {
    $stmt = $conn->prepare("SELECT id, check_in FROM attendance 
                          WHERE user_id = ? AND check_out IS NULL 
                          LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $open_entry = $result->fetch_assoc();
    $stmt->close();

    if (!$open_entry) {
        // Check in
        $status_id = $conn->query("SELECT id FROM attendance_statuses 
                                 WHERE status = 'checked-in'")->fetch_assoc()['id'];
        $stmt = $conn->prepare("INSERT INTO attendance 
                              (user_id, check_in, status_id) 
                              VALUES (?, NOW(), ?)");
        $stmt->bind_param("si", $user_id, $status_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Checked in successfully!";
            $_SESSION['checked_in'] = true;
        } else {
            $_SESSION['error'] = "Check-in failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Check out
        $status_id = $conn->query("SELECT id FROM attendance_statuses 
                                 WHERE status = 'present'")->fetch_assoc()['id'];
        $check_in = new DateTime($open_entry['check_in']);
        $check_out = new DateTime();
        $duration = $check_out->diff($check_in)->format('%Hh %Im');

        $stmt = $conn->prepare("UPDATE attendance SET 
                              check_out = NOW(), 
                              duration = ?, 
                              status_id = ? 
                              WHERE id = ?");
        $stmt->bind_param("sii", $duration, $status_id, $open_entry['id']);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Checked out successfully!";
            $_SESSION['checked_in'] = false;
        } else {
            $_SESSION['error'] = "Check-out failed: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: attendance_dashboard.php");
    exit;
}

// Fetch attendance data
$attendance = [];
$stats = [
    'total_days' => 0,
    'present_days' => 0,
    'current_streak' => 0,
    'avg_hours' => '0h 0m'
];

try {
    $stmt = $conn->prepare("SELECT DATE(check_in) AS date, check_in, check_out, duration, s.status
                          FROM attendance a
                          JOIN attendance_statuses s ON a.status_id = s.id
                          WHERE user_id = ?
                          ORDER BY check_in DESC");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate statistics
    $present_seconds = 0;
    $dates = [];
    $unique_dates = array_unique(array_map(fn($e) => date('Y-m-d', strtotime($e['check_in'])), $attendance));
        rsort($unique_dates); // Ensure descending order (today to past)

        $streak = 0;
        $current_date = new DateTime();

        foreach ($unique_dates as $date) {
            $date_obj = new DateTime($date);
            if ($date_obj->format('Y-m-d') === $current_date->format('Y-m-d')) {
                $streak++;
                $current_date->modify('-1 day');
            } else {
                break;
            }
        }
        $stats['current_streak'] = $streak;


    $stats['total_days'] = count($unique_dates);
    $stats['present_days'] = count(array_filter($attendance, fn($e) => $e['status'] === 'present'));

    if ($stats['present_days'] > 0) {
        $avg_seconds = $present_seconds / $stats['present_days'];
        $stats['avg_hours'] = gmdate('H\h i\m', (int) round($avg_seconds));

    }

    // Calculate streak
    $streak = 0;
    $current_date = new DateTime();
    foreach ($dates as $date) {
        $date_obj = new DateTime($date);
        if ($date_obj->format('Y-m-d') === $current_date->format('Y-m-d')) {
            $streak++;
            $current_date->modify('-1 day');
        } else {
            break;
        }
    }
    $stats['current_streak'] = $streak;

} catch (Exception $e) {
    error_log("Error fetching attendance: " . $e->getMessage());
}

// Fetch leave requests
$leave_requests = [];
try {
    $stmt = $conn->prepare("
        SELECT lr.*, lrs.value
        FROM leave_requests lr
        JOIN leave_request_statuses lrs ON lr.status_id = lrs.id
        WHERE lr.user_id = ?
        ORDER BY lr.request_date DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $leave_requests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching leave requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reset and base styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

:root {
    --primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --primary-solid: #667eea;
    --primary-dark: #5a67d8;
    --secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
    --danger: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --surface: rgba(255, 255, 255, 0.95);
    --surface-dark: rgba(255, 255, 255, 0.1);
    --text-primary: #1a202c;
    --text-secondary: #4a5568;
    --text-muted: #718096;
    --border: rgba(255, 255, 255, 0.2);
    --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    --border-radius: 16px;
    --border-radius-sm: 8px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: var(--background);
    color: var(--text-primary);
    line-height: 1.7;
    min-height: 100vh;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
}

.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    background: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    padding: 40px 50px;
    position: relative;
    animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    flex-wrap: wrap;
    position: relative;
}

.dashboard-header::after {
    content: '';
    position: absolute;
    bottom: -20px;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--primary-solid), transparent);
}

.dashboard-header h1 {
    font-weight: 800;
    font-size: clamp(2rem, 4vw, 2.5rem);
    background: var(--primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    position: relative;
}

.header-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 24px;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: var(--border-radius-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
    text-decoration: none;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
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

.btn:hover,
.btn:focus {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    outline: none;
}

.btn:active {
    transform: translateY(0);
}

.back-btn {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    box-shadow: 0 4px 15px rgba(100, 116, 139, 0.4);
}

.back-btn:hover,
.back-btn:focus {
    box-shadow: 0 8px 25px rgba(100, 116, 139, 0.6);
}

.alert {
    padding: 16px 24px;
    border-radius: var(--border-radius-sm);
    margin-bottom: 24px;
    font-weight: 600;
    border-left: 4px solid;
    backdrop-filter: blur(10px);
    animation: slideIn 0.4s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    color: #065f46;
    border-left-color: #10b981;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
}

.alert-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 127, 0.1) 100%);
    color: #b91c1c;
    border-left-color: #ef4444;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-bottom: 50px;
}

.stat-card {
    background: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    padding: 30px 24px;
    border-radius: var(--border-radius);
    text-align: center;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
    cursor: pointer;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary);
}

.stat-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: var(--shadow-lg);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: var(--primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
    line-height: 1.2;
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 50px;
    background: var(--surface);
    backdrop-filter: blur(20px);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow);
}

thead {
    background: var(--primary);
    color: white;
    position: relative;
}

thead::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: rgba(255, 255, 255, 0.3);
}

th, td {
    padding: 18px 20px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.95rem;
}

th {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.85rem;
}

tbody tr {
    transition: var(--transition);
    position: relative;
}

tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
    transform: scale(1.01);
}

tbody tr:hover td {
    border-bottom-color: rgba(102, 126, 234, 0.2);
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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
    transition: left 0.6s;
}

.status-badge:hover::before {
    left: 100%;
}

.status-pending {
    background: var(--warning);
}

.status-approved {
    background: var(--success);
}

.status-rejected {
    background: var(--danger);
}

.leave-request-form {
    background: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    padding: 40px;
    max-width: 700px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    left: 20vb;
}

.leave-request-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary);
}

.leave-request-form h2 {
    margin-bottom: 30px;
    color: var(--text-primary);
    font-weight: 700;
    font-size: 1.5rem;
    background: var(--primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.form-group {
    margin-bottom: 24px;
}

.leave-request-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.leave-request-form select,
.leave-request-form input[type="date"],
.leave-request-form textarea {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--border-radius-sm);
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    font-size: 1rem;
    transition: var(--transition);
    font-family: inherit;
    color: var(--text-primary);
    border: 1px black solid;
}

.leave-request-form select:focus,
.leave-request-form input[type="date"]:focus,
.leave-request-form textarea:focus {
    border-color: var(--primary-solid);
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: rgba(255, 255, 255, 0.8);
}

.leave-request-form textarea {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
    border: 1px black solid;
}

.leave-request-form .btn {
    width: 100%;
    font-size: 1.1rem;
    font-weight: 700;
    padding: 16px;
    margin-top: 10px;
}

/* Enhanced responsive design */
@media (max-width: 768px) {
    body {
        padding: 15px;
    }

    .dashboard-container {
        padding: 30px 25px;
    }

    .dashboard-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
    }

    .stat-card {
        padding: 20px 16px;
    }

    .stat-value {
        font-size: 2rem;
    }

    .leave-request-form {
        padding: 30px 20px;
    }

    th, td {
        padding: 12px 10px;
        font-size: 0.85rem;
    }

    .btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .dashboard-header h1 {
        font-size: 1.8rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .header-actions {
        width: 100%;
        justify-content: center;
    }

    table {
        font-size: 0.8rem;
    }

    th, td {
        padding: 8px 6px;
    }
}

/* Utility classes */
.glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.gradient-text {
    background: var(--primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hover-lift {
    transition: var(--transition);
}

.hover-lift:hover {
    transform: translateY(-2px);
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Attendance Dashboard</h1>
            <form method="POST" class="header-actions">
            <a href="user_dashboard.php" class="btn back-btn">  <!-- Or your specific page -->
    <i class="fa-solid fa-chevron-left"></i>
    Back to Menu
</a>
                <button type="submit" name="toggle_attendance" class="btn">
                    <?= isset($_SESSION['checked_in']) && $_SESSION['checked_in'] ? 'Time Out' : 'Time In' ?>
                    <i class="fa-solid fa-clock"></i>
                </button>
            </form>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_days'] ?></div>
                <div>Total Days</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['present_days'] ?></div>
                <div>Present Days</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($entry['check_in']))) ?></td>
                        <td><?= htmlspecialchars(date('h:i A', strtotime($entry['check_in']))) ?></td>
                        <td><?= $entry['check_out'] ? htmlspecialchars(date('h:i A', strtotime($entry['check_out']))) : '-' ?></td>
                        <td><?= htmlspecialchars(ucfirst($entry['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="POST" class="leave-request-form">
            <h2>Request Leave</h2>
            <label for="leave_type">Leave Type</label>
            <select name="leave_type" id="leave_type" required>
                <option value="" disabled <?= !isset($_SESSION['form_data']) ? 'selected' : '' ?>>Select leave type</option>
                <option value="Vacation" <?= isset($_SESSION['form_data']['leave_type']) && $_SESSION['form_data']['leave_type'] === 'Vacation' ? 'selected' : '' ?>>Vacation</option>
                <option value="Sick Leave" <?= isset($_SESSION['form_data']['leave_type']) && $_SESSION['form_data']['leave_type'] === 'Sick Leave' ? 'selected' : '' ?>>Sick Leave</option>
                <option value="Personal" <?= isset($_SESSION['form_data']['leave_type']) && $_SESSION['form_data']['leave_type'] === 'Personal' ? 'selected' : '' ?>>Personal</option>
                <option value="Other" <?= isset($_SESSION['form_data']['leave_type']) && $_SESSION['form_data']['leave_type'] === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>

            <label for="start_date">Start Date</label>
            <input type="date" name="start_date" id="start_date" required
                   min="<?= date('Y-m-d') ?>" 
                   value="<?= isset($_SESSION['form_data']['start_date']) ? htmlspecialchars($_SESSION['form_data']['start_date']) : '' ?>">

            <label for="end_date">End Date</label>
            <input type="date" name="end_date" id="end_date" required
                   min="<?= date('Y-m-d') ?>" 
                   value="<?= isset($_SESSION['form_data']['end_date']) ? htmlspecialchars($_SESSION['form_data']['end_date']) : '' ?>">

            <label for="reason">Reason</label>
            <textarea name="reason" id="reason" required><?= isset($_SESSION['form_data']['reason']) ? htmlspecialchars($_SESSION['form_data']['reason']) : '' ?></textarea>

            <button type="submit" name="submit_leave_request" class="btn">Submit Request</button>
        </form>

        <h2 style="margin-top: 40px;">Leave Requests History</h2>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Request Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leave_requests as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['leave_type_id']) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($request['leave_start_date']))) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($request['leave_end_date']))) ?></td>
                        <td><?= htmlspecialchars($request['leave_reason']) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($request['value']) ?>">
                                <?= htmlspecialchars($request['value']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('M j, Y h:i A', strtotime($request['request_date']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leave_requests)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #64748b;">
                            No leave requests found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php unset($_SESSION['form_data']); ?>

    <script>
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');

        function validateDates() {
            if (new Date(endInput.value) < new Date(startInput.value)) {
                alert('End date cannot be before start date');
                endInput.value = startInput.value;
            }
        }

        startInput.addEventListener('change', () => {
            endInput.min = startInput.value;
            validateDates();
        });

        endInput.addEventListener('change', validateDates);
    </script>
</body>
</html>