<?php
// payslip.php

// 1. SESSION & PATH SETUP
if (session_status() === PHP_SESSION_NONE) { session_start(); }
 $path_to_root = ''; 
include 'include/db_connect.php'; 

// 2. MOCK DATA
 $employees = [
    ['id' => 1, 'name' => 'Stephan Peralt', 'role' => 'Team Lead', 'avatar' => 'https://i.pravatar.cc/150?u=1'],
    ['id' => 2, 'name' => 'Andrew Jermia', 'role' => 'Project Lead', 'avatar' => 'https://i.pravatar.cc/150?u=2'],
    ['id' => 3, 'name' => 'Doglas Martini', 'role' => 'Product Designer', 'avatar' => 'https://i.pravatar.cc/150?u=3'],
    ['id' => 4, 'name' => 'John Doe', 'role' => 'Backend Developer', 'avatar' => 'https://i.pravatar.cc/150?u=4'],
];

 $payslips = [
    ['id' => 'PAY-008', 'emp_id' => 1, 'emp' => 'Stephan Peralt', 'month' => 'March 2025', 'amount' => '$4,500', 'status' => 'Pending', 'date' => '2025-03-10'],
    ['id' => 'PAY-007', 'emp_id' => 2, 'emp' => 'Andrew Jermia', 'month' => 'Feb 2025', 'amount' => '$5,200', 'status' => 'Approved', 'date' => '2025-02-28'],
    ['id' => 'PAY-006', 'emp_id' => 3, 'emp' => 'Doglas Martini', 'month' => 'Feb 2025', 'amount' => '$3,800', 'status' => 'Rejected', 'date' => '2025-02-28'],
];

 $view = $_GET['view'] ?? 'generate';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        
        /* Sidebar Layout Logic */
        #mainContent { margin-left: 95px; transition: margin-left 0.3s ease; width: calc(100% - 95px); }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Custom Select Styling */
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        /* Print Styles for Payslip */
        @media print {
            body * { visibility: hidden; }
            #payslipPrintArea, #payslipPrintArea * { visibility: visible; }
            #payslipPrintArea { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body class="text-slate-800">

    <?php include 'sidebars.php'; ?>
    <?php include 'header.php'; ?>

    <main id="mainContent" class="p-6 lg:p-8 min-h-screen">
        
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Payslip Management</h1>
                <nav class="flex text-sm text-gray-500 mt-2">
                    <ol class="inline-flex items-center space-x-2">
                    </ol>
                </nav>
            </div>
            <div class="flex gap-2 bg-white p-1.5 rounded-xl shadow-sm border border-gray-100">
                <a href="?view=generate" class="px-5 py-2 text-sm font-semibold rounded-lg transition <?php echo $view == 'generate' ? 'bg-teal-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50'; ?>">
                    <i class="fa-solid fa-plus mr-1"></i> Generate
                </a>
                <a href="?view=approvals" class="px-5 py-2 text-sm font-semibold rounded-lg transition <?php echo $view == 'approvals' ? 'bg-teal-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50'; ?>">
                    <i class="fa-solid fa-clock mr-1"></i> Approvals
                </a>
                <a href="?view=history" class="px-5 py-2 text-sm font-semibold rounded-lg transition <?php echo $view == 'history' ? 'bg-teal-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50'; ?>">
                    <i class="fa-solid fa-history mr-1"></i> History
                </a>
            </div>
        </div>

        <!-- Notification Toast Container -->
        <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2"></div>

        <!-- Content Area -->
        <?php if($view == 'generate'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover-card overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                        <h2 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-file-invoice-dollar text-teal-600"></i> Generate New Payslip
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">Fill in the details below to generate a salary slip.</p>
                    </div>
                    
                    <form id="generateForm" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select Employee <span class="text-red-500">*</span></label>
                                <select id="employeeSelect" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none bg-white" required>
                                    <option value="" disabled selected>Choose an employee...</option>
                                    <?php foreach($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" data-avatar="<?php echo $emp['avatar']; ?>">
                                            <?php echo $emp['name']; ?> - <?php echo $emp['role']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Payment Mode</label>
                                <select class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none bg-white">
                                    <option>Bank Transfer</option>
                                    <option>Cheque</option>
                                    <option>Cash</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-5 bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-3">Pay Period Configuration</label>
                            <div class="flex flex-wrap items-center gap-6 mb-4">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" name="period_type" value="month" checked class="w-4 h-4 text-teal-600 focus:ring-teal-500">
                                    <span class="font-medium text-slate-700">Month Wise</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" name="period_type" value="range" class="w-4 h-4 text-teal-600 focus:ring-teal-500">
                                    <span class="font-medium text-slate-700">Custom Range</span>
                                </label>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-slate-500 font-medium">Month <span class="text-red-500">*</span></label>
                                    <input type="month" id="payMonth" class="w-full border border-gray-200 rounded-xl p-2.5 text-sm mt-1" required>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 font-medium">Year</label>
                                    <select class="w-full border border-gray-200 rounded-xl p-2.5 text-sm mt-1">
                                        <option>2025</option>
                                        <option>2026</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <button type="button" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-lg transition">Cancel</button>
                            <button type="submit" class="bg-teal-600 text-white px-8 py-2.5 rounded-lg text-sm font-bold hover:bg-teal-700 transition shadow-lg shadow-teal-200 flex items-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i> Generate & Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover-card">
                    <h3 class="font-bold text-sm text-slate-700 mb-4 flex items-center justify-between">
                        Recent Transactions
                        <span class="text-[10px] bg-slate-100 px-2 py-1 rounded-full font-bold">Today</span>
                    </h3>
                    <div class="space-y-3">
                        <?php foreach(array_slice($payslips, 0, 3) as $slip): 
                            $empData = array_filter($employees, fn($e) => $e['name'] == $slip['emp']);
                            $empAvatar = $empData ? reset($empData)['avatar'] : 'https://i.pravatar.cc/150?u=default';
                        ?>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition cursor-pointer border border-transparent hover:border-gray-100">
                            <img src="<?php echo $empAvatar; ?>" class="w-10 h-10 rounded-full object-cover">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate"><?php echo $slip['emp']; ?></p>
                                <p class="text-[10px] text-gray-400"><?php echo $slip['id']; ?> • <?php echo $slip['date']; ?></p>
                            </div>
                            <span class="text-sm font-bold text-teal-600"><?php echo $slip['amount']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-indigo-500 to-purple-600 p-6 rounded-2xl text-white shadow-lg">
                    <h3 class="font-bold text-sm mb-2">Quick Stats</h3>
                    <p class="text-[10px] opacity-80 mb-4">Total Payroll Processed this month</p>
                    <p class="text-3xl font-black">$135,000</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($view == 'approvals'): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover-card">
            <div class="p-6 border-b flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gradient-to-r from-slate-50 to-white">
                <div>
                    <h3 class="font-bold text-lg text-slate-800">Pending Accounts Approval</h3>
                    <p class="text-xs text-gray-500 mt-1">Review and approve generated payslips</p>
                </div>
                <span class="bg-orange-100 text-orange-600 px-4 py-1.5 rounded-full text-xs font-bold shadow-sm">Pending: 1</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 border-b border-gray-100">
                        <tr>
                            <th class="p-5 font-bold">Payslip ID</th>
                            <th class="p-5 font-bold">Employee</th>
                            <th class="p-5 font-bold">Period</th>
                            <th class="p-5 font-bold">Amount</th>
                            <th class="p-5 font-bold">Status</th>
                            <th class="p-5 font-bold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr class="hover:bg-slate-50 transition border-b border-gray-50">
                            <td class="p-5 font-bold text-teal-600">PAY-008</td>
                            <td class="p-5">
                                <div class="flex items-center gap-3">
                                    <img src="https://i.pravatar.cc/150?u=1" class="w-8 h-8 rounded-full">
                                    <span class="font-medium text-slate-800">Stephan Peralt</span>
                                </div>
                            </td>
                            <td class="p-5 text-slate-500">March 2025</td>
                            <td class="p-5 font-bold text-slate-800">$4,500</td>
                            <td class="p-5"><span class="bg-orange-100 text-orange-600 px-3 py-1 rounded-full text-xs font-bold">Pending</span></td>
                            <td class="p-5 text-right space-x-2">
                                <button onclick="showNotification('success', 'Payslip Approved', 'The payslip has been finalized and sent.')" class="bg-teal-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-700 transition shadow-sm">
                                    <i class="fa-solid fa-check mr-1"></i> Approve
                                </button>
                                <button onclick="showNotification('error', 'Payslip Rejected', 'Returned to HR for correction.')" class="bg-red-50 text-red-500 px-4 py-2 rounded-lg text-xs font-bold hover:bg-red-100 transition border border-red-100">
                                    <i class="fa-solid fa-times mr-1"></i> Reject
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if($view == 'history'): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover-card">
            <div class="p-6 border-b flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="font-bold text-lg text-slate-800">Payslip History</h3>
                    <p class="text-xs text-gray-500 mt-1">View and download past transactions</p>
                </div>
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" placeholder="Search ID or Name..." class="border border-gray-200 rounded-lg pl-8 pr-4 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none w-64">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-gray-100">
                        <tr>
                            <th class="p-5 font-bold">ID</th>
                            <th class="p-5 font-bold">Employee</th>
                            <th class="p-5 font-bold">Month</th>
                            <th class="p-5 font-bold">Amount</th>
                            <th class="p-5 font-bold">Status</th>
                            <th class="p-5 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payslips as $row): 
                            $statusColor = $row['status'] == 'Approved' ? 'bg-green-100 text-green-600' : ($row['status'] == 'Pending' ? 'bg-orange-100 text-orange-600' : 'bg-red-100 text-red-600');
                        ?>
                        <tr class="hover:bg-slate-50 transition border-b border-gray-50">
                            <td class="p-5 text-teal-600 font-bold"><?php echo $row['id']; ?></td>
                            <td class="p-5 font-medium text-slate-800"><?php echo $row['emp']; ?></td>
                            <td class="p-5 text-slate-500"><?php echo $row['month']; ?></td>
                            <td class="p-5 font-bold text-slate-800"><?php echo $row['amount']; ?></td>
                            <td class="p-5"><span class="<?php echo $statusColor; ?> px-3 py-1 rounded-full text-xs font-bold"><?php echo $row['status']; ?></span></td>
                            <td class="p-5 text-right">
                                <button onclick="downloadPayslip('<?php echo $row['id']; ?>', '<?php echo $row['emp']; ?>', '<?php echo $row['month']; ?>', '<?php echo $row['amount']; ?>')" class="bg-teal-50 text-teal-600 hover:bg-teal-100 transition px-4 py-2 rounded-lg text-xs font-bold border border-teal-100">
                                    <i class="fa-solid fa-download mr-1"></i> Download
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Hidden Template for Print -->
    <div id="payslipPrintArea" style="display: none;">
        <div style="padding: 40px; font-family: 'Inter', sans-serif; color: #333;">
            <div style="border-bottom: 2px solid #0d9488; padding-bottom: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h1 style="color: #0d9488; margin: 0; font-size: 24px; font-weight: 700;">PAYSLIP</h1>
                <div style="text-align: right;">
                    <h2 style="margin: 0; font-size: 18px; font-weight: 700;" id="print-company">Workack Technologies</h2>
                    <p style="margin: 0; font-size: 12px; color: #666;">123 Business Avenue, Tech Park</p>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px;">
                <div>
                    <p style="margin: 0; font-size: 12px; color: #64748b; font-weight: 600;">EMPLOYEE NAME</p>
                    <p style="margin: 5px 0 0; font-size: 16px; font-weight: 700;" id="print-emp-name">-</p>
                </div>
                <div>
                    <p style="margin: 0; font-size: 12px; color: #64748b; font-weight: 600;">PAY SLIP ID</p>
                    <p style="margin: 5px 0 0; font-size: 16px; font-weight: 700;" id="print-id">-</p>
                </div>
                <div>
                    <p style="margin: 0; font-size: 12px; color: #64748b; font-weight: 600;">PAY PERIOD</p>
                    <p style="margin: 5px 0 0; font-size: 16px; font-weight: 700;" id="print-month">-</p>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <thead>
                    <tr style="background: #0d9488; color: white;">
                        <th style="padding: 12px; text-align: left; font-size: 12px;">EARNINGS</th>
                        <th style="padding: 12px; text-align: right; font-size: 12px;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px; color: #334155;">Basic Salary</td>
                        <td style="padding: 12px; text-align: right; font-weight: 600;" id="print-amount">-</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px; color: #334155;">House Rent Allowance (HRA)</td>
                        <td style="padding: 12px; text-align: right; font-weight: 600;">$500.00</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px; color: #334155;">Special Allowance</td>
                        <td style="padding: 12px; text-align: right; font-weight: 600;">$200.00</td>
                    </tr>
                </tbody>
            </table>

            <div style="background: #0d9488; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 700; font-size: 14px;">NET PAY</span>
                <span style="font-weight: 800; font-size: 20px;" id="print-total">-</span>
            </div>

            <div style="margin-top: 50px; text-align: center; color: #94a3b8; font-size: 10px;">
                <p>Generated via Workack HRMS. This is a computer generated document.</p>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JS for dynamic usage
        const payslipData = <?php echo json_encode($payslips); ?>;

        // 1. Form Validation
        document.getElementById('generateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const empSelect = document.getElementById('employeeSelect');
            const payMonth = document.getElementById('payMonth');

            if (!empSelect.value) {
                showNotification('error', 'Validation Error', 'Please select an employee.');
                return;
            }
            if (!payMonth.value) {
                showNotification('error', 'Validation Error', 'Please select a pay month.');
                return;
            }

            // Success Logic
            showNotification('success', 'Payslip Generated', 'Request has been sent to accounts for approval.');
            // Optionally reset form
            // this.reset();
        });

        // 2. Download Functionality
        function downloadPayslip(id, name, month, amount) {
            // Populate the hidden template
            document.getElementById('print-id').innerText = id;
            document.getElementById('print-emp-name').innerText = name;
            document.getElementById('print-month').innerText = month;
            document.getElementById('print-amount').innerText = amount;
            document.getElementById('print-total').innerText = amount;

            // Clone the content
            const content = document.getElementById('payslipPrintArea').innerHTML;

            // Open a new window
            const printWindow = window.open('', '_blank');
            
            // Write necessary HTML and styles to the new window
            printWindow.document.write(`
                <html>
                <head>
                    <title>Payslip ${id}</title>
                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                    <style>
                        body { font-family: 'Inter', sans-serif; -webkit-print-color-adjust: exact; }
                    </style>
                </head>
                <body>
                    ${content}
                </body>
                </html>
            `);

            printWindow.document.close();
            
            // Trigger print dialog
            setTimeout(() => {
                printWindow.print();
                // printWindow.close(); // Uncomment if you want to close automatically
            }, 500);
        }

        // 3. Toast Notification System
        function showNotification(type, title, message) {
            const container = document.getElementById('toast-container');
            
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-white border-l-4 border-teal-500' : 'bg-white border-l-4 border-red-500';
            const iconColor = type === 'success' ? 'text-teal-500' : 'text-red-500';
            const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';

            toast.className = `p-4 rounded-xl shadow-lg border border-gray-100 flex items-start gap-3 min-w-[320px] transform transition-all duration-300 translate-x-full opacity-0 ${bgColor}`;
            toast.innerHTML = `
                <div class="mt-0.5"><i class="fa-solid ${icon} ${iconColor} text-lg"></i></div>
                <div>
                    <h4 class="font-bold text-sm text-gray-800">${title}</h4>
                    <p class="text-xs text-gray-500 mt-1">${message}</p>
                </div>
            `;

            container.appendChild(toast);

            // Animate In
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            });

            // Remove after 4 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => { toast.remove(); }, 300);
            }, 4000);
        }
    </script>
</body>
</html>