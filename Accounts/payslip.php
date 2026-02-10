<?php 
include '../sidebars.php'; 
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
            background-color: #1b5a5a;
        }
        .bg-darkteal:hover {
            background-color: #144444;
        }
        /* Printable Area Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            #payslipPrintArea, #payslipPrintArea * {
                visibility: visible;
            }
            #payslipPrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        .payslip-container {
            border: 1px solid #000;
            padding: 20px;
            background: white;
            width: 100%;
            max-width: 800px;
            margin: auto;
        }
    </style>
    <script>
        function checkEmployeeId() {
            const empIdInput = document.getElementById('employeeIdInput').value;
            if (!empIdInput.trim()) {
                alert("Enter Employee ID");
            } else {
                // Show the payslip preview section
                document.getElementById('previewSection').classList.remove('hidden');
                document.getElementById('displayEmpId').innerText = empIdInput;
                console.log("Generating view for: " + empIdInput);
            }
        }

        function validatePrint() {
            const empIdInput = document.getElementById('employeeIdInput').value;
            if (!empIdInput.trim()) {
                alert("Please enter/fetch a valid employee first.");
            } else {
                window.print();
            }
        }

        function reprintSlip(empId, name) {
            document.getElementById('employeeIdInput').value = empId;
            document.getElementById('displayEmpId').innerText = empId;
            document.getElementById('previewSection').classList.remove('hidden');
            window.print();
        }
    </script>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto space-y-6">
        
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 no-print">
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

        <div id="previewSection" class="hidden bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div id="payslipPrintArea" class="payslip-container text-sm">
                <div class="flex justify-between items-center border-b pb-4 mb-4">
                    <img src="../assets/logo.png" alt="Logo" class="h-12">
                    <div class="text-right">
                        <h1 class="text-xl font-bold">NEOERA INFOTECH</h1>
                        <p class="text-xs">9/96 h, post, village nagar, Coimbatore, 641107</p>
                    </div>
                </div>

                <h2 class="text-center font-bold border-b pb-2 mb-4 uppercase">Payslip for the month of February 2026</h2>

                <div class="grid grid-cols-2 gap-y-2 mb-6">
                    <div class="flex justify-between px-2"><span>Employee Code:</span><span id="displayEmpId" class="font-semibold"></span></div>
                    <div class="flex justify-between px-2"><span>First Name:</span><span class="font-semibold">Caro</span></div>
                    <div class="flex justify-between px-2"><span>Designation:</span><span class="font-semibold">App Dev</span></div>
                    <div class="flex justify-between px-2"><span>Department:</span><span class="font-semibold">Dev</span></div>
                    <div class="flex justify-between px-2"><span>Date of Joining:</span><span class="font-semibold">2025-12-05</span></div>
                    <div class="flex justify-between px-2"><span>Bank Account:</span><span class="font-semibold">453678954324</span></div>
                </div>

                <table class="w-full border-collapse border border-black mb-4">
                    <thead>
                        <tr class="border-b border-black">
                            <th class="text-left p-1 border-r border-black">EARNINGS</th>
                            <th class="text-right p-1 border-r border-black">FIXED</th>
                            <th class="text-left p-1 border-r border-black">DEDUCTIONS</th>
                            <th class="text-right p-1">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="p-1 border-r border-black">BASIC</td>
                            <td class="text-right p-1 border-r border-black">50000.00</td>
                            <td class="p-1 border-r border-black">ESI</td>
                            <td class="text-right p-1">0.00</td>
                        </tr>
                        <tr>
                            <td class="p-1 border-r border-black">DA</td>
                            <td class="text-right p-1 border-r border-black">0.00</td>
                            <td class="p-1 border-r border-black">PF AMOUNT</td>
                            <td class="text-right p-1">0.00</td>
                        </tr>
                        <tr class="font-bold border-t border-black">
                            <td class="p-1 border-r border-black">TOTAL GROSS PAY</td>
                            <td class="text-right p-1 border-r border-black">50000.00</td>
                            <td class="p-1 border-r border-black">DEDUCTION TOTAL</td>
                            <td class="text-right p-1">0.00</td>
                        </tr>
                    </tbody>
                </table>
                <div class="font-bold p-2 bg-gray-100 text-center border border-black">
                    NET SALARY: ₹ 50,000.00
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden no-print">
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
                            ['id' => 'IGS4001', 'name' => 'Caro', 'month' => 'Feb 2026', 'salary' => '₹ 50,000.00', 'status' => 'PAID'],
                            ['id' => 'IGS2030', 'name' => 'Aisha', 'month' => 'Jan 2026', 'salary' => '₹ 55,000.00', 'status' => 'PAID'],
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
                                <button onclick="reprintSlip('<?= $slip['id'] ?>', '<?= $slip['name'] ?>')" class="bg-[#1e293b] text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 hover:bg-slate-700 transition-all">
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