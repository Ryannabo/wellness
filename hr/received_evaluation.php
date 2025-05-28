<?php
// view_evaluation.php

require __DIR__ . '/../db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    echo "Access denied.";
    exit;
}

$evaluation_id = intval($_GET['id']);

// Fetch evaluation
$sql = "SELECT 
            e.*, 
            u1.name AS evaluator_name, 
            u2.name AS target_name 
        FROM evaluations e
        JOIN users u1 ON e.evaluator_id = u1.id
        LEFT JOIN users u2 ON e.target_id = u2.id
        WHERE e.id = $evaluation_id";

$result = $conn->query($sql);
$evaluation = $result->fetch_assoc();

if (!$evaluation) {
    echo "Evaluation not found.";
    exit;
}

// Fetch related items
$item_sql = "SELECT * FROM evaluation_items WHERE evaluation_id = $evaluation_id";
$item_result = $conn->query($item_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Evaluation Details</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

<h2>Evaluation Details</h2>
<p><strong>Evaluator:</strong> <?= htmlspecialchars($evaluation['evaluator_name']) ?></p>
<p><strong>Target:</strong> <?= htmlspecialchars($evaluation['target_name'] ?? 'N/A') ?></p>
<p><strong>Type:</strong> <?= htmlspecialchars($evaluation['evaluation_type']) ?></p>
<p><strong>Submitted At:</strong> <?= htmlspecialchars($evaluation['created_at']) ?></p>
<p><strong>General Criteria:</strong> <?= nl2br(htmlspecialchars($evaluation['criteria'])) ?></p>

<h3>Evaluation Items</h3>
<?php
if ($item_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Criteria</th><th>Rating</th><th>Comment</th></tr>";
    while ($item = $item_result->fetch_assoc()) {
        echo "<tr>
                <td>{$item['criteria']}</td>
                <td>{$item['rating']}</td>
                <td>{$item['comment']}</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "<p>No individual items found for this evaluation.</p>";
}

$conn->close();
?>

</body>
</html>
