<?php
// admin_attendance.php

// 1. SESSION START & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

// Security check
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// SMART PATH RESOLVER
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Cannot find database connection file.");
}

$current_user_id = $_SESSION['user_id'];
$today_str = date('Y-m-d');

// 2. FETCH LOGGED-IN USER ROLE
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$role_res = $stmt->get_result()->fetch_assoc();
$user_role = $role_res['role'];
$stmt->close();

// 3. FETCH TOTAL EMPLOYEES COUNT BASED ON ROLE
$total_emp_sql = "SELECT COUNT(*) as count FROM employee_profiles WHERE status = 'Active'";
if ($user_role === 'Manager') {
    $total_emp_sql .= " AND (manager_id = $current_user_id 
                        OR reporting_to = $current_user_id 
                        OR reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = $current_user_id OR reporting_to = $current_user_id))";
}
$total_emp_res = mysqli_query($conn, $total_emp_sql);
$total_employees = mysqli_fetch_assoc($total_emp_res)['count'] ?? 0;

// 4. FETCH EXISTING ATTENDANCE DATA (Optimized for last 60 days to keep JS fast)
$sixty_days_ago = date('Y-m-d', strtotime('-60 days'));
$query = "SELECT 
            a.id, 
            a.user_id,
            a.date, 
            a.punch_in, 
            a.punch_out, 
            a.production_hours, 
            a.status,
            ep.emp_id_code, 
            ep.full_name, 
            ep.designation, 
            ep.department, 
            ep.profile_img,
            ep.shift_timings,
            (SELECT SUM(TIMESTAMPDIFF(MINUTE, break_start, IFNULL(break_end, NOW()))) FROM attendance_breaks WHERE attendance_id = a.id) as break_mins
          FROM attendance a
          JOIN employee_profiles ep ON a.user_id = ep.user_id 
          WHERE a.date >= '$sixty_days_ago'";

// Filter for Managers to only see their team
if ($user_role === 'Manager') {
    $query .= " AND (ep.manager_id = ? 
                OR ep.reporting_to = ? 
                OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = ? OR reporting_to = ?))";
}
$query .= " ORDER BY a.date DESC";

$stmt = $conn->prepare($query);
if ($user_role === 'Manager') {
    $stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
}
$stmt->execute();
$result = $stmt->get_result();

$attendanceData = [];
$users_punched_in_today = [];

while ($row = $result->fetch_assoc()) {
    // Track who has punched in today to calculate live absentees later
    if ($row['date'] === $today_str) {
        $users_punched_in_today[] = $row['user_id'];
    }

    $status = $row['status'] ?: 'Present';
    $checkIn = !empty($row['punch_in']) ? date('h:i A', strtotime($row['punch_in'])) : '-';
    $checkOut = !empty($row['punch_out']) ? date('h:i A', strtotime($row['punch_out'])) : '-';
    $break = ($row['break_mins'] !== null && $row['break_mins'] > 0) ? $row['break_mins'] . ' Min' : '0 Min';
    
    // Dynamic Late Calculation based on employee's exact shift
    $late = '0 Min';
    if (!empty($row['punch_in'])) {
        $shift_timings = $row['shift_timings'] ?? '09:00 AM - 06:00 PM';
        $time_parts = explode('-', $shift_timings);
        $shift_start_str = trim($time_parts[0]);
        
        $shift_start = strtotime($row['date'] . ' ' . $shift_start_str);
        $actual_in = strtotime($row['punch_in']);
        
        if ($actual_in > ($shift_start + 60)) { // 1 min grace period
            $late_mins = floor(($actual_in - $shift_start) / 60);
            if($late_mins >= 60) {
                $late = floor($late_mins / 60) . 'h ' . ($late_mins % 60) . 'm';
            } else {
                $late = $late_mins . ' Min';
            }
            if($status === 'On Time' || $status === 'Present') $status = 'Late';
        }
    }

    $prod = !empty($row['production_hours']) ? number_format((float)$row['production_hours'], 2) . ' Hrs' : '0.00 Hrs';

    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['full_name'] ?? 'User')."&background=0d9488&color=fff&bold=true";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = './assets/profiles/' . $imgSource; 
    }

    $attendanceData[] = [
        "id" => $row['id'],
        "user_id" => $row['user_id'],
        "emp_id" => $row['emp_id_code'] ?? 'N/A',
        "name" => $row['full_name'] ?? 'Unknown',
        "avatar" => $imgSource,
        "role" => $row['designation'] ?? 'Employee',
        "dept" => $row['department'] ?? 'Unassigned',
        "date" => $row['date'], // YYYY-MM-DD
        "display_date" => date('d M Y', strtotime($row['date'])),
        "status" => $status,
        "checkin" => $checkIn,
        "checkout" => $checkOut,
        "break" => $break,
        "late" => $late,
        "production" => $prod
    ];
}
$stmt->close();

