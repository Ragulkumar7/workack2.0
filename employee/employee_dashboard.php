<?php
// --- PATH & SESSION LOGIC ---
 $path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- DATABASE CONNECTION ---
// Using the corrected path
require_once '../include/db_connect.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

 $current_user_id = $_SESSION['user_id'];

// --- MYSQL QUERY TO FETCH USER DATA ---
 $sql = "SELECT username, role FROM users WHERE id = ?";
 $stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
 $user_result = mysqli_stmt_get_result($stmt);
 $user_info = mysqli_fetch_assoc($user_result);

// Dynamic variables from database
 $employee_name = $user_info['username']; 
 $employee_role = $user_info['role']; 

// --- ATTENDANCE LOGIC (PUNCH IN/OUT) ---
 $today = date('Y-m-d');

// Check for existing record for today
 $check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
 $check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
 $attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

// Handle Punch Button Clicks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'punch_in' && !$attendance_record) {
        $punch_in_time = date('Y-m-d H:i:s');
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date) VALUES (?, ?, ?)";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "iss", $current_user_id, $punch_in_time, $today);
        mysqli_stmt_execute($ins_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    } elseif ($_POST['action'] == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        $punch_out_time = date('Y-m-d H:i:s');
        $upd_sql = "UPDATE attendance SET punch_out = ? WHERE id = ?";
        $upd_stmt = mysqli_prepare($conn, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, "si", $punch_out_time, $attendance_record['id']);
        mysqli_stmt_execute($upd_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    } elseif ($_POST['action'] == 'take_break') {
        // Logic for break start can be added here (e.g., updating DB status to 'On Break')
        // For now, it refreshes to show button interaction
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Display Variables for Attendance section
 $display_punch_in = $attendance_record ? date('h:i A', strtotime($attendance_record['punch_in'])) : '--:--';
 $total_hours_today = "0:00:00";
if ($attendance_record && $attendance_record['punch_out']) {
    $start = new DateTime($attendance_record['punch_in']);
    $end = new DateTime($attendance_record['punch_out']);
    $total_hours_today = $start->diff($end)->format('%H:%I:%S');
} elseif ($attendance_record && !$attendance_record['punch_out']) {
    $start = new DateTime($attendance_record['punch_in']);
    $end = new DateTime();
    $total_hours_today = $start->diff($end)->format('%H:%I:%S');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - <?php echo htmlspecialchars($employee_name); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        body { 
            background-color: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b; 
        }
        
        .stat-card { 
            transition: transform 0.2s, box-shadow 0.2s; 
        }
        .stat-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        
        .timeline-bar { 
            height: 24px; 
            border-radius: 50px; 
            background: #f1f5f9; 
            position: relative; 
            overflow: hidden; 
            display: flex; 
            gap: 3px; 
            padding: 0 3px; 
            align-items: center; 
        }
        .segment { 
            height: 16px; 
            border-radius: 8px; 
            transition: all 0.3s;
        }
        .segment:hover {
            transform: scaleY(1.2);
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        
        /* Dark Teal Theme Colors */
        .bg-teal-custom { background-color: #0d9488; }
        .text-teal-custom { color: #0d9488; }
        .border-teal-custom { border-color: #0d9488; }
        .bg-teal-dark { background-color: #115e59; }
        
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { 
            content: ''; 
            position: absolute; 
            left: 85px; 
            top: 0; 
            bottom: 0; 
            width: 2px; 
            background: linear-gradient(to bottom, #e2e8f0, #f1f5f9); 
        }

        /* Sidebar Layout */
        #mainContent {
            margin-left: 90px;
            width: calc(100% - 90px);
            transition: margin-left 0.3s ease, width 0.3s ease;
        }
        
        #mainContent.main-shifted {
            margin-left: 310px;
            width: calc(100% - 310px);
        }

        /* Card Hover Effects */
        .hover-card {
            transition: all 0.3s ease;
        }
        .hover-card:hover {
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
        }

        /* Progress Circle Animation */
        @keyframes progressFill {
            from { stroke-dashoffset: 314; }
        }
        .progress-ring {
            animation: progressFill 1s ease-out;
        }

        /* Skill Bar Animation */
        @keyframes slideIn {
            from { width: 0; }
        }
        .skill-progress {
            animation: slideIn 0.8s ease-out;
        }
        
        /* Grid improvements */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1.5rem;
            align-items: start;
        }
        
        /* Equal height cards */
        .equal-height-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body class="bg-slate-100">
    
    <?php include $path_to_root . 'sidebars.php'; ?>
    <?php include $path_to_root . 'header.php'; ?>

    <div class="min-h-screen">
        
        <main id="mainContent" class="p-6 lg:p-8">

            <?php
            // Configuration & Data
            $attendance_date = date("d M Y");
            $attendance_time_display = date("h:i A"); // Current time for display if not fetched
            
            // Date & Resignation Data
            $joining_date = "15 Jan 2024";
            $status = "Resigned"; 
            $notice_period_days = 18; 
            $last_working_day = "25 Feb 2026";
            
            // Data for Charts
            $stats_ontime = 1254;
            $stats_late = 32;
            $stats_wfh = 658;
            $stats_absent = 14;
            $stats_sick = 68;
            ?>

            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Employee Dashboard</h1>
                    <nav class="flex text-sm text-gray-500 mt-2">
                        <ol class="inline-flex items-center space-x-2">
                            <li class="flex items-center"><i class="fa-solid fa-house text-xs text-teal-custom"></i></li>
                            <li class="flex items-center"><i class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i> Dashboard</li>
                            <li class="flex items-center"><i class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i> <span class="text-teal-custom font-semibold">Employee Dashboard</span></li>
                        </ol>
                    </nav>
                </div>
                <div class="flex gap-3 flex-wrap">
                    
                    <button class="bg-white border border-gray-200 px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm hover:shadow-md transition-shadow">
                        <i class="fa-regular fa-calendar text-gray-400"></i> <?php echo date("d-m-Y"); ?>
                    </button>
                    <button class="bg-white border border-gray-200 p-2.5 rounded-xl text-sm flex items-center shadow-sm hover:shadow-md transition-shadow">
                        <i class="fa-solid fa-angles-up text-gray-400"></i>
                    </button>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover-card equal-height-card h-full">
                        <div class="bg-gradient-to-r from-teal-600 to-teal-700 p-8 pb-10">
                            <div class="flex flex-col items-center text-center">
                                <div class="relative">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee_name); ?>&background=ffffff&color=0d9488&size=100&font-size=0.4&bold=true" class="w-24 h-24 rounded-full border-4 border-white shadow-lg">
                                    <div class="absolute bottom-1 right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-white"></div>
                                </div>
                                <h2 class="text-white font-bold text-xl mt-4"><?php echo htmlspecialchars($employee_name); ?></h2>
                                <p class="text-teal-100 text-sm mt-1"><?php echo htmlspecialchars($employee_role); ?></p>
                                <span class="inline-block bg-white/20 text-white text-xs px-3 py-1 rounded-full mt-2">Verified Account</span>
                            </div>
                        </div>
                        <div class="p-6 space-y-5 flex-grow">
                            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                                <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-phone text-teal-custom"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Phone</p>
                                    <p class="font-semibold text-sm text-slate-800">+1 324 3453 545</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                                <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-envelope text-teal-custom"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Email</p>
                                    <p class="font-semibold text-sm text-slate-800"><?php echo htmlspecialchars($employee_name); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                                <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-user-tie text-teal-custom"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Reports To</p>
                                    <p class="font-semibold text-sm text-slate-800">System Admin</p>
                                </div>
                            </div>
                            
                            <div class="border-t border-dashed border-gray-200 pt-5 space-y-4">
                                <div class="flex items-center justify-between p-3 bg-green-50 rounded-xl">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-calendar-check text-green-600 text-sm"></i>
                                        </div>
                                        <span class="text-xs text-gray-600 font-medium">Joined On</span>
                                    </div>
                                    <span class="font-bold text-sm text-slate-800"><?php echo $joining_date; ?></span>
                                </div>

                                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-xl">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-orange-100 flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-file-signature text-orange-600 text-sm"></i>
                                        </div>
                                        <span class="text-xs text-gray-600 font-medium">Status</span>
                                    </div>
                                    <span class="font-bold text-sm text-orange-600"><?php echo $status; ?></span>
                                </div>

                                <?php if($status == "Resigned"): ?>
                                <div class="p-4 bg-gradient-to-r from-red-50 to-orange-50 rounded-xl border border-red-100">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs text-red-600 font-bold uppercase flex items-center gap-1">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Resignation Info
                                        </span>
                                        <span class="text-[10px] bg-white text-orange-600 px-2.5 py-1 rounded-full border border-orange-200 font-bold shadow-sm"><?php echo $notice_period_days; ?> Days Notice</span>
                                    </div>
                                    <div class="flex justify-between items-center mt-3 pt-3 border-t border-red-100">
                                        <span class="text-xs text-gray-500 font-medium">Last Working Day:</span>
                                        <span class="text-sm font-bold text-red-700"><?php echo $last_working_day; ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                            <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1 shrink-0">
                                <i class="fa-regular fa-calendar"></i> 2026
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-8 flex-wrap lg:flex-nowrap">
                            <div class="space-y-4 flex-1 min-w-[200px]">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-teal-600 shrink-0"></div>
                                    <span class="text-teal-600 font-bold w-16 shrink-0"><?php echo $stats_ontime; ?></span>
                                    <span class="text-gray-600 text-sm">On Time</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-green-500 shrink-0"></div>
                                    <span class="text-green-500 font-bold w-16 shrink-0"><?php echo $stats_late; ?></span>
                                    <span class="text-gray-600 text-sm">Late Attendance</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-orange-500 shrink-0"></div>
                                    <span class="text-orange-500 font-bold w-16 shrink-0"><?php echo $stats_wfh; ?></span>
                                    <span class="text-gray-600 text-sm">Work From Home</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-500 shrink-0"></div>
                                    <span class="text-red-500 font-bold w-16 shrink-0"><?php echo $stats_absent; ?></span>
                                    <span class="text-gray-600 text-sm">Absent</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-yellow-500 shrink-0"></div>
                                    <span class="text-yellow-500 font-bold w-16 shrink-0"><?php echo $stats_sick; ?></span>
                                    <span class="text-gray-600 text-sm">Sick Leave</span>
                                </div>
                                <div class="pt-3 mt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500 flex items-center gap-2">
                                        <i class="fa-solid fa-check-circle text-teal-600"></i> 
                                        Better than <span class="text-slate-800 font-bold">85%</span> of Employees
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex-shrink-0 flex justify-center">
                                <div id="attendanceChart" class="w-44 h-44"></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Balance</h3>
                            <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1 shrink-0">
                                <i class="fa-regular fa-calendar"></i> 2026
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-6 mb-6">
                            <div class="text-center p-4 bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl">
                                <p class="text-gray-500 text-xs font-medium mb-1">Total Leaves</p>
                                <p class="font-bold text-2xl text-teal-700">16</p>
                            </div>
                            <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl">
                                <p class="text-gray-500 text-xs font-medium mb-1">Taken</p>
                                <p class="font-bold text-2xl text-blue-700">10</p>
                            </div>
                            <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl">
                                <p class="text-gray-500 text-xs font-medium mb-1">Remaining</p>
                                <p class="font-bold text-2xl text-green-700">6</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-6 mb-6">
                            <div class="text-center p-3 bg-slate-50 rounded-xl">
                                <p class="text-gray-500 text-xs">Absent</p>
                                <p class="font-bold text-lg text-slate-800">2</p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl">
                                <p class="text-gray-500 text-xs">Request</p>
                                <p class="font-bold text-lg text-slate-800">0</p>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-xl">
                                <p class="text-gray-500 text-xs">Loss of Pay</p>
                                <p class="font-bold text-lg text-red-500">2</p>
                            </div>
                        </div>
                        <button onclick="window.location.href='leave_request.php'" class="w-full bg-gradient-to-r from-teal-600 to-teal-700 text-white py-4 rounded-xl font-bold text-sm uppercase tracking-wider shadow-lg shadow-teal-200 hover:shadow-xl hover:shadow-teal-300 transition-all">
                            <i class="fa-solid fa-plus mr-2"></i> Apply New Leave
                        </button>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <!-- Today's Attendance Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card">
                        <div class="text-center mb-6">
                            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-2">Today's Attendance</h3>
                            <p class="text-slate-800 font-bold text-lg"><?php echo date('h:i A, d M Y'); ?></p>
                        </div>
                        
                        <div class="relative flex items-center justify-center my-8">
                            <svg class="w-40 h-40 transform -rotate-90">
                                <circle cx="80" cy="80" r="70" stroke="#f1f5f9" stroke-width="12" fill="transparent" />
                                <circle cx="80" cy="80" r="70" stroke="#0d9488" stroke-width="12" fill="transparent" 
                                    stroke-dasharray="440" stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : '154'; ?>" 
                                    stroke-linecap="round" class="progress-ring" />
                            </svg>
                            <div class="absolute text-center">
                                <p class="text-gray-400 text-[10px] uppercase font-bold tracking-wider">Total Hours</p>
                                <p class="text-slate-800 font-bold text-2xl leading-tight mt-1"><?php echo $total_hours_today; ?></p>
                            </div>
                        </div>
                        
                        <div class="space-y-4 mt-auto">
                            <div class="flex justify-center">
                                <div class="inline-block bg-teal-100 text-teal-700 px-4 py-2 rounded-lg text-xs font-bold">
                                    <i class="fa-solid fa-clock mr-1"></i> 
                                    Status: <?php echo !$attendance_record ? 'Not Punched In' : ($attendance_record['punch_out'] ? 'Shift Ended' : 'On Duty'); ?>
                                </div>
                            </div>
                            <p class="text-center text-xs text-gray-500">
                                <i class="fa-solid fa-fingerprint text-orange-500 mr-1"></i> 
                                Punch In at <?php echo $display_punch_in; ?>
                            </p>

                            <form method="POST">
                                <?php if (!$attendance_record): ?>
                                    <button type="submit" name="action" value="punch_in" class="w-full bg-gradient-to-r from-teal-500 to-teal-600 text-white font-bold py-4 rounded-xl text-sm shadow-lg hover:shadow-teal-300 transition-all">
                                        <i class="fa-solid fa-right-to-bracket mr-2"></i> Punch In
                                    </button>
                                <?php elseif (!$attendance_record['punch_out']): ?>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button type="submit" name="action" value="take_break" class="w-full bg-gradient-to-r from-amber-400 to-amber-500 text-white font-bold py-4 rounded-xl text-sm shadow-lg hover:shadow-amber-200 transition-all">
                                            <i class="fa-solid fa-mug-hot mr-2"></i> Take Break
                                        </button>
                                        <button type="submit" name="action" value="punch_out" class="w-full bg-gradient-to-r from-orange-500 to-orange-600 text-white font-bold py-4 rounded-xl text-sm shadow-lg hover:shadow-orange-300 transition-all">
                                            <i class="fa-solid fa-right-from-bracket mr-2"></i> Punch Out
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-200 text-gray-500 font-bold py-4 rounded-xl text-sm cursor-not-allowed">
                                        <i class="fa-solid fa-check mr-2"></i> Shift Completed
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Notifications Section (Moved Here) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                            <button class="text-xs font-bold text-teal-custom bg-teal-50 px-4 py-2 rounded-lg hover:bg-teal-100 transition shrink-0">View All</button>
                        </div>
                        <div class="space-y-5">
                            <div class="flex gap-4 p-3 hover:bg-slate-50 rounded-xl transition">
                                <img src="https://ui-avatars.com/api/?name=Lex+Murphy&background=random" class="w-11 h-11 rounded-full flex-shrink-0">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate">Lex Murphy requested access to UNIX</p>
                                    <p class="text-[10px] text-gray-400 mb-2">Today at 9:42 AM</p>
                                    <div class="flex items-center gap-2 p-2 border border-gray-100 rounded-lg bg-slate-50">
                                        <i class="fa-solid fa-file-pdf text-red-500 text-sm shrink-0"></i>
                                        <span class="text-xs font-medium text-slate-600 truncate">EY_review.pdf</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-4 p-3 hover:bg-slate-50 rounded-xl transition">
                                <img src="https://ui-avatars.com/api/?name=John+Doe&background=random" class="w-11 h-11 rounded-full flex-shrink-0">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 truncate">John Doe commented on your task</p>
                                    <p class="text-[10px] text-gray-400">Today at 10:00 AM</p>
                                </div>
                            </div>
                            <div class="flex gap-4 p-3 hover:bg-slate-50 rounded-xl transition">
                                <img src="https://ui-avatars.com/api/?name=Admin&background=random" class="w-11 h-11 rounded-full flex-shrink-0">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 truncate">Admin requested leave approval</p>
                                    <p class="text-[10px] text-gray-400 mb-3">Today at 10:50 AM</p>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover-card equal-height-card">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white shadow-lg shadow-orange-200 shrink-0">
                                    <i class="fa-regular fa-clock text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-black text-slate-800">8.36 <span class="text-gray-300 text-lg">/ 9</span></p>
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs font-semibold uppercase tracking-wider">Total Hours Today</p>
                            <div class="text-xs text-green-500 font-bold mt-2 flex items-center gap-1">
                                <i class="fa-solid fa-arrow-up"></i> 5% This Week
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover-card equal-height-card">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white shadow-lg shadow-teal-200 shrink-0">
                                    <i class="fa-solid fa-rotate text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-black text-slate-800">10 <span class="text-gray-300 text-lg">/ 40</span></p>
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs font-semibold uppercase tracking-wider">Total Hours Week</p>
                            <div class="text-xs text-green-500 font-bold mt-2 flex items-center gap-1">
                                <i class="fa-solid fa-arrow-up"></i> 7% Last Week
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover-card equal-height-card">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-200 shrink-0">
                                    <i class="fa-regular fa-calendar-check text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-black text-slate-800">75 <span class="text-gray-300 text-lg">/ 98</span></p>
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs font-semibold uppercase tracking-wider">Total Hours Month</p>
                            <div class="text-xs text-red-500 font-bold mt-2 flex items-center gap-1">
                                <i class="fa-solid fa-arrow-down"></i> 8% Last Month
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover-card equal-height-card">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-400 to-pink-600 flex items-center justify-center text-white shadow-lg shadow-pink-200 shrink-0">
                                    <i class="fa-solid fa-briefcase text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-black text-slate-800">16 <span class="text-gray-300 text-lg">/ 28</span></p>
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs font-semibold uppercase tracking-wider">Overtime This Month</p>
                            <div class="text-xs text-red-500 font-bold mt-2 flex items-center gap-1">
                                <i class="fa-solid fa-arrow-down"></i> 6% Last Month
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12">
                    <div class="bg-white rounded-2xl border border-gray-100 p-7 shadow-sm hover-card equal-height-card">
                        <div class="flex flex-col lg:flex-row justify-between mb-6 gap-4">
                            <h3 class="font-bold text-slate-800 text-lg">Work Timeline</h3>
                            <div class="flex flex-wrap gap-6">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-green-500 shrink-0"></div>
                                    <span class="text-xs text-gray-600">Productive Hours</span>
                                    <span class="text-sm font-bold text-slate-800">08h 36m</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-yellow-500 shrink-0"></div>
                                    <span class="text-xs text-gray-600">Break Hours</span>
                                    <span class="text-sm font-bold text-slate-800">22m 15s</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-blue-500 shrink-0"></div>
                                    <span class="text-xs text-gray-600">Overtime</span>
                                    <span class="text-sm font-bold text-slate-800">02h 15m</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-slate-300 shrink-0"></div>
                                    <span class="text-xs text-gray-600">Total</span>
                                    <span class="text-sm font-bold text-slate-800">12h 36m</span>
                                </div>
                            </div>
                        </div>
                        <div class="timeline-bar mb-3">
                            <div class="segment bg-green-500" style="width: 20%;"></div>
                            <div class="segment bg-yellow-500" style="width: 4%;"></div>
                            <div class="segment bg-green-500" style="width: 18%;"></div>
                            <div class="segment bg-yellow-500" style="width: 3%;"></div>
                            <div class="segment bg-green-500" style="width: 22%;"></div>
                            <div class="segment bg-yellow-500" style="width: 5%;"></div>
                            <div class="segment bg-green-500" style="width: 15%;"></div>
                            <div class="segment bg-blue-500" style="width: 8%;"></div>
                            <div class="segment bg-slate-200" style="width: 5%;"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1 overflow-x-auto">
                            <span class="shrink-0">06:00</span><span class="shrink-0">07:00</span><span class="shrink-0">08:00</span><span class="shrink-0">09:00</span><span class="shrink-0">10:00</span><span class="shrink-0">11:00</span><span class="shrink-0">12:00</span><span class="shrink-0">13:00</span><span class="shrink-0">14:00</span><span class="shrink-0">15:00</span><span class="shrink-0">16:00</span><span class="shrink-0">17:00</span><span class="shrink-0">18:00</span><span class="shrink-0">19:00</span><span class="shrink-0">20:00</span><span class="shrink-0">21:00</span><span class="shrink-0">22:00</span>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Projects</h3>
                            <button class="text-xs font-semibold text-gray-500 flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg hover:bg-slate-200 transition shrink-0">
                                Ongoing Projects <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <?php for($i=0; $i<2; $i++): ?>
                            <div class="border border-gray-100 rounded-xl p-5 bg-gradient-to-br from-white to-slate-50 shadow-sm hover:shadow-md transition h-full flex flex-col">
                                <div class="flex justify-between mb-4">
                                    <h4 class="font-bold text-sm text-slate-800">Office Management</h4>
                                    <button class="text-gray-300 hover:text-gray-500 shrink-0"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                </div>
                                <div class="flex items-center gap-3 mb-4">
                                    <img src="https://ui-avatars.com/api/?name=Anthony+Lewis&background=0d9488&color=fff" class="w-10 h-10 rounded-full shadow shrink-0">
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">Anthony Lewis</p>
                                        <p class="text-[10px] text-gray-400">Project Leader</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 mb-4 p-3 bg-slate-50 rounded-lg">
                                    <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center text-orange-500 shrink-0">
                                        <i class="fa-regular fa-calendar"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">14 Jan 2024</p>
                                        <p class="text-[10px] text-gray-400">Deadline</p>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center pt-3 border-t border-gray-100 mt-auto">
                                    <div class="flex items-center gap-2 text-xs text-green-600 font-bold">
                                        <i class="fa-solid fa-clipboard-list"></i> Tasks: 6/10
                                    </div>
                                    <div class="flex -space-x-2">
                                        <img src="https://ui-avatars.com/api/?name=A&background=0d9488&color=fff&size=24" class="w-6 h-6 rounded-full border-2 border-white">
                                        <img src="https://ui-avatars.com/api/?name=B&background=3b82f6&color=fff&size=24" class="w-6 h-6 rounded-full border-2 border-white">
                                        <div class="w-6 h-6 rounded-full bg-orange-500 text-[10px] text-white flex items-center justify-center border-2 border-white font-bold">+2</div>
                                    </div>
                                </div>
                                <div class="mt-4 bg-slate-100 rounded-lg p-3 flex justify-between items-center">
                                    <span class="text-xs font-medium text-gray-500">Time Spent</span>
                                    <span class="text-sm font-bold text-slate-800">65/120 <span class="text-gray-400 font-normal text-xs">Hrs</span></span>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Tasks</h3>
                            <button class="text-xs font-semibold text-gray-500 flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg hover:bg-slate-200 transition shrink-0">
                                All Projects <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </button>
                        </div>
                        <div class="space-y-3 custom-scrollbar overflow-y-auto max-h-[400px] pr-2">
                            <?php 
                            $tasks = [
                                ['title' => 'Patient appointment booking', 'status' => 'Onhold', 'color' => 'pink'],
                                ['title' => 'Appointment booking with payment', 'status' => 'Inprogress', 'color' => 'purple'],
                                ['title' => 'Patient and Doctor video conferencing', 'status' => 'Completed', 'color' => 'green'],
                                ['title' => 'Private chat module', 'status' => 'Pending', 'color' => 'slate'],
                                ['title' => 'Go-Live and Post-Implementation Support', 'status' => 'Inprogress', 'color' => 'purple'],
                            ];
                            foreach($tasks as $task): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-100 rounded-xl hover:bg-slate-50 transition-colors hover:border-teal-200">
                                <div class="flex items-center gap-3 min-w-0">
                                    <i class="fa-solid fa-grip-vertical text-gray-200 text-xs cursor-move shrink-0"></i>
                                    <input type="checkbox" class="w-4 h-4 rounded text-teal-600 border-gray-300 focus:ring-teal-500 shrink-0">
                                    <span class="text-sm font-medium text-slate-800 truncate"><?php echo htmlspecialchars($task['title']); ?></span>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <?php 
                                    $colors = [
                                        'pink' => 'bg-pink-100 text-pink-600',
                                        'purple' => 'bg-purple-100 text-purple-600',
                                        'green' => 'bg-green-100 text-green-600',
                                        'slate' => 'bg-slate-100 text-slate-600'
                                    ];
                                    ?>
                                    <span class="text-[10px] font-bold px-3 py-1 rounded-full <?php echo $colors[$task['color']]; ?> whitespace-nowrap">
                                        ● <?php echo htmlspecialchars($task['status']); ?>
                                    </span>
                                    <div class="flex -space-x-1">
                                        <img src="https://ui-avatars.com/api/?name=X&background=0d9488&color=fff&size=24" class="w-6 h-6 rounded-full border-2 border-white">
                                        <img src="https://ui-avatars.com/api/?name=Y&background=3b82f6&color=fff&size=24" class="w-6 h-6 rounded-full border-2 border-white">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Performance</h3>
                            <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1 shrink-0">
                                <i class="fa-regular fa-calendar"></i> 2026
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mb-6">
                            <span class="text-4xl font-black text-slate-800">98%</span>
                            <span class="text-xs font-bold text-green-600 bg-green-100 px-3 py-1 rounded-full flex items-center gap-1 shrink-0">
                                <i class="fa-solid fa-arrow-up text-[10px]"></i> 12% vs last year
                            </span>
                        </div>
                        <div class="h-56 w-full relative">
                            <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-[10px] text-gray-400 font-bold py-2">
                                <span>60K</span><span>50K</span><span>40K</span><span>30K</span><span>20K</span><span>10K</span>
                            </div>
                            <svg class="w-full h-full pl-10" viewBox="0 0 400 180">
                                <defs>
                                    <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#0d9488" stop-opacity="0.3" />
                                        <stop offset="100%" stop-color="#0d9488" stop-opacity="0.02" />
                                    </linearGradient>
                                </defs>
                                <path d="M0 150 L 50 140 L 100 100 L 150 110 L 200 80 L 250 60 L 300 50 L 350 40 L 400 30 L 400 180 L 0 180 Z" fill="url(#chartGradient)" />
                                <path d="M0 150 L 50 140 L 100 100 L 150 110 L 200 80 L 250 60 L 300 50 L 350 40 L 400 30" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="400" cy="30" r="5" fill="#0d9488" />
                            </svg>
                            <div class="flex justify-between mt-3 text-[10px] font-semibold text-gray-400 pl-10">
                                <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">My Skills</h3>
                            <div class="text-xs font-bold text-gray-500 bg-slate-100 px-3 py-1.5 rounded-lg flex items-center gap-1 shrink-0">
                                <i class="fa-regular fa-calendar"></i> 2026
                            </div>
                        </div>
                        <div class="space-y-4">
                            <?php 
                            $skills = [
                                ['name' => 'Figma', 'date' => '15 May 2025', 'pct' => 95, 'color' => '#f97316'],
                                ['name' => 'HTML', 'date' => '12 May 2025', 'pct' => 85, 'color' => '#22c55e'],
                                ['name' => 'CSS', 'date' => '12 May 2025', 'pct' => 70, 'color' => '#8b5cf6'],
                                ['name' => 'WordPress', 'date' => '15 May 2025', 'pct' => 61, 'color' => '#3b82f6'],
                                ['name' => 'Javascript', 'date' => '13 May 2025', 'pct' => 58, 'color' => '#1e293b']
                            ];
                            foreach($skills as $skill): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-100 rounded-xl hover:border-teal-200 transition">
                                <div class="flex items-center gap-4 min-w-0">
                                    <div class="w-1.5 h-10 rounded-full shrink-0" style="background-color: <?php echo $skill['color']; ?>"></div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($skill['name']); ?></p>
                                        <p class="text-[10px] text-gray-400">Updated: <?php echo htmlspecialchars($skill['date']); ?></p>
                                    </div>
                                </div>
                                <div class="relative w-12 h-12 flex items-center justify-center shrink-0">
                                    <svg class="w-full h-full -rotate-90">
                                        <circle cx="24" cy="24" r="20" fill="none" stroke="#f1f5f9" stroke-width="4" />
                                        <circle cx="24" cy="24" r="20" fill="none" stroke="<?php echo $skill['color']; ?>" stroke-width="4" 
                                            stroke-dasharray="126" stroke-dashoffset="<?php echo 126 - (126 * $skill['pct'] / 100); ?>" stroke-linecap="round" />
                                    </svg>
                                    <span class="absolute text-[10px] font-bold text-slate-800"><?php echo htmlspecialchars($skill['pct']); ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-3 space-y-5">
                    <div class="bg-gradient-to-br from-teal-600 to-teal-700 rounded-2xl p-6 text-center text-white relative overflow-hidden shadow-lg">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                        <p class="text-xs font-bold uppercase mb-4 tracking-wider opacity-80">Team Birthday</p>
                        <img src="https://ui-avatars.com/api/?name=Andrew+Jermia&background=ffedd5&color=9a3412&size=80" class="w-20 h-20 rounded-full mx-auto border-4 border-white/30 shadow-lg mb-4">
                        <h4 class="font-bold text-lg">Andrew Jermia</h4>
                        <p class="text-xs opacity-70 mb-4">IOS Developer</p>
                        <button class="w-full bg-orange-500 hover:bg-orange-600 py-3 rounded-xl text-sm font-bold transition shadow-lg">
                            <i class="fa-solid fa-gift mr-2"></i> Send Wishes
                        </button>
                    </div>
                    
                    <div class="bg-gradient-to-r from-teal-600 to-teal-700 rounded-2xl p-5 flex justify-between items-center text-white shadow-lg">
                        <div class="min-w-0">
                            <p class="text-sm font-bold">Leave Policy</p>
                            <p class="text-[10px] opacity-70 mt-1">Last Updated : Today</p>
                        </div>
                        <button class="bg-white text-teal-700 text-xs font-bold px-4 py-2 rounded-lg shadow hover:shadow-md transition shrink-0">View All</button>
                    </div>

                    <div class="bg-gradient-to-r from-yellow-400 to-amber-500 rounded-2xl p-5 flex justify-between items-center text-slate-800 shadow-lg">
                        <div class="min-w-0">
                            <p class="text-sm font-bold">Next Holiday</p>
                            <p class="text-[10px] font-medium mt-1">Diwali, 15 Sep 2025</p>
                        </div>
                        <button class="bg-white text-slate-800 text-xs font-bold px-4 py-2 rounded-lg shadow hover:shadow-md transition shrink-0">View All</button>
                    </div>
                </div>

                <!-- Meetings Schedule (Moved down since notifications took the space above, keeping it in the flow) -->
                <div class="col-span-12 lg:col-span-8">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 hover-card equal-height-card h-full">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Meetings Schedule</h3>
                            <button class="text-xs font-semibold text-gray-500 flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg shrink-0">
                                <i class="fa-regular fa-calendar"></i> Today
                            </button>
                        </div>
                        <div class="meeting-timeline space-y-5">
                            <div class="flex items-start gap-4 relative">
                                <span class="text-xs font-bold text-gray-500 w-16 text-right shrink-0">09:25 AM</span>
                                <div class="w-3 h-3 rounded-full bg-orange-500 absolute left-[76px] top-1 z-10 border-2 border-white shadow shrink-0"></div>
                                <div class="bg-gradient-to-r from-orange-50 to-amber-50 p-4 rounded-xl flex-1 border border-orange-100 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">Marketing Strategy Presentation</p>
                                    <p class="text-[10px] text-gray-500 mt-1 flex items-center gap-1"><i class="fa-solid fa-briefcase text-orange-500 shrink-0"></i> Marketing</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 relative">
                                <span class="text-xs font-bold text-gray-500 w-16 text-right shrink-0">11:20 AM</span>
                                <div class="w-3 h-3 rounded-full bg-teal-500 absolute left-[76px] top-1 z-10 border-2 border-white shadow shrink-0"></div>
                                <div class="bg-gradient-to-r from-teal-50 to-cyan-50 p-4 rounded-xl flex-1 border border-teal-100 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">Design Review Project</p>
                                    <p class="text-[10px] text-gray-500 mt-1 flex items-center gap-1"><i class="fa-solid fa-eye text-teal-500 shrink-0"></i> Review</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 relative">
                                <span class="text-xs font-bold text-gray-500 w-16 text-right shrink-0">02:18 PM</span>
                                <div class="w-3 h-3 rounded-full bg-yellow-500 absolute left-[76px] top-1 z-10 border-2 border-white shadow shrink-0"></div>
                                <div class="bg-gradient-to-r from-yellow-50 to-amber-50 p-4 rounded-xl flex-1 border border-yellow-100 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">Birthday Celebration</p>
                                    <p class="text-[10px] text-gray-500 mt-1 flex items-center gap-1"><i class="fa-solid fa-cake-candles text-yellow-500 shrink-0"></i> Celebration</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 relative">
                                <span class="text-xs font-bold text-gray-500 w-16 text-right shrink-0">04:10 PM</span>
                                <div class="w-3 h-3 rounded-full bg-green-500 absolute left-[76px] top-1 z-10 border-2 border-white shadow shrink-0"></div>
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl flex-1 border border-green-100 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">Update of Project Flow</p>
                                    <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1"><i class="fa-solid fa-code text-green-500 shrink-0"></i> Development</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="fixed right-0 top-1/2 -translate-y-1/2 bg-teal-600 text-white p-3 rounded-l-xl shadow-xl cursor-pointer hover:bg-teal-700 transition z-50">
                <i class="fa-solid fa-gear text-lg"></i>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var options = {
                series: [
                    <?php echo $stats_ontime; ?>, 
                    <?php echo $stats_late; ?>, 
                    <?php echo $stats_wfh; ?>, 
                    <?php echo $stats_absent; ?>, 
                    <?php echo $stats_sick; ?>
                ],
                chart: {
                    type: 'donut',
                    width: '100%',
                    height: 180,
                    fontFamily: 'Inter, sans-serif'
                },
                labels: ['On Time', 'Late Attendance', 'Work From Home', 'Absent', 'Sick Leave'],
                colors: ['#0d9488', '#22c55e', '#f97316', '#ef4444', '#eab308'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '11px',
                                    fontWeight: 600,
                                    color: '#64748b'
                                },
                                value: {
                                    show: true,
                                    fontSize: '16px',
                                    fontWeight: 700,
                                    color: '#1e293b',
                                    formatter: function(val) {
                                        return val;
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total',
                                    fontSize: '10px',
                                    fontWeight: 600,
                                    color: '#94a3b8',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: false
                },
                stroke: {
                    show: false
                },
                tooltip: {
                    enabled: true,
                    y: {
                        formatter: function(val) {
                            return val + " days";
                        }
                    }
                }
            };

            var chart = new ApexCharts(document.querySelector("#attendanceChart"), options);
            chart.render();
        });
    </script>
</body>
</html>