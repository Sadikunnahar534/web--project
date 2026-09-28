# Online Quiz System — Web & Internet Edition

**Stack:** HTML, CSS, JavaScript, PHP, MySQL (built to run on XAMPP)

## Setup Guide

### 1. Requirements

XAMPP (provides Apache + PHP + MySQL together) — works on Windows, Mac, and Linux.

### 2. Place the folder

Copy the entire `quiz-system` folder into XAMPP's `htdocs`:

- Windows: `C:\xampp\htdocs\quiz-system`
- Mac/Linux: `/Applications/XAMPP/htdocs/quiz-system` or `/opt/lampp/htdocs/quiz-system`

### 3. Create the database (a single file)

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin` in your browser.
3. Click the **Import** tab at the top.
4. Choose `sql/database.sql` and click **Go** — done in one click.

This creates the `quiz_system` database and all tables (`users`, `question_sets`, `questions`, `results`, `result_answers`, `question_reports`, `monthly_quizzes`, `monthly_questions`, `monthly_attempts`), plus the admin account and 15 sets with a total of 225 questions (Web and Internet topic).

### 4. Configure the database connection

Open `config/db.php` and check that the values match your MySQL setup (on a fresh XAMPP install the defaults are already correct):

```php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "quiz_system";
```

### 5. Admin login

Importing `database.sql` creates the admin account (with a properly hashed password), so you can log in directly:

- **Email:** `admin@quiz.com`
- **Password:** `admin123`

If for some reason you cannot log in, open `http://localhost/quiz-system/reset_admin.php` once. Delete `reset_admin.php` when you're done.

### 6. Run the project

Open in your browser: `http://localhost/quiz-system/index.php`

---

## ✨ How the Features Work

### 15 sets, assigned by student ID

After registering, each student is automatically assigned a set based on their `user_id`:

```
assigned_set_number = ((user_id - 1) % 15) + 1
```

So different students get different sets (not everyone takes the same questions). This logic lives in the `get_assigned_set_number()` function in `includes/auth.php`.

### Question structure per set

Each set has exactly 10 MCQs + 5 True/False questions = 15 questions in total, all on the "Web and Internet" topic and suitable for university level.

### 15-minute timer (must submit in time)

- When the exam starts (`attempt_quiz.php`), the start time is recorded server-side in `$_SESSION`.
- The browser shows a live countdown (from 15:00) — via `js/validate.js` and the inline script in `attempt_quiz.php`.
- When time runs out, the form locks automatically, shows a "Time's up! Not submitted" message, and redirects to `time_up.php`. No result is saved in this case.
- This is also verified server-side in `submit_quiz.php` (elapsed-time check), so disabling JavaScript won't let anyone cheat.

### Option to take another set (optional)

After finishing their assigned set, students get a Yes/No option ("Do you want to take another set?") on `quizzes.php` and `results.php`. Choosing "Yes" lets them pick from the remaining sets they haven't taken yet (`choose_set.php`).

### Celebration video 🎉 on 15/15

If a student scores the full 15 marks, `celebration.php` shows a congratulations page — confetti animation, a big "CONGRATULATIONS" heading, and a random video from the `assets/videos/` folder auto-plays.

To add more videos, no code changes are needed — just upload them from Admin Panel → Manage Celebration Videos (`admin/manage_videos.php`) (`.mp4` / `.webm` / `.ogg`, max 50MB). A random video is picked each time, from however many exist.

### Score list

Each set has a score-list page (`leaderboard.php?set_id=...`). It now shows each student's name, marks, time, and date. It is linked from `quizzes.php`, `results.php`, and `celebration.php`. The admin can also see every student's full results, with names, on the Admin Dashboard.

### Page list

| Page | File |
|---|---|
| Home | `index.php` |
| Register / Login | `register.php`, `login.php` |
| My exam (assigned set) | `quizzes.php` |
| Choose another set | `choose_set.php` |
| Take the exam | `attempt_quiz.php` → `submit_quiz.php` |
| Time up / Not submitted | `time_up.php` |
| My results | `results.php` |
| Celebration (15/15) | `celebration.php` |
| Score list | `leaderboard.php` |
| Admin panel | `admin/dashboard.php`, `admin/manage_quizzes.php`, `admin/manage_questions.php`, `admin/manage_videos.php` |

### CRUD mapping

