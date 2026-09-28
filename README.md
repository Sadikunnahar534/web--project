# Online Quiz System — Web & Internet Edition
### Setup Guide (বাংলা)

Stack: **HTML, CSS, JavaScript, PHP, MySQL** (XAMPP দিয়ে চালানোর জন্য তৈরি)

---

## ১. প্রয়োজনীয় জিনিসপত্র (Requirements)
- **XAMPP** (Apache + PHP + MySQL একসাথে দেয়) — Windows/Mac/Linux যেকোনোটাতেই কাজ করবে।

## ২. ফোল্ডার বসানো
পুরো `quiz-system` ফোল্ডারটা XAMPP-এর `htdocs`-এ কপি করো:
- Windows: `C:\xampp\htdocs\quiz-system`
- Mac/Linux: `/Applications/XAMPP/htdocs/quiz-system` বা `/opt/lampp/htdocs/quiz-system`

## ৩. ডেটাবেস তৈরি করা (একটাই ফাইল)
1. XAMPP Control Panel থেকে **Apache** আর **MySQL** চালু করো।
2. ব্রাউজারে যাও: `http://localhost/phpmyadmin`
3. উপরে **Import** ট্যাবে ক্লিক করো।
4. `sql/database.sql` ফাইলটা বেছে নিয়ে **Go** চাপো — ব্যস, এক ক্লিকেই কাজ শেষ।
   - এটা `quiz_system` ডেটাবেস + সব টেবিল (users, question_sets, questions, results, result_answers, question_reports, monthly_quizzes, monthly_questions, monthly_attempts) + admin অ্যাকাউন্ট + **১৫টা সেট, মোট ২২৫টা প্রশ্ন** (Web and Internet টপিক) — সবকিছু একসাথে বানিয়ে/ভরে দেবে।

## ৪. ডেটাবেস কানেকশন ঠিক করা
`config/db.php` ফাইল খুলে দেখো এই মানগুলো তোমার MySQL সেটআপের সাথে মিলছে কিনা (ফ্রেশ XAMPP-এ ডিফল্ট মান এমনিতেই ঠিক থাকে):
```php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "quiz_system";
```

## ৫. Admin লগইন ঠিক করা
database.sql ইমপোর্ট করলেই admin অ্যাকাউন্ট তৈরি হয়ে যায় (ঠিক পাসওয়ার্ড হ্যাশ সহ) — সরাসরি লগইন করো:
- Email: `admin@quiz.com`
- Password: `admin123`

(কোনো কারণে লগইন না হলেই শুধু `http://localhost/quiz-system/reset_admin.php` একবার খোলো। **কাজ শেষে `reset_admin.php` ডিলিট করে দাও।**)

## ৬. প্রজেক্ট রান করা
ব্রাউজারে যাও:
```
http://localhost/quiz-system/index.php
```

---

## ✨ নতুন ফিচারগুলো কীভাবে কাজ করে

### ১৫টা সেট, স্টুডেন্ট আইডি অনুযায়ী assign
প্রতিটা স্টুডেন্ট রেজিস্টার করার পর তার `user_id` অনুযায়ী স্বয়ংক্রিয়ভাবে একটা সেট assign হয়ে যায়:
```
assigned_set_number = ((user_id - 1) % 15) + 1
```
তাই একেক স্টুডেন্ট একেক সেট পাবে (সবাই একই প্রশ্নে পরীক্ষা দেবে না)। এই লজিকটা আছে `includes/auth.php`-এর `get_assigned_set_number()` ফাংশনে।

### প্রতি সেটে প্রশ্নের গঠন
প্রতিটা সেটে ঠিক **১০টা MCQ + ৫টা True/False = মোট ১৫টা প্রশ্ন** আছে, সব "Web and Internet" টপিকের উপর, ইউনিভার্সিটি লেভেলের জন্য উপযোগী।

### ১৫ মিনিটের টাইমার (must-submit-in-time)
- পরীক্ষা শুরু হওয়ার সাথে সাথে (`attempt_quiz.php`) সার্ভার সাইডে (`$_SESSION`-এ) শুরুর সময় রেকর্ড হয়।
- ব্রাউজারে একটা লাইভ কাউন্টডাউন (১৫:০০ থেকে) দেখায় — `js/validate.js` + `attempt_quiz.php`-এর ইনলাইন স্ক্রিপ্ট।
- সময় শেষ হয়ে গেলে ফর্ম নিজে থেকেই লক হয়ে যায় এবং **"সময় শেষ! Submit হয়নি"** মেসেজ দেখিয়ে `time_up.php`-তে নিয়ে যায় — এই ক্ষেত্রে কোনো ফলাফল সেভ হয় না।
- এটা `submit_quiz.php`-তেও সার্ভার সাইডে যাচাই করা হয় (elapsed time check), তাই কেউ JavaScript বন্ধ করে দিলেও ফাঁকি দিতে পারবে না।

### আরেকটা সেট দেওয়ার Option (optional)
নিজের assign করা সেট শেষ করার পর, "আরেকটা সেট দিতে চাও কিনা" — Yes/No option আসবে (`quizzes.php` ও `results.php`-তে)। "Yes" দিলে বাকি (এখনো না দেওয়া) সেটগুলো থেকে বেছে নিতে পারবে (`choose_set.php`)।

