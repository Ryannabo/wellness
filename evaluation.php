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

// Map role_id to role name
$role_map = [
    1 => 'employee',
    2 => 'manager'
];

// Fetch users by role
$employees = [];
$managers = [];

$result = $conn->query("SELECT id, username, role_id FROM users");
while ($row = $result->fetch_assoc()) {
    $role = $role_map[$row['role_id']] ?? null;

    if ($role === 'employee') {
        $employees[] = $row;
    } elseif ($role === 'manager') {
        $managers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluation Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 2rem;
            background: #f4f4f4;
        }
        h1 {
            text-align: center;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 0.75rem;
            text-align: center;
        }
        th {
            background-color: #eee;
        }
        textarea, input[type="text"], select {
            width: 100%;
            padding: 0.5rem;
            box-sizing: border-box;
        }
        button {
            padding: 0.75rem 2rem;
            background: #007BFF;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .dropdown {
            display: none;
            margin-top: 1rem;
        }
    </style>
    <script>
        function handleEvaluationTypeChange(value) {
            document.getElementById('employeeDropdown').style.display =
                (value === 'Employee to Employee' || value === 'Manager to Employee') ? 'block' : 'none';
            document.getElementById('managerDropdown').style.display =
                (value === 'Employee to Manager') ? 'block' : 'none';
        }
    </script>
</head>
<body>

<h1>General Productivity Evaluation Tool</h1>
<form action="submit_evaluation.php" method="POST">
    <div class="section">
        <label><input type="radio" name="evaluation_type" value="Employee to Employee" onclick="handleEvaluationTypeChange(this.value)" required> Employee to Employee</label>
        <label><input type="radio" name="evaluation_type" value="Manager to Employee" onclick="handleEvaluationTypeChange(this.value)"> Manager to Employee</label>
        <label><input type="radio" name="evaluation_type" value="Employee to Manager" onclick="handleEvaluationTypeChange(this.value)"> Employee to Manager</label>
        <label><input type="radio" name="evaluation_type" value="Self-Evaluation" onclick="handleEvaluationTypeChange(this.value)"> Self-Evaluation</label>
    </div>

    <!-- Employee Dropdown -->
    <div class="section dropdown" id="employeeDropdown">
        <label for="employee_id">Select Employee:</label>
        <select name="employee_id">
            <option value="">-- Choose Employee --</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['username']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Manager Dropdown -->
    <div class="section dropdown" id="managerDropdown">
        <label for="manager_id">Select Manager:</label>
        <select name="manager_id">
            <option value="">-- Choose Manager --</option>
            <?php foreach ($managers as $mgr): ?>
                <option value="<?= $mgr['id'] ?>"><?= htmlspecialchars($mgr['username']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Criteria Table -->
    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Criteria</th>
                    <th colspan="5">Rating (1–5)</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $criteria = [
                    "Task Completion", "Time Management", "Meeting Deadlines",
                    "Quality of Work", "Initiative", "Collaboration",
                    "Problem Solving", "Focus and Attention to Detail",
                    "Creativity and Innovation", "Goal Orientation"
                ];
                foreach ($criteria as $index => $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item) ?></td>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <td><input type="radio" name="rating[<?= $index ?>]" value="<?= $i ?>" required></td>
                    <?php endfor; ?>
                    <td><input type="text" name="comment[<?= $index ?>]" placeholder="Optional comment"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section" style="display: flex; justify-content: space-between;">
                <button type="submit">Submit Evaluation</button>
                <button type="button" onclick="history.back()">Back</button>
    </div>
</form>

</body>
</html>
