<?php
// -------------------------------------------------------------------------
// PAGE: IT Admin Dashboard (Full Professional Overview)
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

// =========================================================================
// 2. FETCH PROFILE DATA
// =========================================================================
$profile_query = "SELECT u.email, u.role, ep.* FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?";
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

// Calculate Experience dynamically
$experience_label = "Fresher";
if (!empty($profile['joining_date'])) {
    $join = new DateTime($profile['joining_date']);
    $now = new DateTime();
    $diff = $now->diff($join);
    if ($diff->y > 0) {
        $experience_label = $diff->y . " Year" . ($diff->y > 1 ? "s" : "");
    } elseif ($diff->m > 0) {
        $experience_label = $diff->m . " Month" . ($diff->m > 1 ? "s" : "");
    }
}

// Resolve Avatar
$profile_img = "https://ui-avatars.com/api/?name=" . urlencode($employee_name) . "&background=0f766e&color=fff&size=128";
if (!empty($profile['profile_img']) && $profile['profile_img'] !== 'default_user.png') {
    $profile_img = str_starts_with($profile['profile_img'], 'http') ? $profile['profile_img'] : '../assets/profiles/' . $profile['profile_img'];
}

// =========================================================================
// 3. HANDLE ATTENDANCE ACTIONS (PUNCH IN/OUT/BREAK)
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
    } elseif ($_POST['action'] == 'break_start') {
        $att_id = $conn->query("SELECT id FROM attendance WHERE user_id = $user_id AND date = '$today'")->fetch_assoc()['id'] ?? 0;
        if ($att_id > 0) {
            $conn->query("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES ($att_id, '$now_db')");
        }
    } elseif ($_POST['action'] == 'break_end') {
        $att_id = $conn->query("SELECT id FROM attendance WHERE user_id = $user_id AND date = '$today'")->fetch_assoc()['id'] ?? 0;
        if ($att_id > 0) {
            $conn->query("UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = $att_id AND break_end IS NULL");
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// =========================================================================
// 4. FETCH ATTENDANCE DATA FOR UI
// =========================================================================
$att_query = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$stmt = $conn->prepare($att_query);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$attendance_record = $stmt->get_result()->fetch_assoc();

$att_status = "Not Punched In";
$display_punch_in = "--:--";
$is_punched_in = false;
$is_punched_out = false;
$is_on_break = false;
$total_seconds_worked = 0;
$total_hours_today = "00:00:00"; 

if ($attendance_record) {
    $att_id = $attendance_record['id'];
    $display_punch_in = date("h:i A", strtotime($attendance_record['punch_in']));
    $is_punched_in = true;
    
    // Check breaks
    $brk_res = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, IFNULL(break_end, '$now_db'))) as total_brk, SUM(CASE WHEN break_end IS NULL THEN 1 ELSE 0 END) as active_break FROM attendance_breaks WHERE attendance_id = $att_id");
    $brk_row = $brk_res->fetch_assoc();
    $total_break_sec = $brk_row['total_brk'] ?? 0;
    $is_on_break = ($brk_row['active_break'] > 0);

    if ($attendance_record['punch_out']) {
        $is_punched_out = true;
        $att_status = "Shift Completed";
        $total_seconds_worked = (strtotime($attendance_record['punch_out']) - strtotime($attendance_record['punch_in'])) - $total_break_sec;
    } else {
        $att_status = $is_on_break ? "On Break" : "On Duty";
        $total_seconds_worked = (time() - strtotime($attendance_record['punch_in'])) - $total_break_sec;
    }

    $total_seconds_worked = max(0, $total_seconds_worked);
    $h = floor($total_seconds_worked / 3600);
    $m = floor(($total_seconds_worked % 3600) / 60);
    $s = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// =========================================================================
// 5. FETCH TICKETS DATA (Real DB Metrics)
// =========================================================================
$pending_tickets = 0;
$internal_tickets = 0;
$external_tickets = 0;
$resolved_today = 0;
$critical_tickets = [];

$pend_q = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE status NOT IN ('Resolved', 'Closed')");
$pending_tickets = $pend_q->fetch_assoc()['cnt'] ?? 0;

$comp_q = $conn->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE status IN ('Resolved', 'Closed') AND DATE(updated_at) = ?");
$comp_q->bind_param("s", $today);
$comp_q->execute();
$resolved_today = $comp_q->get_result()->fetch_assoc()['cnt'] ?? 0;

$int_q = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE department NOT LIKE '%Vendor%' AND department NOT LIKE '%External%' AND status NOT IN ('Resolved', 'Closed')");
$internal_tickets = $int_q->fetch_assoc()['cnt'] ?? 0;

$ext_q = $conn->query("SELECT COUNT(*) as cnt FROM tickets WHERE (department LIKE '%Vendor%' OR department LIKE '%External%') AND status NOT IN ('Resolved', 'Closed')");
$external_tickets = $ext_q->fetch_assoc()['cnt'] ?? 0;

// Fetch Critical/High Priority Tickets for Action Required
$crit_q = $conn->query("
    SELECT t.ticket_code as id, t.subject, t.department as category, t.priority as status, t.created_at, 
           COALESCE(ep.full_name, u.username, 'Unknown') as raised_by 
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
    WHERE t.status NOT IN ('Resolved', 'Closed') 
    ORDER BY FIELD(t.priority, 'Critical', 'Urgent', 'High', 'Medium', 'Low'), t.created_at DESC 
    LIMIT 5
");
while ($r = $crit_q->fetch_assoc()) {
    $time_diff = time() - strtotime($r['created_at']);
    if ($time_diff < 60) $r['time'] = 'Just now';
    elseif ($time_diff < 3600) $r['time'] = floor($time_diff/60).' mins ago';
    elseif ($time_diff < 86400) $r['time'] = floor($time_diff/3600).' hrs ago';
    else $r['time'] = floor($time_diff/86400).' days ago';
    
    $r['initial'] = strtoupper(substr($r['raised_by'], 0, 1));
    
    if (in_array(strtolower($r['status']), ['critical', 'urgent'])) {
        $r['status_color'] = 'bg-red-100 text-red-600 border-red-200';
    } elseif (strtolower($r['status']) == 'high') {
        $r['status_color'] = 'bg-orange-100 text-orange-600 border-orange-200';
    } else {
        $r['status_color'] = 'bg-blue-100 text-blue-600 border-blue-200';
    }
    
    $critical_tickets[] = $r;
}

// =========================================================================
// 6. DYNAMIC TICKET VOLUME CHART DATA (Last 7 Days)
// =========================================================================
$chart_labels = [];
$internal_data = [];
$vendor_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date)); // e.g., 'Mon', 'Tue'
    
    // Internal Tickets
    $q_int = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE DATE(created_at) = '$date' AND department NOT LIKE '%Vendor%' AND department NOT LIKE '%External%'");
    $internal_data[] = $q_int->fetch_assoc()['c'] ?? 0;
    
    // Vendor/External Tickets
    $q_ext = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE DATE(created_at) = '$date' AND (department LIKE '%Vendor%' OR department LIKE '%External%')");
    $vendor_data[] = $q_ext->fetch_assoc()['c'] ?? 0;
}
$chart_labels_json = json_encode($chart_labels);
$internal_data_json = json_encode($internal_data);
$vendor_data_json = json_encode($vendor_data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Admin Dashboard - <?php echo htmlspecialchars($employee_name); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        :root {
            --primary-sidebar-width: 90px;
            --secondary-sidebar-width: 240px;
            --brand-color: #0d9488;
            --brand-hover: #0f766e;
        }

        body { 
            background-color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b;
            overflow-x: hidden;
        }
        
        /* Strict Flex Card Layout to prevent gaps */
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%; 
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* SVG Strict Sizing */
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s ease-out;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        /* Button Customizations */
        .btn-punch { 
            background-color: var(--brand-color); color: white; border: none; width: 100%; 
            padding: 0.85rem; border-radius: 0.75rem; font-weight: 700; 
            display: flex; align-items: center; justify-content: center; gap: 0.5rem; 
            cursor: pointer; transition: 0.2s;
        }
        .btn-punch:hover { background-color: var(--brand-hover); }

        /* Scrollbars */
        .custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        /* Sidebar Transition Logic */
        #mainContent {
            margin-left: var(--primary-sidebar-width);
            width: calc(100% - var(--primary-sidebar-width));
            transition: margin-left 0.3s ease, width 0.3s ease;
            min-height: 100vh;
        }

        body.secondary-open #mainContent {
            margin-left: calc(var(--primary-sidebar-width) + var(--secondary-sidebar-width));
            width: calc(100% - (var(--primary-sidebar-width) + var(--secondary-sidebar-width)));
        }
        
        @media (max-width: 1024px) {
            #mainContent, body.secondary-open #mainContent { 
                margin-left: 0 !important; 
                width: 100% !important; 
            }
        }
    </style>
