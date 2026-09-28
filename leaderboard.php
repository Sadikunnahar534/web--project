<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();

$set_id = intval($_GET['set_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM question_sets WHERE id = ?");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$set = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$set) {
    die("Question set not found.");
}

$stmt = $conn->prepare("SELECT r.user_id, u.name, r.score, r.total_questions, r.taken_at, r.duration_seconds FROM results r JOIN users u ON u.id = r.user_id WHERE r.set_id = ? ORDER BY r.score DESC, r.duration_seconds ASC, r.taken_at ASC");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$rows = $stmt->get_result();
$entries = [];
while ($row = $rows->fetch_assoc()) { $entries[] = $row; }
$back_href = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'quizzes.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result List - Set #<?php echo $set['set_number']; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card">
            <div class="info-row">
                <div class="set-pill">#<?php echo $set['set_number']; ?></div>
                <div>
                    <h2 style="margin-bottom:2px;"><?php echo htmlspecialchars($set['title']); ?></h2>
                    <p style="color:#667; margin:0;">Result list — <?php echo count($entries); ?> attempt(s) so far, ranked by highest mark (tie → less time taken).</p>
                </div>
            </div>

            <?php if (count($entries) === 0): ?>
                <div class="alert alert-info" style="margin-top:14px;">No one has attempted this set yet.</div>
            <?php else: ?>
                <table style="margin-top:16px;">
                    <tr><th>Rank</th><th>Student</th><th>Mark</th><th>Time Taken</th><th>Date</th><th>Time</th></tr>
                    <?php foreach ($entries as $i => $e): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($e['name']); ?><?php echo ((int)$e['user_id'] === (int)$_SESSION['user_id']) ? ' (you)' : ''; ?></td>
                            <td>
                                <span class="score-chip <?php echo $e['score'] == FULL_MARKS ? 'perfect' : ''; ?>">
                                    <?php echo $e['score'] == FULL_MARKS ? '🏆 ' : ''; ?><?php echo $e['score'] . ' / ' . $e['total_questions']; ?>
                                </span>
                            </td>
                            <td><?php echo $e['duration_seconds'] !== null ? floor($e['duration_seconds'] / 60) . 'm ' . str_pad($e['duration_seconds'] % 60, 2, '0', STR_PAD_LEFT) . 's' : '—'; ?></td>
                            <td><?php echo date("d M Y", strtotime($e['taken_at'])); ?></td>
                            <td><?php echo date("h:i A", strtotime($e['taken_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <div style="margin-top:16px;">
                <a class="btn btn-secondary" href="<?php echo $back_href; ?>">&larr; Back</a>
            </div>
        </div>
    </div>
</body>
</html>
