<?php
// manager_dashboard.php

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../index.php");
    exit();
}

// 2. DATABASE CONNECTION & CONFIG
date_default_timezone_set('Asia/Kolkata');
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Cannot find database connection file.");
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$now_db = date('Y-m-d H:i:s');

// =========================================================================
// 3. HANDLE AJAX ATTENDANCE ACTIONS (No Page Reload)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    
    // Punch In
    if ($action === 'punch_in') {
        $check = $conn->query("SELECT id FROM attendance WHERE user_id=$user_id AND date='$today'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')");
            $stmt->bind_param("iss", $user_id, $now_db, $today);
            $stmt->execute();
        }
        echo json_encode(['status' => 'success', 'time' => date("h:i A")]);
        exit();
    }

    // Fetch existing attendance ID for subsequent actions
    $att_result = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id=$user_id AND date='$today'");
    if ($att_result->num_rows > 0) {
        $att_row = $att_result->fetch_assoc();
        $att_id = $att_row['id'];
        $punch_in_time = $att_row['punch_in'];
        
        // Start Break
        if ($action === 'break_start') {
            $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
            $stmt->bind_param("is", $att_id, $now_db);
            $stmt->execute();
            echo json_encode(['status' => 'success']);
            exit();
        }
        
        // End Break
        if ($action === 'break_end') {
            $stmt = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
            $stmt->bind_param("si", $now_db, $att_id);
            $stmt->execute();
            echo json_encode(['status' => 'success']);
            exit();
        }
        
        // Punch Out
        if ($action === 'punch_out') {
            // Force end any active break first
            $conn->query("UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = $att_id AND break_end IS NULL");
            
            // Calculate total break time
            $brk_res = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, IFNULL(break_end, '$now_db'))) as total_brk FROM attendance_breaks WHERE attendance_id = $att_id");
            $brk_row = $brk_res->fetch_assoc();
            $total_brk_sec = $brk_row['total_brk'] ?? 0;
            
            // Calculate production hours
            $total_work_sec = strtotime($now_db) - strtotime($punch_in_time);
            $prod_sec = max(0, $total_work_sec - $total_brk_sec);
            $prod_hours = $prod_sec / 3600;
            
            $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?");
            $stmt->bind_param("sdi", $now_db, $prod_hours, $att_id);
            $stmt->execute();
            
            echo json_encode([
                'status' => 'success', 
                'time' => date("h:i A"), 
                'prod' => number_format($prod_hours, 2).'h'
            ]);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
    exit();
}

// =========================================================================
// 4. FETCH DYNAMIC DATA ON PAGE LOAD
// =========================================================================

// A. Manager Profile Data
$profile_query = "SELECT u.email, u.role, ep.* FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$emp_name = $profile['full_name'] ?? 'Manager';
$emp_role = $profile['designation'] ?? $profile['role'];
$emp_dept = $profile['department'] ?? 'Management';
$emp_phone = $profile['phone'] ?? 'Not Set';
$emp_email = $profile['email'];
$joined_date = $profile['joining_date'] ? date("d M Y", strtotime($profile['joining_date'])) : 'N/A';

// Resolve Avatar
$avatar = "https://ui-avatars.com/api/?name=" . urlencode($emp_name) . "&background=0d9488&color=fff";
if (!empty($profile['profile_img']) && $profile['profile_img'] !== 'default_user.png') {
    $avatar = str_starts_with($profile['profile_img'], 'http') ? $profile['profile_img'] : '../assets/profiles/' . $profile['profile_img'];
}

// B. Attendance Data (Self)
$att_query = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$stmt = $conn->prepare($att_query);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_assoc();

$att_status = "Not Punched In";
$punch_in_time = "--:--";
$punch_out_time = "--:--";
$production = "0.00h";

$is_punched_in = false;
$is_punched_out = false;
$is_on_break = false;
$total_seconds = 0;

if ($attendance) {
    $att_id = $attendance['id'];
    $punch_in_time = date("h:i A", strtotime($attendance['punch_in']));
    $is_punched_in = true;
    
    // Check breaks
    $brk_res = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, IFNULL(break_end, '$now_db'))) as total_brk, SUM(CASE WHEN break_end IS NULL THEN 1 ELSE 0 END) as active_break FROM attendance_breaks WHERE attendance_id = $att_id");
    $brk_row = $brk_res->fetch_assoc();
    $total_break_sec = $brk_row['total_brk'] ?? 0;
    $is_on_break = ($brk_row['active_break'] > 0);

    if ($attendance['punch_out']) {
        $is_punched_out = true;
        $att_status = "Shift Completed";
        $punch_out_time = date("h:i A", strtotime($attendance['punch_out']));
        $production = number_format($attendance['production_hours'], 2) . "h";
        $total_seconds = (strtotime($attendance['punch_out']) - strtotime($attendance['punch_in'])) - $total_break_sec;
    } else {
        $att_status = $is_on_break ? "On Break" : "On Duty";
        $total_seconds = (time() - strtotime($attendance['punch_in'])) - $total_break_sec;
    }
}

