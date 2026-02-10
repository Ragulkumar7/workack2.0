<?php
// manager/performance.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// 2. GET EMPLOYEE ID FROM URL
// If no ID is set, default to 101 (Sarah) for testing
$emp_id = isset($_GET['id']) ? $_GET['id'] : 101; 

// 3. MOCK DATABASE (Detailed Data for each Employee)
// In a real application, you would run a SQL query: SELECT * FROM performance WHERE emp_id = $emp_id
$employees_db = [
    101 => [
        'name' => 'Sarah Jenkins',
        'role' => 'Frontend Developer',
        'dept' => 'Web Development',
        'img'  => 'https://i.pravatar.cc/150?u=201',
        // Project Stats
        'proj_total' => 5, 'proj_ontime' => 5,
        // Task Stats
        'task_total' => 50, 'task_done' => 48,
        // Attendance
        'work_days' => 22, 'leaves' => 0,
        // Manager Rating
        'manager_score' => 95,
        'feedback' => 'Sarah consistently exceeds expectations. Her code quality is top-notch.'
    ],
    102 => [
        'name' => 'Mike Ross',
        'role' => 'Backend Developer',
        'dept' => 'Web Development',
        'img'  => 'https://i.pravatar.cc/150?u=202',
        'proj_total' => 4, 'proj_ontime' => 2, 
        'task_total' => 40, 'task_done' => 30, 
        'work_days' => 22, 'leaves' => 4,
        'manager_score' => 70,
        'feedback' => 'Mike needs to improve on meeting deadlines. Technical skills are good.'
    ],
    103 => [
        'name' => 'Rachel Zane',
        'role' => 'UI/UX Designer',
        'dept' => 'Design',
        'img'  => 'https://i.pravatar.cc/150?u=203',
        'proj_total' => 6, 'proj_ontime' => 5, 
        'task_total' => 35, 'task_done' => 34, 
        'work_days' => 22, 'leaves' => 1,
        'manager_score' => 88,
        'feedback' => 'Great designs this month. Very collaborative team player.'
    ],
    104 => [
        'name' => 'Louis Litt',
        'role' => 'QA Tester',
        'dept' => 'Testing',
        'img'  => 'https://i.pravatar.cc/150?u=204',
        'proj_total' => 3, 'proj_ontime' => 1, 
        'task_total' => 20, 'task_done' => 10, 
        'work_days' => 22, 'leaves' => 5,
        'manager_score' => 50,
        'feedback' => 'Attendance issues are affecting project delivery timelines.'
    ]
];

// 4. LOAD DATA FOR SELECTED EMPLOYEE
$data = $employees_db[$emp_id] ?? $employees_db[101]; // Fallback if ID invalid

// --- CALCULATION ENGINE ---

// A. Project Score (40% Weight)
$p_score = ($data['proj_total'] > 0) ? round(($data['proj_ontime'] / $data['proj_total']) * 100) : 0;

// B. Task Score (30% Weight)
$t_score = ($data['task_total'] > 0) ? round(($data['task_done'] / $data['task_total']) * 100) : 0;
$pending_tasks = $data['task_total'] - $data['task_done'];

// C. Attendance Score (20% Weight)
$present_days = $data['work_days'] - $data['leaves'];
$a_score = round(($present_days / $data['work_days']) * 100);

// D. Manager Score (10% Weight)
$m_score = $data['manager_score'];

// Final Weighted Score
$final_score = ($p_score * 0.4) + ($t_score * 0.3) + ($a_score * 0.2) + ($m_score * 0.1);
$final_score = round($final_score, 1);

