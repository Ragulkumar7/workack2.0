<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workack HRMS | TL Task Management</title>
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

        .project-overview { background: #fff; border-radius: 8px; border: 1px solid var(--border-light); padding: 20px; margin-bottom: 30px; border-left: 5px solid var(--primary-orange); }
        .project-overview h4 { margin: 0 0 10px; color: var(--primary-orange); text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        .project-overview h2 { margin: 0; font-size: 20px; font-weight: 700; }

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; overflow: hidden; }
        .card-title { font-size: 18px; font-weight: 600; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }

        .task-table { width: 100%; border-collapse: collapse; }
        .task-table th { text-align: left; padding: 15px 25px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; }
        .task-table td { padding: 18px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; }
        
        /* Badge Styles */
        .status-badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .pending { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
        .completed { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }

        .btn-save { background: var(--primary-orange); color: white; padding: 11px 22px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .btn-complete { background: #52c41a; padding: 8px 15px; font-size: 12px; }
        .action-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 15px; margin-left: 12px; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 10px; width: 600px; position: relative; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; }

        .input-group { margin-bottom: 18px; position: relative; }
        label { display: block; font-size: 13px; margin-bottom: 8px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 11px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        
        .search-icon { position: absolute; right: 12px; top: 36px; color: #aaa; pointer-events: none; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        <div class="page-header">
            <div>
                <h1>Team Task Management</h1>
                <div class="breadcrumb">Team Lead / Project Breakdown</div>
            </div>
            <button class="btn-save" onclick="openModal('splitTaskModal')">
                <i class="fas fa-plus"></i> Split Task to Team
            </button>
        </div>

        <div class="project-overview">
            <h4>Assigned Master Project</h4>
            <h2 id="parentProjectName">Workack HRMS API Integration</h2>
            <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                <i class="far fa-calendar-alt"></i> Deadline: 15 Feb 2026 | <i class="fas fa-user-tie"></i> Assigned by: Manager
            </div>
        </div>

        <div class="content-card">
            <div class="card-title">Employee Task Distribution</div>
            <table class="task-table" id="teamTaskTable">
                <thead>
                    <tr>
                        <th>Sub-Task Name</th>
                        <th>Assigned Employee</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="subrow-1">
                        <td class="st-title"><strong>Database Schema Design</strong></td>
                        <td class="st-name">Suresh Babu</td>
                        <td class="st-status"><span class="status-badge pending">Pending</span></td>
                        <td class="st-date">08 Feb 2026</td>
                        <td style="text-align: right;">
                            <button class="btn-save btn-complete" onclick="markComplete('subrow-1')">Mark Finished</button>
                            <button class="action-btn" onclick="editSubTask('subrow-1')"><i class="fas fa-edit"></i></button>
                            <button class="action-btn" style="color:red;" onclick="deleteSubTask('subrow-1')"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="splitTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalHeading">Split Task to Employee</h3>
                <span style="cursor:pointer; font-size:24px; color:#aaa;" onclick="closeModal('splitTaskModal')">&times;</span>
            </div>
            <form id="splitForm">
                <div class="modal-body">
                    <input type="hidden" id="editSubRowId">
                    <div class="input-group">
                        <label>Sub-Task Title</label>
                        <input type="text" id="subTitle" placeholder="e.g., UI Login Screen" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Assign Team Member</label>
                        <input type="text" id="empSearch" placeholder="Search employee" list="employeeList" required>
                        <i class="fas fa-search search-icon"></i>
                        <datalist id="employeeList">
                            <option value="Suresh Babu">
                            <option value="Karthik">
                            <option value="Anitha">
                            <option value="Ramesh">
                            <option value="Priya">
                        </datalist>
                    </div>

                    <div style="display:flex; gap:15px;">
                        <div class="input-group" style="flex:1;"><label>Sub-Deadline</label><input type="date" id="subDate" required></div>
                        <div class="input-group" style="flex:1;"><label>Priority</label>
                            <select id="subPriority">
                                <option>High</option>
                                <option>Medium</option>
                                <option>Low</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div style="padding: 20px 25px; border-top: 1px solid #eee; text-align: right; background: #fafafa;">
                    <button type="button" style="background:#eee; color:#444; border:none; padding:10px 20px; border-radius:6px; margin-right:10px; cursor:pointer;" onclick="closeModal('splitTaskModal')">Cancel</button>
                    <button type="submit" class="btn-save">Assign Sub-Task</button>
                </div>
            </form>
        </div>
    </div>

    <div id="proofModal" class="modal">
        <div class="modal-content" style="width:450px;">
            <div class="modal-header">
                <h3>Submit Completion Proof</h3>
                <span style="cursor:pointer;" onclick="closeModal('proofModal')">&times;</span>
            </div>
            <form id="proofForm">
                <div class="modal-body">
                    <input type="hidden" id="proofRowId">
                    <div class="input-group">
                        <label>Completion Note / Link</label>
                        <textarea id="workProof" rows="4" placeholder="e.g. GitHub link or 'Done'" required></textarea>
                    </div>
                </div>
                <div style="padding: 20px; text-align: right; border-top: 1px solid #eee;">
                    <button type="submit" class="btn-save" style="background:#52c41a;">Submit Proof</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function markComplete(rowId) {
            document.getElementById('proofRowId').value = rowId;
            openModal('proofModal');
        }

        document.getElementById('proofForm').onsubmit = function(e) {
            e.preventDefault();
            let rowId = document.getElementById('proofRowId').value;
            let proof = document.getElementById('workProof').value;

            let row = document.getElementById(rowId);
            row.querySelector('.st-status').innerHTML = '<span class="status-badge completed">Completed</span>';
            row.style.backgroundColor = '#f6ffed';
            
            row.querySelector('.btn-complete').style.display = 'none';
            row.querySelector('td:last-child').innerHTML = '<i class="fas fa-check-circle" style="color:#52c41a; font-size:18px;"></i> Verified';

            alert("Proof Submitted: " + proof);
            closeModal('proofModal');
        }

        function editSubTask(rowId) {
            document.getElementById('modalHeading').innerText = "Edit Sub-Task";
            document.getElementById('editSubRowId').value = rowId;
            document.getElementById('subTitle').value = document.querySelector(`#${rowId} .st-title`).innerText;
            document.getElementById('empSearch').value = document.querySelector(`#${rowId} .st-name`).innerText;
            document.getElementById('subDate').value = "2026-02-08"; 
            openModal('splitTaskModal');
        }

        function deleteSubTask(rowId) {
            if(confirm("Remove this assignment?")) {
                document.getElementById(rowId).remove();
            }
        }

        document.getElementById('splitForm').onsubmit = function(e) {
            e.preventDefault();
            alert("Sub-task Assigned Successfully to " + document.getElementById('empSearch').value);
            closeModal('splitTaskModal');
        }

        window.onclick = function(event) { 
            if (event.target.className === 'modal') { 
                closeModal('splitTaskModal'); 
                closeModal('proofModal'); 
            } 
        }
    </script>
</body>
</html>