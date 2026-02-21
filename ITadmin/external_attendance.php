<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { 
    require_once $dbPath; 
} else { 
    require_once $projectRoot . '/include/db_connect.php'; 
}

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// --- HELPER FUNCTION TO FORMAT TIME FOR PHP FETCH ---
function formatTimeForDisplay($timeStr) {
    if (!$timeStr || $timeStr === '00:00:00') return '-';
    return date("h:i A", strtotime($timeStr));
}

// =========================================================================
// 2. BACKEND AJAX HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // A. Add Staff
    if ($_POST['action'] === 'add_staff') {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $fullName = $role ? "$name ($role)" : $name;
        
        $sql = "INSERT INTO external_employees (name, role) VALUES ('$fullName', '$role')";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success', 'id' => mysqli_insert_id($conn), 'name' => $fullName]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // B. Save Attendance
    if ($_POST['action'] === 'save_attendance') {
        $emp_id = intval($_POST['emp_id']);
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $check_in = !empty($_POST['check_in']) ? "'" . mysqli_real_escape_string($conn, $_POST['check_in']) . "'" : "NULL";
        $check_out = !empty($_POST['check_out']) ? "'" . mysqli_real_escape_string($conn, $_POST['check_out']) . "'" : "NULL";

        $check_sql = "SELECT id FROM external_attendance WHERE emp_id = $emp_id AND attendance_date = '$date'";
        $result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $att_id = $row['id'];
            $sql = "UPDATE external_attendance SET check_in = $check_in, check_out = $check_out, status = '$status' WHERE id = $att_id";
        } else {
            $sql = "INSERT INTO external_attendance (emp_id, attendance_date, check_in, check_out, status) 
                    VALUES ($emp_id, '$date', $check_in, $check_out, '$status')";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // C. Fetch Data for EXPORT
    if ($_POST['action'] === 'fetch_export_data') {
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        
        $sql = "SELECT e.name, a.check_in, a.check_out, a.status 
                FROM external_attendance a 
                JOIN external_employees e ON a.emp_id = e.id 
                WHERE a.attendance_date = '$date'
                ORDER BY a.created_at ASC";
                
        $result = mysqli_query($conn, $sql);
        $exportData = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $exportData[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $exportData]);
        exit;
    }
}

// =========================================================================
// 3. FETCH DATA ON PAGE LOAD
// =========================================================================
$external_employees = [];
$res = mysqli_query($conn, "SELECT id, name FROM external_employees ORDER BY name ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $external_employees[] = $row;
    }
}

$current_date = date('Y-m-d');
$recent_attendance = [];
$att_sql = "SELECT e.name, a.attendance_date, a.check_in, a.check_out, a.status, a.created_at 
            FROM external_attendance a 
            JOIN external_employees e ON a.emp_id = e.id 
            WHERE a.attendance_date = '$current_date' 
            ORDER BY a.created_at DESC";