// Determine Color & Grade
if ($final_score >= 85) {
    $grade_color = 'text-emerald-600';
    $bar_color = 'text-emerald-500';
    $grade_text = 'Excellent';
} elseif ($final_score >= 60) {
    $grade_color = 'text-orange-500';
    $bar_color = 'text-orange-500';
    $grade_text = 'Good';
} else {
    $grade_color = 'text-red-500';
    $bar_color = 'text-red-500';
    $grade_text = 'Needs Improvement';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Performance - <?php echo $data['name']; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .progress-ring__circle {
            transition: stroke-dashoffset 0.5s ease-in-out;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .metric-card:hover { transform: translateY(-2px); transition: 0.2s; }
    </style>
</head>
<body>

    <?php include('./sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="mb-6">
            <a href="performance_list.php" class="text-slate-500 hover:text-slate-800 text-sm flex items-center gap-2 transition">
                <i class="fa-solid fa-arrow-left"></i> Back to Employee List
            </a>
        </div>

        <div class="flex justify-between items-end mb-8">
            <div class="flex items-center gap-4">
                <img src="<?php echo $data['img']; ?>" class="w-16 h-16 rounded-full border-4 border-white shadow-sm">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800"><?php echo $data['name']; ?></h1>
                    <div class="flex gap-2 text-sm text-slate-500">
                        <span><?php echo $data['role']; ?></span> &bull; <span><?php echo $data['dept']; ?></span>
                    </div>
                </div>
            </div>
            <button class="bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50">
                <i class="fa-solid fa-download mr-2"></i> Report
            </button>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8 flex flex-col md:flex-row items-center gap-8">
            <div class="relative w-40 h-40 flex items-center justify-center flex-shrink-0">
                <svg class="w-full h-full" viewBox="0 0 100 100">
                    <circle class="text-slate-100 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"></circle>
                    <circle class="<?php echo $bar_color; ?> progress-ring__circle stroke-current" 
                            stroke-width="8" stroke-linecap="round" cx="50" cy="50" r="40" fill="transparent" 
                            stroke-dasharray="251.2" stroke-dashoffset="<?php echo 251.2 - (251.2 * $final_score / 100); ?>"></circle>
                </svg>
                <div class="absolute text-center">
                    <span class="text-4xl font-bold text-slate-800"><?php echo $final_score; ?></span>
                    <span class="block text-[10px] text-slate-400 font-bold tracking-wider mt-1">SCORE</span>
                </div>
            </div>
            
            <div class="flex-1 w-full">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="text-lg font-bold text-slate-800">Performance Grade</h3>
                    <span class="text-lg font-bold <?php echo $grade_color; ?>"><?php echo $grade_text; ?></span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Projects</span> <span>40%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $p_score; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo $data['proj_ontime']; ?>/<?php echo $data['proj_total']; ?> On Time</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Tasks</span> <span>30%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $t_score; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo $data['task_done']; ?> Completed</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Attendance</span> <span>20%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $a_score; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1"><?php echo $data['leaves']; ?> Days Leave</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Manager</span> <span>10%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $m_score; ?>%</div>
                        <div class="text-xs text-slate-500 mt-1">Soft Skills</div>
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
                        <tr>
                            <td class="p-4 font-medium text-slate-700">HRMS Portal v2</td>
                            <td class="p-4 text-slate-500 text-right">10 Feb 2026</td>
                            <td class="p-4 text-right"><span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-bold">On Time</span></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-medium text-slate-700">E-Commerce App</td>
                            <td class="p-4 text-slate-500 text-right">01 Feb 2026</td>
                            <td class="p-4 text-right"><span class="bg-rose-50 text-rose-700 px-2 py-1 rounded text-xs font-bold">Delayed</span></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-medium text-slate-700">Client API Integ.</td>
                            <td class="p-4 text-slate-500 text-right">15 Jan 2026</td>
                            <td class="p-4 text-right"><span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-bold">On Time</span></td>
                        </tr>
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
                        <span class="font-bold text-slate-800"><?php echo $data['task_total']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-600">Completed On Time</span>
                        <span class="font-bold text-emerald-600"><?php echo $data['task_done']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-slate-600">Overdue / Pending</span>
                        <span class="font-bold text-rose-600"><?php echo $pending_tasks; ?></span>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 mt-2">
                        <p class="text-xs text-slate-400 mb-2 font-bold uppercase">Weekly Trend</p>
                        <div class="flex gap-1 h-10 items-end">
                            <div class="w-1/5 bg-blue-200 rounded-t h-4"></div>
                            <div class="w-1/5 bg-blue-300 rounded-t h-6"></div>
                            <div class="w-1/5 bg-blue-400 rounded-t h-5"></div>
                            <div class="w-1/5 bg-blue-500 rounded-t h-8"></div>
                            <div class="w-1/5 bg-slate-200 rounded-t h-full flex items-center justify-center text-[10px] text-slate-500 font-bold">AVG</div>
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
                    <div class="flex items-center gap-4">
                        <input type="range" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer" min="0" max="100" value="<?php echo $m_score; ?>">
                        <span class="font-bold text-slate-800 w-8"><?php echo $m_score; ?></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                        <span>Poor</span>
                        <span>Excellent</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Comments</label>
                    <textarea class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:border-orange-500 outline-none" rows="3"><?php echo $data['feedback']; ?></textarea>
                </div>
            </div>
            <div class="text-right mt-4">
                <button class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">Update Review</button>
            </div>
        </div>

    </div>

</body>
</html>