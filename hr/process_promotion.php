<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../PromotionManager.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = 'Invalid security token. Please try again.';
    $_SESSION['message_type'] = 'danger';
    header('Location: hr_promotions.php');
    exit();
}

// Initialize promotion manager
$promotionManager = new PromotionManager($pdo);

// Get the action
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handleCreatePromotion($promotionManager);
        break;
    
    case 'approve':
        handleApprovePromotion($promotionManager);
        break;
    
    case 'reject':
        handleRejectPromotion($promotionManager);
        break;
    
    default:
        $_SESSION['message'] = 'Invalid action specified.';
        $_SESSION['message_type'] = 'danger';
        header('Location: hr_promotions.php');
        exit();
}

/**
 * Handle creating a new promotion candidate
 */
function handleCreatePromotion($promotionManager) {
    // Validate required fields
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $current_position = trim($_POST['current_position'] ?? '');
    $promotion_date = $_POST['promotion_date'] ?? null;
    $evaluation_comments = trim($_POST['evaluation_comments'] ?? '');
    
    // Validation
    if (!$user_id) {
        $_SESSION['message'] = 'Please select a valid employee.';
        $_SESSION['message_type'] = 'danger';
        header('Location: create_promotion.php');
        exit();
    }
    
    if (empty($current_position)) {
        $_SESSION['message'] = 'Current position is required.';
        $_SESSION['message_type'] = 'danger';
        header('Location: create_promotion.php');
        exit();
    }
    
    // Validate promotion date if provided
    if (!empty($promotion_date)) {
        $date = DateTime::createFromFormat('Y-m-d', $promotion_date);
        if (!$date || $date->format('Y-m-d') !== $promotion_date) {
            $_SESSION['message'] = 'Invalid promotion date format.';
            $_SESSION['message_type'] = 'danger';
            header('Location: create_promotion.php');
            exit();
        }
        
        // Check if date is not in the past
        if ($date < new DateTime('today')) {
            $_SESSION['message'] = 'Promotion date cannot be in the past.';
            $_SESSION['message_type'] = 'danger';
            header('Location: create_promotion.php');
            exit();
        }
    } else {
        $promotion_date = null;
    }
    
    // Create the promotion
    $result = $promotionManager->createPromotion($user_id, $current_position, $promotion_date, $evaluation_comments);
    
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    
    if ($result['success']) {
        // Log the action
        logAuditAction($user_id, 'promotion_created', "Promotion candidate created for user ID: $user_id");
        header('Location: hr_promotions.php');
    } else {
        header('Location: create_promotion.php');
    }
    exit();
}

/**
 * Handle approving a promotion
 */
function handleApprovePromotion($promotionManager) {
    $promotion_id = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
    $evaluation_comments = trim($_POST['evaluation_comments'] ?? '');
    
    if (!$promotion_id) {
        $_SESSION['message'] = 'Invalid promotion ID.';
        $_SESSION['message_type'] = 'danger';
        header('Location: hr_promotions.php');
        exit();
    }
    
    // Approve the promotion
    $result = $promotionManager->approvePromotion($promotion_id, $evaluation_comments);
    
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    
    if ($result['success']) {
        // Log the action
        logAuditAction($_SESSION['user_id'], 'promotion_approved', "Promotion ID: $promotion_id approved");
    }
    
    header('Location: hr_promotions.php');
    exit();
}

/**
 * Handle rejecting a promotion
 */
function handleRejectPromotion($promotionManager) {
    $promotion_id = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
    $evaluation_comments = trim($_POST['evaluation_comments'] ?? '');
    
    if (!$promotion_id) {
        $_SESSION['message'] = 'Invalid promotion ID.';
        $_SESSION['message_type'] = 'danger';
        header('Location: hr_promotions.php');
        exit();
    }
    
    if (empty($evaluation_comments)) {
        $_SESSION['message'] = 'Rejection reason is required.';
        $_SESSION['message_type'] = 'danger';
        header('Location: hr_promotions.php');
        exit();
    }
    
    // Reject the promotion
    $result = $promotionManager->rejectPromotion($promotion_id, $evaluation_comments);
    
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    
    if ($result['success']) {
        // Log the action
        logAuditAction($_SESSION['user_id'], 'promotion_rejected', "Promotion ID: $promotion_id rejected");
    }
    
    header('Location: hr_promotions.php');
    exit();
}

/**
 * Log audit actions
 */
function logAuditAction($user_id, $action_type, $description) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO audit_logs (user_id, action_type, action_description, performed_by) 
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $action_type, $description, $_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Error logging audit action: " . $e->getMessage());
    }
}

// Regenerate CSRF token for next request
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>