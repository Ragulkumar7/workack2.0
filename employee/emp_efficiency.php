<?php
// --- START SESSION ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- FIXED PATH & DB LOGIC ---
$path_to_root = '../'; 
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/workack2.0/include/db_connect.php';

if (file_exists($dbPath)) {
    include_once($dbPath);
} else {
    $dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'db_connect.php';
    include_once($dbPath);
}

if (!isset($_SESSION['id']) && !isset($_SESSION['user_id'])) {
    header("Location: " . $path_to_root . "index.php");
    exit();
}
$current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : $_SESSION['user_id'];

// --- GET PROPER NAME & SECURITY CHECK ---
$name_stmt = $conn->prepare("SELECT full_name FROM employee_profiles WHERE user_id = ?");
$name_stmt->bind_param("i", $current_user_id);
$name_stmt->execute();
$name_res = $name_stmt->get_result()->fetch_assoc();

if(!$name_res) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'><h3>Employee profile not found. Please contact HR.</h3></div>");
}
$emp_full_name = $name_res['full_name'];


// --- 1. DYNAMIC ATTENDANCE CALCULATION (Weight: 15%) ---
$att_stmt = $conn->prepare("
    SELECT COUNT(*) as total_days, 
           SUM(CASE WHEN status = 'On Time' THEN 1 ELSE 0 END) as present_days 
    FROM attendance 
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$att_stmt->bind_param("i", $current_user_id);
$att_stmt->execute();
$att_data = $att_stmt->get_result()->fetch_assoc();
$total_att_days = $att_data['total_days'] > 0 ? $att_data['total_days'] : 1; 
$attendance_pct = min(100, round(($att_data['present_days'] / $total_att_days) * 100));

// --- 2. TASK COMPLETION CALCULATION (Weight: 25%) ---
$task_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
    FROM personal_taskboard 
    WHERE user_id = ? AND status != 'cancelled'
");
$task_stmt->bind_param("i", $current_user_id);
$task_stmt->execute();
$task_res = $task_stmt->get_result()->fetch_assoc();
$task_total = $task_res['total'] > 0 ? $task_res['total'] : 1;
$task_completion_pct = round(($task_res['completed'] / $task_total) * 100);

// --- 3. PROJECT TIMELINES CALCULATION (Weight: 30%) ---
$proj_stmt = $conn->prepare("SELECT task_title as name, due_date as deadline, status FROM project_tasks WHERE assigned_to_user_id = ? LIMIT 5");
$proj_stmt->bind_param("i", $current_user_id);
$proj_stmt->execute();
$projects_list = $proj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$on_time_projects = 0;
foreach($projects_list as $p) { if($p['status'] == 'Completed') $on_time_projects++; }
$proj_total = count($projects_list) > 0 ? count($projects_list) : 1;
$project_completion_pct = round(($on_time_projects / $proj_total) * 100);

// --- 4. AUTOMATED SYSTEM RELIABILITY (Weight: 10%) ---
$late_stmt = $conn->prepare("SELECT COUNT(*) as late_days FROM attendance WHERE user_id = ? AND status = 'Late' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$late_stmt->bind_param("i", $current_user_id);
$late_stmt->execute();
$late_data = $late_stmt->get_result()->fetch_assoc();
$late_days = $late_data['late_days'] ?? 0;
$overdue_count = $task_res['overdue'] ?? 0;

$automated_rating = 100 - ($late_days * 5) - ($overdue_count * 5);
$automated_rating = max(40, min(100, $automated_rating)); 

$auto_comments = "System Analysis: ";
if ($late_days == 0 && $overdue_count == 0) {
    $auto_comments .= "Exceptional reliability! Zero late arrivals and zero overdue tasks detected in the current period.";
} else {
    $auto_comments .= "Detected " . $late_days . " late arrival(s) and " . $overdue_count . " overdue task(s). Improve punctuality and deadline management to increase your overall system rating.";
}

// --- 5. MANUAL MANAGER RATING (Weight: 20%) ---
$perf_stmt = $conn->prepare("SELECT manager_rating_pct, manager_comments, weekly_trend FROM employee_performance WHERE user_id = ?");
$perf_stmt->bind_param("i", $current_user_id);
$perf_stmt->execute();
$perf_table = $perf_stmt->get_result()->fetch_assoc();
$mgr_pct = $perf_table['manager_rating_pct'] ?? 0;

// --- FINAL ENTERPRISE SCORE AGGREGATION ---
// Formula: Projects(30) + Tasks(25) + Attendance(15) + System(10) + Manager(20)
$score = ($project_completion_pct * 0.30) + 
         ($task_completion_pct * 0.25) + 
         ($attendance_pct * 0.15) + 
         ($automated_rating * 0.10) + 
         ($mgr_pct * 0.20);
$score = round($score, 1);

// Enterprise Grading Scale
if($score >= 90) $grade = "Outstanding";
elseif($score >= 75) $grade = "Exceeds Expectations";
elseif($score >= 50) $grade = "Meets Expectations";
else $grade = "Needs Improvement";

// SVG Dash Offset
$offset = 251.2 - (251.2 * ($score / 100));
$weekly_trend = !empty($perf_table['weekly_trend']) ? json_decode($perf_table['weekly_trend'], true) : [0, 0, 0, 0];

$metrics = [
    'total_score' => $score,
    'performance_grade' => $grade,
    'project_completion_pct' => $project_completion_pct,
    'project_details' => $on_time_projects . "/" . count($projects_list) . " Completed",
    'task_completion_pct' => $task_completion_pct,
    'task_details' => $task_res['completed'] . " Completed",
    'total_tasks_assigned' => $task_res['total'],
    'completed_on_time' => $task_res['completed'],
    'overdue_tasks' => $task_res['overdue'] ?? 0,
    'attendance_pct' => $attendance_pct,
    'attendance_details' => $att_data['present_days'] . " / " . $total_att_days . " Days",
    'system_rating_pct' => $automated_rating, 
    'system_comments' => $auto_comments,
    'manager_rating_pct' => $mgr_pct,
    'manager_comments' => $perf_table['manager_comments'] ?? 'No feedback provided yet.'
];

include_once $path_to_root . 'header.php';
include_once $path_to_root . 'sidebars.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | My Performance</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 95px; padding: 10px 30px 30px 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        .progress-ring__circle { transition: stroke-dashoffset 0.5s ease-in-out; transform: rotate(-90deg); transform-origin: 50% 50%; }
        .metric-card:hover { transform: translateY(-2px); transition: 0.2s; }
        
        /* Custom Scrollbars */
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body>

    <div id="mainContent">
        
        <div class="flex justify-between items-end mb-8 mt-0">
            <div class="flex items-center gap-4">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp_full_name); ?>&background=0d9488&color=fff&size=128" class="w-16 h-16 rounded-full border-4 border-white shadow-sm">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">My Performance</h1>
                    <div class="flex gap-2 text-sm text-slate-500">
                        <span>Employee View</span> &bull; <span>Performance Metrics</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8 flex flex-col xl:flex-row items-center gap-8">
            <div class="relative w-40 h-40 flex items-center justify-center flex-shrink-0">
                <svg class="w-full h-full" viewBox="0 0 100 100">
                    <circle class="text-slate-100 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"></circle>
                    <circle class="text-emerald-500 progress-ring__circle stroke-current" 
                            stroke-width="8" stroke-linecap="round" cx="50" cy="50" r="40" fill="transparent" 
                            stroke-dasharray="251.2" stroke-dashoffset="<?php echo $offset; ?>"></circle>
                </svg>
                <div class="absolute text-center">
                    <span class="text-4xl font-bold text-slate-800"><?php echo $score; ?></span>
                    <span class="block text-[10px] text-slate-400 font-bold tracking-wider mt-1">SCORE</span>
                </div>
            </div>
            
            <div class="flex-1 w-full">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="text-lg font-bold text-slate-800">Performance Grade</h3>
                    <span class="text-lg font-bold <?php echo $score < 50 ? 'text-rose-500' : 'text-emerald-600'; ?>"><?php echo htmlspecialchars($metrics['performance_grade']); ?></span>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-[11px] text-slate-400 font-bold uppercase mb-1"><span>Projects</span> <span>30%</span></div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['project_completion_pct']; ?>%</div>
                        <div class="text-[11px] text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['project_details']); ?></div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-[11px] text-slate-400 font-bold uppercase mb-1"><span>Tasks</span> <span>25%</span></div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['task_completion_pct']; ?>%</div>
                        <div class="text-[11px] text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['task_details']); ?></div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-[11px] text-slate-400 font-bold uppercase mb-1"><span>Attendance</span> <span>15%</span></div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['attendance_pct']; ?>%</div>
                        <div class="text-[11px] text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['attendance_details']); ?></div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-[11px] text-slate-400 font-bold uppercase mb-1"><span>System</span> <span>10%</span></div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['system_rating_pct']; ?>%</div>
                        <div class="text-[11px] text-slate-500 mt-1">Reliability Index</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-[11px] text-slate-400 font-bold uppercase mb-1"><span>Manager</span> <span>20%</span></div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['manager_rating_pct']; ?>%</div>
                        <div class="text-[11px] text-slate-500 mt-1">Soft Skills & Output</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[320px]">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center shrink-0">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-layer-group text-blue-500 mr-2"></i> Project Timelines</h4>
                </div>
                <div class="flex-1 overflow-y-auto custom-scroll">
                    <table class="w-full text-sm text-left">
                        <tbody class="divide-y divide-slate-50">
                            <?php if(!empty($projects_list)): ?>
                                <?php foreach($projects_list as $proj): ?>
                                <tr>
                                    <td class="p-4 font-medium text-slate-700"><?php echo htmlspecialchars($proj['name']); ?></td>
                                    <td class="p-4 text-slate-500 text-right"><?php echo date('d M Y', strtotime($proj['deadline'])); ?></td>
                                    <td class="p-4 text-right">
                                        <?php $pClass = ($proj['status'] == 'Completed') ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>
                                        <span class="<?php echo $pClass; ?> px-2 py-1 rounded text-[10px] font-bold tracking-wide uppercase"><?php echo $proj['status']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="p-8 text-center text-slate-400 italic">No timeline data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[320px]">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center shrink-0">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-list-check text-orange-500 mr-2"></i> Task Efficiency</h4>
                </div>
                <div class="p-6 flex flex-col flex-1">
                    <div class="flex items-center justify-between mb-3 shrink-0">
                        <span class="text-sm text-slate-600">Total Tasks Assigned</span>
                        <span class="font-bold text-slate-800"><?php echo $metrics['total_tasks_assigned']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-3 shrink-0">
                        <span class="text-sm text-slate-600">Completed On Time</span>
                        <span class="font-bold text-emerald-600"><?php echo $metrics['completed_on_time']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-4 shrink-0">
                        <span class="text-sm text-slate-600">Overdue / Pending</span>
                        <span class="font-bold text-rose-600"><?php echo $metrics['overdue_tasks']; ?></span>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 mt-auto shrink-0">
                        <p class="text-[10px] text-slate-400 mb-2 font-bold uppercase tracking-widest">Weekly Trend</p>
                        <div class="flex gap-1 h-12 items-end">
                            <div class="w-1/5 bg-blue-200 rounded-t transition-all duration-500" style="height: <?php echo $weekly_trend[0]; ?>%;" title="Week 1"></div>
                            <div class="w-1/5 bg-blue-300 rounded-t transition-all duration-500" style="height: <?php echo $weekly_trend[1]; ?>%;" title="Week 2"></div>
                            <div class="w-1/5 bg-blue-400 rounded-t transition-all duration-500" style="height: <?php echo $weekly_trend[2]; ?>%;" title="Week 3"></div>
                            <div class="w-1/5 bg-blue-500 rounded-t transition-all duration-500" style="height: <?php echo $weekly_trend[3]; ?>%;" title="Week 4"></div>
                            <div class="w-1/5 bg-slate-200 rounded-t h-full flex items-center justify-center text-[10px] text-slate-500 font-bold" title="Average">AVG</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6 mb-10">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col h-[380px]">
                <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-microchip text-blue-500"></i> System Assessment
                </h4>
                <div class="mb-4 shrink-0">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest">Reliability Score</label>
                        <span class="font-bold text-slate-800 text-lg"><?php echo $metrics['system_rating_pct']; ?>/100</span>
                    </div>
                    <div class="relative w-full h-1.5 bg-slate-100 rounded-lg overflow-hidden">
                         <div class="absolute top-0 left-0 h-full bg-blue-500" style="width: <?php echo $metrics['system_rating_pct']; ?>%;"></div>
                    </div>
                </div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 shrink-0">Analysis Results</label>
                <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-700 italic flex-1 overflow-y-auto custom-scroll">
                    "<?php echo htmlspecialchars($metrics['system_comments']); ?>"
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col h-[380px]">
                <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-user-tie text-purple-500"></i> Manager's Review
                </h4>
                <div class="mb-4 shrink-0">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest">Manager Score</label>
                        <span class="font-bold text-slate-800 text-lg"><?php echo $metrics['manager_rating_pct']; ?>/100</span>
                    </div>
                    <div class="relative w-full h-1.5 bg-slate-100 rounded-lg overflow-hidden">
                         <div class="absolute top-0 left-0 h-full bg-purple-500" style="width: <?php echo $metrics['manager_rating_pct']; ?>%;"></div>
                    </div>
                </div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 shrink-0">Official Feedback</label>
                <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-700 italic flex-1 overflow-y-auto custom-scroll">
                    "<?php echo htmlspecialchars($metrics['manager_comments']); ?>"
                </div>
            </div>

        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>