<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require_admin();
$nav_in_admin = true;

$set_id = intval($_GET['set_id'] ?? 0);
$errors = [];
$edit_question = null;

// ---- CREATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $set_id = intval($_POST['set_id']);
    $type = in_array($_POST['type'] ?? '', ['mcq', 'tf'], true) ? $_POST['type'] : 'mcq';
    $qtext = trim($_POST['question_text']);
    $a = $b = $c = $d = null;
    $correct = trim($_POST['correct_answer'] ?? '');

    if ($qtext === '' || $correct === '') {
        $errors[] = "Question text and correct answer are required.";
    } else {
        if ($type === 'mcq') {
            $a = trim($_POST['option_a'] ?? '');
            $b = trim($_POST['option_b'] ?? '');
            $c = trim($_POST['option_c'] ?? '');
            $d = trim($_POST['option_d'] ?? '');
            if ($a === '' || $b === '' || $c === '' || $d === '') {
                $errors[] = "All four options are required for MCQ.";
            }
            $correct = strtoupper($correct);
            if (!in_array($correct, ['A','B','C','D'], true)) {
                $errors[] = "Correct answer for MCQ must be A, B, C or D.";
            }
        } elseif ($type === 'tf') {
            $a = 'True'; $b = 'False';
            if (!in_array($correct, ['True','False'])) {
                $errors[] = "Correct answer for True/False must be True or False.";
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $set_id, $type, $qtext, $a, $b, $c, $d, $correct);
            $stmt->execute();
            $stmt->close();
            header("Location: manage_questions.php?set_id=$set_id");
            exit();
        }
    }
}

// ---- UPDATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    $id = intval($_POST['question_id']);
    $set_id = intval($_POST['set_id']);
    $type = in_array($_POST['type'] ?? '', ['mcq', 'tf'], true) ? $_POST['type'] : 'mcq';
    $qtext = trim($_POST['question_text']);
    $a = $b = $c = $d = null;
    $correct = trim($_POST['correct_answer'] ?? '');

    if ($type === 'mcq') {
        $a = trim($_POST['option_a'] ?? '');
        $b = trim($_POST['option_b'] ?? '');
        $c = trim($_POST['option_c'] ?? '');
        $d = trim($_POST['option_d'] ?? '');
    } elseif ($type === 'tf') {
        $a = 'True'; $b = 'False';
    }

    if ($type === 'mcq') { $correct = strtoupper($correct); }
    $valid = ($qtext !== '') && (($type === 'mcq' && in_array($correct, ['A','B','C','D'], true) && $a !== '' && $b !== '' && $c !== '' && $d !== '')
                              || ($type === 'tf' && in_array($correct, ['True','False'], true)));
    if (!$valid) {
        $errors[] = "Update failed: check the question text, all 4 options and the correct answer (MCQ: A/B/C/D, True/False: True or False).";
        $edit_question = ['id' => $id, 'type' => $type, 'question_text' => $qtext, 'option_a' => $a, 'option_b' => $b, 'option_c' => $c, 'option_d' => $d, 'correct_answer' => $correct];
    } else {
    $stmt = $conn->prepare("UPDATE questions SET type=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=? WHERE id=?");
    $stmt->bind_param("sssssssi", $type, $qtext, $a, $b, $c, $d, $correct, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_questions.php?set_id=$set_id");
    exit();
    }
}

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_questions.php?set_id=$set_id");
    exit();
}

// ---- Load question to edit ----
if (isset($_GET['edit']) && !$edit_question) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_question = $stmt->get_result()->fetch_assoc();
}

