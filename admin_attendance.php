<?php
// admin_attendance.php

// 1. SESSION START & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
$total_emp_sql = "SELECT COUNT(*) as count FROM employee_profiles";
if ($user_role === 'Manager') {
    // Manager sees direct reports and their downline
    $total_emp_sql .= " WHERE manager_id = $current_user_id 
                        OR reporting_to = $current_user_id 
                        OR reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = $current_user_id OR reporting_to = $current_user_id)";
}
$total_emp_res = mysqli_query($conn, $total_emp_sql);
$total_employees = mysqli_fetch_assoc($total_emp_res)['count'];

// 4. FETCH EXISTING ATTENDANCE DATA
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
            (SELECT SUM(TIMESTAMPDIFF(MINUTE, break_start, IFNULL(break_end, NOW()))) FROM attendance_breaks WHERE attendance_id = a.id) as break_mins
          FROM attendance a
          JOIN employee_profiles ep ON a.user_id = ep.user_id";

// Filter for Managers
if ($user_role === 'Manager') {
    $query .= " WHERE ep.manager_id = ? 
                OR ep.reporting_to = ? 
                OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = ? OR reporting_to = ?)";
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
    // Track who has punched in today to calculate absentees later
    if ($row['date'] === $today_str) {
        $users_punched_in_today[] = $row['user_id'];
    }

    $status = $row['status'] ?: 'Present';
    $checkIn = $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : '-';
    $checkOut = $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : '-';
    $break = ($row['break_mins'] !== null) ? $row['break_mins'] . ' Min' : '0 Min';
    
    // Calculate Late Minutes
    $late = '0 Min';
    if ($row['status'] === 'Late' && $row['punch_in']) {
        $shift_start = strtotime(date('Y-m-d 09:00:00', strtotime($row['punch_in'])));
        $actual_in = strtotime($row['punch_in']);
        if ($actual_in > $shift_start) {
            $late = floor(($actual_in - $shift_start) / 60) . ' Min';
        }
    }

    $prod = $row['production_hours'] ? number_format($row['production_hours'], 2) . ' Hrs' : '0 Hrs';

    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['full_name'])."&background=random";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = 'assets/profiles/' . $imgSource; 
    }

    $attendanceData[] = [
        "id" => $row['id'],
        "emp_id" => $row['emp_id_code'] ?? 'N/A',
        "name" => $row['full_name'] ?? 'Unknown',
        "avatar" => $imgSource,
        "role" => $row['designation'] ?? 'Employee',
        "dept" => $row['department'] ?? 'Unassigned',
        "date" => $row['date'],
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
$emp_query = "SELECT user_id, emp_id_code, full_name, designation, department, profile_img FROM employee_profiles";
if ($user_role === 'Manager') {
    $emp_query .= " WHERE manager_id = $current_user_id 
                    OR reporting_to = $current_user_id 
                    OR reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = $current_user_id OR reporting_to = $current_user_id)";
}

$emp_res = mysqli_query($conn, $emp_query);
while($emp = mysqli_fetch_assoc($emp_res)) {
    // If this employee is NOT in the array of people who punched in today
    if (!in_array($emp['user_id'], $users_punched_in_today)) {
        
        $imgSource = $emp['profile_img'];
        if(empty($imgSource) || $imgSource === 'default_user.png') {
            $imgSource = "https://ui-avatars.com/api/?name=".urlencode($emp['full_name'])."&background=random";
        } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
            $imgSource = 'assets/profiles/' . $imgSource; 
        }

        $attendanceData[] = [
            "id" => 'abs_' . $emp['user_id'], // Unique ID for modal identification
            "emp_id" => $emp['emp_id_code'] ?? 'N/A',
            "name" => $emp['full_name'] ?? 'Unknown',
            "avatar" => $imgSource,
            "role" => $emp['designation'] ?? 'Employee',
            "dept" => $emp['department'] ?? 'Unassigned',
            "date" => $today_str,
            "status" => 'Absent',
            "checkin" => '-',
            "checkout" => '-',
            "break" => '0 Min',
            "late" => '-',
            "production" => '0.00 Hrs'
        ];
    }
}

