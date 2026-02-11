<?php
// 1. SESSION START & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR INCLUDE
// This logic finds sidebars.php regardless of which folder you are in.
$sidebarPath = __DIR__ . '/../sidebars.php'; 
if (!file_exists($sidebarPath)) {
    // Fallback if file structure is different
    $sidebarPath = 'sidebars.php';
}

// 3. CHECK LOGIN
if (!isset($_SESSION['user_id'])) { 
    // Redirect to login (Adjust path as needed)
    header("Location: ../index.php"); 
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaves - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* --- GLOBAL VARIABLES & RESET --- */
        :root {
            --primary: #f97316; /* Orange */
            --primary-hover: #ea580c;
            --bg-body: #f8f9fa;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
            --sidebar-width: 95px; /* Default fallback */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT ADJUSTMENT --- */
        .main-content {
            margin-left: var(--primary-sidebar-width, 95px); /* Uses sidebar variable */
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; gap: 15px; flex-wrap: wrap;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb {
            display: flex; align-items: center; font-size: 13px; color: var(--text-muted);
            gap: 8px; margin-top: 5px;
        }
        .header-actions { display: flex; gap: 10px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; font-size: 14px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f1f5f9; }
        .btn-primary {
            background-color: var(--primary); color: white; border-color: var(--primary);
        }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* --- STATS CARDS (Responsive Grid) --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px;
            position: relative; overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
        .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .stat-badge {
            display: inline-block; padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 600;
        }
        /* Card Decorations */
        .card-decoration {
            position: absolute; right: -20px; top: 20%; transform: translateY(-50%);
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; opacity: 0.15;
        }
        /* Specific Colors */
        .card-annual .stat-badge { background: #eefcfd; color: #16636B; }
        .card-annual .stat-value { color: #16636B; }
        .card-annual .card-decoration { background: #16636B; color: #16636B; opacity: 1; } /* Icon bg */
        .card-annual .card-decoration i { color: white; position: relative; z-index: 2; }
        .card-annual .card-decoration::before { content:''; position: absolute; width:100%; height:100%; border-radius:50%; z-index:1; }

        .card-medical .stat-badge { background: #dbeafe; color: #2563eb; }
        .card-medical .card-decoration { background: #3b82f6; }
        .card-casual .stat-badge { background: #f3e8ff; color: #9333ea; }
        .card-casual .card-decoration { background: #a855f7; }
        .card-other .stat-badge { background: #fce7f3; color: #db2777; }
        .card-other .card-decoration { background: #ec4899; }
        
        .card-icon { width: 24px; height: 24px; color: white; }

        /* --- LIST SECTION --- */
        .list-section {
            background: white; border-radius: 12px; border: 1px solid var(--border);
            padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .list-header {
            display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .list-title { font-size: 18px; font-weight: 700; margin-right: auto; }
        .badge-pill { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-orange { background: #ffedd5; color: #c2410c; }
        .badge-cyan { background: #ecfeff; color: #0e7490; }

        /* --- RESPONSIVE FILTERS --- */
        .filters-row {
            display: flex; gap: 12px; margin-bottom: 20px; align-items: center;
        }
        
        .input-group {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px; background: white;
            color: var(--text-muted); font-size: 13px;
            flex: 1; /* Grow to fill space */
            min-width: 150px;
            transition: border 0.2s;
        }
        .input-group:focus-within { border-color: var(--primary); }
        .input-group input, .input-group select {
            border: none; outline: none; color: var(--text-main); font-size: 13px;
            width: 100%; background: transparent; margin-left: 8px; cursor: pointer;
        }

        /* Table */
        .table-container { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { text-align: left; font-size: 12px; color: var(--text-muted); padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        td { font-size: 13px; color: #334155; padding: 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .status-badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        /* --- MODAL (Exact Replica of Screenshot) --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000;
            align-items: center; justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-box {
            background: white; width: 650px; max-width: 95%;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex; flex-direction: column;
            overflow: hidden;
        }

        /* Modal Header */
        .modal-header {
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            background: #fff;
        }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }
        .close-icon { cursor: pointer; color: #94a3b8; transition: 0.2s; }
        .close-icon:hover { color: #0f172a; transform: rotate(90deg); }

        /* Modal Body */
        .modal-body { padding: 24px; overflow-y: auto; max-height: 70vh; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }

        .form-group label {
            display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #334155;
        }
        .form-control {
            width: 100%; padding: 12px;
            border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 14px; color: #1e293b;
            box-sizing: border-box; outline: none; transition: border 0.2s;
            background: #fff;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        .form-control-readonly { background-color: #f1f5f9; cursor: not-allowed; color: #64748b; }

        /* Modal Footer */
        .modal-footer {
            padding: 16px 24px; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 12px; background: #fff;
        }

        /* --- RESPONSIVE MEDIA QUERIES --- */
        @media (max-width: 1024px) {
            .filters-row { flex-wrap: wrap; }
            .input-group { flex: 1 1 40%; /* 2 per row */ }
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; justify-content: space-between; margin-top: 10px; }
            
            /* Responsive Filters: Stack them properly */
            .filters-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .input-group { min-width: 0; width: 100%; }
            .sort-group { grid-column: span 2; }
            
            .modal-box { width: 100%; height: 100%; border-radius: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }

        @media (max-width: 480px) {
            .filters-row { grid-template-columns: 1fr; } /* Stack vertically */
            .sort-group { grid-column: span 1; }
            .list-title { width: 100%; margin-bottom: 10px; }
        }
    </style>
</head>
<body>

    <?php 
    if (file_exists($sidebarPath)) { include($sidebarPath); } 

    else { echo "<div style='background:red; color:white; padding:10px;'>Error: sidebars.php not found!</div>"; } 
    ?>
    <?php include '../header.php'; ?> 
    <div class="main-content" id="mainContent">
        
        <div class="page-header">
            <div class="header-title">
                <h1>Leaves</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px; height:14px;"></i>
                    <span>/</span>
                    <span>Attendance</span>
                    <span>/</span>
                    <span class="active" style="color:#0f172a; font-weight:600;">Leaves</span>
                </div>
            </div>

            <div class="header-actions">
                <button class="btn">
                    <i data-lucide="download" style="width:16px;"></i> Export
                </button>
                <button class="btn btn-primary" onclick="openModal()">
                    <i data-lucide="plus-circle" style="width:16px;"></i> Add Leave
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-annual">
                <div class="stat-title">Annual Leaves</div>
                <div class="stat-value">05</div>
                <div class="stat-badge">Remaining : 07</div>
                <div class="card-decoration"><i data-lucide="calendar" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-medical">
                <div class="stat-title">Medical Leaves</div>
                <div class="stat-value">11</div>
                <div class="stat-badge">Remaining : 01</div>
                <div class="card-decoration"><i data-lucide="syringe" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-casual">
                <div class="stat-title">Casual Leaves</div>
                <div class="stat-value">02</div>
                <div class="stat-badge">Remaining : 10</div>
                <div class="card-decoration"><i data-lucide="hexagon" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-other">
                <div class="stat-title">Other Leaves</div>
                <div class="stat-value">07</div>
                <div class="stat-badge">Remaining : 05</div>
                <div class="card-decoration"><i data-lucide="package-plus" class="card-icon"></i></div>
            </div>
        </div>

        <div class="list-section">
            <div class="list-header">
                <span class="list-title">Leave List</span>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <span class="badge-pill badge-orange">Total : 48</span>
                    <span class="badge-pill badge-cyan">Remaining : 23</span>
                </div>
            </div>

            <div class="filters-row">
                <div class="input-group">
                    <i data-lucide="calendar-days" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" placeholder="Filter by Date..." onfocus="(this.type='date')" onblur="(this.type='text')">
                </div>
                
                <div class="input-group">
                    <select id="filterType" onchange="filterTable()">
                        <option value="">All Leave Types</option>
                        <option value="Annual">Annual Leave</option>
                        <option value="Medical">Medical Leave</option>
                        <option value="Casual">Casual Leave</option>
                    </select>
                    <i data-lucide="chevron-down" style="width:14px; color:#94a3b8;"></i>
                </div>

                <div class="input-group">
                    <select id="filterStatus" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                    <i data-lucide="chevron-down" style="width:14px; color:#94a3b8;"></i>
                </div>

                <div class="input-group sort-group" style="flex: 0 0 auto;">
                    <span style="font-size:12px; color:#94a3b8; white-space:nowrap; margin-right:5px;">Sort:</span>
                    <select style="width:auto; font-weight:500;">
                        <option>Last 7 Days</option>
                        <option>Last 30 Days</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table id="leavesTable">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Approved By</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span style="font-weight:600;">Medical Leave</span></td>
                            <td>02/05/2026 - 02/06/2026</td>
                            <td>Fever and Flu symptoms</td>
                            <td>Sarah Manager</td>
                            <td><span class="status-badge status-approved"><i data-lucide="check" style="width:10px;"></i> Approved</span></td>
                            <td><button class="btn" style="padding:5px;"><i data-lucide="more-vertical" style="width:16px;"></i></button></td>
                        </tr>
                        <tr>
                            <td><span style="font-weight:600;">Casual Leave</span></td>
                            <td>02/10/2026</td>
                            <td>Personal family matter</td>
                            <td>--</td>
                            <td><span class="status-badge status-pending"><i data-lucide="clock" style="width:10px;"></i> Pending</span></td>
                            <td><button class="btn" style="padding:5px;"><i data-lucide="more-vertical" style="width:16px;"></i></button></td>
                        </tr>
                        <tr>
                            <td><span style="font-weight:600;">Annual Leave</span></td>
                            <td>02/15/2026 - 02/20/2026</td>
                            <td>Vacation trip</td>
                            <td>John Doe</td>
                            <td><span class="status-badge status-rejected"><i data-lucide="x" style="width:10px;"></i> Rejected</span></td>
                            <td><button class="btn" style="padding:5px;"><i data-lucide="more-vertical" style="width:16px;"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leaveModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Add Leave</h3>
                <div class="close-icon" onclick="closeModal()">
                    <i data-lucide="x-circle" style="width:24px; height:24px;"></i>
                </div>
            </div>
            
            <div class="modal-body">
                <form id="addLeaveForm">
                    <div class="form-grid">
                        
                        <div class="form-group full-width">
                            <label>Leave Type</label>
                            <select class="form-control">
                                <option>Select</option>
                                <option>Annual Leave</option>
                                <option>Medical Leave</option>
                                <option>Casual Leave</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>From</label>
                            <input type="date" id="dateFrom" class="form-control" onchange="calculateDays()">
                        </div>
                        <div class="form-group">
                            <label>To</label>
                            <input type="date" id="dateTo" class="form-control" onchange="calculateDays()">
                        </div>

                        <div class="form-group">
                            <label>No of Days</label>
                            <input type="text" id="noOfDays" class="form-control form-control-readonly" placeholder="0" readonly>
                        </div>
                        <div class="form-group">
                            <label>Remaining Days</label>
                            <input type="text" class="form-control form-control-readonly" value="8" readonly>
                        </div>

                        <div class="form-group full-width">
                            <label>Reason</label>
                            <textarea class="form-control" rows="4"></textarea>
                        </div>

                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="alert('Leave Request Submitted!')">Add Leave</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        // --- Modal Logic ---
        function openModal() {
            document.getElementById('leaveModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        function closeModal() {
            document.getElementById('leaveModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            // Reset form
            document.getElementById('addLeaveForm').reset();
            document.getElementById('noOfDays').value = '';
        }
        // Close on outside click
        document.getElementById('leaveModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // --- Date Calculation Logic ---
        function calculateDays() {
            const start = document.getElementById('dateFrom').value;
            const end = document.getElementById('dateTo').value;
            const output = document.getElementById('noOfDays');

            if(start && end) {
                const d1 = new Date(start);
                const d2 = new Date(end);
                
                // Calculate difference in time
                const diffTime = Math.abs(d2 - d1);
                // Calculate diff in days (divide by milliseconds per day)
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include start date

                if(d2 < d1) {
                    output.value = "Invalid Date Range";
                    output.style.color = "red";
                } else {
                    output.value = diffDays;
                    output.style.color = "#1e293b";
                }
            } else {
                output.value = "";
            }
        }

        // --- Table Filtering Logic ---
        function filterTable() {
            const typeFilter = document.getElementById('filterType').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const table = document.getElementById('leavesTable');
            const tr = table.getElementsByTagName('tr');

            // Loop through all table rows, and hide those who don't match the search query
            for (let i = 1; i < tr.length; i++) { // Start at 1 to skip header
                const typeTd = tr[i].getElementsByTagName("td")[0];
                const statusTd = tr[i].getElementsByTagName("td")[4];
                
                if (typeTd && statusTd) {
                    const typeValue = typeTd.textContent || typeTd.innerText;
                    const statusValue = statusTd.textContent || statusTd.innerText;

                    const typeMatch = typeValue.toLowerCase().indexOf(typeFilter) > -1;
                    const statusMatch = statusValue.toLowerCase().indexOf(statusFilter) > -1;

                    if (typeMatch && statusMatch) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>