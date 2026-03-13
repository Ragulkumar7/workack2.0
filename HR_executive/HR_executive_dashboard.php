<?php 
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

date_default_timezone_set('Asia/Kolkata');
require_once '../include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$user_role = $_SESSION['role'] ?? 'HR Executive';
$hr_username = $_SESSION['username'] ?? ''; // Added to identify HR for meetings

// =========================================================================
// ACTION: MARK TICKET AS VIEWED & AUTO-UPDATE TABLES
// =========================================================================
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS user_read_status TINYINT(1) DEFAULT 0");
// Added to automatically fix missing meeting_link column in teammate's DB
$conn->query("ALTER TABLE meetings ADD COLUMN IF NOT EXISTS meeting_link VARCHAR(255) DEFAULT NULL");

if (isset($_GET['dismiss_ticket'])) {
    $dismiss_id = intval($_GET['dismiss_ticket']);
    $dismiss_query = "UPDATE tickets SET user_read_status = 1 WHERE id = ? AND user_id = ?";
    $stmt_dismiss = mysqli_prepare($conn, $dismiss_query);
    mysqli_stmt_bind_param($stmt_dismiss, "ii", $dismiss_id, $current_user_id);
    mysqli_stmt_execute($stmt_dismiss);
    header("Location: hr_executive_dashboard.php");
    exit();
}

// -------------------------------------------------------------------------
// 2. FETCH HR EXECUTIVE PROFILE DATA
// -------------------------------------------------------------------------
$employee_name = "HR Executive";
$employee_role = "Human Resources";
$employee_phone = "Not Set";
$employee_email = "Not Set";
$department = "Human Resources";
$joining_date = "Not Set";
$experience_label = "Fresher";
$profile_img = "https://ui-avatars.com/api/?name=HR+Executive&background=0d9488&color=fff&size=128&bold=true";
$shift_timings = '09:00 AM - 06:00 PM';
$reporting_id = 0;

