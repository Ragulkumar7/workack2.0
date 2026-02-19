<?php
// manager/team_performance.php

// 1. INCLUDE DATABASE CONNECTION & SESSION
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
        case 'excellent': return 'bg-emerald-100 text-emerald-800';
        case 'good': return 'bg-blue-100 text-blue-800';
        case 'average': return 'bg-orange-100 text-orange-800';
        case 'low': 
        case 'poor': return 'bg-red-100 text-red-800';
        default: return 'bg-slate-100 text-slate-800';
    }
}

function calculateGrade($score) {
    if ($score >= 90) return 'Excellent';
    if ($score >= 75) return 'Good';
    if ($score >= 50) return 'Average';
    return 'Poor';
}

// 2. FETCH TEAM LEADS AND THEIR PERFORMANCE
// FIXED: Changed from tl_performance to employee_performance to use the new unified table structure
$tl_query = "
    SELECT u.id as tl_id, 
           COALESCE(u.name, ep.full_name) as tl_name, 
           ep.designation as tl_role, 
           ep.department as tl_dept, 
           ep.profile_img as tl_img, 
           COALESCE(per.total_score, 0) as score, 
           COALESCE(per.project_completion_pct, 0) as project_rate, 
           COALESCE(per.attendance_pct, 0) as attendance
    FROM users u
    LEFT JOIN employee_profiles ep ON u.id = ep.user_id
    LEFT JOIN employee_performance per ON u.id = per.user_id
    WHERE u.role = 'Team Lead' AND (u.name IS NOT NULL OR ep.full_name IS NOT NULL)
";

