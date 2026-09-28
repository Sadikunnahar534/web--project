<?php
// Pages inside admin/ must set: $nav_in_admin = true; before including this.
if (!isset($nav_in_admin)) { $nav_in_admin = false; }
$p = $nav_in_admin ? '../' : '';
$a = $nav_in_admin ? '' : 'admin/';
?>
<nav class="navbar">
    <div class="nav-brand">🌐 Web &amp; Internet Quiz</div>
    <ul class="nav-links">
        <li><a href="<?php echo $p; ?>index.php">Home</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($_SESSION['role'] !== 'admin'): ?>
                <li><a href="quizzes.php">My Exam</a></li>
                <li><a href="results.php">My Results</a></li>
                <li><a href="monthly.php">🏆 Monthly Quiz</a></li>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="<?php echo $a; ?>dashboard.php">Admin Panel</a></li>
                <li><a href="<?php echo $a; ?>manage_quizzes.php">Manage Sets</a></li>
                <li><a href="<?php echo $a; ?>manage_monthly.php">Monthly Quiz</a></li>
                <li><a href="<?php echo $a; ?>question_reports.php">Doubts</a></li>
            <?php endif; ?>
            <li><a href="<?php echo $p; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['name']); ?>)</a></li>
        <?php else: ?>
            <li><a href="<?php echo $p; ?>login.php">Login</a></li>
            <li><a href="<?php echo $p; ?>register.php">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
