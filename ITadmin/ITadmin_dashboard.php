<?php
// Session check and DB connection
session_start();
// include 'db_connect.php'; 

// --- MOCK DATA (Replace with DB queries) ---

// Employee Data
$employee_name = "Abishek";
$employee_role = "IT System Administrator";
$employee_phone = "+91 98765 43210";
$employee_email = "admin@e-bhojan.com";
$joining_date = "15 Jan 2024";
$profile_img = "https://ui-avatars.com/api/?name=Abishek&background=0f766e&color=fff&size=128";

// Attendance Data
$total_seconds_worked = 14400; // e.g., 4 hours
$is_on_break = false;
$attendance_record = ['punch_in' => '09:00 AM', 'punch_out' => null];
$display_punch_in = "09:00 AM";
$total_hours_today = "04:00:00";

// Professional Data
$user_info = [
    'experience_label' => '4 Years',
    'department' => 'IT Operations',
    'emergency_contacts' => '[{"name":"Ramesh","phone":"+91 99887 76655"}]'
];

// IT Admin Stats
$pending_tickets = 12;
$internal_tickets = 5;
$external_tickets = 7;
$resolved_today = 3;

// Critical Tickets Array
$critical_tickets = [
    ['id' => 'TKT-86050', 'subject' => 'Server Down', 'time' => '2 hrs ago', 'category' => 'Internal', 'raised_by' => 'Rajesh', 'initial' => 'R', 'status' => 'Critical', 'status_color' => 'bg-red-100 text-red-600 border-red-200'],
    ['id' => 'TKT-86051', 'subject' => 'Gateway Timeout', 'time' => '3 hrs ago', 'category' => 'Vendor', 'raised_by' => 'Finance', 'initial' => 'F', 'status' => 'High', 'status_color' => 'bg-orange-100 text-orange-600 border-orange-200'],
    ['id' => 'TKT-86052', 'subject' => 'DB Sync Fail', 'time' => '5 hrs ago', 'category' => 'SysAdmin', 'raised_by' => 'System', 'initial' => 'S', 'status' => 'Critical', 'status_color' => 'bg-red-100 text-red-600 border-red-200'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Admin Dashboard - <?php echo htmlspecialchars($employee_name); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        :root {
            --primary-sidebar-width: 90px;
            --secondary-sidebar-width: 240px;
        }

        body { 
            background-color: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b;
            overflow-x: hidden;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%; 
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 1.5rem;
            flex-grow: 1;
        }

        /* Progress Ring for Punch In */
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        /* Scrollbars */
        .custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f8fafc; }

        /* Dashboard Grid System */
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            align-items: stretch; 
        }

        /* Sidebar Transition Logic */
        #mainContent {
            margin-left: var(--primary-sidebar-width);
            width: calc(100% - var(--primary-sidebar-width));
            transition: all 0.3s ease;
        }

        body.secondary-open #mainContent {
            margin-left: calc(var(--primary-sidebar-width) + var(--secondary-sidebar-width));
            width: calc(100% - (var(--primary-sidebar-width) + var(--secondary-sidebar-width)));
        }
        
        @media (max-width: 1024px) {
            .dashboard-container { grid-template-columns: 1fr; }
            #mainContent, body.secondary-open #mainContent { 
                margin-left: 0; 
                width: 100%; 
            }
            .col-span-3, .col-span-4, .col-span-5, .col-span-8, .col-span-12 { grid-column: span 12 !important; }
        }
    </style>
