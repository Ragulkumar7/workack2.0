<?php
// employee_attendance_details.php - Management View of Employee Attendance

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- ROBUST DATABASE CONNECTION ---
$dbPath = '../include/db_connect.php';
if (!file_exists($dbPath)) {
    $dbPath = './include/db_connect.php';
}
require_once $dbPath;

// Check Login
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// 2. DATA CONTEXT
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['id']);

// A. Fetch Employee Profile Data
$sql_profile = "SELECT full_name, emp_id_code, designation, joining_date, shift_timings, profile_img FROM employee_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql_profile);
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();
$stmt->close();

$employeeName = $profile_data['full_name'] ?? "Unknown Employee";
$employeeID   = $profile_data['emp_id_code'] ?? "EMP-0000";
$designation  = $profile_data['designation'] ?? "Staff";
$joining_date = !empty($profile_data['joining_date']) ? $profile_data['joining_date'] : '2000-01-01'; // Fallback
$shift_timings = $profile_data['shift_timings'] ?? '09:00 AM - 06:00 PM';

$profile_img = $profile_data['profile_img'] ?? '';
if(empty($profile_img) || $profile_img === 'default_user.png') {
    $profile_img = "https://ui-avatars.com/api/?name=".urlencode($employeeName)."&background=0d9488&color=fff&bold=true";
} elseif (!str_starts_with($profile_img, 'http') && strpos($profile_img, 'assets/profiles/') === false) {
    $profile_img = '../assets/profiles/' . $profile_img;
}

// =========================================================================================
// FILTER LOGIC & DATE BOUNDARIES
// =========================================================================================
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'monthly';

$filter_date  = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d'); 
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');
$from_date    = isset($_GET['from_date']) && !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date      = isset($_GET['to_date']) && !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$today = date('Y-m-d');

// Determine Start and End Dates based on the filter
if ($filter_type === 'daily') {
    $start_date = $filter_date;
    $end_date = $filter_date;
    $currentDisplay = date('d M Y', strtotime($filter_date));
} elseif ($filter_type === 'monthly') {
    $start_date = $filter_month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    $currentDisplay = date('F Y', strtotime($start_date));
} elseif ($filter_type === 'range') {
    if (strtotime($to_date) < strtotime($from_date)) {
        $temp = $from_date; $from_date = $to_date; $to_date = $temp;
    }
    $start_date = $from_date;
    $end_date = $to_date;
    $currentDisplay = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
}

