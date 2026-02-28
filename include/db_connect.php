<?php
// db_connect.php

// Use a global variable check to prevent multiple connections in one execution
global $conn;

if (!$conn) {
    // --- DATABASE CONFIGURATION ---
    $host = "srv1507.hstgr.io"; 
    $user = "u957189082_workackv2";
    $pass = "Work$2026";
    $db   = "u957189082_workackv2";

    // Establish Connection (Wrapped in try-catch for Hostinger limit)
    try {
        $conn = mysqli_connect($host, $user, $pass, $db);
    } catch (mysqli_sql_exception $e) {
        error_log("Connection failed: " . $e->getMessage());
        die("<div style='padding: 2rem; text-align: center; font-family: sans-serif; color: #334155;'>
                <h2 style='color: #ef4444;'>Database Connection Limit Reached</h        2>
                <p>Hostinger strictly limits connections to 500 per hour. Please wait an hour for the limit to reset, or switch to a local XAMPP database for development.</p>
             </div>");
    }

    // Check Connection
    if (!$conn) {
        error_log("Connection failed: " . mysqli_connect_error());
        die("Database connection error. Please try again later.");
    }

    // Set Charset immediately
    mysqli_set_charset($conn, "utf8mb4");

    // --- AUTO-FIX: Missing Column-ah idhuve add pannidum ---
    // Indha logic "soft_skills" column illana mattum adhai add pannum
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM `employee_performance` LIKE 'soft_skills'");
    if (mysqli_num_rows($check_col) == 0) {
        mysqli_query($conn, "ALTER TABLE employee_performance ADD COLUMN soft_skills INT DEFAULT 0");
    }

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