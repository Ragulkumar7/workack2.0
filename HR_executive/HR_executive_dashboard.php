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

// -------------------------------------------------------------------------
// 2. INITIALIZE VARIABLES
// -------------------------------------------------------------------------
$employee_name     = "Employee";
$employee_role     = "Role";
$employee_phone    = "Not Set";
$employee_email    = "";
$joining_date      = "Not Set";
$profile_img       = "";
$total_hours_today = "00:00:00";
$display_punch_in  = "--:--";
$total_seconds_worked = 0;
$is_on_break       = false; 
$total_break_seconds = 0;

// -------------------------------------------------------------------------
// 3. ATTENDANCE LOGIC
// -------------------------------------------------------------------------
$today = date('Y-m-d');

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

    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total 
                FROM attendance_breaks 
                WHERE attendance_id = ? AND break_end IS NOT NULL";
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
// PROFILE & DEPARTMENT COUNTS (SAFE)
// -------------------------------------------------------------------------
$sql_profile = "SELECT ep.full_name, ep.profile_img, ep.designation, ep.emp_id_code, ep.department
                FROM employee_profiles ep 
                WHERE ep.user_id = ?";
$stmt_profile = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt_profile, "i", $current_user_id);
mysqli_stmt_execute($stmt_profile);
$profile_res = mysqli_stmt_get_result($stmt_profile);
$profile = mysqli_fetch_assoc($profile_res);

if (!$profile) {
    $profile = [
        'full_name'    => 'HR Executive',
        'profile_img'  => 'https://ui-avatars.com/api/?name=HR+Executive&background=random',
        'designation'  => 'Human Resources',
        'emp_id_code'  => 'N/A',
        'department'   => 'Human Resources'
    ];
}

