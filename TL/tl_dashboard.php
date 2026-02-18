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


// D. Get Pending Approvals (FIXED: Using PHP Array Merge to avoid Collation errors)
$pending_approvals = [];

// Query 1: Fetch Leave Requests
$leave_q = "
    SELECT 'Leave' as req_type, lr.id, COALESCE(ep.full_name, u.name, 'Unknown') as emp_name, lr.total_days as details, lr.created_at 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.id 
    JOIN employee_profiles ep ON u.id = ep.user_id
    WHERE ep.reporting_to = ? AND lr.tl_status = 'Pending'
";
$stmt_leave = $conn->prepare($leave_q);
if ($stmt_leave) {
    $stmt_leave->bind_param("i", $tl_user_id);
    $stmt_leave->execute();
    $res_leave = $stmt_leave->get_result();
    while ($row = $res_leave->fetch_assoc()) {
        $row['details'] = $row['details'] . ' Days'; // Format neatly
        $pending_approvals[] = $row;
    }
    $stmt_leave->close();
}

// Query 2: Fetch WFH Requests
$wfh_q = "
    SELECT 'WFH' as req_type, w.id, COALESCE(ep.full_name, u.name, 'Unknown') as emp_name, w.shift as details, w.applied_date as created_at 
    FROM wfh_requests w 
    JOIN users u ON w.user_id = u.id 
    JOIN employee_profiles ep ON u.id = ep.user_id
    WHERE ep.reporting_to = ? AND w.status = 'Pending'
";
$stmt_wfh = $conn->prepare($wfh_q);
if ($stmt_wfh) {
    $stmt_wfh->bind_param("i", $tl_user_id);
    $stmt_wfh->execute();
    $res_wfh = $stmt_wfh->get_result();
    while ($row = $res_wfh->fetch_assoc()) {
        $pending_approvals[] = $row;
    }
    $stmt_wfh->close();
}

