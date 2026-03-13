<?php
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// FIX TIMEZONE 
date_default_timezone_set('Asia/Kolkata');

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/workack2.0/include/db_connect.php';
if (file_exists($dbPath)) { include_once($dbPath); } 
else { include_once('../include/db_connect.php'); }

if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$username = $_SESSION['username'] ?? 'User';

// -------------------------------------------------------------------------
// 2. FETCH USER PROFILE & LEAVE DATA
// -------------------------------------------------------------------------
$employee_name = "Chief Financial Officer";
$employee_role = "Chief Financial Officer";
$employee_phone = "Not Set";
$employee_email = "Not Set";
$joining_date = "Not Set";
$db_joining_date = $today; // Required for loop logic
$department = "Management";
$experience_label = "10+ Years";
$profile_img = "";
$emergency_contacts = '[]';
$shift_timings = '09:00 AM - 06:00 PM';
$leaves_total = 2; // Updated default to 2 based on monthly allocation

$sql_profile = "SELECT u.username, u.role, p.full_name, p.phone, p.joining_date, p.designation, p.email, p.profile_img, p.department, p.experience_label, p.emergency_contacts, p.shift_timings, p.casual_leaves 
                FROM users u 
                LEFT JOIN employee_profiles p ON u.id = p.user_id 
                WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
$user_res = mysqli_stmt_get_result($stmt);

$profile_img = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=0d9488&color=fff&size=128&bold=true";
if ($user_info = mysqli_fetch_assoc($user_res)) {
    $username = $user_info['full_name'] ?? $user_info['username'] ?? $username;
    $employee_name = $user_info['full_name'] ?? $username;
    $employee_role = $user_info['designation'] ?? $user_info['role'] ?? 'CFO';
    $employee_phone = $user_info['phone'] ?? 'Not Set';
    $employee_email = $user_info['email'] ?? 'Not Set';
    $department = $user_info['department'] ?? 'Management';
    $experience_label = $user_info['experience_label'] ?? 'Executive';
    
    // Fetching the casual leaves directly from database
    $leaves_total = $user_info['casual_leaves'] ?? 2;
    
    $db_joining_date = $user_info['joining_date'] ?? $today;
    $joining_date = $user_info['joining_date'] ? date("d M Y", strtotime($user_info['joining_date'])) : "Not Set";
    
    $emergency_contacts = $user_info['emergency_contacts'] ?? '[]';
    $shift_timings = $user_info['shift_timings'] ?? $shift_timings;
    
    if (!empty($user_info['profile_img']) && $user_info['profile_img'] !== 'default_user.png') {
        if (str_starts_with($user_info['profile_img'], 'http')) {
            $profile_img = $user_info['profile_img'];
        } else {
            $profile_img = '../assets/profiles/' . $user_info['profile_img'];
        }
    }
}

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';

