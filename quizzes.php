<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/monthly_helpers.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header("Location: admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$assigned_number = get_assigned_set_number($user_id);

// Assigned set details
$stmt = $conn->prepare("SELECT * FROM question_sets WHERE set_number = ?");
$stmt->bind_param("i", $assigned_number);
$stmt->execute();
$assigned_set = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Has the student already attempted their assigned set?
$assigned_result = null;
if ($assigned_set) {
    $stmt = $conn->prepare("SELECT * FROM results WHERE user_id = ? AND set_id = ?");
    $stmt->bind_param("ii", $user_id, $assigned_set['id']);
    $stmt->execute();
    $assigned_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// All attempts by this student so far
$stmt = $conn->prepare("
    SELECT r.*, qs.title, qs.set_number
    FROM results r JOIN question_sets qs ON r.set_id = qs.id
    WHERE r.user_id = ?
    ORDER BY r.taken_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_attempts = $stmt->get_result();
$attempted_set_ids = [];
$attempt_rows = [];
while ($row = $all_attempts->fetch_assoc()) {
    $attempted_set_ids[] = $row['set_id'];
    $attempt_rows[] = $row;
}

$has_more_sets_available = count($attempted_set_ids) < TOTAL_SETS;

// Next / current monthly live quiz (if any)
$next_monthly = null;
$mres = $conn->query("SELECT * FROM monthly_quizzes ORDER BY start_time ASC");
while ($mq = $mres->fetch_assoc()) {
    if (monthly_status($mq) !== 'ended') { $next_monthly = $mq; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Exam - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/features.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card">
            <div class="info-row">
                <div class="set-pill">#<?php echo $assigned_number; ?></div>
                <div>
                    <h2 style="margin-bottom:2px;">Your Assigned Question Set</h2>
                    <p style="color:#667;">Topic: Web and Internet · Set <?php echo $assigned_number; ?> of <?php echo TOTAL_SETS; ?></p>
                </div>
            </div>

            <?php if (!$assigned_set): ?>
                <div class="alert alert-error">Question sets are not loaded yet. Please ask your instructor/admin to import the question bank.</div>
            <?php elseif (!$assigned_result): ?>
                <p><strong><?php echo htmlspecialchars($assigned_set['title']); ?></strong></p>
                <div class="alert alert-info">
                    📋 10 MCQs + 5 True/False questions &nbsp;·&nbsp; ⏱️ 15 minutes &nbsp;·&nbsp; 🏆 Score 15/15 for a special surprise!
                    <br>Once you click Start, the 15-minute timer begins immediately and cannot be paused.
                </div>
                <a class="btn btn-success" href="attempt_quiz.php?id=<?php echo $assigned_set['id']; ?>">Start My Exam</a>
            <?php else: ?>
                <p><strong><?php echo htmlspecialchars($assigned_set['title']); ?></strong> — already completed.</p>
                <div class="score-box" style="margin-top:14px;">
                    <?php if ($assigned_result['score'] == FULL_MARKS): ?>
                        🏆 Full Marks! <?php echo $assigned_result['score']; ?> / <?php echo $assigned_result['total_questions']; ?>
                        <div style="margin-top:10px;"><a class="btn" href="celebration.php?result_id=<?php echo $assigned_result['id']; ?>">View Your Celebration 🎉</a></div>
                    <?php else: ?>
                        You scored <?php echo $assigned_result['score']; ?> / <?php echo $assigned_result['total_questions']; ?>
                    <?php endif; ?>
                </div>
                <p style="margin-top:10px;"><a href="leaderboard.php?set_id=<?php echo $assigned_set['id']; ?>">📊 See the result list for this set</a></p>
            <?php endif; ?>
        </div>

        <?php if ($next_monthly): $mst = monthly_status($next_monthly); ?>
        <div class="card mq-card <?php echo ($mst === 'upcoming') ? '' : 'live'; ?>">
            <h3>🏆 Monthly Live Quiz: <?php echo htmlspecialchars($next_monthly['title']); ?></h3>
            <p style="color:#667; margin-bottom:10px;">
                <?php echo ($mst === 'upcoming') ? 'Starts' : 'Started'; ?>: <strong><?php echo date("d M Y, h:i A", strtotime($next_monthly['start_time'])); ?></strong>
                · <?php echo (int)$next_monthly['duration_minutes']; ?> minutes · everyone plays at the same time, highest mark wins!
            </p>
            <a class="btn <?php echo ($mst === 'upcoming') ? '' : 'btn-success'; ?>" href="monthly.php"><?php echo ($mst === 'upcoming') ? 'View details' : 'Join now'; ?></a>
        </div>
        <?php endif; ?>

        <?php if ($assigned_result && $has_more_sets_available): ?>
        <div class="card">
            <h3>Want to attempt another question set?</h3>
            <p style="color:#667;">This is completely optional — you can attempt one more set from the remaining ones, or stop here.</p>
            <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
                <a class="btn btn-success" href="choose_set.php">Yes, give me another set</a>
                <a class="btn btn-secondary" href="results.php">No, I'm done</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($attempt_rows) > 0): ?>
        <div class="card">
            <h3>Your Attempt History</h3>
            <table>
                <tr><th>Set</th><th>Score</th><th>Date &amp; Time</th><th>Report</th></tr>
                <?php foreach ($attempt_rows as $r): ?>
                    <tr>
                        <td>Set #<?php echo $r['set_number']; ?> — <?php echo htmlspecialchars($r['title']); ?></td>
                        <td><?php echo $r['score'] == FULL_MARKS ? '🏆 ' : ''; ?><?php echo $r['score'] . ' / ' . $r['total_questions']; ?></td>
                        <td><?php echo date("d M Y, h:i A", strtotime($r['taken_at'])); ?></td>
                        <td><a href="result_report.php?result_id=<?php echo $r['id']; ?>">📄 View</a> | <a href="download_report.php?result_id=<?php echo $r['id']; ?>">⬇ PDF</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
