<?php
require __DIR__ . '/../db.php';

$evaluation_id = $_GET['id'] ?? null;
if (!$evaluation_id) {
    die("Evaluation ID missing.");
}

// Fetch evaluation metadata
$sql = "
    SELECT 
        e.created_at,
        u1.name AS evaluator_name,
        u2.name AS evaluatee_name
    FROM evaluations e
    JOIN users u1 ON e.evaluator_id = u1.id
    JOIN users u2 ON e.evaluatee_id = u2.id
    WHERE e.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$evaluation_id]);
$info = $stmt->fetch();

// Fetch evaluation items
$sql = "
    SELECT criteria, rating, comment
    FROM evaluation_items
    WHERE evaluation_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$evaluation_id]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Details</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .content {
            padding: 40px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .info-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            border-radius: 16px 16px 0 0;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }

        .evaluations-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .evaluation-item {
            background: linear-gradient(135deg, #fefefe, #f9fafb);
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .evaluation-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }

        .evaluation-item:last-child {
            margin-bottom: 0;
        }

        .criteria-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 20px;
        }

        .criteria-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            flex: 1;
        }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 80px;
            justify-content: center;
        }

        .rating-excellent {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .rating-good {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .rating-average {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .rating-poor {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .rating-default {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }

        .comment {
            background: rgba(99, 102, 241, 0.05);
            padding: 16px;
            border-radius: 12px;
            border-left: 4px solid #6366f1;
            font-style: italic;
            color: #4b5563;
            line-height: 1.6;
        }

        .comment:empty::before {
            content: "No comment provided";
            color: #9ca3af;
            font-style: italic;
        }

        .stars {
            display: flex;
            gap: 2px;
            margin-left: 8px;
        }

        .star {
            color: #fbbf24;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 30px 20px;
            }

            .title {
                font-size: 2rem;
            }

            .content {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .criteria-header {
                flex-direction: column;
                gap: 12px;
            }

            .evaluations-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a class="back-btn" href="evaluations_list.php">
                <i class="fas fa-arrow-left"></i>
                Back to Evaluations
            </a>
            <h1 class="title">Evaluation Details</h1>
            <p class="subtitle">Comprehensive performance review and feedback</p>
        </div>

        <div class="content">
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-calendar-alt"></i>
                        Date
                    </div>
                    <div class="info-value"><?= htmlspecialchars($info['created_at']) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-user-tie"></i>
                        Evaluator
                    </div>
                    <div class="info-value"><?= htmlspecialchars($info['evaluator_name']) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        Evaluatee
                    </div>
                    <div class="info-value"><?= htmlspecialchars($info['evaluatee_name']) ?></div>
                </div>
            </div>

            <div class="evaluations-section">
                <h2 class="section-title">
                    <i class="fas fa-clipboard-check"></i>
                    Evaluation Criteria & Ratings
                </h2>

                <?php foreach ($items as $item): ?>
                <div class="evaluation-item">
                    <div class="criteria-header">
                        <div class="criteria-name"><?= htmlspecialchars($item['criteria']) ?></div>
                        <div class="rating-badge" data-rating="<?= htmlspecialchars($item['rating']) ?>">
                            <?= htmlspecialchars($item['rating']) ?>
                        </div>
                    </div>
                    <div class="comment"><?= htmlspecialchars($item['comment']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Add dynamic rating styling based on rating text
        function getRatingClass(rating) {
            const ratingLower = rating.toLowerCase();
            if (ratingLower.includes('excellent') || ratingLower.includes('outstanding') || ratingLower === '5') {
                return 'rating-excellent';
            } else if (ratingLower.includes('good') || ratingLower.includes('very good') || ratingLower === '4') {
                return 'rating-good';
            } else if (ratingLower.includes('average') || ratingLower.includes('satisfactory') || ratingLower === '3') {
                return 'rating-average';
            } else if (ratingLower.includes('poor') || ratingLower.includes('needs improvement') || ratingLower === '2' || ratingLower === '1') {
                return 'rating-poor';
            }
            return 'rating-default';
        }

        function getStarRating(rating) {
            const ratingLower = rating.toLowerCase();
            let stars = 3; // default
            
            if (ratingLower.includes('excellent') || ratingLower.includes('outstanding') || ratingLower === '5') {
                stars = 5;
            } else if (ratingLower.includes('good') || ratingLower.includes('very good') || ratingLower === '4') {
                stars = 4;
            } else if (ratingLower.includes('average') || ratingLower.includes('satisfactory') || ratingLower === '3') {
                stars = 3;
            } else if (ratingLower === '2') {
                stars = 2;
            } else if (ratingLower === '1' || ratingLower.includes('poor')) {
                stars = 1;
            }

            let starHtml = '<div class="stars">';
            for (let i = 1; i <= 5; i++) {
                if (i <= stars) {
                    starHtml += '<i class="fas fa-star star"></i>';
                } else {
                    starHtml += '<i class="far fa-star star"></i>';
                }
            }
            starHtml += '</div>';
            return starHtml;
        }

        // Apply dynamic styling to rating badges
        document.addEventListener('DOMContentLoaded', function() {
            const ratingBadges = document.querySelectorAll('.rating-badge');
            ratingBadges.forEach(badge => {
                const rating = badge.getAttribute('data-rating');
                const ratingClass = getRatingClass(rating);
                badge.classList.add(ratingClass);
                
                // Add star rating
                const starRating = getStarRating(rating);
                badge.innerHTML = badge.innerHTML + starRating;
            });

            // Add stagger animation to evaluation items
            const items = document.querySelectorAll('.evaluation-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
        });
    </script>
</body>
</html>