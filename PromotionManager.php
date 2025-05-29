<?php
class PromotionManager {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Get all employees eligible for promotion (role_id = 2)
     */
    public function getEligibleEmployees() {
        try {
            $sql = "SELECT u.id, u.name, u.username, u.email, u.created_at,
                           CASE WHEN p.id IS NOT NULL THEN 'Has Pending Promotion' ELSE 'Eligible' END as promotion_status
                    FROM users u 
                    LEFT JOIN promotions p ON u.id = p.user_id AND p.status_id = 1
                    WHERE u.role_id = 2 AND u.status_id = 1
                    ORDER BY u.name ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching eligible employees: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all pending promotions
     */
    public function getPendingPromotions() {
        try {
            $sql = "SELECT p.id, p.user_id, p.current_position, p.promotion_date, 
                           p.evaluation_comments, p.created_at,
                           u.name as employee_name, u.username, u.email,
                           ps.value as status
                    FROM promotions p
                    JOIN users u ON p.user_id = u.id
                    JOIN promotion_statuses ps ON p.status_id = ps.id
                    WHERE p.status_id = 1
                    ORDER BY p.created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching pending promotions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all promotions with their status
     */
    public function getAllPromotions() {
        try {
            $sql = "SELECT p.id, p.user_id, p.current_position, p.promotion_date, 
                           p.evaluation_comments, p.created_at, p.updated_at,
                           u.name as employee_name, u.username, u.email,
                           ps.value as status, ps.id as status_id
                    FROM promotions p
                    JOIN users u ON p.user_id = u.id
                    JOIN promotion_statuses ps ON p.status_id = ps.id
                    ORDER BY p.created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all promotions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new promotion candidate
     */
    public function createPromotion($user_id, $current_position, $promotion_date = null, $evaluation_comments = '') {
        try {
            // Check if user already has a pending promotion
            $check_sql = "SELECT id FROM promotions WHERE user_id = ? AND status_id = 1";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([$user_id]);
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Employee already has a pending promotion request.'];
            }
            
            // Check if user exists and is an employee
            $user_sql = "SELECT id, name FROM users WHERE id = ? AND role_id = 2 AND status_id = 1";
            $user_stmt = $this->conn->prepare($user_sql);
            $user_stmt->execute([$user_id]);
            
            if ($user_stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Invalid employee selected.'];
            }
            
            $sql = "INSERT INTO promotions (user_id, current_position, status_id, promotion_date, evaluation_comments) 
                    VALUES (?, ?, 1, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$user_id, $current_position, $promotion_date, $evaluation_comments]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Promotion candidate created successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to create promotion candidate.'];
            }
        } catch (PDOException $e) {
            error_log("Error creating promotion: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred.'];
        }
    }
    
    /**
     * Approve a promotion (update user role and promotion status)
     */
    public function approvePromotion($promotion_id, $evaluation_comments = '') {
        try {
            $this->conn->beginTransaction();
            
            // Get promotion details
            $promotion_sql = "SELECT user_id FROM promotions WHERE id = ? AND status_id = 1";
            $promotion_stmt = $this->conn->prepare($promotion_sql);
            $promotion_stmt->execute([$promotion_id]);
            $promotion = $promotion_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$promotion) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Invalid promotion request.'];
            }
            
            // Update user role from employee (2) to manager (3)
            $user_sql = "UPDATE users SET role_id = 3, modified_at = CURRENT_TIMESTAMP WHERE id = ? AND role_id = 2";
            $user_stmt = $this->conn->prepare($user_sql);
            $user_result = $user_stmt->execute([$promotion['user_id']]);
            
            if (!$user_result || $user_stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Failed to update user role.'];
            }
            
            // Update promotion status to approved (2)
            $update_sql = "UPDATE promotions SET status_id = 2, promotion_date = CURDATE(), 
                          evaluation_comments = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_sql);
            $update_result = $update_stmt->execute([$evaluation_comments, $promotion_id]);
            
            if (!$update_result) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Failed to update promotion status.'];
            }
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Promotion approved successfully. Employee has been promoted to manager.'];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error approving promotion: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred.'];
        }
    }
    
    /**
     * Reject a promotion
     */
    public function rejectPromotion($promotion_id, $evaluation_comments = '') {
        try {
            // Check if promotion exists and is pending
            $check_sql = "SELECT id FROM promotions WHERE id = ? AND status_id = 1";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([$promotion_id]);
            
            if ($check_stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Invalid promotion request.'];
            }
            
            // Update promotion status to rejected (3)
            $sql = "UPDATE promotions SET status_id = 3, evaluation_comments = ?, 
                    updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$evaluation_comments, $promotion_id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Promotion request rejected.'];
            } else {
                return ['success' => false, 'message' => 'Failed to reject promotion.'];
            }
        } catch (PDOException $e) {
            error_log("Error rejecting promotion: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred.'];
        }
    }
    
    /**
     * Get promotion statistics
     */
    public function getPromotionStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_promotions,
                        SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) as rejected_count
                    FROM promotions";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching promotion stats: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Debug method to log form data
     */h
    public function logFormData($data) {
        $log_file = __DIR__ . '/promotion_debug.log';
        $log_message = date('Y-m-d H:i:s') . " - Form Data: " . print_r($data, true) . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
?>