// B. Fetch DB Attendance Records
$sql_att = "SELECT * FROM attendance WHERE user_id = ? AND date >= ? AND date <= ?";
$stmt = $conn->prepare($sql_att);
$stmt->bind_param("iss", $view_user_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$db_attendance = [];
while ($row = $result->fetch_assoc()) {
    $db_attendance[$row['date']] = $row;
}
$stmt->close();

// Fetch Approved Leaves to correctly mark "On Leave" instead of "Absent"
$sql_leaves = "SELECT start_date, end_date FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND end_date >= ? AND start_date <= ?";
$stmt_leaves = $conn->prepare($sql_leaves);
$stmt_leaves->bind_param("iss", $view_user_id, $start_date, $end_date);
$stmt_leaves->execute();
$res_leaves = $stmt_leaves->get_result();
$approved_leaves = [];
while ($l_row = $res_leaves->fetch_assoc()) {
    $l_start = strtotime($l_row['start_date']);
    $l_end = strtotime($l_row['end_date']);
    for ($i = $l_start; $i <= $l_end; $i += 86400) {
        $approved_leaves[date('Y-m-d', $i)] = true;
    }
}
$stmt_leaves->close();

// =========================================================================================
// CALENDAR MAPPING & AUTO-ABSENT LOGIC
// =========================================================================================
$attendanceRecords = [];
$total_production = 0;
$late_days = 0;
$total_overtime = 0;
$present_count = 0;

$time_parts = explode('-', $shift_timings);
$shift_start_str = trim($time_parts[0]);
$joining_dt = new DateTime($joining_date);

// Loop through dates from END to START (Descending order for the UI)
$current_dt = new DateTime($end_date);
$start_dt = new DateTime($start_date);

while ($current_dt >= $start_dt) {
    $date_str = $current_dt->format('Y-m-d');
    $is_future = ($date_str > $today);
    $is_before_joining = ($current_dt < $joining_dt);
    $day_of_week = $current_dt->format('N'); // 1 (Mon) - 7 (Sun)

    if (isset($db_attendance[$date_str])) {
        // --- RECORD EXISTS IN DB ---
        $row = $db_attendance[$date_str];
        
        $b_q = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as t_break FROM attendance_breaks WHERE attendance_id = " . $row['id'] . " AND break_end IS NOT NULL");
        $b_sec = $b_q->fetch_assoc()['t_break'] ?? 0;
        if ($b_sec == 0 && !empty($row['break_time'])) { $b_sec = intval($row['break_time']) * 60; }
        $break_min = floor($b_sec / 60);

        $prod = floatval($row['production_hours']);
        if ($prod == 0 && !empty($row['punch_in']) && !empty($row['punch_out'])) {
            $in = strtotime($row['punch_in']);
            $out = strtotime($row['punch_out']);
            $prod = max(0, (($out - $in) - $b_sec) / 3600);
        }
        
        $total_production += $prod;
        $overtime = ($prod > 9) ? ($prod - 9) : 0;
        $total_overtime += $overtime;

        $late_msg = "-";
        $is_late = false;
        $is_absent_db = stripos($row['status'], 'Absent') !== false;

        if (!$is_absent_db) { $present_count++; }

        if (!empty($row['punch_in']) && !$is_absent_db) {
            $shift_start_ts = strtotime($row['date'] . ' ' . $shift_start_str);
            $punch_in_ts = strtotime($row['punch_in']);

            if ($punch_in_ts > ($shift_start_ts + 60)) { // 1 min grace
                $delay_mins = round(($punch_in_ts - $shift_start_ts) / 60);
                if ($delay_mins >= 60) {
                    $late_msg = floor($delay_mins / 60) . "h " . ($delay_mins % 60) . "m";
                } else {
                    $late_msg = $delay_mins . " mins";
                }
                $late_days++;
                $is_late = true;
            }
        }

        $status_raw = $row['status'];
        $pillClass = 'bg-slate-100 text-slate-600 border-slate-200';
        if(stripos($status_raw, 'Time') !== false) $pillClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';
        if(stripos($status_raw, 'Late') !== false || $is_late) { 
            $pillClass = 'bg-amber-50 text-amber-600 border-amber-200'; 
            if(stripos($status_raw, 'Late') === false) $status_raw = 'Late';
        }
        if(stripos($status_raw, 'Absent') !== false) $pillClass = 'bg-rose-50 text-rose-600 border-rose-200';
        if(stripos($status_raw, 'WFH') !== false) $pillClass = 'bg-indigo-50 text-indigo-600 border-indigo-200';

        $prod_class = ($prod > 0 && $prod < 8) ? "text-rose-500 font-bold" : "text-slate-700 font-bold";

        $attendanceRecords[] = [
            "date" => $current_dt->format('d M Y'),
            "checkin" => !empty($row['punch_in']) ? date('h:i A', strtotime($row['punch_in'])) : "-",
            "checkout" => !empty($row['punch_out']) ? date('h:i A', strtotime($row['punch_out'])) : "-",
            "status" => $status_raw,
            "status_class" => $pillClass,
            "break" => ($break_min > 0) ? $break_min . " m" : "-",
            "late" => $late_msg,
            "overtime" => ($overtime > 0) ? number_format($overtime, 2) . " h" : "-",
            "production" => number_format($prod, 2) . " h",
            "prod_class" => $prod_class
        ];

    } else {
        // --- NO DB RECORD: AUTO-ABSENT LOGIC ---
        if (!$is_future && !$is_before_joining) {
            if ($day_of_week == 7) {
                // Sunday -> Weekly Off
                $attendanceRecords[] = [
                    "date" => $current_dt->format('d M Y'),
                    "checkin" => "-", "checkout" => "-",
                    "status" => "Weekly Off",
                    "status_class" => "bg-slate-100 text-slate-400 border-slate-200",
                    "break" => "-", "late" => "-", "overtime" => "-",
                    "production" => "-", "prod_class" => "text-slate-400"
                ];
            } elseif (isset($approved_leaves[$date_str])) {
                // Approved Leave
                $attendanceRecords[] = [
                    "date" => $current_dt->format('d M Y'),
                    "checkin" => "-", "checkout" => "-",
                    "status" => "On Leave",
                    "status_class" => "bg-purple-50 text-purple-600 border-purple-200",
                    "break" => "-", "late" => "-", "overtime" => "-",
                    "production" => "-", "prod_class" => "text-slate-400"
                ];
            } else {
                // Working Day, Past, Not on Leave -> Absent
                $attendanceRecords[] = [
                    "date" => $current_dt->format('d M Y'),
                    "checkin" => "-", "checkout" => "-",
                    "status" => "Absent",
                    "status_class" => "bg-rose-50 text-rose-600 border-rose-200",
                    "break" => "-", "late" => "-", "overtime" => "-",
                    "production" => "0.00 h", "prod_class" => "text-rose-500 font-bold"
                ];
            }
        }
    }
    
    $current_dt->modify('-1 day');
}

$avg_production = ($present_count > 0) ? number_format($total_production / $present_count, 1) : 0;

// =========================================================================================
// LEAVE CARRY-FORWARD LOGIC
// =========================================================================================
$base_leaves_per_month = 2;
$d1 = new DateTime($joining_date); $d1->modify('first day of this month'); 
$d2 = new DateTime('now'); $d2->modify('first day of this month');
$months_worked = ($d2 >= $d1) ? (($d1->diff($d2)->y * 12) + $d1->diff($d2)->m + 1) : 0;
$total_earned_leaves = $months_worked * $base_leaves_per_month;

$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $view_user_id);
$leave_stmt->execute();
$leaves_taken = floatval($leave_stmt->get_result()->fetch_assoc()['taken'] ?? 0);
$leave_balance = max(0, $total_earned_leaves - $leaves_taken);
$leave_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - <?php echo htmlspecialchars($employeeName); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* Modals */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        
        /* Clean Inputs */
        input[type="date"]::-webkit-calendar-picker-indicator, input[type="month"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover, input[type="month"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px; }
        }
    </style>
