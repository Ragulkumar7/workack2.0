<?php
// -------------------------------------------------------------------------
// PAGE: IT Executive Dashboard (Full Professional Overview)
// -------------------------------------------------------------------------
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE & TIMEZONE CONFIG
date_default_timezone_set('Asia/Kolkata');
require_once '../include/db_connect.php'; 

// 2. ATTENDANCE LOGIC
$current_user_id = $_SESSION['user_id'] ?? 1; 
$today = date('Y-m-d');
$display_punch_in = "--:--";
$total_hours_today = "00:00:00";
$is_on_break = false;
$total_seconds_worked = 0;

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

    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ?";
    $sum_stmt = mysqli_prepare($conn, $sum_sql);
    mysqli_stmt_bind_param($sum_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($sum_stmt);
    $sum_res = mysqli_fetch_assoc(mysqli_stmt_get_result($sum_stmt));
    $total_break_seconds = $sum_res['total'] ?? 0;

    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    $start_ts = strtotime($attendance_record['punch_in']);
    $now_ts = ($is_on_break) ? $break_start_ts : (($attendance_record['punch_out']) ? strtotime($attendance_record['punch_out']) : time());
    
    $total_seconds_worked = ($now_ts - $start_ts) - $total_break_seconds;
    if ($total_seconds_worked < 0) $total_seconds_worked = 0;

    $h = floor($total_seconds_worked / 3600);
    $m = floor(($total_seconds_worked % 3600) / 60);
    $s = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// Stats for Charts (Mocked based on your previous code)
$stats_ontime = 22; $stats_late = 2; $stats_wfh = 5; $stats_absent = 1; $stats_sick = 0;
$perf_score = 92; $perf_grade = "A+";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $now_db = date('Y-m-d H:i:s');
    if ($_POST['action'] == 'punch_in' && !$attendance_record) {
        $stmt = mysqli_prepare($conn, "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')");
        mysqli_stmt_bind_param($stmt, "iss", $current_user_id, $now_db, $today);
        mysqli_stmt_execute($stmt);
    } elseif ($_POST['action'] == 'break_start' && $attendance_record && !$is_on_break) {
        $stmt = mysqli_prepare($conn, "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "is", $attendance_record['id'], $now_db);
        mysqli_stmt_execute($stmt);
    } elseif ($_POST['action'] == 'break_end' && $is_on_break) {
        $stmt = mysqli_prepare($conn, "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
        mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
        mysqli_stmt_execute($stmt);
    } elseif ($_POST['action'] == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        $stmt = mysqli_prepare($conn, "UPDATE attendance SET punch_out = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
        mysqli_stmt_execute($stmt);
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit();
}

// 4. MOCK DATA
$exec_name = "Stephen Peralt";
$exec_role = "Senior Software Engineer";
$current_date = date('d M, Y');
$current_time = date('h:i A');

$stock_details = [
    ['item' => 'Laptops', 'available' => 12, 'total' => 45, 'status' => 'Stable'],
    ['item' => 'Monitors', 'available' => 5, 'total' => 30, 'status' => 'Low Stock'],
    ['item' => 'Keyboards', 'available' => 22, 'total' => 50, 'status' => 'Stable']
];

$pending_count = 7;
$completed_today = 4;

$priority_tasks = [
    ['id' => 'IT-2026-901', 'issue' => 'Server Connectivity Failure', 'dept' => 'Finance', 'time' => '10 mins ago'],
    ['id' => 'IT-2026-899', 'issue' => 'CEO Laptop Crash', 'dept' => 'Management', 'time' => '1 hour ago']
];

$recent_tickets = [
    ['id' => 'IT-2026-884', 'issue' => 'Blue Screen Error', 'raised_by' => 'Priya (HR)', 'status' => 'In Progress'],
    ['id' => 'IT-2026-880', 'issue' => 'Printer Jam', 'raised_by' => 'Rahul (Sales)', 'status' => 'Pending'],
    ['id' => 'IT-2026-870', 'issue' => 'Install PowerBI', 'raised_by' => 'Vikram (Data)', 'status' => 'Resolved']
];

include('../header.php'); 
include('../sidebars.php'); 
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    :root {
        --brand-color: #0d9488;
        --pending: #f59e0b;
        --progress: #3b82f6;
        --completed: #10b981;
        --bg-body: #f1f5f9;
        --border: #e2e8f0;
    }
    body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; color: #344767; overflow-x: hidden; }
    #mainContent { margin-left: 95px; padding: 30px; transition: all 0.3s ease; min-height: 100vh; }
    #mainContent.main-shifted { margin-left: 315px; }
    
    .dashboard-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; }
    .card { background: white; border-radius: 1rem; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); height: 100%; display: flex; flex-direction: column; overflow: hidden; }
    .card-body { padding: 1.5rem; flex: 1; }

    /* Attendance Widget */
    .progress-circle-box { width: 140px; height: 140px; border-radius: 50%; border: 10px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 1.5rem auto; position: relative; }
    .btn-punch { background-color: var(--brand-color); color: white; border: none; width: 100%; padding: 0.75rem; border-radius: 0.75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; }

    /* Profile Header */
    .profile-header-bg { background: var(--brand-color); padding: 2rem 1rem; color: white; text-align: center; }
    .profile-img-box { width: 90px; height: 90px; border-radius: 50%; border: 4px solid white; margin: -45px auto 1rem; background: #fff; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .profile-img-box img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    .status-dot { width: 15px; height: 15px; background: #4ade80; border: 2px solid white; border-radius: 50%; position: absolute; bottom: 5px; right: 5px; }

    /* Custom Table & Badges */
    .custom-table th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #64748b; padding: 1rem; }
    .custom-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
    .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .st-low { background: #fee2e2; color: #dc2626; }
    .st-stable { background: #ecfdf5; color: #059669; }
    
    .notice-bar { background: #fffbeb; border-left: 5px solid #f59e0b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
</style>

<div id="mainContent">
    <div class="dashboard-container">
        
        <div class="notice-bar">
            <i data-lucide="megaphone" class="text-amber-600 w-5"></i>
            <div>
                <p class="text-sm font-bold text-amber-900">IT Admin Announcement</p>
                <p class="text-xs text-amber-800">Critical server maintenance at 10 PM tonight. System backups required.</p>
            </div>
        </div>

        <div class="dashboard-grid mb-4">
            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card-body text-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Today's Attendance</span>
                        <p class="font-bold text-lg mt-2"><?php echo $current_time . ', ' . $current_date; ?></p>
                        <div class="progress-circle-box">
                            <svg class="w-full h-full transform -rotate-90" style="position:absolute; top:-10px; left:-10px; width:140px; height:140px;">
                                <circle cx="70" cy="70" r="60" stroke="#f1f5f9" stroke-width="10" fill="transparent"></circle>
                                <?php 
                                    $pct = min(1, $total_seconds_worked / 32400); 
                                    $dashoffset = 377 - ($pct * 377);
                                    $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                                ?>
                                <circle id="progressRing" cx="70" cy="70" r="60" stroke="<?php echo $ringColor; ?>" stroke-width="10" fill="transparent" stroke-dasharray="377" stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset); ?>" stroke-linecap="round" style="transition: 0.5s;"></circle>
                            </svg>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">Worked</span>
                            <span class="text-xl font-bold" id="liveTimer" data-running="<?php echo ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>" data-total="<?php echo $total_seconds_worked; ?>"><?php echo $total_hours_today; ?></span>
                        </div>
                        <form method="POST">
                            <?php if (!$attendance_record): ?>
                                <button type="submit" name="action" value="punch_in" class="btn-punch"><i data-lucide="log-in"></i> Punch In</button>
                            <?php elseif (!$attendance_record['punch_out']): ?>
                                <div style="display:flex; gap:0.5rem;">
                                    <button type="submit" name="action" value="<?php echo $is_on_break ? 'break_end' : 'break_start'; ?>" class="btn-punch" style="background:<?php echo $is_on_break ? '#3b82f6' : '#f59e0b'; ?>;"><i data-lucide="<?php echo $is_on_break ? 'play' : 'coffee'; ?>"></i> <?php echo $is_on_break ? 'Resume' : 'Break'; ?></button>
                                    <button type="submit" name="action" value="punch_out" class="btn-punch" style="background:#f97316;"><i data-lucide="log-out"></i> Out</button>
                                </div>
                            <?php else: ?>
                                <div class="btn-punch bg-slate-100 text-slate-400" style="cursor:default;"><i data-lucide="check-circle"></i> Shift Done</div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-5">
                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-lg mb-4">Leave & Performance</h3>
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div class="space-y-3">
                                <div style="display:flex; align-items:center; gap:0.5rem;"><div style="width:10px; height:10px; border-radius:50%; background:var(--brand-color);"></div><span class="text-sm font-bold w-6"><?php echo $stats_ontime; ?></span><span class="text-sm text-slate-500">On Time</span></div>
                                <div style="display:flex; align-items:center; gap:0.5rem;"><div style="width:10px; height:10px; border-radius:50%; background:var(--completed);"></div><span class="text-sm font-bold w-6"><?php echo $stats_late; ?></span><span class="text-sm text-slate-500">Late</span></div>
                                <div style="display:flex; align-items:center; gap:0.5rem;"><div style="width:10px; height:10px; border-radius:50%; background:var(--pending);"></div><span class="text-sm font-bold w-6"><?php echo $stats_wfh; ?></span><span class="text-sm text-slate-500">WFH</span></div>
                            </div>
                            <div id="attendanceChart"></div>
                        </div>
                        <hr class="my-4">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div><p class="text-xs font-bold text-slate-400 uppercase">Performance Score</p><p class="text-2xl font-bold text-slate-800"><?php echo $perf_score; ?>% (<?php echo $perf_grade; ?>)</p></div>
                            <div id="miniPerfChart" style="width:80px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="card">
                    <div class="profile-header-bg">
                        <h4 class="text-xs font-bold uppercase tracking-widest opacity-80">Stephen Peralt</h4>
                    </div>
                    <div class="card-body">
                        <div class="profile-img-box">
                            <img src="https://ui-avatars.com/api/?name=Stephen+Peralt&background=fff&color=0d9488" alt="Profile">
                            <div class="status-dot"></div>
                        </div>
                        <div class="text-center mb-4">
                            <h2 class="font-bold text-lg text-slate-800"> Stephen Peralt </h2>
                            <p class="text-slate-500 text-xs">Senior Software Engineer</p>
                            <span class="inline-block mt-2 bg-slate-100 px-3 py-1 rounded-full text-[10px] font-bold">IT Operations</span>
                        </div>
                        <div class="text-left space-y-3 pt-3 border-t">
                            <div class="flex items-center gap-3"><i data-lucide="smartphone" class="w-4 text-teal-600"></i><span class="text-xs font-semibold text-slate-600">+91 98765 43210</span></div>
                            <div class="flex items-center gap-3"><i data-lucide="mail" class="w-4 text-teal-600"></i><span class="text-xs font-semibold text-slate-600">stephen@neoera.com</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="col-span-12 lg:col-span-5">
                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-lg mb-4">Inventory Stock</h3>
                        <table class="custom-table w-full">
                            <thead><tr><th>Asset</th><th class="text-center">Avail</th><th class="text-right">Status</th></tr></thead>
                            <tbody>
                                <?php foreach($stock_details as $s): ?>
                                <tr>
                                    <td class="font-bold"><?php echo $s['item']; ?></td>
                                    <td class="text-center"><?php echo $s['available']; ?>/<?php echo $s['total']; ?></td>
                                    <td class="text-right"><span class="badge-status <?php echo ($s['status'] == 'Low Stock') ? 'st-low' : 'st-stable'; ?>"><?php echo $s['status']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-7">
                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-lg mb-4">Active Ticket Performance</h3>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div style="background:#f0fdf4; padding:1rem; border-radius:0.75rem; text-align:center;"><p class="text-2xl font-bold text-emerald-600">0<?php echo $completed_today; ?></p><p class="text-[10px] font-bold text-emerald-500 uppercase">Tickets Completed</p></div>
                            <div style="background:#fff7ed; padding:1rem; border-radius:0.75rem; text-align:center;"><p class="text-2xl font-bold text-orange-600">0<?php echo $pending_count; ?></p><p class="text-[10px] font-bold text-orange-500 uppercase">Pending Actions</p></div>
                        </div>
                        <table class="custom-table w-full">
                            <thead><tr><th>ID</th><th>Issue</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                            <tbody>
                                <?php foreach($recent_tickets as $t): ?>
                                <tr>
                                    <td class="font-bold text-teal-600"><?php echo $t['id']; ?></td>
                                    <td><?php echo $t['issue']; ?></td>
                                    <td><span class="badge-status <?php echo ($t['status']=='Pending') ? 'st-low' : 'st-stable'; ?>"><?php echo $t['status']; ?></span></td>
                                    <td class="text-right"><a href="#" class="text-xs font-bold text-teal-600">VIEW</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();

    document.addEventListener('DOMContentLoaded', function () {
        // Attendance Chart
        new ApexCharts(document.querySelector("#attendanceChart"), {
            series: [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>],
            chart: { type: 'donut', width: 120, height: 120, sparkline: { enabled: true } },
            colors: ['#0d9488', '#10b981', '#f59e0b'],
            stroke: { width: 0 }
        }).render();

        // Mini Performance Chart
        new ApexCharts(document.querySelector("#miniPerfChart"), {
            series: [{ data: [30, 40, 35, 50, 49, 60, 70, 91] }],
            chart: { type: 'area', height: 40, sparkline: { enabled: true } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#0d9488']
        }).render();
    });

    // Live Timer
    const timerElement = document.getElementById('liveTimer');
    const progressRing = document.getElementById('progressRing');
    const isRunning = timerElement.getAttribute('data-running') === 'true';
    let totalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
    const startTime = new Date().getTime();

    function updateTimer() {
        if (!isRunning) return;
        const currentTotal = totalSeconds + Math.floor((new Date().getTime() - startTime) / 1000);
        const h = Math.floor(currentTotal / 3600);
        const m = Math.floor((currentTotal % 3600) / 60);
        const s = currentTotal % 60;
        timerElement.innerText = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
        const progress = Math.min(currentTotal / 32400, 1);
        if(progressRing) progressRing.style.strokeDashoffset = 377 - (progress * 377);
    }
    if (isRunning) setInterval(updateTimer, 1000);
</script>

<?php ob_end_flush(); ?>