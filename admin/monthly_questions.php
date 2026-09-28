<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require '../includes/monthly_helpers.php';
require_admin();
$nav_in_admin = true;

$mid = intval($_GET['id'] ?? $_POST['monthly_id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM monthly_quizzes WHERE id = ?");
$stmt->bind_param("i", $mid);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$quiz) { die("Monthly quiz not found."); }

$errors = [];
$edit_question = null;
$locked = (monthly_status($quiz) !== 'upcoming'); // questions are frozen once the quiz has started

/** Validate a posted question. Returns [error|null, data]. */
function read_question_post() {
    $type = in_array($_POST['type'] ?? '', ['mcq', 'tf'], true) ? $_POST['type'] : 'mcq';
    $qtext = trim($_POST['question_text'] ?? '');
    $correct = trim($_POST['correct_answer'] ?? '');
    $a = $b = $c = $d = null;
    if ($qtext === '') return ["Question text is required.", null];
    if ($type === 'mcq') {
        $a = trim($_POST['option_a'] ?? ''); $b = trim($_POST['option_b'] ?? '');
        $c = trim($_POST['option_c'] ?? ''); $d = trim($_POST['option_d'] ?? '');
        if ($a === '' || $b === '' || $c === '' || $d === '') return ["All four options are required for MCQ.", null];
        $correct = strtoupper($correct);
        if (!in_array($correct, ['A','B','C','D'], true)) return ["Correct answer for MCQ must be A, B, C or D.", null];
    } else {
        $a = 'True'; $b = 'False';
        $correct = ucfirst(strtolower($correct));
        if (!in_array($correct, ['True','False'], true)) return ["Correct answer for True/False must be True or False.", null];
    }
    return [null, ['type'=>$type,'text'=>$qtext,'a'=>$a,'b'=>$b,'c'=>$c,'d'=>$d,'correct'=>$correct]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    csrf_check();

    // ---- ADD ----
    if (isset($_POST['add_question'])) {
        list($err, $q) = read_question_post();
        if ($err) { $errors[] = $err; }
        else {
            $stmt = $conn->prepare("INSERT INTO monthly_questions (monthly_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $mid, $q['type'], $q['text'], $q['a'], $q['b'], $q['c'], $q['d'], $q['correct']);
            $stmt->execute();
            $stmt->close();
            header("Location: monthly_questions.php?id=$mid&saved=1");
            exit();
        }
    }

    // ---- UPDATE ----
    if (isset($_POST['update_question'])) {
        $qid = intval($_POST['question_id'] ?? 0);
        list($err, $q) = read_question_post();
        if ($err) {
            $errors[] = $err;
            $edit_question = ['id'=>$qid,'type'=>$_POST['type'] ?? 'mcq','question_text'=>$_POST['question_text'] ?? '',
                'option_a'=>$_POST['option_a'] ?? '','option_b'=>$_POST['option_b'] ?? '','option_c'=>$_POST['option_c'] ?? '',
                'option_d'=>$_POST['option_d'] ?? '','correct_answer'=>$_POST['correct_answer'] ?? ''];
        } else {
            $stmt = $conn->prepare("UPDATE monthly_questions SET type=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=? WHERE id=? AND monthly_id=?");
            $stmt->bind_param("sssssssii", $q['type'], $q['text'], $q['a'], $q['b'], $q['c'], $q['d'], $q['correct'], $qid, $mid);
            $stmt->execute();
            $stmt->close();
            header("Location: monthly_questions.php?id=$mid&saved=1");
            exit();
        }
    }

    // ---- DELETE ----
    if (isset($_POST['delete_question'])) {
        $qid = intval($_POST['question_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM monthly_questions WHERE id = ? AND monthly_id = ?");
        $stmt->bind_param("ii", $qid, $mid);
        $stmt->execute();
        $stmt->close();
        header("Location: monthly_questions.php?id=$mid");
        exit();
    }

    // ---- COPY all questions of a practice set into this monthly quiz ----
    if (isset($_POST['import_set'])) {
        $sid = intval($_POST['set_id'] ?? 0);
        $stmt = $conn->prepare("INSERT INTO monthly_questions (monthly_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer)
                                SELECT ?, type, question_text, option_a, option_b, option_c, option_d, correct_answer FROM questions WHERE set_id = ? ORDER BY id");
        $stmt->bind_param("ii", $mid, $sid);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        header("Location: monthly_questions.php?id=$mid&imported=" . max(0, $n));
        exit();
    }

    // ---- DELETE ALL ----
    if (isset($_POST['clear_all'])) {
        $stmt = $conn->prepare("DELETE FROM monthly_questions WHERE monthly_id = ?");
        $stmt->bind_param("i", $mid);
        $stmt->execute();
        $stmt->close();
        header("Location: monthly_questions.php?id=$mid");
        exit();
    }
}

if (isset($_GET['edit']) && !$edit_question && !$locked) {
    $qid = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM monthly_questions WHERE id = ? AND monthly_id = ?");
    $stmt->bind_param("ii", $qid, $mid);
    $stmt->execute();
    $edit_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$q_list = monthly_questions($conn, $mid);
$mcq_count = 0; $tf_count = 0;
foreach ($q_list as $r) { if ($r['type'] === 'mcq') $mcq_count++; else $tf_count++; }
$sets = $conn->query("SELECT qs.id, qs.set_number, qs.title, (SELECT COUNT(*) FROM questions q WHERE q.set_id = qs.id) AS c FROM question_sets qs ORDER BY qs.set_number");
$cur = $edit_question ? $edit_question['type'] : 'mcq';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Quiz Questions - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/features.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>📝 Questions — <?php echo htmlspecialchars($quiz['title']); ?></h2>
        <p><a href="manage_monthly.php">&larr; Back to Monthly Quizzes</a></p>
        <p style="color:#667; margin-bottom:12px;">
            Starts <strong><?php echo date("d M Y, h:i A", strtotime($quiz['start_time'])); ?></strong> ·
            This quiz has <strong><?php echo $mcq_count; ?></strong> MCQ + <strong><?php echo $tf_count; ?></strong> True/False = <strong><?php echo count($q_list); ?></strong> question(s).
            Each correct answer = 1 mark.
        </p>

        <?php if ($locked): ?>
            <div class="alert alert-info">🔒 This quiz has already started, so its questions can no longer be changed.</div>
        <?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
        <?php if (isset($_GET['imported'])): ?><div class="alert alert-success"><?php echo (int)$_GET['imported']; ?> question(s) copied from the set.</div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?></div>
        <?php endif; ?>

        <?php if (!$locked): ?>
        <div class="card">
            <h3><?php echo $edit_question ? 'Edit Question' : 'Add a Question'; ?></h3>
            <form method="POST" action="monthly_questions.php?id=<?php echo $mid; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="monthly_id" value="<?php echo $mid; ?>">
                <?php if ($edit_question): ?><input type="hidden" name="question_id" value="<?php echo (int)$edit_question['id']; ?>"><?php endif; ?>

                <label>Question Type</label>
                <select name="type" id="qType" onchange="toggleFields()">
                    <option value="mcq" <?php echo $cur==='mcq'?'selected':''; ?>>Multiple Choice (MCQ)</option>
                    <option value="tf" <?php echo $cur==='tf'?'selected':''; ?>>True / False</option>
                </select>

                <label>Question Text</label>
                <textarea name="question_text" required><?php echo $edit_question ? htmlspecialchars($edit_question['question_text']) : ''; ?></textarea>

                <div id="mcqFields">
                    <?php foreach (['a','b','c','d'] as $o): ?>
                        <label>Option <?php echo strtoupper($o); ?></label>
                        <input type="text" name="option_<?php echo $o; ?>" value="<?php echo ($edit_question && $edit_question['type']==='mcq') ? htmlspecialchars($edit_question['option_'.$o]) : ''; ?>">
                    <?php endforeach; ?>
                </div>

                <label>Correct Answer</label>
                <input type="text" name="correct_answer" placeholder="A/B/C/D for MCQ, True or False for True/False"
                       value="<?php echo $edit_question ? htmlspecialchars($edit_question['correct_answer']) : ''; ?>" required>

                <button type="submit" name="<?php echo $edit_question ? 'update_question' : 'add_question'; ?>"><?php echo $edit_question ? 'Update Question' : 'Add Question'; ?></button>
                <?php if ($edit_question): ?> <a class="btn btn-secondary" href="monthly_questions.php?id=<?php echo $mid; ?>">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>Or copy questions from an existing set</h3>
            <p style="color:#667; margin-bottom:8px;">Copies all questions of the chosen set into this monthly quiz (the original set is not changed). You can then edit/delete individual questions here.</p>
            <form method="POST" action="monthly_questions.php?id=<?php echo $mid; ?>" style="max-width:none;" onsubmit="return confirm('Copy all questions of this set into the monthly quiz?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="monthly_id" value="<?php echo $mid; ?>">
                <select name="set_id" required>
                    <?php while ($s = $sets->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>">#<?php echo $s['set_number']; ?> — <?php echo htmlspecialchars($s['title']); ?> (<?php echo (int)$s['c']; ?> questions)</option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="import_set">Copy Set Questions</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                <h3 style="margin:0;">Questions of this quiz (<?php echo count($q_list); ?>)</h3>
                <?php if (!$locked && count($q_list) > 0): ?>
                    <form method="POST" action="monthly_questions.php?id=<?php echo $mid; ?>" class="inline-form" onsubmit="return confirm('Delete ALL questions of this monthly quiz?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="monthly_id" value="<?php echo $mid; ?>">
                        <button type="submit" name="clear_all" class="btn-danger">Delete all</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (count($q_list) === 0): ?>
                <div class="alert alert-info" style="margin-top:10px;">No questions yet. Add questions above (or copy from a set) — students cannot start a quiz that has no questions.</div>
            <?php else: ?>
            <table>
                <tr><th>#</th><th>Type</th><th>Question</th><th>Correct</th><?php if (!$locked): ?><th>Actions</th><?php endif; ?></tr>
                <?php foreach ($q_list as $i => $q): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo strtoupper($q['type']); ?></td>
                        <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                        <td><?php echo htmlspecialchars($q['correct_answer']); ?></td>
                        <?php if (!$locked): ?>
                        <td>
                            <a href="monthly_questions.php?id=<?php echo $mid; ?>&edit=<?php echo $q['id']; ?>">Edit</a>
                            <form method="POST" action="monthly_questions.php?id=<?php echo $mid; ?>" class="inline-form" onsubmit="return confirm('Delete this question?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="monthly_id" value="<?php echo $mid; ?>">
                                <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                <button type="submit" name="delete_question" class="btn-danger">Delete</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleFields() {
            var t = document.getElementById('qType');
            if (!t) return;
            document.getElementById('mcqFields').style.display = (t.value === 'mcq') ? 'block' : 'none';
        }
        toggleFields();
    </script>
</body>
</html>
