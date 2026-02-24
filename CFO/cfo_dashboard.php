<?php
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// FIX TIMEZONE 
date_default_timezone_set('Asia/Kolkata');

require_once '../include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$username = $_SESSION['username'] ?? 'User';

// -------------------------------------------------------------------------
// 2. FETCH USER PROFILE & LEAVE DATA
// -------------------------------------------------------------------------
$employee_role = "Chief Financial Officer";
$employee_phone = "Not Set";
$employee_email = "Not Set";
$joining_date = "Not Set";
$department = "Management";
$experience_label = "10+ Years";
$profile_img = "";

$sql_profile = "SELECT u.username, u.role, p.full_name, p.phone, p.joining_date, p.designation, p.email, p.profile_img, p.department, p.experience_label 
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
    $employee_role = $user_info['designation'] ?? $user_info['role'] ?? 'CFO';
    $employee_phone = $user_info['phone'] ?? 'Not Set';
    $employee_email = $user_info['email'] ?? 'Not Set';
    $department = $user_info['department'] ?? 'Management';
    $experience_label = $user_info['experience_label'] ?? 'Executive';
    $joining_date = $user_info['joining_date'] ? date("d M Y", strtotime($user_info['joining_date'])) : "Not Set";
    
    if (!empty($user_info['profile_img']) && $user_info['profile_img'] !== 'default_user.png') {
        if (str_starts_with($user_info['profile_img'], 'http')) {
            $profile_img = $user_info['profile_img'];
        } else {
            $profile_img = '../assets/profiles/' . $user_info['profile_img'];
        }
    }
}

// --- LEAVE LOGIC ---
$leaves_total = 20; // Example: Execs might have more leave
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

// --- ATTENDANCE STATS FOR DONUT CHART ---
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
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

// -------------------------------------------------------------------------
// 3. ATTENDANCE LOGIC (Punch In/Out/Break)
// -------------------------------------------------------------------------
$attendance_record = null;
$total_hours_today = "00:00:00";
$display_punch_in = "--:--";
$total_seconds_worked = 0;
$is_on_break = false; 

$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

$total_break_seconds = 0;
$break_start_ts = 0;

