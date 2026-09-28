-- ============================================================
-- Online Quiz System — Web & Internet Edition
-- ONE database file (import this ONE file in phpMyAdmin)
--
-- SAFE TO IMPORT ANY TIME: it NEVER deletes anything.
--   * Fresh install  -> creates the database, all tables, the admin
--                       account, 15 sets (225 questions) and a sample
--                       monthly quiz.
--   * Existing data  -> keeps every student, result, answer, doubt and
--                       monthly quiz you already have, and only adds
--                       whatever is missing (e.g. the monthly questions
--                       table). Old monthly quizzes are upgraded
--                       automatically.
-- ============================================================

CREATE DATABASE IF NOT EXISTS quiz_system;
USE quiz_system;

-- ---------- USERS TABLE ----------
-- Stores both students and admin. role = 'student' or 'admin'
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,   -- stored using password_hash()
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- QUESTION SETS TABLE ----------
-- Exactly 15 sets are used. Each student is auto-assigned one set based on
-- their user id: assigned_set_number = ((user_id - 1) % 15) + 1
CREATE TABLE IF NOT EXISTS question_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    set_number INT NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    topic VARCHAR(100) NOT NULL DEFAULT 'Web and Internet',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- QUESTIONS TABLE ----------
-- type: 'mcq' (4 options), 'tf' (True/False)
-- Each set has 10 mcq + 5 tf = 15 questions total.
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    set_id INT NOT NULL,
    type ENUM('mcq','tf') NOT NULL DEFAULT 'mcq',
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NULL,
    option_b VARCHAR(255) NULL,
    option_c VARCHAR(255) NULL,
    option_d VARCHAR(255) NULL,
    correct_answer VARCHAR(255) NOT NULL, -- 'A'/'B'/'C'/'D' for mcq, 'True'/'False' for tf
    FOREIGN KEY (set_id) REFERENCES question_sets(id) ON DELETE CASCADE
);

-- ---------- RESULTS TABLE ----------
-- One row per attempt. A student may attempt more than one set if they
-- choose to (offered after finishing their first/assigned set).
CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    set_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   -- = submit time
    started_at DATETIME NULL,                        -- when the student opened the exam
    duration_seconds INT NULL,                       -- time taken
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (set_id) REFERENCES question_sets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt (user_id, set_id) -- one attempt per student per set
);

-- ---------- DETAILED ANSWERS (one row per question in each attempt) ----------
CREATE TABLE IF NOT EXISTS result_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    given_answer VARCHAR(255) NOT NULL DEFAULT '',
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_result_question (result_id, question_id)
);

-- ---------- QUESTION DOUBT / REPORTS (student -> admin) ----------
-- The question text + answers are copied here (snapshot) so the doubt is still
-- readable even if the question is edited or deleted later.
CREATE TABLE IF NOT EXISTS question_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    result_id INT NULL,
    question_id INT NULL,
    set_number INT NULL,
    question_text TEXT NOT NULL,
    student_answer VARCHAR(255) NOT NULL DEFAULT '',
    correct_answer VARCHAR(255) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    admin_reply TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
);

-- ---------- MONTHLY LIVE QUIZ ----------
-- Each monthly quiz has its OWN questions (table monthly_questions below).
-- Admin adds them in Admin -> Monthly Quiz -> Questions (or copies from a set).
CREATE TABLE IF NOT EXISTS monthly_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    start_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 15,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS monthly_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monthly_id INT NOT NULL,
    type ENUM('mcq','tf') NOT NULL DEFAULT 'mcq',
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NULL,
    option_b VARCHAR(255) NULL,
    option_c VARCHAR(255) NULL,
    option_d VARCHAR(255) NULL,
    correct_answer VARCHAR(255) NOT NULL, -- 'A'/'B'/'C'/'D' for mcq, 'True'/'False' for tf
    FOREIGN KEY (monthly_id) REFERENCES monthly_quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS monthly_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monthly_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    started_at DATETIME NOT NULL,
    submitted_at DATETIME NOT NULL,
    duration_seconds INT NOT NULL,
    FOREIGN KEY (monthly_id) REFERENCES monthly_quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_monthly_user (monthly_id, user_id)
);

-- ---------- UPGRADE OLD MONTHLY QUIZZES (only does something on an old database) ----------
-- Older versions linked a monthly quiz to a whole question set (column set_id).
-- Monthly quizzes now own their questions, so copy them over, then drop set_id.
SET @has_set = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monthly_quizzes' AND COLUMN_NAME = 'set_id');

SET @sql = IF(@has_set = 0, 'SELECT 1',
  'INSERT INTO monthly_questions (monthly_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer)
   SELECT mq.id, q.type, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer
   FROM monthly_quizzes mq JOIN questions q ON q.set_id = mq.set_id
   WHERE NOT EXISTS (SELECT 1 FROM monthly_questions x WHERE x.monthly_id = mq.id)');
PREPARE mig1 FROM @sql; EXECUTE mig1; DEALLOCATE PREPARE mig1;

SET @fk = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monthly_quizzes'
             AND COLUMN_NAME = 'set_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql = IF(@fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE monthly_quizzes DROP FOREIGN KEY `', @fk, '`'));
PREPARE mig2 FROM @sql; EXECUTE mig2; DEALLOCATE PREPARE mig2;