// --- LEAVE LOGIC ---
$leaves_taken = 0;
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = mysqli_prepare($conn, $leave_sql);
if ($leave_stmt) {
    mysqli_stmt_bind_param($leave_stmt, "i", $current_user_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_res = mysqli_stmt_get_result($leave_stmt);
    if($leave_data = mysqli_fetch_assoc($leave_res)) {
        $leaves_taken = $leave_data['taken'] ?? 0;
    }
}
$leaves_remaining = max(0, $leaves_total - $leaves_taken);

// -------------------------------------------------------------------------
// 3. ATTENDANCE STATS (EXACT DAY-BY-DAY LOOP ENGINE)
// -------------------------------------------------------------------------
// NOTE: Active Timer & Punch Logic is securely handled by attendance_card.php
$current_month = date('m'); 
$current_year = date('Y');

$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$total_late_seconds = 0;

$start_date_stat = date('Y-m-01'); // STRICTLY 1st of the month
$end_date_stat = $today;

// 1. Fetch DB Records for the month
$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND date >= ? AND date <= ?";
$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iss", $current_user_id, $start_date_stat, $end_date_stat);
$stat_stmt->execute();
$stat_res = $stat_stmt->get_result();

$month_att_db = [];
while ($stat_row = $stat_res->fetch_assoc()) {
    $month_att_db[$stat_row['date']] = $stat_row;
}
$stat_stmt->close();

// 2. Fetch Approved Leaves safely for stats
$stmt_all_leaves_stat = $conn->prepare("SELECT start_date, end_date, leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND start_date <= ?");
$stmt_all_leaves_stat->bind_param("is", $current_user_id, $today);
$stmt_all_leaves_stat->execute();
$res_all_leaves_stat = $stmt_all_leaves_stat->get_result();
$all_app_leaves_stat = [];
if ($res_all_leaves_stat) {
    while ($l_row = $res_all_leaves_stat->fetch_assoc()) {
        $curr_l = new DateTime($l_row['start_date']);
        $end_l = new DateTime($l_row['end_date']);
        while ($curr_l <= $end_l) {
            $all_app_leaves_stat[$curr_l->format('Y-m-d')] = $l_row['leave_type'];
            $curr_l->modify('+1 day');
        }
    }
}
$stmt_all_leaves_stat->close();

// 3. Exact Date Loop Engine - NO JOIN DATE OVERRIDE (Match Audit Page exactly)
$iter_dt = new DateTime($start_date_stat);
$today_dt = new DateTime($today);

while ($iter_dt <= $today_dt) {
    $d_str = $iter_dt->format('Y-m-d');
    $dow = $iter_dt->format('N'); // 1 (Mon) to 7 (Sun)
    $is_today = ($d_str === $today);
    
    if (isset($month_att_db[$d_str])) {
        // Present in DB
        $r = $month_att_db[$d_str];
        $st = $r['status'];
        $is_absent_db = (stripos($st, 'Absent') !== false && empty($r['punch_in']));

        if ($is_absent_db) {
            $stats_absent++;
        } else {
            if (stripos($st, 'WFH') !== false) { 
                $stats_wfh++; 
            } elseif (stripos($st, 'Sick') !== false && !isset($all_app_leaves_stat[$d_str])) { 
                $stats_sick++; 
            }

            if (!empty($r['punch_in'])) {
                $expected_start_ts = strtotime($r['date'] . ' ' . $shift_start_str);
                $actual_start_ts = strtotime($r['punch_in']);
                if ($actual_start_ts > ($expected_start_ts + 60)) { 
                    $stats_late++; 
                    $total_late_seconds += ($actual_start_ts - $expected_start_ts);
                } else { 
                    if (stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                        $stats_ontime++; 
                    }
                }
            } else {
                // No punch in but not marked absent in DB
                if (!$is_today && stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                    $stats_absent++;
                }
            }
        }
    } else {
        // NOT in DB - check if Sunday or Leave
        if (!$is_today) {
            if ($dow == 7) {
                // Sunday - do nothing
            } elseif (isset($all_app_leaves_stat[$d_str])) {
                // On Approved Leave
                if (stripos($all_app_leaves_stat[$d_str], 'Sick') !== false) {
                    $stats_sick++;
                }
            } else {
                // Working day, not in DB, not on leave => ABSENT
                $stats_absent++;
            }
        } else {
             // TODAY logic - if not punched in and not Sunday/Leave, it is considered absent today
             if ($dow != 7 && !isset($all_app_leaves_stat[$d_str])) {
                 $stats_absent++; 
             }
        }
    }
    $iter_dt->modify('+1 day');
}

$late_hours = floor($total_late_seconds / 3600);
$late_minutes = floor(($total_late_seconds % 3600) / 60);
$late_time_str = $late_hours . 'h ' . $late_minutes . 'm';

// Leaves Taken specifically for UI display text
$current_month_leaves = 0;
foreach ($all_app_leaves_stat as $ld => $ltype) {
    if (strpos($ld, date('Y-m-')) === 0) {
        $current_month_leaves++;
    }
}


// -------------------------------------------------------------------------
// 4. FETCH INTEGRATED SECTIONS (Notifications, Meetings) - UPDATED FOR CFO
// -------------------------------------------------------------------------
$all_notifications = [];
$all_today_meetings = [];

// Fetch standard notifications
$q_notif = "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 4";
$r_notif = @mysqli_query($conn, $q_notif);
if($r_notif) {
    while($row = mysqli_fetch_assoc($r_notif)) {
        $all_notifications[] = [
            'type' => $row['type'] ?? 'info',
            'title' => htmlspecialchars($row['title']),
            'message' => htmlspecialchars($row['message'] ?? ''),
            'time' => $row['created_at'],
            'link' => $row['link'] ?? '#'
        ];
    }
}

// Fetch Announcements Table Meetings (Scheduled by Managers/HR)
$q_ann_meets = "SELECT a.id, a.title, a.publish_date as meet_date, '' as meet_link, u.department, a.message, a.created_at, COALESCE(u.username, 'Admin') as host_name 
                FROM announcements a 
                LEFT JOIN users u ON a.created_by = u.id 
                WHERE a.category = 'Meeting' AND a.is_archived = 0 
                AND (a.target_audience = 'All' 
                     OR a.target_audience = 'All Employees' 
                     OR a.target_audience = 'Management'
                     OR a.target_audience LIKE '%" . $conn->real_escape_string($username) . "%' 
                     OR a.message LIKE '%" . $conn->real_escape_string($username) . "%'
                     OR a.message LIKE '%" . $conn->real_escape_string($employee_name) . "%'
                     OR a.target_audience = 'CFO')"; 
$r_ann_meets = mysqli_query($conn, $q_ann_meets);
if($r_ann_meets) {
    while($row = mysqli_fetch_assoc($r_ann_meets)) {
        $time = "00:00:00"; 
        if (preg_match('/Time:\s*([^\n]+)/', $row['message'], $matches)) {
            $time = trim($matches[1]);
        }
        $row['meet_time'] = $time;
        
        // Push to My Updates (Live Feed)
        $all_notifications[] = [
            'type' => 'meeting',
            'title' => 'Meeting Scheduled',
            'message' => 'By ' . htmlspecialchars($row['host_name']) . ': ' . htmlspecialchars($row['title']),
            'time' => $row['created_at'] ?? ($row['meet_date'] . ' 00:00:00'), 
            'link' => $path_to_root . 'view_announcements.php'
        ];

        // Push to array if meeting is TODAY OR IN THE FUTURE
        if ($row['meet_date'] >= $today) {
            $all_today_meetings[] = [
                'title' => $row['title'],
                'meet_date' => $row['meet_date'],
                'meet_time' => $time,
                'meet_link' => $row['meet_link'],
                'platform' => 'Online', 
                'department' => $row['department'] ?? 'Team Meeting'
            ];
        }
    }
}

// Fetch from old Calendar Meetings table if it exists
$check_meetings = $conn->query("SHOW TABLES LIKE 'calendar_meetings'");
if ($check_meetings && $check_meetings->num_rows > 0) {
    $q_today_meets = "SELECT cm.title, cm.meet_date as meeting_date, cm.meet_time as meeting_time, cm.meet_link as meeting_link 
                      FROM calendar_meetings cm 
                      JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id 
                      WHERE cmp.user_id = $current_user_id AND cm.meet_date >= CURDATE()";
    $r_today = mysqli_query($conn, $q_today_meets);
    if($r_today) {
        while($row = mysqli_fetch_assoc($r_today)) {
            $all_today_meetings[] = [
                'title' => $row['title'],
                'meet_date' => $row['meeting_date'],
                'meet_time' => $row['meeting_time'],
                'meet_link' => $row['meeting_link'],
                'platform' => 'Online',
                'department' => 'Team Meeting'
            ];
        }
    }
}

// Fetch old meetings table just in case they exist there
$q_old_meets = "SELECT * FROM meetings WHERE meeting_date >= CURDATE() ORDER BY meeting_time ASC LIMIT 4";
$r_old_meets = @mysqli_query($conn, $q_old_meets);
if($r_old_meets) {
    while($row = mysqli_fetch_assoc($r_old_meets)) {
        $all_today_meetings[] = [
            'title' => $row['title'],
            'meet_date' => $row['meeting_date'],
            'meet_time' => $row['meeting_time'],
            'meet_link' => $row['platform'] ?? 'Online',
            'platform' => $row['platform'] ?? 'Online',
            'department' => 'Team Meeting'
        ];
    }
}

// Sort all combined meetings by Date and Time
usort($all_today_meetings, function($a, $b) {
    $timeA = strtotime($a['meet_date'] . ' ' . $a['meet_time']);
    $timeB = strtotime($b['meet_date'] . ' ' . $b['meet_time']);
    return $timeA - $timeB;
});
$all_today_meetings = array_slice($all_today_meetings, 0, 5);

// Sort all notifications by time
usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 6);


