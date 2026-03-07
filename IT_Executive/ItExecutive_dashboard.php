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

// Grab Manager ID for later
$reporting_id = !empty($profile['manager_id']) ? $profile['manager_id'] : ($profile['reporting_to'] ?? 0);

$shift_timings = $profile['shift_timings'] ?? '09:00 AM - 06:00 PM';
$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
$regular_shift_hours = 9;

// =========================================================================
// FETCH REPORTING MANAGER DATA (Variables correctly defined here)
// =========================================================================
$mgr_name = "System Admin";
$mgr_phone = "Not Assigned";
$mgr_email = "admin@company.com";
$mgr_role = "ADMINISTRATOR";

if ($reporting_id > 0) {
    $hm_sql = "SELECT p.full_name, p.phone, u.email, u.role FROM employee_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?";
    $hm_stmt = $conn->prepare($hm_sql);
    $hm_stmt->bind_param("i", $reporting_id);
    $hm_stmt->execute();
    $hm_res = $hm_stmt->get_result();
    if ($hm_info = $hm_res->fetch_assoc()) {
        $mgr_name = !empty($hm_info['full_name']) ? $hm_info['full_name'] : "Manager";
        $mgr_phone = !empty($hm_info['phone']) ? $hm_info['phone'] : "Not Set";
        $mgr_email = !empty($hm_info['email']) ? $hm_info['email'] : "Not Set";
        $mgr_role = strtoupper($hm_info['role'] ?? 'MANAGER'); 
    }
    $hm_stmt->close();
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

// GLOBALLY DEFINED VARIABLES
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
        
        /* Container for Top Row Cards */
        .dashboard-container { 
            display: grid; 
            grid-template-columns: repeat(1, minmax(0, 1fr)); 
            gap: 1.5rem; 
            align-items: stretch;
        }
        @media (min-width: 1024px) {
            .dashboard-container {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

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

            <div class="dashboard-container mb-6">
                
                <div class="flex flex-col gap-6 w-full">
                    <?php 
                // Auto-heal missing closing tags from attendance_card.php during "Break" state
                ob_start();
                include '../attendance_card.php'; 
                $att_card_html = ob_get_clean();
                echo $att_card_html;
                
                $div_open = substr_count(strtolower($att_card_html), '<div');
                $div_close = substr_count(strtolower($att_card_html), '</div');
                if ($div_open > $div_close) {
                    echo str_repeat('</div>', $div_open - $div_close);
                }
                ?>
                </div>

                <div class="flex flex-col gap-6 w-full">
                    <div class="card shrink-0">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                                <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                                <span class="text-[10px] font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded uppercase"><?php echo date('M Y'); ?></span>
                            </div>
                            <div class="flex flex-col xl:flex-row items-center justify-between gap-6">
                                <div class="space-y-3.5 w-full pr-2">
                                    <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="text-xs text-gray-600 font-semibold">On Time</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_ontime; ?></span></div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div><span class="text-xs text-gray-600 font-semibold">Late</span></div>
                                        <div class="text-right">
                                            <span class="font-bold text-slate-800 text-sm block"><?php echo $stats_late; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div><span class="text-xs text-gray-600 font-semibold">WFH</span></div><span class="font-bold text-slate-800 text-sm"><?php echo $stats_wfh; ?></span></div>
                                    <div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div><span class="text-xs text-gray-600 font-semibold">Absent</span></div><span class="font-bold text-slate-800 text-sm">0</span></div>
                                    
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                        <div class="flex items-center gap-2"><i data-lucide="plane-takeoff" class="text-rose-400 w-3 h-3"></i><span class="text-xs text-slate-800 font-bold uppercase">Leaves Taken</span></div>
                                        <span class="font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded text-xs">0 Days</span>
                                    </div>
                                </div>
                                <div class="relative flex-shrink-0 w-28 h-28 mx-auto">
                                    <div id="attendanceChart" class="w-full h-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card flex-grow justify-center">
                        <div class="p-6">
                            <h3 class="font-bold text-slate-800 text-sm mb-4 uppercase tracking-wider">Quick Actions</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <a href="it_exec_ticket_action.php" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-100 p-3 rounded-xl flex flex-col items-center justify-center transition shadow-sm text-indigo-700">
                                    <i data-lucide="ticket" class="w-5 h-5 mb-1"></i>
                                    <span class="text-xs font-bold mt-1">My Tickets</span>
                                </a>
                                <a href="stock_maintenance.php" class="bg-teal-50 hover:bg-teal-100 border border-teal-100 p-3 rounded-xl flex flex-col items-center justify-center transition shadow-sm text-teal-700">
                                    <i data-lucide="box" class="w-5 h-5 mb-1"></i>
                                    <span class="text-xs font-bold mt-1">Inventory</span>
                                </a>
                                <a href="../employee/leave_request.php" class="bg-rose-50 hover:bg-rose-100 border border-rose-100 p-3 rounded-xl flex flex-col items-center justify-center transition shadow-sm text-rose-700">
                                    <i data-lucide="calendar" class="w-5 h-5 mb-1"></i>
                                    <span class="text-xs font-bold mt-1">Apply Leave</span>
                                </a>
                                <a href="../employee/work_from_home_request.php" class="bg-amber-50 hover:bg-amber-100 border border-amber-100 p-3 rounded-xl flex flex-col items-center justify-center transition shadow-sm text-amber-700">
                                    <i data-lucide="laptop" class="w-5 h-5 mb-1"></i>
                                    <span class="text-xs font-bold mt-1">Apply WFH</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-6 w-full">
                    <div class="card overflow-hidden shadow-sm border-slate-200 shrink-0 h-full">
                        <div class="bg-gradient-to-br from-teal-700 to-teal-900 p-10 flex items-center gap-8 relative">
                            <div class="relative shrink-0">
                                <img src="<?php echo $avatar; ?>" class="w-20 h-20 rounded-full border-2 border-white shadow-lg object-cover bg-white">
                                <div class="absolute bottom-0 right-0 w-5 h-5 bg-emerald-400 border-2 border-white rounded-full"></div>
                            </div>
                            <div class="min-w-0 text-white">
                                <h2 class="font-black text-xl truncate"><?php echo htmlspecialchars($emp_name); ?></h2>
                                <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest truncate mt-0.5"><?php echo htmlspecialchars($emp_role); ?></p>
                                <span class="inline-block mt-2 bg-white/20 px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider backdrop-blur-sm border border-white/10">Verified IT</span>
                            </div>
                            <div class="absolute top-4 right-4 flex gap-2">
                                <a href="../settings.php" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition backdrop-blur-sm" title="Edit Profile">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="p-6 space-y-4">
                            <div class="flex flex-col gap-3">
                                
                                <div class="flex items-center gap-4 border border-slate-100 p-3 rounded-xl bg-slate-50 hover:bg-white transition">
                                    <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                        <i data-lucide="phone" class="w-5 h-5"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Phone Number</p>
                                        <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($emp_phone); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 border border-slate-100 p-3 rounded-xl bg-slate-50 hover:bg-white transition">
                                    <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                        <i data-lucide="mail" class="w-5 h-5"></i>
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Email Address</p>
                                        <p class="text-sm font-bold text-slate-800 mt-0.5 truncate w-full" title="<?php echo htmlspecialchars($emp_email); ?>">
                                            <?php echo htmlspecialchars($emp_email); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 border border-slate-100 p-3 rounded-xl bg-slate-50 hover:bg-white transition">
                                    <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                        <i data-lucide="calendar" class="w-5 h-5"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Joining Date</p>
                                        <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($joined_date); ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 border border-slate-100 p-3 rounded-xl bg-slate-50 hover:bg-white transition">
                                    <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                        <i data-lucide="user-check" class="w-5 h-5"></i>
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Reporting To</p>
                                        <div class="flex items-center justify-between mt-0.5">
                                            <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($mgr_name); ?></p>
                                            <span class="text-[8px] font-black text-teal-700 bg-teal-100 px-2 py-0.5 rounded uppercase tracking-wider shrink-0 border border-teal-200"><?php echo htmlspecialchars($mgr_role); ?></span>
                                        </div>
                                    </div>
                                </div>

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
                                            <td class="text-right"><a href="assigned_tickets.php" class="text-xs font-bold text-teal-600 hover:text-teal-800 bg-teal-50 px-2 py-1 rounded border border-teal-200 transition">Open</a></td>
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

    // Apex Charts Initialization
    document.addEventListener('DOMContentLoaded', function () {
        if(document.querySelector("#attendanceChart")) {
            new ApexCharts(document.querySelector("#attendanceChart"), {
                series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>],
                chart: { type: 'donut', width: 130, height: 130, sparkline: { enabled: true } },
                colors: ['#0d9488', '#10b981', '#f59e0b'],
                stroke: { width: 0 },
                tooltip: { enabled: true, y: { formatter: function(val) { return val + " Days" } } }
            }).render();
        }
    });
</script>

<?php ob_end_flush(); ?>