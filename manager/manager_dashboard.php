<?php
// manager_dashboard.php

// 1. SESSION & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect if not logged in or not a Manager
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
// ACTION: MARK TICKET AS VIEWED (DISMISS NOTIFICATION SAFELY)
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
    
    // For Display
    $joining_date_display = $row['joining_date'] ? date("d M Y", strtotime($row['joining_date'])) : "Not Set";
    
    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($mgr_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : $path_to_root . 'assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';

// =========================================================================
// MANAGER'S OWN MONTHLY ATTENDANCE STATS
// =========================================================================
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;

$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$stat_stmt = mysqli_prepare($conn, $stat_sql);
mysqli_stmt_bind_param($stat_stmt, "iii", $mgr_user_id, $current_month, $current_year);
mysqli_stmt_execute($stat_stmt);
$stat_res = mysqli_stmt_get_result($stat_stmt);

while ($stat_row = mysqli_fetch_assoc($stat_res)) {
    if ($stat_row['status'] == 'WFH') { $stats_wfh++; } 
    elseif ($stat_row['status'] == 'Absent') { $stats_absent++; } 
    elseif (in_array($stat_row['status'], ['Sick Leave', 'Sick'])) { $stats_sick++; } 
    else {
        if (!empty($stat_row['punch_in'])) {
            $expected_start_ts = strtotime($stat_row['date'] . ' ' . $shift_start_str);
            $actual_start_ts = strtotime($stat_row['punch_in']);
            if ($actual_start_ts > ($expected_start_ts + 60)) { $stats_late++; } else { $stats_ontime++; }
        } else { $stats_absent++; }
    }
}

// =========================================================================
// MANAGER'S OWN LEAVE CARRY-FORWARD & LOP LOGIC
// =========================================================================
$base_leaves_per_month = 2;
$raw_join_date = $row['joining_date'] ?? null;

if (!empty($raw_join_date) && $raw_join_date != '0000-00-00') {
    $calc_join_date = date('Y-m-d', strtotime($raw_join_date));
    $display_join_month_year = date('M Y', strtotime($raw_join_date));
} else {
    $calc_join_date = date('Y-m-01');
    $display_join_month_year = date('M Y');
}

$d1 = new DateTime($calc_join_date); $d1->modify('first day of this month'); 
$d2 = new DateTime('now'); $d2->modify('first day of this month');

$months_worked = 0;
if ($d2 >= $d1) {
    $interval = $d1->diff($d2);
    $months_worked = ($interval->y * 12) + $interval->m + 1;
}
$total_earned_leaves = $months_worked * $base_leaves_per_month;

// Fetch ALL Approved leaves
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $mgr_user_id);
$leave_stmt->execute();
$leave_data = $leave_stmt->get_result()->fetch_assoc();
$leaves_taken = floatval($leave_data['taken'] ?? 0);
$leaves_remaining = $total_earned_leaves - $leaves_taken;
$lop_days = ($leaves_remaining < 0) ? abs($leaves_remaining) : 0;

// =========================================================================
// TEAM & DEPARTMENT OVERVIEW
// =========================================================================
$res_team = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE manager_id = $mgr_user_id OR reporting_to = $mgr_user_id")->fetch_assoc();
$total_team = $res_team['total'] ?? 0;

$res_p = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND a.date = '$today' AND (a.status='On Time' OR a.status='WFH' OR a.status='Late')")->fetch_assoc();
$team_present = $res_p['cnt'] ?? 0;
$res_l = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND a.date = '$today' AND a.status='Late'")->fetch_assoc();
$team_late = $res_l['cnt'] ?? 0;
$team_absent = max(0, $total_team - $team_present);
$team_att_pct = ($total_team > 0) ? round(($team_present / $total_team) * 100) : 0;

// WHO IS NOT LOGGED IN TODAY
$not_logged_in = [];
$nli_q = "SELECT user_id, full_name, designation, profile_img FROM employee_profiles 
          WHERE (manager_id = ? OR reporting_to = ?) 
          AND user_id NOT IN (SELECT user_id FROM attendance WHERE date = ?) 
          LIMIT 8";
