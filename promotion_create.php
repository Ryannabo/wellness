<?php
// promotion_create.php

require_once 'db.php'; // Ensure this includes a proper PDO or MySQLi connection in $conn

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $user_id = (int) $_POST['user_id'];
    $current_position = trim($_POST['current_position']);
    $status = $_POST['status']; // Must be one of ENUM values
    $promotion_date = $_POST['promotion_date'];
    $evaluation_comments = trim($_POST['evaluation_comments']);

    // ENUM validation for status
    $valid_statuses = ['Pending', 'Approved', 'Rejected'];
    if (!in_array($status, $valid_statuses)) {
        die("Invalid status value.");
    }

    // Prepare and insert into the database
    $stmt = $conn->prepare("INSERT INTO promotion (user_id, current_position, status, promotion_date, evaluation_comments) 
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $current_position, $status, $promotion_date, $evaluation_comments);

    if ($stmt->execute()) {
        echo "✅ Promotion record added successfully.";

        // Optional: Insert into audit log
        // log_action($conn, 'Promotion Created', $user_id, $_SESSION['admin_id']); // pseudo function

        // Optional: Send notification
        // send_notification($user_id, "A new promotion request has been logged."); // pseudo function

    } else {
        echo "❌ Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!-- Promotion Form -->
<!DOCTYPE html>
<html>
<head>
    <title>Create Promotion</title>
</head>
<body>
    <h2>Create Promotion Record</h2>
    <form method="POST" action="promotion_create.php">
        <label for="user_id">Select Employee:</label>
        <select name="user_id" required>
            <?php
            $result = $conn->query("SELECT id, name FROM users ORDER BY name ASC");
            while ($row = $result->fetch_assoc()) {
                echo "<option value=\"{$row['id']}\">{$row['name']}</option>";
            }
            ?>
        </select><br><br>

        <label for="current_position">Current Position:</label>
        <input type="text" name="current_position" required><br><br>

        <label for="status">Status:</label>
        <select name="status" required>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
        </select><br><br>

        <label for="promotion_date">Promotion Date:</label>
        <input type="date" name="promotion_date" required><br><br>

        <label for="evaluation_comments">Evaluation Comments:</label><br>
        <textarea name="evaluation_comments" rows="4" cols="50" required></textarea><br><br>

        <input type="submit" value="Submit Promotion">
    </form>
</body>
</html>
try mo nga