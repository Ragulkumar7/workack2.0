<?php
// employee_attendance_details.php - Management View of Employee Attendance

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- ROBUST DATABASE CONNECTION ---
$dbPath = './include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
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
$sql_profile = "SELECT full_name, emp_id_code, designation, joining_date FROM employee_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql_profile);
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();

$employeeName = $profile_data['full_name'] ?? "Unknown Employee";
$employeeID   = $profile_data['emp_id_code'] ?? "EMP-0000";
$designation  = $profile_data['designation'] ?? "Staff";
$joining_date = $profile_data['joining_date'] ?? date('Y-m-01');

// =========================================================================================
// FILTER LOGIC (Daily, Monthly, Range)
// =========================================================================================
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'daily';

// Default Values
$filter_date  = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d'); // Default: Today
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');
$from_date    = isset($_GET['from_date']) && !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date      = isset($_GET['to_date']) && !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Determine Query Based on Filter Type
if ($filter_type === 'daily') {
    $sql_att = "SELECT * FROM attendance WHERE user_id = ? AND date = ? ORDER BY date DESC";
    $currentDisplay = date('d M Y', strtotime($filter_date));
} elseif ($filter_type === 'monthly') {
    $sql_att = "SELECT * FROM attendance WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? ORDER BY date DESC";
    $currentDisplay = date('F Y', strtotime($filter_month . '-01'));
} elseif ($filter_type === 'range') {
    // Swap dates if from_date is greater than to_date
    if (strtotime($to_date) < strtotime($from_date)) {
        $temp = $from_date; $from_date = $to_date; $to_date = $temp;
    }
    $sql_att = "SELECT * FROM attendance WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date DESC";
    $currentDisplay = date('d M Y', strtotime($from_date)) . " to " . date('d M Y', strtotime($to_date));
}

// Prepare Statement
$stmt = $conn->prepare($sql_att);
if ($filter_type === 'daily') {
    $stmt->bind_param("is", $view_user_id, $filter_date);
} elseif ($filter_type === 'monthly') {
    $stmt->bind_param("is", $view_user_id, $filter_month);
} elseif ($filter_type === 'range') {
    $stmt->bind_param("iss", $view_user_id, $from_date, $to_date);
}

// B. Fetch Attendance Records based on Date Range
$attendanceRecords = [];
$total_production = 0;
$late_days = 0;
$total_overtime = 0;
$days_count = 0;

$sum_work = 0;
$sum_break = 0;
$sum_overtime = 0;

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

// =========================================================================================
// D. LEAVE CARRY-FORWARD LOGIC (Overall Calculation)
// =========================================================================================
$base_leaves_per_month = 2;

// Calculate Total Months Worked from Joining Date to Current Date
$d1 = new DateTime($joining_date);
$d1->modify('first day of this month'); 
$d2 = new DateTime('now');
$d2->modify('first day of this month');

$months_worked = 0;
if ($d2 >= $d1) {
    $interval = $d1->diff($d2);
    $months_worked = ($interval->y * 12) + $interval->m + 1; // +1 to include the current month
}

// Total Earned Leaves Since Joining
$total_earned_leaves = $months_worked * $base_leaves_per_month;

// Fetch Total Leaves Taken by Employee EVER (Approved Only)
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("i", $view_user_id);
$leave_stmt->execute();
$leave_res = $leave_stmt->get_result();
$leave_data = $leave_res->fetch_assoc();

