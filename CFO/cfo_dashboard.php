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
$employee_role = "Chief Financial Officer";
$employee_phone = "Not Set";
$employee_email = "Not Set";
$joining_date = "Not Set";
$department = "Management";
$experience_label = "10+ Years";
$profile_img = "";
$emergency_contacts = '[]';
$shift_timings = '09:00 AM - 06:00 PM';

$sql_profile = "SELECT u.username, u.role, p.full_name, p.phone, p.joining_date, p.designation, p.email, p.profile_img, p.department, p.experience_label, p.emergency_contacts, p.shift_timings 
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

// --- LEAVE LOGIC ---
$leaves_total = 20; 
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
// 3. ATTENDANCE STATS (For Donut Chart)
// -------------------------------------------------------------------------
// NOTE: Active Timer & Punch Logic is securely handled by attendance_card.php
$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$late_time_str = "0h 0m"; // Added to prevent JS error on donut chart
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
// 4. FETCH INTEGRATED SECTIONS (Notifications, Meetings)
// -------------------------------------------------------------------------
$notif_result = @mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 4");
$meet_result = @mysqli_query($conn, "SELECT * FROM meetings WHERE meeting_date = CURDATE() ORDER BY meeting_time ASC LIMIT 10");

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
            <div class="card border-l-4 border-l-teal-600">
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
            <div class="card">
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
            <div class="card">
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
            <div class="card">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="flex flex-col gap-6">
                <div class="h-full">
                    <?php include '../attendance_card.php'; ?>
                </div>
            </div>

            <div class="flex flex-col gap-6 h-full">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2 shrink-0">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Status</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase">Overview</span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center gap-4 shrink-0">
                            <div class="space-y-3 w-full pr-2">
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="text-xs text-gray-600 font-semibold">On Time</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_ontime; ?></span></div>
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="text-xs text-gray-600 font-semibold">Late</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_late; ?></span></div>
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div><span class="text-xs text-gray-600 font-semibold">WFH</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_wfh; ?></span></div>
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div><span class="text-xs text-gray-600 font-semibold">Absent</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_absent; ?></span></div>
                            </div>
                            <div class="relative flex-shrink-0 w-24 h-24 mx-auto">
                                <div id="attendanceChart" class="w-full h-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card flex-grow">
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

            <div class="flex flex-col gap-6">
                <div class="card h-full overflow-hidden shadow-sm border-slate-200">
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
                    
                    <div class="p-4 bg-slate-50 flex-grow flex flex-col justify-between space-y-4">
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
                        <a href="ledger.php" class="bg-slate-50 border border-slate-100 rounded-xl py-4 px-2 flex flex-col items-center justify-center gap-2 hover:border-teal-300 hover:bg-teal-50 transition group h-full">
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
                        <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                        <button class="text-[9px] text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold uppercase tracking-widest">Today</button>
                    </div>
                    <div class="meeting-timeline flex-1 overflow-y-auto custom-scroll pr-2 mt-1 space-y-4">
                        <?php if($meet_result && mysqli_num_rows($meet_result) > 0) { 
                            while($meet = mysqli_fetch_assoc($meet_result)):
                                $is_past = (strtotime($meet['meeting_time']) < time()) ? 'opacity-50' : '';
                                $dot_color = (strtotime($meet['meeting_time']) < time()) ? 'bg-slate-300' : 'bg-teal-500';
                        ?>
                        <div class="meeting-row-wrapper <?php echo $is_past; ?>">
                            <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                            <div class="meeting-flex-container gap-4">
                                <div class="meeting-time-label mt-1"><?php echo date("h:i A", strtotime($meet['meeting_time'])); ?></div>
                                <div class="meeting-content-box shadow-sm py-2 px-3">
                                    <h4 class="text-[13px] font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></h4>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5 flex items-center gap-1"><i class="fa-solid fa-link"></i> <?php echo $meet['platform'] ?? 'Online'; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; } else { ?>
                            <div class="text-center py-6 flex flex-col items-center justify-center h-full text-slate-400">
                                <i class="fa-regular fa-calendar-xmark text-3xl mb-2 opacity-50"></i>
                                <p class="text-xs font-medium">No meetings scheduled for today.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="card h-[380px]">
                <div class="card-body flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-3 border-b border-gray-100 pb-2 shrink-0">
                        <h3 class="font-bold text-slate-800 text-lg">Invoices Pending</h3>
                        <a href="cfo_approvals.php" class="text-[9px] font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded uppercase hover:bg-teal-100 transition">View All</a>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scroll pr-2">
                        <table class="w-full text-left border-collapse">
                            <?php if(empty($recent_invoices)): ?>
                                <tr><td class="text-center py-8 text-slate-400 text-xs">
                                    <i class="fa-solid fa-check-double text-3xl mb-2 opacity-50 block"></i>
                                    No pending invoices found.
                                </td></tr>
                            <?php endif; ?>
                            <?php foreach($recent_invoices as $inv): 
                                $badge = 'badge-pending';
                                if($inv['status'] == 'Paid') $badge = 'badge-paid';
                                if($inv['status'] == 'Overdue') $badge = 'badge-overdue';
                            ?>
                            <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 transition">
                                <td class="py-2.5 px-2">
                                    <p class="font-bold text-[13px] text-slate-800"><?php echo htmlspecialchars($inv['no']); ?></p>
                                    <p class="text-[10px] text-slate-500 font-medium mt-0.5"><?php echo htmlspecialchars($inv['client']); ?> • <?php echo $inv['date']; ?></p>
                                </td>
                                <td class="py-2.5 px-2 text-right">
                                    <p class="font-black text-sm text-slate-800">₹<?php echo number_format($inv['amount']); ?></p>
                                    <span class="status-badge <?php echo $badge; ?> inline-block mt-1"><?php echo htmlspecialchars($inv['status']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>

        <div class="card mb-10">
            <div class="card-body flex flex-col">
                <h3 class="font-bold text-slate-800 text-lg mb-4 shrink-0 border-b border-gray-100 pb-2">Revenue Trend (<?php echo $selected_year; ?>)</h3>
                <div class="relative flex-grow min-h-[300px] w-full">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

    </main>

    <script>
        // APEXCHART FOR LEAVE DETAILS
        const attData = [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>];
        const hasData = attData.some(val => val > 0);
        
        var options = {
            series: hasData ? attData : [1],
            labels: hasData ? ['On Time', 'Late', 'WFH', 'Absent', 'Sick'] : ['No Data'],
            colors: hasData ? ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'] : ['#e2e8f0'],
            chart: { type: 'donut', width: 100, height: 100, sparkline: { enabled: true } },
            plotOptions: { donut: { size: '75%' } },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { enabled: hasData }
        };
        var attendanceChartEl = document.querySelector("#attendanceChart");
        if(attendanceChartEl) {
            new ApexCharts(attendanceChartEl, options).render();
        }

        // CFO FINANCIAL CHART
        const revChartCanvas = document.getElementById('revenueChart');
        if(revChartCanvas) {
            const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" } } } } };

            new Chart(revChartCanvas, {
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
        }
    </script>
</body>
</html>