<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();
if ($_SESSION['role'] === 'admin') { header("Location: admin/dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT r.*, qs.title, qs.set_number
    FROM results r
    JOIN question_sets qs ON r.set_id = qs.id
    WHERE r.user_id = ?
    ORDER BY r.taken_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result();

$attempted_count = $results->num_rows;
$has_more_sets_available = $attempted_count < TOTAL_SETS;

$just_taken = isset($_GET['justTaken']);
$just_score = intval($_GET['score'] ?? 0);
$just_total = intval($_GET['total'] ?? 0);
$just_result_id = intval($_GET['result_id'] ?? 0);
$just_set_id = intval($_GET['set_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Results - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/features.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <?php if ($just_taken): ?>
            <div class="card score-box">
                <?php if ($just_score == FULL_MARKS): ?>
                    🏆 Perfect Score! You got <?php echo $just_score; ?> / <?php echo $just_total; ?>
                    <div style="margin-top:14px;"><a class="btn btn-success" href="celebration.php?result_id=<?php echo $just_result_id; ?>">🎉 See Your Congratulations</a></div>
                <?php else: ?>
                    🎉 You scored <?php echo $just_score; ?> / <?php echo $just_total; ?>
                    <p style="color:#667; font-size:1rem; margin-top:8px;">Score 15/15 next time to unlock a special celebration video!</p>
                <?php endif; ?>
                <?php if ($just_result_id): ?>
                    <p style="margin-top:10px; font-size:1rem;"><a class="btn" href="result_report.php?result_id=<?php echo $just_result_id; ?>">📄 View Detailed Report (correct answers)</a></p>
                <?php endif; ?>
                <?php if ($just_set_id): ?>
                    <p style="margin-top:10px; font-size:1rem;"><a href="leaderboard.php?set_id=<?php echo $just_set_id; ?>">📊 See the result list for this set</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_more_sets_available): ?>
        <div class="card">
            <h3>Want to attempt another question set?</h3>
            <p style="color:#667;">Optional — try one more set from the remaining ones, or stop here.</p>
            <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
                <a class="btn btn-success" href="choose_set.php">Yes, give me another set</a>
                <a class="btn btn-secondary" href="quizzes.php">No, I'm done</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>My Results</h2>
            <table>
                <tr><th>Quiz Set</th><th>Score</th><th>Submitted</th><th>Time Taken</th><th>Report</th></tr>
                <?php if ($attempted_count === 0): ?>
                    <tr><td colspan="5">No quizzes attempted yet.</td></tr>
                <?php endif; ?>
                <?php while ($r = $results->fetch_assoc()): ?>
                    <tr>
                        <td>Set #<?php echo $r['set_number']; ?> — <?php echo htmlspecialchars($r['title']); ?></td>
                        <td><?php echo $r['score'] == FULL_MARKS ? '🏆 ' : ''; ?><?php echo $r['score'] . ' / ' . $r['total_questions']; ?></td>
                        <td><?php echo date("d M Y, h:i A", strtotime($r['taken_at'])); ?></td>
                        <td><?php echo $r['duration_seconds'] !== null ? floor($r['duration_seconds'] / 60) . 'm ' . str_pad($r['duration_seconds'] % 60, 2, '0', STR_PAD_LEFT) . 's' : '—'; ?></td>
                        <td>
                            <a href="result_report.php?result_id=<?php echo $r['id']; ?>">📄 View</a> |
                            <a href="download_report.php?result_id=<?php echo $r['id']; ?>">⬇ PDF</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