SET @sql = IF(@has_set = 0, 'SELECT 1', 'ALTER TABLE monthly_quizzes DROP COLUMN set_id');
PREPARE mig3 FROM @sql; EXECUTE mig3; DEALLOCATE PREPARE mig3;

-- ---------- SAMPLE MONTHLY QUIZ (ready to use) ----------
-- Added ONLY when there is no monthly quiz yet: 15 questions, scheduled for tomorrow 8:00 PM (15 min).
-- Admin can change the date/time or the questions any time before it starts:
-- Admin -> Monthly Quiz -> Edit / Questions.
SET @seed_monthly = ((SELECT COUNT(*) FROM monthly_quizzes) = 0);
INSERT INTO monthly_quizzes (title, start_time, duration_minutes)
SELECT 'Monthly Quiz - Web & Internet', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '20:00:00'), 15
FROM DUAL WHERE @seed_monthly = 1;
SET @mq_id = LAST_INSERT_ID();
INSERT INTO monthly_questions (monthly_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer)
SELECT * FROM (
SELECT @mq_id, 'mcq', 'Which HTML element is used to embed JavaScript code in a page?', '<js>', '<script>', '<javascript>', '<code>', 'B'
UNION ALL
SELECT @mq_id, 'mcq', 'Which CSS property changes the size of text?', 'text-size', 'font-style', 'font-size', 'size', 'C'
UNION ALL
SELECT @mq_id, 'mcq', 'Which HTTP status code means \'Forbidden\'?', '200', '403', '404', '301', 'B'
UNION ALL
SELECT @mq_id, 'mcq', 'Which protocol is used to browse websites securely?', 'HTTP', 'FTP', 'HTTPS', 'SMTP', 'C'
UNION ALL
SELECT @mq_id, 'mcq', 'Which attribute of the <a> tag makes a link open in a new tab?', 'href', 'rel', 'target', 'src', 'C'
UNION ALL
SELECT @mq_id, 'mcq', 'What does \'IP\' stand for in \'IP address\'?', 'Internet Provider', 'Internet Protocol', 'Internal Program', 'Interface Port', 'B'
UNION ALL
SELECT @mq_id, 'mcq', 'Which JavaScript method converts a JSON string into an object?', 'JSON.parse()', 'JSON.stringify()', 'JSON.object()', 'JSON.convert()', 'A'
UNION ALL
SELECT @mq_id, 'mcq', 'Which of the following is a server-side scripting language?', 'HTML', 'CSS', 'PHP', 'Bootstrap', 'C'
UNION ALL
SELECT @mq_id, 'mcq', 'Which CSS module is designed for two-dimensional (rows and columns) layouts?', 'Flexbox', 'CSS Grid', 'Float', 'Inline', 'B'
UNION ALL
SELECT @mq_id, 'mcq', 'Which DNS record type maps a domain name to an IPv4 address?', 'A', 'MX', 'CNAME', 'TXT', 'A'
UNION ALL
SELECT @mq_id, 'tf', 'The href attribute of the <a> tag holds the destination of the link.', 'True', 'False', NULL, NULL, 'True'
UNION ALL
SELECT @mq_id, 'tf', 'JavaScript can only run on servers, never inside a browser.', 'True', 'False', NULL, NULL, 'False'
UNION ALL
SELECT @mq_id, 'tf', 'A domain name is easier for people to remember than an IP address.', 'True', 'False', NULL, NULL, 'True'
UNION ALL
SELECT @mq_id, 'tf', 'CSS stands for Computer Style Sheets.', 'True', 'False', NULL, NULL, 'False'
UNION ALL
SELECT @mq_id, 'tf', 'Sending a password over plain HTTP is just as safe as sending it over HTTPS.', 'True', 'False', NULL, NULL, 'False'
) AS seed WHERE @seed_monthly = 1;

-- ---------- SAMPLE ADMIN ACCOUNT ----------
-- Default admin account (password: admin123)
-- Login: admin@quiz.com / admin123   (change the password after first login).
-- If login ever fails on your PHP build, open reset_admin.php once, then delete it.
INSERT INTO users (name, email, password, role)
SELECT 'Admin', 'admin@quiz.com', '$2y$10$rcybig2NN7OuAthj60LVquR7kxeQn.BSZXnX2TrTEBE2M6kEAQNyW', 'admin'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@quiz.com');


-- ============================================================
-- QUESTION SETS + QUESTIONS (15 sets x 15 questions = 225 total)
-- A set is created (with its questions) ONLY if that set number does not exist yet,
-- so re-importing never duplicates or overwrites your sets/edits.
-- ============================================================

-- Auto-generated question seed data: 15 sets x 15 questions (Web & Internet)
SET @topic = 'Web and Internet';

