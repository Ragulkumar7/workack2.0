<?php
// TL/attendance_tl.php - Team Lead & Executive View of Team Attendance

// 1. SESSION & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

// --- ROBUST DATABASE CONNECTION ---
$dbPath = '../include/db_connect.php';
if (!file_exists($dbPath)) {
    $dbPath = './include/db_connect.php';
}
require_once $dbPath;

// Check Login & Role
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$tl_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'Manager'; // Fetch user role dynamically

// 2. FILTER LOGIC
$filter_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$is_today = ($filter_date === date('Y-m-d'));

// =========================================================================================
// 3. FETCH APPROVED LEAVES FOR THIS DATE (Prevents False Absences/Punches)
// =========================================================================================
$leave_sql = "SELECT user_id, leave_type FROM leave_requests WHERE status = 'Approved' AND ? BETWEEN start_date AND end_date";
$l_stmt = $conn->prepare($leave_sql);
$l_stmt->bind_param("s", $filter_date);
$l_stmt->execute();
$l_res = $l_stmt->get_result();
$team_leaves = [];
while ($l_row = $l_res->fetch_assoc()) {
    $team_leaves[$l_row['user_id']] = $l_row['leave_type'];
}
$l_stmt->close();

// =========================================================================================
// 4. FETCH TEAM ATTENDANCE DATA (STRICT RBAC ISOLATION ENGINE)
// =========================================================================================
$base_sql = "
    SELECT ep.user_id, 
           ep.full_name, 
           ep.emp_id_code, 
           ep.designation,
           ep.department, 
           ep.profile_img, 
           ep.shift_timings,
           a.id as att_id,
           a.punch_in, 
           a.punch_out, 
           a.status, 
           a.production_hours, 
           a.break_time
    FROM employee_profiles ep
    LEFT JOIN attendance a ON ep.user_id = a.user_id AND a.date = ?
";

if (in_array($user_role, ['HR', 'Admin', 'CEO', 'System Admin'])) {
    // 1. Global Admins: See everyone except themselves
    $sql = $base_sql . " WHERE ep.user_id != ? ORDER BY ep.department ASC, ep.full_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $filter_date, $tl_id);

} elseif (in_array($user_role, ['CFO', 'Finance', 'Accounts Manager'])) {
    // 2. CFO & Finance: ONLY see their direct reports OR anyone in the Accounts/Finance departments
    $sql = $base_sql . " WHERE ep.user_id != ? AND (ep.department IN ('Accounts', 'Finance') OR ep.reporting_to = ?) ORDER BY ep.department ASC, ep.full_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $filter_date, $tl_id, $tl_id);

} elseif (in_array($user_role, ['Manager', 'Project Manager', 'General Manager'])) {
    // 3. High-Level Managers: See direct reports AND 2nd level reports
    $sql = $base_sql . " WHERE ep.reporting_to = ? OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE reporting_to = ?) ORDER BY ep.full_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $filter_date, $tl_id, $tl_id);

} else {
    // 4. Standard Team Leads: Only see direct reports
    $sql = $base_sql . " WHERE ep.reporting_to = ? ORDER BY ep.full_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $filter_date, $tl_id);
}

$stmt->execute();
$result = $stmt->get_result();

$team_members = [];
$metrics = [
    'total' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'on_leave' => 0,
    'yet_to_punch' => 0
];

