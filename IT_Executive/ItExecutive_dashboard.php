<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Admin Dashboard - HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-700">

    <main class="p-8">
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[#1e293b]">IT Admin Dashbaord</h1>
                <nav class="flex items-center gap-2 text-sm text-slate-400 mt-1">
                    <i class="fa-solid fa-house text-xs"></i>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span>Dashboard</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-slate-600 font-medium">IT Admin Dashbaord</span>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-2 shadow-sm">
                    <div class="bg-emerald-500 p-1.5 rounded text-white text-[10px]"><i class="fa-solid fa-link"></i></div>
                    <span class="text-sm font-medium">Active Users <span class="font-bold ml-1">248</span></span>
                </div>
                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-2 shadow-sm">
                    <div class="bg-red-500 p-1.5 rounded text-white text-[10px]"><i class="fa-solid fa-circle-info"></i></div>
                    <span class="text-sm font-medium">Security Alerts <span class="font-bold ml-1 text-red-600">3</span></span>
                </div>
                <div class="flex bg-white border border-slate-200 p-1 rounded-lg shadow-sm">
                    <button class="bg-[#f97316] text-white px-4 py-1.5 rounded-md text-sm font-semibold shadow-sm">Production</button>
                    <button class="text-slate-500 px-4 py-1.5 text-sm font-medium hover:bg-slate-50 rounded-md">Staging</button>
                    <button class="text-slate-500 px-4 py-1.5 text-sm font-medium hover:bg-slate-50 rounded-md">Development</button>
                </div>
                <button class="bg-white border border-slate-200 p-2 rounded-lg text-slate-400 hover:text-slate-600 shadow-sm">
                    <i class="fa-solid fa-chevron-up"></i>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php 
            $metrics = [
                ['label' => 'HRMS System Uptime', 'value' => '99.9%', 'sub' => 'Last 30 days', 'icon' => 'fa-clock-rotate-left', 'color' => 'text-orange-500'],
                ['label' => 'API Status', 'value' => 'Healthy', 'sub' => 'All operational', 'icon' => 'fa-share-nodes', 'color' => 'text-emerald-500'],
                ['label' => 'Open IT Tickets', 'value' => '18', 'sub' => '5 high priority', 'icon' => 'fa-ticket-simple', 'color' => 'text-slate-800'],
                ['label' => 'Background Jobs', 'value' => 'Running', 'sub' => '12/12 jobs healthy', 'icon' => 'fa-briefcase', 'color' => 'text-slate-800']
            ];
            foreach($metrics as $m): ?>
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
                <p class="text-slate-400 text-sm font-medium"><?php echo $m['label']; ?></p>
                <h3 class="text-3xl font-bold mt-2 <?php echo $m['color']; ?>"><?php echo $m['value']; ?></h3>
                <p class="text-xs text-slate-400 mt-1"><?php echo $m['sub']; ?></p>
                <div class="absolute top-6 right-6 w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 group-hover:text-slate-500 transition">
                    <i class="fa-solid <?php echo $m['icon']; ?>"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="font-bold text-lg flex items-center gap-2">
                            <i class="fa-solid fa-database text-orange-500 text-sm"></i> Storage Usage By Module (GB)
                        </h3>
                        <div class="flex bg-slate-100 p-1 rounded-lg text-[10px] font-bold">
                            <span class="px-2 py-1 text-slate-400 uppercase">1D</span>
                            <span class="px-2 py-1 text-slate-400 uppercase">7D</span>
                            <span class="px-2 py-1 text-slate-400 uppercase">1M</span>
                            <span class="px-1.5 py-1 bg-[#1e293b] text-white rounded uppercase">1Y</span>
                        </div>
                    </div>
                    
                    <div class="h-64 flex items-end justify-between gap-4 px-4 pb-2">
                        <?php 
                        $storage = [['HR', 280, 'bg-[#2a5d67]'], ['Payroll', 260, 'bg-[#2a5d67]'], ['Attendance', 140, 'bg-[#f47e4d]'], ['Recruitment', 68, 'bg-[#2a5d67]'], ['Leaves', 120, 'bg-[#2a5d67]'], ['Document', 260, 'bg-[#2a5d67]']];
                        foreach($storage as $s): ?>
                        <div class="flex-1 flex flex-col items-center group">
                            <div class="w-full bg-slate-50 rounded-t-lg relative h-48">
                                <div class="<?php echo $s[2]; ?> absolute bottom-0 w-full rounded-t-lg transition-all duration-500 flex items-end justify-center pb-2" style="height: <?php echo ($s[1]/320)*100; ?>%">
                                    <span class="text-[10px] text-white font-bold opacity-0 group-hover:opacity-100 transition"><?php echo $s[1]; ?> GB</span>
                                </div>
                            </div>
                            <span class="text-xs text-slate-400 mt-3 font-medium"><?php echo $s[0]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b flex justify-between items-center">
                        <h3 class="text-lg font-bold text-slate-800">Recent Employee Tickets</h3>
                        <button class="text-sm text-blue-600 font-medium hover:underline">View All</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 border-b">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Ticket ID</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Submitted By</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Issue Type</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Priority</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Last Update</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 font-medium text-blue-600">TKT-2024-05-001</td>
                                    <td class="px-6 py-4 font-semibold">Jane Doe</td>
                                    <td class="px-6 py-4">Hardware</td>
                                    <td class="px-6 py-4"><span class="px-2 py-1 bg-orange-100 text-orange-600 rounded text-[10px] font-bold">Medium</span></td>
                                    <td class="px-6 py-4"><span class="px-2 py-1 bg-green-100 text-green-600 rounded text-[10px] font-bold">Open</span></td>
                                    <td class="px-6 py-4 text-slate-400 text-right">2 hours ago</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i class="fa-solid fa-shield-halved text-orange-500 text-sm"></i> MFA Enabled Users
                    </h3>
                    <div class="flex justify-between items-end mb-4">
                        <p class="text-xs text-slate-400 font-medium">2,168 out of 2,436 users</p>
                        <span class="text-2xl font-bold text-slate-800">89%</span>
                    </div>
                    <div class="flex gap-1.5">
                        <?php for($i=0; $i<15; $i++): ?>
                            <div class="h-3 w-3 rounded-full <?php echo $i < 12 ? 'bg-orange-500' : 'bg-slate-100'; ?>"></div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-6">
                        <i class="fa-solid fa-bolt text-orange-500 text-sm"></i> Quick IT Actions
                    </h3>
                    <div class="grid grid-cols-2 gap-y-8">
                        <?php 
                        $actions = [
                            ['Restart HRMS Services', 'fa-arrows-rotate'], ['Sync Biometric', 'fa-fingerprint'],
                            ['Clear System Cache', 'fa-database'], ['Schedule Maintenance', 'fa-calendar-day']
                        ];
                        foreach($actions as $a): ?>
                        <div class="flex flex-col items-center text-center group cursor-pointer">
                            <div class="w-12 h-12 bg-[#1e293b] text-white rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-500 transition shadow-lg shadow-slate-200">
                                <i class="fa-solid <?php echo $a[1]; ?> text-sm"></i>
                            </div>
                            <span class="text-[11px] font-semibold text-slate-600 px-2"><?php echo $a[0]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>