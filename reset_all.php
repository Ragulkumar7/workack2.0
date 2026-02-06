<?php
// reset_all.php

// --- CORRECT PATH FIX ---
// Your file is inside the "include" folder (singular)
if (file_exists(__DIR__ . '/include/db_connect.php')) {
    require_once __DIR__ . '/include/db_connect.php';
} else {
    // Fallback: check if it's in the root, just in case
    if (file_exists(__DIR__ . '/db_connect.php')) {
        require_once __DIR__ . '/db_connect.php';
    } else {
        die("❌ Error: Could not find 'db_connect.php'. Checked in '/include/' and root.");
    }
}

// 2. Define the new password
$new_password_plain = 'admin123';
$new_password_hash = password_hash($new_password_plain, PASSWORD_DEFAULT);

// 3. Update all users
$sql = "UPDATE users SET password = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $new_password_hash);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<h1>✅ Success!</h1>";
        echo "<p>All user passwords have been reset to: <strong>admin123</strong></p>";
        echo "<p>Total users updated: " . mysqli_stmt_affected_rows($stmt) . "</p>";
        echo "<hr>";
        echo "<p style='color:red; font-weight:bold;'>⚠️ SECURITY WARNING: Delete this file (reset_all.php) immediately.</p>";
    } else {
        echo "❌ Error updating records: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    echo "❌ Error preparing statement: " . mysqli_error($conn);
}
?>