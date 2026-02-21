<?php
/**
 * HR Dashboard - Premium UI (Crash-Proof Version)
 * Connects to: u957189082_workackv2
 */

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Smart Path Resolution
$dbPath = __DIR__ . '/include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Database connection file not found.");
}

date_default_timezone_set('Asia/Kolkata');
header('Content-Type: text/html; charset=utf-8');

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'] ?? 0;
$today = date('Y-m-d');

// -------------------------------------------------------------------------
// 2. HANDLE AJAX ACTIONS (Approvals & Punching)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    $now = date('Y-m-d H:i:s');
    
    // --- A. PUNCH IN ---
    if ($_POST['action'] === 'punch_in') {
        $check = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        if ($check) {
            $check->bind_param("is", $current_user_id, $today);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $response = ['status' => 'error', 'message' => 'Already punched in today.'];
            } else {
                $status = (date('H:i') > '09:30') ? 'Late' : 'On Time';
                $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, punch_in, status) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isss", $current_user_id, $today, $now, $status);
                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'Punched In successfully', 'time' => date('h:i A')];
                    } else {
                        $response = ['status' => 'error', 'message' => $stmt->error];
                    }
                    $stmt->close();
                } else {
                    $response = ['status' => 'error', 'message' => $conn->error];
                }
            }
            $check->close();
        }
    } 
    
    // --- B. PUNCH OUT ---
    elseif ($_POST['action'] === 'punch_out') {
        $att_rec = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        if($att_rec) {
            $break_sec = 0;
            $br_q = $conn->query("SELECT * FROM attendance_breaks WHERE attendance_id = " . $att_rec['id']);
            while($br = $br_q->fetch_assoc()){
                if($br['break_end']) $break_sec += strtotime($br['break_end']) - strtotime($br['break_start']);
            }
            $worked_sec = (time() - strtotime($att_rec['punch_in'])) - $break_sec;
            $prod_hours = max(0, $worked_sec) / 3600;

            $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE user_id = ? AND date = ?");
            if ($stmt) {
                $stmt->bind_param("sdis", $now, $prod_hours, $current_user_id, $today);
                if ($stmt->execute()) {
                    $response = ['status' => 'success'];
                } else {
                    $response = ['status' => 'error', 'message' => $stmt->error];
                }
                $stmt->close();
            }
        }
    }

    // --- C. TAKE BREAK ---
    elseif ($_POST['action'] === 'take_break') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        if($att_rec) {
            $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
            if($stmt) {
                $stmt->bind_param("is", $att_rec['id'], $now);
                if($stmt->execute()) {
                    $conn->query("UPDATE attendance SET break_time = '1' WHERE id = " . $att_rec['id']);
                    $response = ['status' => 'success'];
                }
                $stmt->close();
            }
        }
    }

    // --- D. END BREAK ---
    elseif ($_POST['action'] === 'end_break') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        if($att_rec) {
            $stmt = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
            if($stmt) {
                $stmt->bind_param("si", $now, $att_rec['id']);
                if($stmt->execute()) {
                    $response = ['status' => 'success'];
                }
                $stmt->close();
            }
        }
    }

    // --- E. UPDATE LEAVE STATUS ---
    elseif ($_POST['action'] === 'update_leave_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status']; 
        $stmt = $conn->prepare("UPDATE leave_requests SET hr_status = ?, status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $status, $status, $id);
            if ($stmt->execute()) $response = ['status' => 'success'];
            $stmt->close();
        }
    } 

    // --- F. UPDATE SHIFT STATUS ---
    elseif ($_POST['action'] === 'update_shift_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE shift_swap_requests SET hr_approval = ?, status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $status, $status, $id);
            if ($stmt->execute()) $response = ['status' => 'success'];
            $stmt->close();
        }
    }

    echo json_encode($response);
    exit; 
}

// -------------------------------------------------------------------------
// 3. FETCH DASHBOARD DATA
// -------------------------------------------------------------------------

function safeCount($conn, $sql) {
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) return $res->fetch_assoc()['c'] ?? 0;
    return 0;
}

// A. Stats Metrics 
$total_emp = safeCount($conn, "SELECT COUNT(*) as c FROM employee_profiles WHERE status='Active'");
$new_joinees = safeCount($conn, "SELECT COUNT(*) as c FROM employee_profiles WHERE MONTH(joining_date) = MONTH(CURDATE()) AND YEAR(joining_date) = YEAR(CURDATE()) AND status='Active'");
$on_leave = safeCount($conn, "SELECT COUNT(*) as c FROM leave_requests WHERE status='Approved' AND '$today' BETWEEN start_date AND end_date");
$pending_leaves = safeCount($conn, "SELECT COUNT(*) as c FROM leave_requests WHERE status='Pending'");

