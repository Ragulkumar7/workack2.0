<?php
// manager/performance.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// 2. AUTOMATED CALCULATION ENGINE (MOCK DATA)
// In real app, fetch these from database tables: 'projects', 'tasks', 'attendance'

// A. Project Performance Logic
$projects_assigned = 5;
$projects_completed = 4;
$projects_ontime = 3; // 1 project delayed
$project_score = ($projects_assigned > 0) ? round(($projects_ontime / $projects_assigned) * 100) : 0;

// B. Task Efficiency Logic
$total_tasks = 45;
$completed_tasks = 42;
$overdue_tasks = 3;
$task_score = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100) : 0;

// C. Attendance Logic
$working_days = 22;
$present_days = 20; // 2 days leave
$attendance_score = round(($present_days / $working_days) * 100);

// D. Overall Weighted Score Calculation
// Weights: Projects (40%), Tasks (30%), Attendance (20%), Manager Rating (10%)
$manager_rating = 90; // Manual Input (Default/Placeholder)

$final_score = ($project_score * 0.4) + ($task_score * 0.3) + ($attendance_score * 0.2) + ($manager_rating * 0.1);
$final_score = round($final_score, 1);

// Determine Grade/Color
$grade_color = ($final_score >= 85) ? 'text-emerald-600' : (($final_score >= 60) ? 'text-orange-500' : 'text-red-500');
$grade_text = ($final_score >= 85) ? 'Excellent' : (($final_score >= 60) ? 'Good' : 'Needs Improvement');

$view = $_GET['view'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Automated Performance</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Custom Progress Circle */
        .progress-ring__circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .metric-card { transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-3px); }
    </style>
</head>
<body>

    <?php include('../sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Performance & Appraisal</h1>
                <p class="text-sm text-slate-500">Automated metrics based on real-time activity</p>
            </div>
            <div class="flex gap-2">
                <button class="bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50">
                    <i class="fa-solid fa-download mr-2"></i> Download Report
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8 flex flex-col md:flex-row items-center gap-8">
            <div class="relative w-40 h-40 flex items-center justify-center">
                <svg class="w-full h-full" viewBox="0 0 100 100">
                    <circle class="text-slate-100 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"></circle>
                    <circle class="<?php echo str_replace('text-', 'text-', $grade_color); ?> progress-ring__circle stroke-current" 
                            stroke-width="8" stroke-linecap="round" cx="50" cy="50" r="40" fill="transparent" 
                            stroke-dasharray="251.2" stroke-dashoffset="<?php echo 251.2 - (251.2 * $final_score / 100); ?>"></circle>
                </svg>
                <div class="absolute text-center">
                    <span class="text-3xl font-bold text-slate-800"><?php echo $final_score; ?></span>
                    <span class="block text-xs text-slate-400">OUT OF 100</span>
                </div>
            </div>
            
            <div class="flex-1">
                <h3 class="text-lg font-bold text-slate-800 mb-1">Overall Performance: <span class="<?php echo $grade_color; ?>"><?php echo $grade_text; ?></span></h3>
                <p class="text-sm text-slate-500 mb-6">Score is calculated automatically based on project deadlines, task completion rates, and attendance records.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <div class="text-xs text-slate-400 uppercase font-bold mb-1">Project Delivery</div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $project_score; ?>%</div>
                        <div class="w-full bg-slate-200 h-1.5 rounded-full mt-2">
                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?php echo $project_score; ?>%"></div>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <div class="text-xs text-slate-400 uppercase font-bold mb-1">Task Efficiency</div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $task_score; ?>%</div>
                        <div class="w-full bg-slate-200 h-1.5 rounded-full mt-2">
                            <div class="bg-orange-500 h-1.5 rounded-full" style="width: <?php echo $task_score; ?>%"></div>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <div class="text-xs text-slate-400 uppercase font-bold mb-1">Attendance</div>
                        <div class="text-xl font-bold text-slate-700"><?php echo $attendance_score; ?>%</div>
                        <div class="w-full bg-slate-200 h-1.5 rounded-full mt-2">
                            <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?php echo $attendance_score; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-layer-group text-blue-500 mr-2"></i> Project Timelines</h4>
                    <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2 py-1 rounded">Weight: 40%</span>
                </div>
                <div class="p-0">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-white text-slate-500 border-b border-slate-100">
                            <tr>
                                <th class="p-4 font-semibold">Project Name</th>
                                <th class="p-4 font-semibold">Deadline</th>
                                <th class="p-4 font-semibold">Status</th>
                                <th class="p-4 font-semibold text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr>
                                <td class="p-4 font-medium text-slate-700">HRMS Portal v2</td>
                                <td class="p-4 text-slate-500">10 Feb 2026</td>
                                <td class="p-4"><span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-bold">On Time</span></td>
                                <td class="p-4 text-right font-bold text-emerald-600">+20</td>
                            </tr>
                            <tr>
                                <td class="p-4 font-medium text-slate-700">E-Commerce App</td>
                                <td class="p-4 text-slate-500">01 Feb 2026</td>
                                <td class="p-4"><span class="bg-rose-50 text-rose-700 px-2 py-1 rounded text-xs font-bold">Delayed (2 Days)</span></td>
                                <td class="p-4 text-right font-bold text-rose-600">-5</td>
                            </tr>
                            <tr>
                                <td class="p-4 font-medium text-slate-700">Client API Integ.</td>
                                <td class="p-4 text-slate-500">15 Jan 2026</td>
                                <td class="p-4"><span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-bold">On Time</span></td>
                                <td class="p-4 text-right font-bold text-emerald-600">+20</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-list-check text-orange-500 mr-2"></i> Task Efficiency</h4>
                    <span class="text-xs font-bold bg-orange-100 text-orange-700 px-2 py-1 rounded">Weight: 30%</span>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-slate-600">Total Tasks Assigned</span>
                        <span class="font-bold text-slate-800"><?php echo $total_tasks; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-slate-600">Completed On Time</span>
                        <span class="font-bold text-emerald-600"><?php echo $completed_tasks; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-slate-600">Overdue / Pending</span>
                        <span class="font-bold text-rose-600"><?php echo $overdue_tasks; ?></span>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 mt-4">
                        <p class="text-xs text-slate-400 mb-2">Completion Rate Trend</p>
                        <div class="flex gap-1 h-8 items-end">
                            <div class="w-1/6 bg-blue-200 rounded-t h-4"></div>
                            <div class="w-1/6 bg-blue-300 rounded-t h-6"></div>
                            <div class="w-1/6 bg-blue-400 rounded-t h-5"></div>
                            <div class="w-1/6 bg-blue-500 rounded-t h-8"></div> <div class="w-1/6 bg-slate-200 rounded-t h-full flex items-center justify-center text-[10px] text-slate-500 font-bold">AVG</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mt-6 p-6">
            <h4 class="font-bold text-slate-700 mb-4 border-b pb-2">Manager's Discretionary Score (10%)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Soft Skills & Leadership</label>
                    <input type="range" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer" min="0" max="100" value="90">
                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                        <span>Poor</span>
                        <span>Excellent</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Manager Comments</label>
                    <textarea class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:border-orange-500 outline-none" rows="2" placeholder="Add comments regarding leadership, communication, etc.">Employee shows great initiative in team meetings.</textarea>
                </div>
            </div>
            <div class="text-right mt-4">
                <button class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900">Save Review</button>
            </div>
        </div>

    </div>

</body>
</html>