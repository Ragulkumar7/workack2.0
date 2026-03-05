<?php
// TL/attendance_tl.php - Team Lead View of Team Attendance

// 1. SESSION & SECURITY
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

// 2. FILTER LOGIC
$filter_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$is_today = ($filter_date === date('Y-m-d'));

// 3. FETCH TEAM ATTENDANCE DATA
// This safely joins the team members assigned to this TL with their attendance for the selected date
$sql = "
    SELECT ep.user_id, 
           ep.full_name, 
           ep.emp_id_code, 
           ep.designation, 
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
    WHERE ep.reporting_to = ?
    ORDER BY ep.full_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $filter_date, $tl_id);
$stmt->execute();
$result = $stmt->get_result();

$team_members = [];
$metrics = [
    'total' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'yet_to_punch' => 0
];

while ($row = $result->fetch_assoc()) {
    $metrics['total']++;
    
    // Determine visual status
    $display_status = "Absent";
    $status_color = "bg-rose-50 text-rose-600 border-rose-200";
    
    if (!empty($row['punch_in'])) {
        $db_status = $row['status'] ?? 'Present';
        
        if (stripos($db_status, 'Late') !== false) {
            $display_status = "Late";
            $status_color = "bg-amber-50 text-amber-600 border-amber-200";
            $metrics['late']++;
            $metrics['present']++; // Late is still present
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
        if ($is_today) {
            $display_status = "Yet to Punch";
            $status_color = "bg-slate-100 text-slate-500 border-slate-200";
            $metrics['yet_to_punch']++;
        } else {
            $display_status = "Absent";
            $status_color = "bg-rose-50 text-rose-600 border-rose-200";
            $metrics['absent']++;
        }
    }

    // Process Profile Image safely
    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['full_name'])."&background=0d9488&color=fff&bold=true";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
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
    <title>Team Attendance | Team Lead</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px; }
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
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">Team Attendance</h1>
                <p class="text-slate-500 text-sm mt-1 font-medium">Monitor your team's daily punch records and metrics.</p>
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
            <div class="card p-5 border-b-4 border-b-blue-500 flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Total Team</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['total']; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500">
                    <i class="fa-solid fa-users text-lg"></i>
                </div>
            </div>
            <div class="card p-5 border-b-4 border-b-emerald-500 flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Present</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['present']; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                    <i class="fa-solid fa-user-check text-lg"></i>
                </div>
            </div>
            <div class="card p-5 border-b-4 border-b-amber-500 flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Late Logins</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['late']; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-500">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                </div>
            </div>
            <div class="card p-5 border-b-4 border-b-rose-500 flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Absent</p>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['absent']; ?></h3>
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
                                
                                $prod_hrs = !empty($emp['production_hours']) ? number_format($emp['production_hours'], 2) . ' Hrs' : '-';
                                $prod_class = (floatval($emp['production_hours']) > 0 && floatval($emp['production_hours']) < 8) ? "text-rose-500 font-bold" : "text-slate-600 font-bold";
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $emp['profile_img']; ?>" class="w-10 h-10 rounded-full border border-slate-200 shadow-sm object-cover bg-white">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($emp['full_name']); ?></p>
                                            <p class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($emp['designation'] . ' • ' . $emp['emp_id_code']); ?></p>
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
                                    <a href="../employee_attendance_details.php?id=<?php echo $emp['user_id']; ?>" class="inline-flex items-center justify-center text-slate-400 hover:text-teal-600 bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-200 px-3 py-2 rounded-lg transition-all shadow-sm text-xs font-bold gap-2">
                                        <i class="fa-regular fa-calendar-days"></i> Full History
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
                                        <p class="text-xs mt-1">You currently have no employees reporting to you.</p>
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