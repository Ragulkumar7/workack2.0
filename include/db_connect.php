<?php
// db_connect.php

// Use a global variable check to prevent multiple connections in one execution
global $conn;

if (!$conn) {
    // --- DATABASE CONFIGURATION ---
    $host = "srv1507.hstgr.io"; 
    $user = "u957189082_workacknew";
    $pass = "Workacknew$2026";
    $db   = "u957189082_workacknew";

    // Establish Connection
    $conn = mysqli_connect($host, $user, $pass, $db);

    // Check Connection
    if (!$conn) {
        // Log error instead of die() to keep it cleaner
        error_log("Connection failed: " . mysqli_connect_error());
        die("Database connection error. Please try again later.");
    }

    // Set Charset immediately
    mysqli_set_charset($conn, "utf8mb4");

    // --- TIMEZONE CONFIGURATION ---
    date_default_timezone_set('Asia/Kolkata');
    mysqli_query($conn, "SET time_zone = '+05:30'");
}

// --- ENCRYPTION KEY ---
if (!defined('CHAT_ENCRYPTION_KEY')) {
    define('CHAT_ENCRYPTION_KEY', '646ef2aeedf0bbe2bdcc3c4b3b89e8cdeb3a18194e6c33a9e84149aa48a4ee5d');
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>