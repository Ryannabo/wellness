<?php
require __DIR__ . '/../db.php';

// Fetch distinct evaluations with basic info
$sql = "
    SELECT 
    e.id AS evaluation_id,
    e.created_at,
    e.evaluation_type,
    e.evaluatee_id,
    u1.name AS evaluator_name,
    u2.name AS evaluatee_name
FROM evaluations e
JOIN users u1 ON e.evaluator_id = u1.id
JOIN users u2 ON e.evaluatee_id = u2.id
ORDER BY e.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$evaluations = $stmt->fetchAll();

// Calculate statistics
$total_evaluations = count($evaluations);
$promotion_recommendations = count(array_filter($evaluations, function($eval) { return $eval['evaluation_type'] === 'promotion_recommendation'; }));
$other_evaluations = $total_evaluations - $promotion_recommendations;
$unique_evaluators = count(array_unique(array_column($evaluations, 'evaluator_name')));
$unique_evaluatees = count(array_unique(array_column($evaluations, 'evaluatee_name')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Overview Dashboard</title>
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
            max-width: 1400px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e1e8ed;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            display: block;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .content {
            padding: 30px;
        }

        .filters-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e1e8ed;
            border-radius: 25px;
            padding: 8px 20px;
            transition: all 0.3s ease;
            min-width: 300px;
        }

        .search-box:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box input {
            border: none;
            outline: none;
            padding: 8px;
            font-size: 14px;
            flex: 1;
            background: transparent;
        }

        .search-icon {
            color: #6c757d;
            margin-right: 10px;
        }

        .filter-dropdown {
            padding: 10px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 25px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-dropdown:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e1e8ed;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        th {
            padding: 20px 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid #f1f3f5;
            vertical-align: middle;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff, #f0f4ff);
            transform: scale(1.01);
        }

        .evaluation-id {
            font-weight: 600;
            color: #667eea;
            font-family: 'Courier New', monospace;
        }

        .date-cell {
            color: #6c757d;
            font-size: 14px;
        }

        .person-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .evaluation-type {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .type-promotion {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }

        .type-performance {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .type-other {
            background: linear-gradient(135deg, #f8d7da, #f1b0b7);
            color: #721c24;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .view-btn::after {
            content: '→';
            transition: transform 0.3s ease;
        }

        .view-btn:hover::after {
            transform: translateX(3px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-title {
                font-size: 24px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                padding: 20px;
                gap: 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .table-container {
                font-size: 14px;
            }
            
            th, td {
                padding: 12px 8px;
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
                <div class="header-title">
                    <div class="header-icon">📊</div>
                    Evaluation Dashboard
                </div>
                <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?= $total_evaluations ?></span>
                <span class="stat-label">Total Evaluations</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $promotion_recommendations ?></span>
                <span class="stat-label">Promotion Recommendations</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $unique_evaluators ?></span>
                <span class="stat-label">Active Evaluators</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $unique_evaluatees ?></span>
                <span class="stat-label">Evaluated Employees</span>
            </div>
        </div>

        <div class="content">
            <div class="filters-bar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Search evaluations..." onkeyup="filterTable()">
                </div>
                <select class="filter-dropdown" id="typeFilter" onchange="filterTable()">
                    <option value="">All Types</option>
                    <option value="promotion_recommendation">Promotion Recommendations</option>
                    <option value="performance">Performance Reviews</option>
                </select>
            </div>

            <?php if (count($evaluations) > 0): ?>
                <div class="table-container">
                    <div class="table-wrapper">
                        <table id="evaluationsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date Created</th>
                                    <th>Evaluator</th>
                                    <th>Evaluatee</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($evaluations as $index => $eval): ?>
                                <tr class="fade-in" style="animation-delay: <?= $index * 0.05 ?>s;">
                                    <td><span class="evaluation-id">#<?= str_pad($eval['evaluation_id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                                    <td class="date-cell"><?= date('M j, Y g:i A', strtotime($eval['created_at'])) ?></td>
                                    <td><span class="person-name"><?= htmlspecialchars($eval['evaluator_name']) ?></span></td>
                                    <td><span class="person-name"><?= htmlspecialchars($eval['evaluatee_name']) ?></span></td>
                                    <td>
                                        <span class="evaluation-type <?= $eval['evaluation_type'] === 'promotion_recommendation' ? 'type-promotion' : 'type-other' ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $eval['evaluation_type']))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($eval['evaluation_type'] === 'promotion_recommendation'): ?>
                                            <a class="view-btn" href="promotion_view.php?evaluatee_id=<?= $eval['evaluatee_id'] ?>">View Details</a>
                                        <?php else: ?>
                                            <a class="view-btn" href="view_evaluation.php?id=<?= $eval['evaluation_id'] ?>">View Details</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>No Evaluations Found</h3>
                    <p>No evaluations have been created yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const table = document.getElementById('evaluationsTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                
                let matchesSearch = false;
                let matchesType = true;

                // Check search criteria
                for (let j = 0; j < cells.length - 1; j++) {
                    if (cells[j].textContent.toLowerCase().includes(searchInput)) {
                        matchesSearch = true;
                        break;
                    }
                }

                // Check type filter
                if (typeFilter) {
                    const typeCell = cells[4];
                    const typeText = typeCell.textContent.toLowerCase().replace(/\s+/g, '_');
                    matchesType = typeText.includes(typeFilter);
                }

                // Show/hide row
                if ((searchInput === '' || matchesSearch) && matchesType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        // Add row animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>