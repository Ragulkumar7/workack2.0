<?php
// manager_dashboard.php

// 1. SESSION & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION (Robust Path Resolution)
$db_path = __DIR__ . '/../include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
    $path_to_root = '../';
} else {
    require_once 'include/db_connect.php'; 
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
    $path_to_root = '';
}

date_default_timezone_set('Asia/Kolkata');
$mgr_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$user_role = $_SESSION['role'] ?? 'Manager';

// =========================================================================
// ACTION: MARK TICKET AS VIEWED
// =========================================================================
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS user_read_status TINYINT(1) DEFAULT 0");

if (isset($_GET['dismiss_ticket'])) {
    $dismiss_id = intval($_GET['dismiss_ticket']);
    $dismiss_query = "UPDATE tickets SET user_read_status = 1 WHERE id = ? AND user_id = ?";
    $stmt_dismiss = mysqli_prepare($conn, $dismiss_query);
    mysqli_stmt_bind_param($stmt_dismiss, "ii", $dismiss_id, $mgr_user_id);
    mysqli_stmt_execute($stmt_dismiss);
    header("Location: manager_dashboard.php");
    exit();
}

// =========================================================================
// MANAGER PROFILE & SHIFT TIMINGS
// =========================================================================
$mgr_name = "Manager"; $mgr_phone = "Not Set"; $mgr_email = ""; $mgr_dept = "Management"; $mgr_exp = "Senior"; 
$mgr_emergency_contacts = '[]';
$shift_timings = '09:00 AM - 06:00 PM';
$reporting_id = 0;

$profile_query = "SELECT u.username, u.email, u.role, p.* FROM users u LEFT JOIN employee_profiles p ON u.id = p.user_id WHERE u.id = ?";
$stmt_p = $conn->prepare($profile_query);
$stmt_p->bind_param("i", $mgr_user_id);
$stmt_p->execute();
if ($row = $stmt_p->get_result()->fetch_assoc()) {
    $mgr_name = $row['full_name'] ?? $row['username'];
    $mgr_phone = $row['phone'] ?? 'Not Set';
    $mgr_email = $row['email'] ?? $row['username'];
    $mgr_dept = $row['department'] ?? 'Management';
    $mgr_exp = $row['experience_label'] ?? 'Senior';
    $mgr_emergency_contacts = $row['emergency_contacts'] ?? '[]';
    $shift_timings = $row['shift_timings'] ?? $shift_timings;
    
    $reporting_id = !empty($row['manager_id']) ? $row['manager_id'] : ($row['reporting_to'] ?? 0);
    $joining_date_display = $row['joining_date'] ? date("d M Y", strtotime($row['joining_date'])) : "Not Set";
    
    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($mgr_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : $path_to_root . 'assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$regular_shift_hours = 9; 

// FETCH HIGHER MGR DETAILS
$higher_mgr_name = "Not Assigned";
$higher_mgr_phone = "N/A";
$higher_mgr_email = "N/A";
$higher_mgr_role = "ADMINISTRATOR";

if ($reporting_id > 0) {
    $hm_sql = "SELECT p.full_name, p.phone, u.email, u.role FROM employee_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
    $hm_stmt = $conn->prepare($hm_sql);
    $hm_stmt->bind_param("i", $reporting_id);
    $hm_stmt->execute();
    $hm_res = $hm_stmt->get_result();
    if ($hm_info = $hm_res->fetch_assoc()) {
        $higher_mgr_name = $hm_info['full_name'];
        $higher_mgr_phone = !empty($hm_info['phone']) ? $hm_info['phone'] : 'N/A';
        $higher_mgr_email = !empty($hm_info['email']) ? $hm_info['email'] : 'N/A';
        $higher_mgr_role = strtoupper($hm_info['role'] ?? 'ADMIN'); 
    }
    $hm_stmt->close();
}

// =========================================================================
// ADVANCED HOURS & OVERTIME CALCULATION (TIME TRACKER)
// =========================================================================
$total_seconds_today = 0; $break_seconds_today = 0; $productive_seconds_today = 0; $overtime_seconds_today = 0;
$today_punch_in = null; $attendance_record_today = null; $is_on_break = false;
$display_punch_in = "--:--"; $delay_text = ""; $delay_class = "";
$total_hours_today = "00:00:00"; $break_time_str = "00:00:00";

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    $now = date('Y-m-d H:i:s');
    if ($_POST['action'] === 'punch_in') {
        $status = (date('H:i') > '09:30') ? 'Late' : 'On Time';
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, punch_in, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $mgr_user_id, $today, $now, $status);
        if ($stmt->execute()) $response = ['status' => 'success'];
    } elseif ($_POST['action'] === 'punch_out') {
        $att_rec = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id = $mgr_user_id AND date = '$today'")->fetch_assoc();
        $break_sec = 0;
        $br_q = $conn->query("SELECT * FROM attendance_breaks WHERE attendance_id = " . $att_rec['id']);
        while($br = $br_q->fetch_assoc()){ if($br['break_end']) $break_sec += strtotime($br['break_end']) - strtotime($br['break_start']); }
        $prod_hours = max(0, (time() - strtotime($att_rec['punch_in'])) - $break_sec) / 3600;
        $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE user_id = ? AND date = ?");
        $stmt->bind_param("sdis", $now, $prod_hours, $mgr_user_id, $today);
        if ($stmt->execute()) $response = ['status' => 'success'];
    } elseif ($_POST['action'] === 'take_break') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $mgr_user_id AND date = '$today'")->fetch_assoc();
        $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
        $stmt->bind_param("is", $att_rec['id'], $now);
        if($stmt->execute()) {
            $conn->query("UPDATE attendance SET break_time = '1' WHERE id = " . $att_rec['id']);
            $response = ['status' => 'success'];
        }
    } elseif ($_POST['action'] === 'end_break') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $mgr_user_id AND date = '$today'")->fetch_assoc();
        $stmt = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
        $stmt->bind_param("si", $now, $att_rec['id']);
        if($stmt->execute()) $response = ['status' => 'success'];
    }
    echo json_encode($response); exit; 
}

