<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once '../PromotionManager.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize promotion manager
$promotionManager = new PromotionManager($pdo);

// Get eligible employees
$eligible_employees = $promotionManager->getEligibleEmployees();

// Pre-select user if provided in URL
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Handle success/error messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Promotion Candidate - Wellness System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="form-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-chart-line"></i> Create Promotion Candidate</h2>
                <a href="hr_promotions.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Promotions
                </a>
            </div>

            <!-- Display messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="card shadow">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-user-plus"></i> Promotion Candidate Form</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($eligible_employees)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No eligible employees found. All employees either have pending promotions or are not in the employee role.
                        </div>
                        <a href="hr_promotions.php" class="btn btn-primary">Return to Promotions Dashboard</a>
                    <?php else: ?>
                        <form action="process_promotion.php" method="POST" id="promotionForm">
                            <?php
                            // Debug information
                            if (isset($_SESSION['debug_info'])) {
                                echo '<div class="alert alert-info">';
                                echo '<pre>' . htmlspecialchars(print_r($_SESSION['debug_info'], true)) . '</pre>';
                                echo '</div>';
                                unset($_SESSION['debug_info']);
                            }
                            ?>
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <!-- Employee Selection -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="user_id" class="form-label">Select Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" id="user_id" name="user_id" required>
                                        <option value="">Choose an employee...</option>
                                        <?php foreach ($eligible_employees as $employee): ?>
                                            <?php if ($employee['promotion_status'] == 'Eligible'): ?>
                                                <option value="<?php echo $employee['id']; ?>" 
                                                        <?php echo ($selected_user_id == $employee['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['username']); ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Only employees without pending promotions are shown.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="current_position" class="form-label">Current Position <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="current_position" name="current_position" 
                                           value="Employee" placeholder="e.g., Senior Developer, Team Lead" required>
                                    <div class="form-text">This is the employee's current position before promotion.</div>
                                </div>
                            </div>

                            <!-- Promotion Date -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="promotion_date" class="form-label">Proposed Promotion Date</label>
                                    <input type="date" class="form-control" id="promotion_date" name="promotion_date" 
                                           min="<?php echo date('Y-m-d'); ?>">
                                    <div class="form-text">Leave blank if not yet determined.</div>
                                </div>
                            </div>

                            <!-- Evaluation Comments -->
                            <div class="mb-3">
                                <label for="evaluation_comments" class="form-label">Initial Evaluation Comments</label>
                                <textarea class="form-control" id="evaluation_comments" name="evaluation_comments" 
                                          rows="4" placeholder="Enter any initial evaluation comments, performance notes, or reasons for promotion consideration..."></textarea>
                                <div class="form-text">Optional: Add any relevant comments about the employee's performance or promotion justification.</div>
                            </div>

                            <!-- Employee Preview -->
                            <div id="employee_preview" class="alert alert-info" style="display: none;">
                                <h6><i class="fas fa-user"></i> Employee Information</h6>
                                <div id="preview_content"></div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-between">
                                <a href="hr_promotions.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Promotion Candidate
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle text-info"></i> Promotion Process Information</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Creating a promotion candidate will add them to the pending promotions list</li>
                        <li><i class="fas fa-check text-success"></i> You can review and approve/reject promotions from the main dashboard</li>
                        <li><i class="fas fa-check text-success"></i> Approved promotions will automatically change the employee's role from Employee to Manager</li>
                        <li><i class="fas fa-check text-success"></i> Only employees without existing pending promotions can be selected</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Employee data for preview
        const employees = <?php echo json_encode($eligible_employees); ?>;
        
        document.getElementById('user_id').addEventListener('change', function() {
            const selectedId = this.value;
            const previewDiv = document.getElementById('employee_preview');
            const previewContent = document.getElementById('preview_content');
            
            if (selectedId) {
                const employee = employees.find(emp => emp.id == selectedId);
                if (employee) {
                    previewContent.innerHTML = `
                        <strong>Name:</strong> ${employee.name}<br>
                        <strong>Username:</strong> ${employee.username}<br>
                        <strong>Email:</strong> ${employee.email}<br>
                        <strong>Joined:</strong> ${new Date(employee.created_at).toLocaleDateString()}
                    `;
                    previewDiv.style.display = 'block';
                }
            } else {
                previewDiv.style.display = 'none';
            }
        });

        // Form validation
        document.getElementById('promotionForm').addEventListener('submit', function(e) {
            const userId = document.getElementById('user_id').value;
            const currentPosition = document.getElementById('current_position').value.trim();
            
            if (!userId) {
                e.preventDefault();
                alert('Please select an employee.');
                return false;
            }
            
            if (!currentPosition) {
                e.preventDefault();
                alert('Please enter the current position.');
                return false;
            }
            
            // Confirm submission
            if (!confirm('Are you sure you want to create this promotion candidate?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>