function safe_count($conn, $query) {
    $result = mysqli_query($conn, $query);
    if ($result === false) {
        error_log("Query failed: " . mysqli_error($conn) . " | Query: " . $query);
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return (int)($row['cnt'] ?? 0);
}

$total_employees = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_profiles") ?: 1;

$dev_count     = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_profiles WHERE department = 'Development Team'");
$sales_count   = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_profiles WHERE department = 'Sales & Marketing'");
$accounts_count = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_profiles WHERE department = 'Finance & Accounts'");
$dm_count      = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_profiles WHERE department LIKE '%Marketing%'");

// Dashboard Stats
$open_pos_count   = safe_count($conn, "SELECT COUNT(*) as cnt FROM hiring_requests WHERE status != 'Fulfilled'");
$cand_count       = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates");
$meetings_today   = safe_count($conn, "SELECT COUNT(*) as cnt FROM meetings WHERE meeting_date = '$today'");
$offers_count     = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates WHERE status = 'Shortlisted'");

$stage_applied     = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates WHERE status = 'Parsed'");
$stage_shortlisted = safe_count($conn, "SELECT COUNT(*) as cnt FROM candidates WHERE status = 'Shortlisted'");
$stage_onboarded   = safe_count($conn, "SELECT COUNT(*) as cnt FROM employee_onboarding WHERE status='Completed'");

// Active Jobs & Schedules
$jobs_query = "SELECT * FROM jobs ORDER BY created_at DESC LIMIT 10";
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
        @media (min-width: 1024px) {
            main { margin-left: 100px; width: calc(100% - 100px); }
        }
    </style>
</head>
<body class="min-h-screen">

<main>
    <div class="p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($profile['full_name']) ?> Dashboard</h1>
            <p class="text-sm text-gray-400 mt-1">
                <?= htmlspecialchars($profile['emp_id_code']) ?> | 
                <?= htmlspecialchars($profile['designation']) ?> | 
                <?= htmlspecialchars($profile['department'] ?? 'Human Resources') ?>
            </p>
        </div>

        <!-- Top Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <?php
            $top_stats = [
                ['label' => 'Open Positions', 'val' => $open_pos_count, 'icon' => 'briefcase', 'color' => 'teal'],
                ['label' => 'Total Candidates', 'val' => $cand_count, 'icon' => 'users', 'color' => 'teal'],
                ['label' => 'Interviews Today', 'val' => $meetings_today, 'icon' => 'calendar', 'color' => 'slate'],
                ['label' => 'Shortlisted', 'val' => $offers_count, 'icon' => 'copy', 'color' => 'blue'],
            ];
            foreach($top_stats as $s): ?>
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center text-center">
                <div class="bg-<?= $s['color'] ?>-50 p-3 rounded-xl mb-3 text-<?= $s['color'] ?>-900">
                    <i data-lucide="<?= $s['icon'] ?>" class="w-6 h-6"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800"><?= $s['val'] ?></h2>
                <p class="text-gray-400 text-xs font-medium uppercase tracking-wider mt-1"><?= $s['label'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 items-stretch">
            
            <!-- Attendance Card with User Info -->
            <div class="card">
                <div class="card-body flex flex-col items-center">
                    <div class="text-center mb-4">
                        <img src="<?= htmlspecialchars($profile['profile_img']) ?>" 
                             class="w-16 h-16 rounded-full mx-auto mb-2 object-cover border-2 border-gray-200">
                        <h2 class="font-bold text-lg text-slate-800"><?= htmlspecialchars($profile['full_name']) ?></h2>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($profile['designation']) ?> • <?= htmlspecialchars($profile['department'] ?? 'Human Resources') ?></p>
                    </div>

                    <div class="text-center mb-6">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Today's Attendance</h3>
                        <p class="text-lg font-bold text-slate-800 mt-1"><?= date("h:i A, d M Y") ?></p>
                    </div>

                    <div class="relative w-40 h-40 mb-6">
                        <svg class="w-full h-full">
                            <circle cx="80" cy="80" r="70" stroke="#f1f5f9" stroke-width="12" fill="transparent"></circle>
                            <?php
                                $pct = min(1, $total_seconds_worked / 32400);
                                $dashoffset = 440 - ($pct * 440);
                                $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                            ?>
                            <circle cx="80" cy="80" r="70"
                                    stroke="<?= $ringColor ?>" stroke-width="12" fill="transparent"
                                    stroke-dasharray="440" stroke-dashoffset="<?= $dashoffset ?>"
                                    stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                        </svg>

                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-[10px] text-gray-400 font-bold uppercase">
                                <?= $is_on_break ? 'ON BREAK' : 'Total Hours' ?>
                            </p>
                            <p class="text-2xl font-bold text-slate-800" id="liveTimer"
                               data-running="<?= ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false' ?>"
                               data-total="<?= $total_seconds_worked ?>">
                                <?= $total_hours_today ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="w-full">
                        <?php if (!$attendance_record): ?>
                            <button type="submit" name="action" value="punch_in"
                                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-right-to-bracket"></i> Punch In
                            </button>
                        <?php elseif (!$attendance_record['punch_out']): ?>
                            <div class="grid grid-cols-2 gap-3 w-full">
                                <?php if ($is_on_break): ?>
                                    <button type="submit" name="action" value="break_end"
                                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-xl shadow transition">
                                        <i class="fa-solid fa-play"></i> Resume
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="break_start"
                                            class="bg-amber-400 hover:bg-amber-500 text-white font-bold py-3 rounded-xl shadow transition">
                                        <i class="fa-solid fa-mug-hot"></i> Break
                                    </button>
                                <?php endif; ?>

                                <button type="submit" name="action" value="punch_out"
                                        class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl shadow transition">
                                    <i class="fa-solid fa-right-from-bracket"></i> Out
                                </button>
                            </div>
                        <?php else: ?>
                            <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-3 rounded-xl cursor-not-allowed">
                                <i class="fa-solid fa-check-circle"></i> Shift Completed
                            </button>
                        <?php endif; ?>
                    </form>

                    <p class="text-xs text-gray-400 mt-4 flex items-center gap-1">
                        <i class="fa-solid fa-fingerprint text-orange-500"></i>
                        Punched In at: <span class="font-bold text-slate-600"><?= $display_punch_in ?></span>
                    </p>
                </div>
            </div>

            <!-- Department Overview -->
            <div class="lg:col-span-2 flex flex-col">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm h-full">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-xl text-slate-800">Department Overview</h3>
                        <button class="text-xs text-gray-400 flex items-center border rounded-lg px-2 py-1">
                            <i data-lucide="sliders-horizontal" class="w-3 h-3 mr-1"></i> Current
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php
                        $departments = [
                            ['label' => 'Development', 'count' => $dev_count, 'icon' => 'code', 'color' => 'blue'],
                            ['label' => 'Sales', 'count' => $sales_count, 'icon' => 'dollar-sign', 'color' => 'green'],
                            ['label' => 'Accounts', 'count' => $accounts_count, 'icon' => 'file-text', 'color' => 'yellow'],
                            ['label' => 'Digital Marketing', 'count' => $dm_count, 'icon' => 'megaphone', 'color' => 'purple'],
                        ];
                        foreach ($departments as $dept):
                            $pct = $total_employees > 0 ? round(($dept['count'] / $total_employees) * 100) : 0;
                        ?>
                        <div class="bg-gray-50 p-4 rounded-xl text-center border border-gray-200 hover:shadow-md transition">
                            <div class="bg-<?= $dept['color'] ?>-100 text-<?= $dept['color'] ?>-700 w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i data-lucide="<?= $dept['icon'] ?>" class="w-5 h-5"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-slate-800"><?= $dept['count'] ?></h4>
                            <p class="text-sm font-medium text-gray-600"><?= $dept['label'] ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?= $pct ?>% of total</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Job Openings + Right Column -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Active Job Openings -->
            <div class="lg:col-span-8 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-6 flex justify-between items-center border-b border-gray-50">
                    <h3 class="font-bold text-slate-800">Active Job Openings</h3>
                    <button class="text-xs font-semibold text-teal-900 hover:bg-teal-50 px-3 py-1 rounded-lg">View All</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-4">Job ID</th>
                                <th class="px-6 py-4">Job Title</th>
                                <th class="px-6 py-4 text-center">Location</th>
                                <th class="px-6 py-4 text-center">Applicants</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if(mysqli_num_rows($jobs_res) > 0): ?>
                                <?php while($j = mysqli_fetch_assoc($jobs_res)): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-medium text-slate-500">JOB-00<?= $j['id'] ?></td>
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-slate-800"><?= htmlspecialchars($j['title']) ?></p>
                                        <span class="text-[10px] bg-red-50 text-red-500 px-2 py-0.5 rounded-full font-bold uppercase tracking-tighter">Active</span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-500"><?= htmlspecialchars($j['loc']) ?></td>
                                    <td class="px-6 py-4 text-center font-bold text-slate-800"><?= rand(10, 500) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-gray-400">No active jobs found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Recruitment Overview + Schedules -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Recruitment Overview -->
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800">Recruitment Overview</h3>
                        <button class="text-xs text-gray-400 flex items-center border rounded-lg px-2 py-1">
                            <i data-lucide="download" class="w-3 h-3 mr-1"></i> Monthly
                        </button>
                    </div>
                    <div class="flex justify-around mb-4">
                        <div class="text-center">
                            <span class="text-xs text-gray-400 block">Offer Acceptance</span>
                            <span class="text-lg font-bold">74.4%</span>
                        </div>
                        <div class="text-center border-l border-gray-100 pl-8">
                            <span class="text-xs text-gray-400 block">Overall Hire Rate</span>
                            <span class="text-lg font-bold">2.7%</span>
                        </div>
                    </div>
                    <div class="relative flex justify-center">
                        <canvas id="gaugeChart" height="180"></canvas>
                        <div class="absolute bottom-4 text-center">
                            <p class="text-2xl font-bold text-slate-800"><?= $cand_count ?></p>
                            <p class="text-xs text-gray-400">Total Applications</p>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Schedules -->
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800">Upcoming Schedules</h3>
                        <span class="text-xs text-teal-900 font-bold cursor-pointer">Today</span>
                    </div>
                    <div class="space-y-6">
                        <?php if(mysqli_num_rows($schedule_res) > 0): ?>
                            <?php while($sch = mysqli_fetch_assoc($schedule_res)): ?>
                            <div class="flex items-center gap-4 group">
                                <div class="w-12 h-12 bg-gray-50 rounded-xl flex flex-col items-center justify-center border border-gray-100 group-hover:bg-teal-50 group-hover:border-teal-100 transition-colors">
                                    <span class="text-[10px] text-gray-400 font-bold leading-none"><?= date('M', strtotime($sch['meeting_date'])) ?></span>
                                    <span class="text-lg font-bold text-slate-800 leading-none"><?= date('d', strtotime($sch['meeting_date'])) ?></span>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($sch['title']) ?></h4>
                                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
                                        <i data-lucide="clock" class="w-3 h-3"></i> <?= date('h:i A', strtotime($sch['meeting_time'])) ?>
                                    </p>
                                </div>
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($sch['title']) ?>&background=random" 
                                     class="w-10 h-10 rounded-full grayscale hover:grayscale-0 transition-all cursor-pointer">
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 text-center">No schedules for today.</p>
                        <?php endif; ?>
                        <button class="w-full py-3 bg-teal-900 text-white rounded-xl font-bold text-sm shadow-lg shadow-teal-100 hover:bg-teal-950 transition-all mt-4">
                            View All Schedules
                        </button>
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
                    data: [65, 35],
                    backgroundColor: ['#134e4a', '#f1f5f9'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270,
                    borderRadius: 10,
                    cutout: '80%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
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

            const max = 32400;
            const pct = Math.min(current / max, 1);
            const circumference = 440;
            const offset = circumference - (pct * circumference);
            if (ring) ring.style.strokeDashoffset = offset;
        }

        if (isRunning) {
            setInterval(updateTimer, 1000);
            updateTimer();
        }
    });
</script>
</body>
</html>