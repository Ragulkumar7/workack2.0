<?php 
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

date_default_timezone_set('Asia/Kolkata');

require_once '../include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// -------------------------------------------------------------------------
// 2. FETCH USER PROFILE DATA
// -------------------------------------------------------------------------
$employee_name = "HR Executive";
$employee_role = "Human Resources";
$employee_phone = "Not Set";
$employee_email = "Not Set";
$emp_id_code = "N/A";
$department = "Human Resources";
$joining_date = "Not Set";
$experience_label = "Fresher";
$emergency_contacts = "[]";
$profile_img = "https://ui-avatars.com/api/?name=HR+Executive&background=random";
$user_system_role = "HR Executive"; // Fallback

$sql_profile = "SELECT ep.full_name, ep.profile_img, ep.designation, ep.emp_id_code, ep.department, 
                ep.phone, ep.email, ep.joining_date, ep.experience_label, ep.emergency_contacts,
                u.username, u.email as u_email, u.role
                FROM users u 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                WHERE u.id = ?";
$stmt_profile = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt_profile, "i", $current_user_id);
mysqli_stmt_execute($stmt_profile);
$profile_res = mysqli_stmt_get_result($stmt_profile);

if ($profile = mysqli_fetch_assoc($profile_res)) {
    $employee_name = $profile['full_name'] ?? $profile['username'] ?? 'HR Executive';
    $employee_role = $profile['designation'] ?? $profile['role'] ?? 'Human Resources';
    $user_system_role = $profile['role'] ?? 'HR Executive'; // For permission logic
    $employee_phone = $profile['phone'] ?? 'Not Set';
    $employee_email = $profile['email'] ?? $profile['u_email'] ?? 'Not Set';
    $emp_id_code = $profile['emp_id_code'] ?? 'N/A';
    $department = $profile['department'] ?? 'Human Resources';
    $joining_date = !empty($profile['joining_date']) ? date("d M Y", strtotime($profile['joining_date'])) : "Not Set";
    $experience_label = $profile['experience_label'] ?? 'Fresher';
    $emergency_contacts = $profile['emergency_contacts'] ?? '[]';

    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($employee_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($profile['profile_img']) && $profile['profile_img'] !== 'default_user.png') {
        if (str_starts_with($profile['profile_img'], 'http')) {
            $profile_img = $profile['profile_img'];
        } else {
            $profile_img = '../assets/profiles/' . $profile['profile_img'];
        }
    }
}

// -------------------------------------------------------------------------
// 3. ATTENDANCE LOGIC
// -------------------------------------------------------------------------
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

