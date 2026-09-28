<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/monthly_helpers.php';
require_login();
if ($_SESSION['role'] === 'admin') { header("Location: admin/manage_monthly.php"); exit(); }

$user_id = (int)$_SESSION['user_id'];
$id = intval($_GET['id'] ?? $_POST['monthly_id'] ?? 0);
$now = time();

$stmt = $conn->prepare("SELECT * FROM monthly_quizzes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$quiz) { die("Monthly quiz not found."); }

list($start, $end) = monthly_times($quiz);
$sess_key = 'monthly_start_' . $id;

// Already submitted? -> go to the results page.
$stmt = $conn->prepare("SELECT id FROM monthly_attempts WHERE monthly_id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$already = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($already) { header("Location: monthly_leaderboard.php?id=" . $id); exit(); }

// =====================  SUBMIT  =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!isset($_SESSION[$sess_key])) { header("Location: monthly.php?msg=closed"); exit(); }
    if ($now > $end + MONTHLY_SUBMIT_GRACE_SECONDS) {
        unset($_SESSION[$sess_key]);
        header("Location: monthly.php?msg=late");
        exit();
    }

    $answers = $_POST['answers'] ?? [];
    $started_ts = (int)$_SESSION[$sess_key];
    $duration = max(0, min($now, $end) - $started_ts);

    $stmt = $conn->prepare("SELECT id, correct_answer FROM monthly_questions WHERE monthly_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $qs = $stmt->get_result();
    $total = 0;
    $score = 0;
    while ($row = $qs->fetch_assoc()) {
        $total++;
        $given = isset($answers[$row['id']]) ? trim((string)$answers[$row['id']]) : '';
        if ($given !== '' && strcasecmp($given, trim($row['correct_answer'])) === 0) { $score++; }
    }
    $stmt->close();

    $started_at = date('Y-m-d H:i:s', $started_ts);
    $submitted_at = date('Y-m-d H:i:s', $now);
    try {
        $stmt = $conn->prepare("INSERT INTO monthly_attempts (monthly_id, user_id, score, total_questions, started_at, submitted_at, duration_seconds) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiissi", $id, $user_id, $score, $total, $started_at, $submitted_at, $duration);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1062) {   // 1062 = duplicate submit (double click) - safe to ignore
            http_response_code(500);
            die("Sorry, your answers could not be saved (" . htmlspecialchars($e->getMessage()) . "). Please log out, log in again and retry. <a href=\"monthly.php\">Back</a>");
        }
    }
    unset($_SESSION[$sess_key]);
    header("Location: monthly_leaderboard.php?id=" . $id . "&submitted=1");
    exit();
}

// =====================  SHOW THE EXAM  =====================
if ($now < $start) { header("Location: monthly.php"); exit(); }
if ($now >= $end + MONTHLY_SUBMIT_GRACE_SECONDS) { header("Location: monthly_leaderboard.php?id=" . $id); exit(); }

$questions = monthly_questions($conn, $id);
if (count($questions) === 0) { header("Location: monthly.php?msg=noq"); exit(); }

if (!isset($_SESSION[$sess_key])) {
    // first time opening: must be inside the join window
    if ($now > $start + MONTHLY_JOIN_GRACE_SECONDS || $now >= $end) {
        header("Location: monthly.php?msg=closed");
        exit();
    }
    $_SESSION[$sess_key] = $now;
}
$remaining = max(0, $end - $now);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Monthly Quiz</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/features.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card" style="position:sticky; top:10px; z-index:5; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h2 style="margin-bottom:2px;">🏆 <?php echo htmlspecialchars($quiz['title']); ?></h2>
                <p style="color:#667; margin:0;">Monthly live quiz · ends for everyone at <strong><?php echo date("h:i:s A", $end); ?></strong> — answers are submitted automatically at that time.</p>
            </div>
            <div style="text-align:center;">
                <div style="font-size:0.8rem; color:#667;">TIME REMAINING</div>
                <div id="mTimer" data-seconds="<?php echo $remaining; ?>" style="font-size:1.8rem; font-weight:800; color:#e74c3c;">--:--</div>
            </div>
        </div>

        <div class="card">
            <form id="monthlyForm" method="POST" action="monthly_attempt.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="monthly_id" value="<?php echo $id; ?>">

                <?php $n = 1; foreach ($questions as $q): ?>
                    <div class="question-block">
                        <p>
                            <strong>Q<?php echo $n++; ?>. <?php echo htmlspecialchars($q['question_text']); ?></strong>
                            <span class="qtype-tag"><?php echo $q['type'] === 'mcq' ? 'MCQ' : ($q['type'] === 'tf' ? 'True / False' : 'Short Answer'); ?></span>
                        </p>
                        <div class="options">
                        <?php if ($q['type'] === 'mcq'): ?>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="A"> <?php echo htmlspecialchars($q['option_a']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="B"> <?php echo htmlspecialchars($q['option_b']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="C"> <?php echo htmlspecialchars($q['option_c']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="D"> <?php echo htmlspecialchars($q['option_d']); ?></label>
                        <?php elseif ($q['type'] === 'tf'): ?>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="True"> True</label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="False"> False</label>
                        <?php else: ?>
                            <input type="text" name="answers[<?php echo $q['id']; ?>]" placeholder="Type your short answer here" autocomplete="off">
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" id="submitBtn">Submit Exam</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const timerEl = document.getElementById("mTimer");
            const form = document.getElementById("monthlyForm");
            const btn = document.getElementById("submitBtn");
            let seconds = parseInt(timerEl.getAttribute("data-seconds"), 10);
            let done = false;

            function render() {
                const m = Math.floor(seconds / 60), s = seconds % 60;
                timerEl.textContent = (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
                if (seconds <= 60) timerEl.style.color = "#c0392b";
            }
            render();

            const iv = setInterval(function () {
                seconds--;
                if (seconds <= 0) {
                    seconds = 0; render(); clearInterval(iv);
                    if (!done) {
                        done = true;
                        btn.disabled = true;
                        btn.textContent = "Time's up - submitting...";
                        form.submit();   // auto-submit whatever is answered
                    }
                    return;
                }
                render();
            }, 1000);

            // Manual submit: warn about unanswered questions
            form.addEventListener("submit", function (e) {
                if (done) return;
                let unanswered = 0;
                form.querySelectorAll(".question-block").forEach(function (g) {
                    const radios = g.querySelectorAll("input[type=radio]");
                    const text = g.querySelector("input[type=text]");
                    if (radios.length > 0) { if (!g.querySelector("input[type=radio]:checked")) unanswered++; }
                    else if (text && text.value.trim() === "") unanswered++;
                });
                if (unanswered > 0 && !confirm(unanswered + " question(s) unanswered. Submit anyway?")) {
                    e.preventDefault();
                    return;
                }
                done = true;
                clearInterval(iv);
            });
        })();
    </script>
</body>
</html>