SET @new_1 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 1) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (1, 'Internet Basics & History', @topic);
SET @set_id_1 = (SELECT id FROM question_sets WHERE set_number = 1);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'Who is widely credited as the inventor of the World Wide Web?', 'Tim Berners-Lee', 'Bill Gates', 'Steve Jobs', 'Vint Cerf', 'A' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'What does \'WWW\' stand for?', 'World Web Wide', 'Wide World Web', 'World Wireless Web', 'World Wide Web', 'D' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'In which year was the World Wide Web publicly introduced?', '1985', '1989', '1991', '1995', 'C' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'Which organization is responsible for developing Web standards?', 'ICANN', 'W3C', 'IEEE', 'ISO', 'B' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'What is the predecessor network often called the origin of the Internet?', 'ARPANET', 'BITNET', 'Ethernet', 'Intranet', 'A' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'Which of the following best describes \'the Internet\'?', 'A single website', 'A type of web browser', 'A programming language', 'A global network of interconnected computer networks', 'D' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'Which protocol suite is the foundation of the Internet?', 'HTTP/FTP', 'SMTP/POP3', 'TCP/IP', 'HTML/CSS', 'C' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'What is an ISP?', 'Internal System Process', 'Internet Service Provider', 'Internet Security Protocol', 'Integrated Server Program', 'B' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'Which of these is NOT a web browser?', 'Mozilla Firefox', 'Google Chrome', 'Safari', 'Microsoft Excel', 'D' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'mcq', 'The \'first\' website ever published is associated with which organization?', 'NASA', 'CERN', 'MIT', 'Google', 'B' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'tf', 'HTTP stands for HyperText Transfer Protocol.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'tf', 'The Internet and the World Wide Web are exactly the same thing.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'tf', 'TCP/IP is the core communication protocol of the Internet.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'tf', 'Internet Explorer is still the default browser in Windows 11.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_1 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_1, 'tf', 'The World Wide Web was invented before the Internet.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_1 = 1;

SET @new_2 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 2) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (2, 'Web Browsers & Search Engines', @topic);
SET @set_id_2 = (SELECT id FROM question_sets WHERE set_number = 2);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'Which company developed the Chrome browser?', 'Microsoft', 'Google', 'Apple', 'Mozilla', 'B' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'What is the default search engine mostly associated with Microsoft Edge?', 'Bing', 'Google', 'Yahoo', 'DuckDuckGo', 'A' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'What does a browser\'s \'address bar\' display?', 'The user\'s password', 'The page\'s HTML code', 'The URL of the current page', 'The RAM usage', 'C' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'Which of these is a privacy-focused search engine?', 'Google', 'Bing', 'Yahoo', 'DuckDuckGo', 'D' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'What is the purpose of browser \'bookmarks\'?', 'To speed up the CPU', 'To save favorite web pages for quick access', 'To block ads permanently', 'To translate pages', 'B' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'What does \'incognito\' or \'private browsing\' mode do?', 'Browses without saving history/cookies locally', 'Makes the browser run faster', 'Blocks the internet connection', 'Encrypts all files on disk', 'A' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'Which term refers to small stored files that let websites remember users?', 'Crumbs', 'Tabs', 'Plugins', 'Cookies', 'D' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'What is a \'search engine crawler\' also known as?', 'Firewall', 'Router', 'Bot/Spider', 'Cache', 'C' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'Which shortcut typically refreshes/reloads a webpage?', 'F1', 'F2', 'Ctrl+S', 'F5', 'D' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'mcq', 'What is a \'plugin/extension\' in a browser?', 'A type of virus', 'Add-on software that adds extra features', 'A network cable', 'A search algorithm', 'B' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'tf', 'Bing is a search engine developed by Microsoft.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'tf', 'Search engines rank pages using algorithms.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'tf', 'Incognito mode makes you completely anonymous on the internet.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'tf', 'Ctrl+T is the keyboard shortcut to open a new browser tab in most browsers.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_2 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_2, 'tf', 'A browser and a search engine are the same thing.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_2 = 1;

SET @new_3 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 3) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (3, 'HTML Fundamentals', @topic);
SET @set_id_3 = (SELECT id FROM question_sets WHERE set_number = 3);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'What does HTML stand for?', 'HighText Machine Language', 'HyperTransfer Markup Language', 'HyperText Markup Language', 'Home Tool Markup Language', 'C' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which tag is used to create the largest heading?', '<h1>', '<h6>', '<heading>', '<head>', 'A' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which tag is used to insert an image?', '<picture>', '<src>', '<image>', '<img>', 'D' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which attribute specifies the URL of a link?', 'src', 'href', 'link', 'url', 'B' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which tag defines an unordered list?', '<ol>', '<list>', '<ul>', '<li>', 'C' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which tag is used to create a hyperlink?', '<link>', '<a>', '<href>', '<url>', 'B' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'What is the correct HTML element for inserting a line break?', '<break>', '<lb>', '<newline>', '<br>', 'D' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which tag is the root element of an HTML page?', '<html>', '<body>', '<head>', '<root>', 'A' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which attribute makes an image accessible for screen readers?', 'title', 'longdesc', 'accessible', 'alt', 'D' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'mcq', 'Which tag is used to define a table row?', '<td>', '<tr>', '<th>', '<row>', 'B' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'tf', 'The <p> tag is used to define a paragraph in HTML.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'tf', 'HTML is a programming language.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'tf', 'The <head> section content is displayed directly in the browser window.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'tf', 'The <title> tag content appears in the browser tab, not inside the visible page body.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_3 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_3, 'tf', 'Every HTML tag must have a closing tag (e.g. <br> always needs </br>).', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_3 = 1;