$stmt = $conn->prepare("SELECT * FROM question_sets WHERE id = ?");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$set = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT * FROM questions WHERE set_id = ? ORDER BY id");
$stmt->bind_param("i", $set_id);
$stmt->execute();
$questions = $stmt->get_result();
$mcq_count = 0; $other_count = 0;
$q_list = [];
while ($row = $questions->fetch_assoc()) {
    $q_list[] = $row;
    if ($row['type'] === 'mcq') $mcq_count++; else $other_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Questions - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>.hide{display:none;}</style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>Manage Questions <?php echo $set ? '- Set #' . $set['set_number'] . ' — ' . htmlspecialchars($set['title']) : ''; ?></h2>
        <p><a href="manage_quizzes.php">&larr; Back to Sets</a></p>
        <p style="color:#667;">This set currently has <strong><?php echo $mcq_count; ?></strong> MCQ and <strong><?php echo $other_count; ?></strong> True/False question(s). Target: 10 MCQ + 5 True/False = 15 total.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><?php echo $edit_question ? 'Edit Question' : 'Add Question'; ?></h3>
            <form method="POST" action="manage_questions.php" id="qForm">
                <input type="hidden" name="set_id" value="<?php echo $set_id; ?>">
                <?php if ($edit_question): ?>
                    <input type="hidden" name="question_id" value="<?php echo $edit_question['id']; ?>">
                <?php endif; ?>

                <label>Question Type</label>
                <select name="type" id="qType" onchange="toggleFields()">
                    <?php $cur = $edit_question ? $edit_question['type'] : 'mcq'; ?>
                    <option value="mcq" <?php echo $cur==='mcq'?'selected':''; ?>>Multiple Choice (MCQ)</option>
                    <option value="tf" <?php echo $cur==='tf'?'selected':''; ?>>True / False</option>
                </select>

                <label>Question Text</label>
                <textarea name="question_text" required><?php echo $edit_question ? htmlspecialchars($edit_question['question_text']) : ''; ?></textarea>

                <div id="mcqFields">
                    <label>Option A</label>
                    <input type="text" name="option_a" value="<?php echo $edit_question && $edit_question['type']==='mcq' ? htmlspecialchars($edit_question['option_a']) : ''; ?>">
                    <label>Option B</label>
                    <input type="text" name="option_b" value="<?php echo $edit_question && $edit_question['type']==='mcq' ? htmlspecialchars($edit_question['option_b']) : ''; ?>">
                    <label>Option C</label>
                    <input type="text" name="option_c" value="<?php echo $edit_question && $edit_question['type']==='mcq' ? htmlspecialchars($edit_question['option_c']) : ''; ?>">
                    <label>Option D</label>
                    <input type="text" name="option_d" value="<?php echo $edit_question && $edit_question['type']==='mcq' ? htmlspecialchars($edit_question['option_d']) : ''; ?>">
                </div>

                <label>Correct Answer</label>
                <input type="text" name="correct_answer" id="correctAnswerInput"
                       placeholder="A/B/C/D for MCQ, True or False for True/False"
                       value="<?php echo $edit_question ? htmlspecialchars($edit_question['correct_answer']) : ''; ?>" required>
                <p style="color:#8a93a8; font-size:0.85rem; margin-top:-6px;">MCQ: type A, B, C or D. True/False: type True or False.</p>

                <button type="submit" name="<?php echo $edit_question ? 'update_question' : 'add_question'; ?>">
                    <?php echo $edit_question ? 'Update Question' : 'Add Question'; ?>
                </button>
            </form>
        </div>

        <div class="card">
            <h3>Existing Questions</h3>
            <table>
                <tr><th>Type</th><th>Question</th><th>Correct</th><th>Actions</th></tr>
                <?php foreach ($q_list as $q): ?>
                    <tr>
                        <td><?php echo strtoupper($q['type']); ?></td>
                        <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                        <td><?php echo htmlspecialchars($q['correct_answer']); ?></td>
                        <td>
                            <a href="manage_questions.php?set_id=<?php echo $set_id; ?>&edit=<?php echo $q['id']; ?>">Edit</a> |
                            <a href="manage_questions.php?set_id=<?php echo $set_id; ?>&delete=<?php echo $q['id']; ?>"
                               onclick="return confirm('Delete this question?');" style="color:#e74c3c;">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <script>
        function toggleFields() {
            const type = document.getElementById('qType').value;
            const mcqFields = document.getElementById('mcqFields');
            mcqFields.style.display = (type === 'mcq') ? 'block' : 'none';
        }
        toggleFields();
    </script>
</body>
</html>
