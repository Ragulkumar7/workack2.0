<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Stephan Peralt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .timeline-bar { height: 16px; border-radius: 50px; background: #f1f5f9; position: relative; overflow: hidden; display: flex; gap: 4px; padding: 0 4px; align-items: center; }
        .segment { height: 10px; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        
        /* Dark Teal Theme Colors */
        .bg-teal-custom { background-color: #144d4d; }
        .text-teal-custom { color: #144d4d; }
        .border-teal-custom { border-color: #144d4d; }
        .perf-gradient { fill: url(#chartGradient); fill-opacity: 0.1; }
        
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 74px; top: 0; bottom: 0; width: 1px; background: #e2e8f0; border-style: dashed; }

        /* --- SIDEBAR LAYOUT FIX --- */
        #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px); /* Prevent Overflow */
            transition: margin-left 0.3s ease, width 0.3s ease;
        }
        /* When Secondary Sidebar Opens */
        #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }
    </style>
</head>
<body class="bg-slate-50">

    <?php include '../sidebars.php'; ?>

    <div class="min-h-screen">
        
        <main id="mainContent" class="p-4 md:p-8">

            <?php
            // Configuration & Data
            $employee_name = "Stephan Peralt";
            $attendance_date = "11 Mar 2025";
            $attendance_time = "08:35 AM";
            $total_hours_today = "5:45:32";
            $attendance_percent = 65; 

            // Date & Resignation Data
            $joining_date = "15 Jan 2024";
            $status = "Resigned"; 
            $notice_period_days = 18; 
            $last_working_day = "25 Feb 2026";
            ?>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Employee Dashboard</h1>
                    <nav class="flex text-sm text-gray-500 mt-1">
                        <ol class="inline-flex items-center space-x-1">
                            <li><i class="fa-solid fa-house text-xs"></i></li>
                            <li><span class="mx-2 text-gray-400">></span> Dashboard</li>
                            <li><span class="mx-2 text-gray-400">></span> <span class="text-teal-custom font-medium">Employee Dashboard</span></li>
                        </ol>
                    </nav>
                </div>
                <div class="flex gap-3">
                    <button class="bg-white border border-gray-200 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shadow-sm"><i class="fa-solid fa-file-export text-gray-400"></i> Export <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i></button>
                    <button class="bg-white border border-gray-200 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shadow-sm"><i class="fa-regular fa-calendar text-gray-400"></i> 15-04-2025</button>
                    <button class="bg-white border border-gray-200 p-2 rounded-lg text-sm flex items-center shadow-sm"><i class="fa-solid fa-angles-up text-gray-400"></i></button>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                
                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-full">
                        <div class="bg-teal-custom p-6 flex items-center gap-4">
                            <img src="https://ui-avatars.com/api/?name=Stephan+Peralt&background=4f46e5&color=fff" class="w-14 h-14 rounded-full border-2 border-green-500 p-0.5">
                            <div>
                                <h2 class="text-white font-bold text-lg leading-tight"><?php echo $employee_name; ?></h2>
                                <p class="text-slate-300 text-xs mt-1">Senior Product Designer <span class="text-orange-400 ml-1">• UI/UX Design</span></p>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div><p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Phone Number</p><p class="font-semibold text-sm">+1 324 3453 545</p></div>
                            <div><p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Email Address</p><p class="font-semibold text-sm">steperde124@example.com</p></div>
                            <div><p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Report Office</p><p class="font-semibold text-sm">Doglas Martini</p></div>
                            
                            <div class="pt-4 border-t border-dashed border-gray-100 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-green-50 flex items-center justify-center">
                                            <i class="fa-solid fa-calendar-check text-green-500 text-[10px]"></i>
                                        </div>
                                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Joined On</span>
                                    </div>
                                    <span class="font-bold text-sm text-slate-800"><?php echo $joining_date; ?></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg <?php echo ($status == 'Resigned') ? 'bg-orange-50' : 'bg-teal-50'; ?> flex items-center justify-center">
                                            <i class="fa-solid <?php echo ($status == 'Resigned') ? 'fa-file-signature text-orange-500' : 'fa-user-check text-teal-custom'; ?> text-[10px]"></i>
                                        </div>
                                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Status</span>
                                    </div>
                                    <span class="font-bold text-sm text-slate-800"><?php echo $status; ?></span>
                                </div>

                                <?php if($status == "Resigned"): ?>
                                <div class="p-3 bg-red-50 rounded-xl border border-red-100">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-[9px] text-red-600 font-bold uppercase">Resignation Info</span>
                                        <span class="text-[9px] bg-white text-orange-600 px-2 py-0.5 rounded-full border border-orange-100 font-bold"><?php echo $notice_period_days; ?> Days Notice</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10px] text-gray-500">Last Day:</span>
                                        <span class="text-xs font-black text-red-700"><?php echo $last_working_day; ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6"><h3 class="font-bold text-slate-800 text-sm">Attendance Details</h3><div class="text-[10px] font-bold text-gray-400 border px-2 py-1 rounded">📅 2026</div></div>
                        <div class="flex items-center justify-between">
                            <div class="space-y-3 text-sm">
                                <p><span class="text-teal-custom font-bold mr-2">• 1254</span> on time</p>
                                <p><span class="text-green-500 font-bold mr-2">• 32</span> Late Attendance</p>
                                <p><span class="text-orange-500 font-bold mr-2">• 658</span> Work From Home</p>
                                <p><span class="text-red-500 font-bold mr-2">• 14</span> Absent</p>
                                <p><span class="text-yellow-500 font-bold mr-2">• 68</span> Sick Leave</p>
                                <p class="pt-2 text-[10px] italic text-gray-400"><i class="fa-solid fa-check-square text-teal-custom mr-1"></i> Better than <span class="text-slate-800 font-bold">85%</span> of Employees</p>
                            </div>
                            <div class="w-32 h-32 rounded-full border-[15px] border-teal-custom border-t-orange-500 border-r-yellow-500 border-l-green-500 rotate-45"></div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6"><h3 class="font-bold text-slate-800 text-sm">Leave Balance</h3><div class="text-[10px] font-bold text-gray-400 border px-2 py-1 rounded">📅 2026</div></div>
                        <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                            <div><p class="text-gray-400 text-xs">Total Leaves</p><p class="font-bold text-lg">16</p></div>
                            <div><p class="text-gray-400 text-xs">Taken</p><p class="font-bold text-lg">10</p></div>
                            <div><p class="text-gray-400 text-xs">Absent</p><p class="font-bold text-lg">2</p></div>
                            <div><p class="text-gray-400 text-xs">Request</p><p class="font-bold text-lg">0</p></div>
                            <div><p class="text-gray-400 text-xs">Worked Days</p><p class="font-bold text-lg">240</p></div>
                            <div><p class="text-gray-400 text-xs">Loss of Pay</p><p class="font-bold text-lg text-red-500">2</p></div>
                        </div>
                        <button class="w-full bg-teal-custom text-white py-3 rounded-xl font-bold text-xs uppercase tracking-widest">Apply New Leave</button>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-white border border-teal-100 rounded-2xl p-6 shadow-sm">
                        <div class="text-center mb-4"><h3 class="text-gray-400 text-[10px] font-bold uppercase tracking-widest">Attendance</h3><p class="text-slate-800 font-bold text-sm"><?php echo $attendance_time . ', ' . $attendance_date; ?></p></div>
                        <div class="relative flex items-center justify-center my-4">
                            <svg class="w-28 h-28 transform -rotate-90"><circle cx="56" cy="56" r="50" stroke="#f1f5f9" stroke-width="6" fill="transparent" /><circle cx="56" cy="56" r="50" stroke="#144d4d" stroke-width="6" fill="transparent" stroke-dasharray="314" stroke-dashoffset="100" stroke-linecap="round" /></svg>
                            <div class="absolute text-center"><p class="text-gray-400 text-[8px] uppercase font-bold">Total Hours</p><p class="text-slate-800 font-bold text-sm leading-tight"><?php echo $total_hours_today; ?></p></div>
                        </div>
                        <div class="space-y-3 text-center">
                            <div class="inline-block bg-teal-custom text-white px-3 py-1 rounded text-[10px] font-bold">Production : 3.45 hrs</div>
                            <p class="text-[10px] text-gray-500"><i class="fa-solid fa-fingerprint text-orange-500"></i> Punch In at 10.00 AM</p>
                            <button class="w-full bg-[#f26522] text-white font-bold py-3 rounded-xl text-xs">Punch Out</button>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-9 space-y-6">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white p-4 border rounded-2xl shadow-sm">
                            <div class="flex items-center gap-2 mb-1"><div class="w-6 h-6 rounded bg-orange-500 flex items-center justify-center text-white text-[10px]"><i class="fa-regular fa-clock"></i></div><p class="text-xl font-black">8.36 / <span class="text-gray-300">9</span></p></div>
                            <p class="text-gray-400 text-[10px] font-bold uppercase">Total Hours Today</p>
                            <div class="text-[10px] text-green-500 font-bold mt-2"><i class="fa-solid fa-arrow-up"></i> 5% This Week</div>
                        </div>
                        <div class="bg-white p-4 border rounded-2xl shadow-sm">
                            <div class="flex items-center gap-2 mb-1"><div class="w-6 h-6 rounded bg-teal-custom flex items-center justify-center text-white text-[10px]"><i class="fa-solid fa-rotate"></i></div><p class="text-xl font-black">10 / <span class="text-gray-300">40</span></p></div>
                            <p class="text-gray-400 text-[10px] font-bold uppercase">Total Hours Week</p>
                            <div class="text-[10px] text-green-500 font-bold mt-2"><i class="fa-solid fa-arrow-up"></i> 7% Last Week</div>
                        </div>
                        <div class="bg-white p-4 border rounded-2xl shadow-sm">
                            <div class="flex items-center gap-2 mb-1"><div class="w-6 h-6 rounded bg-blue-500 flex items-center justify-center text-white text-[10px]"><i class="fa-regular fa-calendar-check"></i></div><p class="text-xl font-black">75 / <span class="text-gray-300">98</span></p></div>
                            <p class="text-gray-400 text-[10px] font-bold uppercase">Total Hours Month</p>
                            <div class="text-[10px] text-red-500 font-bold mt-2"><i class="fa-solid fa-arrow-down"></i> 8% Last Month</div>
                        </div>
                        <div class="bg-white p-4 border rounded-2xl shadow-sm">
                            <div class="flex items-center gap-2 mb-1"><div class="w-6 h-6 rounded bg-pink-500 flex items-center justify-center text-white text-[10px]"><i class="fa-solid fa-briefcase"></i></div><p class="text-xl font-black">16 / <span class="text-gray-300">28</span></p></div>
                            <p class="text-gray-400 text-[10px] font-bold uppercase">Overtime this Month</p>
                            <div class="text-[10px] text-red-500 font-bold mt-2"><i class="fa-solid fa-arrow-down"></i> 6% Last Month</div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                        <div class="flex justify-between mb-4">
                            <div class="flex gap-8">
                                <div><p class="text-[9px] text-gray-400 font-bold uppercase">• Total Working hours</p><p class="text-sm font-black">12h 36m</p></div>
                                <div><p class="text-[9px] text-green-500 font-bold uppercase">• Productive Hours</p><p class="text-sm font-black">08h 36m</p></div>
                                <div><p class="text-[9px] text-yellow-500 font-bold uppercase">• Break hours</p><p class="text-sm font-black">22m 15s</p></div>
                                <div><p class="text-[9px] text-blue-500 font-bold uppercase">• Overtime</p><p class="text-sm font-black">02h 15m</p></div>
                            </div>
                        </div>
                        <div class="timeline-bar mb-2">
                            <div class="segment bg-green-500" style="width: 25%;"></div>
                            <div class="segment bg-yellow-500" style="width: 5%;"></div>
                            <div class="segment bg-green-500" style="width: 35%;"></div>
                            <div class="segment bg-yellow-500" style="width: 15%;"></div>
                            <div class="segment bg-green-500" style="width: 15%;"></div>
                            <div class="segment bg-yellow-500" style="width: 5%;"></div>
                            <div class="segment bg-blue-500" style="width: 3%;"></div>
                            <div class="segment bg-blue-500" style="width: 2%;"></div>
                        </div>
                        <div class="flex justify-between text-[9px] text-gray-400 font-bold px-1">
                            <span>06:00</span><span>07:00</span><span>08:00</span><span>09:00</span><span>10:00</span><span>11:00</span><span>12:00</span><span>01:00</span><span>02:00</span><span>03:00</span><span>04:00</span><span>05:00</span><span>06:00</span><span>07:00</span><span>08:00</span><span>09:00</span><span>10:00</span><span>11:00</span>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">Projects</h3>
                            <button class="text-[10px] font-bold text-gray-500 flex items-center gap-1">Ongoing Projects <i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php for($i=0; $i<2; $i++): ?>
                            <div class="border rounded-xl p-4 bg-white shadow-sm">
                                <div class="flex justify-between mb-4"><h4 class="font-bold text-xs text-slate-800">Office Management</h4><i class="fa-solid fa-ellipsis-vertical text-gray-300"></i></div>
                                <div class="flex items-center gap-3 mb-4">
                                    <img src="https://ui-avatars.com/api/?name=Anthony+Lewis&background=random" class="w-8 h-8 rounded-full">
                                    <div><p class="text-xs font-bold text-slate-800 leading-tight">Anthony Lewis</p><p class="text-[9px] text-gray-400">Project Leader</p></div>
                                </div>
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="w-7 h-7 rounded-lg bg-orange-50 flex items-center justify-center text-orange-500 text-xs"><i class="fa-regular fa-calendar"></i></div>
                                    <div><p class="text-[10px] font-bold text-slate-800">14 Jan 2024</p><p class="text-[9px] text-gray-400">Deadline</p></div>
                                </div>
                                <div class="flex justify-between items-center pt-3 border-t">
                                    <div class="flex items-center gap-1 text-[10px] text-green-600 font-bold"><i class="fa-solid fa-clipboard-list"></i> Tasks: 6/10</div>
                                    <div class="flex -space-x-2">
                                        <img src="https://ui-avatars.com/api/?name=A&background=random" class="w-5 h-5 rounded-full border border-white">
                                        <img src="https://ui-avatars.com/api/?name=B&background=random" class="w-5 h-5 rounded-full border border-white">
                                        <div class="w-5 h-5 rounded-full bg-orange-500 text-[8px] text-white flex items-center justify-center border border-white font-bold">+2</div>
                                    </div>
                                </div>
                                <div class="mt-4 bg-slate-100 rounded-lg p-2 flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-gray-500">Time Spent</span>
                                    <span class="text-xs font-black text-slate-800">65/120 <span class="text-gray-400 font-normal">Hrs</span></span>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">Tasks</h3>
                            <button class="text-[10px] font-bold text-gray-500 flex items-center gap-1">All Projects <i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                        <div class="space-y-3 custom-scrollbar overflow-y-auto max-h-[400px] pr-2">
                            <?php 
                            $tasks = [
                                ['title' => 'Patient appointment booking', 'status' => 'Onhold', 'color' => 'pink'],
                                ['title' => 'Appointment booking with payment', 'status' => 'Inprogress', 'color' => 'purple'],
                                ['title' => 'Patient and Doctor video conferencing', 'status' => 'Completed', 'color' => 'green'],
                                ['title' => 'Private chat module', 'status' => 'Pending', 'color' => 'slate', 'checked' => true],
                                ['title' => 'Go-Live and Post-Implementation Support', 'status' => 'Inprogress', 'color' => 'purple'],
                            ];
                            foreach($tasks as $task): ?>
                            <div class="flex items-center justify-between p-3 border rounded-xl hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid fa-grip-vertical text-gray-200 text-xs"></i>
                                    <input type="checkbox" class="rounded text-teal-custom" <?php echo isset($task['checked']) ? 'checked' : ''; ?>>
                                    <i class="fa-regular fa-star text-gray-300 text-xs"></i>
                                    <span class="text-xs font-bold text-slate-700"><?php echo $task['title']; ?></span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <?php 
                                        $colors = [
                                            'pink' => 'bg-pink-50 text-pink-500',
                                            'purple' => 'bg-purple-50 text-purple-600',
                                            'green' => 'bg-green-50 text-green-600',
                                            'slate' => 'bg-slate-100 text-slate-800'
                                        ];
                                    ?>
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?php echo $colors[$task['color']]; ?>">
                                        ● <?php echo $task['status']; ?>
                                    </span>
                                    <div class="flex -space-x-1">
                                        <img src="https://ui-avatars.com/api/?name=X&size=20" class="w-5 h-5 rounded-full">
                                        <img src="https://ui-avatars.com/api/?name=Y&size=20" class="w-5 h-5 rounded-full">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">Performance</h3>
                            <div class="text-[10px] font-bold text-gray-400 border px-2 py-1 rounded">📅 2026</div>
                        </div>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-2xl font-black">98%</span>
                            <span class="text-[10px] font-bold text-green-500 bg-green-50 px-2 py-0.5 rounded-full">12% vs last years</span>
                        </div>
                        <div class="h-48 w-full relative">
                            <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-[10px] text-gray-300 font-bold">
                                <span>60K</span><span>50K</span><span>40K</span><span>30K</span><span>20K</span><span>10K</span>
                            </div>
                            <svg class="w-full h-full pl-8" viewBox="0 0 400 150">
                                <defs>
                                    <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#144d4d" />
                                        <stop offset="100%" stop-color="white" />
                                    </linearGradient>
                                </defs>
                                <path d="M0 120 L 50 120 L 100 80 L 150 80 L 200 70 L 250 40 L 400 40 L 400 150 L 0 150 Z" class="perf-gradient" />
                                <path d="M0 120 L 50 120 L 100 80 L 150 80 L 200 70 L 250 40 L 400 40" fill="none" stroke="#144d4d" stroke-width="3" />
                            </svg>
                            <div class="flex justify-between mt-2 text-[10px] font-bold text-gray-400 pl-8">
                                <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">My Skills</h3>
                            <div class="text-[10px] font-bold text-gray-400 border px-2 py-1 rounded">📅 2026</div>
                        </div>
                        <div class="space-y-4">
                            <?php 
                            $skills = [
                                ['name' => 'Figma', 'date' => '15 May 2025', 'pct' => 95, 'color' => 'orange-500'],
                                ['name' => 'HTML', 'date' => '12 May 2025', 'pct' => 85, 'color' => 'green-500'],
                                ['name' => 'CSS', 'date' => '12 May 2025', 'pct' => 70, 'color' => 'purple-500'],
                                ['name' => 'Wordpress', 'date' => '15 May 2025', 'pct' => 61, 'color' => 'blue-500'],
                                ['name' => 'Javascript', 'date' => '13 May 2025', 'pct' => 58, 'color' => 'slate-800']
                            ];
                            foreach($skills as $skill): ?>
                            <div class="flex items-center justify-between p-3 border border-dashed rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="w-1 h-8 rounded-full bg-<?php echo $skill['color']; ?>"></div>
                                    <div>
                                        <p class="text-xs font-bold"><?php echo $skill['name']; ?></p>
                                        <p class="text-[9px] text-gray-400">Updated: <?php echo $skill['date']; ?></p>
                                    </div>
                                </div>
                                <div class="relative w-10 h-10 flex items-center justify-center">
                                    <svg class="w-full h-full -rotate-90">
                                        <circle cx="20" cy="20" r="18" fill="none" stroke="#f1f5f9" stroke-width="3" />
                                        <circle cx="20" cy="20" r="18" fill="none" stroke="currentColor" stroke-width="3" 
                                            class="text-<?php echo $skill['color']; ?>" 
                                            stroke-dasharray="113" stroke-dashoffset="<?php echo 113 - (113 * $skill['pct'] / 100); ?>" />
                                    </svg>
                                    <span class="absolute text-[8px] font-bold"><?php echo $skill['pct']; ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-3 space-y-4">
                    <div class="bg-teal-custom rounded-2xl p-6 text-center text-white relative overflow-hidden">
                        <p class="text-[10px] font-bold uppercase mb-4 opacity-60">Team Birthday</p>
                        <img src="https://ui-avatars.com/api/?name=Andrew+Jermia&background=ffedd5&color=9a3412" class="w-16 h-16 rounded-full mx-auto border-2 border-orange-400 mb-3">
                        <h4 class="font-bold text-sm">Andrew Jermia</h4>
                        <p class="text-[10px] opacity-60 mb-4">IOS Developer</p>
                        <button class="w-full bg-orange-500 py-2 rounded-lg text-xs font-bold">Send Wishes</button>
                    </div>
                    
                    <div class="bg-teal-custom rounded-2xl p-4 flex justify-between items-center text-white">
                        <div>
                            <p class="text-xs font-bold">Leave Policy</p>
                            <p class="text-[9px] opacity-60">Last Updated : Today</p>
                        </div>
                        <button class="bg-white text-teal-custom text-[9px] font-bold px-3 py-1.5 rounded-lg">View All</button>
                    </div>

                    <div class="bg-[#facc15] rounded-2xl p-4 flex justify-between items-center text-slate-800">
                        <div>
                            <p class="text-xs font-bold">Next Holiday</p>
                            <p class="text-[9px] font-medium">Diwali, 15 Sep 2025</p>
                        </div>
                        <button class="bg-white text-slate-800 text-[9px] font-bold px-3 py-1.5 rounded-lg">View All</button>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">Team Members</h3>
                            <button class="text-[10px] font-bold text-teal-custom bg-teal-50 px-2 py-1 rounded">View All</button>
                        </div>
                        <div class="space-y-4">
                            <?php 
                            $team = [
                                ['name' => 'Alexander Jermai', 'role' => 'UI/UX Designer'],
                                ['name' => 'Doglas Martini', 'role' => 'Product Designer'],
                                ['name' => 'Daniel Esbella', 'role' => 'Project Manager'],
                                ['name' => 'Daniel Esbella', 'role' => 'Team Lead'],
                                ['name' => 'Stephan Peralt', 'role' => 'Team Lead'],
                                ['name' => 'Andrew Jermia', 'role' => 'Project Lead']
                            ];
                            foreach($team as $member): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member['name']); ?>&background=random" class="w-10 h-10 rounded-full">
                                    <div><p class="text-xs font-bold text-slate-800"><?php echo $member['name']; ?></p><p class="text-[10px] text-gray-400"><?php echo $member['role']; ?></p></div>
                                </div>
                                <div class="flex gap-2">
                                    <button class="p-1.5 border rounded-lg text-gray-400 hover:text-teal-custom"><i class="fa-solid fa-phone text-[10px]"></i></button>
                                    <button class="p-1.5 border rounded-lg text-gray-400 hover:text-teal-custom"><i class="fa-solid fa-envelope text-[10px]"></i></button>
                                    <button class="p-1.5 border rounded-lg text-gray-400 hover:text-teal-custom"><i class="fa-solid fa-comment-dots text-[10px]"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">Notifications</h3>
                            <button class="text-[10px] font-bold text-teal-custom bg-teal-50 px-2 py-1 rounded">View All</button>
                        </div>
                        <div class="space-y-5">
                            <div class="flex gap-3">
                                <img src="https://ui-avatars.com/api/?name=Lex+Murphy&background=random" class="w-10 h-10 rounded-full">
                                <div>
                                    <p class="text-xs font-bold text-slate-800">Lex Murphy requested access to UNIX</p>
                                    <p class="text-[10px] text-gray-400 mb-2">Today at 9:42 AM</p>
                                    <div class="flex items-center gap-2 p-2 border rounded-lg bg-gray-50">
                                        <i class="fa-solid fa-file-pdf text-red-500 text-xs"></i><span class="text-[10px] font-medium">EY_review.pdf</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <img src="https://ui-avatars.com/api/?name=Lex+Murphy&background=random" class="w-10 h-10 rounded-full">
                                <div>
                                    <p class="text-xs font-bold text-slate-800">Lex Murphy requested access to UNIX</p>
                                    <p class="text-[10px] text-gray-400">Today at 10:00 AM</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <img src="https://ui-avatars.com/api/?name=Lex+Murphy&background=random" class="w-10 h-10 rounded-full">
                                <div>
                                    <p class="text-xs font-bold text-slate-800">Lex Murphy requested access to UNIX</p>
                                    <p class="text-[10px] text-gray-400 mb-3">Today at 10:50 AM</p>
                                    <div class="flex gap-2">
                                        <button class="bg-teal-custom text-white text-[10px] font-bold px-4 py-1.5 rounded-lg">Approve</button>
                                        <button class="border border-teal-custom text-teal-custom text-[10px] font-bold px-4 py-1.5 rounded-lg">Decline</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-sm">Meetings Schedule</h3>
                            <button class="text-[10px] font-bold text-gray-400 border px-2 py-1 rounded">📅 Today</button>
                        </div>
                        <div class="meeting-timeline space-y-6">
                            <div class="flex items-start gap-4 relative">
                                <span class="text-[10px] font-bold text-gray-400 w-14">09:25 AM</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-orange-500 absolute left-[70px] top-1 z-10 border-2 border-white"></div>
                                <div class="bg-gray-50 p-3 rounded-xl flex-1">
                                    <p class="text-xs font-bold text-slate-800">Marketing Strategy Presentation</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Marketing</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 relative">
                                <span class="text-[10px] font-bold text-gray-400 w-14">09:20 AM</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-teal-custom absolute left-[70px] top-1 z-10 border-2 border-white"></div>
                                <div class="bg-gray-50 p-3 rounded-xl flex-1">
                                    <p class="text-xs font-bold text-slate-800">Design Review Project</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Review</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 relative">
                                <span class="text-[10px] font-bold text-gray-400 w-14">09:18 AM</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-yellow-500 absolute left-[70px] top-1 z-10 border-2 border-white"></div>
                                <div class="bg-gray-50 p-3 rounded-xl flex-1">
                                    <p class="text-xs font-bold text-slate-800">Birthday Celebration of Employee</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Celebration</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 relative">
                                <span class="text-[10px] font-bold text-gray-400 w-14">09:10 AM</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-green-500 absolute left-[70px] top-1 z-10 border-2 border-white"></div>
                                <div class="bg-gray-50 p-3 rounded-xl flex-1">
                                    <p class="text-xs font-bold text-slate-800">Update of Project Flow</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Development</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="fixed right-0 top-1/2 -translate-y-1/2 bg-teal-custom text-white p-2 rounded-l-lg shadow-xl cursor-pointer">
                <i class="fa-solid fa-gear"></i>
            </div>
            
        </main>
    </div>

</body>
</html>