</head>
<body class="bg-slate-50">

    <div class="fixed inset-y-0 left-0 z-50">
        <?php include '../sidebars.php'; ?>
    </div>

    <main id="mainContent">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <?php include '../header.php'; ?>
        </header>

        <div class="p-6 lg:p-8 max-w-[1600px] mx-auto w-full">
            
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 tracking-tight flex items-center gap-3">
                        IT Admin Dashboard
                    </h1>
                    <p class="text-slate-500 text-sm mt-1">Welcome back, <b><?php echo htmlspecialchars($employee_name); ?></b></p>
                </div>
                <div class="flex gap-3">
                    <div class="bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 shadow-sm flex items-center gap-2">
                        <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                    </div>
                </div>
            </div>

            <div class="w-full flex flex-col gap-6">

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="card border-l-4 border-l-red-500">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Pending Tickets</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $pending_tickets; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-500 text-xl shrink-0">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card border-l-4 border-l-teal-600">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Internal (SysAdmin)</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $internal_tickets; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 text-xl shrink-0">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card border-l-4 border-l-blue-500">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">External (Vendor)</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $external_tickets; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl shrink-0">
                                <i class="fas fa-network-wired"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card border-l-4 border-l-green-500">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Resolved Today</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $resolved_today; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl shrink-0">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    
                    <div class="col-span-1 lg:col-span-4">
                        <div class="card">
                            <div class="card-body flex flex-col">
                                <div class="text-center mb-6 w-full border-b border-gray-100 pb-4 shrink-0">
                                    <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">My Attendance</h3>
                                    <p class="text-lg font-bold text-slate-800 mt-1"><?php echo date("h:i A, d M Y"); ?></p>
                                </div>

                                <div class="flex-grow flex flex-col items-center justify-center">
                                    <div class="relative" style="width: 150px; height: 150px; margin: 0 auto;">
                                        <svg class="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 160 160">
                                            <circle cx="80" cy="80" r="70" stroke="#f1f5f9" stroke-width="12" fill="none"></circle>
                                            <?php 
                                                $pct = min(1, $total_seconds_worked / 32400); 
                                                $dashoffset = 440 - ($pct * 440);
                                                $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                                            ?>
                                            <circle cx="80" cy="80" r="70" stroke="<?php echo $ringColor; ?>" stroke-width="12" fill="none" 
                                                stroke-dasharray="440" stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset); ?>" 
                                                stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-0.5"><?php echo $is_on_break ? 'ON BREAK' : 'LOGGED IN'; ?></p>
                                            <p class="text-2xl font-black text-slate-800 leading-none tracking-tight" id="liveTimer" 
                                               data-running="<?php echo ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>"
                                               data-total="<?php echo $total_seconds_worked; ?>">
                                               <?php echo $total_hours_today; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-5 mb-2 text-[10px] font-bold text-teal-700 bg-teal-50 px-4 py-1.5 rounded-full border border-teal-100">
                                        Status: <?php echo $att_status; ?>
                                    </div>
                                </div>

                                <form method="POST" class="w-full mt-auto shrink-0 pt-4">
                                    <?php if (!$attendance_record): ?>
                                        <button type="submit" name="action" value="punch_in" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3.5 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                                            <i class="fa-solid fa-right-to-bracket"></i> Punch In
                                        </button>
                                    <?php elseif (!$attendance_record['punch_out']): ?>
                                        <div class="grid grid-cols-2 gap-3 w-full">
                                            <button type="submit" name="action" value="<?php echo $is_on_break ? 'break_end' : 'break_start'; ?>" class="bg-amber-400 hover:bg-amber-500 text-white font-bold py-3.5 rounded-xl shadow transition flex items-center justify-center gap-2">
                                                <i class="fa-solid <?php echo $is_on_break ? 'fa-play' : 'fa-mug-hot'; ?>"></i> <?php echo $is_on_break ? 'Resume' : 'Break'; ?>
                                            </button>
                                            <button type="submit" name="action" value="punch_out" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3.5 rounded-xl shadow transition flex items-center justify-center gap-2">
                                                <i class="fa-solid fa-right-from-bracket"></i> Out
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button disabled class="w-full bg-slate-50 border border-slate-200 text-slate-400 font-bold py-3.5 rounded-xl cursor-not-allowed flex items-center justify-center gap-2">
                                            <i class="fa-solid fa-check-circle"></i> Shift Completed
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-1 lg:col-span-5">
                        <div class="card">
                            <div class="card-body p-0 flex flex-col h-full">
                                <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-slate-50 rounded-t-2xl shrink-0">
                                    <h3 class="font-bold text-slate-800 text-md">
                                        <i class="fas fa-list-ul text-teal-600 mr-2"></i> Action Required
                                    </h3>
                                    <a href="manage_tickets.php" class="text-[10px] font-bold bg-white text-slate-600 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition shadow-sm">View All</a>
                                </div>
                                
                                <div class="overflow-y-auto overflow-x-auto custom-scroll flex-grow p-2">
                                    <table class="w-full text-left border-collapse whitespace-nowrap">
                                        <thead>
                                            <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100 sticky top-0 bg-white z-10">
                                                <th class="p-4">Ticket</th>
                                                <th class="p-4">Subject</th>
                                                <th class="p-4 text-right">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <?php if(empty($critical_tickets)): ?>
                                                <tr><td colspan="3" class="text-center p-8 text-gray-400"><i class="fa-solid fa-check-double text-2xl mb-2"></i><br>All caught up!</td></tr>
                                            <?php else: foreach($critical_tickets as $ticket): ?>
                                            <tr class="hover:bg-slate-50 transition group">
                                                <td class="p-4">
                                                    <span class="font-bold text-slate-700 text-xs">#<?php echo htmlspecialchars($ticket['id']); ?></span>
                                                    <p class="text-[10px] text-gray-400 mt-1"><?php echo htmlspecialchars($ticket['time']); ?></p>
                                                </td>
                                                <td class="p-4">
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-xs text-slate-800 max-w-[150px] truncate" title="<?php echo htmlspecialchars($ticket['subject']); ?>"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                                                        <div class="flex items-center gap-1.5 mt-1">
                                                            <div class="w-4 h-4 rounded-full bg-teal-600 text-white flex items-center justify-center text-[8px] font-bold">
                                                                <?php echo htmlspecialchars($ticket['initial']); ?>
                                                            </div>
                                                            <span class="text-[10px] text-gray-500 font-medium"><?php echo htmlspecialchars($ticket['raised_by']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="p-4 text-right">
                                                    <span class="text-[9px] px-2.5 py-1 border rounded-md font-black uppercase tracking-wider <?php echo $ticket['status_color']; ?>">
                                                        <?php echo htmlspecialchars($ticket['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-1 lg:col-span-3">
                        <div class="card bg-teal-700 text-white border-0">
                            <div class="p-6 flex flex-col items-center text-center border-b border-teal-600/50 shrink-0">
                                <div class="relative mb-4">
                                    <img src="<?php echo $profile_img; ?>" class="w-24 h-24 rounded-full border-4 border-teal-500 shadow-xl object-cover bg-white">
                                    <div class="absolute bottom-1 right-1 w-5 h-5 bg-green-400 border-2 border-teal-700 rounded-full"></div>
                                </div>
                                <h2 class="font-bold text-xl"><?php echo htmlspecialchars($employee_name); ?></h2>
                                <p class="text-teal-100 text-xs mt-1 font-medium"><?php echo htmlspecialchars($employee_role); ?></p>
                                <span class="bg-teal-800 text-teal-50 border border-teal-600/50 text-[10px] px-3 py-1 rounded-full font-bold mt-3 shadow-inner">Verified Admin</span>
                            </div>
                            
                            <div class="card-body p-6 space-y-4 bg-white text-slate-800 rounded-b-2xl">
                                <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 text-xs shrink-0">
                                        <i class="fa-solid fa-phone"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Phone</p>
                                        <p class="text-xs font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($employee_phone); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 text-xs shrink-0">
                                        <i class="fa-solid fa-envelope"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Email</p>
                                        <p class="text-xs font-semibold text-slate-800 truncate" title="<?php echo htmlspecialchars($employee_email); ?>">
                                            <?php echo htmlspecialchars($employee_email); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100 text-center">
                                        <p class="text-[9px] text-blue-500 font-bold uppercase mb-1">Experience</p>
                                        <p class="text-[11px] font-bold text-blue-900"><?php echo htmlspecialchars($experience_label); ?></p>
                                    </div>
                                    <div class="bg-indigo-50/50 p-3 rounded-xl border border-indigo-100 text-center">
                                        <p class="text-[9px] text-indigo-500 font-bold uppercase mb-1">Dept</p>
                                        <p class="text-[11px] font-bold text-indigo-900 truncate" title="<?php echo htmlspecialchars($employee_dept); ?>"><?php echo htmlspecialchars($employee_dept); ?></p>
                                    </div>
                                </div>

                                <?php
                                $emp_prof_q = $conn->query("SELECT emergency_contacts FROM employee_profiles WHERE user_id = $user_id")->fetch_assoc();
                                $emergency = json_decode($emp_prof_q['emergency_contacts'] ?? '[]', true);
                                if (!empty($emergency)): 
                                    $primary = $emergency[0]; ?>
                                    <div class="p-3 bg-red-50/50 rounded-xl border border-red-100 mt-2">
                                        <div class="flex items-center gap-1.5 mb-1.5">
                                            <i class="fa-solid fa-heart-pulse text-red-500 text-[10px]"></i>
                                            <span class="text-[9px] font-bold text-red-700 uppercase tracking-wider">Emergency Contact</span>
                                        </div>
                                        <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($primary['name']); ?> <br> <span class="text-slate-500 font-medium text-[11px] mt-0.5 inline-block"><?php echo htmlspecialchars($primary['phone']); ?></span></p>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-red-50/50 rounded-xl border border-red-100 mt-2">
                                        <div class="flex items-center gap-1.5 mb-1.5">
                                            <i class="fa-solid fa-heart-pulse text-red-500 text-[10px]"></i>
                                            <span class="text-[9px] font-bold text-red-700 uppercase tracking-wider">Emergency Contact</span>
                                        </div>
                                        <p class="text-xs font-bold text-slate-800">HR Helpdesk <br> <span class="text-slate-500 font-medium text-[11px] mt-0.5 inline-block">+91 1800 123 456</span></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    
                    <div class="col-span-1 lg:col-span-8">
                        <div class="card">
                            <div class="card-body flex flex-col">
                                <div class="flex justify-between items-center mb-6 shrink-0">
                                    <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2"><i class="fa-solid fa-chart-area text-teal-600"></i> Ticket Volume Trend</h3>
                                    <span class="text-[10px] bg-slate-50 px-3 py-1.5 rounded-lg font-bold text-gray-500 uppercase tracking-widest border border-slate-200">Last 7 Days</span>
                                </div>
                                <div id="volumeChart" class="flex-grow w-full"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-1 lg:col-span-4">
                        <div class="card bg-slate-800 border-slate-700 text-white relative overflow-hidden">
                            <div class="absolute right-[-20px] bottom-[-20px] p-4 opacity-10 pointer-events-none">
                                <i class="fas fa-server" style="font-size: 15rem;"></i>
                            </div>
                            
                            <div class="card-body relative z-10 p-8 flex flex-col justify-center">
                                <h3 class="font-bold text-teal-400 text-sm tracking-widest uppercase mb-2">System Health</h3>
                                <h2 class="text-5xl font-black mb-2">99.98%</h2>
                                <p class="text-xs text-slate-400 font-medium mb-8"><i class="fa-solid fa-circle-check text-green-400 mr-1"></i> All systems operational</p>
                                
                                <div class="space-y-6 w-full">
                                    <div>
                                        <div class="flex justify-between text-xs mb-2 font-bold text-slate-300 tracking-wider">
                                            <span>SERVER LOAD</span>
                                            <span>42%</span>
                                        </div>
                                        <div class="w-full bg-slate-700 rounded-full h-2">
                                            <div class="bg-teal-400 h-2 rounded-full" style="width: 42%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-xs mb-2 font-bold text-slate-300 tracking-wider">
                                            <span>MEMORY USAGE</span>
                                            <span>68%</span>
                                        </div>
                                        <div class="w-full bg-slate-700 rounded-full h-2">
                                            <div class="bg-yellow-400 h-2 rounded-full" style="width: 68%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-xs mb-2 font-bold text-slate-300 tracking-wider">
                                            <span>STORAGE CAPACITY</span>
                                            <span>85%</span>
                                        </div>
                                        <div class="w-full bg-slate-700 rounded-full h-2">
                                            <div class="bg-red-400 h-2 rounded-full" style="width: 85%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div> 
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // 1. Sidebar Toggle Logic Support
            window.toggleSecondaryMenuLayout = function(isOpen) {
                if(isOpen) {
                    document.body.classList.add('secondary-open');
                } else {
                    document.body.classList.remove('secondary-open');
                }
            };

            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            
            function updateMargin() {
                if (!primarySidebar || !mainContent) return;
                if (window.innerWidth <= 1024) {
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
            }

            if (primarySidebar) new ResizeObserver(updateMargin).observe(primarySidebar);
            if (secondarySidebar) {
                new MutationObserver(updateMargin).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            window.addEventListener('resize', updateMargin);
            updateMargin();

            // 2. Ticket Volume Area Chart (Dynamically Linked to DB)
            var volumeOptions = {
                series: [{
                    name: 'Internal Tickets',
                    data: <?php echo $internal_data_json; ?>
                }, {
                    name: 'External/Vendor',
                    data: <?php echo $vendor_data_json; ?>
                }],
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif'
                },
                colors: ['#0d9488', '#3b82f6'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: {
                    categories: <?php echo $chart_labels_json; ?>,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 } }
                },
                yaxis: {
                    labels: { style: { colors: '#64748b', fontSize: '11px', fontWeight: 600 } }
                },
                grid: {
                    borderColor: '#e2e8f0',
                    strokeDashArray: 4,
                    yaxis: { lines: { show: true } }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '12px',
                    fontWeight: 600,
                    markers: { radius: 12 }
                },
                tooltip: { theme: 'light' }
            };
            var volumeChart = new ApexCharts(document.querySelector("#volumeChart"), volumeOptions);
            volumeChart.render();

            // 3. Live Timer Logic for Attendance (Perfect Sync)
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
                
                const formattedTime = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');
                
                timerElement.innerText = formattedTime;

                // Update Progress Ring (9 hours = 32400 sec)
                const maxSeconds = 32400; 
                const circumference = 440; // Exact calculation: 2 * pi * r(70) ≈ 439.8
                const progress = Math.min(currentTotal / maxSeconds, 1);
                const offset = circumference - (progress * circumference);
                
                if(progressRing) {
                    progressRing.style.strokeDashoffset = offset;
                }
            }

            if (isRunning) {
                updateTimer();
                setInterval(updateTimer, 1000);
            }
        });
    </script>
</body>
</html>