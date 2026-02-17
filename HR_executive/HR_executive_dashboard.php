<?php
ob_start(); // Fixes "Headers already sent" errors
// hr_dashboard.php

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FIX: Changed _DIR_ to __DIR__ (Double Underscores)
$sidebarPath = __DIR__ . '/../sidebars.php'; 


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// --- STRICT ROLE CHECK ---
$allowed_roles = ['Manager', 'System Admin', 'HR'];
$user_role = $_SESSION['role'] ?? ''; 

if (!in_array($user_role, $allowed_roles)) {
    header("Location: ../employee_dashboard.php");
    exit();
}

// 2. DYNAMIC SESSION DATA
$logged_in_user = $_SESSION['username'] ?? 'User';
$logged_in_role = $_SESSION['role'] ?? 'HR';
$user_id = $_SESSION['user_id'] ?? '1';
$display_name = ucfirst(explode('@', $logged_in_user)[0]); 

// --- DATA SECTION ---
$employee = [
    "name" => $display_name,
    "role" => $logged_in_role,
    "department" => "UI/UX Design",
    "phone" => "+91 98765 43210",
    "email" => $logged_in_user,
    "report_office" => "Doglas Martini",
    "joined_date" => "15 Jan 2024",
    "avatar" => "https://i.pravatar.cc/150?u=" . $user_id
];
$current_date = date("d-m-Y");

