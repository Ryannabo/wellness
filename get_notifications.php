<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationManager.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

$notificationManager = new NotificationManager($pdo);
$user_id = $_SESSION['user_id'];

// Get parameters
$action = $_GET['action'] ?? 'get_notifications';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
$notification_id = isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0;

try {
    switch ($action) {
        case 'get_notifications':
            $notifications = $notificationManager->getUserNotifications($user_id, $limit, $unread_only);
            
            // Format timestamps for display
            foreach ($notifications as &$notification) {
                $notification['time_ago'] = formatTimeAgo(strtotime($notification['created_at']));
                
                // Add icon based on notification type
                $notification['icon'] = getNotificationIcon($notification['notification_type']);
                
                // Add color based on notification type
                $notification['color'] = getNotificationColor($notification['type']);
            }
            
            echo json_encode([
                'success' => true, 
                'notifications' => $notifications,
                'unread_count' => $notificationManager->getUnreadCount($user_id)
            ]);
            break;
            
        case 'get_unread_count':
            $count = $notificationManager->getUnreadCount($user_id);
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'mark_read':
            if ($notification_id > 0) {
                $result = $notificationManager->markAsRead($notification_id, $user_id);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            $result = $notificationManager->markAllAsRead($user_id);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Format timestamp to "time ago" format
 */
function formatTimeAgo($timestamp) {
    $current_time = time();
    $diff = $current_time - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * Get appropriate icon for notification type
 */
function getNotificationIcon($type) {
    switch ($type) {
        case 'account_approval':
            return 'fa-user-check';
        case 'task_approval':
            return 'fa-check-circle';
        case 'task_assignment':
            return 'fa-tasks';
        case 'leave_approval':
            return 'fa-calendar-check';
        case 'promotion_approval':
            return 'fa-arrow-up';
        default:
            return 'fa-bell';
    }
}

/**
 * Get appropriate color for notification type
 */
function getNotificationColor($type) {
    switch ($type) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warning';
        case 'danger':
            return 'danger';
        case 'info':
        default:
            return 'info';
    }
}
?>