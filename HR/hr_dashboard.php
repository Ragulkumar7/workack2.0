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

// =========================================================================
// ACTION: MARK TICKET AS VIEWED
// =========================================================================
$conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS user_read_status TINYINT(1) DEFAULT 0");

if (isset($_GET['dismiss_ticket'])) {
    $dismiss_id = intval($_GET['dismiss_ticket']);
    $dismiss_query = "UPDATE tickets SET user_read_status = 1 WHERE id = ? AND user_id = ?";
    $stmt_dismiss = mysqli_prepare($conn, $dismiss_query);
    mysqli_stmt_bind_param($stmt_dismiss, "ii", $dismiss_id, $current_user_id);
    mysqli_stmt_execute($stmt_dismiss);
    header("Location: hr_dashboard.php");
    exit();
}

// -------------------------------------------------------------------------
// 2. FETCH HR PROFILE DATA
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
    $employee_name = $row['full_name'] ?? $row['username'];
    $employee_role = $row['designation'] ?? $user_role;
    $employee_phone = $row['phone'] ?? 'Not Set';
    $employee_email = !empty($row['email']) ? $row['email'] : $row['u_email'];
    $department = $row['department'] ?? 'Human Resources';
    $experience_label = $row['experience_label'] ?? 'Fresher';
    $shift_timings = $row['shift_timings'] ?? $shift_timings;
    
    // Grab Manager ID for later
    $reporting_id = !empty($row['manager_id']) ? $row['manager_id'] : ($row['reporting_to'] ?? 0);
    
    $joining_date = $row['joining_date'] ? date("d M Y", strtotime($row['joining_date'])) : "Not Set";
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : $path_to_root . 'assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$regular_shift_hours = 9;

// =========================================================================
// FETCH REPORTING MANAGER DATA (RESTORED FIX)
// =========================================================================
$mgr_name = "System Admin";
$mgr_phone = "Not Assigned";
$mgr_email = "admin@company.com";
$mgr_role = "ADMINISTRATOR";

if ($reporting_id > 0) {
    $hm_sql = "SELECT p.full_name, p.phone, u.email, u.role FROM employee_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
    $hm_stmt = $conn->prepare($hm_sql);
    $hm_stmt->bind_param("i", $reporting_id);
    $hm_stmt->execute();
    $hm_res = $hm_stmt->get_result();
    if ($hm_info = $hm_res->fetch_assoc()) {
        $mgr_name = !empty($hm_info['full_name']) ? $hm_info['full_name'] : "Manager";
        $mgr_phone = !empty($hm_info['phone']) ? $hm_info['phone'] : "Not Set";
        $mgr_email = !empty($hm_info['email']) ? $hm_info['email'] : "Not Set";
        $mgr_role = strtoupper($hm_info['role'] ?? 'MANAGER'); 
    }
    $hm_stmt->close();
}

// =========================================================================
// 3. ADVANCED TIME TRACKER (TODAY'S HOURS) & AJAX ACTIONS
// =========================================================================
$total_seconds_today = 0; 
$break_seconds_today = 0; 
$productive_seconds_today = 0; 
$overtime_seconds_today = 0;
$display_break_seconds = 0;
$today_punch_in = null;
$attendance_record_today = null;
$is_on_break = false;
$display_punch_in = "--:--";
$delay_text = "";
$delay_class = "";
$total_hours_today = "00:00:00";
$break_time_str = "00:00:00";

// AJAX Handler for Punch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    $now = date('Y-m-d H:i:s');
    if ($_POST['action'] === 'punch_in') {
        $status = (date('H:i') > '09:30') ? 'Late' : 'On Time';
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, punch_in, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $current_user_id, $today, $now, $status);
        if ($stmt->execute()) $response = ['status' => 'success'];
        $stmt->close();
    } elseif ($_POST['action'] === 'punch_out') {
        $att_rec = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        $break_sec = 0;
        $br_q = $conn->query("SELECT * FROM attendance_breaks WHERE attendance_id = " . $att_rec['id']);
        while($br = $br_q->fetch_assoc()){ if($br['break_end']) $break_sec += strtotime($br['break_end']) - strtotime($br['break_start']); }
        $prod_hours = max(0, (time() - strtotime($att_rec['punch_in'])) - $break_sec) / 3600;
        $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE user_id = ? AND date = ?");
        $stmt->bind_param("sdis", $now, $prod_hours, $current_user_id, $today);
        if ($stmt->execute()) $response = ['status' => 'success'];
    } elseif ($_POST['action'] === 'take_break') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
        $stmt->bind_param("is", $att_rec['id'], $now);
        if($stmt->execute()) {
            $conn->query("UPDATE attendance SET break_time = '1' WHERE id = " . $att_rec['id']);
            $response = ['status' => 'success'];
        }
    } elseif ($_POST['action'] === 'end_break') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        $stmt = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
        $stmt->bind_param("si", $now, $att_rec['id']);
        if($stmt->execute()) $response = ['status' => 'success'];
    }
    echo json_encode($response); exit; 
}