SET @new_4 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 4) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (4, 'CSS Fundamentals', @topic);
SET @set_id_4 = (SELECT id FROM question_sets WHERE set_number = 4);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'What does CSS stand for?', 'Cascading Style Sheets', 'Creative Style System', 'Computer Style Sheets', 'Colorful Style Sheets', 'A' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'Which property changes the text color?', 'font-color', 'text-color', 'fg-color', 'color', 'D' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'Which CSS property controls the space outside an element\'s border?', 'padding', 'spacing', 'margin', 'border', 'C' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'How do you select an element with id \'header\' in CSS?', '.header', '#header', '*header', 'header{}', 'B' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'Which property is used to change the background color?', 'bgcolor', 'color-bg', 'background-color', 'bg', 'C' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'How do you select elements with class \'menu\' in CSS?', '.menu', '#menu', 'menu{}', '*menu', 'A' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'Which CSS layout model uses \'flex-direction\' and \'justify-content\'?', 'Grid', 'Float', 'Table', 'Flexbox', 'D' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'Which property is used to make text bold?', 'text-style', 'font-weight', 'font-boldness', 'weight', 'B' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'What is the correct syntax for referring to an external CSS file?', '<style src=\'style.css\'>', '<css>style.css</css>', '<script src=\'style.css\'>', '<link rel=\'stylesheet\' href=\'style.css\'>', 'D' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'mcq', 'Which CSS property controls the space between an element\'s content and its border?', 'margin', 'padding', 'spacing', 'gap', 'B' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'tf', 'Padding is the CSS box-model property that adds space between an element\'s content and its border.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'tf', 'CSS can be used to make a website responsive to different screen sizes.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'tf', 'Inline CSS has lower priority than an external stylesheet by default.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'tf', 'The CSS unit \'vw\' is relative to the viewport width.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_4 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_4, 'tf', 'CSS Grid and Flexbox are the same layout system.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_4 = 1;

SET @new_5 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 5) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (5, 'JavaScript Basics', @topic);
SET @set_id_5 = (SELECT id FROM question_sets WHERE set_number = 5);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'What is JavaScript primarily used for?', 'Styling web pages', 'Storing databases', 'Adding interactivity/behavior to web pages', 'Designing server hardware', 'C' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which keyword declares a variable that cannot be reassigned?', 'var', 'const', 'let', 'static', 'B' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which symbol is used for single-line comments in JavaScript?', '<!--', '/*', '#', '//', 'D' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which method is used to display an alert box in the browser?', 'alert()', 'msgBox()', 'showMessage()', 'console.log()', 'A' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'What does \'DOM\' stand for?', 'Data Object Method', 'Document Order Model', 'Document Object Model', 'Display Object Manager', 'C' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which event fires when a user clicks a button?', 'onchange', 'onhover', 'onsubmit', 'onclick', 'D' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which of the following is a JavaScript data type?', 'Table', 'Boolean', 'Sheet', 'Style', 'B' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'How do you write an \'if\' statement in JavaScript?', 'if (x == 5)', 'if x = 5 then', 'if x == 5', 'ifx(5)', 'A' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which function converts a string to an integer in JavaScript?', 'toInteger()', 'Int()', 'parseInt()', 'strToInt()', 'C' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'mcq', 'Which method is used to select an HTML element by its id in JavaScript?', 'document.querySelectorId()', 'document.getById()', 'document.selectId()', 'document.getElementById()', 'D' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'tf', 'In JavaScript, functions can only be defined using the keyword \'func\'.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'tf', 'JavaScript can run both in the browser and on a server (e.g., Node.js).', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'tf', 'JavaScript and Java are essentially the same language.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'tf', 'console.log() is used to print output to the browser console.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_5 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_5, 'tf', 'JavaScript is used to validate form input on the client side before submission.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_5 = 1;

SET @new_6 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 6) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (6, 'Web Servers & Hosting', @topic);
SET @set_id_6 = (SELECT id FROM question_sets WHERE set_number = 6);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'What is a web server primarily responsible for?', 'Designing logos', 'Printing documents', 'Managing electricity', 'Storing and serving web pages to clients', 'D' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'Which of these is a popular open-source web server software?', 'Photoshop', 'Apache', 'AutoCAD', 'Excel', 'B' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'What is \'localhost\' commonly used to refer to?', 'Your own computer acting as a server', 'A remote cloud server', 'A domain registrar', 'An email server', 'A' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'Which local server stack bundles Apache, MySQL, and PHP for Windows/Mac/Linux?', 'Photoshop', 'Notepad++', 'Figma', 'XAMPP', 'D' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'What is \'web hosting\'?', 'A type of programming language', 'A service that allows a website to be stored and accessed online', 'A browser feature', 'A design tool', 'B' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'What is the default port number for HTTP?', '21', '25', '80', '443', 'C' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'What is the default port number for HTTPS?', '21', '80', '443', '8080', 'C' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'Which term describes a server that only serves one specific website/domain?', 'Dedicated hosting', 'Shared hosting', 'VPS', 'CDN', 'A' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'What does \'phpMyAdmin\' allow you to manage?', 'JavaScript files', 'CSS themes', 'Email accounts', 'MySQL databases', 'D' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'mcq', 'In a client-server model, the web browser acts as the:', 'Server', 'Router', 'Client', 'Firewall', 'C' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'tf', 'XAMPP bundles Apache, MySQL and PHP together for local development.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'tf', 'A web server can only host one website at a time.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'tf', 'MySQL is a type of relational database management system.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'tf', 'phpMyAdmin can only be used to manage PostgreSQL databases, not MySQL.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_6 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_6, 'tf', 'Port 443 is the default port used for HTTPS traffic.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_6 = 1;

