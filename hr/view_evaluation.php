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
<html>
<head>
    <title>Evaluation Details</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #f0f0f0; }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 6px 12px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <a class="back-btn" href="evaluations_list.php">&larr; Back</a>

    <h2>Evaluation Details</h2>
    <p><strong>Date:</strong> <?= $info['created_at'] ?></p>
    <p><strong>Evaluator:</strong> <?= htmlspecialchars($info['evaluator_name']) ?></p>
    <p><strong>Evaluatee:</strong> <?= htmlspecialchars($info['evaluatee_name']) ?></p>

    <table>
        <thead>
            <tr>
                <th>Criteria</th>
                <th>Rating</th>
                <th>Comment</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['criteria']) ?></td>
                <td><?= htmlspecialchars($item['rating']) ?></td>
                <td><?= htmlspecialchars($item['comment']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
