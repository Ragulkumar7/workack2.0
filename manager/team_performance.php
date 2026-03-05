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
        case 'poor': return 'bg-rose-100 text-rose-800';
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
    ORDER BY tl_name ASC
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
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        /* Hide Scrollbar for clean tables */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body>

    <?php include('../sidebars.php'); ?>

    <div id="mainContent">
        <?php include('../header.php'); ?>
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Team Performance Overview</h1>
                <p class="text-sm text-slate-500 font-medium mt-1">Track and manage Team Leads and their respective members</p>
            </div>
            
            <div class="w-full md:w-auto">
                <div class="relative w-full md:w-72">
                    <input type="text" id="searchInput" onkeyup="filterTeamLeads()" placeholder="Search Team Lead..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-slate-200 focus:border-slate-300 shadow-sm transition-all text-slate-700 font-medium">
                    <i class="fa-solid fa-search absolute left-3.5 top-3.5 text-slate-400"></i>
                </div>
            </div>
        </div>

        <?php if (empty($team_leads)): ?>
            <div class="bg-white p-10 rounded-2xl border border-slate-200 text-center text-slate-500 shadow-sm flex flex-col items-center">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3">
                    <i class="fa-solid fa-users text-2xl text-slate-300"></i>
                </div>
                <h3 class="font-bold text-slate-700">No Team Leads Found</h3>
                <p class="text-sm mt-1">There are no active Team Leads in the system.</p>
            </div>
        <?php else: ?>
            
            <div id="tlContainer">
            <?php foreach ($team_leads as $tl): ?>
                
                <div class="tl-card bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8 transition-all hover:shadow-md">
                    
                    <div class="bg-[#1e293b] p-5 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <?php 
                                // Beautiful Avatar Logic matching the image
                                $tl_img_src = $tl['tl_img'];
                                if(empty($tl_img_src) || $tl_img_src === 'default_user.png') {
                                    $tl_img_src = 'https://ui-avatars.com/api/?name='.urlencode($tl['tl_name'] ?? 'User').'&background=06b6d4&color=fff&bold=true';
                                } elseif (!str_starts_with($tl_img_src, 'http') && strpos($tl_img_src, 'assets/profiles/') === false) {
                                    $tl_img_src = '../assets/profiles/' . $tl_img_src; 
                                }
                            ?>
                            <img src="<?php echo $tl_img_src; ?>" class="w-[52px] h-[52px] rounded-full border-2 border-white shadow-md object-cover bg-white">
                            <div>
                                <h2 class="tl-name font-bold text-white text-lg tracking-wide flex items-center gap-2">
                                    <?php echo htmlspecialchars($tl['tl_name']); ?> 
                                    <span class="text-[11px] font-medium text-slate-400 bg-slate-800/50 px-2 py-0.5 rounded-full border border-slate-700">(Team Lead)</span>
                                </h2>
                                <div class="text-[13px] text-slate-300 mt-0.5 font-medium">
                                    <?php echo htmlspecialchars(($tl['tl_role'] ?? 'Role N/A') . ' • ' . ($tl['tl_dept'] ?? 'Dept N/A')); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                        // STRICT SQL FILTER: Ensure we NEVER fetch other Managers or Team Leads!
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
                            JOIN users u ON ep.user_id = u.id
                            LEFT JOIN employee_performance per ON ep.user_id = per.user_id
                            WHERE ep.reporting_to = ? 
                            AND u.role NOT IN ('Team Lead', 'Manager', 'System Admin', 'HR', 'HR Executive')
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
                                <thead class="bg-white text-slate-500 font-bold border-b border-slate-200 uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th class="p-5 pl-6">Team Member</th>
                                        <th class="p-5 text-center">Projects</th>
                                        <th class="p-5 text-center">Tasks</th>
                                        <th class="p-5 text-center">Attendance</th>
                                        <th class="p-5 text-center">Score</th>
                                        <th class="p-5 text-center">Grade</th>
                                        <th class="p-5 text-center pr-6">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($members as $emp): 
                                        $grade = ($emp['performance'] && $emp['performance'] !== 'N/A' && $emp['performance'] !== 'Pending') ? $emp['performance'] : calculateGrade($emp['performance_score']);
                                        $badgeClass = getBadgeClass($grade);
                                        
                                        // Team Member Beautiful Initials/Avatar Generator
                                        $emp_img = $emp['profile_image'];
                                        if(empty($emp_img) || $emp_img === 'default_user.png') {
                                            // Matching the solid vibrant colors from the UI reference image
                                            $emp_img = 'https://ui-avatars.com/api/?name='.urlencode($emp['full_name'] ?? 'User').'&background=random&color=fff&bold=true';
                                        } elseif (!str_starts_with($emp_img, 'http') && strpos($emp_img, 'assets/profiles/') === false) {
                                            $emp_img = '../assets/profiles/' . $emp_img; 
                                        }
                                        
                                        // Package data for JS Modal
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
                                    <tr class="hover:bg-slate-50/70 transition-colors duration-200">
                                        <td class="p-4 pl-6">
                                            <div class="flex items-center gap-4">
                                                <img src="<?php echo $emp_img; ?>" class="w-[42px] h-[42px] rounded-full shadow-sm object-cover bg-slate-100 border border-slate-200">
                                                <div>
                                                    <div class="font-bold text-slate-800 text-[14px]"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                    <div class="text-[12px] text-slate-500 font-medium mt-0.5"><?php echo htmlspecialchars($emp['designation']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center text-slate-600 font-semibold"><?php echo $emp['project_completion_rate']; ?>%</td>
                                        <td class="p-4 text-center text-slate-600 font-semibold"><?php echo $emp['task_completion_rate'] ?? 0; ?>%</td>
                                        <td class="p-4 text-center text-slate-600 font-semibold"><?php echo $emp['attendance_rate']; ?>%</td>
                                        <td class="p-4 text-center">
                                            <span class="font-black text-slate-800 text-[15px]"><?php echo rtrim(rtrim($emp['performance_score'], '0'), '.'); ?></span><span class="text-[11px] font-bold text-slate-400">/100</span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="px-3 py-1 rounded-md text-[11px] font-black tracking-wide <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($grade); ?></span>
                                        </td>
                                        <td class="p-4 text-center pr-6">
                                            <button data-emp="<?php echo $json_empData; ?>" class="view-performance-btn inline-flex items-center gap-1.5 bg-white border border-slate-200 text-slate-600 px-4 py-1.5 rounded-lg text-[11px] font-bold hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 transition shadow-sm">
                                                <i class="fa-regular fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-10 text-center bg-white flex flex-col items-center justify-center">
                            <div class="text-slate-300 mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-400">No team members assigned to this lead currently.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="perfModal" class="fixed inset-0 z-[100] hidden bg-slate-900 bg-opacity-60 overflow-y-auto backdrop-blur-sm transition-opacity">
        <div class="flex items-center justify-center min-h-screen p-4 sm:p-6">
            <div class="bg-slate-50 rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden relative">
                
                <div class="bg-white p-4 sm:p-6 border-b border-slate-200 flex justify-between items-center sticky top-0 z-10">
                    <div class="flex items-center gap-4">
                        <img id="m_emp_img" src="" class="w-12 h-12 sm:w-14 sm:h-14 rounded-full shadow-sm border border-slate-200 object-cover bg-white">
                        <div>
                            <h2 id="m_emp_name" class="text-lg sm:text-xl font-bold text-slate-800 leading-tight">Employee Name</h2>
                            <p id="m_emp_role" class="text-xs sm:text-sm text-slate-500 font-medium">Designation • Department</p>
                        </div>
                    </div>
                    <button id="closeModalBtn" class="bg-slate-100 w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 mb-6 shadow-sm">
                        <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
                            <h3 class="font-bold text-slate-800 tracking-wide">Performance Grade</h3>
                            <span id="m_emp_grade" class="font-black text-orange-500 text-lg uppercase tracking-wider">Average</span>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-8 items-center">
                            <div class="w-32 h-32 flex-shrink-0 relative flex items-center justify-center rounded-full border-8 border-slate-100 mx-auto md:mx-0">
                                <svg class="absolute inset-0 w-full h-full transform -rotate-90">
                                    <circle id="m_score_circle" cx="60" cy="60" r="56" stroke="currentColor" stroke-width="8" fill="transparent" class="text-orange-500 transition-all duration-1000" stroke-dasharray="351" stroke-dashoffset="351"></circle>
                                </svg>
                                <div class="text-center z-10">
                                    <div id="m_emp_score" class="text-3xl font-black text-slate-800 tracking-tight">0</div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Score</div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full">
                                <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 shadow-sm">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2 tracking-wide"><span>PROJECTS</span> <span>40%</span></div>
                                    <div id="m_emp_projects" class="text-xl sm:text-2xl font-black text-slate-800">0%</div>
                                </div>
                                <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 shadow-sm">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2 tracking-wide"><span>TASKS</span> <span>30%</span></div>
                                    <div id="m_emp_tasks" class="text-xl sm:text-2xl font-black text-slate-800">0%</div>
                                </div>
                                <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 shadow-sm">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2 tracking-wide"><span>ATTENDANCE</span> <span>20%</span></div>
                                    <div id="m_emp_attendance" class="text-xl sm:text-2xl font-black text-slate-800">0%</div>
                                </div>
                                <div class="border border-slate-100 rounded-xl p-4 bg-slate-50 shadow-sm">
                                    <div class="flex justify-between text-[10px] sm:text-xs text-slate-400 font-bold mb-2 tracking-wide"><span>MANAGER</span> <span>10%</span></div>
                                    <div id="m_emp_manager" class="text-xl sm:text-2xl font-black text-slate-800">70%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // --- Search Filter Logic ---
        function filterTeamLeads() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const tlCards = document.querySelectorAll('.tl-card');
            
            tlCards.forEach(card => {
                const tlName = card.querySelector('.tl-name').innerText.toLowerCase();
                
                if (tlName.includes(searchInput)) {
                    card.style.display = ''; 
                } else {
                    card.style.display = 'none'; 
                }
            });
        }

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

                    document.getElementById('m_emp_projects').innerText = empData.projects + "%";
                    document.getElementById('m_emp_tasks').innerText = empData.tasks + "%";
                    document.getElementById('m_emp_attendance').innerText = empData.attendance + "%";
                    document.getElementById('m_emp_manager').innerText = "70%"; 

                    // SVG Animation
                    let circumference = 351;
                    let offset = circumference - (empData.score / 100) * circumference;
                    
                    let gradeEl = document.getElementById('m_emp_grade');
                    let circleEl = document.getElementById('m_score_circle');
                    
                    gradeEl.className = "font-black text-lg uppercase tracking-wider ";
                    circleEl.className.baseVal = "transition-all duration-1000 "; 
                    
                    if(empData.score >= 90) { gradeEl.classList.add('text-emerald-500'); circleEl.classList.add('text-emerald-500'); }
                    else if(empData.score >= 75) { gradeEl.classList.add('text-blue-500'); circleEl.classList.add('text-blue-500'); }
                    else if(empData.score >= 50) { gradeEl.classList.add('text-orange-500'); circleEl.classList.add('text-orange-500'); }
                    else { gradeEl.classList.add('text-rose-500'); circleEl.classList.add('text-rose-500'); }

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