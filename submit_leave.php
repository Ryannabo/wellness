<?php
session_start();
require_once 'db.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'] ?? ''; // Optional: if passed
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$leave_type = $_POST['leave_type'] ?? '';
$reason = $_POST['reason'] ?? '';
$date_filed = date('Y-m-d');
$attachment_path = null;

// Validate required fields
if (empty($start_date) || empty($end_date) || empty($leave_type) || empty($reason)) {
    die("All fields are required.");
}

// Handle file upload if provided
if (!empty($_FILES['attachment']['name'])) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = basename($_FILES["attachment"]["name"]);
    $target_file = $target_dir . time() . "_" . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Optional: Validate file types
    $allowed_types = ['pdf', 'png', 'jpg', 'jpeg'];
    if (!in_array($file_type, $allowed_types)) {
        die("Invalid file type. Only PDF, PNG, JPG, and JPEG allowed.");
    }

    if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
        $attachment_path = $target_file;
    } else {
        die("Error uploading the file.");
    }
}

// Insert leave request into the database
$stmt = $conn->prepare("INSERT INTO leave_requests (user_id, start_date, end_date, leave_type, reason, date_filed, attachment) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssss", $user_id, $start_date, $end_date, $leave_type, $reason, $date_filed, $attachment_path);
if ($stmt->execute()) {
    echo "✅ Leave request submitted successfully.";
    echo '<br><a href="dashboard.php">Return to Dashboard</a>';
} else {
    echo "❌ Error submitting request: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