$sql_profile = "SELECT u.username, u.email as u_email, ep.* FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?";
$stmt_p = $conn->prepare($sql_profile);
$stmt_p->bind_param("i", $current_user_id);
$stmt_p->execute();
if ($row = $stmt_p->get_result()->fetch_assoc()) {
    $hr_username = $row['username'];
    $employee_name = $row['full_name'] ?? $row['username'];
    $employee_role = $row['designation'] ?? $user_role;
    $employee_phone = $row['phone'] ?? 'Not Set';
    $employee_email = !empty($row['email']) ? $row['email'] : $row['u_email'];
    $department = $row['department'] ?? 'Human Resources';
    $experience_label = $row['experience_label'] ?? 'Fresher';
    $shift_timings = $row['shift_timings'] ?? $shift_timings;
    
    $reporting_id = !empty($row['manager_id']) ? $row['manager_id'] : ($row['reporting_to'] ?? 0);
    $joining_date = $row['joining_date'] ? date("Y-m-d", strtotime($row['joining_date'])) : "Not Set";
    
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : $path_to_root . 'assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$regular_shift_hours = 9;

// =========================================================================
// FETCH REPORTING MANAGER
// =========================================================================
$mgr_name = "Not Assigned";
$mgr_phone = "N/A";
$mgr_email = "N/A";
$mgr_role = "MANAGER";

if ($reporting_id > 0) {
    $hm_sql = "SELECT p.full_name, p.phone, u.email, u.role FROM employee_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
    $hm_stmt = $conn->prepare($hm_sql);
    $hm_stmt->bind_param("i", $reporting_id);
    $hm_stmt->execute();
    $hm_res = $hm_stmt->get_result();
    if ($hm_info = $hm_res->fetch_assoc()) {
        $mgr_name = !empty($hm_info['full_name']) ? $hm_info['full_name'] : "Manager";
        $mgr_phone = !empty($hm_info['phone']) ? $hm_info['phone'] : "N/A";
        $mgr_email = !empty($hm_info['email']) ? $hm_info['email'] : "N/A";
        $mgr_role = strtoupper($hm_info['role'] ?? 'MANAGER'); 
    }
    $hm_stmt->close();
}

// =========================================================================
// 3. ADVANCED TIME TRACKER (TODAY'S HOURS FOR CARD)
// =========================================================================
$total_seconds_today = 0;
$break_seconds_today = 0;
$productive_seconds_today = 0;
$overtime_seconds_today = 0;
$today_punch_in = null;
$attendance_record_today = null;
$is_on_break = false;
$total_hours_today = "00:00:00"; 
$break_time_str = "00:00:00";

$today_sql = "SELECT id, punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND date = ?";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("is", $current_user_id, $today);
$today_stmt->execute();
$today_res = $today_stmt->get_result();

if ($t_row = $today_res->fetch_assoc()) {
    $attendance_record_today = $t_row;
    if (!empty($t_row['punch_in'])) {
        $today_punch_in = $t_row['punch_in'];
        $in_time = strtotime($t_row['punch_in']);
        
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
        
        if ($break_seconds_today == 0 && !empty($t_row['break_time'])) {
            $break_seconds_today = intval($t_row['break_time']) * 60;
        }

        $out_time = $is_on_break ? $break_start_ts : (!empty($t_row['punch_out']) ? strtotime($t_row['punch_out']) : time());
        $total_seconds_today = max(0, $out_time - $in_time - $break_seconds_today);

        $productive_seconds_today = max(0, $total_seconds_today);
        $shift_seconds = $regular_shift_hours * 3600;
        $overtime_seconds_today = max(0, $productive_seconds_today - $shift_seconds);

        $hours = floor($total_seconds_today / 3600); 
        $mins = floor(($total_seconds_today % 3600) / 60); 
        $secs = $total_seconds_today % 60;
        $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        
        $b_hours = floor($break_seconds_today / 3600); 
        $b_mins = floor(($break_seconds_today % 3600) / 60); 
        $b_secs = $break_seconds_today % 60;
        $break_time_str = sprintf('%02d:%02d:%02d', $b_hours, $b_mins, $b_secs);
    }
}
$today_stmt->close();

function formatTimeStr($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf("%02dh %02dm", $h, $m);
}

$str_total = formatTimeStr($total_seconds_today);
$str_prod = formatTimeStr($productive_seconds_today);
$str_break = formatTimeStr($break_seconds_today);
$str_ot = formatTimeStr($overtime_seconds_today);

$bar_total = max(1, $total_seconds_today); 
$reg_prod_secs = max(0, $productive_seconds_today - $overtime_seconds_today);
$pct_prod = round(($reg_prod_secs / $bar_total) * 100);
$pct_break = round(($break_seconds_today / $bar_total) * 100);
$pct_ot = round(($overtime_seconds_today / $bar_total) * 100);

// Fetch Monthly Overtime
$ot_monthly_seconds = 0;
$ot_sql = "SELECT punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL";
$ot_stmt = $conn->prepare($ot_sql);
$ot_stmt->bind_param("iii", $current_user_id, $current_month, $current_year);
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
// 4. MONTHLY STATS & LATE HOURS (WITH EXACT ABSENT LOOP LOGIC)
// =========================================================================
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$total_late_seconds = 0;

$start_date_stat = date('Y-m-01');
$end_date_stat = $today;

// 1. Fetch DB Records for the month
$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND date >= ? AND date <= ?";
$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iss", $current_user_id, $start_date_stat, $end_date_stat);
$stat_stmt->execute();
$stat_res = $stat_stmt->get_result();

$month_att_db = [];
while ($stat_row = $stat_res->fetch_assoc()) {
    $month_att_db[$stat_row['date']] = $stat_row;
}
$stat_stmt->close();

// 2. Fetch Approved Leaves safely
$stmt_all_leaves = $conn->prepare("SELECT start_date, end_date, leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND start_date <= ?");
$stmt_all_leaves->bind_param("is", $current_user_id, $today);
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

// 3. Exact Date Loop Engine
$current_dt = new DateTime($end_date_stat);
$start_dt = new DateTime($start_date_stat);

while ($current_dt >= $start_dt) {
    $date_str = $current_dt->format('Y-m-d');
    $is_future = ($date_str > $today);
    $day_of_week = $current_dt->format('N'); 
    
    if (isset($month_att_db[$date_str])) {
        $row = $month_att_db[$date_str];
        $st = $row['status'];
        
        $is_absent_db = (stripos($st, 'Absent') !== false && empty($row['punch_in']));

        if ($is_absent_db) {
            $stats_absent++;
        } else {
            if (stripos($st, 'WFH') !== false) { 
                $stats_wfh++; 
            } elseif (stripos($st, 'Sick') !== false) { 
                $stats_sick++; 
            }

            if (!empty($row['punch_in'])) {
                $shift_start_ts = strtotime($row['date'] . ' ' . $shift_start_str);
                $punch_in_ts = strtotime($row['punch_in']);

                if ($punch_in_ts > ($shift_start_ts + 60)) { 
                    $stats_late++; 
                    $total_late_seconds += ($punch_in_ts - $shift_start_ts);
                } else { 
                    if (stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                        $stats_ontime++; 
                    }
                }
            }
        }
    } else {
        if (!$is_future) {
            if ($day_of_week == 7) {
                // Weekly Off - Do nothing
            } elseif (isset($all_app_leaves[$date_str])) {
                // On Leave
                if (stripos($all_app_leaves[$date_str], 'Sick') !== false) {
                    $stats_sick++;
                }
            } else {
                // Exact Absent Calculation
                $stats_absent++; 
            }
        }
    }
    $current_dt->modify('-1 day');
}

$late_hours = floor($total_late_seconds / 3600);
$late_minutes = floor(($total_late_seconds % 3600) / 60);
$late_time_str = $late_hours . 'h ' . $late_minutes . 'm';

// Leaves Taken specifically for UI display text
$current_month_leaves = 0;
$curr_leave_sql = "SELECT leave_type, SUM(total_days) as days FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND MONTH(start_date) = ? AND YEAR(start_date) = ? GROUP BY leave_type";
$curr_leave_stmt = $conn->prepare($curr_leave_sql);
$curr_leave_stmt->bind_param("iii", $current_user_id, $current_month, $current_year);
$curr_leave_stmt->execute();
$curr_leave_res = $curr_leave_stmt->get_result();

while ($cl_row = $curr_leave_res->fetch_assoc()) {
    $current_month_leaves += floatval($cl_row['days']);
}
$curr_leave_stmt->close();

// =========================================================================
// 5. LEAVE BALANCE (CARRY-FORWARD) - CORRECTED FIXED 2 DAYS
// =========================================================================
$base_leaves_per_month = 2;
$raw_join_date = $joining_date !== "Not Set" ? $joining_date : date('Y-m-01');
$calc_join_date = date('Y-m-d', strtotime($raw_join_date));
$display_join_month_year = date('M Y', strtotime($raw_join_date));

// Fixed to strictly 2 leaves earned limit, as requested
$total_earned_leaves = 2;

$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $current_user_id);
$leave_stmt->execute();
$leave_data = $leave_stmt->get_result()->fetch_assoc();
$leaves_taken = floatval($leave_data['taken'] ?? 0);

