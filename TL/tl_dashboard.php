<?php
// TL/tl_dashboard.php

// 1. SESSION & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION & PATHS
$db_path = __DIR__ . '/../include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
    $path_to_root = '../';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    require_once '../include/db_connect.php'; 
    $path_to_root = '';
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
}

date_default_timezone_set('Asia/Kolkata');
$tl_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$user_role = $_SESSION['role'] ?? 'Team Lead';
$tl_username = $_SESSION['username'] ?? ''; 

// =========================================================================
// ACTION: MARK TICKET AS VIEWED
// =========================================================================
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS user_read_status TINYINT(1) DEFAULT 0");

if (isset($_GET['dismiss_ticket'])) {
    $dismiss_id = intval($_GET['dismiss_ticket']);
    $dismiss_query = "UPDATE tickets SET user_read_status = 1 WHERE id = ? AND user_id = ?";
    $stmt_dismiss = mysqli_prepare($conn, $dismiss_query);
    mysqli_stmt_bind_param($stmt_dismiss, "ii", $dismiss_id, $tl_user_id);
    mysqli_stmt_execute($stmt_dismiss);
    header("Location: tl_dashboard.php");
    exit();
}

// =========================================================================
// TL PROFILE & SHIFT TIMINGS & MANAGER DEETS
// =========================================================================
$tl_name = "Team Leader"; $tl_phone = "Not Set"; $tl_email = "Not Set"; $tl_dept = "General"; $tl_exp = "Fresher"; 
$tl_emergency_contacts = '[]';
$shift_timings = '09:00 AM - 06:00 PM';
$joining_date = "Not Set";

$tl_manager_id = 0;
$tl_manager_name = "Not Assigned";
$tl_manager_phone = "N/A";
$tl_manager_email = "N/A";