SET @new_7 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 7) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (7, 'HTTP/HTTPS & Web Protocols', @topic);
SET @set_id_7 = (SELECT id FROM question_sets WHERE set_number = 7);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'What does HTTPS add to HTTP?', 'Faster loading only', 'Encryption/security via SSL/TLS', 'Better images', 'More storage', 'B' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which HTTP method is typically used to submit form data to a server?', 'GET', 'PING', 'SYNC', 'POST', 'D' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which HTTP status code means \'Not Found\'?', '200', '301', '404', '500', 'C' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which HTTP status code means \'OK\' (success)?', '200', '404', '403', '500', 'A' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which protocol is used to transfer files to/from a server?', 'SMTP', 'FTP', 'POP3', 'ARP', 'B' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which protocol is used for sending emails?', 'SMTP', 'FTP', 'HTTP', 'DNS', 'A' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'What does \'SSL/TLS\' primarily provide?', 'Faster DNS lookup', 'Larger bandwidth', 'Better SEO ranking', 'Encrypted communication between client and server', 'D' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which HTTP status code range generally indicates client errors?', '1xx', '2xx', '4xx', '5xx', 'C' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'What does \'API\' stand for?', 'Automated Program Instruction', 'Applied Protocol Internet', 'Advanced Programming Index', 'Application Programming Interface', 'D' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'mcq', 'Which HTTP method is generally used to request/retrieve data without submitting a body?', 'POST', 'DELETE', 'GET', 'PUT', 'C' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'tf', 'HTTPS stands for HyperText Transfer Protocol Secure.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'tf', 'HTTPS is generally considered more secure than plain HTTP.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'tf', 'FTP is mainly used for sending emails.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'tf', 'HTTP status code 404 means the requested page was not found.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_7 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_7, 'tf', 'GET requests are typically used to submit sensitive form data like passwords.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_7 = 1;

SET @new_8 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 8) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (8, 'URLs, DNS & IP Addressing', @topic);
SET @set_id_8 = (SELECT id FROM question_sets WHERE set_number = 8);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'What does \'URL\' stand for?', 'Universal Retrieval Link', 'United Resource Link', 'Uniform Retrieval Locator', 'Uniform Resource Locator', 'D' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'What does \'DNS\' stand for?', 'Data Network Server', 'Domain Name System', 'Domain Network Service', 'Digital Name Server', 'B' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'What is the main function of DNS?', 'Translating domain names into IP addresses', 'Encrypting passwords', 'Compressing images', 'Hosting websites', 'A' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'Which part of a URL \'https://www.example.com/page\' is the domain name?', 'https', 'page', 'www.example.com', '://', 'C' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'What is an IP address?', 'A unique numerical identifier for a device on a network', 'A type of web page', 'A programming variable', 'A file extension', 'A' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'Which of the following is a valid IPv4 address format?', '192.168.1', '192.168.1.1', '192-168-1-1', '192.168.1.1.1', 'B' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'What does \'TLD\' stand for in domain names (e.g. .com, .org)?', 'Total Link Domain', 'Transfer Level Data', 'Text Language Domain', 'Top-Level Domain', 'D' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'Which company/organization manages global domain name allocation policy?', 'W3C', 'IEEE', 'ICANN', 'Microsoft', 'C' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'What is the purpose of a \'subdomain\' (e.g. blog.example.com)?', 'To register a new TLD', 'To encrypt a website', 'To speed up hosting', 'To create a separate section of a main domain', 'D' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'mcq', 'Which protocol version introduced a much larger address space than IPv4?', 'IPv5', 'IPv2', 'IPv6', 'IPv4+', 'C' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'tf', 'An IP address is a unique numerical identifier assigned to a device on a network.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'tf', 'DNS converts human-readable domain names into IP addresses.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'tf', 'Every website must have a unique IP address that never changes.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'tf', 'In the domain \'www.university.edu\', \'.university\' is the top-level domain (TLD), not \'.edu\'.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_8 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_8, 'tf', '\'.com\', \'.org\', and \'.net\' are all examples of top-level domains.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_8 = 1;

