<?php
// -------------------------------------------------------------------------
// PAGE: IT Admin Dashboard (Updated Layout Alignment)
// -------------------------------------------------------------------------
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// 1. DATABASE & TIMEZONE CONFIG
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

// --- AJAX CARD RELOAD ---
if (isset($_GET['ajax_card']) && $_GET['ajax_card'] == '1') {
    include '../attendance_card.php';
    exit(); 
}

// =========================================================================
// 2. FETCH PROFILE DATA
// =========================================================================
// Updated query to fetch Reporting To manager name
$profile_query = "SELECT u.email, u.role, ep.*, m.name AS reporting_to_name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id LEFT JOIN users m ON ep.reporting_to = m.id WHERE u.id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$employee_name = $profile['full_name'] ?? 'IT Admin';
$employee_role = $profile['designation'] ?? $profile['role'] ?? 'System Administrator';
$employee_dept = $profile['department'] ?? 'IT Operations';
$employee_phone = $profile['phone'] ?? 'Not Set';
$employee_email = $profile['email'] ?? 'Not Set';
$joining_date = !empty($profile['joining_date']) ? date("d M Y", strtotime($profile['joining_date'])) : 'N/A';
$reporting_to = $profile['reporting_to_name'] ?? 'HR'; // Added reporting_to variable

$experience_label = "Fresher";
if (!empty($profile['joining_date'])) {
    $join = new DateTime($profile['joining_date']);
    $now = new DateTime();
    $diff = $now->diff($join);
    if ($diff->y > 0) { $experience_label = $diff->y . " Year" . ($diff->y > 1 ? "s" : ""); }
    elseif ($diff->m > 0) { $experience_label = $diff->m . " Month" . ($diff->m > 1 ? "s" : ""); }
}

$profile_img = "https://ui-avatars.com/api/?name=" . urlencode($employee_name) . "&background=0f766e&color=fff&size=128";
if (!empty($profile['profile_img']) && $profile['profile_img'] !== 'default_user.png') {
    $profile_img = str_starts_with($profile['profile_img'], 'http') ? $profile['profile_img'] : '../assets/profiles/' . $profile['profile_img'];
}

$shift_timings = $profile['shift_timings'] ?? '09:00 AM - 06:00 PM';
$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';

// =========================================================================
// 3. HANDLE ATTENDANCE ACTIONS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'punch_in') {
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $ins_stmt = $conn->prepare($ins_sql);
        $ins_stmt->bind_param("iss", $user_id, $now_db, $today);
        $ins_stmt->execute();
    } elseif ($_POST['action'] == 'punch_out') {
        $check_sql = "SELECT punch_in, id FROM attendance WHERE user_id = ? AND date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $user_id, $today);
        $check_stmt->execute();
        $att_rec = $check_stmt->get_result()->fetch_assoc();
        if ($att_rec && $att_rec['punch_in']) {
            $att_id = $att_rec['id'];
            $conn->query("UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = $att_id AND break_end IS NULL");
            $brk_res = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, IFNULL(break_end, '$now_db'))) as total_brk FROM attendance_breaks WHERE attendance_id = $att_id");
            $total_brk_sec = $brk_res->fetch_assoc()['total_brk'] ?? 0;
            $total_work_sec = strtotime($now_db) - strtotime($att_rec['punch_in']);
            $prod_sec = max(0, $total_work_sec - $total_brk_sec);
            $prod_hours = $prod_sec / 3600;
            $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
            $upd_stmt = $conn->prepare($upd_sql);
            $upd_stmt->bind_param("sdi", $now_db, $prod_hours, $att_id);
            $upd_stmt->execute();
        }
    } elseif ($_POST['action'] == 'take_break' || $_POST['action'] == 'break_start') {
        $b_type = $_POST['break_type'] ?? 'General';
        $att_id = $conn->query("SELECT id FROM attendance WHERE user_id = $user_id AND date = '$today'")->fetch_assoc()['id'] ?? 0;
        if ($att_id > 0) {
            $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start, break_type) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $att_id, $now_db, $b_type);
            $stmt->execute();
            $conn->query("UPDATE attendance SET break_time = '1' WHERE id = $att_id");
        }
    } elseif (in_array($_POST['action'], ['break_end', 'end_break', 'resume_work'])) {
        $att_id = $conn->query("SELECT id FROM attendance WHERE user_id = $user_id AND date = '$today'")->fetch_assoc()['id'] ?? 0;
        if ($att_id > 0) {
            $conn->query("UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = $att_id AND break_end IS NULL");
        }
    }
    if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'success']);
        exit;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// =========================================================================