$today_sql = "SELECT id, punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND date = ?";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("is", $current_user_id, $today);
$today_stmt->execute();
$today_res = $today_stmt->get_result();

if ($t_row = $today_res->fetch_assoc()) {
    $attendance_record_today = $t_row;
    
    if (!empty($t_row['punch_in'])) {
        $today_punch_in = $t_row['punch_in'];
        $display_punch_in = date('h:i A', strtotime($t_row['punch_in']));
        
        $in_time = strtotime($t_row['punch_in']);
        $out_time = !empty($t_row['punch_out']) ? strtotime($t_row['punch_out']) : time(); 
        
        // Delay Check
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

        // Break Calculations
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
        
        $display_break_seconds = $break_seconds_today;
        
        $out_time = $is_on_break ? $break_start_ts : (!empty($t_row['punch_out']) ? strtotime($t_row['punch_out']) : time());
        $total_seconds_today = max(0, ($out_time - $in_time) - $break_seconds_today);
        
        $productive_seconds_today = max(0, $total_seconds_today);
        $shift_seconds = $regular_shift_hours * 3600;
        $overtime_seconds_today = max(0, $productive_seconds_today - $shift_seconds);
        
        $hours = floor($total_seconds_today / 3600); 
        $mins = floor(($total_seconds_today % 3600) / 60); 
        $secs = $total_seconds_today % 60;
        $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        
        $b_hours = floor($display_break_seconds / 3600); 
        $b_mins = floor(($display_break_seconds % 3600) / 60); 
        $b_secs = $display_break_seconds % 60;
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
// 4. COMPANY WIDE DATA (Departments, Employees, Pending Tasks)
// =========================================================================
function safe_count($conn, $query) {
    $res = mysqli_query($conn, $query);
    return $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
}

$total_employees = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_profiles WHERE status='Active'");
$all_present = safe_count($conn, "SELECT COUNT(*) as cnt FROM attendance WHERE date = '$today' AND (status='On Time' OR status='WFH' OR status='Late')");
$all_absent = max(0, $total_employees - $all_present);

$pending_leaves = safe_count($conn, "SELECT COUNT(*) as cnt FROM leave_requests WHERE status='Pending'");
$pending_jobs = safe_count($conn, "SELECT COUNT(*) as cnt FROM hiring_requests WHERE status='Pending'");
$pending_swaps = safe_count($conn, "SELECT COUNT(*) as cnt FROM shift_swap_requests WHERE hr_approval='Pending'");
$pending_wfh = safe_count($conn, "SELECT COUNT(*) as cnt FROM wfh_requests WHERE status='Pending'");

$total_hr_actions = $pending_leaves + $pending_jobs + $pending_swaps + $pending_wfh;

// CANDIDATE COUNT RESTORED
$cand_count = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates");

// Department Mapping for Graph
$dept_counts_query = "SELECT department, COUNT(id) as count FROM employee_profiles WHERE department IS NOT NULL AND department != '' AND status='Active' GROUP BY department";
$dept_counts_result = mysqli_query($conn, $dept_counts_query);
$mapped_depts = [
    'Development' => ['count' => 0, 'color' => 'blue'],
    'Sales'       => ['count' => 0, 'color' => 'green'],
    'Accounts'    => ['count' => 0, 'color' => 'yellow'],
    'IT'          => ['count' => 0, 'color' => 'indigo'],
    'HR'          => ['count' => 0, 'color' => 'purple']
];

while ($r_dept = mysqli_fetch_assoc($dept_counts_result)) {
    $raw_dept = strtolower(trim($r_dept['department']));
    $count = (int)$r_dept['count'];
    if (strpos($raw_dept, 'dev') !== false || strpos($raw_dept, 'eng') !== false) { $mapped_depts['Development']['count'] += $count; }
    elseif (strpos($raw_dept, 'sale') !== false || strpos($raw_dept, 'market') !== false) { $mapped_depts['Sales']['count'] += $count; }
    elseif (strpos($raw_dept, 'acc') !== false || strpos($raw_dept, 'fin') !== false) { $mapped_depts['Accounts']['count'] += $count; }
    elseif (strpos($raw_dept, 'it') !== false) { $mapped_depts['IT']['count'] += $count; }
    elseif (strpos($raw_dept, 'hr') !== false || strpos($raw_dept, 'human') !== false) { $mapped_depts['HR']['count'] += $count; }
    else { $mapped_depts['IT']['count'] += $count; } 
}
$departments_list = [];
foreach($mapped_depts as $label => $data) {
    if($data['count'] > 0) {
        $departments_list[] = ['label' => $label, 'count' => $data['count'], 'color' => $data['color']];
    }
}

// =========================================================================
// 5. MONTHLY STATS & LATE HOURS
// =========================================================================
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$total_late_seconds = 0;

$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iii", $current_user_id, $current_month, $current_year);
$stat_stmt->execute();
$stat_res = $stat_stmt->get_result();

while ($stat_row = $stat_res->fetch_assoc()) {
    $st = $stat_row['status'];
    if (stripos($st, 'WFH') !== false) { 
        $stats_wfh++; 
    } elseif (stripos($st, 'Absent') !== false) { 
        $stats_absent++; 
    } elseif (stripos($st, 'Sick') !== false) { 
        $stats_sick++; 
    } else {
        if (!empty($stat_row['punch_in'])) {
            $expected_start_ts = strtotime($stat_row['date'] . ' ' . $shift_start_str);
            $actual_start_ts = strtotime($stat_row['punch_in']);
            
            if ($actual_start_ts > ($expected_start_ts + 60)) { 
                $stats_late++; 
                $total_late_seconds += ($actual_start_ts - $expected_start_ts);
            } else { 
                $stats_ontime++; 
            }
        } else { 
            $stats_absent++; 
        }
    }
}
$stat_stmt->close();

$late_hours = floor($total_late_seconds / 3600);
$late_minutes = floor(($total_late_seconds % 3600) / 60);
$late_time_str = $late_hours . 'h ' . $late_minutes . 'm';

$current_month_leaves = 0;
$curr_leave_sql = "SELECT leave_type, SUM(total_days) as days FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND MONTH(start_date) = ? AND YEAR(start_date) = ? GROUP BY leave_type";
$curr_leave_stmt = $conn->prepare($curr_leave_sql);
$curr_leave_stmt->bind_param("iii", $current_user_id, $current_month, $current_year);
$curr_leave_stmt->execute();
$curr_leave_res = $curr_leave_stmt->get_result();

while ($cl_row = $curr_leave_res->fetch_assoc()) {
    $current_month_leaves += floatval($cl_row['days']);
    if (stripos($cl_row['leave_type'], 'Sick') !== false) {
        $stats_sick += floatval($cl_row['days']);
    } else {
        $stats_absent += floatval($cl_row['days']); 
    }
}
$curr_leave_stmt->close();

// LEAVE BALANCE (HR Profile)
$base_leaves_per_month = 2;
$raw_join_date = $joining_date !== "Not Set" ? $row['joining_date'] : date('Y-m-01');
$calc_join_date = date('Y-m-d', strtotime($raw_join_date));
$display_join_month_year = date('M Y', strtotime($raw_join_date));

$total_earned_leaves = 2; // FIXED FOR HR EXECUTIVE

$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $current_user_id);
$leave_stmt->execute();
$leave_data = $leave_stmt->get_result()->fetch_assoc();
$leaves_taken = floatval($leave_data['taken'] ?? 0);

$leaves_remaining = $total_earned_leaves - $leaves_taken;
$display_leaves_remaining = ($leaves_remaining < 0) ? 0 : $leaves_remaining; 
$lop_days = ($leaves_remaining < 0) ? abs($leaves_remaining) : 0;


// LATE LOGINS & LOP TRACKER (COMPANY WIDE)
$late_list = [];
$late_q = $conn->query("SELECT ep.full_name, ep.department, a.punch_in FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE a.date = '$today' AND a.status = 'Late' ORDER BY a.punch_in DESC LIMIT 20");
if($late_q) { while($r = $late_q->fetch_assoc()) $late_list[] = $r; }

$lop_list = [];
// Added u.role to the query to identify the HR Executive
$lop_q = $conn->query("SELECT ep.user_id, ep.full_name, ep.department, ep.joining_date, u.role, SUM(lr.total_days) as taken_leaves FROM employee_profiles ep LEFT JOIN users u ON ep.user_id = u.id LEFT JOIN leave_requests lr ON ep.user_id = lr.user_id AND lr.status = 'Approved' GROUP BY ep.user_id");
if ($lop_q) {
    while ($r = $lop_q->fetch_assoc()) {
        $taken = floatval($r['taken_leaves']);
        
        // CHECK IF IT IS HR EXECUTIVE OR OTHER EMPLOYEES
        if (strtolower($r['role']) === 'hr executive' || stripos($r['department'], 'human resource') !== false) {
            $earned = 2; // FIXED 2 DAYS FOR HR
        } else {
            // NORMAL EMPLOYEES (Month based calculation)
            $jd = $r['joining_date'] ?: date('Y-m-01');
            $d1 = new DateTime($jd); $d1->modify('first day of this month'); 
            $d2 = new DateTime('now'); $d2->modify('first day of this month');
            $mw = ($d2 >= $d1) ? (($d1->diff($d2)->y * 12) + $d1->diff($d2)->m + 1) : 0;
            $earned = $mw * 2;
        }

        if ($taken > $earned) {
            $r['lop_days'] = $taken - $earned;
            $lop_list[] = $r;
        }
    }
}

// Active Jobs (Crash Proof Sorting by ID)
$jobs_cond = "WHERE hr.status IN ('Approved', 'In Progress')";
$jobs_query = "SELECT hr.*, u.name as requested_by FROM hiring_requests hr LEFT JOIN users u ON hr.manager_id = u.id $jobs_cond ORDER BY hr.id DESC LIMIT 5";
$jobs_res = mysqli_query($conn, $jobs_query);

// =========================================================================
// 6. STRICT RECENT REQUESTS ONLY (NOTIFICATIONS)
// =========================================================================
$all_notifications = [];

// LEAVES
$q_leaves_pend = "SELECT lr.*, ep.full_name FROM leave_requests lr JOIN employee_profiles ep ON lr.user_id = ep.user_id WHERE lr.status = 'Pending' ORDER BY lr.id DESC LIMIT 6";
$r_leaves_pend = mysqli_query($conn, $q_leaves_pend);
if($r_leaves_pend) {
    while($row = mysqli_fetch_assoc($r_leaves_pend)) {
        $all_notifications[] = [
            'title' => 'Leave Request',
            'message' => htmlspecialchars($row['full_name']) . ' requested ' . htmlspecialchars($row['leave_type']) . '.',
            'time' => $row['created_at'] ?? $row['start_date'] ?? date('Y-m-d H:i:s'), 
            'icon' => 'fa-plane-departure', 'color' => 'text-rose-600 bg-rose-100',
            'link' => '../leave_approval.php'
        ];
    }
}

// SHIFT SWAPS
$q_swaps_pend = "SELECT sr.*, ep.full_name FROM shift_swap_requests sr JOIN employee_profiles ep ON sr.user_id = ep.user_id WHERE sr.hr_approval = 'Pending' ORDER BY sr.id DESC LIMIT 6";
$r_swaps_pend = mysqli_query($conn, $q_swaps_pend);
if($r_swaps_pend) {
    while($row = mysqli_fetch_assoc($r_swaps_pend)) {
        $all_notifications[] = [
            'title' => 'Shift Swap Request',
            'message' => htmlspecialchars($row['full_name']) . ' requested a shift swap.',
            'time' => $row['created_at'] ?? $row['request_date'] ?? date('Y-m-d H:i:s'), 
            'icon' => 'fa-people-arrows', 'color' => 'text-blue-600 bg-blue-100',
            'link' => '../shift_swap_approval_hr.php'
        ];
    }
}

// WORK FROM HOME
$q_wfh_pend = "SELECT wr.*, ep.full_name FROM wfh_requests wr JOIN employee_profiles ep ON wr.user_id = ep.user_id WHERE wr.status = 'Pending' ORDER BY wr.id DESC LIMIT 6";
$r_wfh_pend = mysqli_query($conn, $q_wfh_pend);
if($r_wfh_pend) {
    while($row = mysqli_fetch_assoc($r_wfh_pend)) {
        $all_notifications[] = [
            'title' => 'WFH Request',
            'message' => htmlspecialchars($row['full_name']) . ' requested Work From Home.',
            'time' => $row['created_at'] ?? $row['start_date'] ?? date('Y-m-d H:i:s'), 
            'icon' => 'fa-house-laptop', 'color' => 'text-indigo-600 bg-indigo-100',
            'link' => '../wfh_management.php' // <-- LINK CORRECTED HERE
        ];
    }
}

usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 15); 

