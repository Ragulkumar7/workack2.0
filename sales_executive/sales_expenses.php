<?php
ob_start();
// Include your existing layout files
include '../sidebars.php';
include '../header.php';

// Mock Data for the Expenses Table - Updated to Rupee Symbol 
$expenses = [
    ['name' => 'Online Course', 'date' => '14 Jan 2024', 'method' => 'Cash', 'amount' => '₹3000'],
    ['name' => 'Employee Benefits', 'date' => '21 Jan 2024', 'method' => 'Cash', 'amount' => '₹2500'],
    ['name' => 'Travel', 'date' => '20 Feb 2024', 'method' => 'Cheque', 'amount' => '₹2800'],
    ['name' => 'Office Supplies', 'date' => '15 Mar 2024', 'method' => 'Cash', 'amount' => '₹3300'],
    ['name' => 'Welcome Kit', 'date' => '12 Apr 2024', 'method' => 'Cheque', 'amount' => '₹3600'],
    ['name' => 'Equipment', 'date' => '20 Apr 2024', 'method' => 'Cheque', 'amount' => '₹2000'],
    ['name' => 'Miscellaneous', 'date' => '06 Jul 2024', 'method' => 'Cash', 'amount' => '₹3400'],
    ['name' => 'Payroll', 'date' => '02 Sep 2024', 'method' => 'Cheque', 'amount' => '₹4000'],
    ['name' => 'Cafeteria', 'date' => '15 Nov 2024', 'method' => 'Cash', 'amount' => '₹4500'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses | Workack</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
        }
        
        /* Layout wrapper to prevent overlap with fixed sidebar */
        .main-content { 
            margin-left: 95px; 
            padding: 30px; 
            width: calc(100% - 95px); 
            box-sizing: border-box; 
        }
        @media (max-width: 992px) { 
            .main-content { margin-left: 0; width: 100%; padding-top: 80px; } 
        }

        /* Custom Checkbox Styling */
        .custom-checkbox {
            appearance: none;
            background-color: #f1f5f9;
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.15em;
            height: 1.15em;
            border: 1px solid #cbd5e1;
            border-radius: 0.25em;
            display: grid;
            place-content: center;
            cursor: pointer;
        }
        .custom-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
            background-color: transform;
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        .custom-checkbox:checked {
            background-color: #1b5a5a;
            border-color: #1b5a5a;
        }
        .custom-checkbox:checked::before {
            transform: scale(1);
        }

        /* Table Styles */
        .table-row-hover:hover { background-color: #f8fafc; }
        
        /* Outline removal for select elements */
        select:focus, input:focus { outline: none; border-color: #1b5a5a; box-shadow: 0 0 0 1px #1b5a5a20; }
    </style>
</head>
<body class="text-slate-800">

    <div class="main-content">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Expenses</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                    <i data-lucide="home" class="w-3 h-3"></i>
                    <span>></span>
                    <span>Sales</span>
                    <span>></span>
                    <span class="text-slate-800 font-medium">Expenses</span>
                </nav>
            </div>
            
            <div class="flex gap-3 relative">
                <div>
                    <button onclick="toggleExportMenu()" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm flex items-center gap-2 hover:bg-gray-50 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i> Export <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                    </button>
                    <div id="exportMenu" class="hidden absolute top-full left-0 mt-2 w-48 bg-white border border-gray-100 rounded-lg shadow-lg z-50 overflow-hidden">
                        <button onclick="exportData('pdf')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 transition-colors border-b border-gray-50">
                            <i data-lucide="file-text" class="w-4 h-4"></i> Export as PDF
                        </button>
                        <button onclick="exportData('excel')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 transition-colors">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Export as Excel
                        </button>
                    </div>
                </div>

                <button onclick="document.getElementById('addExpenseModal').classList.remove('hidden')" class="px-4 py-2 bg-[#1b5a5a] text-white rounded-lg text-sm font-semibold shadow-sm flex items-center gap-2 hover:bg-[#134040] transition-colors">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Add New Expenses
                </button>
                <button class="p-2 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 transition-colors">
                    <i data-lucide="chevrons-up-down" class="w-4 h-4 text-gray-600"></i>
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <h2 class="font-bold text-lg text-slate-800">Expenses List</h2>
                
                <div class="flex flex-wrap gap-3">
                    <div class="relative">
                        <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        <input type="text" id="dateRangePicker" value="02/19/2026 - 02/25/2026" class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 w-[220px] bg-white cursor-pointer" readonly>
                    </div>
                    
                    <div class="relative">
                        <select class="pl-4 pr-8 py-2 border border-gray-200 rounded-lg text-sm text-gray-800 font-medium bg-white appearance-none cursor-pointer w-[140px] shadow-sm">
                            <option>₹0.00 - ₹00</option>
                            <option>₹3000</option>
                            <option>₹2500</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#1b5a5a] pointer-events-none font-bold"></i>
                    </div>

                    <div class="relative">
                        <select class="pl-4 pr-8 py-2 border border-gray-200 rounded-lg text-sm text-gray-800 font-medium bg-white appearance-none cursor-pointer w-[160px] shadow-sm">
                            <option>Sort By : Last 7 Days</option>
                            <option>Recently Added</option>
                            <option>Ascending</option>
                            <option>Descending</option>
                            <option>Last Month</option>
                            <option>Last 7 Days</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#1b5a5a] pointer-events-none font-bold"></i>
                    </div>
                </div>
            </div>

            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span>Row Per Page</span>
                    <select class="border border-gray-200 rounded px-2 py-1 bg-white">
                        <option>10</option>
                    </select>
                    <span>Entries</span>
                </div>
                <div class="relative w-64">
                    <input type="text" placeholder="Search" class="w-full pl-4 pr-4 py-1.5 border border-gray-200 rounded text-sm placeholder-gray-400">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-600 font-semibold">
                            <th class="p-4 w-12 text-center">
                                <input type="checkbox" class="custom-checkbox">
                            </th>
                            <th class="p-4">
                                <div class="flex items-center gap-2 cursor-pointer hover:text-slate-800">
                                    Expense Name <i data-lucide="arrow-up-down" class="w-3 h-3 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="p-4">
                                <div class="flex items-center gap-2 cursor-pointer hover:text-slate-800">
                                    Date <i data-lucide="arrow-up-down" class="w-3 h-3 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="p-4">
                                <div class="flex items-center gap-2 cursor-pointer hover:text-slate-800">
                                    Payment Method <i data-lucide="arrow-up-down" class="w-3 h-3 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="p-4">
                                <div class="flex items-center gap-2 cursor-pointer hover:text-slate-800">
                                    Amount <i data-lucide="arrow-up-down" class="w-3 h-3 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="p-4 text-center">
                                <i data-lucide="arrow-up-down" class="w-3 h-3 text-gray-400 mx-auto"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr class="border-b border-gray-50 text-sm text-gray-600 table-row-hover transition-colors">
                            <td class="p-4 text-center">
                                <input type="checkbox" class="custom-checkbox">
                            </td>
                            <td class="p-4 font-medium text-slate-700"><?php echo htmlspecialchars($expense['name']); ?></td>
                            <td class="p-4"><?php echo htmlspecialchars($expense['date']); ?></td>
                            <td class="p-4"><?php echo htmlspecialchars($expense['method']); ?></td>
                            <td class="p-4 font-medium text-slate-800"><?php echo htmlspecialchars($expense['amount']); ?></td>
                            <td class="p-4">
                                <div class="flex items-center justify-center gap-3">
                                    <button class="text-gray-400 hover:text-blue-600 transition-colors" title="Edit">
                                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div id="addExpenseModal" class="fixed inset-0 bg-slate-900/40 z-50 hidden flex items-center justify-center backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
            <div class="flex justify-between items-center p-6 pb-4">
                <h3 class="text-lg font-bold text-slate-800">Add Expenses</h3>
                <button type="button" onclick="document.getElementById('addExpenseModal').classList.add('hidden')" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-full transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 pt-2">
                <form>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Expenses</label>
                            <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a] transition-shadow" placeholder="">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Date</label>
                                <div class="relative">
                                    <input type="text" class="w-full pl-4 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a] transition-shadow" placeholder="dd/mm/yyyy">
                                    <i data-lucide="calendar" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Amount</label>
                                <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a] transition-shadow" placeholder="">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Payment Method</label>
                            <div class="relative">
                                <select class="w-full pl-4 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a] appearance-none bg-white transition-shadow cursor-pointer">
                                    <option value="">Select</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Online">Online Transfer</option>
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Attach Proof</label>
                            <input type="file" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a] bg-white transition-shadow text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-[#1b5a5a] file:text-white hover:file:bg-[#134040]">
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-8">
                        <button type="button" onclick="document.getElementById('addExpenseModal').classList.add('hidden')" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-[#1b5a5a] text-white rounded-lg text-sm font-semibold hover:bg-[#134040] transition-colors shadow-sm">
                            Add Expenses
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Initialize Flatpickr for Date Range picker logic
        flatpickr("#dateRangePicker", {
            mode: "range",
            dateFormat: "m/d/Y",
            onChange: function(selectedDates, dateStr, instance) {
                console.log("Selected Date Range: ", dateStr);
            }
        });

        // Toggle Export Menu visibility
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside of the menu
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('exportMenu');
            const exportBtn = menu.previousElementSibling;
            if (!menu.contains(event.target) && !exportBtn.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });

        // Placeholder function for Export actions
        function exportData(type) {
            alert('Exporting data as ' + type.toUpperCase() + '...');
            document.getElementById('exportMenu').classList.add('hidden');
        }
    </script>
</body>
</html>