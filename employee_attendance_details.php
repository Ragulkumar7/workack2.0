<?php
// employee_attendance_details.php - Enterprise Management View

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Kolkata'); 

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

$logged_in_user = $_SESSION['user_id'] ?? $_SESSION['id'];
$user_role = $_SESSION['role'] ?? 'Employee';
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : $logged_in_user;

// =========================================================================================
// SECURITY FIX: STRICT AUTHORIZATION GUARD
// =========================================================================================
if ($user_role === 'Employee' && $logged_in_user != $view_user_id) {
    die("<div style='font-family:sans-serif; padding:50px; text-align:center;'>
            <h2 style='color:#ef4444;'>403 Unauthorized</h2>
            <p>You do not have permission to view another employee's attendance records.</p>
         </div>");
}

// 2. FETCH EMPLOYEE PROFILE DATA (Safely removed ep.email to prevent SQL crash)
$sql_profile = "SELECT ep.full_name, u.email as u_email, ep.emp_id_code, ep.designation, ep.joining_date, ep.shift_timings, ep.profile_img 
                FROM employee_profiles ep 
                JOIN users u ON ep.user_id = u.id 
                WHERE ep.user_id = ?";
$stmt = $conn->prepare($sql_profile);
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();
$stmt->close();

$employeeName = $profile_data['full_name'] ?? "Unknown Employee";
$employeeEmail = trim($profile_data['u_email'] ?? "");
$employeeID   = trim($profile_data['emp_id_code'] ?? "EMP-0000");
$designation  = $profile_data['designation'] ?? "Staff";
$joining_date = !empty($profile_data['joining_date']) ? $profile_data['joining_date'] : ''; 
$shift_timings = $profile_data['shift_timings'] ?? '09:00 AM - 06:00 PM';

$profile_img = $profile_data['profile_img'] ?? '';
if(empty($profile_img) || $profile_img === 'default_user.png') {
    $profile_img = "https://ui-avatars.com/api/?name=".urlencode($employeeName)."&background=0d9488&color=fff&bold=true";
} elseif (strpos($profile_img, 'http') !== 0 && strpos($profile_img, 'assets/profiles/') === false) {
    $profile_img = '../assets/profiles/' . $profile_img;
}

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$today = date('Y-m-d');
$required_production_hours = 8; 

// =========================================================================================
// FETCH EXACT LEAVE QUOTA (WITH AUTO-COLUMN DETECTOR)
// =========================================================================================
$allocated_leaves = 12; // Base fallback

$u_em = $conn->real_escape_string(strtolower($employeeEmail));
$e_id = $conn->real_escape_string($employeeID);
$e_id_clean = $conn->real_escape_string(str_replace('-', '', $e_id));

$where_clauses = [];
if($u_em !== '') $where_clauses[] = "LOWER(email) = '$u_em'";
if($e_id !== '' && $e_id !== 'EMP-0000') $where_clauses[] = "emp_id_code = '$e_id'";
if($e_id_clean !== '' && $e_id_clean !== 'EMP0000') $where_clauses[] = "REPLACE(emp_id_code, '-', '') = '$e_id_clean'";

if(count($where_clauses) > 0) {
    $where_sql = implode(' OR ', $where_clauses);
    // Use SELECT * to grab the row regardless of what the column is named
    $q_onb = "SELECT * FROM employee_onboarding WHERE ($where_sql) ORDER BY id DESC LIMIT 1";
    $res_onb = $conn->query($q_onb);
    
    if($res_onb && $res_onb->num_rows > 0) {
        $row_onb = $res_onb->fetch_assoc();
        
        // Auto-Detect the correct column name from the database schema
        if (array_key_exists('allocated_leaves', $row_onb) && trim($row_onb['allocated_leaves']) !== '') {
            $allocated_leaves = floatval($row_onb['allocated_leaves']);
        } elseif (array_key_exists('total_leaves', $row_onb) && trim($row_onb['total_leaves']) !== '') {
            $allocated_leaves = floatval($row_onb['total_leaves']);
        } elseif (array_key_exists('leave_quota', $row_onb) && trim($row_onb['leave_quota']) !== '') {
            $allocated_leaves = floatval($row_onb['leave_quota']);
        }
    }
}

$total_earned_leaves = $allocated_leaves;

$leave_stmt = $conn->prepare("SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'");
$leave_stmt->bind_param("i", $view_user_id);
$leave_stmt->execute();
$leaves_taken_approved = floatval($leave_stmt->get_result()->fetch_assoc()['taken'] ?? 0);
$leave_stmt->close();

// =========================================================================================
// SAFE SQL FETCH FOR ATTENDANCE
// =========================================================================================
$join_str = $joining_date;
if (empty($join_str) || $join_str < '2020-01-01') {
    $stmt_min = $conn->prepare("SELECT MIN(date) as min_date FROM attendance WHERE user_id = ?");
    $stmt_min->bind_param("i", $view_user_id);
    $stmt_min->execute();
    $min_att = $stmt_min->get_result()->fetch_assoc();
    $join_str = !empty($min_att['min_date']) ? $min_att['min_date'] : $today; 
    $stmt_min->close();
}

$join_dt = new DateTime($join_str);
$today_dt = new DateTime($today);

$sql_all = "SELECT a.id, a.date, a.punch_in, a.punch_out, a.status, a.break_time, a.production_hours, 
            (SELECT SUM(TIMESTAMPDIFF(SECOND, b.break_start, b.break_end)) FROM attendance_breaks b WHERE b.attendance_id = a.id) as t_break,
            (SELECT COUNT(*) FROM attendance_breaks b WHERE b.attendance_id = a.id) as break_count
            FROM attendance a 
            WHERE a.user_id = ? AND a.date >= ? AND a.date <= ?";

$stmt_all = $conn->prepare($sql_all);
$stmt_all->bind_param("iss", $view_user_id, $join_str, $today);
$stmt_all->execute();
$res_all = $stmt_all->get_result();
$all_att_db = [];
if ($res_all) {
    while($r = $res_all->fetch_assoc()) { $all_att_db[$r['date']] = $r; }
}
$stmt_all->close();

// Fetch Approved Leaves safely
$stmt_all_leaves = $conn->prepare("SELECT start_date, end_date FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND start_date <= ?");
$stmt_all_leaves->bind_param("is", $view_user_id, $today);
$stmt_all_leaves->execute();
$res_all_leaves = $stmt_all_leaves->get_result();
$all_app_leaves = [];
if ($res_all_leaves) {
    while ($l_row = $res_all_leaves->fetch_assoc()) {
        $curr_l = new DateTime($l_row['start_date']);
        $end_l = new DateTime($l_row['end_date']);
        while ($curr_l <= $end_l) {
            $all_app_leaves[$curr_l->format('Y-m-d')] = true;
            $curr_l->modify('+1 day');
        }
    }
}
$stmt_all_leaves->close();

// =========================================================================================
// PROGRESSIVE AUTO-DEDUCTION ENGINE
// =========================================================================================
$auto_deducted_leaves = 0;
$monthly_lates = [];
$daily_penalties = [];

if ($join_dt <= $today_dt) {
    $curr_check_dt = clone $join_dt;
    
    while($curr_check_dt <= $today_dt) {
        $d_str = $curr_check_dt->format('Y-m-d');
        $m_str = $curr_check_dt->format('Y-m'); 
        $dow = $curr_check_dt->format('N');
        $is_today = ($d_str === $today);
        
        if(!isset($monthly_lates[$m_str])) { $monthly_lates[$m_str] = 0; }

        if ($dow == 7 || isset($all_app_leaves[$d_str])) {
            $curr_check_dt->modify('+1 day');
            continue;
        }

        if (isset($all_att_db[$d_str])) {
            $r = $all_att_db[$d_str];
            
            if (!($is_today && empty($r['punch_out']))) {
                
                $is_absent_db = (stripos($r['status'], 'Absent') !== false && empty($r['punch_in']));

                if ($is_absent_db) {
                    $auto_deducted_leaves += 1.0;
                    $daily_penalties[$d_str] = "<span class='block text-[9px] text-rose-500 font-black mt-1 tracking-wider'>-1.0 LEAVE (Absent)</span>";
                } else {
                    $prod = floatval($r['production_hours']);
                    $b_sec = isset($r['t_break']) ? intval($r['t_break']) : 0;
                    if ($b_sec == 0 && !empty($r['break_time'])) { $b_sec = intval($r['break_time']) * 60; }
                    
                    if ($prod == 0 && !empty($r['punch_in']) && !empty($r['punch_out'])) {
                        $in = strtotime($r['punch_in']); $out = strtotime($r['punch_out']);
                        $prod = max(0, (($out - $in) - $b_sec) / 3600);
                    }
                    
                    $is_late = false;
                    if (!empty($r['punch_in'])) {
                        $shift_s = strtotime($r['date'] . ' ' . $shift_start_str);
                        $p_in = strtotime($r['punch_in']);
                        if ($p_in > ($shift_s + 60)) { $is_late = true; } 
                    }

                    if ($is_late) { $monthly_lates[$m_str]++; }
                    $late_count = $monthly_lates[$m_str];

                    if ($prod > 0 && $prod < $required_production_hours) {
                        $auto_deducted_leaves += 0.5;
                        $pen_text = "-0.5 LEAVE (Low Prod)";
                        if($is_late) $pen_text .= " + Late #$late_count";
                        $daily_penalties[$d_str] = "<span class='block text-[9px] text-amber-500 font-black mt-1 tracking-wider'>$pen_text</span>";
                    } elseif ($is_late) {
                        if ($late_count % 2 == 0) {
                            $auto_deducted_leaves += 0.5;
                            $daily_penalties[$d_str] = "<span class='block text-[9px] text-amber-500 font-black mt-1 tracking-wider'>-0.5 LEAVE (Late #$late_count)</span>";
                        } else {
                            $daily_penalties[$d_str] = "<span class='block text-[9px] text-blue-500 font-black mt-1 tracking-wider'>PERMISSION (Late #$late_count)</span>";
                        }
                    } elseif ($prod == 0 && empty($r['punch_in'])) {
                        $auto_deducted_leaves += 1.0;
                        $daily_penalties[$d_str] = "<span class='block text-[9px] text-rose-500 font-black mt-1 tracking-wider'>-1.0 LEAVE (No Punch)</span>";
                    }
                }
            }
        } else {
            if (!$is_today) {
                $auto_deducted_leaves += 1.0;
                $daily_penalties[$d_str] = "<span class='block text-[9px] text-rose-500 font-black mt-1 tracking-wider'>-1.0 LEAVE (Absent)</span>";
            }
        }
        $curr_check_dt->modify('+1 day');
    }
}

$leave_balance = $total_earned_leaves - $leaves_taken_approved - $auto_deducted_leaves;

// =========================================================================================
// FILTER LOGIC
// =========================================================================================
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'monthly';
$filter_date  = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : $today; 
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');
$from_date    = isset($_GET['from_date']) && !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date      = isset($_GET['to_date']) && !empty($_GET['to_date']) ? $_GET['to_date'] : $today;

if ($filter_type === 'daily') {
    $start_date = $filter_date; $end_date = $filter_date;
    $currentDisplay = date('d M Y', strtotime($filter_date));
} elseif ($filter_type === 'monthly') {
    $start_date = $filter_month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    if($end_date > $today) { $end_date = $today; } 
    $currentDisplay = date('F Y', strtotime($start_date));
} elseif ($filter_type === 'range') {
    if (strtotime($to_date) < strtotime($from_date)) { $temp = $from_date; $from_date = $to_date; $to_date = $temp; }
    $start_date = $from_date; $end_date = $to_date;
    $currentDisplay = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
}

// =========================================================================================
// CALENDAR MAPPING & NEW ANOMALY DETECTOR ENGINE
// =========================================================================================
$attendanceRecords = [];
$total_production = 0; $late_days = 0; $total_overtime = 0; $present_count = 0; $absent_count = 0; // ADDED ABSENT COUNT
$anomalies_detected = []; 

$sql_table = "SELECT a.id, a.date, a.punch_in, a.punch_out, a.status, a.break_time, a.production_hours, 
              (SELECT SUM(TIMESTAMPDIFF(SECOND, b.break_start, b.break_end)) FROM attendance_breaks b WHERE b.attendance_id = a.id) as t_break,
              (SELECT COUNT(*) FROM attendance_breaks b WHERE b.attendance_id = a.id) as break_count
              FROM attendance a 
              WHERE a.user_id = ? AND a.date >= ? AND a.date <= ? 
              ORDER BY a.date DESC";
$stmt_table = $conn->prepare($sql_table);
$stmt_table->bind_param("iss", $view_user_id, $start_date, $end_date);
$stmt_table->execute();
$res_table = $stmt_table->get_result();
$table_db_records = [];
if ($res_table) {
    while($r = $res_table->fetch_assoc()) { 
        $table_db_records[$r['date']] = $r; 
    }
}
$stmt_table->close();

$current_dt = new DateTime($end_date);
$start_dt = new DateTime($start_date);

while ($current_dt >= $start_dt) {
    $date_str = $current_dt->format('Y-m-d');
    $is_future = ($date_str > $today);
    $day_of_week = $current_dt->format('N'); 
    
    $penalty_html = $daily_penalties[$date_str] ?? "";
    $anomaly_html = "";

    if (isset($table_db_records[$date_str])) {
        $row = $table_db_records[$date_str];
        
        $b_sec = isset($row['t_break']) ? intval($row['t_break']) : 0;
        if ($b_sec == 0 && !empty($row['break_time'])) { $b_sec = intval($row['break_time']) * 60; }
        $break_min = floor($b_sec / 60);
        $break_count = isset($row['break_count']) ? $row['break_count'] : 0;

        $prod = floatval($row['production_hours']);
        
        // Active Shift Live Calculation
        if ($prod == 0 && !empty($row['punch_in'])) {
            $in = strtotime($row['punch_in']); 
            $out = !empty($row['punch_out']) ? strtotime($row['punch_out']) : time();
            $prod = max(0, (($out - $in) - $b_sec) / 3600);
        }
        
        $total_production += $prod;
        $overtime = ($prod > $required_production_hours) ? ($prod - $required_production_hours) : 0;
        $total_overtime += $overtime;

        $late_msg = "-";
        $is_late = false;
        
        $is_absent_db = (stripos($row['status'], 'Absent') !== false && empty($row['punch_in']));

        // ADDED LOGIC FOR ABSENT CALCULATION
        if (!$is_absent_db) { 
            $present_count++; 
        } else { 
            $absent_count++; 
        }

        if (!empty($row['punch_in']) && !$is_absent_db) {
            $shift_start_ts = strtotime($row['date'] . ' ' . $shift_start_str);
            $punch_in_ts = strtotime($row['punch_in']);

            if ($punch_in_ts > ($shift_start_ts + 60)) { 
                $delay_mins = round(($punch_in_ts - $shift_start_ts) / 60);
                $late_msg = ($delay_mins >= 60) ? floor($delay_mins / 60) . "h " . ($delay_mins % 60) . "m" : $delay_mins . " mins";
                $late_days++;
                $is_late = true;
                
                // Anomaly: Severe Late
                if ($delay_mins > 180) {
                    $anomaly_html .= "<span class='block text-[9px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded mt-1 font-bold w-fit'><i class='fa-solid fa-triangle-exclamation'></i> Severe Late (>3Hrs)</span>";
                    $anomalies_detected[] = "Severe Late on $date_str";
                }
            }
        }

        // --- ENTERPRISE ANOMALY DETECTION ENGINE ---
        if (!empty($row['punch_out']) && $prod < 0.5 && $prod > 0 && !$is_absent_db) {
            // Fake / Ghost Punch (In and out within 30 mins)
            $anomaly_html .= "<span class='block text-[9px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded mt-1 font-bold w-fit'><i class='fa-solid fa-ghost'></i> Ghost Punch Suspected</span>";
            $anomalies_detected[] = "Ghost Punch on $date_str";
        }
        if ($break_count >= 4 || $break_min > 90) {
            // Suspicious Break Patterns
            $anomaly_html .= "<span class='block text-[9px] bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded mt-1 font-bold w-fit'><i class='fa-solid fa-mug-hot'></i> Excessive Breaks</span>";
            $anomalies_detected[] = "Excessive Breaks on $date_str";
        }
        if ($overtime >= 3) {
            // High Overtime / Burnout
            $anomaly_html .= "<span class='block text-[9px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded mt-1 font-bold w-fit'><i class='fa-solid fa-fire'></i> Unusual Overtime Alert</span>";
            $anomalies_detected[] = "High Overtime on $date_str";
        }

        // Status pill logic
        $status_raw = $row['status'];
        if (!empty($row['punch_in']) && stripos($status_raw, 'Absent') !== false) {
            $status_raw = 'Present'; // Correct DB mistake
        }

        $pillClass = 'bg-slate-100 text-slate-600 border-slate-200';
        if(stripos($status_raw, 'Time') !== false || stripos($status_raw, 'Present') !== false) $pillClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';
        if(stripos($status_raw, 'Late') !== false || $is_late) { 
            $pillClass = 'bg-amber-50 text-amber-600 border-amber-200'; 
            if(stripos($status_raw, 'Late') === false) $status_raw = 'Late';
        }
        if(stripos($status_raw, 'Absent') !== false) $pillClass = 'bg-rose-50 text-rose-600 border-rose-200';
        if(stripos($status_raw, 'WFH') !== false) $pillClass = 'bg-indigo-50 text-indigo-600 border-indigo-200';

        $attendanceRecords[] = [
            "date" => $current_dt->format('d M Y'),
            "checkin" => !empty($row['punch_in']) ? date('h:i A', strtotime($row['punch_in'])) : "-",
            "checkout" => !empty($row['punch_out']) ? date('h:i A', strtotime($row['punch_out'])) : "-",
            "status" => $status_raw,
            "penalty_html" => $penalty_html,
            "anomaly_html" => $anomaly_html,
            "status_class" => $pillClass,
            "break" => ($break_min > 0) ? $break_min . " m" : "-",
            "late" => $late_msg,
            "overtime" => ($overtime > 0) ? number_format($overtime, 2) . " h" : "-",
            "production" => number_format($prod, 2) . " h",
            "prod_class" => ($prod > 0 && $prod < $required_production_hours) ? "text-rose-500 font-bold" : "text-slate-700 font-bold"
        ];

    } else {
        if (!$is_future) {
            if ($day_of_week == 7) {
                $attendanceRecords[] = [
                    "date" => $current_dt->format('d M Y'), "checkin" => "-", "checkout" => "-",
                    "status" => "Weekly Off", "penalty_html" => "", "anomaly_html" => "", "status_class" => "bg-slate-100 text-slate-400 border-slate-200",
                    "break" => "-", "late" => "-", "overtime" => "-", "production" => "-", "prod_class" => "text-slate-400"
                ];
            } elseif (isset($all_app_leaves[$date_str])) {
                $attendanceRecords[] = [
                    "date" => $current_dt->format('d M Y'), "checkin" => "-", "checkout" => "-",
                    "status" => "On Leave", "penalty_html" => "", "anomaly_html" => "", "status_class" => "bg-purple-50 text-purple-600 border-purple-200",
                    "break" => "-", "late" => "-", "overtime" => "-", "production" => "-", "prod_class" => "text-slate-400"
                ];
            } else {
                // ADDED LOGIC FOR ABSENT CALCULATION
                $absent_count++; 
                $attendanceRecords[] = [
                    "date" => $current_dt->format('d M Y'), "checkin" => "-", "checkout" => "-",
                    "status" => "Absent", "penalty_html" => $penalty_html, "anomaly_html" => "", "status_class" => "bg-rose-50 text-rose-600 border-rose-200",
                    "break" => "-", "late" => "-", "overtime" => "-", "production" => "0.00 h", "prod_class" => "text-rose-500 font-bold"
                ];
            }
        }
    }
    
    $current_dt->modify('-1 day');
}

$avg_production = ($present_count > 0) ? number_format($total_production / $present_count, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Audit - <?php echo htmlspecialchars($employeeName); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        
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
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 mt-2">
            <div>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-slate-800 tracking-tight">Attendance Audit</h1>
                <nav class="flex text-slate-500 text-xs mt-1.5 gap-2 font-medium">
                    <a href="#" class="hover:text-teal-600 transition">Team Management</a>
                    <span>/</span>
                    <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($employeeName); ?></span>
                </nav>
            </div>
            <button onclick="window.history.back()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Roster
            </button>
        </div>

        <?php if(count($anomalies_detected) > 0): ?>
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 mb-6 flex items-start gap-4 shadow-sm">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-robot text-xl"></i>
            </div>
            <div>
                <h3 class="text-rose-800 font-black text-lg tracking-tight">AI Guard Warning</h3>
                <p class="text-rose-600 text-sm font-medium mt-0.5">The system detected <span class="font-bold"><?php echo count($anomalies_detected); ?> suspicious activities</span> during this period.</p>
                <ul class="mt-2 text-xs font-bold text-rose-700 space-y-1">
                    <?php 
                        $disp_anoms = array_slice($anomalies_detected, 0, 3);
                        foreach($disp_anoms as $anom) { echo "<li>• $anom</li>"; }
                        if(count($anomalies_detected) > 3) echo "<li>• ...and " . (count($anomalies_detected)-3) . " more. Check logs below.</li>";
                    ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            
            <div class="lg:col-span-1 flex flex-col gap-6">
                <div class="card p-6 text-center flex flex-col items-center">
                    <div class="w-full flex justify-end mb-2">
                        <span class="bg-indigo-50 text-indigo-600 border border-indigo-100 text-[9px] font-black px-2 py-0.5 rounded tracking-widest uppercase">Manager View</span>
                    </div>
                    <div class="w-24 h-24 rounded-full border-4 border-slate-50 p-1 mb-4 relative shadow-sm">
                        <img src="<?php echo $profile_img; ?>" class="rounded-full w-full h-full object-cover">
                        <div class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></div>
                    </div>
                    <h2 class="text-lg font-black text-slate-800 leading-tight"><?php echo htmlspecialchars($employeeName); ?></h2>
                    <p class="text-slate-500 text-xs font-medium mt-1"><?php echo htmlspecialchars($designation); ?> • <?php echo htmlspecialchars($employeeID); ?></p>
                    
                    <div class="w-full bg-white border border-slate-200 rounded-xl p-4 mt-6 text-left shadow-sm">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">Leave Policy Status</p>
                        
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="bg-teal-50 p-2 rounded-lg text-center border border-teal-100">
                                <p class="text-[9px] text-teal-700 font-bold uppercase mb-0.5">Allocated</p>
                                <p class="text-lg font-black text-teal-800"><?php echo $allocated_leaves; ?></p>
                            </div>
                            <div class="bg-blue-50 p-2 rounded-lg text-center border border-blue-100">
                                <p class="text-[9px] text-blue-700 font-bold uppercase mb-0.5">Taken</p>
                                <p class="text-lg font-black text-blue-800"><?php echo $leaves_taken_approved; ?></p>
                            </div>
                        </div>
                        
                        <?php if($auto_deducted_leaves > 0): ?>
                        <div class="bg-orange-50 p-2 rounded-lg text-center border border-orange-100 mb-3">
                            <p class="text-[9px] text-orange-700 font-bold uppercase mb-0.5 flex justify-center items-center gap-1">
                                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Penalties
                            </p>
                            <p class="text-lg font-black text-orange-800">-<?php echo number_format($auto_deducted_leaves, 1); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="bg-slate-800 p-3 rounded-lg text-center shadow-md mt-1 relative overflow-hidden">
                            <i class="fa-solid fa-umbrella-beach text-white/5 text-4xl absolute -right-2 -bottom-2"></i>
                            <p class="text-[10px] text-slate-300 font-bold uppercase tracking-widest mb-0.5 relative z-10">Available Left</p>
                            <p class="text-2xl font-black relative z-10 <?php echo $leave_balance < 0 ? 'text-rose-400' : 'text-emerald-400'; ?>">
                                <?php echo number_format($leave_balance, 1); ?> <span class="text-xs font-bold text-slate-400">Days</span>
                            </p>
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
                
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="card p-5 border-b-4 border-b-blue-500 hover:-translate-y-1 transition-transform">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-stopwatch text-blue-500 mr-1"></i> Avg. Daily</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo $avg_production; ?> <span class="text-sm font-bold text-slate-400">Hrs</span></h3>
                    </div>
                    <div class="card p-5 border-b-4 border-b-amber-500 hover:-translate-y-1 transition-transform">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-clock-rotate-left text-amber-500 mr-1"></i> Late Days</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo sprintf("%02d", $late_days); ?> <span class="text-sm font-bold text-slate-400">Days</span></h3>
                    </div>
                    <div class="card p-5 border-b-4 border-b-emerald-500 hover:-translate-y-1 transition-transform">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-calendar-check text-emerald-500 mr-1"></i> Present</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo sprintf("%02d", $present_count); ?> <span class="text-sm font-bold text-slate-400">Days</span></h3>
                    </div>
                    
                    <div class="card p-5 border-b-4 border-b-rose-500 hover:-translate-y-1 transition-transform">
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-user-xmark text-rose-500 mr-1"></i> Absent</p>
                        <h3 class="text-2xl font-black text-slate-800"><?php echo sprintf("%02d", $absent_count); ?> <span class="text-sm font-bold text-slate-400">Days</span></h3>
                    </div>

                    <div class="card p-5 border-b-4 border-b-purple-500 hover:-translate-y-1 transition-transform">
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
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status / Guard</th>
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
                                            <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider rounded-md border inline-block <?php echo $row['status_class']; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                            <?php echo $row['penalty_html']; ?>
                                            <?php echo $row['anomaly_html']; ?>
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

        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            document.getElementById('inputDaily').className = (type === 'daily') ? 'block w-full sm:w-auto' : 'hidden';
            document.getElementById('inputMonthly').className = (type === 'monthly') ? 'block w-full sm:w-auto' : 'hidden';
            document.getElementById('inputRange').className = (type === 'range') ? 'flex items-center gap-2 w-full sm:w-auto bg-slate-50 p-1 rounded-xl border border-slate-200' : 'hidden';
        }
        
        document.addEventListener('DOMContentLoaded', toggleFilterInputs);

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
            statusEl.className = `px-3 py-1 rounded-md text-[11px] font-black uppercase tracking-wider border inline-block ${data.status_class}`;

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