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
    </style>
</head>
<body class="text-gray-700">

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

    <main class="p-6 max-w-[1600px] mx-auto">
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
                <button class="flex items-center gap-2 bg-white border border-gray-200 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                    <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>
                <button class="flex items-center gap-2 bg-darkteal hover-darkteal text-white px-5 py-2 rounded-lg text-sm font-medium transition-all shadow-md">
                    <i class="fa-solid fa-circle-plus"></i> Add Salary
                </button>
                <button class="p-2 border rounded-lg bg-white text-gray-400"><i class="fa-solid fa-chevron-up"></i></button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b flex flex-wrap items-center justify-between gap-4">
                <h3 class="font-semibold text-gray-800">Employee Salary List</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 border rounded-lg px-3 py-2 bg-gray-50 text-sm">
                        <i class="fa-regular fa-calendar text-gray-400"></i>
                        <span>02/03/2026 - 02/09/2026</span>
                    </div>
                    <select class="border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none">
                        <option>Designation</option>
                    </select>
                    <select class="border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none">
                        <option>Sort By: Last 7 Days</option>
                    </select>
                </div>
            </div>

            <div class="px-5 py-4 flex items-center justify-between bg-white">
                <div class="text-sm text-gray-500">
                    Row Per Page 
                    <select class="mx-1 border rounded px-1 py-1 focus:outline-none">
                        <option>10</option>
                        <option>25</option>
                    </select>
                    Entries
                </div>
                <div class="relative">
                    <input type="text" placeholder="Search" class="border border-gray-200 rounded-lg py-1.5 pl-3 pr-8 text-sm focus:outline-none">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 font-medium border-y">
                        <tr>
                            <th class="px-5 py-4 w-10"><input type="checkbox" class="rounded text-teal-600"></th>
                            <th class="px-4 py-4">Emp ID <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                            <th class="px-4 py-4">Name <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                            <th class="px-4 py-4">Email <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                            <th class="px-4 py-4">Phone <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                            <th class="px-4 py-4">Designation <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                            <th class="px-4 py-4 text-nowrap">Joining Date <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                            <th class="px-4 py-4">Salary <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
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
                                <select class="border rounded-md px-2 py-1 text-xs focus:outline-none">
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

    <div class="fixed right-0 top-1/2 transform -translate-y-1/2 bg-darkteal p-2 rounded-l-md cursor-pointer text-white shadow-lg">
        <i class="fa-solid fa-gear animate-spin-slow"></i>
    </div>

</body>
</html>