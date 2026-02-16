<?php 
include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Salary Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f1f5f9; /* Slate-100 */
        }

        /* --- CRITICAL LAYOUT FIX FOR SIDEBAR --- */
        #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px;
            min-height: 100vh;
        }
        /* When Secondary Sidebar Opens */
        #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Theme Colors (Dark Teal Custom) */
        .bg-darkteal { background-color: #144d4d; }
        .hover-darkteal:hover { background-color: #115e59; }
        .text-darkteal { color: #144d4d; }
        .border-darkteal { border-color: #144d4d; }
        
        /* Custom Utility Classes */
        .hidden-element { display: none; }
        .dropdown-shadow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); }
        
        /* Table Styling */
        .table-container { border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td { font-size: 0.875rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        tr:hover td { background: #f8fafc; }
        
        /* Form Inputs */
        select, input {
            border-color: #e2e8f0;
        }
        select:focus, input:focus {
            border-color: #144d4d;
            outline: none;
            box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1);
        }
    </style>
</head>
<body class="text-gray-700">

    <main id="mainContent">
        
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Employee Salary</h1>
                <nav class="flex text-xs text-gray-400 mt-1 gap-2 items-center">
                </nav>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <button onclick="toggleElement('exportDropdown')" class="flex items-center gap-2 bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                        <i class="fa-solid fa-file-export text-gray-400"></i> Export <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i>
                    </button>
                    <div id="exportDropdown" class="hidden-element absolute right-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 overflow-hidden">
                        <div class="py-1">
                            <button class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-file-pdf text-red-500"></i>
                                <span class="font-medium">Export as PDF</span>
                            </button>
                            <button class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-slate-50 transition-colors border-t border-gray-50">
                                <i class="fa-solid fa-file-excel text-green-600"></i>
                                <span class="font-medium">Export as Excel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <button onclick="toggleElement('salaryModal')" class="flex items-center gap-2 bg-darkteal hover-darkteal text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-lg" style="box-shadow: 0 4px 6px -1px rgba(20, 77, 77, 0.2);">
                    <i class="fa-solid fa-circle-plus"></i> Add Salary
                </button>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            
            <!-- Filters Row -->
            <div class="p-5 border-b flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                <h3 class="font-semibold text-gray-800">Employee Salary List</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <button onclick="toggleElement('datePickerDropdown')" class="flex items-center gap-2 border border-gray-200 rounded-xl px-4 py-2 bg-white text-sm hover:border-gray-300 transition">
                            <i class="fa-regular fa-calendar text-gray-400"></i>
                            <span class="text-gray-600 font-medium">02/04/2026 - 02/10/2026</span>
                        </button>
                        <div id="datePickerDropdown" class="hidden-element absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 py-1 overflow-hidden">
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Today</button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Yesterday</button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-white bg-darkteal font-medium">Last 7 Days</button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Last 30 Days</button>
                        </div>
                    </div>
                    
                    <div class="relative">
                        <button onclick="toggleElement('designationDropdown')" class="border border-gray-200 rounded-xl px-4 py-2 text-sm bg-white flex items-center gap-2 hover:border-gray-300 transition">
                            Designation <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </button>
                        <div id="designationDropdown" class="hidden-element absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 py-2">
                            <button class="w-full text-left px-5 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Finance</button>
                            <button class="w-full text-left px-5 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Developer</button>
                            <button class="w-full text-left px-5 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Executive</button>
                            <button class="w-full text-left px-5 py-2.5 text-sm text-gray-700 hover:bg-slate-50">Manager</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Pagination Controls -->
            <div class="px-5 py-4 flex items-center justify-between bg-white border-b border-gray-100">
                <div class="text-sm text-gray-500 flex items-center gap-2">
                    Show
                    <select class="border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none text-xs">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                    entries
                </div>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" placeholder="Search employees..." class="border border-gray-200 rounded-lg py-2 pl-9 pr-4 text-sm w-64">
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="px-5 py-4 w-10"><input type="checkbox" class="rounded text-teal-600"></th>
                            <th class="px-4 py-4">Emp ID <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                            <th class="px-4 py-4">Name <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                            <th class="px-4 py-4">Email <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                            <th class="px-4 py-4">Phone <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                            <th class="px-4 py-4">Designation</th>
                            <th class="px-4 py-4 text-nowrap">Joining Date <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                            <th class="px-4 py-4">Salary <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                            <th class="px-4 py-4 text-center">Payslip</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $employees = [
                            ['id' => 'Emp-001', 'name' => 'Anthony Lewis', 'dept' => 'Finance', 'email' => 'anthony@example.com', 'phone' => '(123) 4567 890', 'desig' => 'Finance', 'join' => '12 Sep 2024', 'salary' => '$40,000'],
                            ['id' => 'Emp-002', 'name' => 'Brian Villalobos', 'dept' => 'Developer', 'email' => 'brian@example.com', 'phone' => '(179) 7382 829', 'desig' => 'Developer', 'join' => '24 Oct 2024', 'salary' => '$35,000'],
                            ['id' => 'Emp-003', 'name' => 'Harvey Smith', 'dept' => 'Developer', 'email' => 'harvey@example.com', 'phone' => '(184) 2719 738', 'desig' => 'Executive', 'join' => '18 Feb 2024', 'salary' => '$20,000'],
                            ['id' => 'Emp-004', 'name' => 'Stephan Peralt', 'dept' => 'Executive Officer', 'email' => 'peralt@example.com', 'phone' => '(193) 7839 748', 'desig' => 'Executive', 'join' => '17 Oct 2024', 'salary' => '$22,000'],
                            ['id' => 'Emp-005', 'name' => 'Doglas Martini', 'dept' => 'Manager', 'email' => 'martniwr@example.com', 'phone' => '(183) 9302 890', 'desig' => 'Manager', 'join' => '20 Jul 2024', 'salary' => '$25,000'],
                        ];

                        foreach ($employees as $emp): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4"><input type="checkbox" class="rounded text-teal-600"></td>
                            <td class="px-4 py-4 font-medium text-gray-500"><?= $emp['id'] ?></td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($emp['name']) ?>&background=144d4d&color=fff" class="w-9 h-9 rounded-full">
                                    <div>
                                        <div class="font-bold text-gray-900"><?= $emp['name'] ?></div>
                                        <div class="text-xs text-gray-400 font-medium"><?= $emp['dept'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-gray-500"><?= $emp['email'] ?></td>
                            <td class="px-4 py-4 text-gray-500"><?= $emp['phone'] ?></td>
                            <td class="px-4 py-4">
                                <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium">
                                    <?= $emp['desig'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-gray-500"><?= $emp['join'] ?></td>
                            <td class="px-4 py-4 font-bold text-slate-800"><?= $emp['salary'] ?></td>
                            <td class="px-4 py-4 text-center">
                                <button class="bg-darkteal text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-800 transition-all shadow-sm">
                                    Generate Slip
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Salary Modal -->
    <div id="salaryModal" class="hidden-element fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="toggleElement('salaryModal')"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden">
                <div class="px-6 py-5 border-b flex items-center justify-between bg-slate-50">
                    <h2 class="text-xl font-bold text-gray-800">Add Employee Salary</h2>
                    <button onclick="toggleElement('salaryModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-circle-xmark text-2xl"></i>
                    </button>
                </div>

                <form class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 mb-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Employee Name</label>
                            <div class="relative">
                                <select class="w-full border border-gray-200 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-teal-500 outline-none appearance-none text-gray-500">
                                    <option>Select Employee</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-gray-400 text-xs"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Net Salary</label>
                            <input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-teal-500 outline-none" placeholder="$0.00">
                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-5 border-b pb-2">
                            <h3 class="font-bold text-gray-800">Earnings</h3>
                            <button type="button" class="text-darkteal font-bold text-sm flex items-center gap-1 hover:opacity-80">
                                <i class="fa-solid fa-plus text-xs"></i> Add New
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-4">
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">Basic</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">DA(40%)</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">HRA(15%)</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">Conveyance</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-5 border-b pb-2">
                            <h3 class="font-bold text-gray-800">Deductions</h3>
                            <button type="button" class="text-darkteal font-bold text-sm flex items-center gap-1 hover:opacity-80">
                                <i class="fa-solid fa-plus text-xs"></i> Add New
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-4">
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">TDS</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">ESI</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">PF</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                            <div><label class="text-sm font-semibold text-gray-700 mb-2 block">Prof.Tax</label><input type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 outline-none"></div>
                        </div>
                    </div>

                    <div class="flex justify-center gap-4 pt-4 border-t">
                        <button type="button" onclick="toggleElement('salaryModal')" class="px-8 py-3 border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors min-w-[140px]">
                            Cancel
                        </button>
                        <button type="submit" class="px-8 py-3 bg-darkteal hover-darkteal text-white rounded-xl text-sm font-bold shadow-md transition-all min-w-[200px]">
                            Save Salary
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleElement(id) {
            const el = document.getElementById(id);
            const isHidden = el.classList.contains('hidden-element');
            
            // Close other dropdowns if opening a new one
            if(id.includes('Dropdown') && isHidden) {
                document.querySelectorAll('[id$="Dropdown"]').forEach(d => {
                    if (d.id !== id) d.classList.add('hidden-element');
                });
            }

            el.classList.toggle('hidden-element');
            
            // Prevent body scroll when modal is open
            if (id === 'salaryModal') {
                document.body.style.overflow = el.classList.contains('hidden-element') ? 'auto' : 'hidden';
            }
        }

        // Close dropdowns when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('[id$="Dropdown"]').forEach(d => d.classList.add('hidden-element'));
            }
        });
    </script>
</body>
</html>