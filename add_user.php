//not used

<?php
session_start();
require __DIR__ . '/db.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';
$genders = [];
$roles = [];
$csrf_token = bin2hex(random_bytes(32));

try {
    // Fetch roles and genders from DB
    $genders = $pdo->query("SELECT id, value FROM genders ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($genders) || empty($roles)) {
        throw new Exception("System configuration error - roles or genders missing.");
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Security verification failed.");
        }

        // [Keep all your existing validation and input processing code here]
        // [No changes needed in the user creation logic itself]

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users 
            (name, username, email, password, role_id, gender_id, contact_number, emergency_number, address, birthday)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name, $username, $email, $hashedPassword, $role_id, $gender_id,
            $contact_number, $emergency_number, $address, $birthday
        ]);

        $success = "User account successfully created.";

    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$_SESSION['csrf_token'] = $csrf_token;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User</title>
    <style>
        body {
            background: #f8fafc;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .form-container {
            background: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 650px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: .5rem;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: .75rem;
            border: 1px solid #cbd5e1;
            border-radius: .5rem;
        }
        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
            padding: .75rem 1.5rem;
            border: none;
            border-radius: .5rem;
            cursor: pointer;
            width: 100%;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: .5rem;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: .5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<div class="form-container">
    <h2>Create User Account</h2>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

        <div class="grid">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" pattern="[a-zA-Z0-9_]{3,20}" required>
            </div>
        </div>

        <div class="grid">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$" required>
            </div>
        </div>

        <div class="grid">
            <div class="form-group">
                <label>Role</label>
                <select name="role_id" class="form-control" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars(ucfirst($role['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Gender</label>
                <select name="gender_id" class="form-control" required>
                    <option value="">-- Select Gender --</option>
                    <?php foreach ($genders as $gender): ?>
                        <option value="<?= (int)$gender['id'] ?>"><?= htmlspecialchars($gender['value']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid">
            <div class="form-group">
                <label>Contact Number</label>
                <input type="tel" name="contact_number" class="form-control" pattern="[0-9]{11}" required>
            </div>

            <div class="form-group">
                <label>Emergency Number</label>
                <input type="tel" name="emergency_number" class="form-control" pattern="[0-9]{11}" required>
            </div>
        </div>

        <div class="form-group">
            <label>Address</label>
            <textarea name="address" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-group">
            <label>Birthday</label>
            <input type="date" name="birthday" class="form-control" max="<?= date('Y-m-d') ?>" required>
        </div>

        <button type="submit" class="btn-primary">Create User</button>
    </form>
</div>
</body>
</html>
