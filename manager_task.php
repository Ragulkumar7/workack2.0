<?php
// manager_dashboard.php

// 1. SESSION & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION
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
// MANAGER PROFILE & SHIFT TIMINGS & MANAGER DEETS
// =========================================================================
$mgr_name = "Manager"; $mgr_phone = "Not Set"; $mgr_email = "Not Set"; $mgr_dept = "Management"; $mgr_exp = "Senior"; 
$mgr_emergency_contacts = '[]';
$shift_timings = '09:00 AM - 06:00 PM';
$joining_date = "Not Set";
$reporting_id = 0;

$higher_mgr_name = "Not Assigned";
$higher_mgr_phone = "N/A";
$higher_mgr_email = "N/A";

$profile_query = "SELECT u.username, u.email, u.role, p.* FROM users u LEFT JOIN employee_profiles p ON u.id = p.user_id WHERE u.id = ?";
$stmt_p = $conn->prepare($profile_query);
$stmt_p->bind_param("i", $mgr_user_id);
$stmt_p->execute();
if ($row = $stmt_p->get_result()->fetch_assoc()) {
    $mgr_name = $row['full_name'] ?? $row['username'];
    $mgr_phone = $row['phone'] ?? 'Not Set';
    $mgr_email = !empty($row['email']) ? $row['email'] : $row['username'];
    $mgr_dept = $row['department'] ?? 'Management';
    $mgr_exp = $row['experience_label'] ?? 'Senior';
    $mgr_emergency_contacts = $row['emergency_contacts'] ?? '[]';
    $shift_timings = $row['shift_timings'] ?? $shift_timings;
    
    $joining_date = $row['joining_date'] ?? null;
    $joining_date_display = $joining_date ? date("d M Y", strtotime($joining_date)) : "Not Set";
    
    $reporting_id = intval($row['manager_id'] ?? $row['reporting_to'] ?? 0);

    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($mgr_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : $path_to_root . 'assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

// Fetch Manager Contact Deets
if ($reporting_id > 0) {
    $mgr_res = $conn->query("SELECT ep.full_name, ep.phone, u.email FROM employee_profiles ep JOIN users u ON ep.user_id = u.id WHERE ep.user_id = $reporting_id")->fetch_assoc();
    if($mgr_res) {
        $higher_mgr_name = $mgr_res['full_name'];
        $higher_mgr_phone = !empty($mgr_res['phone']) ? $mgr_res['phone'] : 'N/A';
        $higher_mgr_email = !empty($mgr_res['email']) ? $mgr_res['email'] : 'N/A';
    }
}

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$regular_shift_hours = 9;

// =========================================================================
// ADVANCED TIME TRACKER (TODAY'S HOURS SUMMARY FOR UI)
// =========================================================================
$total_seconds_today = 0; $break_seconds_today = 0; $productive_seconds_today = 0; $overtime_seconds_today = 0;
$display_break_seconds = 0; $today_punch_in = null; $attendance_record_today = null;
$is_on_break = false; $display_punch_in = "--:--"; $delay_text = ""; $delay_class = "";
$total_hours_today = "00:00:00"; $break_time_str = "00:00:00";

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
        
        $display_break_seconds = $break_seconds_today;
        $out_time = $is_on_break ? $break_start_ts : (!empty($t_row['punch_out']) ? strtotime($t_row['punch_out']) : time());
        $total_seconds_today = max(0, ($out_time - $in_time) - $break_seconds_today);
        
        $productive_seconds_today = max(0, $total_seconds_today);
        $shift_seconds = $regular_shift_hours * 3600;
        $overtime_seconds_today = max(0, $productive_seconds_today - $shift_seconds);
        
        $hours = floor($total_seconds_today / 3600); $mins = floor(($total_seconds_today % 3600) / 60); $secs = $total_seconds_today % 60;
        $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        
        $b_hours = floor($display_break_seconds / 3600); $b_mins = floor(($display_break_seconds % 3600) / 60); $b_secs = $display_break_seconds % 60;
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
$str_break = formatTimeStr($display_break_seconds);
$str_ot = formatTimeStr($overtime_seconds_today);

$bar_total = max(1, $total_seconds_today); 
$pct_prod = round((max(0, $productive_seconds_today - $overtime_seconds_today) / $bar_total) * 100);
$pct_break = round(($display_break_seconds / $bar_total) * 100);
$pct_ot = round(($overtime_seconds_today / $bar_total) * 100);

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
// MGR'S OWN MONTHLY ATTENDANCE STATS
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
            $expected_start_ts = strtotime($stat_row['date'] . ' ' . $shift_start_str);
            $actual_start_ts = strtotime($stat_row['punch_in']);
            if ($actual_start_ts > ($expected_start_ts + 60)) { 
                $stats_late++; $total_late_seconds += ($actual_start_ts - $expected_start_ts);
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

// =========================================================================
// MGR'S OWN LEAVE CARRY-FORWARD LOGIC
// =========================================================================
$base_leaves_per_month = 2;
$raw_join_date = (!empty($joining_date) && $joining_date !== "Not Set") ? $joining_date : date('Y-m-01');
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
// FETCH MY TEAM & TEAM ATTENDANCE
// =========================================================================
$team_members = [];
$team_q = "SELECT ep.user_id, ep.full_name, ep.designation, ep.profile_img, a.status as today_status 
           FROM employee_profiles ep 
           LEFT JOIN attendance a ON ep.user_id = a.user_id AND a.date = ? 
           WHERE ep.manager_id = ? OR ep.reporting_to = ? LIMIT 10";
$stmt_team = $conn->prepare($team_q);
if ($stmt_team) {
    $stmt_team->bind_param("sii", $today, $mgr_user_id, $mgr_user_id);
    $stmt_team->execute();
    $res_team_data = $stmt_team->get_result();
    while ($r = $res_team_data->fetch_assoc()) {
        $team_members[] = $r;
    }
    $stmt_team->close();
}

$res_team = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE manager_id = $mgr_user_id OR reporting_to = $mgr_user_id")->fetch_assoc();
$total_team = $res_team['total'] ?? 0;

$res_p = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND a.date = '$today' AND (a.status='On Time' OR a.status='WFH' OR a.status='Late')")->fetch_assoc();
$team_present = $res_p['cnt'] ?? 0;

$res_l = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND a.date = '$today' AND a.status='Late'")->fetch_assoc();
$team_late = $res_l['cnt'] ?? 0;

$team_absent = max(0, $total_team - $team_present);
$team_att_pct = ($total_team > 0) ? round(($team_present / $total_team) * 100) : 0;

$not_logged_in = [];
$nli_q = "SELECT user_id, full_name, designation, profile_img FROM employee_profiles 
          WHERE (manager_id = ? OR reporting_to = ?) AND user_id NOT IN (SELECT user_id FROM attendance WHERE date = ?) LIMIT 8";
$stmt_nli = $conn->prepare($nli_q);
$stmt_nli->bind_param("iis", $mgr_user_id, $mgr_user_id, $today);
$stmt_nli->execute();
$res_nli = $stmt_nli->get_result();
while($r = $res_nli->fetch_assoc()) { $not_logged_in[] = $r; }
$stmt_nli->close();

// =========================================================================
// TL PROJECTS DYNAMIC PROGRESS 
// =========================================================================
$active_projects = [];
$q_tl_proj = "SELECT p.id as project_id, p.project_name, p.status, p.deadline, ep.full_name as tl_name, ep.profile_img,
              (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id) as total_tasks,
              (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id AND pt.status = 'Completed') as completed_tasks
              FROM projects p JOIN employee_profiles ep ON p.leader_id = ep.user_id 
              WHERE p.created_by = ? AND p.status != 'Completed' ORDER BY p.id DESC LIMIT 4";
$stmt_tlp = $conn->prepare($q_tl_proj);
if ($stmt_tlp) {
    $stmt_tlp->bind_param("i", $mgr_user_id);
    $stmt_tlp->execute();
    $res_tlp = $stmt_tlp->get_result();
    while($r = $res_tlp->fetch_assoc()) { 
        $r['dynamic_progress'] = ($r['total_tasks'] > 0) ? round(($r['completed_tasks'] / $r['total_tasks']) * 100) : 0;
        $active_projects[] = $r; 
    }
    $stmt_tlp->close();
}

// =========================================================================
// UNIFIED NOTIFICATIONS
// =========================================================================
$all_notifications = [];

$q_tickets = "SELECT id, ticket_code, subject FROM tickets WHERE user_id = $mgr_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY id DESC LIMIT 3";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 'id' => $row['id'],
            'title' => 'Ticket Solved: #' . ($row['ticket_code'] ?? $row['id']),
            'message' => 'IT Team resolved: ' . htmlspecialchars($row['subject']),
            'time' => date('Y-m-d H:i:s'),
            'icon' => 'fa-check-double', 'color' => 'text-emerald-600 bg-emerald-100',
            'link' => '?dismiss_ticket=' . $row['id']
        ];
    }
}

