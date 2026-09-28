<?php
// ============================================
// Session + Auth Helpers
// Include this AFTER session_start() (and after config/db.php) on any
// page that needs login checks or set-assignment logic.
// ============================================

/**
 * True only if the user id stored in the session still exists in the database.
 * (If the database was re-imported or the account was deleted, an old login
 * session must not keep working - otherwise results could not be saved.)
 */
function session_user_exists() {
    global $conn;
    if (!isset($_SESSION['user_id'])) return false;
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    $_SESSION['role'] = $row['role'];
    return true;
}

function require_login() {
    if (!session_user_exists()) {
        $_SESSION = [];
        header("Location: login.php");
        exit();
    }
}

function require_admin() {
    if (!session_user_exists() || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit();
    }
}

/**
 * Every student is deterministically assigned ONE primary question set
 * based on their user (student) ID, so not all students see the same
 * question set. Formula: ((user_id - 1) % TOTAL_SETS) + 1
 */
function get_assigned_set_number($user_id) {
    return (($user_id - 1) % TOTAL_SETS) + 1;
}

// ---------- CSRF protection for forms that change data ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_check() {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    if (!$ok) {
        http_response_code(400);
        die("Invalid or expired form token. Please go back, refresh the page and try again.");
    }
}