$tl_result = mysqli_query($conn, $tl_query);
$team_leads = [];
if ($tl_result) {
    $team_leads = mysqli_fetch_all($tl_result, MYSQLI_ASSOC);
} else {
    die("Query Failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Team Performance List</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 0; padding: 15px; width: 100%; transition: 0.3s; }
        
        /* Desktop sidebar offset */
        @media (min-width: 1024px) {
            #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); }
            #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        }
        
        /* Custom Range Slider */
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: #3b82f6;
            cursor: pointer;
            margin-top: -8px;
            box-shadow: 0 0 0 4px #eff6ff;
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 6px;
            cursor: pointer;
            background: #e2e8f0;
            border-radius: 4px;
        }
        
        /* Hide Scrollbar for clean tables */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body>

    <?php include('../sidebars.php'); ?>

    <div id="mainContent">
        <?php include('../header.php'); ?>
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Team Performance Overview</h1>
                <p class="text-sm text-slate-500">Track and manage Team Leads and their respective members</p>
            </div>
            
            <div class="w-full md:w-auto">
                <div class="relative w-full md:w-64">
                    <input type="text" placeholder="Search employee..." class="w-full pl-10 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 shadow-sm">
                    <i class="fa-solid fa-search absolute left-3 top-3 text-slate-400 text-xs"></i>
                </div>
            </div>
        </div>

        <?php if (empty($team_leads)): ?>
            <div class="bg-white p-8 rounded-xl border border-slate-200 text-center text-slate-500 shadow-sm">
                No active Team Leads found in the system.
            </div>
        <?php else: ?>
            
            <?php foreach ($team_leads as $tl): ?>
                
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
                    
                    <div class="bg-slate-800 p-5 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <?php 
                                $tl_img_src = !empty($tl['tl_img']) ? $tl['tl_img'] : 'https://ui-avatars.com/api/?name='.urlencode($tl['tl_name']).'&background=random';
                            ?>
                            <img src="<?php echo $tl_img_src; ?>" class="w-12 h-12 rounded-full border-2 border-white shadow-sm object-cover">
                            <div>
                                <h2 class="font-bold text-white text-lg"><?php echo htmlspecialchars($tl['tl_name']); ?> <span class="text-xs font-normal text-slate-300 ml-2">(Team Lead)</span></h2>
                                <div class="text-sm text-slate-300"><?php echo htmlspecialchars(($tl['tl_role'] ?? 'Role N/A') . ' • ' . ($tl['tl_dept'] ?? 'Dept N/A')); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php
                        // FIXED: Replaced the team_members query with the new joined employee_profiles/employee_performance query
                        $tm_query = "
                            SELECT ep.user_id as id, 
                                   ep.full_name, 
                                   ep.designation, 
                                   ep.profile_img as profile_image, 
                                   per.performance_grade as performance, 
                                   COALESCE(per.total_score, 0) as performance_score, 
                                   COALESCE(per.project_completion_pct, 0) as project_completion_rate, 
                                   COALESCE(per.task_completion_pct, 0) as task_completion_rate, 
                                   COALESCE(per.attendance_pct, 0) as attendance_rate 
                            FROM employee_profiles ep
                            LEFT JOIN employee_performance per ON ep.user_id = per.user_id
                            WHERE ep.reporting_to = ?
                        ";
                        $tm_stmt = mysqli_prepare($conn, $tm_query);
                        mysqli_stmt_bind_param($tm_stmt, "i", $tl['tl_id']);
                        mysqli_stmt_execute($tm_stmt);
                        $tm_result = mysqli_stmt_get_result($tm_stmt);
                        $members = mysqli_fetch_all($tm_result, MYSQLI_ASSOC);
                        mysqli_stmt_close($tm_stmt);
                    ?>

                    <?php if (count($members) > 0): ?>
                        <div class="overflow-x-auto hide-scrollbar">
                            <table class="w-full text-sm text-left whitespace-nowrap">
                                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200 uppercase text-xs">
                                    <tr>
                                        <th class="p-4">Team Member</th>
                                        <th class="p-4 text-center">Projects</th>
                                        <th class="p-4 text-center">Tasks</th>
                                        <th class="p-4 text-center">Attendance</th>
                                        <th class="p-4 text-center">Score</th>
                                        <th class="p-4 text-center">Grade</th>
                                        <th class="p-4 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($members as $emp): 
                                        $grade = ($emp['performance'] && $emp['performance'] !== 'N/A') ? $emp['performance'] : calculateGrade($emp['performance_score']);
                                        $badgeClass = getBadgeClass($grade);
                                        $emp_img = (!empty($emp['profile_image']) && $emp['profile_image'] !== 'default_user.png') ? $emp['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($emp['full_name']).'&background=random';
                                        
                                        // Package employee data securely for JS
                                        $empData = [
                                            'id' => $emp['id'],
                                            'name' => $emp['full_name'],
                                            'designation' => $emp['designation'],
                                            'image' => $emp_img,
                                            'grade' => $grade,
                                            'score' => rtrim(rtrim($emp['performance_score'], '0'), '.'),
                                            'projects' => $emp['project_completion_rate'],
                                            'tasks' => $emp['task_completion_rate'] ?? 0,
                                            'attendance' => $emp['attendance_rate']
                                        ];
                                        $json_empData = htmlspecialchars(json_encode($empData), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <img src="<?php echo $emp_img; ?>" class="w-10 h-10 rounded-full border border-slate-200 shadow-sm object-cover">
                                                <div>
                                                    <div class="font-bold text-slate-700"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($emp['designation']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center text-slate-600 font-medium"><?php echo $emp['project_completion_rate']; ?>%</td>
                                        <td class="p-4 text-center text-slate-600 font-medium"><?php echo $emp['task_completion_rate'] ?? 0; ?>%</td>
                                        <td class="p-4 text-center text-slate-600 font-medium"><?php echo $emp['attendance_rate']; ?>%</td>
                                        <td class="p-4 text-center">
                                            <span class="font-bold text-slate-800 text-base"><?php echo rtrim(rtrim($emp['performance_score'], '0'), '.'); ?></span><span class="text-xs text-slate-400">/100</span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($grade); ?></span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <button data-emp="<?php echo $json_empData; ?>" class="view-performance-btn inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-800 hover:text-white transition shadow-sm">
                                                <i class="fa-regular fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-6 text-center text-sm text-slate-500 bg-slate-50">
                            <i class="fa-solid fa-users-slash text-2xl text-slate-300 mb-2 block"></i>
                            No team members assigned to this lead currently.
                        </div>
                    <?php endif; ?>
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
                            <h3 class="font-bold text-slate-800">Performance Grade</h3>
                            <span id="m_emp_grade" class="font-bold text-orange-500 text-lg">Average</span>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-6 items-center">
                            <div class="w-32 h-32 flex-shrink-0 relative flex items-center justify-center rounded-full border-8 border-slate-100 mx-auto md:mx-0">
                                <svg class="absolute inset-0 w-full h-full transform -rotate-90">
                                    <circle id="m_score_circle" cx="60" cy="60" r="56" stroke="currentColor" stroke-width="8" fill="transparent" class="text-orange-500 transition-all duration-1000" stroke-dasharray="351" stroke-dashoffset="351"></circle>
                                </svg>
                                <div class="text-center z-10">
                                    <div id="m_emp_score" class="text-3xl font-bold text-slate-800">0</div>
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Score</div>
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
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2"><span>MANAGER</span> <span>10%</span></div>
                                    <div id="m_emp_manager" class="text-xl sm:text-2xl font-bold text-slate-800">70%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 shadow-sm">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-6 border-b border-slate-100 pb-4">
                            <i class="fa-regular fa-comment-dots text-blue-500"></i> Manager's Feedback
                        </h3>
                        
                        <form action="update_review.php" method="POST">
                            <input type="hidden" name="employee_id" id="m_form_emp_id">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                                <div>
                                    <div class="flex justify-between items-end mb-4">
                                        <label class="text-sm font-semibold text-slate-600">Soft Skills Rating (10% Score)</label>
                                        <span id="sliderValue" class="text-2xl font-bold text-slate-800 bg-slate-100 px-3 py-1 rounded-md">70</span>
                                    </div>
                                    <input type="range" name="soft_skills" id="softSkillsSlider" min="0" max="100" value="70" class="w-full appearance-none bg-transparent outline-none mb-2" oninput="document.getElementById('sliderValue').innerText = this.value">
                                    <div class="flex justify-between text-xs text-slate-400 font-medium">
                                        <span>Poor</span>
                                        <span>Excellent</span>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-slate-600 mb-2">Comments</label>
                                    <textarea name="comments" rows="3" class="w-full border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Write feedback here..."></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end pt-4 border-t border-slate-100">
                                <button type="submit" class="w-full sm:w-auto bg-slate-800 text-white font-semibold py-2.5 px-6 rounded-lg shadow hover:bg-slate-700 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-check-circle"></i> Update Review
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

            // Open Modal Event
            viewButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const empData = JSON.parse(this.getAttribute('data-emp'));
                    
                    document.getElementById('m_emp_img').src = empData.image;
                    document.getElementById('m_emp_name').innerText = empData.name;
                    document.getElementById('m_emp_role').innerText = empData.designation;
                    document.getElementById('m_emp_grade').innerText = empData.grade;
                    document.getElementById('m_emp_score').innerText = empData.score;
                    document.getElementById('m_form_emp_id').value = empData.id;

                    document.getElementById('m_emp_projects').innerText = empData.projects + "%";
                    document.getElementById('m_emp_tasks').innerText = empData.tasks + "%";
                    document.getElementById('m_emp_attendance').innerText = empData.attendance + "%";
                    document.getElementById('m_emp_manager').innerText = "70%"; 

                    // SVG Animation
                    let circumference = 351;
                    let offset = circumference - (empData.score / 100) * circumference;
                    
                    let gradeEl = document.getElementById('m_emp_grade');
                    let circleEl = document.getElementById('m_score_circle');
                    
                    gradeEl.className = "font-bold text-lg ";
                    circleEl.className.baseVal = "transition-all duration-1000 "; 
                    
                    if(empData.score >= 90) { gradeEl.classList.add('text-emerald-500'); circleEl.classList.add('text-emerald-500'); }
                    else if(empData.score >= 75) { gradeEl.classList.add('text-blue-500'); circleEl.classList.add('text-blue-500'); }
                    else if(empData.score >= 50) { gradeEl.classList.add('text-orange-500'); circleEl.classList.add('text-orange-500'); }
                    else { gradeEl.classList.add('text-red-500'); circleEl.classList.add('text-red-500'); }

                    modal.classList.remove('hidden');
                    
                    // Trigger animation after a tiny delay
                    setTimeout(() => {
                        circleEl.style.strokeDashoffset = offset;
                    }, 50);
                });
            });

            // Close Modal Events
            const closeModal = () => {
                modal.classList.add('hidden');
                document.getElementById('m_score_circle').style.strokeDashoffset = 351; // reset ring
            };

            closeBtn.addEventListener('click', closeModal);
            
            // Close when clicking outside
            modal.addEventListener('click', (e) => {
                if(e.target === modal || e.target.parentElement === modal) closeModal();
            });
        });
    </script>
</body>
</html>