<?php
// api/attendance_action.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

// 2. Database Connection
$dbPath = '../include/db_connect.php';
if (file_exists($dbPath)) { 
    require_once $dbPath; 
} else { 
    echo json_encode(['success' => false, 'message' => 'Database connection missing.']); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$now_db = date('Y-m-d H:i:s');
$action = $_POST['action'] ?? ''; // Catches the action sent by your JS

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action received.']);
    exit();
}

// ==========================================
// 3. PROCESS ATTENDANCE LOGIC
// ==========================================

// PUNCH IN
if ($action === 'punch_in') {
    $check = $conn->query("SELECT id FROM attendance WHERE user_id=$user_id AND date='$today'");
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')");
        $stmt->bind_param("iss", $user_id, $now_db, $today);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'time' => date("h:i A")]);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Database insert failed.']);
            exit();
        }
    }
    echo json_encode(['success' => true, 'message' => 'Already punched in.']);
    exit();
}

// Fetch existing attendance ID for Breaks and Punch Out
$att_result = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id=$user_id AND date='$today'");
if ($att_result->num_rows > 0) {
    $att_row = $att_result->fetch_assoc();
    $att_id = $att_row['id'];
    $punch_in_time = $att_row['punch_in'];
    
    // START BREAK
    if ($action === 'break_start') {
        $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
        $stmt->bind_param("is", $att_id, $now_db);
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to start break.']);
        }
        exit();
    }
    
    // END BREAK
    if ($action === 'break_end') {
        $stmt = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
        $stmt->bind_param("si", $now_db, $att_id);
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to end break.']);
        }
        exit();
    }
    
    // PUNCH OUT
    if ($action === 'punch_out') {
        // Force end any active break first
        $conn->query("UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = $att_id AND break_end IS NULL");
        
        // Calculate break time
        $brk_res = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, IFNULL(break_end, '$now_db'))) as total_brk FROM attendance_breaks WHERE attendance_id = $att_id");
        $brk_row = $brk_res->fetch_assoc();
        $total_brk_sec = $brk_row['total_brk'] ?? 0;
        
        // Calculate production
        $total_work_sec = strtotime($now_db) - strtotime($punch_in_time);
        $prod_sec = max(0, $total_work_sec - $total_brk_sec);
        $prod_hours = $prod_sec / 3600;
        
        $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?");
        $stmt->bind_param("sdi", $now_db, $prod_hours, $att_id);
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'time' => date("h:i A"), 'prod' => number_format($prod_hours, 2).'h']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to punch out.']);
        }
        exit();
    }
}

// Fallback if no active attendance record is found
echo json_encode(['success' => false, 'message' => 'Action failed. Record not found.']);
exit();
?>