$profile_query = "SELECT u.username, u.email, u.role, p.* FROM users u LEFT JOIN employee_profiles p ON u.id = p.user_id WHERE u.id = ?";
$stmt_p = $conn->prepare($profile_query);
$stmt_p->bind_param("i", $tl_user_id);
$stmt_p->execute();
if ($row = $stmt_p->get_result()->fetch_assoc()) {
    $tl_username = $row['username'];
    $tl_name = $row['full_name'] ?? $row['username'];
    $tl_phone = $row['phone'] ?? 'Not Set';
    $tl_email = !empty($row['email']) ? $row['email'] : $row['username'];
    $tl_dept = $row['department'] ?? 'General';
    $tl_exp = $row['experience_label'] ?? 'Fresher';
    $tl_emergency_contacts = $row['emergency_contacts'] ?? '[]';
    $shift_timings = $row['shift_timings'] ?? $shift_timings;
    
    $joining_date = $row['joining_date'] ?? null;
    $joining_date_display = $joining_date ? date("d M Y", strtotime($joining_date)) : "Not Set";
    
    $tl_manager_id = intval($row['reporting_to'] ?? $row['manager_id'] ?? 0);

    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($tl_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : $path_to_root . 'assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

if ($tl_manager_id > 0) {
    $mgr_res = $conn->query("SELECT ep.full_name, ep.phone, u.email FROM employee_profiles ep JOIN users u ON ep.user_id = u.id WHERE ep.user_id = $tl_manager_id")->fetch_assoc();
    if($mgr_res) {
        $tl_manager_name = $mgr_res['full_name'];
        $tl_manager_phone = !empty($mgr_res['phone']) ? $mgr_res['phone'] : 'N/A';
        $tl_manager_email = !empty($mgr_res['email']) ? $mgr_res['email'] : 'N/A';
    }
}

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$regular_shift_hours = 9;

// =========================================================================
// ADVANCED TIME TRACKER (READ-ONLY SUMMARY FOR COLUMN 3)
// =========================================================================
$total_seconds_today = 0; $break_seconds_today = 0; $productive_seconds_today = 0; $overtime_seconds_today = 0;
$display_break_seconds = 0; $today_punch_in = null; $attendance_record_today = null;
$is_on_break = false; $display_punch_in = "--:--"; $delay_text = ""; $delay_class = "";
$total_hours_today = "00:00:00"; $break_time_str = "00:00:00";

$today_sql = "SELECT id, punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND date = ?";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("is", $tl_user_id, $today);
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
$ot_stmt->bind_param("iii", $tl_user_id, $current_month, $current_year);
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
// TL'S OWN MONTHLY ATTENDANCE STATS (EXACT AUDIT PAGE MATCH)
// =========================================================================
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$total_late_seconds = 0;

$start_date_stat = date('Y-m-01'); // STRICTLY 1st of the month
$end_date_stat = $today;

// 1. Fetch DB Records for the month
$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND date >= ? AND date <= ?";
$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iss", $tl_user_id, $start_date_stat, $end_date_stat);
$stat_stmt->execute();
$stat_res = $stat_stmt->get_result();

$month_att_db = [];
while ($stat_row = $stat_res->fetch_assoc()) {
    $month_att_db[$stat_row['date']] = $stat_row;
}
$stat_stmt->close();

// 2. Fetch Approved Leaves safely
$stmt_all_leaves = $conn->prepare("SELECT start_date, end_date, leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND start_date <= ?");
$stmt_all_leaves->bind_param("is", $tl_user_id, $today);
$stmt_all_leaves->execute();
$res_all_leaves = $stmt_all_leaves->get_result();
$all_app_leaves = [];
if ($res_all_leaves) {
    while ($l_row = $res_all_leaves->fetch_assoc()) {
        $curr_l = new DateTime($l_row['start_date']);
        $end_l = new DateTime($l_row['end_date']);
        while ($curr_l <= $end_l) {
            $all_app_leaves[$curr_l->format('Y-m-d')] = $l_row['leave_type'];
            $curr_l->modify('+1 day');
        }
    }
}
$stmt_all_leaves->close();

// 3. Exact Date Loop Engine - NO JOIN DATE OVERRIDE
$iter_dt = new DateTime($start_date_stat);
$today_dt = new DateTime($today);

while ($iter_dt <= $today_dt) {
    $d_str = $iter_dt->format('Y-m-d');
    $dow = $iter_dt->format('N'); // 1 (Mon) to 7 (Sun)
    $is_today = ($d_str === $today);
    
    if (isset($month_att_db[$d_str])) {
        // Present in DB
        $r = $month_att_db[$d_str];
        $st = $r['status'];
        $is_absent_db = (stripos($st, 'Absent') !== false && empty($r['punch_in']));

        if ($is_absent_db) {
            $stats_absent++;
        } else {
            if (stripos($st, 'WFH') !== false) { 
                $stats_wfh++; 
            } elseif (stripos($st, 'Sick') !== false && !isset($all_app_leaves[$d_str])) { 
                $stats_sick++; 
            }

            if (!empty($r['punch_in'])) {
                $expected_start_ts = strtotime($r['date'] . ' ' . $shift_start_str);
                $actual_start_ts = strtotime($r['punch_in']);
                if ($actual_start_ts > ($expected_start_ts + 60)) { 
                    $stats_late++; 
                    $total_late_seconds += ($actual_start_ts - $expected_start_ts);
                } else { 
                    if (stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                        $stats_ontime++; 
                    }
                }
            } else {
                // No punch in but not marked absent in DB
                if (!$is_today && stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                    $stats_absent++;
                }
            }
        }
    } else {
        // NOT in DB - check if Sunday or Leave
        if (!$is_today) {
            if ($dow == 7) {
                // Sunday - do nothing
            } elseif (isset($all_app_leaves[$d_str])) {
                // On Approved Leave
                if (stripos($all_app_leaves[$d_str], 'Sick') !== false) {
                    $stats_sick++;
                }
            } else {
                // Working day, not in DB, not on leave => ABSENT
                $stats_absent++;
            }
        } else {
             // TODAY logic - if not punched in and not Sunday/Leave, it is considered absent today
             if ($dow != 7 && !isset($all_app_leaves[$d_str])) {
                 $stats_absent++; 
             }
        }
    }
    $iter_dt->modify('+1 day');
}

$late_hours = floor($total_late_seconds / 3600);
$late_minutes = floor(($total_late_seconds % 3600) / 60);
$late_time_str = $late_hours . 'h ' . $late_minutes . 'm';

// Leaves Taken specifically for UI display text
$current_month_leaves = 0;
foreach ($all_app_leaves as $ld => $ltype) {
    if (strpos($ld, date('Y-m-')) === 0) {
        $current_month_leaves++;
    }
}


// =========================================================================
// TL'S OWN LEAVE CARRY-FORWARD LOGIC
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
$leave_stmt->bind_param("i", $tl_user_id);
$leave_stmt->execute();
$leaves_taken = floatval($leave_stmt->get_result()->fetch_assoc()['taken'] ?? 0);
$leaves_remaining = $total_earned_leaves - $leaves_taken;
$display_leaves_remaining = ($leaves_remaining < 0) ? 0 : $leaves_remaining; 
$lop_days = ($leaves_remaining < 0) ? abs($leaves_remaining) : 0;


// =========================================================================
// FETCH MY TEAM
// =========================================================================
$team_members = [];
$team_q = "SELECT ep.user_id, ep.full_name, ep.designation, ep.profile_img, a.status as today_status 
           FROM employee_profiles ep 
           LEFT JOIN attendance a ON ep.user_id = a.user_id AND a.date = ? 
           WHERE ep.reporting_to = ? LIMIT 10";
$stmt_team = $conn->prepare($team_q);
if ($stmt_team) {
    $stmt_team->bind_param("si", $today, $tl_user_id);
    $stmt_team->execute();
    $res_team_data = $stmt_team->get_result();
    while ($r = $res_team_data->fetch_assoc()) {
        $team_members[] = $r;
    }
    $stmt_team->close();
}

$res_team = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE reporting_to = $tl_user_id")->fetch_assoc();
$total_team = $res_team['total'] ?? 0;
$res_p = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE ep.reporting_to = $tl_user_id AND a.date = '$today' AND (a.status='On Time' OR a.status='WFH' OR a.status='Late')")->fetch_assoc();
$team_present = $res_p['cnt'] ?? 0;
$res_l = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE ep.reporting_to = $tl_user_id AND a.date = '$today' AND a.status='Late'")->fetch_assoc();
$team_late = $res_l['cnt'] ?? 0;
$team_absent = max(0, $total_team - $team_present);
$team_att_pct = ($total_team > 0) ? round(($team_present / $total_team) * 100) : 0;


// =========================================================================
// PROJECTS DYNAMIC PROGRESS & TASKS
// =========================================================================
$active_projects = [];
$proj_q = "SELECT p.id, p.project_name, p.deadline, p.status,
                 (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id) as total_tasks,
                 (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id AND pt.status = 'Completed') as completed_tasks
           FROM projects p 
           WHERE p.leader_id = ? AND p.status != 'Completed' 
           ORDER BY p.id DESC LIMIT 4";
$stmt_proj = $conn->prepare($proj_q);
if ($stmt_proj) {
    $stmt_proj->bind_param("i", $tl_user_id);
    $stmt_proj->execute();
    $res_proj = $stmt_proj->get_result();
    while ($p_row = $res_proj->fetch_assoc()) { 
        $active_projects[] = $p_row; 
    }
    $stmt_proj->close();
}

$task_sql = "SELECT * FROM personal_taskboard WHERE user_id = ? ORDER BY id DESC LIMIT 15";
$task_stmt = mysqli_prepare($conn, $task_sql);
mysqli_stmt_bind_param($task_stmt, "i", $tl_user_id);
mysqli_stmt_execute($task_stmt);
$tasks_result = mysqli_stmt_get_result($task_stmt);

$high_tasks = 0; $med_tasks = 0; $low_tasks = 0;
$tp_q = "SELECT pt.priority, COUNT(*) as cnt FROM project_tasks pt JOIN projects p ON pt.project_id = p.id WHERE p.leader_id = ? AND pt.status != 'Completed' GROUP BY pt.priority";
$stmt_tp = $conn->prepare($tp_q);
if ($stmt_tp) {
    $stmt_tp->bind_param("i", $tl_user_id);
    $stmt_tp->execute();
    $res_tp = $stmt_tp->get_result();
    while ($pr_row = $res_tp->fetch_assoc()) {
        if ($pr_row['priority'] == 'High') $high_tasks = $pr_row['cnt'];
        if ($pr_row['priority'] == 'Medium') $med_tasks = $pr_row['cnt'];
        if ($pr_row['priority'] == 'Low') $low_tasks = $pr_row['cnt'];
    }
    $stmt_tp->close();
}

// =========================================================================
// UNIFIED NOTIFICATIONS & MEETINGS INTEGRATION
// =========================================================================
$all_notifications = [];
$all_today_meetings = []; 

// 1. Fetch old Calendar Meetings if table exists (Now fetches >= TODAY)
$check_meetings = $conn->query("SHOW TABLES LIKE 'calendar_meetings'");
if ($check_meetings && $check_meetings->num_rows > 0) {
    // Push into Live Feed Array
    $q_meet_feed = "SELECT cm.id, cm.title, cm.meet_date, cm.meet_time, cm.meet_link, cm.created_at, COALESCE(ep.full_name, 'A team member') as host_name 
                    FROM calendar_meetings cm 
                    JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id 
                    LEFT JOIN employee_profiles ep ON cm.created_by = ep.user_id 
                    WHERE cmp.user_id = $tl_user_id 
                    ORDER BY cm.created_at DESC LIMIT 4";
    $r_meet_feed = mysqli_query($conn, $q_meet_feed);
    if($r_meet_feed) {
        while($row = mysqli_fetch_assoc($r_meet_feed)) {
            $meet_datetime = date('d M Y', strtotime($row['meet_date'])) . ' at ' . date('h:i A', strtotime($row['meet_time']));
            $all_notifications[] = [
                'type' => 'meeting',
                'title' => 'Meeting: ' . htmlspecialchars($row['title']),
                'message' => htmlspecialchars($row['host_name']) . ' invited you to a meeting on ' . $meet_datetime . '.',
                'time' => $row['created_at'] ?? date('Y-m-d H:i:s'), 
                'icon' => 'fa-video', 
                'color' => 'text-indigo-600 bg-indigo-100',
                'link' => $path_to_root . 'team_chat.php' 
            ];
        }
    }

    // Fetch list for Meetings Widget (UPDATED to >= CURDATE to show upcoming ones)
    $q_today_meets = "SELECT cm.id, cm.title, cm.meet_date, cm.meet_time, cm.meet_link, ep.department 
                      FROM calendar_meetings cm 
                      JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id 
                      LEFT JOIN employee_profiles ep ON cm.created_by = ep.user_id
                      WHERE cmp.user_id = $tl_user_id AND cm.meet_date >= CURDATE()";
    $r_today = mysqli_query($conn, $q_today_meets);
    if($r_today) {
        while($row = mysqli_fetch_assoc($r_today)) {
            $all_today_meetings[] = $row;
        }
    }
}

// 2. Fetch Announcements Table Meetings
$q_ann_meets = "SELECT a.id, a.title, a.publish_date as meet_date, '' as meet_link, u.department, a.message, a.created_at, COALESCE(u.username, 'Manager') as host_name 
                FROM announcements a 
                LEFT JOIN users u ON a.created_by = u.id 
                WHERE a.category = 'Meeting' AND a.is_archived = 0 
                AND (a.target_audience = 'All' 
                     OR a.target_audience = 'All Employees' 
                     OR a.target_audience LIKE '%" . $conn->real_escape_string($tl_username) . "%' 
                     OR a.message LIKE '%" . $conn->real_escape_string($tl_username) . "%'
                     OR a.message LIKE '%" . $conn->real_escape_string($tl_name) . "%')";
$r_ann_meets = mysqli_query($conn, $q_ann_meets);
if($r_ann_meets) {
    while($row = mysqli_fetch_assoc($r_ann_meets)) {
        // Extract time from message: "Time: 10:30 \nAgenda..."
        $time = "00:00:00"; 
        if (preg_match('/Time:\s*([^\n]+)/', $row['message'], $matches)) {
            $time = trim($matches[1]);
        }
        $row['meet_time'] = $time;
        
        // Push to My Updates (Live Feed)
        $all_notifications[] = [
            'type' => 'meeting_announcement',
            'title' => 'Meeting Scheduled: ' . htmlspecialchars($row['title']),
            'message' => 'Meeting scheduled by ' . htmlspecialchars($row['host_name']),
            'time' => $row['created_at'] ?? ($row['meet_date'] . ' 00:00:00'), 
            'icon' => 'fa-handshake', 
            'color' => 'text-indigo-600 bg-indigo-100',
            'link' => '../view_announcements.php' 
        ];

        // UPDATED: Push to array if meeting is TODAY OR IN THE FUTURE
        if ($row['meet_date'] >= $today) {
            $all_today_meetings[] = $row;
        }
    }
}

// Sort combined meetings by Date and Time so the closest meeting shows first
usort($all_today_meetings, function($a, $b) {
    $timeA = strtotime($a['meet_date'] . ' ' . $a['meet_time']);
    $timeB = strtotime($b['meet_date'] . ' ' . $b['meet_time']);
    return $timeA - $timeB;
});


// Other normal notifications
$q_tickets = "SELECT id, ticket_code, subject FROM tickets WHERE user_id = $tl_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY id DESC LIMIT 3";
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

$q_leaves = "SELECT leave_type, status FROM leave_requests WHERE user_id = $tl_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 3";
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

$q_announcements = "SELECT id, title, message FROM announcements WHERE is_archived = 0 AND (target_audience = 'All' OR target_audience = '$user_role' OR target_audience = 'All Employees') AND category != 'Meeting' ORDER BY id DESC LIMIT 5"; 
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

session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TL Dashboard - SmartHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        .card { 
            background: white; 
            border-radius: 1rem; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); 
            transition: all 0.3s ease; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
        }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1;}
        
        .card-body { padding: 1.25rem; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;} 
        
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 75px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .meeting-row-wrapper { position: relative; margin-bottom: 1.5rem; }
        .meeting-dot { position: absolute; left: 70px; top: 10px; width: 12px; height: 12px; border-radius: 50%; z-index: 10; border: 2px solid white; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .meeting-flex-container { display: flex; align-items: flex-start; gap: 24px; }
        .meeting-time-label { width: 68px; text-align: right; flex-shrink: 0; font-weight: 700; font-size: 12px; color: #64748b; padding-top: 4px; }
        .meeting-content-box { background-color: #f8fafc; padding: 12px; border-radius: 0.75rem; border: 1px solid #f1f5f9; flex-grow: 1; transition: all 0.2s;}
        .meeting-content-box:hover { background-color: white; border-color: #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);}
        
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(1, 1fr); 
            gap: 1.5rem; 
            align-items: start; 
        }
        
        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        
        #mainContent { margin-left: 90px; width: calc(100% - 90px); transition: all 0.3s; padding: 24px; box-sizing: border-box; max-width: 1600px; margin-right: auto;}
        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px;}
        }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>
    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">TL Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Welcome back, <b class="text-slate-700"><?php echo htmlspecialchars($tl_name); ?></b></p>
            </div>
            <div class="flex gap-3">
                <div class="bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>
        </div>

        <div class="dashboard-grid mb-10">

            <div class="flex flex-col gap-6">
                
                <?php if(file_exists($path_to_root . 'attendance_card.php')) include $path_to_root . 'attendance_card.php'; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Team Attendance</h3>
                            <a href="attendance_tl.php" class="text-[10px] text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded uppercase hover:bg-teal-100 transition">View All</a>
                        </div>
                        <div class="flex items-center justify-between mt-2 shrink-0">
                            <div class="text-center">
                                <span class="text-3xl font-black text-slate-800 block"><?php echo $team_present; ?></span>
                                <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Present</span>
                            </div>
                            <div class="text-center">
                                <span class="text-3xl font-black text-slate-800 block"><?php echo $team_late; ?></span>
                                <span class="text-[10px] font-bold text-orange-500 uppercase tracking-widest">Late</span>
                            </div>
                            <div class="text-center">
                                <span class="text-3xl font-black text-slate-800 block"><?php echo $team_absent; ?></span>
                                <span class="text-[10px] font-bold text-red-500 uppercase tracking-widest">Absent</span>
                            </div>
                        </div>
                        <div class="mt-4 shrink-0">
                            <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                <span>Team Strength: <?php echo $total_team; ?></span>
                                <span><?php echo $team_att_pct; ?>%</span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-teal-500 rounded-full" style="width: <?php echo $team_att_pct; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-t border-dashed border-gray-200 flex flex-col min-h-0 flex-grow">
                            <div class="flex justify-between items-center mb-2 shrink-0">
                                <h3 class="font-bold text-slate-800 text-sm">My Team</h3>
                                <a href="team_member.php" class="text-[9px] bg-slate-100 text-slate-600 font-bold px-2 py-1 rounded uppercase hover:bg-slate-200 transition">View List</a>
                            </div>
                            <div class="space-y-2 overflow-y-auto custom-scroll pr-2 max-h-[250px]">
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
                                                <p class="text-xs font-bold text-slate-800 truncate" style="max-width: 100px;"><?php echo htmlspecialchars($m_name); ?></p>
                                                <p class="text-[9px] text-slate-500 font-medium truncate"><?php echo htmlspecialchars($m_role); ?></p>
                                            </div>
                                        </div>
                                        <span class="text-[8px] font-bold px-2 py-0.5 rounded uppercase tracking-wider <?php echo $status_color; ?>"><?php echo $m_status; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-slate-400">
                                        <p class="text-xs font-medium">No team members assigned.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card h-[310px]">
                    <div class="card-body flex flex-col min-h-0">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">My Updates</h3>
                            <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase border border-slate-200">Live Feed</span>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scroll pr-2 mt-3 space-y-3">
                            <?php if(!empty($all_notifications)): ?>
                                <?php foreach($all_notifications as $notif): ?>
                                <div class="flex gap-3 items-start border border-gray-100 p-3 rounded-xl hover:bg-slate-50 transition shadow-sm">
                                    <div class="w-8 h-8 rounded-full <?php echo $notif['color']; ?> flex items-center justify-center font-bold text-[10px] shrink-0">
                                        <i class="fa-solid <?php echo $notif['icon']; ?>"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex justify-between items-start">
                                            <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                            <p class="text-[8px] text-gray-400 mt-1 shrink-0"><?php echo date("d M Y", strtotime($notif['time'])); ?></p>
                                        </div>
                                        <p class="text-[10px] text-gray-500 mt-1 line-clamp-2 leading-snug"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        
                                        <div class="mt-2 text-right">
                                            <?php if(isset($notif['type']) && $notif['type'] == 'ticket'): ?>
                                                <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-emerald-50 text-emerald-700 font-bold px-2.5 py-1 rounded-full border border-emerald-200 hover:bg-emerald-100 transition shadow-sm">
                                                    <i class="fa-solid fa-check-double mr-1"></i> Mark as Viewed
                                                </a>
                                            <?php elseif(isset($notif['type']) && ($notif['type'] == 'meeting' || $notif['type'] == 'meeting_announcement')): ?>
                                                <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold px-2.5 py-1 rounded-full hover:bg-indigo-100 transition shadow-sm">
                                                    <i class="fa-solid fa-video mr-1"></i> View Details
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-white border border-gray-200 text-slate-600 font-bold px-2.5 py-1 rounded-full hover:bg-slate-100 transition shadow-sm">
                                                    View Details <i class="fa-solid fa-arrow-right ml-1"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-slate-400">
                                    <i class="fa-regular fa-bell-slash text-3xl mb-2 opacity-80"></i>
                                    <p class='text-xs font-medium text-slate-500'>No recent updates.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6">
                
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">My Attendance Stats</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center gap-4 shrink-0">
                            <div class="space-y-3 w-full pr-2">
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
                            <div class="relative flex-shrink-0 w-28 h-28 mx-auto">
                                <div id="attendanceChart" class="w-full h-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body flex flex-col gap-3">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 shrink-0">
                            <div class="bg-teal-50 p-3 rounded-xl text-center border border-teal-100">
                                <p class="text-[9px] text-teal-700 font-bold uppercase mb-1">Earned</p>
                                <p class="text-xl font-black text-teal-800"><?php echo $total_earned_leaves; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center border border-blue-100">
                                <p class="text-[9px] text-blue-700 font-bold uppercase mb-1">Taken</p>
                                <p class="text-xl font-black text-blue-800"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center border border-green-200 shadow-sm relative overflow-hidden">
                                <p class="text-[9px] text-green-800 font-bold uppercase relative z-10 mb-1">Left</p>
                                <p class="text-xl font-black relative z-10 <?php echo $leaves_remaining < 0 ? 'text-rose-600' : 'text-green-800'; ?>">
                                    <?php echo $display_leaves_remaining; ?>
                                </p>
                                <?php if($leaves_remaining < 0): ?>
                                    <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-rose-500"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if($leaves_remaining < 0): ?>
                            <div class="bg-rose-50 border border-rose-200 rounded-lg p-2 flex items-center gap-3 shrink-0">
                                <div class="w-6 h-6 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 flex-shrink-0 text-[10px]"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <p class="text-[11px] font-semibold text-rose-700 leading-tight">Limit exceeded! <b><?php echo $lop_days; ?> Days</b> considered as LOP.</p>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-1.5 mt-2 shrink-0">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Recent Leave Policy</p>
                            <div class="flex items-center justify-between p-1.5 bg-slate-50 rounded-lg border border-slate-100">
                                <span class="text-[10px] font-bold text-slate-600">Monthly Accrual</span>
                                <span class="text-[10px] font-black text-teal-600">+2.0 Days</span>
                            </div>
                            <div class="flex items-center justify-between p-1.5 bg-slate-50 rounded-lg border border-slate-100">
                                <span class="text-[10px] font-bold text-slate-600">Sick Leave Cap</span>
                                <span class="text-[10px] font-black text-slate-700">12 Days/Year</span>
                            </div>
                        </div>

                        <div class="mt-auto shrink-0">
                            <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2 rounded-lg text-center transition shadow-sm shadow-teal-200/50 text-xs">
                                <i class="fa-solid fa-plus mr-1"></i> APPLY FOR LEAVE
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-slate-800 text-lg mb-1 shrink-0">Project Tasks Priority</h3>
                        <div id="priorityDonutChart" class="flex justify-center my-2 shrink-0"></div>
                        <div class="flex justify-around mt-0 border-t pt-2 border-slate-100 shrink-0">
                            <div class="text-center"><span class="block text-red-500 font-black text-lg"><?php echo $high_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">High</span></div>
                            <div class="text-center"><span class="block text-amber-500 font-black text-lg"><?php echo $med_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">Medium</span></div>
                            <div class="text-center"><span class="block text-emerald-500 font-black text-lg"><?php echo $low_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">Low</span></div>
                        </div>
                    </div>
                </div>

                <div class="card h-[420px]">
                    <div class="card-body flex flex-col min-h-0">
                        <div class="flex justify-between items-center mb-3 border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">My Personal Tasks</h3>
                            <a href="task_tl.php" class="text-[9px] bg-teal-50 text-teal-700 font-bold px-2 py-1 rounded uppercase hover:bg-teal-100 transition">Tasks Board</a>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scroll pr-2 mt-1 space-y-2">
                            <?php if(mysqli_num_rows($tasks_result) > 0) {
                                while($task = mysqli_fetch_assoc($tasks_result)): 
                                    $badge_bg = ($task['priority'] == 'High') ? 'bg-rose-100 text-rose-600' : (($task['priority'] == 'Medium') ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-600');
                                    $icon_class = ($task['status'] == 'completed') ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-regular fa-circle text-teal-600';
                            ?>
                            <div class="flex items-center justify-between p-2.5 border border-gray-100 rounded-lg hover:bg-slate-50 transition shadow-sm">
                                <div class="flex items-center gap-2.5">
                                    <i class="<?php echo $icon_class; ?> text-sm"></i>
                                    <div>
                                        <span class="text-[13px] font-medium text-slate-700 block truncate max-w-[200px] lg:max-w-[300px]" title="<?php echo htmlspecialchars($task['title']); ?>"><?php echo htmlspecialchars($task['title']); ?></span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded <?php echo $badge_bg; ?>"><?php echo $task['priority']; ?></span>
                                </div>
                            </div>
                            <?php endwhile; } else { ?>
                                <div class="text-center py-8 text-slate-400">
                                    <i class="fa-solid fa-clipboard-check text-3xl mb-2 opacity-50"></i>
                                    <p class="text-xs font-medium">No personal tasks found.</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6">
                
                <div class="card overflow-hidden shadow-sm border-slate-200">
                    <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-6 flex items-center gap-4 relative shrink-0">
                        <div class="relative shrink-0">
                            <img src="<?php echo $profile_img; ?>" class="w-16 h-16 rounded-full border-2 border-white shadow-lg object-cover bg-white">
                            <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <div class="min-w-0 text-white">
                            <h2 class="font-black text-xl truncate tracking-tight"><?php echo htmlspecialchars($tl_name); ?></h2>
                            <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest truncate mt-0.5 opacity-90"><?php echo htmlspecialchars($user_role); ?></p>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-white border-b border-gray-100 shrink-0">
                         <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-phone text-teal-600 text-xs"></i>
                                </div>
                                <p class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($tl_phone); ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-envelope text-teal-600 text-xs"></i>
                                </div>
                                <p class="text-xs font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($tl_email); ?>">
                                    <?php echo htmlspecialchars($tl_email); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-slate-50 flex-grow flex flex-col justify-between">
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-shield text-purple-500"></i> Reporting Manager
                            </p>
                            <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-200 shadow-sm mb-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-slate-800 truncate"><?php echo htmlspecialchars($tl_manager_name); ?></p>
                                    <p class="text-[10px] text-slate-500 font-medium mt-0.5 truncate">
                                        <i class="fa-solid fa-envelope text-[9px] mr-1"></i> <?php echo htmlspecialchars($tl_manager_email); ?>
                                    </p>
                                </div>
                                <a href="tel:<?php echo $tl_manager_phone; ?>" class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-teal-600 hover:text-white transition-colors flex-shrink-0">
                                    <i class="fa-solid fa-phone text-[10px]"></i>
                                </a>
                            </div>

                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                                    <p class="text-[8px] text-gray-400 font-black uppercase tracking-tighter">Experience</p>
                                    <p class="text-[11px] font-black text-slate-700 mt-0.5"><?php echo htmlspecialchars($tl_exp); ?></p>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                                    <p class="text-[8px] text-gray-400 font-black uppercase tracking-tighter">Department</p>
                                    <p class="text-[11px] font-black text-slate-700 mt-0.5"><?php echo htmlspecialchars($tl_dept); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-sm">
                            <p class="text-[8px] text-gray-400 font-black uppercase tracking-widest mb-1">Company Journey</p>
                            <div class="flex justify-between items-center">
                                <p class="text-[11px] font-black text-slate-700">Joined On</p>
                                <span class="text-[9px] font-bold text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded-lg"><?php echo $joining_date_display; ?></span>
                            </div>
                        </div>

                        <?php
                        $emergency = json_decode($tl_emergency_contacts, true);
                        if (!empty($emergency)): 
                            $primary = $emergency[0]; ?>
                            <div class="p-2.5 bg-rose-50 rounded-xl border border-rose-100 flex items-center justify-between mt-4 shadow-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-lg bg-rose-100 flex items-center justify-center text-rose-500 shadow-inner">
                                        <i class="fa-solid fa-heart-pulse text-[10px]"></i>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-black text-rose-700 uppercase block tracking-tight">Emergency</span>
                                        <p class="text-[11px] font-black text-slate-800"><?php echo htmlspecialchars($primary['name']); ?></p>
                                    </div>
                                </div>
                                <p class="text-[9px] font-black text-rose-600"><?php echo htmlspecialchars($primary['phone']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-blue-200">
                    <div class="card-body flex flex-col">
                        <div class="flex justify-between items-center mb-4 border-b border-blue-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-1.5"><i class="fa-solid fa-stopwatch text-blue-500 text-md"></i> Time Tracker</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-slate-50 px-1.5 py-0.5 rounded border border-gray-100">Today</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 mb-3 shrink-0">
                            <div class="bg-white p-2 rounded-lg border border-slate-100">
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-0.5 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 block"></span> Productive</p>
                                <p class="text-sm font-black text-slate-800"><?php echo $str_prod; ?></p>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-slate-100 text-right">
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest mb-0.5 flex items-center justify-end gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 block"></span> Break</p>
                                <p class="text-sm font-black text-slate-800"><?php echo $str_break; ?></p>
                            </div>
                        </div>

                        <div class="flex-grow space-y-2 mb-3 shrink-0">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Work Log</p>
                            <div class="relative pl-3 border-l-2 border-slate-100 space-y-3">
                                <div class="relative">
                                    <div class="absolute -left-[17px] top-1 w-2 h-2 rounded-full bg-emerald-500 border border-white"></div>
                                    <p class="text-[10px] font-bold text-slate-700">Punch In</p>
                                    <p class="text-[8px] text-slate-400"><?php echo $display_punch_in; ?></p>
                                </div>
                                <div class="relative">
                                    <div class="absolute -left-[17px] top-1 w-2 h-2 rounded-full bg-blue-500 border border-white"></div>
                                    <p class="text-[10px] font-bold text-slate-700">Current Session</p>
                                    <p class="text-[8px] text-slate-400">Ongoing Activity</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto shrink-0">
                            <div class="w-full bg-slate-100 rounded-full h-1.5 flex overflow-hidden mb-2 border border-slate-200/60 shadow-inner">
                                <div class="bg-emerald-500 h-full transition-all" style="width: <?php echo $pct_prod; ?>%" title="Productive"></div>
                                <div class="bg-amber-400 h-full transition-all" style="width: <?php echo $pct_break; ?>%" title="Break"></div>
                                <div class="bg-blue-500 h-full transition-all" style="width: <?php echo $pct_ot; ?>%" title="Overtime"></div>
                            </div>
                            
                            <div class="pt-1 border-t border-gray-100">
                                <div class="flex items-center justify-between bg-orange-50 border border-orange-100 px-2.5 py-1.5 rounded-lg mt-1">
                                    <p class="text-[8px] text-orange-600 font-bold uppercase tracking-widest">OT This Month</p>
                                    <span class="text-xs font-black text-orange-600"><?php echo $overtime_this_month; ?> <span class="text-[9px] font-bold text-orange-500">Hrs</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card h-[420px]">
                    <div class="card-body flex flex-col min-h-0">
                        <div class="flex justify-between items-center mb-3 border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                            <button class="text-[9px] text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold uppercase tracking-widest">Upcoming</button>
                        </div>
                        <div class="meeting-timeline flex-1 overflow-y-auto custom-scroll pr-2 mt-2 pt-1 space-y-4">
                            <?php if(!empty($all_today_meetings)) {
                                $color_palette = ['bg-teal-500', 'bg-indigo-500', 'bg-rose-500', 'bg-orange-500'];
                                $c_idx = 0;
                                foreach($all_today_meetings as $meet): 
                                    $dot_color = $color_palette[$c_idx % 4];
                                    $c_idx++;
                            ?>
                            <div class="meeting-row-wrapper">
                                <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                                <div class="meeting-flex-container gap-4">
                                    <div class="meeting-time-label">
                                        <span class="block text-[10px] text-teal-600 mb-0.5"><?php echo ($meet['meet_date'] == $today) ? 'Today' : date("d M", strtotime($meet['meet_date'])); ?></span>
                                        <?php echo date("h:i A", strtotime($meet['meet_time'])); ?>
                                    </div>
                                    <div class="meeting-content-box shadow-sm py-2 px-3">
                                        <p class="text-[13px] font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></p>
                                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5"><?php echo htmlspecialchars($meet['department'] ?? 'Team Meeting'); ?></p>
                                        <?php if(!empty($meet['meet_link'])): 
                                            $actual_link = trim($meet['meet_link']);
                                            if (strpos($actual_link, '.') !== false) {
                                                if (!preg_match("~^(?:f|ht)tps?://~i", $actual_link) && strpos($actual_link, '/') !== 0) {
                                                    $actual_link = "https://" . $actual_link;
                                                }
                                            } else {
                                                $actual_link = $path_to_root . "team_chat.php?room_id=" . urlencode($actual_link);
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($actual_link); ?>" <?php echo (strpos($actual_link, 'team_chat.php') === false) ? 'target="_blank"' : ''; ?> class="text-[10px] text-indigo-600 font-bold mt-1 inline-block hover:underline">
                                                <i class="fa-solid fa-video"></i> Join Meeting
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; } else { echo "<div class='text-center py-8 text-slate-400'><i class='fa-regular fa-calendar-xmark text-3xl mb-2 opacity-50'></i><p class='text-xs font-medium'>No meetings scheduled.</p></div>"; } ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // Live Timer Logic
            let attendanceTimerInterval = null;
            function initAttendance() {
                if (attendanceTimerInterval) clearInterval(attendanceTimerInterval);

                const timerElement = document.getElementById('liveTimer');
                const progressRing = document.getElementById('progressRing');
                const breakTimerElement = document.getElementById('breakTimer');

                if (!timerElement) return;

                const isWorkRunning = timerElement.getAttribute('data-running') === 'true';
                const isBreakRunning = breakTimerElement ? breakTimerElement.getAttribute('data-break-running') === 'true' : false;
                
                const workTotalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
                const breakTotalSeconds = breakTimerElement ? (parseInt(breakTimerElement.getAttribute('data-break-total')) || 0) : 0;
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
                    attendanceTimerInterval = setInterval(updateTimer, 1000);
                }
            }
            initAttendance();

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
                            if (opts.seriesIndex === 1) { 
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

            // Task Priority Chart
            var prioOptions = {
                series: [<?php echo $high_tasks; ?>, <?php echo $med_tasks; ?>, <?php echo $low_tasks; ?>],
                labels: ['High', 'Medium', 'Low'],
                chart: { type: 'donut', height: 150 },
                colors: ['#ef4444', '#f59e0b', '#10b981'],
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '70%', labels: { show: true, name: {show: false}, value: { fontSize: '20px', fontWeight: 900, color: '#1e293b' }, total: { show: true, showAlways: true, label: 'Tasks', color: '#64748b' } } } } }
            };
            var priorityDonutChartEl = document.querySelector("#priorityDonutChart");
            if (priorityDonutChartEl) {
                new ApexCharts(priorityDonutChartEl, prioOptions).render();
            }
        });
    </script>
</body>
</html>