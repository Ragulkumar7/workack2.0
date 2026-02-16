<?php
// payslip.php

// 1. SESSION & PATH SETUP
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$path_to_root = ''; // Root file
include 'include/db_connect.php'; // Ensure DB connection exists

// 2. MOCK DATA (Since we don't have the DB tables yet)
$employees = [
    ['id' => 1, 'name' => 'Stephan Peralt', 'role' => 'Team Lead'],
    ['id' => 2, 'name' => 'Andrew Jermia', 'role' => 'Project Lead'],
    ['id' => 3, 'name' => 'Doglas Martini', 'role' => 'Product Designer']
];

// Mock Database of Payslips
$payslips = [
    ['id' => 'PAY-008', 'emp' => 'Stephan Peralt', 'month' => 'March 2025', 'amount' => '$4,500', 'status' => 'Pending', 'date' => '2025-03-10'],
    ['id' => 'PAY-007', 'emp' => 'Andrew Jermia', 'month' => 'Feb 2025', 'amount' => '$5,200', 'status' => 'Approved', 'date' => '2025-02-28'],
    ['id' => 'PAY-006', 'emp' => 'Doglas Martini', 'month' => 'Feb 2025', 'amount' => '$3,800', 'status' => 'Rejected', 'date' => '2025-02-28'],
];

// 3. HANDLE VIEW LOGIC
$view = $_GET['view'] ?? 'generate';
$current_role = $_SESSION['role'] ?? 'HR'; // Default for testing
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
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        #mainContent { margin-left: 95px; transition: margin-left 0.3s ease; }
        #mainContent.main-shifted { margin-left: 315px; }
    </style>
</head>
<body class="text-slate-800">

    <?php include 'sidebars.php'; ?>
    <?php include 'header.php'; ?>

    <main id="mainContent" class="p-8 min-h-screen">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Payslip Management</h1>
                <p class="text-sm text-slate-500">Generate, Approve, and Manage Employee Salaries</p>
            </div>
            <div class="flex gap-2">
                <a href="?view=generate" class="px-4 py-2 text-sm font-medium rounded-lg <?php echo $view == 'generate' ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600'; ?>">Generate</a>
                <a href="?view=approvals" class="px-4 py-2 text-sm font-medium rounded-lg <?php echo $view == 'approvals' ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600'; ?>">Approvals</a>
                <a href="?view=history" class="px-4 py-2 text-sm font-medium rounded-lg <?php echo $view == 'history' ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600'; ?>">History</a>
            </div>
        </div>

        <?php if($view == 'generate'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="font-bold text-lg mb-6 flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar text-indigo-500"></i> Generate New Payslip</h2>
                
                <form action="" method="POST" class="space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select Employee</label>
                            <select class="w-full border rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option>Select Employee...</option>
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name']; ?> (<?php echo $emp['role']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Payment Mode</label>
                            <select class="w-full border rounded-lg p-2.5 text-sm outline-none">
                                <option>Bank Transfer</option>
                                <option>Cheque</option>
                                <option>Cash</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-lg border border-slate-100">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-3">Pay Period</label>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" name="period_type" value="month" checked class="text-indigo-600"> Month Wise
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" name="period_type" value="range" class="text-indigo-600"> Date Range
                            </label>
                        </div>
                        
                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs text-slate-400">Month</label>
                                <input type="month" class="w-full border rounded p-2 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-slate-400">Year</label>
                                <select class="w-full border rounded p-2 text-sm">
                                    <option>2025</option>
                                    <option>2026</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t">
                        <button type="button" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700">Cancel</button>
                        <button type="button" onclick="alert('Payslip Generated! Status: Pending Approval sent to Accounts.')" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                            Generate & Send to Accounts
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-sm text-slate-700 mb-4">Recent Transactions</h3>
                <div class="space-y-4">
                    <?php foreach($payslips as $slip): ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-100 transition">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-indigo-50 text-indigo-600 font-bold text-xs">
                            <?php echo substr($slip['emp'], 0, 1); ?>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs font-bold text-slate-800"><?php echo $slip['emp']; ?></p>
                            <p class="text-[10px] text-slate-400"><?php echo $slip['id']; ?> • <?php echo $slip['date']; ?></p>
                        </div>
                        <span class="text-xs font-bold text-slate-700"><?php echo $slip['amount']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($view == 'approvals'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-slate-700">Pending Accounts Approval</h3>
                <span class="bg-orange-100 text-orange-600 px-3 py-1 rounded-full text-xs font-bold">Pending: 1</span>
            </div>
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-4 font-bold border-b">Payslip ID</th>
                        <th class="p-4 font-bold border-b">Employee</th>
                        <th class="p-4 font-bold border-b">Period</th>
                        <th class="p-4 font-bold border-b">Amount</th>
                        <th class="p-4 font-bold border-b">Status</th>
                        <th class="p-4 font-bold border-b text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y">
                    <tr class="hover:bg-slate-50">
                        <td class="p-4 font-medium text-indigo-600">PAY-008</td>
                        <td class="p-4 font-bold text-slate-700">Stephan Peralt</td>
                        <td class="p-4 text-slate-500">March 2025</td>
                        <td class="p-4 font-bold text-slate-800">$4,500</td>
                        <td class="p-4"><span class="bg-orange-100 text-orange-600 px-2 py-1 rounded text-xs font-bold">Pending Accounts</span></td>
                        <td class="p-4 text-right">
                            <button onclick="alert('Approved! Notification sent to HR.')" class="bg-green-500 text-white px-3 py-1.5 rounded text-xs font-bold hover:bg-green-600">Approve</button>
                            <button class="bg-red-100 text-red-500 px-3 py-1.5 rounded text-xs font-bold hover:bg-red-200 ml-2">Reject</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if($view == 'history'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b flex justify-between items-center">
                <h3 class="font-bold text-slate-700">Payslip History</h3>
                <input type="text" placeholder="Search..." class="border rounded px-3 py-1.5 text-sm">
            </div>
            <table class="w-full text-left text-sm divide-y">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Employee</th>
                        <th class="p-4">Month</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payslips as $row): 
                        $statusColor = $row['status'] == 'Approved' ? 'bg-green-100 text-green-600' : ($row['status'] == 'Pending' ? 'bg-orange-100 text-orange-600' : 'bg-red-100 text-red-600');
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-4 text-indigo-600 font-medium"><?php echo $row['id']; ?></td>
                        <td class="p-4 font-bold text-slate-700"><?php echo $row['emp']; ?></td>
                        <td class="p-4 text-slate-500"><?php echo $row['month']; ?></td>
                        <td class="p-4 font-bold"><?php echo $row['amount']; ?></td>
                        <td class="p-4"><span class="<?php echo $statusColor; ?> px-2 py-1 rounded text-xs font-bold"><?php echo $row['status']; ?></span></td>
                        <td class="p-4 text-right">
                            <button class="text-slate-400 hover:text-indigo-600"><i class="fa-solid fa-download"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </main>
</body>
</html>