$leaves_remaining = $total_earned_leaves - $leaves_taken;
$display_leaves_remaining = ($leaves_remaining < 0) ? 0 : $leaves_remaining; 
$lop_days = ($leaves_remaining < 0) ? abs($leaves_remaining) : 0;


// =========================================================================
// 6. HR SPECIFIC DATA (Departments Grouped Properly, Jobs, Metrics)
// =========================================================================
$total_employees_query = "SELECT COUNT(*) as cnt FROM employee_profiles";
$res = mysqli_query($conn, $total_employees_query);
$total_employees = mysqli_fetch_assoc($res)['cnt'] ?? 1;

$res_present = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE date = '$today' AND (status='On Time' OR status='WFH' OR status='Late')")->fetch_assoc();
$all_present = $res_present['cnt'] ?? 0;
$all_absent = max(0, $total_employees - $all_present);

// Correctly grouped and summed department mapping
$dept_counts_query = "SELECT department, COUNT(id) as count FROM employee_profiles WHERE department IS NOT NULL AND department != '' GROUP BY department";
$dept_counts_result = mysqli_query($conn, $dept_counts_query);

$mapped_depts = [
    'Development' => ['count' => 0, 'icon' => 'code', 'color' => 'blue'],
    'Sales'       => ['count' => 0, 'icon' => 'dollar-sign', 'color' => 'green'],
    'Accounts'    => ['count' => 0, 'icon' => 'file-text', 'color' => 'yellow'],
    'IT'          => ['count' => 0, 'icon' => 'monitor', 'color' => 'indigo'],
    'HR'          => ['count' => 0, 'icon' => 'users', 'color' => 'purple']
];

while ($r_dept = mysqli_fetch_assoc($dept_counts_result)) {
    $raw_dept = strtolower(trim($r_dept['department']));
    $count = (int)$r_dept['count'];
    
    if (strpos($raw_dept, 'dev') !== false || strpos($raw_dept, 'eng') !== false) { $mapped_depts['Development']['count'] += $count; }
    elseif (strpos($raw_dept, 'sale') !== false || strpos($raw_dept, 'market') !== false) { $mapped_depts['Sales']['count'] += $count; }
    elseif (strpos($raw_dept, 'acc') !== false || strpos($raw_dept, 'fin') !== false) { $mapped_depts['Accounts']['count'] += $count; }
    elseif (strpos($raw_dept, 'it') !== false) { $mapped_depts['IT']['count'] += $count; }
    elseif (strpos($raw_dept, 'hr') !== false || strpos($raw_dept, 'human') !== false) { $mapped_depts['HR']['count'] += $count; }
    else { $mapped_depts['IT']['count'] += $count; } // Default fallback
}