$stmt_nli = $conn->prepare($nli_q);
$stmt_nli->bind_param("iis", $mgr_user_id, $mgr_user_id, $today);
$stmt_nli->execute();
$res_nli = $stmt_nli->get_result();
while($r = $res_nli->fetch_assoc()) { $not_logged_in[] = $r; }
$stmt_nli->close();

// =========================================================================
// TL PROJECTS DYNAMIC PROGRESS (Calculated based on employee task completion)
// =========================================================================
$tl_projects = [];
$q_tl_proj = "SELECT p.id as project_id, p.project_name, p.status, p.deadline, ep.full_name as tl_name, ep.profile_img,
              (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id) as total_tasks,
              (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id AND pt.status = 'Completed') as completed_tasks
              FROM projects p 
              JOIN employee_profiles ep ON p.leader_id = ep.user_id 
              WHERE (ep.manager_id = ? OR ep.reporting_to = ?) 
              ORDER BY p.id DESC LIMIT 5";
$stmt_tlp = $conn->prepare($q_tl_proj);
$stmt_tlp->bind_param("ii", $mgr_user_id, $mgr_user_id);
$stmt_tlp->execute();
$res_tlp = $stmt_tlp->get_result();
while($r = $res_tlp->fetch_assoc()) { 
    // Dynamic percentage calculation
    if ($r['total_tasks'] > 0) {
        $r['dynamic_progress'] = round(($r['completed_tasks'] / $r['total_tasks']) * 100);
    } else {
        $r['dynamic_progress'] = 0;
    }
    $tl_projects[] = $r; 
}
$stmt_tlp->close();

// =========================================================================
// ACTION NEEDED (Manager Approvals)
// =========================================================================
$action_requests = [];
// 1. Shift Swaps
$q_swaps = "SELECT ssr.id, ep.full_name, ssr.request_date, 'Shift Swap' as req_type 
            FROM shift_swap_requests ssr 
            JOIN employee_profiles ep ON ssr.user_id = ep.user_id 
            WHERE ssr.tl_approval = 'Approved' AND ssr.manager_approval = 'Pending' LIMIT 3";
$r_swaps = mysqli_query($conn, $q_swaps);
if($r_swaps) { while($r = mysqli_fetch_assoc($r_swaps)) { $action_requests[] = $r; } }

// 2. Leave Requests
$q_mleaves = "SELECT lr.id, ep.full_name, lr.created_at as request_date, 'Leave' as req_type 
              FROM leave_requests lr 
              JOIN employee_profiles ep ON lr.user_id = ep.user_id 
              WHERE (ep.manager_id = $mgr_user_id OR ep.reporting_to = $mgr_user_id) AND lr.status = 'Pending' LIMIT 3";
$r_mleaves = mysqli_query($conn, $q_mleaves);
if($r_mleaves) { while($r = mysqli_fetch_assoc($r_mleaves)) { $action_requests[] = $r; } }

// =========================================================================
// UNIFIED NOTIFICATIONS
// =========================================================================
$all_notifications = [];

// IT Tickets
$q_tickets = "SELECT id, ticket_code, subject, created_at FROM tickets WHERE user_id = $mgr_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY created_at DESC LIMIT 3";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 'id' => $row['id'],
            'title' => 'Ticket Solved: #' . ($row['ticket_code'] ?? $row['id']),
            'message' => 'IT Team resolved: ' . htmlspecialchars($row['subject']),
            'time' => $row['created_at'], 'icon' => 'fa-check-double', 'color' => 'text-green-600 bg-green-100',
            'link' => '?dismiss_ticket=' . $row['id']
        ];
    }
}

