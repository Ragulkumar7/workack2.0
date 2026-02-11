<?php
// attendance.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. VIEW LOGIC
$view = isset($_GET['view']) ? $_GET['view'] : 'admin_dashboard';
$user_name = $_SESSION['username'] ?? "User";

// Data for Employee View
$employeeName = $user_name;
$currentDateRange = "01/31/2026 - 02/06/2026";
$attendanceRecords = [
    ["date" => "02 Sep 2024", "checkin" => "09:12 AM", "status" => "Present", "checkout" => "09:17 PM", "break" => "14 Min", "late" => "12 Min", "overtime" => "-", "production" => "8.35Hrs", "color" => "green"],
    ["date" => "06 Jul 2024", "checkin" => "09:00 AM", "status" => "Present", "checkout" => "07:13 PM", "break" => "32 Min", "late" => "-", "overtime" => "75 Min", "production" => "9.15 Hrs", "color" => "blue"],
    ["date" => "10 Dec 2024", "checkin" => "-", "status" => "Absent", "checkout" => "-", "break" => "-", "late" => "-", "overtime" => "-", "production" => "0.00 Hrs", "color" => "red"],
    ["date" => "12 Apr 2024", "checkin" => "09:00 AM", "status" => "Present", "checkout" => "06:43 PM", "break" => "23 Min", "late" => "-", "overtime" => "10 Min", "production" => "8.22 Hrs", "color" => "green"],
    ["date" => "14 Jan 2024", "checkin" => "09:32 AM", "status" => "Present", "checkout" => "06:45 PM", "break" => "30 Min", "late" => "32 Min", "overtime" => "20 Min", "production" => "8.55 Hrs", "color" => "green"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - <?php echo ucwords(str_replace('_', ' ', $view)); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-orange: #ff5e3a; --bg-gray: #f8f9fa; --border-color: #edf2f7; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 13px; color: #333; overflow-x: hidden; }
        
        #mainContent { 
            margin-left: 95px; 
            padding: 25px 35px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px; background: #fff; }
        .table thead th { background: #f9fafb; padding: 15px; border-bottom: 1px solid var(--border-color); color: #4a5568; font-weight: 600; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        
        .status-pill { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-present { background: #e6fffa; color: #38a169; }
        .bg-absent { background: #fff5f5; color: #e53e3e; }
        .bg-pending { background: #eef6ff; color: #3182ce; }
        .prod-btn { color: white; border: none; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .btn-orange { background: var(--primary-orange); color: white; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 600; }
        
        .modal-active { display: flex !important; }

        /* Notification Toast Styling */
        #exportToast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            display: none;
            background: #38a169;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-weight: 600;
            animation: fadeInOut 3s ease-in-out forwards;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -20px); }
            15% { opacity: 1; transform: translate(-50%, 0); }
            85% { opacity: 1; transform: translate(-50%, 0); }
            100% { opacity: 0; transform: translate(-50%, -20px); }
        }
    </style>
</head>
<body class="bg-slate-50">

    <div id="exportToast"><i class="fa-solid fa-circle-check mr-2"></i> Report Downloaded</div>

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="reportModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b">
                <h2 class="text-2xl font-bold">Attendance</h2>
                <button onclick="closeModal()" class="bg-slate-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-slate-600 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8 bg-slate-50 p-6 rounded-lg border border-slate-100">
                    <div><p class="text-slate-500 text-sm mb-1">Date</p><p class="font-bold text-lg" id="modalDate">15 Apr 2025</p></div>
                    <div><p class="text-slate-500 text-sm mb-1">Punch in at</p><p class="font-bold text-lg" id="modalPunchIn">09:00 AM</p></div>
                    <div><p class="text-slate-500 text-sm mb-1">Punch out at</p><p class="font-bold text-lg" id="modalPunchOut">06:45 PM</p></div>
                    <div><p class="text-slate-500 text-sm mb-1">Status</p><p class="font-bold text-lg" id="modalStatus">Present</p></div>
                </div>

                <div class="grid grid-cols-4 gap-4 mb-8">
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-slate-200"></span> Total Working hours</p>
                        <p class="text-3xl font-bold text-slate-800">12h 36m</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Productive Hours</p>
                        <p class="text-3xl font-bold text-slate-800">08h 36m</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Break hours</p>
                        <p class="text-3xl font-bold text-slate-800">22m 15s</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Overtime</p>
                        <p class="text-3xl font-bold text-slate-800">02h 15m</p>
                    </div>
                </div>

                <div class="h-10 w-full bg-slate-50 rounded-full flex overflow-hidden mb-4 border border-slate-100 p-1">
                    <div style="width: 15%"></div>
                    <div class="h-full bg-emerald-500 rounded-lg" style="width: 12%"></div>
                    <div class="h-full bg-amber-400 rounded-lg mx-1" style="width: 4%"></div>
                    <div class="h-full bg-emerald-500 rounded-lg" style="width: 20%"></div>
                    <div class="h-full bg-amber-400 rounded-lg mx-1" style="width: 10%"></div>
                    <div class="h-full bg-emerald-500 rounded-lg" style="width: 15%"></div>
                    <div class="h-full bg-amber-400 rounded-lg mx-1" style="width: 4%"></div>
                    <div class="h-full bg-blue-500 rounded-lg" style="width: 3%"></div>
                    <div class="h-full bg-blue-500 rounded-lg ml-1" style="width: 3%"></div>
                </div>

                <div class="flex justify-between text-[11px] text-slate-400 font-medium px-1 uppercase">
                    <span>06:00</span><span>07:00</span><span>08:00</span><span>09:00</span><span>10:00</span><span>11:00</span><span>12:00</span><span>01:00</span><span>02:00</span><span>03:00</span><span>04:00</span><span>05:00</span><span>06:00</span><span>07:00</span><span>08:00</span><span>09:00</span><span>10:00</span><span>11:00</span>
                </div>
            </div>
        </div>
    </div>

    <div id="timesheetModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-slate-800">Add Todays Work</h2>
                <button onclick="closeTimesheetModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <form class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Project <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Deadline <span class="text-red-500">*</span></label>
                    <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Total Hours <span class="text-red-500">*</span></label>
                        <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Remaining Hours <span class="text-red-500">*</span></label>
                        <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Date <span class="text-red-500">*</span></label>
                        <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Hours <span class="text-red-500">*</span></label>
                        <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeTimesheetModal()" class="px-6 py-2.5 rounded-lg border font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg bg-[#ff5e3a] text-white font-semibold hover:bg-orange-600 transition shadow-md">Add Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="swapModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-slate-800">Add Request</h2>
                <button onclick="closeSwapModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <form class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Employee Name <span class="text-red-500">*</span></label>
                    <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Designation <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Current Shift <span class="text-red-500">*</span></label>
                        <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                            <option>Select</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Requested Shift <span class="text-red-500">*</span></label>
                        <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                            <option>Select</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeSwapModal()" class="px-6 py-2.5 rounded-lg border font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg bg-[#ff5e3a] text-white font-semibold hover:bg-orange-600 transition shadow-md">Add Request</button>
                </div>
            </form>
        </div>
    </div>

    <div id="overtimeModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-slate-800">Add Overtime</h2>
                <button onclick="closeOvertimeModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <form class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Employee <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Overtime date <span class="text-red-500">*</span></label>
                    <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Overtime <span class="text-red-500">*</span></label>
                        <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Remaining Hours <span class="text-red-500">*</span></label>
                        <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Description</label>
                    <textarea class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500 min-h-[100px]"></textarea>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-2">Status <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeOvertimeModal()" class="px-6 py-2.5 rounded-lg border font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg bg-[#ff5e3a] text-white font-semibold hover:bg-orange-600 transition shadow-md">Add Overtime</button>
                </div>
            </form>
        </div>
    </div>

    <div id="wfhModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-slate-800">Add Request</h2>
                <button onclick="closeWFHModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <form class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Employee Name <span class="text-red-500">*</span></label>
                    <input type="text" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Designation <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Shift <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Start Date <span class="text-red-500">*</span></label>
                        <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">End Date <span class="text-red-500">*</span></label>
                        <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                    </div>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-2">Reviewer <span class="text-red-500">*</span></label>
                    <textarea class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500 min-h-[80px]"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeWFHModal()" class="px-6 py-2.5 rounded-lg border font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg bg-[#ff5e3a] text-white font-semibold hover:bg-orange-600 transition shadow-md">Add Request</button>
                </div>
            </form>
        </div>
    </div>

    <main id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h4 class="fw-bold mb-0 text-dark"><?php echo ucwords(str_replace('_', ' ', $view)); ?></h4></div>
            <div class="d-flex gap-2">
                <button class="btn btn-light border btn-sm" onclick="triggerExport()"><i class="fa-solid fa-download"></i> Export</button>
                
                <?php if ($view !== 'schedule_timing'): ?>
                <button class="btn btn-orange btn-sm shadow-sm" onclick="<?php echo ($view == 'attendance_employee' || $view == 'attendance_admin') ? "openModal({name: '$user_name', date: '15 Apr 2025', in: '09:00 AM', out: '06:45 PM', status: 'Present'})" : "handleGlobalAdd('$view')"; ?>">
                    <?php 
                        if($view == 'attendance_employee' || $view == 'attendance_admin') echo "<i class='fa-regular fa-file-lines'></i> Report";
                        elseif($view == 'timesheets') echo "+ Add Today's Work";
                        elseif($view == 'overtime') echo "+ Add Overtime";
                        elseif($view == 'shift_swap') echo "+ Add New Request";
                        elseif($view == 'wfh') echo "+ Add New Request";
                        else echo "Report";
                    ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($view == 'admin_dashboard'): ?>
            <div class="card p-4 mb-4">
                <div class="d-flex align-items-center">
                    <img src="https://i.pravatar.cc/60?img=12" class="rounded-circle me-3 border border-3 border-light">
                    <div>
                        <h5 class="fw-bold mb-0">Welcome Back, <?php echo $user_name; ?> <i class="fa-solid fa-circle-check text-primary small"></i></h5>
                        <p class="text-muted small mb-0">You have <span class="text-danger">21 Pending Approvals</span> & <span class="text-danger">14 Leave Requests</span></p>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <?php 
                $dashStats = [
                    ['t' => 'Attendance Overview', 'v' => '120/154'],
                    ['t' => 'Total No of Projects', 'v' => '90/125'],
                    ['t' => 'Total No of Clients', 'v' => '69/86'],
                    ['t' => 'Earnings', 'v' => '$21,445']
                ];
                foreach($dashStats as $ds): ?>
                <div class="col-md-3"><div class="card p-3"><h6><?php echo $ds['t']; ?></h6><h3 class="fw-bold"><?php echo $ds['v']; ?></h3><a href="#" class="small text-muted text-decoration-none">View Details</a></div></div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view == 'attendance_admin'): ?>
            <div class="card mb-4 text-center">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="text-start"><h6 class="fw-bold mb-0">Attendance Details Today</h6><small>Data from 800+ total employees</small></div>
                    <div class="small fw-bold">Total Absenties today <img src="https://i.pravatar.cc/25?img=1" class="rounded-circle ms-1"><span class="badge bg-orange rounded-circle">+1</span></div>
                </div>
                <div class="row g-0">
                    <div class="col border-end p-3">Present<h4>250</h4><span class="badge bg-success-subtle text-success">+1%</span></div>
                    <div class="col border-end p-3">Late Login<h4>45</h4><span class="badge bg-danger-subtle text-danger">-1%</span></div>
                    <div class="col border-end p-3">Uninformed<h4>15</h4><span class="badge bg-danger-subtle text-danger">-12%</span></div>
                    <div class="col border-end p-3">Permission<h4>03</h4><span class="badge bg-success-subtle text-success">+1%</span></div>
                    <div class="col p-3">Absent<h4>12</h4><span class="badge bg-danger-subtle text-danger">-19%</span></div>
                </div>
            </div>
            <div class="card p-0 overflow-hidden">
                <table class="table mb-0">
                    <thead><tr><th>Employee</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Break</th><th>Late</th><th>Production Hours</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+10; ?>" class="avatar-img"><strong>User <?php echo $i; ?></strong><br><small>Developer</small></td>
                            <td><span class="status-pill bg-present">● Present</span></td>
                            <td>09:00 AM</td><td>06:<?php echo rand(10,59); ?> PM</td><td>20 Min</td><td>1<?php echo $i; ?> Min</td>
                            <td><span class="prod-btn bg-success">8.55 Hrs</span></td>
                            <td><button class="btn btn-sm text-primary" onclick="openModal({name: 'User <?php echo $i; ?>', date: '06 Feb 2026', in: '09:00 AM', out: '06:<?php echo rand(10,59); ?> PM', status: 'Present'})"><i class="fa-solid fa-file-lines"></i> Report</button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'attendance_employee'): ?>
            <div class="grid grid-cols-12 gap-6 mb-8">
                <div class="col-span-12 lg:col-span-3 card p-6 text-center">
                    <p class="text-slate-500 text-sm">Good Morning, <?php echo $employeeName; ?></p>
                    <h2 class="text-xl font-bold mt-1 mb-4"><?php echo date('H:i A, d M Y'); ?></h2>
                    <div class="relative inline-block mb-6">
                        <div class="w-32 h-32 rounded-full border-[6px] border-emerald-500 p-1 mx-auto">
                            <img src="https://i.pravatar.cc/150?u=adrian" alt="Profile" class="rounded-full w-full h-full object-cover">
                        </div>
                    </div>
                    <div id="statusTag" class="bg-orange-500 text-white py-2 px-4 rounded-md mb-4 text-sm font-medium">Production : 3.45 hrs</div>
                    <p class="text-slate-600 text-sm mb-6 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-fingerprint text-orange-500"></i> <span id="punchText">Punch In at 10.00 AM</span>
                    </p>
                    <button id="punchBtn" onclick="togglePunch()" class="w-full bg-[#111827] text-white py-3 rounded-md font-bold transition-all">Punch Out</button>
                </div>

                <div class="col-span-12 lg:col-span-9 flex flex-col gap-6">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="card p-5">
                            <div class="bg-orange-100 text-orange-600 w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-regular fa-clock"></i></div>
                            <div class="text-2xl font-bold">8.36 <span class="text-slate-400 font-normal">/ 9</span></div>
                            <p class="text-slate-400 text-xs mt-1">Total Hours Today</p>
                            <div class="mt-4 text-emerald-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-up text-[10px]"></i> 5% This Week</div>
                        </div>
                        <div class="card p-5">
                            <div class="bg-slate-900 text-white w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-solid fa-stopwatch"></i></div>
                            <div class="text-2xl font-bold">10 <span class="text-slate-400 font-normal">/ 40</span></div>
                            <p class="text-slate-400 text-xs mt-1">Total Hours Week</p>
                            <div class="mt-4 text-emerald-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-up text-[10px]"></i> 7% Last Week</div>
                        </div>
                        <div class="card p-5">
                            <div class="bg-blue-100 text-blue-600 w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-regular fa-calendar-check"></i></div>
                            <div class="text-2xl font-bold">75 <span class="text-slate-400 font-normal">/ 98</span></div>
                            <p class="text-slate-400 text-xs mt-1">Total Hours Month</p>
                            <div class="mt-4 text-red-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-down text-[10px]"></i> 8% Last Month</div>
                        </div>
                        <div class="card p-5 relative overflow-hidden">
                            <div class="bg-pink-100 text-pink-600 w-8 h-8 flex items-center justify-center rounded mb-3"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div class="text-2xl font-bold">16 <span class="text-slate-400 font-normal">/ 28</span></div>
                            <p class="text-slate-400 text-xs mt-1">Overtime this...</p>
                            <div class="mt-4 text-red-500 text-xs font-bold flex items-center gap-1"><i class="fa fa-arrow-down text-[10px]"></i> 6% Last Month</div>
                        </div>
                    </div>
                    <div class="card p-6">
                        <div class="grid grid-cols-4 gap-4 mb-6 text-center lg:text-left">
                            <div><p class="text-[11px] text-slate-400">Total Working hours</p><h3 class="text-2xl font-bold">12h 36m</h3></div>
                            <div><p class="text-[11px] text-slate-400">Productive Hours</p><h3 class="text-2xl font-bold">08h 36m</h3></div>
                            <div><p class="text-[11px] text-slate-400">Break hours</p><h3 class="text-2xl font-bold">22m 15s</h3></div>
                            <div><p class="text-[11px] text-slate-400">Overtime</p><h3 class="text-2xl font-bold">02h 15m</h3></div>
                        </div>
                        <div class="h-10 w-full bg-slate-50 rounded-full flex overflow-hidden mb-4 border border-slate-100">
                            <div style="width: 12%"></div><div class="h-full bg-emerald-500" style="width: 10%"></div><div class="h-full bg-amber-400" style="width: 3%"></div>
                            <div class="h-full bg-emerald-500" style="width: 20%"></div><div class="h-full bg-amber-400" style="width: 10%"></div>
                            <div class="h-full bg-emerald-500" style="width: 12%"></div><div class="h-full bg-amber-400" style="width: 3%"></div>
                            <div class="h-full bg-blue-500" style="width: 4%"></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-slate-400 font-medium px-1 uppercase">
                            <span>06:00</span><span>08:00</span><span>10:00</span><span>12:00</span><span>02:00</span><span>04:00</span><span>06:00</span><span>08:00</span><span>10:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="p-4 border-b bg-white flex justify-between items-center">
                    <h3 class="text-lg font-bold">Recent History</h3>
                    <div class="border rounded px-3 py-2 text-sm flex items-center gap-2 text-slate-600">
                        <i class="fa-regular fa-calendar-days text-orange-500"></i><span class="font-medium"><?php echo $currentDateRange; ?></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="attendanceTable">
                        <thead class="bg-slate-50 text-slate-600 font-semibold border-b">
                            <tr><th class="p-4">Date</th><th class="p-4">Check In</th><th class="p-4">Status</th><th class="p-4">Check Out</th><th class="p-4">Break</th><th class="p-4">Late</th><th class="p-4">Overtime</th><th class="p-4">Production</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($attendanceRecords as $row): ?>
                            <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="openModal({name: '<?php echo $employeeName; ?>', date: '<?php echo $row['date']; ?>', in: '<?php echo $row['checkin']; ?>', out: '<?php echo $row['checkout']; ?>', status: '<?php echo $row['status']; ?>'})">
                                <td class="p-4 text-slate-500"><?php echo $row['date']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['checkin']; ?></td>
                                <td class="p-4"><span class="<?php echo ($row['status'] == 'Present') ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?> px-3 py-1 rounded-full text-xs font-bold"><?php echo $row['status']; ?></span></td>
                                <td class="p-4 text-slate-500"><?php echo $row['checkout']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['break']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['late']; ?></td>
                                <td class="p-4 text-slate-500"><?php echo $row['overtime']; ?></td>
                                <td class="p-4"><span class="bg-emerald-500 text-white px-3 py-1.5 rounded text-xs font-bold"><i class="fa fa-clock mr-1"></i><?php echo $row['production']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($view == 'schedule_timing'): ?>
            <div class="card p-0 overflow-hidden">
                <div class="p-3 border-bottom fw-bold">Schedule Timing List</div>
                <table class="table mb-0">
                    <thead><tr><th>Name</th><th>Job Title</th><th>User Available Timings</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i; ?>" class="avatar-img"> Staff Member <?php echo $i; ?></td>
                            <td>Accountant</td><td class="small">11-03-2026 - 11:00 AM-12:00 PM<br>12-03-2026 - 10:00 AM-11:00 AM</td>
                            <td><button class="btn btn-dark btn-sm" onclick="validateAction('Schedule')">Schedule Timing</button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'shift_swap'): ?>
            <div class="card p-0 overflow-hidden">
                <table class="table mb-0">
                    <thead><tr><th>Emp ID</th><th>Name</th><th>Designation</th><th>Current Shift</th><th>Requested Shift</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td>Emp-00<?php echo $i; ?></td>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+5; ?>" class="avatar-img"> User Name <?php echo $i; ?></td>
                            <td>Designer</td><td>Regular</td><td>Night</td>
                            <td><span class="status-pill <?php echo ($i%2==0)?'bg-present':'bg-pending'; ?>"><?php echo ($i%2==0)?'Approved':'Pending'; ?></span></td>
                            <td><button class="btn btn-sm" onclick="validateAction('Process Request')"><i class="fa-solid fa-check-circle"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'overtime'): ?>
            <div class="row g-3 mb-4 text-center">
                <div class="col-md-3"><div class="card p-3">Overtime Employee<h4 class="fw-bold">12</h4></div></div>
                <div class="col-md-3"><div class="card p-3">Overtime Hours<h4 class="fw-bold">118</h4></div></div>
                <div class="col-md-3"><div class="card p-3">Pending Request<h4 class="fw-bold">23</h4></div></div>
                <div class="col-md-3"><div class="card p-3">Rejected<h4 class="fw-bold">5</h4></div></div>
            </div>
            <div class="card p-0 overflow-hidden">
                <table class="table mb-0">
                    <thead><tr><th>Employee</th><th>Date</th><th>Overtime Hours</th><th>Project</th><th>Approved By</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+15; ?>" class="avatar-img"> Employee <?php echo $i; ?></td>
                            <td>14 Jan 2026</td><td><?php echo rand(10,50); ?></td><td>Project <?php echo $i; ?></td>
                            <td>Manager X</td><td><span class="status-pill bg-present">Accepted</span></td>
                            <td><button class="btn btn-sm text-danger" onclick="validateAction('Delete')"><i class="fa-solid fa-trash"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'wfh'): ?>
            <div class="card p-0 overflow-hidden">
                <div class="p-3 border-bottom fw-bold">WFH Request List</div>
                <table class="table mb-0">
                    <thead><tr><th>Emp ID</th><th>Name</th><th>Designation</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td>Emp-0<?php echo $i; ?></td>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+20; ?>" class="avatar-img"> Worker <?php echo $i; ?></td>
                            <td>Support</td><td>Health Issue</td><td><span class="status-pill bg-present">Approved</span></td>
                            <td><button class="btn btn-sm text-primary" onclick="validateAction('View Details')"><i class="fa-solid fa-eye"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'timesheets'): ?>
            <div class="card p-0 overflow-hidden">
                <table class="table mb-0">
                    <thead><tr><th>Employee</th><th>Date</th><th>Project</th><th>Assigned Hours</th><th>Worked Hours</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i; ?>" class="avatar-img"> User <?php echo $i; ?></td>
                            <td>14 Jan 2026</td><td>Project Beta</td><td>40</td><td><?php echo rand(10,40); ?></td>
                            <td><button class="btn btn-sm" onclick="validateAction('Edit Entry')"><i class="fa-solid fa-edit"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const modal = document.getElementById('reportModal');
        const tsModal = document.getElementById('timesheetModal');
        const swapModal = document.getElementById('swapModal');
        const overtimeModal = document.getElementById('overtimeModal');
        const wfhModal = document.getElementById('wfhModal');
        const toast = document.getElementById('exportToast');
        let isPunchedOut = false;

        function triggerExport() {
            // 1. Show message at the top
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);

            // 2. CSV Generation logic
            let csv = [];
            // CSV Header
            csv.push("Date,Check In,Status,Check Out,Break,Late,Overtime,Production");
            
            // Add existing static PHP data to CSV
            const records = <?php echo json_encode($attendanceRecords); ?>;
            records.forEach(row => {
                csv.push(`${row.date},${row.checkin},${row.status},${row.checkout},${row.break},${row.late},${row.overtime},${row.production}`);
            });

            // 3. Download the file as .csv (opens in Excel)
            const csvString = csv.join("\n");
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "Attendance_Report.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function openModal(data) { 
            if(data) {
                document.getElementById('modalDate').innerText = data.date;
                document.getElementById('modalPunchIn').innerText = data.in || '-';
                document.getElementById('modalPunchOut').innerText = data.out || '-';
                document.getElementById('modalStatus').innerText = data.status;
            }
            modal.classList.add('modal-active'); 
            document.body.style.overflow = 'hidden'; 
        }

        function closeModal() { 
            modal.classList.remove('modal-active'); 
            document.body.style.overflow = 'auto'; 
        }

        function openTimesheetModal() { tsModal.classList.add('modal-active'); document.body.style.overflow = 'hidden'; }
        function closeTimesheetModal() { tsModal.classList.remove('modal-active'); document.body.style.overflow = 'auto'; }
        function openSwapModal() { swapModal.classList.add('modal-active'); document.body.style.overflow = 'hidden'; }
        function closeSwapModal() { swapModal.classList.remove('modal-active'); document.body.style.overflow = 'auto'; }
        function openOvertimeModal() { overtimeModal.classList.add('modal-active'); document.body.style.overflow = 'hidden'; }
        function closeOvertimeModal() { overtimeModal.classList.remove('modal-active'); document.body.style.overflow = 'auto'; }
        function openWFHModal() { wfhModal.classList.add('modal-active'); document.body.style.overflow = 'hidden'; }
        function closeWFHModal() { wfhModal.classList.remove('modal-active'); document.body.style.overflow = 'auto'; }

        function togglePunch() {
            const btn = document.getElementById('punchBtn');
            const statusTag = document.getElementById('statusTag');
            const punchText = document.getElementById('punchText');
            
            if (!isPunchedOut) {
                if(confirm("Confirm Punch Out?")) {
                    btn.innerText = "Punch In";
                    btn.classList.replace('bg-[#111827]', 'bg-emerald-600');
                    statusTag.innerText = "Shift Ended";
                    statusTag.classList.replace('bg-orange-500', 'bg-slate-400');
                    punchText.innerText = "Punch Out at 06:45 PM";
                    isPunchedOut = true;
                    alert("Punched Out Successfully.");
                }
            } else {
                if(confirm("Confirm Punch In?")) {
                    btn.innerText = "Punch Out";
                    btn.classList.replace('bg-emerald-600', 'bg-[#111827]');
                    statusTag.innerText = "Production : 3.45 hrs";
                    statusTag.classList.replace('bg-slate-400', 'bg-orange-500');
                    punchText.innerText = "Punch In at 10.00 AM";
                    isPunchedOut = false;
                    alert("Punched In Successfully.");
                }
            }
        }

        function validateAction(type) {
            if(confirm(`Are you sure you want to ${type} this entry?`)) {
                alert(`${type} successful!`);
            }
        }

        function handleGlobalAdd(view) {
            if(view === 'timesheets') {
                openTimesheetModal();
            } else if (view === 'shift_swap') {
                openSwapModal();
            } else if (view === 'overtime') {
                openOvertimeModal();
            } else if (view === 'wfh') {
                openWFHModal();
            } else {
                let name = view.replace('_', ' ');
                let input = prompt(`Enter details for new ${name}:`);
                if(input) { alert("Entry added successfully to " + name); }
            }
        }

        window.onclick = (e) => { 
            if (e.target == modal) closeModal(); 
            if (e.target == tsModal) closeTimesheetModal(); 
            if (e.target == swapModal) closeSwapModal();
            if (e.target == overtimeModal) closeOvertimeModal();
            if (e.target == wfhModal) closeWFHModal();
        }
    </script>
</body>
</html>