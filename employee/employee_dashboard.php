<?php
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. FIX TIMEZONE (Crucial for correct punch times)
date_default_timezone_set('Asia/Kolkata');

// Database Connection
require_once '../include/db_connect.php'; 

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// -------------------------------------------------------------------------
// 2. INITIALIZE ALL VARIABLES (Prevents "Undefined Variable" Errors)
// -------------------------------------------------------------------------
$employee_name = "Employee";
$employee_role = "Role";
$employee_phone = "Not Set";
$employee_email = "";
$joining_date = "Not Set";
$profile_img = "";
$attendance_record = null;
$total_hours_today = "00:00:00";
$display_punch_in = "--:--";
$total_seconds_worked = 0;

// Stats Counters
$stats_ontime = 0;
$stats_late = 0;
$stats_wfh = 0;
$stats_absent = 0;
$stats_sick = 0;

// Leave Balance Defaults
$leaves_total = 16;
$leaves_taken = 0;
$leaves_remaining = 16;

// -------------------------------------------------------------------------
// 3. DATABASE QUERIES
// -------------------------------------------------------------------------

// A. Fetch User Profile
// 2. FIX SQL QUERY: Added department, experience_label, emergency_contacts
$sql_profile = "SELECT u.username, u.role, p.full_name, p.phone, p.joining_date, p.designation, p.email, p.profile_img, 
                p.department, p.experience_label, p.emergency_contacts 
                FROM users u 
                LEFT JOIN employee_profiles p ON u.id = p.user_id 
                WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
$user_res = mysqli_stmt_get_result($stmt);

if ($user_info = mysqli_fetch_assoc($user_res)) {
    $employee_name = $user_info['full_name'] ?? $user_info['username'];
    $employee_role = $user_info['designation'] ?? $user_info['role'];
    $employee_phone = $user_info['phone'] ?? '+1 234 567 890';
    $employee_email = $user_info['email'] ?? $user_info['username'];
    $joining_date = $user_info['joining_date'] ? date("d M Y", strtotime($user_info['joining_date'])) : "Not Set";
    
    // Generate Avatar URL if no image
    if(empty($user_info['profile_img'])) {
        $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($employee_name) . "&background=ffffff&color=0d9488&size=128&bold=true";
    } else {
        $profile_img = $user_info['profile_img'];
    }
}

// B. Attendance Logic (Punch In/Out)
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'punch_in' && !$attendance_record) {
        $punch_in_time = date('Y-m-d H:i:s');
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "iss", $current_user_id, $punch_in_time, $today);
        mysqli_stmt_execute($ins_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    } elseif ($_POST['action'] == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        $punch_out_time = date('Y-m-d H:i:s');
        
        // Calculate Production Hours
        $start = new DateTime($attendance_record['punch_in']);
        $end = new DateTime($punch_out_time);
        $diff = $start->diff($end);
        $hours = $diff->h + ($diff->i / 60);

        $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
        $upd_stmt = mysqli_prepare($conn, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, "sdi", $punch_out_time, $hours, $attendance_record['id']);
        mysqli_stmt_execute($upd_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }
}

// Calculate Display Time
if ($attendance_record) {
    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    
    $start_t = new DateTime($attendance_record['punch_in']);
    $end_t = ($attendance_record['punch_out']) ? new DateTime($attendance_record['punch_out']) : new DateTime();
    
    $diff = $start_t->diff($end_t);
    $total_hours_today = $diff->format('%H:%I:%S');
    $total_seconds_worked = ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
}

// C. Fetch Statistics
$stat_sql = "SELECT status, COUNT(*) as count FROM attendance WHERE user_id = ? GROUP BY status";
$stat_stmt = mysqli_prepare($conn, $stat_sql);
mysqli_stmt_bind_param($stat_stmt, "i", $current_user_id);
mysqli_stmt_execute($stat_stmt);
$stat_res = mysqli_stmt_get_result($stat_stmt);
while ($row = mysqli_fetch_assoc($stat_res)) {
    if ($row['status'] == 'On Time') $stats_ontime = $row['count'];
    if ($row['status'] == 'Late') $stats_late = $row['count'];
    if ($row['status'] == 'WFH') $stats_wfh = $row['count'];
    if ($row['status'] == 'Absent') $stats_absent = $row['count'];
    if ($row['status'] == 'Sick Leave' || $row['status'] == 'Sick') $stats_sick = $row['count'];
}

