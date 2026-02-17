<?php 
include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Executive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f3f6f9; }
        canvas { max-width: 100%; }

        /* Punch Card Specific Styles */
        .profile-ring-container {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#134e4a 0% 70%, #3b82f6 70% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
        }
        .profile-ring-inner {
            width: 110px;
            height: 110px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        .production-badge {
            background-color: #134e4a;
            color: white;
            display: inline-block;
            padding: 6px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(19, 78, 74, 0.2);
        }
        .btn-punch-out { background-color: #111827; color: white; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; transition: 0.3s; margin-bottom: 8px; }
        .btn-punch-out:hover { background-color: #1f2937; }
        .btn-break { background-color: white; color: #134e4a; border: 1px solid #134e4a; width: 100%; padding: 10px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        .btn-break:hover { background-color: #f0fdfa; }
        .btn-punch-in { background-color: #134e4a; color: white; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; transition: 0.3s; }
        .btn-punch-in:hover { background-color: #0f3d3a; }

        /* FIXED OVERLAP HERE */
        @media (min-width: 1024px) {
            main { 
                margin-left: 100px; /* Adjust this value to match your exact sidebar width */
                width: calc(100% - 100px); 
            }
        }
    </style>
</head>
<body class="min-h-screen">

    <main>
        <div class="p-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-800">HR Executive</h1>
                <p class="text-sm text-gray-400 mt-1">Intelligence / <span class="text-slate-600">Overview</span></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <?php
                $top_stats = [
                    ['label' => 'Open Positions', 'val' => '47', 'icon' => 'briefcase', 'color' => 'teal'],
                    ['label' => 'Total Candidates', 'val' => '2,384', 'icon' => 'users', 'color' => 'teal'],
                    ['label' => 'Interviews Today', 'val' => '12', 'icon' => 'calendar', 'color' => 'slate'],
                    ['label' => 'Offers Released', 'val' => '28', 'icon' => 'copy', 'color' => 'blue'],
                ];
                foreach($top_stats as $s): ?>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center text-center">
                    <div class="bg-<?= $s['color'] ?>-50 p-3 rounded-xl mb-3 text-<?= $s['color'] ?>-900">
                        <i data-lucide="<?= $s['icon'] ?>" class="w-6 h-6"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800"><?= $s['val'] ?></h2>
                    <p class="text-gray-400 text-xs font-medium uppercase tracking-wider mt-1"><?= $s['label'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 items-stretch">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm text-center flex flex-col justify-center">
                    <div class="mb-4">
                        <p class="text-gray-500 font-medium text-xs">Good Morning, Team Leader</p>
                        <h2 class="text-2xl font-bold text-gray-800 mt-1" id="liveClock">00:00 AM</h2>
                        <p class="text-[10px] text-gray-400 font-medium mt-1" id="liveDate">-- --- ----</p>
                    </div>

                    <div class="profile-ring-container">
                        <div class="profile-ring-inner">
                            <img src="https://i.pravatar.cc/300?img=11" alt="Profile" class="profile-img">
                        </div>
                    </div>

                    <div class="production-badge">
                        Production : <span id="productionTimer">0.00</span> hrs
                    </div>

                    <div class="flex items-center justify-center gap-2 text-gray-600 mb-6" id="statusDisplay">
                        <i data-lucide="clock" class="w-4 h-4 text-emerald-500"></i>
                        <span class="font-medium text-xs" id="punchTimeText">Punched In at 04:12 pm</span>
                    </div>

                    <div id="actionButtons"></div>
                </div>

                <div class="lg:col-span-2 flex flex-col">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-xl text-slate-800">Stage Performance</h3>
                            <button class="text-xs text-gray-400 flex items-center border rounded-lg px-2 py-1">
                                <i data-lucide="sliders-horizontal" class="w-3 h-3 mr-1"></i> Last 30 Days
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 h-[240px]">
                            <?php
                            $stages = [
                                ['label' => 'Applied', 'num' => '1,848', 'rate' => '36.3%', 'icon' => 'layout-template', 'iconBg' => 'bg-orange-500', 'barColor' => 'bg-orange-500'],
                                ['label' => 'Shortlisted', 'num' => '2,384', 'rate' => '37.4%', 'icon' => 'hourglass', 'iconBg' => 'bg-teal-600', 'barColor' => 'bg-teal-600'],
                                ['label' => 'Interviewed', 'num' => '892', 'rate' => '36.3%', 'icon' => 'calendar-days', 'iconBg' => 'bg-slate-800', 'barColor' => 'bg-slate-800'],
                                ['label' => 'Offered', 'num' => '324', 'rate' => '26.5%', 'icon' => 'file-text', 'iconBg' => 'bg-blue-500', 'barColor' => 'bg-blue-500'],
                                ['label' => 'Hired', 'num' => '64', 'rate' => '41.2%', 'icon' => 'user-check', 'iconBg' => 'bg-green-500', 'barColor' => 'bg-green-500']
                            ];
                            foreach($stages as $stage): ?>
                            <div class="bg-white border border-gray-100 p-4 rounded-xl flex flex-col justify-between shadow-sm">
                                <div class="flex justify-between items-start">
                                    <p class="text-[12px] font-bold text-gray-500 truncate mr-1 uppercase tracking-tight"><?= $stage['label'] ?></p>
                                    <div class="<?= $stage['iconBg'] ?> p-2 rounded-lg text-white shrink-0">
                                        <i data-lucide="<?= $stage['icon'] ?>" class="w-4 h-4"></i>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h4 class="text-2xl font-extrabold text-slate-800 leading-tight"><?= $stage['num'] ?></h4>
                                    <div class="flex justify-between items-center mb-2 mt-2">
                                        <span class="text-[10px] text-gray-400 font-medium">Progress</span>
                                        <span class="text-[11px] text-slate-600 font-bold"><?= $stage['rate'] ?></span>
                                    </div>
                                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="<?= $stage['barColor'] ?> h-full" style="width: <?= $stage['rate'] ?>"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-8 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 flex justify-between items-center border-b border-gray-50">
                        <h3 class="font-bold text-slate-800">Active Job Openings</h3>
                        <button class="text-xs font-semibold text-teal-900 hover:bg-teal-50 px-3 py-1 rounded-lg">View All</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-400 font-medium">
                                <tr>
                                    <th class="px-6 py-4">Job ID</th>
                                    <th class="px-6 py-4">Job Title</th>
                                    <th class="px-6 py-4 text-center">Location</th>
                                    <th class="px-6 py-4 text-center">Applicants</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php
                                $jobs = [
                                    ['id' => 'JOB-001', 'title' => 'Frontend Developer', 'priority' => 'High', 'loc' => 'Remote', 'count' => '1452'],
                                    ['id' => 'JOB-002', 'title' => 'Product Manager', 'priority' => 'High', 'loc' => 'Office', 'count' => '1342'],
                                    ['id' => 'JOB-003', 'title' => 'UX Designer', 'priority' => 'High', 'loc' => 'Hybrid', 'count' => '1287']
                                ];
                                foreach($jobs as $j): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-medium text-slate-500"><?= $j['id'] ?></td>
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-slate-800"><?= $j['title'] ?></p>
                                        <span class="text-[10px] bg-red-50 text-red-500 px-2 py-0.5 rounded-full font-bold uppercase tracking-tighter"><?= $j['priority'] ?> Priority</span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-500"><?= $j['loc'] ?></td>
                                    <td class="px-6 py-4 text-center font-bold text-slate-800"><?= $j['count'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800">Recruitment Overview</h3>
                            <button class="text-xs text-gray-400 flex items-center border rounded-lg px-2 py-1"><i data-lucide="download" class="w-3 h-3 mr-1"></i> Monthly</button>
                        </div>
                        <div class="flex justify-around mb-4">
                            <div class="text-center">
                                <span class="text-xs text-gray-400 block">Offer Acceptance</span>
                                <span class="text-lg font-bold">74.4%</span>
                            </div>
                            <div class="text-center border-l border-gray-100 pl-8">
                                <span class="text-xs text-gray-400 block">Overall Hire Rate</span>
                                <span class="text-lg font-bold">2.7%</span>
                            </div>
                        </div>
                        <div class="relative flex justify-center">
                            <canvas id="gaugeChart" height="180"></canvas>
                            <div class="absolute bottom-4 text-center">
                                <p class="text-2xl font-bold text-slate-800">2,384</p>
                                <p class="text-xs text-gray-400">Total Applications</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800">Upcoming Schedules</h3>
                            <span class="text-xs text-teal-900 font-bold cursor-pointer">Today</span>
                        </div>
                        <div class="space-y-6">
                            <?php
                            $schedules = [
                                ['role' => 'Product Designer', 'time' => '09:00 AM - 10:30 AM', 'date' => 'Mar 02'],
                                ['role' => 'Marketing Manager', 'time' => '01:00 PM - 02:00 PM', 'date' => 'Apr 22'],
                                ['role' => 'Sr. Data Science', 'time' => '11:00 AM - 12:30 PM', 'date' => 'May 11']
                            ];
                            foreach($schedules as $index => $sch): ?>
                            <div class="flex items-center gap-4 group">
                                <div class="w-12 h-12 bg-gray-50 rounded-xl flex flex-col items-center justify-center border border-gray-100 group-hover:bg-teal-50 group-hover:border-teal-100 transition-colors">
                                    <span class="text-[10px] text-gray-400 font-bold leading-none"><?= explode(' ', $sch['date'])[0] ?></span>
                                    <span class="text-lg font-bold text-slate-800 leading-none"><?= explode(' ', $sch['date'])[1] ?></span>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-bold text-slate-800"><?= $sch['role'] ?></h4>
                                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> <?= $sch['time'] ?></p>
                                </div>
                                <img src="https://i.pravatar.cc/150?u=<?= $index ?>" class="w-10 h-10 rounded-full grayscale hover:grayscale-0 transition-all cursor-pointer">
                            </div>
                            <?php endforeach; ?>
                            <button class="w-full py-3 bg-teal-900 text-white rounded-xl font-bold text-sm shadow-lg shadow-teal-100 hover:bg-teal-950 transition-all mt-4">View All Schedules</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // GAUGE CHART
        const ctx = document.getElementById('gaugeChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [65, 35],
                    backgroundColor: ['#134e4a', '#f1f5f9'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270,
                    borderRadius: 10,
                    cutout: '80%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });

        // --- PUNCH LOGIC ---
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            hours = String(hours).padStart(2, '0');
            document.getElementById('liveClock').textContent = `${hours}:${minutes} ${ampm}`;
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            document.getElementById('liveDate').textContent = now.toLocaleDateString('en-GB', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        let timerInterval;
        let secondsElapsed = 0;
        let currentState = 'in'; 
        let punchInTimeStr = "04:12 pm";

        function updateUI() {
            const container = document.getElementById('actionButtons');
            const statusDisplay = document.getElementById('statusDisplay');
            const badge = document.querySelector('.production-badge');

            if (currentState === 'out') {
                container.innerHTML = `<button onclick="handlePunch('in')" class="btn-punch-in">Punch In</button>`;
                statusDisplay.innerHTML = `<i data-lucide="fingerprint" class="w-4 h-4 text-gray-400"></i> Not Punched In`;
                badge.style.opacity = '0.5';
            } else if (currentState === 'in') {
                container.innerHTML = `
                    <button onclick="handlePunch('out')" class="btn-punch-out">Punch Out</button>
                    <button onclick="toggleBreak()" class="btn-break"><i data-lucide="coffee" class="w-4 h-4"></i> Take a Break</button>`;
                statusDisplay.innerHTML = `<i data-lucide="clock" class="w-4 h-4 text-emerald-500"></i> Punch In at ${punchInTimeStr}`;
                badge.style.opacity = '1';
            } else if (currentState === 'break') {
                container.innerHTML = `
                    <button onclick="toggleBreak()" class="btn-break" style="background:#fef3c7; color:#d97706; border-color:#d97706;">
                        <i data-lucide="play" class="w-4 h-4"></i> Resume Work
                    </button>`;
                statusDisplay.innerHTML = `<i data-lucide="coffee" class="w-4 h-4 text-orange-500"></i> On Break`;
            }
            lucide.createIcons();
        }

        function handlePunch(action) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }).toLowerCase();
            if (action === 'in') {
                currentState = 'in';
                punchInTimeStr = timeString;
                startTimer();
            } else {
                currentState = 'out';
                stopTimer();
                secondsElapsed = 0;
                updateTimerDisplay();
            }
            updateUI();
        }

        function toggleBreak() {
            if (currentState === 'in') {
                currentState = 'break';
                stopTimer();
            } else {
                currentState = 'in';
                startTimer();
            }
            updateUI();
        }

        function startTimer() {
            stopTimer();
            timerInterval = setInterval(() => {
                secondsElapsed++;
                updateTimerDisplay();
            }, 1000);
        }

        function stopTimer() { clearInterval(timerInterval); }

        function updateTimerDisplay() {
            const hours = (secondsElapsed / 3600).toFixed(2);
            document.getElementById('productionTimer').textContent = hours;
        }

        updateUI();
        startTimer();
    </script>
</body>
</html>