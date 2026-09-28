<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require_admin();
$nav_in_admin = true;

$students = $conn->query("SELECT id, name, email, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC");

$results = $conn->query("
    SELECT r.id AS result_id, u.name AS student_name, qs.set_number, qs.title AS quiz_title, r.score, r.total_questions, r.taken_at
    FROM results r
    JOIN users u ON r.user_id = u.id
    JOIN question_sets qs ON r.set_id = qs.id
    ORDER BY r.taken_at DESC
");

$stat_students = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
$stat_attempts = $conn->query("SELECT COUNT(*) c FROM results")->fetch_assoc()['c'];
$stat_perfect = $conn->query("SELECT COUNT(*) c FROM results WHERE score = " . FULL_MARKS)->fetch_assoc()['c'];
$stat_sets = $conn->query("SELECT COUNT(*) c FROM question_sets")->fetch_assoc()['c'];

$stat_doubts = $conn->query("SELECT COUNT(*) c FROM question_reports WHERE status='open'")->fetch_assoc()['c'];

$sets_list = $conn->query("SELECT * FROM question_sets ORDER BY set_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/features.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>Admin Panel</h2>

        <div class="card">
            <div class="stat-grid">
                <div class="stat-box"><div class="num"><?php echo $stat_students; ?></div><div class="label">Registered Students</div></div>
                <div class="stat-box"><div class="num"><?php echo $stat_attempts; ?></div><div class="label">Total Attempts</div></div>
                <div class="stat-box"><div class="num"><?php echo $stat_perfect; ?></div><div class="label">🏆 Perfect Scores</div></div>
                <div class="stat-box"><div class="num"><?php echo $stat_sets; ?></div><div class="label">Question Sets</div></div>
            </div>
            <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap;">
                <a class="btn" href="manage_quizzes.php">Manage Question Sets</a>
                <a class="btn btn-secondary" href="manage_videos.php">Manage Celebration Videos 🎬</a>
                <a class="btn btn-success" href="manage_monthly.php">🏆 Monthly Quiz</a>
                <a class="btn btn-danger" href="question_reports.php">🚩 Doubts (<?php echo $stat_doubts; ?> open)</a>
            </div>
        </div>

        <div class="card">
            <h3>Score Lists by Set (anonymous to students, full detail for you)</h3>
            <div class="quiz-grid">
                <?php while ($s = $sets_list->fetch_assoc()): ?>
                    <div class="card" style="margin-bottom:0;">
                        <div class="info-row"><div class="set-pill">#<?php echo $s['set_number']; ?></div><strong><?php echo htmlspecialchars($s['title']); ?></strong></div>
                        <a href="../leaderboard.php?set_id=<?php echo $s['id']; ?>">View score list &rarr;</a>
                        &nbsp;|&nbsp;
                        <a href="manage_questions.php?set_id=<?php echo $s['id']; ?>">Edit questions &rarr;</a>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="card">
            <h3>Registered Students</h3>
            <table>
                <tr><th>Name</th><th>Email</th><th>Joined</th></tr>
                <?php while ($s = $students->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                        <td><?php echo date("d M Y, h:i A", strtotime($s['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h3>All Quiz Results (with student names — admin only)</h3>
            <table>
                <tr><th>Student</th><th>Set</th><th>Score</th><th>Date &amp; Time</th><th>Report</th></tr>
                <?php while ($r = $results->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                        <td>#<?php echo $r['set_number']; ?> — <?php echo htmlspecialchars($r['quiz_title']); ?></td>
                        <td><?php echo $r['score'] == FULL_MARKS ? '🏆 ' : ''; ?><?php echo $r['score'] . ' / ' . $r['total_questions']; ?></td>
                        <td><?php echo date("d M Y, h:i A", strtotime($r['taken_at'])); ?></td>
                        <td><a href="../result_report.php?result_id=<?php echo $r['result_id']; ?>">📄 View</a></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
