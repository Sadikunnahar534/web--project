<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();
if ($_SESSION['role'] === 'admin') { header("Location: admin/dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT set_id FROM results WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$attempted_ids = [];
while ($row = $res->fetch_assoc()) { $attempted_ids[] = $row['set_id']; }
$stmt->close();

if (count($attempted_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($attempted_ids), '?'));
    $types = str_repeat('i', count($attempted_ids));
    $sql = "SELECT * FROM question_sets WHERE id NOT IN ($placeholders) ORDER BY set_number";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$attempted_ids);
    $stmt->execute();
    $available_sets = $stmt->get_result();
} else {
    $available_sets = $conn->query("SELECT * FROM question_sets ORDER BY set_number");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose Another Set - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card">
            <h2>Choose a Question Set</h2>
            <p style="color:#667; margin-bottom: 14px;">Pick any one of the remaining sets below. Same rules apply: 10 MCQ + 5 True/False, 15 minutes.</p>

            <?php if ($available_sets->num_rows === 0): ?>
                <div class="alert alert-info">You've already attempted all 15 sets. Great job covering the whole topic! 🎓</div>
                <a class="btn" href="results.php">View My Results</a>
            <?php else: ?>
                <div class="quiz-grid">
                    <?php while ($s = $available_sets->fetch_assoc()): ?>
                        <div class="card" style="margin-bottom:0;">
                            <div class="info-row"><div class="set-pill">#<?php echo $s['set_number']; ?></div><h3 style="margin:0;"><?php echo htmlspecialchars($s['title']); ?></h3></div>
                            <a class="btn" href="attempt_quiz.php?id=<?php echo $s['id']; ?>">Attempt this set</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
