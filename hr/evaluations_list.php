<?php
require __DIR__ . '/../db.php';

// Fetch distinct evaluations with basic info
$sql = "
    SELECT 
        e.id AS evaluation_id,
        e.created_at,
        e.evaluation_type,
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Evaluation Overview</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #f0f0f0; }
        a.view-btn {
            padding: 6px 12px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Evaluation Summary</h2>
    <a href="dashboard.php" style="text-decoration: none; padding: 8px 16px; background-color: #007BFF; color: white; border-radius: 4px;">&larr; Back to Dashboard</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Evaluation ID</th>
                <th>Date</th>
                <th>Evaluator</th>
                <th>Evaluatee</th>
                <th>Evaluation Type</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($evaluations as $eval): ?>
            <tr>
                <td><?= $eval['evaluation_id'] ?></td>
                <td><?= $eval['created_at'] ?></td>
                <td><?= htmlspecialchars($eval['evaluator_name']) ?></td>
                <td><?= htmlspecialchars($eval['evaluatee_name']) ?></td>
                <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $eval['evaluation_type']))) ?></td>
                <td><a class="view-btn" href="view_evaluation.php?id=<?= $eval['evaluation_id'] ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>