$today_sql = "SELECT id, punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND date = ?";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("is", $mgr_user_id, $today);
$today_stmt->execute();
$today_res = $today_stmt->get_result();

if ($t_row = $today_res->fetch_assoc()) {
    $attendance_record_today = $t_row;
    if (!empty($t_row['punch_in'])) {
        $today_punch_in = $t_row['punch_in'];
        $display_punch_in = date('h:i A', strtotime($t_row['punch_in']));
        $in_time = strtotime($t_row['punch_in']);
        
        $expected_start_ts = strtotime($today . ' ' . $shift_start_str);
        $diff_seconds = $in_time - $expected_start_ts;
        if ($diff_seconds > 60) { 
            $delay_text = "Late by " . floor($diff_seconds / 60) . " mins";
            $delay_class = "text-rose-600 bg-rose-50 border-rose-200";
        } elseif ($diff_seconds < -60) { 
            $delay_text = "Early by " . floor(abs($diff_seconds) / 60) . " mins";
            $delay_class = "text-emerald-600 bg-emerald-50 border-emerald-200";
        } else {
            $delay_text = "On Time";
            $delay_class = "text-teal-600 bg-teal-50 border-teal-200";
        }
        
        $brk_sql = "SELECT break_start, break_end FROM attendance_breaks WHERE attendance_id = ?";
        $b_stmt = $conn->prepare($brk_sql);
        $b_stmt->bind_param("i", $t_row['id']);
        $b_stmt->execute();
        $b_res = $b_stmt->get_result();
        while($b_row = $b_res->fetch_assoc()) {
            if ($b_row['break_end']) {
                $break_seconds_today += strtotime($b_row['break_end']) - strtotime($b_row['break_start']);
            } else {
                $is_on_break = true;
                $break_start_ts = strtotime($b_row['break_start']);
                $break_seconds_today += time() - $break_start_ts;
            }
        }
        $b_stmt->close();
        
        if ($break_seconds_today == 0 && !empty($t_row['break_time'])) { $break_seconds_today = intval($t_row['break_time']) * 60; }
        
        $out_time = $is_on_break ? $break_start_ts : (!empty($t_row['punch_out']) ? strtotime($t_row['punch_out']) : time());
        $total_seconds_today = max(0, ($out_time - $in_time) - $break_seconds_today);
        
        $productive_seconds_today = max(0, $total_seconds_today);
        $shift_seconds = $regular_shift_hours * 3600;
        $overtime_seconds_today = max(0, $productive_seconds_today - $shift_seconds);
        
        $hours = floor($total_seconds_today / 3600); $mins = floor(($total_seconds_today % 3600) / 60); $secs = $total_seconds_today % 60;
        $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        
        $b_hours = floor($break_seconds_today / 3600); $b_mins = floor(($break_seconds_today % 3600) / 60); $b_secs = $break_seconds_today % 60;
        $break_time_str = sprintf('%02d:%02d:%02d', $b_hours, $b_mins, $b_secs);
    }
}
$today_stmt->close();

function formatTimeStr($seconds) {
    $h = floor($seconds / 3600); $m = floor(($seconds % 3600) / 60);
    return sprintf("%02dh %02dm", $h, $m);
}
$str_total = formatTimeStr($total_seconds_today);
$str_prod = formatTimeStr($productive_seconds_today);
$str_break = formatTimeStr($break_seconds_today);
$str_ot = formatTimeStr($overtime_seconds_today);

$bar_total = max(1, $total_seconds_today); 
$pct_prod = round((max(0, $productive_seconds_today - $overtime_seconds_today) / $bar_total) * 100);
$pct_break = round(($break_seconds_today / $bar_total) * 100);
$pct_ot = round(($overtime_seconds_today / $bar_total) * 100);

// Fetch Monthly Overtime
$ot_monthly_seconds = 0;
$ot_sql = "SELECT punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL";
$ot_stmt = $conn->prepare($ot_sql);
$ot_stmt->bind_param("iii", $mgr_user_id, $current_month, $current_year);
$ot_stmt->execute();
$ot_res = $ot_stmt->get_result();

while ($ot_row = $ot_res->fetch_assoc()) {
    $in = strtotime($ot_row['punch_in']);
    $out = strtotime($ot_row['punch_out']);
    $w_sec = max(0, $out - $in) - (intval($ot_row['break_time']) * 60);
    if ($w_sec > ($regular_shift_hours * 3600)) {
        $ot_monthly_seconds += ($w_sec - ($regular_shift_hours * 3600));
    }
}
$ot_stmt->close();
$overtime_this_month = round($ot_monthly_seconds / 3600, 1);

