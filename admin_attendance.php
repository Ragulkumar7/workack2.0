<?php

include 'sidebars.php'; 
include 'header.php';

// attendance_admin.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Check auth (uncomment in production)
// if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA GENERATION
 $departments = ['IT', 'HR', 'Sales', 'Marketing', 'Finance'];
 $attendanceData = [];

// Generate data for the last 30 days
for ($i = 1; $i <= 50; $i++) {
    $day = rand(1, 30); 
    $month = date('m');
    $year = date('Y');
    $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
    
    // Generate Display ID (e.g., EMP-001)
    $displayId = "EMP-" . str_pad($i, 3, '0', STR_PAD_LEFT);
    
    // Random Status Logic
    $statusRand = rand(0, 10);
    $status = 'Present';
    $checkIn = '09:00 AM';
    $checkOut = '06:00 PM';
    $break = '20 Min';
    $late = '0 Min';
    $prod = '8.55 Hrs';

    if ($statusRand > 8) { 
        $status = 'Absent'; 
        $checkIn = '-'; $checkOut = '-'; $break = '-'; $late = '-'; $prod = '0 Hrs';
    } elseif ($statusRand == 7) {
        $status = 'Late'; 
        $late = rand(5, 45) . ' Min';
        $checkIn = '09:' . $late . ' AM';
    } elseif ($statusRand == 6) {
        $status = 'Half Day';
        $prod = '4.30 Hrs';
        $checkOut = '01:00 PM';
    }

    $attendanceData[] = [
        "id" => $i,
        "emp_id" => $displayId,
        "name" => "Employee " . $i,
        "avatar" => "https://i.pravatar.cc/150?img=" . ($i + 10),
        "role" => "Staff Member",
        "dept" => $departments[array_rand($departments)],
        "date" => $dateStr,
        "status" => $status,
        "checkin" => $checkIn,
        "checkout" => $checkOut,
        "break" => $break,
        "late" => $late,
        "production" => $prod
    ];
}

