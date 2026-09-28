<?php
// ============================================
// Database Connection (MySQLi)
// This ONE file is included everywhere PHP needs the DB.
// Change these 4 values if your XAMPP/MySQL setup is different.
// ============================================

// All exam times (start/submit/monthly quiz) use this timezone.
date_default_timezone_set('Asia/Dhaka');

$DB_HOST = "localhost";
$DB_USER = "root";       // change to your MySQL username
$DB_PASS = "";           // change to your MySQL password
$DB_NAME = "quiz_system";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
// Keep MySQL's clock (taken_at, created_at...) in the same timezone as PHP.
$conn->query("SET time_zone = '" . date('P') . "'");

// Total number of question sets available in the system.
define('TOTAL_SETS', 15);
define('QUESTIONS_PER_SET', 15);
define('FULL_MARKS', 15);

// Monthly quiz rules
define('MONTHLY_JOIN_GRACE_SECONDS', 180);  // students may JOIN only within 3 min after the start time
define('MONTHLY_SUBMIT_GRACE_SECONDS', 15); // network buffer when the common end time hits

// Safety net: some PHP builds ship without the mbstring extension.
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        $a = preg_split('//u', (string)$str, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($a, $start, $length));
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($str) {
        return count(preg_split('//u', (string)$str, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
