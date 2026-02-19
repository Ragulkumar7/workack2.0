<?php
// TL/task_tl.php - Team Leader Task Management

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php'; 

// CHECK LOGIN & ROLE
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$tl_id = $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION (Create Sub-Task) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_task') {
    $project_id = (int)$_POST['project_id']; 
    $title = mysqli_real_escape_string($conn, $_POST['task_title']);
    $desc = mysqli_real_escape_string($conn, $_POST['task_desc']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    
    // Safely capture and trim the comma-separated assignees string
    $assignees = isset($_POST['assignees']) ? mysqli_real_escape_string($conn, trim($_POST['assignees'])) : ''; 

    // Insert into project_tasks table
    $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, task_title, description, assigned_to, priority, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isssssi", $project_id, $title, $desc, $assignees, $priority, $due_date, $tl_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Task assigned successfully to your team!'); window.location.href='task_tl.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error assigning task. Please try again.');</script>";
    }
}

// --- HANDLE DELETE TASK ---
if (isset($_GET['delete_task'])) {
    $task_id = intval($_GET['delete_task']);
    $conn->query("DELETE FROM project_tasks WHERE id = $task_id AND created_by = $tl_id");
    header("Location: task_tl.php");
    exit();
}

// --- 1. FETCH PROJECTS ASSIGNED TO ME (BY MANAGER) ---
$projects_sql = "SELECT * FROM projects WHERE leader_id = ? ORDER BY id DESC";
$p_stmt = $conn->prepare($projects_sql);
$p_stmt->bind_param("i", $tl_id);
$p_stmt->execute();
$projects_result = $p_stmt->get_result();

// --- 2. FETCH TASKS CREATED BY ME (FOR MY TEAM) ---
$tasks_sql = "SELECT t.*, p.project_name 
              FROM project_tasks t 
              JOIN projects p ON t.project_id = p.id 
              WHERE t.created_by = ? 
              ORDER BY t.due_date ASC";
