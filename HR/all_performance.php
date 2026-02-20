<?php
// hr_performance.php

// 1. INCLUDE DATABASE CONNECTION & SESSION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php';

// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// Helper functions for badges and grades
function getBadgeClass($grade) {
    switch(strtolower($grade)) {
        case 'high': 
        case 'excellent': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'good': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'average': return 'bg-orange-100 text-orange-800 border-orange-200';
        case 'low': 
        case 'poor': return 'bg-red-100 text-red-800 border-red-200';
        default: return 'bg-slate-100 text-slate-800 border-slate-200';
    }
}

function calculateGrade($score) {
    if ($score >= 90) return 'Excellent';
    if ($score >= 75) return 'Good';
    if ($score >= 50) return 'Average';
    return 'Poor';
}

// Handle Filters
$selected_dept = isset($_GET['department']) ? $_GET['department'] : 'All';
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch distinct departments for the filter dropdown
$dept_query = "SELECT DISTINCT department FROM employee_profiles WHERE department IS NOT NULL AND department != ''";
$dept_result = mysqli_query($conn, $dept_query);
$departments_list = [];
while ($d_row = mysqli_fetch_assoc($dept_result)) {
    $departments_list[] = $d_row['department'];
}

// Build the Main Query
// EXCLUDING top-level roles so HR only sees employees under them
$where_clauses = ["ep.status = 'Active'", "u.role NOT IN ('System Admin', 'HR', 'Manager', 'CFO')"];

if ($selected_dept !== 'All') {
    $safe_dept = mysqli_real_escape_string($conn, $selected_dept);
    $where_clauses[] = "ep.department = '$safe_dept'";
}

$where_sql = implode(' AND ', $where_clauses);

/* Note: If you add an 'evaluation_month' column to `employee_performance` table in the future, 
 you can add this to the LEFT JOIN: AND DATE_FORMAT(per.created_at, '%Y-%m') = '$selected_month'
*/

$hr_query = "
    SELECT ep.user_id as id, 
           ep.full_name, 
           ep.designation, 
           ep.department,
           ep.profile_img as profile_image, 
           per.performance_grade as performance, 
           COALESCE(per.total_score, 0) as performance_score, 
           COALESCE(per.project_completion_pct, 0) as project_completion_rate, 
           COALESCE(per.task_completion_pct, 0) as task_completion_rate, 
           COALESCE(per.attendance_pct, 0) as attendance_rate 
    FROM employee_profiles ep
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN employee_performance per ON ep.user_id = per.user_id
    WHERE $where_sql
    ORDER BY ep.department ASC, per.total_score DESC
";

$hr_result = mysqli_query($conn, $hr_query);
$all_employees = [];

