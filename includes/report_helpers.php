<?php
// ============================================
// Helpers for the detailed result report (HTML page + PDF download)
// ============================================

function format_duration($seconds) {
    if ($seconds === null || $seconds === '') return '—';
    $seconds = max(0, (int)$seconds);
    return intdiv($seconds, 60) . ' min ' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT) . ' sec';
}

function format_dt($value, $with_seconds = true) {
    if (!$value) return '—';
    return date($with_seconds ? "d M Y, h:i:s A" : "d M Y, h:i A", strtotime($value));
}

/** Turns a stored answer (A/B/C/D, True/False or text) into readable text. */
function answer_text($q, $value) {
    $value = trim((string)$value);
    if ($value === '') return 'Not answered';
    if ($q['type'] === 'mcq') {
        $letter = strtoupper($value);
        if (in_array($letter, ['A', 'B', 'C', 'D'], true)) {
            $opt = $q['option_' . strtolower($letter)] ?? '';
            return $letter . ') ' . $opt;
        }
    }
    return $value;
}

/**
 * Loads one attempt + every answered question.
 * Returns null if it doesn't exist or the viewer isn't allowed to see it
 * (a student can only open their OWN report; admin can open any).
 */
function load_report($conn, $result_id, $viewer_id, $is_admin) {
    $stmt = $conn->prepare("
        SELECT r.*, qs.title, qs.set_number, u.name AS student_name
        FROM results r
        JOIN question_sets qs ON r.set_id = qs.id
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $result_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) return null;
    if (!$is_admin && (int)$res['user_id'] !== (int)$viewer_id) return null;

    $stmt = $conn->prepare("
        SELECT ra.given_answer, ra.is_correct,
               q.id AS qid, q.type, q.question_text,
               q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer
        FROM result_answers ra
        JOIN questions q ON q.id = ra.question_id
        WHERE ra.result_id = ?
        ORDER BY q.id
    ");
    $stmt->bind_param("i", $result_id);
    $stmt->execute();
    $rows = [];
    $q = $stmt->get_result();
    while ($r = $q->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();

    return ['result' => $res, 'rows' => $rows];
}

/** Correct / wrong / skipped counts for a list of report rows. */
function report_counts($rows) {
    $c = ['correct' => 0, 'wrong' => 0, 'skipped' => 0];
    foreach ($rows as $r) {
        if (trim($r['given_answer']) === '') $c['skipped']++;
        elseif ((int)$r['is_correct'] === 1) $c['correct']++;
        else $c['wrong']++;
    }
    return $c;
}