// Sort the combined results by Date (Newest first)
usort($pending_approvals, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to the latest 4 requests for the dashboard widget
$pending_approvals = array_slice($pending_approvals, 0, 4);

// Close connection to save resources
$conn->close();

// Sidebar path fallback
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

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-gray);
            margin: 0; padding: 0;
            color: var(--text-dark);
        }

        /* --- LAYOUT --- */
        #mainContent {
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width));
            transition: all 0.3s ease; 
            padding: 25px 35px;
            padding-top: 0 !important;
        }

        @media (max-width: 768px) {
            #mainContent { 
                margin-left: 0 !important; 
                width: 100% !important;
                padding: 15px; 
            }
        }

        /* --- CARDS --- */
        .card { 
            background: white; 
            border-radius: 12px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
            padding: 20px;
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;
            transition: 0.2s; cursor: pointer; border: 1px solid var(--border-color); background: white;
        }
        .btn:hover { background: #f3f4f6; }
        
        .btn-punch {
            background-color: #111827; 
            color: white; border: none; width: 100%;
            padding: 12px; font-size: 16px; font-weight: 600; border-radius: 8px;
        }
        .btn-punch:hover { background-color: #1f2937; }
        
        /* --- ATTENDANCE RING --- */
        .profile-ring-container {
            position: relative; width: 140px; height: 140px; border-radius: 50%;
            background: conic-gradient(#10b981 0% 65%, #3b82f6 65% 100%);
            display: flex; align-items: center; justify-content: center; margin: 0 auto;
        }
        .profile-ring-inner {
            width: 128px; height: 128px; background: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; z-index: 10;
        }
        .profile-img {
            width: 115px; height: 115px; border-radius: 50%; object-fit: cover;
        }
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
                    <span class="text-sm font-medium">Production : <span id="productionTimer">0.00</span> hrs</span>
                </div>

                <div class="flex items-center justify-center gap-2 text-gray-600 mb-6" id="statusDisplay">
                    <i data-lucide="fingerprint" class="w-5 h-5 text-orange-500"></i>
                    <span class="font-medium text-sm">Not Punched In</span>
                </div>

                <div class="w-full space-y-3">
                    <button id="mainPunchBtn" onclick="handlePunch()" class="btn-punch">Punch In</button>
                    <button id="breakBtn" onclick="toggleBreak()" class="btn w-full border-orange-200 text-orange-600 hover:bg-orange-50 hidden">
                        <i data-lucide="coffee" class="w-4 h-4 mr-2"></i> Take a Break
                    </button>
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
                        <h3 class="font-bold text-lg">Task Progress</h3>
                        <div class="flex gap-2 text-xs">
                             <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-orange-500 mr-1"></span> Assigned</span>
                             <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-emerald-500 mr-1"></span> Done</span>
                        </div>
                    </div>
                    <div id="taskPerformanceChart" style="min-height: 220px;"></div>
                </div>

                <div class="card col-span-1 md:col-span-2 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Presence</h3>
                    </div>
                    <div id="presenceHeatmap" style="min-height: 220px;"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card">
                <h3 class="font-bold text-lg mb-4">Task Priority</h3>
                <div id="priorityDonutChart" class="flex justify-center"></div>
                <div class="grid grid-cols-3 gap-1 mt-4 text-xs text-gray-600 text-center">
                    <div><span class="block text-red-500 font-bold">15</span>High</div>
                    <div><span class="block text-yellow-500 font-bold">22</span>Med</div>
                    <div><span class="block text-emerald-500 font-bold">8</span>Low</div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Active Projects</h3>
                    <button class="text-xs text-blue-500 hover:underline">View All</button>
                </div>
                <div class="space-y-4">
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">ERP Migration</h5>
                            <span class="text-xs font-bold text-emerald-600">75%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full">
                            <div class="h-full bg-emerald-500 w-3/4 rounded-full"></div>
                        </div>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">Mobile UI</h5>
                            <span class="text-xs font-bold text-orange-600">42%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full">
                            <div class="h-full bg-orange-500 w-[42%] rounded-full"></div>
                        </div>
                    </div>
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
                                $link = $app['req_type'] == 'Leave' ? '../leave_approvals.php' : '../wfh_management.php';
                            ?>
                            <div class="flex gap-3 items-center p-2 rounded-lg border <?php echo $bg_color; ?>">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['emp_name']); ?>&background=random" class="w-10 h-10 rounded-full">
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($app['emp_name']); ?></p>
                                    <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">
                                        <?php echo $app['req_type'] === 'Leave' ? 'Leave Request (' . $app['details'] . ')' : 'WFH Request (' . $app['details'] . ')'; ?>
                                    </span>
                                </div>
                                <a href="<?php echo $link; ?>" class="text-primary-orange hover:bg-orange-100 p-2 rounded transition" title="Go to Approvals">
                                    <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
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
           1. LIVE CLOCK & DATE LOGIC
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
           2. ATTENDANCE & TIMER LOGIC
           ============================== */
        let timerInterval;
        let secondsElapsed = 0;
        let isPunchedIn = false;
        let isOnBreak = false;

        window.addEventListener('load', () => {
            const savedState = localStorage.getItem('tl_attendanceState');
            const savedTime = localStorage.getItem('tl_punchTime');
            const savedSeconds = localStorage.getItem('tl_secondsElapsed');

            if (savedSeconds) secondsElapsed = parseInt(savedSeconds);

            if (savedState === 'punchedIn') {
                isPunchedIn = true;
                setUIState('in', savedTime);
                startTimer();
            } else if (savedState === 'onBreak') {
                isPunchedIn = true;
                isOnBreak = true;
                setUIState('break', savedTime);
            }
            updateTimerDisplay();
        });

        function handlePunch() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            if (!isPunchedIn) {
                isPunchedIn = true;
                localStorage.setItem('tl_attendanceState', 'punchedIn');
                localStorage.setItem('tl_punchTime', timeString);
                setUIState('in', timeString);
                startTimer();
            } else {
                stopTimer();
                isPunchedIn = false;
                isOnBreak = false;
                secondsElapsed = 0; 
                localStorage.removeItem('tl_attendanceState');
                localStorage.removeItem('tl_punchTime');
                localStorage.removeItem('tl_secondsElapsed');
                setUIState('out');
                updateTimerDisplay();
            }
        }

        function toggleBreak() {
            if (!isPunchedIn) return;

            if (!isOnBreak) {
                isOnBreak = true;
                stopTimer(); 
                localStorage.setItem('tl_attendanceState', 'onBreak');
                setUIState('break', localStorage.getItem('tl_punchTime'));
            } else {
                isOnBreak = false;
                startTimer(); 
                localStorage.setItem('tl_attendanceState', 'punchedIn');
                setUIState('in', localStorage.getItem('tl_punchTime'));
            }
        }

        function startTimer() {
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                secondsElapsed++;
                localStorage.setItem('tl_secondsElapsed', secondsElapsed);
                updateTimerDisplay();
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
        }

        function updateTimerDisplay() {
            const hours = Math.floor(secondsElapsed / 3600);
            const minutes = Math.floor((secondsElapsed % 3600) / 60);
            let displayVal = `${hours}.${String(minutes).padStart(2, '0')}`;
            document.getElementById('productionTimer').textContent = displayVal;
        }

        function setUIState(state, time = '') {
            const mainBtn = document.getElementById('mainPunchBtn');
            const breakBtn = document.getElementById('breakBtn');
            const statusTxt = document.getElementById('statusDisplay');

            if (state === 'in') {
                mainBtn.textContent = "Punch Out";
                mainBtn.className = "btn-punch bg-slate-900 hover:bg-slate-800"; 
                breakBtn.classList.remove('hidden');
                breakBtn.innerHTML = '<i data-lucide="coffee" class="w-4 h-4 mr-2"></i> Take a Break';
                
                statusTxt.innerHTML = `<i data-lucide="clock" class="w-5 h-5 text-emerald-500"></i> Punch In at ${time}`;
            } else if (state === 'break') {
                mainBtn.textContent = "Punch Out"; 
                breakBtn.classList.remove('hidden');
                breakBtn.innerHTML = '<i data-lucide="play" class="w-4 h-4 mr-2"></i> Resume Work';
                
                statusTxt.innerHTML = `<i data-lucide="coffee" class="w-5 h-5 text-orange-500"></i> On Break`;
            } else {
                mainBtn.textContent = "Punch In";
                mainBtn.className = "btn-punch bg-emerald-600 hover:bg-emerald-700"; 
                breakBtn.classList.add('hidden');
                
                statusTxt.innerHTML = `<i data-lucide="fingerprint" class="w-5 h-5 text-gray-400"></i> Not Punched In`;
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

        new ApexCharts(document.querySelector("#presenceHeatmap"), {
            series: [
                { name: 'W1', data: [12, 11, 12, 10, 12] },
                { name: 'W2', data: [10, 12, 12, 12, 11] },
                { name: 'W3', data: [12, 12, 10, 12, 12] },
            ],
            chart: { type: 'heatmap', height: 220, toolbar: { show: false } },
            colors: ['#F97316'],
            plotOptions: { heatmap: { radius: 2, enableShades: true, shadeIntensity: 0.5 } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['M', 'T', 'W', 'T', 'F'], labels: {style: {fontSize: '10px'}} }
        }).render();

        new ApexCharts(document.querySelector("#priorityDonutChart"), {
            series: [15, 22, 8],
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