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
    $roleName = $role_map[$row['role_id']] ?? 'unknown';
    if ($roleName === 'employee') {
        $employees[] = $row;
    } elseif ($roleName === 'manager') {
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
        background-color: #f2f4f8;
        margin: 0;
        padding: 20px;
        color: #333;
    }

    h1 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 30px;
    }

    form {
        background: #fff;
        padding: 30px;
        max-width: 900px;
        margin: auto;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    }

    .section {
        margin-bottom: 25px;
    }

    label {
        margin-right: 20px;
        font-size: 16px;
        cursor: pointer;
    }

    select, input[type="text"] {
        padding: 8px;
        width: 100%;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: 15px;
        margin-top: 8px;
    }

    .dropdown {
        display: none;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th, td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
        font-size: 14px;
    }

    th {
        background-color: #f0f2f5;
        color: #333;
    }

    tr:nth-child(even) {
        background-color: #fafafa;
    }

    input[type="radio"] {
        transform: scale(1.2);
        cursor: pointer;
    }

    input[type="text"]::placeholder {
        color: #aaa;
    }

    button {
        padding: 12px 25px;
        font-size: 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    button[type="submit"] {
        background-color: #3498db;
        color: white;
    }

    button[type="button"] {
        background-color: #bdc3c7;
        color: white;
    }

    button:hover {
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        table, th, td {
            font-size: 12px;
        }

        button {
            width: 100%;
            margin-top: 10px;
        }

        .section {
            margin-bottom: 15px;
        }
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

<h1>General Wellness Evaluation Tool</h1>
<form action="submit_evaluation.php" method="POST">
    <div class="section">
        <label><input type="radio" name="evaluation_type" value="Employee to Employee" onclick="handleEvaluationTypeChange(this.value)" required> Employee to Employee</label>
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
                    "Stress Management", "Work-Life Balance", "Emotional Stability",
                    "Healthy Communication", "Healthy Communication", "Relationship with Others",
                    "Energy and Motivation", "Positivity and Attitude",
                    "Participation in Wellness Activities", "Openness to Feedback and Help", "General Happiness at Work"
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
