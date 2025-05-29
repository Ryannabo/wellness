<?php
class NotificationManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Ensure notifications table exists with proper structure
        $this->createNotificationsTable();
    }
    
    private function createNotificationsTable() {
        try {
            // Check if task_id column is nullable
            $stmt = $this->pdo->query("DESCRIBE notifications");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $task_id_nullable = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'task_id' && $column['Null'] === 'YES') {
                    $task_id_nullable = true;
                    break;
                }
            }
            
            // Make task_id nullable if it isn't already
            if (!$task_id_nullable) {
                $this->pdo->exec("ALTER TABLE notifications MODIFY COLUMN task_id INT(11) NULL");
            }
            
        } catch (PDOException $e) {
            error_log("Failed to update notifications table: " . $e->getMessage());
        }
    }
    
    public function createNotification($user_id, $notification_type, $type, $message, $task_id = null) {
        try {
            // Validate inputs
            if (empty($user_id) || empty($message)) {
                error_log("❌ Invalid notification parameters: user_id=$user_id, message=" . substr($message, 0, 50));
                return false;
            }
            
            // Check if user exists
            $userCheck = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->execute([$user_id]);
            if (!$userCheck->fetch()) {
                error_log("❌ User ID $user_id does not exist");
                return false;
            }
            
            error_log("🔔 Creating notification: user_id=$user_id, type=$notification_type, level=$type, message=" . substr($message, 0, 50));
            
            // Simple INSERT without transaction (single operation)
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, notification_type, type, message, is_read, created_at, task_id) 
                VALUES (?, ?, ?, ?, 0, NOW(), ?)
            ");
            
            $result = $stmt->execute([$user_id, $notification_type, $type, $message, $task_id]);
            
            if ($result) {
                $insertId = $this->pdo->lastInsertId();
                error_log("✅ Notification created successfully with ID: $insertId");
                
                // Verify the notification was actually inserted
                $verify = $this->pdo->prepare("SELECT * FROM notifications WHERE id = ?");
                $verify->execute([$insertId]);
                $inserted = $verify->fetch(PDO::FETCH_ASSOC);
                
                if ($inserted) {
                    error_log("✅ Notification verified in database: " . json_encode($inserted));
                    return $insertId; // Return the notification ID
                } else {
                    error_log("❌ Notification not found after insert - database issue");
                    return false;
                }
            } else {
                error_log("❌ Notification creation failed: " . implode(', ', $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("❌ Database error creating notification: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("❌ General error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserNotifications($user_id, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT n.*, t.title as task_title 
                FROM notifications n
                LEFT JOIN tasks t ON n.task_id = t.id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get notifications: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Failed to get unread count: " . $e->getMessage());
            return 0;
        }
    }
    
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
    
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ?
            ");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Failed to mark all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    // MAIN METHOD: Task approval notification (when admin clicks "complete")
    public function notifyTaskApproval($user_id, $task_id, $task_title, $manager_name, $action = 'approved') {
        if ($action === 'approved') {
            $message = "Task '{$task_title}' has been approved by manager. Great work!";
        } else {
            $message = "Task '{$task_title}' has been rejected by manager. Please check for feedback.";
        }
        
        $notification_type = 'task_approval';
        $type = $action === 'approved' ? 'success' : 'warning';
        
        error_log("🎯 Sending task {$action} notification to user {$user_id}: {$message}");
        
        return $this->createNotification($user_id, $notification_type, $type, $message, $task_id);
    }
    
    // Task rejection notification
    public function notifyTaskRejection($user_id, $task_id, $task_title, $manager_name) {
        return $this->notifyTaskApproval($user_id, $task_id, $task_title, $manager_name, 'rejected');
    }
    
    // Task assignment notification
    public function notifyTaskAssignment($user_id, $task_id, $task_title, $manager_name, $due_date) {
        $message = "You have been assigned a new task: '{$task_title}' by manager. Due date: {$due_date}";
        return $this->createNotification($user_id, 'task_assignment', 'info', $message, $task_id);
    }
    
    // Task status change notification - Updated message format
    public function notifyTaskStatusChange($user_id, $task_id, $task_title, $old_status, $new_status, $manager_name) {
        // Create different messages based on the status change
        if ($new_status === 'completed' || $new_status === 'Completed') {
            $message = "Task '{$task_title}' has been approved by manager";
        } elseif ($new_status === 'in_progress' || $new_status === 'In Progress') {
            $message = "Task '{$task_title}' has been set to in progress by manager";
        } elseif ($new_status === 'pending' || $new_status === 'Pending') {
            $message = "Task '{$task_title}' has been set to pending by manager";
        } else {
            // Fallback for any other status
            $message = "Task '{$task_title}' status has been updated by manager";
        }
        
        return $this->createNotification($user_id, 'task_status_change', 'info', $message, $task_id);
    }
    
    // Bulk notification for multiple users
    public function createBulkNotification($user_ids, $notification_type, $type, $message, $task_id = null) {
        $success_count = 0;
        foreach ($user_ids as $user_id) {
            if ($this->createNotification($user_id, $notification_type, $type, $message, $task_id)) {
                $success_count++;
            }
        }
        return $success_count;
    }
    
    // Get notifications with pagination
    public function getUserNotificationsPaginated($user_id, $page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            
            $stmt = $this->pdo->prepare("
                SELECT n.*, t.title as task_title,
                       COUNT(*) OVER() as total_count
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
                'total' => $notifications[0]['total_count'] ?? 0,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil(($notifications[0]['total_count'] ?? 0) / $limit)
            ];
        } catch (PDOException $e) {
            error_log("Failed to get paginated notifications: " . $e->getMessage());
            return ['notifications' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }
    }
}
?>