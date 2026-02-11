<?php
// employee_attendance_details.php - Management View of Employee Attendance

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. DATA CONTEXT (Simulating selected employee data)
// In a real application, you would retrieve this via $_GET['emp_id'] from your database
$employeeName = "Adrian De Silva";
$employeeID = "EMP-0452";
$designation = "Senior Web Developer";
$currentDateRange = "01 Feb 2026 - 07 Feb 2026";

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
        
        /* Layout Handling based on sidebars.php */
        #mainContent { 
            margin-left: 95px; /* Matches --primary-sidebar-width in sidebars.php */
            padding: 25px 35px; 
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            #mainContent { margin-left: 0 !important; padding: 15px; }
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
        
        /* Custom UI Components */
        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-present { background: #dcfce7; color: #166534; }
        .bg-absent { background: #fee2e2; color: #991b1b; }
        
        .btn-orange { background: var(--primary-orange); color: white; border: none; font-weight: 600; transition: 0.3s; }
        .btn-orange:hover { background: #e54d2e; color: white; transform: translateY(-1px); }

        /* Modal Overlay Fix */
        .modal-backdrop-custom { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
            <?php include 'header.php'; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Employee Attendance Details</h1>
                <nav class="flex text-slate-500 text-xs mt-1 gap-2">
                    <a href="#" class="hover:text-orange-500">Attendance</a>
                    <span>/</span>
                    <a href="#" class="hover:text-orange-500">Admin Panel</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold"><?php echo $employeeName; ?></span>
                </nav>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <button class="flex-1 md:flex-none btn btn-light border text-sm shadow-sm" onclick="exportToCSV()">
                    <i class="fa-solid fa-download mr-2 text-orange-500"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6 mb-8">
            <div class="col-span-12 lg:col-span-3 card p-6 text-center shadow-md">
                <div class="flex justify-end mb-2">
                    <span class="bg-emerald-100 text-emerald-600 text-[10px] font-bold px-2 py-1 rounded">LOGGED IN</span>
                </div>
                <div class="w-24 h-24 rounded-full border-4 border-orange-500 p-1 mx-auto mb-4">
                    <img src="https://i.pravatar.cc/150?u=adrian" class="rounded-full w-full h-full object-cover">
                </div>
                <h2 class="text-lg font-bold text-slate-800"><?php echo $employeeName; ?></h2>
                <p class="text-slate-500 text-xs mb-6"><?php echo $designation; ?> (<?php echo $employeeID; ?>)</p>
                
                <div id="statusTag" class="bg-emerald-500 text-white py-2 px-4 rounded-md mb-4 text-sm font-medium transition-all shadow-sm">Status: In-Progress</div>
                <p class="text-slate-600 text-sm mb-6 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-fingerprint text-orange-500"></i> <span id="punchText" class="font-medium">Punch In at 09:00 AM</span>
                </p>
                <button id="punchBtn" onclick="togglePunch()" class="w-full bg-[#111827] text-white py-3 rounded-md font-bold transition-all shadow-md active:scale-95">Punch Out</button>

                <div class="bg-slate-50 rounded-xl p-3 mt-6 border border-slate-100">
                    <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Session Production</p>
                    <p class="text-xl font-bold text-slate-800">08h 45m</p>
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
                        <h4 class="font-bold text-slate-700">Weekly Performance Timeline</h4>
                        <span class="text-xs text-slate-400 italic">Expected: 45h / week</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6 text-center">
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase">Working</p><h3 class="text-xl font-bold">42h 10m</h3></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase">Production</p><h3 class="text-xl font-bold text-emerald-600">38h 20m</h3></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase">Breaks</p><h3 class="text-xl font-bold text-amber-500">3h 50m</h3></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase">Overtime</p><h3 class="text-xl font-bold text-blue-600">5h 15m</h3></div>
                    </div>
                    
                    <div class="h-12 w-full bg-slate-100 rounded-2xl flex overflow-hidden p-1.5 border border-slate-200">
                        <div class="h-full bg-emerald-500 rounded-xl shadow-sm" style="width: 70%" title="Production"></div>
                        <div class="h-full bg-amber-400 rounded-xl mx-1 shadow-sm" style="width: 15%" title="Breaks"></div>
                        <div class="h-full bg-blue-500 rounded-xl shadow-sm" style="width: 10%" title="Overtime"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400 font-bold px-2 mt-2 tracking-widest uppercase">
                        <span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="p-4 border-b flex flex-col md:flex-row justify-between items-center gap-4">
                <h3 class="text-lg font-bold text-slate-800">Attendance History Log</h3>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i class="fa fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-orange-500"></i>
                        <input type="text" class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-400/20" value="<?php echo $currentDateRange; ?>" readonly>
                    </div>
                    <select class="border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none bg-white">
                        <option>Current Week</option>
                        <option>Last 30 Days</option>
                    </select>
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
                                <button class="btn btn-sm btn-outline-primary transition hover:bg-blue-600 hover:text-white" title="Detailed Daily Report" onclick="openReportModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                    <i class="fa fa-chart-line"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="reportDetailModal" class="fixed inset-0 modal-backdrop-custom z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="p-6 border-b flex justify-between items-center bg-slate-900 text-white">
                <h2 class="text-xl font-bold"><i class="fa fa-fingerprint mr-2 text-orange-400"></i>Daily Breakdown Details</h2>
                <button onclick="closeReportModal()" class="text-white/70 hover:text-white transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-8">
                <div class="grid grid-cols-4 gap-4 mb-8 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Date</p><p class="font-bold" id="detDate"></p></div>
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Punch In</p><p class="font-bold" id="detIn"></p></div>
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Punch Out</p><p class="font-bold" id="detOut"></p></div>
                    <div><p class="text-slate-400 text-[10px] uppercase font-bold">Status</p><p class="font-bold" id="detStatus"></p></div>
                </div>
                
                <h5 class="font-bold text-slate-700 mb-4">Production Timeline (Activity Log)</h5>
                <div class="h-10 w-full bg-slate-100 rounded-full flex overflow-hidden mb-2 p-1 border">
                    <div class="bg-emerald-500 rounded-lg" style="width: 45%"></div>
                    <div class="bg-amber-400 rounded-lg mx-1" style="width: 8%"></div>
                    <div class="bg-emerald-500 rounded-lg" style="width: 30%"></div>
                    <div class="bg-blue-500 rounded-lg ml-1" style="width: 17%"></div>
                </div>
                <div class="flex justify-between text-[9px] text-slate-400 font-bold px-2 uppercase tracking-tighter">
                    <span>09:00 AM</span><span>11:00 AM</span><span>01:00 PM</span><span>03:00 PM</span><span>05:00 PM</span><span>07:00 PM</span><span>09:00 PM</span>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <button class="btn btn-dark btn-sm px-4 rounded-lg shadow-sm" onclick="closeReportModal()">Close View</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const reportModal = document.getElementById('reportDetailModal');
        let isPunchedOut = false;

        // Toggle Punch Logic (Restored)
        function togglePunch() {
            const btn = document.getElementById('punchBtn');
            const statusTag = document.getElementById('statusTag');
            const punchText = document.getElementById('punchText');
            
            if (!isPunchedOut) {
                if(confirm("Confirm Punch Out for this employee?")) {
                    btn.innerText = "Punch In";
                    btn.classList.replace('bg-[#111827]', 'bg-emerald-600');
                    statusTag.innerText = "Shift Completed";
                    statusTag.classList.replace('bg-emerald-500', 'bg-slate-400');
                    punchText.innerText = "Punch Out at 06:45 PM";
                    isPunchedOut = true;
                    alert("Shift Logged Out Successfully.");
                }
            } else {
                if(confirm("Confirm Punch In for this employee?")) {
                    btn.innerText = "Punch Out";
                    btn.classList.replace('bg-emerald-600', 'bg-[#111827]');
                    statusTag.innerText = "Status: In-Progress";
                    statusTag.classList.replace('bg-slate-400', 'bg-emerald-500');
                    punchText.innerText = "Punch In at 09:00 AM";
                    isPunchedOut = false;
                    alert("Shift Re-Started Successfully.");
                }
            }
        }

        // Toggle Report Modal
        function openReportModal(data) {
            document.getElementById('detDate').innerText = data.date;
            document.getElementById('detIn').innerText = data.checkin;
            document.getElementById('detOut').innerText = data.checkout;
            document.getElementById('detStatus').innerText = data.status;
            
            reportModal.classList.remove('hidden');
            reportModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeReportModal() {
            reportModal.classList.add('hidden');
            reportModal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // CSV Export Trigger
        function exportToCSV() {
            const rows = [
                ["Date", "Check In", "Check Out", "Status", "Production"],
                <?php foreach($attendanceRecords as $r): ?>
                ["<?php echo $r['date']; ?>", "<?php echo $r['checkin']; ?>", "<?php echo $r['checkout']; ?>", "<?php echo $r['status']; ?>", "<?php echo $r['production']; ?>"],
                <?php endforeach; ?>
            ];
            let csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.join(",")).join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "<?php echo $employeeID; ?>_Attendance_Log.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Close on outside click
        window.onclick = function(event) {
            if (event.target == reportModal) closeReportModal();
        }
    </script>
</body>
</html>