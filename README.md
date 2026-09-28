Online Quiz System — Web & Internet Edition
Setup Guide
Stack: HTML, CSS, JavaScript, PHP, MySQL (Built to run with XAMPP)
1. Requirements
XAMPP (Provides Apache + PHP + MySQL together) — Works on Windows, Mac, or Linux.
2. Folder Placement
Copy the entire quiz-system folder into the XAMPP htdocs directory:

Windows: C:\xampp\htdocs\quiz-system
Mac/Linux: /Applications/XAMPP/htdocs/quiz-system or /opt/lampp/htdocs/quiz-system
3. Database Setup (Single File)
Open the XAMPP Control Panel and start Apache and MySQL.
Open your browser and go to: http://localhost/phpmyadmin
Click on the Import tab at the top.
Select the sql/database.sql file and click Go — setup is done in a single click.

This will automatically create the quiz_system database along with all required tables (users, question_sets, questions, results, result_answers, question_reports, monthly_quizzes, monthly_questions, monthly_attempts), an admin account, and 15 question sets containing a total of 225 questions (Web and Internet topic).
4. Configuring Database Connection
Open config/db.php and verify whether the connection parameters match your MySQL setup (default XAMPP values are usually correct):$DB_HOST = "localhost";

$DB_USER = "root";

$DB_PASS = "";

$DB_NAME = "quiz_system";
5. Admin Login
Importing database.sql automatically creates an admin account with a hashed password. You can log in directly:

Email: admin@quiz.com
Password: admin123

(If you encounter login issues for any reason, open http://localhost/quiz-system/reset_admin.php once in your browser. Delete reset_admin.php after completing the reset.)
6. Running the Project
Open your browser and navigate to:http://localhost/quiz-system/index.php
How the Features Work
15 Question Sets Assigned by Student ID
After registration, a student is automatically assigned a question set based on their user_id:assigned_set_number = ((user_id - 1) % 15) + 1

This ensures different students get different sets rather than taking the exam on identical questions. This logic is located in the get_assigned_set_number() function inside includes/auth.php.
Structure per Question Set
Each set contains exactly 10 MCQs + 5 True/False questions (total of 15 questions), tailored for university-level topics on "Web and Internet".
15-Minute Timer (Must Submit in Time)
When an exam begins (attempt_quiz.php), the start timestamp is saved on the server side inside $_SESSION.
A live countdown timer (from 15:00) is displayed on the browser via js/validate.js and inline script in attempt_quiz.php.
When time expires, the form automatically locks, displays a "Time is up! Not Submitted" message, and redirects to time_up.php. No results are saved in this scenario.
This is also verified server-side inside submit_quiz.php (elapsed time check), preventing bypasses even if JavaScript is disabled.
Option to Take Another Set (Optional)
After completing their assigned set, students get a Yes/No option asking if they want to attempt another set (quizzes.php and results.php). Selecting "Yes" opens choose_set.php, allowing them to select from remaining (unattempted) sets.
Celebration Video for 15/15 Marks
When a student scores a full 15 marks, they are directed to a celebration page (celebration.php) featuring confetti animations, a large "CONGRATULATIONS" banner, and an auto-playing video randomly picked from the assets/videos/ directory.
No code modification is needed to add new videos: upload directly via Admin Panel → Manage Celebration Videos (admin/manage_videos.php). Supported formats: .mp4, .webm, .ogg (max 50MB).
Anonymous Score List
Each set has a leaderboard page (leaderboard.php?set_id=...) displaying marks, dates, and timestamps without revealing student names. Admins can view complete results with student names inside the Admin Dashboard.
Application Architecture
Page Mapping
Feature / Page
File Path
Home
index.php
Register / Login
register.php, login.php
My Quiz (Assigned Set)
quizzes.php
Choose Another Set
choose_set.php
Attempt Quiz
attempt_quiz.php → submit_quiz.php
Time Up / Not Submitted
time_up.php
My Results
results.php
Celebration (15/15)
celebration.php
Anonymous Score List
leaderboard.php
Admin Panel
admin/dashboard.php, admin/manage_quizzes.php, admin/manage_questions.php, admin/manage_videos.php

CRUD Mapping
Create: Student registration, admin adding sets/questions, saving quiz attempt results, admin video uploads.
Read: View sets/questions, personal test results, anonymous score list, admin overview of all students/results.
Update: Admin editing question/set titles.
Delete: Admin deleting sets, questions, or videos.
Security Features (Instructor Overview)
Password Hashing: All passwords use password_hash() and password_verify(). Never stored as plain text.
SQL Injection Prevention: All SQL queries use prepared statements with parameter binding.
XSS Defense: All visual dynamic outputs are sanitized using htmlspecialchars().
Access Control: Restricted pages are protected using require_login() and require_admin().
Server-side Validation: The 15-minute time limit is enforced via PHP sessions, not just JavaScript.
File Upload Security: File extension and size checks are performed on video uploads, and basename() is used during file deletions to prevent Path Traversal attacks.
Important Constants
Defined in config/db.php:define('TOTAL_SETS', 15);

define('QUESTIONS_PER_SET', 15);

define('FULL_MARKS', 15);

(If you increase or decrease the total number of sets, update TOTAL_SETS accordingly, as student assignment logic depends on it.)
Advanced Features
1. Detailed Report + PDF Download
Clicking View on results.php or quizzes.php opens result_report.php. It displays submitted answers, correct answers, right/wrong markings, start time, submission time, and total time elapsed.
Download PDF triggers download_report.php (built using includes/simple_pdf.php without third-party library dependencies).
2. Question Doubts & Reporting
Inside result_report.php, every question includes a Report a doubt input box.
Reports save the user's input alongside the original question snapshot in the question_reports table (retaining context even if the original question is later modified or deleted).
Admins handle reports via Admin → Doubts (admin/question_reports.php) to reply or mark as resolved. Students can review replies directly on their report page.
3. Monthly Live Quiz
Scheduling: Admins can schedule live quizzes via Admin → Monthly Quiz (admin/manage_monthly.php) by specifying titles, start times, and durations. Questions can be built manually or copied from an existing set.
Dedicated Questions: Questions can be managed on admin/monthly_questions.php. Modifying monthly questions does not alter the original question set. Questions lock once the contest begins.
Sample Quiz Included: database.sql includes a sample Monthly Quiz (15 questions) pre-scheduled for 8:00 PM the following day.
Live Participation: Students monitor countdowns on monthly.php. The system grants access automatically when live. Joining is permitted within 3 minutes of start time (MONTHLY_JOIN_GRACE_SECONDS in config/db.php). All participants share the same end time; answers auto-submit when time expires.
Winner Logic: Highest score wins. In the event of a tie, the participant with the faster completion time is ranked higher.
Leaderboard & Celebrations: Results display on monthly_leaderboard.php. The winner receives a banner and confetti.
Winner Video: Winners can stream video content directly via Watch winner video, with an option to cycle through available videos using Play another video.
Database Import Safety: Re-importing sql/database.sql updates structure/missing tables without dropping existing student records or quiz attempts.
4. Named Result Leaderboard
leaderboard.php displays student names, scores, durations, and attempt timestamps per set.
New File Additions
result_report.php, download_report.php, monthly.php, monthly_attempt.php, monthly_leaderboard.php, css/features.css, includes/report_helpers.php, includes/monthly_helpers.php, includes/simple_pdf.php, admin/manage_monthly.php, admin/monthly_questions.php, admin/question_reports.php
Timezone Setting
Set to Asia/Dhaka across both PHP and MySQL configuration in config/db.php.
