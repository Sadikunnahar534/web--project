<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/report_helpers.php';
require 'includes/simple_pdf.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$result_id = intval($_GET['result_id'] ?? 0);

$report = load_report($conn, $result_id, $user_id, $is_admin);
if (!$report) {
    http_response_code(404);
    die("Report not found.");
}
$res = $report['result'];
$rows = $report['rows'];
$counts = report_counts($rows);
$total = (int)$res['total_questions'];
$percent = $total > 0 ? round(($res['score'] / $total) * 100) : 0;

$NAVY = [0.11, 0.16, 0.29];
$GREEN = [0.10, 0.50, 0.25];
$RED = [0.75, 0.22, 0.17];
$GREY = [0.40, 0.40, 0.45];
$ORANGE = [0.75, 0.48, 0.05];

$pdf = new SimplePDF();
$pdf->line('Web & Internet Quiz System - Result Report', 16, true, $NAVY, 0, 4);
$pdf->line('Set #' . $res['set_number'] . ' - ' . $res['title'], 12, true, [0, 0, 0], 0, 2);
$pdf->line('Student: ' . $res['student_name'], 10.5, false, $GREY);
$pdf->rule();
$pdf->line('Score: ' . $res['score'] . ' / ' . $total . '  (' . $percent . '%)', 11, true, $NAVY);
$pdf->line('Started at: ' . format_dt($res['started_at']), 10);
$pdf->line('Submitted at: ' . format_dt($res['taken_at']), 10);
$pdf->line('Time taken: ' . format_duration($res['duration_seconds']), 10);
if (count($rows) > 0) {
    $pdf->line('Correct: ' . $counts['correct'] . '   Wrong: ' . $counts['wrong'] . '   Not answered: ' . $counts['skipped'], 10);
}
$pdf->rule();
$pdf->space(4);

if (count($rows) === 0) {
    $pdf->line('Detailed answers were not recorded for this attempt.', 10, false, $GREY);
}

$n = 1;
foreach ($rows as $r) {
    $given = trim($r['given_answer']);
    $skipped = ($given === '');
    $ok = ((int)$r['is_correct'] === 1);

    $pdf->line('Q' . $n++ . '. ' . $r['question_text'], 10.5, true, [0, 0, 0], 0, 2);
    if ($r['type'] === 'mcq') {
        foreach (['A', 'B', 'C', 'D'] as $L) {
            $pdf->line($L . ') ' . $r['option_' . strtolower($L)], 9.5, false, $GREY, 14);
        }
    }
    $mineColor = $skipped ? $ORANGE : ($ok ? $GREEN : $RED);
    $pdf->line('Your answer: ' . answer_text($r, $given), 10, false, $mineColor, 14);
    $pdf->line('Correct answer: ' . answer_text($r, $r['correct_answer']), 10, false, $GREEN, 14);
    $pdf->line('Result: ' . ($skipped ? 'Not answered' : ($ok ? 'Correct' : 'Wrong')), 10, true, $mineColor, 14, 8);
}

$data = $pdf->output();
$fname = 'quiz_report_set' . $res['set_number'] . '_' . date('Ymd_His', strtotime($res['taken_at'])) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . strlen($data));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $data;
exit();