// B. Current User Attendance Calculation
$today_att = null;
$total_seconds_worked = 0;
$is_on_break = false;
$display_punch_in = "--:--";

$stmt = $conn->prepare("SELECT id, punch_in, punch_out, status FROM attendance WHERE user_id = ? AND date = ?");
if ($stmt) {
    $stmt->bind_param("is", $current_user_id, $today);
    $stmt->execute();
    $today_att = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($today_att) {
    $display_punch_in = date('h:i A', strtotime($today_att['punch_in']));
    $break_seconds = 0;
    
    $br_q = $conn->query("SELECT * FROM attendance_breaks WHERE attendance_id = " . $today_att['id']);
    if($br_q) {
        while ($br = $br_q->fetch_assoc()) {
            if ($br['break_end']) {
                $break_seconds += strtotime($br['break_end']) - strtotime($br['break_start']);
            } else {
                $is_on_break = true;
                $break_seconds += time() - strtotime($br['break_start']);
            }
        }
    }
    
    if (!$today_att['punch_out']) {
        $total_seconds_worked = (time() - strtotime($today_att['punch_in'])) - $break_seconds;
    } else {
        $total_seconds_worked = (strtotime($today_att['punch_out']) - strtotime($today_att['punch_in'])) - $break_seconds;
    }
    $total_seconds_worked = max(0, $total_seconds_worked);
}
$total_hours_today = gmdate("H:i:s", $total_seconds_worked);

// C. Department Data (FIXED TO SHOW IN GRAPH)
$depts = [];
$max_count = 1; 
$res = $conn->query("
    SELECT 
        COALESCE(NULLIF(department, ''), 'Unassigned') as dept_name, 
        COUNT(*) as count 
    FROM employee_profiles 
    WHERE status='Active' 
    GROUP BY dept_name 
    ORDER BY count DESC 
    LIMIT 6
");

if ($res && $res->num_rows > 0) {
    while($row = $res->fetch_assoc()){
        $depts[] = $row;
        if($row['count'] > $max_count) $max_count = $row['count'];
    }
} else {
    // Dummy Data if no active employees found (for UI testing)
    $depts = [
        ['dept_name' => 'IT Support', 'count' => 5],
        ['dept_name' => 'HR', 'count' => 3],
        ['dept_name' => 'Development', 'count' => 8]
    ];
    $max_count = 8;
}

// D. Fetch Lists
$announcements = [];
$res = $conn->query("SELECT * FROM announcements WHERE is_archived=0 ORDER BY created_at DESC LIMIT 2");
if ($res) { while($row = $res->fetch_assoc()) $announcements[] = $row; }

$leaves = [];
$stmt = $conn->prepare("SELECT lr.*, ep.full_name, ep.profile_img, ep.designation FROM leave_requests lr JOIN employee_profiles ep ON lr.user_id = ep.user_id WHERE lr.status='Pending' ORDER BY lr.created_at DESC LIMIT 3");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) { while($row = $result->fetch_assoc()) $leaves[] = $row; }
    $stmt->close();
}

$shifts = [];
$stmt = $conn->prepare("SELECT ss.*, ep.full_name, ep.department FROM shift_swap_requests ss JOIN employee_profiles ep ON ss.user_id = ep.user_id WHERE ss.status='Pending' ORDER BY ss.created_at DESC LIMIT 3");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) { while($row = $result->fetch_assoc()) $shifts[] = $row; }
    $stmt->close();
}

