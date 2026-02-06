<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .timeline-track { background: #f1f5f9; height: 24px; border-radius: 12px; overflow: hidden; }
        .progress-circle { position: relative; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
        .progress-circle::before { content: ""; position: absolute; inset: 4px; background: white; border-radius: 50%; z-index: 1; }
        .progress-circle span { position: relative; z-index: 2; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body class="p-6">

    <?php
    // --- DATA SECTION ---
    $employee = [
        "name" => "Stephan Peralt",
        "role" => "Senior Product Designer",
        "department" => "UI/UX Design",
        "phone" => "+1 324 3453 545",
        "email" => "steperde124@example.com",
        "report_office" => "Doglas Martini",
        "joined_date" => "15 Jan 2024",
        "avatar" => "https://i.pravatar.cc/150?u=stephan"
    ];
    $current_date = "15-04-2025";

    $stats = [
        "on_time" => 1254, "late" => 32, "wfh" => 658, "absent" => 14, "sick" => 68, "percentile" => 85
    ];

    $leave_summary = [
        "total" => 16, "taken" => 10, "absent" => 2, "request" => 0, "worked_days" => 240, "lop" => 2
    ];

    $hourly_stats = [
        ["label" => "Total Hours Today", "value" => "8.36", "total" => "9", "trend" => "5% This Week", "up" => true, "icon" => "fa-clock", "bg" => "bg-orange-500"],
        ["label" => "Total Hours Week", "value" => "10", "total" => "40", "trend" => "7% Last Week", "up" => true, "icon" => "fa-hourglass-half", "bg" => "bg-slate-800"],
        ["label" => "Total Hours Month", "value" => "75", "total" => "98", "trend" => "8% Last Month", "up" => false, "icon" => "fa-calendar-check", "bg" => "bg-blue-500"],
        ["label" => "Overtime this Month", "value" => "16", "total" => "28", "trend" => "6% Last Month", "up" => false, "icon" => "fa-business-time", "bg" => "bg-pink-500"]
    ];

    $timeline_stats = [
        ["label" => "Total Working hours", "value" => "12h 36m", "color" => "bg-gray-200"],
        ["label" => "Productive Hours", "value" => "08h 36m", "color" => "bg-green-500"],
        ["label" => "Break hours", "value" => "22m 15s", "color" => "bg-orange-400"],
        ["label" => "Overtime", "value" => "02h 15m", "color" => "bg-blue-500"]
    ];

    $projects = [
        ["title" => "Office Management", "leader" => "Anthony Lewis", "deadline" => "14 Jan 2024", "spent" => "65", "total_hrs" => "120"],
        ["title" => "Office Management", "leader" => "Anthony Lewis", "deadline" => "14 Jan 2024", "spent" => "65", "total_hrs" => "120"]
    ];

    $tasks = [
        ["name" => "Patient appointment booking", "status" => "Onhold", "color" => "text-pink-500 bg-pink-50", "star" => true, "checked" => false],
        ["name" => "Appointment booking with payment", "status" => "Inprogress", "color" => "text-purple-500 bg-purple-50", "star" => false, "checked" => false],
        ["name" => "Patient and Doctor video conferencing", "status" => "Completed", "color" => "text-green-500 bg-green-50", "star" => false, "checked" => false],
        ["name" => "Private chat module", "status" => "Pending", "color" => "text-cyan-700 bg-cyan-50", "star" => false, "checked" => true],
        ["name" => "Go-Live and Post-Implementation Support", "status" => "Inprogress", "color" => "text-purple-500 bg-purple-50", "star" => false, "checked" => false],
    ];

    $skills = [
        ["name" => "Figma", "date" => "15 May 2025", "percent" => 95, "color" => "#f97316"],
        ["name" => "HTML", "date" => "12 May 2025", "percent" => 85, "color" => "#22c55e"],
        ["name" => "CSS", "date" => "12 May 2025", "percent" => 70, "color" => "#a855f7"],
        ["name" => "Wordpress", "date" => "15 May 2025", "percent" => 61, "color" => "#3b82f6"],
        ["name" => "Javascript", "date" => "13 May 2025", "percent" => 58, "color" => "#1e293b"]
    ];

    $team_members = [
        ["name" => "Alexander Jermai", "role" => "UI/UX Designer", "img" => "https://i.pravatar.cc/150?u=1"],
        ["name" => "Doglas Martini", "role" => "Product Designer", "img" => "https://i.pravatar.cc/150?u=2"],
        ["name" => "Daniel Esbella", "role" => "Project Manager", "img" => "https://i.pravatar.cc/150?u=3"],
        ["name" => "Daniel Esbella", "role" => "Team Lead", "img" => "https://i.pravatar.cc/150?u=4"],
        ["name" => "Stephan Peralt", "role" => "Team Lead", "img" => "https://i.pravatar.cc/150?u=5"],
        ["name" => "Andrew Jermia", "role" => "Project Lead", "img" => "https://i.pravatar.cc/150?u=6"]
    ];

    $notifications = [
        ["user" => "Lex Murphy", "time" => "Today at 9:42 AM", "action" => "requested access to UNIX", "file" => "EY_review.pdf", "img" => "https://i.pravatar.cc/150?u=7"],
        ["user" => "Lex Murphy", "time" => "Today at 10:00 AM", "action" => "requested access to UNIX", "img" => "https://i.pravatar.cc/150?u=8"],
        ["user" => "Lex Murphy", "time" => "Today at 10:50 AM", "action" => "requested access to UNIX", "buttons" => true, "img" => "https://i.pravatar.cc/150?u=9"],
        ["user" => "Lex Murphy", "time" => "Today at 12:00 PM", "action" => "requested access to UNIX", "img" => "https://i.pravatar.cc/150?u=10"]
    ];

    $meetings = [
        ["time" => "09:25 AM", "title" => "Marketing Strategy Presentation", "dept" => "Marketing", "color" => "bg-orange-500"],
        ["time" => "09:20 AM", "title" => "Design Review Hospital, doctors Management Project", "dept" => "Review", "color" => "bg-[#074751]"],
        ["time" => "09:18 AM", "title" => "Birthday Celebration of Employee", "dept" => "Celebration", "color" => "bg-yellow-500"],
        ["time" => "09:10 AM", "title" => "Update of Project Flow", "dept" => "Development", "color" => "bg-green-500"]
    ];
    ?>

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
            <nav class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                <i class="fa-solid fa-house text-xs"></i> 
                <span>&rsaquo; Dashboard</span> <span>&rsaquo; Employee Dashboard</span>
            </nav>
        </div>
        <div class="flex gap-2">
            <button class="bg-white border px-4 py-2 rounded shadow-sm text-sm flex items-center gap-2"><i class="fa-solid fa-file-export"></i> Export</button>
            <button class="bg-white border px-4 py-2 rounded shadow-sm text-sm flex items-center gap-2"><i class="fa-regular fa-calendar"></i> <?php echo $current_date; ?></button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
        <div class="lg:col-span-4 bg-white rounded-lg shadow-sm border overflow-hidden relative">
            <div class="bg-[#1e1e1e] p-6 flex items-center gap-4 text-white">
                <img src="<?php echo $employee['avatar']; ?>" class="w-16 h-16 rounded-full border-2 border-white">
                <div>
                    <h2 class="text-xl font-semibold"><?php echo $employee['name']; ?></h2>
                    <p class="text-sm text-gray-400"><?php echo $employee['role']; ?> • <?php echo $employee['department']; ?></p>
                </div>
                <div class="absolute right-0 top-12 bg-[#e67e22] p-2 rounded-l-md cursor-pointer"><i class="fa-solid fa-gear"></i></div>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <div><p class="text-xs text-gray-400">Phone Number</p><p class="font-medium"><?php echo $employee['phone']; ?></p></div>
                <div><p class="text-xs text-gray-400">Email Address</p><p class="font-medium"><?php echo $employee['email']; ?></p></div>
                <div><p class="text-xs text-gray-400">Report Office</p><p class="font-medium"><?php echo $employee['report_office']; ?></p></div>
                <div><p class="text-xs text-gray-400">Joined on</p><p class="font-medium"><?php echo $employee['joined_date']; ?></p></div>
            </div>
        </div>

        <div class="lg:col-span-4 bg-white p-6 rounded-lg border shadow-sm flex flex-col items-center">
            <div class="w-full flex justify-between mb-4"><h2 class="font-bold">Leave Details</h2><span class="text-xs border px-1 rounded">2026</span></div>
            <div class="w-40 h-40"><canvas id="leaveChart"></canvas></div>
            <div class="mt-6 w-full text-sm text-gray-500 flex items-center gap-2 border-t pt-4">
                <input type="checkbox" checked class="accent-orange-500"> Better than 85% of Employees
            </div>
        </div>

        <div class="lg:col-span-4 bg-white p-6 rounded-lg border shadow-sm flex flex-col">
            <div class="flex justify-between mb-6"><h2 class="font-bold">Leave Details</h2><span class="text-xs border px-1 rounded">2026</span></div>
            <div class="grid grid-cols-2 gap-y-6 flex-grow">
                <?php foreach($leave_summary as $k => $v) echo "<div><p class='text-xs text-gray-400'>".ucwords(str_replace('_',' ',$k))."</p><p class='font-bold text-lg'>$v</p></div>"; ?>
            </div>
            <button class="w-full bg-[#0f172a] text-white py-2 rounded mt-6">Apply New Leave</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <?php foreach ($hourly_stats as $card): ?>
        <div class="bg-white p-5 rounded-lg border shadow-sm">
            <div class="<?php echo $card['bg']; ?> w-7 h-7 rounded text-white flex items-center justify-center mb-3"><i class="fa-solid <?php echo $card['icon']; ?> text-[10px]"></i></div>
            <p class="text-xl font-bold"><?php echo $card['value']; ?> <span class="text-gray-400 font-normal text-sm">/ <?php echo $card['total']; ?></span></p>
            <p class="text-gray-400 text-xs mt-1 mb-4 border-b pb-4"><?php echo $card['label']; ?></p>
            <div class="flex items-center gap-2 text-xs font-semibold <?php echo $card['up'] ? 'text-green-500' : 'text-red-500'; ?>">
                <i class="fa-solid <?php echo $card['up'] ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> <?php echo $card['trend']; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <?php foreach ($timeline_stats as $t_stat): ?>
            <div><p class="flex items-center gap-2 text-gray-400 text-xs mb-2"><span class="w-2 h-2 rounded-full <?php echo $t_stat['color']; ?>"></span><?php echo $t_stat['label']; ?></p><p class="text-xl font-bold text-gray-800"><?php echo $t_stat['value']; ?></p></div>
            <?php endforeach; ?>
        </div>
        <div class="timeline-track flex mb-4">
            <div class="h-full bg-green-500 rounded-lg mr-1" style="width: 15%; margin-left: 10%;"></div>
            <div class="h-full bg-orange-400 rounded-lg mr-1" style="width: 4%;"></div>
            <div class="h-full bg-green-500 rounded-lg mr-1" style="width: 25%;"></div>
            <div class="h-full bg-orange-400 rounded-lg mr-1" style="width: 12%;"></div>
            <div class="h-full bg-green-500 rounded-lg mr-1" style="width: 20%;"></div>
            <div class="h-full bg-orange-400 rounded-lg mr-1" style="width: 3%;"></div>
            <div class="h-full bg-blue-500 rounded-lg mr-1" style="width: 4%;"></div>
            <div class="h-full bg-blue-500 rounded-lg" style="width: 3%;"></div>
        </div>
        <div class="flex justify-between text-[10px] text-gray-400 px-1">
            <span>06:00</span><span>08:00</span><span>10:00</span><span>12:00</span><span>02:00</span><span>04:00</span><span>06:00</span><span>08:00</span><span>10:00</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between items-center mb-6"><h2 class="font-bold">Projects</h2><span class="text-xs text-gray-500">Ongoing Projects <i class="fa-solid fa-chevron-down ml-1"></i></span></div>
            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($projects as $proj): ?>
                <div class="border rounded-lg p-4">
                    <div class="flex justify-between mb-4"><h3 class="font-bold text-sm"><?php echo $proj['title']; ?></h3><i class="fa-solid fa-ellipsis-vertical text-gray-300"></i></div>
                    <div class="flex items-center gap-2 mb-4"><img src="https://i.pravatar.cc/30?u=1" class="w-8 h-8 rounded-full"><p class="text-xs font-medium"><?php echo $proj['leader']; ?></p></div>
                    <div class="bg-gray-50 p-3 rounded-md flex justify-between items-center"><span class="text-xs text-gray-500">Time Spent</span><span class="text-xs font-bold"><?php echo $proj['spent']; ?>/<?php echo $proj['total_hrs']; ?> Hrs</span></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between items-center mb-6"><h2 class="font-bold">Tasks</h2><span class="text-xs text-gray-500">All Projects <i class="fa-solid fa-chevron-down ml-1"></i></span></div>
            <div class="space-y-3">
                <?php foreach ($tasks as $task): ?>
                <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="accent-orange-500" <?php echo $task['checked'] ? 'checked' : ''; ?>>
                        <span class="text-xs <?php echo $task['checked'] ? 'line-through text-gray-400' : 'text-gray-700 font-medium'; ?>"><?php echo $task['name']; ?></span>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $task['color']; ?>"><?php echo $task['status']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
        <div class="lg:col-span-7 bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between items-center mb-8"><h2 class="text-lg font-bold text-gray-800">Performance</h2><button class="border rounded px-3 py-1 text-xs text-gray-500"><i class="fa-regular fa-calendar mr-1"></i>2026</button></div>
            <div class="mb-6 flex items-baseline gap-3"><span class="text-3xl font-bold text-gray-800">98%</span><span class="text-xs font-bold text-green-500 bg-green-50 px-2 py-1 rounded">12% <span class="font-normal text-gray-400 ml-1">vs last years</span></span></div>
            <div class="h-64"><canvas id="performanceChart"></canvas></div>
        </div>

        <div class="lg:col-span-5 bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between items-center mb-6"><h2 class="font-bold">My Skills</h2><span class="text-xs border px-1 rounded">2026</span></div>
            <div class="space-y-4">
                <?php foreach ($skills as $skill): ?>
                <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-1 h-8 rounded-full" style="background: <?php echo $skill['color']; ?>;"></div>
                        <div><p class="text-xs font-bold"><?php echo $skill['name']; ?></p><p class="text-[10px] text-gray-400">Updated: <?php echo $skill['date']; ?></p></div>
                    </div>
                    <div class="progress-circle" style="background: conic-gradient(<?php echo $skill['color']; ?> <?php echo $skill['percent']; ?>%, #f1f5f9 0);"><span><?php echo $skill['percent']; ?>%</span></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-[#1e293b] p-6 rounded-lg text-center relative overflow-hidden">
            <h3 class="text-white font-bold mb-4">Team Birthday</h3>
            <div class="relative z-10 flex flex-col items-center">
                <img src="https://i.pravatar.cc/150?u=andrew" class="w-16 h-16 rounded-full border-2 border-pink-400 mb-3">
                <p class="text-white font-bold text-sm">Andrew Jermia</p><p class="text-slate-500 text-[10px] mb-4">IOS Developer</p>
                <button class="bg-[#f97316] text-white px-6 py-2 rounded text-xs font-bold hover:bg-orange-600 transition">Send Wishes</button>
            </div>
        </div>
        <div class="flex flex-col gap-6">
            <div class="bg-[#0e4b5e] p-5 rounded-lg flex items-center justify-between text-white">
                <div><h3 class="font-bold text-sm">Leave Policy</h3><p class="text-cyan-200 text-[10px]">Last Updated: Today</p></div>
                <button class="bg-white text-slate-800 px-3 py-1 rounded text-[10px] font-bold">View All</button>
            </div>
            <div class="bg-[#ffb400] p-5 rounded-lg flex items-center justify-between">
                <div><h3 class="text-slate-900 font-bold text-sm">Next Holiday</h3><p class="text-slate-800 text-[10px]">Diwali, 15 Sep 2025</p></div>
                <button class="bg-white text-slate-800 px-3 py-1 rounded text-[10px] font-bold">View All</button>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between items-center mb-6"><h2 class="font-bold">Team Members</h2><button class="text-xs border px-3 py-1 rounded text-gray-500">View All</button></div>
            <div class="space-y-4">
                <?php foreach (array_slice($team_members, 0, 3) as $m): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3"><img src="<?php echo $m['img']; ?>" class="w-8 h-8 rounded-full"><p class="text-xs font-bold"><?php echo $m['name']; ?></p></div>
                    <div class="flex gap-2"><i class="fa-solid fa-phone text-xs text-gray-400"></i><i class="fa-solid fa-envelope text-xs text-gray-400"></i></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between mb-6"><h2 class="font-bold">Notifications</h2><button class="text-xs border px-3 py-1 rounded text-gray-500">View All</button></div>
            <div class="space-y-6">
                <?php foreach (array_slice($notifications, 0, 2) as $note): ?>
                <div class="flex gap-4">
                    <img src="<?php echo $note['img']; ?>" class="w-10 h-10 rounded-full object-cover">
                    <div class="flex-grow">
                        <p class="text-sm text-gray-800"><span class="font-bold"><?php echo $note['user']; ?></span> <?php echo $note['action']; ?></p>
                        <p class="text-[11px] text-gray-400 mt-0.5"><?php echo $note['time']; ?></p>
                        <?php if (isset($note['file'])): ?><div class="mt-3 flex items-center gap-2 border rounded-md p-2 w-fit bg-gray-50"><i class="fa-solid fa-file-pdf text-red-500 text-sm"></i><span class="text-xs text-gray-700"><?php echo $note['file']; ?></span></div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border shadow-sm">
            <div class="flex justify-between mb-6"><h2 class="font-bold text-gray-800">Meetings Schedule</h2><button class="border rounded px-3 py-1 text-xs text-gray-500">Today</button></div>
            <div class="space-y-5 relative">
                <div class="absolute left-[3.25rem] top-2 bottom-8 w-px bg-gray-100"></div>
                
                <?php foreach ($meetings as $meet): ?>
                <div class="flex items-start gap-4">
                    <div class="text-[10px] text-gray-400 font-bold w-12 pt-1"><?php echo $meet['time']; ?></div>
                    <div class="relative z-10 w-2 h-2 rounded-full <?php echo $meet['color']; ?> mt-1.5 -ml-1"></div>
                    <div class="flex-grow bg-gray-50 p-3 rounded-lg hover:shadow-md transition cursor-pointer">
                        <p class="text-sm font-bold text-gray-700"><?php echo $meet['title']; ?></p>
                        <p class="text-[10px] text-gray-400"><?php echo $meet['dept']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Leave Chart
        new Chart(document.getElementById('leaveChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [1254, 32, 658, 14, 68],
                    backgroundColor: ['#074751', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                    borderWidth: 0, cutout: '75%'
                }]
            },
            options: { plugins: { legend: { display: false } }, maintainAspectRatio: false }
        });

        // Performance Chart
        new Chart(document.getElementById('performanceChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    data: [20000, 20000, 35000, 35000, 40000, 60000, 60000],
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.2)',
                    fill: true, tension: 0.1, pointRadius: 0
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: false, ticks: { callback: (v) => v / 1000 + 'K' }, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>