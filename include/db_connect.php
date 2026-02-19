<?php
// db_connect.php

// --- DATABASE CONFIGURATION ---
$host = "srv1507.hstgr.io"; 
$user = "u957189082_workack";
$pass = "Work$2026";
$db   = "u957189082_workack";

// Establish Connection
$conn = mysqli_connect($host, $user, $pass, $db);

// Check Connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// --- NEW: FORCE INDIA TIMEZONE FOR PHP AND MYSQL ---
date_default_timezone_set('Asia/Kolkata');
mysqli_query($conn, "SET time_zone = '+05:30'");
// ────────────────────────────────────────────────
// ADD ENCRYPTION KEY HERE (after successful connection)
// NEVER commit this key to Git or share it!
// Generate a strong 32-byte key once (256-bit AES)
// You can generate one by running: echo bin2hex(random_bytes(32));
define('CHAT_ENCRYPTION_KEY', ' 646ef2aeedf0bbe2bdcc3c4b3b89e8cdeb3a18194e6c33a9e84149aa48a4ee5d '); // ← must be 64 hex chars (32 bytes) // ← CHANGE THIS!

// Set Charset
mysqli_set_charset($conn, "utf8mb4");

// Start Session for User Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>