$q_leaves = "SELECT leave_type, status FROM leave_requests WHERE user_id = $mgr_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 3";
$r_leaves = mysqli_query($conn, $q_leaves);
if($r_leaves) {
    while($row = mysqli_fetch_assoc($r_leaves)) {
        $icon = $row['status'] == 'Approved' ? 'fa-check-circle' : 'fa-times-circle';
        $color = $row['status'] == 'Approved' ? 'text-emerald-600 bg-emerald-100' : 'text-rose-600 bg-rose-100';
        $all_notifications[] = [
            'type' => 'leave',
            'title' => 'Leave ' . $row['status'],
            'message' => 'Your ' . htmlspecialchars($row['leave_type']) . ' request was ' . strtolower($row['status']) . '.',
            'time' => date('Y-m-d H:i:s'), 
            'icon' => $icon, 'color' => $color,
            'link' => '../employee/leave_request.php'
        ];
    }
}

$q_swaps = "SELECT status FROM shift_swap_requests WHERE user_id = $mgr_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 2";
$r_swaps = mysqli_query($conn, $q_swaps);
if($r_swaps) {
    while($row = mysqli_fetch_assoc($r_swaps)) {
        $icon = $row['status'] == 'Approved' ? 'fa-check-circle' : 'fa-times-circle';
        $color = $row['status'] == 'Approved' ? 'text-emerald-600 bg-emerald-100' : 'text-rose-600 bg-rose-100';
        $all_notifications[] = [
            'type' => 'swap',
            'title' => 'Shift Swap ' . $row['status'],
            'message' => 'Your shift swap request was ' . strtolower($row['status']) . '.',
            'time' => date('Y-m-d H:i:s'), 
            'icon' => $icon, 'color' => $color,
            'link' => '../employee/shift_swap_request.php'
        ];
    }
}