// Own Leave Approvals
$q_own_leaves = "SELECT leave_type, status, start_date FROM leave_requests WHERE user_id = $mgr_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 2";
$r_own_leaves = mysqli_query($conn, $q_own_leaves);
if($r_own_leaves) {
    while($row = mysqli_fetch_assoc($r_own_leaves)) {
        $all_notifications[] = [
            'type' => 'leave',
            'title' => 'Leave ' . $row['status'],
            'message' => 'Your ' . $row['leave_type'] . ' request was ' . strtolower($row['status']) . '.',
            'time' => $row['start_date'] . ' 09:00:00', 
            'icon' => ($row['status'] == 'Approved') ? 'fa-check-circle' : 'fa-times-circle',
            'color' => ($row['status'] == 'Approved') ? 'text-emerald-500 bg-emerald-100' : 'text-rose-500 bg-rose-100',
            'link' => '../employee/leave_request.php'
        ];
    }
}

// Announcements
$q_announcements = "SELECT * FROM announcements WHERE is_archived = 0 AND (target_audience = 'All' OR target_audience = '$user_role') ORDER BY created_at DESC LIMIT 10"; 
$r_announcements = mysqli_query($conn, $q_announcements);
if($r_announcements) {
    while($row = mysqli_fetch_assoc($r_announcements)) {
        $all_notifications[] = [
            'type' => 'announcement', 'title' => 'Announcement: ' . htmlspecialchars($row['title']),
            'message' => htmlspecialchars(substr($row['message'], 0, 45)) . '...',
            'time' => $row['created_at'], 'icon' => 'fa-bullhorn', 'color' => 'text-orange-600 bg-orange-100',
            'link' => $path_to_root . 'view_announcements.php'
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --primary-teal: #0d9488; --bg-gray: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-gray); color: #1e293b; margin: 0; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); padding: 25px 35px; transition: all 0.3s ease; }
        @media (max-width: 991px) { 
            #mainContent { margin-left: 0; width: 100%; padding: 70px 15px 15px 15px; } 
            .dashboard-container { grid-template-columns: 1fr; }
            .col-span-12, .lg\:col-span-4, .lg\:col-span-5, .lg\:col-span-3 { grid-column: span 12 !important; }
        }
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .card-body { padding: 1.5rem; flex-grow: 1; }
        .btn-teal { background-color: var(--primary-teal); color: white; padding: 10px 16px; border-radius: 10px; font-weight: 600; transition: 0.3s; text-align: center; display: inline-block; width: 100%; }
        .btn-teal:hover { background-color: #0f766e; }
        .dashboard-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; align-items: stretch; }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .stat-badge { font-size: 1.5rem; font-weight: 900; line-height: 1; color: #1e293b; }
    </style>
</head>
<body class="bg-slate-100">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>
    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <main id="mainContent">

        <div class="max-w-[1600px] mx-auto w-full">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 mt-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Manager Dashboard</h1>
                    <nav class="flex text-xs text-gray-500 mt-1 gap-2 items-center">
                        <i class="fa-solid fa-house text-teal-600"></i>
                        <span>/</span>
                        <span>Dashboard</span>
                        <span>/</span>
                        <span class="text-teal-700 font-medium">Overview</span>
                    </nav>
                </div>
                <div class="flex gap-2">
                    
                    <div class="bg-teal-50 border border-teal-100 text-teal-700 px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 shadow-sm">
                        <i class="fa-regular fa-calendar"></i> <?php echo date("d M Y"); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between hover:shadow-md transition cursor-pointer" onclick="window.location.href='manager_employee.php'">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Total Team</p><p class="stat-badge"><?php echo $total_team; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Present Today</p><p class="stat-badge text-emerald-600"><?php echo $team_present; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-check"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between hover:shadow-md transition cursor-pointer" onclick="window.scrollTo(0, document.body.scrollHeight);">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Absent Today</p><p class="stat-badge text-red-500"><?php echo $team_absent; ?></p></div>
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-lg"><i class="fa-solid fa-user-xmark"></i></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between hover:shadow-md transition">
                <div><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Pending Actions</p><p class="stat-badge text-orange-500"><?php echo count($action_requests); ?></p></div>
                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-lg"><i class="fa-solid fa-clipboard-list"></i></div>
            </div>
        </div>

        <div class="dashboard-container">

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                
                <?php include '../attendance_card.php'; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                            <button class="text-[10px] text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded uppercase">Your Feed</button>
                        </div>
                        <div class="space-y-4 custom-scroll overflow-y-auto max-h-[300px] pr-2">
                            <?php if(!empty($all_notifications)): ?>
                                <?php foreach($all_notifications as $notif): ?>
                                <div class="flex gap-3 items-start border-b border-gray-50 pb-3 last:border-0 hover:bg-slate-50 transition p-2 -mx-2 rounded relative">
                                    <div class="w-8 h-8 rounded-full <?php echo $notif['color']; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                        <i class="fa-solid <?php echo $notif['icon']; ?>"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-[10px] text-gray-400"><?php echo date("d M Y, h:i A", strtotime($notif['time'])); ?></p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        
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
                                <p class='text-sm text-gray-400 text-center py-4'>No new personal notifications.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-5 flex flex-col gap-6">
                
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">My Monthly Stats</h3>
                            <span class="text-xs font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded"><?php echo date('F Y'); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div>
                                    <span class="font-bold text-slate-700 w-8"><?php echo $stats_ontime; ?></span>
                                    <span class="text-sm text-gray-500">On Time</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                                    <span class="font-bold text-slate-700 w-8"><?php echo $stats_late; ?></span>
                                    <span class="text-sm text-gray-500">Late Attendance</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div>
                                    <span class="font-bold text-slate-700 w-8"><?php echo $stats_wfh; ?></span>
                                    <span class="text-sm text-gray-500">Work From Home</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                                    <span class="font-bold text-slate-700 w-8"><?php echo $stats_absent; ?></span>
                                    <span class="text-sm text-gray-500">Absent</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full bg-yellow-500"></div>
                                    <span class="font-bold text-slate-700 w-8"><?php echo $stats_sick; ?></span>
                                    <span class="text-sm text-gray-500">Sick Leave</span>
                                </div>
                            </div>
                            <div class="relative">
                                <div id="attendanceChart" class="w-32 h-32"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">With Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-teal-50 p-3 rounded-xl text-center border border-teal-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Earned</p>
                                <p class="text-2xl font-bold text-teal-700"><?php echo $total_earned_leaves; ?></p>
                                <p class="text-[8px] text-teal-600 mt-1 opacity-70">Since: <?php echo $display_join_month_year; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center border border-blue-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Taken</p>
                                <p class="text-2xl font-bold text-blue-700"><?php echo $leaves_taken; ?></p>
                                <p class="text-[8px] text-blue-600 mt-1 opacity-70">Approved only</p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center border border-green-100 relative overflow-hidden">
                                <p class="text-[10px] text-gray-500 font-bold uppercase relative z-10">Left</p>
                                <p class="text-2xl font-bold relative z-10 <?php echo $leaves_remaining < 0 ? 'text-rose-600' : 'text-green-700'; ?>">
                                    <?php echo $leaves_remaining; ?>
                                </p>
                                <?php if($leaves_remaining < 0): ?>
                                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-rose-500"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if($leaves_remaining < 0): ?>
                            <div class="bg-rose-50 border border-rose-200 rounded-lg p-2 mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                                <p class="text-[10px] font-bold text-rose-700">Leave limit exceeded! <b><?php echo $lop_days; ?> Days</b> considered as LOP.</p>
                            </div>
                        <?php endif; ?>

                        <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-3 rounded-lg text-center transition shadow-lg shadow-teal-200 mt-auto">
                            <i class="fa-solid fa-plus mr-2"></i> APPLY NEW LEAVE
                        </a>
                    </div>
                </div>

            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="card overflow-hidden">
                    <div class="bg-teal-700 p-8 flex flex-col items-center text-center">
                        <div class="relative mb-3">
                            <img src="<?php echo $profile_img; ?>" class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover">
                            <div class="absolute bottom-1 right-1 w-6 h-6 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <h2 class="text-white font-bold text-lg"><?php echo htmlspecialchars($mgr_name); ?></h2>
                        <p class="text-teal-200 text-sm mb-3"><?php echo htmlspecialchars($user_role); ?></p>
                        <span class="bg-white/20 text-white text-xs px-3 py-1 rounded-full font-bold">Verified Account</span>
                    </div>
                    <div class="card-body space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Phone</p>
                                <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($mgr_phone); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Email</p>
                                <p class="text-sm font-semibold text-slate-800 truncate w-40" title="<?php echo htmlspecialchars($mgr_email); ?>">
                                    <?php echo htmlspecialchars($mgr_email); ?>
                                </p>
                            </div>
                        </div>
                        <hr class="border-dashed border-gray-200">
                        <div class="bg-green-50 p-3 rounded-lg flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check text-green-600"></i>
                                <span class="text-xs font-bold text-gray-600">Joined</span>
                            </div>
                            <span class="text-xs font-bold text-slate-800"><?php echo $joining_date_display; ?></span>
                        </div>

                        <div class="mt-6 pt-6 border-t border-dashed border-gray-200">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Professional Info</h4>
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Experience</p>
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($mgr_exp); ?></p>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Department</p>
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($mgr_dept); ?></p>
                                </div>
                            </div>
                            
                            <?php
                            $emergency = json_decode($mgr_emergency_contacts, true);
                            if (!empty($emergency)): 
                                $primary = $emergency[0]; ?>
                                <div class="p-3 bg-red-50 rounded-xl border border-red-100">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fa-solid fa-heart-pulse text-red-500 text-[10px]"></i>
                                        <span class="text-[10px] font-bold text-red-700 uppercase">Emergency Contact</span>
                                    </div>
                                    <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($primary['name']); ?></p>
                                    <p class="text-[10px] text-slate-500"><?php echo htmlspecialchars($primary['phone']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-6">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">TL Projects Progress</h3>
                            <a href="manager_projects.php" class="text-[10px] bg-teal-50 text-teal-700 font-bold px-2 py-1 rounded uppercase hover:bg-teal-100 transition">Manage</a>
                        </div>
                        <div class="space-y-4 custom-scroll overflow-y-auto max-h-[350px] pr-2">
                            <?php if(!empty($tl_projects)): ?>
                                <?php foreach($tl_projects as $proj): 
                                    $p_status = $proj['status'];
                                    $progress_pct = intval($proj['dynamic_progress']);
                                    
                                    // Progress Bar Color Logic
                                    $prog_color = 'bg-blue-500';
                                    if($progress_pct >= 100) { $prog_color = 'bg-emerald-500'; }
                                    elseif($progress_pct < 30) { $prog_color = 'bg-orange-500'; }

                                    // TL Avatar resolution
                                    $tl_img = "https://ui-avatars.com/api/?name=".urlencode($proj['tl_name'])."&background=random";
                                    if (!empty($proj['profile_img']) && $proj['profile_img'] !== 'default_user.png') {
                                        $tl_img = str_starts_with($proj['profile_img'], 'http') ? $proj['profile_img'] : $path_to_root . 'assets/profiles/' . $proj['profile_img'];
                                    }
                                ?>
                                <div class="p-4 border border-gray-100 rounded-xl bg-slate-50 hover:border-teal-200 transition shadow-sm flex flex-col gap-2">
                                    <div class="flex justify-between items-start mb-1">
                                        <h4 class="font-bold text-sm text-slate-800 truncate pr-2 w-3/4" title="<?php echo htmlspecialchars($proj['project_name']); ?>">
                                            <?php echo htmlspecialchars($proj['project_name']); ?>
                                        </h4>
                                        <span class="text-[9px] font-bold px-2 py-1 rounded uppercase tracking-wider bg-white border border-gray-200 text-gray-600 flex-shrink-0">
                                            <?php echo $p_status; ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 mb-2">
                                        <img src="<?php echo $tl_img; ?>" class="w-5 h-5 rounded-full object-cover border border-slate-200">
                                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">TL: <?php echo htmlspecialchars($proj['tl_name']); ?></span>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-[9px] font-bold text-gray-500 mb-1 uppercase tracking-wider">
                                            <span>Progress (<?php echo $proj['completed_tasks'] . '/' . $proj['total_tasks']; ?> Tasks)</span>
                                            <span class="<?php echo str_replace('bg-', 'text-', $prog_color); ?>"><?php echo $progress_pct; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                            <div class="<?php echo $prog_color; ?> h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $progress_pct; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <?php if(!empty($proj['deadline'])): ?>
                                    <div class="mt-1 text-[9px] text-gray-400 font-medium text-right">
                                        Due: <?php echo date("d M Y", strtotime($proj['deadline'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-6 text-slate-400">
                                    <i class="fa-solid fa-layer-group text-3xl mb-2 opacity-50"></i>
                                    <p class="text-sm font-medium">No projects handled by your TLs currently.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-6">
                <div class="card border-orange-200">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Action Needed</h3>
                            <span class="bg-orange-100 text-orange-700 text-[10px] px-2 py-1 rounded font-bold uppercase">Approvals</span>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[350px] pr-2">
                            <?php if(!empty($action_requests)): ?>
                                <?php foreach($action_requests as $req): 
                                    $icon = $req['req_type'] == 'Leave' ? 'fa-plane-departure text-rose-500' : 'fa-people-arrows text-blue-500';
                                    $bg = $req['req_type'] == 'Leave' ? 'bg-rose-50 border-rose-100' : 'bg-blue-50 border-blue-100';
                                    
                                    // Redirect to specific pages based on type
                                    $link = $req['req_type'] == 'Leave' ? $path_to_root . 'employee/leave_request.php' : 'shift_swap_approval_manager.php';
                                ?>
                                <div class="p-3 rounded-lg border <?php echo $bg; ?> flex items-center justify-between transition hover:shadow-md">
                                    <div class="flex items-center gap-3">
                                        <i class="fa-solid <?php echo $icon; ?> text-lg"></i>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($req['full_name']); ?></p>
                                            <p class="text-[10px] text-slate-500 font-medium"><?php echo $req['req_type']; ?> Request</p>
                                        </div>
                                    </div>
                                    <a href="<?php echo $link; ?>" class="text-[10px] bg-white border px-2 py-1 rounded font-bold text-slate-600 hover:bg-slate-100">Review</a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-6 text-slate-400">
                                    <i class="fa-solid fa-mug-hot text-3xl mb-2 opacity-50"></i>
                                    <p class="text-sm font-medium">No pending approvals!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-12 gap-6 mb-6">
            <div class="col-span-12">
                <div class="card border-red-200">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                                <i class="fa-solid fa-user-clock text-red-500"></i> Not Logged In Today
                            </h3>
                            <span class="bg-red-50 text-red-600 px-3 py-1 rounded-full text-xs font-bold"><?php echo count($not_logged_in); ?> Absent / Late</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php if(!empty($not_logged_in)): ?>
                                <?php foreach($not_logged_in as $nli): 
                                    $n_img = "https://ui-avatars.com/api/?name=".urlencode($nli['full_name'])."&background=random";
                                    if (!empty($nli['profile_img']) && $nli['profile_img'] !== 'default_user.png') {
                                        $n_img = str_starts_with($nli['profile_img'], 'http') ? $nli['profile_img'] : $path_to_root . 'assets/profiles/' . $nli['profile_img'];
                                    }
                                ?>
                                <div class="flex items-center gap-3 p-3 bg-red-50/50 border border-red-100 rounded-xl hover:shadow-sm transition">
                                    <img src="<?php echo $n_img; ?>" class="w-10 h-10 rounded-full object-cover border border-red-200">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($nli['full_name']); ?>"><?php echo htmlspecialchars($nli['full_name']); ?></p>
                                        <p class="text-[10px] text-slate-500 font-medium truncate"><?php echo htmlspecialchars($nli['designation']); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full text-center py-6 text-slate-400">
                                    <i class="fa-solid fa-check-double text-3xl mb-2 text-emerald-400 opacity-80"></i>
                                    <p class="text-sm font-medium">Excellent! Everyone has logged in today.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Manager's Own Attendance Chart
            var attOptions = {
                series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>],
                chart: { type: 'donut', width: 100, height: 100, sparkline: { enabled: true } },
                labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick'],
                colors: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                stroke: { width: 0 },
                tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
            };
            new ApexCharts(document.querySelector("#attendanceChart"), attOptions).render();
        });
    </script>
</body>
</html>