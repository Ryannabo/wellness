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

// Fetch users by role
$employees = [];
$managers = [];

$result = $conn->query("SELECT id, username, role_id FROM users");
while ($row = $result->fetch_assoc()) {
    if ($row['role_id'] === '2') {
        $employees[] = $row;
    } elseif ($row['role_id'] === '3') {
        $managers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Form</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .evaluation-type {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .radio-option {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.2rem 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .radio-option input[type="radio"]:checked + .radio-label {
            border-color: #42e695;
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 230, 149, 0.3);
        }

        .radio-icon {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e0;
            border-radius: 50%;
            position: relative;
            transition: all 0.3s ease;
        }

        .radio-option input[type="radio"]:checked + .radio-label .radio-icon {
            border-color: white;
            background: white;
        }

        .radio-option input[type="radio"]:checked + .radio-label .radio-icon::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #42e695;
        }

        .dropdown {
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        select {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1rem;
        }

        select:focus {
            outline: none;
            border-color: #42e695;
            box-shadow: 0 0 0 3px rgba(66, 230, 149, 0.1);
        }

        .criteria-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .criteria-table th {
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
            color: white;
            padding: 1.2rem 1rem;
            font-weight: 600;
            text-align: center;
            font-size: 0.9rem;
        }

        .criteria-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            background: white;
            transition: background-color 0.2s ease;
        }

        .criteria-table tr:hover td {
            background-color: #f0fdf4;
        }

        .criteria-table tr:last-child td {
            border-bottom: none;
        }

        .criteria-name {
            text-align: left !important;
            font-weight: 500;
            color: #2d3748;
            min-width: 200px;
        }

        .rating-cell {
            position: relative;
            padding: 0.5rem;
        }

        .rating-cell input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .rating-circle {
            width: 32px;
            height: 32px;
            border: 2px solid #cbd5e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.9rem;
            color: #718096;
        }

        .rating-cell input[type="radio"]:checked + .rating-circle {
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
            border-color: #42e695;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(66, 230, 149, 0.3);
        }

        .comment-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            resize: none;
        }

        .comment-input:focus {
            outline: none;
            border-color: #42e695;
            box-shadow: 0 0 0 3px rgba(66, 230, 149, 0.1);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 150px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(66, 230, 149, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 230, 149, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
            transform: translateY(-2px);
        }

        .rating-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #4a5568;
        }

        .legend-circle {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #cbd5e0;
        }

        .wellness-icon {
            color: #42e695;
            margin-right: 0.3rem;
        }

        @media (max-width: 768px) {
            .form-card {
                padding: 2rem 1.5rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .evaluation-type {
                flex-direction: column;
            }

            .criteria-table {
                font-size: 0.85rem;
            }

            .criteria-table th,
            .criteria-table td {
                padding: 0.8rem 0.5rem;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .rating-legend {
                gap: 1rem;
            }
        }
    </style>
    <script>
        function handleEvaluationTypeChange(value) {
            const employeeDropdown = document.getElementById('employeeDropdown');

            if (value === 'Manager to Employee') {
                employeeDropdown.style.display = 'block';
            } else {
                employeeDropdown.style.display = 'none';
            }
        }


        // Add smooth animations for rating selections
        document.addEventListener('DOMContentLoaded', function() {
            const ratingInputs = document.querySelectorAll('input[type="radio"][name^="rating"]');
            ratingInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Add a subtle animation effect
                    const circle = this.nextElementSibling;
                    circle.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        circle.style.transform = 'scale(1.1)';
                    }, 150);
                });
            });
        });
    </script>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-heart"></i> Wellness Assessment</h1>
        <p>Comprehensive mental health and wellbeing evaluation tool</p>
    </div>

    <div class="form-card">
        <form action="submit_evaluation.php" method="POST">
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Evaluation Type
                </div>
                <div class="evaluation-type">
                    <div class="radio-option">
                        <input type="radio" name="evaluation_type" value="Manager to Employee" id="manager-to-employee" onclick="handleEvaluationTypeChange(this.value)">
                        <label class="radio-label" for="manager-to-employee">
                            <div class="radio-icon"></div>
                            <div>
                                <div style="font-weight: 600;">Manager to Employee</div>
                                <div style="font-size: 0.9rem; opacity: 0.8;">Evaluate team member performance</div>
                            </div>
                        </label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="evaluation_type" value="Self-Evaluation" id="self-evaluation" onclick="handleEvaluationTypeChange(this.value)">
                        <label class="radio-label" for="self-evaluation">
                            <div class="radio-icon"></div>
                            <div>
                                <div style="font-weight: 600;">Self-Evaluation</div>
                                <div style="font-size: 0.9rem; opacity: 0.8;">Assess your own performance</div>
                            </div>
                        </label>
                    </div>
            </div>

            <!-- Employee Dropdown -->
            <div class="section dropdown" id="employeeDropdown">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    Select Employee
                </div>
                <select name="employee_id">
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Manager Dropdown -->
            <div class="section dropdown" id="managerDropdown">
                <div class="section-title">
                    <i class="fas fa-user-tie"></i>
                    Select Manager
                </div>
                <select name="manager_id">
                    <option value="">-- Choose Manager --</option>
                    <?php foreach ($managers as $mgr): ?>
                        <option value="<?= $mgr['id'] ?>"><?= htmlspecialchars($mgr['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Criteria Table -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-clipboard-check wellness-icon"></i>
                    Wellness Criteria
                </div>
                
                <div class="rating-legend">
                    <div class="legend-item">
                        <div class="legend-circle" style="background: #fee; border-color: #fcc;"></div>
                        <span>1 - Concerning</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-circle" style="background: #fef0e6; border-color: #fed7aa;"></div>
                        <span>2 - Needs Support</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-circle" style="background: #fef9e6; border-color: #fde68a;"></div>
                        <span>3 - Average</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-circle" style="background: #f0fdf4; border-color: #bbf7d0;"></div>
                        <span>4 - Good</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-circle" style="background: #42e695; border-color: #42e695;"></div>
                        <span>5 - Excellent</span>
                    </div>
                </div>

                <table class="criteria-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Wellness Criteria</th>
                            <th style="width: 8%;">1</th>
                            <th style="width: 8%;">2</th>
                            <th style="width: 8%;">3</th>
                            <th style="width: 8%;">4</th>
                            <th style="width: 8%;">5</th>
                            <th style="width: 35%;">Comments</th>
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
                            <td class="criteria-name"><?= htmlspecialchars($item) ?></td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <td class="rating-cell">
                                    <input type="radio" name="rating[<?= $index ?>]" value="<?= $i ?>" id="rating_<?= $index ?>_<?= $i ?>" required>
                                    <div class="rating-circle"><?= $i ?></div>
                                </td>
                            <?php endfor; ?>
                            <td>
                                <input type="text" name="comment[<?= $index ?>]" class="comment-input" placeholder="Share your thoughts or observations...">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-heart"></i>
                        Submit Assessment
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="history.back()">
                        <i class="fas fa-arrow-left"></i>
                        Go Back
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

</body>
</html>