<?php
// employee_attendance_details.php - Management View of Employee Attendance

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- ROBUST DATABASE CONNECTION ---
$dbPath = './include/db_connect.php';

if (file_exists($dbPath)) {
    include_once($dbPath);
} else {
    die("Error: db_connect.php not found at " . htmlspecialchars($dbPath));
}

// Check Login
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// 2. DATA CONTEXT
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['id']);

// A. Fetch Employee Profile Data
$sql_profile = "SELECT full_name, emp_id_code, designation FROM employee_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql_profile);
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();

$employeeName = $profile_data['full_name'] ?? "Unknown Employee";
$employeeID   = $profile_data['emp_id_code'] ?? "EMP-0000";
$designation  = $profile_data['designation'] ?? "Staff";


// --- DATE FILTER LOGIC ---
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
if($filter_date) {
    $currentDateRange = date('d M Y', strtotime($filter_date));
    $sql_att = "SELECT * FROM attendance WHERE user_id = ? AND date = ? ORDER BY date DESC";
} else {
    $currentDateRange = date('d M Y', strtotime('-7 days')) . " - " . date('d M Y');
    $sql_att = "SELECT * FROM attendance WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY date DESC";
}

// B. Fetch Attendance Records
$attendanceRecords = [];
$total_production = 0;
$late_days = 0;
$total_overtime = 0;
$days_count = 0;

$sum_work = 0;
$sum_break = 0;
$sum_overtime = 0;

$stmt = $conn->prepare($sql_att);

