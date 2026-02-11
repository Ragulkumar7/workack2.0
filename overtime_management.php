<?php
// overtime_management.php - Professional Overtime Oversight for Managers/HR

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    // Redirect if not logged in
    header("Location: index.php"); 
    exit(); 
}

// 2. PATH LOGIC FOR SIDEBAR
$sidebarPath = __DIR__ . '/sidebars.php'; 
if (!file_exists($sidebarPath)) {
    // Check if it's one directory up (common in employee/admin folder structures)
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
}

// 3. SAMPLE OVERTIME DATA (Normally fetched from database)
$overtimeRecords = [
    ["id" => 1, "emp_id" => "EMP-045", "name" => "Sarah Connor", "date" => "14 Jan 2026", "hours" => "4.5", "project" => "Project Alpha", "approver" => "Manager X", "status" => "Accepted"],
    ["id" => 2, "emp_id" => "EMP-012", "name" => "John Smith", "date" => "15 Jan 2026", "hours" => "2.0", "project" => "Client Beta", "approver" => "--", "status" => "Pending"],
    ["id" => 3, "emp_id" => "EMP-088", "name" => "Ellen Ripley", "date" => "16 Jan 2026", "hours" => "5.0", "project" => "Maintenance", "approver" => "Manager Y", "status" => "Accepted"],
    ["id" => 4, "emp_id" => "EMP-033", "name" => "Kyle Reese", "date" => "16 Jan 2026", "hours" => "3.5", "project" => "Project Gamma", "approver" => "Admin", "status" => "Rejected"],
    ["id" => 5, "emp_id" => "EMP-102", "name" => "Arthur Dent", "date" => "17 Jan 2026", "hours" => "1.5", "project" => "Deep Thought", "approver" => "--", "status" => "Pending"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Management - HRMS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-orange: #ff5e3a; 
            --bg-gray: #f8f9fa; 
            --border-color: #edf2f7; 
            --sidebar-width: 95px; 
        }

        body { 
            background-color: var(--bg-gray); 
            font-family: 'Inter', sans-serif; 
            font-size: 13px; 
            color: #333; 
            overflow-x: hidden; 
        }
        
        /* Layout Adjustments for sidebars.php */
        #mainContent { 
            margin-left: var(--sidebar-width); 
            padding: 25px 35px; 
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            #mainContent { margin-left: 0 !important; padding: 15px; }
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.04); background: #fff; }
        
        /* Status Badges */
        .status-pill { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-accepted { background: #e6fffa; color: #38a169; }
        .bg-pending { background: #eef6ff; color: #3182ce; }
        .bg-rejected { background: #fff5f5; color: #e53e3e; }
        
        .btn-orange { background: var(--primary-orange); color: white; border: none; border-radius: 6px; padding: 10px 20px; font-weight: 600; transition: 0.3s; }
        .btn-orange:hover { background: #e54d2e; color: white; transform: translateY(-1px); }

        .table thead th { background: #f9fafb; padding: 15px; border-bottom: 1px solid var(--border-color); color: #4a5568; font-weight: 600; text-transform: uppercase; font-size: 11px; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        
        /* Avatar placeholder */
        .avatar-circle { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #64748b; }

        /* Modal Custom Style */
        .modal-backdrop-blur { background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) { include($sidebarPath); } ?>

    <main id="mainContent">
            <?php include 'header.php'; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Overtime Management</h1>
                <p class="text-slate-500 text-xs mt-1">Review and approve employee additional working hours</p>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-light border text-sm" onclick="exportReport()"><i class="fa-solid fa-download mr-2"></i> Export Logs</button>
                <button class="btn btn-orange text-sm shadow-sm" onclick="openOvertimeModal()"><i class="fa-solid fa-plus mr-2"></i> Add Overtime</button>
            </div>
        </div>

        <div class="row g-4 mb-6">
            <div class="col-6 col-md-3">
                <div class="card p-4 text-center border-b-4 border-blue-400">
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">OT Employees</p>
                    <h2 class="text-3xl font-bold text-slate-800">12</h2>
                    <span class="text-[10px] text-emerald-500 font-bold"><i class="fa fa-arrow-up"></i> 4% vs Last Week</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-4 text-center border-b-4 border-emerald-400">
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">Total OT Hours</p>
                    <h2 class="text-3xl font-bold text-slate-800">118h</h2>
                    <span class="text-[10px] text-slate-400">Monthly Accumulation</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-4 text-center border-b-4 border-orange-400">
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">Pending Requests</p>
                    <h2 class="text-3xl font-bold text-orange-500">23</h2>
                    <span class="text-[10px] text-orange-400 font-bold">Action Required</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-4 text-center border-b-4 border-red-400">
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-1">Rejected Requests</p>
                    <h2 class="text-3xl font-bold text-red-500">05</h2>
                    <span class="text-[10px] text-slate-400">This Month</span>
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="relative">
                        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="empSearch" class="w-full pl-10 pr-4 py-2.5 border rounded-lg text-sm bg-slate-50 focus:outline-none focus:ring-1 focus:ring-orange-400" placeholder="Search by Employee Name or ID..." onkeyup="filterTable()">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select text-sm py-2.5 bg-slate-50" id="statusFilter" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="Accepted">Accepted</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input type="date" class="form-control text-sm py-2 bg-slate-50">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark w-full py-2.5 text-sm font-bold">Apply Filters</button>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="overtimeTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Hours</th>
                            <th>Project</th>
                            <th>Approved By</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($overtimeRecords as $record): ?>
                        <tr class="transition">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="avatar-circle"><?php echo substr($record['name'], 0, 1); ?></div>
                                    <div>
                                        <div class="font-bold text-slate-700"><?php echo $record['name']; ?></div>
                                        <div class="text-[10px] text-slate-400 uppercase font-bold"><?php echo $record['emp_id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-600 font-medium"><?php echo $record['date']; ?></td>
                            <td><span class="bg-slate-100 px-2 py-1 rounded font-bold text-slate-700"><?php echo $record['hours']; ?> Hrs</span></td>
                            <td class="text-slate-500 italic"><?php echo $record['project']; ?></td>
                            <td class="text-slate-500"><?php echo $record['approver']; ?></td>
                            <td>
                                <span class="status-pill bg-<?php echo strtolower($record['status']); ?>">
                                    <i class="fa fa-circle text-[6px]"></i> <?php echo $record['status']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="flex justify-center gap-2">
                                    <?php if($record['status'] == 'Pending'): ?>
                                        <button class="btn btn-sm btn-outline-success border-0" title="Approve" onclick="validateAction('Approve', '<?php echo $record['name']; ?>')"><i class="fa-solid fa-check"></i></button>
                                        <button class="btn btn-sm btn-outline-danger border-0" title="Reject" onclick="validateAction('Reject', '<?php echo $record['name']; ?>')"><i class="fa-solid fa-xmark"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-secondary border-0" title="Edit"><i class="fa-solid fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger border-0" title="Delete" onclick="validateAction('Delete', '<?php echo $record['name']; ?>')"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="overtimeModal" class="fixed inset-0 modal-backdrop-blur z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="flex justify-between items-center p-5 border-b bg-slate-50">
                <h2 class="text-xl font-bold text-slate-800">New Overtime Entry</h2>
                <button onclick="closeOvertimeModal()" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-circle-xmark text-2xl"></i></button>
            </div>
            <form class="p-6 space-y-4">
                <div class="form-group">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Employee <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-xl p-2.5 bg-slate-50 outline-none focus:border-orange-500">
                        <option>Select Employee</option>
                        <option>Sarah Connor (EMP-045)</option>
                        <option>John Smith (EMP-012)</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">OT Date <span class="text-red-500">*</span></label>
                        <input type="date" class="w-full border rounded-xl p-2.5 bg-slate-50 outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Total Hours <span class="text-red-500">*</span></label>
                        <input type="number" step="0.5" class="w-full border rounded-xl p-2.5 bg-slate-50 outline-none focus:border-orange-500" placeholder="e.g. 2.5">
                    </div>
                </div>
                <div class="form-group">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Project/Task Reference</label>
                    <input type="text" class="w-full border rounded-xl p-2.5 bg-slate-50 outline-none focus:border-orange-500" placeholder="e.g. Website Migration">
                </div>
                <div class="form-group">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Manager Remarks</label>
                    <textarea class="w-full border rounded-xl p-3 bg-slate-50 outline-none focus:ring-1 focus:ring-orange-400" rows="3" placeholder="Optional comments..."></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeOvertimeModal()" class="px-6 py-2 rounded-lg font-bold text-slate-500 border border-slate-200 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-6 py-2 rounded-lg bg-[#ff5e3a] text-white font-bold shadow-lg hover:shadow-orange-200 transition">Save Log Entry</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('overtimeModal');

        function openOvertimeModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeOvertimeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Live Filtering Logic
        function filterTable() {
            const searchVal = document.getElementById('empSearch').value.toLowerCase();
            const statusVal = document.getElementById('statusFilter').value.toLowerCase();
            const table = document.getElementById('overtimeTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const nameText = rows[i].getElementsByTagName('td')[0].innerText.toLowerCase();
                const statusText = rows[i].getElementsByTagName('td')[5].innerText.toLowerCase();
                
                const matchesSearch = nameText.includes(searchVal);
                const matchesStatus = statusVal === "" || statusText.includes(statusVal);

                rows[i].style.display = (matchesSearch && matchesStatus) ? "" : "none";
            }
        }

        function validateAction(action, name) {
            if(confirm(`Are you sure you want to ${action} this request for ${name}?`)) {
                alert(`${action} successful!`);
                // In a real app, send AJAX request to update DB here
            }
        }

        function exportReport() {
            alert("Generating CSV report for current results...");
        }

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target == modal) closeOvertimeModal();
        }
    </script>
</body>
</html>