// =========================================================================
// MONTHLY STATS & LEAVE LOGIC
// =========================================================================
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$total_late_seconds = 0;

$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iii", $mgr_user_id, $current_month, $current_year);
$stat_stmt->execute();
$stat_res = $stat_stmt->get_result();

while ($stat_row = $stat_res->fetch_assoc()) {
    $st = $stat_row['status'];
    if (stripos($st, 'WFH') !== false) { $stats_wfh++; } 
    elseif (stripos($st, 'Absent') !== false) { $stats_absent++; } 
    elseif (stripos($st, 'Sick') !== false) { $stats_sick++; } 
    else {
        if (!empty($stat_row['punch_in'])) {
            $e_ts = strtotime($stat_row['date'] . ' ' . $shift_start_str);
            $a_ts = strtotime($stat_row['punch_in']);
            if ($a_ts > ($e_ts + 60)) { 
                $stats_late++; $total_late_seconds += ($a_ts - $e_ts);
            } else { $stats_ontime++; }
        } else { $stats_absent++; }
    }
}
$stat_stmt->close();
$late_hours = floor($total_late_seconds / 3600);
$late_minutes = floor(($total_late_seconds % 3600) / 60);
$late_time_str = $late_hours . 'h ' . $late_minutes . 'm';

$current_month_leaves = 0;
$curr_leave_sql = "SELECT leave_type, SUM(total_days) as days FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND MONTH(start_date) = ? AND YEAR(start_date) = ? GROUP BY leave_type";
$curr_leave_stmt = $conn->prepare($curr_leave_sql);
$curr_leave_stmt->bind_param("iii", $mgr_user_id, $current_month, $current_year);
$curr_leave_stmt->execute();
$curr_leave_res = $curr_leave_stmt->get_result();
while ($cl_row = $curr_leave_res->fetch_assoc()) {
    $current_month_leaves += floatval($cl_row['days']);
    if (stripos($cl_row['leave_type'], 'Sick') !== false) { $stats_sick += floatval($cl_row['days']); } 
    else { $stats_absent += floatval($cl_row['days']); }
}
$curr_leave_stmt->close();

$base_leaves_per_month = 2;
$raw_join_date = $joining_date_display !== "Not Set" ? $row['joining_date'] : date('Y-m-01');
$calc_join_date = date('Y-m-d', strtotime($raw_join_date));
$display_join_month_year = date('M Y', strtotime($raw_join_date));

$d1 = new DateTime($calc_join_date); $d1->modify('first day of this month'); 
$d2 = new DateTime('now'); $d2->modify('first day of this month');
$months_worked = ($d2 >= $d1) ? (($d1->diff($d2)->y * 12) + $d1->diff($d2)->m + 1) : 0;
$total_earned_leaves = $months_worked * $base_leaves_per_month;

$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $mgr_user_id);
$leave_stmt->execute();
$leaves_taken = floatval($leave_stmt->get_result()->fetch_assoc()['taken'] ?? 0);
$leaves_remaining = $total_earned_leaves - $leaves_taken;
$display_leaves_remaining = ($leaves_remaining < 0) ? 0 : $leaves_remaining; 
$lop_days = ($leaves_remaining < 0) ? abs($leaves_remaining) : 0;

// =========================================================================
// TEAM & DEPARTMENT OVERVIEW
// =========================================================================
$res_team = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE manager_id = $mgr_user_id OR reporting_to = $mgr_user_id")->fetch_assoc();
$total_team = $res_team['total'] ?? 0;

$res_p = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND a.date = '$today' AND (a.status='On Time' OR a.status='WFH' OR a.status='Late')")->fetch_assoc();
$team_present = $res_p['cnt'] ?? 0;
$team_absent = max(0, $total_team - $team_present);

$not_logged_in = [];
$nli_q = "SELECT user_id, full_name, designation, profile_img FROM employee_profiles 
          WHERE (manager_id = ? OR reporting_to = ?) AND user_id NOT IN (SELECT user_id FROM attendance WHERE date = ?) LIMIT 8";
$stmt_nli = $conn->prepare($nli_q);
$stmt_nli->bind_param("iis", $mgr_user_id, $mgr_user_id, $today);
$stmt_nli->execute();
$res_nli = $stmt_nli->get_result();
while($r = $res_nli->fetch_assoc()) { $not_logged_in[] = $r; }
$stmt_nli->close();

// TL PROJECTS
$tl_projects = [];
$q_tl_proj = "SELECT p.id as project_id, p.project_name, p.status, p.deadline, ep.full_name as tl_name, ep.profile_img,
              (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id) as total_tasks,
              (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id AND pt.status = 'Completed') as completed_tasks
              FROM projects p JOIN employee_profiles ep ON p.leader_id = ep.user_id 
              WHERE (ep.manager_id = ? OR ep.reporting_to = ?) ORDER BY p.id DESC LIMIT 4";
$stmt_tlp = $conn->prepare($q_tl_proj);
$stmt_tlp->bind_param("ii", $mgr_user_id, $mgr_user_id);
$stmt_tlp->execute();
$res_tlp = $stmt_tlp->get_result();
while($r = $res_tlp->fetch_assoc()) { 
    $r['dynamic_progress'] = ($r['total_tasks'] > 0) ? round(($r['completed_tasks'] / $r['total_tasks']) * 100) : 0;
    $tl_projects[] = $r; 
}
$stmt_tlp->close();

