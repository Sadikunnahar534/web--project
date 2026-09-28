<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: quizzes.php");
    exit();
}

$set_id = intval($_POST['set_id']);
$answers = $_POST['answers'] ?? [];
$user_id = $_SESSION['user_id'];

$TIME_LIMIT_SECONDS = 15 * 60;
$GRACE_SECONDS = 10;
$timer_key = 'exam_start_' . $set_id;

// The exam must have been opened (attempt_quiz.php records the start time in the session).
if (!isset($_SESSION[$timer_key])) {
    header("Location: attempt_quiz.php?id=" . $set_id);
    exit();
}

$started_ts = (int)$_SESSION[$timer_key];
$submitted_ts = time();
$elapsed = $submitted_ts - $started_ts;

// ---- Server-side enforcement of the 15-minute limit (never trust the client) ----
if ($elapsed > $TIME_LIMIT_SECONDS + $GRACE_SECONDS) {
    unset($_SESSION[$timer_key]);
    header("Location: time_up.php?set_id=" . $set_id);
    exit();
}
$duration = min($elapsed, $TIME_LIMIT_SECONDS);

// Prevent double-submission if a result already exists for this user+set.
$stmt = $conn->prepare("SELECT id FROM results WHERE user_id = ? AND set_id = ?");
$stmt->bind_param("ii", $user_id, $set_id);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    unset($_SESSION[$timer_key]);
    header("Location: results.php");
    exit();
}
$stmt->close();

// ---- Fetch correct answers from DB (never trust client-side scoring) ----
$stmt = $conn->prepare("SELECT id, type, correct_answer FROM questions WHERE set_id = ?");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
$score = 0;
$graded = []; // [question_id, given_answer, is_correct]

while ($row = $result->fetch_assoc()) {
    $total++;
    $qid = $row['id'];
    $given = isset($answers[$qid]) ? trim((string)$answers[$qid]) : '';
    $is_correct = 0;

    // mcq (A/B/C/D), tf (True/False) and short answers: case-insensitive, trimmed match.
    if ($given !== '' && strcasecmp($given, trim($row['correct_answer'])) === 0) {
        $score++;
        $is_correct = 1;
    }
    $graded[] = [(int)$qid, mb_substr($given, 0, 255), $is_correct];
}
$stmt->close();

// ---- Store result + every answer (so the student can view/download a report) ----
$started_at = date('Y-m-d H:i:s', $started_ts);
$new_result_id = 0;

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO results (user_id, set_id, score, total_questions, started_at, duration_seconds) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiisi", $user_id, $set_id, $score, $total, $started_at, $duration);
    $stmt->execute();
    $new_result_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO result_answers (result_id, question_id, given_answer, is_correct) VALUES (?, ?, ?, ?)");
    foreach ($graded as $g) {
        $stmt->bind_param("iisi", $new_result_id, $g[0], $g[1], $g[2]);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        // Duplicate submit (double click): the result is already saved - just show it.
        unset($_SESSION[$timer_key]);
        header("Location: results.php");
        exit();
    }
    // Any other problem: tell the student honestly instead of showing an empty result page.
    http_response_code(500);
    die("Sorry, your result could not be saved (" . htmlspecialchars($e->getMessage()) . "). Please log out, log in again and retry. <a href=\"quizzes.php\">Back</a>");
}

unset($_SESSION[$timer_key]);

header("Location: results.php?justTaken=1&result_id=$new_result_id&score=$score&total=$total&set_id=$set_id");
exit();