if($filter_date) {
    $stmt->bind_param("is", $view_user_id, $filter_date);
} else {
    $stmt->bind_param("i", $view_user_id);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $days_count++;
    $prod = floatval($row['production_hours']);
    $total_production += $prod;

    if ($row['status'] == 'Late') { $late_days++; }

    $overtime = ($prod > 9) ? ($prod - 9) : 0;
    $total_overtime += $overtime;

    // --- BREAK CALCULATION & DATABASE UPDATE LOGIC ---
    $break_min = 0;
    $daily_break_hours = 0;
    
    if ($row['punch_in'] && $row['punch_out']) {
        $in = strtotime($row['punch_in']);
        $out = strtotime($row['punch_out']);
        $total_duration = ($out - $in) / 3600; 
        
        $daily_break_hours = $total_duration - $prod;
        if($daily_break_hours < 0) $daily_break_hours = 0;
        
        $break_min = round($daily_break_hours * 60);

        // --- UPDATED DATABASE SYNC BLOCK ---
        // Only update if the database value is currently empty
        if (empty($row['break_time'])) {
            $update_sql = "UPDATE attendance SET break_time = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $break_min, $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    $sum_work += ($prod - $overtime);
    $sum_overtime += $overtime;
    $sum_break += $daily_break_hours;

    $attendanceRecords[] = [
        "date" => date('d M Y', strtotime($row['date'])),
        "checkin" => $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : "-",
        "status" => ($row['status'] == 'Absent') ? 'Absent' : 'Present',
        "status_raw" => $row['status'],
        "checkout" => $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : "-",
        "break" => ($break_min > 0) ? $break_min . " Min" : "-",
        "late" => ($row['status'] == 'Late') ? "Yes" : "-",
        "overtime" => ($overtime > 0) ? number_format($overtime, 2) . " Hrs" : "-",
        "production" => number_format($prod, 2) . " Hrs",
        "color" => ($row['status'] == 'Absent') ? "red" : "green"
    ];
}

// C. Calculate Final Averages
$avg_production = ($days_count > 0) ? number_format($total_production / $days_count, 1) : 0;

// D. Calculate Graph Percentages
$grand_total_hours = $sum_work + $sum_break + $sum_overtime;
if ($grand_total_hours > 0) {
    $pct_work = ($sum_work / $grand_total_hours) * 100;
    $pct_break = ($sum_break / $grand_total_hours) * 100;
    $pct_overtime = ($sum_overtime / $grand_total_hours) * 100;
} else {
    $pct_work = 0; $pct_break = 0; $pct_overtime = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - <?php echo htmlspecialchars($employeeName); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-orange: #ff5e3a; --bg-gray: #f8f9fa; --border-color: #edf2f7; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 13px; color: #333; overflow-x: hidden; }
        #mainContent { margin-left: 95px; padding: 25px 35px; transition: all 0.3s ease; min-height: 100vh; }
        @media (max-width: 768px) { #mainContent { margin-left: 0 !important; padding: 15px; } }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-present { background: #dcfce7; color: #166534; }
        .bg-absent { background: #fee2e2; color: #991b1b; }
        .bg-late { background: #fee2e2; color: #b91c1c; }
        .modal-backdrop-custom { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('./sidebars.php'); ?>

    <main id="mainContent">
        <?php include('./header.php'); ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"> Attendance Details</h1>
                <nav class="flex text-slate-500 text-xs mt-1 gap-2">
                    <a href="#" class="hover:text-orange-500">Attendance</a>
                    <span>/</span>
                    <a href="#" class="hover:text-orange-500">Admin Panel</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($employeeName); ?></span>
                </nav>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6 mb-8">
            <div class="col-span-12 lg:col-span-3 card p-6 text-center shadow-md h-fit">
                <div class="flex justify-end mb-2">
                    <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-1 rounded">MANAGEMENT VIEW</span>
                </div>
                <div class="w-24 h-24 rounded-full border-4 border-orange-500 p-1 mx-auto mb-4 relative">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employeeName); ?>&background=random" class="rounded-full w-full h-full object-cover">
                    <div class="absolute bottom-1 right-1 w-5 h-5 bg-emerald-500 border-2 border-white rounded-full"></div>
                </div>
                <h2 class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($employeeName); ?></h2>
                <p class="text-slate-500 text-xs mb-6"><?php echo htmlspecialchars($designation); ?> (<?php echo htmlspecialchars($employeeID); ?>)</p>
                
                <div class="bg-slate-100 text-slate-500 py-2 px-4 rounded-md mb-4 text-sm font-medium shadow-sm">
                    Status: Tracking History
                </div>

                <div class="bg-slate-50 rounded-xl p-3 mt-6 border border-slate-100">
                    <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Total Production (Period)</p>
                    <p class="text-xl font-bold text-slate-800"><?php echo number_format($total_production, 2); ?> Hrs</p>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-9">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                    <div class="card p-4 border-l-4 border-orange-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Avg. Production</p>
                        <h3 class="text-2xl font-bold"><?php echo $avg_production; ?> <small class="text-slate-400 font-normal">Hrs</small></h3>
                    </div>
                    <div class="card p-4 border-l-4 border-blue-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Late Logins</p>
                        <h3 class="text-2xl font-bold"><?php echo sprintf("%02d", $late_days); ?> <small class="text-slate-400 font-normal">Days</small></h3>
                    </div>
                    <div class="card p-4 border-l-4 border-emerald-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Total Attendance</p>
                        <h3 class="text-2xl font-bold"><?php echo ($days_count > 0) ? '100%' : '0%'; ?></h3>
                    </div>
                    <div class="card p-4 border-l-4 border-purple-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Overtime</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($total_overtime, 1); ?> <small class="text-slate-400 font-normal">Hrs</small></h3>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="p-4 border-b flex flex-col md:flex-row justify-between items-center gap-4">
                        <h3 class="text-lg font-bold text-slate-800">Attendance History Log</h3>
                        <div class="flex items-center gap-2">
                            <form action="" method="GET" class="flex items-center">
                                <input type="hidden" name="id" value="<?php echo $view_user_id; ?>">
                                <input type="date" name="date" class="pl-4 pr-2 py-2 border border-slate-200 rounded-lg text-xs font-semibold bg-slate-50 cursor-pointer" 
                                       value="<?php echo isset($_GET['date']) ? $_GET['date'] : ''; ?>" 
                                       onchange="this.form.submit()">
                            </form>
                            <span class="text-xs text-slate-400 font-medium ml-2 border-l pl-2 border-slate-200">
                                <?php echo $currentDateRange; ?>
                            </span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Break Time</th><th>Production</th><th>Late</th><th>Overtime</th><th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($attendanceRecords)): ?>
                                    <?php foreach ($attendanceRecords as $row): ?>
                                    <tr>
                                        <td class="font-medium text-slate-700"><?php echo $row['date']; ?></td>
                                        <td><?php echo $row['checkin']; ?></td>
                                        <td><?php echo $row['checkout']; ?></td>
                                        <td>
                                            <?php 
                                            $pillClass = 'bg-present';
                                            if($row['status'] == 'Absent') $pillClass = 'bg-absent';
                                            if($row['status_raw'] == 'Late') $pillClass = 'bg-late';
                                            ?>
                                            <span class="status-pill <?php echo $pillClass; ?>"><?php echo $row['status']; ?></span>
                                        </td>
                                        <td><span class="text-amber-600 font-semibold"><?php echo $row['break']; ?></span></td>
                                        <td><span class="font-bold text-slate-800"><?php echo $row['production']; ?></span></td>
                                        <td class="<?php echo $row['late'] != '-' ? 'text-red-500 font-semibold' : 'text-slate-400'; ?>"><?php echo $row['late']; ?></td>
                                        <td class="<?php echo $row['overtime'] != '-' ? 'text-blue-600 font-semibold' : 'text-slate-400'; ?>"><?php echo $row['overtime']; ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary transition hover:bg-blue-600 hover:text-white" onclick='openReportModal(<?php echo json_encode($row); ?>)'>
                                                <i class="fa fa-chart-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center p-4 text-slate-400">No attendance records found for this period.</td></tr>
                                <?php endif; ?>
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
        const reportModal = document.getElementById('reportDetailModal');
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