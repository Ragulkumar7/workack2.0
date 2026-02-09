<?php
// TL/dashboard.php - Team Leader / Sales Dashboard

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. SIDEBAR PATH (Going up one level from 'TL' folder)
$sidebarPath = __DIR__ . '/../sidebars.php'; 
if (!file_exists($sidebarPath)) {
    $sidebarPath = 'sidebars.php'; // Fallback
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-gray);
            margin: 0; padding: 0;
            color: var(--text-dark);
        }

        /* --- LAYOUT --- */
        #mainContent { 
            margin-left: 95px; /* Matches sidebar width */
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

        /* --- BUTTONS --- */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;
            transition: 0.2s; cursor: pointer; border: 1px solid var(--border-color); background: white;
        }
        .btn:hover { background: #f3f4f6; }
        .btn-orange { background: #f97316; color: white; border-color: #f97316; }
        .btn-orange:hover { background: #ea580c; }

        /* --- STATUS BADGES --- */
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-contacted { background: #e0f2fe; color: #0284c7; }
        .badge-closed { background: #dcfce7; color: #166534; }
        .badge-lost { background: #fee2e2; color: #991b1b; }
        .badge-not { background: #f3e8ff; color: #7e22ce; }

        /* --- AVATARS --- */
        .avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        
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
                <h1 class="text-2xl font-bold text-gray-800">Leads Dashboard</h1>
                <nav class="flex text-gray-500 text-xs mt-1 gap-2">
                    <a href="#" class="hover:text-orange-500">Dashboard</a>
                    <span>/</span>
                    <span class="text-gray-800 font-semibold">Leads Dashboard</span>
                </nav>
            </div>
            <div class="flex gap-2">
                <button class="btn"><i data-lucide="download" class="w-4 h-4 mr-2"></i> Export</button>
                <div class="btn bg-white"><i data-lucide="calendar" class="w-4 h-4 mr-2 text-gray-400"></i> 02/03/2026 - 02/09/2026</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                        <i data-lucide="triangle" class="w-6 h-6 fill-current"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Total Leads</p>
                        <h3 class="text-2xl font-bold text-gray-800">6000</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-500 w-3/4"></div>
                    </div>
                    <p class="text-xs text-red-500 mt-2 font-semibold"><i data-lucide="trending-down" class="w-3 h-3 inline"></i> -4.01% <span class="text-gray-400 font-normal">from last week</span></p>
                </div>
            </div>

            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-teal-100 flex items-center justify-center text-teal-600">
                        <i data-lucide="target" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">New Leads</p>
                        <h3 class="text-2xl font-bold text-gray-800">120</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-teal-500 w-1/2"></div>
                    </div>
                    <p class="text-xs text-emerald-500 mt-2 font-semibold"><i data-lucide="trending-up" class="w-3 h-3 inline"></i> +20.01% <span class="text-gray-400 font-normal">from last week</span></p>
                </div>
            </div>

            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                        <i data-lucide="bar-chart-2" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Lost Leads</p>
                        <h3 class="text-2xl font-bold text-gray-800">30</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-red-500 w-1/4"></div>
                    </div>
                    <p class="text-xs text-emerald-500 mt-2 font-semibold"><i data-lucide="trending-up" class="w-3 h-3 inline"></i> +55% <span class="text-gray-400 font-normal">from last week</span></p>
                </div>
            </div>

            <div class="card flex flex-col justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase">Total Sales</p>
                        <h3 class="text-2xl font-bold text-gray-800">9895</h3>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-purple-500 w-3/5"></div>
                    </div>
                    <p class="text-xs text-emerald-500 mt-2 font-semibold"><i data-lucide="trending-up" class="w-3 h-3 inline"></i> +55% <span class="text-gray-400 font-normal">from last week</span></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Pipeline Stages</h3>
                    <button class="btn btn-sm border text-xs">2023 - 2024</button>
                </div>
                <div class="flex gap-6 mb-4">
                    <div><span class="block w-2 h-2 rounded-full bg-orange-500 inline-block mr-1"></span><span class="text-xs text-gray-500">Contacted</span><div class="font-bold">50000</div></div>
                    <div><span class="block w-2 h-2 rounded-full bg-teal-600 inline-block mr-1"></span><span class="text-xs text-gray-500">Opportunity</span><div class="font-bold">25985</div></div>
                    <div><span class="block w-2 h-2 rounded-full bg-blue-500 inline-block mr-1"></span><span class="text-xs text-gray-500">Not Contacted</span><div class="font-bold">12566</div></div>
                </div>
                <div id="pipelineChart" style="min-height: 300px;"></div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">New Leads</h3>
                    <button class="btn btn-sm border text-xs">This Week</button>
                </div>
                <div id="heatmapChart" style="min-height: 300px;"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Lost Leads</h3>
                    <div class="text-xs text-gray-500">Sales Pipeline <i data-lucide="chevron-down" class="w-3 h-3 inline"></i></div>
                </div>
                <div id="lostLeadsChart"></div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Leads By Companies</h3>
                    <button class="btn btn-sm border text-xs">This Week</button>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center font-bold">P</div>
                            <div>
                                <h5 class="font-bold text-sm">Pitch</h5>
                                <p class="text-xs text-gray-500">Value: $45,985</p>
                            </div>
                        </div>
                        <span class="badge badge-not">Not Contacted</span>
                    </div>
                    <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-600 text-white flex items-center justify-center font-bold">I</div>
                            <div>
                                <h5 class="font-bold text-sm">Initech</h5>
                                <p class="text-xs text-gray-500">Value: $21,145</p>
                            </div>
                        </div>
                        <span class="badge badge-closed">Closed</span>
                    </div>
                    <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-yellow-500 text-white flex items-center justify-center font-bold">U</div>
                            <div>
                                <h5 class="font-bold text-sm">Umbrella Corp</h5>
                                <p class="text-xs text-gray-500">Value: $15,685</p>
                            </div>
                        </div>
                        <span class="badge badge-contacted">Contacted</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Leads by Source</h3>
                    <button class="btn btn-sm border text-xs">This Week</button>
                </div>
                <div id="sourceChart" class="flex justify-center"></div>
                <div class="grid grid-cols-2 gap-2 mt-4 text-xs text-gray-600">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-teal-600 mr-2"></span> Google (40%)</div>
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-yellow-400 mr-2"></span> Paid (35%)</div>
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-pink-500 mr-2"></span> Campaign (15%)</div>
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-purple-500 mr-2"></span> Referrals (10%)</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Recent Follow Up</h3>
                    <button class="btn btn-sm border text-xs">View All</button>
                </div>
                <div class="space-y-4">
                    <?php 
                    $follows = [
                        ['img'=>'11', 'name'=>'Alexander Jermai', 'role'=>'UI/UX Designer'],
                        ['img'=>'12', 'name'=>'Doglas Martini', 'role'=>'Product Designer'],
                        ['img'=>'13', 'name'=>'Daniel Esbella', 'role'=>'Project Manager']
                    ];
                    foreach($follows as $f): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="https://i.pravatar.cc/150?img=<?= $f['img'] ?>" class="avatar">
                            <div>
                                <h5 class="font-bold text-sm text-gray-800"><?= $f['name'] ?></h5>
                                <p class="text-xs text-gray-500"><?= $f['role'] ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button class="w-8 h-8 rounded-full border flex items-center justify-center text-gray-500 hover:bg-gray-100"><i data-lucide="mail" class="w-4 h-4"></i></button>
                            <button class="w-8 h-8 rounded-full border flex items-center justify-center text-gray-500 hover:bg-gray-100"><i data-lucide="phone" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Recent Activities</h3>
                    <button class="btn btn-sm border text-xs">View All</button>
                </div>
                <div class="pl-4 border-l border-dashed border-gray-200 ml-4 relative">
                    <div class="timeline-item">
                        <div class="timeline-icon bg-emerald-500"><i data-lucide="phone" class="w-3 h-3"></i></div>
                        <p class="text-sm font-medium">Drain responded to your appointment</p>
                        <span class="text-xs text-gray-400">09:25 PM</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-blue-500"><i data-lucide="message-circle" class="w-3 h-3"></i></div>
                        <p class="text-sm font-medium">You sent 1 Message to James</p>
                        <span class="text-xs text-gray-400">10:25 PM</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-purple-500"><i data-lucide="user" class="w-3 h-3"></i></div>
                        <p class="text-sm font-medium">Meeting With <span class="font-bold">Abraham</span></p>
                        <span class="text-xs text-gray-400">09:25 PM</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Notifications</h3>
                    <button class="btn btn-sm border text-xs">View All</button>
                </div>
                <div class="space-y-4">
                    <div class="flex gap-3">
                        <img src="https://i.pravatar.cc/150?img=5" class="avatar">
                        <div>
                            <p class="text-sm text-gray-800"><span class="font-bold">Lex Murphy</span> requested access to...</p>
                            <span class="text-xs text-gray-400">Today at 9:42 AM</span>
                            <div class="mt-2 flex gap-2">
                                <span class="px-2 py-1 bg-gray-100 rounded text-xs border flex items-center gap-1"><i data-lucide="file" class="w-3 h-3"></i> EY_review.pdf</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <img src="https://i.pravatar.cc/150?img=8" class="avatar">
                        <div class="w-full">
                            <p class="text-sm text-gray-800"><span class="font-bold">Ray Arnold</span> requested access to...</p>
                            <span class="text-xs text-gray-400">Today at 10:50 AM</span>
                            <div class="mt-2 flex gap-2">
                                <button class="btn btn-orange text-xs py-1 px-3">Approve</button>
                                <button class="btn text-xs py-1 px-3">Decline</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Top Countries</h3>
                    <button class="btn btn-sm border text-xs">Referrals <i data-lucide="chevron-down" class="w-3 h-3"></i></button>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-1 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                            <div>
                                <h5 class="text-sm font-bold">Singapore</h5>
                                <p class="text-xs text-gray-400">Leads: 236</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-teal-600"></div>
                            <div>
                                <h5 class="text-sm font-bold">France</h5>
                                <p class="text-xs text-gray-400">Leads: 589</p>
                            </div>
                        </div>
                    </div>
                    <div id="countriesChart" class="w-32"></div>
                </div>
            </div>

            <div class="card col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Recent Leads</h3>
                    <button class="btn btn-sm border text-xs">View All</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-3 font-semibold text-gray-500">Company Name</th>
                                <th class="p-3 font-semibold text-gray-500">Stage</th>
                                <th class="p-3 font-semibold text-gray-500">Created Date</th>
                                <th class="p-3 font-semibold text-gray-500">Lead Owner</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr>
                                <td class="p-3 flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-xs">B</div>
                                    <span class="font-semibold text-gray-700">BrightWave</span>
                                </td>
                                <td class="p-3"><span class="badge badge-contacted">Contacted</span></td>
                                <td class="p-3 text-gray-500">14 Jan 2024</td>
                                <td class="p-3 text-gray-500">William Parsons</td>
                            </tr>
                            <tr>
                                <td class="p-3 flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-xs">S</div>
                                    <span class="font-semibold text-gray-700">Stellar</span>
                                </td>
                                <td class="p-3"><span class="badge badge-closed">Closed</span></td>
                                <td class="p-3 text-gray-500">21 Jan 2024</td>
                                <td class="p-3 text-gray-500">Lucille Tomberlin</td>
                            </tr>
                            <tr>
                                <td class="p-3 flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">Q</div>
                                    <span class="font-semibold text-gray-700">Quantum</span>
                                </td>
                                <td class="p-3"><span class="badge badge-lost">Lost</span></td>
                                <td class="p-3 text-gray-500">20 Feb 2024</td>
                                <td class="p-3 text-gray-500">Fred Johnson</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // 1. PIPELINE CHART
        new ApexCharts(document.querySelector("#pipelineChart"), {
            series: [{ name: 'Leads', data: [44, 55, 41, 67, 22, 43, 21, 33, 45, 31, 87, 65] }],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            colors: ['#F97316'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
            grid: { borderColor: '#f3f4f6' }
        }).render();

        // 2. HEATMAP
        new ApexCharts(document.querySelector("#heatmapChart"), {
            series: [
                { name: 'Mon', data: [20, 30, 40, 20] },
                { name: 'Tue', data: [30, 40, 30, 50] },
                { name: 'Wed', data: [40, 20, 50, 20] },
                { name: 'Thu', data: [20, 50, 20, 30] }
            ],
            chart: { type: 'heatmap', height: 300, toolbar: { show: false } },
            colors: ['#F97316'],
            plotOptions: { heatmap: { radius: 2, enableShades: true, shadeIntensity: 0.5 } },
            dataLabels: { enabled: false }
        }).render();

        // 3. LOST LEADS
        new ApexCharts(document.querySelector("#lostLeadsChart"), {
            series: [{ name: 'Lost', data: [10, 20, 15, 30, 25] }],
            chart: { type: 'area', height: 250, toolbar: { show: false } },
            colors: ['#ef4444'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.1 } },
            stroke: { curve: 'smooth', width: 2 }
        }).render();

        // 4. SOURCE DONUT
        new ApexCharts(document.querySelector("#sourceChart"), {
            series: [40, 35, 15, 10],
            labels: ['Google', 'Paid', 'Campaigns', 'Referrals'],
            chart: { type: 'donut', height: 280 },
            colors: ['#0d9488', '#facc15', '#ec4899', '#a855f7'],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '75%', labels: { show: true, total: { show: true, label: 'Google', fontSize: '14px', color: '#6b7280' } } } } }
        }).render();

        // 5. COUNTRIES PIE
        new ApexCharts(document.querySelector("#countriesChart"), {
            series: [589, 350, 221],
            chart: { type: 'donut', width: 140 },
            colors: ['#0d9488', '#F97316', '#3b82f6'],
            legend: { show: false },
            dataLabels: { enabled: false }
        }).render();
    </script>
</body>
</html>