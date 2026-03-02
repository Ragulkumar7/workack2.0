<?php
// TL/tl_dashboard.php

// 1. SESSION & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION
$db_path = __DIR__ . '/../include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    require_once '../include/db_connect.php'; 
}

date_default_timezone_set('Asia/Kolkata');
$tl_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$user_role = $_SESSION['role'] ?? 'Team Lead';

// =========================================================================
// ACTION: MARK TICKET AS VIEWED (DISMISS NOTIFICATION SAFELY)
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
// TL PROFILE & SHIFT TIMINGS
// =========================================================================
$tl_name = "Team Leader"; $tl_phone = "Not Set"; $tl_email = ""; $tl_dept = "General"; $tl_exp = "Fresher"; $tl_join = "Not Set";
$shift_timings = '09:00 AM - 06:00 PM';
$tl_emergency_contacts = '[]';

$profile_query = "SELECT u.username, u.email, p.* FROM users u LEFT JOIN employee_profiles p ON u.id = p.user_id WHERE u.id = ?";
$stmt_p = $conn->prepare($profile_query);
$stmt_p->bind_param("i", $tl_user_id);
$stmt_p->execute();
if ($row = $stmt_p->get_result()->fetch_assoc()) {
    $tl_name = $row['full_name'] ?? $row['username'];
    $tl_phone = $row['phone'] ?? $tl_phone;
    $tl_email = $row['email'] ?? $row['username'];
    $tl_dept = $row['department'] ?? $tl_dept;
    $tl_exp = $row['experience_label'] ?? $tl_exp;
    $tl_join = $row['joining_date'] ? date("d M Y", strtotime($row['joining_date'])) : "Not Set";
    $shift_timings = $row['shift_timings'] ?? $shift_timings;
    $tl_emergency_contacts = $row['emergency_contacts'] ?? '[]';
    
    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($tl_name) . "&background=1b5a5a&color=fff&size=128&bold=true";
    if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($row['profile_img'], 'http') ? $row['profile_img'] : '../assets/profiles/' . $row['profile_img'];
    }
}
$stmt_p->close();

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';

// =========================================================================
// TL'S OWN MONTHLY ATTENDANCE STATS
// =========================================================================
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;

$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$stat_stmt = mysqli_prepare($conn, $stat_sql);
mysqli_stmt_bind_param($stat_stmt, "iii", $tl_user_id, $current_month, $current_year);
mysqli_stmt_execute($stat_stmt);
$stat_res = mysqli_stmt_get_result($stat_stmt);

while ($row = mysqli_fetch_assoc($stat_res)) {
    if ($row['status'] == 'WFH') { $stats_wfh++; } 
    elseif ($row['status'] == 'Absent') { $stats_absent++; } 
    elseif (in_array($row['status'], ['Sick Leave', 'Sick'])) { $stats_sick++; } 
    else {
        if (!empty($row['punch_in'])) {
            $expected_start_ts = strtotime($row['date'] . ' ' . $shift_start_str);
            $actual_start_ts = strtotime($row['punch_in']);
            if ($actual_start_ts > ($expected_start_ts + 60)) {
                $stats_late++;
            } else {
                $stats_ontime++;
            }
        } else {
            $stats_absent++;
        }
    }
}

// =========================================================================
// TL'S OWN LEAVE CARRY-FORWARD LOGIC
// =========================================================================
$base_leaves_per_month = 2;
// Use original join date for calculation
$calc_join_date = ($tl_join !== "Not Set") ? date('Y-m-d', strtotime($tl_join)) : date('Y-m-01');
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
$leave_stmt->bind_param("i", $tl_user_id);
$leave_stmt->execute();
$leave_data = $leave_stmt->get_result()->fetch_assoc();
$leaves_taken = $leave_data['taken'] ?? 0;
$leaves_remaining = max(0, $total_earned_leaves - $leaves_taken);

// =========================================================================
// TEAM ATTENDANCE OVERVIEW
// =========================================================================
$res_team = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE reporting_to = $tl_user_id")->fetch_assoc();
$total_team = $res_team['total'] ?? 0;
$res_p = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE ep.reporting_to = $tl_user_id AND a.date = '$today' AND (a.status='On Time' OR a.status='WFH' OR a.status='Late')")->fetch_assoc();
$team_present = $res_p['cnt'] ?? 0;
$res_l = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE ep.reporting_to = $tl_user_id AND a.date = '$today' AND a.status='Late'")->fetch_assoc();
$team_late = $res_l['cnt'] ?? 0;
$team_absent = max(0, $total_team - $team_present);
$team_att_pct = ($total_team > 0) ? round(($team_present / $total_team) * 100) : 0;

