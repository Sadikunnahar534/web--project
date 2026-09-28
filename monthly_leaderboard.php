<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/monthly_helpers.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM monthly_quizzes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$quiz) { die("Monthly quiz not found."); }

list($start, $end) = monthly_times($quiz);
$status = monthly_status($quiz);
$ended = ($status === 'ended');
$back = $is_admin ? 'admin/manage_monthly.php' : 'monthly.php';

$ranking = $ended ? monthly_ranking($conn, $id) : [];
$winner = $ranking[0] ?? null;

$i_am_winner = ($winner && (int)$winner['user_id'] === $user_id);
$can_watch = ($i_am_winner || $is_admin);          // winner (and admin) get the video option
$videos = $can_watch ? celebration_videos() : [];
$wait_seconds = max(0, $end + MONTHLY_SUBMIT_GRACE_SECONDS - time());

$stmt = $conn->prepare("SELECT id FROM monthly_attempts WHERE monthly_id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$i_submitted = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Quiz Results - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/features.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <?php if (!$ended): ?>
            <div class="card">
                <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                <?php if (isset($_GET['submitted']) || $i_submitted): ?>
                    <div class="alert alert-success">✅ Your answers have been submitted!</div>
                <?php endif; ?>
                <p>Results and the winner will be announced when the quiz ends at <strong><?php echo date("d M Y, h:i:s A", $end); ?></strong>.</p>
                <?php if (time() >= $start): ?>
                    <p style="color:#667;">Results in</p>
                    <div class="countdown" id="waitTimer" data-left="<?php echo $wait_seconds; ?>">--:--</div>
                    <p style="color:#8a93a8; font-size:0.85rem; margin:6px 0 12px;">This page refreshes by itself when the results are ready.</p>
                <?php endif; ?>
                <a class="btn btn-secondary" href="<?php echo $back; ?>">&larr; Back</a>
            </div>
        <?php else: ?>

            <?php if ($winner): ?>
                <div class="winner-box celebration-wrap" id="confettiHost">
                    <div style="font-size:3rem;">🏆</div>
                    <?php if ($i_am_winner): ?>
                        <h1>🎉 CONGRATULATIONS <?php echo htmlspecialchars(strtoupper($winner['name'])); ?>! 🎉</h1>
                        <h2 style="color:#5a4600;">You are the WINNER of <?php echo htmlspecialchars($quiz['title']); ?>!</h2>
                    <?php else: ?>
                        <h1>🎉 CONGRATULATIONS <?php echo htmlspecialchars(strtoupper($winner['name'])); ?>! 🎉</h1>
                        <h2 style="color:#5a4600;">Winner of <?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <?php endif; ?>
                    <p style="color:#8a6d00; font-size:1.1rem;">
                        Scored <strong><?php echo $winner['score'] . ' / ' . $winner['total_questions']; ?></strong>
                        in <?php echo format_seconds_short($winner['duration_seconds']); ?>
                    </p>

                    <?php if ($can_watch): ?>
                        <?php if (count($videos) > 0): ?>
                            <div id="videoArea" style="margin-top:14px;">
                                <button type="button" id="watchBtn">🎬 Watch <?php echo $i_am_winner ? 'your' : 'the'; ?> winner video</button>
                                <div id="videoBox" style="display:none;">
                                    <video id="winnerVideo" class="celebration-video" controls playsinline></video>
                                    <?php if (count($videos) > 1): ?>
                                        <button type="button" id="anotherBtn" class="btn-secondary">🔀 Play another video</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" style="max-width:480px; margin:16px auto 0;">🎬 No celebration video is available yet — the admin can upload one from Admin → Manage Celebration Videos.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Nobody participated in this quiz.</div>
            <?php endif; ?>

            <div class="card">
                <h3>Final Ranking — <?php echo htmlspecialchars($quiz['title']); ?></h3>
                <p style="color:#667; margin-bottom:10px;">Ranked by highest score; if scores are equal, the student who took less time ranks higher. <?php echo count($ranking); ?> participant(s).</p>
                <?php if (count($ranking) > 0): ?>
                <table>
                    <tr><th>Rank</th><th>Name</th><th>Score</th><th>Time Taken</th><th>Submitted</th></tr>
                    <?php foreach ($ranking as $i => $r): ?>
                        <tr class="<?php echo ((int)$r['user_id'] === $user_id) ? 'me' : ($i === 0 ? 'winner-row' : ''); ?>">
                            <td><?php echo $i === 0 ? '🏆 1' : $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($r['name']); ?><?php echo ((int)$r['user_id'] === $user_id) ? ' (you)' : ''; ?></td>
                            <td><?php echo $r['score'] . ' / ' . $r['total_questions']; ?></td>
                            <td><?php echo format_seconds_short($r['duration_seconds']); ?></td>
                            <td><?php echo date("h:i:s A", strtotime($r['submitted_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
                <div style="margin-top:16px;"><a class="btn btn-secondary" href="<?php echo $back; ?>">&larr; Back</a></div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$ended): ?>
    <script>
        (function () {
            var el = document.getElementById("waitTimer");
            if (!el) return;
            var left = parseInt(el.getAttribute("data-left"), 10);
            function draw() {
                var m = Math.floor(left / 60), s = left % 60;
                el.textContent = (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
            }
            draw();
            setInterval(function () {
                left--;
                if (left <= 0) { location.reload(); return; }
                draw();
            }, 1000);
        })();
    </script>
    <?php endif; ?>

    <?php if ($ended && $winner): ?>
    <script src="js/validate.js"></script>
    <?php if ($can_watch && count($videos) > 0): ?>
    <script>
        (function () {
            var files = <?php echo json_encode(array_map(function ($f) { return 'assets/videos/' . rawurlencode($f); }, $videos)); ?>;
            var idx = Math.floor(Math.random() * files.length);
            var btn = document.getElementById("watchBtn");
            var box = document.getElementById("videoBox");
            var vid = document.getElementById("winnerVideo");
            var another = document.getElementById("anotherBtn");
            function play() { vid.src = files[idx]; vid.play().catch(function () {}); }
            btn.addEventListener("click", function () {
                btn.style.display = "none";
                box.style.display = "block";
                play();
            });
            if (another) another.addEventListener("click", function () {
                idx = (idx + 1) % files.length;
                play();
            });
        })();
    </script>
    <?php endif; ?>
    <?php endif; ?>
</body>
</html>