// ACTION NEEDED
$action_requests = [];
$q_swaps = "SELECT ssr.id, ep.full_name, ssr.request_date, 'Shift Swap' as req_type 
            FROM shift_swap_requests ssr JOIN employee_profiles ep ON ssr.user_id = ep.user_id 
            WHERE ssr.tl_approval = 'Approved' AND ssr.manager_approval = 'Pending' LIMIT 4";
$r_swaps = mysqli_query($conn, $q_swaps);
if($r_swaps) { while($r = mysqli_fetch_assoc($r_swaps)) { $action_requests[] = $r; } }

$q_mleaves = "SELECT lr.id, ep.full_name, lr.created_at as request_date, 'Leave' as req_type 
              FROM leave_requests lr JOIN employee_profiles ep ON lr.user_id = ep.user_id 
              WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND lr.status = 'Pending' LIMIT 4";
$r_mleaves = mysqli_query($conn, $q_mleaves);
if($r_mleaves) { while($r = mysqli_fetch_assoc($r_mleaves)) { $action_requests[] = $r; } }

// NOTIFICATIONS
$all_notifications = [];
$q_tickets = "SELECT id, ticket_code, subject, created_at FROM tickets WHERE user_id = $mgr_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY created_at DESC LIMIT 3";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 'id' => $row['id'], 'title' => 'Ticket Solved',
            'message' => htmlspecialchars($row['subject']), 'time' => $row['created_at'], 'icon' => 'fa-check-double', 'color' => 'text-green-600 bg-green-100', 'link' => '?dismiss_ticket=' . $row['id']
        ];
    }
}
usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 10); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - SmartHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; height: 100%; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1; transform: translateY(-2px); }
        
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .stat-badge { font-size: 1.5rem; font-weight: 900; line-height: 1; color: #1e293b; }
        
        /* EXACT 3x3 MATRIX GRID (NO GAPS) */
        .matrix-grid { 
            display: grid; 
            grid-template-columns: repeat(1, minmax(0, 1fr)); 
            gap: 1.5rem; 
            align-items: stretch;
        }
        @media (min-width: 1024px) {
            .matrix-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        #mainContent { margin-left: 90px; width: calc(100% - 90px); transition: all 0.3s; padding: 24px; box-sizing: border-box; max-width: 1600px; margin-right: auto;}
        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px;}
        }

        .progress-ring-circle { transition: stroke-dashoffset 0.35s; transform: rotate(-90deg); transform-origin: 50% 50%; }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>
    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <main id="mainContent">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">Manager Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Welcome back, <b class="text-slate-700"><?php echo htmlspecialchars($mgr_name); ?></b></p>
            </div>
            <div class="flex gap-2">
                <button class="bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 shadow-sm hover:bg-gray-50 transition">
                    <i class="fa-solid fa-file-export text-teal-600 mr-1"></i> Export
                </button>
                <div class="bg-teal-50 border border-teal-100 px-4 py-2.5 rounded-xl text-sm font-bold text-teal-700 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='manager_employee.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Total Team</p><p class="stat-badge"><?php echo $total_team; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Present Today</p><p class="stat-badge text-emerald-600"><?php echo $team_present; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-check"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Absent Today</p><p class="stat-badge text-red-500"><?php echo $team_absent; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-xmark"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between hover:shadow-md transition">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Pending Actions</p><p class="stat-badge text-orange-500"><?php echo count($action_requests); ?></p></div>
                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-lg"><i class="fa-solid fa-clipboard-list"></i></div>
            </div>
        </div>

        <div class="matrix-grid">

            <div class="card overflow-hidden shadow-sm border-slate-200">
                <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-6 flex flex-col items-center text-center relative rounded-t-xl shrink-0">
                    <div class="absolute top-3 right-3 bg-white/20 px-2 py-0.5 rounded backdrop-blur-sm">
                        <span class="text-[9px] text-white font-bold tracking-widest uppercase">Verified</span>
                    </div>
                    <div class="relative mb-2">
                        <img src="<?php echo $profile_img; ?>" class="w-24 h-24 rounded-full border-4 border-white shadow-xl object-cover bg-white">
                        <div class="absolute bottom-1 right-1 w-5 h-5 bg-green-400 border-[3px] border-white rounded-full shadow-sm"></div>
                    </div>
                    <h2 class="text-white font-black text-xl leading-tight tracking-tight"><?php echo htmlspecialchars($mgr_name); ?></h2>
                    <p class="text-teal-100 text-xs font-semibold mt-1 uppercase tracking-widest"><?php echo htmlspecialchars($user_role); ?></p>
                </div>
                <div class="p-5 flex flex-col justify-between flex-grow">
                    <div class="flex flex-col gap-2.5">
                        <div class="flex items-center gap-3 border border-slate-100 p-2.5 rounded-lg bg-slate-50">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0"><i class="fa-solid fa-phone text-sm"></i></div>
                            <div class="min-w-0">
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Phone Number</p>
                                <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($mgr_phone); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 border border-slate-100 p-2.5 rounded-lg bg-slate-50">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0"><i class="fa-solid fa-envelope text-sm"></i></div>
                            <div class="min-w-0 w-full">
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Email Address</p>
                                <p class="text-xs font-bold text-slate-800 truncate w-full" title="<?php echo htmlspecialchars($mgr_email); ?>"><?php echo htmlspecialchars($mgr_email); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-indigo-50 p-3 rounded-lg border border-indigo-100 mt-4">
                        <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-1.5">Reporting To</p>
                        <div class="flex items-center justify-between mb-1 gap-2">
                            <p class="text-xs font-bold text-indigo-900 truncate"><?php echo htmlspecialchars($higher_mgr_name); ?></p>
                            <span class="text-[8px] font-black text-white bg-indigo-500 px-2 py-0.5 rounded shadow-sm shrink-0"><?php echo htmlspecialchars($higher_mgr_role); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-teal-200">
                <div class="p-6 flex flex-col items-center w-full h-full">
                    <div class="w-full flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-fingerprint text-teal-600"></i> Punch Action</h3>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded"><?php echo date("d M Y"); ?></span>
                    </div>

                    <div class="w-full bg-slate-50 border border-slate-100 p-3 rounded-xl mb-4 flex justify-between items-center shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded bg-teal-100 flex items-center justify-center text-teal-600"><i class="fa-solid fa-clock"></i></div>
                            <div><p class="text-[9px] font-bold text-gray-400 uppercase">Shift</p><p class="text-xs font-bold text-slate-700">General</p></div>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] font-bold text-gray-400 uppercase">Timings</p>
                            <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($shift_timings); ?></p>
                        </div>
                    </div>

                    <div class="relative w-36 h-36 mb-4 shrink-0 flex-grow flex items-center justify-center">
                        <svg class="w-full h-full transform -rotate-90 absolute" viewBox="0 0 176 176">
                            <circle cx="88" cy="88" r="78" stroke="#f1f5f9" stroke-width="12" fill="transparent"></circle>
                            <?php 
                                $pct = min(1, max(0, $total_seconds_today) / 32400); 
                                $circumference = 490; 
                                $dashoffset = $circumference - ($pct * $circumference);
                                $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                            ?>
                            <circle cx="88" cy="88" r="78" stroke="<?php echo $ringColor; ?>" stroke-width="12" fill="transparent" 
                                stroke-dasharray="490" stroke-dashoffset="<?php echo ($attendance_record_today && $attendance_record_today['punch_out']) ? '0' : max(0, $dashoffset); ?>" 
                                stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider"><?php echo $is_on_break ? 'WORK PAUSED' : 'TOTAL WORK'; ?></p>
                            <p class="text-xl font-black <?php echo $is_on_break ? 'text-gray-400' : 'text-slate-800'; ?>" id="liveTimer" 
                                data-running="<?php echo ($attendance_record_today && !$attendance_record_today['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>"
                                data-total="<?php echo $total_seconds_today; ?>">
                                <?php echo $total_hours_today; ?>
                            </p>
                            <?php if ($is_on_break): ?>
                                <div class="mt-1 flex items-center justify-center gap-1 text-amber-500 font-bold text-xs bg-amber-50 px-2 py-0.5 rounded-full animate-pulse">
                                    <i class="fa-solid fa-mug-hot text-[9px]"></i>
                                    <span id="breakTimer" data-break-running="true" data-break-total="<?php echo $display_break_seconds; ?>"><?php echo $break_time_str; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="w-full shrink-0">
                        <?php if (!$attendance_record_today): ?>
                            <button type="button" onclick="punchAction('punch_in')" id="btnPunchIn" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 rounded-lg shadow-md transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-right-to-bracket"></i> Punch In
                            </button>
                        <?php elseif (!$attendance_record_today['punch_out']): ?>
                            <div class="grid grid-cols-2 gap-2 w-full">
                                <?php if ($is_on_break): ?>
                                    <button type="button" onclick="punchAction('end_break')" id="btnEndBreak" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 rounded-lg shadow-sm transition flex justify-center items-center gap-2 text-sm">
                                        <i class="fa-solid fa-play"></i> Resume
                                    </button>
                                <?php else: ?>
                                    <button type="button" onclick="punchAction('take_break')" id="btnBreak" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 rounded-lg shadow-sm transition flex justify-center items-center gap-2 text-sm">
                                        <i class="fa-solid fa-mug-hot"></i> Break
                                    </button>
                                <?php endif; ?>
                                <button type="button" onclick="punchAction('punch_out')" id="btnPunchOut" class="bg-red-500 hover:bg-rose-600 text-white font-bold py-2.5 rounded-lg shadow-sm transition flex justify-center items-center gap-2 text-sm">
                                    <i class="fa-solid fa-right-from-bracket"></i> Out
                                </button>
                            </div>
                        <?php else: ?>
                            <button disabled class="w-full bg-slate-100 text-slate-400 font-bold py-2.5 rounded-lg cursor-not-allowed flex justify-center items-center gap-2 border border-slate-200">
                                <i class="fa-solid fa-check-circle text-emerald-500"></i> Shift Completed
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if($attendance_record_today): ?>
                    <div class="w-full mt-3 flex justify-between items-center bg-gray-50 p-2 rounded border border-gray-100 shrink-0">
                        <p class="text-[9px] text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-fingerprint text-teal-600"></i> In: <span class="font-bold text-slate-700"><?php echo $display_punch_in; ?></span>
                        </p>
                        <?php if($delay_text != ""): ?>
                            <span class="text-[8px] font-bold px-1.5 py-0.5 border rounded <?php echo $delay_class; ?>"><?php echo $delay_text; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-blue-200">
                <div class="p-6 flex flex-col justify-center h-full">
                    <div class="flex justify-between items-center mb-5 border-b border-blue-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2"><i class="fa-solid fa-stopwatch text-blue-500 text-lg"></i> Today's Time Tracker</h3>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded border border-gray-100">Live</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4 flex-grow content-center">
                        <div>
                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mb-1 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 block"></span> Productive</p>
                            <p class="text-lg font-black text-slate-800"><?php echo $str_prod; ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mb-1 flex items-center justify-end gap-1"><span class="w-2 h-2 rounded-full bg-amber-400 block"></span> Break</p>
                            <p class="text-lg font-black text-slate-800"><?php echo $str_break; ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mb-1 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500 block"></span> Overtime</p>
                            <p class="text-lg font-black text-slate-800"><?php echo $str_ot; ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mb-1">Total Hours</p>
                            <p class="text-lg font-black text-blue-600"><?php echo $str_total; ?></p>
                        </div>
                    </div>

                    <div class="w-full bg-slate-100 rounded-full h-3 flex overflow-hidden mb-5 border border-slate-200/60 shadow-inner shrink-0">
                        <div class="bg-emerald-500 h-full transition-all" style="width: <?php echo $pct_prod; ?>%" title="Productive"></div>
                        <div class="bg-amber-400 h-full transition-all" style="width: <?php echo $pct_break; ?>%" title="Break"></div>
                        <div class="bg-blue-500 h-full transition-all" style="width: <?php echo $pct_ot; ?>%" title="Overtime"></div>
                    </div>
                    
                    <div class="pt-3 border-t border-gray-100 shrink-0 mt-auto">
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1.5">Total Overtime This Month</p>
                        <div class="flex items-center justify-between bg-orange-50 border border-orange-100 p-2.5 rounded-lg">
                            <span class="text-base font-black text-orange-600"><?php echo $overtime_this_month; ?> <span class="text-[10px] font-bold text-orange-500">Hrs</span></span>
                            <span class="text-[8px] bg-white text-orange-500 border border-orange-200 px-2 py-0.5 rounded font-black uppercase tracking-wider shadow-sm">Bonus Target</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">My Monthly Stats</h3>
                        <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                    </div>
                    <div class="flex flex-col items-center justify-center gap-4 flex-grow">
                        <div class="space-y-3 w-full">
                            <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="text-xs text-gray-600 font-semibold">On Time</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_ontime; ?></span></div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="text-xs text-gray-600 font-semibold">Late</span></div>
                                <div class="text-right">
                                    <span class="font-bold text-slate-800 text-sm block"><?php echo $stats_late; ?></span>
                                    <span class="text-[9px] text-gray-400 block -mt-1 font-bold"><?php echo $late_time_str; ?></span>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div><span class="text-xs text-gray-600 font-semibold">WFH</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_wfh; ?></span></div>
                            <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div><span class="text-xs text-gray-600 font-semibold">Absent</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_absent; ?></span></div>
                            
                            <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                <div class="flex items-center gap-2"><i class="fa-solid fa-plane-departure text-rose-400 text-xs"></i><span class="text-xs text-slate-800 font-bold uppercase">Leaves Taken</span></div>
                                <span class="font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded text-xs"><?php echo $current_month_leaves; ?> Days</span>
                            </div>
                        </div>
                        <div class="relative flex-shrink-0 w-28 h-28 mx-auto mt-2">
                            <div id="attendanceChart" class="w-full h-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6 flex flex-col h-full justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-5">
                            <div class="bg-teal-50 p-4 rounded-xl text-center border border-teal-100 flex flex-col justify-center">
                                <p class="text-[9px] text-teal-700 font-bold uppercase mb-1">Earned</p>
                                <p class="text-2xl font-black text-teal-800"><?php echo $total_earned_leaves; ?></p>
                                <p class="text-[8px] text-teal-600/70 mt-1 font-semibold truncate">Since: <?php echo $display_join_month_year; ?></p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl text-center border border-blue-100 flex flex-col justify-center">
                                <p class="text-[9px] text-blue-700 font-bold uppercase mb-1">Taken</p>
                                <p class="text-2xl font-black text-blue-800"><?php echo $leaves_taken; ?></p>
                                <p class="text-[8px] text-blue-600/70 mt-1 font-semibold truncate">Approved Only</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-xl text-center border border-green-200 shadow-sm relative overflow-hidden flex flex-col justify-center">
                                <p class="text-[9px] text-green-800 font-bold uppercase relative z-10 mb-1">Left</p>
                                <p class="text-3xl font-black relative z-10 <?php echo $leaves_remaining < 0 ? 'text-rose-600' : 'text-green-800'; ?>">
                                    <?php echo $display_leaves_remaining; ?>
                                </p>
                                <?php if($leaves_remaining < 0): ?>
                                    <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-rose-500"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if($leaves_remaining < 0): ?>
                            <div class="bg-rose-50 border border-rose-200 rounded-lg p-2.5 mb-4 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 flex-shrink-0"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <p class="text-xs font-semibold text-rose-700 leading-tight">Leave limit exceeded! <b><?php echo $lop_days; ?> Days</b> considered as LOP.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-auto shrink-0">
                        <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-lg text-center transition shadow-md shadow-teal-200/50 text-sm">
                            <i class="fa-solid fa-plus mr-1.5"></i> APPLY FOR LEAVE
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                        <button class="text-[10px] text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded uppercase border border-teal-100">Live Feed</button>
                    </div>
                    <div class="space-y-3 custom-scroll overflow-y-auto h-[240px] pr-2 flex-grow">
                        <?php if(!empty($all_notifications)): ?>
                            <?php foreach($all_notifications as $notif): ?>
                            <div class="flex gap-3 items-start border-b border-gray-50 pb-3 last:border-0 hover:bg-slate-50 transition p-2 -mx-2 rounded relative">
                                <div class="w-8 h-8 rounded-full <?php echo $notif['color']; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                    <i class="fa-solid <?php echo $notif['icon']; ?>"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                    <p class="text-[10px] text-gray-400"><?php echo date("d M Y, h:i A", strtotime($notif['time'])); ?></p>
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    
                                    <div class="mt-2">
                                        <?php if(isset($notif['type']) && $notif['type'] == 'ticket'): ?>
                                            <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[10px] bg-green-50 text-green-700 font-bold px-2 py-1 rounded border border-green-200 hover:bg-green-100 transition">
                                                <i class="fa-solid fa-check mr-1"></i> Mark as Viewed
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[10px] bg-slate-100 text-slate-600 font-bold px-2 py-1 rounded hover:bg-slate-200 transition">
                                                View Details <i class="fa-solid fa-arrow-right ml-1 text-[8px]"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-regular fa-bell-slash text-3xl mb-2 opacity-50"></i>
                                <p class='text-sm font-medium'>No new notifications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-orange-200">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4 border-b border-orange-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">Action Needed</h3>
                        <span class="bg-orange-100 text-orange-700 text-[10px] px-2 py-1 rounded font-bold uppercase border border-orange-200">Approvals</span>
                    </div>
                    <div class="space-y-3 custom-scroll overflow-y-auto h-[240px] pr-2 flex-grow">
                        <?php if(!empty($action_requests)): ?>
                            <?php foreach($action_requests as $req): 
                                $icon = $req['req_type'] == 'Leave' ? 'fa-plane-departure text-rose-500' : 'fa-people-arrows text-blue-500';
                                $bg = $req['req_type'] == 'Leave' ? 'bg-rose-50 border-rose-100' : 'bg-blue-50 border-blue-100';
                                $link = $req['req_type'] == 'Leave' ? '../employee/leave_request.php' : 'shift_swap_approval_manager.php';
                            ?>
                            <div class="p-3 rounded-lg border <?php echo $bg; ?> flex items-center justify-between transition hover:shadow-md">
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid <?php echo $icon; ?> text-lg w-5 text-center"></i>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($req['full_name']); ?></p>
                                        <p class="text-[10px] text-slate-500 font-medium"><?php echo $req['req_type']; ?> Request</p>
                                    </div>
                                </div>
                                <a href="<?php echo $link; ?>" class="text-[10px] bg-white border px-2 py-1 rounded font-bold text-slate-600 hover:bg-slate-100 transition shadow-sm">Review</a>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-solid fa-mug-hot text-3xl mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">No pending approvals!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">TL Projects Progress</h3>
                        <a href="manager_projects.php" class="text-[10px] bg-teal-50 text-teal-700 font-bold px-2 py-1 rounded uppercase hover:bg-teal-100 transition border border-teal-200">Manage</a>
                    </div>
                    <div class="space-y-4 custom-scroll overflow-y-auto h-[240px] pr-2 flex-grow">
                        <?php if(!empty($tl_projects)): ?>
                            <?php foreach($tl_projects as $proj): 
                                $p_status = $proj['status'];
                                $progress_pct = intval($proj['dynamic_progress']);
                                
                                $prog_color = 'bg-blue-500';
                                if($progress_pct >= 100) { $prog_color = 'bg-emerald-500'; }
                                elseif($progress_pct < 30) { $prog_color = 'bg-orange-500'; }

                                $tl_img = "https://ui-avatars.com/api/?name=".urlencode($proj['tl_name'])."&background=random";
                                if (!empty($proj['profile_img']) && $proj['profile_img'] !== 'default_user.png') {
                                    $tl_img = str_starts_with($proj['profile_img'], 'http') ? $proj['profile_img'] : $path_to_root . 'assets/profiles/' . $proj['profile_img'];
                                }
                            ?>
                            <div class="p-3.5 border border-gray-100 rounded-xl bg-slate-50 hover:border-teal-200 transition shadow-sm flex flex-col gap-2">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-sm text-slate-800 truncate pr-2 w-3/4" title="<?php echo htmlspecialchars($proj['project_name']); ?>">
                                        <?php echo htmlspecialchars($proj['project_name']); ?>
                                    </h4>
                                    <span class="text-[8px] font-bold px-2 py-0.5 rounded uppercase tracking-wider bg-white border border-gray-200 text-gray-600 flex-shrink-0 shadow-sm">
                                        <?php echo htmlspecialchars($p_status); ?>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 mb-1.5">
                                    <img src="<?php echo $tl_img; ?>" class="w-5 h-5 rounded-full object-cover border border-slate-200">
                                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider truncate">TL: <?php echo htmlspecialchars($proj['tl_name']); ?></span>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[9px] font-bold text-gray-500 mb-1 uppercase tracking-wider">
                                        <span>Progress (<?php echo $proj['completed_tasks'] . '/' . $proj['total_tasks']; ?>)</span>
                                        <span class="<?php echo str_replace('bg-', 'text-', $prog_color); ?>"><?php echo $progress_pct; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                        <div class="<?php echo $prog_color; ?> h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $progress_pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-solid fa-layer-group text-3xl mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">No projects handled currently.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-red-200">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4 border-b border-red-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                            <i class="fa-solid fa-user-clock text-red-500"></i> Not Logged In
                        </h3>
                        <span class="bg-red-50 text-red-600 px-3 py-1 rounded-full text-xs font-bold border border-red-200 shadow-sm"><?php echo count($not_logged_in); ?> Absent</span>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-2.5 custom-scroll overflow-y-auto h-[240px] pr-2 flex-grow">
                        <?php if(!empty($not_logged_in)): ?>
                            <?php foreach($not_logged_in as $nli): 
                                $n_img = "https://ui-avatars.com/api/?name=".urlencode($nli['full_name'])."&background=random";
                                if (!empty($nli['profile_img']) && $nli['profile_img'] !== 'default_user.png') {
                                    $n_img = str_starts_with($nli['profile_img'], 'http') ? $nli['profile_img'] : $path_to_root . 'assets/profiles/' . $nli['profile_img'];
                                }
                            ?>
                            <div class="flex items-center gap-3 p-2.5 bg-red-50/50 border border-red-100 rounded-xl hover:shadow-sm transition">
                                <img src="<?php echo $n_img; ?>" class="w-9 h-9 rounded-full object-cover border border-red-200 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($nli['full_name']); ?>"><?php echo htmlspecialchars($nli['full_name']); ?></p>
                                    <p class="text-[10px] text-slate-500 font-medium truncate"><?php echo htmlspecialchars($nli['designation']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-solid fa-check-double text-3xl mb-2 text-emerald-400 opacity-80"></i>
                                <p class="text-sm font-medium text-slate-600">Excellent!</p>
                                <p class="text-xs">Everyone is present today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // 1. LIVE TIMER LOGIC
            const timerElement = document.getElementById('liveTimer');
            const progressRing = document.getElementById('progressRing');
            const breakTimerElement = document.getElementById('breakTimer');

            if(timerElement) {
                const isWorkRunning = timerElement.getAttribute('data-running') === 'true';
                const isBreakRunning = breakTimerElement ? breakTimerElement.getAttribute('data-break-running') === 'true' : false;
                
                let workTotalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
                let breakTotalSeconds = breakTimerElement ? (parseInt(breakTimerElement.getAttribute('data-break-total')) || 0) : 0;
                const startTime = new Date().getTime(); 

                function formatTime(totalSecs) {
                    const h = Math.floor(totalSecs / 3600);
                    const m = Math.floor((totalSecs % 3600) / 60);
                    const s = totalSecs % 60;
                    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'); 
                }

                function updateTimer() {
                    const now = new Date().getTime();
                    const diffSeconds = Math.floor((now - startTime) / 1000);
                    
                    if (isWorkRunning) {
                        const currentWork = workTotalSeconds + diffSeconds;
                        timerElement.innerText = formatTime(currentWork);
                        const progress = Math.min(currentWork / 32400, 1);
                        if(progressRing) progressRing.style.strokeDashoffset = 490 - (progress * 490);
                    }

                    if (isBreakRunning && breakTimerElement) {
                        const currentBreak = breakTotalSeconds + diffSeconds;
                        breakTimerElement.innerText = formatTime(currentBreak);
                    }
                }

                if (isWorkRunning || isBreakRunning) {
                    setInterval(updateTimer, 1000);
                    updateTimer();
                }
            }

            // 2. DONUT CHART LOGIC
            var lateTimeStr = "<?php echo $late_time_str; ?>";
            var totalData = <?php echo $stats_ontime + $stats_late + $stats_wfh + $stats_absent + $stats_sick; ?>;
            var seriesData = totalData > 0 ? [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>] : [0,0,0,0,0];

            var attOptions = {
                series: seriesData,
                chart: { type: 'donut', width: 100, height: 100, sparkline: { enabled: true } },
                labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick Leave'],
                colors: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                stroke: { width: 0 },
                tooltip: { 
                    fixed: { enabled: false }, 
                    x: { show: true }, 
                    marker: { show: true },
                    y: {
                        formatter: function(val, opts) {
                            if (opts.seriesIndex === 1) { return val + " Days (Total: " + lateTimeStr + ")"; }
                            return val + " Days";
                        }
                    }
                }
            };
            
            var attendanceChartEl = document.querySelector("#attendanceChart");
            if (attendanceChartEl) { new ApexCharts(attendanceChartEl, attOptions).render(); }
        });

        // 3. AJAX ACTION HANDLERS
        function punchAction(action) {
            let btnId = '';
            if(action === 'punch_in') btnId = 'btnPunchIn';
            else if(action === 'punch_out') btnId = 'btnPunchOut';
            else if(action === 'take_break') btnId = 'btnBreak';
            else if(action === 'end_break') btnId = 'btnEndBreak';
            
            const btn = document.getElementById(btnId);
            if(btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner inline-block w-4 h-4 border-2 border-white rounded-full border-t-transparent animate-spin"></span>';
            }

            const formData = new FormData();
            formData.append('action', action);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    window.location.reload(); 
                } else {
                    alert('Error: ' + data.message);
                    if(btn) window.location.reload(); 
                }
            })
            .catch(error => {
                alert('Network Error occurred.');
                if(btn) window.location.reload();
            });
        }
    </script>
</body>
</html>