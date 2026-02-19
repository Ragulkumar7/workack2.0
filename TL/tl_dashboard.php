<?php
// TL/tl_dashboard.php

// 1. SESSION & SECURITY
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

$tl_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// =========================================================================
// H. TL'S OWN ATTENDANCE LOGIC (AJAX & INITIAL LOAD)
// =========================================================================

// Function to calculate current worked seconds
function getWorkedSeconds($conn, $user_id, $date) {
    $att_check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
    $stmt_chk = $conn->prepare($att_check_sql);
    $stmt_chk->bind_param("is", $user_id, $date);
    $stmt_chk->execute();
    $record = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if (!$record) return 0;

    $break_seconds = 0;
    $is_on_break = false;
    $break_start_ts = 0;

    // Check if on break
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $stmt_bk = $conn->prepare($bk_sql);
    $stmt_bk->bind_param("i", $record['id']);
    $stmt_bk->execute();
    if ($bk_row = $stmt_bk->get_result()->fetch_assoc()) {
        $is_on_break = true;
        $break_start_ts = strtotime($bk_row['break_start']);
    }
    $stmt_bk->close();

    // Sum past breaks
    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NOT NULL";
    $stmt_sum = $conn->prepare($sum_sql);
    $stmt_sum->bind_param("i", $record['id']);
    $stmt_sum->execute();
    $sum_res = $stmt_sum->get_result()->fetch_assoc();
    $break_seconds = $sum_res['total'] ?? 0;
    $stmt_sum->close();

    $start_ts = strtotime($record['punch_in']);
    if ($is_on_break) {
        $now_ts = $break_start_ts;
    } elseif ($record['punch_out']) {
        $now_ts = strtotime($record['punch_out']);
    } else {
        $now_ts = time();
    }
    
    $worked = ($now_ts - $start_ts) - $break_seconds;
    return $worked > 0 ? $worked : 0;
}