// C. Team Attendance Stats (Donut Chart)
$team_stats = ["On Time" => 0, "Late" => 0, "WFH" => 0, "Absent" => 0, "Sick" => 0];
$team_sql = "SELECT a.status, COUNT(*) as count FROM attendance a 
             JOIN employee_profiles ep ON a.user_id = ep.user_id 
             WHERE ep.manager_id = ? AND a.date = ? GROUP BY a.status";
$stmt = $conn->prepare($team_sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$team_res = $stmt->get_result();
while ($row = $team_res->fetch_assoc()) {
    $stat = $row['status'];
    if (isset($team_stats[$stat])) $team_stats[$stat] = $row['count'];
    elseif ($stat == 'Sick Leave') $team_stats['Sick'] = $row['count'];
}

// D. Leave Summary
$leave_total = 16;
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$stmt = $conn->prepare($leave_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$leave_taken = $stmt->get_result()->fetch_assoc()['taken'] ?? 0;
$leave_left = max(0, $leave_total - $leave_taken);

// E. Active Projects
$projects = [];
$proj_sql = "SELECT project_name, client_name, deadline, progress FROM projects WHERE leader_id = ? AND status = 'Active' LIMIT 3";
$stmt = $conn->prepare($proj_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$proj_res = $stmt->get_result();
while ($row = $proj_res->fetch_assoc()) { $projects[] = $row; }

// F. Personal Tasks
$tasks = [];
$task_sql = "SELECT title, status, priority FROM personal_taskboard WHERE user_id = ? ORDER BY id DESC LIMIT 5";
$stmt = $conn->prepare($task_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$task_res = $stmt->get_result();
while ($row = $task_res->fetch_assoc()) {
    $color = "text-slate-600 bg-slate-100";
    if ($row['status'] == 'completed') $color = "text-green-600 bg-green-100";
    elseif ($row['status'] == 'inprogress') $color = "text-purple-600 bg-purple-100";
    elseif ($row['priority'] == 'High') $color = "text-red-600 bg-red-100";
    $tasks[] = ["name" => $row['title'], "status" => ucfirst($row['status']), "color" => $color];
}

// G. Skills
$skills = [];
$skill_sql = "SELECT skill_name, proficiency, color_hex, last_updated FROM employee_skills WHERE user_id = ?";
$stmt = $conn->prepare($skill_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$skill_res = $stmt->get_result();
while ($row = $skill_res->fetch_assoc()) { $skills[] = $row; }

// H. Notifications
$notifications = [];
$notif_sql = "SELECT title, message, created_at, type FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt = $conn->prepare($notif_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_res = $stmt->get_result();
while ($row = $notif_res->fetch_assoc()) { $notifications[] = $row; }

// I. Meetings Today
$meetings = [];
$meet_sql = "SELECT title, department, meeting_time, type_color FROM meetings WHERE meeting_date = ? ORDER BY meeting_time ASC LIMIT 4";
$stmt = $conn->prepare($meet_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$meet_res = $stmt->get_result();
while ($row = $meet_res->fetch_assoc()) { $meetings[] = $row; }

// Static Data for Visuals
$hourly_stats = [
    ["label" => "Team Prod. Hrs", "value" => "42.5", "total" => "50", "trend" => "5% This Week", "up" => true, "icon" => "fa-clock", "bg" => "from-orange-400 to-orange-600"],
    ["label" => "Tasks Completed", "value" => "18", "total" => "24", "trend" => "7% Last Week", "up" => true, "icon" => "fa-list-check", "bg" => "from-teal-400 to-teal-600"],
    ["label" => "Leave Requests", "value" => "3", "total" => "5", "trend" => "2 Pending", "up" => false, "icon" => "fa-envelope-open-text", "bg" => "from-blue-400 to-blue-600"],
    ["label" => "Overtime (Team)", "value" => "12", "total" => "20", "trend" => "Low Overtime", "up" => true, "icon" => "fa-business-time", "bg" => "from-pink-400 to-pink-600"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | Workack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        html { font-size: 16px; }

        .hover-card { transition: all 0.2s ease; }
        .hover-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.07); transform: translateY(-2px); }

        .progress-circle { position: relative; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
        .progress-circle::before { content: ""; position: absolute; inset: 4px; background: white; border-radius: 50%; z-index: 1; }
        .progress-circle span { position: relative; z-index: 2; font-size: 10px; font-weight: bold; }

        /* Responsive Sidebar Layout */
        #mainContent { 
            margin-left: 95px; 
            transition: margin-left 0.3s ease, width 0.3s ease; 
            padding: 24px 32px; 
            width: calc(100% - 95px);
            min-height: 100vh;
            box-sizing: border-box;
        }
        @media (max-width: 992px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 16px; }
        }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800">

    <?php include $sidebarPath; ?>

    <main id="mainContent">
        <?php include $headerPath; ?>

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
                    <button class="bg-white border border-gray-200 px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 shadow-sm hover:bg-gray-50 transition">
                        <i class="fa-solid fa-file-export text-gray-400"></i> Export
                    </button>
                    <div class="bg-teal-50 border border-teal-100 text-teal-700 px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 shadow-sm">
                        <i class="fa-regular fa-calendar"></i> <?php echo date("d M Y"); ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                <?php foreach ($hourly_stats as $card): ?>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover-card flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?php echo $card['bg']; ?> flex items-center justify-center text-white shadow-md mb-4">
                        <i class="fa-solid <?php echo $card['icon']; ?> text-xl"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-800"><?php echo $card['value']; ?></p>
                    <p class="text-gray-500 text-[10px] font-bold uppercase tracking-wide mt-2"><?php echo $card['label']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6 items-stretch">
                
                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6 h-full">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex flex-col shrink-0">
                        <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-50">
                            <h3 class="font-bold text-slate-800 text-sm uppercase tracking-wider">My Attendance</h3>
                            <span class="text-[10px] font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded"><?php echo date("d M Y"); ?></span>
                        </div>
                        
                        <div class="flex flex-col items-center justify-center my-2 flex-grow">
                            <div class="relative w-36 h-36 mx-auto">
                                <svg class="w-full h-full transform -rotate-90">
                                    <circle cx="72" cy="72" r="64" stroke="#f1f5f9" stroke-width="10" fill="transparent" />
                                    <?php 
                                        $pct = min(1, $total_seconds / 32400); 
                                        $dashoffset = 402 - ($pct * 402); 
                                    ?>
                                    <circle cx="72" cy="72" r="64" stroke="#0d9488" stroke-width="10" fill="transparent" stroke-dasharray="402" stroke-dashoffset="<?php echo $is_punched_out ? '0' : $dashoffset; ?>" stroke-linecap="round" id="progressRing" class="transition-all duration-500" />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <p class="text-2xl font-black text-slate-800 leading-none" id="liveTimer" 
                                       data-running="<?php echo ($is_punched_in && !$is_punched_out && !$is_on_break) ? 'true' : 'false'; ?>" 
                                       data-total="<?php echo $total_seconds; ?>">
                                        <?php echo sprintf('%02d:%02d:%02d', floor($total_seconds/3600), floor(($total_seconds%3600)/60), $total_seconds%60); ?>
                                    </p>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-1">Total Hours</p>
                                </div>
                            </div>
                            <span id="uiStatusText" class="mt-4 text-[10px] font-bold text-teal-700 bg-teal-50 px-3 py-1 rounded-full border border-teal-100">
                                Status: <?php echo $att_status; ?>
                            </span>
                        </div>

                        <div class="mt-4 w-full">
                            <div class="grid grid-cols-3 gap-2 text-center text-[10px] font-bold bg-slate-50 p-3 rounded-xl border border-gray-100 mb-4">
                                <div><p class="text-gray-400 uppercase mb-1">Punch In</p><p id="uiPunchIn" class="text-slate-800 text-xs"><?php echo $punch_in_time; ?></p></div>
                                <div class="border-x border-gray-200"><p class="text-gray-400 uppercase mb-1">Punch Out</p><p id="uiPunchOut" class="text-slate-800 text-xs"><?php echo $punch_out_time; ?></p></div>
                                <div><p class="text-gray-400 uppercase mb-1">Production</p><p id="uiProduction" class="text-teal-600 text-xs"><?php echo $production; ?></p></div>
                            </div>
                            
                            <div id="attendanceActionButtons" class="w-full flex gap-2">
                                <?php if(!$is_punched_in): ?>
                                    <button onclick="performAjaxAction('punch_in')" class="w-full bg-teal-600 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-teal-700 transition flex justify-center items-center gap-2">
                                        <i class="fa-solid fa-fingerprint text-base"></i> Punch In
                                    </button>
                                <?php elseif(!$is_punched_out): ?>
                                    <?php if(!$is_on_break): ?>
                                        <button onclick="performAjaxAction('break_start')" class="w-1/2 bg-yellow-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-yellow-600 transition flex justify-center items-center gap-2">
                                            <i class="fa-solid fa-mug-hot text-base"></i> Break
                                        </button>
                                    <?php else: ?>
                                        <button onclick="performAjaxAction('break_end')" class="w-1/2 bg-emerald-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-emerald-600 transition flex justify-center items-center gap-2">
                                            <i class="fa-solid fa-play text-base"></i> End Break
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="performAjaxAction('punch_out')" class="w-1/2 bg-orange-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-orange-600 transition flex justify-center items-center gap-2">
                                        <i class="fa-solid fa-right-from-bracket text-base"></i> Punch Out
                                    </button>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-3 rounded-xl text-xs cursor-not-allowed flex justify-center items-center gap-2 border border-gray-200">
                                        <i class="fa-solid fa-check-circle text-base"></i> Shift Completed
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex-grow flex flex-col">
                        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-50">
                            <h3 class="font-bold text-slate-800 text-lg"><i class="fa-solid fa-layer-group text-teal-600 mr-2"></i>Active Projects</h3>
                            <a href="projects.php" class="text-xs font-bold text-teal-600 hover:underline">View All</a>
                        </div>
                        <div class="flex flex-col gap-4 flex-grow">
                            <?php if(empty($projects)): ?>
                                <div class="text-center text-gray-400 py-8 flex-grow flex flex-col justify-center"><i class="fa-solid fa-box-open text-3xl mb-2 block"></i> No active projects.</div>
                            <?php else: foreach ($projects as $proj): ?>
                            <div class="border border-gray-100 rounded-xl p-4 bg-slate-50/50 hover:bg-white transition hover:shadow-sm">
                                <div class="flex justify-between mb-3">
                                    <h4 class="font-bold text-sm text-slate-800 truncate pr-2"><?php echo htmlspecialchars($proj['project_name']); ?></h4>
                                    <span class="text-[10px] bg-white border border-gray-200 px-2 py-0.5 rounded font-bold text-gray-500 flex-shrink-0 ml-2">Client: <?php echo htmlspecialchars($proj['client_name'] ?? 'Internal'); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                                    <div class="bg-teal-600 h-1.5 rounded-full" style="width: <?php echo $proj['progress']; ?>%"></div>
                                </div>
                                <div class="flex justify-between items-center text-[10px] font-bold text-gray-500 mt-2 pt-2 border-t border-gray-100">
                                    <span>Deadline: <?php echo ($proj['deadline'] == '0000-00-00') ? 'N/A' : date("d M Y", strtotime($proj['deadline'])); ?></span>
                                    <span class="text-teal-600"><?php echo $proj['progress']; ?>% Completed</span>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6 h-full">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex flex-col shrink-0">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-lg">Team Attendance Today</h3>
                            <span class="text-[10px] font-bold text-gray-500 bg-slate-100 px-2 py-1 rounded border border-gray-200"><i class="fa-solid fa-users mr-1"></i>Live</span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center justify-around gap-6">
                            <div class="space-y-4 text-sm w-full xl:w-auto">
                                <div class="flex items-center gap-3 justify-between xl:justify-start">
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-teal-600"></div><span class="text-gray-600 font-medium">On Time</span></div>
                                    <span class="text-teal-600 font-bold text-base"><?php echo $team_stats['On Time']; ?></span>
                                </div>
                                <div class="flex items-center gap-3 justify-between xl:justify-start">
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-green-500"></div><span class="text-gray-600 font-medium">Late</span></div>
                                    <span class="text-green-500 font-bold text-base"><?php echo $team_stats['Late']; ?></span>
                                </div>
                                <div class="flex items-center gap-3 justify-between xl:justify-start">
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-orange-500"></div><span class="text-gray-600 font-medium">WFH</span></div>
                                    <span class="text-orange-500 font-bold text-base"><?php echo $team_stats['WFH']; ?></span>
                                </div>
                                <div class="flex items-center gap-3 justify-between xl:justify-start">
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-red-500"></div><span class="text-gray-600 font-medium">Absent</span></div>
                                    <span class="text-red-500 font-bold text-base"><?php echo $team_stats['Absent']; ?></span>
                                </div>
                            </div>
                            
                            <div class="w-40 h-40 relative flex-shrink-0 mx-auto">
                                <canvas id="leaveChart"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-3xl font-black text-slate-800"><?php echo array_sum($team_stats); ?></span>
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-1">Total Team</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex-grow flex flex-col justify-between">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">My Leave Balance</h3>
                            <span class="text-[10px] font-bold text-gray-500 bg-slate-100 border border-gray-200 px-2 py-1 rounded"><i class="fa-regular fa-calendar mr-1"></i>2026</span>
                        </div>
                        <div class="flex flex-col justify-center flex-grow">
                            <div class="grid grid-cols-3 gap-3 mb-6">
                                <div class="text-center p-4 bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl border border-teal-200/50">
                                    <p class="text-teal-600/80 text-[10px] font-bold uppercase mb-1">Total</p>
                                    <p class="font-black text-2xl text-teal-800"><?php echo $leave_total; ?></p>
                                </div>
                                <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200/50">
                                    <p class="text-blue-600/80 text-[10px] font-bold uppercase mb-1">Taken</p>
                                    <p class="font-black text-2xl text-blue-800"><?php echo $leave_taken; ?></p>
                                </div>
                                <div class="text-center p-4 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl border border-emerald-200/50 shadow-sm">
                                    <p class="text-emerald-600/80 text-[10px] font-bold uppercase mb-1">Left</p>
                                    <p class="font-black text-2xl text-emerald-700"><?php echo $leave_left; ?></p>
                                </div>
                            </div>
                            <a href="leave_request.php" class="w-full mt-auto bg-slate-800 text-white py-3 rounded-xl font-bold text-xs shadow hover:bg-slate-700 transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-calendar-plus"></i> Apply Leave
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6 h-full">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover-card shrink-0 flex flex-col">
                        <div class="bg-gradient-to-r from-teal-600 to-teal-800 p-6 relative text-center">
                            <img src="<?php echo $avatar; ?>" class="w-20 h-20 rounded-full border-4 border-white shadow-lg object-cover mx-auto">
                            <a href="settings.php" class="absolute top-4 right-4 bg-white/20 p-1.5 rounded-lg hover:bg-white/40 transition"><i class="fa-solid fa-gear text-white text-xs"></i></a>
                            <h2 class="text-white font-bold text-lg mt-3 truncate"><?php echo htmlspecialchars($emp_name); ?></h2>
                            <p class="text-teal-100 text-xs truncate"><?php echo htmlspecialchars($emp_role); ?></p>
                        </div>
                        <div class="p-5 space-y-3">
                            <div class="flex items-center gap-3 p-2.5 bg-slate-50 border border-slate-100 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-phone text-teal-600 text-xs"></i></div>
                                <div class="min-w-0 flex-1"><p class="text-[9px] text-gray-400 uppercase font-bold">Phone</p><p class="font-semibold text-xs truncate"><?php echo htmlspecialchars($emp_phone); ?></p></div>
                            </div>
                            <div class="flex items-center gap-3 p-2.5 bg-slate-50 border border-slate-100 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-envelope text-teal-600 text-xs"></i></div>
                                <div class="min-w-0 flex-1"><p class="text-[9px] text-gray-400 uppercase font-bold">Email</p><p class="font-semibold text-xs truncate" title="<?php echo htmlspecialchars($emp_email); ?>"><?php echo htmlspecialchars($emp_email); ?></p></div>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-emerald-50 border border-emerald-100 rounded-lg mt-2">
                                <span class="text-[10px] text-emerald-700 font-bold flex items-center gap-2"><i class="fa-solid fa-calendar-check"></i> Joined</span>
                                <span class="font-bold text-xs text-slate-800"><?php echo $joined_date; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex-grow flex flex-col">
                        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-50">
                            <h3 class="font-bold text-slate-800 text-lg"><i class="fa-solid fa-list-check text-orange-500 mr-2"></i>My Tasks</h3>
                            <button class="text-[10px] font-semibold text-gray-500 flex items-center gap-1 bg-slate-100 px-3 py-1.5 rounded border border-gray-200">Pending <i class="fa-solid fa-chevron-down text-[8px]"></i></button>
                        </div>
                        <div class="flex flex-col gap-3 flex-grow overflow-y-auto max-h-[300px] pr-1 custom-scrollbar">
                            <?php if(empty($tasks)): ?>
                                <div class="text-center text-gray-400 py-8 flex-grow flex flex-col justify-center"><i class="fa-solid fa-check-double text-3xl mb-2 block"></i> All caught up!</div>
                            <?php else: foreach ($tasks as $task): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:border-teal-200 bg-white transition shadow-sm">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="w-5 h-5 rounded border-2 border-gray-300 flex items-center justify-center cursor-pointer hover:border-teal-500 transition shrink-0"></div>
                                    <span class="text-sm font-semibold text-slate-700 truncate"><?php echo htmlspecialchars($task['name']); ?></span>
                                </div>
                                <span class="text-[10px] font-bold px-3 py-1 rounded-full flex-shrink-0 ml-2 <?php echo $task['color']; ?>"><?php echo $task['status']; ?></span>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6 items-stretch">
                <div class="col-span-12 lg:col-span-8 bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex flex-col h-full">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg"><i class="fa-solid fa-chart-line text-blue-500 mr-2"></i>Company Performance Trend</h3>
                        <button class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-bold text-gray-500 bg-slate-50"><i class="fa-regular fa-calendar mr-1"></i> 2026</button>
                    </div>
                    <div class="h-72 w-full mt-auto"><canvas id="performanceChart"></canvas></div>
                </div>

                <div class="col-span-12 lg:col-span-4 bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex flex-col h-full">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg"><i class="fa-solid fa-bolt text-yellow-500 mr-2"></i>My Skills</h3>
                        <span class="text-[10px] font-bold text-gray-500 bg-slate-100 border border-gray-200 px-2 py-1 rounded">Levels</span>
                    </div>
                    <div class="flex flex-col gap-4 flex-grow overflow-y-auto max-h-[300px] pr-1 custom-scrollbar">
                        <?php if(empty($skills)): ?>
                            <div class="text-center text-gray-400 py-8 text-sm flex-grow flex flex-col justify-center">No skills added yet.</div>
                        <?php else: foreach ($skills as $skill): ?>
                        <div class="flex items-center justify-between p-3 border border-gray-100 bg-slate-50/50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-10 rounded-full" style="background: <?php echo $skill['color_hex']; ?>;"></div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($skill['skill_name']); ?></p>
                                    <p class="text-[10px] text-gray-400 mt-0.5 font-medium">Updated: <?php echo date("M Y", strtotime($skill['last_updated'])); ?></p>
                                </div>
                            </div>
                            <div class="progress-circle shadow-sm" style="background: conic-gradient(<?php echo $skill['color_hex']; ?> <?php echo $skill['proficiency']; ?>%, #e2e8f0 0);">
                                <span class="text-slate-700"><?php echo $skill['proficiency']; ?>%</span>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6 items-stretch">
                <div class="col-span-12 lg:col-span-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex flex-col h-full">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg"><i class="fa-regular fa-bell text-pink-500 mr-2"></i>Notifications</h3>
                        <button class="text-xs font-bold text-teal-600 bg-teal-50 border border-teal-100 px-3 py-1.5 rounded-lg">View All</button>
                    </div>
                    <div class="flex flex-col gap-4 flex-grow">
                        <?php if(empty($notifications)): ?>
                            <div class="text-center text-gray-400 py-4 text-sm flex-grow flex flex-col justify-center">You're all caught up!</div>
                        <?php else: foreach ($notifications as $note): 
                            $icon = ($note['type'] == 'file') ? 'fa-file-pdf text-red-500 bg-red-50' : (($note['type'] == 'comment') ? 'fa-comment text-blue-500 bg-blue-50' : 'fa-bell text-orange-500 bg-orange-50');
                        ?>
                        <div class="flex gap-4 p-3 border border-transparent hover:border-gray-100 hover:bg-slate-50 rounded-xl transition cursor-pointer">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?php echo explode(' ', $icon)[2]; ?>">
                                <i class="fa-solid <?php echo explode(' ', $icon)[0] . ' ' . explode(' ', $icon)[1]; ?>"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-slate-800"><span class="font-bold"><?php echo htmlspecialchars($note['title']); ?></span></p>
                                <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($note['message']); ?></p>
                                <p class="text-[10px] text-gray-400 font-medium mt-1"><i class="fa-regular fa-clock mr-1"></i><?php echo date("h:i A - d M", strtotime($note['created_at'])); ?></p>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover-card flex flex-col h-full">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg"><i class="fa-solid fa-handshake text-indigo-500 mr-2"></i>Today's Meetings</h3>
                        <button class="text-[10px] font-bold text-gray-500 bg-slate-100 border border-gray-200 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-plus mr-1"></i> Add New</button>
                    </div>
                    <div class="flex flex-col gap-4 relative flex-grow">
                        <?php if(empty($meetings)): ?>
                            <div class="text-center text-gray-400 py-4 text-sm flex-grow flex flex-col justify-center">No meetings scheduled for today.</div>
                        <?php else: ?>
                        <div class="absolute left-[70px] top-2 bottom-2 w-0.5 bg-gray-200 rounded-full"></div>
                        <?php foreach ($meetings as $meet): 
                            $dotClass = "bg-teal-500";
                            if($meet['type_color'] == 'orange') $dotClass = "bg-orange-500";
                            if($meet['type_color'] == 'yellow') $dotClass = "bg-yellow-500";
                        ?>
                        <div class="flex items-start gap-4 relative">
                            <span class="text-xs font-bold text-gray-500 w-14 text-right pt-2"><?php echo date("h:i A", strtotime($meet['meeting_time'])); ?></span>
                            <div class="w-3 h-3 rounded-full <?php echo $dotClass; ?> absolute left-[65px] top-2.5 z-10 border-2 border-white shadow-sm ring-2 ring-gray-50"></div>
                            <div class="flex-grow bg-white p-3 rounded-xl border border-gray-100 shadow-sm hover:shadow transition ml-2">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></p>
                                <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 tracking-wider"><?php echo htmlspecialchars($meet['department']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Sidebar Layout Integration
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            if (!primarySidebar || !mainContent) return;

            const updateMargin = () => {
                if (window.innerWidth <= 992) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    return;
                }
                let totalWidth = primarySidebar.offsetWidth;
                if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                    totalWidth += secondarySidebar.offsetWidth;
                }
                mainContent.style.marginLeft = totalWidth + 'px';
                mainContent.style.width = `calc(100% - ${totalWidth}px)`;
            };

            new ResizeObserver(() => updateMargin()).observe(primarySidebar);
            if (secondarySidebar) {
                new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            window.addEventListener('resize', updateMargin);
            updateMargin();
        }
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);

        // ==========================================
        // AJAX ATTENDANCE LOGIC
        // ==========================================
        let timerInterval;

        function updateAttendanceUI(action, data) {
            const timerEl = document.getElementById('liveTimer');
            const statusEl = document.getElementById('uiStatusText');
            const btnContainer = document.getElementById('attendanceActionButtons');
            const inEl = document.getElementById('uiPunchIn');
            const outEl = document.getElementById('uiPunchOut');
            const prodEl = document.getElementById('uiProduction');

            Swal.fire({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                icon: 'success', title: 'Success', text: 'Attendance updated successfully.'
            });

            if (action === 'punch_in') {
                timerEl.setAttribute('data-running', 'true');
                statusEl.innerHTML = "Status: On Duty";
                inEl.innerText = data.time;
                btnContainer.innerHTML = `
                    <button onclick="performAjaxAction('break_start')" class="w-1/2 bg-yellow-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-yellow-600 transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-mug-hot text-base"></i> Break
                    </button>
                    <button onclick="performAjaxAction('punch_out')" class="w-1/2 bg-orange-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-orange-600 transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-right-from-bracket text-base"></i> Punch Out
                    </button>
                `;
                startLiveTimer();
            } 
            else if (action === 'break_start') {
                timerEl.setAttribute('data-running', 'false');
                clearInterval(timerInterval);
                statusEl.innerHTML = "Status: On Break";
                statusEl.className = "mt-4 text-[10px] font-bold text-yellow-700 bg-yellow-50 px-3 py-1 rounded-full border border-yellow-100";
                btnContainer.innerHTML = `
                    <button onclick="performAjaxAction('break_end')" class="w-1/2 bg-emerald-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-emerald-600 transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-play text-base"></i> End Break
                    </button>
                    <button onclick="performAjaxAction('punch_out')" class="w-1/2 bg-orange-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-orange-600 transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-right-from-bracket text-base"></i> Punch Out
                    </button>
                `;
            }
            else if (action === 'break_end') {
                timerEl.setAttribute('data-running', 'true');
                statusEl.innerHTML = "Status: On Duty";
                statusEl.className = "mt-4 text-[10px] font-bold text-teal-700 bg-teal-50 px-3 py-1 rounded-full border border-teal-100";
                btnContainer.innerHTML = `
                    <button onclick="performAjaxAction('break_start')" class="w-1/2 bg-yellow-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-yellow-600 transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-mug-hot text-base"></i> Break
                    </button>
                    <button onclick="performAjaxAction('punch_out')" class="w-1/2 bg-orange-500 text-white font-bold py-3 rounded-xl text-xs shadow-md hover:bg-orange-600 transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-right-from-bracket text-base"></i> Punch Out
                    </button>
                `;
                // Reset start time to accurately continue tracking relative to current total
                startLiveTimer(); 
            }
            else if (action === 'punch_out') {
                timerEl.setAttribute('data-running', 'false');
                clearInterval(timerInterval);
                statusEl.innerHTML = "Status: Shift Completed";
                outEl.innerText = data.time;
                prodEl.innerText = data.prod;
                btnContainer.innerHTML = `
                    <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-3 rounded-xl text-xs cursor-not-allowed flex justify-center items-center gap-2 border border-gray-200">
                        <i class="fa-solid fa-check-circle text-base"></i> Shift Completed
                    </button>
                `;
            }
        }

        function performAjaxAction(action) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
            
            let formData = new FormData();
            formData.append('ajax_action', action);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    updateAttendanceUI(action, data);
                } else {
                    Swal.fire('Error', 'Failed to process request.', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Network error occurred.', 'error'));
        }

        function startLiveTimer() {
            const timerElement = document.getElementById('liveTimer');
            const progressRing = document.getElementById('progressRing');
            if(!timerElement) return;

            let totalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
            const startTime = new Date().getTime();

            clearInterval(timerInterval); // prevent double loops

            timerInterval = setInterval(() => {
                if (timerElement.getAttribute('data-running') !== 'true') return;

                const now = new Date().getTime();
                const diffSeconds = Math.floor((now - startTime) / 1000);
                const currentTotal = totalSeconds + diffSeconds;
                
                // Update attribute so if we pause, we know exactly where we stopped
                timerElement.setAttribute('data-total', currentTotal);
                
                const hours = Math.floor(currentTotal / 3600);
                const minutes = Math.floor((currentTotal % 3600) / 60);
                const seconds = currentTotal % 60;
                
                timerElement.innerText = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

                const maxSeconds = 32400; // 9 hours target
                const circumference = 402; // 2 * pi * 64 (approx)
                const progress = Math.min(currentTotal / maxSeconds, 1);
                const offset = circumference - (progress * circumference);
                if(progressRing) progressRing.style.strokeDashoffset = offset;
            }, 1000);
        }

        // Initialize timer on page load if applicable
        document.addEventListener('DOMContentLoaded', () => {
            const timerElement = document.getElementById('liveTimer');
            if (timerElement && timerElement.getAttribute('data-running') === 'true') {
                startLiveTimer();
            }
        });

        // Charts
        document.addEventListener('DOMContentLoaded', function () {
            // Leave/Attendance Chart (Team Stats)
            const leaveCtx = document.getElementById('leaveChart');
            if(leaveCtx) {
                new Chart(leaveCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick'],
                        datasets: [{
                            data: [<?= $team_stats['On Time'] ?>, <?= $team_stats['Late'] ?>, <?= $team_stats['WFH'] ?>, <?= $team_stats['Absent'] ?>, <?= $team_stats['Sick'] ?>],
                            backgroundColor: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                            borderWidth: 2, borderColor: '#ffffff', cutout: '75%'
                        }]
                    },
                    options: { 
                        plugins: { legend: { display: false }, tooltip: { enabled: true } }, 
                        maintainAspectRatio: false, responsive: true,
                        animation: { animateScale: true, animateRotate: true }
                    }
                });
            }

            // Performance Chart (Static aesthetics)
            const perfCtx = document.getElementById('performanceChart');
            if(perfCtx) {
                const ctx = perfCtx.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)'); // Blue
                gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                        datasets: [{
                            label: 'Company Revenue Output',
                            data: [30, 45, 38, 65, 58, 85, 75, 92, 88, 105],
                            borderColor: '#3b82f6', backgroundColor: gradient, fill: true, tension: 0.4, 
                            pointBackgroundColor: '#ffffff', pointBorderColor: '#3b82f6', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { size: 11 }, color: '#94a3b8' }, grid: { borderDash: [4, 4], color: '#f1f5f9', drawBorder: false } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94a3b8' } }
                        },
                        interaction: { mode: 'nearest', axis: 'x', intersect: false }
                    }
                });
            }
        });
    </script>
</body>
</html>