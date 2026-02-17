<?php
// TL/task_tl.php - Team Leader Task Management

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// CHECK LOGIN (Uncomment for production)
// if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// --- SIMULATED DATA (Replace this with SQL Query later) ---
$managerProjects = [
    [
        'id' => 101,
        'title' => 'Workack HRMS API Integration',
        'desc' => 'Integrate Stripe Payment Gateway and biometric attendance sync.',
        'deadline' => '15 Feb 2026',
        'progress' => 65,
        'status' => 'In Progress',
        'team' => ['Suresh', 'Anitha', 'Karthik']
    ],
    [
        'id' => 102,
        'title' => 'Client Dashboard Redesign',
        'desc' => 'Revamp the UI/UX for the main client portal using React.',
        'deadline' => '28 Feb 2026',
        'progress' => 30,
        'status' => 'Pending',
        'team' => ['Ramesh', 'Suresh']
    ],
    [
        'id' => 103,
        'title' => 'Mobile App Optimization',
        'desc' => 'Fix performance issues in the Android build regarding login latency.',
        'deadline' => '10 Mar 2026',
        'progress' => 85,
        'status' => 'Completed',
        'team' => ['Karthik']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Task Management - Workack</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            /* --- COLOR PALETTE --- */
            --primary: #0f766e; /* Teal 700 */
            --primary-hover: #115e59;
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 95px;
        }
           
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden;
        }
        
        /* --- SIDEBAR ALIGNMENT --- */
        #mainContent { 
            margin-left: var(--sidebar-width);
            padding: 30px 40px; 
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            box-sizing: border-box;
            transition: all 0.3s ease;
            padding-top: 0 !important;

        }

        /* --- HEADER --- */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 30px; gap: 15px; flex-wrap: wrap;
        }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1e293b; letter-spacing: -0.5px; margin: 0; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; margin-top: 6px; }

        /* --- 1. ACTIVE PROJECTS GRID (Refined) --- */
        .section-header { 
            font-size: 13px; font-weight: 700; color: var(--text-muted); 
            text-transform: uppercase; margin-bottom: 16px; letter-spacing: 0.8px; 
            display: flex; align-items: center; gap: 8px;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .project-card {
            background: var(--bg-card);
            border-radius: 10px;
            border: 1px solid var(--border);
            border-top: 4px solid var(--primary); /* TOP ACCENT */
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .project-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); 
        }

        .card-top { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
        .proj-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; line-height: 1.4; }
        .proj-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .card-meta { margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border); }
        .meta-row { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        
        /* Progress Bar */
        .progress-container { width: 100%; background: #f1f5f9; height: 6px; border-radius: 10px; overflow: hidden; }
        .progress-bar { height: 100%; background: var(--primary); border-radius: 10px; transition: width 0.4s ease; }

        /* Status Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-In { background: #eff6ff; color: #2563eb; } /* In Progress */
        .badge-Pending { background: #fff7ed; color: #c2410c; }
        .badge-Completed { background: #ecfdf5; color: #059669; }

        /* --- 2. TASK TABLE SECTION --- */
        .task-container { 
            background: var(--bg-card); border: 1px solid var(--border); 
            border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;
        }
        
        .task-header-row {
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .task-title h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; }

        .search-wrapper { position: relative; width: 100%; max-width: 320px; }
        .search-wrapper input {
            width: 100%; padding: 10px 10px 10px 36px; border-radius: 8px;
            border: 1px solid var(--border); font-size: 13px; outline: none; transition: 0.2s;
            background: #f8fafc;
        }
        .search-wrapper input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1); }
        .search-icon { position: absolute; left: 12px; top: 11px; color: #94a3b8; width: 15px; }

        /* Table Styling */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; padding: 14px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }
        tr:last-child td { border-bottom: none; }

        /* Common Elements */
        .btn { 
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; 
            cursor: pointer; border: none; transition: 0.2s;
        }
        .btn-primary { background-color: var(--primary); color: white; box-shadow: 0 2px 4px rgba(15, 118, 110, 0.2); }
        .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-1px); }
        
        .btn-icon { 
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid transparent; 
            background: transparent; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; 
        }
        .btn-icon:hover { background: #f1f5f9; color: var(--primary); border-color: #e2e8f0; }
        .btn-icon.delete:hover { background: #fef2f2; color: var(--danger); border-color: #fecaca; }

        .user-chip { 
            background: #f1f5f9; color: #334155; padding: 5px 10px; 
            border-radius: 6px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0;
        }

        /* --- MODAL --- */
        .modal-overlay { 
            display: none; position: fixed; z-index: 9999; left: 0; top: 0; 
            width: 100%; height: 100%; background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(4px); align-items: center; justify-content: center; 
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        
        .modal-box { 
            background: white; width: 550px; max-width: 90%; 
            border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
            display: flex; flex-direction: column; overflow: hidden;
        }
        
        .modal-head { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
        .modal-head h3 { margin: 0; font-size: 17px; font-weight: 700; color: #1e293b; }
        
        .modal-content { padding: 24px; overflow-y: auto; max-height: 70vh; }
        .modal-foot { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc; }

        .input-group { margin-bottom: 16px; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569; }
        .form-input { 
            width: 100%; padding: 10px; border: 1px solid var(--border); 
            border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: 0.2s;
        }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1); }

        .add-row { display: flex; gap: 8px; }
        .chip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding: 10px; background: #f8fafc; border: 1px dashed var(--border); border-radius: 6px; min-height: 38px; }
        .chip-removable { background: white; border: 1px solid var(--border); padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px; }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            #mainContent { margin-left: 0; width: 100%; padding: 20px; }
            .projects-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-header button { width: 100%; }
        }
    </style>
</head>
<body>

    <?php 
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
    if (file_exists($sidebarPath)) { include($sidebarPath); }
    ?>

    <div id="mainContent">
        <?php 
        $path_to_root = '../'; // Set this so header links (settings/logout) work correctly
        include('../header.php'); 
        ?>
        
        <div class="page-header">
            <div>
                <h1>Team Task Management</h1>
                <div class="breadcrumb">
                    <i data-lucide="layout-dashboard" style="width:14px;"></i>
                    <span>/</span> Performance <span>/</span> Task Board
                </div>
            </div>
            <button class="btn btn-primary" onclick="openModal('taskModal')">
                <i data-lucide="plus" style="width:16px;"></i> Split New Task
            </button>
        </div>

        <div class="section-header">
            <i data-lucide="layers" style="width:14px;"></i> Active Projects (Assigned by Manager)
        </div>
        
        <div class="projects-grid">
            <?php foreach($managerProjects as $proj): 
                $statusClass = explode(' ', $proj['status'])[0]; // Gets 'In', 'Pending', etc.
            ?>
            <div class="project-card">
                <div>
                    <div class="card-top">
                        <div class="badge badge-<?= $statusClass ?>"><?= $proj['status'] ?></div>
                        </div>
                    <h3 class="proj-title"><?= $proj['title'] ?></h3>
                    <p class="proj-desc"><?= $proj['desc'] ?></p>
                </div>

                <div class="card-meta">
                    <div class="meta-row">
                        <span>Deadline: <b><?= $proj['deadline'] ?></b></span>
                        <span><?= $proj['progress'] ?>%</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= $proj['progress'] ?>%"></div>
                    </div>
                    <div style="margin-top: 12px; display:flex;">
                        <?php foreach(array_slice($proj['team'], 0, 3) as $member): ?>
                        <div style="width:24px; height:24px; background:#e2e8f0; border-radius:50%; border:2px solid #fff; margin-right:-8px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#64748b;" title="<?= $member ?>">
                            <?= substr($member, 0, 1) ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if(count($proj['team']) > 3): ?>
                            <div style="width:24px; height:24px; background:#f1f5f9; border-radius:50%; border:2px solid #fff; margin-left:0; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:600; color:#64748b;">+<?= count($proj['team'])-3 ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="section-header">
            <i data-lucide="list-todo" style="width:14px;"></i> Team Task Split
        </div>

        <div class="task-container">
            <div class="task-header-row">
                <div class="task-title">
                    <h3>Sub-Task List</h3>
                </div>
                <div class="search-wrapper">
                    <i data-lucide="search" class="search-icon"></i>
                    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search tasks, employees or status...">
                </div>
            </div>

            <div class="table-responsive">
                <table id="taskTable">
                    <thead>
                        <tr>
                            <th width="35%">Sub-Task Details</th>
                            <th width="25%">Assigned To</th>
                            <th width="15%">Priority</th>
                            <th width="15%">Due Date</th>
                            <th width="10%" style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="taskTableBody">
                        <tr id="row-1">
                            <td>
                                <div style="font-weight:600; color:#0f172a; font-size:14px;">Database Schema Design</div>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Create SQL tables for user modules</div>
                            </td>
                            <td>
                                <div class="user-chip"><i data-lucide="user" style="width:12px;"></i> Suresh Babu</div>
                            </td>
                            <td><span style="font-size:12px; font-weight:600; color:#eab308;">Medium</span></td>
                            <td>12 Feb 2026</td>
                            <td style="text-align: right;">
                                <button class="btn-icon" onclick="editTask('row-1')" title="Edit"><i data-lucide="pencil" style="width:14px;"></i></button>
                                <button class="btn-icon delete" onclick="deleteTask('row-1')" title="Delete"><i data-lucide="trash-2" style="width:14px;"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="taskModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-head">
                <h3 id="modalTitle">Split Task to Employees</h3>
                <button class="btn-icon" onclick="closeModal('taskModal')"><i data-lucide="x" style="width:18px;"></i></button>
            </div>
            
            <div class="modal-content">
                <form id="taskForm" onsubmit="event.preventDefault(); saveTask();">
                    <input type="hidden" id="editRowId">

                    <div class="input-group">
                        <label>Sub-Task Title <span style="color:red">*</span></label>
                        <input type="text" id="tTitle" class="form-input" placeholder="e.g. Frontend Login Page" required>
                    </div>

                    <div class="input-group">
                        <label>Description <span style="color:red">*</span></label>
                        <textarea id="tDesc" class="form-input" rows="3" placeholder="Explain the task requirements..." required></textarea>
                    </div>

                    <div class="input-group">
                        <label>Assign Team Members <span style="color:red">*</span></label>
                        <div class="add-row">
                            <input type="text" id="empInput" class="form-input" placeholder="Search employee..." list="empList">
                            <button type="button" class="btn btn-primary" style="padding: 0 16px;" onclick="addAssignee()"><i data-lucide="plus" style="width:16px;"></i></button>
                        </div>
                        <datalist id="empList">
                            <option value="Suresh Babu">
                            <option value="Karthik">
                            <option value="Anitha">
                            <option value="Ramesh">
                        </datalist>
                        
                        <div id="chipContainer" class="chip-container">
                            <span style="font-size:12px; color:#94a3b8; width:100%; text-align:center; margin:auto;">No members added</span>
                        </div>
                    </div>

                    <div style="display:flex; gap:20px;">
                        <div class="input-group" style="flex:1;">
                            <label>Due Date <span style="color:red">*</span></label>
                            <input type="date" id="tDate" class="form-input" required>
                        </div>
                        <div class="input-group" style="flex:1;">
                            <label>Priority</label>
                            <select id="tPriority" class="form-input">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn" style="background:#fff; border:1px solid #e2e8f0;" onclick="closeModal('taskModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTask()">Assign Task</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        let selectedAssignees = [];
        let rowCounter = 2; // Start after static rows

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            if (!document.getElementById('editRowId').value) resetForm();
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            setTimeout(resetForm, 300);
        }

        function resetForm() {
            document.getElementById('taskForm').reset();
            document.getElementById('editRowId').value = '';
            document.getElementById('modalTitle').innerText = "Split Task to Employees";
            selectedAssignees = [];
            renderChips();
        }

        // --- ASSIGNEE LOGIC ---
        function addAssignee() {
            const input = document.getElementById('empInput');
            const val = input.value.trim();
            if (val && !selectedAssignees.includes(val)) {
                selectedAssignees.push(val);
                renderChips();
                input.value = '';
            }
        }

        function removeAssignee(index) {
            selectedAssignees.splice(index, 1);
            renderChips();
        }

        function renderChips() {
            const container = document.getElementById('chipContainer');
            if (selectedAssignees.length === 0) {
                container.innerHTML = '<span style="font-size:12px; color:#94a3b8; width:100%; text-align:center; margin:auto;">No members added</span>';
                return;
            }
            container.innerHTML = selectedAssignees.map((name, i) => `
                <div class="chip-removable">
                    ${name} <i data-lucide="x" style="width:12px; cursor:pointer; color:#ef4444;" onclick="removeAssignee(${i})"></i>
                </div>
            `).join('');
            lucide.createIcons();
        }

        // --- SAVE TASK LOGIC ---
        function saveTask() {
            const title = document.getElementById('tTitle').value;
            const desc = document.getElementById('tDesc').value;
            const date = document.getElementById('tDate').value;
            const priority = document.getElementById('tPriority').value;
            const editId = document.getElementById('editRowId').value;

            if (!title || !date || selectedAssignees.length === 0) {
                alert("Please fill all required fields and add at least one member.");
                return;
            }

            // Generate Assignee HTML
            const assigneeHtml = selectedAssignees.map(name => 
                `<div class="user-chip"><i data-lucide="user" style="width:12px;"></i> ${name}</div>`
            ).join(' ');

            const pColor = priority === 'High' ? '#ef4444' : (priority === 'Medium' ? '#eab308' : '#10b981');
            const dateStr = new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            if (editId) {
                // Update
                const row = document.getElementById(editId);
                row.cells[0].innerHTML = `<div style="font-weight:600; color:#0f172a; font-size:14px;">${title}</div><div style="font-size:12px; color:#64748b; margin-top:2px;">${desc}</div>`;
                row.cells[1].innerHTML = assigneeHtml;
                row.cells[2].innerHTML = `<span style="font-size:12px; font-weight:600; color:${pColor};">${priority}</span>`;
                row.cells[3].innerText = dateStr;
            } else {
                // Create
                const tbody = document.getElementById('taskTableBody');
                const newRow = document.createElement('tr');
                newRow.id = 'row-' + rowCounter++;
                newRow.innerHTML = `
                    <td>
                        <div style="font-weight:600; color:#0f172a; font-size:14px;">${title}</div>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">${desc}</div>
                    </td>
                    <td>${assigneeHtml}</td>
                    <td><span style="font-size:12px; font-weight:600; color:${pColor};">${priority}</span></td>
                    <td>${dateStr}</td>
                    <td style="text-align: right;">
                        <button class="btn-icon" onclick="editTask('${newRow.id}')"><i data-lucide="pencil" style="width:14px;"></i></button>
                        <button class="btn-icon delete" onclick="deleteTask('${newRow.id}')"><i data-lucide="trash-2" style="width:14px;"></i></button>
                    </td>
                `;
                tbody.appendChild(newRow);
            }

            closeModal('taskModal');
            lucide.createIcons();
        }

        function deleteTask(id) {
            if(confirm("Are you sure you want to delete this task?")) {
                document.getElementById(id).remove();
            }
        }

        function editTask(id) {
            const row = document.getElementById(id);
            const title = row.cells[0].querySelector('div:first-child').innerText;
            const desc = row.cells[0].querySelector('div:last-child').innerText;
            
            document.getElementById('tTitle').value = title;
            document.getElementById('tDesc').value = desc;
            document.getElementById('editRowId').value = id;
            document.getElementById('modalTitle').innerText = "Edit Task";
            
            selectedAssignees = [];
            row.cells[1].querySelectorAll('.user-chip').forEach(chip => {
                selectedAssignees.push(chip.innerText.trim());
            });
            renderChips();
            openModal('taskModal');
        }

        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('taskTableBody').getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                let text = rows[i].textContent || rows[i].innerText;
                rows[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? "" : "none";
            }
        }
    </script>
</body>
</html>