// 5. INJECT "ABSENT" RECORDS FOR EMPLOYEES WHO HAVEN'T PUNCHED IN TODAY
$emp_query = "SELECT user_id, emp_id_code, full_name, designation, department, profile_img FROM employee_profiles WHERE status = 'Active'";
if ($user_role === 'Manager') {
    $emp_query .= " AND (manager_id = $current_user_id 
                    OR reporting_to = $current_user_id 
                    OR reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = $current_user_id OR reporting_to = $current_user_id))";
}

$emp_res = mysqli_query($conn, $emp_query);
while($emp = mysqli_fetch_assoc($emp_res)) {
    if (!in_array($emp['user_id'], $users_punched_in_today)) {
        
        $imgSource = $emp['profile_img'];
        if(empty($imgSource) || $imgSource === 'default_user.png') {
            $imgSource = "https://ui-avatars.com/api/?name=".urlencode($emp['full_name'] ?? 'User')."&background=0d9488&color=fff&bold=true";
        } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
            $imgSource = './assets/profiles/' . $imgSource; 
        }

        $attendanceData[] = [
            "id" => 'abs_' . $emp['user_id'], 
            "user_id" => $emp['user_id'],
            "emp_id" => $emp['emp_id_code'] ?? 'N/A',
            "name" => $emp['full_name'] ?? 'Unknown',
            "avatar" => $imgSource,
            "role" => $emp['designation'] ?? 'Employee',
            "dept" => $emp['department'] ?? 'Unassigned',
            "date" => $today_str,
            "display_date" => date('d M Y', strtotime($today_str)),
            "status" => 'Absent',
            "checkin" => '-',
            "checkout" => '-',
            "break" => '0 Min',
            "late" => '-',
            "production" => '0.00 Hrs'
        ];
    }
}