// -------------------------------------------------------------------------
// 5. FINANCIAL DATA & CHART PREP (CFO Dashboard specifics)
// -------------------------------------------------------------------------
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

$kpi = ['income' => 0, 'expense' => 0, 'profit' => 0, 'ar' => 0];
$kpi['income'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(credit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = '$selected_month' AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
$kpi['expense'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = '$selected_month' AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
$kpi['profit'] = $kpi['income'] - $kpi['expense'];
$kpi['ar'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE status != 'Paid'"))['val'] ?? 0;

$recent_invoices = [];
$res_inv = @mysqli_query($conn, "SELECT i.invoice_no, c.client_name, i.invoice_date, i.grand_total, i.status FROM invoices i JOIN clients c ON i.client_id = c.id ORDER BY i.created_at DESC LIMIT 6");
if($res_inv){
    while($row = mysqli_fetch_assoc($res_inv)){
        $recent_invoices[] = ['no' => $row['invoice_no'], 'client' => $row['client_name'], 'date' => date('d-M-Y', strtotime($row['invoice_date'])), 'amount' => $row['grand_total'], 'status' => $row['status']];
    }
}

$rev_labels = []; $rev_income = []; $rev_expense = []; $rev_profit = [];
for($m=1; $m<=12; $m++) {
    $rev_labels[] = date('M', mktime(0,0,0,$m, 1));
    $inc = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(credit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = $m AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
    $exp = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = $m AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
    $rev_income[] = $inc;
    $rev_expense[] = $exp;
    $rev_profit[] = $inc - $exp;
}

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFO Dashboard - <?php echo htmlspecialchars($username); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent { margin-left: 90px; width: calc(100% - 90px); transition: all 0.3s; }
        @media (max-width: 1024px) { #mainContent { margin-left: 0; width: 100%; padding-top: 80px; } }
        
        /* Strict boundary management for perfectly aligned cards */
        .card { 
            background: white; 
            border-radius: 1rem; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); 
            transition: all 0.3s ease; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
        }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1;}
        
        /* Card body must flex-grow but have min-height: 0 so internal scrolls work */
        .card-body { padding: 1.25rem; flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;} 

        /* Custom Scrollbars */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        /* CFO specific components */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-paid { background: #dcfce7; color: #16a34a; }
        .badge-unpaid { background: #fef9c3; color: #d97706; }
        .badge-overdue { background: #fee2e2; color: #dc2626; }
        .badge-pending { background: #f1f5f9; color: #64748b; }

        /* Meetings Timeline */
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 74px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .meeting-row-wrapper { position: relative; margin-bottom: 1.2rem; }
        .meeting-dot { position: absolute; left: 70px; top: 12px; width: 10px; height: 10px; border-radius: 50%; z-index: 10; border: 2px solid white; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .meeting-flex-container { display: flex; align-items: flex-start; gap: 20px; }
        .meeting-time-label { width: 62px; text-align: right; flex-shrink: 0; font-weight: 700; font-size: 11px; color: #64748b; padding-top: 8px; }
        .meeting-content-box { background-color: #f8fafc; padding: 10px 14px; border-radius: 0.75rem; border: 1px solid #f1f5f9; flex-grow: 1; }
        
        /* Grid Layout Alignment */
        .dashboard-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; align-items: stretch; margin-bottom: 1.5rem;}
        @media (max-width: 1024px) {
            .dashboard-container { grid-template-columns: 1fr; }
            .col-span-12, .col-span-3, .col-span-4, .col-span-5, .col-span-6, .col-span-8 { grid-column: span 12 !important; }
        }
    </style>
</head>
<body class="bg-slate-50">

    <main id="mainContent" class="p-6 lg:p-8 min-h-screen">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">CFO Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Executive financial & operational overview</p>
            </div>
            <form method="GET" class="flex gap-3 bg-white p-2.5 rounded-xl border border-gray-200 shadow-sm">
                <select name="month" class="bg-transparent border-none text-sm font-bold text-slate-700 outline-none pr-2">
                    <?php foreach($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= ($selected_month == $num) ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="w-px h-5 bg-gray-200 self-center"></div>
                <select name="year" class="bg-transparent border-none text-sm font-bold text-slate-700 outline-none pr-2">
                    <option value="2026" <?= ($selected_year == '2026') ? 'selected' : '' ?>>2026</option>
                    <option value="2025" <?= ($selected_year == '2025') ? 'selected' : '' ?>>2025</option>
                </select>
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-1.5 rounded-lg transition text-xs shadow-sm">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="card border-l-4 border-l-teal-600" style="height: max-content;">
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Net Profit</p>
                            <h3 class="text-2xl font-black text-slate-800 mt-1">₹<?php echo number_format($kpi['profit']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-chart-line"></i></div>
                    </div>
                </div>
            </div>
            <div class="card" style="height: max-content;">
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">A/R (Pending)</p>
                            <h3 class="text-2xl font-black text-amber-600 mt-1">₹<?php echo number_format($kpi['ar']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    </div>
                </div>
            </div>
            <div class="card" style="height: max-content;">
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Income</p>
                            <h3 class="text-2xl font-black text-emerald-600 mt-1">₹<?php echo number_format($kpi['income']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-arrow-trend-down"></i></div>
                    </div>
                </div>
            </div>
            <div class="card" style="height: max-content;">
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Expense</p>
                            <h3 class="text-2xl font-black text-rose-600 mt-1">₹<?php echo number_format($kpi['expense']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-arrow-trend-up"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <div class="h-full">
                    <?php include '../attendance_card.php'; ?>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <div class="card" style="height: max-content;">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Status</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase">Overview</span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center gap-4 shrink-0">
                            <div class="space-y-3 w-full pr-2">
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="text-xs text-gray-600 font-semibold">On Time</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_ontime; ?></span></div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="text-xs text-gray-600 font-semibold">Late</span></div>
                                    <div class="text-right">
                                        <span class="font-bold text-slate-800 text-sm block"><?php echo $stats_late; ?></span>
                                        <span class="text-[9px] text-gray-400 block -mt-1 font-bold"><?php echo $late_time_str; ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div><span class="text-xs text-gray-600 font-semibold">WFH</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_wfh; ?></span></div>
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div><span class="text-xs text-gray-600 font-semibold">Absent</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_absent; ?></span></div>
                                
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-plane-departure text-rose-400 text-xs"></i><span class="text-xs text-slate-800 font-bold uppercase">Leaves Taken</span></div>
                                    <span class="font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded text-xs"><?php echo $current_month_leaves; ?> Days</span>
                                </div>
                            </div>
                            <div class="relative flex-shrink-0 w-24 h-24 mx-auto">
                                <div id="attendanceChart" class="w-full h-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="height: max-content;">
                    <div class="card-body flex flex-col gap-3">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Carry Forward</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-teal-50 p-3 rounded-xl text-center border border-teal-100">
                                <p class="text-[9px] text-teal-700 font-bold uppercase mb-1">Total</p>
                                <p class="text-xl font-black text-teal-800"><?php echo $leaves_total; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center border border-blue-100">
                                <p class="text-[9px] text-blue-700 font-bold uppercase mb-1">Taken</p>
                                <p class="text-xl font-black text-blue-800"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center border border-green-200 shadow-sm relative overflow-hidden">
                                <p class="text-[9px] text-green-800 font-bold uppercase relative z-10 mb-1">Left</p>
                                <p class="text-xl font-black relative z-10 text-green-800">
                                    <?php echo $leaves_remaining; ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-auto pt-2">
                            <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-lg text-center transition shadow-sm shadow-teal-200/50 text-xs">
                                <i class="fa-solid fa-plus mr-1"></i> APPLY NEW LEAVE
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <div class="card overflow-hidden shadow-sm border-slate-200" style="height: max-content;">
                    <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-6 flex items-center gap-4 relative shrink-0">
                        <div class="relative shrink-0">
                            <img src="<?php echo $profile_img; ?>" class="w-16 h-16 rounded-full border-2 border-white shadow-lg object-cover bg-white">
                            <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <div class="min-w-0 text-white">
                            <h2 class="font-black text-xl truncate tracking-tight"><?php echo htmlspecialchars($username); ?></h2>
                            <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest truncate mt-0.5 opacity-90"><?php echo htmlspecialchars($employee_role); ?></p>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-white border-b border-gray-100 shrink-0">
                         <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-phone text-teal-600 text-xs"></i>
                                </div>
                                <p class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($employee_phone); ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-envelope text-teal-600 text-xs"></i>
                                </div>
                                <p class="text-xs font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($employee_email); ?>">
                                    <?php echo htmlspecialchars($employee_email); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-slate-50 flex flex-col space-y-4">
                        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-shield text-purple-500"></i> Reporting To
                            </p>
                            <div class="flex justify-between items-center">
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-slate-800 truncate">Board of Directors</p>
                                    <p class="text-[10px] text-slate-500 font-medium mt-0.5 truncate">
                                        <i class="fa-solid fa-envelope text-[9px] mr-1"></i> management@company.com
                                    </p>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 flex-shrink-0">
                                    <i class="fa-solid fa-building text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                                <p class="text-[9px] text-gray-400 font-black uppercase tracking-tighter">Experience</p>
                                <p class="text-xs font-black text-slate-700 mt-1"><?php echo htmlspecialchars($experience_label); ?></p>
                            </div>
                            <div class="bg-white p-3 rounded-xl border border-slate-200 text-center shadow-sm">
                                <p class="text-[9px] text-gray-400 font-black uppercase tracking-tighter">Department</p>
                                <p class="text-xs font-black text-slate-700 mt-1"><?php echo htmlspecialchars($department); ?></p>
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                            <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Company Journey</p>
                            <div class="flex justify-between items-center">
                                <p class="text-xs font-black text-slate-700">Joined On</p>
                                <span class="text-[10px] font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded-lg"><?php echo $joining_date; ?></span>
                            </div>
                        </div>

                        <?php
                        $emergency = json_decode($emergency_contacts, true);
                        if (!empty($emergency)): 
                            $primary = $emergency[0]; ?>
                            <div class="p-3 bg-rose-50 rounded-xl border border-rose-100 flex items-center justify-between shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center text-rose-500 shadow-inner">
                                        <i class="fa-solid fa-heart-pulse text-xs"></i>
                                    </div>
                                    <div>
                                        <span class="text-[9px] font-black text-rose-700 uppercase block tracking-tight">Emergency</span>
                                        <p class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($primary['name']); ?></p>
                                    </div>
                                </div>
                                <p class="text-[10px] font-black text-rose-600 bg-white px-2 py-1 rounded-lg border border-rose-100"><?php echo htmlspecialchars($primary['phone']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2 shrink-0">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-amber-500 text-lg"></i>
                            <h3 class="font-bold text-slate-800 text-lg">Action Hub</h3>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 flex-1 min-h-0">
                        <a href="cfo_approvals.php" class="bg-slate-50 border border-slate-100 rounded-xl py-4 px-2 flex flex-col items-center justify-center gap-2 hover:border-teal-300 hover:bg-teal-50 transition group h-full">
                            <i class="fa-solid fa-check-double text-2xl text-slate-400 group-hover:text-teal-600 transition"></i><span class="text-[10px] font-bold uppercase tracking-wide text-slate-700 text-center">Approvals</span>
                        </a>
                        <a href="../Accounts/ledger.php" class="bg-slate-50 border border-slate-100 rounded-xl py-4 px-2 flex flex-col items-center justify-center gap-2 hover:border-teal-300 hover:bg-teal-50 transition group h-full">
                            <i class="fa-solid fa-book-open text-2xl text-slate-400 group-hover:text-teal-600 transition"></i><span class="text-[10px] font-bold uppercase tracking-wide text-slate-700 text-center">Ledger</span>
                        </a>
                        <a href="cfo_reports.php" class="bg-slate-50 border border-slate-100 rounded-xl py-4 px-2 flex flex-col items-center justify-center gap-2 hover:border-teal-300 hover:bg-teal-50 transition group h-full">
                            <i class="fa-solid fa-chart-pie text-2xl text-slate-400 group-hover:text-teal-600 transition"></i><span class="text-[10px] font-bold uppercase tracking-wide text-slate-700 text-center">Reports</span>
                        </a>
                        <a href="tax_filing.php" class="bg-slate-50 border border-slate-100 rounded-xl py-4 px-2 flex flex-col items-center justify-center gap-2 hover:border-teal-300 hover:bg-teal-50 transition group h-full">
                            <i class="fa-solid fa-building-columns text-2xl text-slate-400 group-hover:text-teal-600 transition"></i><span class="text-[10px] font-bold uppercase tracking-wide text-slate-700 text-center">Tax & Filing</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-3 border-b border-gray-100 pb-2 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">My Updates</h3>
                        <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase border border-slate-200">Live Feed</span>
                    </div>
                    <div class="space-y-4 custom-scroll overflow-y-auto pr-2" style="max-height: 300px;">
                        <?php if(!empty($all_notifications)): ?>
                            <?php foreach($all_notifications as $notif): 
                                $icon_bg = ($notif['type'] == 'file') ? 'bg-red-50 text-red-500' : ( ($notif['type'] == 'meeting' || $notif['type'] == 'meeting_chat') ? 'bg-indigo-50 text-indigo-600' : 'bg-teal-50 text-teal-600' );
                            ?>
                            <div class="flex gap-3 items-start border-b border-slate-50 pb-3 last:border-0 hover:bg-slate-50 p-2 rounded-lg transition">
                                <div class="w-8 h-8 rounded-full <?php echo $icon_bg; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                    <?php if($notif['type'] == 'meeting' || $notif['type'] == 'meeting_chat') { echo '<i class="fa-solid fa-video"></i>'; } else { echo strtoupper(substr($notif['title'], 0, 1)); } ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-[10px] text-gray-400 shrink-0 mt-0.5"><?php echo date("h:i A", strtotime($notif['time'])); ?></p>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class='text-center py-8 text-sm text-slate-400'>No new notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-3 border-b border-gray-100 pb-2 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                        <button class="text-[9px] text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold uppercase tracking-widest">Upcoming</button>
                    </div>
                    <div class="meeting-timeline flex-1 overflow-y-auto custom-scroll pr-2 mt-1 space-y-4">
                        <?php if(!empty($all_today_meetings)) { 
                            $color_palette = ['bg-teal-500', 'bg-indigo-500', 'bg-rose-500', 'bg-orange-500'];
                            $c_idx = 0;
                            foreach($all_today_meetings as $meet):
                                $is_past = (strtotime($meet['meet_time']) < time() && $meet['meet_date'] == $today) ? 'opacity-50' : '';
                                $dot_color = (strtotime($meet['meet_time']) < time() && $meet['meet_date'] == $today) ? 'bg-slate-300' : $color_palette[$c_idx % 4];
                                $c_idx++;
                        ?>
                        <div class="meeting-row-wrapper <?php echo $is_past; ?>">
                            <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                            <div class="meeting-flex-container gap-4">
                                <div class="meeting-time-label mt-1">
                                    <span class="block text-[9px] text-teal-600 mb-0.5"><?php echo ($meet['meet_date'] == $today) ? 'Today' : date("d M", strtotime($meet['meet_date'])); ?></span>
                                    <?php echo date("h:i A", strtotime($meet['meet_time'])); ?>
                                </div>
                                <div class="meeting-content-box shadow-sm py-2 px-3">
                                    <h4 class="text-[13px] font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></h4>
                                    <?php if(!empty($meet['meet_link']) && $meet['meet_link'] !== 'Online'): 
                                        $actual_link = trim($meet['meet_link']);
                                        if (strpos($actual_link, '.') !== false) {
                                            if (!preg_match("~^(?:f|ht)tps?://~i", $actual_link) && strpos($actual_link, '/') !== 0) {
                                                $actual_link = "https://" . $actual_link;
                                            }
                                        } else {
                                            $actual_link = $path_to_root . "team_chat.php?room_id=" . urlencode($actual_link);
                                        }
                                    ?>
                                        <a href="<?php echo htmlspecialchars($actual_link); ?>" <?php echo (strpos($actual_link, 'team_chat.php') === false) ? 'target="_blank"' : ''; ?> class="text-[10px] text-indigo-600 font-bold mt-1.5 inline-block hover:underline">
                                            <i class="fa-solid fa-video"></i> Join Meeting
                                        </a>
                                    <?php else: ?>
                                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5 flex items-center gap-1"><i class="fa-solid fa-link"></i> <?php echo $meet['platform'] ?? 'Online'; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; } else { ?>
                            <div class="text-center py-6 flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-regular fa-calendar-xmark text-3xl mb-2 opacity-50"></i>
                                <p class="text-xs font-medium mt-2">No meetings scheduled.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-8 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Cash Flow (Current Year)</h3>
                    </div>
                    <div class="relative h-64"><canvas id="cashFlowChart"></canvas></div>
                </div>
            </div>
            
            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Recent Transactions</h3>
                        <a href="ledger.php" class="text-xs font-bold text-teal-600 hover:underline">Ledger</a>
                    </div>
                    <div class="custom-scroll overflow-y-auto" style="max-height: 250px;">
                        <div class="space-y-4">
                            <?php if(empty($recent_transactions)): echo "<div class='text-center py-8 text-slate-400 text-sm'>No recent transactions.</div>"; endif; ?>
                            <?php foreach($recent_transactions as $txn): 
                                $icon = 'fa-file-invoice'; $bg = 'bg-blue-50 text-blue-600';
                                if(strtolower($txn['type']) == 'expense' || strtolower($txn['type']) == 'expenses') { $icon='fa-receipt'; $bg='bg-red-50 text-red-600'; }
                            ?>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl <?php echo $bg; ?> flex items-center justify-center text-lg"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                                    <div>
                                        <p class="font-bold text-sm text-slate-800 truncate w-32"><?php echo htmlspecialchars($txn['party']); ?></p>
                                        <p class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($txn['date']); ?> • <?php echo htmlspecialchars($txn['type']); ?></p>
                                    </div>
                                </div>
                                <div class="font-bold text-sm text-slate-800">₹<?php echo number_format($txn['amount']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-6 card">
                <div class="card-body">
                    <h3 class="font-bold text-slate-800 text-lg mb-4">Expense Distribution</h3>
                    <div class="relative h-56"><canvas id="expenseChart"></canvas></div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-6 card">
                <div class="card-body">
                    <h3 class="font-bold text-slate-800 text-lg mb-4">Invoice Approval Status</h3>
                    <div class="relative h-56"><canvas id="invoiceBarChart"></canvas></div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // 1. LIVE TIMER & PROGRESS RING 
        const timerElement = document.getElementById('liveTimer');
        const progressRing = document.getElementById('progressRing');
        const isRunning = timerElement ? timerElement.getAttribute('data-running') === 'true' : false;
        let totalSeconds = timerElement ? parseInt(timerElement.getAttribute('data-total')) || 0 : 0;
        const startTime = new Date().getTime(); 

        function updateTimer() {
            if (!isRunning || !timerElement) return; 
            const now = new Date().getTime();
            const diffSeconds = Math.floor((now - startTime) / 1000);
            const currentTotal = totalSeconds + diffSeconds;
            const hours = Math.floor(currentTotal / 3600);
            const minutes = Math.floor((currentTotal % 3600) / 60);
            const seconds = currentTotal % 60;
            
            timerElement.innerText = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            const progress = Math.min(currentTotal / 32400, 1);
            if(progressRing) progressRing.style.strokeDashoffset = 440 - (progress * 440);
        }
        if (isRunning) setInterval(updateTimer, 1000);

        // 2. APEXCHART FOR LEAVE DETAILS (ATTENDANCE STATS)
        const attData = [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>];
        const hasData = attData.some(val => val > 0);
        
        var options = {
            series: hasData ? attData : [1],
            labels: hasData ? ['On Time', 'Late', 'WFH', 'Absent'] : ['No Data'],
            colors: hasData ? ['#0d9488', '#22c55e', '#f97316', '#ef4444'] : ['#e2e8f0'],
            chart: { type: 'donut', height: 130 },
            plotOptions: { donut: { size: '75%' } },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { enabled: hasData }
        };
        if(document.querySelector("#attendanceChart")) {
            new ApexCharts(document.querySelector("#attendanceChart"), options).render();
        }

        // 3. FINANCIAL CHARTS
        const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" } } } } };

        if(document.getElementById('cashFlowChart')) {
            new Chart(document.getElementById('cashFlowChart'), {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        { label: 'Income', data: <?php echo json_encode($cash_flow_income); ?>, backgroundColor: '#0d9488', borderRadius: 4 },
                        { label: 'Expense', data: <?php echo json_encode($cash_flow_expense); ?>, backgroundColor: '#ef4444', borderRadius: 4 }
                    ]
                },
                options: { ...commonOptions, scales: { y: { beginAtZero: true, grid: { borderDash: [2, 2], color: '#f1f5f9' } }, x: { grid: { display: false } } } }
            });
        }

        if(document.getElementById('expenseChart')) {
            new Chart(document.getElementById('expenseChart'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($exp_labels); ?>,
                    datasets: [{ data: <?php echo json_encode($exp_data); ?>, backgroundColor: ['#e67e22', '#3b82f6', '#10b981', '#6366f1'], borderWidth: 0 }]
                },
                options: { ...commonOptions, cutout: '70%' }
            });
        }

        if(document.getElementById('invoiceBarChart')) {
            new Chart(document.getElementById('invoiceBarChart'), {
                type: 'bar', indexAxis: 'y',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{ label: 'Invoices', data: <?php echo json_encode($inv_status_data); ?>, backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderRadius: 4, barThickness: 20 }]
                },
                options: { ...commonOptions, scales: { x: { grid: { display: false } }, y: { grid: { display: false } } } }
            });
        }
    </script>
</body>
</html>