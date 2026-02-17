<?php
session_start();
require_once '../include/db_connect.php';
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$action = $_POST['action'] ?? '';

// Get today's attendance record
$sql = "SELECT id, punch_in FROM attendance WHERE user_id = ? AND date = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $user_id, $today);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$att_record = mysqli_fetch_assoc($result);

try {
    if ($action === 'punch_in') {
        if (!$att_record) {
            $ins = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
            $stmt = mysqli_prepare($conn, $ins);
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $now, $today);
            mysqli_stmt_execute($stmt);
            echo json_encode(['status' => 'success', 'state' => 'punched_in', 'time' => date('h:i A')]);
        }
    } 
    
    elseif ($action === 'break_start') {
        if ($att_record) {
            $ins = "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $ins);
            mysqli_stmt_bind_param($stmt, "is", $att_record['id'], $now);
            mysqli_stmt_execute($stmt);
            echo json_encode(['status' => 'success', 'state' => 'on_break']);
        }
    } 
    
    elseif ($action === 'break_end') {
        if ($att_record) {
            // Find open break
            $upd = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
            $stmt = mysqli_prepare($conn, $upd);
            mysqli_stmt_bind_param($stmt, "si", $now, $att_record['id']);
            mysqli_stmt_execute($stmt);
            echo json_encode(['status' => 'success', 'state' => 'punched_in']);
        }
    } 
    
    elseif ($action === 'punch_out') {
        if ($att_record) {
            // 1. Close any open breaks first
            $close_break = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
            $stmt_cb = mysqli_prepare($conn, $close_break);
            mysqli_stmt_bind_param($stmt_cb, "si", $now, $att_record['id']);
            mysqli_stmt_execute($stmt_cb);

            // 2. Calculate Total Break Time
            $break_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as break_seconds 
                          FROM attendance_breaks WHERE attendance_id = ?";
            $stmt_b = mysqli_prepare($conn, $break_sql);
            mysqli_stmt_bind_param($stmt_b, "i", $att_record['id']);
            mysqli_stmt_execute($stmt_b);
            $b_res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_b));
            $break_seconds = $b_res['break_seconds'] ?? 0;

            // 3. Calculate Production Hours (Total Time - Break Time)
            $start_time = strtotime($att_record['punch_in']);
            $end_time = strtotime($now);
            $total_duration = $end_time - $start_time;
            $production_seconds = $total_duration - $break_seconds;
            $production_hours = $production_seconds / 3600; // Convert to decimal hours

            // 4. Update Attendance
            $upd = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $upd);
            mysqli_stmt_bind_param($stmt, "sdi", $now, $production_hours, $att_record['id']);
            mysqli_stmt_execute($stmt);

            echo json_encode(['status' => 'success', 'state' => 'punched_out']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>