// =========================================================================
// 7. NEW ADDITION: RECENT NEW JOINERS 
// =========================================================================
$new_joiners = [];
$nj_q = "SELECT full_name, department, joining_date FROM employee_profiles WHERE status = 'Active' AND joining_date IS NOT NULL ORDER BY joining_date DESC LIMIT 4";
$nj_res = @mysqli_query($conn, $nj_q);
if($nj_res) {
    while($row = mysqli_fetch_assoc($nj_res)) {
        $new_joiners[] = $row;
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
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; overflow-x: hidden; }
        
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column;}
        .card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1; transform: translateY(-2px); transition: all 0.3s ease; }
        
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        
        .stat-badge { font-size: 1.5rem; font-weight: 900; line-height: 1; color: #1e293b; }

        /* The Grid Framework for exact square alignment with stretch */
        .dashboard-container { 
            display: grid; 
            grid-template-columns: repeat(1, minmax(0, 1fr)); 
            gap: 1.5rem; 
            align-items: stretch; /* Forces equal height columns */
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

        /* Progress ring SVG */
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($path_to_root . 'sidebars.php')) include($path_to_root . 'sidebars.php'); ?>
    <?php if (file_exists($path_to_root . 'header.php')) include($path_to_root . 'header.php'); ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">HR Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Welcome back, <b class="text-slate-700"><?php echo htmlspecialchars($employee_name); ?></b></p>
            </div>
            <div class="flex gap-3">
                <div class="bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='employee_management.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Total Employees</p><p class="stat-badge"><?php echo $total_employees; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='admin_attendance.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Company Present</p><p class="stat-badge text-emerald-600"><?php echo $all_present; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-check"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='admin_attendance.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Company Absent</p><p class="stat-badge text-red-500"><?php echo $all_absent; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-xmark"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:shadow-md transition" onclick="window.location.href='hr_task_management.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Pending HR Tasks</p><p class="stat-badge text-orange-500"><?php echo $total_hr_actions; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-lg"><i class="fa-solid fa-clipboard-list"></i></div>
            </div>
        </div>

        <div class="dashboard-container">

            <div class="flex flex-col gap-6 w-full"> 
                
                <?php include '../attendance_card.php'; ?>
            

                
             <div class="card border-blue-200 shrink-0">
                    <div class="p-6 flex flex-col h-full">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-briefcase text-blue-500"></i> Active Jobs
                            </h3>
                            <a href="jobs.php" class="bg-blue-50 text-blue-600 border border-blue-100 px-3 py-1 rounded-lg text-[10px] font-bold uppercase hover:bg-blue-100 transition">Manage</a>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-3 custom-scroll overflow-y-auto max-h-[350px] pr-2 flex-grow">
                            <?php if(!empty($jobs_res) && mysqli_num_rows($jobs_res) > 0): ?>
                                <?php while($req = mysqli_fetch_assoc($jobs_res)): 
                                    $j_dept = strtolower($req['department']);
                                    $j_icon = 'fa-briefcase'; 
                                    if(strpos($j_dept, 'dev') !== false || strpos($j_dept, 'eng') !== false) { $j_icon = 'fa-code'; }
                                    elseif(strpos($j_dept, 'sale') !== false || strpos($j_dept, 'market') !== false) { $j_icon = 'fa-chart-line'; }
                                    elseif(strpos($j_dept, 'hr') !== false || strpos($j_dept, 'human') !== false) { $j_icon = 'fa-users'; }
                                    elseif(strpos($j_dept, 'acc') !== false || strpos($j_dept, 'fin') !== false) { $j_icon = 'fa-file-invoice-dollar'; }
                                    
                                    $j_status_bg = 'bg-gray-100 text-gray-600';
                                    if ($req['status'] == 'Approved') $j_status_bg = 'bg-teal-100 text-teal-700 border border-teal-200';
                                    if ($req['status'] == 'In Progress') $j_status_bg = 'bg-blue-100 text-blue-700 border border-blue-200';
                                ?>
                                <div class="p-3 bg-white border border-gray-100 rounded-xl hover:shadow-md hover:border-blue-200 transition">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-sm font-bold text-slate-800 truncate pr-2"><i class="fa-solid <?= $j_icon ?> mr-1 text-blue-500"></i> <?= htmlspecialchars($req['job_title']); ?></p>
                                        <span class="text-[8px] font-bold px-2 py-0.5 <?= $j_status_bg ?> rounded uppercase shrink-0"><?= htmlspecialchars($req['status']) ?></span>
                                    </div>
                                    <div class="flex items-center justify-between mt-2">
                                        <p class="text-[10px] text-gray-500 font-medium truncate">By: <?= htmlspecialchars($req['requested_by'] ?? 'Unknown'); ?></p>
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 bg-slate-50 px-2 py-0.5 rounded border">
                                            <i class="fa-solid fa-users text-gray-400"></i> <span class="text-blue-600"><?= $req['vacancy_count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-slate-400">
                                    <i class="fa-solid fa-check-double text-3xl mb-2 text-emerald-400 opacity-80"></i>
                                    <p class="text-sm font-medium">No active job requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                    <div class="card flex flex-col flex-grow">
                    <div class="p-6 flex flex-col h-full">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Action Center</h3>
                            <span class="text-[10px] text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded uppercase border border-teal-100">Live Requests</span>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[350px] pr-2">
                            <?php if(!empty($all_notifications)): ?>
                                <?php foreach($all_notifications as $notif): ?>
                                <div class="flex gap-3 items-start border border-gray-100 p-3 rounded-xl hover:bg-slate-50 transition shadow-sm bg-white">
                                    <div class="w-8 h-8 rounded-full <?php echo $notif['color']; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                        <i class="fa-solid <?php echo $notif['icon']; ?>"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex justify-between items-start">
                                            <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                            <p class="text-[9px] text-gray-400 mt-1 shrink-0"><?php echo date("d M", strtotime($notif['time'])); ?></p>
                                        </div>
                                        <p class="text-[11px] text-gray-500 mt-1 line-clamp-2 leading-snug"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        
                                        <div class="mt-2 text-right">
                                            <a href="<?php echo $notif['link']; ?>" class="inline-flex items-center text-[9px] bg-white border border-gray-200 text-slate-600 font-bold px-3 py-1 rounded-full hover:bg-slate-100 transition shadow-sm">
                                                Review <i class="fa-solid fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-10 text-slate-400">
                                    <i class="fa-solid fa-check-double text-4xl mb-3 text-emerald-400 opacity-80"></i>
                                    <p class='text-sm font-medium text-slate-500'>All caught up!</p>
                                    <p class="text-[10px] mt-1">No pending requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6 w-full"> 
                
                <div class="card shrink-0">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center justify-between gap-6">
                            <div class="space-y-3.5 w-full pr-2">
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="text-xs text-gray-600 font-semibold">On Time</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_ontime; ?></span></div>
                                
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="text-xs text-gray-600 font-semibold">Late</span></div>
                                    <div class="text-right">
                                        <span class="font-bold text-slate-800 text-sm block"><?php echo $stats_late; ?></span>
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

                <div class="card border-rose-200 flex-grow">
                    <div class="p-6 flex flex-col h-full">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-clock-rotate-left text-rose-500"></i> Disciplinary Tracking
                            </h3>
                        </div>
                        
                        <div class="flex flex-col gap-4 flex-grow">
                            <div class="flex flex-col h-[180px] border border-amber-100 rounded-xl overflow-hidden shrink-0">
                                <div class="bg-amber-50 p-2 flex justify-between items-center border-b border-amber-100 shrink-0">
                                    <h4 class="text-[9px] font-black text-amber-700 uppercase tracking-widest">Late Today</h4>
                                    <span class="text-[9px] font-bold text-amber-600 bg-white px-2 py-0.5 rounded border border-amber-200"><?= count($late_list) ?></span>
                                </div>
                                <div class="p-2 space-y-2 custom-scroll overflow-y-auto flex-grow bg-white">
                                    <?php if(!empty($late_list)): foreach($late_list as $late): ?>
                                        <div class="flex items-center justify-between p-2 bg-amber-50/30 rounded border border-amber-100 hover:bg-amber-50 transition">
                                            <div class="min-w-0 pr-2">
                                                <p class="text-[11px] font-bold text-slate-800 truncate" title="<?= htmlspecialchars($late['full_name']) ?>"><?= htmlspecialchars($late['full_name']) ?></p>
                                                <p class="text-[8px] text-slate-500 truncate"><?= htmlspecialchars($late['department']) ?></p>
                                            </div>
                                            <span class="text-[9px] font-bold text-amber-700 shrink-0">
                                                <?= date('h:i A', strtotime($late['punch_in'])) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="text-center py-6 text-slate-400">
                                            <p class="text-[10px] font-medium">No late logins today.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex flex-col h-[250px] border border-rose-100 rounded-xl overflow-hidden shrink-0 mt-12">
                                <div class="bg-rose-50 p-2 flex justify-between items-center border-b border-rose-100 shrink-0">
                                    <h4 class="text-[9px] font-black text-rose-700 uppercase tracking-widest">LOP Limit Cross</h4>
                                    <span class="text-[9px] font-bold text-rose-600 bg-white px-2 py-0.5 rounded border border-rose-200"><?= count($lop_list) ?></span>
                                </div>
                                <div class="p-2 space-y-2 custom-scroll overflow-y-auto flex-grow bg-white">
                                    <?php if(!empty($lop_list)): foreach($lop_list as $lop): ?>
                                        <div class="flex items-center justify-between p-2 bg-rose-50/30 rounded border border-rose-100 hover:bg-rose-50 transition">
                                            <div class="min-w-0 pr-2">
                                                <p class="text-[11px] font-bold text-slate-800 truncate" title="<?= htmlspecialchars($lop['full_name']) ?>"><?= htmlspecialchars($lop['full_name']) ?></p>
                                                <p class="text-[8px] text-slate-500 truncate"><?= htmlspecialchars($lop['department']) ?></p>
                                            </div>
                                            <span class="text-[9px] font-bold text-rose-600 bg-rose-100/80 px-1.5 py-0.5 rounded shrink-0">
                                                <?= $lop['lop_days'] ?> LOP
                                            </span>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="text-center py-6 text-slate-400">
                                            <p class="text-[10px] font-medium">No LOP cases.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shrink-0 mt-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3 shrink-0">
                            <h3 class="font-bold text-lg text-slate-800">Department Overview</h3>
                            <span class="text-[10px] text-teal-700 bg-teal-50 px-2 py-1 rounded-lg font-bold border border-teal-100">Live</span>
                        </div>
                        <div class="w-full h-[220px] relative flex justify-center">
                            <div id="deptDonutChart" class="w-full h-full"></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6 w-full"> 
                <div class="card overflow-hidden shadow-sm border-slate-200 shrink-0">
                    <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-10 flex items-center gap-8 relative">
                        <div class="relative shrink-0">
                            <img src="<?php echo $profile_img; ?>" class="w-20 h-20 rounded-full border-2 border-white shadow-lg object-cover bg-white">
                            <div class="absolute bottom-0 right-0 w-5 h-5 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <div class="min-w-0 text-white">
                            <h2 class="font-black text-xl truncate"><?php echo htmlspecialchars($employee_name); ?></h2>
                            <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest truncate mt-0.5"><?php echo htmlspecialchars($employee_role); ?></p>
                            <span class="inline-block mt-2 bg-white/20 px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider backdrop-blur-sm border border-white/10">Verified HR</span>
                        </div>
                        <div class="absolute top-4 right-4 flex gap-2">
                            <a href="../settings.php" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition backdrop-blur-sm" title="Edit Profile">
                                <i class="fas fa-pencil-alt text-xs"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-4 border border-slate-100 p-3 rounded-xl bg-slate-50 hover:bg-white transition">
                                <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                    <i class="fa-solid fa-phone text-base"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Phone Number</p>
                                    <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($employee_phone); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-4 border border-slate-100 p-3 rounded-xl bg-slate-50 hover:bg-white transition">
                                <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                    <i class="fa-solid fa-envelope text-base"></i>
                                </div>
                                <div class="min-w-0 w-full">
                                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Email Address</p>
                                    <p class="text-sm font-bold text-slate-800 mt-0.5 truncate w-full" title="<?php echo htmlspecialchars($employee_email); ?>">
                                        <?php echo htmlspecialchars($employee_email); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="border-dashed border-gray-200 my-2">
                        
                        <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100">
                            <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2">Reporting To</p>
                            
                            <div class="flex items-center justify-between mb-2 gap-2">
                                <p class="text-sm font-bold text-indigo-900 break-words leading-tight">
                                    <?php echo htmlspecialchars($mgr_name); ?> 
                                </p>
                                <span class="text-[8px] font-black text-white bg-indigo-500 px-2 py-0.5 rounded tracking-wider shadow-sm shrink-0"><?php echo htmlspecialchars($mgr_role); ?></span>
                            </div>
                            
                            <div class="flex flex-col gap-1.5 mt-2">
                                <div class="flex items-center gap-2 text-xs text-indigo-700 font-medium">
                                    <i class="fa-solid fa-phone w-4 text-center opacity-70"></i> <?php echo htmlspecialchars($mgr_phone); ?>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-indigo-700 font-medium break-all leading-tight">
                                    <i class="fa-solid fa-envelope w-4 text-center opacity-70 mt-0.5"></i> <?php echo htmlspecialchars($mgr_email); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
               

            

                <div class="card flex flex-col shrink-0 mt-10">
                    <div class="p-6 flex flex-col h-full">
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
                        
                        <div class="relative flex justify-center flex-grow items-center w-full min-h-[200px]">
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

        </div> 
    </main>

    <script>
        lucide.createIcons();

        // -------------------------------------------------------------
        // Personal Time Tracker logic
        // -------------------------------------------------------------
        document.addEventListener('DOMContentLoaded', function() {
            const timerElement = document.getElementById('liveTimer');
            const progressRing = document.getElementById('progressRing');
            const breakTimerElement = document.getElementById('breakTimer');

            if(!timerElement) return;

            const isWorkRunning = timerElement.getAttribute('data-running') === 'true';
            const isBreakRunning = breakTimerElement ? breakTimerElement.getAttribute('data-break-running') === 'true' : false;
            
            let workTotalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
            let breakTotalSeconds = breakTimerElement ? (parseInt(breakTimerElement.getAttribute('data-break-total')) || 0) : 0;
            const startTime = new Date().getTime(); 

            function formatTime(totalSecs) {
                const h = Math.floor(totalSecs / 3600);
                const m = Math.floor((totalSecs % 3600) / 60);
                return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'); 
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
        });

        // AJAX ACTION HANDLERS
        function punchAction(action) {
            let btnId = '';
            if(action === 'punch_in') btnId = 'btnPunchIn';
            else if(action === 'punch_out') btnId = 'btnPunchOut';
            else if(action === 'take_break') btnId = 'btnBreak';
            else if(action === 'end_break') btnId = 'btnEndBreak';
            
            const btn = document.getElementById(btnId);
            if(btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner"></span>';
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

        // -------------------------------------------------------------
        // DEPARTMENT OVERVIEW DONUT CHART
        // -------------------------------------------------------------
        document.addEventListener('DOMContentLoaded', function () {
            var deptLabels = <?php echo json_encode(array_column($departments_list, 'label')); ?>;
            var deptCounts = <?php echo json_encode(array_column($departments_list, 'count')); ?>;
            var deptColors = <?php 
                $chartColors = [];
                foreach($departments_list as $d) {
                    if($d['color'] == 'blue') $chartColors[] = '#3b82f6';
                    elseif($d['color'] == 'green') $chartColors[] = '#22c55e';
                    elseif($d['color'] == 'yellow') $chartColors[] = '#eab308';
                    elseif($d['color'] == 'indigo') $chartColors[] = '#6366f1';
                    elseif($d['color'] == 'purple') $chartColors[] = '#a855f7';
                    else $chartColors[] = '#94a3b8';
                }
                echo json_encode($chartColors);
            ?>;

            if(deptCounts.length > 0) {
                var deptOptions = {
                    series: deptCounts,
                    chart: { type: 'donut', height: 230 },
                    labels: deptLabels,
                    colors: deptColors,
                    stroke: { show: true, colors: '#ffffff', width: 2 },
                    legend: { position: 'bottom', fontSize: '10px', markers: { radius: 12 } },
                    dataLabels: { enabled: false },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '70%',
                                labels: { show: true, name: { show: true, fontSize: '10px' }, value: { show: true, fontSize: '20px', fontWeight: 'bold' }, total: { show: true, showAlways: true, label: 'Total', fontSize: '12px' } }
                            }
                        }
                    }
                };
                new ApexCharts(document.querySelector("#deptDonutChart"), deptOptions).render();
            } else {
                document.querySelector("#deptDonutChart").innerHTML = "<p class='text-center text-xs text-gray-400 mt-10'>No department data to display.</p>";
            }
        });
        
        // GAUGE CHART
        const gaugeCtx = document.getElementById('gaugeChart')?.getContext('2d');
        if (gaugeCtx) {
            new Chart(gaugeCtx, {
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
    </script>
</body>
</html>