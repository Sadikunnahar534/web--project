<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require '../includes/report_helpers.php';
require_admin();
$nav_in_admin = true;

// ---- Reply / change status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    csrf_check();
    $id = intval($_POST['id'] ?? 0);
    $reply = trim($_POST['admin_reply'] ?? '');
    $status = ($_POST['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open';
    $resolved_at = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;
    $reply_val = ($reply === '') ? null : $reply;

    $stmt = $conn->prepare("UPDATE question_reports SET admin_reply = ?, status = ?, resolved_at = ? WHERE id = ?");
    $stmt->bind_param("sssi", $reply_val, $status, $resolved_at, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: question_reports.php?status=" . urlencode($_GET['status'] ?? 'open') . "&saved=1");
    exit();
}

$filter = $_GET['status'] ?? 'open';
if (!in_array($filter, ['open', 'resolved', 'all'], true)) $filter = 'open';

$sql = "SELECT qr.*, u.name AS student_name, u.email, q.set_id AS q_set_id
        FROM question_reports qr
        JOIN users u ON u.id = qr.user_id
        LEFT JOIN questions q ON q.id = qr.question_id";
if ($filter !== 'all') { $sql .= " WHERE qr.status = '" . $filter . "'"; }
$sql .= " ORDER BY qr.created_at DESC";
$reports = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Question Doubts - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/features.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>🚩 Question Doubts / Reports</h2>
        <p style="margin-bottom:14px;">
            Show:
            <a href="?status=open"><?php echo $filter === 'open' ? '<strong>Open</strong>' : 'Open'; ?></a> |
            <a href="?status=resolved"><?php echo $filter === 'resolved' ? '<strong>Resolved</strong>' : 'Resolved'; ?></a> |
            <a href="?status=all"><?php echo $filter === 'all' ? '<strong>All</strong>' : 'All'; ?></a>
        </p>
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

        <?php if ($reports->num_rows === 0): ?>
            <div class="alert alert-info">No doubts in this list.</div>
        <?php endif; ?>

        <?php while ($r = $reports->fetch_assoc()): ?>
            <div class="card">
                <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                    <strong>Set #<?php echo (int)$r['set_number']; ?> — <?php echo htmlspecialchars($r['student_name']); ?> <span style="color:#8a93a8; font-weight:normal;">(<?php echo htmlspecialchars($r['email']); ?>)</span></strong>
                    <span><span class="pill pill-<?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span> <span style="color:#8a93a8; font-size:0.85rem;"><?php echo format_dt($r['created_at'], false); ?></span></span>
                </div>
                <p style="margin:10px 0;"><strong>Question:</strong> <?php echo htmlspecialchars($r['question_text']); ?></p>
                <p class="ans-line"><b>Student's answer:</b> <?php echo htmlspecialchars($r['student_answer']); ?></p>
                <p class="ans-line"><b>Answer key:</b> <?php echo htmlspecialchars($r['correct_answer']); ?></p>
                <div class="doubt-item"><strong>Doubt:</strong> <?php echo nl2br(htmlspecialchars($r['message'])); ?></div>

                <form method="POST" action="question_reports.php?status=<?php echo urlencode($filter); ?>" style="margin-top:12px; max-width:none;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                    <label>Reply to student (optional)</label>
                    <textarea name="admin_reply" rows="2"><?php echo htmlspecialchars((string)$r['admin_reply']); ?></textarea>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <select name="status">
                            <option value="open" <?php echo $r['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="resolved" <?php echo $r['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                        <button type="submit" name="save_report">Save</button>
                        <?php if ($r['result_id']): ?><a href="../result_report.php?result_id=<?php echo (int)$r['result_id']; ?>#q<?php echo (int)$r['question_id']; ?>">View student's report &rarr;</a><?php endif; ?>
                                        <?php if ($r['q_set_id']): ?><a href="manage_questions.php?set_id=<?php echo (int)$r['q_set_id']; ?>&edit=<?php echo (int)$r['question_id']; ?>">Edit this question &rarr;</a><?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>
