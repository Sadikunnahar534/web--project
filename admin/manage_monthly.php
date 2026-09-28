<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require '../includes/monthly_helpers.php';
require_admin();
$nav_in_admin = true;

$errors = [];

// ---- CREATE a monthly quiz ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_monthly'])) {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $copy_set = intval($_POST['copy_set_id'] ?? 0);   // optional: 0 = start with an empty question list
    $start_raw = trim($_POST['start_time'] ?? '');
    $duration = intval($_POST['duration_minutes'] ?? 15);
    $start_ts = strtotime(str_replace('T', ' ', $start_raw));

    if ($title === '') $errors[] = "Title is required.";
    if (!$start_ts) $errors[] = "Please choose a valid start date & time.";
    if ($duration < 5 || $duration > 180) $errors[] = "Duration must be between 5 and 180 minutes.";

    if (empty($errors)) {
        $start_sql = date('Y-m-d H:i:s', $start_ts);
        $stmt = $conn->prepare("INSERT INTO monthly_quizzes (title, start_time, duration_minutes) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $title, $start_sql, $duration);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        if ($copy_set > 0) {
            $stmt = $conn->prepare("INSERT INTO monthly_questions (monthly_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer)
                                    SELECT ?, type, question_text, option_a, option_b, option_c, option_d, correct_answer FROM questions WHERE set_id = ? ORDER BY id");
            $stmt->bind_param("ii", $new_id, $copy_set);
            $stmt->execute();
            $stmt->close();
        }
        // Go straight to the question page so the admin can add/check the questions.
        header("Location: monthly_questions.php?id=" . $new_id);
        exit();
    }
}

// ---- UPDATE title / date-time / duration (only before the quiz starts) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_monthly'])) {
    csrf_check();
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $duration = intval($_POST['duration_minutes'] ?? 15);
    $start_ts = strtotime(str_replace('T', ' ', trim($_POST['start_time'] ?? '')));

    $stmt = $conn->prepare("SELECT * FROM monthly_quizzes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$old) $errors[] = "Quiz not found.";
    elseif (monthly_status($old) !== 'upcoming') $errors[] = "This quiz has already started, so its schedule can no longer be changed.";
    if ($title === '') $errors[] = "Title is required.";
    if (!$start_ts) $errors[] = "Please choose a valid start date & time.";
    elseif ($start_ts <= time()) $errors[] = "The start time must be in the future.";
    if ($duration < 5 || $duration > 180) $errors[] = "Duration must be between 5 and 180 minutes.";

    if (empty($errors)) {
        $start_sql = date('Y-m-d H:i:s', $start_ts);
        $stmt = $conn->prepare("UPDATE monthly_quizzes SET title = ?, start_time = ?, duration_minutes = ? WHERE id = ?");
        $stmt->bind_param("ssii", $title, $start_sql, $duration, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_monthly.php?updated=1");
        exit();
    }
}

// ---- DELETE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_monthly'])) {
    csrf_check();
    $id = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM monthly_quizzes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_monthly.php");
    exit();
}