$stats = [
    "on_time" => 1254, "late" => 32, "wfh" => 658, "absent" => 14, "sick" => 68, "percentile" => 85
];
$leave_summary = [
    "total" => 16, "taken" => 10, "absent" => 2, "request" => 0, "worked_days" => 240, "lop" => 2
];
$hourly_stats = [
    ["label" => "Total Hours Today", "value" => "8.36", "total" => "9", "trend" => "5% This Week", "up" => true, "icon" => "fa-clock", "bg" => "from-orange-400 to-orange-600"],
    ["label" => "Total Hours Week", "value" => "10", "total" => "40", "trend" => "7% Last Week", "up" => true, "icon" => "fa-hourglass-half", "bg" => "from-teal-400 to-teal-600"],
    ["label" => "Total Hours Month", "value" => "75", "total" => "98", "trend" => "8% Last Month", "up" => false, "icon" => "fa-calendar-check", "bg" => "from-blue-400 to-blue-600"],
    ["label" => "Overtime this Month", "value" => "16", "total" => "28", "trend" => "6% Last Month", "up" => false, "icon" => "fa-business-time", "bg" => "from-pink-400 to-pink-600"]
];
$timeline_stats = [
    ["label" => "Total Working hours", "value" => "12h 36m", "color" => "bg-slate-300"],
    ["label" => "Productive Hours", "value" => "08h 36m", "color" => "bg-green-500"],
    ["label" => "Break hours", "value" => "22m 15s", "color" => "bg-orange-400"],
    ["label" => "Overtime", "value" => "02h 15m", "color" => "bg-blue-500"]
];
$projects = [
    ["title" => "Office Management", "leader" => "Anthony Lewis", "deadline" => "14 Jan 2024", "spent" => "65", "total_hrs" => "120"],
    ["title" => "HRMS Dashboard UI", "leader" => "Aparna", "deadline" => "20 Feb 2026", "spent" => "90", "total_hrs" => "150"]
];
$tasks = [
    ["name" => "Patient appointment booking", "status" => "Onhold", "color" => "text-pink-600 bg-pink-100", "checked" => false],
    ["name" => "Appointment booking with payment", "status" => "Inprogress", "color" => "text-purple-600 bg-purple-100", "checked" => false],
    ["name" => "Patient and Doctor video conferencing", "status" => "Completed", "color" => "text-green-600 bg-green-100", "checked" => false],
    ["name" => "Private chat module", "status" => "Pending", "color" => "text-slate-600 bg-slate-100", "checked" => true],
    ["name" => "Go-Live and Post-Implementation Support", "status" => "Inprogress", "color" => "text-purple-600 bg-purple-100", "checked" => false],
];
$skills = [
    ["name" => "Figma", "date" => "15 May 2025", "percent" => 95, "color" => "#f97316"],
    ["name" => "HTML", "date" => "12 May 2025", "percent" => 85, "color" => "#22c55e"],
    ["name" => "CSS", "date" => "12 May 2025", "percent" => 70, "color" => "#a855f7"],
    ["name" => "Wordpress", "date" => "15 May 2025", "percent" => 61, "color" => "#3b82f6"],
    ["name" => "Javascript", "date" => "13 May 2025", "percent" => 58, "color" => "#1e293b"]
];
$notifications = [
    ["user" => "Lex Murphy", "time" => "Today at 9:42 AM", "action" => "requested access to UNIX", "file" => "EY_review.pdf", "img" => "https://i.pravatar.cc/150?u=7"],
    ["user" => "John Doe", "time" => "Today at 10:00 AM", "action" => "commented on your task", "img" => "https://i.pravatar.cc/150?u=8"],
    ["user" => "Admin", "time" => "Today at 10:50 AM", "action" => "requested leave approval", "img" => "https://i.pravatar.cc/150?u=9", "action_btn" => true]
];
$meetings = [
    ["time" => "09:25 AM", "title" => "Marketing Strategy Presentation", "dept" => "Marketing", "color" => "bg-orange-500"],
    ["time" => "11:20 AM", "title" => "Design Review Management Project", "dept" => "Review", "color" => "bg-teal-600"],
    ["time" => "02:18 PM", "title" => "Birthday Celebration of Employee", "dept" => "Celebration", "color" => "bg-yellow-500"],
    ["time" => "04:10 PM", "title" => "Update of Project Flow", "dept" => "Development", "color" => "bg-green-500"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $logged_in_role; ?> Dashboard | Workack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .hover-card { transition: all 0.3s ease; }
        .hover-card:hover { box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .timeline-bar { height: 24px; border-radius: 50px; background: #f1f5f9; position: relative; overflow: hidden; display: flex; gap: 3px; padding: 0 3px; align-items: center; }
        .segment { height: 16px; border-radius: 8px; transition: all 0.3s; }
        .progress-circle { position: relative; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
        .progress-circle::before { content: ""; position: absolute; inset: 5px; background: white; border-radius: 50%; z-index: 1; }
        .progress-circle span { position: relative; z-index: 2; font-size: 11px; font-weight: bold; }
        #mainContent { margin-left: 95px; transition: margin-left 0.3s ease; padding: 24px; width: calc(100% - 95px); }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .attendance-btn { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .checked-in { background: #ef4444 !important; }
        .checked-out { background: #0d9488 !important; }
    </style>
</head>
<body class="bg-slate-100">

<?php include('../header.php'); ?>
    
<?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent">

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight"><?php echo $logged_in_role; ?> Dashboard</h1>
                <nav class="flex text-sm text-gray-500 mt-2">
                    <ol class="inline-flex items-center space-x-2">
                        <li class="flex items-center"><i class="fa-solid fa-house text-xs text-teal-600"></i></li>
                        <li class="flex items-center"><i class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i> Dashboard</li>
                        <li class="flex items-center"><i class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i> <span class="text-teal-600 font-semibold"><?php echo $logged_in_role; ?> Overview</span></li>
                    </ol>
                </nav>
            </div>
            <div class="flex gap-3 flex-wrap">
                <button class="bg-white border border-gray-200 px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm hover:shadow-md transition">
                    <i class="fa-solid fa-file-export text-gray-400"></i> Export <i class="fa-solid fa-chevron-down text-[10px] text-gray-400"></i>
                </button>
                <button class="bg-white border border-gray-200 px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm hover:shadow-md transition">
                    <i class="fa-regular fa-calendar text-gray-400"></i> <?php echo $current_date; ?>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6 mb-6">
            
            <div class="col-span-12 lg:col-span-3">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover-card">
                    <div class="bg-gradient-to-r from-teal-600 to-teal-700 p-8 pb-10 relative">
                        <div class="flex flex-col items-center text-center">
                            <div class="relative">
                                <img src="<?php echo $employee['avatar']; ?>" class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover">
                                <div id="statusDot" class="absolute bottom-1 right-1 w-5 h-5 bg-gray-400 rounded-full border-2 border-white"></div>
                            </div>
                            <h2 class="text-white font-bold text-xl mt-4"><?php echo $employee['name']; ?></h2>
                            <p class="text-teal-100 text-sm mt-1"><?php echo $employee['role']; ?></p>
                        </div>
                        <button class="absolute top-6 right-6 bg-white/20 p-2 rounded-lg hover:bg-white/30 transition"><i class="fa-solid fa-gear text-white"></i></button>
                    </div>
                    <div class="p-6 space-y-3">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Attendance</span>
                                <span id="timerText" class="text-xs font-mono font-bold text-slate-600">00:00:00</span>
                            </div>
                            <button id="attendanceBtn" onclick="toggleAttendance()" class="attendance-btn w-full py-3 rounded-xl text-white font-bold text-sm shadow-lg flex items-center justify-center gap-2 checked-out">
                                <i id="btnIcon" class="fa-solid fa-right-to-bracket"></i>
                                <span id="btnText">Check In</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                            <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-phone text-teal-600"></i></div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Phone</p>
                                <p class="font-semibold text-sm text-slate-800"><?php echo $employee['phone']; ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                            <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-envelope text-teal-600"></i></div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Email</p>
                                <p class="font-semibold text-sm text-slate-800 truncate"><?php echo $employee['email']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-9 grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-7">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover-card h-[80%] flex flex-col">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                            <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1">
                                <i class="fa-regular fa-calendar"></i> 2026
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-6 flex-grow">
                            <div class="space-y-4 flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-teal-600"></div>
                                    <span class="text-teal-600 font-bold w-12"><?php echo $stats['on_time']; ?></span>
                                    <span class="text-gray-600 text-sm">On Time</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                    <span class="text-green-500 font-bold w-12"><?php echo $stats['late']; ?></span>
                                    <span class="text-gray-600 text-sm">Late</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                    <span class="text-orange-500 font-bold w-12"><?php echo $stats['wfh']; ?></span>
                                    <span class="text-gray-600 text-sm">WFH</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                    <span class="text-red-500 font-bold w-12"><?php echo $stats['absent']; ?></span>
                                    <span class="text-gray-600 text-sm">Absent</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                    <span class="text-yellow-500 font-bold w-12"><?php echo $stats['sick']; ?></span>
                                    <span class="text-gray-600 text-sm">Sick Leave</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 w-44 h-44 relative">
                                <canvas id="leaveChart"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-2xl font-black text-slate-800"><?php echo $stats['percentile']; ?>%</span>
                                    <span class="text-[11px] text-gray-400 font-bold text-center uppercase tracking-tighter">Performance</span>
                                </div>
                            </div>
                        </div>
                        <div class="pt-4 mt-6 border-t border-gray-100 text-xs text-gray-500 flex items-center gap-2">
                            <i class="fa-solid fa-check-circle text-teal-600"></i> 
                            Better than <span class="text-slate-800 font-bold"><?php echo $stats['percentile']; ?>%</span> of staff
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover-card h-[80%] flex flex-col">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1">
                                <i class="fa-regular fa-calendar"></i> 2026
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-4 flex-grow content-center">
                            <div class="text-center p-4 bg-teal-50 rounded-xl border border-teal-100">
                                <p class="text-gray-500 text-[10px] font-bold mb-1 uppercase tracking-wider">Total</p>
                                <p class="font-black text-2xl text-teal-700"><?php echo $leave_summary['total']; ?></p>
                            </div>
                            <div class="text-center p-4 bg-blue-50 rounded-xl border border-blue-100">
                                <p class="text-gray-500 text-[10px] font-bold mb-1 uppercase tracking-wider">Taken</p>
                                <p class="font-black text-2xl text-blue-700"><?php echo $leave_summary['taken']; ?></p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-xl border border-green-100">
                                <p class="text-gray-500 text-[10px] font-bold mb-1 uppercase tracking-wider">Left</p>
                                <p class="font-black text-2xl text-green-700"><?php echo $leave_summary['total'] - $leave_summary['taken']; ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-6">
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-gray-400 text-[10px] font-bold uppercase">Absent</p>
                                <p class="font-bold text-lg text-slate-800"><?php echo $leave_summary['absent']; ?></p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-gray-400 text-[10px] font-bold uppercase">Request</p>
                                <p class="font-bold text-lg text-slate-800"><?php echo $leave_summary['request']; ?></p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-gray-400 text-[10px] font-bold uppercase">LOP</p>
                                <p class="font-bold text-lg text-red-500"><?php echo $leave_summary['lop']; ?></p>
                            </div>
                        </div>
                        <button class="w-full bg-teal-600 text-white py-4 rounded-xl font-bold text-sm uppercase tracking-widest shadow-lg shadow-teal-100 hover:bg-teal-700 transition-all">
                            <i class="fa-solid fa-plus mr-2"></i> Apply New Leave
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <?php foreach ($hourly_stats as $card): ?>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover-card">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?php echo $card['bg']; ?> flex items-center justify-center text-white shadow-lg">
                        <i class="fa-solid <?php echo $card['icon']; ?> text-lg"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-slate-800"><?php echo $card['value']; ?> <span class="text-gray-300 text-lg">/ <?php echo $card['total']; ?></span></p>
                    </div>
                </div>
                <p class="text-gray-500 text-xs font-semibold uppercase tracking-wider"><?php echo $card['label']; ?></p>
                <div class="text-xs font-bold mt-2 flex items-center gap-1 <?php echo $card['up'] ? 'text-green-500' : 'text-red-500'; ?>">
                    <i class="fa-solid <?php echo $card['up'] ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> <?php echo $card['trend']; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-7 shadow-sm hover-card mb-6">
            <div class="flex flex-col lg:flex-row justify-between mb-6 gap-4">
                <h3 class="font-bold text-slate-800 text-lg">Work Timeline</h3>
                <div class="flex flex-wrap gap-6">
                    <?php foreach ($timeline_stats as $t_stat): ?>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full <?php echo $t_stat['color']; ?>"></div>
                        <span class="text-xs text-gray-500"><?php echo $t_stat['label']; ?></span>
                        <span class="text-sm font-bold text-slate-800"><?php echo $t_stat['value']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="timeline-bar mb-3">
                <div class="segment bg-green-500" style="width: 15%; margin-left: 1%;"></div>
                <div class="segment bg-orange-400" style="width: 4%;"></div>
                <div class="segment bg-green-500" style="width: 25%;"></div>
                <div class="segment bg-orange-400" style="width: 3%;"></div>
                <div class="segment bg-green-500" style="width: 20%;"></div>
                <div class="segment bg-orange-400" style="width: 3%;"></div>
                <div class="segment bg-blue-500" style="width: 8%;"></div>
                <div class="segment bg-slate-200" style="width: 19%;"></div>
            </div>
            <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1">
                <span>06:00</span><span>08:00</span><span>10:00</span><span>12:00</span><span>14:00</span><span>16:00</span><span>18:00</span><span>20:00</span><span>22:00</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">Projects</h3>
                    <button class="text-xs font-semibold text-gray-500 flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg hover:bg-slate-200 transition">Ongoing <i class="fa-solid fa-chevron-down text-[10px]"></i></button>
                </div>
                <div class="grid grid-cols-1 gap-5">
                    <?php foreach ($projects as $proj): ?>
                    <div class="border border-gray-100 rounded-xl p-5 bg-gradient-to-br from-white to-slate-50 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between mb-4">
                            <h4 class="font-bold text-sm text-slate-800"><?php echo $proj['title']; ?></h4>
                            <button class="text-gray-300 hover:text-gray-500"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        </div>
                        <div class="flex items-center gap-3 mb-4">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($proj['leader']); ?>&background=random" class="w-10 h-10 rounded-full shadow">
                            <div>
                                <p class="text-sm font-bold text-slate-800"><?php echo $proj['leader']; ?></p>
                                <p class="text-[10px] text-gray-400">Project Leader</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mb-4 p-3 bg-slate-50 rounded-lg">
                            <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center text-orange-500">
                                <i class="fa-regular fa-calendar"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-800"><?php echo $proj['deadline']; ?></p>
                                <p class="text-[10px] text-gray-400">Deadline</p>
                            </div>
                        </div>
                        <div class="bg-slate-100 rounded-lg p-3 flex justify-between items-center">
                            <span class="text-xs font-medium text-gray-500">Time Spent</span>
                            <span class="text-sm font-bold text-slate-800"><?php echo $proj['spent']; ?>/<?php echo $proj['total_hrs']; ?> <span class="text-gray-400 font-normal text-xs">Hrs</span></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">Tasks</h3>
                    <button class="text-xs font-semibold text-gray-500 flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg hover:bg-slate-200 transition">All Tasks <i class="fa-solid fa-chevron-down text-[10px]"></i></button>
                </div>
                <div class="space-y-3">
                    <?php foreach ($tasks as $task): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-100 rounded-xl hover:bg-slate-50 transition-colors hover:border-teal-200">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-grip-vertical text-gray-200 text-xs cursor-move"></i>
                            <input type="checkbox" class="w-4 h-4 rounded text-teal-600 border-gray-300 focus:ring-teal-500" <?php echo $task['checked'] ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium text-slate-700 <?php echo $task['checked'] ? 'line-through text-gray-400' : ''; ?>"><?php echo $task['name']; ?></span>
                        </div>
                        <span class="text-[10px] font-bold px-3 py-1 rounded-full <?php echo $task['color']; ?>">● <?php echo $task['status']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6 mb-6">
            <div class="col-span-12 lg:col-span-7 bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">Performance</h3>
                    <button class="border rounded-lg px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-50 flex items-center gap-1"><i class="fa-regular fa-calendar"></i> 2026</button>
                </div>
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-4xl font-black text-slate-800">98%</span>
                    <span class="text-xs font-bold text-green-600 bg-green-100 px-3 py-1 rounded-full flex items-center gap-1"><i class="fa-solid fa-arrow-up text-[10px]"></i> 12% vs last year</span>
                </div>
                <div class="h-56 w-full">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-5 bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">My Skills</h3>
                    <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1"><i class="fa-regular fa-calendar"></i> 2026</div>
                </div>
                <div class="space-y-4">
                    <?php foreach ($skills as $skill): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-100 rounded-xl hover:border-teal-200 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-1.5 h-10 rounded-full" style="background: <?php echo $skill['color']; ?>;"></div>
                            <div>
                                <p class="text-sm font-bold text-slate-800"><?php echo $skill['name']; ?></p>
                                <p class="text-[10px] text-gray-400">Updated: <?php echo $skill['date']; ?></p>
                            </div>
                        </div>
                        <div class="progress-circle" style="background: conic-gradient(<?php echo $skill['color']; ?> <?php echo $skill['percent']; ?>%, #f1f5f9 0);">
                            <span><?php echo $skill['percent']; ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                    <button class="text-xs font-bold text-teal-600 bg-teal-50 px-4 py-2 rounded-lg hover:bg-teal-100 transition">View All</button>
                </div>
                <div class="space-y-5">
                    <?php foreach ($notifications as $note): ?>
                    <div class="flex gap-4 p-3 hover:bg-slate-50 rounded-xl transition">
                        <img src="<?php echo $note['img']; ?>" class="w-11 h-11 rounded-full flex-shrink-0 object-cover">
                        <div>
                            <p class="text-sm font-semibold text-slate-800"><span class="font-bold"><?php echo $note['user']; ?></span> <?php echo $note['action']; ?></p>
                            <p class="text-[10px] text-gray-400 mb-2"><?php echo $note['time']; ?></p>
                            <?php if(isset($note['file'])): ?>
                            <div class="flex items-center gap-2 p-2 border border-gray-100 rounded-lg bg-slate-50">
                                <i class="fa-solid fa-file-pdf text-red-500 text-sm"></i>
                                <span class="text-xs font-medium text-slate-600"><?php echo $note['file']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(isset($note['action_btn'])): ?>
                            <div class="flex gap-2 mt-2">
                                <button class="bg-teal-600 text-white text-[10px] font-bold px-4 py-1.5 rounded-lg">Approve</button>
                                <button class="border border-teal-600 text-teal-600 text-[10px] font-bold px-4 py-1.5 rounded-lg">Decline</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card h-full">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">Meetings Schedule</h3>
                    <button class="text-xs font-semibold text-gray-500 flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg"><i class="fa-regular fa-calendar"></i> Today</button>
                </div>
                <div class="space-y-5 relative">
                    <div class="absolute left-[76px] top-2 bottom-2 w-0.5 bg-gray-100"></div>
                    <?php foreach ($meetings as $meet): ?>
                    <div class="flex items-start gap-4 relative">
                        <span class="text-xs font-bold text-gray-500 w-16 text-right pt-1"><?php echo $meet['time']; ?></span>
                        <div class="w-3 h-3 rounded-full <?php echo $meet['color']; ?> absolute left-[72px] top-1.5 z-10 border-2 border-white shadow"></div>
                        <div class="flex-grow bg-gradient-to-r from-slate-50 to-white p-4 rounded-xl border border-gray-100 hover:shadow-md transition cursor-pointer">
                            <p class="text-sm font-bold text-slate-800"><?php echo $meet['title']; ?></p>
                            <p class="text-[10px] text-gray-500 mt-1"><?php echo $meet['dept']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </main>

    <script>
        // --- ATTENDANCE LOGIC ---
        let isCheckedIn = false;
        let timerInterval;
        let seconds = 0;

        function toggleAttendance() {
            const btn = document.getElementById('attendanceBtn');
            const btnText = document.getElementById('btnText');
            const btnIcon = document.getElementById('btnIcon');
            const statusDot = document.getElementById('statusDot');
            isCheckedIn = !isCheckedIn;
            if (isCheckedIn) {
                btn.classList.replace('checked-out', 'checked-in');
                btnText.innerText = "Check Out";
                btnIcon.classList.replace('fa-right-to-bracket', 'fa-right-from-bracket');
                statusDot.classList.replace('bg-gray-400', 'bg-green-500');
                startTimer();
            } else {
                btn.classList.replace('checked-in', 'checked-out');
                btnText.innerText = "Check In";
                btnIcon.classList.replace('fa-right-from-bracket', 'fa-right-to-bracket');
                statusDot.classList.replace('bg-green-500', 'bg-gray-400');
                stopTimer();
            }
        }

        function startTimer() {
            timerInterval = setInterval(() => {
                seconds++;
                let hrs = Math.floor(seconds / 3600);
                let mins = Math.floor((seconds % 3600) / 60);
                let secs = seconds % 60;
                document.getElementById('timerText').innerText = `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            }, 1000);
        }

        function stopTimer() { clearInterval(timerInterval); }

        const leaveCtx = document.getElementById('leaveChart').getContext('2d');
        new Chart(leaveCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?php echo $stats['on_time']; ?>, <?php echo $stats['late']; ?>, <?php echo $stats['wfh']; ?>, <?php echo $stats['absent']; ?>, <?php echo $stats['sick']; ?>],
                    backgroundColor: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                    borderWidth: 0, 
                    cutout: '80%'
                }]
            },
            options: { plugins: { legend: { display: false } }, maintainAspectRatio: false, responsive: true }
        });

        const perfCtx = document.getElementById('performanceChart').getContext('2d');
        const gradient = perfCtx.createLinearGradient(0, 0, 0, 250);
        gradient.addColorStop(0, 'rgba(13, 148, 136, 0.3)');
        gradient.addColorStop(1, 'rgba(13, 148, 136, 0.0)');
        new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                datasets: [{
                    data: [20000, 25000, 35000, 32000, 40000, 48000, 55000, 60000],
                    borderColor: '#0d9488',
                    backgroundColor: gradient,
                    fill: true, tension: 0.4, pointRadius: 0, borderWidth: 3
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: false, 
                        ticks: { callback: (v) => v / 1000 + 'K', font: { size: 10, weight: 'bold' }, color: '#94a3b8' }, 
                        grid: { borderDash: [5, 5], color: '#f1f5f9' } 
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8' } }
                }
            }
        });
    </script>
</body>
</html>