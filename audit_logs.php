<?php
include 'db.php'; // your DB connection

$conn = new mysqli("localhost", "root", "", "wellness");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT a.*, e.name 
        FROM audit_logs a 
        JOIN users e ON a.user_id = e.id 
        ORDER BY a.action_type DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Audit Log</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f1f5f9;
      padding: 2rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    th {
      background-color: #f3f4f6;
      font-weight: 600;
    }
    tr:hover {
      background-color: #f9fafb;
    }
  </style>
</head>
<body>

<h2>Employee Audit Log</h2>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Employee</th>
      <th>Type</th>
      <th>Action</th>
      <th>Date/Time</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if ($result->num_rows > 0) {
        $count = 1;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$count}</td>
                    <td>{$row['name']}</td>
                    <td>" . ucfirst($row['type']) . "</td>
                    <td>{$row['action']}</td>
                    <td>{$row['timestamp']}</td>
                  </tr>";
            $count++;
        }
    } else {
        echo "<tr><td colspan='5'>No logs found.</td></tr>";
    }
    ?>
  </tbody>
</table>

</body>
</html>