$is_on_break = false;
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
    $total_break_seconds = (int)($sum_res['total'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $now_db = date('Y-m-d H:i:s');

    if ($_POST['action'] == 'punch_in' && !$attendance_record) {
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "iss", $current_user_id, $now_db, $today);
        mysqli_stmt_execute($ins_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
    elseif ($_POST['action'] == 'break_start' && $attendance_record && !$is_on_break) {
        $ins_bk = "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $ins_bk);
        mysqli_stmt_bind_param($stmt, "is", $attendance_record['id'], $now_db);
        mysqli_stmt_execute($stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
    elseif ($_POST['action'] == 'break_end' && $attendance_record && $is_on_break) {
        $upd_bk = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
        $stmt = mysqli_prepare($conn, $upd_bk);
        mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
        mysqli_stmt_execute($stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
    elseif ($_POST['action'] == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        if ($is_on_break) {
            $upd_bk = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
            $stmt = mysqli_prepare($conn, $upd_bk);
            mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
            mysqli_stmt_execute($stmt);
            $total_break_seconds += (strtotime($now_db) - $break_start_ts);
        }

        $start_ts = strtotime($attendance_record['punch_in']);
        $end_ts   = strtotime($now_db);
        $total_duration = $end_ts - $start_ts;
        $production_seconds = max(0, $total_duration - $total_break_seconds);
        $production_hours = $production_seconds / 3600;

        $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
        $upd_stmt = mysqli_prepare($conn, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, "sdi", $now_db, $production_hours, $attendance_record['id']);
        mysqli_stmt_execute($upd_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
}

$display_punch_in   = "--:--";
$total_hours_today  = "00:00:00";
$total_seconds_worked = 0;

if ($attendance_record) {
    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    $start_ts = strtotime($attendance_record['punch_in']);

    if ($is_on_break) {
        $now_ts = $break_start_ts;
    } elseif ($attendance_record['punch_out']) {
        $now_ts = strtotime($attendance_record['punch_out']);
    } else {
        $now_ts = time();
    }

    $total_seconds_worked = max(0, $now_ts - $start_ts - $total_break_seconds);

    $h = floor($total_seconds_worked / 3600);
    $m = floor(($total_seconds_worked % 3600) / 60);
    $s = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// -------------------------------------------------------------------------
// 4. DYNAMIC DEPARTMENT COUNTS 
// -------------------------------------------------------------------------
$total_employees_query = "SELECT COUNT(*) as cnt FROM employee_profiles";
$res = mysqli_query($conn, $total_employees_query);
$total_employees = mysqli_fetch_assoc($res)['cnt'] ?? 1;

$dept_counts_query = "
    SELECT 
        department, 
        COUNT(id) as count 
    FROM employee_profiles 
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY count DESC
";
$dept_counts_result = mysqli_query($conn, $dept_counts_query);

$departments_list = [];
$icons = ['code', 'dollar-sign', 'file-text', 'server', 'users', 'briefcase', 'monitor'];
$colors = ['blue', 'green', 'yellow', 'purple', 'indigo', 'orange', 'teal'];

$idx = 0;
while ($row = mysqli_fetch_assoc($dept_counts_result)) {
    $dept_name = trim($row['department']);
    if($dept_name == 'Development Team') $dept_name = 'Development';
    
    $departments_list[] = [
        'label' => $dept_name,
        'count' => $row['count'],
        'icon' => $icons[$idx % count($icons)],
        'color' => $colors[$idx % count($colors)]
    ];
    $idx++;
}

// -------------------------------------------------------------------------
// 5. OTHER DASHBOARD DATA
// -------------------------------------------------------------------------
function safe_count($conn, $query) {
    $result = mysqli_query($conn, $query);
    if ($result === false) { return 0; }
    $row = mysqli_fetch_assoc($result);
    return (int)($row['cnt'] ?? 0);
}

$cand_count = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates");

// DYNAMIC JOB REQUESTS (hiring_requests instead of static jobs table)
$jobs_cond = "";
if ($user_system_role === 'HR Executive') {
    $jobs_cond = "WHERE hr.status IN ('Approved', 'In Progress', 'Fulfilled')";
}

$jobs_query = "SELECT hr.*, u.name as requested_by 
               FROM hiring_requests hr 
               LEFT JOIN users u ON hr.manager_id = u.id 
               $jobs_cond 
               ORDER BY hr.created_at DESC LIMIT 5";
$jobs_res = mysqli_query($conn, $jobs_query);

$schedule_query = "SELECT * FROM meetings WHERE meeting_date >= '$today' ORDER BY meeting_date ASC LIMIT 5";
$schedule_res = mysqli_query($conn, $schedule_query);

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Executive Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f3f6f9; }
        canvas { max-width: 100%; }
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .card-body { padding: 1.5rem; }
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        /* Custom scrollbar for inner containers */
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f8fafc; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        @media (min-width: 1024px) {
            main { margin-left: 100px; width: calc(100% - 100px); }
        }
    </style>
</head>
<body class="min-h-screen">

<main>
    <div class="p-8 max-w-[1600px] mx-auto">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800"><?= htmlspecialchars($employee_name) ?> Dashboard</h1>
                <p class="text-sm text-gray-500 mt-1">
                    <?= htmlspecialchars($emp_id_code) ?> | 
                    <?= htmlspecialchars($employee_role) ?> | 
                    <?= htmlspecialchars($department) ?>
                </p>
            </div>
            <div class="flex gap-3">
                <div class="bg-white border border-gray-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="far fa-calendar-alt text-teal-600"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <div class="col-span-1 lg:col-span-9 flex flex-col gap-6">
                
                <div class="grid grid-cols-1 lg:grid-cols-9 gap-6">
                    
                    <div class="lg:col-span-3">
                        <div class="card h-fit">
                            <div class="card-body flex flex-col items-center justify-center p-4">
                                <div class="text-center mb-3">
                                    <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Today's Attendance</h3>
                                    <p class="text-sm font-bold text-slate-800 mt-1"><?= date("h:i A, d M") ?></p>
                                </div>

                                <div class="relative w-28 h-28 mb-4">
                                    <svg class="w-full h-full" viewBox="0 0 128 128">
                                        <circle cx="64" cy="64" r="56" stroke="#f1f5f9" stroke-width="10" fill="transparent"></circle>
                                        <?php
                                            $pct = min(1, $total_seconds_worked / 32400);
                                            $dashoffset = 351.85 - ($pct * 351.85); 
                                            $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                                        ?>
                                        <circle cx="64" cy="64" r="56"
                                                stroke="<?= $ringColor ?>" stroke-width="10" fill="transparent"
                                                stroke-dasharray="351.85" stroke-dashoffset="<?= ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset) ?>"
                                                stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                                    </svg>

                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase">
                                            <?= $is_on_break ? 'ON BREAK' : 'Total Hrs' ?>
                                        </p>
                                        <p class="text-lg font-bold text-slate-800 mt-0.5" id="liveTimer"
                                           data-running="<?= ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false' ?>"
                                           data-total="<?= $total_seconds_worked ?>">
                                            <?= $total_hours_today ?>
                                        </p>
                                    </div>
                                </div>

                                <form method="POST" class="w-full">
                                    <?php if (!$attendance_record): ?>
                                        <button type="submit" name="action" value="punch_in"
                                                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 rounded-lg shadow transition flex items-center justify-center gap-2 text-sm">
                                            <i class="fa-solid fa-right-to-bracket"></i> Punch In
                                        </button>
                                    <?php elseif (!$attendance_record['punch_out']): ?>
                                        <div class="grid grid-cols-2 gap-2 w-full">
                                            <?php if ($is_on_break): ?>
                                                <button type="submit" name="action" value="break_end"
                                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 rounded-lg shadow transition text-xs">
                                                    <i class="fa-solid fa-play"></i> Resume
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="break_start"
                                                        class="bg-amber-400 hover:bg-amber-500 text-white font-bold py-2 rounded-lg shadow transition text-xs">
                                                    <i class="fa-solid fa-mug-hot"></i> Break
                                                </button>
                                            <?php endif; ?>

                                            <button type="submit" name="action" value="punch_out"
                                                    class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 rounded-lg shadow transition text-xs">
                                                <i class="fa-solid fa-right-from-bracket"></i> Out
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-2 rounded-lg cursor-not-allowed text-sm border border-gray-200">
                                            <i class="fa-solid fa-check-circle text-gray-400"></i> Shift Done
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <p class="text-[10px] text-gray-400 mt-3 flex items-center gap-1">
                                    <i class="fa-solid fa-fingerprint text-orange-500"></i>
                                    In: <span class="font-bold text-slate-600"><?= $display_punch_in ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-6">
                        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm h-fit">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-lg text-slate-800">Department Overview</h3>
                                <span class="text-[10px] text-teal-700 bg-teal-50 px-2 py-1 rounded-lg font-bold border border-teal-100">All Active Teams</span>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <?php foreach ($departments_list as $dept):
                                    $pct = $total_employees > 0 ? round(($dept['count'] / $total_employees) * 100) : 0;
                                ?>
                                <div class="bg-gray-50 p-3 rounded-xl text-center border border-gray-200 hover:border-teal-300 transition-colors">
                                    <div class="bg-<?= $dept['color'] ?>-100 text-<?= $dept['color'] ?>-700 w-8 h-8 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <i data-lucide="<?= $dept['icon'] ?>" class="w-4 h-4"></i>
                                    </div>
                                    <h4 class="text-xl font-bold text-slate-800"><?= $dept['count'] ?></h4>
                                    <p class="text-xs font-medium text-gray-600 truncate px-1 mt-0.5" title="<?= $dept['label'] ?>"><?= $dept['label'] ?></p>
                                    <p class="text-[10px] text-gray-400 mt-0.5"><?= $pct ?>% of total</p>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($departments_list)): ?>
                                    <div class="col-span-3 text-center text-gray-400 py-4 text-sm">No department data found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="grid grid-cols-1 lg:grid-cols-9 gap-6">
                    
                    <div class="lg:col-span-5 bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col h-[380px]">
                        <div class="p-5 flex justify-between items-center border-b border-gray-50 shrink-0">
                            <h3 class="font-bold text-slate-800">Active Job Openings</h3>
                            <a href="jobs.php" class="text-[10px] font-bold text-teal-700 hover:bg-teal-50 px-2 py-1 rounded border border-gray-200 transition inline-block">View All</a>
                        </div>
                        
                        <div class="overflow-y-auto flex-grow custom-scroll p-4 space-y-3">
                            <?php if($jobs_res && mysqli_num_rows($jobs_res) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($jobs_res)): 
                                    // Dynamic Department Icon
                                    $j_dept = strtolower($row['department']);
                                    $j_icon = 'fa-briefcase'; $j_icon_bg = 'bg-gray-100 text-gray-600';
                                    if(strpos($j_dept, 'dev') !== false || strpos($j_dept, 'eng') !== false) { $j_icon = 'fa-code'; $j_icon_bg = 'bg-blue-100 text-blue-600'; }
                                    elseif(strpos($j_dept, 'sale') !== false || strpos($j_dept, 'market') !== false) { $j_icon = 'fa-chart-line'; $j_icon_bg = 'bg-green-100 text-green-600'; }
                                    elseif(strpos($j_dept, 'hr') !== false || strpos($j_dept, 'human') !== false) { $j_icon = 'fa-users'; $j_icon_bg = 'bg-purple-100 text-purple-600'; }
                                    elseif(strpos($j_dept, 'acc') !== false || strpos($j_dept, 'fin') !== false) { $j_icon = 'fa-file-invoice-dollar'; $j_icon_bg = 'bg-yellow-100 text-yellow-600'; }

                                    // Status Badge
                                    $j_status_bg = 'bg-gray-100 text-gray-600';
                                    if ($row['status'] == 'Approved') $j_status_bg = 'bg-teal-100 text-teal-700';
                                    if ($row['status'] == 'In Progress') $j_status_bg = 'bg-blue-100 text-blue-700';
                                ?>
                                <div class="border border-gray-100 rounded-xl p-3.5 hover:shadow-md transition bg-white group">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-10 h-10 <?= $j_icon_bg ?> rounded-lg flex items-center justify-center text-lg shadow-sm shrink-0">
                                            <i class="fa-solid <?= $j_icon ?>"></i>
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <h3 class="font-bold text-gray-800 text-sm truncate" title="<?= htmlspecialchars($row['job_title']) ?>"><?= htmlspecialchars($row['job_title']) ?></h3>
                                            <p class="text-[11px] text-gray-500 truncate mt-0.5"><?= htmlspecialchars($row['department']) ?></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded <?= $j_status_bg ?> block text-center">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                            <?php if($row['priority'] == 'High'): ?>
                                                <span class="text-[9px] font-bold text-red-500 mt-1 flex items-center justify-end gap-1"><i class="fa-solid fa-bolt"></i> Urgent</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 gap-2 text-[10px] text-gray-600 bg-gray-50 p-2 rounded-lg mt-2">
                                        <div class="flex items-center gap-1.5 truncate" title="Created On">
                                            <i class="fa-regular fa-calendar text-gray-400"></i>
                                            <span class="truncate font-medium"><?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : 'N/A' ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 truncate justify-center" title="Vacancies">
                                            <i class="fa-solid fa-users text-gray-400"></i>
                                            <span class="font-medium"><?= $row['vacancy_count'] ?> Pos</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 truncate justify-end" title="Experience">
                                            <i class="fa-solid fa-briefcase text-gray-400"></i>
                                            <span class="truncate font-medium"><?= htmlspecialchars($row['experience_required']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-10 flex flex-col items-center justify-center h-full">
                                    <i class="fa-solid fa-folder-open text-gray-200 text-4xl mb-3"></i>
                                    <p class="text-sm font-bold text-gray-400">No active job requests found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="lg:col-span-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col h-[380px]">
                        <div class="flex justify-between items-center mb-4 shrink-0">
                            <h3 class="font-bold text-slate-800">Recruitment Metrics</h3>
                            <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-1 rounded font-bold border border-gray-200">This Month</span>
                        </div>
                        
                        <div class="flex justify-around bg-slate-50 rounded-xl p-3 mb-4 border border-slate-100 shrink-0">
                            <div class="text-center">
                                <span class="text-[10px] text-gray-500 font-bold uppercase block mb-1">Offer Acceptance</span>
                                <span class="text-lg font-black text-slate-800">74.4%</span>
                            </div>
                            <div class="w-px bg-gray-200 mx-2"></div>
                            <div class="text-center">
                                <span class="text-[10px] text-gray-500 font-bold uppercase block mb-1">Overall Hire Rate</span>
                                <span class="text-lg font-black text-slate-800">12.7%</span>
                            </div>
                        </div>
                        
                        <div class="relative flex justify-center flex-grow items-center">
                            <div class="w-48 h-48 relative">
                                <canvas id="gaugeChart"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pt-8">
                                    <p class="text-3xl font-black text-slate-800"><?= $cand_count ?></p>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Applications</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="col-span-1 xl:col-span-3 flex flex-col gap-6">
                
                <div class="card overflow-hidden h-fit border-0 shadow-sm ring-1 ring-gray-200">
                    <div class="bg-gradient-to-b from-teal-700 to-teal-800 p-6 flex flex-col items-center text-center relative">
                        <div class="absolute top-4 right-4 flex gap-2">
                            <button class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition backdrop-blur-sm" title="Edit Profile">
                                <i class="fas fa-pencil-alt text-xs"></i>
                            </button>
                        </div>
                        <div class="relative mb-3 mt-2">
                            <img src="<?php echo $profile_img; ?>" class="w-20 h-20 rounded-full border-4 border-white shadow-lg object-cover bg-white">
                            <div class="absolute bottom-1 right-1 w-5 h-5 bg-emerald-400 border-2 border-white rounded-full"></div>
                        </div>
                        <h2 class="text-white font-bold text-lg"><?php echo htmlspecialchars($employee_name); ?></h2>
                        <p class="text-teal-100 text-xs font-medium mt-0.5 mb-3"><?php echo htmlspecialchars($employee_role); ?></p>
                        <span class="bg-white/10 border border-white/20 text-white text-[10px] px-3 py-1 rounded-full font-bold tracking-wider uppercase">Verified User</span>
                    </div>
                    
                    <div class="p-5 space-y-4 bg-white">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                <i class="fa-solid fa-phone text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Phone Number</p>
                                <p class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($employee_phone); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 shrink-0">
                                <i class="fa-solid fa-envelope text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Email Address</p>
                                <p class="text-xs font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($employee_email); ?>">
                                    <?php echo htmlspecialchars($employee_email); ?>
                                </p>
                            </div>
                        </div>
                        
                        <hr class="border-gray-100">
                        
                        <div class="bg-emerald-50 p-3 rounded-xl flex justify-between items-center border border-emerald-100/50">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check text-emerald-600"></i>
                                <span class="text-xs font-bold text-emerald-900">Date Joined</span>
                            </div>
                            <span class="text-xs font-bold text-slate-700"><?php echo $joining_date; ?></span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <h4 class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-3">Professional Data</h4>
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                    <p class="text-[8px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">Experience</p>
                                    <p class="text-[11px] font-bold text-slate-700"><?php echo htmlspecialchars($experience_label); ?></p>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 overflow-hidden">
                                    <p class="text-[8px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">Department</p>
                                    <p class="text-[11px] font-bold text-slate-700 truncate" title="<?= htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></p>
                                </div>
                            </div>
                            
                            <?php
                            $emergency = json_decode($emergency_contacts, true);
                            if (!empty($emergency) && is_array($emergency)): 
                                $primary = $emergency[0]; ?>
                                <div class="p-3 bg-red-50/50 rounded-xl border border-red-100 flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <i class="fa-solid fa-heart-pulse text-red-500 text-[10px]"></i>
                                            <span class="text-[9px] font-bold text-red-700 uppercase tracking-wider">Emergency Contact</span>
                                        </div>
                                        <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($primary['name'] ?? 'Not Set'); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <a href="tel:<?php echo htmlspecialchars($primary['phone'] ?? ''); ?>" class="w-8 h-8 rounded-full bg-white border border-red-100 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition shadow-sm">
                                            <i class="fas fa-phone-alt text-[10px]"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm h-fit">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-sm text-slate-800 uppercase tracking-wider">Upcoming Schedules</h3>
                        <span class="text-[10px] text-teal-700 bg-teal-50 px-2 py-1 rounded font-bold border border-teal-100">Today</span>
                    </div>
                    <div class="space-y-4">
                        <?php if(mysqli_num_rows($schedule_res) > 0): ?>
                            <?php while($sch = mysqli_fetch_assoc($schedule_res)): ?>
                            <div class="flex items-center gap-3 group">
                                <div class="w-10 h-10 bg-slate-50 rounded-lg flex flex-col items-center justify-center border border-gray-100 group-hover:bg-teal-50 group-hover:border-teal-100 transition-colors shrink-0">
                                    <span class="text-[8px] text-gray-400 font-bold uppercase leading-tight"><?= date('M', strtotime($sch['meeting_date'])) ?></span>
                                    <span class="text-sm font-black text-slate-800 leading-tight"><?= date('d', strtotime($sch['meeting_date'])) ?></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($sch['title']) ?></h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5 flex items-center gap-1 font-medium">
                                        <i data-lucide="clock" class="w-2.5 h-2.5"></i> <?= date('h:i A', strtotime($sch['meeting_time'])) ?>
                                    </p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i data-lucide="calendar-x" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <p class="text-xs text-gray-500 font-medium">No schedules for today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();

    // GAUGE CHART
    const ctx = document.getElementById('gaugeChart')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [74.4, 25.6],
                    backgroundColor: ['#0f766e', '#f1f5f9'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270,
                    borderRadius: 8,
                    cutout: '80%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                layout: { padding: 0 }
            }
        });
    }

    // TIMER SCRIPT
    document.addEventListener('DOMContentLoaded', () => {
        const timerEl = document.getElementById('liveTimer');
        const ring    = document.getElementById('progressRing');

        if (!timerEl) return;

        const isRunning   = timerEl.dataset.running === 'true';
        let totalSeconds  = parseInt(timerEl.dataset.total) || 0;
        const pageLoadTime = new Date().getTime();

        function updateTimer() {
            if (!isRunning) return;

            const now = new Date().getTime();
            const diff = Math.floor((now - pageLoadTime) / 1000);
            const current = totalSeconds + diff;

            const h = Math.floor(current / 3600);
            const m = Math.floor((current % 3600) / 60);
            const s = current % 60;

            timerEl.innerText = `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;

            // Circumference matches CSS property
            const max = 32400; // 9 hours
            const pct = Math.min(current / max, 1);
            const circumference = 351.85; 
            const offset = circumference - (pct * circumference);
            if (ring) ring.style.strokeDashoffset = offset;
        }

        if (isRunning) {
            setInterval(updateTimer, 1000);
            updateTimer();
        }
    });

    // AJAX ACTION HANDLERS
    function punchAction(action) {
        let btnId = '';
        if(action === 'punch_in') btnId = 'btnPunchIn';
        else if(action === 'punch_out') btnId = 'btnPunchOut';
        else if(action === 'take_break') btnId = 'btnBreak';
        else if(action === 'end_break') btnId = 'btnEndBreak';
        
        const btn = document.getElementById(btnId);
        if(btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>';
        }

        const formData = new FormData();
        formData.append('action', action);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                window.location.reload(); 
            } else {
                alert('Error: ' + data.message);
                if(btn) window.location.reload(); 
            }
        })
        .catch(error => {
            alert('Network Error occurred.');
            if(btn) window.location.reload();
        });
    }
</script>
</body>
</html>