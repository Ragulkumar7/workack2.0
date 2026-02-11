<?php
$employeeName = "Adrian";
$currentDateRange = "01/31/2026 - 02/06/2026";

$attendanceRecords = [
    ["date" => "02 Sep 2024", "checkin" => "09:12 AM", "status" => "Present", "checkout" => "09:17 PM", "break" => "14 Min", "late" => "12 Min", "overtime" => "-", "production" => "8.35Hrs", "color" => "green"],
    ["date" => "06 Jul 2024", "checkin" => "09:00 AM", "status" => "Present", "checkout" => "07:13 PM", "break" => "32 Min", "late" => "-", "overtime" => "75 Min", "production" => "9.15 Hrs", "color" => "blue"],
    ["date" => "10 Dec 2024", "checkin" => "-", "status" => "Absent", "checkout" => "-", "break" => "-", "late" => "-", "overtime" => "-", "production" => "0.00 Hrs", "color" => "red"],
    ["date" => "12 Apr 2024", "checkin" => "09:00 AM", "status" => "Present", "checkout" => "06:43 PM", "break" => "23 Min", "late" => "-", "overtime" => "10 Min", "production" => "8.22 Hrs", "color" => "green"],
    ["date" => "14 Jan 2024", "checkin" => "09:32 AM", "status" => "Present", "checkout" => "06:45 PM", "break" => "30 Min", "late" => "32 Min", "overtime" => "20 Min", "production" => "8.55 Hrs", "color" => "green"]
];

// --- PATH LOGIC FOR INCLUDES ---
// This ensures sidebars and header are found even if this file is in a subfolder
$path_to_root = file_exists('sidebars.php') ? '' : '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Employee Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; }
        .card { background: white; border-radius: 8px; border: 1px solid #e2e8f0; }
        .modal-active { display: flex !important; }
        select { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%223%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%3E%3C%2Fpath%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 0.75rem center; padding-right: 2rem !important; }

        /* --- Layout Fix for Sidebar & Header --- */
        #mainContent {
            margin-left: 95px; /* Matches Sidebar Width */
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column; /* Stacks Header on top of Content */
        }
        /* Logic handled by sidebars.php JS */
        #mainContent.main-shifted { margin-left: 315px; }
    </style>