// 4. MONTHLY STATS (EXACT DAY-BY-DAY LOOP ENGINE FOR ACCURATE ABSENT)
// =========================================================================
$current_month = date('m'); 
$current_year = date('Y');

$stats_ontime = 0; $stats_late = 0; $stats_wfh = 0; $stats_absent = 0; $stats_sick = 0;
$total_late_seconds = 0;

$start_date_stat = date('Y-m-01'); // STRICTLY 1st of the month
$end_date_stat = $today;

// 1. Fetch DB Records for the month
$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND date >= ? AND date <= ?";
$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iss", $user_id, $start_date_stat, $end_date_stat);
$stat_stmt->execute();
$stat_res = $stat_stmt->get_result();

$month_att_db = [];
while ($stat_row = $stat_res->fetch_assoc()) {
    $month_att_db[$stat_row['date']] = $stat_row;
}
$stat_stmt->close();

// 2. Fetch Approved Leaves safely
$stmt_all_leaves = $conn->prepare("SELECT start_date, end_date, leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND start_date <= ?");
$stmt_all_leaves->bind_param("is", $user_id, $today);
$stmt_all_leaves->execute();
$res_all_leaves = $stmt_all_leaves->get_result();
$all_app_leaves = [];
if ($res_all_leaves) {
    while ($l_row = $res_all_leaves->fetch_assoc()) {
        $curr_l = new DateTime($l_row['start_date']);
        $end_l = new DateTime($l_row['end_date']);
        while ($curr_l <= $end_l) {
            $all_app_leaves[$curr_l->format('Y-m-d')] = $l_row['leave_type'];
            $curr_l->modify('+1 day');
        }
    }
}
$stmt_all_leaves->close();

