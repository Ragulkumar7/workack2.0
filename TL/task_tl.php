<?php
// TL/task_tl.php - Team Leader Task Management

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../include/db_connect.php'; 

// CHECK LOGIN & ROLE
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$tl_id = $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION (Create OR Edit Task) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Common Variables
    $action = $_POST['action'];
    $project_id = $_POST['project_id']; 
    $title = $_POST['task_title'];
    $desc = $_POST['task_desc'];
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    
    // Get Assignees from Hidden Input
    $assignees = !empty($_POST['assignees']) ? $_POST['assignees'] : NULL;

    if ($assignees) {
        if ($action == 'add_task') {
            // INSERT QUERY
            $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, task_title, description, assigned_to, priority, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("isssssi", $project_id, $title, $desc, $assignees, $priority, $due_date, $tl_id);
            $msg = "Task Assigned Successfully";
        } elseif ($action == 'edit_task') {
            // UPDATE QUERY
            $task_id = $_POST['task_id'];
            $stmt = $conn->prepare("UPDATE project_tasks SET project_id=?, task_title=?, description=?, assigned_to=?, priority=?, due_date=? WHERE id=? AND created_by=?");
            $stmt->bind_param("isssssii", $project_id, $title, $desc, $assignees, $priority, $due_date, $task_id, $tl_id);
            $msg = "Task Updated Successfully";
        }

        if (isset($stmt) && $stmt->execute()) {
            header("Location: task_tl.php?msg=$msg");
            exit();
        } else {
            $error = "Database Error: " . $conn->error;
        }
    } else {
        $error = "Please assign the task to at least one employee.";
    }
}

// --- HANDLE DELETE TASK ---
if (isset($_GET['delete_task'])) {
    $task_id = $_GET['delete_task'];
    $conn->query("DELETE FROM project_tasks WHERE id = $task_id AND created_by = $tl_id");
    header("Location: task_tl.php?msg=Task Deleted");
    exit();
}

// --- FETCH PROJECTS (For Dropdown) ---
$projects_sql = "SELECT * FROM projects WHERE leader_id = ? ORDER BY id DESC";
$p_stmt = $conn->prepare($projects_sql);
$p_stmt->bind_param("i", $tl_id);
$p_stmt->execute();
$projects_result = $p_stmt->get_result();