// CRITICAL FIX: High-security JSON encode prevents JS crashing on names with quotes (e.g. O'Connor)
$jsonData = json_encode($attendanceData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Enterprise Attendance Tracker</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { 
            background-color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b;
            overflow-x: hidden; 
        }

        /* DYNAMIC LAYOUT */
        .main-wrapper { 
            margin-left: 95px; 
            padding: 32px; 
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease; 
            min-height: 100vh;
        }
        .main-wrapper.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        
        @media (max-width: 1024px) {
            .main-wrapper { margin-left: 0 !important; width: 100% !important; padding: 16px; padding-top: 80px; }
        }

        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        /* Modal Transitions */
        .modal-backdrop-custom { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }

        /* Toast */
        #toast {
            visibility: hidden; min-width: 250px; background-color: #0d9488; color: #fff; text-align: center;
            border-radius: 8px; padding: 16px; position: fixed; z-index: 10000; left: 50%; bottom: 30px;
            transform: translateX(-50%); font-size: 14px; font-weight: bold; opacity: 0; transition: opacity 0.4s, bottom 0.4s;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>
    <?php include $headerPath; ?>

    <div class="main-wrapper" id="mainWrapper">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h2 class="font-black text-slate-800 text-3xl tracking-tight mb-1">Attendance Monitor</h2>
                <p class="text-slate-500 text-sm font-medium">Daily overview of company-wide attendance and shifts.</p>
            </div>
            <div>
                <button onclick="exportCSV()" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:shadow-md hover:border-teal-300 hover:text-teal-700 transition flex items-center gap-2">
                    <i class="fa-solid fa-file-export"></i> Export Report
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
                <div>
                    <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-0.5">Total Headcount</div>
                    <div class="text-2xl font-black text-slate-800"><?php echo number_format($total_employees); ?></div>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition border-b-4 border-b-emerald-500">
                <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 shrink-0">
                    <i class="fa-solid fa-user-check text-xl"></i>
                </div>
                <div>
                    <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-0.5" id="lblPresent">Present Selected Day</div>
                    <div class="text-2xl font-black text-slate-800" id="statPresent">0</div>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition border-b-4 border-b-amber-500">
                <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 shrink-0">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
                <div>
                    <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-0.5" id="lblLate">Late Arrivals</div>
                    <div class="text-2xl font-black text-slate-800" id="statLate">0</div>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition border-b-4 border-b-rose-500">
                <div class="w-12 h-12 rounded-full bg-rose-50 flex items-center justify-center text-rose-500 shrink-0">
                    <i class="fa-solid fa-user-xmark text-xl"></i>
                </div>
                <div>
                    <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-0.5" id="lblAbsent">Absent</div>
                    <div class="text-2xl font-black text-slate-800" id="statAbsent">0</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
            
            <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex flex-col xl:flex-row gap-4 items-start xl:items-center justify-between shrink-0">
                <div class="flex flex-wrap gap-3 w-full xl:w-auto">
                    <input type="date" id="filterDate" value="<?php echo $today_str; ?>" class="bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition shadow-sm w-full sm:w-auto cursor-pointer">

                    <select id="filterStatus" class="bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition shadow-sm w-full sm:w-auto cursor-pointer">
                        <option value="">All Statuses</option>
                        <option value="Present">Present (All)</option>
                        <option value="On Time">↳ On Time</option>
                        <option value="Late">↳ Late</option>
                        <option value="WFH">↳ WFH</option>
                        <option value="Absent">Absent</option>
                    </select>
                </div>
                
                <div class="relative w-full xl:w-72">
                    <i class="fa-solid fa-search absolute left-3.5 top-3.5 text-slate-400"></i>
                    <input type="text" id="searchInput" class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-sm font-medium focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition shadow-sm" placeholder="Search Name or ID...">
                </div>
            </div>

            <div class="overflow-x-auto custom-scroll max-h-[600px]">
                <table class="w-full text-left whitespace-nowrap text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employee</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Emp ID</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Department</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Check In</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Check Out</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Production</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody" class="divide-y divide-slate-100">
                        </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-slate-100 flex justify-between items-center bg-slate-50">
                <span class="text-xs font-bold text-slate-500">Showing <span id="showingCount" class="text-slate-800 font-black">0</span> records</span>
            </div>
        </div>
    </div>

    <div id="viewModal" class="fixed inset-0 modal-backdrop-custom z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden transform scale-95 transition-transform duration-300" id="modalBox">
            
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-900 text-white relative">
                <h5 class="text-lg font-bold flex items-center gap-2"><i class="fa-regular fa-file-lines text-teal-400"></i> Daily Report</h5>
                <button onclick="closeModal('viewModal')" class="w-8 h-8 rounded-full bg-white/10 hover:bg-rose-500 flex items-center justify-center text-white transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="flex items-center mb-6">
                    <img id="vm-avatar" src="" class="w-14 h-14 rounded-full border-2 border-slate-100 shadow-sm object-cover mr-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-black text-lg text-slate-800" id="vm-name">User Name</h4>
                            <span class="bg-slate-100 border border-slate-200 text-slate-600 text-[10px] font-black px-2 py-0.5 rounded uppercase" id="vm-id">EMP-001</span>
                            <span id="detStatus" class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border">STATUS</span>
                        </div>
                        <p class="text-sm font-medium text-slate-500" id="vm-dept">Department</p>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</div>
                        <div class="font-bold text-slate-800" id="vm-date">--</div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6">
                    <div class="flex justify-between items-center text-center">
                        <div class="flex-1">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Punch In</p>
                            <p class="font-bold text-emerald-600 text-base" id="vm-in">--:--</p>
                        </div>
                        <div class="flex-1 border-x border-slate-200">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Break</p>
                            <p class="font-bold text-amber-500 text-base" id="vm-break">-</p>
                        </div>
                        <div class="flex-1">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Punch Out</p>
                            <p class="font-bold text-rose-500 text-base" id="vm-out">--:--</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="p-4 border border-slate-200 rounded-xl shadow-sm text-center">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Production</p>
                        <p class="font-black text-2xl text-slate-800" id="vm-total">0 Hrs</p>
                    </div>
                    <div class="p-4 border border-slate-200 rounded-xl shadow-sm text-center">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Late Arrival</p>
                        <p class="font-black text-2xl text-slate-800" id="vm-late">0 Min</p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center pt-2">
                    <a href="#" id="vm-full-history" class="text-teal-600 hover:text-teal-800 text-sm font-bold flex items-center gap-1 transition">
                        <i class="fa-solid fa-chart-pie"></i> View Full Monthly History
                    </a>
                    <button class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-6 py-2.5 rounded-xl transition shadow-md" onclick="closeModal('viewModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast">Action Successful</div>

    <script>
        // 1. INITIALIZE DATA FROM PHP SAFELY
        let allRecords = <?php echo $jsonData; ?>;

        // 2. AUTO-ADJUST LAYOUT BASED ON SIDEBAR WIDTH
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainWrapper = document.getElementById('mainWrapper');
            
            if (!primarySidebar || !mainWrapper) return;

            const updateMargin = () => {
                let totalWidth = 0;
                totalWidth += primarySidebar.offsetWidth;

                if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                    totalWidth += secondarySidebar.offsetWidth;
                }
                document.documentElement.style.setProperty('--sidebar-width', totalWidth + 'px');
            };

            const ro = new ResizeObserver(() => { updateMargin(); });
            ro.observe(primarySidebar);

            if (secondarySidebar) {
                const mo = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            updateMargin();
                        }
                    });
                });
                mo.observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            updateMargin();
        }
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);

        // 3. DOM ELEMENTS
        const tableBody = document.getElementById('attendanceTableBody');
        const filterDate = document.getElementById('filterDate');
        const filterStatus = document.getElementById('filterStatus');
        const searchInput = document.getElementById('searchInput');
        const showingCount = document.getElementById('showingCount');
        
        // Stat Elements
        const statPresent = document.getElementById('statPresent');
        const statLate = document.getElementById('statLate');
        const statAbsent = document.getElementById('statAbsent');

        // 4. RENDER FUNCTION WITH EXACT DAILY MATH
        function renderTable() {
            tableBody.innerHTML = '';
            
            let presentCount = 0;
            let lateCount = 0;
            let absentCount = 0;

            const dateVal = filterDate.value; // YYYY-MM-DD
            const statusVal = filterStatus.value;
            const searchVal = searchInput.value.toLowerCase();

            // Filter engine
            const filteredData = allRecords.filter(record => {
                // Enterprise Rule: Dashboard Table displays ONE specific day at a time.
                const matchDate = dateVal === "" || record.date === dateVal;
                
                // Handle "Present" encompassing multiple sub-statuses
                let matchStatus = false;
                if(statusVal === "") { matchStatus = true; }
                else if(statusVal === "Present" && (record.status === 'On Time' || record.status === 'Present' || record.status === 'Late' || record.status === 'WFH')) { matchStatus = true; }
                else if(record.status === statusVal) { matchStatus = true; }

                const matchSearch = record.name.toLowerCase().includes(searchVal) || record.emp_id.toLowerCase().includes(searchVal);

                return matchDate && matchStatus && matchSearch;
            });

            // Update KPI Stats based strictly on the selected day
            filteredData.forEach(rec => {
                if(rec.status === 'On Time' || rec.status === 'Present' || rec.status === 'WFH') {
                    presentCount++;
                }
                else if(rec.status === 'Late') { 
                    presentCount++; // Late people ARE present.
                    lateCount++; 
                }
                else if(rec.status === 'Absent') {
                    absentCount++;
                }
            });

            statPresent.innerText = presentCount; 
            statLate.innerText = lateCount;
            statAbsent.innerText = absentCount;
            showingCount.innerText = filteredData.length;

            if(filteredData.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-16 text-slate-400 font-medium"><i class="fa-solid fa-folder-open text-3xl mb-3 opacity-50 block"></i> No attendance records found for this selection.</td></tr>`;
                return;
            }

            // Build UI Rows
            filteredData.forEach(rec => {
                let statusClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';
                if(rec.status === 'Absent') statusClass = 'bg-rose-50 text-rose-600 border-rose-200';
                if(rec.status === 'Late') statusClass = 'bg-amber-50 text-amber-600 border-amber-200';
                if(rec.status === 'WFH') statusClass = 'bg-blue-50 text-blue-600 border-blue-200';

                // Red text for low production (< 8 hours)
                let prodClass = "font-bold text-slate-700";
                if(rec.status !== 'Absent' && parseFloat(rec.production) > 0 && parseFloat(rec.production) < 8) {
                    prodClass = "font-bold text-rose-500";
                }

                const row = `
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <img src="${rec.avatar}" class="w-10 h-10 rounded-full object-cover border border-slate-200 shadow-sm">
                                <div>
                                    <div class="font-bold text-slate-800 text-sm">${rec.name}</div>
                                    <div class="text-[11px] font-medium text-slate-500">${rec.role}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="bg-slate-100 border border-slate-200 text-slate-600 px-2 py-1 rounded text-xs font-bold font-mono shadow-sm">${rec.emp_id}</span>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-600">${rec.dept}</td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider rounded-md border ${statusClass}">
                                ${rec.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-600">${rec.checkin}</td>
                        <td class="px-6 py-4 font-medium text-slate-600">${rec.checkout}</td>
                        <td class="px-6 py-4 ${prodClass}">${rec.production}</td>
                        <td class="px-6 py-4 text-right">
                            <button onclick="openViewModal('${rec.id}')" class="text-slate-500 hover:text-teal-600 bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-200 px-3 py-1.5 rounded-lg transition-all shadow-sm text-xs font-bold flex items-center gap-1 ml-auto">
                                <i class="fa-solid fa-expand"></i> View
                            </button>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
        }

        // 5. EVENT LISTENERS
        filterDate.addEventListener('change', renderTable);
        filterStatus.addEventListener('change', renderTable);
        searchInput.addEventListener('input', renderTable);

        // 6. MODAL LOGIC
        const modalEl = document.getElementById('viewModal');
        const modalBox = document.getElementById('modalBox');

        function openModal() {
            modalEl.classList.remove('hidden');
            modalEl.classList.add('flex');
            document.body.style.overflow = 'hidden';
            setTimeout(() => { modalBox.classList.remove('scale-95'); modalBox.classList.add('scale-100'); }, 10);
        }

        function closeModal() {
            modalBox.classList.remove('scale-100');
            modalBox.classList.add('scale-95');
            setTimeout(() => { 
                modalEl.classList.add('hidden');
                modalEl.classList.remove('flex');
                document.body.style.overflow = 'auto';
            }, 200);
        }

        function openViewModal(id) {
            const rec = allRecords.find(r => String(r.id) === String(id));
            if(!rec) return;

            document.getElementById('vm-name').innerText = rec.name;
            document.getElementById('vm-id').innerText = rec.emp_id;
            document.getElementById('vm-dept').innerText = rec.dept;
            document.getElementById('vm-avatar').src = rec.avatar;
            document.getElementById('vm-date').innerText = rec.display_date;
            document.getElementById('vm-in').innerText = rec.checkin;
            document.getElementById('vm-out').innerText = rec.checkout;
            document.getElementById('vm-break').innerText = rec.break;
            document.getElementById('vm-total').innerText = rec.production;
            document.getElementById('vm-late').innerText = rec.late;
            
            // Dynamic Status Badge Injection Fix
            const statusEl = document.getElementById('detStatus');
            statusEl.innerText = rec.status;
            
            let statusClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';
            if(rec.status === 'Absent') statusClass = 'bg-rose-50 text-rose-600 border-rose-200';
            if(rec.status === 'Late') statusClass = 'bg-amber-50 text-amber-600 border-amber-200';
            if(rec.status === 'WFH') statusClass = 'bg-blue-50 text-blue-600 border-blue-200';
            statusEl.className = `px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border ${statusClass}`;

            // Smart routing to detailed history page
            let pathPrefix = window.location.pathname.includes('/manager/') ? '../' : '';
            document.getElementById('vm-full-history').href = `${pathPrefix}employee_attendance_details.php?id=${rec.user_id}`;

            openModal();
        }

        window.onclick = function(event) {
            if (event.target == modalEl) closeModal();
        }

        // 7. EXPORT TO CSV
        function exportCSV() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Employee ID,Name,Department,Date,Status,CheckIn,CheckOut,Production\n";

            const rows = document.querySelectorAll("#attendanceTableBody tr");
            if (rows.length === 0 || rows[0].innerText.includes('No attendance records')) {
                alert("No records to export for this date.");
                return;
            }

            rows.forEach(row => {
                const cols = row.querySelectorAll("td");
                if(cols.length > 1) { 
                    const rowData = [
                        cols[1].innerText.trim(),       // Emp ID
                        cols[0].innerText.replace(/\n/g, ' ').trim(), // Name/Role combo
                        cols[2].innerText.trim(),       // Dept
                        document.getElementById('filterDate').value, // Selected Date
                        cols[3].innerText.trim(),       // Status
                        cols[4].innerText,              // In
                        cols[5].innerText,              // Out
                        cols[6].innerText               // Prod
                    ];
                    // Clean names by removing extra spaces and roles
                    rowData[1] = rowData[1].split('  ')[0]; 
                    
                    csvContent += rowData.map(d => `"${d}"`).join(",") + "\n";
                }
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `Attendance_Report_${document.getElementById('filterDate').value}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast("Report downloaded successfully");
        }

        // Helper: Toast
        function showToast(msg) {
            const toast = document.getElementById("toast");
            toast.innerText = msg;
            toast.className = "show";
            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
        }

        // Initial Render
        renderTable();

    </script>
</body>
</html>