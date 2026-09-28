<?php
// ============================================
// Helpers for the Monthly Live Quiz
// Needs config/db.php (constants) to be loaded first.
// ============================================

/** [start_timestamp, end_timestamp] - everyone shares the SAME end time. */
function monthly_times($quiz) {
    $start = strtotime($quiz['start_time']);
    $end = $start + ((int)$quiz['duration_minutes']) * 60;
    return [$start, $end];
}

/**
 * upcoming : before the start time (nobody can enter)
 * joining  : started, and the join window is still open
 * running  : started, join window closed (only people already inside continue)
 * ended    : finished, results/winner are public
 */
function monthly_status($quiz, $now = null) {
    $now = $now ?? time();
    list($start, $end) = monthly_times($quiz);
    if ($now < $start) return 'upcoming';
    if ($now >= $end + MONTHLY_SUBMIT_GRACE_SECONDS) return 'ended';
    return ($now <= $start + MONTHLY_JOIN_GRACE_SECONDS) ? 'joining' : 'running';
}

/** Winner = highest score; tie -> less time taken; tie -> submitted first. */
function monthly_ranking($conn, $monthly_id) {
    $stmt = $conn->prepare("
        SELECT ma.*, u.name
        FROM monthly_attempts ma
        JOIN users u ON u.id = ma.user_id
        WHERE ma.monthly_id = ?
        ORDER BY ma.score DESC, ma.duration_seconds ASC, ma.submitted_at ASC
    ");
    $stmt->bind_param("i", $monthly_id);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

/** 754 -> "12m 34s" */
function format_seconds_short($seconds) {
    $seconds = max(0, (int)$seconds);
    return intdiv($seconds, 60) . 'm ' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT) . 's';
}

/** All questions that belong to one monthly quiz. */
function monthly_questions($conn, $monthly_id) {
    $stmt = $conn->prepare("SELECT * FROM monthly_questions WHERE monthly_id = ? ORDER BY id");
    $stmt->bind_param("i", $monthly_id);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

/** Celebration video file names found in assets/videos/ (same folder the 15/15 page uses). */
function celebration_videos() {
    $dir = __DIR__ . '/../assets/videos';
    $out = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if (preg_match('/\.(mp4|webm|ogg)$/i', $f)) { $out[] = $f; }
        }
    }
    return $out;
}
