<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "wellness");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Retrieve form data
$evaluator_id = $_SESSION['user_id'];
$evaluation_type = $_POST['evaluation_type'];
$employee_id = $_POST['employee_id'] ?? null;
$manager_id = $_POST['manager_id'] ?? null;
$ratings = $_POST['rating'];
$comments = $_POST['comment'];

// Determine evaluatee ID
switch ($evaluation_type) {
    case 'Employee to Employee':
    case 'Manager to Employee':
        $evaluatee_id = $employee_id;
        break;
    case 'Promotion Recommendation':
        $evaluatee_id = $employee_id;
        break;
    case 'Employee to Manager':
        $evaluatee_id = $manager_id;
        break;
    case 'Self-Evaluation':
        $evaluatee_id = $evaluator_id;
        break;
    default:
        die("Invalid evaluation type.");
}

// Insert the evaluation record
$stmt = $conn->prepare("INSERT INTO evaluations (evaluator_id, evaluatee_id, evaluation_type, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $evaluator_id, $evaluatee_id, $evaluation_type);
$stmt->execute();
$evaluation_id = $stmt->insert_id;
$stmt->close();

// Insert ratings
$criteria_list = [
    "Task Completion", "Time Management", "Meeting Deadlines",
    "Quality of Work", "Initiative", "Collaboration",
    "Problem Solving", "Focus and Attention to Detail",
    "Creativity and Innovation", "Goal Orientation"
];

$stmt = $conn->prepare("INSERT INTO evaluation_items (evaluation_id, criteria, rating, comment) VALUES (?, ?, ?, ?)");
foreach ($criteria_list as $index => $criteria) {
    $rating = $ratings[$index] ?? null;
    $comment = $comments[$index] ?? '';
    $stmt->bind_param("isis", $evaluation_id, $criteria, $rating, $comment);
    $stmt->execute();
}
$stmt->close();

$conn->close();

// Redirect or success message
echo "<script>alert('Evaluation submitted successfully!'); window.history.back();</script>";