$att_res = mysqli_query($conn, $att_sql);
if ($att_res) {
    while ($row = mysqli_fetch_assoc($att_res)) {
        $recent_attendance[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Employees Attendance | SmartHR</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --primary: #1b5a5a; --primary-hover: #144d4d; --bg-body: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); }
        #mainContent { margin-left: 95px; transition: margin-left 0.3s ease; min-height: 100vh; display: flex; flex-direction: column; }
        .styled-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s; outline: none; background-color: #fff; }
        .styled-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }
        .dropdown-menu { display: none; opacity: 0; transform: translateY(-10px); transition: all 0.2s ease; }
        .dropdown-menu.show { display: block; opacity: 1; transform: translateY(0); }
        .modal-overlay { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; }
        .modal-overlay.show { display: flex; }
    </style>
</head>
<body>

<div id="mainContent">
    <?php include '../header.php'; ?>
    <?php include '../sidebars.php'; ?>

    <div class="flex justify-between items-center px-8 py-6 bg-white border-b border-gray-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">External Staff Attendance</h1>
            <p class="text-sm text-gray-500 mt-1">Manage attendance for vendors, contract workers, and daily wagers.</p>
        </div>
        
        <div class="flex items-center gap-4">
            
            <div class="relative">
                <button onclick="toggleExportMenu()" class="bg-white border border-gray-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-50 transition flex items-center gap-2 shadow-sm">
                    <i data-lucide="download" class="w-4 h-4"></i> Export
                </button>
                <div id="exportMenu" class="dropdown-menu absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border border-gray-100 z-50 overflow-hidden">
                    <a href="javascript:void(0)" onclick="exportData('pdf')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2 border-b border-gray-50">
                        <i data-lucide="file-text" class="w-4 h-4"></i> As PDF
                    </a>
                    <a href="javascript:void(0)" onclick="exportData('excel')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700 flex items-center gap-2">
                        <i data-lucide="sheet" class="w-4 h-4"></i> As Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6 md:p-8 w-full flex-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="attendanceTable">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider border-b border-gray-200">
                            <th class="px-6 py-4 font-semibold w-16 text-center">S.No</th>
                            <th class="px-6 py-4 font-semibold w-64">Employee Name</th>
                            <th class="px-6 py-4 font-semibold w-40">Date</th>
                            <th class="px-6 py-4 font-semibold w-32">Check-In</th>
                            <th class="px-6 py-4 font-semibold w-32">Check-Out</th>
                            <th class="px-6 py-4 font-semibold w-32">Status</th>
                            <th class="px-6 py-4 font-semibold text-center w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100" id="tableBody">
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 text-center text-gray-500 font-medium sno-cell">1</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <select class="styled-input employee-select">
                                        <option value="" disabled selected>Select Staff...</option>
                                        <?php foreach($external_employees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" onclick="openAddModal()" class="bg-[#1b5a5a] text-white p-2 rounded-md hover:bg-[#144d4d] transition shadow-sm" title="Add New Person">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <input type="date" class="styled-input text-gray-700 attendance-date-input" value="<?= date('Y-m-d') ?>">
                            </td>
                            
                            <td class="px-6 py-4">
                                <input type="text" class="styled-input time-picker check-in-input cursor-pointer" placeholder="--:-- --" readonly>
                            </td>
                            <td class="px-6 py-4">
                                <input type="text" class="styled-input time-picker check-out-input cursor-pointer" placeholder="--:-- --" readonly>
                            </td>

                            <td class="px-6 py-4">
                                <select class="styled-input font-medium text-green-600 status-select" onchange="updateStatusColor(this)">
                                    <option value="Present" class="text-green-600 font-semibold" selected>Present</option>
                                    <option value="Absent" class="text-red-600 font-semibold">Absent</option>
                                    <option value="Half Day" class="text-yellow-600 font-semibold">Half Day</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button type="button" onclick="saveEntry(this)" class="bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-600 hover:text-white px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 mx-auto">
                                    <i data-lucide="save" class="w-3.5 h-3.5"></i> Save
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <button type="button" onclick="addNewRow()" class="text-[#1b5a5a] text-sm font-bold flex items-center gap-2 hover:underline">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Another Row
                </button>
            </div>
        </div>

        <div id="recentSection" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden <?= count($recent_attendance) > 0 ? '' : 'hidden' ?>">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center gap-2">
                <i data-lucide="history" class="w-5 h-5 text-[#1b5a5a]"></i>
                <h3 class="font-bold text-slate-800">Recently Saved Entries</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-100">
                            <th class="px-6 py-3 font-semibold">#</th>
                            <th class="px-6 py-3 font-semibold">Name</th>
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Check-In</th>
                            <th class="px-6 py-3 font-semibold">Check-Out</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Time Saved</th>
                        </tr>
                    </thead>
                    <tbody id="recentTableBody" class="divide-y divide-gray-50">
                        <?php 
                        $count = 1;
                        foreach($recent_attendance as $record): 
                            $statusBadge = '';
                            if ($record['status'] === 'Present') $statusBadge = '<span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-md font-bold text-xs">Present</span>';
                            if ($record['status'] === 'Absent') $statusBadge = '<span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-md font-bold text-xs">Absent</span>';
                            if ($record['status'] === 'Half Day') $statusBadge = '<span class="px-2.5 py-1 bg-yellow-100 text-yellow-700 rounded-md font-bold text-xs">Half Day</span>';
                            
                            $checkInDisp = formatTimeForDisplay($record['check_in']);
                            $checkOutDisp = formatTimeForDisplay($record['check_out']);
                            $timeSaved = date("h:i:s A", strtotime($record['created_at']));
                            $attDate = date("d-m-Y", strtotime($record['attendance_date'])); 
                        ?>
                        <tr class="bg-white hover:bg-gray-50 transition">
                            <td class="px-6 py-3 text-gray-500 text-sm serial-number"><?= $count++ ?></td>
                            <td class="px-6 py-3 font-semibold text-slate-800 text-sm"><?= htmlspecialchars($record['name']) ?></td>
                            <td class="px-6 py-3 text-sm text-gray-600 font-medium"><?= $attDate ?></td>
                            <td class="px-6 py-3 text-sm text-gray-600 font-medium"><?= $checkInDisp ?></td>
                            <td class="px-6 py-3 text-sm text-gray-600 font-medium"><?= $checkOutDisp ?></td>
                            <td class="px-6 py-3"><?= $statusBadge ?></td>
                            <td class="px-6 py-3 text-xs text-gray-400 font-medium"><?= $timeSaved ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<div id="addEmployeeModal" class="modal-overlay fixed inset-0 z-[100] items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform" id="modalContent">
        <div class="px-6 py-4 bg-[#1b5a5a] text-white flex justify-between items-center">
            <h3 class="font-bold text-lg">Add External Staff</h3>
            <button onclick="closeAddModal()" class="text-teal-200 hover:text-white transition"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name *</label>
                <input type="text" id="newStaffName" class="styled-input" placeholder="e.g. Ramesh Kumar">
            </div>
            <div class="mb-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Category / Role (Optional)</label>
                <input type="text" id="newStaffRole" class="styled-input" placeholder="e.g. Plumber, Vendor">
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm font-bold hover:bg-gray-50">Cancel</button>
                <button type="button" onclick="saveNewStaff()" id="saveStaffBtn" class="px-4 py-2 bg-[#1b5a5a] text-white rounded-lg text-sm font-bold hover:bg-[#144d4d] shadow-md flex items-center gap-2">Add Staff</button>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    // INITIALIZE FLATPICKR ON LOAD
    function initTimePickers() {
        flatpickr(".time-picker", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "h:i K", // 12 Hour format (e.g. 02:30 PM)
            time_24hr: false
        });
    }
    document.addEventListener("DOMContentLoaded", initTimePickers);

    function toggleExportMenu() { document.getElementById('exportMenu').classList.toggle('show'); }
    document.addEventListener('click', (e) => { if (!e.target.closest('.relative')) { document.getElementById('exportMenu').classList.remove('show'); } });

    function updateStatusColor(select) {
        select.className = "styled-input font-medium text-gray-700 status-select"; 
        if(select.value === "Present") select.classList.add("text-green-600");
        if(select.value === "Absent") select.classList.add("text-red-600");
        if(select.value === "Half Day") select.classList.add("text-yellow-600");
    }

    // Dynamic Row Logic
    function addNewRow() {
        const tableBody = document.getElementById('tableBody');
        const rowCount = tableBody.children.length + 1;
        const existingSelect = document.querySelector('.employee-select').innerHTML;
        
        const todayStr = new Date().toISOString().split('T')[0];

        const tr = document.createElement('tr');
        tr.className = "hover:bg-slate-50 transition";
        tr.innerHTML = `
            <td class="px-6 py-4 text-center text-gray-500 font-medium sno-cell">${rowCount}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <select class="styled-input employee-select">${existingSelect}</select>
                    <button type="button" onclick="openAddModal()" class="bg-[#1b5a5a] text-white p-2 rounded-md hover:bg-[#144d4d] transition shadow-sm"><i data-lucide="plus" class="w-4 h-4"></i></button>
                </div>
            </td>
            <td class="px-6 py-4">
                <input type="date" class="styled-input text-gray-700 attendance-date-input" value="${todayStr}">
            </td>
            <td class="px-6 py-4">
                <input type="text" class="styled-input time-picker check-in-input cursor-pointer" placeholder="--:-- --" readonly>
            </td>
            <td class="px-6 py-4">
                <input type="text" class="styled-input time-picker check-out-input cursor-pointer" placeholder="--:-- --" readonly>
            </td>
            <td class="px-6 py-4">
                <select class="styled-input font-medium text-green-600 status-select" onchange="updateStatusColor(this)">
                    <option value="Present" class="text-green-600 font-semibold" selected>Present</option>
                    <option value="Absent" class="text-red-600 font-semibold">Absent</option>
                    <option value="Half Day" class="text-yellow-600 font-semibold">Half Day</option>
                </select>
            </td>
            <td class="px-6 py-4 text-center">
                <button type="button" onclick="saveEntry(this)" class="bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-600 hover:text-white px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 mx-auto">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i> Save
                </button>
            </td>
        `;
        tableBody.appendChild(tr);
        lucide.createIcons();
        
        // Re-initialize Flatpickr for the new row's inputs
        initTimePickers();
    }

    const modal = document.getElementById('addEmployeeModal');
    const modalContent = document.getElementById('modalContent');
    function openAddModal() { modal.classList.add('show'); setTimeout(() => modalContent.classList.remove('scale-95'), 10); document.getElementById('newStaffName').focus(); }
    function closeAddModal() { modalContent.classList.add('scale-95'); setTimeout(() => modal.classList.remove('show'), 200); document.getElementById('newStaffName').value = ''; document.getElementById('newStaffRole').value = ''; }

    function saveNewStaff() {
        const name = document.getElementById('newStaffName').value.trim();
        const role = document.getElementById('newStaffRole').value.trim();
        if (name === "") { Swal.fire('Required', 'Please enter the staff name.', 'warning'); return; }

        const btn = document.getElementById('saveStaffBtn');
        btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Adding...`;
        lucide.createIcons();

        const formData = new FormData();
        formData.append('action', 'add_staff');
        formData.append('name', name);
        formData.append('role', role);

        fetch('', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = 'Add Staff';
            if (data.status === 'success') {
                const allSelects = document.querySelectorAll('.employee-select');
                allSelects.forEach(select => {
                    const option = document.createElement('option');
                    option.value = data.id;
                    option.text = data.name;
                    select.appendChild(option);
                });
                const lastSelect = allSelects[allSelects.length - 1];
                lastSelect.value = data.id;
                closeAddModal();
                Swal.fire({ title: 'Added!', text: `${data.name} added.`, icon: 'success', timer: 1500, showConfirmButton: false });
            } else { Swal.fire('Error', data.message, 'error'); }
        });
    }

    // --- CONVERTS "02:30 PM" to "14:30:00" FOR DATABASE SAVE ---
    function convertTo24Hour(time12h) {
        if (!time12h) return '';
        const [time, modifier] = time12h.split(' ');
        let [hours, minutes] = time.split(':');
        if (hours === '12') hours = '00';
        if (modifier === 'PM') hours = parseInt(hours, 10) + 12;
        return `${hours.toString().padStart(2, '0')}:${minutes}:00`;
    }

    // EXPORTS
    function formatTimeForExport(timeString) {
        if (!timeString || timeString === '00:00:00') return '-';
        const [hourString, minute] = timeString.split(':');
        let hour = parseInt(hourString, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        hour = hour ? hour : 12; 
        const formattedHour = hour < 10 ? '0' + hour : hour;
        return `${formattedHour}:${minute} ${ampm}`;
    }

    function exportData(format) {
        const date = document.getElementById('exportDate').value; 
        document.getElementById('exportMenu').classList.remove('show');
        
        Swal.fire({ title: 'Generating File...', text: 'Fetching data from the database.', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        const formData = new FormData();
        formData.append('action', 'fetch_export_data');
        formData.append('date', date);

        fetch('', { method: 'POST', body: formData }).then(response => response.json()).then(res => {
            if (res.status === 'success') {
                if (res.data.length === 0) { Swal.fire('No Data Found', `No attendance was recorded for ${date}.`, 'info'); return; }
                const headers = [['S.No', 'Employee Name', 'Check-In', 'Check-Out', 'Status']];
                const rows = res.data.map((item, index) => [ index + 1, item.name, formatTimeForExport(item.check_in), formatTimeForExport(item.check_out), item.status ]);
                if (format === 'excel') generateExcel(headers, rows, date); else if (format === 'pdf') generatePDF(headers, rows, date);
            } else { Swal.fire('Error', res.message, 'error'); }
        });
    }

    function generateExcel(headers, rows, date) {
        const wb = XLSX.utils.book_new();
        const wsData = headers.concat(rows);
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        XLSX.utils.book_append_sheet(wb, ws, "Attendance");
        XLSX.writeFile(wb, `External_Attendance_${date}.xlsx`);
        Swal.close();
    }

    function generatePDF(headers, rows, date) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(16);
        doc.text("External Staff Attendance Report", 14, 20);
        doc.setFontSize(11);
        doc.text(`Date: ${date}`, 14, 28);
        doc.autoTable({ head: headers, body: rows, startY: 35, theme: 'grid', headStyles: { fillColor: [27, 90, 90] } });
        doc.save(`External_Attendance_${date}.pdf`);
        Swal.close();
    }

    // SAVING ATTENDANCE ENTRY
    function saveEntry(btn) {
        const row = btn.closest('tr');
        const empSelect = row.querySelector('.employee-select');
        const status = row.querySelector('.status-select').value;
        const rowDateInput = row.querySelector('.attendance-date-input').value; 
        
        // Grab the display values (e.g., "02:30 PM") directly from inputs
        const displayCheckIn = row.querySelector('.check-in-input').value;
        const displayCheckOut = row.querySelector('.check-out-input').value;
        
        // Convert to DB values (e.g., "14:30:00")
        const dbCheckIn = convertTo24Hour(displayCheckIn);
        const dbCheckOut = convertTo24Hour(displayCheckOut);

        if (!empSelect.value) { Swal.fire('Missing Details', 'Please select an employee name first.', 'error'); return; }
        if (!rowDateInput) { Swal.fire('Missing Date', 'Please select a valid Date for this entry.', 'warning'); return; }
        if (!dbCheckIn && status !== 'Absent') { Swal.fire('Missing Time', 'Please enter a Check-In time.', 'warning'); return; }

        const empName = empSelect.options[empSelect.selectedIndex].text;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Saving...`;
        btn.classList.replace('bg-indigo-50', 'bg-indigo-600');
        btn.classList.replace('text-indigo-700', 'text-white');
        lucide.createIcons();

        const formData = new FormData();
        formData.append('action', 'save_attendance');
        formData.append('emp_id', empSelect.value);
        formData.append('date', rowDateInput); 
        formData.append('check_in', dbCheckIn);
        formData.append('check_out', dbCheckOut);
        formData.append('status', status);

        fetch('', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                btn.innerHTML = `<i data-lucide="check" class="w-3.5 h-3.5"></i> Saved`;
                btn.classList.replace('bg-indigo-600', 'bg-green-600');
                btn.classList.replace('border-indigo-200', 'border-green-600');
                lucide.createIcons();
                
                addRecordToRecentList(empName, rowDateInput, displayCheckIn || '-', displayCheckOut || '-', status);

                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.replace('bg-green-600', 'bg-indigo-50');
                    btn.classList.replace('text-white', 'text-indigo-700');
                    btn.classList.replace('border-green-600', 'border-indigo-200');
                    
                    // Reset fields
                    empSelect.value = "";
                    row.querySelector('.check-in-input')._flatpickr.clear();
                    row.querySelector('.check-out-input')._flatpickr.clear();
                    updateStatusColor(row.querySelector('.status-select')); 
                    lucide.createIcons();
                }, 1500);
            } else {
                btn.innerHTML = originalHtml;
                btn.classList.replace('bg-indigo-600', 'bg-indigo-50');
                btn.classList.replace('text-white', 'text-indigo-700');
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function addRecordToRecentList(name, dateVal, checkInDisplay, checkOutDisplay, status) {
        document.getElementById('recentSection').classList.remove('hidden');
        const tbody = document.getElementById('recentTableBody');

        const dParts = dateVal.split('-');
        const displayDate = `${dParts[2]}-${dParts[1]}-${dParts[0]}`;

        let statusBadge = '';
        if (status === 'Present') statusBadge = '<span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-md font-bold text-xs">Present</span>';
        if (status === 'Absent') statusBadge = '<span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-md font-bold text-xs">Absent</span>';
        if (status === 'Half Day') statusBadge = '<span class="px-2.5 py-1 bg-yellow-100 text-yellow-700 rounded-md font-bold text-xs">Half Day</span>';

        const tr = document.createElement('tr');
        tr.className = "bg-white hover:bg-gray-50 transition";
        const now = new Date();
        const timeSaved = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        tr.innerHTML = `
            <td class="px-6 py-3 text-gray-500 text-sm serial-number">*</td>
            <td class="px-6 py-3 font-semibold text-slate-800 text-sm">${name}</td>
            <td class="px-6 py-3 text-sm text-gray-600 font-medium">${displayDate}</td>
            <td class="px-6 py-3 text-sm text-gray-600 font-medium">${checkInDisplay}</td>
            <td class="px-6 py-3 text-sm text-gray-600 font-medium">${checkOutDisplay}</td>
            <td class="px-6 py-3">${statusBadge}</td>
            <td class="px-6 py-3 text-xs text-gray-400 font-medium">${timeSaved}</td>
        `;
        tbody.insertBefore(tr, tbody.firstChild);

        Array.from(tbody.children).forEach((row, index) => {
            row.querySelector('.serial-number').innerText = index + 1;
        });
    }
</script>

</body>
</html>