$departments_list = [];
foreach($mapped_depts as $label => $data) {
    if($data['count'] > 0) {
        $departments_list[] = [
            'label' => $label,
            'count' => $data['count'],
            'icon' => $data['icon'],
            'color' => $data['color']
        ];
    }
}

function safe_count($conn, $query) {
    $result = mysqli_query($conn, $query);
    if ($result === false) { return 0; }
    $row = mysqli_fetch_assoc($result);
    return (int)($row['cnt'] ?? 0);
}
$cand_count = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates");

// Pending Jobs Logic
$job_reqs = [];
$q_jobs_pending = "SELECT hr.id, hr.job_title, hr.department, hr.vacancy_count, hr.status, u.name as requester_name 
                   FROM hiring_requests hr 
                   LEFT JOIN users u ON hr.manager_id = u.id 
                   WHERE hr.status = 'Pending' ORDER BY hr.id DESC LIMIT 4";
$r_jobs_pending = mysqli_query($conn, $q_jobs_pending);
if($r_jobs_pending) {
    while($row = mysqli_fetch_assoc($r_jobs_pending)) {
        $job_reqs[] = $row;
    }
}

// Active Jobs Logic
$jobs_cond = "WHERE hr.status IN ('Approved', 'In Progress')";
$jobs_query = "SELECT hr.id, hr.job_title, hr.department, hr.vacancy_count, hr.status, u.name as requested_by 
               FROM hiring_requests hr 
               LEFT JOIN users u ON hr.manager_id = u.id 
               $jobs_cond ORDER BY hr.id DESC LIMIT 4";
$jobs_res = mysqli_query($conn, $jobs_query);


// =========================================================================
// 7. UNIFIED NOTIFICATIONS
// =========================================================================
$all_notifications = [];

$q_tickets = "SELECT id, ticket_code, subject FROM tickets WHERE user_id = $current_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY id DESC LIMIT 3";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 'id' => $row['id'],
            'title' => 'Ticket Solved: #' . ($row['ticket_code'] ?? $row['id']),
            'message' => 'IT Team has resolved your ticket: ' . htmlspecialchars($row['subject']),
            'time' => date('Y-m-d H:i:s'),
            'icon' => 'fa-check-double', 'color' => 'text-green-600 bg-green-100',
            'link' => '?dismiss_ticket=' . $row['id']
        ];
    }
}

$q_leaves = "SELECT leave_type, status, start_date FROM leave_requests WHERE user_id = $current_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 3";
$r_leaves = mysqli_query($conn, $q_leaves);
if($r_leaves) {
    while($row = mysqli_fetch_assoc($r_leaves)) {
        $all_notifications[] = [
            'type' => 'leave', 'title' => 'Leave ' . $row['status'],
            'message' => 'Your ' . $row['leave_type'] . ' request was ' . strtolower($row['status']) . '.',
            'time' => $row['start_date'] . ' 09:00:00', 
            'icon' => ($row['status'] == 'Approved') ? 'fa-check-circle' : 'fa-times-circle',
            'color' => ($row['status'] == 'Approved') ? 'text-emerald-500 bg-emerald-100' : 'text-rose-500 bg-rose-100',
            'link' => '../employee/leave_request.php'
        ];
    }
}

$q_announcements = "SELECT id, title, message FROM announcements WHERE is_archived = 0 AND category != 'Meeting' AND (target_audience = 'All' OR target_audience = '$user_role') ORDER BY id DESC LIMIT 10"; 
$r_announcements = mysqli_query($conn, $q_announcements);
if($r_announcements) {
    while($row = mysqli_fetch_assoc($r_announcements)) {
        $all_notifications[] = [
            'type' => 'announcement', 'title' => 'Announcement: ' . htmlspecialchars($row['title']),
            'message' => htmlspecialchars(substr($row['message'], 0, 50)) . '...',
            'time' => date('Y-m-d H:i:s'), 'icon' => 'fa-bullhorn', 'color' => 'text-orange-600 bg-orange-100',
            'link' => '../view_announcements.php'
        ];
    }
}

