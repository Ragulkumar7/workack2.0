<?php
// TL/task_tl.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        }

        body { 
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden;
        }
        
        /* --- SIDEBAR INTEGRATION CSS --- */
        #mainContent { 
            margin-left: 95px; 
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
            box-sizing: border-box;
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }
        /* --------------------------- */
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        .project-overview { background: #fff; border-radius: 8px; border: 1px solid var(--border-light); padding: 20px; margin-bottom: 30px; border-left: 5px solid var(--primary-orange); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .project-overview h4 { margin: 0 0 10px; color: var(--primary-orange); text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        .project-overview h2 { margin: 0; font-size: 20px; font-weight: 700; }

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .card-title { font-size: 18px; font-weight: 600; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; margin: 0; }

        .task-table { width: 100%; border-collapse: collapse; }
        .task-table th { text-align: left; padding: 15px 25px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; font-weight: 600; }
        .task-table td { padding: 18px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; }
        
        /* Badge Styles */
        .status-badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .pending { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
        .completed { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }
        
        /* Assignee Tag Style in Table */
        .assignee-tag { display: inline-block; background: #f0f2f5; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-right: 4px; margin-bottom: 4px; border: 1px solid #e0e0e0; }

        .btn-save { background: var(--primary-orange); color: white; padding: 11px 22px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .btn-save:hover { background-color: #e54e2d; }
        .btn-complete { background: #52c41a; padding: 8px 15px; font-size: 12px; }
        .btn-complete:hover { background: #389e0d; }
        
        .action-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 15px; margin-left: 12px; transition: 0.2s; }
        .action-btn:hover { color: var(--primary-orange); }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 10px; width: 600px; position: relative; animation: slideIn 0.3s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; background: #fafafa; display: flex; justify-content: space-between; align-items: center; border-radius: 10px 10px 0 0; }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-body { padding: 25px; }

        .input-group { margin-bottom: 18px; position: relative; }
        .input-group label { display: block; font-size: 13px; margin-bottom: 8px; font-weight: 600; color: var(--text-dark); }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 11px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 14px; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus { outline: none; border-color: var(--primary-orange); }
        
        .search-icon { position: absolute; right: 12px; top: 12px; color: #aaa; pointer-events: none; }

        /* External Resource Toggle */
        .external-toggle {
            background: #fff8e1; border: 1px solid #ffe58f; padding: 12px; border-radius: 6px;
            display: flex; align-items: center; gap: 10px; margin-bottom: 15px;
        }
        .external-toggle input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #f59e0b; }
        .external-toggle label { margin: 0; font-size: 13px; color: #b76e00; cursor: pointer; }
    </style>
</head>
<body>

    <?php include('../sidebars.php'); ?>

    <div id="mainContent">
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
                <i class="far fa-calendar-alt"></i> Deadline: 15 Feb 2026 &nbsp;|&nbsp; <i class="fas fa-user-tie"></i> Assigned by: Manager
            </div>
        </div>

        <div class="content-card">
            <h3 class="card-title">Employee Task Distribution</h3>
            <div style="overflow-x: auto;">
                <table class="task-table" id="teamTaskTable">
                    <thead>
                        <tr>
                            <th>Sub-Task Name</th>
                            <th>Assigned Employees</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="subrow-1">
                            <td class="st-title"><strong>Database Schema Design</strong></td>
                            <td class="st-name">
                                <span class="assignee-tag">Suresh Babu</span>
                            </td>
                            <td class="st-status"><span class="status-badge pending">Pending</span></td>
                            <td class="st-date">08 Feb 2026</td>
                            <td style="text-align: right;">
                                <button class="btn-save btn-complete" onclick="markComplete('subrow-1')">Mark Finished</button>
                                <button class="action-btn" onclick="editSubTask('subrow-1')"><i class="fas fa-edit"></i></button>
                                <button class="action-btn" style="color:#dc3545;" onclick="deleteSubTask('subrow-1')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="splitTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalHeading">Split Task to Employees</h3>
                <span style="cursor:pointer; font-size:24px; color:#aaa; line-height: 1;" onclick="closeModal('splitTaskModal')">&times;</span>
            </div>
            <form id="splitForm">
                <div class="modal-body">
                    <input type="hidden" id="editSubRowId">
                    
                    <div class="input-group">
                        <label>Sub-Task Title</label>
                        <input type="text" id="subTitle" placeholder="e.g., UI Login Screen" required>
                    </div>
                    
                    <div class="external-toggle">
                        <input type="checkbox" id="isExternal" onchange="toggleExternalAssign()">
                        <label for="isExternal">Add employee from another department?</label>
                    </div>

                    <div class="input-group" id="deptGroup" style="display:none;">
                        <label style="color: #d97706;">Select Source Department</label>
                        <select id="sourceDept" onchange="fetchExternalEmployees()">
                            <option value="">-- Select Department --</option>
                            <option value="IT">IT Development</option>
                            <option value="Marketing">Digital Marketing</option>
                            <option value="Design">UI/UX Design</option>
                            <option value="Accounts">Accounts & Finance</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label id="assignLabel">Select Team Member</label>
                        <div style="display:flex; gap:10px;">
                            <div style="position:relative; flex:1;">
                                <input type="text" id="empSearch" placeholder="Search employee" list="employeeList">
                                <i class="fas fa-search search-icon" style="top: 12px;"></i>
                                <datalist id="employeeList">
                                    </datalist>
                            </div>
                            <button type="button" onclick="addAssignee()" class="btn-save" style="padding: 10px 15px; background: #28a745;">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>

                    <div id="selectedAssigneesList" style="margin-bottom: 15px; display:flex; flex-wrap:wrap; gap:8px; min-height: 30px; padding: 5px; border: 1px dashed #e0e0e0; border-radius: 6px;">
                        <span style="font-size:12px; color:#aaa; width:100%; text-align:center; line-height:28px;" id="emptyMsg">No employees added yet</span>
                    </div>

                    <div style="display:flex; gap:15px;">
                        <div class="input-group" style="flex:1;">
                            <label>Sub-Deadline</label>
                            <input type="date" id="subDate" required>
                        </div>
                        <div class="input-group" style="flex:1;">
                            <label>Priority</label>
                            <select id="subPriority">
                                <option>High</option>
                                <option selected>Medium</option>
                                <option>Low</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div style="padding: 20px 25px; border-top: 1px solid #eee; text-align: right; background: #fafafa; border-radius: 0 0 10px 10px;">
                    <button type="button" style="background:#eee; color:#444; border:none; padding:11px 20px; border-radius:6px; margin-right:10px; cursor:pointer; font-weight:600;" onclick="closeModal('splitTaskModal')">Cancel</button>
                    <button type="submit" class="btn-save">Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <div id="proofModal" class="modal">
        <div class="modal-content" style="width:450px;">
            <div class="modal-header">
                <h3>Submit Completion Proof</h3>
                <span style="cursor:pointer; font-size:24px; color:#aaa; line-height: 1;" onclick="closeModal('proofModal')">&times;</span>
            </div>
            <form id="proofForm">
                <div class="modal-body">
                    <input type="hidden" id="proofRowId">
                    <div class="input-group" style="margin-bottom: 0;">
                        <label>Completion Note / Link</label>
                        <textarea id="workProof" rows="4" placeholder="e.g. GitHub link or 'Done'" required></textarea>
                    </div>
                </div>
                <div style="padding: 20px 25px; text-align: right; border-top: 1px solid #eee; background: #fafafa; border-radius: 0 0 10px 10px;">
                    <button type="button" style="background:#eee; color:#444; border:none; padding:11px 20px; border-radius:6px; margin-right:10px; cursor:pointer; font-weight:600;" onclick="closeModal('proofModal')">Cancel</button>
                    <button type="submit" class="btn-save" style="background:#52c41a;">Submit Proof</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- DATA & VARIABLES ---
        const myTeam = ["Suresh Babu", "Karthik", "Anitha", "Ramesh", "Priya"];
        let selectedAssignees = []; // Stores the final list of selected employees

        // --- MODAL CONTROLS ---
        function closeModal(id) { 
            document.getElementById(id).style.display = 'none'; 
        }

        // --- MULTI-ASSIGNEE LOGIC ---
        
        // 1. Initialize Modal State (Reset Everything)
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            if(id === 'splitTaskModal') {
                // If it's a new task (checking by title emptiness for now)
                if(!document.getElementById('subTitle').value) {
                    selectedAssignees = [];
                    renderAssignees();
                    document.getElementById('isExternal').checked = false;
                    toggleExternalAssign(); // This will reset list to My Team
                    document.getElementById('modalHeading').innerText = "Split Task to Employees";
                }
            }
        }

        // 2. Toggle External Department Dropdown
        function toggleExternalAssign() {
            const isExt = document.getElementById('isExternal').checked;
            const deptGroup = document.getElementById('deptGroup');
            const assignLabel = document.getElementById('assignLabel');
            const empInput = document.getElementById('empSearch');
            
            // Clear input only, retain selectedAssignees array
            empInput.value = ""; 

            if (isExt) {
                deptGroup.style.display = 'block';
                assignLabel.innerText = "Select External Employee";
                empInput.placeholder = "Select department first...";
                updateDataList([]); // Clear list until dept selected
            } else {
                deptGroup.style.display = 'none';
                assignLabel.innerText = "Select Team Member";
                empInput.placeholder = "Search employee";
                document.getElementById('sourceDept').value = ""; 
                updateDataList(myTeam); // Show own team
            }
        }

        // 3. Mock Fetch External Employees
        function fetchExternalEmployees() {
            const dept = document.getElementById('sourceDept').value;
            let extEmployees = [];

            if(dept === "IT") extEmployees = ["Ragul (IT)", "Vasanth (IT)", "Deepak (IT)"];
            else if(dept === "Marketing") extEmployees = ["John (Mkt)", "Sarah (Mkt)"];
            else if(dept === "Design") extEmployees = ["Figma User 1", "Sketch Guru"];
            else if(dept === "Accounts") extEmployees = ["Acc. Manager", "Auditor"];

            updateDataList(extEmployees);
            document.getElementById('empSearch').placeholder = "Search " + dept + " employee...";
        }

        // 4. Update Datalist Options
        function updateDataList(names) {
            const dataList = document.getElementById('employeeList');
            dataList.innerHTML = '';
            names.forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                dataList.appendChild(option);
            });
        }

        // 5. Add Person to List
        function addAssignee() {
            const empName = document.getElementById('empSearch').value.trim();
            if (!empName) return alert("Please select an employee first.");
            
            if (selectedAssignees.includes(empName)) {
                return alert("This employee is already added!");
            }

            selectedAssignees.push(empName);
            renderAssignees();
            document.getElementById('empSearch').value = ""; // Clear input
        }

        // 6. Remove Person from List
        function removeAssignee(index) {
            selectedAssignees.splice(index, 1);
            renderAssignees();
        }

        // 7. Render Chips (UI)
        function renderAssignees() {
            const container = document.getElementById('selectedAssigneesList');
            container.innerHTML = "";
            
            if(selectedAssignees.length === 0) {
                container.innerHTML = '<span style="font-size:12px; color:#aaa; width:100%; text-align:center; line-height:28px;">No employees added yet</span>';
                return;
            }

            selectedAssignees.forEach((name, index) => {
                const tag = document.createElement('div');
                // Chip Style
                tag.style.cssText = "background:#e3f2fd; color:#0d47a1; padding:5px 12px; border-radius:20px; font-size:12px; display:flex; align-items:center; gap:6px; border:1px solid #90caf9; font-weight:500;";
                tag.innerHTML = `
                    ${name} 
                    <i class="fas fa-times" style="cursor:pointer; color:#ef5350;" onclick="removeAssignee(${index})"></i>
                `;
                container.appendChild(tag);
            });
        }

        // --- SUBMIT LOGIC ---
        document.getElementById('splitForm').onsubmit = function(e) {
            e.preventDefault();
            
            if (selectedAssignees.length === 0) {
                return alert("Please assign at least one employee.");
            }

            let taskTitle = document.getElementById('subTitle').value;
            // Generate list string for display/alert
            let assignedListStr = selectedAssignees.join(", "); 

            // Logic to handle Edit vs New (For Demo, we just alert)
            let action = document.getElementById('modalHeading').innerText;
            if(action.includes("Edit")) {
                alert(`Task Updated!\nAssigned to: ${assignedListStr}`);
            } else {
                alert(`Task "${taskTitle}" assigned successfully to:\n${assignedListStr}`);
                
                // Add Row Logic (Optional Visual Demo)
                // addRowToTable(taskTitle, assignedListStr, 'Pending', document.getElementById('subDate').value);
            }
            
            closeModal('splitTaskModal');
        }

        // --- TABLE ACTIONS ---
        function markComplete(rowId) {
            document.getElementById('proofRowId').value = rowId;
            document.getElementById('workProof').value = ''; 
            document.getElementById('proofModal').style.display = 'block';
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
            
            // Get current values
            let title = document.querySelector(`#${rowId} .st-title`).innerText;
            // For demo edit, we just grab the text. In real app, you'd fetch ID list.
            let currentAssigneesText = document.querySelector(`#${rowId} .st-name`).innerText;
            
            document.getElementById('subTitle').value = title;
            document.getElementById('subDate').value = "2026-02-08"; // Mock date
            
            // Reset and populate list
            selectedAssignees = currentAssigneesText.split(',').map(s => s.trim()).filter(s => s);
            renderAssignees();
            
            document.getElementById('splitTaskModal').style.display = 'block';
        }

        function deleteSubTask(rowId) {
            if(confirm("Are you sure you want to remove this assignment?")) {
                document.getElementById(rowId).remove();
            }
        }

        window.onclick = function(event) { 
            if (event.target.className === 'modal') { 
                event.target.style.display = 'none';
            } 
        }
    </script>
</body>
</html>