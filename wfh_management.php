<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR INCLUDE
// This logic finds sidebars.php regardless of which folder you are in.
$sidebarPath = __DIR__ . '/../sidebars.php'; 
if (!file_exists($sidebarPath)) {
    $sidebarPath = 'sidebars.php'; 
}

// 3. LOGIN CHECK
if (!isset($_SESSION['user_id'])) { 
    // Uncomment the lines below when you are ready to enforce login
    // header("Location: ../index.php"); 
    // exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WFH Management - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary: #ea580c; 
            --primary-hover: #c2410c;
            --bg-body: #f8f9fa;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --white: #ffffff;
            --sidebar-width: 95px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
            line-height: 1.5;
        }

        .main-content {
            margin-left: var(--primary-sidebar-width, 95px);
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; font-size: 14px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-sm { padding: 6px 10px; font-size: 12px; }

        /* --- MANAGEMENT CARD --- */
        .management-card {
            background: white; border: 1px solid var(--border);
            border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; }

        /* --- FILTERS --- */
        .filters-grid {
            display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 25px; align-items: center;
        }
        
        .filter-group {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px; background: white; 
            flex: 1; min-width: 140px; position: relative; transition: border-color 0.2s;
        }
        .filter-group:hover { border-color: #d1d5db; }
        .filter-group i { color: #9ca3af; width: 16px; height: 16px; flex-shrink: 0; }
        
        .filter-group select, .filter-group input {
            border: none; outline: none; width: 100%; margin-left: 8px; 
            font-size: 13px; color: var(--text-main); background: transparent; cursor: pointer;
        }
        .date-filter { flex: 1.5; min-width: 200px; }

        .table-tools {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 15px; flex-wrap: wrap; gap: 10px;
        }
        .search-container { position: relative; width: 280px; }
        .search-input {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border);
            border-radius: 6px; font-size: 13px; outline: none; transition: border-color 0.2s;
        }
        .search-input:focus { border-color: var(--primary); }

        /* --- TABLE --- */
        .table-responsive { overflow-x: auto; width: 100%; border-radius: 8px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        
        thead { background: #f9fafb; }
        th { 
            text-align: left; padding: 14px 16px; font-size: 12px; 
            font-weight: 600; color: #4b5563; text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }
        td { padding: 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
        
        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary); }

        .emp-cell { display: flex; align-items: center; gap: 12px; }
        .emp-avatar {
            width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
            background: #e5e7eb; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 11px;
        }
        .emp-name { font-weight: 600; color: #111827; }

        .status-pill { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #eff6ff; color: #1e40af; }
        .status-completed { background: #ecfdf5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        /* --- MODALS --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: white; width: 550px; max-width: 95%; 
            border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
            overflow: hidden; animation: modalIn 0.3s ease-out;
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .modal-header { 
            padding: 20px 24px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; }
        
        .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #374151; }
        .form-group label span { color: #ef4444; }
        
        .form-control {
            width: 100%; padding: 12px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;
        }
        .form-control:focus { border-color: var(--primary); }
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #f9fafb; }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .filters-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .filters-grid { display: flex; flex-direction: column; align-items: stretch; }
            .table-tools { flex-direction: column; align-items: stretch; }
            .search-container { width: 100%; }
            .row-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content" id="mainContent">
        
        
        <div class="page-header">
            <div>
                <h1>Work From Home Management</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span>
                    <span>Leaves</span>
                    <span>/</span>
                    <span style="font-weight:600; color:#111827;">Work From Home Management</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn"><i data-lucide="download" style="width:16px;"></i> Export <i data-lucide="chevron-down" style="width:14px;"></i></button>
                <button class="btn btn-primary" onclick="toggleModal('addRequestModal', true)"><i data-lucide="plus-circle" style="width:16px;"></i> Add New Request</button>
            </div>
        </div>

        <div class="management-card">
            <h3 class="card-title">Employee List</h3>
            
            <div class="filters-grid">
                
                <div class="filter-group date-filter">
                    <i data-lucide="calendar"></i>
                    <input type="text" id="dateRangeInput" value="02/01/2026 - 02/07/2026" placeholder="Select dates">
                    <select onchange="updateDateInput(this)" style="width: 20px; margin-left: -20px; opacity: 0; position: absolute; right: 10px; cursor: pointer;">
                        <option value="">Preset Ranges</option>
                        <option value="Today">Today</option>
                        <option value="Yesterday">Yesterday</option>
                        <option value="Last 7 Days">Last 7 Days</option>
                        <option value="Last 30 Days">Last 30 Days</option>
                        <option value="This Year">This Year</option>
                        <option value="Next Year">Next Year</option>
                    </select>
                </div>

                <div class="filter-group">
                    <select id="filterDesignation" onchange="runFilter()">
                        <option value="">Designation</option>
                        <option>Accountant</option>
                        <option>App Developer</option>
                        <option>Technician</option>
                        <option>Web Developer</option>
                        <option>Business Analyst</option>
                        <option>Admin</option>
                        <option>SEO Analyst</option>
                    </select>
                    <i data-lucide="chevron-down" style="width:14px;"></i>
                </div>

                <div class="filter-group">
                    <select id="filterShift" onchange="runFilter()">
                        <option value="">Shift</option>
                        <option>Regular</option>
                        <option>Night</option>
                    </select>
                    <i data-lucide="chevron-down" style="width:14px;"></i>
                </div>

                <div class="filter-group">
                    <select id="filterStatus" onchange="runFilter()">
                        <option value="">Status</option>
                        <option>Approved</option>
                        <option>Pending</option>
                        <option>Completed</option>
                        <option>Rejected</option>
                    </select>
                    <i data-lucide="chevron-down" style="width:14px;"></i>
                </div>

                <div class="filter-group">
                    <span style="font-size:12px; color:#6b7280; white-space:nowrap;">Sort By:</span>
                    <select>
                        <option>Last 7 Days</option>
                        <option>Recently Added</option>
                        <option>Ascending</option>
                        <option>Descending</option>
                    </select>
                </div>
            </div>

            <div class="table-tools">
                <div style="font-size:13px; color:#4b5563;">
                    Row Per Page <select style="border:1px solid #d1d5db; padding:4px; border-radius:4px;"><option>10</option><option>25</option></select> Entries
                </div>
                <div class="search-container">
                    <input type="text" id="mainSearch" class="search-input" placeholder="Search employee ID or name..." onkeyup="runFilter()">
                </div>
            </div>

            <div class="table-responsive">
                <table id="employeeWfhTable">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)"></th>
                            <th>Emp ID <i data-lucide="arrow-up-down" style="width:12px;"></i></th>
                            <th>Name <i data-lucide="arrow-up-down" style="width:12px;"></i></th>
                            <th>Designation <i data-lucide="arrow-up-down" style="width:12px;"></i></th>
                            <th>Shift <i data-lucide="arrow-up-down" style="width:12px;"></i></th>
                            <th>Reason <i data-lucide="arrow-up-down" style="width:12px;"></i></th>
                            <th>Date <i data-lucide="arrow-up-down" style="width:12px;"></i></th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="row-10">
                            <td><input type="checkbox" class="row-checkbox"></td>
                            <td style="font-weight:700;">Emp-010</td>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-avatar" style="background:#fef08a; color:#854d0e;">LB</div>
                                    <span class="emp-name">Lori Broaddus</span>
                                </div>
                            </td>
                            <td>Business Analyst</td>
                            <td>Night</td>
                            <td>Power outage</td>
                            <td>24 Jan 2025</td>
                            <td><span class="status-pill status-approved" id="status-text-10">Approved</span></td>
                            <td>
                                <button class="btn btn-sm" onclick="openApprovalModal(10, 'Lori Broaddus', 'Power outage', 'Approved')">
                                    <i data-lucide="edit-3" style="width:14px;"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <tr id="row-09">
                            <td><input type="checkbox" class="row-checkbox"></td>
                            <td style="font-weight:700;">Emp-009</td>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-avatar" style="background:#ffedd5; color:#9a3412;">CW</div>
                                    <span class="emp-name">Connie Waters</span>
                                </div>
                            </td>
                            <td>Admin</td>
                            <td>Regular</td>
                            <td>Internet issue</td>
                            <td>02 Feb 2025</td>
                            <td><span class="status-pill status-pending" id="status-text-09">Pending</span></td>
                            <td>
                                <button class="btn btn-sm" onclick="openApprovalModal(9, 'Connie Waters', 'Internet issue', 'Pending')">
                                    <i data-lucide="edit-3" style="width:14px;"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <tr id="row-08">
                            <td><input type="checkbox" class="row-checkbox"></td>
                            <td style="font-weight:700;">Emp-008</td>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-avatar" style="background:#fee2e2; color:#991b1b;">RS</div>
                                    <span class="emp-name">Rebecca Smith</span>
                                </div>
                            </td>
                            <td>SEO Analyst</td>
                            <td>Night</td>
                            <td>Mild health issue</td>
                            <td>17 Feb 2025</td>
                            <td><span class="status-pill status-completed" id="status-text-08">Completed</span></td>
                            <td>
                                <button class="btn btn-sm" onclick="openApprovalModal(8, 'Rebecca Smith', 'Mild health issue', 'Completed')">
                                    <i data-lucide="edit-3" style="width:14px;"></i> Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="addRequestModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Add Request</h3>
                <i data-lucide="x" class="close-icon" style="cursor:pointer;" onclick="toggleModal('addRequestModal', false)"></i>
            </div>
            <div class="modal-body">
                <form id="addRequestForm">
                    <div class="form-group">
                        <label>Employee Name <span>*</span></label>
                        <input type="text" class="form-control" placeholder="Enter name">
                    </div>
                    <div class="form-group">
                        <label>Designation <span>*</span></label>
                        <select class="form-control">
                            <option>Select Designation</option>
                            <option>Accountant</option>
                            <option>App Developer</option>
                            <option>Technician</option>
                            <option>Web Developer</option>
                            <option>Business Analyst</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Shift <span>*</span></label>
                        <select class="form-control">
                            <option>Select Shift</option>
                            <option>Regular</option>
                            <option>Night</option>
                        </select>
                    </div>
                    <div class="row-grid">
                        <div class="form-group">
                            <label>Start Date <span>*</span></label>
                            <input type="date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>End Date <span>*</span></label>
                            <input type="date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Reason / Notes <span>*</span></label>
                        <textarea class="form-control" rows="3" placeholder="Explain the requirement..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="toggleModal('addRequestModal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="alert('Request Added Successfully!')">Add Request</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="approvalModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Update Request Status</h3>
                <i data-lucide="x" style="cursor:pointer;" onclick="toggleModal('approvalModal', false)"></i>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editRequestId">
                <div class="form-group">
                    <label>Employee</label>
                    <input type="text" id="editEmpName" class="form-control" readonly style="background:#f9fafb;">
                </div>
                <div class="form-group">
                    <label>Stated Reason</label>
                    <textarea id="editEmpReason" class="form-control" readonly style="background:#f9fafb;" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Set Status <span>*</span></label>
                    <select id="editStatusSelect" class="form-control">
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Completed">Completed</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Manager's Comment</label>
                    <textarea id="editComment" class="form-control" rows="3" placeholder="Add approval/rejection notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="toggleModal('approvalModal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="saveStatusUpdate()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // --- INITIALIZE ICONS ---
        lucide.createIcons();

        // --- MODAL TOGGLE LOGIC ---
        function toggleModal(modalId, show) {
            const modal = document.getElementById(modalId);
            modal.classList.toggle('active', show);
            document.body.style.overflow = show ? 'hidden' : 'auto';
        }

        // --- DATE PRESET LOGIC ---
        function updateDateInput(select) {
            const val = select.value;
            const input = document.getElementById('dateRangeInput');
            if (val) {
                input.value = val;
            }
        }

        // --- SELECT ALL CHECKBOXES ---
        function toggleAllCheckboxes(masterCheckbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = masterCheckbox.checked;
            });
        }

        // --- APPROVAL MODAL DATA LOADING ---
        function openApprovalModal(id, name, reason, status) {
            // Fill modal fields
            document.getElementById('editRequestId').value = id;
            document.getElementById('editEmpName').value = name;
            document.getElementById('editEmpReason').value = reason;
            document.getElementById('editStatusSelect').value = status;
            
            // Show modal
            toggleModal('approvalModal', true);
        }

        // --- SAVE STATUS UPDATE (UI ONLY) ---
        function saveStatusUpdate() {
            const id = document.getElementById('editRequestId').value;
            const newStatus = document.getElementById('editStatusSelect').value;
            
            // Pad ID if needed to match the IDs used in the status-text-XX elements
            const paddedId = id.toString().padStart(2, '0');
            const statusEl = document.getElementById('status-text-' + paddedId);

            if (statusEl) {
                statusEl.innerText = newStatus;
                // Reset classes and add the correct one
                statusEl.className = 'status-pill status-' + newStatus.toLowerCase();
            }

            alert('Status updated successfully for request #' + id);
            toggleModal('approvalModal', false);
        }

        // --- FILTER & SEARCH LOGIC ---
        function runFilter() {
            const search = document.getElementById('mainSearch').value.toUpperCase();
            const desig = document.getElementById('filterDesignation').value.toUpperCase();
            const shift = document.getElementById('filterShift').value.toUpperCase();
            const status = document.getElementById('filterStatus').value.toUpperCase();
            
            const rows = document.getElementById('employeeWfhTable').getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const name = rows[i].cells[2].textContent.toUpperCase();
                const id = rows[i].cells[1].textContent.toUpperCase();
                const dText = rows[i].cells[3].textContent.toUpperCase();
                const sText = rows[i].cells[4].textContent.toUpperCase();
                const stText = rows[i].cells[7].textContent.toUpperCase();

                const matchesSearch = name.includes(search) || id.includes(search);
                const matchesDesig = desig === "" || dText.includes(desig);
                const matchesShift = shift === "" || sText.includes(shift);
                const matchesStatus = status === "" || stText.includes(status);

                rows[i].style.display = (matchesSearch && matchesDesig && matchesShift && matchesStatus) ? "" : "none";
            }
        }
    </script>
</body>
</html>