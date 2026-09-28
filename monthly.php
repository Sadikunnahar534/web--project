<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/monthly_helpers.php';
require_login();
if ($_SESSION['role'] === 'admin') { header("Location: admin/manage_monthly.php"); exit(); }

$user_id = (int)$_SESSION['user_id'];
$now = time();

$quizzes = [];
$res = $conn->query("SELECT mq.*, (SELECT COUNT(*) FROM monthly_attempts ma WHERE ma.monthly_id = mq.id) AS participants FROM monthly_quizzes mq ORDER BY mq.start_time DESC");
while ($row = $res->fetch_assoc()) { $quizzes[] = $row; }

$mine = [];
$stmt = $conn->prepare("SELECT monthly_id, score, total_questions FROM monthly_attempts WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mr = $stmt->get_result();
while ($row = $mr->fetch_assoc()) { $mine[(int)$row['monthly_id']] = $row; }
$stmt->close();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Quiz - Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/features.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <h2>🏆 Monthly Live Quiz</h2>
        <p style="color:#667; margin-bottom:16px;">Everyone takes the same quiz at the same time. Join within <?php echo intdiv(MONTHLY_JOIN_GRACE_SECONDS, 60); ?> minutes of the start time — the quiz ends for all students together. The highest mark wins (tie → less time taken).</p>

        <?php if ($msg === 'closed'): ?>
            <div class="alert alert-error">The join window for that quiz is closed.</div>
        <?php elseif ($msg === 'late'): ?>
            <div class="alert alert-error">Time was over, so that submission was not accepted.</div>
        <?php elseif ($msg === 'noq'): ?>
            <div class="alert alert-error">This quiz has no questions yet. Please contact the admin.</div>
        <?php endif; ?>

        <?php if (count($quizzes) === 0): ?>
            <div class="alert alert-info">No monthly quiz has been scheduled yet. Check back soon!</div>
        <?php endif; ?>

        <?php foreach ($quizzes as $q):
            $st = monthly_status($q, $now);
            list($start, $end) = monthly_times($q);
            $id = (int)$q['id'];
            $my = $mine[$id] ?? null;
            $inside = isset($_SESSION['monthly_start_' . $id]);
        ?>
            <div class="card mq-card <?php echo $st === 'ended' ? 'ended' : ($st === 'upcoming' ? '' : 'live'); ?>">
                <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
                    <h3 style="margin:0;"><?php echo htmlspecialchars($q['title']); ?></h3>
                    <?php if ($st === 'upcoming'): ?><span class="pill pill-upcoming">UPCOMING</span>
                    <?php elseif ($st === 'ended'): ?><span class="pill pill-ended">ENDED</span>
                    <?php else: ?><span class="pill pill-live">● LIVE</span><?php endif; ?>
                </div>
                <p style="color:#667; margin:8px 0;">
                    📅 <?php echo date("d M Y, h:i A", $start); ?> → <?php echo date("h:i A", $end); ?>
                    · ⏱️ <?php echo (int)$q['duration_minutes']; ?> min
                    · 👥 <?php echo (int)$q['participants']; ?> joined
                </p>

                <?php if ($st === 'upcoming'): ?>
                    <p>Starts in</p>
                    <div class="countdown" data-countdown="<?php echo $start - $now; ?>" data-open="monthly_attempt.php?id=<?php echo $id; ?>">--:--:--</div>
                    <p style="color:#8a93a8; font-size:0.85rem; margin-top:6px;">This page opens the quiz automatically when the time comes — keep it open.</p>

                <?php elseif ($my && $st !== 'ended'): ?>
                    <div class="alert alert-success" style="margin:0;">✅ You have submitted. The winner is announced when the quiz ends at <?php echo date("h:i A", $end); ?>.</div>

                <?php elseif ($st === 'joining'): ?>
                    <a class="btn btn-success" href="monthly_attempt.php?id=<?php echo $id; ?>">Join Now</a>

                <?php elseif ($st === 'running' && $inside): ?>
                    <a class="btn btn-success" href="monthly_attempt.php?id=<?php echo $id; ?>">Continue my exam</a>

                <?php elseif ($st === 'running'): ?>
                    <div class="alert alert-error" style="margin:0;">The join window has closed for this quiz.</div>

                <?php else: /* ended */ ?>
                    <?php if ($my): ?><p style="margin-bottom:8px;">Your score: <strong><?php echo $my['score'] . ' / ' . $my['total_questions']; ?></strong></p><?php endif; ?>
                    <a class="btn" href="monthly_leaderboard.php?id=<?php echo $id; ?>">🏆 View results &amp; winner</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Live countdown (based on the server's clock, not the student's PC clock)
        document.querySelectorAll("[data-countdown]").forEach(function (el) {
            let s = parseInt(el.getAttribute("data-countdown"), 10);
            function draw() {
                const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
                el.textContent = [h, m, sec].map(function (v) { return (v < 10 ? "0" : "") + v; }).join(":");
            }
            draw();
            setInterval(function () {
                s--;
                if (s <= 0) { location.href = el.getAttribute("data-open") || location.href; return; }
                draw();
            }, 1000);
        });
    </script>
</body>
</html>