### ১৫/১৫ পেলে Celebration Video 🎉
কোনো স্টুডেন্ট পুরো ১৫ নম্বর পেলে `celebration.php`-এ একটা কনগ্র্যাচুলেশন পেজ দেখায় — কনফেটি অ্যানিমেশন, বড় করে "CONGRATULATIONS" লেখা, এবং `assets/videos/` ফোল্ডার থেকে র‍্যান্ডমভাবে একটা ভিডিও অটো-প্লে হয়।

**নতুন ভিডিও যোগ করতে চাইলে** কোনো কোড পরিবর্তন লাগবে না — Admin Panel → **Manage Celebration Videos** (`admin/manage_videos.php`) পেজ থেকে সরাসরি আপলোড করে দিলেই হবে (.mp4/.webm/.ogg, max 50MB)। যতগুলো ভিডিও থাকবে, প্রতিবার এলোমেলোভাবে একটা বেছে দেখানো হবে।

### Anonymous Score List (নাম ছাড়া, শুধু মার্ক + তারিখ/সময়)
প্রতিটা সেটের জন্য একটা স্কোর-লিস্ট পেজ আছে (`leaderboard.php?set_id=...`) — এখানে **নাম দেখানো হয় না**, শুধু **মার্ক, তারিখ ও সময়** (কে কবে কত পেয়েছে) দেখানো হয়। এটা `quizzes.php`, `results.php`, `celebration.php` থেকে লিংক করা আছে। Admin চাইলে Admin Dashboard-এ প্রতিটা স্টুডেন্টের নাম-সহ পুরো ফলাফলও দেখতে পারবে।

---

## পেজের তালিকা
| পেজ | ফাইল |
|---|---|
| Home | `index.php` |
| Register / Login | `register.php`, `login.php` |
| আমার পরীক্ষা (assigned set) | `quizzes.php` |
| আরেকটা সেট বেছে নেওয়া | `choose_set.php` |
| পরীক্ষা দেওয়া | `attempt_quiz.php` → `submit_quiz.php` |
| সময় শেষ / Not Submitted | `time_up.php` |
| আমার ফলাফল | `results.php` |
| Celebration (১৫/১৫) | `celebration.php` |
| Anonymous Score List | `leaderboard.php` |
| Admin Panel | `admin/dashboard.php`, `admin/manage_quizzes.php`, `admin/manage_questions.php`, `admin/manage_videos.php` |

## CRUD Mapping
- **Create**: রেজিস্ট্রেশন, admin নতুন সেট/প্রশ্ন যোগ করা, স্টুডেন্টের পরীক্ষার ফলাফল সেভ হওয়া, admin নতুন ভিডিও আপলোড
- **Read**: সেট/প্রশ্ন দেখা, নিজের ফলাফল দেখা, anonymous score list দেখা, admin-এর সব স্টুডেন্ট/ফলাফল দেখা
- **Update**: admin প্রশ্ন/সেটের টাইটেল এডিট করা
- **Delete**: admin সেট/প্রশ্ন/ভিডিও ডিলিট করা

## নিরাপত্তা (Security notes — instructor-কে দেখানোর জন্য)
- সব পাসওয়ার্ড `password_hash()`/`password_verify()` দিয়ে হ্যাশ করা — কখনো plain text-এ সেভ হয় না।
- সব SQL query prepared statements দিয়ে (bound parameters) — SQL injection থেকে সুরক্ষিত।
- সব আউটপুট `htmlspecialchars()` দিয়ে escape করা — XSS আটকানোর জন্য।
- Access control: `require_login()` আর `require_admin()` দিয়ে পেজ সুরক্ষিত।
- ১৫ মিনিটের টাইম-লিমিট শুধু JavaScript-এ নয়, PHP সেশনেও সার্ভার সাইডে চেক করা হয়।
- ভিডিও আপলোডে ফাইল এক্সটেনশন + সাইজ চেক করা হয়, এবং ডিলিটের সময় `basename()` দিয়ে path traversal আটকানো হয়।

## গুরুত্বপূর্ণ কনস্ট্যান্ট (দরকার হলে বদলাতে পারো)
`config/db.php`-তে:
```php
define('TOTAL_SETS', 15);
define('QUESTIONS_PER_SET', 15);
define('FULL_MARKS', 15);
```
সেট সংখ্যা বাড়ালে/কমালে `TOTAL_SETS` আপডেট করতে ভুলো না — এটার উপর ভিত্তি করেই স্টুডেন্টদের সেট assign হয়।


---

## 🆕 নতুন ফিচার (Report, Doubt, Monthly Quiz)

### ১. পরীক্ষার পর Detailed Report + PDF
- `results.php` / `quizzes.php` থেকে **📄 View** চাপলে `result_report.php` খোলে: প্রতিটা প্রশ্নে তোমার উত্তর, সঠিক উত্তর, ঠিক/ভুল মার্কিং, কখন শুরু ও submit করেছ, কতক্ষণ লেগেছে।
- **⬇ Download PDF** → `download_report.php` (কোনো লাইব্রেরি লাগে না, `includes/simple_pdf.php` নিজেই PDF বানায়)।
- PDF-এ বাংলা নাম `?` দেখাতে পারে; HTML রিপোর্টে ঠিক থাকে।