// Fetch Team Members List
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

// =========================================================================
// PROJECTS & TASK PRIORITIES
// =========================================================================
$active_projects = [];
$proj_q = "SELECT project_name, progress FROM projects WHERE leader_id = ? AND status != 'Completed' LIMIT 4";
$stmt_proj = $conn->prepare($proj_q);
if ($stmt_proj) {
    $stmt_proj->bind_param("i", $tl_user_id);
    $stmt_proj->execute();
    $res_proj = $stmt_proj->get_result();
    while ($row = $res_proj->fetch_assoc()) { $active_projects[] = $row; }
    $stmt_proj->close();
}

// My Own Tasks
$task_sql = "SELECT * FROM personal_taskboard WHERE user_id = ? ORDER BY id DESC LIMIT 4";
$task_stmt = mysqli_prepare($conn, $task_sql);
mysqli_stmt_bind_param($task_stmt, "i", $tl_user_id);
mysqli_stmt_execute($task_stmt);
$tasks_result = mysqli_stmt_get_result($task_stmt);

// Task Priorities (from Managed Projects)
$high_tasks = 0; $med_tasks = 0; $low_tasks = 0;
$tp_q = "SELECT pt.priority, COUNT(*) as cnt FROM project_tasks pt JOIN projects p ON pt.project_id = p.id WHERE p.leader_id = ? AND pt.status != 'Completed' GROUP BY pt.priority";
$stmt_tp = $conn->prepare($tp_q);
if ($stmt_tp) {
    $stmt_tp->bind_param("i", $tl_user_id);
    $stmt_tp->execute();
    $res_tp = $stmt_tp->get_result();
    while ($row = $res_tp->fetch_assoc()) {
        if ($row['priority'] == 'High') $high_tasks = $row['cnt'];
        if ($row['priority'] == 'Medium') $med_tasks = $row['cnt'];
        if ($row['priority'] == 'Low') $low_tasks = $row['cnt'];
    }
    $stmt_tp->close();
}

// =========================================================================
// UNIFIED NOTIFICATIONS
// =========================================================================
$all_notifications = [];

$q_tickets = "SELECT id, ticket_code, subject, created_at FROM tickets WHERE user_id = $tl_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY created_at DESC LIMIT 3";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 'id' => $row['id'],
            'title' => 'Ticket Solved: #' . ($row['ticket_code'] ?? $row['id']),
            'message' => 'IT Team resolved your ticket: ' . htmlspecialchars($row['subject']),
            'time' => $row['created_at'], 'icon' => 'fa-check-double',
            'color' => 'text-green-600 bg-green-100',
            'link' => '?dismiss_ticket=' . $row['id']
        ];
    }
}

$q_own_leaves = "SELECT leave_type, status, start_date FROM leave_requests WHERE user_id = $tl_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 2";
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

$q_announcements = "SELECT * FROM announcements WHERE is_archived = 0 AND (target_audience = 'All' OR target_audience = '$user_role') ORDER BY created_at DESC LIMIT 10"; 
$r_announcements = mysqli_query($conn, $q_announcements);
if($r_announcements) {
    while($row = mysqli_fetch_assoc($r_announcements)) {
        $all_notifications[] = [
            'type' => 'announcement',
            'title' => 'Announcement: ' . htmlspecialchars($row['title']),
            'message' => htmlspecialchars(substr($row['message'], 0, 50)) . '...',
            'time' => $row['created_at'], 'icon' => 'fa-bullhorn',
            'color' => 'text-orange-600 bg-orange-100',
            'link' => '../view_announcements.php'
        ];
    }
}

// [BUG FIX]: Changed "created_at" to "start_date" as "created_at" does not exist in projects table
$q_new_proj = "SELECT project_name, start_date as created_at FROM projects WHERE leader_id = $tl_user_id ORDER BY start_date DESC LIMIT 2";
$r_new_proj = mysqli_query($conn, $q_new_proj);
if($r_new_proj) {
    while($row = mysqli_fetch_assoc($r_new_proj)) {
        $all_notifications[] = [
            'type' => 'project',
            'title' => 'New Project Assigned',
            'message' => 'You are leading: ' . htmlspecialchars($row['project_name']),
            'time' => $row['created_at'] . ' 09:00:00', 'icon' => 'fa-briefcase',
            'color' => 'text-blue-600 bg-blue-100',
            'link' => 'tl_projects.php'
        ];
    }
}

usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 15); 

// Meetings
$meet_result = mysqli_query($conn, "SELECT * FROM meetings WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 3");

$sidebarPath = __DIR__ . '/../sidebars.php';
$headerPath = __DIR__ . '/../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TL Dashboard - SmartHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --primary-teal: #1b5a5a; --bg-gray: #f8fafc; }
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
        .btn-teal:hover { background-color: #134040; }
        .dashboard-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; align-items: stretch; }
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 80px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .meeting-row-wrapper { position: relative; margin-bottom: 1.5rem; }
        .meeting-dot { position: absolute; left: 76px; top: 10px; width: 10px; height: 10px; border-radius: 50%; z-index: 10; border: 2px solid white; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .meeting-flex-container { display: flex; align-items: flex-start; gap: 24px; }
        .meeting-time-label { width: 68px; text-align: right; flex-shrink: 0; font-weight: 700; font-size: 12px; color: #64748b; padding-top: 4px; }
        .meeting-content-box { background-color: #f8fafc; padding: 12px; border-radius: 0.75rem; border: 1px solid #f1f5f9; flex-grow: 1; }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
    </style>
</head>
<body class="bg-slate-100">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent" class="p-6 lg:p-8 min-h-screen">
        <?php if (file_exists($headerPath)) include($headerPath); ?>

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">TL Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Welcome back, <b><?php echo htmlspecialchars($tl_name); ?></b></p>
            </div>
            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                <i class="fa-regular fa-calendar text-[#1b5a5a]"></i>
                <span class="text-sm font-bold text-slate-600"><?php echo date('d M Y'); ?></span>
            </div>
        </div>

        <div class="dashboard-container">

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <?php include '../attendance_card.php'; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                            <button class="text-[10px] text-[#1b5a5a] font-bold bg-[#eefcfd] px-2 py-1 rounded uppercase">Your Feed</button>
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
                                    <div class="w-2.5 h-2.5 rounded-full bg-[#1b5a5a]"></div>
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
                            <div class="relative"><div id="attendanceChart" class="w-32 h-32"></div></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">With Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-[#eefcfd] p-3 rounded-xl text-center border border-[#1b5a5a]/20">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Earned</p>
                                <p class="text-2xl font-bold text-[#1b5a5a]"><?php echo $total_earned_leaves; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center border border-blue-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Taken</p>
                                <p class="text-2xl font-bold text-blue-700"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center border border-green-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Left</p>
                                <p class="text-2xl font-bold <?php echo $leaves_remaining == 0 ? 'text-rose-600' : 'text-green-700'; ?>">
                                    <?php echo $leaves_remaining; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if($leaves_remaining == 0): ?>
                            <div class="bg-rose-50 border border-rose-200 rounded-lg p-2 mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                                <p class="text-xs font-medium text-rose-700">Monthly limit exceeded! Extra leaves are considered as LOP.</p>
                            </div>
                        <?php endif; ?>

                        <a href="../employee/leave_request.php" class="block w-full bg-[#1b5a5a] hover:bg-[#134040] text-white font-bold py-3 rounded-lg text-center transition shadow-lg shadow-[#1b5a5a]/30 mt-auto">
                            <i class="fa-solid fa-plus mr-2"></i> APPLY NEW LEAVE
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="card overflow-hidden">
                    <div class="bg-[#1b5a5a] p-8 flex flex-col items-center text-center">
                        <div class="relative mb-3">
                            <img src="<?php echo $profile_img; ?>" class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover">
                            <div class="absolute bottom-1 right-1 w-6 h-6 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <h2 class="text-white font-bold text-lg"><?php echo htmlspecialchars($tl_name); ?></h2>
                        <p class="text-[#eefcfd] text-sm mb-3"><?php echo htmlspecialchars($tl_dept); ?> Lead</p>
                        <span class="bg-white/20 text-white text-xs px-3 py-1 rounded-full font-bold">Verified Account</span>
                    </div>
                    <div class="card-body space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-[#eefcfd] flex items-center justify-center text-[#1b5a5a]">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Phone</p>
                                <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($tl_phone); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-[#eefcfd] flex items-center justify-center text-[#1b5a5a]">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Email</p>
                                <p class="text-sm font-semibold text-slate-800 truncate w-40" title="<?php echo htmlspecialchars($tl_email); ?>">
                                    <?php echo htmlspecialchars($tl_email); ?>
                                </p>
                            </div>
                        </div>
                        <hr class="border-dashed border-gray-200">
                        <div class="bg-green-50 p-3 rounded-lg flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check text-green-600"></i>
                                <span class="text-xs font-bold text-gray-600">Joined</span>
                            </div>
                            <span class="text-xs font-bold text-slate-800"><?php echo $tl_join; ?></span>
                        </div>

                        <div class="mt-6 pt-6 border-t border-dashed border-gray-200">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Professional Info</h4>
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Experience</p>
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($tl_exp); ?></p>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Department</p>
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($tl_dept); ?></p>
                                </div>
                            </div>
                            
                            <?php
                            $emergency = json_decode($tl_emergency_contacts, true);
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

            <div class="col-span-12 lg:col-span-4">
                <div class="card flex-grow">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Team Attendance</h3>
                            <a href="attendance_tl.php" class="text-[10px] text-[#1b5a5a] font-bold bg-[#eefcfd] px-2 py-1 rounded uppercase hover:bg-[#1b5a5a]/20 transition">View All</a>
                        </div>
                        <div class="flex items-center justify-between mt-2">
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
                        <div class="mt-6">
                            <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                <span>Team Strength: <?php echo $total_team; ?></span>
                                <span><?php echo $team_att_pct; ?>%</span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-[#1b5a5a] rounded-full" style="width: <?php echo $team_att_pct; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">My Managed Projects</h3>
                            <a href="tl_projects.php" class="text-[10px] bg-[#eefcfd] text-[#1b5a5a] font-bold px-2 py-1 rounded uppercase hover:bg-[#1b5a5a]/20 transition">View All</a>
                        </div>
                        <div class="space-y-4 custom-scroll overflow-y-auto max-h-[150px] pr-2">
                            <?php if(!empty($active_projects)): ?>
                                <?php foreach($active_projects as $proj): ?>
                                <div class="border border-gray-100 rounded-xl p-4 shadow-sm hover:border-[#1b5a5a]/30 transition bg-slate-50">
                                    <h4 class="font-bold text-sm text-slate-800 mb-2 truncate" title="<?php echo htmlspecialchars($proj['project_name']); ?>"><?php echo htmlspecialchars($proj['project_name']); ?></h4>
                                    <div class="flex justify-between text-[10px] font-black text-[#1b5a5a] mb-1 uppercase tracking-widest">
                                        <span>Progress</span>
                                        <span><?php echo $proj['progress']; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-[#1b5a5a] h-1.5 rounded-full" style="width: <?php echo $proj['progress']; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class='text-sm text-gray-400'>No active projects.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">My Personal Tasks</h3>
                            <a href="task_tl.php" class="text-[10px] bg-[#eefcfd] text-[#1b5a5a] font-bold px-2 py-1 rounded uppercase hover:bg-[#1b5a5a]/20 transition">Tasks Board</a>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[150px] pr-2">
                            <?php if(mysqli_num_rows($tasks_result) > 0) {
                                while($task = mysqli_fetch_assoc($tasks_result)): 
                                    $badge_bg = ($task['priority'] == 'High') ? 'bg-rose-100 text-rose-600' : (($task['priority'] == 'Medium') ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-600');
                                    $icon_class = ($task['status'] == 'completed') ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-regular fa-circle text-[#1b5a5a]';
                            ?>
                            <div class="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3">
                                    <i class="<?php echo $icon_class; ?>"></i>
                                    <div>
                                        <span class="text-sm font-medium text-slate-700 block w-32 truncate"><?php echo htmlspecialchars($task['title']); ?></span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded <?php echo $badge_bg; ?>"><?php echo $task['priority']; ?></span>
                                </div>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-400'>No personal tasks found.</p>"; } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-slate-800 text-lg mb-2">Project Tasks Priority</h3>
                        <div id="priorityDonutChart" class="flex justify-center my-4"></div>
                        <div class="flex justify-around mt-2 border-t pt-4 border-slate-100">
                            <div class="text-center"><span class="block text-red-500 font-black text-lg"><?php echo $high_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">High</span></div>
                            <div class="text-center"><span class="block text-amber-500 font-black text-lg"><?php echo $med_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">Medium</span></div>
                            <div class="text-center"><span class="block text-emerald-500 font-black text-lg"><?php echo $low_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">Low</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">My Team</h3>
                            <a href="team_member.php" class="text-[10px] bg-[#eefcfd] text-[#1b5a5a] font-bold px-2 py-1 rounded uppercase hover:bg-[#1b5a5a]/20 transition">View All</a>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[250px] pr-2">
                            <?php if(!empty($team_members)): ?>
                                <?php foreach($team_members as $member): 
                                    $m_name = $member['full_name'] ?: 'Unknown';
                                    $m_role = $member['designation'] ?: 'Employee';
                                    $m_status = $member['today_status'] ?: 'Not Logged In';
                                    
                                    $m_img = "https://ui-avatars.com/api/?name=".urlencode($m_name)."&background=random";
                                    if (!empty($member['profile_img']) && $member['profile_img'] !== 'default_user.png') {
                                        $m_img = str_starts_with($member['profile_img'], 'http') ? $member['profile_img'] : '../assets/profiles/' . $member['profile_img'];
                                    }

                                    $status_color = 'bg-slate-100 text-slate-500';
                                    if ($m_status == 'On Time') $status_color = 'bg-emerald-100 text-emerald-700';
                                    elseif ($m_status == 'Late') $status_color = 'bg-orange-100 text-orange-700';
                                    elseif ($m_status == 'Absent') $status_color = 'bg-rose-100 text-rose-700';
                                    elseif ($m_status == 'WFH') $status_color = 'bg-blue-100 text-blue-700';
                                ?>
                                <div class="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:bg-slate-50 transition">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $m_img; ?>" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($m_name); ?></p>
                                            <p class="text-[10px] text-slate-500 font-medium"><?php echo htmlspecialchars($m_role); ?></p>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-bold px-2 py-1 rounded uppercase tracking-wider <?php echo $status_color; ?>"><?php echo $m_status; ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-6 text-slate-400">
                                    <i class="fa-solid fa-users-slash text-3xl mb-2 opacity-50"></i>
                                    <p class="text-sm font-medium">No team members assigned.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                            <button class="text-xs text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold">Today</button>
                        </div>
                        <div class="meeting-timeline space-y-6">
                            <?php if($meet_result && mysqli_num_rows($meet_result) > 0) {
                                while($meet = mysqli_fetch_assoc($meet_result)): 
                                    $dot_color = ($meet['type_color']=='orange') ? 'bg-orange-500' : (($meet['type_color']=='teal') ? 'bg-[#1b5a5a]' : 'bg-yellow-500');
                            ?>
                            <div class="meeting-row-wrapper">
                                <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                                <div class="meeting-flex-container">
                                    <div class="meeting-time-label">
                                        <?php echo date("h:i A", strtotime($meet['meeting_time'])); ?>
                                    </div>
                                    <div class="meeting-content-box">
                                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></p>
                                        <p class="text-[10px] text-gray-500 mt-1"><?php echo htmlspecialchars($meet['department']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-400 pl-4'>No meetings scheduled today.</p>"; } ?>
                        </div>
                    </div>
                </div>
            </div>

        </div> 
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // My Attendance Chart
            var attOptions = {
                series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>],
                chart: { type: 'donut', width: 100, height: 100, sparkline: { enabled: true } },
                labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick'],
                colors: ['#1b5a5a', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                stroke: { width: 0 },
                tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
            };
            new ApexCharts(document.querySelector("#attendanceChart"), attOptions).render();

            // Task Priority Chart
            var prioOptions = {
                series: [<?php echo $high_tasks; ?>, <?php echo $med_tasks; ?>, <?php echo $low_tasks; ?>],
                labels: ['High', 'Medium', 'Low'],
                chart: { type: 'donut', height: 180 },
                colors: ['#ef4444', '#f59e0b', '#10b981'],
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '70%', labels: { show: true, name: {show: false}, value: { fontSize: '24px', fontWeight: 900, color: '#1e293b' }, total: { show: true, showAlways: true, label: 'Tasks', color: '#64748b' } } } } }
            };
            new ApexCharts(document.querySelector("#priorityDonutChart"), prioOptions).render();
        });
    </script>
</body>
</html>