if ($attendance_record) {
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $bk_stmt = mysqli_prepare($conn, $bk_sql);
    mysqli_stmt_bind_param($bk_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($bk_stmt);
    if ($bk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt))) {
        $is_on_break = true;
        $break_start_ts = strtotime($bk_row['break_start']);
    }

    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NOT NULL";
    $sum_stmt = mysqli_prepare($conn, $sum_sql);
    mysqli_stmt_bind_param($sum_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($sum_stmt);
    $sum_res = mysqli_fetch_assoc(mysqli_stmt_get_result($sum_stmt));
    $total_break_seconds = $sum_res['total'] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $now_db = date('Y-m-d H:i:s');
    if ($_POST['action'] == 'punch_in' && !$attendance_record) {
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "iss", $current_user_id, $now_db, $today);
        mysqli_stmt_execute($ins_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } elseif ($_POST['action'] == 'break_start' && $attendance_record && !$is_on_break) {
        $ins_bk = "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $ins_bk);
        mysqli_stmt_bind_param($stmt, "is", $attendance_record['id'], $now_db);
        mysqli_stmt_execute($stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } elseif ($_POST['action'] == 'break_end' && $attendance_record && $is_on_break) {
        $upd_bk = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
        $stmt = mysqli_prepare($conn, $upd_bk);
        mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
        mysqli_stmt_execute($stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } elseif ($_POST['action'] == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        if ($is_on_break) {
            mysqli_query($conn, "UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = {$attendance_record['id']} AND break_end IS NULL");
            $total_break_seconds += (strtotime($now_db) - $break_start_ts);
        }
        $start_ts = strtotime($attendance_record['punch_in']);
        $end_ts = strtotime($now_db);
        $production_seconds = max(0, ($end_ts - $start_ts) - $total_break_seconds);
        $hours = $production_seconds / 3600;

        $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
        $upd_stmt = mysqli_prepare($conn, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, "sdi", $now_db, $hours, $attendance_record['id']);
        mysqli_stmt_execute($upd_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
}

if ($attendance_record) {
    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    $start_ts = strtotime($attendance_record['punch_in']);
    if ($is_on_break) { $now_ts = $break_start_ts; } 
    elseif ($attendance_record['punch_out']) { $now_ts = strtotime($attendance_record['punch_out']); } 
    else { $now_ts = time(); }
    
    $total_seconds_worked = max(0, ($now_ts - $start_ts) - $total_break_seconds);
    $hours = floor($total_seconds_worked / 3600);
    $mins = floor(($total_seconds_worked % 3600) / 60);
    $secs = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
}

// -------------------------------------------------------------------------
// 4. FETCH INTEGRATED SECTIONS (Notifications, Meetings)
// -------------------------------------------------------------------------
$notif_result = @mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 4");
$meet_result = @mysqli_query($conn, "SELECT * FROM meetings WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 4");

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

// Fetch Real KPI Data
$kpi = ['income' => 0, 'expense' => 0, 'profit' => 0, 'ar' => 0];

$kpi['income'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(credit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = '$selected_month' AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
$kpi['expense'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = '$selected_month' AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
$kpi['profit'] = $kpi['income'] - $kpi['expense'];
$kpi['ar'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE status != 'Paid'"))['val'] ?? 0; // Accounts Receivable (Unpaid Invoices)

// Recent Invoices
$recent_invoices = [];
$res_inv = @mysqli_query($conn, "SELECT i.invoice_no, c.client_name, i.invoice_date, i.grand_total, i.status FROM invoices i JOIN clients c ON i.client_id = c.id ORDER BY i.created_at DESC LIMIT 3");
if($res_inv){
    while($row = mysqli_fetch_assoc($res_inv)){
        $recent_invoices[] = ['no' => $row['invoice_no'], 'client' => $row['client_name'], 'date' => date('d-M-Y', strtotime($row['invoice_date'])), 'amount' => $row['grand_total'], 'status' => $row['status']];
    }
}

// Chart 1: CFO Revenue Trend (Real Data)
$rev_labels = []; $rev_income = []; $rev_expense = []; $rev_profit = [];
for($m=1; $m<=12; $m++) {
    $rev_labels[] = date('M', mktime(0,0,0,$m, 1));
    $inc = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(credit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = $m AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
    $exp = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = $m AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
    $rev_income[] = $inc;
    $rev_expense[] = $exp;
    $rev_profit[] = $inc - $exp;
}

// Chart 2: CFO Budget vs Actual
$budget_labels = ['Salaries', 'Rent', 'Marketing', 'Operations', 'Tech'];
$budget_actual = [];
foreach($budget_labels as $bl) {
    $val = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger WHERE remarks LIKE '%$bl%' AND MONTH(entry_date) = '$selected_month' AND YEAR(entry_date) = '$selected_year'"))['val'] ?? 0;
    $budget_actual[] = $val;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; }
        
        /* Dashboard Framework */
        .dashboard-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; align-items: stretch; margin-bottom: 1.5rem;}
        #mainContent { margin-left: 90px; width: calc(100% - 90px); transition: all 0.3s; }
        
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; overflow: hidden; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .card-body { padding: 1.5rem; flex-grow: 1; }

        .progress-ring-circle { transition: stroke-dashoffset 0.35s; transform: rotate(-90deg); transform-origin: 50% 50%; }

        /* Custom Scrollbars */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }

        /* CFO specific components */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-paid { background: #dcfce7; color: #16a34a; }
        .badge-unpaid { background: #fef9c3; color: #d97706; }
        .badge-overdue { background: #fee2e2; color: #dc2626; }
        .badge-approved { background: #dcfce7; color: #16a34a; }
        .badge-pending { background: #f1f5f9; color: #64748b; }

        /* Meetings Timeline */
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 80px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .meeting-row-wrapper { position: relative; margin-bottom: 1.5rem; }
        .meeting-dot { position: absolute; left: 76px; top: 10px; width: 10px; height: 10px; border-radius: 50%; z-index: 10; border: 2px solid white; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .meeting-flex-container { display: flex; align-items: flex-start; gap: 24px; }
        .meeting-time-label { width: 68px; text-align: right; flex-shrink: 0; font-weight: 700; font-size: 12px; color: #64748b; padding-top: 4px; }
        .meeting-content-box { background-color: #f8fafc; padding: 12px; border-radius: 0.75rem; border: 1px solid #f1f5f9; flex-grow: 1; }

        @media (max-width: 1024px) {
            .dashboard-container { grid-template-columns: 1fr; }
            #mainContent { margin-left: 0; width: 100%; }
            .col-span-12, .col-span-3, .col-span-4, .col-span-5, .col-span-6, .col-span-8, .col-span-7 { grid-column: span 12 !important; }
        }
    </style>
</head>
<body class="bg-slate-100">

    <main id="mainContent" class="p-6 lg:p-8 min-h-screen">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">CFO Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Executive financial & operational overview</p>
            </div>
            <form method="GET" class="flex gap-3 bg-white p-2 rounded-xl border border-gray-200 shadow-sm">
                <select name="month" class="bg-transparent border-none text-sm font-semibold text-slate-700 outline-none pr-4">
                    <?php foreach($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= ($selected_month == $num) ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="w-px h-6 bg-gray-200 self-center"></div>
                <select name="year" class="bg-transparent border-none text-sm font-semibold text-slate-700 outline-none pr-4">
                    <option value="2026" <?= ($selected_year == '2026') ? 'selected' : '' ?>>2026</option>
                    <option value="2025" <?= ($selected_year == '2025') ? 'selected' : '' ?>>2025</option>
                </select>
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white p-2 rounded-lg transition">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </form>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-3 card border-l-4 border-l-teal-600">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Net Profit</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">₹<?php echo number_format($kpi['profit']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl"><i class="fa-solid fa-chart-line"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">A/R (Pending)</p>
                            <h3 class="text-2xl font-bold text-amber-600 mt-1">₹<?php echo number_format($kpi['ar']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Income</p>
                            <h3 class="text-2xl font-bold text-emerald-600 mt-1">₹<?php echo number_format($kpi['income']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl"><i class="fa-solid fa-arrow-trend-down"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Expense</p>
                            <h3 class="text-2xl font-bold text-red-600 mt-1">₹<?php echo number_format($kpi['expense']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl"><i class="fa-solid fa-arrow-trend-up"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container mb-6">
            
            <div class="col-span-12 lg:col-span-4 card overflow-hidden">
                <div class="bg-[#1b5a5a] p-6 flex flex-col items-center text-center relative">
                    <div class="relative mb-3 mt-2">
                        <img src="<?php echo $profile_img; ?>" alt="Profile" class="w-20 h-20 rounded-full border-4 border-white shadow-lg object-cover">
                        <div class="absolute bottom-1 right-1 w-5 h-5 bg-green-400 border-2 border-white rounded-full"></div>
                    </div>
                    <h2 class="text-white font-bold text-lg"><?php echo htmlspecialchars($username); ?></h2>
                    <p class="text-teal-100 text-xs mb-2"><?php echo htmlspecialchars($employee_role); ?></p>
                </div>
                <div class="card-body space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700"><i class="fa-solid fa-phone text-sm"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase">Phone</p>
                            <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($employee_phone); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700"><i class="fa-solid fa-envelope text-sm"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase">Email</p>
                            <p class="text-sm font-semibold text-slate-800 truncate w-40" title="<?php echo htmlspecialchars($employee_email); ?>"><?php echo htmlspecialchars($employee_email); ?></p>
                        </div>
                    </div>
                    <hr class="border-dashed border-gray-200">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <p class="text-[9px] text-gray-400 font-bold uppercase">Experience</p>
                            <p class="text-xs font-bold text-slate-800 mt-1"><?php echo htmlspecialchars($experience_label); ?></p>
                        </div>
                        <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <p class="text-[9px] text-gray-400 font-bold uppercase">Department</p>
                            <p class="text-xs font-bold text-slate-800 mt-1"><?php echo htmlspecialchars($department); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <span class="text-xs font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded">Year 2026</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-6">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Total</p>
                                <p class="text-xl font-bold text-slate-700 mt-1"><?php echo $leaves_total; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl border border-blue-100 text-center">
                                <p class="text-[10px] text-blue-600 font-bold uppercase">Taken</p>
                                <p class="text-xl font-bold text-blue-700 mt-1"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-teal-50 p-3 rounded-xl border border-teal-100 text-center">
                                <p class="text-[10px] text-teal-600 font-bold uppercase">Left</p>
                                <p class="text-xl font-bold text-teal-700 mt-1"><?php echo $leaves_remaining; ?></p>
                            </div>
                        </div>
                    </div>
                    <a href="../employee/leave_request.php" class="block w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3.5 rounded-xl text-center transition shadow-lg shadow-teal-200/50">
                        <i class="fa-solid fa-plus mr-2"></i> APPLY NEW LEAVE
                    </a>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg">Leave Stats</h3>
                        <span class="text-xs font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded">Overview</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="font-bold text-slate-700 w-6"><?php echo $stats_ontime; ?></span><span class="text-xs text-gray-500">On Time</span></div>
                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="font-bold text-slate-700 w-6"><?php echo $stats_late; ?></span><span class="text-xs text-gray-500">Late</span></div>
                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div><span class="font-bold text-slate-700 w-6"><?php echo $stats_wfh; ?></span><span class="text-xs text-gray-500">WFH</span></div>
                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div><span class="font-bold text-slate-700 w-6"><?php echo $stats_absent; ?></span><span class="text-xs text-gray-500">Absent</span></div>
                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-yellow-500"></div><span class="font-bold text-slate-700 w-6"><?php echo $stats_sick; ?></span><span class="text-xs text-gray-500">Sick Leave</span></div>
                        </div>
                        <div class="relative"><div id="attendanceChart" class="w-28 h-28"></div></div>
                    </div>
                </div>
            </div>

        </div>

        <div class="dashboard-container">
            
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body flex flex-col items-center justify-between">
                    <div class="text-center w-full">
                        <h3 class="font-bold text-slate-800 text-base border-b border-slate-100 pb-3 mb-4">Punch Time</h3>
                    </div>
                    <div class="relative w-32 h-32 mb-4">
                        <svg class="w-full h-full transform -rotate-90">
                            <circle cx="64" cy="64" r="56" stroke="#f1f5f9" stroke-width="10" fill="transparent"></circle>
                            <?php 
                                $pct = min(1, $total_seconds_worked / 32400); 
                                $dashoffset = 352 - ($pct * 352); // r=56 circumference
                                $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                            ?>
                            <circle cx="64" cy="64" r="56" stroke="<?php echo $ringColor; ?>" stroke-width="10" fill="transparent" 
                                stroke-dasharray="352" stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset); ?>" 
                                stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-[9px] text-gray-400 font-bold uppercase"><?php echo $is_on_break ? 'BREAK' : 'HOURS'; ?></p>
                            <p class="text-lg font-bold text-slate-800" id="liveTimer" 
                               data-running="<?php echo ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>"
                               data-total="<?php echo $total_seconds_worked; ?>"><?php echo $total_hours_today; ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="w-full">
                        <?php if (!$attendance_record): ?>
                            <button type="submit" name="action" value="punch_in" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 rounded-lg shadow transition text-sm"><i class="fa-solid fa-right-to-bracket mr-1"></i> Punch In</button>
                        <?php elseif (!$attendance_record['punch_out']): ?>
                            <div class="grid grid-cols-2 gap-2 w-full">
                                <?php if ($is_on_break): ?>
                                    <button type="submit" name="action" value="break_end" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 rounded-lg shadow transition text-xs"><i class="fa-solid fa-play"></i> Resume</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="break_start" class="bg-amber-400 hover:bg-amber-500 text-white font-bold py-2.5 rounded-lg shadow transition text-xs"><i class="fa-solid fa-mug-hot"></i> Break</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="punch_out" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-lg shadow transition text-xs"><i class="fa-solid fa-right-from-bracket"></i> Out</button>
                            </div>
                        <?php else: ?>
                            <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-2.5 rounded-lg text-sm cursor-not-allowed">Completed</button>
                        <?php endif; ?>
                    </form>
                    <p class="text-[10px] text-gray-400 mt-3 font-semibold uppercase">In: <?php echo $display_punch_in; ?></p>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body">
                    <div class="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                        <i class="fa-solid fa-bolt text-teal-600 text-lg"></i>
                        <h3 class="font-bold text-slate-800 text-base">CFO Action Hub</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3 h-[calc(100%-40px)]">
                        <a href="cfo_approvals.php" class="bg-slate-50 border border-slate-100 rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:border-teal-500 hover:bg-white hover:shadow transition group">
                            <i class="fa-solid fa-check-double text-2xl text-slate-400 group-hover:text-teal-600"></i><span class="text-xs font-semibold text-slate-700 text-center">Approvals Center</span>
                        </a>
                        <a href="ledger.php" class="bg-slate-50 border border-slate-100 rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:border-teal-500 hover:bg-white hover:shadow transition group">
                            <i class="fa-solid fa-book-open text-2xl text-slate-400 group-hover:text-teal-600"></i><span class="text-xs font-semibold text-slate-700 text-center">Master Ledger</span>
                        </a>
                        <a href="cfo_reports.php" class="bg-slate-50 border border-slate-100 rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:border-teal-500 hover:bg-white hover:shadow transition group">
                            <i class="fa-solid fa-chart-pie text-2xl text-slate-400 group-hover:text-teal-600"></i><span class="text-xs font-semibold text-slate-700 text-center">Financial Reports</span>
                        </a>
                        <a href="tax_filing.php" class="bg-slate-50 border border-slate-100 rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:border-teal-500 hover:bg-white hover:shadow transition group">
                            <i class="fa-solid fa-building-columns text-2xl text-slate-400 group-hover:text-teal-600"></i><span class="text-xs font-semibold text-slate-700 text-center">Tax & Filing</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-5 card">
                <div class="card-body flex flex-col">
                    <div class="flex justify-between items-center mb-5 border-b border-slate-100 pb-3">
                        <h3 class="font-bold text-slate-800 text-base">Scheduled Meetings</h3>
                        <button class="text-teal-600 bg-teal-50 w-6 h-6 rounded flex items-center justify-center"><i class="fa-solid fa-plus text-xs"></i></button>
                    </div>
                    <div class="meeting-timeline custom-scroll overflow-y-auto pr-2" style="max-height: 190px;">
                        <?php if($meet_result && mysqli_num_rows($meet_result) > 0) { 
                            while($meet = mysqli_fetch_assoc($meet_result)):
                                $is_past = (strtotime($meet['meeting_time']) < time()) ? 'opacity-50' : '';
                                $dot_color = (strtotime($meet['meeting_time']) < time()) ? 'bg-slate-300' : 'bg-teal-500';
                        ?>
                        <div class="meeting-row-wrapper <?php echo $is_past; ?>">
                            <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                            <div class="meeting-flex-container">
                                <div class="meeting-time-label"><?php echo date("h:i A", strtotime($meet['meeting_time'])); ?></div>
                                <div class="meeting-content-box">
                                    <h4 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></h4>
                                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-1"><i class="fa-solid fa-link text-slate-400"></i> <?php echo $meet['platform'] ?? 'Online'; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; } else { ?>
                            <div class="text-center py-6 flex flex-col items-center justify-center">
                                <i class="fa-regular fa-calendar-xmark text-3xl text-slate-200 mb-2"></i>
                                <p class="text-xs text-slate-400 font-medium">No meetings scheduled for today.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-7 card">
                <div class="card-body">
                    <h3 class="font-bold text-slate-800 text-lg mb-4">Revenue Trend (<?php echo $selected_year; ?>)</h3>
                    <div class="relative h-64"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
            
            <div class="col-span-12 lg:col-span-5 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Invoices Pending Payment</h3>
                        <a href="cfo_approvals.php" class="text-xs font-bold text-teal-600 hover:underline bg-teal-50 px-2 py-1 rounded">View All</a>
                    </div>
                    <div class="custom-scroll overflow-y-auto" style="max-height: 250px; padding-right:5px;">
                        <table class="w-full text-left border-collapse">
                            <?php if(empty($recent_invoices)): ?>
                                <tr><td class="text-center py-8 text-slate-400 text-sm">No pending invoices.</td></tr>
                            <?php endif; ?>
                            <?php foreach($recent_invoices as $inv): 
                                $badge = 'badge-pending';
                                if($inv['status'] == 'Paid') $badge = 'badge-paid';
                                if($inv['status'] == 'Overdue') $badge = 'badge-overdue';
                            ?>
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-3">
                                    <p class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($inv['no']); ?></p>
                                    <p class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($inv['client']); ?> • <?php echo $inv['date']; ?></p>
                                </td>
                                <td class="py-3 text-right">
                                    <p class="font-bold text-sm text-slate-800">₹<?php echo number_format($inv['amount']); ?></p>
                                    <span class="status-badge <?php echo $badge; ?> inline-block mt-1"><?php echo htmlspecialchars($inv['status']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // 1. LIVE TIMER LOGIC 
        const timerElement = document.getElementById('liveTimer');
        const progressRing = document.getElementById('progressRing');
        const isRunning = timerElement.getAttribute('data-running') === 'true';
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
            
            timerElement.innerText = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            const progress = Math.min(currentTotal / 32400, 1);
            if(progressRing) progressRing.style.strokeDashoffset = 352 - (progress * 352);
        }
        if (isRunning) setInterval(updateTimer, 1000);

        // 2. APEXCHART FOR LEAVE DETAILS
        const attData = [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>];
        const hasData = attData.some(val => val > 0);
        
        var options = {
            series: hasData ? attData : [1],
            labels: hasData ? ['On Time', 'Late', 'WFH', 'Absent', 'Sick'] : ['No Data'],
            colors: hasData ? ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'] : ['#e2e8f0'],
            chart: { type: 'donut', height: 130 },
            plotOptions: { donut: { size: '75%' } },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { enabled: hasData }
        };
        new ApexCharts(document.querySelector("#attendanceChart"), options).render();

        // 3. CFO FINANCIAL CHART
        const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: "'Inter', sans-serif" } } } } };

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($rev_labels); ?>,
                datasets: [
                    { label: 'Income', data: <?php echo json_encode($rev_income); ?>, borderColor: '#0d9488', backgroundColor: 'rgba(13, 148, 136, 0.1)', fill: true, tension: 0.4 },
                    { label: 'Expense', data: <?php echo json_encode($rev_expense); ?>, borderColor: '#ef4444', backgroundColor: 'transparent', borderDash: [5, 5], tension: 0.4 }
                ]
            },
            options: { ...commonOptions, scales: { y: { beginAtZero: true, grid: { borderDash: [2, 2], color: '#f1f5f9' } }, x: { grid: { display: false } } } }
        });
    </script>
</body>
</html>