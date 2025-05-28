<?php
// Include your database connection
require 'db.php';

// Query to fetch promotion details from the database
$stmt = $conn->prepare("SELECT name, time, date, position, promotion_status FROM promotions");
$stmt->execute();

// Fetch the promotion data
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return the data as JSON
echo json_encode($promotions);
?>