while ($row = $result->fetch_assoc()) {
    $metrics['total']++;
    $uid = $row['user_id'];
    
    $shift_timings = $row['shift_timings'] ?? '09:00 AM - 06:00 PM';
    $time_parts = explode('-', $shift_timings);
    $shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
    $shift_start_time = strtotime($filter_date . ' ' . $shift_start_str);
    
    $display_status = "Absent";
    $status_color = "bg-rose-50 text-rose-600 border-rose-200";
    
    // LIVE PRODUCTION TRACKING
    $prod_val = floatval($row['production_hours']);
    if ($prod_val == 0 && !empty($row['punch_in'])) {
        $in_time = strtotime($row['punch_in']);
        if (empty($row['punch_out'])) {
            $out_time = $is_today ? time() : strtotime($filter_date . ' 18:00:00');
        } else {
            $out_time = strtotime($row['punch_out']);
        }
        $b_sec = intval($row['break_time']) * 60;
        $prod_val = max(0, (($out_time - $in_time) - $b_sec) / 3600);
    }
    $row['live_production'] = $prod_val;
    
    // STRICT STATUS HIERARCHY: Leave > DB Absent > Punch In > Auto-Absent > Yet to Punch
    if (isset($team_leaves[$uid])) {
        $display_status = "On Leave";
        $status_color = "bg-purple-50 text-purple-600 border-purple-200";
        $metrics['on_leave']++;
    } elseif (isset($row['status']) && stripos($row['status'], 'Absent') !== false) {
        $display_status = "Absent";
        $status_color = "bg-rose-50 text-rose-600 border-rose-200";
        $metrics['absent']++;
    } elseif (!empty($row['punch_in'])) {
        $db_status = $row['status'] ?? 'Present';
        $punch_in_ts = strtotime($row['punch_in']);
        
        if (stripos($db_status, 'Late') !== false || $punch_in_ts > ($shift_start_time + 60)) {
            $display_status = "Late";
            $status_color = "bg-amber-50 text-amber-600 border-amber-200";
            $metrics['late']++;
            $metrics['present']++; 
        } elseif (stripos($db_status, 'WFH') !== false) {
            $display_status = "WFH";
            $status_color = "bg-blue-50 text-blue-600 border-blue-200";
            $metrics['present']++;
        } else {
            $display_status = "On Time";
            $status_color = "bg-emerald-50 text-emerald-600 border-emerald-200";
            $metrics['present']++;
        }
    } else {
        // Did not punch in
        if ($is_today) {
            if (time() > ($shift_start_time + 7200)) { // If 2 hours late and no punch, force Absent
                $display_status = "Absent";
                $status_color = "bg-rose-50 text-rose-600 border-rose-200";
                $metrics['absent']++;
            } else {
                $display_status = "Yet to Punch";
                $status_color = "bg-slate-100 text-slate-500 border-slate-200";
                $metrics['yet_to_punch']++;
            }
        } else {
            $display_status = "Absent";
            $status_color = "bg-rose-50 text-rose-600 border-rose-200";
            $metrics['absent']++;
        }
    }

    // PROCESS PROFILE IMAGE SAFELY
    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['full_name'])."&background=0d9488&color=fff&bold=true";
    } elseif (strpos($imgSource, 'http') !== 0 && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = '../assets/profiles/' . $imgSource;
    }

    $row['display_status'] = $display_status;
    $row['status_color'] = $status_color;
    $row['profile_img'] = $imgSource;
    $team_members[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Attendance | Enterprise View</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
            box-sizing: border-box; /* Added to prevent overflow */
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

        /* CHANGED: Used 991px to prevent overlap on Tablets/iPads */
        @media (max-width: 991px) {
            #mainContent, #mainContent.main-shifted { 
                margin-left: 0 !important; 
                width: 100% !important; 
                padding: 80px 16px 24px 16px !important; /* Added 80px top padding for mobile header */
            }
        }
    </style>