$edit = null;
if (isset($_GET['edit']) || (isset($_POST['update_monthly']) && !empty($errors))) {
    $stmt = $conn->prepare("SELECT * FROM monthly_quizzes WHERE id = ?");
    $eid = intval($_GET['edit'] ?? $_POST['id'] ?? 0);
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($edit && monthly_status($edit) !== 'upcoming') { $edit = null; }
}
$sets = $conn->query("SELECT id, set_number, title FROM question_sets ORDER BY set_number");
$list = $conn->query("
    SELECT mq.*,
           (SELECT COUNT(*) FROM monthly_questions x WHERE x.monthly_id = mq.id) AS q_count,
           (SELECT COUNT(*) FROM monthly_attempts ma WHERE ma.monthly_id = mq.id) AS participants
    FROM monthly_quizzes mq
    ORDER BY mq.start_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Quiz - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/features.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>🏆 Monthly Live Quiz</h2>
        <p style="color:#667; margin-bottom:14px;">Schedule a quiz, then add its own questions (or copy them from a set). All students play the same questions at the same time; the quiz ends for everyone together. Highest mark wins (tie → less time taken) and the winner is congratulated with a celebration video.</p>

                <?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Monthly quiz updated.</div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><?php echo $edit ? 'Edit Monthly Quiz' : 'Schedule a New Monthly Quiz'; ?></h3>
            <form method="POST" action="manage_monthly.php" style="max-width:none;">
                <?php echo csrf_field(); ?>
                <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>
                <label for="title">Title</label>
                <input type="text" id="title" name="title" placeholder="e.g. October Monthly Quiz" value="<?php echo $edit ? htmlspecialchars($edit['title']) : ''; ?>" required>
                <div class="form-row">
                    <?php if (!$edit): ?>
                    <div>
                        <label for="copy_set_id">Questions (optional)</label>
                        <select id="copy_set_id" name="copy_set_id">
                            <option value="0">I will add my own questions</option>
                            <?php while ($s = $sets->fetch_assoc()): ?>
                                <option value="<?php echo $s['id']; ?>">Copy from set #<?php echo $s['set_number']; ?> — <?php echo htmlspecialchars($s['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label for="start_time">Start date &amp; time</label>
                        <input type="datetime-local" id="start_time" name="start_time" value="<?php echo $edit ? date('Y-m-d\TH:i', strtotime($edit['start_time'])) : ''; ?>" required>
                    </div>
                    <div>
                        <label for="duration_minutes">Duration (minutes)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" value="<?php echo $edit ? (int)$edit['duration_minutes'] : 15; ?>" min="5" max="180" required>
                    </div>
                </div>
                <button type="submit" name="<?php echo $edit ? 'update_monthly' : 'add_monthly'; ?>"><?php echo $edit ? 'Save Changes' : 'Schedule Quiz'; ?></button>
                <?php if ($edit): ?> <a class="btn btn-secondary" href="manage_monthly.php">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>Scheduled Quizzes</h3>
            <table>
                <tr><th>Title</th><th>Questions</th><th>Start</th><th>Duration</th><th>Status</th><th>Joined</th><th>Winner</th><th>Actions</th></tr>
                <?php while ($m = $list->fetch_assoc()):
                    $st = monthly_status($m);
                    $winner = null;
                    if ($st === 'ended') { $rk = monthly_ranking($conn, (int)$m['id']); $winner = $rk[0] ?? null; }
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['title']); ?></td>
                        <td><?php echo (int)$m['q_count']; ?><?php if ((int)$m['q_count'] === 0): ?> <span class="pill pill-open">none!</span><?php endif; ?></td>
                        <td><?php echo date("d M Y, h:i A", strtotime($m['start_time'])); ?></td>
                        <td><?php echo (int)$m['duration_minutes']; ?> min</td>
                        <td>
                            <?php if ($st === 'upcoming'): ?><span class="pill pill-upcoming">UPCOMING</span>
                            <?php elseif ($st === 'ended'): ?><span class="pill pill-ended">ENDED</span>
                            <?php else: ?><span class="pill pill-live">● LIVE</span><?php endif; ?>
                        </td>
                        <td><?php echo (int)$m['participants']; ?></td>
                        <td><?php echo $winner ? '🏆 ' . htmlspecialchars($winner['name']) . ' (' . $winner['score'] . '/' . $winner['total_questions'] . ')' : '—'; ?></td>
                        <td>
                            <?php if ($st === 'upcoming'): ?><a href="manage_monthly.php?edit=<?php echo $m['id']; ?>">Edit time</a> |<?php endif; ?>
                            <a href="monthly_questions.php?id=<?php echo $m['id']; ?>">Questions</a> |
                            <a href="../monthly_leaderboard.php?id=<?php echo $m['id']; ?>">Results</a>
                            <form method="POST" action="manage_monthly.php" class="inline-form" onsubmit="return confirm('Delete this monthly quiz and all its results?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <button type="submit" name="delete_monthly" class="btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