- **Create:** registration, admin adding new sets/questions, saving a student's exam result, admin uploading new videos
- **Read:** viewing sets/questions, viewing own results, viewing the score list, admin viewing all students/results
- **Update:** admin editing question/set titles
- **Delete:** admin deleting sets/questions/videos

### Security notes (to show the instructor)

- All passwords are hashed with `password_hash()` / `password_verify()` — never stored in plain text.
- All SQL queries use prepared statements (bound parameters) — protected against SQL injection.
- All output is escaped with `htmlspecialchars()` — prevents XSS.
- Access control: pages are protected with `require_login()` and `require_admin()`.
- The 15-minute time limit is enforced not only in JavaScript but also server-side in the PHP session.
- Video uploads check file extension and size, and `basename()` is used on delete to prevent path traversal.

### Important constants (change if needed)

In `config/db.php`:

```php
define('TOTAL_SETS', 15);
define('QUESTIONS_PER_SET', 15);
define('FULL_MARKS', 15);
```

If you increase or decrease the number of sets, don't forget to update `TOTAL_SETS` — students' sets are assigned based on it.

---

## 🆕 New Features (Report, Doubt, Monthly Quiz)

### 1. Detailed report + PDF after the exam

Clicking 📄 **View** from `results.php` / `quizzes.php` opens `result_report.php`: for every question it shows your answer, the correct answer, correct/wrong marking, when you started and submitted, and how long it took.

⬇ **Download PDF** → `download_report.php` (no library needed; `includes/simple_pdf.php` generates the PDF itself).

Note: Bengali names may show as `?` in the PDF; they display correctly in the HTML report.

### 2. Doubt / Report on questions

Below each question on the report page there is a 🚩 **Report a doubt** box. The question text + your answer are saved (in the `question_reports` table), so they remain even if the question is later edited or deleted.

From Admin → Doubts (`admin/question_reports.php`) the admin can view, reply to, and mark doubts as Resolved; students see the reply on their report page.

### 3. Monthly Live Quiz (updated)

- **Scheduling:** Admin → Monthly Quiz (`admin/manage_monthly.php`): schedule a quiz with a title, start time, and duration. You can copy questions from a set right away, or leave it empty and create your own questions.
- **Monthly quiz's own questions:** after scheduling, `admin/monthly_questions.php` opens — here you can Add / Edit / Delete MCQ / True-False questions, and use "Copy questions from a set" to copy all questions from any set (the original set is not changed). Questions lock once the quiz starts. If there are 0 questions, students cannot start the quiz.
- **Ready-made sample quiz:** importing `database.sql` leaves a Monthly Quiz (with 15 questions) scheduled for 8:00 PM the next day. To change the date/time/duration, use Admin → Monthly Quiz → Edit time (only before the quiz starts).
- **Student side:** students see a countdown on `monthly.php`; when the time comes, the page enters the quiz automatically. They must join within 3 minutes of the start (`MONTHLY_JOIN_GRACE_SECONDS` in `config/db.php`). Everyone has the same end time, and answers are auto-submitted when time runs out.
- **Winner:** the highest score wins; ties are broken by who took less time. After the quiz ends, `monthly_leaderboard.php` shows the full ranking with student names, and the winner is congratulated with a "🎉 CONGRATULATIONS <name>!" banner + confetti.
- **Winner video:** the winner (and admin) get a 🎬 **Watch winner video** button on that page. It plays a video from `assets/videos/`, and "Play another video" shows a different one. `celebration1–5.mp4` are already in the folder; more videos can be uploaded from Admin → Manage Celebration Videos (the same videos also play randomly for a 15/15 score).
- **Database:** just one file — `sql/database.sql`. Simply Import it in phpMyAdmin. It deletes nothing: on a fresh install it creates everything (15 sets, admin, sample monthly quiz), and if data already exists it keeps all students/results/questions and only adds the missing parts (e.g. the `monthly_questions` table) — so it is safe to import as many times as you like.

### 4. Result list (with names)

`leaderboard.php` now shows each student's name, marks, time, and date for every set.

### New files

`result_report.php`, `download_report.php`, `monthly.php`, `monthly_attempt.php`, `monthly_leaderboard.php`, `css/features.css`, `includes/report_helpers.php`, `includes/monthly_helpers.php`, `includes/simple_pdf.php`, `admin/manage_monthly.php`, `admin/monthly_questions.php`, `admin/question_reports.php`

### Timezone

`Asia/Dhaka` is set in `config/db.php` (for both PHP and MySQL).
