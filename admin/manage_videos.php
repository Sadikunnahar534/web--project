<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require_admin();
$nav_in_admin = true;

$video_dir = __DIR__ . '/../assets/videos';
$errors = [];
$success = "";

// ---- UPLOAD a new video ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_video'])) {
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please choose a valid video file to upload.";
    } else {
        $orig_name = $_FILES['video']['name'];
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed = ['mp4', 'webm', 'ogg'];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Only .mp4, .webm or .ogg video files are allowed.";
        } elseif ($_FILES['video']['size'] > 50 * 1024 * 1024) {
            $errors[] = "Video is too large (max 50MB).";
        } else {
            $safe_name = 'celebration_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($orig_name, PATHINFO_FILENAME)) . '.' . $ext;
            $target = $video_dir . '/' . $safe_name;
            if (!is_dir($video_dir)) { mkdir($video_dir, 0755, true); }
            if (move_uploaded_file($_FILES['video']['tmp_name'], $target)) {
                $success = "Video uploaded successfully! It will now show up randomly for students who score full marks.";
            } else {
                $errors[] = "Upload failed. Check folder permissions for assets/videos.";
            }
        }
    }
}

// ---- DELETE a video ----
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']); // basename() prevents path traversal
    $target = $video_dir . '/' . $file;
    if (is_file($target) && preg_match('/\.(mp4|webm|ogg)$/i', $file)) {
        unlink($target);
    }
    header("Location: manage_videos.php");
    exit();
}

$videos = [];
if (is_dir($video_dir)) {
    foreach (scandir($video_dir) as $f) {
        if (preg_match('/\.(mp4|webm|ogg)$/i', $f)) {
            $videos[] = $f;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Celebration Videos - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>🎬 Manage Celebration Videos</h2>
        <p style="color:#667;">These videos play automatically (a random one is picked) whenever a student scores <?php echo FULL_MARKS; ?>/<?php echo FULL_MARKS; ?>. Add as many as you like — no code changes needed.</p>

        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Upload a New Video</h3>
            <form method="POST" action="manage_videos.php" enctype="multipart/form-data">
                <label for="video">Video file (.mp4, .webm, .ogg — max 50MB)</label>
                <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg" required>
                <button type="submit" name="upload_video">Upload Video</button>
            </form>
        </div>

        <div class="card">
            <h3>Current Videos (<?php echo count($videos); ?>)</h3>
            <?php if (count($videos) === 0): ?>
                <div class="alert alert-info">No videos yet — upload one above.</div>
            <?php else: ?>
                <div class="quiz-grid">
                    <?php foreach ($videos as $v): ?>
                        <div class="card" style="margin-bottom:0;">
                            <video src="../assets/videos/<?php echo rawurlencode($v); ?>" controls style="width:100%; border-radius:8px;"></video>
                            <p style="font-size:0.8rem; color:#8a93a8; margin:8px 0; word-break:break-all;"><?php echo htmlspecialchars($v); ?></p>
                            <a href="manage_videos.php?delete=<?php echo rawurlencode($v); ?>"
                               onclick="return confirm('Delete this video?');" style="color:#e74c3c;">Delete</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