$t_stmt = $conn->prepare($tasks_sql);
$t_stmt->bind_param("i", $tl_id);
$t_stmt->execute();
$tasks_result = $t_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Task Management</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: #0f766e;
            --primary-hover: #115e59;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --sidebar-width: 95px;
        }
           
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; }
        
        #mainContent { 
            margin-left: var(--sidebar-width); padding: 30px 40px; 
            width: calc(100% - var(--sidebar-width)); min-height: 100vh;
            box-sizing: border-box; transition: all 0.3s ease; padding-top: 0 !important;
        }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; margin-top: 6px; }

        .section-header { font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        
        /* Projects Grid */
        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .project-card { background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border); border-top: 4px solid var(--primary); padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .project-card:hover { transform: translateY(-3px); }
        .card-top { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .proj-title { font-size: 16px; font-weight: 700; margin: 0 0 6px 0; }
        .proj-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-meta { margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border); }
        .progress-container { width: 100%; background: #f1f5f9; height: 6px; border-radius: 10px; overflow: hidden; margin-top: 8px; }
        .progress-bar { height: 100%; background: var(--primary); border-radius: 10px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-Active { background: #eff6ff; color: #2563eb; }
        .badge-Pending { background: #fff7ed; color: #c2410c; }
        .badge-Completed { background: #ecfdf5; color: #059669; }

        /* Task Table */
        .task-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .task-header-row { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .search-wrapper input { padding: 10px 10px 10px 36px; border-radius: 8px; border: 1px solid var(--border); outline: none; width: 250px; }
        
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 14px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; background: #f8fafc; }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }

        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary); color: white; gap: 8px; transition: 0.2s; }
        .btn:hover { background-color: var(--primary-hover); }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; background: transparent; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border: none; transition: 0.2s;}
        .btn-icon:hover { background: #f1f5f9; color: var(--primary); }
        .btn-icon.delete:hover { background: #fef2f2; color: #ef4444; }
        
        .user-chip { 
            background: #f8fafc; padding: 4px 10px; border-radius: 20px; font-size: 12px; 
            border: 1px solid #e2e8f0; display: inline-flex; align-items: center; 
            color: #475569; font-weight: 500;
        }

        /* Responsive Modal Fixes */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        
        .modal-box { 
            background: white; 
            width: 550px; 
            max-width: 100%; 
            border-radius: 12px; 
            padding: 24px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
            max-height: 85vh; /* Prevents modal from getting taller than the screen */
            overflow-y: auto; /* Adds internal scrollbar when content is long */
        }
        
        /* Custom Scrollbar for the modal */
        .modal-box::-webkit-scrollbar { width: 6px; }
        .modal-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .modal-box::-webkit-scrollbar-track { background: transparent; }

        .input-group { margin-bottom: 16px; width: 100%; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569; }
        .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; outline: none; transition: 0.2s; box-sizing: border-box;}
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1); }
        
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        .assign-input-group { display: flex; gap: 10px; }
        .assign-input-group input { flex: 1; min-width: 0; }
        .assign-input-group button { flex-shrink: 0; }
        
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px; }

        .chip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding: 10px; background: #f8fafc; border: 1px dashed var(--border); border-radius: 6px; min-height: 48px; }
        .chip-removable { background: white; border: 1px solid var(--border); padding: 4px 10px; border-radius: 15px; font-size: 12px; display: flex; align-items: center; gap: 6px; font-weight: 500;}

        /* Mobile Responsiveness */
        @media (max-width: 992px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 20px !important; }
            .projects-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .page-header .btn { width: 100%; }
            .task-header-row { flex-direction: column; align-items: flex-start; gap: 15px; }
            .search-wrapper { width: 100%; }
            .search-wrapper input { width: 100%; }
            
            .modal-box { padding: 20px; }
            .form-row { flex-direction: column; gap: 0; } /* Stacks due date and priority on mobile */
            .modal-actions { flex-direction: column-reverse; gap: 10px; }
            .modal-actions .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <?php include '../sidebars.php'; ?>

    <div id="mainContent">
        <?php 
        $path_to_root = '../'; 
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
            <button class="btn" onclick="openModal('taskModal')">
                <i data-lucide="plus" style="width:16px;"></i> Split New Task
            </button>
        </div>

        <div class="section-header">
            <i data-lucide="layers" style="width:14px;"></i> Active Projects (Assigned by Manager)
        </div>
        
        <div class="projects-grid">
            <?php 
            if ($projects_result->num_rows > 0):
                while($proj = $projects_result->fetch_assoc()): 
                    $status = $proj['status'] ?? 'Active';
                    $progress = $proj['progress'] ?? 0;
            ?>
            <div class="project-card">
                <div>
                    <div class="card-top">
                        <div class="badge badge-<?= $status ?>"><?= $status ?></div>
                    </div>
                    <h3 class="proj-title"><?= htmlspecialchars($proj['project_name']) ?></h3>
                    <p class="proj-desc"><?= htmlspecialchars($proj['description'] ?? 'No description') ?></p>
                </div>

                <div class="card-meta">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px;">
                        <span>Deadline: <b><?= date('d M Y', strtotime($proj['deadline'] ?? date('Y-m-d'))) ?></b></span>
                        <span><?= $progress ?>%</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="grid-column: 1 / -1; padding: 30px; text-align: center; background: #fff; border-radius: 10px; border: 1px dashed #cbd5e1;">
                    <i data-lucide="folder-open" style="width: 30px; color: #cbd5e1; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;"></i>
                    <p style="color:#64748b; font-size:14px; margin:0;">No active projects assigned to you.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="section-header">
            <i data-lucide="list-todo" style="width:14px;"></i> Sub-Task List (Assigned to Team)
        </div>

        <div class="task-container">
            <div class="task-header-row">
                <h3 style="margin:0; font-size:16px; font-weight:700; color:#1e293b;">Sub-Task List</h3>
                <div class="search-wrapper" style="position:relative;">
                    <i data-lucide="search" style="width:14px; position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
                    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search tasks...">
                </div>
            </div>

            <div class="table-responsive">
                <table id="taskTable">
                    <thead>
                        <tr>
                            <th width="30%">Sub-Task Details</th>
                            <th width="20%">Project</th>
                            <th width="20%">Assigned To</th>
                            <th width="10%">Priority</th>
                            <th width="10%">Due Date</th>
                            <th width="10%" style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="taskTableBody">
                        <?php 
                        if ($tasks_result->num_rows > 0):
                            while($task = $tasks_result->fetch_assoc()): 
                                // Determine Priority Colors
                                $pColor = $task['priority'] == 'High' ? '#ef4444' : ($task['priority'] == 'Medium' ? '#f59e0b' : '#10b981');
                                $pBg = $task['priority'] == 'High' ? '#fef2f2' : ($task['priority'] == 'Medium' ? '#fffbeb' : '#ecfdf5');
                                $pBorder = $task['priority'] == 'High' ? '#fecaca' : ($task['priority'] == 'Medium' ? '#fef3c7' : '#d1fae5');
                                
                                // Safely split assigned names
                                $assigned_str = $task['assigned_to'] ?? '';
                                $assignees_array = array_filter(array_map('trim', explode(',', $assigned_str))); 
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:#0f172a; font-size:14px;"><?= htmlspecialchars($task['task_title']) ?></div>
                                <div style="font-size:12px; color:#64748b; margin-top:4px;"><?= htmlspecialchars($task['description']) ?></div>
                            </td>
                            <td><span style="font-size:13px; font-weight:600; color:#475569;"><?= htmlspecialchars($task['project_name']) ?></span></td>
                            <td>
                                <?php if(!empty($assignees_array)): ?>
                                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                    <?php foreach($assignees_array as $name): ?>
                                        <span class="user-chip">
                                            <i data-lucide="user" style="width:12px; height:12px; margin-right:4px;"></i> 
                                            <?= htmlspecialchars($name) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:12px; font-weight: 500;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background:<?= $pBg ?>; color:<?= $pColor ?>; border:1px solid <?= $pBorder ?>; padding: 4px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase;">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; font-weight: 500; color: #475569;"><?= date('d M Y', strtotime($task['due_date'])) ?></td>
                            <td style="text-align: right;">
                                <a href="task_tl.php?delete_task=<?= $task['id'] ?>" class="btn-icon delete" onclick="return confirm('Are you sure you want to delete this task?')" title="Delete Task">
                                    <i data-lucide="trash-2" style="width:16px;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#64748b;">No sub-tasks created yet. Click 'Split New Task' to begin.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="taskModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-bottom:20px; font-size: 18px; color: #0f172a; font-weight: 700;">Split Task to Employees</h3>
            <form method="POST" action="task_tl.php" onsubmit="return validateAndSubmit(event)">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" id="assigneesInput" name="assignees">

                <div class="input-group">
                    <label>Select Master Project <span style="color:#ef4444;">*</span></label>
                    <select name="project_id" class="form-input" required>
                        <option value="">-- Choose Project --</option>
                        <?php 
                        $projects_result->data_seek(0);
                        while($p = $projects_result->fetch_assoc()): 
                        ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Sub-Task Title <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="task_title" placeholder="e.g., Build Login API" class="form-input" required>
                </div>

                <div class="input-group">
                    <label>Description <span style="color:#ef4444;">*</span></label>
                    <textarea name="task_desc" class="form-input" placeholder="Enter task instructions..." rows="3" required></textarea>
                </div>

                <div class="input-group">
                    <label>Assign Team Members <span style="color:#ef4444;">*</span></label>
                    <div class="assign-input-group">
                        <input type="text" id="empInput" class="form-input" placeholder="Type name and click Add..." list="empList" onkeypress="handleEnter(event)">
                        <button type="button" class="btn" style="padding:0 20px;" onclick="addAssignee()">Add</button>
                    </div>
                    <datalist id="empList">
                        <?php 
                        $emp_sql = "SELECT full_name FROM employee_profiles WHERE reporting_to = ?";
                        $e_stmt = $conn->prepare($emp_sql);
                        $e_stmt->bind_param("i", $tl_id);
                        $e_stmt->execute();
                        $e_res = $e_stmt->get_result();
                        
                        if($e_res) {
                            while($e = $e_res->fetch_assoc()) {
                                if(!empty(trim($e['full_name']))) {
                                    echo "<option value='".htmlspecialchars($e['full_name'])."'>";
                                }
                            }
                        }
                        $e_stmt->close();
                        ?>
                    </datalist>
                    <div id="chipContainer" class="chip-container">
                        <span style="color: #94a3b8; font-size: 12px; margin-top: 4px;">Selected employees will appear here...</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group form-col">
                        <label>Due Date <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="due_date" class="form-input" required>
                    </div>
                    <div class="input-group form-col">
                        <label>Priority</label>
                        <select name="priority" class="form-input">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn" style="background:#fff; border:1px solid #e2e8f0; color:#475569;" onclick="closeModal('taskModal')">Cancel</button>
                    <button type="submit" class="btn">Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        let selectedAssignees = [];

        function openModal(id) { 
            document.getElementById(id).classList.add('active'); 
        }
        
        function closeModal(id) { 
            document.getElementById(id).classList.remove('active'); 
            // Clear form and arrays on close
            document.querySelector('form[action="task_tl.php"]').reset();
            selectedAssignees = [];
            renderChips();
        }

        // Handle Enter Key in input to prevent early form submission
        function handleEnter(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addAssignee();
            }
        }

        function addAssignee() {
            const input = document.getElementById('empInput');
            const val = input.value.trim();
            if (val && !selectedAssignees.includes(val)) {
                selectedAssignees.push(val);
                renderChips();
                input.value = '';
                input.focus();
            }
        }

        function removeAssignee(index) {
            selectedAssignees.splice(index, 1);
            renderChips();
        }

        function renderChips() {
            const container = document.getElementById('chipContainer');
            const hiddenInput = document.getElementById('assigneesInput');
            
            hiddenInput.value = selectedAssignees.join(','); 
            
            if (selectedAssignees.length === 0) {
                container.innerHTML = '<span style="color: #94a3b8; font-size: 12px; margin-top: 4px;">Selected employees will appear here...</span>';
                return;
            }

            container.innerHTML = selectedAssignees.map((name, i) => `
                <div class="chip-removable">
                    ${name} <i data-lucide="x" style="width:14px; cursor:pointer; color:#ef4444; margin-left: 2px;" onclick="removeAssignee(${i})"></i>
                </div>
            `).join('');
            lucide.createIcons();
        }

        // Smart Form Validation & Capture
        function validateAndSubmit(e) {
            const input = document.getElementById('empInput');
            const val = input.value.trim();
            
            // Auto-add anything left in the text box if the user forgot to hit "Add"
            if (val && !selectedAssignees.includes(val)) {
                selectedAssignees.push(val);
            }
            
            if (selectedAssignees.length === 0) {
                e.preventDefault();
                alert("Please select and add at least one team member to assign this task to.");
                input.focus();
                return false;
            }
            
            document.getElementById('assigneesInput').value = selectedAssignees.join(',');
            return true;
        }

        // Filter Table Logic
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('taskTableBody').getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                // Skip the "No sub-tasks" row
                if (rows[i].cells.length === 1) continue; 
                
                let text = rows[i].textContent || rows[i].innerText;
                rows[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? "" : "none";
            }
        }
    </script>
</body>
</html>