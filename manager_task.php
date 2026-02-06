<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workack HRMS | Manager Task Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-light: #f7f7f7;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #333333;
            --text-muted: #666666;
            --border-light: #e3e3e3;
            --sidebar-width: 260px;
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; display: flex; }
        .sidebar-wrapper { width: var(--sidebar-width); background: var(--white); height: 100vh; position: fixed; border-right: 1px solid var(--border-light); z-index: 100; }
        .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 0; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .card-title { font-size: 18px; font-weight: 600; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }

        .task-table { width: 100%; border-collapse: collapse; }
        .task-table th { text-align: left; padding: 15px 25px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; font-weight: 600; }
        .task-table td { padding: 18px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; vertical-align: middle; }
        
        .badge { padding: 5px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-tl { background: #e6f7ff; color: #1890ff; border: 1px solid #91d5ff; }
        .badge-hr { background: #f9f0ff; color: #722ed1; border: 1px solid #d3adf7; }
        .badge-emp { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }

        .btn-save { background: var(--primary-orange); color: white; padding: 11px 22px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .btn-save:hover { background: #e54e2d; box-shadow: 0 4px 8px rgba(255, 91, 55, 0.2); }
        
        .action-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 15px; margin-left: 12px; transition: 0.2s; }
        .action-btn:hover { color: var(--primary-orange); }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 4% auto; padding: 0; border-radius: 10px; width: 600px; position: relative; overflow: hidden; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 18px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 13px; margin-bottom: 8px; font-weight: 600; color: #444; }
        input, select, textarea { width: 100%; padding: 11px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
        input:focus { border-color: var(--primary-orange); outline: none; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        <div class="page-header">
            <div>
                <h1>Task Management</h1>
                <div class="breadcrumb">Manager / Master Task Distribution</div>
            </div>
            <button class="btn-save" onclick="prepareAddModal()">
                <i class="fas fa-plus"></i> Create New Task
            </button>
        </div>

        <div class="content-card">
            <div class="card-title">
                Global Task Overview
                <div style="font-weight: normal;">
                    <input type="text" placeholder="Search tasks or names..." style="width: 220px; padding: 7px 12px; font-size: 12px; border-radius: 20px; border: 1px solid var(--border-light);">
                </div>
            </div>
            
            <table class="task-table" id="taskTable">
                <thead>
                    <tr>
                        <th>Task / Project Title</th>
                        <th>Assigned To</th>
                        <th>Category</th>
                        <th>Deadline</th>
                        <th>Priority</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="row-1">
                        <td class="t-title"><strong>Workack HRMS API Integration</strong></td>
                        <td class="t-person">Ragul Kumar (TL)</td>
                        <td class="t-cat"><span class="badge badge-tl">Team Lead</span></td>
                        <td class="t-date">15 Feb 2026</td>
                        <td class="t-priority"><span style="color:#ff5b37; font-weight:600;">High</span></td>
                        <td style="text-align: right;">
                            <button class="action-btn" title="View Details" onclick="viewTask('row-1')"><i class="fas fa-eye"></i></button>
                            <button class="action-btn" title="Edit" onclick="editTask('row-1')"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" title="Delete" style="color:#ff4d4f;" onclick="deleteTask('row-1')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr id="row-2">
                        <td class="t-title"><strong>Monthly Payroll Review</strong></td>
                        <td class="t-person">Vasanth (HR)</td>
                        <td class="t-cat"><span class="badge badge-hr">Human Resource</span></td>
                        <td class="t-date">10 Feb 2026</td>
                        <td class="t-priority">Medium</td>
                        <td style="text-align: right;">
                            <button class="action-btn" onclick="viewTask('row-2')"><i class="fas fa-eye"></i></button>
                            <button class="action-btn" onclick="editTask('row-2')"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" style="color:#ff4d4f;" onclick="deleteTask('row-2')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addMasterTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; font-size:18px;" id="modalHeading">Assign Master Task</h3>
                <span style="cursor:pointer; font-size:24px; color:#aaa;" onclick="closeModal('addMasterTaskModal')">&times;</span>
            </div>
            <form id="taskForm">
                <input type="hidden" id="editRowId">
                <div class="modal-body">
                    <div class="input-group">
                        <label>Task / Project Title <span style="color:red;">*</span></label>
                        <input type="text" id="taskTitle" placeholder="Enter task name" required>
                    </div>

                    <div class="form-grid">
                        <div class="input-group">
                            <label>Assign To (User Level) <span style="color:red;">*</span></label>
                            <select id="userRole" required>
                                <option value="">Select Level</option>
                                <option value="tl">Team Lead (TL)</option>
                                <option value="hr">Human Resource (HR)</option>
                                <option value="emp">Direct Employee</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Specific Personnel <span style="color:red;">*</span></label>
                            <select id="assignedId" required>
                                <option value="">Choose Person</option>
                                <option value="Ragul Kumar (TL)">Ragul Kumar</option>
                                <option value="Vasanth (HR)">Vasanth</option>
                                <option value="Suresh Babu (Employee)">Suresh Babu</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="input-group">
                            <label>Overall Deadline</label>
                            <input type="date" id="deadline" required>
                        </div>
                        <div class="input-group">
                            <label>Priority</label>
                            <select id="priority">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Task Instructions</label>
                        <textarea id="description" rows="4" placeholder="Describe the goal..."></textarea>
                    </div>
                </div>
                <div style="padding: 20px 25px; border-top: 1px solid #eee; text-align: right; background: #fafafa;">
                    <button type="button" style="background:#eee; color:#444; border:none; padding:10px 20px; border-radius:6px; margin-right:10px; cursor:pointer;" onclick="closeModal('addMasterTaskModal')">Cancel</button>
                    <button type="submit" class="btn-save">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // Prepare Modal for Adding
        function prepareAddModal() {
            document.getElementById('taskForm').reset();
            document.getElementById('editRowId').value = "";
            document.getElementById('modalHeading').innerText = "Assign Master Task";
            openModal('addMasterTaskModal');
        }

        // VIEW Logic
        function viewTask(rowId) {
            const title = document.querySelector(`#${rowId} .t-title`).innerText;
            const person = document.querySelector(`#${rowId} .t-person`).innerText;
            alert(`View Mode:\nTask: ${title}\nAssigned to: ${person}`);
        }

        // EDIT Logic
        function editTask(rowId) {
            const title = document.querySelector(`#${rowId} .t-title`).innerText;
            const person = document.querySelector(`#${rowId} .t-person`).innerText;
            const date = document.querySelector(`#${rowId} .t-date`).innerText;
            const priority = document.querySelector(`#${rowId} .t-priority`).innerText;

            document.getElementById('editRowId').value = rowId;
            document.getElementById('taskTitle').value = title;
            document.getElementById('assignedId').value = person;
            document.getElementById('priority').value = priority;
            document.getElementById('modalHeading').innerText = "Edit Task";
            
            openModal('addMasterTaskModal');
        }

        // DELETE Logic
        function deleteTask(rowId) {
            if(confirm("Are you sure you want to delete this task?")) {
                document.getElementById(rowId).remove();
            }
        }

        // Form Submit Simulation
        document.getElementById('taskForm').onsubmit = function(e) {
            e.preventDefault();
            const rowId = document.getElementById('editRowId').value;
            
            if(rowId) {
                // Update Row
                document.querySelector(`#${rowId} .t-title`).innerHTML = `<strong>${document.getElementById('taskTitle').value}</strong>`;
                document.querySelector(`#${rowId} .t-person`).innerText = document.getElementById('assignedId').value;
                document.querySelector(`#${rowId} .t-priority`).innerText = document.getElementById('priority').value;
                alert("Task updated locally!");
            } else {
                alert("Task creation would trigger save_manager_task.php");
            }
            closeModal('addMasterTaskModal');
        };

        window.onclick = function(event) {
            if (event.target.className === 'modal') { closeModal('addMasterTaskModal'); }
        }
    </script>
</body>
</html>