// Pass data to JS
 $jsonData = json_encode($attendanceData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Attendance Management</title>
    
    <!-- Dependencies -->
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
            --sidebar-width: 95px; /* Start matching sidebars.php width */
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
        .status-present { background: #dcfce7; color: #166534; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-late { background: #fef9c3; color: #854d0e; }
        .status-half { background: #e0f2fe; color: #075985; }

        /* Custom Elements */
        .avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .action-btn { padding: 0.4rem 0.8rem; font-size: 0.8rem; border-radius: 6px; transition: all 0.2s; }
        
        /* Modal Transitions */
        .modal-backdrop { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
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

    <div class="main-wrapper" id="mainWrapper">
        
        <!-- Page Header -->
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

        <!-- Stats Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card p-3 d-flex align-items-center">
                    <div class="bg-primary-subtle p-3 rounded-circle me-3">
                        <i class="fa-solid fa-users text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Total Employees</div>
                        <div class="fs-5 fw-bold">1,240</div>
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

        <!-- Filters & Table Card -->
        <div class="table-card overflow-hidden">
            
            <!-- Filter Toolbar -->
            <div class="p-3 border-bottom bg-light d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2">
                    <select id="filterMonth" class="form-select form-select-sm border-secondary-subtle shadow-none" style="width: 140px;">
                        <option value="">All Months</option>
                        <option value="<?php echo date('Y-m'); ?>" selected>This Month</option>
                        <option value="<?php echo date('Y-m', strtotime('-1 month')); ?>">Last Month</option>
                    </select>

                    <select id="filterDept" class="form-select form-select-sm border-secondary-subtle shadow-none" style="width: 140px;">
                        <option value="">All Depts</option>
                        <option value="IT">IT</option>
                        <option value="HR">HR</option>
                        <option value="Sales">Sales</option>
                        <option value="Marketing">Marketing</option>
                    </select>

                    <select id="filterStatus" class="form-select form-select-sm border-secondary-subtle shadow-none" style="width: 120px;">
                        <option value="">All Status</option>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                    </select>
                </div>
                
                <div class="position-relative">
                    <i class="fa-solid fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="searchInput" class="form-control form-control-sm ps-5 border-secondary-subtle shadow-none" placeholder="Search Name or ID..." style="width: 220px;">
                </div>
            </div>

            <!-- Data Table -->
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
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Placeholder -->
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

    <!-- VIEW REPORT MODAL -->
    <div id="viewModal" class="custom-modal fixed-top modal-backdrop w-100 h-100 align-items-center justify-content-center z-50">
        <div class="bg-white rounded-4 shadow-lg w-100" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
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

                <!-- Timeline Visualization -->
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
                    <div class="d-flex justify-content-between mt-2 text-muted small" style="font-size: 0.7rem;">
                        <span>09:00</span>
                        <span>01:00</span>
                        <span>01:20</span>
                        <span>06:00</span>
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
                            <div class="text-muted small">Overtime</div>
                            <div class="fw-bold fs-4" id="vm-late">00h 00m</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast">Action Successful</div>

    <script>
        // 1. INITIALIZE DATA
        let allRecords = <?php echo $jsonData; ?>;

        // 2. AUTO-ADJUST LAYOUT BASED ON SIDEBAR WIDTH (UPDATED LOGIC)
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainWrapper = document.getElementById('mainWrapper');
            
            if (!primarySidebar || !mainWrapper) return;

            // Function to calculate total width based on sidebar state
            const updateMargin = () => {
                let totalWidth = 0;

                // Always add primary sidebar width
                totalWidth += primarySidebar.offsetWidth;

                // If secondary sidebar is open (has class 'open'), add its width
                if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                    totalWidth += secondarySidebar.offsetWidth;
                }

                // Update CSS Variable
                document.documentElement.style.setProperty('--sidebar-width', totalWidth + 'px');
            };

            // A. Observe Primary Sidebar for size changes (e.g. window resize)
            const ro = new ResizeObserver(() => { 
                updateMargin(); 
            });
            ro.observe(primarySidebar);

            // B. Observe Secondary Sidebar for class changes (Opening/Closing)
            // MutationObserver detects when the 'open' class is toggled via JS
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

            // Initial set
            updateMargin();
        }

        // Run observer on load
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);

        // 3. DOM ELEMENTS
        const tableBody = document.getElementById('attendanceTableBody');
        const filterMonth = document.getElementById('filterMonth');
        const filterDept = document.getElementById('filterDept');
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

            // Get Filter Values
            const monthVal = filterMonth.value;
            const deptVal = filterDept.value;
            const statusVal = filterStatus.value;
            const searchVal = searchInput.value.toLowerCase();

            // Filter Logic
            const filteredData = allRecords.filter(record => {
                const recordMonth = record.date.substring(0, 7);
                
                const matchMonth = monthVal === "" || recordMonth === monthVal;
                const matchDept = deptVal === "" || record.dept === deptVal;
                const matchStatus = statusVal === "" || record.status === statusVal;
                const matchSearch = record.name.toLowerCase().includes(searchVal) || record.emp_id.toLowerCase().includes(searchVal);

                return matchMonth && matchDept && matchStatus && matchSearch;
            });

            // Update Stats based on filtered view
            filteredData.forEach(rec => {
                if(rec.status === 'Present') presentCount++;
                else if(rec.status === 'Late') lateCount++;
                else if(rec.status === 'Absent') absentCount++;
            });

            // Update DOM Stats
            statPresent.innerText = presentCount;
            statLate.innerText = lateCount;
            statAbsent.innerText = absentCount;
            showingCount.innerText = filteredData.length;

            // Generate Rows
            if(filteredData.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">No records found matching filters.</td></tr>`;
                return;
            }

            filteredData.forEach(rec => {
                let statusClass = 'status-present';
                if(rec.status === 'Absent') statusClass = 'status-absent';
                if(rec.status === 'Late') statusClass = 'status-late';
                if(rec.status === 'Half Day') statusClass = 'status-half';

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
                            <!-- VIEW BUTTON ONLY (EDIT REMOVED) -->
                            <button onclick="openViewModal(${rec.id})" class="btn btn-sm btn-light text-primary" title="View Report">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
        }

        // 5. EVENT LISTENERS
        filterMonth.addEventListener('change', renderTable);
        filterDept.addEventListener('change', renderTable);
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

        // Open View Details
        function openViewModal(id) {
            const rec = allRecords.find(r => r.id === id);
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
