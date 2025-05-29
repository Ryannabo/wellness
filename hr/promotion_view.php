<?php 
require __DIR__ . '/../db.php';

if (!isset($_GET['evaluatee_id']) || !is_numeric($_GET['evaluatee_id'])) {
    die("Invalid or missing evaluatee_id.");
}

$evaluatee_id = intval($_GET['evaluatee_id']);

// Fetch evaluatee info (must be an employee)
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = :id AND role_id = 2");
$stmt->execute([':id' => $evaluatee_id]);
$evaluatee = $stmt->fetch();

if (!$evaluatee) {
    die("Employee not found.");
}

// Fetch promotion recommendation comments from managers only
$sql = "
    SELECT 
        ei.comment,
        e.created_at,
        u.name as evaluator_name
    FROM evaluations e
    JOIN users u ON e.evaluator_id = u.id
    LEFT JOIN evaluation_items ei ON ei.evaluation_id = e.id
    WHERE e.evaluation_type = 'promotion_recommendation'
      AND e.evaluatee_id = :evaluatee_id
      AND u.role_id = 3
      AND ei.comment IS NOT NULL
    ORDER BY e.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':evaluatee_id' => $evaluatee_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Comments for <?= htmlspecialchars($evaluatee['name']) ?></title>
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
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
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

        .header-content {
            position: relative;
            z-index: 1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }

        .back-btn::before {
            content: '←';
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .back-btn:hover::before {
            transform: translateX(-3px);
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .employee-name {
            background: linear-gradient(45deg, #f39c12, #e74c3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .content {
            padding: 40px;
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            display: block;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .no-comments {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: 2px dashed #dee2e6;
        }

        .no-comments-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .comment-card {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .comment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .comment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .comment-header {
            padding: 20px 25px 10px;
            border-bottom: 1px solid #f1f3f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .evaluator-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .evaluator-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .evaluator-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }

        .comment-date {
            font-size: 13px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .comment-body {
            padding: 20px 25px 25px;
        }

        .comment-text {
            color: #2c3e50;
            font-size: 16px;
            line-height: 1.7;
            white-space: pre-wrap;
            position: relative;
        }

        .comment-text::before {
            content: '"';
            font-size: 48px;
            color: #e1e8ed;
            position: absolute;
            top: -10px;
            left: -15px;
            font-family: Georgia, serif;
        }

        .comment-text::after {
            content: '"';
            font-size: 48px;
            color: #e1e8ed;
            position: absolute;
            bottom: -30px;
            right: 0;
            font-family: Georgia, serif;
        }

        .comment-index {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .employee-name {
                font-size: 26px;
            }
            
            .content {
                padding: 25px;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .comment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .comment-date {
                align-self: flex-end;
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <a href="evaluations_list.php" class="back-btn">Back to Evaluation Summary</a>
                <div class="page-title">Promotion Recommendations</div>
                <div class="employee-name"><?= htmlspecialchars($evaluatee['name']) ?></div>
            </div>
        </div>

        <div class="content">
            <?php if (count($comments) > 0): ?>
                <div class="stats-bar">
                    <div class="stat-item">
                        <span class="stat-number"><?= count($comments) ?></span>
                        <span class="stat-label">Total Recommendations</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= count(array_unique(array_column($comments, 'evaluator_name'))) ?></span>
                        <span class="stat-label">Unique Evaluators</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= !empty($comments) ? date('M j, Y', strtotime($comments[0]['created_at'])) : 'N/A' ?></span>
                        <span class="stat-label">Latest Review</span>
                    </div>
                </div>

                <?php foreach ($comments as $index => $row): ?>
                    <div class="comment-card fade-in" style="animation-delay: <?= $index * 0.1 ?>s;">
                        <div class="comment-index"><?= $index + 1 ?></div>
                        <div class="comment-header">
                            <div class="evaluator-info">
                                <div class="evaluator-avatar">
                                    <?= strtoupper(substr($row['evaluator_name'] ?? 'Manager', 0, 1)) ?>
                                </div>
                                <div class="evaluator-name"><?= htmlspecialchars($row['evaluator_name'] ?? 'Manager') ?></div>
                            </div>
                            <div class="comment-date">
                                <?= date('M j, Y \a\t g:i A', strtotime($row['created_at'])) ?>
                            </div>
                        </div>
                        <div class="comment-body">
                            <div class="comment-text"><?= htmlspecialchars($row['comment']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-comments">
                    <div class="no-comments-icon">📋</div>
                    <h3>No Promotion Recommendations Found</h3>
                    <p>This employee currently has no promotion recommendations from managers.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add staggered animation to comment cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.comment-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>