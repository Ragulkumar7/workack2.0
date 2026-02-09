<?php
// TL/dashboard.php - Professional Team Leader Dashboard

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR INCLUDE (Path logic for TL folder)
$sidebarPath = __DIR__ . '/../sidebars.php'; 
if (!file_exists($sidebarPath)) {
    $sidebarPath = 'sidebars.php'; 
}

// 3. LOGIN CHECK
if (!isset($_SESSION['user_id'])) { 
    // header("Location: ../index.php"); 
    // exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Leader Dashboard - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary-orange: #ff5e3a; 
            --bg-gray: #f8f9fa; 
            --border-color: #edf2f7; 
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --sidebar-width: 95px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-gray);
            margin: 0; padding: 0;
            color: var(--text-dark);
        }

        /* --- LAYOUT --- */
        #mainContent { 
            margin-left: var(--sidebar-width); 
            padding: 25px 35px; 
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            #mainContent { margin-left: 0 !important; padding: 15px; }
        }

        /* --- CARDS --- */
        .card { 
            background: white; 
            border-radius: 12px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
            padding: 20px;
            height: 100%;
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;
            transition: 0.2s; cursor: pointer; border: 1px solid var(--border-color); background: white;
        }
        .btn:hover { background: #f3f4f6; }
        .btn-orange { background: #f97316; color: white; border-color: #f97316; }
        .btn-orange:hover { background: #ea580c; }

        /* --- BADGES --- */
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-high { background: #fee2e2; color: #ef4444; }
        .badge-medium { background: #fef3c7; color: #d97706; }
        .badge-low { background: #dcfce7; color: #16a34a; }

        /* --- TIMELINE --- */
        .timeline-item { position: relative; padding-left: 24px; padding-bottom: 24px; border-left: 1px dashed #e5e7eb; }
        .timeline-item:last-child { border-left: none; }
        .timeline-icon { 
            position: absolute; left: -16px; top: 0; width: 32px; height: 32px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;
        }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Team Leader Dashboard</h1>
                <nav class="flex text-gray-500 text-xs mt-1 gap-2">
                    <a href="#" class="hover:text-orange-500">Dashboard</a>
                    <span>/</span>
                    <span class="text-gray-800 font-semibold">Overview</span>
                </nav>
            </div>
            <div class="flex gap-2">
                <button class="btn"><i data-lucide="download" class="w-4 h-4 mr-2"></i> Report</button>
                <div class="btn bg-white"><i data-lucide="calendar" class="w-4 h-4 mr-2 text-gray-400"></i> Current Week Overview</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Total Team</p>
                        <h3 class="text-2xl font-bold text-gray-800">12 Members</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 w-full"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 font-semibold">Engineering Department</p>
                </div>
            </div>

            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                        <i data-lucide="user-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Present Today</p>
                        <h3 class="text-2xl font-bold text-gray-800">10 / 12</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 w-[83%]"></div>
                    </div>
                    <p class="text-xs text-emerald-500 mt-2 font-semibold"><i data-lucide="trending-up" class="w-3 h-3 inline"></i> 83% <span class="text-gray-400 font-normal">Attendance rate</span></p>
                </div>
            </div>

            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                        <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Active Tasks</p>
                        <h3 class="text-2xl font-bold text-gray-800">45</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-500 w-3/4"></div>
                    </div>
                    <p class="text-xs text-orange-400 mt-2 font-semibold"><i data-lucide="alert-circle" class="w-3 h-3 inline"></i> 12 <span class="text-gray-400 font-normal">due this week</span></p>
                </div>
            </div>

            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                        <i data-lucide="zap" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Team Efficiency</p>
                        <h3 class="text-2xl font-bold text-gray-800">92%</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-purple-500 w-[92%]"></div>
                    </div>
                    <p class="text-xs text-emerald-500 mt-2 font-semibold"><i data-lucide="trending-up" class="w-3 h-3 inline"></i> +5% <span class="text-gray-400 font-normal">from last month</span></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Task Progress Tracker</h3>
                    <div class="flex gap-2">
                        <button class="btn btn-sm border text-xs">This Month</button>
                    </div>
                </div>
                <div class="flex gap-6 mb-4">
                    <div><span class="block w-2 h-2 rounded-full bg-orange-500 inline-block mr-1"></span><span class="text-xs text-gray-500">Assigned</span><div class="font-bold">128</div></div>
                    <div><span class="block w-2 h-2 rounded-full bg-emerald-500 inline-block mr-1"></span><span class="text-xs text-gray-500">Completed</span><div class="font-bold">112</div></div>
                    <div><span class="block w-2 h-2 rounded-full bg-blue-500 inline-block mr-1"></span><span class="text-xs text-gray-500">Backlog</span><div class="font-bold">16</div></div>
                </div>
                <div id="taskPerformanceChart" style="min-height: 300px;"></div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Presence Heatmap</h3>
                    <button class="btn btn-sm border text-xs">Last 4 Weeks</button>
                </div>
                <div id="presenceHeatmap" style="min-height: 300px;"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Task Priority Breakdown</h3>
                </div>
                <div id="priorityDonutChart" class="flex justify-center"></div>
                <div class="grid grid-cols-2 gap-2 mt-4 text-xs text-gray-600">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span> High (15)</div>
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-yellow-400 mr-2"></span> Medium (22)</div>
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> Low (8)</div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Top Active Projects</h3>
                    <button class="btn btn-sm border text-xs">View All</button>
                </div>
                <div class="space-y-4">
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">ERP Migration</h5>
                            <span class="text-xs font-bold text-emerald-600">75%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 w-3/4"></div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">Leader: Alexander J.</p>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">Mobile UI Redesign</h5>
                            <span class="text-xs font-bold text-orange-600">42%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-orange-500 w-[42%]"></div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">Leader: Rebecca S.</p>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">Cloud Security Audit</h5>
                            <span class="text-xs font-bold text-blue-600">90%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 w-[90%]"></div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">Leader: Connie W.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Pending Approvals</h3>
                    <button class="btn btn-sm border text-xs">Review</button>
                </div>
                <div class="space-y-4">
                    <div class="flex gap-3 items-center p-2 rounded-lg bg-orange-50/50 border border-orange-100">
                        <img src="https://i.pravatar.cc/150?img=5" class="w-10 h-10 rounded-full border-2 border-white">
                        <div class="flex-1">
                            <p class="text-sm text-gray-800"><span class="font-bold">Lori B.</span> applied for WFH</p>
                            <span class="text-[10px] text-gray-400">Reason: Power outage</span>
                        </div>
                        <div class="flex gap-1">
                            <button class="w-7 h-7 rounded bg-white border border-emerald-500 text-emerald-500 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition"><i data-lucide="check" class="w-4 h-4"></i></button>
                            <button class="w-7 h-7 rounded bg-white border border-red-500 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition"><i data-lucide="x" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                    <div class="flex gap-3 items-center p-2 rounded-lg bg-blue-50/50 border border-blue-100">
                        <img src="https://i.pravatar.cc/150?img=12" class="w-10 h-10 rounded-full border-2 border-white">
                        <div class="flex-1">
                            <p class="text-sm text-gray-800"><span class="font-bold">John D.</span> leave request</p>
                            <span class="text-[10px] text-gray-400">Casual Leave (2 Days)</span>
                        </div>
                        <div class="flex gap-1">
                            <button class="w-7 h-7 rounded bg-white border border-emerald-500 text-emerald-500 flex items-center justify-center"><i data-lucide="check" class="w-4 h-4"></i></button>
                            <button class="w-7 h-7 rounded bg-white border border-red-500 text-red-500 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Recent Team Activity</h3>
                    <button class="btn btn-sm border text-xs">Full Log</button>
                </div>
                <div class="pl-4 border-l border-dashed border-gray-200 ml-4 relative">
                    <div class="timeline-item">
                        <div class="timeline-icon bg-emerald-500"><i data-lucide="upload" class="w-3 h-3"></i></div>
                        <p class="text-sm font-medium">Lori B. uploaded project assets</p>
                        <span class="text-xs text-gray-400">10:45 AM</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-blue-500"><i data-lucide="message-square" class="w-3 h-3"></i></div>
                        <p class="text-sm font-medium">Group chat: Daily Standup started</p>
                        <span class="text-xs text-gray-400">09:15 AM</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-purple-500"><i data-lucide="clock" class="w-3 h-3"></i></div>
                        <p class="text-sm font-medium">Arthur D. logged 4.5 hours on Task #402</p>
                        <span class="text-xs text-gray-400">Yesterday</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Team Notifications</h3>
                </div>
                <div class="space-y-4">
                    <div class="flex gap-3 items-start border-b pb-3">
                        <div class="w-8 h-8 rounded bg-red-100 text-red-600 flex items-center justify-center shrink-0"><i data-lucide="bell" class="w-4 h-4"></i></div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">Deadline Approaching</p>
                            <p class="text-xs text-gray-500">ERP Migration milestone is due in 24 hours.</p>
                            <span class="text-[10px] text-gray-400">2h ago</span>
                        </div>
                    </div>
                    <div class="flex gap-3 items-start">
                        <div class="w-8 h-8 rounded bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i data-lucide="info" class="w-4 h-4"></i></div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">New Task Assigned</p>
                            <p class="text-xs text-gray-500">Manager assigned "Security Review" to your team.</p>
                            <span class="text-[10px] text-gray-400">5h ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // 1. TASK PERFORMANCE (Assigned vs Completed)
        new ApexCharts(document.querySelector("#taskPerformanceChart"), {
            series: [
                { name: 'Assigned', data: [80, 95, 87, 100, 110, 128] },
                { name: 'Completed', data: [75, 85, 82, 90, 105, 112] }
            ],
            chart: { type: 'bar', height: 300, toolbar: { show: false }, stacked: false },
            colors: ['#F97316', '#10B981'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun'] },
            grid: { borderColor: '#f3f4f6' }
        }).render();

        // 2. PRESENCE HEATMAP
        new ApexCharts(document.querySelector("#presenceHeatmap"), {
            series: [
                { name: 'Week 1', data: [12, 11, 12, 10, 12] },
                { name: 'Week 2', data: [10, 12, 12, 12, 11] },
                { name: 'Week 3', data: [12, 12, 10, 12, 12] },
                { name: 'Week 4', data: [11, 10, 12, 12, 12] }
            ],
            chart: { type: 'heatmap', height: 300, toolbar: { show: false } },
            colors: ['#F97316'],
            plotOptions: { heatmap: { radius: 2, enableShades: true, shadeIntensity: 0.5 } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] }
        }).render();

        // 3. PRIORITY DONUT
        new ApexCharts(document.querySelector("#priorityDonutChart"), {
            series: [15, 22, 8],
            labels: ['High', 'Medium', 'Low'],
            chart: { type: 'donut', height: 280 },
            colors: ['#EF4444', '#FBBF24', '#10B981'],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '75%' } } }
        }).render();
    </script>
</body>
</html>