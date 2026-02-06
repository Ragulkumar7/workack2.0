<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workack HRMS | HR Task Management</title>
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

        /* Manager Tasks Section (Locked) */
        .manager-assigned-box { background: #fff8f6; border: 1px solid #ffe0d8; border-radius: 8px; padding: 20px; margin-bottom: 30px; border-left: 5px solid #ff4d4f; }
        .manager-assigned-box h4 { margin: 0 0 10px; color: #ff4d4f; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .card-title { font-size: 18px; font-weight: 600; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }

        .task-table { width: 100%; border-collapse: collapse; }
        .task-table th { text-align: left; padding: 15px 25px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; font-weight: 600; }
        .task-table td { padding: 18px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; vertical-align: middle; }
        
        .status-badge { padding: 5px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-manager { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
        .badge-hr { background: #f9f0ff; color: #722ed1; border: 1px solid #d3adf7; }

        .btn-save { background: var(--primary-orange); color: white; padding: 11px 22px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .action-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 15px; margin-left: 12px; transition: 0.2s; }
        .action-btn:hover { color: var(--primary-orange); }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 10px; width: 600px; position: relative; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
        .modal-body { padding: 25px; }
        
        .input-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; margin-bottom: 8px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 11px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 14px; box-sizing: border-box; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        <div class="page-header">
            <div>
                <h1>Task Management</h1>
                <div style="font-size: 13px; color: var(--text-muted); margin-top: 5px;">HR Portal / Global Assignments</div>
            </div>
            <button class="btn-save" onclick="openModal('assignTaskModal')">
                <i class="fas fa-plus"></i> Assign New Task
            </button>
        </div>

        <div class="manager-assigned-box">
            <h4><i class="fas fa-user-shield"></i> Incoming Tasks from Manager</h4>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 16px;">Monthly Payroll Review - Feb 2026</h3>
                    <p style="margin: 5px 0 0; font-size: 12px; color: var(--text-muted);">Verify all employee attendance and tax deductions by 10th Feb.</p>
                </div>
                <span class="status-badge badge-manager">Pending Audit</span>
            </div>
        </div>

        <div class="content-card">
            <div class="card-title">Organization-Wide Task List</div>
            <table class="task-table" id="hrTaskTable">
                <thead>
                    <tr>
                        <th>Task Details</th>
                        <th>Assigned To</th>
                        <th>Category</th>
                        <th>Deadline</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="hr-task-1">
                        <td>
                            <div style="font-weight: 600;">Employee Background Verification</div>
                            <div style="font-size: 11px; color: var(--text-muted);">Check 3 new hire documents</div>
                        </td>
                        <td>Vasanth (HR)</td>
                        <td><span class="status-badge badge-hr">Human Resource</span></td>
                        <td>08 Feb 2026</td>
                        <td style="text-align: right;">
                            <button class="action-btn" title="View" onclick="viewTask('hr-task-1')"><i class="fas fa-eye"></i></button>
                            <button class="action-btn" title="Edit" onclick="editTask('hr-task-1')"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" title="Delete" style="color:#ff4d4f;" onclick="deleteTask('hr-task-1')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="assignTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalHeader">Assign Task</h3>
                <span style="cursor:pointer; font-size:24px; color:#aaa;" onclick="closeModal('assignTaskModal')">&times;</span>
            </div>
            <form id="hrTaskForm">
                <div class="modal-body">
                    <input type="hidden" id="editTaskId">
                    <div class="input-group">
                        <label>Task Title</label>
                        <input type="text" id="taskTitle" placeholder="e.g. Performance Review" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="input-group">
                            <label>Personnel</label>
                            <select id="empSelect" required>
                                <option value="">Select Employee</option>
                                <option value="Vasanth">Vasanth</option>
                                <option value="Suresh">Suresh</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Deadline</label>
                            <input type="date" id="taskDeadline" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Description</label>
                        <textarea id="taskDesc" rows="4" placeholder="Instructions..."></textarea>
                    </div>
                </div>
                <div style="padding: 20px 25px; border-top: 1px solid #eee; text-align: right; background: #fafafa;">
                    <button type="button" style="background:#eee; color:#444; border:none; padding:10px 20px; border-radius:6px; margin-right:10px; cursor:pointer;" onclick="closeModal('assignTaskModal')">Cancel</button>
                    <button type="submit" class="btn-save">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // Functionality for Actions
        function viewTask(id) {
            let title = document.querySelector(`#${id} div`).innerText;
            alert("Viewing details for: " + title);
        }

        function editTask(id) {
            document.getElementById('modalHeader').innerText = "Edit Assigned Task";
            document.getElementById('editTaskId').value = id;
            document.getElementById('taskTitle').value = document.querySelector(`#${id} div`).innerText;
            openModal('assignTaskModal');
        }

        function deleteTask(id) {
            if(confirm("Delete this assignment?")) {
                document.getElementById(id).remove();
            }
        }

        document.getElementById('hrTaskForm').onsubmit = function(e) {
            e.preventDefault();
            alert("Task processed successfully!");
            closeModal('assignTaskModal');
        };

        window.onclick = function(event) { if (event.target.className === 'modal') { closeModal('assignTaskModal'); } }
    </script>
</body>
</html>