</head>
<body>

    <?php 
        $sidebars_path = '../sidebars.php';
        if(!file_exists($sidebars_path)) $sidebars_path = './sidebars.php';
        include($sidebars_path); 

        $header_path = '../header.php';
        if(!file_exists($header_path)) $header_path = './header.php';
        include($header_path); 
    ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-slate-800 tracking-tight">Attendance Roster</h1>
                <p class="text-slate-500 text-sm mt-1 font-medium">Monitor daily punch records and live production across your scope.</p>
            </div>
            
            <form action="" method="GET" class="flex items-center gap-2 bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm w-full md:w-auto">
                <div class="flex items-center pl-3 pr-2 py-1.5 bg-slate-50 rounded-lg border border-slate-100 w-full md:w-auto">
                    <i class="fa-regular fa-calendar text-teal-600 mr-2"></i>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" 
                           class="bg-transparent border-none outline-none text-sm font-bold text-slate-700 w-full cursor-pointer"
                           onchange="this.form.submit()">
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-5 border-b-4 border-b-blue-500 flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Total Team</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['total']; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500">
                    <i class="fa-solid fa-users text-lg"></i>
                </div>
            </div>
            <div class="card p-5 border-b-4 border-b-emerald-500 flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Present</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['present']; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                    <i class="fa-solid fa-user-check text-lg"></i>
                </div>
            </div>
            <div class="card p-5 border-b-4 border-b-amber-500 flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Late Logins</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['late']; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-500">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                </div>
            </div>
            <div class="card p-5 border-b-4 border-b-rose-500 flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Absent / Leave</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo ($metrics['absent'] + $metrics['on_leave'] + $metrics['yet_to_punch']); ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
                    <i class="fa-solid fa-user-xmark text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card flex flex-col flex-grow overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0">
                <h3 class="text-lg font-black text-slate-800">Daily Roster <span class="text-sm font-medium text-slate-400 ml-2">(<?php echo date('d M Y', strtotime($filter_date)); ?>)</span></h3>
            </div>
            
            <div class="overflow-x-auto custom-scroll">
                <table class="w-full text-left whitespace-nowrap text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Team Member</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Shift</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Punch In</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Punch Out</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Production</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($team_members)): ?>
                            <?php foreach ($team_members as $emp): 
                                $p_in = !empty($emp['punch_in']) ? date('h:i A', strtotime($emp['punch_in'])) : '--:--';
                                $p_out = !empty($emp['punch_out']) ? date('h:i A', strtotime($emp['punch_out'])) : '--:--';
                                
                                $prod_hrs = ($emp['live_production'] > 0) ? number_format($emp['live_production'], 2) . ' Hrs' : '-';
                                $prod_class = ($emp['live_production'] > 0 && $emp['live_production'] < 8 && !empty($emp['punch_out'])) ? "text-rose-500 font-bold" : "text-slate-700 font-bold";
                                
                                // Glowing Live Indicator if working right now
                                if ($emp['live_production'] > 0 && empty($emp['punch_out'])) {
                                    $prod_hrs = "<span class='flex items-center gap-1.5 text-blue-600'><span class=" . '"relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span></span>' . number_format($emp['live_production'], 2) . " Hrs</span>";
                                    $prod_class = "font-black";
                                }
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $emp['profile_img']; ?>" class="w-10 h-10 rounded-full border border-slate-200 shadow-sm object-cover bg-white">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($emp['full_name']); ?></p>
                                            <p class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($emp['designation']); ?> • <?php echo htmlspecialchars($emp['department'] ?? 'Unassigned'); ?></p>
                                            <p class="text-[10px] font-bold text-teal-600 uppercase tracking-widest mt-0.5"><?php echo htmlspecialchars($emp['emp_id_code']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-1 rounded border border-slate-200">
                                        <?php echo htmlspecialchars($emp['shift_timings'] ?? 'General'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-700"><?php echo $p_in; ?></td>
                                <td class="px-6 py-4 font-bold text-slate-700"><?php echo $p_out; ?></td>
                                <td class="px-6 py-4 <?php echo $prod_class; ?>"><?php echo $prod_hrs; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-md border <?php echo $emp['status_color']; ?>">
                                        <?php echo htmlspecialchars($emp['display_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="../employee_attendance_details.php?id=<?php echo $emp['user_id']; ?>" class="inline-flex items-center justify-center text-slate-500 hover:text-teal-700 bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-200 px-3 py-2 rounded-lg transition-all shadow-sm text-xs font-bold gap-2">
                                        <i class="fa-solid fa-chart-pie"></i> Full Audit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-16">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3 border border-slate-100">
                                            <i class="fa-solid fa-users-slash text-2xl text-slate-300"></i>
                                        </div>
                                        <p class="font-bold text-slate-500">No Team Members Found</p>
                                        <p class="text-xs mt-1">There are no employees under your supervision for this department.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>