SET @new_9 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 9) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (9, 'Web Security Basics', @topic);
SET @set_id_9 = (SELECT id FROM question_sets WHERE set_number = 9);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What is \'phishing\'?', 'A type of web hosting', 'A CSS animation technique', 'Tricking users into revealing sensitive information via fake messages/sites', 'A JavaScript framework', 'C' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What does \'SQL Injection\' attempt to exploit?', 'Weak Wi-Fi passwords', 'Slow internet speed', 'Browser cache size', 'Poorly sanitized database queries', 'D' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What is the purpose of hashing a password before storing it?', 'To make the password shorter', 'To protect the original password even if the database is leaked', 'To make login faster', 'To translate the password', 'B' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What does \'XSS\' stand for?', 'Cross-Site Scripting', 'Extra Secure System', 'XML Style Sheet', 'Cross Server Sync', 'A' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'Which of these is a good password practice?', 'Reusing the same password everywhere', 'Using your name and birthdate', 'Sharing your password with friends', 'Using long, unique passwords with mixed characters', 'D' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What is a firewall used for?', 'Designing web pages', 'Compressing files', 'Monitoring and controlling incoming/outgoing network traffic', 'Editing images', 'C' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What is \'two-factor authentication (2FA)\'?', 'A type of firewall', 'An extra verification step beyond just a password', 'A CSS layout method', 'A database index', 'B' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What is malware?', 'Malicious software designed to harm or exploit systems', 'A web design framework', 'A search engine', 'A type of good software', 'A' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'Why should input data from users always be validated on the server side?', 'Because it makes pages load faster', 'Because it improves image quality', 'Because client-side checks can be bypassed', 'Because it changes font size', 'C' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'mcq', 'What is the purpose of using prepared statements in database queries?', 'To prevent SQL injection attacks', 'To make queries run in a different language', 'To automatically design tables', 'To encrypt the internet connection', 'A' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'tf', 'Malware refers to malicious software designed to harm or exploit computer systems.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'tf', 'Storing passwords in plain text in a database is a safe practice.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'tf', 'HTTPS helps protect data transmitted between a browser and a server from eavesdropping.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'tf', 'Phishing attacks trick users into revealing sensitive information through fake messages or websites.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_9 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_9, 'tf', 'Using the same weak password on multiple sites is considered a security best practice.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_9 = 1;

SET @new_10 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 10) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (10, 'Cookies, Sessions & Web Storage', @topic);
SET @set_id_10 = (SELECT id FROM question_sets WHERE set_number = 10);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'What is a \'cookie\' in web terminology?', 'A type of web server', 'A JavaScript error', 'A small piece of data stored in the browser to remember information', 'A CSS animation', 'C' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'What is a \'session\' typically used for on a website?', 'Permanently deleting user data', 'Temporarily storing user data on the server during their visit', 'Compressing images', 'Designing the layout', 'B' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'In PHP, which function starts a session?', 'session_start()', 'start_session()', 'begin_session()', 'new_session()', 'A' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'Where is session data typically stored by default in a basic PHP setup?', 'Only in the URL', 'Only in the CSS file', 'On the DNS server', 'On the server', 'D' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'What is \'localStorage\' in web development?', 'Browser storage that persists data even after the browser is closed', 'A type of server database', 'A CSS property', 'A network protocol', 'A' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'How does a website \'remember\' that a user is logged in as they browse different pages?', 'Re-entering the password on every page', 'Using only CSS', 'Using only HTML', 'Using sessions/cookies to track login state', 'D' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'Which of the following ends a user\'s logged-in session in most PHP apps?', 'session_end()', 'logout()', 'session_destroy()', 'delete_session()', 'C' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'What is the main difference between a cookie and sessionStorage?', 'They are exactly identical', 'Cookies are sent to the server with every request; sessionStorage is not', 'sessionStorage is a server-side concept', 'Cookies only work in Chrome', 'B' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'Why might a website use cookies for a shopping cart?', 'To block the user from buying items', 'To slow down the checkout', 'To remember items added to the cart between page loads', 'To change the currency automatically', 'C' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'mcq', 'What happens to sessionStorage data when the browser tab is closed?', 'It is cleared', 'It is emailed to the server', 'It becomes a cookie', 'It is printed', 'A' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'tf', 'session_destroy() is the PHP function used to destroy/end a session.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'tf', 'Cookies are stored on the client side (in the browser).', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'tf', 'Sessions in PHP require calling session_start() before using session data.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'tf', 'localStorage data is automatically cleared as soon as the browser tab is closed.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_10 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_10, 'tf', 'Once a browser is closed, cookies are always deleted immediately regardless of settings.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_10 = 1;

SET @new_11 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 11) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (11, 'E-commerce & Online Services', @topic);
SET @set_id_11 = (SELECT id FROM question_sets WHERE set_number = 11);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What does \'e-commerce\' mean?', 'Sending emails only', 'Designing logos', 'Buying and selling goods/services over the internet', 'Writing HTML code', 'C' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'Which of the following is an example of an e-commerce platform?', 'Wikipedia', 'Google Docs', 'Notepad', 'Amazon', 'D' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What is a \'shopping cart\' on an e-commerce site?', 'A physical cart used by delivery staff', 'A feature that temporarily holds items a user intends to purchase', 'A type of database', 'A CSS framework', 'B' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What is \'online banking\'?', 'Performing banking transactions via the internet', 'Printing money at home', 'A type of web browser', 'A search engine feature', 'A' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'Which payment method is commonly used for secure online transactions?', 'Only cash', 'Only cheques', 'Only barter', 'Credit/debit cards and digital wallets', 'D' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What is a \'digital wallet\'?', 'A physical leather wallet', 'An app/service that stores payment information for online transactions', 'A type of firewall', 'A browser plugin only for images', 'B' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What does B2C stand for in e-commerce?', 'Business to Cloud', 'Bank to Client', 'Business to Consumer', 'Broadband to Computer', 'C' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What is the purpose of an \'order confirmation email\' after online shopping?', 'To confirm the purchase details to the customer', 'To cancel the order automatically', 'To advertise unrelated products only', 'To reset the password', 'A' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'Which of these best describes \'online food delivery services\'?', 'A type of database', 'A programming language', 'Platforms connecting customers with restaurants via the internet', 'A network cable', 'C' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'mcq', 'What security feature is essential for e-commerce checkout pages?', 'Bright colors', 'HTTPS/SSL encryption', 'Large fonts', 'Background music', 'B' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'tf', 'Amazon is primarily known as a search engine rather than an e-commerce platform.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'tf', 'E-commerce only refers to buying physical products, never services.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'tf', 'HTTPS is important for protecting payment information during online checkout.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'tf', 'The term \'e-commerce\' describes online buying and selling of goods and services.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_11 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_11, 'tf', 'A digital wallet can store payment card details for faster online checkout.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_11 = 1;

