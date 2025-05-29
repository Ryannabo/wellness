<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$evaluator_id = $_SESSION['user_id'];
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evaluatee_id = intval($_POST['employee_id']);
    $comments = trim($_POST['comments']);
    $evaluation_type = 'promotion_recommendation'; // static type

    if ($evaluatee_id > 0 && !empty($comments)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert into evaluations
            $stmt = $pdo->prepare("INSERT INTO evaluations (evaluatee_id, evaluator_id, evaluation_type) 
                                   VALUES (:evaluatee_id, :evaluator_id, :evaluation_type)");
            $stmt->execute([
                ':evaluatee_id' => $evaluatee_id,
                ':evaluator_id' => $evaluator_id,
                ':evaluation_type' => $evaluation_type
            ]);

            // Get the inserted evaluation ID
            $evaluation_id = $pdo->lastInsertId();

            // Insert into evaluation_items
            $stmt = $pdo->prepare("INSERT INTO evaluation_items (evaluation_id, comment) 
                                   VALUES (:evaluation_id, :comment)");
            $stmt->execute([
                ':evaluation_id' => $evaluation_id,
                ':comment' => $comments
            ]);

            // Commit transaction
            $pdo->commit();
            $message = "Promotion recommendation submitted.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Please select an employee and provide comments.";
    }
}

// Fetch distinct employees evaluated by this evaluator
$employees = [];
$stmt = $pdo->prepare("
    SELECT id, name
    FROM users
    WHERE role_id = 2
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Recommendation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
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

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .header h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 14px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .form-content {
            padding: 40px;
        }

        .message {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-weight: 500;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .message.success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: linear-gradient(135deg, #f8d7da, #f1b0b7);
            color: #721c24;
            border: 1px solid #f1b0b7;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        select, textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
        }

        select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
            appearance: none;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: #764ba2;
            transform: translateX(-5px);
        }

        .back-link a::before {
            content: '←';
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .back-link a:hover::before {
            transform: translateX(-3px);
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h2 {
                font-size: 24px;
            }
            
            .form-content {
                padding: 25px;
            }
        }

        /* Loading animation for form submission */
        .loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .loading .submit-btn::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Promotion Recommendation</h2>
            <div class="subtitle">Submit your professional recommendation</div>
        </div>

        <div class="form-content">
            <?php if (!empty($message)): ?>
                <div class="message <?= strpos($message, 'Error') === 0 ? 'error' : 'success' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="promotionForm">
                <div class="form-group">
                    <label for="employee_id">Select Employee</label>
                    <select name="employee_id" id="employee_id" required>
                        <option value="">-- Choose Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="comments">Recommendation Comments</label>
                    <textarea 
                        name="comments" 
                        id="comments" 
                        placeholder="Provide detailed justification for your promotion recommendation..."
                        required
                    ></textarea>
                </div>

                <button type="submit" class="submit-btn">Submit Recommendation</button>
            </form>

            <div class="back-link">
                <a href="manager_dashboard.php">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        // Add loading state on form submission
        document.getElementById('promotionForm').addEventListener('submit', function() {
            this.classList.add('loading');
        });

        // Auto-resize textarea
        const textarea = document.getElementById('comments');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(this.scrollHeight, 120) + 'px';
        });
    </script>
</body>
</html>