<?php
// TL/task_tl.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check Login (Uncomment for production)
// if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Task Management - Workack HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            /* --- THEME COLORS (Matched to Image 1) --- */
            --primary: #1b5a5a; /* Orange */
            --primary-hover: #113c3c;
            --secondary: #64748b;
            --success: #10b981; /* Green for Active/Add buttons */
            --danger: #ef4444;
            --warning-bg: #fffbeb;
            --warning-border: #fcd34d;
            
            /* --- LAYOUT COLORS --- */
            --bg-body: #f8f9fa;
            --bg-card: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e2e8f0;
        }
           
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden;
        }
        
        /* --- LAYOUT & SIDEBAR --- */
        #mainContent { 
            margin-left: 95px; /* Sidebar width */
            padding: 24px 32px; 
            transition: all 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        /* --- HEADER --- */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px; 
        }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 700; color: #1e293b; }
        .breadcrumb { 
            font-size: 13px; color: var(--text-muted); margin-top: 5px; 
            display: flex; align-items: center; gap: 6px; 
        }

        /* --- PROJECT CARD --- */
        .project-overview { 
            background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border); 
            padding: 24px; margin-bottom: 30px; border-left: 5px solid var(--primary); 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
        }
        .project-overview h4 { margin: 0 0 8px; color: var(--primary); text-transform: uppercase; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
        .project-overview h2 { margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; }

        /* --- CONTENT CARD & TABLE --- */
        .content-card { 
            background: var(--bg-card); border: 1px solid var(--border); 
            border-radius: 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
        }
        .card-header {
            padding: 16px 24px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-size: 16px; font-weight: 600; margin: 0; }

        /* Table Styling (Matched to Image 1) */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        
        thead { background-color: #f8fafc; border-bottom: 1px solid var(--border); }
        th { 
            text-align: left; padding: 14px 24px; font-size: 12px; 
            font-weight: 600; color: #64748b; text-transform: uppercase; 
        }
        
        td { 
            padding: 16px 24px; border-bottom: 1px solid #f1f5f9; 
            font-size: 14px; vertical-align: middle; color: #334155;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }

        /* --- BUTTONS --- */
        .btn { 
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 9px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; 
            cursor: pointer; transition: 0.2s; border: none; outline: none;
        }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }

        .btn-success { background-color: var(--success); color: white; padding: 8px 14px; }
        .btn-success:hover { background-color: #059669; }

        .btn-outline { background: white; border: 1px solid var(--border); color: var(--text-main); }
        .btn-outline:hover { background: #f8fafc; }

        .icon-btn { 
            padding: 6px; border-radius: 4px; border: none; background: transparent; 
            cursor: pointer; color: var(--secondary); transition: 0.2s; 
        }
        .icon-btn:hover { background: #f1f5f9; color: var(--primary); }
        .icon-btn.delete:hover { color: var(--danger); background: #fef2f2; }

        /* --- BADGES & TAGS --- */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-Pending { background: #fff7ed; color: #c2410c; }
        .status-Completed { background: #dcfce7; color: #166534; }

        .assignee-chip { 
            display: inline-flex; align-items: center; gap: 6px;
            background: #f1f5f9; color: #475569; padding: 4px 10px; 
            border-radius: 20px; font-size: 12px; font-weight: 500; margin-right: 5px;
        }

        /* --- MODAL STYLING (Matched to Image 2) --- */
        .modal { 
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; 
            width: 100%; height: 100%; background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(2px); align-items: center; justify-content: center; 
        }
        .modal.active { display: flex; }

        .modal-content { 
            background: white; width: 600px; max-width: 90%; 
            border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
            animation: slideIn 0.2s ease-out; overflow: hidden;
        }
        @keyframes slideIn { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header { 
            padding: 16px 24px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .modal-header h3 { font-size: 18px; font-weight: 600; margin: 0; }

        .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }

        /* Form Inputs */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #374151; }
        .form-group label span { color: var(--danger); }
        
        .form-control { 
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); 
            border-radius: 6px; font-size: 14px; font-family: inherit; color: #1e293b;
            box-sizing: border-box; transition: 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }

        /* External Toggle Box */
        .external-box {
            background: var(--warning-bg); border: 1px solid var(--warning-border);
            padding: 12px; border-radius: 6px; display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }
        .external-box label { margin: 0; font-weight: 500; color: #92400e; cursor: pointer; }
        .external-box input { accent-color: #d97706; width: 16px; height: 16px; cursor: pointer; }

        /* Add Member Row */
        .add-member-row { display: flex; gap: 10px; }
        .add-member-row .form-control { flex: 1; }
        
        /* Dashed Empty State */
        .empty-assignees {
            border: 1px dashed var(--border); border-radius: 6px; padding: 15px;
            text-align: center; color: var(--text-muted); font-size: 13px; margin-top: 10px;
        }
        
        /* Assignee Chips inside Modal */
        .modal-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .modal-chip {
            background: #f1f5f9; border: 1px solid var(--border); padding: 5px 10px;
            border-radius: 6px; font-size: 13px; display: flex; align-items: center; gap: 8px;
        }

        .modal-footer {
            padding: 16px 24px; border-top: 1px solid var(--border); 
            background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            #mainContent { margin-left: 0; padding: 16px; width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .row-split { flex-direction: column; }
            .add-member-row { flex-direction: column; }
            .add-member-row button { width: 100%; }
        }
    </style>
</head>
<body>

    <?php 
    // Use the robust include method
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
    if (file_exists($sidebarPath)) { include($sidebarPath); }
    ?>

    <div id="mainContent">
        
        <div class="page-header">
            <div>
                <h1>Team Task Management</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span> Performance <span>/</span> Task Management
                </div>
            </div>
            <button class="btn btn-primary" onclick="openModal('taskModal')">
                <i data-lucide="plus-circle" style="width:18px;"></i> Split Task
            </button>
        </div>

        <div class="project-overview">
            <h4>Assigned Master Project</h4>
            <h2>Workack HRMS API Integration</h2>
            <div style="margin-top: 12px; font-size: 13px; color: #64748b; display:flex; gap: 20px; align-items:center; flex-wrap:wrap;">
                <span style="display:flex; align-items:center; gap:6px;"><i data-lucide="calendar" style="width:14px;"></i> Deadline: <b>15 Feb 2026</b></span>
                <span style="display:flex; align-items:center; gap:6px;"><i data-lucide="user" style="width:14px;"></i> Assigned by: <b>Manager</b></span>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Employee Task Distribution</h3>
                <div style="position:relative; width:250px;">
                    <input type="text" placeholder="Search tasks..." class="form-control" style="padding-left:35px; height:36px;">
                    <i data-lucide="search" style="width:16px; position:absolute; left:10px; top:10px; color:#94a3b8;"></i>
                </div>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">Sub-Task Name</th>
                            <th style="width: 30%;">Assigned To</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 15%;">Due Date</th>
                            <th style="width: 15%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="taskTableBody">
                        <tr id="row-1">
                            <td>
                                <div style="font-weight:600; color:#1e293b;">Database Schema Design</div>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Create SQL Tables for Users</div>
                            </td>
                            <td>
                                <div class="assignee-list">
                                    <span class="assignee-chip"><i data-lucide="user" style="width:12px;"></i> Suresh Babu</span>
                                </div>
                            </td>
                            <td><span class="status-badge status-Pending">Pending</span></td>
                            <td>08 Feb 2026</td>
                            <td style="text-align: right;">
                                <button class="icon-btn" title="Edit" onclick="editTask('row-1')"><i data-lucide="edit-3" style="width:16px;"></i></button>
                                <button class="icon-btn delete" title="Delete" onclick="deleteTask('row-1')"><i data-lucide="trash-2" style="width:16px;"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Split Task to Employees</h3>
                <button class="icon-btn" onclick="closeModal('taskModal')"><i data-lucide="x" style="width:20px;"></i></button>
            </div>
            
            <div class="modal-body">
                <form id="taskForm">
                    <input type="hidden" id="editRowId">

                    <div class="form-group">
                        <label>Sub-Task Title <span>*</span></label>
                        <input type="text" id="tTitle" class="form-control" placeholder="e.g., UI Login Screen Design" required>
                    </div>

                    <div class="form-group">
                        <label>Task Description <span>*</span></label>
                        <textarea id="tDesc" class="form-control" rows="3" placeholder="Explain what needs to be done..." required></textarea>
                    </div>

                    <div class="external-box">
                        <input type="checkbox" id="isExternal" onchange="toggleExternal()">
                        <label for="isExternal">Assign to another department?</label>
                    </div>

                    <div class="form-group" id="deptSelectGroup" style="display:none;">
                        <label>Select Department</label>
                        <select class="form-control" id="deptSelect" onchange="updateEmpPlaceholder()">
                            <option>Marketing</option>
                            <option>Design</option>
                            <option>Finance</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Add Team Member</label>
                        <div class="add-member-row">
                            <input type="text" id="empInput" class="form-control" placeholder="Search employee..." list="empList">
                            <datalist id="empList">
                                <option value="Suresh Babu">
                                <option value="Karthik">
                                <option value="Anitha">
                            </datalist>
                            <button type="button" class="btn btn-success" onclick="addAssignee()">
                                <i data-lucide="plus" style="width:16px;"></i> Add
                            </button>
                        </div>
                        
                        <div id="assigneeContainer" class="empty-assignees">
                            No employees added yet
                        </div>
                    </div>

                    <div style="display:flex; gap:15px;" class="row-split">
                        <div class="form-group" style="flex:1;">
                            <label>Due Date <span>*</span></label>
                            <input type="date" id="tDate" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Priority</label>
                            <select id="tPriority" class="form-control">
                                <option>Medium</option>
                                <option>High</option>
                                <option>Low</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('taskModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveTask()">Assign Task</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        // State
        let selectedAssignees = [];
        let rowCounter = 2;

        // Modal Functions
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            // Reset for new task
            if (!document.getElementById('editRowId').value) {
                resetForm();
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            setTimeout(resetForm, 300); // Clear after animation
        }

        function resetForm() {
            document.getElementById('taskForm').reset();
            document.getElementById('editRowId').value = '';
            document.getElementById('modalTitle').innerText = "Split Task to Employees";
            selectedAssignees = [];
            renderAssignees();
            document.getElementById('isExternal').checked = false;
            toggleExternal();
        }

        // Toggle External Department Logic
        function toggleExternal() {
            const isExt = document.getElementById('isExternal').checked;
            const deptGroup = document.getElementById('deptSelectGroup');
            const empInput = document.getElementById('empInput');

            if (isExt) {
                deptGroup.style.display = 'block';
                empInput.placeholder = "Search Marketing employee...";
            } else {
                deptGroup.style.display = 'none';
                empInput.placeholder = "Search employee...";
            }
        }

        function updateEmpPlaceholder() {
            const dept = document.getElementById('deptSelect').value;
            document.getElementById('empInput').placeholder = "Search " + dept + " employee...";
        }

        // Assignee Logic
        function addAssignee() {
            const input = document.getElementById('empInput');
            const val = input.value.trim();
            if (val && !selectedAssignees.includes(val)) {
                selectedAssignees.push(val);
                renderAssignees();
                input.value = '';
            } else if (selectedAssignees.includes(val)) {
                alert('Employee already added!');
            }
        }

        function removeAssignee(index) {
            selectedAssignees.splice(index, 1);
            renderAssignees();
        }

        function renderAssignees() {
            const container = document.getElementById('assigneeContainer');
            
            if (selectedAssignees.length === 0) {
                container.innerHTML = 'No employees added yet';
                container.className = 'empty-assignees';
                return;
            }

            container.className = 'modal-chips';
            container.innerHTML = selectedAssignees.map((name, i) => `
                <div class="modal-chip">
                    <span>${name}</span>
                    <i data-lucide="x" style="width:14px; cursor:pointer; color:#ef4444;" onclick="removeAssignee(${i})"></i>
                </div>
            `).join('');
            lucide.createIcons();
        }

        // Save Task (Create/Edit)
        function saveTask() {
            const title = document.getElementById('tTitle').value;
            const desc = document.getElementById('tDesc').value;
            const date = document.getElementById('tDate').value;
            const editId = document.getElementById('editRowId').value;

            if(!title || !date) return alert("Please fill required fields");
            if(selectedAssignees.length === 0) return alert("Please assign at least one member");

            // Format Date
            const dateObj = new Date(date);
            const dateStr = dateObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            // Create Assignee HTML for Table
            const assigneeHtml = selectedAssignees.map(name => 
                `<span class="assignee-chip"><i data-lucide="user" style="width:12px;"></i> ${name}</span>`
            ).join('');

            if(editId) {
                // Update
                const row = document.getElementById(editId);
                row.cells[0].innerHTML = `<div style="font-weight:600; color:#1e293b;">${title}</div><div style="font-size:12px; color:#64748b; margin-top:2px;">${desc}</div>`;
                row.cells[1].innerHTML = `<div class="assignee-list">${assigneeHtml}</div>`;
                row.cells[3].innerText = dateStr;
            } else {
                // Create
                const newId = 'row-' + rowCounter++;
                const tbody = document.getElementById('taskTableBody');
                const tr = document.createElement('tr');
                tr.id = newId;
                tr.innerHTML = `
                    <td>
                        <div style="font-weight:600; color:#1e293b;">${title}</div>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">${desc}</div>
                    </td>
                    <td><div class="assignee-list">${assigneeHtml}</div></td>
                    <td><span class="status-badge status-Pending">Pending</span></td>
                    <td>${dateStr}</td>
                    <td style="text-align: right;">
                        <button class="icon-btn" onclick="editTask('${newId}')"><i data-lucide="edit-3" style="width:16px;"></i></button>
                        <button class="icon-btn delete" onclick="deleteTask('${newId}')"><i data-lucide="trash-2" style="width:16px;"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            }

            closeModal('taskModal');
            lucide.createIcons();
        }

        // Table Actions
        function deleteTask(id) {
            if(confirm('Delete this task?')) {
                document.getElementById(id).remove();
            }
        }

        function editTask(id) {
            const row = document.getElementById(id);
            // In a real app, you would fetch data from ID. Here we scrape the DOM for demo.
            const title = row.cells[0].querySelector('div:first-child').innerText;
            const desc = row.cells[0].querySelector('div:last-child').innerText;
            
            document.getElementById('tTitle').value = title;
            document.getElementById('tDesc').value = desc;
            document.getElementById('editRowId').value = id;
            document.getElementById('modalTitle').innerText = "Edit Sub-Task";

            // Reset assignees for demo scraping
            selectedAssignees = []; 
            const chips = row.cells[1].querySelectorAll('.assignee-chip');
            chips.forEach(c => selectedAssignees.push(c.innerText.trim()));
            renderAssignees();

            openModal('taskModal');
        }

        // Close on outside click
        window.onclick = function(e) {
            if(e.target.classList.contains('modal')) {
                closeModal('taskModal');
            }
        }
    </script>
</body>
</html>