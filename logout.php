<?php
// logout.php
session_start();

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// 3. Destroy the session on the server
session_destroy();

// 4. Redirect to the login page
header("Location: index.php");
exit();
?>