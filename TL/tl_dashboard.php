<?php
// TL/tl_dashboard.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION
$db_path = __DIR__ . '/../include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    require_once '../include/db_connect.php'; 
}

$tl_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// =========================================================================
// H. TL'S OWN ATTENDANCE LOGIC
// =========================================================================

function getWorkedSeconds($conn, $user_id, $date) {
    $att_check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
    $stmt_chk = $conn->prepare($att_check_sql);
    $stmt_chk->bind_param("is", $user_id, $date);
    $stmt_chk->execute();
    $record = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if (!$record) return 0;

    $break_seconds = 0;
    $is_on_break = false;
    $break_start_ts = 0;

    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $stmt_bk = $conn->prepare($bk_sql);
    $stmt_bk->bind_param("i", $record['id']);
    $stmt_bk->execute();
    if ($bk_row = $stmt_bk->get_result()->fetch_assoc()) {
        $is_on_break = true;
        $break_start_ts = strtotime($bk_row['break_start']);
    }
    $stmt_bk->close();

    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NOT NULL";
    $stmt_sum = $conn->prepare($sum_sql);
    $stmt_sum->bind_param("i", $record['id']);
    $stmt_sum->execute();
    $sum_res = $stmt_sum->get_result()->fetch_assoc();
    $break_seconds = $sum_res['total'] ?? 0;
    $stmt_sum->close();

    $start_ts = strtotime($record['punch_in']);
    if ($is_on_break) {
        $now_ts = $break_start_ts;
    } elseif ($record['punch_out']) {
        $now_ts = strtotime($record['punch_out']);
    } else {
        $now_ts = time();
    }
    
    $worked = ($now_ts - $start_ts) - $break_seconds;
    return $worked > 0 ? $worked : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $now_db = date('Y-m-d H:i:s');
    
    $att_check_sql = "SELECT id, punch_out FROM attendance WHERE user_id = ? AND date = ?";
    $stmt_chk = $conn->prepare($att_check_sql);
    $stmt_chk->bind_param("is", $tl_user_id, $today);
    $stmt_chk->execute();
    $record = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    $is_on_break = false;
    if ($record) {
        $bk_sql = "SELECT id FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
        $stmt_bk = $conn->prepare($bk_sql);
        $stmt_bk->bind_param("i", $record['id']);
        $stmt_bk->execute();
        if ($stmt_bk->get_result()->fetch_assoc()) { $is_on_break = true; }
        $stmt_bk->close();
    }

    try {
        if ($action === 'punch_in' && !$record) {
            $shift_type = $_POST['shift_type'] ?? 'regular';
            $status = 'On Time';
            $current_time = time();
            $shift_start = strtotime(date('Y-m-d') . ($shift_type === 'afternoon' ? ' 12:00:00' : ($shift_type === 'night' ? ' 19:00:00' : ' 09:00:00')));
            if ($current_time > $shift_start) { $status = 'Late'; }

            $ins = $conn->prepare("INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, ?)");
            $ins->bind_param("isss", $tl_user_id, $now_db, $today, $status);
            $ins->execute();
            echo json_encode(['status' => 'success', 'state' => 'in', 'time' => date('h:i A'), 'att_status' => $status]);
            exit();
        } 
        elseif ($action === 'break_start' && $record && !$is_on_break) {
            $ins = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)");
            $ins->bind_param("is", $record['id'], $now_db);
            $ins->execute();
            echo json_encode(['status' => 'success', 'state' => 'break', 'seconds' => getWorkedSeconds($conn, $tl_user_id, $today)]);
            exit();
        } 
        elseif ($action === 'break_end' && $record && $is_on_break) {
            $upd = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
            $upd->bind_param("si", $now_db, $record['id']);
            $upd->execute();
            echo json_encode(['status' => 'success', 'state' => 'in', 'seconds' => getWorkedSeconds($conn, $tl_user_id, $today)]);
            exit();
        } 
        elseif ($action === 'punch_out' && $record && !$record['punch_out']) {
            if ($is_on_break) {
                $upd = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
                $upd->bind_param("si", $now_db, $record['id']);
                $upd->execute();
            }
            $final_seconds = getWorkedSeconds($conn, $tl_user_id, $today);
            $hours = $final_seconds / 3600;
            $upd = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?");
            $upd->bind_param("sdi", $now_db, $hours, $record['id']);
            $upd->execute();
            echo json_encode(['status' => 'success', 'state' => 'out', 'seconds' => $final_seconds]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// 3. FETCH DATA
$tl_attendance_record = null;
$tl_is_on_break = false;
$stmt_chk = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt_chk->bind_param("is", $tl_user_id, $today);
$stmt_chk->execute();
$tl_attendance_record = $stmt_chk->get_result()->fetch_assoc();
$stmt_chk->close();

$tl_display_punch_in = $tl_attendance_record ? date('h:i A', strtotime($tl_attendance_record['punch_in'])) : "--:--";
if ($tl_attendance_record) {
    $stmt_bk = $conn->prepare("SELECT id FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL");
    $stmt_bk->bind_param("i", $tl_attendance_record['id']);
    $stmt_bk->execute();
    if ($stmt_bk->get_result()->fetch_assoc()) { $tl_is_on_break = true; }
    $stmt_bk->close();
}
$tl_total_seconds_worked = getWorkedSeconds($conn, $tl_user_id, $today);

// Fetch Profile Info
$tl_name = "Team Leader"; $tl_phone = "98765 43210"; $tl_email = "frank.tl@gmail.com"; $tl_dept = "Development"; $tl_exp = "Fresher"; $tl_join = date('d M Y');
$profile_query = "SELECT COALESCE(ep.full_name, u.name) as name, ep.phone, u.email, ep.department, ep.experience_label, ep.joining_date 
                  FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?";
$stmt_p = $conn->prepare($profile_query);
$stmt_p->bind_param("i", $tl_user_id);
$stmt_p->execute();
if ($row = $stmt_p->get_result()->fetch_assoc()) {
    $tl_name = $row['name']; $tl_phone = $row['phone'] ?? $tl_phone; $tl_email = $row['email'] ?? $tl_email;
    $tl_dept = $row['department'] ?? $tl_dept; $tl_exp = $row['experience_label'] ?? $tl_exp;
    $tl_join = $row['joining_date'] ? date('d M Y', strtotime($row['joining_date'])) : $tl_join;
}
$stmt_p->close();

// Stats with assoc fetch to avoid mysqli result error
$res_team = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE reporting_to = $tl_user_id")->fetch_assoc();
$total_team = $res_team['total'] ?? 0;
$res_p = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE ep.reporting_to = $tl_user_id AND a.date = '$today' AND (a.status='On Time' OR a.status='WFH')")->fetch_assoc();
$present = $res_p['cnt'] ?? 0;
$res_l = $conn->query("SELECT COUNT(*) as cnt FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE ep.reporting_to = $tl_user_id AND a.date = '$today' AND a.status='Late'")->fetch_assoc();
$late = $res_l['cnt'] ?? 0;
$absent = max(0, $total_team - ($present + $late));
$attendance_percentage = ($total_team > 0) ? round((($present + $late) / $total_team) * 100) : 0;

// Fetch Recent Tasks
$recent_tasks = [];
$rt_q = "SELECT pt.task_title, pt.assigned_to, pt.status, p.project_name FROM project_tasks pt JOIN projects p ON pt.project_id = p.id WHERE p.leader_id = ? ORDER BY pt.created_at DESC LIMIT 5";
$stmt_rt = $conn->prepare($rt_q);
if ($stmt_rt) {
    $stmt_rt->bind_param("i", $tl_user_id);
    $stmt_rt->execute();
    $res_rt = $stmt_rt->get_result();
    while ($row = $res_rt->fetch_assoc()) { $recent_tasks[] = $row; }
    $stmt_rt->close();
}

// Fetch Projects
$active_projects = [];
$proj_q = "SELECT project_name, progress FROM projects WHERE leader_id = ? AND status = 'Active'";
$stmt_proj = $conn->prepare($proj_q);
if ($stmt_proj) {
    $stmt_proj->bind_param("i", $tl_user_id);
    $stmt_proj->execute();
    $res_proj = $stmt_proj->get_result();
    while ($row = $res_proj->fetch_assoc()) { $active_projects[] = $row; }
    $stmt_proj->close();
}

// Task Priority
$high_tasks = 0; $med_tasks = 0; $low_tasks = 0;
$tp_q = "SELECT pt.priority, COUNT(*) as cnt FROM project_tasks pt JOIN projects p ON pt.project_id = p.id WHERE p.leader_id = ? GROUP BY pt.priority";
$stmt_tp = $conn->prepare($tp_q);
if ($stmt_tp) {
    $stmt_tp->bind_param("i", $tl_user_id);
    $stmt_tp->execute();
    $res_tp = $stmt_tp->get_result();
    while ($row = $res_tp->fetch_assoc()) {
        if ($row['priority'] == 'High') $high_tasks = $row['cnt'];
        if ($row['priority'] == 'Medium') $med_tasks = $row['cnt'];
        if ($row['priority'] == 'Low') $low_tasks = $row['cnt'];
    }
    $stmt_tp->close();
}

$sidebarPath = __DIR__ . '/../sidebars.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TL Dashboard - Modern UI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --primary-teal: #0d9488; --bg-gray: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-gray); margin: 0; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); padding: 25px 35px; transition: all 0.3s ease; }
        @media (max-width: 768px) { #mainContent { margin-left: 0; width: 100%; padding: 15px; } }
        .card-modern { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); overflow: hidden; }
        .btn-teal { background-color: var(--primary-teal); color: white; padding: 12px; border-radius: 10px; font-weight: 600; transition: 0.3s; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-teal:hover { background-color: #0f766e; }
        .progress-ring-circle { transition: stroke-dashoffset 0.35s; transform: rotate(-90deg); transform-origin: 50% 50%; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent">
        <?php 
        $headerPath = __DIR__ . '/../header.php'; 
        if (file_exists($headerPath)) include($headerPath); 
        ?>

        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-black text-slate-800">Dashboard</h1>
            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                <i data-lucide="calendar" class="w-4 h-4 text-teal-600"></i>
                <span class="text-sm font-bold text-slate-600"><?php echo date('D, d M Y'); ?></span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-8 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="card-modern p-6 text-center">
                       <?php include '../attendance_card.php'; ?>
                    </div>

                    <div class="card-modern p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="font-black text-slate-800 text-sm uppercase">Leave Details</h3>
                                <span class="text-[10px] bg-slate-100 px-2 py-1 rounded text-slate-500 font-bold"><?php echo date('Y'); ?></span>
                            </div>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center"><span class="text-sm font-semibold text-slate-500">On Time</span><span class="font-bold text-teal-600"><?php echo $present; ?></span></div>
                                <div class="flex justify-between items-center"><span class="text-sm font-semibold text-slate-500">Late</span><span class="font-bold text-red-500"><?php echo $late; ?></span></div>
                                <div class="flex justify-between items-center"><span class="text-sm font-semibold text-slate-500">Absent</span><span class="font-bold text-slate-800"><?php echo $absent; ?></span></div>
                            </div>
                        </div>
                        <div class="text-center mt-6">
                            <div class="text-3xl font-black text-slate-800"><?php echo $attendance_percentage; ?>%</div>
                            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">Attendance Rate</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="card-modern p-6">
                        <h3 class="font-black text-slate-800 text-sm uppercase mb-6">Notifications</h3>
                        <div class="space-y-4">
                            <div class="flex gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="w-10 h-10 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center font-black text-xs">N</div>
                                <div><p class="text-sm font-black text-slate-800 leading-tight">New Task Assigned</p><p class="text-[10px] text-slate-400 mt-1">Check your task management module.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-modern p-6">
                        <h3 class="font-black text-slate-800 text-sm uppercase mb-6">Leave Balance</h3>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-slate-50 p-4 rounded-2xl text-center border border-slate-100"><p class="text-[8px] text-slate-400 font-black">Total</p><p class="text-lg font-black">16</p></div>
                            <div class="bg-teal-50 p-4 rounded-2xl text-center border border-teal-100"><p class="text-[8px] text-teal-400 font-black">Taken</p><p class="text-lg font-black text-teal-700">2</p></div>
                            <div class="bg-emerald-50 p-4 rounded-2xl text-center border border-emerald-100"><p class="text-[8px] text-emerald-400 font-black">Left</p><p class="text-lg font-black text-emerald-700">14</p></div>
                        </div>
                        <button onclick="window.location.href='../employee/leave_request.php'" class="btn-teal py-3"><i data-lucide="plus" class="w-5 h-5"></i> APPLY NEW LEAVE</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="card-modern p-6">
                        <h3 class="font-black text-slate-800 text-sm uppercase mb-6">Project Progress</h3>
                        <div class="space-y-6">
                            <?php if(!empty($active_projects)): foreach($active_projects as $proj): ?>
                            <div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest truncate max-w-[150px]"><?php echo htmlspecialchars($proj['project_name']); ?></span>
                                    <span class="text-[10px] font-black text-teal-600"><?php echo $proj['progress']; ?>%</span>
                                </div>
                                <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-teal-500 rounded-full" style="width: <?php echo $proj['progress']; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                            <p class="text-center text-[10px] font-black text-slate-400 py-4 uppercase tracking-widest">No Projects Found</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-modern p-6">
                        <h3 class="font-black text-slate-800 text-sm uppercase mb-6">Task Priority</h3>
                        <div id="priorityDonutChart"></div>
                        <div class="flex justify-around mt-6">
                            <div class="text-center"><span class="block text-red-500 font-black text-lg"><?php echo $high_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">High</span></div>
                            <div class="text-center"><span class="block text-amber-500 font-black text-lg"><?php echo $med_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">Medium</span></div>
                            <div class="text-center"><span class="block text-emerald-500 font-black text-lg"><?php echo $low_tasks; ?></span><span class="text-[9px] font-black text-slate-400 uppercase">Low</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4">
                <div class="card-modern bg-white">
                    <div class="bg-[#0d9488] p-8 text-center text-white">
                        <div class="relative w-28 h-28 mx-auto mb-6">
                            <div class="w-full h-full rounded-full border-4 border-white/30 flex items-center justify-center bg-teal-800 text-3xl font-black shadow-xl uppercase">
                                <?php echo substr($tl_name, 0, 1) . (strpos($tl_name, ' ') ? substr($tl_name, strpos($tl_name, ' ') + 1, 1) : ''); ?>
                                <div class="absolute bottom-1 right-2 w-6 h-6 bg-emerald-400 border-4 border-[#0d9488] rounded-full"></div>
                            </div>
                        </div>
                        <h2 class="text-xl font-black leading-none mb-1"><?php echo htmlspecialchars($tl_name); ?></h2>
                        <p class="text-teal-100 text-xs font-bold opacity-80 mb-6 uppercase tracking-wider">Team Leader / Developer</p>
                        <div class="inline-block bg-white/20 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest">Verified Account</div>
                    </div>

                    <div class="p-8 space-y-8">
                        <div class="space-y-6">
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center shadow-sm"><i data-lucide="phone" class="w-6 h-6"></i></div>
                                <div><p class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Phone</p><p class="text-sm font-black text-slate-800"><?php echo $tl_phone; ?></p></div>
                            </div>
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center shadow-sm"><i data-lucide="mail" class="w-6 h-6"></i></div>
                                <div class="truncate"><p class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Email</p><p class="text-sm font-black text-slate-800 truncate"><?php echo $tl_email; ?></p></div>
                            </div>
                        </div>
                        <hr class="border-slate-100">
                        <div>
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6">Professional Info</h3>
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100"><p class="text-[8px] text-slate-400 font-black uppercase mb-1">Experience</p><p class="text-xs font-black text-slate-800 uppercase"><?php echo $tl_exp; ?></p></div>
                                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100"><p class="text-[8px] text-slate-400 font-black uppercase mb-1">Department</p><p class="text-xs font-black text-slate-800 uppercase"><?php echo $tl_dept; ?></p></div>
                            </div>
                            <div class="flex justify-between items-center p-4 bg-teal-50 rounded-2xl border border-teal-100 text-teal-800">
                                <div class="flex items-center gap-3 font-bold"><i data-lucide="calendar-days" class="w-5 h-5"></i><span class="text-xs uppercase">Joined</span></div>
                                <span class="text-xs font-black uppercase"><?php echo $tl_join; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        function updateClock() {
            const now = new Date();
            let h = now.getHours(), m = String(now.getMinutes()).padStart(2,'0'), s = String(now.getSeconds()).padStart(2,'0');
            let ampm = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
            document.getElementById('liveClock').textContent = `${String(h).padStart(2,'0')}:${m}:${s} ${ampm}`;
        }
        setInterval(updateClock, 1000); updateClock();

        let isRunning = <?php echo ($tl_attendance_record && !$tl_attendance_record['punch_out'] && !$tl_is_on_break) ? 'true' : 'false'; ?>;
        let totalSeconds = <?php echo $tl_total_seconds_worked; ?>;
        let currentSessionStart = new Date().getTime(); 

        function runTimer() {
            if (!isRunning) return;
            const diff = Math.floor((new Date().getTime() - currentSessionStart) / 1000);
            const activeTotal = totalSeconds + diff;
            const h = Math.floor(activeTotal/3600), m = Math.floor((activeTotal%3600)/60), s = activeTotal%60;
            document.getElementById('productionTimer').textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            
            const ring = document.getElementById('progressRingCircle');
            if(ring){
                const pct = Math.min(1, activeTotal / 32400); 
                ring.style.strokeDashoffset = 440 - (pct * 440);
            }
        }
        if (isRunning) setInterval(runTimer, 1000);

        function handleAjaxAction(actionType) {
            const fd = new FormData(); 
            fd.append('ajax_action', actionType);
            if (actionType === 'punch_in') {
                const shift = document.getElementById('shift_select');
                if(shift) fd.append('shift_type', shift.value);
            }
            fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.status === 'success') {
                    if(data.att_status === 'Late') alert("Note: Marked as Late.");
                    location.reload();
                } else alert(data.message);
            }).catch(() => location.reload());
        }

        new ApexCharts(document.querySelector("#priorityDonutChart"), {
            series: [<?php echo $high_tasks; ?>, <?php echo $med_tasks; ?>, <?php echo $low_tasks; ?>],
            labels: ['High', 'Medium', 'Low'],
            chart: { type: 'donut', height: 180 },
            colors: ['#ef4444', '#f59e0b', '#10b981'],
            legend: { show: false },
            plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'TOTAL' } } } } }
        }).render();
    </script>
</body>
</html>