$total_leaves_taken = $leave_data['taken'] ?? 0;
$leave_balance = $total_earned_leaves - $total_leaves_taken;
if($leave_balance < 0) $leave_balance = 0; // Prevent negative display

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
        input[type="date"]::-webkit-calendar-picker-indicator, input[type="month"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover, input[type="month"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }
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
                
                <div class="bg-teal-50 border border-teal-100 rounded-xl p-4 mt-2 text-left">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-teal-700 text-xs font-bold uppercase tracking-wider">Leave Balance</p>
                        <i class="fa-solid fa-umbrella-beach text-teal-500"></i>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-black text-teal-800"><?php echo $leave_balance; ?></span>
                        <span class="text-xs text-teal-600 font-medium mb-1">Available</span>
                    </div>
                    <p class="text-[9px] text-teal-600 mt-2 opacity-80">(Includes carry-forward since joining)</p>
                </div>

                <div class="bg-slate-50 rounded-xl p-3 mt-4 border border-slate-100">
                    <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Total Production (Shown)</p>
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
                        <p class="text-slate-400 text-xs font-bold uppercase">Days Present</p>
                        <h3 class="text-2xl font-bold"><?php echo $days_count; ?> <small class="text-slate-400 font-normal">Days</small></h3>
                    </div>
                    <div class="card p-4 border-l-4 border-purple-500">
                        <p class="text-slate-400 text-xs font-bold uppercase">Overtime</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($total_overtime, 1); ?> <small class="text-slate-400 font-normal">Hrs</small></h3>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="p-4 border-b flex flex-col xl:flex-row justify-between items-center gap-4 bg-white rounded-t-xl">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-bold text-slate-800 whitespace-nowrap">Attendance History</h3>
                            <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-xs font-semibold border border-slate-200"><?php echo $currentDisplay; ?></span>
                        </div>
                        
                        <form action="" method="GET" class="flex flex-col sm:flex-row items-center gap-2 w-full xl:w-auto" id="filterForm">
                            <input type="hidden" name="id" value="<?php echo $view_user_id; ?>">
                            
                            <select name="filter_type" id="filterType" class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-700 outline-none w-full sm:w-auto font-medium" onchange="toggleFilterInputs()">
                                <option value="daily" <?php echo $filter_type == 'daily' ? 'selected' : ''; ?>>Single Date</option>
                                <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Month Wise</option>
                                <option value="range" <?php echo $filter_type == 'range' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>

                            <div id="inputDaily" class="<?php echo $filter_type == 'daily' ? 'block' : 'hidden'; ?> w-full sm:w-auto">
                                <input type="date" name="filter_date" value="<?php echo $filter_date; ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none text-slate-700 font-medium">
                            </div>

                            <div id="inputMonthly" class="<?php echo $filter_type == 'monthly' ? 'block' : 'hidden'; ?> w-full sm:w-auto">
                                <input type="month" name="filter_month" value="<?php echo $filter_month; ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none text-slate-700 font-medium">
                            </div>

                            <div id="inputRange" class="<?php echo $filter_type == 'range' ? 'flex' : 'hidden'; ?> items-center gap-2 w-full sm:w-auto">
                                <input type="date" name="from_date" value="<?php echo $from_date; ?>" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm outline-none text-slate-700 font-medium" title="From Date">
                                <span class="text-slate-400 text-xs font-bold">TO</span>
                                <input type="date" name="to_date" value="<?php echo $to_date; ?>" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm outline-none text-slate-700 font-medium" title="To Date">
                            </div>

                            <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-orange-600 transition w-full sm:w-auto whitespace-nowrap">
                                <i class="fa-solid fa-filter mr-1"></i> Apply
                            </button>
                        </form>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Date</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Check In</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Check Out</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Status</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Break Time</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Production</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Late</th>
                                    <th class="text-xs text-slate-500 font-bold uppercase py-3">Overtime</th>
                                    <th class="text-center text-xs text-slate-500 font-bold uppercase py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($attendanceRecords)): ?>
                                    <?php foreach ($attendanceRecords as $row): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="font-medium text-slate-700 py-3"><?php echo $row['date']; ?></td>
                                        <td class="py-3"><?php echo $row['checkin']; ?></td>
                                        <td class="py-3"><?php echo $row['checkout']; ?></td>
                                        <td class="py-3">
                                            <?php 
                                            $pillClass = 'bg-present';
                                            if($row['status'] == 'Absent') $pillClass = 'bg-absent';
                                            if($row['status_raw'] == 'Late') $pillClass = 'bg-late';
                                            ?>
                                            <span class="status-pill <?php echo $pillClass; ?>"><?php echo $row['status']; ?></span>
                                        </td>
                                        <td class="py-3"><span class="text-amber-600 font-semibold"><?php echo $row['break']; ?></span></td>
                                        <td class="py-3"><span class="font-bold text-slate-800"><?php echo $row['production']; ?></span></td>
                                        <td class="py-3 <?php echo $row['late'] != '-' ? 'text-red-500 font-semibold' : 'text-slate-400'; ?>"><?php echo $row['late']; ?></td>
                                        <td class="py-3 <?php echo $row['overtime'] != '-' ? 'text-blue-600 font-semibold' : 'text-slate-400'; ?>"><?php echo $row['overtime']; ?></td>
                                        <td class="text-center py-3">
                                            <button class="btn btn-sm btn-outline-primary transition hover:bg-blue-600 hover:text-white" onclick='openReportModal(<?php echo json_encode($row); ?>)'>
                                                <i class="fa fa-chart-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-12">
                                            <div class="flex flex-col items-center justify-center text-slate-400">
                                                <i class="fa-regular fa-folder-open text-4xl mb-3 opacity-50"></i>
                                                <p class="font-medium text-sm">No attendance records found for the selected filter.</p>
                                            </div>
                                        </td>
                                    </tr>
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
        // Toggle Filter Inputs Script
        function toggleFilterInputs() {
            const type = document.getElementById('filterType').value;
            
            document.getElementById('inputDaily').className = (type === 'daily') ? 'block w-full sm:w-auto' : 'hidden';
            document.getElementById('inputMonthly').className = (type === 'monthly') ? 'block w-full sm:w-auto' : 'hidden';
            document.getElementById('inputRange').className = (type === 'range') ? 'flex items-center gap-2 w-full sm:w-auto' : 'hidden';
        }
        
        // Initialize state on page load
        document.addEventListener('DOMContentLoaded', toggleFilterInputs);

        // Modal Logic
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