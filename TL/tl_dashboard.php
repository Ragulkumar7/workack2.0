<?php
// TL/tl_dashboard.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. SIDEBAR INCLUDE
$sidebarPath = __DIR__ . '/../sidebars.php'; 

// 3. USER NAME CHECK (Fixing the Error)
// We use the null coalescing operator (??) to provide a fallback if 'user_name' isn't set.
$userName = $_SESSION['user_name'] ?? 'Team Leader'; 
// Simulation for testing if session is empty (You can remove this line in production)
if(!isset($_SESSION['user_id'])) { $_SESSION['user_id'] = 1; } 
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
            --sidebar-width: 95px; /* Adjust if your sidebar is wider */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-gray);
            margin: 0; padding: 0;
            color: var(--text-dark);
        }

        /* --- LAYOUT --- */
        #mainContent {
    margin-left: var(--primary-sidebar-width); /* Uses the 95px variable from sidebar.php */
    width: calc(100% - var(--primary-sidebar-width));
    transition: all 0.3s ease; /* Smooth movement when secondary opens */
    padding: 25px 35px;
    padding-top: 0 !important;

}
#mainContent.main-shifted {
    margin-left: calc(var(--primary-sidebar-width) + var(--secondary-sidebar-width));
    width: calc(100% - (var(--primary-sidebar-width) + var(--secondary-sidebar-width)));
}
        
        @media (max-width: 768px) {
    #mainContent { 
        margin-left: 0 !important; 
        width: 100% !important;
        padding: 15px; 
    }
}

        /* --- CARDS --- */
        .card { 
            background: white; 
            border-radius: 12px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
            padding: 20px;
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;
            transition: 0.2s; cursor: pointer; border: 1px solid var(--border-color); background: white;
        }
        .btn:hover { background: #f3f4f6; }
        
        /* Specific Button Styles from Image */
        .btn-punch {
            background-color: #111827; /* Dark Slate/Black */
            color: white;
            border: none;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-punch:hover { background-color: #1f2937; }
        
        .btn-break {
            background-color: #f59e0b; /* Amber */
            color: white;
            border: none;
        }

        /* --- TIMELINE --- */
        .timeline-item { position: relative; padding-left: 24px; padding-bottom: 24px; border-left: 1px dashed #e5e7eb; }
        .timeline-item:last-child { border-left: none; }
        .timeline-icon { 
            position: absolute; left: -16px; top: 0; width: 32px; height: 32px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;
        }

        /* --- ATTENDANCE RING (CSS Gradient for the circle around image) --- */
        .profile-ring-container {
            position: relative;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            /* Conic gradient to mimic the progress bar in the image */
            background: conic-gradient(#10b981 0% 65%, #3b82f6 65% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .profile-ring-inner {
            width: 128px;
            height: 128px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .profile-img {
            width: 115px;
            height: 115px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent">
        <?php 
        $path_to_root = '../'; // Set this so header links (settings/logout) work correctly
        include('../header.php'); 
        ?>
        
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
                <div class="btn bg-white"><i data-lucide="calendar" class="w-4 h-4 mr-2 text-gray-400"></i> Current Week</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
            
            <div class="card flex flex-col items-center justify-between text-center col-span-1 lg:row-span-2 h-full shadow-lg border-orange-100">
                
                <div class="mt-2">
                    <p class="text-gray-500 font-medium">Good Morning, <?php echo $userName; ?></p>
                    <h2 class="text-3xl font-bold text-gray-800 mt-1" id="liveClock">00:00 AM</h2>
                    <p class="text-sm text-gray-400 font-medium mt-1" id="liveDate">11 Mar 2025</p>
                </div>

                <div class="my-6 relative">
                    <div class="profile-ring-container">
                        <div class="profile-ring-inner">
                            <img src="https://i.pravatar.cc/300?img=11" alt="Profile" class="profile-img">
                        </div>
                    </div>
                </div>

                <div class="bg-orange-500 text-white px-6 py-2 rounded-lg shadow-md mb-4 w-full max-w-[200px]">
                    <span class="text-sm font-medium">Production : <span id="productionTimer">0.00</span> hrs</span>
                </div>

                <div class="flex items-center justify-center gap-2 text-gray-600 mb-6" id="statusDisplay">
                    <i data-lucide="fingerprint" class="w-5 h-5 text-orange-500"></i>
                    <span class="font-medium text-sm">Not Punched In</span>
                </div>

                <div class="w-full space-y-3">
                    <button id="mainPunchBtn" onclick="handlePunch()" class="btn-punch">
                        Punch In
                    </button>
                    
                    <button id="breakBtn" onclick="toggleBreak()" class="btn w-full border-orange-200 text-orange-600 hover:bg-orange-50 hidden">
                        <i data-lucide="coffee" class="w-4 h-4 mr-2"></i> Take a Break
                    </button>
                </div>
            </div>

            <div class="col-span-1 lg:col-span-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                            <i data-lucide="users" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Total Team</p>
                            <h3 class="text-2xl font-bold text-gray-800">12</h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 w-full"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 font-semibold">Engineering Dept.</p>
                    </div>
                </div>

                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                            <i data-lucide="user-check" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Present</p>
                            <h3 class="text-2xl font-bold text-gray-800">10 / 12</h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 w-[83%]"></div>
                        </div>
                        <p class="text-xs text-emerald-500 mt-2 font-semibold">83% Attendance</p>
                    </div>
                </div>

                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                            <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Tasks</p>
                            <h3 class="text-2xl font-bold text-gray-800">45</h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-orange-500 w-3/4"></div>
                        </div>
                        <p class="text-xs text-orange-400 mt-2 font-semibold">12 due this week</p>
                    </div>
                </div>

                <div class="card flex flex-col justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                            <i data-lucide="zap" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Efficiency</p>
                            <h3 class="text-2xl font-bold text-gray-800">92%</h3>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-500 w-[92%]"></div>
                        </div>
                        <p class="text-xs text-emerald-500 mt-2 font-semibold">+5% growth</p>
                    </div>
                </div>
            
                <div class="card col-span-1 md:col-span-2 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Task Progress</h3>
                        <div class="flex gap-2 text-xs">
                             <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-orange-500 mr-1"></span> Assigned</span>
                             <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-emerald-500 mr-1"></span> Done</span>
                        </div>
                    </div>
                    <div id="taskPerformanceChart" style="min-height: 220px;"></div>
                </div>

                <div class="card col-span-1 md:col-span-2 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Presence</h3>
                    </div>
                    <div id="presenceHeatmap" style="min-height: 220px;"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card">
                <h3 class="font-bold text-lg mb-4">Task Priority</h3>
                <div id="priorityDonutChart" class="flex justify-center"></div>
                <div class="grid grid-cols-3 gap-1 mt-4 text-xs text-gray-600 text-center">
                    <div><span class="block text-red-500 font-bold">15</span>High</div>
                    <div><span class="block text-yellow-500 font-bold">22</span>Med</div>
                    <div><span class="block text-emerald-500 font-bold">8</span>Low</div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Active Projects</h3>
                    <button class="text-xs text-blue-500 hover:underline">View All</button>
                </div>
                <div class="space-y-4">
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">ERP Migration</h5>
                            <span class="text-xs font-bold text-emerald-600">75%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full">
                            <div class="h-full bg-emerald-500 w-3/4 rounded-full"></div>
                        </div>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-gray-50 transition">
                        <div class="flex justify-between mb-2">
                            <h5 class="font-bold text-sm">Mobile UI</h5>
                            <span class="text-xs font-bold text-orange-600">42%</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full">
                            <div class="h-full bg-orange-500 w-[42%] rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Approvals</h3>
                    <span class="badge badge-high bg-red-50 text-red-500 px-2 py-1 rounded">2 New</span>
                </div>
                <div class="space-y-4">
                    <div class="flex gap-3 items-center p-2 rounded-lg bg-orange-50 border border-orange-100">
                        <img src="https://i.pravatar.cc/150?img=5" class="w-10 h-10 rounded-full">
                        <div class="flex-1">
                            <p class="text-sm font-bold">Lori B.</p>
                            <span class="text-[10px] text-gray-500">WFH Request</span>
                        </div>
                        <button class="text-emerald-500 hover:bg-emerald-100 p-1 rounded"><i data-lucide="check" class="w-4 h-4"></i></button>
                        <button class="text-red-500 hover:bg-red-100 p-1 rounded"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>
                    <div class="flex gap-3 items-center p-2 rounded-lg bg-blue-50 border border-blue-100">
                        <img src="https://i.pravatar.cc/150?img=12" class="w-10 h-10 rounded-full">
                        <div class="flex-1">
                            <p class="text-sm font-bold">John D.</p>
                            <span class="text-[10px] text-gray-500">Leave (2 Days)</span>
                        </div>
                        <button class="text-emerald-500 hover:bg-emerald-100 p-1 rounded"><i data-lucide="check" class="w-4 h-4"></i></button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        /* ==============================
           1. LIVE CLOCK & DATE LOGIC
           ============================== */
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            hours = String(hours).padStart(2, '0');

            document.getElementById('liveClock').textContent = `${hours}:${minutes} ${ampm}`;
            
            // Date Format: 11 Mar 2025
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            document.getElementById('liveDate').textContent = now.toLocaleDateString('en-GB', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        /* ==============================
           2. ATTENDANCE & TIMER LOGIC
           ============================== */
        let timerInterval;
        let secondsElapsed = 0;
        let isPunchedIn = false;
        let isOnBreak = false;

        // Restore state from LocalStorage on load (Simulating Database)
        window.addEventListener('load', () => {
            const savedState = localStorage.getItem('attendanceState');
            const savedTime = localStorage.getItem('punchTime');
            const savedSeconds = localStorage.getItem('secondsElapsed');

            if (savedSeconds) secondsElapsed = parseInt(savedSeconds);

            if (savedState === 'punchedIn') {
                isPunchedIn = true;
                setUIState('in', savedTime);
                startTimer();
            } else if (savedState === 'onBreak') {
                isPunchedIn = true;
                isOnBreak = true;
                setUIState('break', savedTime);
                // Don't start timer on break
            }
            updateTimerDisplay();
        });

        function handlePunch() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            if (!isPunchedIn) {
                // ACTION: PUNCH IN
                isPunchedIn = true;
                localStorage.setItem('attendanceState', 'punchedIn');
                localStorage.setItem('punchTime', timeString);
                setUIState('in', timeString);
                startTimer();
            } else {
                // ACTION: PUNCH OUT
                stopTimer();
                isPunchedIn = false;
                isOnBreak = false;
                secondsElapsed = 0; // Reset or save to DB
                localStorage.removeItem('attendanceState');
                localStorage.removeItem('punchTime');
                localStorage.removeItem('secondsElapsed');
                setUIState('out');
                updateTimerDisplay();
            }
        }

        function toggleBreak() {
            if (!isPunchedIn) return;

            if (!isOnBreak) {
                // START BREAK
                isOnBreak = true;
                stopTimer(); // Pause production timer
                localStorage.setItem('attendanceState', 'onBreak');
                setUIState('break', localStorage.getItem('punchTime'));
            } else {
                // END BREAK
                isOnBreak = false;
                startTimer(); // Resume production timer
                localStorage.setItem('attendanceState', 'punchedIn');
                setUIState('in', localStorage.getItem('punchTime'));
            }
        }

        function startTimer() {
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                secondsElapsed++;
                localStorage.setItem('secondsElapsed', secondsElapsed);
                updateTimerDisplay();
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
        }

        function updateTimerDisplay() {
            // Convert seconds to decimal hours (like 3.45 hrs) or HH:MM
            // The image shows "3.45 hrs", which usually means 3 hours and 45% of an hour OR 3h 45m.
            // Let's do Standard HH.mm format for clarity or decimal.
            // Decimal: (seconds / 3600).toFixed(2)
            
            const hours = Math.floor(secondsElapsed / 3600);
            const minutes = Math.floor((secondsElapsed % 3600) / 60);
            
            // Format: 3.45 (meaning 3 hours 45 mins loosely, or actually decimal)
            // Let's stick to the image style:
            let displayVal = `${hours}.${String(minutes).padStart(2, '0')}`;
            document.getElementById('productionTimer').textContent = displayVal;
        }

        function setUIState(state, time = '') {
            const mainBtn = document.getElementById('mainPunchBtn');
            const breakBtn = document.getElementById('breakBtn');
            const statusTxt = document.getElementById('statusDisplay');

            if (state === 'in') {
                mainBtn.textContent = "Punch Out";
                mainBtn.className = "btn-punch bg-slate-900 hover:bg-slate-800"; // Dark
                breakBtn.classList.remove('hidden');
                breakBtn.innerHTML = '<i data-lucide="coffee" class="w-4 h-4 mr-2"></i> Take a Break';
                
                statusTxt.innerHTML = `<i data-lucide="clock" class="w-5 h-5 text-emerald-500"></i> Punch In at ${time}`;
            } else if (state === 'break') {
                mainBtn.textContent = "Punch Out"; 
                breakBtn.classList.remove('hidden');
                breakBtn.innerHTML = '<i data-lucide="play" class="w-4 h-4 mr-2"></i> Resume Work';
                
                statusTxt.innerHTML = `<i data-lucide="coffee" class="w-5 h-5 text-orange-500"></i> On Break`;
            } else {
                // Out
                mainBtn.textContent = "Punch In";
                mainBtn.className = "btn-punch bg-emerald-600 hover:bg-emerald-700"; // Green for Start
                breakBtn.classList.add('hidden');
                
                statusTxt.innerHTML = `<i data-lucide="fingerprint" class="w-5 h-5 text-gray-400"></i> Not Punched In`;
            }
            lucide.createIcons();
        }

        /* ==============================
           3. APEXCHARTS CONFIG
           ============================== */
        // Task Chart
        new ApexCharts(document.querySelector("#taskPerformanceChart"), {
            series: [
                { name: 'Assigned', data: [80, 95, 87, 100, 110, 128] },
                { name: 'Completed', data: [75, 85, 82, 90, 105, 112] }
            ],
            chart: { type: 'bar', height: 220, toolbar: { show: false }, stacked: false },
            colors: ['#F97316', '#10B981'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun'], labels: {style: {fontSize: '10px'}} },
            grid: { borderColor: '#f3f4f6', padding: {top: 0, bottom: 0} }
        }).render();

        // Heatmap
        new ApexCharts(document.querySelector("#presenceHeatmap"), {
            series: [
                { name: 'W1', data: [12, 11, 12, 10, 12] },
                { name: 'W2', data: [10, 12, 12, 12, 11] },
                { name: 'W3', data: [12, 12, 10, 12, 12] },
            ],
            chart: { type: 'heatmap', height: 220, toolbar: { show: false } },
            colors: ['#F97316'],
            plotOptions: { heatmap: { radius: 2, enableShades: true, shadeIntensity: 0.5 } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['M', 'T', 'W', 'T', 'F'], labels: {style: {fontSize: '10px'}} }
        }).render();

        // Priority Donut
        new ApexCharts(document.querySelector("#priorityDonutChart"), {
            series: [15, 22, 8],
            labels: ['High', 'Medium', 'Low'],
            chart: { type: 'donut', height: 200 },
            colors: ['#EF4444', '#FBBF24', '#10B981'],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '65%' } } }
        }).render();
    </script>
</body>
</html>