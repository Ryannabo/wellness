<?php
session_start();
require __DIR__ . '/db.php';
require_once 'NotificationManager.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notificationManager = new NotificationManager($pdo);

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_notifications':
        $limit = $_GET['limit'] ?? 10;
        $notifications = $notificationManager->getUserNotifications($user_id, $limit);
        $unread_count = $notificationManager->getUnreadCount($user_id);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'get_unread_count':
        $count = $notificationManager->getUnreadCount($user_id);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'mark_read':
        $notification_id = $_POST['notification_id'] ?? 0;
        $result = $notificationManager->markAsRead($notification_id, $user_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'mark_all_read':
        $result = $notificationManager->markAllAsRead($user_id);
        echo json_encode(['success' => $result]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
