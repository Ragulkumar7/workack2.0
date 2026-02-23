<?php 
include '../../sidebars.php'; 
include '../../header.php';
?>
<?php
// Mock Data for the Charts
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$leadsData = [0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
$opportunitiesData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

$sourceLabels = ['Facebook', 'Website', 'Email', 'Instagram', 'Other'];
$sourceData = [20, 20, 20, 20, 20]; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #fafafa;
        }

        .main-content {
            margin-left: 95px;
            width: calc(100% - 95px);
            transition: all 0.3s ease;
            box-sizing: border-box;
            padding: 30px;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding-top: 80px;
                padding-left: 15px;
                padding-right: 15px;
            }
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800">

    <main class="main-content">
        <h1 class="text-2xl font-bold text-slate-900 mb-6">Analytics Dashboard</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center text-center">
                <p class="text-sm text-gray-500 mb-1">Good Morning, admin</p>
                <h2 class="text-4xl font-bold text-[#1e293b] mb-1 tracking-tight">04:16 PM</h2>
                <p class="text-sm text-gray-400 mb-8">23 Feb 2026</p>
                
                <div class="relative w-36 h-36 mb-8 flex items-center justify-center">
                    <svg class="absolute w-full h-full transform -rotate-90">
                        <circle cx="72" cy="72" r="68" stroke="#e2e8f0" stroke-width="4" fill="transparent" />
                        <circle cx="72" cy="72" r="68" stroke="#3b82f6" stroke-width="4" fill="transparent" stroke-dasharray="427" stroke-dashoffset="213" stroke-linecap="round" />
                        <circle cx="72" cy="72" r="68" stroke="#10b981" stroke-width="4" fill="transparent" stroke-dasharray="427" stroke-dashoffset="320" stroke-linecap="round" />
                    </svg>
                    <div class="w-32 h-32 rounded-full bg-[#1b4343] flex items-center justify-center text-white text-4xl font-semibold border-4 border-white shadow-lg">
                        AD
                    </div>
                </div>

                <div class="w-full bg-[#1b5a5a] text-white font-bold py-3 rounded-xl mb-6 text-sm">
                    Production : 0.00 hrs
                </div>

                <div class="flex items-center justify-center gap-2 text-emerald-600 font-medium text-sm mb-6">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    <span>Not Punched In</span>
                </div>

                <button class="w-full bg-[#1b5a5a] hover:bg-[#134040] text-white font-bold py-4 rounded-xl transition-all shadow-md active:scale-[0.98]">
                    Punch In
                </button>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-teal-50 text-[#1b5a5a] p-2 rounded-lg">
                        <i data-lucide="trending-up" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-slate-800">Monthly Leads</h2>
                </div>
                <div class="relative h-[400px] w-full">
                    <canvas id="leadsChart"></canvas>
                </div>
            </div>

            <div class="bg-white overflow-hidden rounded-2xl border border-gray-100 shadow-sm flex flex-col">
                <div class="bg-[#1b5a5a] p-8 flex flex-col items-center text-center">
                    <div class="relative mb-4">
                        <div class="w-24 h-24 rounded-full border-2 border-white flex items-center justify-center text-white text-3xl font-bold">
                            SP
                        </div>
                        <div class="absolute bottom-1 right-1 w-6 h-6 bg-[#4ade80] border-4 border-[#1b5a5a] rounded-full"></div>
                    </div>
                    <h2 class="text-white text-xl font-bold">Stephen Peralt</h2>
                    <p class="text-teal-100 text-sm mt-1">Senior Software Engineer</p>
                    <button class="mt-4 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold py-2 px-6 rounded-full border border-white/30 backdrop-blur-sm transition-all">
                        Verified Account
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-teal-50 rounded-lg flex items-center justify-center text-[#1b5a5a]">
                            <i data-lucide="phone" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Phone</p>
                            <p class="text-sm font-bold text-slate-700">+1 234 567 890</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-teal-50 rounded-lg flex items-center justify-center text-[#1b5a5a]">
                            <i data-lucide="mail" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Email</p>
                            <p class="text-sm font-bold text-slate-700">employee@gmail.com</p>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-2"></div>

                    <div class="bg-emerald-50/50 p-3 rounded-xl flex justify-between items-center px-4">
                        <div class="flex items-center gap-2 text-emerald-700">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                            <span class="text-sm font-semibold">Joined</span>
                        </div>
                        <span class="text-sm font-bold text-slate-700">15 Jan 2024</span>
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-2"></div>

                    <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-2">Professional Info</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                            <p class="text-[10px] text-gray-400 font-bold uppercase">Experience</p>
                            <p class="text-sm font-bold text-slate-700">Fresher</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                            <p class="text-[10px] text-gray-400 font-bold uppercase">Department</p>
                            <p class="text-sm font-bold text-slate-700">Development Team</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm lg:col-span-2">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-teal-50 text-[#1b5a5a] p-2 rounded-lg">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-slate-800">Monthly Opportunities</h2>
                </div>
                <div class="relative h-[300px] w-full">
                    <canvas id="opportunitiesChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-teal-50 text-[#1b5a5a] p-2 rounded-lg">
                        <i data-lucide="pie-chart" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-slate-800">Lead Sources</h2>
                </div>
                <div class="relative h-[300px] w-full flex justify-center">
                    <canvas id="sourceChart"></canvas>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', borderDash: [4, 4], drawBorder: false },
                    ticks: { color: '#64748b', font: { size: 12 }, padding: 10, stepSize: 2 },
                    border: { display: false }
                },
                x: {
                    grid: { color: '#f1f5f9', borderDash: [4, 4], drawBorder: false },
                    ticks: { color: '#64748b', font: { size: 12 }, padding: 10 },
                    border: { display: false }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, color: '#1b5a5a', padding: 20, font: { size: 13 } }
                }
            }
        };

        const months = <?php echo json_encode($months); ?>;
        
        const leadsCtx = document.getElementById('leadsChart').getContext('2d');
        new Chart(leadsCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'leads',
                    data: <?php echo json_encode($leadsData); ?>,
                    borderColor: '#1b5a5a',
                    backgroundColor: '#1b5a5a',
                    borderWidth: 2,
                    pointBackgroundColor: '#1b5a5a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4
                }]
            },
            options: commonOptions
        });

        const oppCtx = document.getElementById('opportunitiesChart').getContext('2d');
        new Chart(oppCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'opportunities',
                    data: <?php echo json_encode($opportunitiesData); ?>,
                    borderColor: '#1b5a5a',
                    backgroundColor: '#1b5a5a',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4
                }]
            },
            options: { ...commonOptions, scales: { ...commonOptions.scales, y: { ...commonOptions.scales.y, max: 4, stepSize: 1 } } }
        });

        const sourceCtx = document.getElementById('sourceChart').getContext('2d');
        new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($sourceLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($sourceData); ?>,
                    backgroundColor: ['#1b5a5a', '#2c7a7b', '#38b2ac', '#81e6d9', '#e6fffa'],
                    borderWidth: 4,
                    borderColor: '#ffffff'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%' }
        });
    </script>
</body>
</html>