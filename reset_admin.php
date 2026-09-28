<?php
// ============================================
// Run this ONCE after importing the database to guarantee the admin
// password hash works on your exact PHP/OpenSSL build.
// Visit: http://localhost/quiz-system/reset_admin.php
// Then DELETE this file.
// ============================================
require 'config/db.php';

$email = 'admin@quiz.com';
$new_password = 'admin123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
$stmt->bind_param("ss", $hash, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "<h2 style='font-family:sans-serif;color:#155724;'>✅ Admin password has been reset.</h2>";
    echo "<p style='font-family:sans-serif;'>Login with:<br>Email: <b>$email</b><br>Password: <b>$new_password</b></p>";
    echo "<p style='font-family:sans-serif;color:#c0392b;'><b>Important:</b> Delete this file (reset_admin.php) now.</p>";
} else {
    // No admin row existed yet (e.g. schema.sql wasn't imported with the sample admin) — create one.
    $stmt2 = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('Admin', ?, ?, 'admin')");
    $stmt2->bind_param("ss", $email, $hash);
    if ($stmt2->execute()) {
        echo "<h2 style='font-family:sans-serif;color:#155724;'>✅ Admin account created.</h2>";
        echo "<p style='font-family:sans-serif;'>Login with:<br>Email: <b>$email</b><br>Password: <b>$new_password</b></p>";
        echo "<p style='font-family:sans-serif;color:#c0392b;'><b>Important:</b> Delete this file (reset_admin.php) now.</p>";
    } else {
        echo "<p style='font-family:sans-serif;color:#c0392b;'>Something went wrong: " . htmlspecialchars($conn->error) . "</p>";
    }
}
$stmt->close();
$conn->close();
?>
