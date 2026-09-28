<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Web &amp; Internet Quiz System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="hero">
            <div class="hero-text">
                <div class="badge">University · Web &amp; Internet Course</div>
                <h1>Online Quiz System</h1>
                <p>Every student is assigned their own unique question set (out of 15 sets) —
                   10 MCQs + 5 True/False questions, 15 minutes on the clock.
                   Score full marks (15/15) and unlock a special congratulations video! 🎉</p>

                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="register.php" class="btn">Get Started — Register</a>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                    </div>
                <?php else: ?>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="btn">Go to Admin Panel</a>
                        <?php else: ?>
                            <a href="quizzes.php" class="btn">Go to My Exam</a>
                            <a href="results.php" class="btn btn-secondary">My Results</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="hero-illustration">
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="100" cy="100" r="92" fill="rgba(255,255,255,0.08)"/>
                    <rect x="40" y="55" width="120" height="80" rx="8" fill="#ffffff" opacity="0.95"/>
                    <rect x="48" y="63" width="104" height="52" rx="3" fill="#2c5aa0"/>
                    <rect x="58" y="72" width="45" height="6" rx="3" fill="#bcd6f5"/>
                    <rect x="58" y="84" width="70" height="6" rx="3" fill="#bcd6f5"/>
                    <rect x="58" y="96" width="55" height="6" rx="3" fill="#bcd6f5"/>
                    <rect x="90" y="135" width="20" height="10" fill="#ffffff" opacity="0.9"/>
                    <rect x="70" y="145" width="60" height="8" rx="4" fill="#ffffff" opacity="0.9"/>
                    <circle cx="150" cy="55" r="16" fill="#f1c40f"/>
                    <path d="M144 55 l4 4 l8 -9" stroke="#7a5b00" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="42" cy="45" r="10" fill="#27ae60"/>
                </svg>
            </div>
        </div>

        <div class="card">
            <h3>How it works</h3>
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="num">15</div>
                    <div class="label">Question Sets</div>
                </div>
                <div class="stat-box">
                    <div class="num">10+5</div>
                    <div class="label">MCQ + True/False</div>
                </div>
                <div class="stat-box">
                    <div class="num">15 min</div>
                    <div class="label">Time Limit</div>
                </div>
                <div class="stat-box">
                    <div class="num">15/15</div>
                    <div class="label">Full Marks Reward 🎉</div>
                </div>
            </div>
            <p style="margin-top:16px;">HTML builds the pages → CSS styles them → JavaScript validates input and
            runs the countdown timer → PHP grades and processes everything on the server → MySQL stores
            students, question sets, and results.</p>
        </div>
    </div>

    <p class="site-footer">Online Quiz System · Web &amp; Internet · Built with HTML, CSS, JavaScript, PHP &amp; MySQL</p>
</body>
</html>
