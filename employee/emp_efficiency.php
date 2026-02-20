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

// --- 1. DYNAMIC CALCULATION: ATTENDANCE (Weight: 20%) ---
// Logic: (Present Days / Last 30 Days) * 100
$att_stmt = $conn->prepare("SELECT COUNT(*) as present_days FROM attendance WHERE user_id = ? AND status = 'On Time' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$att_stmt->bind_param("i", $current_user_id);
$att_stmt->execute();
$att_data = $att_stmt->get_result()->fetch_assoc();
$attendance_pct = min(100, round(($att_data['present_days'] / 22) * 100)); // Assuming 22 working days/month

// --- 2. DYNAMIC CALCULATION: TASK COMPLETION (Weight: 30%) ---
// Pulling from personal_taskboard table
$task_stmt = $conn->prepare("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
    FROM personal_taskboard WHERE user_id = ?");
$task_stmt->bind_param("i", $current_user_id);
$task_stmt->execute();
$task_res = $task_stmt->get_result()->fetch_assoc();
$task_total = $task_res['total'] > 0 ? $task_res['total'] : 1;
$task_completion_pct = round(($task_res['completed'] / $task_total) * 100);

// --- 3. DYNAMIC CALCULATION: PROJECT TIMELINES (Weight: 40%) ---
// Fetching active projects from project_tasks table
$proj_stmt = $conn->prepare("SELECT task_title as name, due_date as deadline, status FROM project_tasks WHERE assigned_to LIKE ? LIMIT 5");
$emp_search = "%" . $_SESSION['name'] . "%"; // Search by name as stored in your project_tasks
$proj_stmt->bind_param("s", $emp_search);
$proj_stmt->execute();
$projects_list = $proj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$on_time_projects = 0;
foreach($projects_list as $p) { if($p['status'] == 'Completed') $on_time_projects++; }
$proj_total = count($projects_list) > 0 ? count($projects_list) : 1;
$project_completion_pct = round(($on_time_projects / $proj_total) * 100);

// --- 4. MANAGER RATING (Weight: 10%) ---
// Still fetched from performance table as it requires manual input
$stmt = $conn->prepare("SELECT manager_rating_pct, manager_comments, weekly_trend FROM employee_performance WHERE user_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$perf_table = $stmt->get_result()->fetch_assoc();
$mgr_pct = $perf_table['manager_rating_pct'] ?? 0;

// --- FINAL SCORE AGGREGATION ---
$score = ($project_completion_pct * 0.4) + ($task_completion_pct * 0.3) + ($attendance_pct * 0.2) + ($mgr_pct * 0.1);
$score = round($score, 1);

// Determine Grade
if($score >= 90) $grade = "Excellent";
elseif($score >= 75) $grade = "Good";
elseif($score >= 50) $grade = "Average";
else $grade = "Needs Improvement";

// SVG Dash Offset
$offset = 251.2 - (251.2 * ($score / 100));
$weekly_trend = !empty($perf_table['weekly_trend']) ? json_decode($perf_table['weekly_trend'], true) : [0, 0, 0, 0];

// Update metrics array for display
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
    'attendance_details' => $att_data['present_days'] . " Days Present",
    'manager_rating_pct' => $mgr_pct,
    'manager_details' => 'Soft Skills',
    'manager_comments' => $perf_table['manager_comments'] ?? 'No feedback available.'
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
    </style>
</head>
<body>

    <div id="mainContent">
        
        <div class="flex justify-between items-end mb-8 mt-0">
            <div class="flex items-center gap-4">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['name'] ?? 'Employee'); ?>&background=0d9488&color=fff&size=128" class="w-16 h-16 rounded-full border-4 border-white shadow-sm">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">My Performance</h1>
                    <div class="flex gap-2 text-sm text-slate-500">
                        <span>Employee View</span> &bull; <span>Performance Metrics</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8 flex flex-col md:flex-row items-center gap-8">
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
                    <span class="text-lg font-bold text-emerald-600"><?php echo htmlspecialchars($metrics['performance_grade']); ?></span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Projects</span> <span>40%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['project_completion_pct']; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['project_details']); ?></div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Tasks</span> <span>30%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['task_completion_pct']; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['task_details']); ?></div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Attendance</span> <span>20%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['attendance_pct']; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['attendance_details']); ?></div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Manager</span> <span>10%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $metrics['manager_rating_pct']; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($metrics['manager_details']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-layer-group text-blue-500 mr-2"></i> Project Timelines</h4>
                </div>
                <table class="w-full text-sm text-left">
                    <tbody class="divide-y divide-slate-50">
                        <?php if(!empty($projects_list)): ?>
                            <?php foreach($projects_list as $proj): ?>
                            <tr>
                                <td class="p-4 font-medium text-slate-700"><?php echo htmlspecialchars($proj['name']); ?></td>
                                <td class="p-4 text-slate-500 text-right"><?php echo date('d M Y', strtotime($proj['deadline'])); ?></td>
                                <td class="p-4 text-right">
                                    <?php $pClass = ($proj['status'] == 'Completed') ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>
                                    <span class="<?php echo $pClass; ?> px-2 py-1 rounded text-xs font-bold"><?php echo $proj['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="p-4 text-center text-slate-400 italic">No timeline data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-list-check text-orange-500 mr-2"></i> Task Efficiency</h4>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-600">Total Tasks Assigned</span>
                        <span class="font-bold text-slate-800"><?php echo $metrics['total_tasks_assigned']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-600">Completed On Time</span>
                        <span class="font-bold text-emerald-600"><?php echo $metrics['completed_on_time']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-slate-600">Overdue / Pending</span>
                        <span class="font-bold text-rose-600"><?php echo $metrics['overdue_tasks']; ?></span>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 mt-2">
                        <p class="text-xs text-slate-400 mb-2 font-bold uppercase">Weekly Trend</p>
                        <div class="flex gap-1 h-10 items-end">
                            <div class="w-1/5 bg-blue-200 rounded-t transition-all duration-500" 
                                 style="height: <?php echo $weekly_trend[0]; ?>%;" title="Mon"></div>
                            <div class="w-1/5 bg-blue-300 rounded-t transition-all duration-500" 
                                 style="height: <?php echo $weekly_trend[1]; ?>%;" title="Tue"></div>
                            <div class="w-1/5 bg-blue-400 rounded-t transition-all duration-500" 
                                 style="height: <?php echo $weekly_trend[2]; ?>%;" title="Wed"></div>
                            <div class="w-1/5 bg-blue-500 rounded-t transition-all duration-500" 
                                 style="height: <?php echo $weekly_trend[3]; ?>%;" title="Thu"></div>
                            <div class="w-1/5 bg-slate-200 rounded-t h-full flex items-center justify-center text-[10px] text-slate-500 font-bold" title="Average">AVG</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mt-6 p-6">
            <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-comment-dots text-slate-400"></i> Manager's Feedback (10% Score)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Soft Skills Rating</label>
                    <div class="relative w-full h-2 bg-slate-200 rounded-lg overflow-hidden mt-3">
                         <div class="absolute top-0 left-0 h-full bg-slate-800" style="width: <?php echo $metrics['manager_rating_pct']; ?>%;"></div>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-slate-400">Rated by Team Lead</span>
                        <span class="font-bold text-slate-800 text-lg"><?php echo $metrics['manager_rating_pct']; ?>/100</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Comments</label>
                    <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-700 italic">
                        "<?php echo htmlspecialchars($metrics['manager_comments']); ?>"
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>