// D. Fetch Leave Balance
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = mysqli_prepare($conn, $leave_sql);
mysqli_stmt_bind_param($leave_stmt, "i", $current_user_id);
mysqli_stmt_execute($leave_stmt);
$leave_res = mysqli_stmt_get_result($leave_stmt);
if($leave_data = mysqli_fetch_assoc($leave_res)) {
    $leaves_taken = $leave_data['taken'] ?? 0;
    $leaves_remaining = $leaves_total - $leaves_taken;
}

// E. Projects
$proj_sql = "SELECT * FROM projects WHERE leader_id = ? OR leader_id IS NOT NULL LIMIT 2";
$proj_stmt = mysqli_prepare($conn, $proj_sql);
mysqli_stmt_bind_param($proj_stmt, "i", $current_user_id);
mysqli_stmt_execute($proj_stmt);
$projects_result = mysqli_stmt_get_result($proj_stmt);

// F. Tasks (Personal Taskboard)
$task_sql = "SELECT * FROM personal_taskboard WHERE user_id = ? ORDER BY id DESC LIMIT 5";
$task_stmt = mysqli_prepare($conn, $task_sql);
mysqli_stmt_bind_param($task_stmt, "i", $current_user_id);
mysqli_stmt_execute($task_stmt);
$tasks_result = mysqli_stmt_get_result($task_stmt);

// G. Skills
$skill_sql = "SELECT * FROM employee_skills WHERE user_id = ?";
$skill_stmt = mysqli_prepare($conn, $skill_sql);
mysqli_stmt_bind_param($skill_stmt, "i", $current_user_id);
mysqli_stmt_execute($skill_stmt);
$skills_result = mysqli_stmt_get_result($skill_stmt);

// H. Performance
$perf_sql = "SELECT * FROM employee_performance WHERE user_id = ?";
$perf_stmt = mysqli_prepare($conn, $perf_sql);
mysqli_stmt_bind_param($perf_stmt, "i", $current_user_id);
mysqli_stmt_execute($perf_stmt);
$perf_data = mysqli_fetch_assoc(mysqli_stmt_get_result($perf_stmt));
$perf_score = $perf_data['total_score'] ?? 0;
$perf_grade = $perf_data['performance_grade'] ?? 'N/A';

