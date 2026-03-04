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
    header("Location: HR_executive_dashboard.php");
    exit();
}

// -------------------------------------------------------------------------
// 2. FETCH HR EXECUTIVE PROFILE DATA
// -------------------------------------------------------------------------
$employee_name = "HR Executive";
$employee_role = "Human Resources";
$employee_phone = "Not Set";
$employee_email = "Not Set";
$emp_id_code = "N/A";
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
// FETCH REPORTING MANAGER
// =========================================================================
$mgr_name = "Alice";
$mgr_phone = "+91 9876543210";
$mgr_email = "alice@company.com";
$mgr_role = "HR MANAGER";

if ($reporting_id > 0) {
    $hm_sql = "SELECT p.full_name, p.phone, u.email, u.role FROM employee_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
    $hm_stmt = $conn->prepare($hm_sql);
    $hm_stmt->bind_param("i", $reporting_id);
    $hm_stmt->execute();
    $hm_res = $hm_stmt->get_result();
    if ($hm_info = $hm_res->fetch_assoc()) {
        $mgr_name = !empty($hm_info['full_name']) ? $hm_info['full_name'] : "Alice";
        $mgr_phone = !empty($hm_info['phone']) ? $hm_info['phone'] : "+91 9876543210";
        $mgr_email = !empty($hm_info['email']) ? $hm_info['email'] : "alice@company.com";
        $mgr_role = strtoupper($hm_info['role'] ?? 'HR MANAGER'); 
    }
    $hm_stmt->close();
}

// =========================================================================
// 3. ADVANCED TIME TRACKER (TODAY'S HOURS)
// =========================================================================
$total_seconds_today = 0;
$break_seconds_today = 0;
$productive_seconds_today = 0;
$overtime_seconds_today = 0;
$today_punch_in = null;

$today_sql = "SELECT id, punch_in, punch_out, break_time FROM attendance WHERE user_id = ? AND date = ?";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("is", $current_user_id, $today);
$today_stmt->execute();
$today_res = $today_stmt->get_result();

