<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

<?php
// Simulated user and dashboard data for glowing Schoology-like dashboard
$user = [
    'name' => 'Bright Student',
    'profile_pic' => 'https://i.pravatar.cc/60?img=3',
];

$courses = [
    [
        'title' => 'Literature & Poetry',
        'progress' => 0.75,
        'instructor' => 'Ms. Aurora Dawn',
        'color' => '#FFB347', // warm orange
    ],
    [
        'title' => 'Biology Basics',
        'progress' => 0.50,
        'instructor' => 'Dr. Fern Greene',
        'color' => '#77DD77', // soft green
    ],
    [
        'title' => 'Mathematics 101',
        'progress' => 0.33,
        'instructor' => 'Mr. Clay Summers',
        'color' => '#AEC6CF', // gentle blue
    ],
];

$announcements = [
    ['text' => 'New assignment posted for Literature.', 'date' => 'May 14'],
    ['text' => 'Biology lab rescheduled to May 20.', 'date' => 'May 12'],
    ['text' => 'Math quiz next week, prepare well!', 'date' => 'May 10'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Sunlit Schoology Dashboard Replica</title>
<link rel="stylesheet" href="styles.css" />
</head>
<body>
<header>
    <div class="topbar">
        <h1>Sunlit Schoology</h1>
        <div class="user-profile">
            <img src="<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile Picture" />
            <span><?= htmlspecialchars($user['name']) ?></span>
        </div>
    </div>
</header>

<div class="container">
    <nav class="sidebar">
        <ul>
            <li><a href="#" class="active">Dashboard</a></li>
            <li><a href="#">Courses</a></li>
            <li><a href="#">Calendar</a></li>
            <li><a href="#">Messages</a></li>
            <li><a href="#">Grades</a></li>
            <li><a href="#">Settings</a></li>
        </ul>
    </nav>

    <main>
        <section class="courses">
            <h2>Your Courses</h2>
            <div class="course-cards">
                <?php foreach($courses as $course): ?>
                    <article class="course-card" style="border-top-color: <?= $course['color'] ?>">
                        <h3><?= htmlspecialchars($course['title']) ?></h3>
                        <p>Instructor: <?= htmlspecialchars($course['instructor']) ?></p>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?= $course['progress'] * 100 ?>%; background-color: <?= $course['color'] ?>;"></div>
                        </div>
                        <small><?= intval($course['progress'] * 100) ?>% Complete</small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="announcements">
            <h2>Announcements</h2>
            <ul>
                <?php foreach($announcements as $ann): ?>
                    <li>
                        <strong><?= htmlspecialchars($ann['date']) ?>:</strong> <?= htmlspecialchars($ann['text']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>
</div>

<footer>
    <p>☀️ Radiant learning days &copy; <?= date('Y') ?> Bright School</p>
</footer>
</body>
</html>
