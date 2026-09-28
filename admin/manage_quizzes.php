<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require_admin();
$nav_in_admin = true;

$errors = [];

// ---- CREATE a new set ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_set'])) {
    $title = trim($_POST['title']);
    $topic = trim($_POST['topic']) ?: 'Web and Internet';

    if ($title === '') {
        $errors[] = "Set title is required.";
    } else {
        $max = $conn->query("SELECT COALESCE(MAX(set_number),0) m FROM question_sets")->fetch_assoc()['m'];
        $next_number = $max + 1;
        $stmt = $conn->prepare("INSERT INTO question_sets (set_number, title, topic) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $next_number, $title, $topic);
        $stmt->execute();
        $stmt->close();
        header("Location: manage_quizzes.php");
        exit();
    }
}

// ---- UPDATE a set's title/topic ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_set'])) {
    $id = intval($_POST['set_id']);
    $title = trim($_POST['title']);
    $topic = trim($_POST['topic']) ?: 'Web and Internet';
    if ($title !== '') {
        $stmt = $conn->prepare("UPDATE question_sets SET title=?, topic=? WHERE id=?");
        $stmt->bind_param("ssi", $title, $topic, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_quizzes.php");
    exit();
}

// ---- DELETE a set ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM question_sets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_quizzes.php");
    exit();
}

$quizzes = $conn->query("
    SELECT qs.*, (SELECT COUNT(*) FROM questions q WHERE q.set_id = qs.id) AS q_count
    FROM question_sets qs ORDER BY set_number
");
$total_sets_now = $conn->query("SELECT COUNT(*) c FROM question_sets")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Question Sets - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h2>Manage Question Sets</h2>
        <p style="color:#667;">The system is designed around <strong>15 sets</strong> (each student is auto-assigned one by ID). You currently have <strong><?php echo $total_sets_now; ?></strong> set(s).
        <?php if ($total_sets_now != TOTAL_SETS): ?>
            <br>⚠️ This differs from the <code>TOTAL_SETS</code> constant (<?php echo TOTAL_SETS; ?>) in <code>config/db.php</code> — update that constant to match if you add/remove sets.
        <?php endif; ?>
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Add New Set</h3>
            <form method="POST" action="manage_quizzes.php">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" placeholder="e.g. Advanced Web Security" required>

                <label for="topic">Topic</label>
                <input type="text" id="topic" name="topic" value="Web and Internet">

                <button type="submit" name="add_set">Add Set</button>
            </form>
        </div>

        <div class="card">
            <h3>Existing Sets</h3>
            <table>
                <tr><th>#</th><th>Title</th><th>Topic</th><th>Questions</th><th>Actions</th></tr>
                <?php while ($q = $quizzes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $q['set_number']; ?></td>
                        <td><?php echo htmlspecialchars($q['title']); ?></td>
                        <td><?php echo htmlspecialchars($q['topic']); ?></td>
                        <td><?php echo $q['q_count']; ?> / <?php echo QUESTIONS_PER_SET; ?></td>
                        <td>
                            <a href="manage_questions.php?set_id=<?php echo $q['id']; ?>">Questions</a> |
                            <a href="../leaderboard.php?set_id=<?php echo $q['id']; ?>">Score List</a> |
                            <a href="manage_quizzes.php?delete=<?php echo $q['id']; ?>"
                               onclick="return confirm('Delete this set and all its questions/results?');"
                               style="color:#e74c3c;">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
