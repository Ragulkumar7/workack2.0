<?php
// payroll_salary.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA
$payroll_data = [
    [
        'id' => 'EMP-101', 
        'name' => 'Sarah Jenkins', 
        'role' => 'Frontend Developer', 
        'email' => 'sarah@example.com',
        'join_date' => '12 Jan 2023',
        'current_salary' => 40000, 
        'performance_score' => 92 
    ],
    [
        'id' => 'EMP-102', 
        'name' => 'Mike Ross', 
        'role' => 'Backend Developer', 
        'email' => 'mike@example.com',
        'join_date' => '15 Mar 2023',
        'current_salary' => 35000, 
        'performance_score' => 70 
    ],
    [
        'id' => 'EMP-103', 
        'name' => 'Rachel Zane', 
        'role' => 'UI Designer', 
        'email' => 'rachel@example.com',
        'join_date' => '20 Jun 2023',
        'current_salary' => 38000, 
        'performance_score' => 88 
    ],
    [
        'id' => 'EMP-104', 
        'name' => 'Louis Litt', 
        'role' => 'QA Tester', 
        'email' => 'louis@example.com',
        'join_date' => '01 Feb 2024',
        'current_salary' => 20000, 
        'performance_score' => 45 
    ]
];

// 3. LOGIC: CALCULATE SUGGESTED HIKE
function calculateHike($current_salary, $score) {
    $hike_percent = 0;
    
    if ($score >= 90) {
        $hike_percent = 20; // Excellent
    } elseif ($score >= 75) {
        $hike_percent = 10; // Good
    } elseif ($score >= 60) {
        $hike_percent = 5;  // Average
    }
    
    $hike_amount = ($current_salary * $hike_percent) / 100;
    $new_salary = $current_salary + $hike_amount;
    
    return [
        'percent' => $hike_percent,
        'amount' => $hike_amount,
        'new_total' => $new_salary
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Smart Salary Hike</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        #mainContent { 
            margin-left: 95px; 
            width: calc(100% - 95px); 
            transition: 0.3s; 
            min-height: 100vh;
        }
        #mainContent.main-shifted { 
            margin-left: 315px; 
            width: calc(100% - 315px); 
        }

        .bg-darkteal { background-color: #0d5c63; }
        .hover-darkteal:hover { background-color: #0a494f; }
        .text-darkteal { color: #0d5c63; }
        
        .hidden-element { display: none; }
    </style>
</head>
<body class="text-gray-700">

    <?php include('sidebars.php'); ?>

    <div id="mainContent">

        <header class="h-16 bg-white border-b flex items-center justify-between px-6 sticky top-0 z-40">
            <h1 class="text-xl font-bold text-gray-800">Payroll & Salary Hike</h1>
            <div class="flex items-center gap-4">
                <button class="flex items-center gap-2 bg-darkteal hover-darkteal text-white px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-md">
                    <i class="fa-solid fa-file-export"></i> Export Report
                </button>
            </div>
        </header>

        <main class="p-6">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-xs font-bold text-gray-400 uppercase">Total Monthly Payout</div>
                    <div class="text-2xl font-bold text-gray-800 mt-1">₹1,33,000</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-xs font-bold text-gray-400 uppercase">Pending Appraisals</div>
                    <div class="text-2xl font-bold text-orange-500 mt-1">4 Employees</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-xs font-bold text-gray-400 uppercase">Avg. Performance Score</div>
                    <div class="text-2xl font-bold text-emerald-600 mt-1">73.8%</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Salary Revision List</h3>
                    <div class="text-sm text-gray-500">Based on recent performance review</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-600 font-medium border-y uppercase text-xs">
                            <tr>
                                <th class="px-5 py-4">Employee</th>
                                <th class="px-5 py-4 text-center">Performance Score</th>
                                <th class="px-5 py-4">Current Salary</th>
                                <th class="px-5 py-4">Suggested Hike</th>
                                <th class="px-5 py-4">New Salary</th>
                                <th class="px-5 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($payroll_data as $emp): 
                                $hike_data = calculateHike($emp['current_salary'], $emp['performance_score']);
                                
                                $score_color = 'text-red-500';
                                if($emp['performance_score'] >= 90) $score_color = 'text-emerald-600';
                                elseif($emp['performance_score'] >= 75) $score_color = 'text-blue-600';
                                elseif($emp['performance_score'] >= 60) $score_color = 'text-orange-500';
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-xs">
                                            <?= substr($emp['name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-900"><?= $emp['name'] ?></div>
                                            <div class="text-xs text-gray-400"><?= $emp['role'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-5 py-4 text-center">
                                    <div class="text-lg font-bold <?= $score_color ?>"><?= $emp['performance_score'] ?>%</div>
                                </td>

                                <td class="px-5 py-4 font-medium text-gray-600">
                                    ₹<?= number_format($emp['current_salary']) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <?php if($hike_data['percent'] > 0): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">
                                            +<?= $hike_data['percent'] ?>%
                                        </span>
                                        <div class="text-xs text-green-600 mt-1">+₹<?= number_format($hike_data['amount']) ?></div>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-500 px-2 py-1 rounded text-xs font-bold">No Hike</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-4 font-bold text-gray-800">
                                    ₹<?= number_format($hike_data['new_total']) ?>
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <button onclick="openApproveModal('<?= $emp['name'] ?>', '<?= number_format($hike_data['new_total']) ?>')" class="bg-black text-white px-3 py-1.5 rounded text-xs font-medium hover:bg-gray-800 transition">
                                        Approve Hike
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="approveModal" class="hidden-element fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="toggleModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-check text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Confirm Salary Hike?</h3>
                <p class="text-sm text-gray-500 mt-2">
                    You are about to update the salary for <span id="modalEmpName" class="font-bold text-gray-800"></span> to <span id="modalNewSal" class="font-bold text-emerald-600"></span>.
                </p>
                
                <div class="flex justify-center gap-3 mt-6">
                    <button onclick="toggleModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button onclick="toggleModal(); alert('Salary Updated Successfully!')" class="px-4 py-2 bg-darkteal text-white rounded-lg text-sm font-medium hover:opacity-90">Confirm Update</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleModal() {
            const el = document.getElementById('approveModal');
            el.classList.toggle('hidden-element');
        }

        function openApproveModal(name, salary) {
            document.getElementById('modalEmpName').innerText = name;
            document.getElementById('modalNewSal').innerText = '₹' + salary;
            toggleModal();
        }
    </script>

</body>
</html>