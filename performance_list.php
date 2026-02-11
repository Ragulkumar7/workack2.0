<?php
// manager/performance_list.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// 2. MOCK DATABASE (Employee List with Summary Stats)
// In real app: SELECT * FROM employees
$employees = [
    [
        'id' => 101,
        'name' => 'Sarah Jenkins',
        'role' => 'Frontend Developer',
        'dept' => 'Web Development',
        'img'  => 'https://i.pravatar.cc/150?u=201',
        'score' => 92,
        'grade' => 'Excellent',
        'project_rate' => '100%',
        'attendance' => '98%'
    ],
    [
        'id' => 102,
        'name' => 'Mike Ross',
        'role' => 'Backend Developer',
        'dept' => 'Web Development',
        'img'  => 'https://i.pravatar.cc/150?u=202',
        'score' => 70,
        'grade' => 'Average',
        'project_rate' => '50%',
        'attendance' => '82%'
    ],
    [
        'id' => 103,
        'name' => 'Rachel Zane',
        'role' => 'UI/UX Designer',
        'dept' => 'Design',
        'img'  => 'https://i.pravatar.cc/150?u=203',
        'score' => 88,
        'grade' => 'Good',
        'project_rate' => '85%',
        'attendance' => '95%'
    ],
    [
        'id' => 104,
        'name' => 'Louis Litt',
        'role' => 'QA Tester',
        'dept' => 'Testing',
        'img'  => 'https://i.pravatar.cc/150?u=204',
        'score' => 45,
        'grade' => 'Poor',
        'project_rate' => '30%',
        'attendance' => '60%'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Employee Performance List</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        
        /* Status Badges */
        .badge-excellent { background-color: #d1fae5; color: #065f46; } /* Green */
        .badge-good { background-color: #dbeafe; color: #1e40af; }      /* Blue */
        .badge-average { background-color: #ffedd5; color: #9a3412; }   /* Orange */
        .badge-poor { background-color: #fee2e2; color: #991b1b; }      /* Red */
    </style>
</head>
<body>

    <?php include('./sidebars.php'); ?>

    <div id="mainContent">
        <?php include('header.php'); ?>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Performance Overview</h1>
                <p class="text-sm text-slate-500">Track and manage employee performance ratings</p>
            </div>
            
            <div class="flex gap-3">
                <div class="relative">
                    <input type="text" placeholder="Search employee..." class="pl-10 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <i class="fa-solid fa-search absolute left-3 top-3 text-slate-400 text-xs"></i>
                </div>
                <button class="bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50">
                    <i class="fa-solid fa-filter mr-2"></i> Filter
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200 uppercase text-xs">
                    <tr>
                        <th class="p-4">Employee Name</th>
                        <th class="p-4">Department</th>
                        <th class="p-4 text-center">Projects</th>
                        <th class="p-4 text-center">Attendance</th>
                        <th class="p-4 text-center">Overall Score</th>
                        <th class="p-4 text-center">Grade</th>
                        <th class="p-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($employees as $emp): 
                        // Determine Badge Color based on Grade
                        $badgeClass = '';
                        switch($emp['grade']) {
                            case 'Excellent': $badgeClass = 'badge-excellent'; break;
                            case 'Good': $badgeClass = 'badge-good'; break;
                            case 'Average': $badgeClass = 'badge-average'; break;
                            default: $badgeClass = 'badge-poor';
                        }
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo $emp['img']; ?>" class="w-10 h-10 rounded-full border border-slate-200 shadow-sm">
                                <div>
                                    <div class="font-bold text-slate-700"><?php echo $emp['name']; ?></div>
                                    <div class="text-xs text-slate-500"><?php echo $emp['role']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-slate-600 font-medium"><?php echo $emp['dept']; ?></td>
                        <td class="p-4 text-center text-slate-600"><?php echo $emp['project_rate']; ?></td>
                        <td class="p-4 text-center text-slate-600"><?php echo $emp['attendance']; ?></td>
                        
                        <td class="p-4 text-center">
                            <span class="font-bold text-slate-800 text-base"><?php echo $emp['score']; ?></span><span class="text-xs text-slate-400">/100</span>
                        </td>
                        
                        <td class="p-4 text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $badgeClass; ?>">
                                <?php echo $emp['grade']; ?>
                            </span>
                        </td>
                        
                        <td class="p-4 text-center">
                            <a href="performance.php?view=details&id=<?php echo $emp['id']; ?>" class="inline-flex items-center gap-2 bg-slate-800 text-white px-4 py-1.5 rounded-lg text-xs font-medium hover:bg-orange-500 transition shadow-sm">
                                <i class="fa-regular fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>