// FETCH MEETINGS FOR HR (Scheduled by HR / Others)
$all_today_meetings = [];
$q_ann_meets = "SELECT a.id, a.title, a.publish_date as meet_date, '' as meet_link, u.department, a.message, a.created_at, COALESCE(u.username, 'Admin') as host_name 
                FROM announcements a 
                LEFT JOIN users u ON a.created_by = u.id 
                WHERE a.category = 'Meeting' AND a.is_archived = 0 
                AND (a.target_audience = 'All' 
                     OR a.target_audience = 'All Employees' 
                     OR a.target_audience = '$user_role'
                     OR a.target_audience LIKE '%" . $conn->real_escape_string($hr_username) . "%' 
                     OR a.message LIKE '%" . $conn->real_escape_string($hr_username) . "%'
                     OR a.message LIKE '%" . $conn->real_escape_string($employee_name) . "%'
                     OR a.created_by = $current_user_id)";
$r_ann_meets = mysqli_query($conn, $q_ann_meets);
if($r_ann_meets) {
    while($row = mysqli_fetch_assoc($r_ann_meets)) {
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

        // Push to array if meeting is TODAY OR IN THE FUTURE
        if ($row['meet_date'] >= $today) {
            $all_today_meetings[] = $row;
        }
    }
}

usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 10); 

// =========================================================================
// 8. NEW ADDITIONS: MEETINGS, NEW JOINERS, CELEBRATIONS
// =========================================================================

// A. Meetings (Combine with old calendar logic)
$check_meetings = $conn->query("SHOW TABLES LIKE 'calendar_meetings'");
if ($check_meetings && $check_meetings->num_rows > 0) {
    $q_meetings = "SELECT title, meet_date as meeting_date, meet_time as meeting_time, meet_link as meeting_link FROM calendar_meetings WHERE meet_date >= '$today' AND created_by = $current_user_id ORDER BY meet_date ASC, meet_time ASC LIMIT 3";
    $r_meetings = @mysqli_query($conn, $q_meetings);
    if ($r_meetings) {
        while ($row = mysqli_fetch_assoc($r_meetings)) {
            $all_today_meetings[] = [
                'title' => $row['title'],
                'meet_date' => $row['meeting_date'],
                'meet_time' => $row['meeting_time'],
                'meet_link' => $row['meeting_link']
            ];
        }
    }
}

usort($all_today_meetings, function($a, $b) {
    $timeA = strtotime($a['meet_date'] . ' ' . $a['meet_time']);
    $timeB = strtotime($b['meet_date'] . ' ' . $b['meet_time']);
    return $timeA - $timeB;
});

// Limit to 3 for the dashboard card
$meetings_list = array_slice($all_today_meetings, 0, 3);


// B. RECENT NEW JOINERS 
$new_joiners = [];
$nj_q = "SELECT full_name, department, joining_date FROM employee_profiles WHERE status = 'Active' AND joining_date IS NOT NULL ORDER BY joining_date DESC LIMIT 4";
$nj_res = @mysqli_query($conn, $nj_q);
if($nj_res) {
    while($row = mysqli_fetch_assoc($nj_res)) {
        $new_joiners[] = $row;
    }
}

