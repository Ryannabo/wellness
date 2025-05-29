<?php
/**
 * NotificationManager Class
 * Handles all notification operations for the wellness system
 */
class NotificationManager {
    private $pdo;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
    }
    
    /**
     * Create a notification (private helper method)
     */
    private function createNotification($user_id, $message, $type, $reference_id = null, $reference_type = '', $task_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, task_id, message, notification_type, reference_id, reference_type, type, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'info', NOW())
            ");
            return $stmt->execute([$user_id, $task_id ?? 0, $message, $type, $reference_id, $reference_type]);
        } catch (PDOException $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user notifications with pagination (enhanced version)
     */
    public function getUserNotificationsPaginated($user_id, $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $count_stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
            $count_stmt->execute([$user_id]);
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get notifications
            $stmt = $this->pdo->prepare("
                SELECT n.*, t.title as task_title 
                FROM notifications n 
                LEFT JOIN tasks t ON n.task_id = t.id 
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'notifications' => $notifications,
                'total' => $total,
                'total_pages' => ceil($total / $limit),
                'current_page' => $page,
                'per_page' => $limit
            ];
        } catch (PDOException $e) {
            error_log("Failed to get paginated notifications: " . $e->getMessage());
            return [
                'notifications' => [],
                'total' => 0,
                'total_pages' => 0,
                'current_page' => 1,
                'per_page' => $limit
            ];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (PDOException $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Failed to mark all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify when HR approves an account
     * THIS IS THE MISSING METHOD!
     */
    public function notifyAccountApproval($user_id, $approved_by_hr_id = null, $status = 'approved') {
        // Get HR name if provided
        $hr_name = 'HR';
        if ($approved_by_hr_id) {
            try {
                $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$approved_by_hr_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $hr_name = $result['name'];
                }
            } catch (PDOException $e) {
                error_log("Failed to get HR name: " . $e->getMessage());
            }
        }
        
        if ($status === 'approved') {
            $message = "🎉 Your account has been approved by HR ({$hr_name}). Welcome to the team!";
            $notification_type = 'account_approval';
            $type = 'success';
        } else {
            $message = "❌ Your account application has been rejected by HR ({$hr_name}). Please contact HR for more information.";
            $notification_type = 'account_rejection';
            $type = 'warning';
        }
        
        error_log("🎯 Sending account {$status} notification to user {$user_id}: {$message}");
        
        return $this->createNotification($user_id, $message, $type, $user_id, 'user_account');
    }

    /**
     * Notify when HR approves/rejects leave request
     */
    public function notifyLeaveApproval($user_id, $leave_request_id, $hr_id, $status) {
        $hr_name = 'HR';
        if ($hr_id) {
            try {
                $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$hr_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $hr_name = $result['name'];
                }
            } catch (PDOException $e) {
                error_log("Failed to get HR name: " . $e->getMessage());
            }
        }
        
        if ($status === 'approved') {
            $message = "🌴 Your leave request has been approved by HR ({$hr_name}).";
            $notification_type = 'leave_approval';
            $type = 'success';
        } else {
            $message = "❌ Your leave request has been rejected by HR ({$hr_name}).";
            $notification_type = 'leave_rejection';
            $type = 'warning';
        }
        
        return $this->createNotification($user_id, $message, $type, $leave_request_id, 'leave_request');
    }

    /**
     * Notify when employee is promoted
     */
    public function notifyPromotion($user_id, $promotion_id, $hr_id, $status) {
        $hr_name = 'HR';
        if ($hr_id) {
            try {
                $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$hr_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $hr_name = $result['name'];
                }
            } catch (PDOException $e) {
                error_log("Failed to get HR name: " . $e->getMessage());
            }
        }
        
        if ($status === 'approved') {
            $message = "🎉 Congratulations! Your promotion has been approved by HR ({$hr_name}).";
            $notification_type = 'promotion_approval';
            $type = 'success';
        } else {
            $message = "📋 Your promotion request has been reviewed by HR ({$hr_name}). Please check with HR for feedback.";
            $notification_type = 'promotion_rejection';
            $type = 'warning';
        }
        
        return $this->createNotification($user_id, $message, $type, $promotion_id, 'promotion');
    }
    
    /**
     * Notify when manager approves task status
     */
    public function notifyTaskApproval($employee_id, $task_id, $task_title, $manager_name, $action) {
        switch ($action) {
            case 'approved':
                $message = "✅ Your task '{$task_title}' has been approved by {$manager_name}. Great work!";
                $type = 'task_approval';
                break;
            case 'rejected':
                $message = "🔄 Your task '{$task_title}' needs revision. Please check with {$manager_name} for feedback.";
                $type = 'task_rejection';
                break;
            default:
                $message = "📋 Task '{$task_title}' status updated by {$manager_name}.";
                $type = 'task_update';
        }
        
        return $this->createNotification($employee_id, $message, $type, $task_id, 'task', $task_id);
    }
    
    /**
     * Notify when task status changes
     */
    public function notifyTaskStatusChange($employee_id, $task_id, $task_title, $old_status, $new_status, $manager_name) {
        $message = "📋 Task '{$task_title}' status changed from '{$old_status}' to '{$new_status}' by {$manager_name}.";
        $type = 'task_status_change';
        
        return $this->createNotification($employee_id, $message, $type, $task_id, 'task', $task_id);
    }
    
    /**
     * Notify when new task is assigned
     */
    public function notifyTaskAssignment($employee_id, $task_id, $assigned_by_id) {
        $assigner_name = $this->getUserName($assigned_by_id);
        $task_details = $this->getTaskDetails($task_id);
        
        $message = "📋 New task assigned: '{$task_details['title']}' by {$assigner_name}. Due: {$task_details['due_date']}";
        $type = 'task_assignment';
        
        return $this->createNotification($employee_id, $message, $type, $task_id, 'task', $task_id);
    }
    
    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications($user_id, $limit = 10, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            error_log("Failed to get unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Helper method to get user name
     */
    private function getUserName($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['name'] : 'Unknown User';
        } catch (PDOException $e) {
            return 'Unknown User';
        }
    }
    
    /**
     * Helper method to get task title
     */
    private function getTaskTitle($task_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT title FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['title'] : 'Unknown Task';
        } catch (PDOException $e) {
            return 'Unknown Task';
        }
    }
    
    /**
     * Helper method to get task details
     */
    private function getTaskDetails($task_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT title, due_date FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $result['due_date'] = date('M j, Y', strtotime($result['due_date']));
            }
            return $result ?: ['title' => 'Unknown Task', 'due_date' => 'Unknown'];
        } catch (PDOException $e) {
            return ['title' => 'Unknown Task', 'due_date' => 'Unknown'];
        }
    }
}
?>