if ($t_row = $today_res->fetch_assoc()) {
    if (!empty($t_row['punch_in'])) {
        $today_punch_in = $t_row['punch_in'];
        $in_time = strtotime($t_row['punch_in']);
        $out_time = !empty($t_row['punch_out']) ? strtotime($t_row['punch_out']) : time(); 
        
        $total_seconds_today = max(0, $out_time - $in_time);
        
        $brk_sql = "SELECT break_start, break_end FROM attendance_breaks WHERE attendance_id = ?";
        $b_stmt = $conn->prepare($brk_sql);
        $b_stmt->bind_param("i", $t_row['id']);
        $b_stmt->execute();
        $b_res = $b_stmt->get_result();
        while($b_row = $b_res->fetch_assoc()) {
            $b_start = strtotime($b_row['break_start']);
            $b_end = !empty($b_row['break_end']) ? strtotime($b_row['break_end']) : time();
            $break_seconds_today += max(0, $b_end - $b_start);
        }
        $b_stmt->close();
        
        if ($break_seconds_today == 0 && !empty($t_row['break_time'])) {
            $break_seconds_today = intval($t_row['break_time']) * 60;
        }

        $productive_seconds_today = max(0, $total_seconds_today - $break_seconds_today);
        $shift_seconds = $regular_shift_hours * 3600;
        $overtime_seconds_today = max(0, $productive_seconds_today - $shift_seconds);
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
$hours_worked_today = max(0, round($total_seconds_today / 3600, 1));

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
// 4. MONTHLY STATS & LATE HOURS
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

// =========================================================================
// 5. LEAVE BALANCE (CARRY-FORWARD)
// =========================================================================
$base_leaves_per_month = 2;
$raw_join_date = $joining_date !== "Not Set" ? $row['joining_date'] : date('Y-m-01');
$calc_join_date = date('Y-m-d', strtotime($raw_join_date));
$display_join_month_year = date('M Y', strtotime($raw_join_date));

$d1 = new DateTime($calc_join_date); $d1->modify('first day of this month'); 
$d2 = new DateTime('now'); $d2->modify('first day of this month');

$months_worked = 0;
if ($d2 >= $d1) {
    $interval = $d1->diff($d2);
    $months_worked = ($interval->y * 12) + $interval->m + 1; 
}
$total_earned_leaves = $months_worked * $base_leaves_per_month;

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

// --- FIXED: Pending Jobs Logic ---
// Adjusted JOIN to use manager_id instead of requested_by
$job_reqs = [];
$q_jobs_pending = "SELECT hr.*, u.name as requester_name 
                   FROM hiring_requests hr 
                   LEFT JOIN users u ON hr.manager_id = u.id 
                   WHERE hr.status = 'Pending' LIMIT 4";
$r_jobs_pending = mysqli_query($conn, $q_jobs_pending);
if($r_jobs_pending) {
    while($row = mysqli_fetch_assoc($r_jobs_pending)) {
        $job_reqs[] = $row;
    }
}

// --- FIXED: Active Jobs Logic ---
$jobs_cond = "WHERE hr.status IN ('Approved', 'In Progress')";
$jobs_query = "SELECT hr.*, u.name as requested_by 
               FROM hiring_requests hr 
               LEFT JOIN users u ON hr.manager_id = u.id 
               $jobs_cond ORDER BY hr.created_at DESC LIMIT 4";
$jobs_res = mysqli_query($conn, $jobs_query);


// =========================================================================
// 7. UNIFIED NOTIFICATIONS
// =========================================================================
$all_notifications = [];

$q_tickets = "SELECT id, ticket_code, subject, updated_at FROM tickets WHERE user_id = $current_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY updated_at DESC LIMIT 3";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 'id' => $row['id'],
            'title' => 'Ticket Solved: #' . ($row['ticket_code'] ?? $row['id']),
            'message' => 'IT Team has resolved your ticket: ' . htmlspecialchars($row['subject']),
            'time' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
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

$q_announcements = "SELECT * FROM announcements WHERE is_archived = 0 AND (target_audience = 'All' OR target_audience = '$user_role') ORDER BY created_at DESC LIMIT 10"; 
$r_announcements = mysqli_query($conn, $q_announcements);
if($r_announcements) {
    while($row = mysqli_fetch_assoc($r_announcements)) {
        $all_notifications[] = [
            'type' => 'announcement', 'title' => 'Announcement: ' . htmlspecialchars($row['title']),
            'message' => htmlspecialchars(substr($row['message'], 0, 50)) . '...',
            'time' => $row['created_at'], 'icon' => 'fa-bullhorn', 'color' => 'text-orange-600 bg-orange-100',
            'link' => '../view_announcements.php'
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
    <title>HR Executive Dashboard - SmartHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; overflow-x: hidden; }
        
        /* Strict Card Layout CSS - NO Stretching! */
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.3s ease; display: flex; flex-direction: column; }
        .card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1; transform: translateY(-2px); }
        .card-body { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; }
        
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        
        .stat-badge { font-size: 1.5rem; font-weight: 900; line-height: 1; color: #1e293b; }

        /* The Grid Logic: minmax allows columns to shrink/grow evenly without gaps */
        .dashboard-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); 
            gap: 1.5rem; 
            align-items: start; 
        }

        #mainContent { margin-left: 90px; width: calc(100% - 90px); transition: all 0.3s; padding: 24px; box-sizing: border-box; max-width: 1600px; margin-right: auto;}
        
        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px;}
        }
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
                            <h3 class="font-bold text-lg text-slate-800">Departments Overview</h3>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <?php foreach ($departments_list as $dept):
                                $pct = $total_employees > 0 ? round(($dept['count'] / $total_employees) * 100) : 0;
                            ?>
                            <div class="bg-gray-50 p-3 rounded-xl text-center border border-gray-200 hover:border-teal-300 transition-colors">
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

                <div class="card">
                    <div class="p-6 flex flex-col flex-grow">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                            <button class="text-[10px] text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded uppercase border border-teal-100">Live Feed</button>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[300px] pr-2">
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
                                <p class='text-sm text-gray-400 text-center py-4'>No new notifications.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6 w-full"> 
                
                <div class="card">
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
                    <div class="p-6 flex flex-col flex-grow">
                        <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
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

                        <div class="mt-auto">
                            <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-lg text-center transition shadow-md shadow-teal-200/50 text-sm">
                                <i class="fa-solid fa-plus mr-1.5"></i> APPLY FOR LEAVE
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card border-blue-200 flex-grow">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-briefcase text-blue-500"></i> Active Jobs
                            </h3>
                            <a href="jobs.php" class="bg-blue-50 text-blue-600 border border-blue-100 px-3 py-1 rounded-lg text-[10px] font-bold uppercase hover:bg-blue-100 transition">View All</a>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-3 custom-scroll overflow-y-auto max-h-[300px] pr-2">
                            <?php if(!empty($jobs_res) && mysqli_num_rows($jobs_res) > 0): ?>
                                <?php while($req = mysqli_fetch_assoc($jobs_res)): 
                                    $j_dept = strtolower($req['department']);
                                    $j_icon = 'fa-briefcase'; $j_icon_bg = 'bg-gray-100 text-gray-600';
                                    if(strpos($j_dept, 'dev') !== false || strpos($j_dept, 'eng') !== false) { $j_icon = 'fa-code'; $j_icon_bg = 'bg-blue-100 text-blue-600'; }
                                    elseif(strpos($j_dept, 'sale') !== false || strpos($j_dept, 'market') !== false) { $j_icon = 'fa-chart-line'; $j_icon_bg = 'bg-green-100 text-green-600'; }
                                    elseif(strpos($j_dept, 'hr') !== false || strpos($j_dept, 'human') !== false) { $j_icon = 'fa-users'; $j_icon_bg = 'bg-purple-100 text-purple-600'; }
                                    elseif(strpos($j_dept, 'acc') !== false || strpos($j_dept, 'fin') !== false) { $j_icon = 'fa-file-invoice-dollar'; $j_icon_bg = 'bg-yellow-100 text-yellow-600'; }
                                    
                                    $j_status_bg = 'bg-gray-100 text-gray-600';
                                    if ($req['status'] == 'Approved') $j_status_bg = 'bg-teal-100 text-teal-700';
                                    if ($req['status'] == 'In Progress') $j_status_bg = 'bg-blue-100 text-blue-700';
                                ?>
                                <div class="p-3 bg-slate-50 border border-gray-100 rounded-xl hover:shadow-md hover:border-blue-200 transition">
                                    <div class="flex justify-between items-start mb-1">
                                        <p class="text-sm font-bold text-slate-800 truncate pr-2"><i class="fa-solid <?= $j_icon ?> mr-1 text-blue-500"></i> <?= htmlspecialchars($req['job_title']); ?></p>
                                        <span class="text-[9px] font-bold px-2 py-0.5 <?= $j_status_bg ?> rounded uppercase"><?= htmlspecialchars($req['status']) ?></span>
                                    </div>
                                    <p class="text-[10px] text-gray-500 font-medium mb-2">Req by: <?= htmlspecialchars($req['requested_by'] ?? 'Unknown'); ?> • <?= htmlspecialchars($req['department']); ?></p>
                                    <div class="flex items-center gap-2 text-xs font-bold text-slate-600">
                                        <i class="fa-solid fa-users text-gray-400"></i> Openings: <span class="text-blue-600"><?= $req['vacancy_count']; ?></span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-slate-400">
                                    <i class="fa-solid fa-check-double text-2xl mb-2 text-emerald-400 opacity-80"></i>
                                    <p class="text-xs font-medium">No active job requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-span-12 lg:col-span-1 flex flex-col gap-6 w-full"> 
                
                <div class="card overflow-hidden shadow-sm border-slate-200">
                    <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-8 flex flex-col items-center text-center relative rounded-t-xl">
                        <div class="absolute top-4 right-4 bg-white/20 px-2 py-0.5 rounded backdrop-blur-sm">
                            <span class="text-[9px] text-white font-bold tracking-widest uppercase">Verified</span>
                        </div>
                        <div class="relative mb-3">
                            <img src="<?php echo $profile_img; ?>" class="w-28 h-28 rounded-full border-4 border-white shadow-xl object-cover bg-white">
                            <div class="absolute bottom-1 right-2 w-6 h-6 bg-green-400 border-[3px] border-white rounded-full shadow-sm"></div>
                        </div>
                        <h2 class="text-white font-black text-2xl leading-tight tracking-tight"><?php echo htmlspecialchars($employee_name); ?></h2>
                        <p class="text-teal-100 text-sm font-semibold mt-1 uppercase tracking-widest"><?php echo htmlspecialchars($employee_role); ?></p>
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
                        
                        <div class="grid grid-cols-2 gap-3 mt-2">
                            <div class="bg-emerald-50 p-3 rounded-xl border border-emerald-100 text-center">
                                <p class="text-[8px] text-emerald-600 font-bold uppercase tracking-wider mb-1">Joined Date</p>
                                <p class="text-sm font-black text-emerald-800 truncate"><?php echo $joining_date; ?></p>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-wider mb-1">Department</p>
                                <p class="text-sm font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></p>
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
                
                <div class="card border-blue-200 shrink-0">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-5 border-b border-blue-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2"><i class="fa-solid fa-stopwatch text-blue-500 text-lg"></i> Today's Time Tracker</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded border border-gray-100">Live</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-5">
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

                        <div class="w-full bg-slate-100 rounded-full h-3 flex overflow-hidden mb-5 border border-slate-200/60 shadow-inner">
                            <div class="bg-emerald-500 h-full transition-all" style="width: <?php echo $pct_prod; ?>%" title="Productive"></div>
                            <div class="bg-amber-400 h-full transition-all" style="width: <?php echo $pct_break; ?>%" title="Break"></div>
                            <div class="bg-blue-500 h-full transition-all" style="width: <?php echo $pct_ot; ?>%" title="Overtime"></div>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-2">Total Overtime This Month</p>
                            <div class="flex items-center justify-between bg-orange-50 border border-orange-100 p-3 rounded-lg">
                                <span class="text-lg font-black text-orange-600"><?php echo $overtime_this_month; ?> <span class="text-xs font-bold text-orange-500">Hrs</span></span>
                                <span class="text-[8px] bg-white text-orange-500 border border-orange-200 px-2 py-0.5 rounded font-black uppercase tracking-wider shadow-sm">Bonus Target</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card flex flex-col flex-grow">
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