</head>
<body>

    <?php 
        $sidebars_path = '../sidebars.php';
        if(!file_exists($sidebars_path)) $sidebars_path = './sidebars.php';
        include($sidebars_path); 

        $header_path = '../header.php';
        if(!file_exists($header_path)) $header_path = './header.php';
        include($header_path); 
    ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">Attendance Record</h1>
                <nav class="flex text-slate-500 text-xs mt-1.5 gap-2 font-medium">
                    <a href="admin_attendance.php" class="hover:text-teal-600 transition">Attendance</a>
                    <span>/</span>
                    <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($employeeName); ?></span>
                </nav>
            </div>
            <button onclick="window.history.back()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Roster
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            
            <div class="lg:col-span-1 flex flex-col gap-6">
                <div class="card p-6 text-center flex flex-col items-center">
                    <div class="w-full flex justify-end mb-2">
                        <span class="bg-indigo-50 text-indigo-600 border border-indigo-100 text-[9px] font-black px-2 py-0.5 rounded tracking-widest uppercase">Admin View</span>
                    </div>
                    <div class="w-24 h-24 rounded-full border-4 border-slate-50 p-1 mb-4 relative shadow-sm">
                        <img src="<?php echo $profile_img; ?>" class="rounded-full w-full h-full object-cover">
                        <div class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></div>
                    </div>
                    <h2 class="text-lg font-black text-slate-800 leading-tight"><?php echo htmlspecialchars($employeeName); ?></h2>
                    <p class="text-slate-500 text-xs font-medium mt-1"><?php echo htmlspecialchars($designation); ?> • <?php echo htmlspecialchars($employeeID); ?></p>
                    
                    <div class="w-full bg-teal-50 border border-teal-100 rounded-xl p-4 mt-6 text-left relative overflow-hidden">
                        <i class="fa-solid fa-umbrella-beach text-teal-500/20 text-6xl absolute -right-2 -bottom-2"></i>
                        <p class="text-teal-700 text-[10px] font-black uppercase tracking-widest mb-1 relative z-10">Leave Balance</p>
                        <div class="flex items-baseline gap-1.5 relative z-10">
                            <span class="text-3xl font-black text-teal-800"><?php echo $leave_balance; ?></span>
                            <span class="text-xs text-teal-600 font-bold mb-1">Days Left</span>
                        </div>
                    </div>

                    <div class="w-full bg-slate-50 rounded-xl p-4 mt-4 border border-slate-100 text-left">
                        <p class="text-slate-500 text-[10px] uppercase font-black tracking-widest mb-1">Total Production</p>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-slate-800"><?php echo number_format($total_production, 1); ?></span>
                            <span class="text-xs text-slate-500 font-bold mb-0.5">Hours</span>
                        </div>
                        <p class="text-[9px] text-slate-400 font-medium mt-1">Based on selected filter period</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 flex flex-col gap-6">
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="card p-5 border-b-4 border-b-blue-500 hover:shadow-md transition">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-stopwatch text-blue-500 mr-1"></i> Avg. Daily</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo $avg_production; ?> <span class="text-sm font-bold text-slate-400">Hrs</span></h3>
                    </div>
                    <div class="card p-5 border-b-4 border-b-amber-500 hover:shadow-md transition">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-clock-rotate-left text-amber-500 mr-1"></i> Late Days</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo sprintf("%02d", $late_days); ?> <span class="text-sm font-bold text-slate-400">Days</span></h3>
                    </div>
                    <div class="card p-5 border-b-4 border-b-emerald-500 hover:shadow-md transition">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-calendar-check text-emerald-500 mr-1"></i> Present</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo sprintf("%02d", $present_count); ?> <span class="text-sm font-bold text-slate-400">Days</span></h3>
                    </div>
                    <div class="card p-5 border-b-4 border-b-purple-500 hover:shadow-md transition">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-bolt text-purple-500 mr-1"></i> Overtime</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo number_format($total_overtime, 1); ?> <span class="text-sm font-bold text-slate-400">Hrs</span></h3>
                    </div>
                </div>

                <div class="card flex flex-col flex-grow">
                    
                    <div class="p-5 border-b border-slate-100 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 bg-white rounded-t-2xl shrink-0">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-black text-slate-800">Timesheet History</h3>
                            <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-md text-[10px] font-black tracking-widest uppercase border border-slate-200"><?php echo $currentDisplay; ?></span>
                        </div>
                        
                        <form action="" method="GET" class="flex flex-col sm:flex-row items-center gap-3 w-full xl:w-auto" id="filterForm">
                            <input type="hidden" name="id" value="<?php echo $view_user_id; ?>">
                            
                            <select name="filter_type" id="filterType" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 text-slate-700 outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 w-full sm:w-auto font-semibold transition" onchange="toggleFilterInputs()">
                                <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Month Wise</option>
                                <option value="daily" <?php echo $filter_type == 'daily' ? 'selected' : ''; ?>>Specific Date</option>
                                <option value="range" <?php echo $filter_type == 'range' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>

                            <div id="inputMonthly" class="<?php echo $filter_type == 'monthly' ? 'block' : 'hidden'; ?> w-full sm:w-auto">
                                <input type="month" name="filter_month" value="<?php echo $filter_month; ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-teal-500 text-slate-700 font-semibold bg-slate-50 transition">
                            </div>

                            <div id="inputDaily" class="<?php echo $filter_type == 'daily' ? 'block' : 'hidden'; ?> w-full sm:w-auto">
                                <input type="date" name="filter_date" value="<?php echo $filter_date; ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-teal-500 text-slate-700 font-semibold bg-slate-50 transition">
                            </div>

                            <div id="inputRange" class="<?php echo $filter_type == 'range' ? 'flex' : 'hidden'; ?> items-center gap-2 w-full sm:w-auto bg-slate-50 p-1 rounded-xl border border-slate-200">
                                <input type="date" name="from_date" value="<?php echo $from_date; ?>" class="w-full bg-transparent border-none rounded-lg px-2 py-1.5 text-sm outline-none text-slate-700 font-semibold" title="From Date">
                                <span class="text-slate-400 text-[10px] font-black uppercase px-1">TO</span>
                                <input type="date" name="to_date" value="<?php echo $to_date; ?>" class="w-full bg-transparent border-none rounded-lg px-2 py-1.5 text-sm outline-none text-slate-700 font-semibold" title="To Date">
                            </div>

                            <button type="submit" class="bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-slate-900 transition-colors w-full sm:w-auto flex justify-center items-center gap-2">
                                <i class="fa-solid fa-sliders"></i> Filter
                            </button>
                        </form>
                    </div>
                    
                    <div class="overflow-x-auto custom-scroll">
                        <table class="w-full text-left whitespace-nowrap text-sm">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Punch In</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Punch Out</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Break</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Production</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Late By</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">OT</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($attendanceRecords)): ?>
                                    <?php foreach ($attendanceRecords as $row): 
                                        $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 font-bold text-slate-700"><?php echo $row['date']; ?></td>
                                        <td class="px-6 py-4 font-medium text-slate-400"><?php echo $row['checkin']; ?></td>
                                        <td class="px-6 py-4 font-medium text-slate-400"><?php echo $row['checkout']; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider rounded-md border <?php echo $row['status_class']; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-amber-600 font-bold"><?php echo $row['break']; ?></td>
                                        <td class="px-6 py-4 <?php echo $row['prod_class']; ?>"><?php echo $row['production']; ?></td>
                                        <td class="px-6 py-4 font-bold <?php echo $row['late'] != '-' ? 'text-rose-500' : 'text-slate-300'; ?>"><?php echo $row['late']; ?></td>
                                        <td class="px-6 py-4 font-bold <?php echo $row['overtime'] != '-' ? 'text-purple-500' : 'text-slate-300'; ?>"><?php echo $row['overtime']; ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <button onclick="openReportModal(<?php echo $json_data; ?>)" class="text-slate-400 hover:text-teal-600 bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-200 p-2 rounded-lg transition-all shadow-sm">
                                                <i class="fa-solid fa-expand"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-16">
                                            <div class="flex flex-col items-center justify-center text-slate-400">
                                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3 border border-slate-100">
                                                    <i class="fa-regular fa-calendar-xmark text-2xl text-slate-300"></i>
                                                </div>
                                                <p class="font-bold text-slate-500">No Records Found</p>
                                                <p class="text-xs mt-1">There is no attendance data for the selected period.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <div id="reportDetailModal" class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300" id="modalBox">
            
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-800 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-teal-400">
                        <i class="fa-solid fa-calendar-day text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black tracking-tight" id="detDate">Date</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Daily Breakdown Summary</p>
                    </div>
                </div>
                <button onclick="closeReportModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-rose-500 flex items-center justify-center text-white transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="p-6 bg-slate-50/50">
                
                <div class="flex justify-between items-center mb-6">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Final Status</span>
                    <span id="detStatus" class="px-3 py-1 rounded-md text-[11px] font-black uppercase tracking-wider border"></span>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                            <i class="fa-solid fa-right-to-bracket"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-0.5">Punch In</p>
                            <p class="font-bold text-slate-800 text-base" id="detIn">--:--</p>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 shrink-0">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-0.5">Punch Out</p>
                            <p class="font-bold text-slate-800 text-base" id="detOut">--:--</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                        <p class="text-[9px] text-emerald-600 font-black uppercase tracking-widest mb-1">Production</p>
                        <p class="font-black text-slate-800 text-lg" id="detProd">-</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                        <p class="text-[9px] text-amber-600 font-black uppercase tracking-widest mb-1">Break Time</p>
                        <p class="font-black text-slate-800 text-lg" id="detBreak">-</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                        <p class="text-[9px] text-purple-600 font-black uppercase tracking-widest mb-1">Overtime</p>
                        <p class="font-black text-slate-800 text-lg" id="detOT">-</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold py-2.5 px-6 rounded-xl transition shadow-md" onclick="closeReportModal()">Close Window</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Dynamic Filter Form Logic
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            
            document.getElementById('inputDaily').className = (type === 'daily') ? 'block w-full sm:w-auto' : 'hidden';
            document.getElementById('inputMonthly').className = (type === 'monthly') ? 'block w-full sm:w-auto' : 'hidden';
            document.getElementById('inputRange').className = (type === 'range') ? 'flex items-center gap-2 w-full sm:w-auto bg-slate-50 p-1 rounded-xl border border-slate-200' : 'hidden';
        }
        
        document.addEventListener('DOMContentLoaded', toggleFilterInputs);

        // Advanced Modal Logic
        const reportModal = document.getElementById('reportDetailModal');
        const modalBox = document.getElementById('modalBox');

        function openReportModal(data) {
            document.getElementById('detDate').innerText = data.date;
            document.getElementById('detIn').innerText = data.checkin;
            document.getElementById('detOut').innerText = data.checkout;
            document.getElementById('detProd').innerText = data.production;
            document.getElementById('detBreak').innerText = data.break;
            document.getElementById('detOT').innerText = data.overtime;
            
            const statusEl = document.getElementById('detStatus');
            statusEl.innerText = data.status;
            statusEl.className = `px-3 py-1 rounded-md text-[11px] font-black uppercase tracking-wider border ${data.status_class}`;

            reportModal.classList.remove('hidden');
            reportModal.classList.add('flex');
            setTimeout(() => { modalBox.classList.remove('scale-95'); modalBox.classList.add('scale-100'); }, 10);
        }

        function closeReportModal() {
            modalBox.classList.remove('scale-100');
            modalBox.classList.add('scale-95');
            setTimeout(() => { 
                reportModal.classList.add('hidden');
                reportModal.classList.remove('flex');
            }, 200);
        }

        window.onclick = function(event) {
            if (event.target == reportModal) closeReportModal();
        }
    </script>
</body>
</html>