// 3. Exact Date Loop Engine - NO JOIN DATE OVERRIDE
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
            } elseif (stripos($st, 'Sick') !== false && !isset($all_app_leaves[$d_str])) { 
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
            } elseif (isset($all_app_leaves[$d_str])) {
                // On Approved Leave
                if (stripos($all_app_leaves[$d_str], 'Sick') !== false) {
                    $stats_sick++;
                }
            } else {
                // Working day, not in DB, not on leave => ABSENT
                $stats_absent++;
            }
        } else {
             // TODAY logic - if not punched in and not Sunday/Leave, it is considered absent today
             if ($dow != 7 && !isset($all_app_leaves[$d_str])) {
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
foreach ($all_app_leaves as $ld => $ltype) {
    if (strpos($ld, date('Y-m-')) === 0) {
        $current_month_leaves++;
    }
}


// Ticket Metrics
$pending_tickets = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE status NOT IN ('Resolved', 'Closed')")->fetch_assoc()['cnt'] ?? 0;
$resolved_today = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE status IN ('Resolved', 'Closed') AND DATE(updated_at) = '$today'")->fetch_assoc()['cnt'] ?? 0;
$internal_tickets = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE department NOT LIKE '%Vendor%' AND status NOT IN ('Resolved', 'Closed')")->fetch_assoc()['cnt'] ?? 0;
$external_tickets = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE department LIKE '%Vendor%' AND status NOT IN ('Resolved', 'Closed')")->fetch_assoc()['cnt'] ?? 0;

$critical_tickets = [];
$crit_q = $conn->query("SELECT t.ticket_code as id, t.subject, t.priority as status, t.created_at, COALESCE(ep.full_name, u.username) as raised_by FROM tickets t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE t.status NOT IN ('Resolved', 'Closed') ORDER BY FIELD(t.priority, 'Critical', 'Urgent', 'High', 'Medium', 'Low'), t.created_at DESC LIMIT 5");
while ($r = $crit_q->fetch_assoc()) {
    $r['time'] = 'Active'; 
    $r['initial'] = strtoupper(substr($r['raised_by'], 0, 1));
    $r['status_color'] = (in_array(strtolower($r['status']), ['critical', 'urgent'])) ? 'bg-red-100 text-red-600 border-red-200' : 'bg-blue-100 text-blue-600 border-blue-200';
    $critical_tickets[] = $r;
}

// Chart Data (Last 7 Days)
$chart_labels = []; $internal_data = []; $vendor_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    $internal_data[] = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE DATE(created_at) = '$date' AND department NOT LIKE '%Vendor%'")->fetch_assoc()['c'] ?? 0;
    $vendor_data[] = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE DATE(created_at) = '$date' AND department LIKE '%Vendor%'")->fetch_assoc()['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root { --primary-sidebar-width: 90px; --brand-color: #0d9488; }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column; overflow: hidden; transition: 0.3s; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        #mainContent { margin-left: var(--primary-sidebar-width); width: calc(100% - var(--primary-sidebar-width)); min-height: 100vh; }
        @media (max-width: 1024px) { #mainContent { margin-left: 0; width: 100%; } }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50">

    <div class="fixed inset-y-0 left-0 z-50"> <?php include '../sidebars.php'; ?> </div>

    <main id="mainContent">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200"> <?php include '../header.php'; ?> </header>

        <div class="p-6 lg:p-8 max-w-[1600px] mx-auto">
            
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">IT Admin Dashboard</h1>
                    <p class="text-slate-500 text-sm">Welcome back, <b><?php echo htmlspecialchars($employee_name); ?></b></p>
                </div>
                <div class="bg-white border px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card border-l-4 border-l-red-500 p-5 flex-row justify-between items-center">
                    <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Pending Tickets</p><h2 class="text-2xl font-black text-slate-800"><?php echo $pending_tickets; ?></h2></div>
                    <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-500"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="card border-l-4 border-l-teal-600 p-5 flex-row justify-between items-center">
                    <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Internal</p><h2 class="text-2xl font-black text-slate-800"><?php echo $internal_tickets; ?></h2></div>
                    <div class="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-teal-600"><i class="fas fa-server"></i></div>
                </div>
                <div class="card border-l-4 border-l-blue-500 p-5 flex-row justify-between items-center">
                    <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">External</p><h2 class="text-2xl font-black text-slate-800"><?php echo $external_tickets; ?></h2></div>
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500"><i class="fas fa-network-wired"></i></div>
                </div>
                <div class="card border-l-4 border-l-green-500 p-5 flex-row justify-between items-center">
                    <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Resolved Today</p><h2 class="text-2xl font-black text-slate-800"><?php echo $resolved_today; ?></h2></div>
                    <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center text-green-500"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-4">
                    <div id="attendanceCardWrapper">
                        <?php include '../attendance_card.php'; ?>
                    </div>
                </div>

                <div class="lg:col-span-4 flex flex-col gap-6">
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                            <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                        </div>
                        <div class="flex flex-col xl:flex-row items-center justify-between gap-6">
                            <div class="space-y-3.5 w-full pr-2">
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="text-xs text-gray-600 font-semibold">On Time</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_ontime; ?></span></div>
                                
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="text-xs text-gray-600 font-semibold">Late</span></div>
                                    <div class="text-right">
                                        <span class="font-bold text-slate-800 text-sm block"><?php echo $stats_late; ?></span>
                                        <span class="text-[9px] text-gray-400 block -mt-1 font-bold"><?php echo $late_time_str; ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div><span class="text-xs text-gray-600 font-semibold">WFH</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_wfh; ?></span></div>
                                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div><span class="text-xs text-gray-600 font-semibold">Absent</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_absent; ?></span></div>
                                
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-plane-departure text-rose-400 text-xs"></i><span class="text-xs text-slate-800 font-bold uppercase">Leaves Taken</span></div>
                                    <span class="font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded text-xs"><?php echo $current_month_leaves; ?> Days</span>
                                </div>
                            </div>
                            <div class="relative flex-shrink-0 w-28 h-28 mx-auto">
                                <div id="attendanceChart" class="w-full h-full"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card flex-grow">
                        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-bold text-slate-800 text-sm">Action Required</h3>
                            <a href="manage_tickets.php" class="text-[10px] font-bold text-teal-600">View All</a>
                        </div>
                        <div class="overflow-y-auto custom-scroll" style="max-height: 250px;">
                            <table class="w-full text-left text-[11px]">
                                <tbody class="divide-y">
                                    <?php foreach($critical_tickets as $ticket): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3 font-bold text-slate-700">#<?php echo $ticket['id']; ?></td>
                                        <td class="p-3">
                                            <p class="font-bold text-slate-800 truncate max-w-[150px]"><?php echo $ticket['subject']; ?></p>
                                            <p class="text-[9px] text-gray-400"><?php echo $ticket['raised_by']; ?></p>
                                        </td>
                                        <td class="p-3 text-right">
                                            <span class="px-2 py-0.5 rounded text-[8px] font-bold uppercase <?php echo $ticket['status_color']; ?>"><?php echo $ticket['status']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="card">
                        <div class="bg-teal-700 p-6 text-center text-white relative">
                            <img src="<?php echo $profile_img; ?>" class="w-20 h-20 rounded-full border-4 border-teal-500 mx-auto mb-3 shadow-lg bg-white">
                            <h2 class="font-bold text-lg"><?php echo htmlspecialchars($employee_name); ?></h2>
                            <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest"><?php echo htmlspecialchars($employee_role); ?></p>
                            <span class="inline-block bg-white/20 text-[8px] px-2 py-0.5 rounded mt-2">Verified IT Admin</span>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-xl border">
                                <div class="w-8 h-8 rounded bg-teal-50 flex items-center justify-center text-teal-600"><i class="fa-solid fa-phone text-xs"></i></div>
                                <div><p class="text-[8px] text-gray-400 font-bold">PHONE</p><p class="text-[11px] font-bold"><?php echo htmlspecialchars($employee_phone); ?></p></div>
                            </div>
                            <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-xl border">
                                <div class="w-8 h-8 rounded bg-teal-50 flex items-center justify-center text-teal-600"><i class="fa-solid fa-envelope text-xs"></i></div>
                                <div class="truncate"><p class="text-[8px] text-gray-400 font-bold">EMAIL</p><p class="text-[11px] font-bold truncate"><?php echo htmlspecialchars($employee_email); ?></p></div>
                            </div>
                            <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-xl border">
                                <div class="w-8 h-8 rounded bg-teal-50 flex items-center justify-center text-teal-600"><i class="fa-solid fa-calendar-check text-xs"></i></div>
                                <div><p class="text-[8px] text-gray-400 font-bold">JOINING DATE</p><p class="text-[11px] font-bold"><?php echo htmlspecialchars($joining_date); ?></p></div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <div class="bg-blue-50 p-2 rounded text-center border border-blue-100">
                                    <p class="text-[8px] text-blue-500 font-bold uppercase">Experience</p>
                                    <p class="text-[10px] font-bold text-blue-900"><?php echo htmlspecialchars($experience_label); ?></p>
                                </div>
                                <div class="bg-indigo-50 p-2 rounded text-center border border-indigo-100">
                                    <p class="text-[8px] text-indigo-500 font-bold uppercase">Department</p>
                                    <p class="text-[10px] font-bold text-indigo-900 truncate"><?php echo htmlspecialchars($employee_dept); ?></p>
                                </div>
                            </div>

                            <div class="bg-purple-50 p-2 rounded text-center border border-purple-100 mt-2">
                                <p class="text-[8px] text-purple-500 font-bold uppercase">Reporting To</p>
                                <p class="text-[10px] font-bold text-purple-900 truncate"><?php echo htmlspecialchars($reporting_to); ?></p>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-chart-area text-teal-600"></i> Ticket Volume Trend</h3>
                    <span class="text-[10px] bg-slate-50 px-3 py-1 rounded font-bold text-gray-500 border">LAST 7 DAYS</span>
                </div>
                <div id="volumeChart" class="h-[250px]"></div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Area Chart
            new ApexCharts(document.querySelector("#volumeChart"), {
                series: [{ name: 'Internal', data: <?php echo json_encode($internal_data); ?> }, 
                         { name: 'External', data: <?php echo json_encode($vendor_data); ?> }],
                chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'Inter' },
                colors: ['#0d9488', '#3b82f6'],
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: <?php echo json_encode($chart_labels); ?> },
                dataLabels: { enabled: false }
            }).render();

            // Attendance Donut (Dynamic with PHP variables)
            var lateTimeStr = "<?php echo $late_time_str; ?>";
            var totalData = <?php echo $stats_ontime + $stats_late + $stats_wfh + $stats_absent + $stats_sick; ?>;
            var seriesData = totalData > 0 ? [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>, <?php echo $stats_sick; ?>] : [0,0,0,0,0];

            if(document.querySelector("#attendanceChart")) {
                new ApexCharts(document.querySelector("#attendanceChart"), {
                    series: seriesData,
                    chart: { type: 'donut', sparkline: { enabled: true } },
                    labels: ['On Time', 'Late', 'WFH', 'Absent', 'Sick Leave'],
                    colors: ['#0d9488', '#10b981', '#f59e0b', '#ef4444', '#eab308'],
                    stroke: { width: 0 },
                    tooltip: { 
                        enabled: true, 
                        y: { 
                            formatter: function(val, opts) { 
                                if (opts.seriesIndex === 1) return val + " Days (" + lateTimeStr + ")";
                                return val + " Days"; 
                            } 
                        } 
                    }
                }).render();
            }
        });
    </script>
</body>
</html>