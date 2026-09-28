<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/report_helpers.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$result_id = intval($_GET['result_id'] ?? $_POST['result_id'] ?? 0);

$report = load_report($conn, $result_id, $user_id, $is_admin);
if (!$report) {
    http_response_code(404);
    die("Report not found.");
}
$res = $report['result'];
$rows = $report['rows'];
$is_owner = ((int)$res['user_id'] === $user_id);
$errors = [];

// ---------- Student reports a doubt about ONE question ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_doubt']) && $is_owner) {
    csrf_check();
    $qid = intval($_POST['question_id'] ?? 0);
    $msg = trim($_POST['message'] ?? '');

    $found = null;
    foreach ($rows as $r) { if ((int)$r['qid'] === $qid) { $found = $r; break; } }

    if (!$found) {
        $errors[] = "That question is not part of this attempt.";
    } elseif (mb_strlen($msg) < 5) {
        $errors[] = "Please describe your doubt (at least 5 characters).";
    } elseif (mb_strlen($msg) > 1000) {
        $errors[] = "Your message is too long (max 1000 characters).";
    } else {
        // Copy of the question + answers is saved so the doubt stays readable later.
        $qtext = $found['question_text'];
        $mine = mb_substr(answer_text($found, $found['given_answer']), 0, 255);
        $right = mb_substr(answer_text($found, $found['correct_answer']), 0, 255);
        $set_no = (int)$res['set_number'];
        $stmt = $conn->prepare("INSERT INTO question_reports (user_id, result_id, question_id, set_number, question_text, student_answer, correct_answer, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiissss", $user_id, $result_id, $qid, $set_no, $qtext, $mine, $right, $msg);
        $stmt->execute();
        $stmt->close();
        header("Location: result_report.php?result_id=" . $result_id . "&reported=1#q" . $qid);
        exit();
    }
}

// ---------- Doubts already sent for this attempt ----------
$doubts = [];
$stmt = $conn->prepare("SELECT * FROM question_reports WHERE result_id = ? ORDER BY created_at");
$stmt->bind_param("i", $result_id);
$stmt->execute();
$dq = $stmt->get_result();
while ($d = $dq->fetch_assoc()) { $doubts[(int)$d['question_id']][] = $d; }
$stmt->close();

$counts = report_counts($rows);
$total = (int)$res['total_questions'];
$percent = $total > 0 ? round(($res['score'] / $total) * 100) : 0;
$back = $is_admin ? 'admin/dashboard.php' : 'results.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result Report - Set #<?php echo $res['set_number']; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/features.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="card">
            <div class="info-row">
                <div class="set-pill">#<?php echo $res['set_number']; ?></div>
                <div>
                    <h2 style="margin-bottom:2px;">Result Report — <?php echo htmlspecialchars($res['title']); ?></h2>
                    <p style="color:#667;">Student: <strong><?php echo htmlspecialchars($res['student_name']); ?></strong></p>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-box"><div class="k">Score</div><div class="v"><?php echo $res['score'] . ' / ' . $total; ?> (<?php echo $percent; ?>%)</div></div>
                <div class="summary-box"><div class="k">Started at</div><div class="v" style="font-size:0.95rem;"><?php echo format_dt($res['started_at']); ?></div></div>
                <div class="summary-box"><div class="k">Submitted at</div><div class="v" style="font-size:0.95rem;"><?php echo format_dt($res['taken_at']); ?></div></div>
                <div class="summary-box"><div class="k">Time taken</div><div class="v"><?php echo format_duration($res['duration_seconds']); ?></div></div>
                <?php if (count($rows) > 0): ?>
                <div class="summary-box good"><div class="k">Correct</div><div class="v"><?php echo $counts['correct']; ?></div></div>
                <div class="summary-box bad"><div class="k">Wrong</div><div class="v"><?php echo $counts['wrong']; ?></div></div>
                <div class="summary-box"><div class="k">Not answered</div><div class="v"><?php echo $counts['skipped']; ?></div></div>
                <?php endif; ?>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn btn-success" href="download_report.php?result_id=<?php echo $result_id; ?>">⬇ Download PDF</a>
                <a class="btn btn-secondary" href="<?php echo $back; ?>">&larr; Back</a>
            </div>
        </div>

        <?php if (isset($_GET['reported'])): ?>
            <div class="alert alert-success">✅ Your doubt was sent to the admin. You will see the reply here once it is reviewed.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?></div>
        <?php endif; ?>

        <?php if (count($rows) === 0): ?>
            <div class="alert alert-info">Detailed answers were not recorded for this attempt (it was taken before the report feature was added). The score and time above are still correct.</div>
        <?php endif; ?>

        <?php $n = 1; foreach ($rows as $r):
            $given = trim($r['given_answer']);
            $state = ($given === '') ? 'skipped' : (((int)$r['is_correct'] === 1) ? 'correct' : 'wrong');
            $label = ['correct' => '✔ Correct', 'wrong' => '✘ Wrong', 'skipped' => '— Not answered'][$state];
            $qid = (int)$r['qid'];
        ?>
            <div class="rq <?php echo $state; ?>" id="q<?php echo $qid; ?>">
                <div class="rq-head">
                    <strong>Q<?php echo $n++; ?>. <?php echo htmlspecialchars($r['question_text']); ?></strong>
                    <span class="rq-status"><?php echo $label; ?></span>
                </div>

                <?php if ($r['type'] === 'mcq'): ?>
                    <ul class="opt-list">
                    <?php foreach (['A', 'B', 'C', 'D'] as $L):
                        $cls = '';
                        if (strcasecmp($L, trim($r['correct_answer'])) === 0) $cls = 'opt-correct';
                        elseif (strcasecmp($L, $given) === 0) $cls = 'opt-wrong';
                        $mark = '';
                        if (strcasecmp($L, $given) === 0) $mark = ' &nbsp;← your answer';
                        if ($cls === 'opt-correct') $mark .= ' &nbsp;✔ correct';
                    ?>
                        <li class="<?php echo $cls; ?>"><?php echo $L . ') ' . htmlspecialchars($r['option_' . strtolower($L)]) . $mark; ?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ans-line"><b>Your answer:</b> <?php echo htmlspecialchars(answer_text($r, $given)); ?></p>
                    <p class="ans-line"><b>Correct answer:</b> <?php echo htmlspecialchars(answer_text($r, $r['correct_answer'])); ?></p>
                <?php endif; ?>

                <?php if (!empty($doubts[$qid])): foreach ($doubts[$qid] as $d): ?>
                    <div class="doubt-item">
                        <span class="pill pill-<?php echo $d['status']; ?>"><?php echo strtoupper($d['status']); ?></span>
                        <strong>Your doubt:</strong> <?php echo nl2br(htmlspecialchars($d['message'])); ?>
                        <div style="color:#8a93a8; font-size:0.8rem;"><?php echo format_dt($d['created_at'], false); ?></div>
                        <?php if (!empty($d['admin_reply'])): ?>
                            <div class="reply"><strong>Admin reply:</strong> <?php echo nl2br(htmlspecialchars($d['admin_reply'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>

                <?php if ($is_owner): ?>
                <details class="doubt">
                    <summary>🚩 Report a doubt about this question</summary>
                    <form method="POST" action="result_report.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="result_id" value="<?php echo $result_id; ?>">
                        <input type="hidden" name="question_id" value="<?php echo $qid; ?>">
                        <label>What is your doubt? (wrong answer key, unclear question, etc.)</label>
                        <textarea name="message" maxlength="1000" required></textarea>
                        <button type="submit" name="report_doubt">Send to admin</button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
