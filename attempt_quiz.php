<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();
if ($_SESSION['role'] === 'admin') { header("Location: admin/dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];
$set_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM question_sets WHERE id = ?");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$set = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$set) {
    die("Question set not found.");
}

// Block re-attempting a set already completed by this student.
$stmt = $conn->prepare("SELECT id FROM results WHERE user_id = ? AND set_id = ?");
$stmt->bind_param("ii", $user_id, $set_id);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    header("Location: results.php");
    exit();
}
$stmt->close();

// ---- 15-minute timer, tracked server-side in the session ----
$TIME_LIMIT_SECONDS = 15 * 60;
$GRACE_SECONDS = 10; // small network/latency buffer
$timer_key = 'exam_start_' . $set_id;

if (!isset($_SESSION[$timer_key])) {
    $_SESSION[$timer_key] = time();
}
$elapsed = time() - $_SESSION[$timer_key];
$remaining = $TIME_LIMIT_SECONDS - $elapsed;

if ($remaining <= -$GRACE_SECONDS) {
    // Already timed out (e.g. student reloaded the page after time was up)
    unset($_SESSION[$timer_key]);
    header("Location: time_up.php?set_id=" . $set_id);
    exit();
}
if ($remaining < 0) { $remaining = 0; }

$stmt = $conn->prepare("SELECT * FROM questions WHERE set_id = ? ORDER BY id");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$questions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($set['title']); ?> - Attempt Quiz</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card" style="position:sticky; top:10px; z-index:5; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h2 style="margin-bottom:2px;">Set #<?php echo $set['set_number']; ?> — <?php echo htmlspecialchars($set['title']); ?></h2>
                <p style="color:#667; margin:0;">Topic: Web and Internet · 10 MCQ + 5 True/False</p>
            </div>
            <div style="text-align:center;">
                <div style="font-size:0.8rem; color:#667;">TIME REMAINING</div>
                <div id="quizTimer" data-seconds="<?php echo $remaining; ?>" style="font-size:1.8rem; font-weight:800; color:#e74c3c;">15:00</div>
            </div>
        </div>

        <div class="card">
            <form id="quizForm" method="POST" action="submit_quiz.php">
                <input type="hidden" name="set_id" value="<?php echo $set['id']; ?>">

                <?php $n = 1; while ($q = $questions->fetch_assoc()): ?>
                    <div class="question-block">
                        <p>
                            <strong>Q<?php echo $n++; ?>. <?php echo htmlspecialchars($q['question_text']); ?></strong>
                            <span class="qtype-tag">
                                <?php echo $q['type'] === 'mcq' ? 'MCQ' : ($q['type'] === 'tf' ? 'True / False' : 'Short Answer'); ?>
                            </span>
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
                <?php endwhile; ?>

                <button type="submit" id="submitBtn">Submit Exam</button>
            </form>
        </div>
    </div>

    <script src="js/validate.js"></script>
    <script>
        // Hard 15-minute enforcement: auto-lock the exam when time runs out.
        (function () {
            const timerEl = document.getElementById("quizTimer");
            const form = document.getElementById("quizForm");
            const submitBtn = document.getElementById("submitBtn");
            let seconds = parseInt(timerEl.getAttribute("data-seconds"), 10);

            function render() {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                timerEl.textContent = (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
                if (seconds <= 60) timerEl.style.color = "#c0392b";
            }
            render();

            const iv = setInterval(function () {
                seconds--;
                if (seconds <= 0) {
                    seconds = 0;
                    render();
                    clearInterval(iv);
                    submitBtn.disabled = true;
                    submitBtn.textContent = "Time's up";
                    alert("⏰ Time's up! Your exam was NOT submitted in time.");
                    window.location.href = "time_up.php?set_id=<?php echo $set['id']; ?>";
                    return;
                }
                render();
            }, 1000);

            // Prevent double interference with the manual-submit confirm in validate.js
            form.addEventListener("submit", function () {
                clearInterval(iv);
            });
        })();
    </script>
</body>
</html>