$type_query = $conn->query("
    SELECT 
        SUM(CASE WHEN employment_type = 'Permanent' THEN 1 ELSE 0 END) as full_time,
        SUM(CASE WHEN employment_type = 'Contract' THEN 1 ELSE 0 END) as contract,
        SUM(CASE WHEN employment_type = 'Intern' THEN 1 ELSE 0 END) as probation
    FROM employee_onboarding 
    WHERE status = 'Completed'
");
$workforce = $type_query ? $type_query->fetch_assoc() : [];
$full_time = $workforce['full_time'] ?? 0;
$contract = $workforce['contract'] ?? 0;
$probation = $workforce['probation'] ?? 0;

$job_sql = "SELECT COUNT(*) as total_jobs FROM jobs";
$job_res = $conn->query($job_sql);
$total_jobs = $job_res ? $job_res->fetch_assoc()['total_jobs'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | Premium UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #0d9488; --primary-dark: #0f766e; --bg-main: #f8fafc; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; color: #1e293b; overflow-x: hidden; }
        
        .dashboard-container { margin-left: 95px; min-height: 100vh; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 1024px) { .dashboard-container { margin-left: 0; padding: 1rem; } }
        
        .glass-card { background-color: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; padding: 1.5rem; }
        
        .attendance-circle {
            position: relative; width: 140px; height: 140px; margin: 0 auto;
            display: flex; align-items: center; justify-content: center;
        }
        
        .spinner { border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top: 2px solid white; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .bar-container { display: flex; align-items: flex-end; gap: 4px; height: 60px; }
        .bar { width: 8px; background: #134e4a; border-radius: 10px; }
        .bar-light { background: #e2e8f0; }
    </style>
</head>
<body class="antialiased">

    <?php 
    if (file_exists($sidebarPath)) include $sidebarPath; 
    if (file_exists($headerPath)) include $headerPath; 
    ?>

    <div class="dashboard-container">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">HR Dashboard</h1>
                <nav class="flex text-sm text-slate-400 mt-1 font-medium items-center">
                    <i data-lucide="layout-grid" class="w-4 h-4 mr-2"></i>
                    <span>Overview</span>
                </nav>
            </div>
            <div class="text-sm text-slate-500 font-medium bg-white px-4 py-2 rounded-full border shadow-sm">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card flex items-center justify-between border-l-4 border-teal-600 hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Employees</p>
                    <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?php echo number_format($total_emp); ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-teal-600"><i data-lucide="users" class="w-5 h-5"></i></div>
            </div>
            <div class="glass-card flex items-center justify-between border-l-4 border-indigo-600 hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">New Joinees</p>
                    <h3 class="text-2xl font-extrabold text-slate-800 mt-1">+<?php echo $new_joinees; ?></h3>
                    <p class="text-[10px] text-slate-400">This Month</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600"><i data-lucide="user-plus" class="w-5 h-5"></i></div>
            </div>
            <div class="glass-card flex items-center justify-between border-l-4 border-orange-500 hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">On Leave Today</p>
                    <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?php echo $on_leave; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-500"><i data-lucide="calendar-minus" class="w-5 h-5"></i></div>
            </div>
            <div class="glass-card flex items-center justify-between border-l-4 border-rose-500 hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Approvals</p>
                    <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?php echo $pending_leaves; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-500"><i data-lucide="clock" class="w-5 h-5"></i></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <div class="lg:col-span-4 space-y-6">
                
                <div class="glass-card text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-teal-500 to-emerald-500"></div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">My Attendance</p>
                    
                    <div class="attendance-circle mb-4">
                        <svg class="absolute inset-0 w-full h-full transform -rotate-90">
                            <circle cx="70" cy="70" r="62" stroke="#f1f5f9" stroke-width="8" fill="transparent"></circle>
                            <circle id="progressRing" cx="70" cy="70" r="62" stroke="<?= $is_on_break ? '#f59e0b' : '#0d9488' ?>" stroke-width="8" fill="transparent" stroke-dasharray="390" stroke-dashoffset="390" class="transition-all duration-1000"></circle>
                        </svg>
                        <div class="relative z-10 text-center">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total Hours</p>
                            <p class="text-2xl font-extrabold text-slate-800" id="timerDisplay" data-total="<?= $total_seconds_worked ?>"><?= $total_hours_today ?></p>
                        </div>
                    </div>

                    <div class="flex justify-center mb-4">
                        <?php if (!$today_att): ?>
                            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 text-slate-600 text-xs font-bold border border-slate-200">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i> Not Punched In
                            </div>
                        <?php elseif ($today_att['punch_out']): ?>
                            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold border border-emerald-100">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Shift Completed
                            </div>
                        <?php elseif ($is_on_break): ?>
                            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-orange-50 text-orange-600 text-xs font-bold border border-orange-100">
                                <i data-lucide="coffee" class="w-3.5 h-3.5"></i> On Break
                            </div>
                        <?php else: ?>
                            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-teal-50 text-teal-700 text-xs font-bold border border-teal-100">
                                <i data-lucide="activity" class="w-3.5 h-3.5"></i> Currently Working
                            </div>
                        <?php endif; ?>
                    </div>

                    <p class="text-[11px] text-slate-400 mb-4 flex items-center justify-center gap-1">
                        <i data-lucide="fingerprint" class="w-3 h-3 text-teal-600"></i> Punch In at <?= $display_punch_in ?>
                    </p>

                    <div class="w-full">
                        <?php if (!$today_att): ?>
                            <button onclick="punchAction('punch_in')" id="btnPunchIn" class="w-full py-3.5 bg-[#0d9488] hover:bg-[#0b7a6f] text-white rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-md">
                                <i data-lucide="log-in" class="w-5 h-5"></i> Punch In
                            </button>
                        <?php elseif (!$today_att['punch_out']): ?>
                            <?php if (!$is_on_break): ?>
                                <div class="grid grid-cols-2 gap-3">
                                    <button onclick="punchAction('take_break')" id="btnBreak" class="w-full py-3.5 bg-[#f59e0b] hover:bg-[#d97706] text-white rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-md">
                                        <i data-lucide="coffee" class="w-5 h-5"></i> Break
                                    </button>
                                    <button onclick="punchAction('punch_out')" id="btnPunchOut" class="w-full py-3.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-md">
                                        <i data-lucide="log-out" class="w-5 h-5"></i> Punch Out
                                    </button>
                                </div>
                            <?php else: ?>
                                <button onclick="punchAction('end_break')" id="btnEndBreak" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-md">
                                    <i data-lucide="play" class="w-5 h-5"></i> End Break
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button disabled class="w-full py-3.5 bg-gray-100 text-gray-400 rounded-xl font-bold flex items-center justify-center gap-2 cursor-not-allowed">
                                <i data-lucide="calendar-check" class="w-5 h-5"></i> Done for Today
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="glass-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800">Announcements</h3>
                        <a href="#" class="text-xs font-bold text-teal-600 underline">View All</a>
                    </div>
                    <div class="space-y-4">
                        <?php if(!empty($announcements)): ?>
                            <?php foreach($announcements as $ann): ?>
                            <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 hover:shadow-sm transition">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-[10px] font-bold uppercase text-teal-600 bg-teal-50 px-2 py-0.5 rounded"><?php echo htmlspecialchars($ann['category']); ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo date('d M', strtotime($ann['created_at'])); ?></span>
                                </div>
                                <h4 class="text-sm font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($ann['title']); ?></h4>
                                <p class="text-xs text-slate-500 line-clamp-2"><?php echo htmlspecialchars(substr($ann['message'], 0, 80)); ?>...</p>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 text-center py-4">No new announcements.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-6">
                
                <div class="glass-card">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Department Distribution</h3>
                            <p class="text-xs text-slate-400">Active employees per department</p>
                        </div>
                        <a href="../employee_management.php" class="text-xs font-bold text-teal-600 bg-teal-50 border border-teal-100 px-3 py-1.5 rounded-lg hover:bg-teal-100 transition-colors">Manage Directory</a>
                    </div>
                    
                    <div class="flex items-end justify-around gap-2 h-40 mb-4 px-4 border-b border-slate-100 pb-2">
                        <?php if(!empty($depts)): foreach($depts as $d): 
                            $height = ($d['count'] / $max_count) * 100;
                        ?>
                        <div class="flex flex-col items-center w-full group cursor-pointer h-full justify-end">
                            <div class="relative w-full max-w-[40px] bg-slate-100 rounded-t-lg overflow-visible h-full flex items-end">
                                <div style="height: <?php echo $height; ?>%;" class="w-full bg-teal-700 group-hover:bg-teal-500 rounded-t-lg transition-all duration-300 relative shadow-sm">
                                    <span class="absolute -top-6 left-1/2 transform -translate-x-1/2 text-xs font-bold text-slate-700 opacity-0 group-hover:opacity-100 transition-opacity bg-white px-2 py-0.5 rounded shadow-sm border border-slate-200 z-10">
                                        <?php echo $d['count']; ?>
                                    </span>
                                </div>
                            </div>
                            <span class="text-[9px] font-bold text-slate-500 mt-2 truncate w-full text-center" title="<?php echo htmlspecialchars($d['dept_name']); ?>">
                                <?php echo htmlspecialchars($d['dept_name']); ?>
                            </span>
                        </div>
                        <?php endforeach; else: ?>
                            <p class="text-xs text-slate-400 text-center w-full pb-4">No department data found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="glass-card">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800">Leave Requests</h3>
                            <a href="../leave_approval.php" class="text-[10px] font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded-full border border-teal-100 hover:bg-teal-100 transition">View All</a>
                        </div>
                        <div class="space-y-3">
                            <?php if(!empty($leaves)): foreach($leaves as $lv): 
                                $img = $lv['profile_img'] ? $lv['profile_img'] : "https://ui-avatars.com/api/?name=".urlencode($lv['full_name']);
                                if(!str_starts_with($img, 'http') && strpos($img, 'assets') === false) $img = '../assets/profiles/' . $img;
                            ?>
                            <div class="flex items-center justify-between p-3 border border-slate-100 rounded-xl hover:bg-slate-50 transition shadow-sm">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo htmlspecialchars($img); ?>" class="w-8 h-8 rounded-full object-cover">
                                    <div>
                                        <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($lv['full_name']); ?></p>
                                        <p class="text-[10px] text-slate-400"><?php echo date('d M', strtotime($lv['start_date'])) . ' - ' . date('d M', strtotime($lv['end_date'])); ?></p>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold uppercase text-orange-600 bg-orange-50 px-2 py-1 rounded"><?php echo htmlspecialchars($lv['leave_type']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                                <div class="text-center py-6 text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                    <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-emerald-400"></i>
                                    <p class="text-xs font-medium">All caught up!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="glass-card">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg">Workforce Info</h3>
                                <p class="text-xs text-slate-400">By Employment Type</p>
                            </div>
                        </div>
                        
                        <div class="bar-container mb-6 border-b border-slate-100 pb-4">
                            <div class="bar" style="height: 40%"></div><div class="bar" style="height: 60%"></div><div class="bar" style="height: 30%"></div><div class="bar" style="height: 70%"></div><div class="bar" style="height: 50%"></div><div class="bar" style="height: 80%"></div><div class="bar" style="height: 40%"></div><div class="bar" style="height: 65%"></div><div class="bar" style="height: 45%"></div><div class="bar" style="height: 90%"></div><div class="bar" style="height: 35%"></div><div class="bar" style="height: 75%"></div><div class="bar" style="height: 55%"></div><div class="bar" style="height: 85%"></div><div class="bar" style="height: 40%"></div><div class="bar" style="height: 60%"></div>
                            <div class="bar bar-light" style="height: 40%"></div><div class="bar bar-light" style="height: 60%"></div><div class="bar bar-light" style="height: 30%"></div><div class="bar bar-light" style="height: 70%"></div><div class="bar bar-light" style="height: 50%"></div><div class="bar bar-light" style="height: 80%"></div><div class="bar bar-light" style="height: 40%"></div><div class="bar bar-light" style="height: 65%"></div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-teal-200 transition">
                                <p class="text-[10px] font-bold text-slate-500 uppercase flex items-center gap-1.5 mb-1">
                                    <span class="w-2 h-2 rounded-full bg-teal-700"></span> Permanent
                                </p>
                                <p class="text-xl font-extrabold text-slate-800"><?= $full_time ?></p>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 hover:border-blue-200 transition">
                                <p class="text-[10px] font-bold text-slate-500 uppercase flex items-center gap-1.5 mb-1">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Contract
                                </p>
                                <p class="text-xl font-extrabold text-slate-800"><?= $contract ?></p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // LIVE TIMER FOR ATTENDANCE
        document.addEventListener('DOMContentLoaded', function() {
            const timerElement = document.getElementById('timerDisplay');
            const progressRing = document.getElementById('progressRing');
            
            if(!timerElement) return;

            const isRunning = <?= ($today_att && !$today_att['punch_out'] && !$is_on_break) ? 'true' : 'false' ?>;
            let totalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
            const startTime = new Date().getTime(); 

            function updateTimer() {
                if (!isRunning) return; 

                const now = new Date().getTime();
                const diffSeconds = Math.floor((now - startTime) / 1000);
                const currentTotal = totalSeconds + diffSeconds;
                
                const hours = Math.floor(currentTotal / 3600);
                const minutes = Math.floor((currentTotal % 3600) / 60);
                const seconds = currentTotal % 60;
                
                timerElement.innerText = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');

                // Update Progress Ring (Assume 9 hours shift = 32400 seconds)
                const maxSeconds = 32400; 
                const circumference = 390;
                const progress = Math.min(currentTotal / maxSeconds, 1);
                const offset = circumference - (progress * circumference);
                
                if(progressRing) progressRing.style.strokeDashoffset = offset;
            }

            if (isRunning) {
                setInterval(updateTimer, 1000);
            } else {
                const maxSeconds = 32400; 
                const circumference = 390;
                const progress = Math.min(totalSeconds / maxSeconds, 1);
                if(progressRing) progressRing.style.strokeDashoffset = circumference - (progress * circumference);
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
    </script>
</body>
</html>