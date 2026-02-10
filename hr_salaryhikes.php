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
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .bg-darkteal { background-color: #0d5c63; }
        .hover-darkteal:hover { background-color: #0a494f; }
        .text-darkteal { color: #0d5c63; }
        
        /* Modal & Dropdown Utilities */
        .hidden-element { display: none; }
        .dropdown-shadow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="text-gray-700">
    <?php include 'sidebars.php'; ?>

    <div class="flex flex-col ml-20">

        <header class="h-16 bg-white border-b flex items-center justify-between px-6 sticky top-0 z-50">
            <div class="flex items-center gap-4">
                <button class="p-2 hover:bg-gray-100 rounded-lg"><i class="fa-solid fa-bars-staggered"></i></button>
                <div class="relative hidden md:block">
                    <input type="text" placeholder="Search in HRMS" class="bg-gray-50 border border-gray-200 rounded-lg py-2 pl-10 pr-4 w-64 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    <div class="absolute right-2 top-2 text-[10px] text-gray-400 border rounded px-1 bg-white">CTRL + /</div>
                </div>
                <i class="fa-solid fa-table-cells-large text-gray-500 cursor-pointer ml-2"></i>
                <i class="fa-solid fa-gear text-gray-500 cursor-pointer"></i>
            </div>

            <div class="flex items-center gap-5 text-gray-500">
                <i class="fa-solid fa-expand cursor-pointer hover:text-teal-600"></i>
                <i class="fa-solid fa-grid-horizontal cursor-pointer hover:text-teal-600"></i>
                <i class="fa-regular fa-comment-dots cursor-pointer hover:text-teal-600"></i>
                <i class="fa-regular fa-envelope cursor-pointer hover:text-teal-600"></i>
                <div class="relative">
                    <i class="fa-regular fa-bell cursor-pointer hover:text-teal-600"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 w-2 h-2 rounded-full border border-white"></span>
                </div>
                <div class="flex items-center gap-2 cursor-pointer border-l pl-4">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=0d5c63&color=fff" class="w-8 h-8 rounded-full shadow-sm">
                </div>
            </div>
        </header>

        <main class="p-6 max-w-[1600px]">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Employee Salary</h1>
                    <nav class="flex text-xs text-gray-400 mt-1 gap-2">
                        <i class="fa-solid fa-house"></i>
                        <span>/</span>
                        <span>Payroll</span>
                        <span>/</span>
                        <span class="text-gray-600 font-medium">Employee Salary</span>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <button onclick="toggleElement('exportDropdown')" class="flex items-center gap-2 bg-white border border-gray-200 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                            <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i>
                        </button>
                        <div id="exportDropdown" class="hidden-element absolute right-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 overflow-hidden">
                            <div class="py-1">
                                <button class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fa-solid fa-file-pdf text-gray-600"></i>
                                    <span class="font-medium">Export as PDF</span>
                                </button>
                                <button class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors border-t border-gray-50">
                                    <i class="fa-solid fa-file-excel text-gray-600"></i>
                                    <span class="font-medium">Export as Excel</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button onclick="toggleElement('salaryModal')" class="flex items-center gap-2 bg-darkteal hover-darkteal text-white px-5 py-2 rounded-lg text-sm font-medium transition-all shadow-md">
                        <i class="fa-solid fa-circle-plus"></i> Add Salary
                    </button>
                    <button class="p-2 border rounded-lg bg-white text-gray-400"><i class="fa-solid fa-chevron-up"></i></button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b flex flex-wrap items-center justify-between gap-4">
                    <h3 class="font-semibold text-gray-800">Employee Salary List</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative">
                            <button onclick="toggleElement('datePickerDropdown')" class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 bg-white text-sm hover:bg-gray-50">
                                <i class="fa-regular fa-calendar text-gray-400"></i>
                                <span class="text-gray-600">02/04/2026 - 02/10/2026</span>
                            </button>
                            <div id="datePickerDropdown" class="hidden-element absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 py-1 overflow-hidden">
                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Today</button>
                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Yesterday</button>
                                <button class="w-full text-left px-4 py-2 text-sm text-white bg-darkteal font-medium">Last 7 Days</button>
                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Last 30 Days</button>
                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">This Year</button>
                                <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Next Year</button>
                                <div class="border-t border-gray-100 mt-1 pt-1">
                                    <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Custom Range</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <button onclick="toggleElement('designationDropdown')" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white flex items-center gap-2">
                                Designation <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </button>
                            <div id="designationDropdown" class="hidden-element absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 py-2">
                                <button class="w-full text-left px-5 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Finance</button>
                                <button class="w-full text-left px-5 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Developer</button>
                                <button class="w-full text-left px-5 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Executive</button>
                                <button class="w-full text-left px-5 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Manager</button>
                            </div>
                        </div>

                        <div class="relative">
                            <button onclick="toggleElement('sortDropdown')" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white flex items-center gap-2 min-w-[180px] justify-between">
                                <span>Sort By : Last 7 Days</span>
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </button>
                            <div id="sortDropdown" class="hidden-element absolute right-0 mt-2 w-56 bg-white border border-gray-100 rounded-xl dropdown-shadow z-50 py-2">
                                <button class="w-full text-left px-6 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Recently Added</button>
                                <button class="w-full text-left px-6 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Ascending</button>
                                <button class="w-full text-left px-6 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Descending</button>
                                <button class="w-full text-left px-6 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50">Last Month</button>
                                <button class="w-full text-left px-6 py-2.5 text-[15px] text-gray-700 hover:bg-gray-50 font-medium">Last 7 Days</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 flex items-center justify-between bg-white">
                    <div class="text-sm text-gray-500">
                        Row Per Page
                        <select class="mx-1 border border-gray-200 rounded px-1 py-1 focus:outline-none">
                            <option>10</option>
                            <option>25</option>
                        </select>
                        Entries
                    </div>
                    <div class="relative">
                        <input type="text" placeholder="Search" class="border border-gray-200 rounded-lg py-1.5 pl-3 pr-8 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-600 font-medium border-y">
                            <tr>
                                <th class="px-5 py-4 w-10"><input type="checkbox" class="rounded text-teal-600"></th>
                                <th class="px-4 py-4">Emp ID <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4">Name <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4">Email <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4">Phone <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4">Designation <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4 text-nowrap">Joining Date <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4">Salary <i class="fa-solid fa-sort ml-1 opacity-30 text-[10px]"></i></th>
                                <th class="px-4 py-4 text-center">Payslip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $employees = [
                                ['id' => 'Emp-001', 'name' => 'Anthony Lewis', 'dept' => 'Finance', 'email' => 'anthony@example.com', 'phone' => '(123) 4567 890', 'desig' => 'Finance', 'join' => '12 Sep 2024', 'salary' => '$40,000'],
                                ['id' => 'Emp-002', 'name' => 'Brian Villalobos', 'dept' => 'Developer', 'email' => 'brian@example.com', 'phone' => '(179) 7382 829', 'desig' => 'Developer', 'join' => '24 Oct 2024', 'salary' => '$35,000'],
                                ['id' => 'Emp-003', 'name' => 'Harvey Smith', 'dept' => 'Developer', 'email' => 'harvey@example.com', 'phone' => '(184) 2719 738', 'desig' => 'Executive', 'join' => '18 Feb 2024', 'salary' => '$20,000'],
                                ['id' => 'Emp-004', 'name' => 'Stephan Peralt', 'dept' => 'Executive Officer', 'email' => 'peralt@example.com', 'phone' => '(193) 7839 748', 'desig' => 'Executive', 'join' => '17 Oct 2024', 'salary' => '$22,000'],
                                ['id' => 'Emp-005', 'name' => 'Doglas Martini', 'dept' => 'Manager', 'email' => 'martniwr@example.com', 'phone' => '(183) 9302 890', 'desig' => 'Manager', 'join' => '20 Jul 2024', 'salary' => '$25,000'],
                            ];

                            foreach ($employees as $emp): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4"><input type="checkbox" class="rounded text-teal-600"></td>
                                <td class="px-4 py-4 font-medium text-gray-500"><?= $emp['id'] ?></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($emp['name']) ?>&background=random" class="w-9 h-9 rounded-full">
                                        <div>
                                            <div class="font-bold text-gray-900"><?= $emp['name'] ?></div>
                                            <div class="text-xs text-gray-400 font-medium"><?= $emp['dept'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-500"><?= $emp['email'] ?></td>
                                <td class="px-4 py-4 text-gray-500"><?= $emp['phone'] ?></td>
                                <td class="px-4 py-4">
                                    <select class="border border-gray-200 rounded-md px-2 py-1 text-xs focus:outline-none">
                                        <option <?= $emp['desig'] == 'Finance' ? 'selected' : '' ?>>Finance</option>
                                        <option <?= $emp['desig'] == 'Developer' ? 'selected' : '' ?>>Developer</option>
                                        <option <?= $emp['desig'] == 'Executive' ? 'selected' : '' ?>>Executive</option>
                                        <option <?= $emp['desig'] == 'Manager' ? 'selected' : '' ?>>Manager</option>
                                    </select>
                                </td>
                                <td class="px-4 py-4 text-gray-500"><?= $emp['join'] ?></td>
                                <td class="px-4 py-4 font-semibold text-gray-800"><?= $emp['salary'] ?></td>
                                <td class="px-4 py-4 text-center">
                                    <button class="bg-gray-800 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-black transition-all">
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
    </div>

    <div id="salaryModal" class="hidden-element fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="toggleElement('salaryModal')"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden">
                <div class="px-6 py-5 border-b flex items-center justify-between">
                    <h2 class="text-xl font-bold text-[#1f2937]">Add Employee Salary</h2>
                    <button onclick="toggleElement('salaryModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-circle-xmark text-2xl"></i>
                    </button>
                </div>

                <form class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 mb-8">
                        <div>
                            <label class="block text-[15px] font-semibold text-gray-700 mb-2.5">Employee Name</label>
                            <div class="relative">
                                <select class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white focus:ring-1 focus:ring-teal-500 outline-none appearance-none text-gray-500">
                                    <option>Select</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-gray-400 text-xs"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[15px] font-semibold text-gray-700 mb-2.5">Net Salary</label>
                            <input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:ring-1 focus:ring-teal-500 outline-none">
                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="font-bold text-gray-800 text-lg">Earnings</h3>
                            <button type="button" class="text-darkteal font-bold text-[15px] flex items-center gap-1 hover:opacity-80">
                                <i class="fa-solid fa-plus text-xs"></i> Add New
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-4">
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Basic</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">DA(40%)</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">HRA(15%)</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Conveyance</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Allowance</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Medical Allowance</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Others</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                        </div>
                    </div>

                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="font-bold text-gray-800 text-lg">Deductions</h3>
                            <button type="button" class="text-darkteal font-bold text-[15px] flex items-center gap-1 hover:opacity-80">
                                <i class="fa-solid fa-plus text-xs"></i> Add New
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-4">
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">TDS</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">ESI</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">PF</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Leave</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Prof.Tax</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Labour Welfare</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                            <div><label class="text-[15px] font-semibold text-gray-700 mb-2 block">Others</label><input type="text" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 outline-none focus:border-teal-500"></div>
                        </div>
                    </div>

                    <div class="flex justify-center gap-4 mb-4">
                        <button type="button" onclick="toggleElement('salaryModal')" class="px-8 py-3 border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors min-w-[140px]">
                            Cancel
                        </button>
                        <button type="submit" class="px-8 py-3 bg-darkteal hover-darkteal text-white rounded-lg text-sm font-bold shadow-sm transition-all min-w-[200px]">
                            Add Employee Salary
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
            
            if(id.includes('Dropdown') && isHidden) {
                document.querySelectorAll('[id$="Dropdown"]').forEach(d => {
                    if (d.id !== id) d.classList.add('hidden-element');
                });
            }

            el.classList.toggle('hidden-element');
            
            if (id === 'salaryModal') {
                document.body.style.overflow = el.classList.contains('hidden-element') ? 'auto' : 'hidden';
            }
        }

        window.onclick = function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('[id$="Dropdown"]').forEach(d => d.classList.add('hidden-element'));
            }
        }
    </script>
</body>
</html>