</head>
<body class="bg-slate-100">

    <div class="fixed inset-y-0 left-0 z-50">
        <?php include '../sidebars.php'; ?>
    </div>

    <main id="mainContent" class="min-h-screen">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <?php include '../header.php'; ?>
        </header>

        <div class="p-6 lg:p-8">
            
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 tracking-tight flex items-center gap-3">
                        IT Admin Dashboard
                    </h1>
                    <p class="text-slate-500 text-sm mt-1">Welcome back, <b><?php echo htmlspecialchars($employee_name); ?></b></p>
                </div>
                <div class="flex gap-3">
                    <div class="bg-white border border-gray-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 shadow-sm flex items-center gap-2">
                        <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-container">

                <div class="col-span-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-2">
                    <div class="card border-l-4 border-l-red-500">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Pending Tickets</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $pending_tickets; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-500 text-xl">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card border-l-4 border-l-teal-600">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Internal (SysAdmin)</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $internal_tickets; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 text-xl">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card border-l-4 border-l-blue-500">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">External (Vendor)</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $external_tickets; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl">
                                <i class="fas fa-network-wired"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card border-l-4 border-l-green-500">
                        <div class="card-body flex justify-between items-center p-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Resolved Today</p>
                                <h2 class="text-3xl font-black text-slate-800"><?php echo $resolved_today; ?></h2>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                    <div class="card">
                        <div class="card-body flex flex-col items-center">
                            <div class="text-center mb-6">
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Today's Attendance</h3>
                                <p class="text-lg font-bold text-slate-800 mt-1"><?php echo date("h:i A, d M Y"); ?></p>
                            </div>

                            <div class="relative w-40 h-40 mb-6">
                                <svg class="w-full h-full transform -rotate-90">
                                    <circle cx="80" cy="80" r="70" stroke="#f1f5f9" stroke-width="12" fill="transparent"></circle>
                                    <?php 
                                        $pct = min(1, $total_seconds_worked / 32400); 
                                        $dashoffset = 440 - ($pct * 440);
                                        $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                                    ?>
                                    <circle cx="80" cy="80" r="70" stroke="<?php echo $ringColor; ?>" stroke-width="12" fill="transparent" 
                                        stroke-dasharray="440" stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset); ?>" 
                                        stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase"><?php echo $is_on_break ? 'ON BREAK' : 'Total Hours'; ?></p>
                                    <p class="text-2xl font-bold text-slate-800" id="liveTimer" 
                                       data-running="<?php echo ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>"
                                       data-total="<?php echo $total_seconds_worked; ?>">
                                       <?php echo $total_hours_today; ?>
                                    </p>
                                </div>
                            </div>

                            <form method="POST" class="w-full">
                                <?php if (!$attendance_record): ?>
                                    <button type="submit" name="action" value="punch_in" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-right-to-bracket"></i> Punch In
                                    </button>
                                <?php elseif (!$attendance_record['punch_out']): ?>
                                    <div class="grid grid-cols-2 gap-3 w-full">
                                        <?php if ($is_on_break): ?>
                                            <button type="submit" name="action" value="break_end" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-xl shadow transition">
                                                <i class="fa-solid fa-play"></i> Resume
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="break_start" class="bg-amber-400 hover:bg-amber-500 text-white font-bold py-3 rounded-xl shadow transition">
                                                <i class="fa-solid fa-mug-hot"></i> Break
                                            </button>
                                        <?php endif; ?>
                                        <button type="submit" name="action" value="punch_out" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl shadow transition">
                                            <i class="fa-solid fa-right-from-bracket"></i> Out
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-3 rounded-xl cursor-not-allowed">
                                        <i class="fa-solid fa-check-circle"></i> Shift Completed
                                    </button>
                                <?php endif; ?>
                            </form>

                            <p class="text-xs text-gray-400 mt-4 flex items-center gap-1">
                                <i class="fa-solid fa-fingerprint text-orange-500"></i> 
                                Punched In at: <span class="font-bold text-slate-600"><?php echo $display_punch_in; ?></span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5 flex flex-col gap-6">
                    <div class="card">
                        <div class="card-body p-0 flex flex-col h-full">
                            <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                                <h3 class="font-bold text-slate-800 text-md">
                                    <i class="fas fa-list-ul text-teal-600 mr-2"></i> Action Required
                                </h3>
                                <a href="manage_tickets.php" class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-1 rounded hover:bg-slate-200 transition">View All</a>
                            </div>
                            
                            <div class="overflow-x-auto custom-scroll p-2 flex-grow">
                                <table class="w-full text-left border-collapse whitespace-nowrap">
                                    <thead>
                                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                            <th class="p-3">Ticket</th>
                                            <th class="p-3">Subject</th>
                                            <th class="p-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <?php foreach($critical_tickets as $ticket): ?>
                                        <tr class="hover:bg-slate-50 transition group">
                                            <td class="p-3">
                                                <span class="font-bold text-slate-700 text-xs">#<?php echo $ticket['id']; ?></span>
                                                <p class="text-[9px] text-gray-400 mt-0.5"><?php echo $ticket['time']; ?></p>
                                            </td>
                                            <td class="p-3">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-xs text-slate-800"><?php echo $ticket['subject']; ?></span>
                                                    <div class="flex items-center gap-1 mt-0.5">
                                                        <div class="w-3 h-3 rounded-full bg-teal-600 text-white flex items-center justify-center text-[7px] font-bold">
                                                            <?php echo $ticket['initial']; ?>
                                                        </div>
                                                        <span class="text-[10px] text-gray-500"><?php echo $ticket['raised_by']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <span class="text-[9px] px-2 py-0.5 border rounded-full font-black uppercase tracking-wider <?php echo $ticket['status_color']; ?>">
                                                    <?php echo $ticket['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-3 flex flex-col gap-6">
                    <div class="card overflow-hidden">
                        <div class="bg-teal-700 p-6 flex flex-col items-center text-center">
                            <div class="relative mb-3">
                                <img src="<?php echo $profile_img; ?>" class="w-20 h-20 rounded-full border-4 border-white shadow-lg object-cover">
                                <div class="absolute bottom-1 right-1 w-5 h-5 bg-green-400 border-2 border-white rounded-full"></div>
                            </div>
                            <h2 class="text-white font-bold text-md"><?php echo htmlspecialchars($employee_name); ?></h2>
                            <p class="text-teal-200 text-xs mb-3"><?php echo htmlspecialchars($employee_role); ?></p>
                            <span class="bg-white/20 text-white text-[10px] px-2 py-1 rounded-full font-bold">Verified Account</span>
                        </div>
                        <div class="card-body p-5 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700 text-xs">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <div>
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Phone</p>
                                    <p class="text-xs font-semibold text-slate-800"><?php echo htmlspecialchars($employee_phone); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700 text-xs">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                                <div>
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Email</p>
                                    <p class="text-xs font-semibold text-slate-800 truncate w-32" title="<?php echo htmlspecialchars($employee_email); ?>">
                                        <?php echo htmlspecialchars($employee_email); ?>
                                    </p>
                                </div>
                            </div>
                            <hr class="border-dashed border-gray-200">
                            
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Experience</p>
                                    <p class="text-[11px] font-bold text-slate-700"><?php echo htmlspecialchars($user_info['experience_label'] ?? 'Fresher'); ?></p>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Department</p>
                                    <p class="text-[11px] font-bold text-slate-700"><?php echo htmlspecialchars($user_info['department'] ?? 'General'); ?></p>
                                </div>
                            </div>

                            <?php
                            $emergency = json_decode($user_info['emergency_contacts'] ?? '[]', true);
                            if (!empty($emergency)): 
                                $primary = $emergency[0]; ?>
                                <div class="p-2 bg-red-50 rounded-lg border border-red-100">
                                    <div class="flex items-center gap-1 mb-1">
                                        <i class="fa-solid fa-heart-pulse text-red-500 text-[9px]"></i>
                                        <span class="text-[9px] font-bold text-red-700 uppercase">Emergency Contact</span>
                                    </div>
                                    <p class="text-[11px] font-bold text-slate-800"><?php echo htmlspecialchars($primary['name']); ?>: <span class="text-slate-500 font-normal"><?php echo htmlspecialchars($primary['phone']); ?></span></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-slate-800 text-lg">Ticket Volume Trend</h3>
                                <span class="text-[10px] bg-slate-100 px-2 py-1 rounded font-bold text-gray-500 uppercase tracking-widest">This Week</span>
                            </div>
                            <div id="volumeChart"></div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="card bg-slate-800 border-slate-700 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-10">
                            <i class="fas fa-server text-8xl"></i>
                        </div>
                        <div class="card-body relative z-10 flex flex-col justify-center">
                            <h3 class="font-bold text-teal-400 text-sm tracking-widest uppercase mb-1">System Health</h3>
                            <h2 class="text-3xl font-black mb-4">99.98%</h2>
                            
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs mb-1 font-semibold text-slate-300">
                                        <span>Server Load</span>
                                        <span>42%</span>
                                    </div>
                                    <div class="w-full bg-slate-700 rounded-full h-1.5">
                                        <div class="bg-teal-400 h-1.5 rounded-full" style="width: 42%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1 font-semibold text-slate-300">
                                        <span>Memory Usage</span>
                                        <span>68%</span>
                                    </div>
                                    <div class="w-full bg-slate-700 rounded-full h-1.5">
                                        <div class="bg-yellow-400 h-1.5 rounded-full" style="width: 68%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // 1. Sidebar Toggle Logic Support
            window.toggleSecondaryMenuLayout = function(isOpen) {
                if(isOpen) {
                    document.body.classList.add('secondary-open');
                } else {
                    document.body.classList.remove('secondary-open');
                }
            };

            // 2. Ticket Volume Area Chart (ApexCharts)
            var volumeOptions = {
                series: [{
                    name: 'Internal Tickets',
                    data: [12, 18, 15, 22, 14, 28, 19]
                }, {
                    name: 'Vendor Tickets',
                    data: [5, 8, 4, 11, 7, 13, 9]
                }],
                chart: {
                    type: 'area',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif'
                },
                colors: ['#0d9488', '#3b82f6'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: {
                    categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: '#94a3b8', fontSize: '10px', fontWeight: 600 } }
                },
                yaxis: {
                    labels: { style: { colors: '#94a3b8', fontSize: '10px', fontWeight: 600 } }
                },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 4,
                    yaxis: { lines: { show: true } }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '12px',
                    fontWeight: 600,
                    markers: { radius: 12 }
                },
                tooltip: { theme: 'light' }
            };
            var volumeChart = new ApexCharts(document.querySelector("#volumeChart"), volumeOptions);
            volumeChart.render();

            // 3. Live Timer Logic for Attendance
            const timerElement = document.getElementById('liveTimer');
            const progressRing = document.getElementById('progressRing');
            const isRunning = timerElement.getAttribute('data-running') === 'true';
            
            let totalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
            const startTime = new Date().getTime(); 

            function updateTimer() {
                if (!isRunning) return; 

                const now = new Date().getTime();
                const diffSeconds = Math.floor((now - startTime) / 1000);
                const currentTotal = totalSeconds + diffSeconds;
                
                const hours = Math.floor(currentTotal / 3600);
                const minutes = Math.floor((currentTotal % 3600) / 60);
                const seconds = currentTotal % 60;
                
                const formattedTime = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');
                
                timerElement.innerText = formattedTime;

                // Update Progress Ring (9 hours = 32400 sec)
                const maxSeconds = 32400; 
                const circumference = 440;
                const progress = Math.min(currentTotal / maxSeconds, 1);
                const offset = circumference - (progress * circumference);
                if(progressRing) {
                    progressRing.style.strokeDashoffset = offset;
                }
            }

            if (isRunning) {
                setInterval(updateTimer, 1000);
            }
        });
        
        // Sidebar active handler
        function handleNavClick(item, element) {
            const panel = document.getElementById('secondaryPanel');
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            if (item.subItems && item.subItems.length > 0) {
                panel.classList.add('open');
                document.body.classList.add('secondary-open'); 
            } else {
                if (panel) panel.classList.remove('open');
                document.body.classList.remove('secondary-open'); 
                if (item.path && item.path !== '#') window.location.href = item.path;
            }
        }
    </script>
</body>
</html>