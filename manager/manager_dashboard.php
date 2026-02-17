<?php
// manager_dashboard.php

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. DYNAMIC SESSION DATA
 $logged_in_user = $_SESSION['username'];
 $logged_in_role = $_SESSION['role']; 
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
    "avatar" => "https://i.pravatar.cc/150?u=" . $_SESSION['user_id']
];
 $current_date = date("d-m-Y");

 $attendance_data = [
    "status" => "On Duty",
    "punch_in" => "09:00 AM",
    "total_hours" => "05:45:32",
    "production" => "4.5 hrs"
];

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
    ["label" => "Working Hrs", "value" => "12h 36m", "color" => "bg-slate-300"],
    ["label" => "Productive", "value" => "08h 36m", "color" => "bg-green-500"],
    ["label" => "Break", "value" => "22m 15s", "color" => "bg-orange-400"],
    ["label" => "Overtime", "value" => "02h 15m", "color" => "bg-blue-500"]
];

 $projects = [
    ["title" => "Office Management", "leader" => "Anthony Lewis", "deadline" => "14 Jan 2024", "spent" => "65", "total_hrs" => "120"],
    ["title" => "HRMS Dashboard UI", "leader" => "Aparna", "deadline" => "20 Feb 2026", "spent" => "90", "total_hrs" => "150"]
];

 $tasks = [
    ["name" => "Patient appointment booking", "status" => "Onhold", "color" => "text-pink-600 bg-pink-100"],
    ["name" => "Appointment booking with payment", "status" => "Inprogress", "color" => "text-purple-600 bg-purple-100"],
    ["name" => "Patient and Doctor video conferencing", "status" => "Completed", "color" => "text-green-600 bg-green-100"],
    ["name" => "Private chat module", "status" => "Pending", "color" => "text-slate-600 bg-slate-100"],
    ["name" => "Go-Live Support", "status" => "Inprogress", "color" => "text-purple-600 bg-purple-100"],
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
    ["time" => "09:25 AM", "title" => "Marketing Strategy", "dept" => "Marketing", "color" => "bg-orange-500"],
    ["time" => "11:20 AM", "title" => "Design Review", "dept" => "Review", "color" => "bg-teal-600"],
    ["time" => "02:18 PM", "title" => "Birthday Celebration", "dept" => "Event", "color" => "bg-yellow-500"],
    ["time" => "04:10 PM", "title" => "Project Flow Update", "dept" => "Dev", "color" => "bg-green-500"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | Workack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        /* Increased Base Font Size */
        html { font-size: 16px; }

        .hover-card { transition: all 0.2s ease; }
        .hover-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.07); transform: translateY(-1px); }

        .timeline-bar { height: 20px; border-radius: 50px; background: #f1f5f9; position: relative; overflow: hidden; display: flex; gap: 2px; padding: 0 2px; align-items: center; }
        .segment { height: 14px; border-radius: 8px; }
        
        .progress-circle { position: relative; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
        .progress-circle::before { content: ""; position: absolute; inset: 4px; background: white; border-radius: 50%; z-index: 1; }
        .progress-circle span { position: relative; z-index: 2; font-size: 10px; font-weight: bold; }

        /* LAYOUT OPTIMIZED */
        #mainContent { 
            margin-left: 95px; 
            transition: margin-left 0.3s ease; 
            padding: 20px 24px; 
            width: calc(100% - 95px);
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }
    </style>
</head>
<body class="bg-slate-100">

    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>

    <main id="mainContent">

        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Manager Dashboard</h1>
                <nav class="flex text-xs text-gray-500 mt-1 gap-2 items-center">
                    <i class="fa-solid fa-house text-teal-600"></i>
                    <span>/</span>
                    <span>Dashboard</span>
                    <span>/</span>
                    <span class="text-teal-700 font-medium">Manager Overview</span>
                </nav>
            </div>
            <div class="flex gap-2">
                <button class="bg-white border px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-file-export text-gray-400"></i> Export
                </button>
                <button class="bg-white border px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fa-regular fa-calendar text-gray-400"></i> <?php echo $current_date; ?>
                </button>
            </div>
        </div>

        <!-- Top Grid (Profile, Leave, Summary) -->
        <div class="grid grid-cols-12 gap-5 mb-5">
            
            <!-- Profile Card -->
            <div class="col-span-12 lg:col-span-3">
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden hover-card h-full">
                    <div class="bg-gradient-to-r from-teal-600 to-teal-700 p-6 relative text-center">
                        <img src="<?php echo $employee['avatar']; ?>" class="w-20 h-20 rounded-full border-4 border-white shadow-lg object-cover mx-auto">
                        <div class="absolute top-4 right-4 bg-white/20 p-1.5 rounded-lg"><i class="fa-solid fa-gear text-white text-xs"></i></div>
                        <h2 class="text-white font-bold text-lg mt-3"><?php echo $employee['name']; ?></h2>
                        <p class="text-teal-100 text-xs"><?php echo $employee['role']; ?> • <?php echo $employee['department']; ?></p>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-phone text-teal-600 text-xs"></i></div>
                            <div><p class="text-[9px] text-gray-400 uppercase font-bold">Phone</p><p class="font-semibold text-xs"><?php echo $employee['phone']; ?></p></div>
                        </div>
                        <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-envelope text-teal-600 text-xs"></i></div>
                            <div><p class="text-[9px] text-gray-400 uppercase font-bold">Email</p><p class="font-semibold text-xs truncate"><?php echo $employee['email']; ?></p></div>
                        </div>
                         <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-lg">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center"><i class="fa-solid fa-user-tie text-teal-600 text-xs"></i></div>
                            <div><p class="text-[9px] text-gray-400 uppercase font-bold">Reports To</p><p class="font-semibold text-xs"><?php echo $employee['report_office']; ?></p></div>
                        </div>
                        <div class="flex items-center justify-between p-2 bg-green-50 rounded-lg">
                            <span class="text-[10px] text-gray-600 font-medium flex items-center gap-2"><i class="fa-solid fa-calendar-check text-green-600"></i> Joined On</span>
                            <span class="font-bold text-xs text-slate-800"><?php echo $employee['joined_date']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Details Card (Optimized Space) -->
            <div class="col-span-12 lg:col-span-5">
                <div class="bg-white rounded-xl shadow-sm border p-6 hover-card h-full flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                        <span class="text-[10px] font-bold text-gray-500 bg-slate-100 px-2 py-1 rounded"><i class="fa-regular fa-calendar mr-1"></i>2026</span>
                    </div>
                    <!-- Added flex-grow, items-center, justify-around to fill space -->
                    <div class="flex-grow flex items-center justify-around">
                        <div class="space-y-4 text-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-teal-600"></div>
                                <span class="text-teal-600 font-bold w-14 text-base"><?php echo $stats['on_time']; ?></span>
                                <span class="text-gray-600">On Time</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                <span class="text-green-500 font-bold w-14 text-base"><?php echo $stats['late']; ?></span>
                                <span class="text-gray-600">Late</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                <span class="text-orange-500 font-bold w-14 text-base"><?php echo $stats['wfh']; ?></span>
                                <span class="text-gray-600">WFH</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                <span class="text-red-500 font-bold w-14 text-base"><?php echo $stats['absent']; ?></span>
                                <span class="text-gray-600">Absent</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                <span class="text-yellow-500 font-bold w-14 text-base"><?php echo $stats['sick']; ?></span>
                                <span class="text-gray-600">Sick</span>
                            </div>
                        </div>
                        
                        <!-- Increased Chart Size to w-52 h-52 -->
                        <div class="w-52 h-52 relative flex-shrink-0">
                            <canvas id="leaveChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-3xl font-black text-slate-800"><?php echo $stats['percentile']; ?>%</span>
                                <span class="text-xs text-gray-400 font-medium">Performance</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Card (Optimized Space) -->
            <div class="col-span-12 lg:col-span-4">
                <div class="bg-white rounded-xl shadow-sm border p-6 hover-card h-full flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                        <span class="text-[10px] font-bold text-gray-500 bg-slate-100 px-2 py-1 rounded"><i class="fa-regular fa-calendar mr-1"></i>2026</span>
                    </div>
                    <!-- Added flex-grow to fill height -->
                    <div class="flex-grow flex flex-col justify-between">
                        <div class="grid grid-cols-3 gap-4 mb-5">
                            <!-- Increased padding and text size -->
                            <div class="text-center p-4 bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl">
                                <p class="text-gray-500 text-xs mb-1">Total</p>
                                <p class="font-bold text-2xl text-teal-700"><?php echo $leave_summary['total']; ?></p>
                            </div>
                            <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl">
                                <p class="text-gray-500 text-xs mb-1">Taken</p>
                                <p class="font-bold text-2xl text-blue-700"><?php echo $leave_summary['taken']; ?></p>
                            </div>
                            <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl">
                                <p class="text-gray-500 text-xs mb-1">Left</p>
                                <p class="font-bold text-2xl text-green-700"><?php echo $leave_summary['total'] - $leave_summary['taken']; ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-5">
                            <!-- Increased padding -->
                            <div class="text-center p-3 bg-slate-50 rounded-xl">
                                <p class="text-gray-500 text-[10px]">Absent</p>
                                <p class="font-bold text-lg text-slate-800"><?php echo $leave_summary['absent']; ?></p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl">
                                <p class="text-gray-500 text-[10px]">Request</p>
                                <p class="font-bold text-lg text-slate-800"><?php echo $leave_summary['request']; ?></p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl">
                                <p class="text-gray-500 text-[10px]">LOP</p>
                                <p class="font-bold text-lg text-red-500"><?php echo $leave_summary['lop']; ?></p>
                            </div>
                        </div>
                        <a href="employee/leave_request.php" class="w-full mt-auto bg-teal-600 text-white py-2.5 rounded-lg font-bold text-xs shadow-sm hover:bg-teal-700 transition inline-block text-center no-underline">
                            <i class="fa-solid fa-plus mr-1"></i> Apply New Leave
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance & Stats Row -->
        <div class="grid grid-cols-12 gap-5 mb-5">
            
            <!-- Punch In/Out Card -->
            <div class="col-span-12 lg:col-span-3">
                <div class="bg-white rounded-xl shadow-sm border p-5 hover-card h-full">
                    <div class="text-center mb-3">
                        <h3 class="text-gray-400 text-[10px] font-bold uppercase tracking-wider">Today's Attendance</h3>
                        <p class="text-slate-800 font-bold text-sm mt-1"><?php echo date("h:i A") . ', ' . date("d M Y"); ?></p>
                    </div>
                    <div class="relative flex items-center justify-center my-3">
                        <svg class="w-28 h-28 transform -rotate-90">
                            <circle cx="56" cy="56" r="50" stroke="#f1f5f9" stroke-width="8" fill="transparent" />
                            <circle cx="56" cy="56" r="50" stroke="#144d4d" stroke-width="8" fill="transparent" stroke-dasharray="314" stroke-dashoffset="100" stroke-linecap="round" />
                        </svg>
                        <div class="absolute text-center">
                            <p class="text-gray-400 text-[9px] uppercase font-bold">Total Hours</p>
                            <p class="text-slate-800 font-bold text-lg leading-tight"><?php echo $attendance_data['total_hours']; ?></p>
                        </div>
                    </div>
                    <div class="text-center space-y-2">
                        <div class="inline-block bg-teal-100 text-teal-700 px-3 py-1 rounded text-[10px] font-bold">
                            Production : <?php echo $attendance_data['production']; ?>
                        </div>
                        <p class="text-[10px] text-gray-500"><i class="fa-solid fa-fingerprint text-orange-500 mr-1"></i> Punch In at <?php echo $attendance_data['punch_in']; ?></p>
                        <button id="punchBtn" onclick="togglePunch()" class="w-full bg-orange-500 text-white font-bold py-2 rounded-lg text-xs shadow-sm hover:bg-orange-600 transition">
                            <i class="fa-solid fa-right-from-bracket mr-1"></i> Punch Out
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="col-span-12 lg:col-span-9">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 h-full">
                    <?php foreach ($hourly_stats as $card): ?>
                    <div class="bg-white p-6 rounded-xl shadow-sm border hover-card flex flex-col items-center justify-center text-center">
                        <div class="w-12 h-12 rounded-lg bg-gradient-to-br <?php echo $card['bg']; ?> flex items-center justify-center text-white shadow mb-3">
                            <i class="fa-solid <?php echo $card['icon']; ?> text-lg"></i>
                        </div>
                        <p class="text-2xl font-black text-slate-800"><?php echo $card['value']; ?> <span class="text-gray-300 text-base">/ <?php echo $card['total']; ?></span></p>
                        <p class="text-gray-500 text-[11px] font-medium uppercase mt-1"><?php echo $card['label']; ?></p>
                        <div class="text-[11px] font-bold mt-2 <?php echo $card['up'] ? 'text-green-500' : 'text-red-500'; ?>">
                            <i class="fa-solid <?php echo $card['up'] ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> <?php echo $card['trend']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Timeline Bar -->
        <div class="bg-white rounded-xl border p-5 shadow-sm hover-card mb-5">
            <div class="flex flex-col lg:flex-row justify-between mb-4 gap-3">
                <h3 class="font-bold text-slate-800 text-sm">Work Timeline</h3>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($timeline_stats as $t_stat): ?>
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full <?php echo $t_stat['color']; ?>"></div>
                        <span class="text-[10px] text-gray-500"><?php echo $t_stat['label']; ?></span>
                        <span class="text-xs font-bold text-slate-800"><?php echo $t_stat['value']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="timeline-bar mb-2">
                <div class="segment bg-green-500" style="width: 15%; margin-left: 1%;"></div>
                <div class="segment bg-orange-400" style="width: 4%;"></div>
                <div class="segment bg-green-500" style="width: 25%;"></div>
                <div class="segment bg-orange-400" style="width: 3%;"></div>
                <div class="segment bg-green-500" style="width: 20%;"></div>
                <div class="segment bg-orange-400" style="width: 3%;"></div>
                <div class="segment bg-blue-500" style="width: 8%;"></div>
                <div class="segment bg-slate-200" style="width: 19%;"></div>
            </div>
            <div class="flex justify-between text-[9px] text-gray-400 font-bold px-1">
                <span>06:00</span><span>08:00</span><span>10:00</span><span>12:00</span><span>14:00</span><span>16:00</span><span>18:00</span><span>20:00</span><span>22:00</span>
            </div>
        </div>

        <!-- Projects & Tasks -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
            
            <!-- Projects -->
            <div class="bg-white rounded-xl shadow-sm border p-5 hover-card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800">Projects</h3>
                    <button class="text-[10px] font-semibold text-gray-500 flex items-center gap-1 bg-slate-100 px-3 py-1.5 rounded">Ongoing <i class="fa-solid fa-chevron-down text-[8px]"></i></button>
                </div>
                <div class="grid grid-cols-1 gap-3">
                    <?php foreach ($projects as $proj): ?>
                    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="flex justify-between mb-2">
                            <h4 class="font-bold text-sm text-slate-800"><?php echo $proj['title']; ?></h4>
                            <i class="fa-solid fa-ellipsis-vertical text-gray-300 text-xs cursor-pointer"></i>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($proj['leader']); ?>&background=random" class="w-7 h-7 rounded-full">
                            <div><p class="text-[11px] font-bold"><?php echo $proj['leader']; ?></p><p class="text-[9px] text-gray-400">Leader</p></div>
                        </div>
                        <div class="bg-slate-50 rounded p-2 flex justify-between items-center">
                            <span class="text-[10px] text-gray-500">Deadline: <?php echo $proj['deadline']; ?></span>
                            <span class="text-xs font-bold text-teal-600"><?php echo $proj['spent']; ?>/<?php echo $proj['total_hrs']; ?> Hrs</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tasks -->
            <div class="bg-white rounded-xl shadow-sm border p-5 hover-card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800">Tasks</h3>
                    <button class="text-[10px] font-semibold text-gray-500 flex items-center gap-1 bg-slate-100 px-3 py-1.5 rounded">All <i class="fa-solid fa-chevron-down text-[8px]"></i></button>
                </div>
                <div class="space-y-2">
                    <?php foreach ($tasks as $task): ?>
                    <div class="flex items-center justify-between p-2 border rounded-lg hover:bg-slate-50 transition">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-grip-vertical text-gray-100 text-xs cursor-move"></i>
                            <input type="checkbox" class="rounded text-teal-600">
                            <span class="text-xs font-medium text-slate-700"><?php echo $task['name']; ?></span>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?php echo $task['color']; ?>">● <?php echo $task['status']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Performance & Skills -->
        <div class="grid grid-cols-12 gap-5 mb-5">
            
            <!-- Performance Chart -->
            <div class="col-span-12 lg:col-span-7 bg-white rounded-xl shadow-sm border p-5 hover-card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800">Performance</h3>
                    <button class="border rounded px-2 py-1 text-[10px] text-gray-500"><i class="fa-regular fa-calendar mr-1"></i> 2026</button>
                </div>
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-3xl font-black text-slate-800">98%</span>
                    <span class="text-[10px] font-bold text-green-600 bg-green-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-arrow-up text-[8px]"></i> 12% vs last year</span>
                </div>
                <div class="h-48 w-full"><canvas id="performanceChart"></canvas></div>
            </div>

            <!-- My Skills -->
            <div class="col-span-12 lg:col-span-5 bg-white rounded-xl shadow-sm border p-5 hover-card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800">My Skills</h3>
                    <span class="text-[10px] font-bold text-gray-500 bg-slate-100 px-2 py-1 rounded">2026</span>
                </div>
                <div class="space-y-3">
                    <?php foreach ($skills as $skill): ?>
                    <div class="flex items-center justify-between p-3 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-8 rounded-full" style="background: <?php echo $skill['color']; ?>;"></div>
                            <div><p class="text-xs font-bold"><?php echo $skill['name']; ?></p><p class="text-[9px] text-gray-400">Updated: <?php echo $skill['date']; ?></p></div>
                        </div>
                        <div class="progress-circle" style="background: conic-gradient(<?php echo $skill['color']; ?> <?php echo $skill['percent']; ?>%, #f1f5f9 0);"><span><?php echo $skill['percent']; ?>%</span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Notifications & Meetings -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
            
            <!-- Notifications -->
            <div class="bg-white rounded-xl shadow-sm border p-5 hover-card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800">Notifications</h3>
                    <button class="text-[10px] font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded">View All</button>
                </div>
                <div class="space-y-3">
                    <?php foreach ($notifications as $note): ?>
                    <div class="flex gap-3 p-2 hover:bg-slate-50 rounded-lg transition">
                        <img src="<?php echo $note['img']; ?>" class="w-9 h-9 rounded-full flex-shrink-0 object-cover">
                        <div class="flex-1">
                            <p class="text-xs font-semibold text-slate-800"><span class="font-bold"><?php echo $note['user']; ?></span> <?php echo $note['action']; ?></p>
                            <p class="text-[9px] text-gray-400 mb-1"><?php echo $note['time']; ?></p>
                            <?php if(isset($note['file'])): ?>
                            <div class="flex items-center gap-2 p-1.5 border rounded bg-slate-50">
                                <i class="fa-solid fa-file-pdf text-red-500 text-xs"></i>
                                <span class="text-[10px] font-medium"><?php echo $note['file']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(isset($note['action_btn'])): ?>
                            <div class="flex gap-2 mt-1">
                                <button class="bg-teal-600 text-white text-[9px] font-bold px-3 py-1 rounded">Approve</button>
                                <button class="border border-teal-600 text-teal-600 text-[9px] font-bold px-3 py-1 rounded">Decline</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Meetings -->
            <div class="bg-white rounded-xl shadow-sm border p-5 hover-card h-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800">Meetings</h3>
                    <button class="text-[10px] font-semibold text-gray-500 flex items-center gap-1 bg-slate-100 px-3 py-1 rounded"><i class="fa-regular fa-calendar"></i> Today</button>
                </div>
                <div class="space-y-4 relative">
                    <div class="absolute left-[68px] top-1 bottom-1 w-0.5 bg-gray-100"></div>
                    <?php foreach ($meetings as $meet): ?>
                    <div class="flex items-start gap-3 relative">
                        <span class="text-[10px] font-bold text-gray-400 w-14 text-right pt-0.5"><?php echo $meet['time']; ?></span>
                        <div class="w-2.5 h-2.5 rounded-full <?php echo $meet['color']; ?> absolute left-[64px] top-1 z-10 border-2 border-white shadow"></div>
                        <div class="flex-grow bg-slate-50 p-3 rounded-lg border">
                            <p class="text-xs font-bold text-slate-800"><?php echo $meet['title']; ?></p>
                            <p class="text-[9px] text-gray-500 mt-0.5"><?php echo $meet['dept']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </main>

    <script>
        function togglePunch() {
            const btn = document.getElementById('punchBtn');
            const isIn = btn.innerText.includes("Punch In");
            if(isIn) {
                btn.innerHTML = '<i class="fa-solid fa-right-from-bracket mr-1"></i> Punch Out';
                btn.classList.remove('bg-teal-600', 'hover:bg-teal-700');
                btn.classList.add('bg-orange-500', 'hover:bg-orange-600');
            } else {
                btn.innerHTML = '<i class="fa-solid fa-right-to-bracket mr-1"></i> Punch In';
                btn.classList.remove('bg-orange-500', 'hover:bg-orange-600');
                btn.classList.add('bg-teal-600', 'hover:bg-teal-700');
            }
        }

        // Leave Chart
        new Chart(document.getElementById('leaveChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?= $stats['on_time'] ?>, <?= $stats['late'] ?>, <?= $stats['wfh'] ?>, <?= $stats['absent'] ?>, <?= $stats['sick'] ?>],
                    backgroundColor: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                    borderWidth: 0, cutout: '80%'
                }]
            },
            options: { plugins: { legend: { display: false } }, maintainAspectRatio: false, responsive: true }
        });

        // Performance Chart
        const perfCtx = document.getElementById('performanceChart').getContext('2d');
        const gradient = perfCtx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(13, 148, 136, 0.3)');
        gradient.addColorStop(1, 'rgba(13, 148, 136, 0.0)');

        new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                datasets: [{
                    data: [20000, 25000, 35000, 32000, 40000, 48000, 55000, 60000],
                    borderColor: '#0d9488', backgroundColor: gradient, fill: true, tension: 0.4, pointRadius: 0, borderWidth: 2
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: false, ticks: { callback: (v) => v / 1000 + 'K', font: { size: 9 }, color: '#94a3b8' }, grid: { borderDash: [3, 3], color: '#f1f5f9' } },
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#94a3b8' } }
                }
            }
        });
    </script>
</body>
</html>