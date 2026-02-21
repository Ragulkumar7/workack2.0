<?php
// -------------------------------------------------------------------------
// PAGE: IT Executive Dashboard (Full Professional Overview)
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

$emp_name = $profile['full_name'] ?? 'IT Executive';
$emp_role = $profile['designation'] ?? $profile['role'] ?? 'IT Support';
$emp_dept = $profile['department'] ?? 'IT Infrastructure';
$emp_phone = $profile['phone'] ?? 'Not Set';
$emp_email = $profile['email'] ?? 'Not Set';
$joined_date = !empty($profile['joining_date']) ? date("d M Y", strtotime($profile['joining_date'])) : 'N/A';

// Resolve Avatar
$avatar = "https://ui-avatars.com/api/?name=" . urlencode($emp_name) . "&background=0d9488&color=fff";
if (!empty($profile['profile_img']) && $profile['profile_img'] !== 'default_user.png') {
    $avatar = str_starts_with($profile['profile_img'], 'http') ? $profile['profile_img'] : '../assets/profiles/' . $profile['profile_img'];
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
$attendance = $stmt->get_result()->fetch_assoc();

// GLOBALLY DEFINED VARIABLES (Fixes the undefined variable error on line 321)
$att_status = "Not Punched In";
$display_punch_in = "--:--";
$is_punched_in = false;
$is_punched_out = false;
$is_on_break = false;
$total_seconds_worked = 0;
$total_hours_today = "00:00:00"; 

if ($attendance) {
    $att_id = $attendance['id'];
    $display_punch_in = date("h:i A", strtotime($attendance['punch_in']));
    $is_punched_in = true;
    
    // Check breaks
    $brk_res = $conn->query("SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, IFNULL(break_end, '$now_db'))) as total_brk, SUM(CASE WHEN break_end IS NULL THEN 1 ELSE 0 END) as active_break FROM attendance_breaks WHERE attendance_id = $att_id");
    $brk_row = $brk_res->fetch_assoc();
    $total_break_sec = $brk_row['total_brk'] ?? 0;
    $is_on_break = ($brk_row['active_break'] > 0);

    if ($attendance['punch_out']) {
        $is_punched_out = true;
        $att_status = "Shift Completed";
        $total_seconds_worked = (strtotime($attendance['punch_out']) - strtotime($attendance['punch_in'])) - $total_break_sec;
    } else {
        $att_status = $is_on_break ? "On Break" : "On Duty";
        $total_seconds_worked = (time() - strtotime($attendance['punch_in'])) - $total_break_sec;
    }

    // Safely format total seconds into HH:MM:SS
    $total_seconds_worked = max(0, $total_seconds_worked);
    $h = floor($total_seconds_worked / 3600);
    $m = floor(($total_seconds_worked % 3600) / 60);
    $s = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// =========================================================================
// 5. FETCH TICKETS DATA (Real DB Metrics)
// =========================================================================
$pending_count = 0;
$completed_today = 0;
$recent_tickets = [];

$pend_q = $conn->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE assigned_to = ? AND status NOT IN ('Resolved', 'Closed')");
$pend_q->bind_param("i", $user_id);
$pend_q->execute();
$pending_count = $pend_q->get_result()->fetch_assoc()['cnt'] ?? 0;

$comp_q = $conn->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE assigned_to = ? AND status IN ('Resolved', 'Closed') AND DATE(updated_at) = ?");
$comp_q->bind_param("is", $user_id, $today);
$comp_q->execute();
$completed_today = $comp_q->get_result()->fetch_assoc()['cnt'] ?? 0;

$rec_q = $conn->prepare("SELECT t.ticket_code, t.subject, t.status, COALESCE(ep.full_name, u.username) as raised_by 
                         FROM tickets t 
                         LEFT JOIN users u ON t.user_id = u.id 
                         LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                         WHERE t.assigned_to = ? 
                         ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Waiting for Parts', 'Resolved', 'Closed'), t.created_at DESC 
                         LIMIT 5");
$rec_q->bind_param("i", $user_id);
$rec_q->execute();
$rec_res = $rec_q->get_result();
while($r = $rec_res->fetch_assoc()) { 
    $recent_tickets[] = $r; 
}

// =========================================================================
// 6. FETCH INVENTORY DATA (Dynamic from hardware_assets table)
// =========================================================================
$stock_details = [];
$inv_query = "SELECT 
                COALESCE(category, 'Uncategorized') as item, 
                COUNT(*) as total, 
                SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available 
              FROM hardware_assets 
              GROUP BY category";

$inv_res = $conn->query($inv_query);

if ($inv_res && $inv_res->num_rows > 0) {
    while ($row = $inv_res->fetch_assoc()) {
        // Automatically determine status based on available stock
        $status = ($row['available'] < 5 && $row['total'] > 0) ? 'Low Stock' : 'Stable';
        
        $stock_details[] = [
            'item' => htmlspecialchars($row['item']),
            'available' => $row['available'],
            'total' => $row['total'],
            'status' => $status
        ];
    }
} else {
    // Graceful fallback if table is completely empty or just created
    $stock_details = [
        ['item' => 'No Assets Found', 'available' => 0, 'total' => 0, 'status' => 'Stable']
    ];
}

// Stats for Charts
$stats_ontime = 22; $stats_late = 2; $stats_wfh = 1;
$perf_score = 92; $perf_grade = "A+";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IT Executive</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-color: #0d9488;
            --brand-hover: #0f766e;
            --pending: #f59e0b;
            --completed: #10b981;
            --bg-body: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 95px;
        }
        body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent { 
            margin-left: var(--sidebar-width); 
            padding: 24px 32px; 
            transition: margin-left 0.3s ease, width 0.3s ease; 
            min-height: 100vh; 
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
        }

        .dashboard-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; }
        .card { background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; flex-direction: column; overflow: hidden; transition: 0.2s;}
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.05); transform: translateY(-2px);}
        .card-body { padding: 1.5rem; flex: 1; }

        /* Attendance Widget */
        .progress-circle-box { width: 140px; height: 140px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 1.5rem auto; position: relative; }
        .btn-punch { background-color: var(--brand-color); color: white; border: none; width: 100%; padding: 0.85rem; border-radius: 8px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; transition: 0.2s;}
        .btn-punch:hover { background-color: var(--brand-hover); }

        /* Profile Header */
        .profile-header-bg { background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); padding: 2rem 1rem; color: white; text-align: center; }
        .profile-img-box { width: 90px; height: 90px; border-radius: 50%; border: 4px solid white; margin: -45px auto 1rem; background: #fff; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-img-box img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .status-dot { width: 16px; height: 16px; background: #22c55e; border: 2px solid white; border-radius: 50%; position: absolute; bottom: 5px; right: 5px; }

        /* Custom Table & Badges */
        .custom-table { width: 100%; text-align: left; border-collapse: collapse; }
        .custom-table th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #64748b; padding: 1rem; border-bottom: 1px solid var(--border);}
        .custom-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; vertical-align: middle;}
        .custom-table tr:hover td { background-color: #f8fafc; }
        
        .badge-status { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block;}
        .st-low { background: #fee2e2; color: #dc2626; }
        .st-stable { background: #ecfdf5; color: #059669; }
        .st-open { background: #e0f2fe; color: #2563eb; }
        .st-progress { background: #fef3c7; color: #d97706; }
        .st-resolved { background: #dcfce7; color: #16a34a; }
        
        .notice-bar { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; border-right: 1px solid #fef3c7; border-top: 1px solid #fef3c7; border-bottom: 1px solid #fef3c7;}

        @media (max-width: 992px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 16px; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div id="mainContent">
        <?php include $headerPath; ?>

        <div class="max-w-[1600px] mx-auto w-full mt-4">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">IT Executive Dashboard</h1>
                    <p class="text-sm text-gray-500 mt-1">Welcome back, here is your system overview.</p>
                </div>
            </div>

            <div class="notice-bar shadow-sm">
                <i data-lucide="megaphone" class="text-amber-600 w-6 h-6 shrink-0"></i>
                <div>
                    <p class="text-sm font-bold text-amber-900 mb-0.5">Admin Announcement</p>
                    <p class="text-xs text-amber-800 mb-0">Please prioritize tickets related to the recent Server Migration. Update statuses promptly.</p>
                </div>
            </div>

            <div class="dashboard-grid mb-6 items-stretch">
                <div class="col-span-12 lg:col-span-3 h-full">
                    <div class="card h-full">
                        <div class="profile-header-bg">
                            <h4 class="text-xs font-bold uppercase tracking-widest opacity-80"><?php echo htmlspecialchars($emp_dept); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="profile-img-box">
                                <img src="<?php echo $avatar; ?>" alt="Profile">
                                <div class="status-dot"></div>
                            </div>
                            <div class="text-center mb-5">
                                <h2 class="font-bold text-lg text-slate-800"> <?php echo htmlspecialchars($emp_name); ?> </h2>
                                <p class="text-slate-500 text-xs font-medium"><?php echo htmlspecialchars($emp_role); ?></p>
                                <span class="inline-block mt-2 bg-slate-100 px-3 py-1 rounded-md text-[10px] font-bold text-slate-600 border border-slate-200"><?php echo htmlspecialchars($emp_dept); ?></span>
                            </div>
                            <div class="text-left space-y-3 pt-4 border-t border-slate-100">
                                <div class="flex items-center gap-3"><i data-lucide="smartphone" class="w-4 h-4 text-teal-600"></i><span class="text-xs font-semibold text-slate-600"><?php echo htmlspecialchars($emp_phone); ?></span></div>
                                <div class="flex items-center gap-3"><i data-lucide="mail" class="w-4 h-4 text-teal-600"></i><span class="text-xs font-semibold text-slate-600 truncate" title="<?php echo htmlspecialchars($emp_email); ?>"><?php echo htmlspecialchars($emp_email); ?></span></div>
                                <div class="flex items-center gap-3"><i data-lucide="calendar" class="w-4 h-4 text-teal-600"></i><span class="text-xs font-semibold text-slate-600">Joined: <?php echo $joined_date; ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 h-full">
                    <div class="card h-full">
                        <div class="card-body flex flex-col">
                            <div class="text-center border-b border-slate-100 pb-3 mb-2">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">My Attendance</span>
                                <p class="font-bold text-sm mt-1 text-slate-800"><?php echo date('h:i A') . ' • ' . date('d M Y'); ?></p>
                            </div>
                            
                            <div class="progress-circle-box flex-grow">
                                <svg class="w-full h-full transform -rotate-90" style="position:absolute; top:0; left:0;">
                                    <circle cx="70" cy="70" r="62" stroke="#f1f5f9" stroke-width="8" fill="transparent"></circle>
                                    <?php 
                                        $pct = min(1, $total_seconds_worked / 32400); // 9 Hrs Target
                                        $dashoffset = 390 - ($pct * 390); // 2*pi*62 ≈ 390
                                        $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                                    ?>
                                    <circle id="progressRing" cx="70" cy="70" r="62" stroke="<?php echo $ringColor; ?>" stroke-width="8" fill="transparent" stroke-dasharray="390" stroke-dashoffset="<?php echo $is_punched_out ? '0' : max(0, $dashoffset); ?>" stroke-linecap="round" style="transition: 0.5s;"></circle>
                                </svg>
                                <span class="text-[10px] font-bold text-gray-400 uppercase mt-2">Logged In</span>
                                <span class="text-2xl font-black text-slate-800 leading-none mt-1" id="liveTimer" data-running="<?php echo ($is_punched_in && !$is_punched_out && !$is_on_break) ? 'true' : 'false'; ?>" data-total="<?php echo $total_seconds_worked; ?>"><?php echo $total_hours_today; ?></span>
                            </div>
                            
                            <div class="text-center mb-4 text-[10px] font-bold text-teal-700 bg-teal-50 px-3 py-1 rounded-full border border-teal-100 mx-auto">
                                Status: <?php echo $att_status; ?>
                            </div>

                            <form method="POST" class="mt-auto">
                                <?php if (!$is_punched_in): ?>
                                    <button type="submit" name="action" value="punch_in" class="btn-punch"><i data-lucide="fingerprint" class="w-5 h-5"></i> Punch In</button>
                                <?php elseif (!$is_punched_out): ?>
                                    <div class="flex gap-2">
                                        <button type="submit" name="action" value="<?php echo $is_on_break ? 'break_end' : 'break_start'; ?>" class="btn-punch w-1/2" style="background:<?php echo $is_on_break ? '#10b981' : '#f59e0b'; ?>;">
                                            <i data-lucide="<?php echo $is_on_break ? 'play' : 'coffee'; ?>" class="w-4 h-4"></i> <?php echo $is_on_break ? 'End Break' : 'Break'; ?>
                                        </button>
                                        <button type="submit" name="action" value="punch_out" class="btn-punch w-1/2" style="background:#ef4444;">
                                            <i data-lucide="log-out" class="w-4 h-4"></i> Punch Out
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button disabled class="w-full bg-slate-100 text-slate-400 font-bold py-3 rounded-lg text-xs cursor-not-allowed flex justify-center items-center gap-2 border border-slate-200">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i> Shift Completed
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5 h-full">
                    <div class="card h-full">
                        <div class="card-body flex flex-col">
                            <h3 class="font-bold text-slate-800 text-lg mb-4">Monthly Performance</h3>
                            <div class="flex items-center justify-between flex-grow">
                                <div class="space-y-4 w-full">
                                    <div class="flex items-center justify-between px-3 py-2 bg-slate-50 rounded-lg">
                                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-teal-600"></div><span class="text-sm text-slate-600 font-medium">On Time</span></div>
                                        <span class="text-sm font-bold text-slate-800"><?php echo $stats_ontime; ?> Days</span>
                                    </div>
                                    <div class="flex items-center justify-between px-3 py-2 bg-slate-50 rounded-lg">
                                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-emerald-500"></div><span class="text-sm text-slate-600 font-medium">Late</span></div>
                                        <span class="text-sm font-bold text-slate-800"><?php echo $stats_late; ?> Days</span>
                                    </div>
                                    <div class="flex items-center justify-between px-3 py-2 bg-slate-50 rounded-lg">
                                        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-amber-500"></div><span class="text-sm text-slate-600 font-medium">WFH / Leaves</span></div>
                                        <span class="text-sm font-bold text-slate-800"><?php echo $stats_wfh; ?> Days</span>
                                    </div>
                                </div>
                                <div class="w-32 h-32 shrink-0 ml-4">
                                    <div id="attendanceChart"></div>
                                </div>
                            </div>
                            <hr class="my-4 border-slate-100">
                            <div class="flex justify-between items-center mt-auto">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Resolution Score</p>
                                    <p class="text-2xl font-black text-teal-700"><?php echo $perf_score; ?>% <span class="text-sm font-bold text-slate-500">(<?php echo $perf_grade; ?>)</span></p>
                                </div>
                                <div id="miniPerfChart" style="width:100px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid items-stretch">
                <div class="col-span-12 lg:col-span-5 h-full">
                    <div class="card h-full">
                        <div class="card-body flex flex-col">
                            <h3 class="font-bold text-slate-800 text-lg mb-4"><i data-lucide="box" class="w-5 h-5 inline mr-1 text-teal-600"></i> IT Inventory Status</h3>
                            <div class="overflow-x-auto flex-grow">
                                <table class="custom-table w-full">
                                    <thead><tr><th>Asset Type</th><th class="text-center">Available</th><th class="text-right">Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach($stock_details as $s): ?>
                                        <tr>
                                            <td class="font-semibold text-slate-700"><?php echo $s['item']; ?></td>
                                            <td class="text-center font-medium"><?php echo $s['available']; ?> <span class="text-slate-400 text-xs">/ <?php echo $s['total']; ?></span></td>
                                            <td class="text-right"><span class="badge-status <?php echo ($s['status'] == 'Low Stock') ? 'st-low' : 'st-stable'; ?>"><?php echo $s['status']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7 h-full">
                    <div class="card h-full">
                        <div class="card-body flex flex-col">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-slate-800 text-lg"><i data-lucide="ticket" class="w-5 h-5 inline mr-1 text-orange-500"></i> Assigned Tickets Tracker</h3>
                                <a href="assigned_tickets.php" class="text-xs font-bold text-teal-600 hover:underline">View All</a>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4 shrink-0">
                                <div class="bg-emerald-50 border border-emerald-100 p-3 rounded-xl flex items-center justify-between">
                                    <div><p class="text-2xl font-black text-emerald-700 leading-none"><?php echo sprintf("%02d", $completed_today); ?></p><p class="text-[10px] font-bold text-emerald-600 uppercase mt-1">Resolved Today</p></div>
                                    <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center"><i data-lucide="check-circle" class="w-5 h-5"></i></div>
                                </div>
                                <div class="bg-amber-50 border border-amber-100 p-3 rounded-xl flex items-center justify-between">
                                    <div><p class="text-2xl font-black text-amber-700 leading-none"><?php echo sprintf("%02d", $pending_count); ?></p><p class="text-[10px] font-bold text-amber-600 uppercase mt-1">Pending Actions</p></div>
                                    <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center"><i data-lucide="clock" class="w-5 h-5"></i></div>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto flex-grow">
                                <table class="custom-table min-w-full">
                                    <thead><tr><th>Ticket ID</th><th>Issue Subject</th><th>Requester</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($recent_tickets)): ?>
                                            <tr><td colspan="5" class="text-center py-6 text-slate-400">No recent tickets assigned.</td></tr>
                                        <?php else: foreach($recent_tickets as $t): 
                                            $st_class = 'st-open';
                                            if (strtolower($t['status']) == 'in progress') $st_class = 'st-progress';
                                            elseif (in_array(strtolower($t['status']), ['resolved', 'closed'])) $st_class = 'st-resolved';
                                        ?>
                                        <tr>
                                            <td class="font-bold text-slate-700 text-xs">#<?php echo htmlspecialchars($t['ticket_code']); ?></td>
                                            <td class="font-medium text-slate-800 max-w-[150px] truncate" title="<?php echo htmlspecialchars($t['subject']); ?>"><?php echo htmlspecialchars($t['subject']); ?></td>
                                            <td class="text-xs text-slate-500"><?php echo htmlspecialchars($t['raised_by']); ?></td>
                                            <td><span class="badge-status <?php echo $st_class; ?>"><?php echo htmlspecialchars($t['status']); ?></span></td>
                                            <td class="text-right"><a href="assigned_tickets.php" class="text-xs font-bold text-teal-600 hover:text-teal-800 bg-teal-50 px-2 py-1 rounded">Open</a></td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<script>
    // Initialize Icons
    lucide.createIcons();

    // Responsive Sidebar Logic
    function setupLayoutObserver() {
        const primarySidebar = document.querySelector('.sidebar-primary');
        const secondarySidebar = document.querySelector('.sidebar-secondary');
        const mainContent = document.getElementById('mainContent');
        if (!primarySidebar || !mainContent) return;

        const updateMargin = () => {
            if (window.innerWidth <= 992) {
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
        };

        new ResizeObserver(() => updateMargin()).observe(primarySidebar);
        if (secondarySidebar) {
            new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
        }
        window.addEventListener('resize', updateMargin);
        updateMargin();
    }
    document.addEventListener('DOMContentLoaded', setupLayoutObserver);

    // Live Timer Logic
    document.addEventListener('DOMContentLoaded', function () {
        const timerElement = document.getElementById('liveTimer');
        const progressRing = document.getElementById('progressRing');
        if(!timerElement) return;

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

            const maxSeconds = 32400; // 9 hours
            const circumference = 390; // 2 * pi * 62
            const progress = Math.min(currentTotal / maxSeconds, 1);
            const offset = circumference - (progress * circumference);
            if(progressRing) progressRing.style.strokeDashoffset = offset;
        }

        if (isRunning) setInterval(updateTimer, 1000);

        // Apex Charts Initialization
        if(document.querySelector("#attendanceChart")) {
            new ApexCharts(document.querySelector("#attendanceChart"), {
                series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>],
                chart: { type: 'donut', width: 130, height: 130, sparkline: { enabled: true } },
                colors: ['#0d9488', '#10b981', '#f59e0b'],
                stroke: { width: 0 },
                tooltip: { enabled: true, y: { formatter: function(val) { return val + " Days" } } }
            }).render();
        }

        if(document.querySelector("#miniPerfChart")) {
            new ApexCharts(document.querySelector("#miniPerfChart"), {
                series: [{ name: 'Score', data: [75, 80, 85, 82, 88, 90, 92] }],
                chart: { type: 'area', height: 40, sparkline: { enabled: true } },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0, stops: [0, 100] } },
                colors: ['#0d9488'],
                tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function (seriesName) { return '' } } }, marker: { show: false } }
            }).render();
        }
    });
</script>

<?php ob_end_flush(); ?>