<?php 
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
 $path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. FIX TIMEZONE (Crucial for correct punch times)
date_default_timezone_set('Asia/Kolkata');

// Database Connection
require_once '../include/db_connect.php'; 

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

 $current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 15; 

// -------------------------------------------------------------------------
// 2. INITIALIZE ALL VARIABLES (Prevents "Undefined Variable" Errors)
// -------------------------------------------------------------------------
 $employee_name = "Employee";
 $employee_role = "Role";
 $employee_phone = "Not Set";
 $employee_email = "";
 $joining_date = "Not Set";
 $profile_img = "";
 $attendance_record = null;
 $total_hours_today = "00:00:00";
 $display_punch_in = "--:--";
 $total_seconds_worked = 0;
 $is_on_break = false; // Tracks if currently on break
 $total_break_seconds = 0; // Tracks sum of all completed breaks today

// Stats Counters
 $stats_ontime = 0;
 $stats_late = 0;
 $stats_wfh = 0;
 $stats_absent = 0;
 $stats_sick = 0;

// Leave Balance Defaults
 $leaves_total = 16;
 $leaves_taken = 0;
 $leaves_remaining = 16;

// -------------------------------------------------------------------------
// 3. AJAX HANDLERS (Punch In / Punch Out / Break Logic)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $now_db = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $action = $_POST['action'];

    // --- FETCH TODAY'S ATTENDANCE RECORD ---
    $check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
    mysqli_stmt_execute($check_stmt);
    $attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    // --- ACTION: PUNCH IN ---
    if ($action == 'punch_in' && !$attendance_record) {
        $status = (date('H:i') > '09:30') ? 'Late' : 'On Time'; // Example logic
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, ?)";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "isss", $current_user_id, $now_db, $today, $status);
        mysqli_stmt_execute($ins_stmt);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- ACTION: START BREAK ---
    if ($action == 'break_start' && $attendance_record && !$is_on_break) {
        // Insert into breaks table
        $ins_bk = "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $ins_bk);
        mysqli_stmt_bind_param($stmt, "is", $attendance_record['id'], $now_db);
        mysqli_stmt_execute($stmt);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- ACTION: END BREAK ---
    if ($action == 'break_end' && $attendance_record && $is_on_break) {
        // Update the break record
        $upd_bk = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
        $stmt = mysqli_prepare($conn, $upd_bk);
        mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
        mysqli_stmt_execute($stmt);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- ACTION: PUNCH OUT ---
    if ($action == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        
        // 1. Close active break if any
        if ($is_on_break) {
            $close_bk_sql = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
            $stmt_close = mysqli_prepare($conn, $close_bk_sql);
            mysqli_stmt_bind_param($stmt_close, "si", $now_db, $attendance_record['id']);
            mysqli_stmt_execute($stmt_close);
            
            // Calculate duration of this last break
            $break_start_ts = strtotime($attendance_record['active_break_start'] ?? $now_db); // Fallback
            $last_break_seconds = strtotime($now_db) - $break_start_ts;
            $total_break_seconds += $last_break_seconds;
        }

        // 2. Calculate Total Duration (Punch Out - Punch In)
        $start_ts = strtotime($attendance_record['punch_in']);
        $end_ts = strtotime($now_db);
        $total_duration = $end_ts - $start_ts;

        // 3. Calculate Production Hours (Total Duration - Total Breaks)
        $production_seconds = $total_duration - $total_break_seconds;
        if($production_seconds < 0) $production_seconds = 0;
        
        // Convert to decimal hours (e.g., 5400 seconds = 1.5 hours)
        $hours_decimal = $production_seconds / 3600; 

        // 4. Update Attendance Table
        $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
        $upd_stmt = mysqli_prepare($conn, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, "sdi", $now_db, $hours_decimal, $attendance_record['id']);
        mysqli_stmt_execute($upd_stmt);
        echo json_encode(['status' => 'success', 'hours_worked' => round($hours_decimal, 2)]);
        exit;
    }
    
    exit; 
}

// -------------------------------------------------------------------------
// 4. DATA FETCHING
// -------------------------------------------------------------------------
 $date_today = date('Y-m-d');

// A. Fetch User Profile
 $sql_profile = "SELECT ep.full_name, ep.profile_img, ep.designation, ep.emp_id_code 
                 FROM employee_profiles ep 
                 WHERE ep.user_id = '$current_user_id'";
 $profile_res = mysqli_query($conn, $sql_profile);
 $profile = mysqli_fetch_assoc($profile_res);
if(!$profile) { 
    $profile = ['full_name' => 'HR Executive', 'profile_img' => 'https://ui-avatars.com/api/?name=HR+Executive&background=random', 'designation' => 'Human Resources']; 
}

// B. Attendance Logic (Punch In/Out/Break)
 $att_query = "SELECT * FROM attendance WHERE user_id = '$current_user_id' AND date = '$date_today'";
 $att_res = mysqli_query($conn, $att_query);
 $attendance = mysqli_fetch_assoc($att_res);

// --- NEW: CHECK FOR ACTIVE BREAK ---
 $active_break_time = 0;
if ($attendance) {
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $bk_stmt = mysqli_prepare($conn, $bk_sql);
    mysqli_stmt_bind_param($bk_stmt, "i", $attendance['id']);
    mysqli_stmt_execute($bk_stmt);
    if ($bk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt))) {
        $is_on_break = true;
        $active_break_time = strtotime($bk_row['break_start']);
    }

    // Sum up all completed breaks
    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NOT NULL";
    $sum_stmt = mysqli_prepare($conn, $sum_sql);
    mysqli_stmt_bind_param($sum_stmt, "i", $attendance['id']);
    mysqli_stmt_execute($sum_stmt);
    $sum_res = mysqli_fetch_assoc(mysqli_stmt_get_result($sum_stmt));
    $total_break_seconds = $sum_res['total'] ?? 0;
}

