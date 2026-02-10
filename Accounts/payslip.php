<?php 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .bg-darkteal {
            background-color: #0d5a5e;
        }
        .bg-darkteal:hover {
            background-color: #0a4649;
        }
    </style>
    <script>
        function checkEmployeeId() {
            const empIdInput = document.getElementById('employeeIdInput').value;
            if (!empIdInput.trim()) {
                alert("Enter Employee ID");
            } else {
                console.log("Generating view for: " + empIdInput);
            }
        }

        function validatePrint() {
            const empIdInput = document.getElementById('employeeIdInput').value;
            if (!empIdInput.trim()) {
                alert("Please enter/fetch a valid employee first.");
            } else {
                console.log("Printing payslip for: " + empIdInput);
            }
        }
    </script>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto space-y-6">
        
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-end gap-4">
                <div class="flex-grow">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Employee ID</label>
                    <input type="text" 
                           id="employeeIdInput"
                           placeholder="Enter Employee ID (e.g. IGS2919)" 
                           class="w-full md:max-w-md p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:outline-none text-gray-600">
                </div>
                <div class="flex gap-3">
                    <button onclick="checkEmployeeId()" class="bg-darkteal text-white px-6 py-3 rounded-xl font-semibold flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-bolt-lightning text-sm"></i>
                        Generate View
                    </button>
                    <button onclick="validatePrint()" class="bg-darkteal text-white px-6 py-3 rounded-xl font-semibold flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-print text-sm"></i>
                        Print Payslip
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-50">
                <h2 class="text-xl font-bold text-[#1e293b] flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                    Recently Generated Payslips
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-8 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Emp ID</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Net Salary</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $payslips = [
                            ['id' => 'IGS4001', 'name' => 'Caro', 'month' => '', 'salary' => '₹ 50,000.00', 'status' => 'PAID'],
                            ['id' => 'IGS2030', 'name' => 'Aisha', 'month' => '', 'salary' => '₹ 55,000.00', 'status' => 'PAID'],
                            ['id' => 'IGS2028', 'name' => 'Bishop', 'month' => '', 'salary' => '₹ 75,000.00', 'status' => 'PAID'],
                            ['id' => 'IGS2002', 'name' => 'Gunther', 'month' => 'July', 'salary' => '₹ 41,666.67', 'status' => 'PAID'],
                            ['id' => 'NIT001', 'name' => 'RK', 'month' => 'march 2026', 'salary' => '₹ 21,000.00', 'status' => 'PAID'],
                            ['id' => 'NIT001', 'name' => 'RK', 'month' => 'feb 2026', 'salary' => '₹ 13,500.00', 'status' => 'PAID'],
                            ['id' => 'IGS6666', 'name' => 'Dorothy', 'month' => 'march 2026', 'salary' => '₹ 21,080.00', 'status' => 'PAID'],
                        ];

                        foreach ($payslips as $slip): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-8 py-5 font-bold text-[#1e293b] text-sm"><?= $slip['id'] ?></td>
                            <td class="px-6 py-5 text-gray-600 text-sm"><?= $slip['name'] ?></td>
                            <td class="px-6 py-5 text-gray-500 text-sm"><?= $slip['month'] ?></td>
                            <td class="px-6 py-5 font-bold text-[#1e293b] text-sm"><?= $slip['salary'] ?></td>
                            <td class="px-6 py-5">
                                <span class="bg-green-100 text-green-600 px-3 py-1 rounded text-[10px] font-bold tracking-widest">
                                    <?= $slip['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                <button class="bg-[#1e293b] text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 hover:bg-slate-700 transition-all">
                                    <i class="fa-solid fa-print text-[10px]"></i>
                                    Reprint
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>