SET @new_12 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 12) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (12, 'Social Media & Web 2.0', @topic);
SET @set_id_12 = (SELECT id FROM question_sets WHERE set_number = 12);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What best defines \'Web 2.0\'?', 'The very first version of the internet', 'A type of hardware', 'A programming language', 'The evolution of the web towards user-generated content and interactivity', 'D' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'Which of the following is a social media platform?', 'Facebook', 'MySQL', 'Apache', 'XAMPP', 'A' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What is a \'hashtag\' used for on social media?', 'Encrypting a post', 'Deleting a post', 'Categorizing/searching posts by topic', 'Hosting a website', 'C' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What does \'viral content\' mean?', 'Content infected with a computer virus', 'Content that spreads rapidly and widely among users', 'Content that is always false', 'Content only visible to admins', 'B' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'Which of these is a video-sharing platform?', 'Excel', 'Photoshop', 'YouTube', 'Notepad++', 'C' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What is a \'blog\'?', 'A regularly updated website/section featuring articles or posts', 'A type of database', 'A network protocol', 'A CSS framework', 'A' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What is \'user-generated content\'?', 'Content generated automatically by a server', 'Content only created by admins', 'Content that cannot be shared', 'Content created and shared by ordinary users rather than the platform itself', 'D' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'Which term describes a person with a large, influential following on social media?', 'Administrator', 'Influencer', 'Developer', 'Hacker', 'B' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What is the primary purpose of professional networking platforms like LinkedIn?', 'Connecting professionals and sharing career-related content', 'Playing video games', 'Online shopping only', 'Watching movies', 'A' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'mcq', 'What does \'social media engagement\' typically measure?', 'Only the number of followers', 'Only the internet speed', 'Only the number of images posted', 'Likes, comments, shares and other interactions', 'D' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'tf', 'Facebook is primarily a web hosting and domain registration service, not a social media platform.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'tf', 'Web 2.0 emphasizes user interaction and collaboration compared to the static early web.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'tf', 'A blog cannot be updated after it is first published.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'tf', 'The \'#\' symbol is commonly used to create a searchable hashtag on social media.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_12 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_12, 'tf', 'Influencers can affect audience opinions and purchasing decisions through social media.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_12 = 1;

SET @new_13 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 13) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (13, 'Cloud Computing & Web Hosting Services', @topic);
SET @set_id_13 = (SELECT id FROM question_sets WHERE set_number = 13);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What is \'cloud computing\'?', 'Only weather forecasting software', 'A local hard drive backup only', 'Delivering computing services (storage, servers, etc.) over the internet', 'A type of printer', 'C' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'Which of the following is a popular cloud service provider?', 'Notepad++', 'Amazon Web Services (AWS)', 'XAMPP', 'Photoshop', 'B' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What does \'SaaS\' stand for?', 'System as a Server', 'Storage as a Service', 'Server as a Software', 'Software as a Service', 'D' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What is a \'CDN\' (Content Delivery Network) used for?', 'Distributing content from servers closer to users for faster delivery', 'Designing website logos', 'Writing JavaScript code', 'Managing email inboxes', 'A' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What does \'scalability\' mean in cloud hosting?', 'The physical size of a server room', 'The ability to increase or decrease resources based on demand', 'The color scheme of a website', 'The number of browser tabs open', 'B' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What is \'shared hosting\'?', 'A dedicated server for one website only', 'A local server on your own PC', 'A type of firewall', 'Multiple websites hosted on the same physical server sharing resources', 'D' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What does \'uptime\' measure for a hosting service?', 'The speed of typing on a keyboard', 'The number of website visitors', 'The percentage of time a server/service is operational and accessible', 'The number of images on a page', 'C' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What is a \'VPS\' (Virtual Private Server)?', 'A virtualized server that mimics a dedicated server within a shared physical machine', 'A physical dedicated computer only', 'A type of web browser', 'A CSS framework', 'A' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'Which of these is an example of cloud storage service?', 'Notepad', 'MySQL Workbench', 'Google Drive', 'Photoshop', 'C' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'mcq', 'What does \'backup\' mean in the context of web hosting?', 'A copy of data kept safe in case the original is lost or damaged', 'Deleting old data permanently', 'Compressing images automatically', 'Changing the website\'s font', 'A' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'tf', 'AWS (Amazon Web Services) is a popular cloud computing service provider.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'tf', 'Cloud computing allows resources to be scaled up or down based on demand.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'tf', 'A CDN slows down website loading speed for users far from the origin server.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'tf', 'Shared hosting means each website gets its own dedicated physical server with no sharing.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_13 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_13, 'tf', 'Regular backups help protect website data from being permanently lost.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_13 = 1;

