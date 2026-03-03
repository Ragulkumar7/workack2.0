<?php
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$path_to_root = '../'; 

// 1. FIX TIMEZONE
date_default_timezone_set('Asia/Kolkata');
require_once '../include/db_connect.php'; 

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// =========================================================================
// ACTION: MARK TICKET AS VIEWED (DISMISS NOTIFICATION SAFELY)
// =========================================================================
// [PERFORMANCE FIX 1]: Disabled Database Lock. Running this on every page load 
// locks the tables, causing other pages to freeze. Run this directly in your DB once.
// $conn->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS user_read_status TINYINT(1) DEFAULT 0");

if (isset($_GET['dismiss_ticket'])) {
    $dismiss_id = intval($_GET['dismiss_ticket']);
    
    // Updates ONLY the read status, preserving the actual ticket 'status'
    $dismiss_query = "UPDATE tickets SET user_read_status = 1 WHERE id = ? AND user_id = ?";
    $stmt_dismiss = mysqli_prepare($conn, $dismiss_query);
    mysqli_stmt_bind_param($stmt_dismiss, "ii", $dismiss_id, $current_user_id);
    mysqli_stmt_execute($stmt_dismiss);
    
    // Redirect to clear URL parameters
    header("Location: employee_dashboard.php");
    exit();
}

$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$user_role = $_SESSION['role'] ?? 'Employee';

// [PERFORMANCE FIX 2]: Released Session Lock. 
// Closing the session immediately after reading the variables prevents the 
// browser from freezing when you try to navigate to 'leave_request.php'.
session_write_close();

// -------------------------------------------------------------------------
// 2. INITIALIZE VARIABLES
// -------------------------------------------------------------------------
$employee_name = "Employee"; $employee_role = "Role"; $employee_phone = "Not Set";
$employee_email = ""; $joining_date = "Not Set"; $profile_img = "";

// Statistics
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;

// -------------------------------------------------------------------------
// 3. DATABASE QUERIES
// -------------------------------------------------------------------------

// A. Fetch User Profile & Shift Timings
$sql_profile = "SELECT u.username, u.role, p.* FROM users u LEFT JOIN employee_profiles p ON u.id = p.user_id WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
$user_res = mysqli_stmt_get_result($stmt);
$user_info = mysqli_fetch_assoc($user_res);

if ($user_info) {
    $employee_name = $user_info['full_name'] ?? $user_info['username'];
    $employee_role = $user_info['designation'] ?? $user_info['role'];
    $employee_phone = $user_info['phone'] ?? 'Not Set';
    $employee_email = $user_info['email'] ?? $user_info['username'];
    $joining_date = $user_info['joining_date'] ? date("d M Y", strtotime($user_info['joining_date'])) : "Not Set";
    
    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($employee_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($user_info['profile_img']) && $user_info['profile_img'] !== 'default_user.png') {
        $profile_img = str_starts_with($user_info['profile_img'], 'http') ? $user_info['profile_img'] : '../assets/profiles/' . $user_info['profile_img'];
    }
}

// Prepare Shift Timing for Late Logic Comparison
$shift_timings = $user_info['shift_timings'] ?? '09:00 AM - 06:00 PM';
$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';

// B. Fetch Statistics - Dynamically Calculating Late vs On-Time
$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$stat_stmt = mysqli_prepare($conn, $stat_sql);
mysqli_stmt_bind_param($stat_stmt, "iii", $current_user_id, $current_month, $current_year);
mysqli_stmt_execute($stat_stmt);
$stat_res = mysqli_stmt_get_result($stat_stmt);

