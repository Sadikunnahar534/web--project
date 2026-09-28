<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'];
$result_id = intval($_GET['result_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT r.*, qs.title, qs.set_number
    FROM results r JOIN question_sets qs ON r.set_id = qs.id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->bind_param("ii", $result_id, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result || $result['score'] != FULL_MARKS) {
    header("Location: results.php");
    exit();
}

// Pick a random celebration video from assets/videos/ (admin can add more anytime).
$video_dir = __DIR__ . '/assets/videos';
$video_web_dir = 'assets/videos';
$videos = [];
if (is_dir($video_dir)) {
    foreach (scandir($video_dir) as $f) {
        if (preg_match('/\.(mp4|webm|ogg)$/i', $f)) {
            $videos[] = $f;
        }
    }
}
$chosen_video = null;
if (count($videos) > 0) {
    $chosen_video = $videos[array_rand($videos)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Congratulations! - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card celebration-wrap" id="confettiHost">
            <h1>🎉 CONGRATULATIONS <?php echo htmlspecialchars(strtoupper($_SESSION['name'])); ?>! 🎉</h1>
            <p style="font-size:1.2rem; color:#5a4600; margin:8px 0 4px;">You scored a PERFECT <?php echo $result['score']; ?> / <?php echo $result['total_questions']; ?>!</p>
            <p style="color:#8a6d00;">Set #<?php echo $result['set_number']; ?> — <?php echo htmlspecialchars($result['title']); ?> · Web &amp; Internet</p>

            <?php if ($chosen_video): ?>
                <video class="celebration-video" src="<?php echo $video_web_dir . '/' . rawurlencode($chosen_video); ?>" controls autoplay muted playsinline></video>
            <?php else: ?>
                <div class="alert alert-info" style="max-width:480px; margin:16px auto;">🎬 No celebration video is available yet — ask your admin to add one.</div>
            <?php endif; ?>

            <p style="font-size:1.05rem; font-weight:600; color:#1b2a4a; margin-top:10px;">🏅 You are a Web &amp; Internet Champion — outstanding work!</p>

            <div style="margin-top:16px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                <a class="btn" href="leaderboard.php?set_id=<?php echo $result['set_id']; ?>">See the score list for this set</a>
                <a class="btn btn-secondary" href="results.php">Back to My Results</a>
            </div>
        </div>
    </div>

    <script src="js/validate.js"></script>
</body>
</html>
