<?php
// employee_attendance_details.php - Management View of Employee Attendance

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. DATA CONTEXT (Simulating selected employee data)
$employeeName = "Adrian De Silva";
$employeeID = "EMP-0452";
$designation = "Senior Web Developer";
$currentDateRange = date('d M Y') . " - " . date('d M Y', strtotime('+7 days'));

$attendanceRecords = [
    ["date" => "06 Feb 2026", "checkin" => "09:12 AM", "status" => "Present", "checkout" => "09:17 PM", "break" => "14 Min", "late" => "12 Min", "overtime" => "2.5 Hrs", "production" => "8.35 Hrs", "color" => "green"],
    ["date" => "05 Feb 2026", "checkin" => "09:00 AM", "status" => "Present", "checkout" => "07:13 PM", "break" => "32 Min", "late" => "-", "overtime" => "75 Min", "production" => "9.15 Hrs", "color" => "blue"],
    ["date" => "04 Feb 2026", "checkin" => "-", "status" => "Absent", "checkout" => "-", "break" => "-", "late" => "-", "overtime" => "-", "production" => "0.00 Hrs", "color" => "red"],
    ["date" => "03 Feb 2026", "checkin" => "09:00 AM", "status" => "Present", "checkout" => "06:43 PM", "break" => "23 Min", "late" => "-", "overtime" => "10 Min", "production" => "8.22 Hrs", "color" => "green"],
    ["date" => "02 Feb 2026", "checkin" => "09:32 AM", "status" => "Present", "checkout" => "06:45 PM", "break" => "30 Min", "late" => "32 Min", "overtime" => "20 Min", "production" => "8.55 Hrs", "color" => "green"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - <?php echo $employeeName; ?></title>
    
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
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            #mainContent { margin-left: 0 !important; padding: 15px; }
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-present { background: #dcfce7; color: #166534; }
        .bg-absent { background: #fee2e2; color: #991b1b; }
        .modal-backdrop-custom { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
        
        /* Break Animation */
        @keyframes pulse-orange {
            0% { box-shadow: 0 0 0 0 rgba(255, 165, 0, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 165, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 165, 0, 0); }
        }
        .break-active { animation: pulse-orange 2s infinite; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
        <?php include 'header.php'; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"> Attendance Details</h1>
                <nav class="flex text-slate-500 text-xs mt-1 gap-2">
                    <a href="#" class="hover:text-orange-500">Attendance</a>
                    <span>/</span>
                    <a href="#" class="hover:text-orange-500">Admin Panel</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold"><?php echo $employeeName; ?></span>
                </nav>
            </div>
            
        </div>

        <div class="grid grid-cols-12 gap-6 mb-8">
            <div class="col-span-12 lg:col-span-3 card p-6 text-center shadow-md h-fit">
                <div class="flex justify-end mb-2">
                    <span id="systemStatus" class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-1 rounded">NOT LOGGED IN</span>
                </div>
                <div class="w-24 h-24 rounded-full border-4 border-orange-500 p-1 mx-auto mb-4 relative">
                    <img src="https://i.pravatar.cc/150?u=adrian" class="rounded-full w-full h-full object-cover">
                    <div id="activeIndicator" class="absolute bottom-1 right-1 w-5 h-5 bg-slate-300 border-2 border-white rounded-full"></div>
                </div>
                <h2 class="text-lg font-bold text-slate-800"><?php echo $employeeName; ?></h2>
                <p class="text-slate-500 text-xs mb-6"><?php echo $designation; ?> (<?php echo $employeeID; ?>)</p>
                
                <div id="statusTag" class="bg-slate-100 text-slate-500 py-2 px-4 rounded-md mb-4 text-sm font-medium transition-all shadow-sm">
                    Status: Not Started
                </div>
                
                


                <div class="bg-slate-50 rounded-xl p-3 mt-6 border border-slate-100">
                    <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Session Production</p>
                    <p class="text-xl font-bold text-slate-800" id="liveTimer">00h 00m 00s</p>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-9">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                    <div class="card p-4 border-l-4 border-orange-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Avg. Production</p>
                        <h3 class="text-2xl font-bold">8.5 <small class="text-slate-400 font-normal">Hrs</small></h3>
                        <p class="text-emerald-500 text-[10px] font-bold mt-2"><i class="fa fa-arrow-up"></i> 12% Above Avg</p>
                    </div>
                    <div class="card p-4 border-l-4 border-blue-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Late Logins</p>
                        <h3 class="text-2xl font-bold">02 <small class="text-slate-400 font-normal">Days</small></h3>
                        <p class="text-red-500 text-[10px] font-bold mt-2">This Month</p>
                    </div>
                    <div class="card p-4 border-l-4 border-emerald-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Total Attendance</p>
                        <h3 class="text-2xl font-bold">98%</h3>
                        <p class="text-slate-400 text-[10px] font-bold mt-2">Annual Consistency</p>
                    </div>
                    <div class="card p-4 border-l-4 border-purple-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Overtime</p>
                        <h3 class="text-2xl font-bold">14.2 <small class="text-slate-400 font-normal">Hrs</small></h3>
                        <p class="text-blue-500 text-[10px] font-bold mt-2">Approved</p>
                    </div>
                </div>

                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="font-bold text-slate-700">Weekly Performance</h4>
                        <span class="text-xs text-slate-400 italic">Target: 9 Hrs / Day</span>
                    </div>
                    <div class="h-12 w-full bg-slate-100 rounded-2xl flex overflow-hidden p-1.5 border border-slate-200">
                        <div class="h-full bg-emerald-500 rounded-xl shadow-sm" style="width: 70%" title="Production"></div>
                        <div class="h-full bg-amber-400 rounded-xl mx-1 shadow-sm" style="width: 15%" title="Breaks"></div>
                        <div class="h-full bg-blue-500 rounded-xl shadow-sm" style="width: 10%" title="Overtime"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400 font-bold px-2 mt-2 tracking-widest uppercase">
                        <span>Working</span><span>Break</span><span>Overtime</span>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="p-4 border-b flex flex-col md:flex-row justify-between items-center gap-4">
                        <h3 class="text-lg font-bold text-slate-800">Attendance History Log</h3>
                        <div class="flex items-center gap-2">
                            <input type="text" class="pl-4 pr-4 py-2 border border-slate-200 rounded-lg text-xs font-semibold bg-slate-50" value="<?php echo $currentDateRange; ?>" readonly>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Status</th>
                                    <th>Production</th>
                                    <th>Late</th>
                                    <th>Overtime</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($attendanceRecords as $row): ?>
                                <tr>
                                    <td class="font-medium text-slate-700"><?php echo $row['date']; ?></td>
                                    <td><?php echo $row['checkin']; ?></td>
                                    <td><?php echo $row['checkout']; ?></td>
                                    <td>
                                        <span class="status-pill <?php echo ($row['status'] == 'Present') ? 'bg-present' : 'bg-absent'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><span class="font-bold text-slate-800"><?php echo $row['production']; ?></span></td>
                                    <td class="<?php echo $row['late'] != '-' ? 'text-red-500 font-semibold' : 'text-slate-400'; ?>"><?php echo $row['late']; ?></td>
                                    <td class="<?php echo $row['overtime'] != '-' ? 'text-blue-600 font-semibold' : 'text-slate-400'; ?>"><?php echo $row['overtime']; ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary transition hover:bg-blue-600 hover:text-white" onclick="openReportModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fa fa-chart-line"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="reportDetailModal" class="fixed inset-0 modal-backdrop-custom z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden">
            <div class="p-6 border-b flex justify-between items-center bg-slate-900 text-white">
                <h2 class="text-xl font-bold">Daily Breakdown Details</h2>
                <button onclick="closeReportModal()" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-8">
                <div class="grid grid-cols-4 gap-4 mb-8 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Date</p><p class="font-bold" id="detDate"></p></div>
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Punch In</p><p class="font-bold" id="detIn"></p></div>
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Punch Out</p><p class="font-bold" id="detOut"></p></div>
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Status</p><p class="font-bold" id="detStatus"></p></div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button class="btn btn-dark btn-sm px-4 rounded-lg shadow-sm" onclick="closeReportModal()">Close View</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // --- GLOBAL VARIABLES ---
        let workSeconds = 0; // Total seconds worked
        let timerInterval = null;
        let isPunchedIn = false;
        let isOnBreak = false;
        const reportModal = document.getElementById('reportDetailModal');

        // --- TIMER FORMATTER (HHh MMm SSs) ---
        function formatTime(totalSeconds) {
            const h = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
            const m = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
            const s = (totalSeconds % 60).toString().padStart(2, '0');
            return `${h}h ${m}m ${s}s`;
        }

        function startTimer() {
            timerInterval = setInterval(() => {
                if (!isOnBreak) {
                    workSeconds++;
                    document.getElementById('liveTimer').innerText = formatTime(workSeconds);
                }
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
        }

        // --- PUNCH IN / OUT LOGIC ---
        function togglePunch() {
            const btn = document.getElementById('punchBtn');
            const breakBtn = document.getElementById('breakBtn');
            const statusTag = document.getElementById('statusTag');
            const punchText = document.getElementById('punchText');
            const activeInd = document.getElementById('activeIndicator');
            const sysStatus = document.getElementById('systemStatus');

            if (!isPunchedIn) {
                // PUNCH IN ACTION
                if(confirm("Confirm Punch In for today?")) {
                    isPunchedIn = true;
                    
                    // UI Updates
                    btn.innerText = "Punch Out";
                    btn.classList.replace('bg-[#111827]', 'bg-red-600');
                    btn.classList.add('hover:bg-red-700');
                    
                    statusTag.innerText = "Status: Working 👨‍💻";
                    statusTag.className = "bg-emerald-100 text-emerald-700 py-2 px-4 rounded-md mb-4 text-sm font-bold shadow-sm border border-emerald-200";
                    
                    punchText.innerText = "Punched In at " + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    // Show Break Button
                    breakBtn.classList.remove('hidden');
                    
                    // Indicators
                    activeInd.classList.replace('bg-slate-300', 'bg-emerald-500');
                    sysStatus.innerText = "LOGGED IN";
                    sysStatus.className = "bg-emerald-100 text-emerald-600 text-[10px] font-bold px-2 py-1 rounded";

                    startTimer();
                }
            } else {
                // PUNCH OUT ACTION
                const hoursWorked = workSeconds / 3600;
                
                // 9-HOUR RULE CHECK
                if (hoursWorked < 9) {
                    let confirmLogout = confirm(
                        "⚠️ WARNING: You have NOT completed 9 hours of production.\n\n" +
                        "Early logout will result in LOSS OF PAY.\n\n" +
                        "Are you sure you want to punch out?"
                    );
                    if (!confirmLogout) return; // Cancel logout
                } else {
                    if(!confirm("Confirm Punch Out?")) return;
                }

                // If confirmed (or > 9 hours)
                isPunchedIn = false;
                stopTimer();

                // UI Reset
                btn.innerText = "Shift Completed";
                btn.classList.replace('bg-red-600', 'bg-slate-800');
                btn.disabled = true; // Disable after shift end
                
                statusTag.innerText = "Shift Ended";
                statusTag.className = "bg-slate-200 text-slate-500 py-2 px-4 rounded-md mb-4 text-sm font-bold shadow-sm";
                
                breakBtn.classList.add('hidden'); // Hide break button
                
                activeInd.classList.replace('bg-emerald-500', 'bg-slate-300');
                sysStatus.innerText = "SHIFT ENDED";
                sysStatus.className = "bg-slate-200 text-slate-500 text-[10px] font-bold px-2 py-1 rounded";
                
                alert("Shift Logged Out Successfully. Total Production: " + formatTime(workSeconds));
            }
        }

        // --- BREAK LOGIC ---
        function toggleBreak() {
            const breakBtn = document.getElementById('breakBtn');
            const statusTag = document.getElementById('statusTag');
            const activeInd = document.getElementById('activeIndicator');

            if (!isOnBreak) {
                // START BREAK
                if(confirm("Start your break? Production timer will pause.")) {
                    isOnBreak = true;
                    
                    breakBtn.innerText = "End Break ▶️";
                    breakBtn.classList.replace('bg-amber-500', 'bg-slate-700');
                    breakBtn.classList.add('break-active'); // Animation
                    
                    statusTag.innerText = "Status: On Break ☕";
                    statusTag.className = "bg-amber-100 text-amber-700 py-2 px-4 rounded-md mb-4 text-sm font-bold shadow-sm border border-amber-200";
                    
                    activeInd.classList.replace('bg-emerald-500', 'bg-amber-400');
                    
                    // Disable Punch Out during break (optional logic)
                    document.getElementById('punchBtn').disabled = true;
                    document.getElementById('punchBtn').classList.add('opacity-50');
                }
            } else {
                // END BREAK
                isOnBreak = false;
                
                breakBtn.innerText = "Take a Break ☕";
                breakBtn.classList.replace('bg-slate-700', 'bg-amber-500');
                breakBtn.classList.remove('break-active');
                
                statusTag.innerText = "Status: Working 👨‍💻";
                statusTag.className = "bg-emerald-100 text-emerald-700 py-2 px-4 rounded-md mb-4 text-sm font-bold shadow-sm border border-emerald-200";
                
                activeInd.classList.replace('bg-amber-400', 'bg-emerald-500');
                
                // Re-enable Punch Out
                document.getElementById('punchBtn').disabled = false;
                document.getElementById('punchBtn').classList.remove('opacity-50');
            }
        }

        // --- EXPORT & MODAL UTILS ---
        function openReportModal(data) {
            document.getElementById('detDate').innerText = data.date;
            document.getElementById('detIn').innerText = data.checkin;
            document.getElementById('detOut').innerText = data.checkout;
            document.getElementById('detStatus').innerText = data.status;
            
            reportModal.classList.remove('hidden');
            reportModal.classList.add('flex');
        }

        function closeReportModal() {
            reportModal.classList.add('hidden');
            reportModal.classList.remove('flex');
        }

        window.onclick = function(event) {
            if (event.target == reportModal) closeReportModal();
        }
    </script>
</body>
</html>