$q_announcements = "SELECT id, title, message FROM announcements WHERE is_archived = 0 AND (target_audience = 'All' OR target_audience = '$user_role') ORDER BY id DESC LIMIT 5"; 
$r_announcements = mysqli_query($conn, $q_announcements);
if($r_announcements) {
    while($row = mysqli_fetch_assoc($r_announcements)) {
        $all_notifications[] = [
            'type' => 'announcement',
            'title' => 'Announcement: ' . htmlspecialchars($row['title']),
            'message' => htmlspecialchars(substr($row['message'], 0, 50)) . '...',
            'time' => date('Y-m-d H:i:s'), 
            'icon' => 'fa-bullhorn', 'color' => 'text-orange-600 bg-orange-100',
            'link' => '../view_announcements.php'
        ];
    }
}

usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 6); 

// TASKS & MEETINGS
$task_sql = "SELECT * FROM personal_taskboard WHERE user_id = $mgr_user_id ORDER BY id DESC LIMIT 5";
$tasks_result = mysqli_query($conn, $task_sql);
$pending_tasks_count = $conn->query("SELECT COUNT(*) as cnt FROM personal_taskboard WHERE user_id = $mgr_user_id AND status != 'completed'")->fetch_assoc()['cnt'] ?? 0;

$meet_result = mysqli_query($conn, "SELECT * FROM meetings WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 5");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - SmartHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        /* Strict boundary management for perfectly aligned cards */
        .card { 
            background: white; 
            border-radius: 1rem; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); 
            display: flex; 
            flex-direction: column; 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            overflow: hidden; 
        }
        .card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1; transform: translateY(-2px); }
        
        /* Card body must flex-grow but have min-height: 0 so internal scrolls work perfectly */
        .card-body { padding: 1.5rem; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;}
        
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        
        .stat-badge { font-size: 1.5rem; font-weight: 900; line-height: 1; color: #1e293b; }
        
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 80px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .meeting-row-wrapper { position: relative; margin-bottom: 1.5rem; }
        .meeting-dot { position: absolute; left: 76px; top: 10px; width: 10px; height: 10px; border-radius: 50%; z-index: 10; border: 2px solid white; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .meeting-flex-container { display: flex; align-items: flex-start; gap: 24px; }
        .meeting-time-label { width: 68px; text-align: right; flex-shrink: 0; font-weight: 700; font-size: 12px; color: #64748b; padding-top: 4px; }
        .meeting-content-box { background-color: #f8fafc; padding: 12px; border-radius: 0.75rem; border: 1px solid #f1f5f9; flex-grow: 1; }

        /* PERFECT 3-COLUMN FLEX GRID */
        .dashboard-container { 
            display: grid; 
            grid-template-columns: repeat(1, minmax(0, 1fr)); 
            gap: 1.5rem; 
            align-items: stretch; /* Forces equal heights */
        }
        @media (min-width: 1024px) {
            .dashboard-container {
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
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='task_manager.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">My Pending Tasks</p><p class="stat-badge text-orange-500"><?php echo $pending_tasks_count; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-lg"><i class="fa-solid fa-list-check"></i></div>
            </div>
        </div>

        <div class="dashboard-container mb-6">
            
            <div class="flex flex-col gap-6 h-full">
                <div class="h-full">
                    <?php include $path_to_root . 'attendance_card.php'; ?>
                </div>
            </div>

            <div class="flex flex-col gap-6 h-full">
                <div class="card shrink-0">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">My Attendance Stats</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center justify-between gap-6 shrink-0">
                            <div class="space-y-3.5 w-full pr-2">
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
                            </div>
                            <div class="relative flex-shrink-0 w-28 h-28 mx-auto mt-2">
                                <div id="attendanceChart" class="w-full h-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card flex-grow">
                    <div class="card-body flex flex-col justify-between">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-4 shrink-0">
                            <div class="bg-teal-50 p-4 rounded-xl text-center border border-teal-100 flex flex-col justify-center">
                                <p class="text-[9px] text-teal-700 font-bold uppercase mb-1">Earned</p>
                                <p class="text-2xl font-black text-teal-800"><?php echo $total_earned_leaves; ?></p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl text-center border border-blue-100 flex flex-col justify-center">
                                <p class="text-[9px] text-blue-700 font-bold uppercase mb-1">Taken</p>
                                <p class="text-2xl font-black text-blue-800"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-xl text-center border border-green-200 shadow-sm relative overflow-hidden flex flex-col justify-center">
                                <p class="text-[9px] text-green-800 font-bold uppercase relative z-10 mb-1">Left</p>
                                <p class="text-2xl font-black relative z-10 <?php echo $leaves_remaining < 0 ? 'text-rose-600' : 'text-green-800'; ?>">
                                    <?php echo $display_leaves_remaining; ?>
                                </p>
                            </div>
                        </div>
                        <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-lg text-center transition shadow-md shadow-teal-200/50 text-sm mt-auto shrink-0">
                            <i class="fa-solid fa-plus mr-1.5"></i> APPLY FOR LEAVE
                        </a>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-6 h-full">
                <div class="card overflow-hidden shadow-sm border-slate-200 shrink-0">
                    <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-4 flex items-center gap-4 relative">
                        <div class="relative shrink-0">
                            <img src="<?php echo $profile_img; ?>" class="w-14 h-14 rounded-full border-2 border-white shadow-lg object-cover bg-white">
                            <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <div class="min-w-0 text-white">
                            <h2 class="font-black text-lg truncate"><?php echo htmlspecialchars($mgr_name); ?></h2>
                            <p class="text-teal-100 text-[9px] font-bold uppercase tracking-widest truncate mt-0.5"><?php echo htmlspecialchars($user_role); ?></p>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-white border-b border-gray-100">
                         <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-phone text-teal-600 w-4 text-center text-xs"></i>
                                <p class="text-[11px] font-bold text-slate-700 truncate"><?php echo htmlspecialchars($mgr_phone); ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-envelope text-teal-600 w-4 text-center text-xs"></i>
                                <p class="text-[11px] font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($mgr_email); ?>">
                                    <?php echo htmlspecialchars($mgr_email); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-slate-50">
                        <div class="bg-white p-2 rounded-lg border border-slate-200 mb-3 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1"><i class="fa-solid fa-user-shield mr-1 text-purple-500"></i> Reporting Manager</p>
                            <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($higher_mgr_name); ?></p>
                            <?php if($higher_mgr_phone !== 'N/A' && $higher_mgr_phone !== ''): ?>
                                <p class="text-[9px] text-slate-500 font-medium mt-1"><i class="fa-solid fa-phone text-[8px] mr-1"></i> <?php echo htmlspecialchars($higher_mgr_phone); ?></p>
                            <?php endif; ?>
                            <?php if($higher_mgr_email !== 'N/A' && $higher_mgr_email !== ''): ?>
                                <p class="text-[9px] text-slate-500 font-medium mt-0.5 truncate" title="<?php echo htmlspecialchars($higher_mgr_email); ?>"><i class="fa-solid fa-envelope text-[8px] mr-1"></i> <?php echo htmlspecialchars($higher_mgr_email); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-white p-2 rounded-lg border border-slate-200 text-center">
                                <p class="text-[8px] text-gray-400 font-bold uppercase">Experience</p>
                                <p class="text-[11px] font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($mgr_exp); ?></p>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-slate-200 text-center">
                                <p class="text-[8px] text-gray-400 font-bold uppercase">Department</p>
                                <p class="text-[11px] font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($mgr_dept); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-blue-200 flex-grow">
                    <div class="card-body flex flex-col justify-between">
                        <div class="flex justify-between items-center border-b border-blue-100 pb-2 mb-4 shrink-0">
                            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-1.5"><i class="fa-solid fa-stopwatch text-blue-500 text-md"></i> Time Tracker</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-slate-50 px-1.5 py-0.5 rounded border border-gray-100">Today</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-4 shrink-0">
                            <div>
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-0.5 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 block"></span> Productive</p>
                                <p class="text-md font-black text-slate-800"><?php echo $str_prod; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-0.5 flex items-center justify-end gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 block"></span> Break</p>
                                <p class="text-md font-black text-slate-800"><?php echo $str_break; ?></p>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-0.5 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 block"></span> Overtime</p>
                                <p class="text-md font-black text-slate-800"><?php echo $str_ot; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-0.5">Total Hours</p>
                                <p class="text-md font-black text-blue-600"><?php echo $str_total; ?></p>
                            </div>
                        </div>

                        <div class="mt-auto shrink-0">
                            <div class="w-full bg-slate-100 rounded-full h-2 flex overflow-hidden mb-3 border border-slate-200/60 shadow-inner">
                                <div class="bg-emerald-500 h-full transition-all" style="width: <?php echo $pct_prod; ?>%" title="Productive"></div>
                                <div class="bg-amber-400 h-full transition-all" style="width: <?php echo $pct_break; ?>%" title="Break"></div>
                                <div class="bg-blue-500 h-full transition-all" style="width: <?php echo $pct_ot; ?>%" title="Overtime"></div>
                            </div>
                            <div class="pt-2 border-t border-gray-100">
                                <div class="flex items-center justify-between bg-orange-50 border border-orange-100 px-2 py-1.5 rounded-lg">
                                    <p class="text-[9px] text-orange-600 font-bold uppercase tracking-widest">OT This Month</p>
                                    <span class="text-sm font-black text-orange-600"><?php echo $overtime_this_month; ?> <span class="text-[10px] font-bold text-orange-500">Hrs</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div> 

        <div class="dashboard-container mb-6">
            
            <div class="card h-[400px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">My Updates</h3>
                        <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase border border-slate-200">Live Feed</span>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scroll pr-2 space-y-3 min-h-0">
                        <?php if(!empty($all_notifications)): ?>
                            <?php foreach($all_notifications as $notif): ?>
                            <div class="flex gap-3 items-start border border-gray-100 p-3 rounded-xl hover:bg-slate-50 transition shadow-sm">
                                <div class="w-8 h-8 rounded-full <?php echo $notif['color']; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                    <i class="fa-solid <?php echo $notif['icon']; ?>"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-[9px] text-gray-400 mt-1 shrink-0"><?php echo date("d M Y", strtotime($notif['time'])); ?></p>
                                    </div>
                                    <p class="text-[11px] text-gray-500 mt-1 line-clamp-2 leading-snug"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    
                                    <div class="mt-2 text-right">
                                        <?php if(isset($notif['type']) && $notif['type'] == 'ticket'): ?>
                                            <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-emerald-50 text-emerald-700 font-bold px-3 py-1.5 rounded-full border border-emerald-200 hover:bg-emerald-100 transition shadow-sm">
                                                <i class="fa-solid fa-check-double mr-1"></i> Mark as Viewed
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-white border border-gray-200 text-slate-600 font-bold px-3 py-1.5 rounded-full hover:bg-slate-100 transition shadow-sm">
                                                View Details <i class="fa-solid fa-arrow-right ml-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center py-10 text-slate-400 h-full">
                                <i class="fa-regular fa-bell-slash text-4xl mb-3 opacity-80"></i>
                                <p class='text-sm font-medium text-slate-500'>No recent updates.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card h-[400px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">Team Attendance</h3>
                        <a href="manager_employee.php" class="text-[9px] bg-slate-100 text-slate-600 font-bold px-2 py-1 rounded uppercase hover:bg-slate-200 transition">View List</a>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scroll pr-2 space-y-2 min-h-0">
                        <?php if(!empty($team_members)): ?>
                            <?php foreach($team_members as $member): 
                                $m_name = $member['full_name'] ?: 'Unknown';
                                $m_role = $member['designation'] ?: 'Employee';
                                $m_status = $member['today_status'] ?: 'Not Logged In';
                                
                                $m_img = "https://ui-avatars.com/api/?name=".urlencode($m_name)."&background=random";
                                if (!empty($member['profile_img']) && $member['profile_img'] !== 'default_user.png') {
                                    $m_img = str_starts_with($member['profile_img'], 'http') ? $member['profile_img'] : $path_to_root . 'assets/profiles/' . $member['profile_img'];
                                }

                                $status_color = 'bg-slate-100 text-slate-500';
                                if ($m_status == 'On Time') $status_color = 'bg-emerald-100 text-emerald-700';
                                elseif ($m_status == 'Late') $status_color = 'bg-orange-100 text-orange-700';
                                elseif ($m_status == 'Absent') $status_color = 'bg-rose-100 text-rose-700';
                                elseif ($m_status == 'WFH') $status_color = 'bg-blue-100 text-blue-700';
                            ?>
                            <div class="flex items-center justify-between p-2 border border-gray-50 rounded-lg hover:bg-slate-50 transition">
                                <div class="flex items-center gap-2">
                                    <img src="<?php echo $m_img; ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate" style="max-width: 120px;"><?php echo htmlspecialchars($m_name); ?></p>
                                        <p class="text-[9px] text-slate-500 font-medium truncate"><?php echo htmlspecialchars($m_role); ?></p>
                                    </div>
                                </div>
                                <span class="text-[8px] font-bold px-2 py-0.5 rounded uppercase tracking-wider <?php echo $status_color; ?>"><?php echo $m_status; ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center py-10 text-slate-400 h-full">
                                <i class="fa-solid fa-users-slash text-4xl mb-3 opacity-50"></i>
                                <p class="text-sm font-medium mt-2">No team members assigned.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card h-[400px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">TL Projects</h3>
                        <a href="manager_projects.php" class="text-[10px] bg-teal-50 text-teal-700 font-bold px-2 py-1 rounded uppercase hover:bg-teal-100 transition border border-teal-200">Manage</a>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scroll pr-2 space-y-4 min-h-0">
                        <?php if(!empty($active_projects)): ?>
                            <?php foreach($active_projects as $proj): 
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
                            <div class="flex flex-col items-center justify-center py-10 text-slate-400 h-full">
                                <i class="fa-solid fa-layer-group text-4xl mb-3 opacity-50"></i>
                                <p class="text-sm font-medium mt-2">No projects assigned.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div> 

        <div class="dashboard-container mb-10">
            
            <div class="card h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">My Personal Tasks</h3>
                        <a href="task_manager.php" class="text-[10px] bg-teal-50 text-teal-700 font-bold px-2 py-1 rounded uppercase hover:bg-teal-100 transition">Tasks Board</a>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scroll pr-2 space-y-3 min-h-0">
                        <?php if(mysqli_num_rows($tasks_result) > 0) {
                            while($task = mysqli_fetch_assoc($tasks_result)): 
                                $badge_bg = ($task['priority'] == 'High') ? 'bg-rose-100 text-rose-600' : (($task['priority'] == 'Medium') ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-600');
                                $icon_class = ($task['status'] == 'completed') ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-regular fa-circle text-teal-600';
                        ?>
                        <div class="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:bg-slate-50 transition shadow-sm">
                            <div class="flex items-center gap-3 min-w-0">
                                <i class="<?php echo $icon_class; ?> shrink-0"></i>
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-slate-700 block truncate max-w-[200px] lg:max-w-[300px]" title="<?php echo htmlspecialchars($task['title']); ?>"><?php echo htmlspecialchars($task['title']); ?></span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0 ml-2">
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded <?php echo $badge_bg; ?>"><?php echo $task['priority']; ?></span>
                            </div>
                        </div>
                        <?php endwhile; } else { ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-solid fa-clipboard-check text-4xl mb-3 opacity-50"></i>
                                <p class="text-sm mt-2">No personal tasks found.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="card h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                        <button class="text-[10px] text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold uppercase tracking-widest">Today</button>
                    </div>
                    <div class="meeting-timeline flex-1 overflow-y-auto custom-scroll pr-2 pt-2 space-y-6 min-h-0">
                        <?php if($meet_result && mysqli_num_rows($meet_result) > 0) {
                            while($meet = mysqli_fetch_assoc($meet_result)): 
                                $dot_color = ($meet['type_color']=='orange') ? 'bg-orange-500' : (($meet['type_color']=='teal') ? 'bg-teal-500' : 'bg-yellow-500');
                        ?>
                        <div class="meeting-row-wrapper">
                            <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                            <div class="meeting-flex-container">
                                <div class="meeting-time-label">
                                    <?php echo date("h:i A", strtotime($meet['meeting_time'])); ?>
                                </div>
                                <div class="meeting-content-box shadow-sm">
                                    <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></p>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1"><?php echo htmlspecialchars($meet['department']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; } else { ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-regular fa-calendar-xmark text-4xl mb-3 opacity-50"></i>
                                <p class="text-sm mt-2">No meetings scheduled today.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="card border-red-200 h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-4 border-b border-red-100 pb-3 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                            <i class="fa-solid fa-user-clock text-red-500"></i> Not Logged In
                        </h3>
                        <span class="bg-red-50 text-red-600 px-3 py-1 rounded-full text-[10px] font-bold border border-red-200 shadow-sm"><?php echo count($not_logged_in); ?> Absent</span>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scroll pr-2 grid grid-cols-1 gap-2.5 min-h-0">
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
                                <i class="fa-solid fa-check-double text-4xl mb-3 text-emerald-400 opacity-80"></i>
                                <p class="text-sm font-medium text-slate-600 mt-2">Excellent!</p>
                                <p class="text-xs mt-1">Everyone is present today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // Update late string directly in javascript for tooltip
            var lateTimeStr = "<?php echo $late_time_str; ?>";

            // Attendance Donut Chart
            var attOptions = {
                series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>],
                chart: { type: 'donut', width: 100, height: 100, sparkline: { enabled: true } },
                labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick'],
                colors: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                stroke: { width: 0 },
                tooltip: { 
                    fixed: { enabled: false }, 
                    x: { show: true }, 
                    marker: { show: true },
                    y: {
                        formatter: function(val, opts) {
                            if (opts.seriesIndex === 1) { // If "Late" is hovered
                                return val + " Days (Total: " + lateTimeStr + ")";
                            }
                            return val + " Days";
                        }
                    }
                }
            };
            var attendanceChartEl = document.querySelector("#attendanceChart");
            if (attendanceChartEl) {
                new ApexCharts(attendanceChartEl, attOptions).render();
            }
        });
    </script>
</body>
</html>