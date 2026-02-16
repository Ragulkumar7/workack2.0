<?php
// 1. SESSION START & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR INCLUDE
$sidebarPath = __DIR__ . '/../sidebars.php'; 
if (!file_exists($sidebarPath)) {
    $sidebarPath = 'sidebars.php';
}

// 3. CHECK LOGIN
if (!isset($_SESSION['user_id'])) { 
    // header("Location: ../index.php"); 
    // exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* --- GLOBAL VARIABLES (Matched to your theme) --- */
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --bg-body: #f8f9fa;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT --- */
        .main-content {
            margin-left: 95px; /* Matches your sidebar */
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; gap: 15px; flex-wrap: wrap;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #0f172a; }
        .breadcrumb {
            display: flex; align-items: center; font-size: 13px; color: var(--text-muted);
            gap: 8px; margin-top: 5px;
        }

        /* --- STATS GRID (Manager View) --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px;
            border: 1px solid var(--border);
            position: relative; overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
        }
        .stat-info h4 { font-size: 13px; color: var(--text-muted); margin: 0 0 5px 0; font-weight: 500; }
        .stat-info h2 { font-size: 28px; font-weight: 700; margin: 0; color: var(--text-main); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        
        .card-pending .stat-icon { background: #fff7ed; color: #f97316; }
        .card-approved .stat-icon { background: #f0fdf4; color: #16a34a; }
        .card-rejected .stat-icon { background: #fef2f2; color: #dc2626; }
        .card-total .stat-icon { background: #eff6ff; color: #2563eb; }

        /* --- TABLE SECTION --- */
        .list-section {
            background: white; border-radius: 12px; border: 1px solid var(--border);
            padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        /* Filters */
        .filters-row {
            display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .search-box {
            flex: 2; min-width: 250px;
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px;
        }
        .search-box input { border: none; outline: none; width: 100%; font-size: 13px; margin-left: 8px; }
        
        .filter-select {
            flex: 1; min-width: 150px;
            padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; color: var(--text-main); outline: none; cursor: pointer;
        }

        /* Table Styling */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { 
            text-align: left; font-size: 12px; color: #475569; padding: 14px 20px; 
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; 
        }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }

        /* Employee Profile in Table */
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-avatar {
            width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
            background: #e2e8f0; display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #64748b;
        }
        .emp-info { display: flex; flex-direction: column; }
        .emp-name { font-weight: 600; color: #0f172a; }
        .emp-dept { font-size: 11px; color: #64748b; }

        /* Badges */
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .status-Pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .status-Approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-Rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .leave-type { 
            font-weight: 500; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: #334155; font-size: 12px; 
        }

        /* Action Buttons */
        .action-container { display: flex; gap: 8px; }
        .btn-icon {
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border);
            background: white; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s; color: var(--text-muted);
        }
        .btn-icon:hover { background: #f8fafc; color: var(--primary); }
        .btn-approve:hover { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .btn-reject:hover { background: #fee2e2; color: #991b1b; border-color: #fecaca; }

        /* --- MODAL --- */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px);
        }
        .modal-overlay.active { display: flex; animation: fadeUp 0.2s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .modal-box {
            background: white; width: 600px; max-width: 95%; border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden;
        }
        .modal-header {
            padding: 16px 24px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center; background: #fff;
        }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
        
        .modal-body { padding: 24px; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .detail-item label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
        .detail-item p { margin: 0; font-size: 14px; font-weight: 500; color: #1e293b; }
        
        .reason-box { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; }
        .reason-box p { font-size: 13px; color: #334155; line-height: 1.5; margin: 0; }

        .modal-footer {
            padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .btn { padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        .btn-outline { background: white; border: 1px solid var(--border); color: #334155; }
        .btn-green { background: var(--success); color: white; }
        .btn-red { background: var(--danger); color: white; }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .filters-row { flex-direction: column; }
            .search-box, .filter-select { width: 100%; }
            .detail-grid { grid-template-columns: 1fr; gap: 15px; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) { include($sidebarPath); } ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1>Leave Approvals</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span> Leaves <span>/</span> <span style="font-weight:600; color:#0f172a;">Approvals</span>
                </div>
            </div>
            </div>

        <div class="stats-grid">
            <div class="stat-card card-pending">
                <div class="stat-info">
                    <h4>Pending Requests</h4>
                    <h2>04</h2>
                </div>
                <div class="stat-icon"><i data-lucide="clock"></i></div>
            </div>
            <div class="stat-card card-approved">
                <div class="stat-info">
                    <h4>Approved Today</h4>
                    <h2>12</h2>
                </div>
                <div class="stat-icon"><i data-lucide="check-circle-2"></i></div>
            </div>
            <div class="stat-card card-rejected">
                <div class="stat-info">
                    <h4>Rejected Today</h4>
                    <h2>01</h2>
                </div>
                <div class="stat-icon"><i data-lucide="x-circle"></i></div>
            </div>
            <div class="stat-card card-total">
                <div class="stat-info">
                    <h4>Total Employees</h4>
                    <h2>48</h2>
                </div>
                <div class="stat-icon"><i data-lucide="users"></i></div>
            </div>
        </div>

        <div class="list-section">
            
            <div class="filters-row">
                <div class="search-box">
                    <i data-lucide="search" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" id="searchInput" placeholder="Search employee or ID..." onkeyup="filterTable()">
                </div>
                <select class="filter-select" id="typeFilter" onchange="filterTable()">
                    <option value="">All Leave Types</option>
                    <option value="Medical">Medical Leave</option>
                    <option value="Casual">Casual Leave</option>
                    <option value="Annual">Annual Leave</option>
                </select>
                <select class="filter-select" id="statusFilter" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="table-responsive">
                <table id="approvalTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Duration</th>
                            <th>Days</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="emp-profile">
                                    <img src="https://i.pravatar.cc/150?u=1" class="emp-avatar" alt="User">
                                    <div class="emp-info">
                                        <span class="emp-name">John Doe</span>
                                        <span class="emp-dept">Web Development</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="leave-type">Medical Leave</span></td>
                            <td>12 Feb - 14 Feb 2026</td>
                            <td>03</td>
                            <td>10 Feb 2026</td>
                            <td><span class="status-badge status-Pending"><i data-lucide="clock" style="width:10px;"></i> Pending</span></td>
                            <td>
                                <div class="action-container">
                                    <button class="btn-icon btn-approve" onclick="quickAction(this, 'Approved')" title="Approve"><i data-lucide="check" style="width:16px;"></i></button>
                                    <button class="btn-icon btn-reject" onclick="quickAction(this, 'Rejected')" title="Reject"><i data-lucide="x" style="width:16px;"></i></button>
                                    <button class="btn-icon" onclick="viewDetails('John Doe', 'Medical Leave', '12 Feb - 14 Feb 2026', 'Fever and viral infection.', '3')" title="View Details"><i data-lucide="eye" style="width:16px;"></i></button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <div class="emp-profile">
                                    <div class="emp-avatar">SS</div>
                                    <div class="emp-info">
                                        <span class="emp-name">Sarah Smith</span>
                                        <span class="emp-dept">Design Team</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="leave-type">Casual Leave</span></td>
                            <td>20 Feb 2026</td>
                            <td>01</td>
                            <td>15 Feb 2026</td>
                            <td><span class="status-badge status-Approved"><i data-lucide="check" style="width:10px;"></i> Approved</span></td>
                            <td>
                                <div class="action-container">
                                    <button class="btn-icon" onclick="viewDetails('Sarah Smith', 'Casual Leave', '20 Feb 2026', 'Personal work at bank.', '1')"><i data-lucide="eye" style="width:16px;"></i></button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="emp-profile">
                                    <img src="https://i.pravatar.cc/150?u=3" class="emp-avatar" alt="User">
                                    <div class="emp-info">
                                        <span class="emp-name">Michael Brown</span>
                                        <span class="emp-dept">Marketing</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="leave-type">Annual Leave</span></td>
                            <td>01 Mar - 05 Mar 2026</td>
                            <td>05</td>
                            <td>10 Feb 2026</td>
                            <td><span class="status-badge status-Pending"><i data-lucide="clock" style="width:10px;"></i> Pending</span></td>
                            <td>
                                <div class="action-container">
                                    <button class="btn-icon btn-approve" onclick="quickAction(this, 'Approved')"><i data-lucide="check" style="width:16px;"></i></button>
                                    <button class="btn-icon btn-reject" onclick="quickAction(this, 'Rejected')"><i data-lucide="x" style="width:16px;"></i></button>
                                    <button class="btn-icon" onclick="viewDetails('Michael Brown', 'Annual Leave', '01 Mar - 05 Mar', 'Family vacation.', '5')"><i data-lucide="eye" style="width:16px;"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="approvalModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Leave Request Details</h3>
                <i data-lucide="x" style="cursor:pointer; color:#94a3b8;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Employee Name</label>
                        <p id="mName">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Leave Type</label>
                        <p id="mType">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Duration</label>
                        <p id="mDate">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Total Days</label>
                        <p id="mDays">--</p>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom:8px;">
                    <label>Employee Reason</label>
                </div>
                <div class="reason-box">
                    <p id="mReason">--</p>
                </div>

                <div class="form-group">
                    <label>Manager Remarks (Optional)</label>
                    <textarea class="form-control" rows="2" placeholder="Add a note..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-red" onclick="modalAction('Rejected')">Reject</button>
                <button class="btn btn-green" onclick="modalAction('Approved')">Approve Request</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // --- FILTER FUNCTIONALITY ---
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const type = document.getElementById('typeFilter').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            
            const rows = document.querySelectorAll('#approvalTable tbody tr');

            rows.forEach(row => {
                const name = row.querySelector('.emp-name').innerText.toLowerCase();
                const lType = row.querySelector('td:nth-child(2)').innerText.toLowerCase();
                const lStatus = row.querySelector('.status-badge').innerText.toLowerCase();

                const matchesSearch = name.includes(search);
                const matchesType = type === '' || lType.includes(type);
                const matchesStatus = status === '' || lStatus.includes(status);

                if (matchesSearch && matchesType && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // --- MODAL LOGIC ---
        const modal = document.getElementById('approvalModal');
        let currentActionRow = null;

        function viewDetails(name, type, date, reason, days) {
            document.getElementById('mName').innerText = name;
            document.getElementById('mType').innerText = type;
            document.getElementById('mDate').innerText = date;
            document.getElementById('mReason').innerText = reason;
            document.getElementById('mDays').innerText = days + " Days";
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // --- ACTION LOGIC (Simulated) ---
        function quickAction(btn, status) {
            const row = btn.closest('tr');
            const statusBadge = row.querySelector('.status-badge');
            
            updateStatusVisuals(statusBadge, status);
            
            // In a real app, perform AJAX request here
            alert(`Request marked as ${status}`);
        }

        function modalAction(status) {
            alert(`Request ${status} successfully!`);
            closeModal();
            // In real app, reload table or update row
        }

        function updateStatusVisuals(badge, status) {
            badge.className = `status-badge status-${status}`;
            if(status === 'Approved') {
                badge.innerHTML = '<i data-lucide="check" style="width:10px;"></i> Approved';
            } else if(status === 'Rejected') {
                badge.innerHTML = '<i data-lucide="x" style="width:10px;"></i> Rejected';
            }
            lucide.createIcons();
        }

        // Close modal on outside click
        modal.addEventListener('click', (e) => {
            if(e.target === modal) closeModal();
        });
    </script>
</body>
</html>