// Calculate Display Time
if ($attendance) {
    $display_punch_in = date('h:i A', strtotime($attendance['punch_in']));
    
    $start_ts = strtotime($attendance['punch_in']);
    
    if ($is_on_break) {
        // Timer paused at break start
        $now_ts = $active_break_time;
    } elseif ($attendance['punch_out']) {
        // Timer stopped at punch out
        $now_ts = strtotime($attendance['punch_out']);
    } else {
        // Timer running
        $now_ts = time();
    }
    
    $total_seconds_worked = ($now_ts - $start_ts) - $total_break_seconds;
    if ($total_seconds_worked < 0) $total_seconds_worked = 0;

    $hours = floor($total_seconds_worked / 3600);
    $mins = floor(($total_seconds_worked % 3600) / 60);
    $secs = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
}

// 3. Dashboard Stats
 $open_pos_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM hiring_requests WHERE status != 'Fulfilled'"))['cnt'];
 $cand_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM candidates"))['cnt'];
 $meetings_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM meetings WHERE meeting_date = '$date_today'"))['cnt'];
 $offers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM candidates WHERE status = 'Shortlisted'"))['cnt'];

// 4. Stage Performance Data
 $stage_applied = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM candidates WHERE status = 'Parsed'"))['cnt'];
 $stage_shortlisted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM candidates WHERE status = 'Shortlisted'"))['cnt'];
 $stage_interviewed = $stage_shortlisted; // Simplified for demo
 $stage_hired = 0; // Simplified for demo

// 5. Active Jobs
 $jobs_query = "SELECT * FROM jobs ORDER BY created_at DESC LIMIT 10";
 $jobs_res = mysqli_query($conn, $jobs_query);