SET @new_14 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 14) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (14, 'Web Development Tools & APIs', @topic);
SET @set_id_14 = (SELECT id FROM question_sets WHERE set_number = 14);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What is an \'API\' used for in web development?', 'Allowing different software systems to communicate with each other', 'Designing colors only', 'Compressing videos', 'Managing electricity usage', 'A' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What is \'Git\' commonly used for?', 'Editing images', 'Version control of source code', 'Sending emails', 'Hosting databases only', 'B' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'Which of these is a popular code editor for web development?', 'Photoshop', 'Excel', 'PowerPoint', 'Visual Studio Code', 'D' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What does \'responsive web design\' mean?', 'A design that responds with sound effects', 'A design that only works on desktops', 'A design that adapts to different screen sizes and devices', 'A design with no images', 'C' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What is \'debugging\'?', 'Designing a logo', 'Writing new HTML tags only', 'Deleting a website', 'The process of finding and fixing errors in code', 'D' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What does \'JSON\' stand for?', 'JavaScript Object Notation', 'Java Simple Object Network', 'Joint System Object Notation', 'JavaScript Ordered Numbers', 'A' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'Which browser tool is commonly used to inspect HTML/CSS/JS and debug web pages?', 'Task Manager', 'Developer Tools (DevTools)', 'File Explorer', 'Control Panel', 'B' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What is a \'framework\' in web development (e.g., Bootstrap, Laravel)?', 'A type of computer virus', 'A hardware device', 'A pre-built structure/toolset that speeds up development', 'A search engine', 'C' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What is \'version control\' primarily used for?', 'Controlling the volume of a video', 'Managing electricity versions', 'Formatting text only', 'Tracking and managing changes to code over time', 'D' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'mcq', 'What does \'responsive\' CSS typically rely on to adapt layouts?', 'Media queries', 'Only inline styles', 'Only JavaScript alerts', 'Only images', 'A' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'tf', 'API stands for Application Programming Interface.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'tf', 'Responsive web design helps a website look good on both mobile phones and desktops.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'tf', 'Git is primarily used for editing images in web design.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'tf', 'Visual Studio Code is primarily a video editing tool, not a code editor.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_14 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_14, 'tf', 'JSON is commonly used as a lightweight data-interchange format in web APIs.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_14 = 1;

SET @new_15 = ((SELECT COUNT(*) FROM question_sets WHERE set_number = 15) = 0);
INSERT IGNORE INTO question_sets (set_number, title, topic) VALUES (15, 'Emerging Web Technologies', @topic);
SET @set_id_15 = (SELECT id FROM question_sets WHERE set_number = 15);
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What does \'IoT\' stand for?', 'Internet of Technology', 'Input Output Terminal', 'Internet of Things', 'Internal Online Tool', 'C' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What is a \'Progressive Web App (PWA)\'?', 'A type of hardware device', 'A database management system', 'A type of firewall', 'A web application that offers app-like experience, even offline', 'D' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What does \'AI\' commonly refer to in modern web applications?', 'Automatic Internet', 'Artificial Intelligence', 'Applied Interface', 'Analog Input', 'B' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What is \'blockchain\' most commonly associated with?', 'A decentralized, distributed ledger technology', 'A type of web browser', 'A CSS animation library', 'A file compression format', 'A' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What does \'AR/VR\' stand for?', 'Automatic Reality / Virtual Router', 'Augmented Reality / Virtual Reality', 'Applied Robotics / Video Rendering', 'Advanced Routing / Virtual RAM', 'B' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What is a \'chatbot\'?', 'A physical robot', 'A type of database', 'A software application that simulates conversation with users', 'A network cable', 'C' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What is \'Web3\' generally associated with?', 'The third version of HTML', 'A type of CSS framework', 'An old version of the internet', 'Decentralized web concepts often involving blockchain', 'D' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'Which of the following is an example of an IoT device?', 'A smart thermostat connected to the internet', 'A regular non-connected calculator', 'A printed book', 'A wired-only landline phone', 'A' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What does \'machine learning\' allow computer systems to do?', 'Only display static web pages', 'Only send emails', 'Only format text', 'Learn patterns from data to improve performance without explicit programming', 'D' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'mcq', 'What is a key benefit of a Progressive Web App (PWA)?', 'It can work offline and be installed like a native app', 'It requires no internet connection ever', 'It cannot be updated', 'It only works on one specific browser', 'A' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'tf', 'IoT stands for Internet of Things.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'tf', 'A chatbot can be used to automatically respond to user queries on a website.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'tf', 'Blockchain technology is only used for storing images.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'tf', 'PWA stands for Progressive Web App.', 'True', 'False', NULL, NULL, 'True' FROM DUAL WHERE @new_15 = 1;
INSERT INTO questions (set_id, type, question_text, option_a, option_b, option_c, option_d, correct_answer) SELECT @set_id_15, 'tf', 'Artificial Intelligence has no application in modern web development.', 'True', 'False', NULL, NULL, 'False' FROM DUAL WHERE @new_15 = 1;
