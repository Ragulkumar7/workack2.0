<?php
// api/attendance_action.php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Smart DB Connector
$paths = ['../include/db_connect.php', '../../include/db_connect.php'];
$db_found = false;
foreach($paths as $path) { 
    if(file_exists($path)) { 
        require_once $path; 
        $db_found = true; break; 
    } 
}

header('Content-Type: application/json');

if (!$db_found || !isset($conn) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or DB Error.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$now_db = date('Y-m-d H:i:s');
$action = $_POST['action'] ?? '';

// Fetch current state
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

$is_on_break = false;
if ($attendance_record) {
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $bk_stmt = mysqli_prepare($conn, $bk_sql);
    mysqli_stmt_bind_param($bk_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($bk_stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt))) {
        $is_on_break = true;
    }
}

// Execute Actions
if ($action == 'punch_in' && !$attendance_record) {
    $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
    $ins_stmt = mysqli_prepare($conn, $ins_sql);
    mysqli_stmt_bind_param($ins_stmt, "iss", $current_user_id, $now_db, $today);
    if(mysqli_stmt_execute($ins_stmt)) { echo json_encode(['success' => true]); exit; }
} 
elseif ($action == 'break_start' && $attendance_record && !$is_on_break) {
    $ins_bk = "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $ins_bk);
    mysqli_stmt_bind_param($stmt, "is", $attendance_record['id'], $now_db);
    if(mysqli_stmt_execute($stmt)) { echo json_encode(['success' => true]); exit; }
} 
elseif ($action == 'break_end' && $attendance_record && $is_on_break) {
    $upd_bk = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
    $stmt = mysqli_prepare($conn, $upd_bk);
    mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
    if(mysqli_stmt_execute($stmt)) { echo json_encode(['success' => true]); exit; }
} 
elseif ($action == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
    if ($is_on_break) {
        mysqli_query($conn, "UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = {$attendance_record['id']} AND break_end IS NULL");
    }
    $sum_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = {$attendance_record['id']} AND break_end IS NOT NULL"));
    
    $start_ts = strtotime($attendance_record['punch_in']);
    $end_ts = strtotime($now_db);
    $production_seconds = max(0, ($end_ts - $start_ts) - ($sum_res['total'] ?? 0));
    $hours = $production_seconds / 3600;

    $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
    $upd_stmt = mysqli_prepare($conn, $upd_sql);
    mysqli_stmt_bind_param($upd_stmt, "sdi", $now_db, $hours, $attendance_record['id']);
    if(mysqli_stmt_execute($upd_stmt)) { echo json_encode(['success' => true]); exit; }
}

echo json_encode(['success' => false, 'message' => 'Action failed.']);
?>