while ($row = mysqli_fetch_assoc($stat_res)) {
    if ($row['status'] == 'WFH') {
        $stats_wfh++;
    } elseif ($row['status'] == 'Absent') {
        $stats_absent++;
    } elseif (in_array($row['status'], ['Sick Leave', 'Sick'])) {
        $stats_sick++;
    } else {
        if (!empty($row['punch_in'])) {
            $expected_start_ts = strtotime($row['date'] . ' ' . $shift_start_str);
            $actual_start_ts = strtotime($row['punch_in']);
            
            // Allow 1 minute grace period
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

// C. Fetch Leave Balance (CARRY-FORWARD LOGIC FIXED)
$base_leaves_per_month = 2;
// Use original join date for calculation
$calc_join_date = ($user_info['joining_date'] ?? date('Y-m-01'));
$d1 = new DateTime($calc_join_date); $d1->modify('first day of this month'); 
$d2 = new DateTime('now'); $d2->modify('first day of this month');

$months_worked = 0;
if ($d2 >= $d1) {
    $interval = $d1->diff($d2);
    $months_worked = ($interval->y * 12) + $interval->m + 1; // Includes current month
}
$total_earned_leaves = $months_worked * $base_leaves_per_month;

// Calculate TOTAL leaves taken by this employee EVER
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = mysqli_prepare($conn, $leave_sql);
mysqli_stmt_bind_param($leave_stmt, "i", $current_user_id);
mysqli_stmt_execute($leave_stmt);
$leave_res = mysqli_stmt_get_result($leave_stmt);
if($leave_data = mysqli_fetch_assoc($leave_res)) {
    $leaves_taken = $leave_data['taken'] ?? 0;
} else {
    $leaves_taken = 0;
}
$leaves_remaining = $total_earned_leaves - $leaves_taken;


// D. Projects
$proj_sql = "
    SELECT p.id, p.project_name, p.deadline, 
           COUNT(pt.id) as total_tasks,
           SUM(CASE WHEN pt.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM projects p
    JOIN project_tasks pt ON p.id = pt.project_id
    WHERE FIND_IN_SET(?, pt.assigned_to) > 0
    GROUP BY p.id
    LIMIT 3
";
$proj_stmt = mysqli_prepare($conn, $proj_sql);
mysqli_stmt_bind_param($proj_stmt, "s", $employee_name);
mysqli_stmt_execute($proj_stmt);
$projects_result = mysqli_stmt_get_result($proj_stmt);

// E. Tasks (Self-assigned personal tasks)
$task_sql = "SELECT * FROM personal_taskboard WHERE user_id = ? ORDER BY id DESC LIMIT 5";
$task_stmt = mysqli_prepare($conn, $task_sql);
mysqli_stmt_bind_param($task_stmt, "i", $current_user_id);
mysqli_stmt_execute($task_stmt);
$tasks_result = mysqli_stmt_get_result($task_stmt);

// F. Skills
$skill_stmt = mysqli_prepare($conn, "SELECT * FROM employee_skills WHERE user_id = ?");
mysqli_stmt_bind_param($skill_stmt, "i", $current_user_id);
mysqli_stmt_execute($skill_stmt);
$skills_result = mysqli_stmt_get_result($skill_stmt);

// G. Performance
$perf_stmt = mysqli_prepare($conn, "SELECT * FROM employee_performance WHERE user_id = ?");
mysqli_stmt_bind_param($perf_stmt, "i", $current_user_id);
mysqli_stmt_execute($perf_stmt);
$perf_data = mysqli_fetch_assoc(mysqli_stmt_get_result($perf_stmt));
$perf_score = $perf_data['total_score'] ?? 0;
$perf_grade = $perf_data['performance_grade'] ?? 'N/A';

// H. UNIFIED PERSONAL NOTIFICATIONS
$all_notifications = [];

// 1. Solved IT Tickets Notification (FIXED: Checks for Resolved/Closed and User Read Status)
$q_tickets = "SELECT id, ticket_code, subject, updated_at FROM tickets WHERE user_id = $current_user_id AND status IN ('Resolved', 'Closed') AND user_read_status = 0 ORDER BY updated_at DESC LIMIT 5";
$r_tickets = mysqli_query($conn, $q_tickets);
if($r_tickets) {
    while($row = mysqli_fetch_assoc($r_tickets)) {
        $all_notifications[] = [
            'type' => 'ticket', 
            'id' => $row['id'],
            'title' => 'Ticket Solved: #' . ($row['ticket_code'] ?? $row['id']),
            'message' => 'IT Team has resolved your ticket: ' . htmlspecialchars($row['subject']),
            'time' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
            'icon' => 'fa-check-double',
            'color' => 'text-green-600 bg-green-100',
            'link' => '?dismiss_ticket=' . $row['id']
        ];
    }
}

// 2. Leave Notifications
$q_leaves = "SELECT leave_type, status, start_date FROM leave_requests WHERE user_id = $current_user_id AND status IN ('Approved', 'Rejected') ORDER BY id DESC LIMIT 3";
$r_leaves = mysqli_query($conn, $q_leaves);
if($r_leaves) {
    while($row = mysqli_fetch_assoc($r_leaves)) {
        $all_notifications[] = [
            'type' => 'leave',
            'title' => 'Leave ' . $row['status'],
            'message' => 'Your ' . $row['leave_type'] . ' request was ' . strtolower($row['status']) . '.',
            'time' => $row['start_date'] . ' 09:00:00', 
            'icon' => ($row['status'] == 'Approved') ? 'fa-check-circle' : 'fa-times-circle',
            'color' => ($row['status'] == 'Approved') ? 'text-emerald-500 bg-emerald-100' : 'text-rose-500 bg-rose-100',
            'link' => 'leave_request.php'
        ];
    }
}

// 3. Task Notifications (From TL)
$q_tasks = "SELECT * FROM project_tasks WHERE FIND_IN_SET('$employee_name', assigned_to) > 0 AND status != 'Completed' ORDER BY id DESC LIMIT 3";
$r_tasks = mysqli_query($conn, $q_tasks);
if($r_tasks) {
    while($row = mysqli_fetch_assoc($r_tasks)) {
        $all_notifications[] = [
            'type' => 'task',
            'title' => 'Pending Task',
            'message' => 'TL assigned you: ' . htmlspecialchars($row['task_title']),
            'time' => date('Y-m-d H:i:s'), 
            'icon' => 'fa-list-check',
            'color' => 'text-blue-600 bg-blue-100',
            'link' => 'task_tl.php'
        ];
    }
}

// 4. Announcements
$q_announcements = "SELECT * FROM announcements WHERE is_archived = 0 AND (target_audience = 'All' OR target_audience = '$user_role') ORDER BY created_at DESC LIMIT 10"; 
$r_announcements = mysqli_query($conn, $q_announcements);
if($r_announcements) {
    while($row = mysqli_fetch_assoc($r_announcements)) {
        $all_notifications[] = [
            'type' => 'announcement',
            'title' => 'Announcement: ' . htmlspecialchars($row['title']),
            'message' => htmlspecialchars(substr($row['message'], 0, 50)) . '...',
            'time' => $row['created_at'],
            'icon' => 'fa-bullhorn',
            'color' => 'text-orange-600 bg-orange-100',
            'link' => '../view_announcements.php'
        ];
    }
}

// Sort all combined notifications by Time Descending
usort($all_notifications, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Show latest 15 for the scrollable view
$all_notifications = array_slice($all_notifications, 0, 15); 

// MEETINGS FETCH
$meet_result = mysqli_query($conn, "SELECT * FROM meetings WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - <?php echo htmlspecialchars($employee_name); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; }
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .card-body { padding: 1.5rem; flex-grow: 1; }
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
        .dashboard-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; align-items: stretch; }
        
        #mainContent { margin-left: 100px; width: calc(100% - 100px); transition: all 0.3s; padding-top: 10px;}
        @media (max-width: 991px) {
            .dashboard-container { grid-template-columns: 1fr; }
            #mainContent { margin-left: 0; width: 100%; padding-top: 70px;}
            .col-span-3, .col-span-4, .col-span-5, .col-span-6, .col-span-12 { grid-column: span 12 !important; }
        }
    </style>
</head>
<body class="bg-slate-100">

    <?php include $path_to_root . 'sidebars.php'; ?>
    <?php include $path_to_root . 'header.php'; ?>

    <main id="mainContent" class="p-6 lg:p-8 min-h-screen">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Employee Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Welcome back, <b><?php echo htmlspecialchars($employee_name); ?></b></p>
            </div>
            <div class="flex gap-3">
                <div class="bg-white border border-gray-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar"></i> <?php echo date("d M Y"); ?>
                </div>
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
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
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
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">With Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-teal-50 p-3 rounded-xl text-center border border-teal-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Earned</p>
                                <p class="text-2xl font-bold text-teal-700"><?php echo $total_earned_leaves; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center border border-blue-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Taken</p>
                                <p class="text-2xl font-bold text-blue-700"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center border border-green-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Left</p>
                                <p class="text-2xl font-bold <?php echo $leaves_remaining < 0 ? 'text-rose-600' : 'text-green-700'; ?>">
                                    <?php echo $leaves_remaining; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if($leaves_remaining < 0): ?>
                            <div class="bg-rose-50 border border-rose-200 rounded-lg p-2 mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                                <p class="text-[10px] font-bold text-rose-700">Monthly limit exceeded! Extra leaves are considered as LOP.</p>
                            </div>
                        <?php endif; ?>

                        <a href="leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-3 rounded-lg text-center transition shadow-lg shadow-teal-200 mt-auto">
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
                        <h2 class="text-white font-bold text-lg"><?php echo htmlspecialchars($employee_name); ?></h2>
                        <p class="text-teal-200 text-sm mb-3"><?php echo htmlspecialchars($employee_role); ?></p>
                        <span class="bg-white/20 text-white text-xs px-3 py-1 rounded-full font-bold">Verified Account</span>
                    </div>
                    <div class="card-body space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Phone</p>
                                <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($employee_phone); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Email</p>
                                <p class="text-sm font-semibold text-slate-800 truncate w-40" title="<?php echo htmlspecialchars($employee_email); ?>">
                                    <?php echo htmlspecialchars($employee_email); ?>
                                </p>
                            </div>
                        </div>
                        <hr class="border-dashed border-gray-200">
                        <div class="bg-green-50 p-3 rounded-lg flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check text-green-600"></i>
                                <span class="text-xs font-bold text-gray-600">Joined</span>
                            </div>
                            <span class="text-xs font-bold text-slate-800"><?php echo $joining_date; ?></span>
                        </div>

                        <div class="mt-6 pt-6 border-t border-dashed border-gray-200">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Professional Info</h4>
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Experience</p>
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($user_info['experience_label'] ?? 'Fresher'); ?></p>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Department</p>
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($user_info['department'] ?? 'General'); ?></p>
                                </div>
                            </div>
                            
                            <?php
                            $emergency = json_decode($user_info['emergency_contacts'] ?? '[]', true);
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
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Involved Projects</h3>
                        </div>
                        <div class="space-y-4 custom-scroll overflow-y-auto max-h-[300px] pr-2">
                            <?php if(mysqli_num_rows($projects_result) > 0) {
                                while($proj = mysqli_fetch_assoc($projects_result)): 
                                $pct = ($proj['total_tasks'] > 0) ? round(($proj['completed_tasks'] / $proj['total_tasks']) * 100) : 0;
                            ?>
                            <div class="border border-gray-100 rounded-xl p-4 shadow-sm hover:border-teal-200 transition">
                                <h4 class="font-bold text-sm text-slate-800 mb-1"><?php echo htmlspecialchars($proj['project_name']); ?></h4>
                                <p class="text-[10px] text-gray-400 mb-2">Deadline: <?php echo date("d M Y", strtotime($proj['deadline'])); ?></p>
                                <div class="flex justify-between text-xs font-bold text-teal-600 mb-1">
                                    <span><?php echo $proj['completed_tasks'] . '/' . $proj['total_tasks']; ?> My Tasks Done</span>
                                    <span><?php echo $pct; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="bg-teal-600 h-1.5 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-500'>No active projects assigned.</p>"; } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">My Tasks</h3>
                            <a href="task_tl.php" class="text-[10px] bg-teal-50 text-teal-700 font-bold px-2 py-1 rounded uppercase hover:bg-teal-100 transition">TL Tasks Board</a>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[300px] pr-2">
                            <?php if(mysqli_num_rows($tasks_result) > 0) {
                                while($task = mysqli_fetch_assoc($tasks_result)): 
                                    $badge_bg = ($task['priority'] == 'High') ? 'bg-rose-100 text-rose-600' : (($task['priority'] == 'Medium') ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-600');
                                    $icon_class = ($task['status'] == 'completed') ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-regular fa-circle text-teal-600';
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
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Performance</h3>
                            <span class="text-xs bg-slate-100 px-2 py-1 rounded font-bold text-gray-500">2026</span>
                        </div>
                        <div class="flex items-center gap-4 mb-2">
                            <span class="text-4xl font-black text-slate-800"><?php echo $perf_score; ?>%</span>
                            <span class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded-full flex items-center gap-1">
                                <i class="fa-solid fa-arrow-up"></i> Grade: <?php echo $perf_grade; ?>
                            </span>
                        </div>
                        <div id="perfChart"></div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-slate-800 text-lg mb-4">Skills</h3>
                        <div class="space-y-4">
                            <?php if(mysqli_num_rows($skills_result) > 0) { 
                                while($skill = mysqli_fetch_assoc($skills_result)): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-100 bg-slate-50/50 rounded-xl hover:border-teal-200 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-1.5 h-8 rounded-full" style="background-color: <?php echo $skill['color_hex']; ?>"></div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($skill['skill_name']); ?></p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-slate-800"><?php echo $skill['proficiency']; ?>%</span>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-400'>No skills added.</p>"; } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-6">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                            <button class="text-xs text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold">Today</button>
                        </div>
                        <div class="meeting-timeline space-y-6">
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
            // Attendance Donut Chart
            var attOptions = {
                series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>],
                chart: { type: 'donut', width: 100, height: 100, sparkline: { enabled: true } },
                labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick'],
                colors: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                stroke: { width: 0 },
                tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
            };
            new ApexCharts(document.querySelector("#attendanceChart"), attOptions).render();

            // Performance Area Chart
            var perfOptions = {
                series: [{ name: 'Score', data: [65, 72, 78, 85, 92, <?php echo $perf_score; ?>] }],
                chart: { type: 'area', height: 130, toolbar: { show: false }, sparkline: { enabled: true } },
                colors: ['#0d9488'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
                tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
            };
            new ApexCharts(document.querySelector("#perfChart"), perfOptions).render();
        });
    </script>
</body>
</html>