if ($hr_result) {
    while ($row = mysqli_fetch_assoc($hr_result)) {
        $dept = !empty($row['department']) ? $row['department'] : 'Unassigned Department';
        if (!isset($all_employees[$dept])) {
            $all_employees[$dept] = [];
        }
        $all_employees[$dept][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Employee Performance Track</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 0; padding: 15px; width: 100%; transition: 0.3s; }
        
        @media (min-width: 1024px) {
            #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); }
            #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        }
        
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none; height: 20px; width: 20px; border-radius: 50%;
            background: #0d9488; cursor: pointer; margin-top: -8px; box-shadow: 0 0 0 4px #ccfbf1;
        }
        input[type=range]::-webkit-slider-runnable-track { width: 100%; height: 6px; cursor: pointer; background: #e2e8f0; border-radius: 4px; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body>

    <?php include('../sidebars.php'); ?>

    <div id="mainContent">
        <?php include('../header.php'); ?>
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Employee Performance</h1>
                <p class="text-sm text-slate-500">Track and evaluate performance scores of your workforce</p>
            </div>
            
            <form method="GET" class="w-full lg:w-auto flex flex-col sm:flex-row gap-3">
                <div class="flex items-center bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm">
                    <i class="fa-solid fa-calendar-days text-slate-400 mr-2"></i>
                    <input type="month" name="month" value="<?php echo $selected_month; ?>" class="text-sm text-slate-700 focus:outline-none" onchange="this.form.submit()">
                </div>

                <div class="flex items-center bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm min-w-[200px]">
                    <i class="fa-solid fa-layer-group text-slate-400 mr-2"></i>
                    <select name="department" class="w-full text-sm text-slate-700 focus:outline-none bg-transparent" onchange="this.form.submit()">
                        <option value="All">All Departments</option>
                        <?php foreach($departments_list as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php if($selected_dept === $d) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative w-full sm:w-64">
                    <input type="text" id="searchInput" placeholder="Search employee..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 shadow-sm">
                    <i class="fa-solid fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
            </form>
        </div>

        <?php if (empty($all_employees)): ?>
            <div class="bg-white p-12 rounded-xl border border-slate-200 text-center shadow-sm">
                <i class="fa-solid fa-users-slash text-4xl text-slate-300 mb-3 block"></i>
                <h3 class="text-lg font-bold text-slate-700">No Employees Found</h3>
                <p class="text-slate-500 text-sm mt-1">Try changing the department or month filter.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($all_employees as $dept_name => $members): ?>
                
                <div class="department-block bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
                    
                    <div class="bg-teal-800 p-4 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-700 flex items-center justify-center text-teal-100">
                                <i class="fa-solid fa-users text-lg"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-white text-lg"><?php echo htmlspecialchars($dept_name); ?></h2>
                                <div class="text-xs text-teal-200"><?php echo count($members); ?> Employees Evaluated</div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto hide-scrollbar">
                        <table class="w-full text-sm text-left whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200 uppercase text-[11px] tracking-wider">
                                <tr>
                                    <th class="p-4">Employee Details</th>
                                    <th class="p-4 text-center">Projects</th>
                                    <th class="p-4 text-center">Tasks</th>
                                    <th class="p-4 text-center">Attendance</th>
                                    <th class="p-4 text-center">Total Score</th>
                                    <th class="p-4 text-center">Grade</th>
                                    <th class="p-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($members as $emp): 
                                    $grade = ($emp['performance'] && $emp['performance'] !== 'N/A' && $emp['performance'] !== 'Pending') ? $emp['performance'] : calculateGrade($emp['performance_score']);
                                    $badgeClass = getBadgeClass($grade);
                                    
                                    $emp_img = $emp['profile_image'];
                                    if(empty($emp_img) || $emp_img === 'default_user.png') {
                                        $emp_img = 'https://ui-avatars.com/api/?name='.urlencode($emp['full_name'] ?? 'User').'&background=random';
                                    } elseif (!str_starts_with($emp_img, 'http') && strpos($emp_img, 'assets/profiles/') === false) {
                                        $emp_img = '../assets/profiles/' . $emp_img; 
                                    }
                                    
                                    $empData = [
                                        'id' => $emp['id'],
                                        'name' => $emp['full_name'],
                                        'designation' => $emp['designation'],
                                        'department' => $emp['department'],
                                        'image' => $emp_img,
                                        'grade' => $grade,
                                        'score' => rtrim(rtrim($emp['performance_score'], '0'), '.'),
                                        'projects' => $emp['project_completion_rate'],
                                        'tasks' => $emp['task_completion_rate'] ?? 0,
                                        'attendance' => $emp['attendance_rate']
                                    ];
                                    $json_empData = htmlspecialchars(json_encode($empData), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="emp-row hover:bg-slate-50 transition" data-name="<?php echo strtolower(htmlspecialchars($emp['full_name'])); ?>">
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?php echo $emp_img; ?>" class="w-10 h-10 rounded-full border border-slate-200 shadow-sm object-cover">
                                            <div>
                                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($emp['designation']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center text-slate-600 font-medium"><?php echo $emp['project_completion_rate']; ?>%</td>
                                    <td class="p-4 text-center text-slate-600 font-medium"><?php echo $emp['task_completion_rate'] ?? 0; ?>%</td>
                                    <td class="p-4 text-center text-slate-600 font-medium"><?php echo $emp['attendance_rate']; ?>%</td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="font-bold text-slate-800 text-base"><?php echo rtrim(rtrim($emp['performance_score'], '0'), '.'); ?></span>
                                            <span class="text-[10px] text-slate-400">/100</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold border <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($grade); ?></span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <button data-emp="<?php echo $json_empData; ?>" class="view-performance-btn inline-flex items-center gap-2 bg-white border border-slate-200 text-teal-700 px-4 py-1.5 rounded-lg text-xs font-semibold hover:bg-teal-50 hover:border-teal-200 transition shadow-sm">
                                            <i class="fa-regular fa-chart-bar"></i> View Full
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="perfModal" class="fixed inset-0 z-[100] hidden bg-slate-900 bg-opacity-60 overflow-y-auto backdrop-blur-sm transition-opacity">
        <div class="flex items-center justify-center min-h-screen p-4 sm:p-6">
            <div class="bg-slate-50 rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden relative">
                
                <div class="bg-white p-4 sm:p-6 border-b border-slate-200 flex justify-between items-center sticky top-0 z-10">
                    <div class="flex items-center gap-4">
                        <img id="m_emp_img" src="" class="w-12 h-12 sm:w-14 sm:h-14 rounded-full shadow-sm border border-slate-200 object-cover">
                        <div>
                            <h2 id="m_emp_name" class="text-lg sm:text-xl font-bold text-slate-800 leading-tight">Employee Name</h2>
                            <p id="m_emp_role" class="text-xs sm:text-sm text-slate-500">Designation • Department</p>
                        </div>
                    </div>
                    <button id="closeModalBtn" class="bg-slate-100 w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 mb-6 shadow-sm">
                        <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
                            <h3 class="font-bold text-slate-800">Performance Overview <span class="text-xs text-slate-400 font-normal ml-2">(<?php echo date('F Y', strtotime($selected_month)); ?>)</span></h3>
                            <span id="m_emp_grade" class="font-bold text-orange-500 text-lg">Average</span>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-6 items-center">
                            <div class="w-32 h-32 flex-shrink-0 relative flex items-center justify-center rounded-full border-8 border-slate-100 mx-auto md:mx-0">
                                <svg class="absolute inset-0 w-full h-full transform -rotate-90">
                                    <circle id="m_score_circle" cx="60" cy="60" r="56" stroke="currentColor" stroke-width="8" fill="transparent" class="text-orange-500 transition-all duration-1000" stroke-dasharray="351" stroke-dashoffset="351"></circle>
                                </svg>
                                <div class="text-center z-10">
                                    <div id="m_emp_score" class="text-3xl font-bold text-slate-800">0</div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Score</div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 w-full">
                                <div class="border border-slate-100 rounded-lg p-3 sm:p-4 bg-slate-50">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2"><span>PROJECTS</span> <span>40%</span></div>
                                    <div id="m_emp_projects" class="text-xl sm:text-2xl font-bold text-slate-800">0%</div>
                                </div>
                                <div class="border border-slate-100 rounded-lg p-3 sm:p-4 bg-slate-50">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2"><span>TASKS</span> <span>30%</span></div>
                                    <div id="m_emp_tasks" class="text-xl sm:text-2xl font-bold text-slate-800">0%</div>
                                </div>
                                <div class="border border-slate-100 rounded-lg p-3 sm:p-4 bg-slate-50">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2"><span>ATTENDANCE</span> <span>20%</span></div>
                                    <div id="m_emp_attendance" class="text-xl sm:text-2xl font-bold text-slate-800">0%</div>
                                </div>
                                <div class="border border-slate-100 rounded-lg p-3 sm:p-4 bg-slate-50">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2"><span>MANAGER RATING</span> <span>10%</span></div>
                                    <div id="m_emp_manager" class="text-xl sm:text-2xl font-bold text-slate-800">70%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 shadow-sm">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-6 border-b border-slate-100 pb-4">
                            <i class="fa-solid fa-clipboard-check text-teal-600"></i> HR Verification & Notes
                        </h3>
                        
                        <form action="hr_update_review.php" method="POST">
                            <input type="hidden" name="employee_id" id="m_form_emp_id">
                            <input type="hidden" name="eval_month" value="<?php echo $selected_month; ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                                <div>
                                    <div class="flex justify-between items-end mb-4">
                                        <label class="text-sm font-semibold text-slate-600">Adjust Soft Skills Rating (HR Override)</label>
                                        <span id="sliderValue" class="text-2xl font-bold text-teal-700 bg-teal-50 px-3 py-1 rounded-md">70</span>
                                    </div>
                                    <input type="range" name="soft_skills" id="softSkillsSlider" min="0" max="100" value="70" class="w-full appearance-none bg-transparent outline-none mb-2" oninput="document.getElementById('sliderValue').innerText = this.value">
                                    <div class="flex justify-between text-xs text-slate-400 font-medium">
                                        <span>Needs Improvement</span>
                                        <span>Exceptional</span>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-slate-600 mb-2">HR Official Remarks</label>
                                    <textarea name="comments" rows="3" class="w-full border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none" placeholder="Add official HR notes for appraisal records..."></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end pt-4 border-t border-slate-100">
                                <button type="submit" class="w-full sm:w-auto bg-teal-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow hover:bg-teal-800 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-save"></i> Save HR Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('perfModal');
            const closeBtn = document.getElementById('closeModalBtn');
            const viewButtons = document.querySelectorAll('.view-performance-btn');
            const searchInput = document.getElementById('searchInput');

            // JS Filter for Name Search
            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('.emp-row');
                const departments = document.querySelectorAll('.department-block');

                rows.forEach(row => {
                    const name = row.getAttribute('data-name');
                    if (name.includes(term)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                departments.forEach(dept => {
                    const visibleRows = dept.querySelectorAll('.emp-row[style=""]');
                    if (visibleRows.length === 0) {
                        dept.style.display = 'none';
                    } else {
                        dept.style.display = 'block';
                    }
                });
            });

            // Open Modal
            viewButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const empData = JSON.parse(this.getAttribute('data-emp'));
                    
                    document.getElementById('m_emp_img').src = empData.image;
                    document.getElementById('m_emp_name').innerText = empData.name;
                    document.getElementById('m_emp_role').innerText = `${empData.designation} • ${empData.department}`;
                    document.getElementById('m_emp_grade').innerText = empData.grade;
                    document.getElementById('m_emp_score').innerText = empData.score;
                    document.getElementById('m_form_emp_id').value = empData.id;

                    document.getElementById('m_emp_projects').innerText = empData.projects + "%";
                    document.getElementById('m_emp_tasks').innerText = empData.tasks + "%";
                    document.getElementById('m_emp_attendance').innerText = empData.attendance + "%";
                    document.getElementById('m_emp_manager').innerText = "70%"; 

                    let circumference = 351;
                    let offset = circumference - (empData.score / 100) * circumference;
                    let gradeEl = document.getElementById('m_emp_grade');
                    let circleEl = document.getElementById('m_score_circle');
                    
                    gradeEl.className = "font-bold text-lg ";
                    circleEl.className.baseVal = "transition-all duration-1000 "; 
                    
                    if(empData.score >= 90) { gradeEl.classList.add('text-emerald-600'); circleEl.classList.add('text-emerald-500'); }
                    else if(empData.score >= 75) { gradeEl.classList.add('text-blue-600'); circleEl.classList.add('text-blue-500'); }
                    else if(empData.score >= 50) { gradeEl.classList.add('text-orange-500'); circleEl.classList.add('text-orange-500'); }
                    else { gradeEl.classList.add('text-red-600'); circleEl.classList.add('text-red-500'); }

                    modal.classList.remove('hidden');
                    setTimeout(() => { circleEl.style.strokeDashoffset = offset; }, 50);
                });
            });

            const closeModal = () => {
                modal.classList.add('hidden');
                document.getElementById('m_score_circle').style.strokeDashoffset = 351;
            };

            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if(e.target === modal || e.target.parentElement === modal) closeModal();
            });
        });
    </script>
</body>
</html>