// Check if this is an AJAX request for attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $now_db = date('Y-m-d H:i:s');
    
    // Fetch current record
    $att_check_sql = "SELECT id, punch_out FROM attendance WHERE user_id = ? AND date = ?";
    $stmt_chk = $conn->prepare($att_check_sql);
    $stmt_chk->bind_param("is", $tl_user_id, $today);
    $stmt_chk->execute();
    $record = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    // Determine Break Status
    $is_on_break = false;
    if ($record) {
        $bk_sql = "SELECT id FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
        $stmt_bk = $conn->prepare($bk_sql);
        $stmt_bk->bind_param("i", $record['id']);
        $stmt_bk->execute();
        if ($stmt_bk->get_result()->fetch_assoc()) {
            $is_on_break = true;
        }
        $stmt_bk->close();
    }

    try {
        if ($action === 'punch_in' && !$record) {
            $ins = $conn->prepare("INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')");
            $ins->bind_param("iss", $tl_user_id, $now_db, $today);
            $ins->execute();
            echo json_encode(['status' => 'success', 'state' => 'in', 'time' => date('h:i A')]);
            exit();
        } 
        elseif ($action === 'break_start' && $record && !$is_on_break) {
            $ins = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
            $ins->bind_param("is", $record['id'], $now_db);
            $ins->execute();
            echo json_encode(['status' => 'success', 'state' => 'break', 'seconds' => getWorkedSeconds($conn, $tl_user_id, $today)]);
            exit();
        } 
        elseif ($action === 'break_end' && $record && $is_on_break) {
            $upd = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
            $upd->bind_param("si", $now_db, $record['id']);
            $upd->execute();
            echo json_encode(['status' => 'success', 'state' => 'in', 'seconds' => getWorkedSeconds($conn, $tl_user_id, $today)]);
            exit();
        } 
        elseif ($action === 'punch_out' && $record && !$record['punch_out']) {
            if ($is_on_break) {
                $upd = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
                $upd->bind_param("si", $now_db, $record['id']);
                $upd->execute();
            }
            
            $final_seconds = getWorkedSeconds($conn, $tl_user_id, $today);
            $hours = $final_seconds / 3600;
            
            $upd = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?");
            $upd->bind_param("sdi", $now_db, $hours, $record['id']);
            $upd->execute();
            echo json_encode(['status' => 'success', 'state' => 'out', 'seconds' => $final_seconds]);
            exit();
        }
        
        echo json_encode(['status' => 'error', 'message' => 'Invalid action or state']);
        exit();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// INITIAL PAGE LOAD DATA FETCHING

// 1. Fetch current day attendance record for display
$tl_attendance_record = null;
$tl_is_on_break = false;
$att_check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$stmt_chk = $conn->prepare($att_check_sql);
$stmt_chk->bind_param("is", $tl_user_id, $today);
$stmt_chk->execute();
$tl_attendance_record = $stmt_chk->get_result()->fetch_assoc();
$stmt_chk->close();

$tl_display_punch_in = "--:--";
if ($tl_attendance_record) {
    $tl_display_punch_in = date('h:i A', strtotime($tl_attendance_record['punch_in']));
    
    // Check if on break
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $stmt_bk = $conn->prepare($bk_sql);
    $stmt_bk->bind_param("i", $tl_attendance_record['id']);
    $stmt_bk->execute();
    if ($stmt_bk->get_result()->fetch_assoc()) {
        $tl_is_on_break = true;
    }
    $stmt_bk->close();
}
$tl_total_seconds_worked = getWorkedSeconds($conn, $tl_user_id, $today);


// =========================================================================
// 3. FETCH DYNAMIC DASHBOARD DATA
// =========================================================================

// A. Get TL's Name and Employee ID
$tl_name = "Team Leader";
$tl_emp_id = "EMP-TL01";
$name_query = "SELECT COALESCE(ep.full_name, u.name) as name, COALESCE(ep.emp_id_code, u.employee_id) as emp_id 
               FROM users u 
               LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
               WHERE u.id = ?";
$stmt_name = $conn->prepare($name_query);
$stmt_name->bind_param("i", $tl_user_id);
$stmt_name->execute();
$res_name = $stmt_name->get_result();
if ($row = $res_name->fetch_assoc()) { 
    $tl_name = $row['name'] ? $row['name'] : 'Team Leader'; 
    $tl_emp_id = $row['emp_id'] ? $row['emp_id'] : 'EMP-TL01'; 
}
$stmt_name->close();

// B. Get Total Team Size
$total_team = 0;
$team_q = "SELECT COUNT(id) as total FROM employee_profiles WHERE reporting_to = ?";
$stmt_team = $conn->prepare($team_q);
$stmt_team->bind_param("i", $tl_user_id);
$stmt_team->execute();
$res_team = $stmt_team->get_result();
if ($row = $res_team->fetch_assoc()) { $total_team = $row['total']; }
$stmt_team->close();

// C. Get Today's Attendance Stats
$present = 0;
$late = 0;
$att_q = "SELECT a.status FROM attendance a 
          JOIN employee_profiles ep ON a.user_id = ep.user_id 
          WHERE ep.reporting_to = ? AND a.date = ?";
$stmt_att = $conn->prepare($att_q);
$stmt_att->bind_param("is", $tl_user_id, $today);
$stmt_att->execute();
$res_att = $stmt_att->get_result();
while ($row = $res_att->fetch_assoc()) {
    if ($row['status'] == 'On Time' || $row['status'] == 'WFH') { $present++; }
    if ($row['status'] == 'Late') { $late++; }
}
$stmt_att->close();

$absent = $total_team - ($present + $late);
if ($absent < 0) $absent = 0;
$attendance_percentage = ($total_team > 0) ? round((($present + $late) / $total_team) * 100) : 0;

// D. Get Pending Approvals
$pending_approvals = [];
$leave_q = "SELECT 'Leave' as req_type, lr.id, COALESCE(ep.full_name, u.name, 'Unknown') as emp_name, CONCAT(lr.total_days, ' Days') as details, lr.created_at 
            FROM leave_requests lr JOIN users u ON lr.user_id = u.id JOIN employee_profiles ep ON u.id = ep.user_id
            WHERE ep.reporting_to = ? AND lr.tl_status = 'Pending'";
$stmt_leave = $conn->prepare($leave_q);
if ($stmt_leave) {
    $stmt_leave->bind_param("i", $tl_user_id);
    $stmt_leave->execute();
    $res_leave = $stmt_leave->get_result();
    while ($row = $res_leave->fetch_assoc()) { $pending_approvals[] = $row; }
    $stmt_leave->close();
}

$wfh_q = "SELECT 'WFH' as req_type, w.id, COALESCE(ep.full_name, u.name, 'Unknown') as emp_name, w.shift as details, w.applied_date as created_at 
          FROM wfh_requests w JOIN users u ON w.user_id = u.id JOIN employee_profiles ep ON u.id = ep.user_id
          WHERE ep.reporting_to = ? AND w.status = 'Pending'";
$stmt_wfh = $conn->prepare($wfh_q);
if ($stmt_wfh) {
    $stmt_wfh->bind_param("i", $tl_user_id);
    $stmt_wfh->execute();
    $res_wfh = $stmt_wfh->get_result();
    while ($row = $res_wfh->fetch_assoc()) { $pending_approvals[] = $row; }
    $stmt_wfh->close();
}
usort($pending_approvals, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
$pending_approvals = array_slice($pending_approvals, 0, 4);

// E. Get Active Projects
$active_projects = [];
$proj_q = "SELECT project_name, progress FROM projects WHERE leader_id = ? AND status = 'Active' LIMIT 3";
$stmt_proj = $conn->prepare($proj_q);
if ($stmt_proj) {
    $stmt_proj->bind_param("i", $tl_user_id);
    $stmt_proj->execute();
    $res_proj = $stmt_proj->get_result();
    while ($row = $res_proj->fetch_assoc()) { $active_projects[] = $row; }
    $stmt_proj->close();
}

// F. Get Task Priorities
$high_tasks = 0; $med_tasks = 0; $low_tasks = 0;
$tp_q = "SELECT pt.priority, COUNT(*) as cnt FROM project_tasks pt JOIN projects p ON pt.project_id = p.id WHERE p.leader_id = ? GROUP BY pt.priority";
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

// G. NEW: Get Recent Team Tasks
$recent_tasks = [];
$rt_q = "SELECT pt.task_title, pt.assigned_to, pt.status, p.project_name 
         FROM project_tasks pt 
         JOIN projects p ON pt.project_id = p.id 
         WHERE pt.created_by = ? OR p.leader_id = ? 
         ORDER BY pt.created_at DESC LIMIT 5";
$stmt_rt = $conn->prepare($rt_q);
if ($stmt_rt) {
    $stmt_rt->bind_param("ii", $tl_user_id, $tl_user_id);
    $stmt_rt->execute();
    $res_rt = $stmt_rt->get_result();
    while ($row = $res_rt->fetch_assoc()) {
        $recent_tasks[] = $row;
    }
    $stmt_rt->close();
}

$conn->close();

$sidebarPath = __DIR__ . '/../sidebars.php'; 
if (!file_exists($sidebarPath)) { $sidebarPath = 'sidebars.php'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Leader Dashboard - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary-orange: #ff5e3a; 
            --bg-gray: #f8f9fa; 
            --border-color: #edf2f7; 
            --text-dark: #1f2937;
            --sidebar-width: 95px; 
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-gray); margin: 0; padding: 0; color: var(--text-dark); }
        
        #mainContent { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); transition: all 0.3s ease; padding: 25px 35px; padding-top: 0 !important; }
        @media (max-width: 768px) { #mainContent { margin-left: 0 !important; width: 100% !important; padding: 15px; } }

        .card { background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0,0,0,0.02); padding: 20px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; transition: 0.2s; cursor: pointer; border: 1px solid var(--border-color); background: white; }
        .btn:hover { background: #f3f4f6; }
        .btn-punch { background-color: #111827; color: white; border: none; width: 100%; padding: 12px; font-size: 16px; font-weight: 600; border-radius: 8px; }
        .btn-punch:hover { background-color: #1f2937; }
        
        .profile-ring-container { position: relative; width: 140px; height: 140px; border-radius: 50%; background: conic-gradient(#10b981 0% 65%, #3b82f6 65% 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto; }
        .profile-ring-inner { width: 128px; height: 128px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .profile-img { width: 115px; height: 115px; border-radius: 50%; object-fit: cover; }
        
        /* Custom Scrollbar for widgets */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent">
        <?php 
        $headerPath = __DIR__ . '/../header.php'; 
        if (file_exists($headerPath)) { include($headerPath); } else { include('../header.php'); }
        ?>
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 mt-4 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Team Leader Dashboard</h1>
                <nav class="flex text-gray-500 text-xs mt-1 gap-2">
                    <a href="#" class="hover:text-orange-500">Dashboard</a>
                    <span>/</span>
                    <span class="text-gray-800 font-semibold">Overview</span>
                </nav>
            </div>
            <div class="flex gap-2">
                <button class="btn"><i data-lucide="download" class="w-4 h-4 mr-2"></i> Report</button>
                <div class="btn bg-white"><i data-lucide="calendar" class="w-4 h-4 mr-2 text-gray-400"></i> <?php echo date('M d, Y'); ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
            
            <div class="card flex flex-col items-center justify-between text-center col-span-1 lg:row-span-2 h-full shadow-lg border-orange-100">
                <div class="mt-2">
                    <p class="text-gray-500 font-medium">Welcome Back,</p>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1"><?php echo htmlspecialchars($tl_name); ?></h2>
                    <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-bold mt-2 border border-orange-200 shadow-sm">
                        <i data-lucide="badge-check" class="w-3 h-3"></i> <?php echo htmlspecialchars($tl_emp_id); ?>
                    </span>
                    <h2 class="text-3xl font-bold text-gray-800 mt-4" id="liveClock">00:00 AM</h2>
                </div>

                <div class="my-6 relative">
                    <div class="profile-ring-container">
                        <div class="profile-ring-inner">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($tl_name); ?>&background=random" alt="Profile" class="profile-img">
                        </div>
                    </div>
                </div>

                <div class="bg-orange-500 text-white px-6 py-2 rounded-lg shadow-md mb-4 w-full max-w-[200px]">
                    <span class="text-sm font-medium">Duration : 
                        <span id="productionTimer" 
                              data-running="<?php echo ($tl_attendance_record && !$tl_attendance_record['punch_out'] && !$tl_is_on_break) ? 'true' : 'false'; ?>"
                              data-total="<?php echo $tl_total_seconds_worked; ?>">
                            <?php 
                                $h = floor($tl_total_seconds_worked / 3600);
                                $m = floor(($tl_total_seconds_worked % 3600) / 60);
                                $s = $tl_total_seconds_worked % 60;
                                echo str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
                            ?>
                        </span>
                    </span>
                </div>

                <div class="flex items-center justify-center gap-2 text-gray-600 mb-6" id="statusDisplay">
                    <?php if (!$tl_attendance_record): ?>
                        <i data-lucide="fingerprint" class="w-5 h-5 text-gray-400"></i>
                        <span class="font-medium text-sm">Not Punched In</span>
                    <?php elseif ($tl_attendance_record['punch_out']): ?>
                        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                        <span class="font-medium text-sm">Shift Completed</span>
                    <?php elseif ($tl_is_on_break): ?>
                        <i data-lucide="coffee" class="w-5 h-5 text-orange-500"></i>
                        <span class="font-medium text-sm">On Break</span>
                    <?php else: ?>
                        <i data-lucide="clock" class="w-5 h-5 text-emerald-500"></i>
                        <span class="font-medium text-sm" id="punchInTimeDisplay">Punched In at <?php echo $tl_display_punch_in; ?></span>
                    <?php endif; ?>
                </div>

                <div class="w-full space-y-3" id="attendanceButtons">
                    <?php if (!$tl_attendance_record): ?>
                        <button onclick="handleAjaxAction('punch_in')" class="btn-punch bg-emerald-600 hover:bg-emerald-700">Punch In</button>
                    <?php elseif (!$tl_attendance_record['punch_out']): ?>
                        <button onclick="handleAjaxAction('punch_out')" class="btn-punch bg-slate-900 hover:bg-slate-800 mb-2">Punch Out</button>
                        <?php if ($tl_is_on_break): ?>
                            <button onclick="handleAjaxAction('break_end')" class="btn w-full border-blue-200 text-blue-600 hover:bg-blue-50">
                                <i data-lucide="play" class="w-4 h-4 mr-2"></i> Resume Work
                            </button>
                        <?php else: ?>
                            <button onclick="handleAjaxAction('break_start')" class="btn w-full border-orange-200 text-orange-600 hover:bg-orange-50">
                                <i data-lucide="coffee" class="w-4 h-4 mr-2"></i> Take a Break
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button disabled class="btn-punch bg-gray-300 text-gray-500 cursor-not-allowed">Shift Completed</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-span-1 lg:col-span-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                            <i data-lucide="users" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Total Team</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_team; ?></h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 w-full"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 font-semibold">Subordinate Employees</p>
                    </div>
                </div>

                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                            <i data-lucide="user-check" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Present</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo $present; ?> <span class="text-sm text-gray-400 font-medium">/ <?php echo $total_team; ?></span></h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500" style="width: <?php echo $attendance_percentage; ?>%"></div>
                        </div>
                        <p class="text-xs text-emerald-500 mt-2 font-semibold"><?php echo $attendance_percentage; ?>% Attendance</p>
                    </div>
                </div>

                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                            <i data-lucide="user-minus" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Absent</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo $absent; ?></h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-orange-500" style="width: <?php echo ($total_team > 0) ? ($absent/$total_team*100) : 0; ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 font-semibold">Today's Leaves</p>
                    </div>
                </div>

                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                            <i data-lucide="zap" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Efficiency</p>
                            <h3 class="text-2xl font-bold text-gray-800">92%</h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-500 w-[92%]"></div>
                        </div>
                        <p class="text-xs text-emerald-500 mt-2 font-semibold">+5% growth</p>
                    </div>
                </div>
            
                <div class="card col-span-1 md:col-span-2 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Overall Project Progress</h3>
                        <div class="flex gap-2 text-xs">
                             <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-orange-500 mr-1"></span> Assigned</span>
                             <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-emerald-500 mr-1"></span> Done</span>
                        </div>
                    </div>
                    <div id="taskPerformanceChart" style="min-height: 220px;"></div>
                </div>

                <div class="card col-span-1 md:col-span-2 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Recent Team Tasks</h3>
                        <a href="task_tl.php" class="text-xs text-blue-500 hover:underline">View All</a>
                    </div>
                    <div class="space-y-3 max-h-[220px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php if(count($recent_tasks) > 0): ?>
                            <?php foreach($recent_tasks as $task): ?>
                                <?php 
                                    $status_color = 'bg-gray-100 text-gray-600';
                                    if($task['status'] == 'Completed') $status_color = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
                                    elseif($task['status'] == 'In Progress') $status_color = 'bg-blue-100 text-blue-700 border border-blue-200';
                                    elseif($task['status'] == 'Pending') $status_color = 'bg-orange-100 text-orange-700 border border-orange-200';
                                    
                                    // Extract first assignee name from comma-separated string
                                    $assignees = explode(',', $task['assigned_to']);
                                    $first_assignee = trim($assignees[0]) ? trim($assignees[0]) : 'Unassigned';
                                ?>
                                <div class="flex justify-between items-center p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition">
                                    <div class="flex items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($first_assignee); ?>&background=random" class="w-9 h-9 rounded-full shadow-sm">
                                        <div>
                                            <h5 class="text-sm font-bold text-gray-800 leading-tight"><?php echo htmlspecialchars($task['task_title']); ?></h5>
                                            <p class="text-[10px] text-gray-500 font-medium mt-0.5">
                                                <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($task['project_name']); ?></span> • <?php echo htmlspecialchars($first_assignee); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-1 rounded text-[10px] font-bold <?php echo $status_color; ?>">
                                        <?php echo htmlspecialchars($task['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-6 text-gray-400">
                                <i data-lucide="clipboard-list" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-sm font-medium">No recent tasks found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card">
                <h3 class="font-bold text-lg mb-4">Task Priority</h3>
                <div id="priorityDonutChart" class="flex justify-center"></div>
                <div class="grid grid-cols-3 gap-1 mt-4 text-xs text-gray-600 text-center">
                    <div><span class="block text-red-500 font-bold"><?php echo $high_tasks; ?></span>High</div>
                    <div><span class="block text-yellow-500 font-bold"><?php echo $med_tasks; ?></span>Med</div>
                    <div><span class="block text-emerald-500 font-bold"><?php echo $low_tasks; ?></span>Low</div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Active Projects</h3>
                    <a href="#" class="text-xs text-blue-500 hover:underline">View All</a>
                </div>
                <div class="space-y-4 max-h-[250px] overflow-y-auto custom-scrollbar pr-2">
                    <?php if(count($active_projects) > 0): ?>
                        <?php foreach($active_projects as $proj): ?>
                            <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                                <div class="flex justify-between mb-2">
                                    <h5 class="font-bold text-sm truncate max-w-[80%]"><?php echo htmlspecialchars($proj['project_name']); ?></h5>
                                    <span class="text-xs font-bold text-emerald-600"><?php echo $proj['progress']; ?>%</span>
                                </div>
                                <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo $proj['progress']; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <i data-lucide="briefcase" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm font-medium">No active projects assigned</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Approvals</h3>
                    <?php if(count($pending_approvals) > 0): ?>
                        <span class="badge badge-high bg-red-50 text-red-500 px-2 py-1 rounded text-xs font-bold"><?php echo count($pending_approvals); ?> Pending</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php if(count($pending_approvals) > 0): ?>
                        <?php foreach($pending_approvals as $app): ?>
                            <?php 
                                $bg_color = $app['req_type'] == 'Leave' ? 'bg-blue-50 border-blue-100' : 'bg-orange-50 border-orange-100';
                                $link = $app['req_type'] == 'Leave' ? '../leave_approval.php' : '../wfh_management.php';
                            ?>
                            <div class="flex gap-3 items-center p-2 rounded-lg border <?php echo $bg_color; ?> hover:shadow-sm transition">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['emp_name']); ?>&background=random" class="w-10 h-10 rounded-full shadow-sm">
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($app['emp_name']); ?></p>
                                    <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">
                                        <?php echo $app['req_type'] === 'Leave' ? 'Leave Request (' . $app['details'] . ')' : 'WFH Request (' . $app['details'] . ')'; ?>
                                    </span>
                                </div>
                                <a href="<?php echo $link; ?>" class="text-primary-orange hover:bg-orange-100 p-2 rounded transition" title="Go to Approvals">
                                    <i data-lucide="arrow-right-circle" class="w-5 h-5"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm font-medium">All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        /* ==============================
           1. LIVE CLOCK
           ============================== */
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; 
            hours = String(hours).padStart(2, '0');
            document.getElementById('liveClock').textContent = `${hours}:${minutes} ${ampm}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        /* ==============================
           2. NEW AJAX ATTENDANCE & LIVE TIMER LOGIC
           ============================== */
        let timerInterval;
        let isRunning = <?php echo ($tl_attendance_record && !$tl_attendance_record['punch_out'] && !$tl_is_on_break) ? 'true' : 'false'; ?>;
        let totalSeconds = <?php echo $tl_total_seconds_worked; ?>;
        let currentSessionStart = new Date().getTime(); 

        function formatTimerDisplay(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function runTimer() {
            if (!isRunning) return;
            const now = new Date().getTime();
            const diffSeconds = Math.floor((now - currentSessionStart) / 1000);
            const activeTotal = totalSeconds + diffSeconds;
            document.getElementById('productionTimer').textContent = formatTimerDisplay(activeTotal);
        }

        if (isRunning) {
            timerInterval = setInterval(runTimer, 1000);
        }

        // AJAX function to call the server
        function handleAjaxAction(actionType) {
            const formData = new FormData();
            formData.append('ajax_action', actionType);

            fetch('', { // Posting to self
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateUI(data.state, data.time, data.seconds);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert("Action failed. Reloading...");
                location.reload();
            });
        }

        // Update UI dynamically based on server response
        function updateUI(state, timeStr = null, dbSeconds = null) {
            const btnContainer = document.getElementById('attendanceButtons');
            const statusTxt = document.getElementById('statusDisplay');
            
            // Sync seconds from database
            if (dbSeconds !== null) {
                totalSeconds = parseInt(dbSeconds);
                document.getElementById('productionTimer').textContent = formatTimerDisplay(totalSeconds);
            }

            if (state === 'in') {
                isRunning = true;
                currentSessionStart = new Date().getTime();
                clearInterval(timerInterval);
                timerInterval = setInterval(runTimer, 1000);
                
                let displayTime = timeStr ? timeStr : document.getElementById('punchInTimeDisplay')?.textContent.replace('Punched In at ', '') || '';
                
                btnContainer.innerHTML = `
                    <button onclick="handleAjaxAction('punch_out')" class="btn-punch bg-slate-900 hover:bg-slate-800 mb-2">Punch Out</button>
                    <button onclick="handleAjaxAction('break_start')" class="btn w-full border-orange-200 text-orange-600 hover:bg-orange-50">
                        <i data-lucide="coffee" class="w-4 h-4 mr-2"></i> Take a Break
                    </button>
                `;
                statusTxt.innerHTML = `<i data-lucide="clock" class="w-5 h-5 text-emerald-500"></i> <span class="font-medium text-sm" id="punchInTimeDisplay">Punched In at ${displayTime}</span>`;
            
            } else if (state === 'break') {
                isRunning = false;
                clearInterval(timerInterval);
                
                btnContainer.innerHTML = `
                    <button onclick="handleAjaxAction('punch_out')" class="btn-punch bg-slate-900 hover:bg-slate-800 mb-2">Punch Out</button>
                    <button onclick="handleAjaxAction('break_end')" class="btn w-full border-blue-200 text-blue-600 hover:bg-blue-50">
                        <i data-lucide="play" class="w-4 h-4 mr-2"></i> Resume Work
                    </button>
                `;
                statusTxt.innerHTML = `<i data-lucide="coffee" class="w-5 h-5 text-orange-500"></i> <span class="font-medium text-sm">On Break</span>`;
            
            } else if (state === 'out') {
                isRunning = false;
                clearInterval(timerInterval);
                
                btnContainer.innerHTML = `
                    <button disabled class="btn-punch bg-gray-300 text-gray-500 cursor-not-allowed">Shift Completed</button>
                `;
                statusTxt.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i> <span class="font-medium text-sm">Shift Completed</span>`;
            }
            
            lucide.createIcons();
        }

        /* ==============================
           3. APEXCHARTS CONFIG
           ============================== */
        new ApexCharts(document.querySelector("#taskPerformanceChart"), {
            series: [
                { name: 'Assigned', data: [80, 95, 87, 100, 110, 128] },
                { name: 'Completed', data: [75, 85, 82, 90, 105, 112] }
            ],
            chart: { type: 'bar', height: 220, toolbar: { show: false }, stacked: false },
            colors: ['#F97316', '#10B981'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun'], labels: {style: {fontSize: '10px'}} },
            grid: { borderColor: '#f3f4f6', padding: {top: 0, bottom: 0} }
        }).render();

        new ApexCharts(document.querySelector("#priorityDonutChart"), {
            series: [<?php echo $high_tasks; ?>, <?php echo $med_tasks; ?>, <?php echo $low_tasks; ?>],
            labels: ['High', 'Medium', 'Low'],
            chart: { type: 'donut', height: 200 },
            colors: ['#EF4444', '#FBBF24', '#10B981'],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '65%' } } }
        }).render();
    </script>
</body>
</html>