// C. Celebrations (Birthdays / Anniversaries)
$celebrations = [];
$q_celeb = "SELECT u.username, ep.dob, ep.joining_date FROM employee_profiles ep LEFT JOIN users u ON ep.user_id = u.id WHERE MONTH(ep.dob) = '$current_month' OR MONTH(ep.joining_date) = '$current_month' LIMIT 4";
$r_celeb = @mysqli_query($conn, $q_celeb);
if ($r_celeb) {
    while ($row = mysqli_fetch_assoc($r_celeb)) {
        if (!empty($row['dob']) && date('m', strtotime($row['dob'])) == $current_month) {
            $celebrations[] = ['name' => $row['username'], 'type' => 'Birthday', 'date' => date('d M', strtotime($row['dob'])), 'icon' => 'fa-cake-candles', 'color' => 'text-pink-500 bg-pink-100'];
        }
        if (!empty($row['joining_date']) && date('m', strtotime($row['joining_date'])) == $current_month && date('Y', strtotime($row['joining_date'])) != $current_year) {
            $celebrations[] = ['name' => $row['username'], 'type' => 'Work Anniversary', 'date' => date('d M', strtotime($row['joining_date'])), 'icon' => 'fa-award', 'color' => 'text-amber-500 bg-amber-100'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Executive Dashboard - SmartHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; overflow-x: hidden; }
        
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform 0.3s ease, box-shadow 0.3s ease; display: flex; flex-direction: column; }
        .card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1; transform: translateY(-2px); }
        
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        
        .stat-badge { font-size: 1.5rem; font-weight: 900; line-height: 1; color: #1e293b; }

        /* EXACT 3-COLUMN FLEX GRID */
        .dashboard-container { 
            display: grid; 
            grid-template-columns: repeat(1, minmax(0, 1fr)); 
            gap: 1.5rem; 
            align-items: start; /* Prevents cards from stretching unnaturally */
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

    <?php if (file_exists($path_to_root . 'sidebars.php')) include($path_to_root . 'sidebars.php'); ?>
    <?php if (file_exists($path_to_root . 'header.php')) include($path_to_root . 'header.php'); ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">HR Executive Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Welcome back, <b class="text-slate-700"><?php echo htmlspecialchars($employee_name); ?></b></p>
            </div>
            <div class="flex gap-3">
                <div class="bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Total Employees</p><p class="stat-badge"><?php echo $total_employees; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Company Present</p><p class="stat-badge text-emerald-600"><?php echo $all_present; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-check"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Company Absent</p><p class="stat-badge text-red-500"><?php echo $all_absent; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-xmark"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='jobs.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Pending Job Req</p><p class="stat-badge text-orange-500"><?php echo count($job_reqs); ?></p></div>
                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-lg"><i class="fa-solid fa-briefcase"></i></div>
            </div>
        </div>

        <div class="dashboard-container mb-6">

            <div class="flex flex-col gap-6 w-full"> 
                
                <?php include '../attendance_card.php'; ?>

                <div class="card">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg">My Updates</h3>
                            <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase border border-slate-200">Live Feed</span>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[300px] pr-2">
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
                                            <?php elseif(isset($notif['type']) && ($notif['type'] == 'meeting' || $notif['type'] == 'meeting_announcement')): ?>
                                                <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold px-3 py-1.5 rounded-full hover:bg-indigo-100 transition shadow-sm">
                                                    <i class="fa-solid fa-video mr-1"></i> View Details
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
                                <div class="text-center py-10 text-slate-400">
                                    <i class="fa-regular fa-bell-slash text-4xl mb-3 opacity-80"></i>
                                    <p class='text-sm font-medium text-slate-500'>No recent updates.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card border-blue-200">
                    <div class="p-6 flex flex-col">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Recruitment Metrics</h3>
                            <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-1 rounded font-bold border border-gray-200">This Month</span>
                        </div>
                        
                        <div class="flex justify-around bg-slate-50 rounded-xl p-3 mb-4 border border-slate-100 shrink-0">
                            <div class="text-center">
                                <span class="text-[10px] text-gray-500 font-bold uppercase block mb-1">Offer Acceptance</span>
                                <span class="text-lg font-black text-slate-800">74.4%</span>
                            </div>
                            <div class="w-px bg-gray-200 mx-2"></div>
                            <div class="text-center">
                                <span class="text-[10px] text-gray-500 font-bold uppercase block mb-1">Overall Hire Rate</span>
                                <span class="text-lg font-black text-slate-800">12.7%</span>
                            </div>
                        </div>
                        
                        <div class="relative flex justify-center items-center w-full min-h-[200px]">
                            <div class="w-48 h-48 relative">
                                <canvas id="gaugeChart"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pt-8">
                                    <p class="text-3xl font-black text-slate-800"><?= $cand_count ?></p>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Applications</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6 w-full"> 
                
                <div class="card">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg">My Attendance Stats</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center justify-between gap-6">
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
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-4">
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

                        <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-lg text-center transition shadow-md shadow-teal-200/50 text-sm mt-2">
                            <i class="fa-solid fa-plus mr-1.5"></i> APPLY FOR LEAVE
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="p-6 flex flex-col">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-lg text-slate-800">Departments Overview</h3>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 custom-scroll overflow-y-auto max-h-[300px] pr-2">
                            <?php foreach ($departments_list as $dept):
                                $pct = $total_employees > 0 ? round(($dept['count'] / $total_employees) * 100) : 0;
                            ?>
                            <div class="bg-gray-50 p-3 rounded-xl text-center border border-gray-200 hover:border-teal-300 transition-colors shadow-sm">
                                <div class="bg-<?= $dept['color'] ?>-100 text-<?= $dept['color'] ?>-700 w-8 h-8 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i data-lucide="<?= $dept['icon'] ?>" class="w-4 h-4"></i>
                                </div>
                                <h4 class="text-lg font-black text-slate-800"><?= $dept['count'] ?></h4>
                                <p class="text-[10px] font-bold text-gray-500 truncate px-1 mt-0.5 uppercase tracking-wider" title="<?= $dept['label'] ?>"><?= $dept['label'] ?></p>
                                <p class="text-[9px] text-gray-400 mt-0.5"><?= $pct ?>% of total</p>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($departments_list)): ?>
                                <div class="col-span-2 text-center text-gray-400 py-4 text-sm">No department data found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card border-indigo-200 flex-grow mt-6">
                    <div class="p-6 flex flex-col h-full">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-user-plus text-indigo-500"></i> New Joiners
                            </h3>
                            <span class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-1 rounded font-bold border border-indigo-100">Recent</span>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto pr-2 flex-grow flex flex-col justify-center">
                            <?php if(!empty($new_joiners)): ?>
                                <div class="space-y-3 h-full">
                                <?php foreach($new_joiners as $nj): ?>
                                    <div class="flex gap-3 items-center border border-gray-100 p-2.5 rounded-xl hover:bg-slate-50 transition shadow-sm">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
                                            <i class="fa-solid fa-user-check"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($nj['full_name']) ?></p>
                                            <p class="text-[10px] text-gray-500 font-medium"><?= htmlspecialchars($nj['department']) ?></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-1 rounded-md border border-slate-200"><?= date('d M Y', strtotime($nj['joining_date'])) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-slate-400 text-sm font-medium my-auto">
                                    No recent joiners found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6 w-full"> 
                
                <div class="card overflow-hidden shadow-sm border-slate-200 shrink-0">
                    <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-4 flex items-center gap-4 relative">
                        <div class="relative shrink-0">
                            <img src="<?php echo $profile_img; ?>" class="w-14 h-14 rounded-full border-2 border-white shadow-lg object-cover bg-white">
                            <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <div class="min-w-0 text-white">
                            <h2 class="font-black text-lg truncate"><?php echo htmlspecialchars($employee_name); ?></h2>
                            <p class="text-teal-100 text-[9px] font-bold uppercase tracking-widest truncate mt-0.5"><?php echo htmlspecialchars($employee_role); ?></p>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-white border-b border-gray-100">
                         <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-phone text-teal-600 w-4 text-center text-xs"></i>
                                <p class="text-[11px] font-bold text-slate-700 truncate"><?php echo htmlspecialchars($employee_phone); ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-envelope text-teal-600 w-4 text-center text-xs"></i>
                                <p class="text-[11px] font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($employee_email); ?>">
                                    <?php echo htmlspecialchars($employee_email); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-slate-50">
                        <div class="bg-white p-2 rounded-lg border border-slate-200 mb-3 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1"><i class="fa-solid fa-user-shield mr-1 text-purple-500"></i> Reporting Manager</p>
                            <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($mgr_name); ?></p>
                            <?php if($mgr_phone !== 'N/A' && $mgr_phone !== ''): ?>
                                <p class="text-[9px] text-slate-500 font-medium mt-1"><i class="fa-solid fa-phone text-[8px] mr-1"></i> <?php echo htmlspecialchars($mgr_phone); ?></p>
                            <?php endif; ?>
                            <?php if($mgr_email !== 'N/A' && $mgr_email !== ''): ?>
                                <p class="text-[9px] text-slate-500 font-medium mt-0.5 truncate" title="<?php echo htmlspecialchars($mgr_email); ?>"><i class="fa-solid fa-envelope text-[8px] mr-1"></i> <?php echo htmlspecialchars($mgr_email); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div class="bg-white p-2 rounded-lg border border-slate-200 text-center">
                                <p class="text-[8px] text-gray-400 font-bold uppercase">Experience</p>
                                <p class="text-[11px] font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($experience_label); ?></p>
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-slate-200 text-center">
                                <p class="text-[8px] text-gray-400 font-bold uppercase">Department</p>
                                <p class="text-[11px] font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($department); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-blue-200">
                    <div class="p-4">
                        <div class="flex justify-between items-center mb-4 border-b border-blue-100 pb-2">
                            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-1.5"><i class="fa-solid fa-stopwatch text-blue-500 text-md"></i> Time Tracker</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-slate-50 px-1.5 py-0.5 rounded border border-gray-100">Today</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-3">
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
                                <p class="text-md font-black text-blue-600" id="liveTimer" data-running="<?php echo ($attendance_record_today && !$attendance_record_today['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>" data-total="<?php echo $total_seconds_today; ?>"><?php echo $str_total; ?></p>
                            </div>
                        </div>

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

                <div class="card border-pink-200">
                    <div class="p-6 flex flex-col h-full flex-grow">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-cake-candles text-pink-500"></i> This Month's Celebrations
                            </h3>
                        </div>
                        <div class="custom-scroll overflow-y-auto pr-2 flex-grow flex flex-col justify-center">
                            <?php if(!empty($celebrations)): ?>
                                <div class="space-y-3 h-full">
                                <?php foreach($celebrations as $celeb): ?>
                                    <div class="flex gap-3 items-center border border-gray-100 p-2.5 rounded-xl hover:bg-slate-50 transition shadow-sm">
                                        <div class="w-8 h-8 rounded-full <?= $celeb['color'] ?> flex items-center justify-center font-bold text-xs shrink-0">
                                            <i class="fa-solid <?= $celeb['icon'] ?>"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($celeb['name']) ?></p>
                                            <p class="text-[10px] text-gray-500 font-medium"><?= htmlspecialchars($celeb['type']) ?></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-1 rounded-md border border-slate-200"><?= htmlspecialchars($celeb['date']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-slate-400 text-sm font-medium my-auto">
                                    No birthdays or work anniversaries this month.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card border-purple-200">
                    <div class="p-6 flex flex-col h-full flex-grow">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-video text-purple-500"></i> Upcoming Meetings
                            </h3>
                        </div>
                        <div class="custom-scroll overflow-y-auto pr-2 flex-grow flex flex-col justify-center">
                            <?php if(!empty($meetings_list)): ?>
                                <div class="space-y-3 h-full">
                                <?php foreach($meetings_list as $meeting): ?>
                                    <div class="bg-purple-50 p-3 rounded-xl border border-purple-100 hover:shadow-sm transition">
                                        <p class="font-bold text-sm text-slate-800 mb-1"><?= htmlspecialchars($meeting['title']) ?></p>
                                        <div class="flex justify-between items-center text-xs text-purple-700 font-medium">
                                            <span>
                                                <i class="fa-regular fa-calendar mr-1"></i> <?= ($meeting['meet_date'] == $today) ? 'Today' : date('d M', strtotime($meeting['meet_date'])) ?> 
                                                <i class="fa-regular fa-clock ml-2 mr-1"></i> <?= date('h:i A', strtotime($meeting['meet_time'])) ?>
                                            </span>
                                            <?php if(!empty($meeting['meet_link'])): ?>
                                                <a href="<?= htmlspecialchars($meeting['meet_link']) ?>" target="_blank" class="bg-purple-600 text-white px-2.5 py-1 rounded-md hover:bg-purple-700 transition">Join</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-slate-400 text-sm font-medium my-auto">
                                    No upcoming meetings scheduled.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div> 
    </main>

    <script>
        lucide.createIcons();

        // GAUGE CHART
        const ctx = document.getElementById('gaugeChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [74.4, 25.6],
                        backgroundColor: ['#0f766e', '#f1f5f9'],
                        borderWidth: 0,
                        circumference: 180,
                        rotation: 270,
                        borderRadius: 8,
                        cutout: '80%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    layout: { padding: 0 }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            
            // Live Timer Logic (For display text updating, actual buttons are in attendance_card)
            let attendanceTimerInterval = null;
            function initAttendance() {
                if (attendanceTimerInterval) clearInterval(attendanceTimerInterval);

                const timerElement = document.getElementById('liveTimer');

                if (!timerElement) return;

                const isWorkRunning = timerElement.getAttribute('data-running') === 'true';
                const workTotalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
                const startTime = new Date().getTime(); 

                function formatTime(totalSecs) {
                    const h = Math.floor(totalSecs / 3600);
                    const m = Math.floor((totalSecs % 3600) / 60);
                    return String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm';
                }

                function updateTimer() {
                    const now = new Date().getTime();
                    const diffSeconds = Math.floor((now - startTime) / 1000);
                    
                    if (isWorkRunning) {
                        const currentWork = workTotalSeconds + diffSeconds;
                        timerElement.innerText = formatTime(currentWork);
                    }
                }

                if (isWorkRunning) {
                    attendanceTimerInterval = setInterval(updateTimer, 60000); // update every minute for 'h m' format
                }
            }
            initAttendance();

            // Pass PHP variables to JS cleanly
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
        });
    </script>
</body>
</html>