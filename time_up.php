<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();

$set_id = intval($_GET['set_id'] ?? 0);
$set = null;
if ($set_id) {
    $stmt = $conn->prepare("SELECT * FROM question_sets WHERE id = ?");
    $stmt->bind_param("i", $set_id);
    $stmt->execute();
    $set = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Time's Up - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card encourage-box">
            <h1 style="font-size:2rem;">⏰ Time's Up!</h1>
            <p style="font-size:1.1rem; margin:14px 0;">
                Your 15-minute time limit for
                <?php echo $set ? "<strong>Set #" . $set['set_number'] . " — " . htmlspecialchars($set['title']) . "</strong>" : "this exam"; ?>
                ran out, so your exam was <strong>not submitted</strong>.
            </p>
            <p style="color:#667;">No worries — this attempt was not recorded. You can start again whenever you're ready and manage your time carefully.</p>
            <div style="margin-top:16px;">
                <a class="btn" href="quizzes.php">Back to My Exam</a>
            </div>
        </div>
    </div>
</body>
</html>