// Pass database records to JavaScript
$jsonData = json_encode($attendanceData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Attendance Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary: #4f46e5; 
            --primary-light: #e0e7ff;
            --bg-body: #f1f5f9; 
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --sidebar-width: 95px;
        }
        body { 
            background-color: var(--bg-body); 
            font-family: 'Inter', sans-serif; 
            color: var(--text-dark);
            overflow-x: hidden; 
        }

        /* DYNAMIC LAYOUT */
        .main-wrapper { 
            margin-left: var(--sidebar-width); 
            padding: 2rem; 
            width: calc(100% - var(--sidebar-width));
            transition: margin-left 0.3s ease, width 0.3s ease; 
        }
        
        /* Card Styling */
        .stat-card { border: none; border-radius: 12px; background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .table-card { border: none; border-radius: 12px; background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }

        /* Table Styling */
        .table thead th { 
            background-color: #f8fafc; 
            color: var(--text-muted); 
            font-weight: 600; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem;
        }
        .table tbody td { 
            vertical-align: middle; 
            padding: 1rem; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 0.9rem;
        }
        
        /* Status Pills */
        .status-badge { 
            padding: 0.35rem 0.75rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            display: inline-block;
        }
        .status-present, .status-ontime { background: #dcfce7; color: #166534; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-late { background: #fef9c3; color: #854d0e; }
        .status-half, .status-wfh { background: #e0f2fe; color: #075985; }

        /* Custom Elements */
        .avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .action-btn { padding: 0.4rem 0.8rem; font-size: 0.8rem; border-radius: 6px; transition: all 0.2s; }
        
        /* Modal Transitions */
        .modal-backdrop-custom { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
        .custom-modal { display: none; }
        .custom-modal.active { display: flex; }
        
        /* Toast */
        #toast {
            visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center;
            border-radius: 8px; padding: 16px; position: fixed; z-index: 10000; left: 50%; bottom: 30px;
            transform: translateX(-50%); font-size: 14px; opacity: 0; transition: opacity 0.5s, bottom 0.5s;
        }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>
    <?php include $headerPath; ?>

    <div class="main-wrapper" id="mainWrapper">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark fs-4 mb-1">Attendance Management</h2>
                <p class="text-muted small mb-0">Manage and track employee attendance records.</p>
            </div>
            <div>
                <button onclick="exportCSV()" class="btn btn-light border action-btn fw-semibold">
                    <i class="fa-solid fa-download text-primary me-1"></i> Export Report
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card p-3 d-flex align-items-center">
                    <div class="bg-primary-subtle p-3 rounded-circle me-3">
                        <i class="fa-solid fa-users text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Total Employees</div>
                        <div class="fs-5 fw-bold"><?php echo number_format($total_employees); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-3 d-flex align-items-center">
                    <div class="bg-success-subtle p-3 rounded-circle me-3">
                        <i class="fa-solid fa-user-check text-success fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Present Today</div>
                        <div class="fs-5 fw-bold" id="statPresent">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-3 d-flex align-items-center">
                    <div class="bg-warning-subtle p-3 rounded-circle me-3">
                        <i class="fa-solid fa-clock text-warning fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Late Arrivals</div>
                        <div class="fs-5 fw-bold" id="statLate">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-3 d-flex align-items-center">
                    <div class="bg-danger-subtle p-3 rounded-circle me-3">
                        <i class="fa-solid fa-user-xmark text-danger fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Absent</div>
                        <div class="fs-5 fw-bold" id="statAbsent">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card overflow-hidden">
            
            <div class="p-3 border-bottom bg-light d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2">
                    <select id="filterMonth" class="form-select form-select-sm border-secondary-subtle shadow-none" style="width: 140px;">
                        <option value="">All Months</option>
                        <option value="<?php echo date('Y-m'); ?>" selected>This Month</option>
                        <option value="<?php echo date('Y-m', strtotime('-1 month')); ?>">Last Month</option>
                    </select>

                    <select id="filterStatus" class="form-select form-select-sm border-secondary-subtle shadow-none" style="width: 120px;">
                        <option value="">All Status</option>
                        <option value="On Time">On Time</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                        <option value="WFH">WFH</option>
                    </select>
                </div>
                
                <div class="position-relative">
                    <i class="fa-solid fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="searchInput" class="form-control form-control-sm ps-5 border-secondary-subtle shadow-none" placeholder="Search Name or ID..." style="width: 220px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Emp ID</th>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Production</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                        </tbody>
                </table>
            </div>
            
            <div class="p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">Showing <span id="showingCount">0</span> records</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <div id="viewModal" class="custom-modal fixed-top modal-backdrop-custom w-100 h-100 align-items-center justify-content-center z-50">
        <div class="bg-white rounded-4 shadow-lg w-100 mx-3" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-primary text-white rounded-top-4">
                <h5 class="mb-0 fw-bold"><i class="fa-regular fa-file-lines me-2"></i>Detailed Report</h5>
                <button onclick="closeModal('viewModal')" class="btn btn-sm btn-light text-primary fw-bold rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">&times;</button>
            </div>
            <div class="p-5">
                <div class="d-flex align-items-center mb-4">
                    <img id="vm-avatar" src="" class="avatar me-3" style="width: 60px; height: 60px;">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="fw-bold mb-0" id="vm-name">User Name</h4>
                            <span class="badge bg-light text-dark border font-monospace" id="vm-id">EMP-001</span>
                        </div>
                        <p class="text-muted mb-0" id="vm-dept">Department</p>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="text-muted small">Date</div>
                        <div class="fw-bold fs-5" id="vm-date">--</div>
                    </div>
                </div>

                <div class="bg-light p-4 rounded-3 mb-4 border">
                    <div class="d-flex justify-content-between text-center mb-2">
                        <div>
                            <div class="text-muted small">Punch In</div>
                            <div class="fw-bold text-success" id="vm-in">09:00 AM</div>
                        </div>
                        <div>
                            <div class="text-muted small">Break</div>
                            <div class="fw-bold text-warning" id="vm-break">20m</div>
                        </div>
                        <div>
                            <div class="text-muted small">Punch Out</div>
                            <div class="fw-bold text-danger" id="vm-out">06:00 PM</div>
                        </div>
                    </div>
                    <div class="progress rounded-pill" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 45%"></div>
                        <div class="progress-bar bg-warning" role="progressbar" style="width: 10%"></div>
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 45%"></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 border rounded-3">
                            <div class="text-muted small">Total Working Hours</div>
                            <div class="fw-bold fs-4" id="vm-total">08h 00m</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded-3">
                            <div class="text-muted small">Late Arrival</div>
                            <div class="fw-bold fs-4" id="vm-late">00h 00m</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast">Action Successful</div>

    <script>
        // 1. INITIALIZE DATA FROM PHP
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
        const filterMonth = document.getElementById('filterMonth');
        const filterStatus = document.getElementById('filterStatus');
        const searchInput = document.getElementById('searchInput');
        const showingCount = document.getElementById('showingCount');
        
        // Stat Elements
        const statPresent = document.getElementById('statPresent');
        const statLate = document.getElementById('statLate');
        const statAbsent = document.getElementById('statAbsent');

        // 4. RENDER FUNCTION
        function renderTable() {
            tableBody.innerHTML = '';
            
            let presentCount = 0;
            let lateCount = 0;
            let absentCount = 0;

            const monthVal = filterMonth.value;
            const statusVal = filterStatus.value;
            const searchVal = searchInput.value.toLowerCase();

            const filteredData = allRecords.filter(record => {
                const recordMonth = record.date.substring(0, 7); // Gets YYYY-MM
                
                const matchMonth = monthVal === "" || recordMonth === monthVal;
                const matchStatus = statusVal === "" || record.status === statusVal;
                const matchSearch = record.name.toLowerCase().includes(searchVal) || record.emp_id.toLowerCase().includes(searchVal);

                return matchMonth && matchStatus && matchSearch;
            });

            // Update Stats based on filtered view
            filteredData.forEach(rec => {
                if(rec.status === 'On Time' || rec.status === 'Present') presentCount++;
                else if(rec.status === 'Late') lateCount++;
                else if(rec.status === 'Absent') absentCount++;
            });

            // Update DOM Stats
            statPresent.innerText = presentCount;
            statLate.innerText = lateCount;
            statAbsent.innerText = absentCount;
            showingCount.innerText = filteredData.length;

            if(filteredData.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">No records found matching filters.</td></tr>`;
                return;
            }

            filteredData.forEach(rec => {
                let statusClass = 'status-present';
                if(rec.status === 'Absent') statusClass = 'status-absent';
                if(rec.status === 'Late') statusClass = 'status-late';
                if(rec.status === 'WFH') statusClass = 'status-wfh';

                const row = `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="${rec.avatar}" class="avatar me-2">
                                <div>
                                    <div class="fw-bold text-dark">${rec.name}</div>
                                    <small class="text-muted" style="font-size: 0.75rem;">${rec.role}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border font-monospace">${rec.emp_id}</span>
                        </td>
                        <td>${rec.date}</td>
                        <td><span class="badge bg-light text-dark border">${rec.dept}</span></td>
                        <td><span class="status-badge ${statusClass}">${rec.status}</span></td>
                        <td>${rec.checkin}</td>
                        <td>${rec.checkout}</td>
                        <td><span class="fw-bold text-dark">${rec.production}</span></td>
                        <td class="text-end">
                            <button onclick="openViewModal('${rec.id}')" class="btn btn-sm btn-light text-primary border" title="View Report">
                                <i class="fa-solid fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
        }

        // 5. EVENT LISTENERS
        filterMonth.addEventListener('change', renderTable);
        filterStatus.addEventListener('change', renderTable);
        searchInput.addEventListener('input', renderTable);

        // 6. MODAL LOGIC
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function openViewModal(id) {
            // Note: Use String() comparison because ID might be an int OR a string like 'abs_20'
            const rec = allRecords.find(r => String(r.id) === String(id));
            if(!rec) return;

            document.getElementById('vm-name').innerText = rec.name;
            document.getElementById('vm-id').innerText = rec.emp_id;
            document.getElementById('vm-dept').innerText = rec.dept;
            document.getElementById('vm-avatar').src = rec.avatar;
            document.getElementById('vm-date').innerText = rec.date;
            document.getElementById('vm-in').innerText = rec.checkin;
            document.getElementById('vm-out').innerText = rec.checkout;
            document.getElementById('vm-break').innerText = rec.break;
            document.getElementById('vm-total').innerText = rec.production;
            document.getElementById('vm-late').innerText = rec.late;
            
            openModal('viewModal');
        }

        // 7. EXPORT TO CSV
        function exportCSV() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Employee ID,Name,Department,Date,Status,CheckIn,CheckOut,Production\n";

            const rows = document.querySelectorAll("#attendanceTableBody tr");
            rows.forEach(row => {
                const cols = row.querySelectorAll("td");
                if(cols.length > 1) { 
                    const rowData = [
                        cols[1].innerText.trim(),       // Emp ID
                        cols[0].innerText.replace(/\n/g, ' '), // Name
                        cols[3].innerText.trim(),       // Dept
                        cols[2].innerText,              // Date
                        cols[4].innerText.trim(),       // Status
                        cols[5].innerText,              // In
                        cols[6].innerText,              // Out
                        cols[7].innerText               // Prod
                    ];
                    csvContent += rowData.join(",") + "\n";
                }
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "attendance_export.csv");
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