</head>
<body>

    <?php include $path_to_root . 'sidebars.php'; ?>

    <main id="mainContent">
        
        <div class="flex-shrink-0 w-full z-40 sticky top-0">
            <?php include $path_to_root . 'header.php'; ?>
        </div>

        <div class="p-6 flex-grow overflow-y-auto">
            
            <div id="reportModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b">
                        <h2 class="text-2xl font-bold">Attendance</h2>
                        <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-xl"></i></button>
                    </div>
                    <div class="p-8">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8 bg-slate-50 p-6 rounded-lg border">
                            <div><p class="text-slate-500 text-sm">Date</p><p class="font-bold">15 Apr 2025</p></div>
                            <div><p class="text-slate-500 text-sm">Punch in at</p><p class="font-bold">09:00 AM</p></div>
                            <div><p class="text-slate-500 text-sm">Punch out at</p><p class="font-bold">06:45 PM</p></div>
                            <div><p class="text-slate-500 text-sm">Status</p><p class="font-bold">Present</p></div>
                        </div>
                        <div class="grid grid-cols-4 gap-4 mb-8">
                            <div><p class="text-slate-400 text-xs">Total Working hours</p><p class="text-xl font-bold">12h 36m</p></div>
                            <div><p class="text-slate-400 text-xs">Productive Hours</p><p class="text-xl font-bold text-emerald-500">08h 36m</p></div>
                            <div><p class="text-slate-400 text-xs">Break hours</p><p class="text-xl font-bold text-amber-500">22m 15s</p></div>
                            <div><p class="text-slate-400 text-xs">Overtime</p><p class="text-xl font-bold text-blue-500">02h 15m</p></div>
                        </div>
                        <div class="h-8 w-full bg-slate-100 rounded-full flex overflow-hidden mb-4">
                            <div style="width: 15%"></div>
                            <div class="h-full bg-emerald-500" style="width: 15%"></div>
                            <div class="h-full bg-amber-400 mx-1" style="width: 5%"></div>
                            <div class="h-full bg-emerald-500" style="width: 30%"></div>
                            <div class="h-full bg-amber-400 mx-1" style="width: 10%"></div>
                            <div class="h-full bg-blue-500" style="width: 10%"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400 px-1">
                            <span>06:00</span><span>09:00</span><span>12:00</span><span>03:00</span><span>06:00</span><span>09:00</span><span>11:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Employee Attendance</h1>
                    <p class="text-sm text-slate-500 flex items-center gap-2 mt-1">
                        <i class="fa fa-home"></i> <i class="fa fa-chevron-right text-[10px]"></i> Attendance <i class="fa fa-chevron-right text-[10px]"></i> <span class="text-slate-700">Employee Attendance</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button class="bg-white border px-4 py-2 rounded text-sm text-slate-600 flex items-center gap-2">
                        <i class="fa-solid fa-file-export"></i> Export <i class="fa fa-chevron-down text-[10px]"></i>
                    </button>
                    <button onclick="openModal()" class="bg-orange-500 text-white px-6 py-2 rounded flex items-center gap-2 shadow-sm font-medium">
                        <i class="fa-regular fa-file-lines"></i> Report
                    </button>
                    <button class="bg-white border p-2 rounded text-slate-400"><i class="fa-solid fa-angles-up text-xs"></i></button>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6 mb-8">
                <div class="col-span-12 lg:col-span-3 card p-6 text-center">
                    <p class="text-slate-500 text-sm">Good Morning, <?php echo $employeeName; ?></p>
                    <h2 class="text-xl font-bold mt-1 mb-4">08:35 AM, 11 Mar 2025</h2>
                    <div class="relative inline-block mb-6">
                        <div class="w-32 h-32 rounded-full border-[6px] border-emerald-500 p-1">
                            <img src="https://i.pravatar.cc/150?u=adrian" alt="Profile" class="rounded-full w-full h-full object-cover">
                        </div>
                    </div>
                    <div id="statusTag" class="bg-orange-500 text-white py-2 px-4 rounded-md mb-4 text-sm font-medium">
                        Production : 3.45 hrs
                    </div>
                    <p class="text-slate-600 text-sm mb-6 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-fingerprint text-orange-500"></i> <span id="punchText">Punch In at 10.00 AM</span>
                    </p>
                    <button id="punchBtn" onclick="togglePunch()" class="w-full bg-[#111827] text-white py-3 rounded-md font-bold transition-all">
                        Punch Out
                    </button>
                </div>

                <div class="col-span-12 lg:col-span-9 flex flex-col gap-6">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="card p-5">
                            <div class="bg-orange-100 text-orange-600 w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-regular fa-clock"></i></div>
                            <div class="text-2xl font-bold">8.36 <span class="text-slate-400 font-normal">/ 9</span></div>
                            <p class="text-slate-400 text-xs mt-1">Total Hours Today</p>
                            <div class="mt-4 text-emerald-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-up text-[10px]"></i> 5% This Week</div>
                        </div>
                        <div class="card p-5">
                            <div class="bg-slate-900 text-white w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-solid fa-stopwatch"></i></div>
                            <div class="text-2xl font-bold">10 <span class="text-slate-400 font-normal">/ 40</span></div>
                            <p class="text-slate-400 text-xs mt-1">Total Hours Week</p>
                            <div class="mt-4 text-emerald-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-up text-[10px]"></i> 7% Last Week</div>
                        </div>
                        <div class="card p-5">
                            <div class="bg-blue-100 text-blue-600 w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-regular fa-calendar-check"></i></div>
                            <div class="text-2xl font-bold">75 <span class="text-slate-400 font-normal">/ 98</span></div>
                            <p class="text-slate-400 text-xs mt-1">Total Hours Month</p>
                            <div class="mt-4 text-red-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-down text-[10px]"></i> 8% Last Month</div>
                        </div>
                        <div class="card p-5 relative overflow-hidden">
                            <div class="bg-pink-100 text-pink-600 w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div class="text-2xl font-bold">16 <span class="text-slate-400 font-normal">/ 28</span></div>
                            <p class="text-slate-400 text-xs mt-1">Overtime this...</p>
                            <div class="mt-4 text-red-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-down text-[10px]"></i> 6% Last Month</div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <div class="grid grid-cols-4 gap-4 mb-6">
                            <div><p class="text-[11px] text-slate-400">Total Working hours</p><h3 class="text-2xl font-bold">12h 36m</h3></div>
                            <div><p class="text-[11px] text-slate-400">Productive Hours</p><h3 class="text-2xl font-bold">08h 36m</h3></div>
                            <div><p class="text-[11px] text-slate-400">Break hours</p><h3 class="text-2xl font-bold">22m 15s</h3></div>
                            <div><p class="text-[11px] text-slate-400">Overtime</p><h3 class="text-2xl font-bold">02h 15m</h3></div>
                        </div>
                        <div class="h-10 w-full bg-slate-50 rounded-full flex overflow-hidden mb-4 border border-slate-100">
                            <div style="width: 12%"></div>
                            <div class="h-full bg-emerald-500" style="width: 10%"></div>
                            <div class="h-full bg-amber-400" style="width: 3%"></div>
                            <div class="h-full bg-emerald-500" style="width: 20%"></div>
                            <div class="h-full bg-amber-400" style="width: 10%"></div>
                            <div class="h-full bg-emerald-500" style="width: 12%"></div>
                            <div class="h-full bg-amber-400" style="width: 3%"></div>
                            <div class="h-full bg-blue-500" style="width: 4%"></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-slate-400 font-medium px-1 uppercase">
                            <span>06:00</span><span>08:00</span><span>10:00</span><span>12:00</span><span>02:00</span><span>04:00</span><span>06:00</span><span>08:00</span><span>10:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="p-4 border-b bg-white">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <h3 class="text-lg font-bold">Attendance Log</h3>
                        <div class="flex flex-wrap gap-2">
                            <div class="border rounded px-3 py-2 text-sm flex items-center gap-2 text-slate-600">
                                <i class="fa-regular fa-calendar-days text-orange-500"></i>
                                <span class="font-medium"><?php echo $currentDateRange; ?></span>
                            </div>
                            
                            <select class="border rounded px-4 py-2 text-sm text-slate-600 focus:outline-none cursor-pointer">
                                <option value="" disabled selected>Select Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                            </select>

                            <select class="border rounded px-4 py-2 text-sm text-slate-600 focus:outline-none cursor-pointer">
                                <option value="last_7_days" selected>Sort By : Last 7 Days</option>
                                <option value="recently_added">Recently Added</option>
                                <option value="ascending">Ascending</option>
                                <option value="descending">Descending</option>
                                <option value="last_month">Last Month</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-b flex justify-between items-center bg-white">
                    <div class="flex items-center text-sm text-slate-600">
                        Row Per Page 
                        <select class="mx-2 border rounded p-1"><option>10</option></select> 
                        Entries
                    </div>
                    <div class="relative">
                        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" placeholder="Search" class="border rounded-md pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600 font-semibold border-b">
                            <tr>
                                <th class="p-4">Date</th><th class="p-4">Check In</th><th class="p-4">Status</th>
                                <th class="p-4">Check Out</th><th class="p-4">Break</th><th class="p-4">Late</th>
                                <th class="p-4">Overtime</th><th class="p-4">Production Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($attendanceRecords as $row): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 text-slate-500"><?php echo $row['date']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['checkin']; ?></td>
                                <td class="p-4">
                                    <span class="<?php echo ($row['status'] == 'Present') ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?> px-3 py-1 rounded-full text-xs font-bold flex items-center w-fit">
                                        <span class="w-1 h-1 rounded-full mr-2 <?php echo ($row['status'] == 'Present') ? 'bg-green-600' : 'bg-red-600'; ?>"></span> <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-slate-500"><?php echo $row['checkout']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['break']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['late']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['overtime']; ?></td>
                                <td class="p-4">
                                    <span class="<?php echo ($row['color'] == 'green') ? 'bg-emerald-500' : (($row['color'] == 'blue') ? 'bg-blue-500' : 'bg-red-600'); ?> text-white px-3 py-1.5 rounded text-xs font-bold flex items-center w-fit">
                                        <i class="fa fa-clock mr-2"></i> <?php echo $row['production']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const modal = document.getElementById('reportModal');
        let isPunchedOut = false;

        function openModal() { modal.classList.add('modal-active'); document.body.style.overflow = 'hidden'; }
        function closeModal() { modal.classList.remove('modal-active'); document.body.style.overflow = 'auto'; }

        function togglePunch() {
            const btn = document.getElementById('punchBtn');
            const statusTag = document.getElementById('statusTag');
            const punchText = document.getElementById('punchText');
            
            if (!isPunchedOut) {
                btn.innerText = "Punch In";
                btn.classList.replace('bg-[#111827]', 'bg-emerald-600');
                statusTag.innerText = "Shift Ended";
                statusTag.classList.replace('bg-orange-500', 'bg-slate-400');
                punchText.innerText = "Punch Out at 06:45 PM";
                isPunchedOut = true;
            } else {
                btn.innerText = "Punch Out";
                btn.classList.replace('bg-emerald-600', 'bg-[#111827]');
                statusTag.innerText = "Production : 3.45 hrs";
                statusTag.classList.replace('bg-slate-400', 'bg-orange-500');
                punchText.innerText = "Punch In at 10.00 AM";
                isPunchedOut = false;
            }
        }

        window.onclick = (e) => { if (e.target == modal) closeModal(); }
    </script>
</body>
</html>