### ২. প্রশ্ন নিয়ে Doubt / Report
- রিপোর্ট পেজে প্রতিটা প্রশ্নের নিচে **🚩 Report a doubt** বক্স। প্রশ্নের টেক্সট + তোমার উত্তর সেভ হয় (`question_reports` টেবিল), প্রশ্ন পরে এডিট/ডিলিট হলেও থাকে।
- Admin → **Doubts** (`admin/question_reports.php`) থেকে দেখা, রিপ্লাই দেওয়া, Resolved করা যায়; স্টুডেন্ট রিপোর্ট পেজে রিপ্লাই দেখে।

### ৩. Monthly Live Quiz (আপডেটেড)
- Admin → **Monthly Quiz** (`admin/manage_monthly.php`): টাইটেল, শুরুর সময়, সময়কাল দিয়ে quiz schedule করো। চাইলে তখনই কোনো সেট থেকে প্রশ্ন কপি করে নিতে পারো, অথবা খালি রেখে নিজের প্রশ্ন বানাতে পারো।
- **Monthly quiz-এর নিজস্ব প্রশ্ন**: schedule করার পর `admin/monthly_questions.php` খোলে — এখানে MCQ / True-False প্রশ্ন Add / Edit / Delete করা যায়, আর "Copy questions from a set" দিয়ে যেকোনো সেটের সব প্রশ্ন কপিও করা যায় (আসল সেট বদলায় না)। কুইজ শুরু হয়ে গেলে প্রশ্ন লক হয়ে যায়। প্রশ্ন ০টা থাকলে স্টুডেন্ট কুইজ শুরু করতে পারবে না।
- **রেডি নমুনা কুইজ**: `database.sql` ইমপোর্ট করলেই একটা Monthly Quiz (১৫টা প্রশ্নসহ) **পরের দিন রাত ৮:০০টায়** schedule হয়ে থাকে। তারিখ/সময়/সময়কাল বদলাতে Admin → Monthly Quiz → **Edit time** (কুইজ শুরুর আগ পর্যন্ত)।
- স্টুডেন্ট `monthly.php` থেকে কাউন্টডাউন দেখে; সময় হলে পেজ নিজে থেকেই কুইজে ঢুকে যায়। শুরুর ৩ মিনিটের মধ্যে join করতে হয় (`config/db.php`-এর `MONTHLY_JOIN_GRACE_SECONDS`)। সবার শেষ সময় একই, সময় শেষে উত্তর অটো-submit।
- **Winner** = সবচেয়ে বেশি নম্বর; সমান হলে যে কম সময় নিয়েছে। কুইজ শেষ হলে `monthly_leaderboard.php`-এ **স্টুডেন্টের নামসহ** পুরো র‍্যাঙ্কিং লিস্ট দেখায়, আর winner-কে "🎉 CONGRATULATIONS <নাম>!" ব্যানার + কনফেটি দিয়ে অভিনন্দন জানানো হয়।
- **Winner Video**: winner (এবং admin) ওই পেজে **🎬 Watch winner video** বাটন পায়। চাপলে `assets/videos/`-এর ভিডিও চলে, "Play another video" দিয়ে অন্যটা দেখা যায়। `celebration1–5.mp4` ফোল্ডারে দেওয়াই আছে; আরও ভিডিও Admin → Manage Celebration Videos থেকে আপলোড করা যায় (১৫/১৫ পেলেও এই ভিডিওগুলোই র‍্যান্ডম চলে)।

> **ডেটাবেস:** শুধু একটাই ফাইল — `sql/database.sql`। phpMyAdmin-এ Import করলেই হবে। এটা **কিছুই ডিলিট করে না**: নতুন ইনস্টলে সব তৈরি করে (১৫ সেট, admin, নমুনা monthly quiz), আর আগে থেকে ডেটা থাকলে স্টুডেন্ট/রেজাল্ট/প্রশ্ন সব রেখে শুধু ঘাটতি অংশ (যেমন `monthly_questions` টেবিল) যোগ করে — তাই যতবার ইচ্ছা ইমপোর্ট করা নিরাপদ।

### ৪. Result List (নামসহ)
`leaderboard.php` এখন প্রতিটা সেটের জন্য স্টুডেন্টের **নাম, মার্ক, সময়, তারিখ** দেখায়।

### নতুন ফাইলের তালিকা
`result_report.php`, `download_report.php`, `monthly.php`, `monthly_attempt.php`, `monthly_leaderboard.php`, `css/features.css`, `includes/report_helpers.php`, `includes/monthly_helpers.php`, `includes/simple_pdf.php`, `admin/manage_monthly.php`, `admin/monthly_questions.php`, `admin/question_reports.php`

### টাইমজোন
`config/db.php`-এ `Asia/Dhaka` সেট করা আছে (PHP + MySQL দুটোতেই)।