// 6. Upcoming Schedules (Meetings)
 $schedule_query = "SELECT * FROM meetings WHERE meeting_date >= '$date_today' ORDER BY meeting_date ASC LIMIT 5";
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f3f6f9; }
        canvas { max-width: 100%; }

        /* Punch Card Specific Styles */
        .profile-ring-container {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#134e4a 0% 70%, #3b82f6 70% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
        }
        .profile-ring-inner {
            width: 110px;
            height: 110px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        .production-badge {
            background-color: #134e4a;
            color: white;
            display: inline-block;
            padding: 6px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(19, 78, 74, 0.2);
        }
        .btn-punch-out { background-color: #111827; color: white; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; transition: 0.3s; margin-bottom: 8px; }
        .btn-punch-out:hover { background-color: #1f2937; }
        .btn-break { background-color: white; color: #134e4a; border: 1px solid #134e4a; width: 100%; padding: 10px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        .btn-break:hover { background-color: #f0fdfa; }
        .btn-punch-in { background-color: #134e4a; color: white; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; transition: 0.3s; }
        .btn-punch-in:hover { background-color: #0f3d3a; }

        /* FIXED OVERLAP HERE */
        @media (min-width: 1024px) {
            main { 
                margin-left: 100px; 
                width: calc(100% - 100px); 
            }
        }
    </style>
</head>
<body class="min-h-screen">

    <main>
        <div class="p-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-800">HR Executive</h1>
                <p class="text-sm text-gray-400 mt-1">Intelligence / <span class="text-slate-600">Overview</span></p>
            </div>

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
                <!-- Left: Attendance Profile -->
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm text-center flex flex-col justify-center">
                    <div class="mb-4">
                        <p class="text-gray-500 font-medium text-xs">Good Morning, <?= explode(' ', $profile['full_name'])[0] ?></p>
                        <h2 class="text-2xl font-bold text-gray-800 mt-1" id="liveClock">00:00 AM</h2>
                        <p class="text-[10px] text-gray-400 font-medium mt-1" id="liveDate">-- --- ----</p>
                    </div>

                    <div class="profile-ring-container">
                        <div class="profile-ring-inner">
                            <!-- Using DB Profile Image -->
                            <img src="<?= !empty($profile['profile_img']) ? $profile['profile_img'] : 'https://ui-avatars.com/api/?name='.$profile['full_name'].'&background=random' ?>" alt="Profile" class="profile-img">
                        </div>
                    </div>

                    <div class="production-badge">
                        Production : <span id="productionTimer"><?= $attendance['production_hours'] ?? 0 ?></span> hrs
                    </div>

                    <div class="flex items-center justify-center gap-2 text-gray-600 mb-6" id="statusDisplay">
                        <!-- Status Logic: Check if punched in/out/break -->
                        <?php if($attendance): ?>
                            <?php if($is_on_break): ?>
                                <i data-lucide="coffee" class="w-4 h-4 text-orange-500"></i>
                                <span class="font-medium text-xs">On Break</span>
                            <?php elseif(!$attendance['punch_out']): ?>
                                <i data-lucide="clock" class="w-4 h-4 text-emerald-500"></i>
                                <span class="font-medium text-xs">Punched In at <?= date('h:i A', strtotime($attendance['punch_in'])) ?></span>
                            <?php else: ?>
                                <i data-lucide="fingerprint" class="w-4 h-4 text-gray-400"></i>
                                <span class="font-medium text-xs">Punched Out</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <i data-lucide="fingerprint" class="w-4 h-4 text-gray-400"></i>
                            <span class="font-medium text-xs">Not Punched In</span>
                        <?php endif; ?>
                    </div>

                    <div id="actionButtons"></div>
                </div>

                <!-- Middle: Stage Performance -->
                <div class="lg:col-span-2 flex flex-col">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-xl text-slate-800">Stage Performance</h3>
                            <button class="text-xs text-gray-400 flex items-center border rounded-lg px-2 py-1">
                                <i data-lucide="sliders-horizontal" class="w-3 h-3 mr-1"></i> Last 30 Days
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 h-[240px]">
                            <?php
                            $total_pipeline = max(1, $stage_applied);
                            $stages = [
                                ['label' => 'Applied', 'num' => $stage_applied, 'rate' => '100%', 'icon' => 'layout-template', 'iconBg' => 'bg-orange-500', 'barColor' => 'bg-orange-500'],
                                ['label' => 'Shortlisted', 'num' => $stage_shortlisted, 'rate' => round(($stage_shortlisted/$total_pipeline)*100) . '%', 'icon' => 'hourglass', 'iconBg' => 'bg-teal-600', 'barColor' => 'bg-teal-600'],
                                ['label' => 'Interviewed', 'num' => $stage_interviewed, 'rate' => round(($stage_interviewed/$total_pipeline)*100) . '%', 'icon' => 'calendar-days', 'iconBg' => 'bg-slate-800', 'barColor' => 'bg-slate-800'],
                                ['label' => 'Hired', 'num' => $stage_hired, 'rate' => round(($stage_hired/$total_pipeline)*100) . '%', 'icon' => 'file-text', 'iconBg' => 'bg-blue-500', 'barColor' => 'bg-blue-500'],
                                ['label' => 'Onboarded', 'num' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employee_onboarding WHERE status='Completed'"))['cnt'], 'rate' => '5%', 'icon' => 'user-check', 'iconBg' => 'bg-green-500', 'barColor' => 'bg-green-500']
                            ];
                            foreach($stages as $stage): ?>
                            <div class="bg-white border border-gray-100 p-4 rounded-xl flex flex-col justify-between shadow-sm">
                                <div class="flex justify-between items-start">
                                    <p class="text-[12px] font-bold text-gray-500 truncate mr-1 uppercase tracking-tight"><?= $stage['label'] ?></p>
                                    <div class="<?= $stage['iconBg'] ?> p-2 rounded-lg text-white shrink-0">
                                        <i data-lucide="<?= $stage['icon'] ?>" class="w-4 h-4"></i>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h4 class="text-2xl font-extrabold text-slate-800 leading-tight"><?= $stage['num'] ?></h4>
                                    <div class="flex justify-between items-center mb-2 mt-2">
                                        <span class="text-[10px] text-gray-400 font-medium">Progress</span>
                                        <span class="text-[11px] text-slate-600 font-bold"><?= $stage['rate'] ?></span>
                                    </div>
                                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="<?= $stage['barColor'] ?> h-full" style="width: <?= $stage['rate'] ?>"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

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
                                            <p class="font-bold text-slate-800"><?= $j['title'] ?></p>
                                            <span class="text-[10px] bg-red-50 text-red-500 px-2 py-0.5 rounded-full font-bold uppercase tracking-tighter">Active</span>
                                        </td>
                                        <td class="px-6 py-4 text-center text-gray-500"><?= $j['loc'] ?></td>
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

                <!-- Right Column: Overview & Schedule -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800">Recruitment Overview</h3>
                            <button class="text-xs text-gray-400 flex items-center border rounded-lg px-2 py-1"><i data-lucide="download" class="w-3 h-3 mr-1"></i> Monthly</button>
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
                                        <h4 class="text-sm font-bold text-slate-800"><?= $sch['title'] ?></h4>
                                        <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> <?= date('h:i A', strtotime($sch['meeting_time'])) ?></p>
                                    </div>
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($sch['title']) ?>&background=random" class="w-10 h-10 rounded-full grayscale hover:grayscale-0 transition-all cursor-pointer">
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 text-center">No schedules for today.</p>
                            <?php endif; ?>
                            <button class="w-full py-3 bg-teal-900 text-white rounded-xl font-bold text-sm shadow-lg shadow-teal-100 hover:bg-teal-950 transition-all mt-4">View All Schedules</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // GAUGE CHART
        const ctx = document.getElementById('gaugeChart').getContext('2d');
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

        // --- PUNCH LOGIC ---
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            hours = String(hours).padStart(2, '0');
            document.getElementById('liveClock').textContent = `${hours}:${minutes} ${ampm}`;
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            document.getElementById('liveDate').textContent = now.toLocaleDateString('en-GB', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Initial State Logic (Injected from PHP)
        let currentState = '<?= ($attendance && !$attendance['punch_out'] && !$is_on_break) ? 'in' : 'out' ?>';
        let secondsElapsed = <?= $total_seconds_worked ?>;
        
        let timerInterval;
        let punchInTimeStr = "04:12 pm"; // Default placeholder

        function updateUI() {
            const container = document.getElementById('actionButtons');
            const statusDisplay = document.getElementById('statusDisplay');
            const badge = document.querySelector('.production-badge');

            if (currentState === 'out') {
                container.innerHTML = `<button onclick="handlePunch('in')" class="btn-punch-in">Punch In</button>`;
                statusDisplay.innerHTML = `<i data-lucide="fingerprint" class="w-4 h-4 text-gray-400"></i> Not Punched In`;
                badge.style.opacity = '0.5';
            } else if (currentState === 'in') {
                container.innerHTML = `
                    <button onclick="handlePunch('out')" class="btn-punch-out">Punch Out</button>
                    <button onclick="toggleBreak()" class="btn-break"><i data-lucide="coffee" class="w-4 h-4"></i> Take a Break</button>`;
                statusDisplay.innerHTML = `<i data-lucide="clock" class="w-4 h-4 text-emerald-500"></i> Punch In at ${punchInTimeStr}`;
                badge.style.opacity = '1';
            } else if (currentState === 'break') {
                container.innerHTML = `
                    <button onclick="toggleBreak()" class="btn-break" style="background:#fef3c7; color:#d97706; border-color:#d97706;">
                        <i data-lucide="play" class="w-4 h-4"></i> Resume Work
                    </button>`;
                statusDisplay.innerHTML = `<i data-lucide="coffee" class="w-4 h-4 text-orange-500"></i> On Break`;
            }
            lucide.createIcons();
        }

        async function handlePunch(action) {
            const formData = new FormData();
            formData.append('action', action);

            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.status === 'success') {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }).toLowerCase();
                    
                    if (action === 'in') {
                        currentState = 'in';
                        punchInTimeStr = timeString;
                        startTimer();
                    } else {
                        currentState = 'out';
                        stopTimer();
                        secondsElapsed = 0;
                        updateTimerDisplay();
                        location.reload(); // Reload to see updated DB hours
                    }
                    updateUI();
                } else {
                    alert(result.message);
                }
            } catch (err) {
                console.error(err);
                alert("Connection failed");
            }
        }

        async function toggleBreak() {
            if (currentState === 'in') {
                // Start Break
                await handlePunch('break_start');
                if (currentState === 'break') {
                    stopTimer();
                }
            } else {
                // End Break
                await handlePunch('break_end');
                if (currentState === 'in') {
                    startTimer();
                }
            }
            updateUI();
        }

        function startTimer() {
            stopTimer();
            timerInterval = setInterval(() => {
                secondsElapsed++;
                updateTimerDisplay();
            }, 1000);
        }

        function stopTimer() { clearInterval(timerInterval); }

        function updateTimerDisplay() {
            const hours = (secondsElapsed / 3600).toFixed(2);
            document.getElementById('productionTimer').textContent = hours;
        }

        updateUI();
        <?php if(currentState === 'in'): ?>startTimer();<?php endif; ?>
    </script>
</body>
</html>