// I. Notifications & Meetings
$notif_result = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 3");
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
        body { 
            background-color: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b; 
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%; 
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 1.5rem;
            flex-grow: 1;
        }

        /* Progress Ring for Punch In */
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        /* Timeline for Meetings - FIXED ALIGNMENT */
        .meeting-timeline { 
            position: relative; 
        }

        /* The vertical line - fixed at exactly 80px from the left */
        .meeting-timeline::before { 
            content: ''; 
            position: absolute; 
            left: 80px; 
            top: 0; 
            bottom: 0; 
            width: 2px; 
            background: #e2e8f0; 
        }

        /* Individual row wrapper */
        .meeting-row-wrapper {
            position: relative;
            margin-bottom: 1.5rem; 
        }

        /* The dot sits exactly on the line */
        .meeting-dot { 
            position: absolute; 
            left: 76px; 
            top: 10px; 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            z-index: 10; 
            border: 2px solid white; 
            box-shadow: 0 0 0 1px rgba(0,0,0,0.05);
        }

        /* Flex container for the content */
        .meeting-flex-container {
            display: flex;
            align-items: flex-start;
            gap: 24px; 
        }

        /* Time label - fixed width to stay strictly to the left of the 80px line */
        .meeting-time-label { 
            width: 68px; 
            text-align: right; 
            flex-shrink: 0; 
            font-weight: 700; 
            font-size: 12px;
            color: #64748b; 
            padding-top: 4px;
        }

        /* Content box */
        .meeting-content-box {
            background-color: #f8fafc;
            padding: 12px;
            border-radius: 0.75rem;
            border: 1px solid #f1f5f9;
            flex-grow: 1;
        }

        /* Scrollbars */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }

        /* Dashboard Grid System */
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            align-items: stretch; 
        }

        /* Sidebar Adjustment */
        #mainContent {
            margin-left: 90px;
            width: calc(100% - 90px);
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .dashboard-container { grid-template-columns: 1fr; }
            #mainContent { margin-left: 0; width: 100%; }
            .col-span-3, .col-span-4, .col-span-5, .col-span-6 { grid-column: span 12 !important; }
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

            <div class="col-span-12 lg:col-span-3">
                <div class="card overflow-hidden">
                    <div class="bg-teal-700 p-8 flex flex-col items-center text-center">
                        <div class="relative mb-3">
                            <img src="<?php echo $profile_img; ?>" class="w-24 h-24 rounded-full border-4 border-white shadow-lg">
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
                            <div>
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

            <div class="col-span-12 lg:col-span-5 flex flex-col gap-6">
                
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                            <span class="text-xs font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded">2026</span>
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
                        <h3 class="font-bold text-slate-800 text-lg mb-4">Leave Balance</h3>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-teal-50 p-3 rounded-xl text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Total</p>
                                <p class="text-2xl font-bold text-teal-700"><?php echo $leaves_total; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Taken</p>
                                <p class="text-2xl font-bold text-blue-700"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Left</p>
                                <p class="text-2xl font-bold text-green-700"><?php echo $leaves_remaining; ?></p>
                            </div>
                        </div>
                        <a href="leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-3 rounded-lg text-center transition shadow-lg shadow-teal-200">
                            <i class="fa-solid fa-plus mr-2"></i> APPLY NEW LEAVE
                        </a>
                    </div>
                </div>

            </div>

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                
                <div class="card">
                    <div class="card-body flex flex-col items-center">
                        <div class="text-center mb-6">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Today's Attendance</h3>
                            <p class="text-lg font-bold text-slate-800 mt-1"><?php echo date("h:i A, d M Y"); ?></p>
                        </div>

                        <div class="relative w-40 h-40 mb-6">
                            <svg class="w-full h-full transform -rotate-90">
                                <circle cx="80" cy="80" r="70" stroke="#f1f5f9" stroke-width="12" fill="transparent"></circle>
                                <?php 
                                    // 9 hours = 32400 seconds. 
                                    // Circumference = 2 * pi * 70 ≈ 440
                                    $pct = min(1, $total_seconds_worked / 32400); 
                                    $dashoffset = 440 - ($pct * 440);
                                ?>
                                <circle cx="80" cy="80" r="70" stroke="#0d9488" stroke-width="12" fill="transparent" 
                                    stroke-dasharray="440" stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset); ?>" 
                                    stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Total Hours</p>
                                <p class="text-2xl font-bold text-slate-800" id="liveTimer" 
                                   data-start="<?php echo ($attendance_record && !$attendance_record['punch_out']) ? strtotime($attendance_record['punch_in']) * 1000 : ''; ?>"
                                   data-total="<?php echo $total_seconds_worked; ?>">
                                   <?php echo $total_hours_today; ?>
                                </p>
                            </div>
                        </div>

                        <form method="POST" class="w-full">
                            <?php if (!$attendance_record): ?>
                                <button type="submit" name="action" value="punch_in" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-right-to-bracket"></i> Punch In
                                </button>
                            <?php elseif (!$attendance_record['punch_out']): ?>
                                <div class="grid grid-cols-2 gap-3 w-full">
                                    <button type="submit" name="action" value="take_break" class="bg-amber-400 hover:bg-amber-500 text-white font-bold py-3 rounded-xl shadow transition">
                                        <i class="fa-solid fa-mug-hot"></i> Break
                                    </button>
                                    <button type="submit" name="action" value="punch_out" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl shadow transition">
                                        <i class="fa-solid fa-right-from-bracket"></i> Out
                                    </button>
                                </div>
                            <?php else: ?>
                                <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-3 rounded-xl cursor-not-allowed">
                                    <i class="fa-solid fa-check-circle"></i> Shift Completed
                                </button>
                            <?php endif; ?>
                        </form>

                        <p class="text-xs text-gray-400 mt-4 flex items-center gap-1">
                            <i class="fa-solid fa-fingerprint text-orange-500"></i> 
                            Punched In at: <span class="font-bold text-slate-600"><?php echo $display_punch_in; ?></span>
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                            <button class="text-xs text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded">View All</button>
                        </div>
                        <div class="space-y-4">
                            <?php if($notif_result && mysqli_num_rows($notif_result) > 0) { 
                                while($notif = mysqli_fetch_assoc($notif_result)): 
                                    $icon_bg = ($notif['type'] == 'file') ? 'bg-red-50 text-red-500' : 'bg-teal-50 text-teal-600';
                                    $initial = strtoupper(substr($notif['title'], 0, 1));
                            ?>
                            <div class="flex gap-3 items-start border-b border-gray-50 pb-3 last:border-0">
                                <div class="w-8 h-8 rounded-full <?php echo $icon_bg; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                    <?php echo $initial; ?>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo date("h:i A", strtotime($notif['created_at'])); ?></p>
                                    <?php if($notif['type'] == 'file'): ?>
                                        <div class="flex items-center gap-1 mt-1 text-xs text-slate-500 bg-slate-50 p-1 rounded">
                                            <i class="fa-solid fa-file-pdf text-red-500"></i> <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-400'>No notifications.</p>"; } ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Projects</h3>
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
                                    <span><?php echo $proj['completed_tasks'] . '/' . $proj['total_tasks']; ?> Tasks</span>
                                    <span><?php echo $pct; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div class="bg-teal-600 h-1.5 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-500'>No active projects.</p>"; } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">My Tasks</h3>
                        </div>
                        <div class="space-y-3 custom-scroll overflow-y-auto max-h-[300px] pr-2">
                            <?php if(mysqli_num_rows($tasks_result) > 0) {
                                while($task = mysqli_fetch_assoc($tasks_result)): 
                                    $badge_bg = ($task['priority'] == 'High') ? 'bg-pink-100 text-pink-600' : 'bg-slate-100 text-slate-600';
                            ?>
                            <div class="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" <?php echo ($task['status'] == 'completed') ? 'checked' : ''; ?> class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 border-gray-300">
                                    <span class="text-sm font-medium text-slate-700 truncate w-32"><?php echo htmlspecialchars($task['title']); ?></span>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded <?php echo $badge_bg; ?>"><?php echo $task['priority']; ?></span>
                            </div>
                            <?php endwhile; } else { echo "<p class='text-sm text-gray-500'>No tasks found.</p>"; } ?>
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
                            <div class="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:border-teal-200 transition">
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
                series: [{ name: 'Score', data: [65, 72, 78, 85, 92, 98] }],
                chart: { type: 'area', height: 130, toolbar: { show: false }, sparkline: { enabled: true } },
                colors: ['#0d9488'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
                tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
            };
            new ApexCharts(document.querySelector("#perfChart"), perfOptions).render();

            // Live Timer Logic
            const timerElement = document.getElementById('liveTimer');
            const progressRing = document.getElementById('progressRing');
            const startTimeAttr = timerElement.getAttribute('data-start');
            
            if (startTimeAttr) {
                const startTime = parseInt(startTimeAttr); // Timestamp in milliseconds
                
                function updateTimer() {
                    const now = new Date().getTime();
                    const diff = now - startTime;
                    
                    // Calculate hours, minutes, seconds
                    const totalSeconds = Math.floor(diff / 1000);
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    
                    // Format with leading zeros
                    const formattedTime = 
                        String(hours).padStart(2, '0') + ':' + 
                        String(minutes).padStart(2, '0') + ':' + 
                        String(seconds).padStart(2, '0');
                    
                    timerElement.innerText = formattedTime;

                    // Update Progress Ring (Based on 9 hours = 32400 seconds)
                    const maxSeconds = 32400; 
                    const circumference = 440;
                    const progress = Math.min(totalSeconds / maxSeconds, 1);
                    const offset = circumference - (progress * circumference);
                    if(progressRing) {
                        progressRing.style.strokeDashoffset = offset;
                    }
                }

                // Run immediately and then every second
                updateTimer();
                setInterval(updateTimer, 1000);
            }
        });
    </script>
</body>
</html>