// --- FETCH TASKS (For Table) ---
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
            --bg-body: #f1f5f9;
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
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; background: #f8fafc; }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }

        .btn { display: inline-flex; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary); color: white; gap: 8px; }
        .btn:hover { background-color: var(--primary-hover); }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; background: transparent; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border: none; }
        .btn-icon:hover { background: #f1f5f9; color: var(--primary); }
        .btn-icon.delete:hover { background: #fef2f2; color: #ef4444; }
        
        /* FIX: Assignee Wrapper for Table */
        .assignee-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .user-chip { 
            background: #f1f5f9; 
            padding: 4px 10px; 
            border-radius: 15px; 
            font-size: 12px; 
            border: 1px solid #e2e8f0; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
            font-weight: 500;
            color: #475569;
            white-space: nowrap;
        }

        /* Modal */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 550px; max-width: 90%; border-radius: 12px; padding: 24px; }
        .input-group { margin-bottom: 16px; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569; }
        .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; }
        .chip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding: 10px; background: #f8fafc; border: 1px dashed var(--border); border-radius: 6px; min-height: 42px; }
        .chip-removable { background: white; border: 1px solid var(--border); padding: 4px 10px; border-radius: 15px; font-size: 12px; display: flex; align-items: center; gap: 6px; }
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
            <button class="btn" onclick="openModal('add')">
                <i data-lucide="plus" style="width:16px;"></i> Split New Task
            </button>
        </div>

        <?php if(isset($error)): ?>
            <div style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:6px; margin-bottom:20px; font-size:14px;">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['msg'])): ?>
            <div style="background:#f0fdf4; color:#16a34a; padding:10px; border-radius:6px; margin-bottom:20px; font-size:14px;">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

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
                    <i data-lucide="folder-open" style="width: 30px; color: #cbd5e1; margin-bottom: 10px;"></i>
                    <p style="color:#64748b; font-size:14px; margin:0;">No active projects assigned to you.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="task-container">
            <div class="task-header-row">
                <h3 style="margin:0; font-size:16px; font-weight:700; color:#1e293b;">Sub-Task List</h3>
                <div class="search-wrapper">
                    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search tasks...">
                </div>
            </div>

            <div class="table-responsive">
                <table id="taskTable">
                    <thead>
                        <tr>
                            <th width="30%">Sub-Task Details</th>
                            <th width="20%">Project</th>
                            <th width="25%">Assigned To</th>
                            <th width="10%">Priority</th>
                            <th width="10%">Due Date</th>
                            <th width="5%" style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="taskTableBody">
                        <?php 
                        if ($tasks_result->num_rows > 0):
                            while($task = $tasks_result->fetch_assoc()): 
                                $pColor = $task['priority'] == 'High' ? '#ef4444' : ($task['priority'] == 'Medium' ? '#eab308' : '#10b981');
                                $assigneeStr = $task['assigned_to'];
                                $assigneeArray = (!empty($assigneeStr)) ? explode(',', $assigneeStr) : [];
                                
                                // Prepare data for edit (JSON encode safely)
                                $taskData = htmlspecialchars(json_encode($task), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:#0f172a; font-size:14px;"><?= htmlspecialchars($task['task_title']) ?></div>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;"><?= htmlspecialchars($task['description']) ?></div>
                            </td>
                            <td><span style="font-size:12px; font-weight:600; color:#475569;"><?= htmlspecialchars($task['project_name']) ?></span></td>
                            <td>
                                <div class="assignee-wrapper">
                                    <?php if (!empty($assigneeArray)): ?>
                                        <?php foreach($assigneeArray as $name): ?>
                                            <?php if (trim($name) !== ''): ?>
                                                <div class="user-chip">
                                                    <i data-lucide="user" style="width:12px; height:12px;"></i> 
                                                    <?= htmlspecialchars(trim($name)) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:#94a3b8; font-style:italic;">Unassigned</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span style="font-size:12px; font-weight:600; color:<?= $pColor ?>;"><?= $task['priority'] ?></span></td>
                            <td><?= date('d M Y', strtotime($task['due_date'])) ?></td>
                            <td style="text-align: right;">
                                <div style="display:flex; justify-content:flex-end; gap:5px;">
                                    <button class="btn-icon" onclick='editTask(<?= $taskData ?>)' title="Edit">
                                        <i data-lucide="pencil" style="width:14px;"></i>
                                    </button>
                                    <a href="task_tl.php?delete_task=<?= $task['id'] ?>" class="btn-icon delete" onclick="return confirm('Delete this task?')" title="Delete">
                                        <i data-lucide="trash-2" style="width:14px;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">No sub-tasks created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="taskModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-bottom:20px;" id="modalTitle">Split Task to Employees</h3>
            <form method="POST" action="task_tl.php" onsubmit="return validateForm()">
                <input type="hidden" name="action" id="formAction" value="add_task">
                <input type="hidden" name="task_id" id="taskId">
                <input type="hidden" id="assigneesInput" name="assignees">

                <div class="input-group">
                    <label>Select Project</label>
                    <select name="project_id" id="projectSelect" class="form-input" required>
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
                    <label>Sub-Task Title</label>
                    <input type="text" name="task_title" id="taskTitle" class="form-input" required>
                </div>

                <div class="input-group">
                    <label>Description</label>
                    <textarea name="task_desc" id="taskDesc" class="form-input" rows="2" required></textarea>
                </div>

                <div class="input-group">
                    <label>Assign Team Members</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="empInput" class="form-input" placeholder="Type name..." list="empList" autocomplete="off">
                        <button type="button" class="btn" style="padding:0 16px;" onclick="addAssignee()">Add</button>
                    </div>
                    <datalist id="empList">
                        <?php 
                        $emp_sql = "SELECT full_name FROM team_members WHERE status='Active'";
                        $e_res = $conn->query($emp_sql);
                        if($e_res) {
                            while($e = $e_res->fetch_assoc()) {
                                echo "<option value='".$e['full_name']."'>";
                            }
                        }
                        ?>
                    </datalist>
                    <div id="chipContainer" class="chip-container"></div>
                </div>

                <div style="display:flex; gap:20px;">
                    <div class="input-group" style="flex:1;">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="dueDate" class="form-input" required>
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>Priority</label>
                        <select name="priority" id="taskPriority" class="form-input">
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn" style="background:#fff; border:1px solid #e2e8f0; color:#333;" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn" id="submitBtn">Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        let selectedAssignees = [];

        function openModal(mode) { 
            document.getElementById('taskModal').classList.add('active'); 
            if(mode === 'add') resetForm();
        }
        
        function closeModal() { document.getElementById('taskModal').classList.remove('active'); }

        // --- EDIT TASK FUNCTION (Populate Data) ---
        function editTask(data) {
            // Set Form Data
            document.getElementById('formAction').value = 'edit_task';
            document.getElementById('taskId').value = data.id;
            document.getElementById('projectSelect').value = data.project_id;
            document.getElementById('taskTitle').value = data.task_title;
            document.getElementById('taskDesc').value = data.description;
            document.getElementById('dueDate').value = data.due_date;
            document.getElementById('taskPriority').value = data.priority;
            
            // Set Modal UI for Editing
            document.getElementById('modalTitle').innerText = "Edit Task";
            document.getElementById('submitBtn').innerText = "Update Task";

            // Load Existing Assignees
            if (data.assigned_to) {
                // Split by comma and remove empty spaces
                selectedAssignees = data.assigned_to.split(',').map(name => name.trim()).filter(n => n);
            } else {
                selectedAssignees = [];
            }
            renderChips();
            
            openModal('edit');
        }

        // Reset Form for New Task
        function resetForm() {
            document.getElementById('formAction').value = 'add_task';
            document.getElementById('taskId').value = '';
            document.querySelector('form').reset();
            document.getElementById('modalTitle').innerText = "Split Task to Employees";
            document.getElementById('submitBtn').innerText = "Assign Task";
            selectedAssignees = [];
            renderChips();
        }

        // Add Employee Chip
        function addAssignee() {
            const input = document.getElementById('empInput');
            const val = input.value.trim();
            if (val && !selectedAssignees.includes(val)) {
                selectedAssignees.push(val);
                renderChips();
                input.value = '';
            }
        }

        // Remove Employee Chip
        function removeAssignee(index) {
            selectedAssignees.splice(index, 1);
            renderChips();
        }

        // Render Chips & Update Hidden Input
        function renderChips() {
            const container = document.getElementById('chipContainer');
            const hiddenInput = document.getElementById('assigneesInput');
            
            hiddenInput.value = selectedAssignees.join(','); 
            
            container.innerHTML = selectedAssignees.map((name, i) => `
                <div class="chip-removable">
                    ${name} <i data-lucide="x" style="width:12px; cursor:pointer; color:#ef4444;" onclick="removeAssignee(${i})"></i>
                </div>
            `).join('');
            lucide.createIcons();
        }

        // Validate Form before Submit
        function validateForm() {
            const input = document.getElementById('empInput');
            const val = input.value.trim();
            
            // If text remains in input, auto-add it
            if (val && !selectedAssignees.includes(val)) {
                selectedAssignees.push(val);
                renderChips();
            }

            if (selectedAssignees.length === 0) {
                alert("Please assign the task to at least one employee.");
                return false;
            }
            return true;
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