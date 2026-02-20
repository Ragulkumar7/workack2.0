<?php
// TL/task_tl.php - Team Leader Task Management

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php'; 

// CHECK LOGIN & ROLE
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$tl_id = $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION (Create Multiple Sub-Tasks) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_task') {
    $project_id = (int)$_POST['project_id']; 
    $tasks_json = $_POST['tasks_data']; // Hidden input containing JSON string of all tasks
    $tasks_array = json_decode($tasks_json, true);

    if (!empty($tasks_array)) {
        $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, task_title, description, assigned_to, priority, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
        
        foreach ($tasks_array as $task) {
            $title = mysqli_real_escape_string($conn, $task['title']);
            $desc = mysqli_real_escape_string($conn, $task['desc']);
            $assignees = mysqli_real_escape_string($conn, $task['assignees']);
            $priority = mysqli_real_escape_string($conn, $task['priority']);
            $due_date = mysqli_real_escape_string($conn, $task['due_date']);
            
            $stmt->bind_param("isssssi", $project_id, $title, $desc, $assignees, $priority, $due_date, $tl_id);
            $stmt->execute();
        }
        $stmt->close();
        echo "<script>alert('All tasks successfully assigned to your team!'); window.location.href='task_tl.php';</script>";
        exit();
    }
}

// --- HANDLE DELETE TASK ---
if (isset($_GET['delete_task'])) {
    $task_id = intval($_GET['delete_task']);
    $conn->query("DELETE FROM project_tasks WHERE id = $task_id AND created_by = $tl_id");
    header("Location: task_tl.php");
    exit();
}

// --- 1. FETCH PROJECTS ASSIGNED TO ME ---
$projects_sql = "SELECT * FROM projects WHERE leader_id = ? ORDER BY id DESC";
$p_stmt = $conn->prepare($projects_sql);
$p_stmt->bind_param("i", $tl_id);
$p_stmt->execute();
$projects_result = $p_stmt->get_result();

// --- 2. FETCH TASKS CREATED BY ME ---
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
        :root { --primary: #0f766e; --primary-hover: #115e59; --bg-body: #f8fafc; --bg-card: #ffffff; --text-main: #0f172a; --text-muted: #64748b; --border: #e2e8f0; --sidebar-width: 95px; }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; }
        #mainContent { margin-left: var(--sidebar-width); padding: 30px 40px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; box-sizing: border-box; transition: all 0.3s ease; padding-top: 0 !important; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; margin-top: 6px; }
        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .project-card { background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border); border-top: 4px solid var(--primary); padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .task-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 14px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; background: #f8fafc; }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary); color: white; gap: 8px; transition: 0.2s; }
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 700px; max-width: 100%; border-radius: 12px; padding: 24px; max-height: 90vh; overflow-y: auto; }
        .input-group { margin-bottom: 16px; }
        .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; box-sizing: border-box;}
        .task-list-preview { margin-top: 20px; border-top: 2px solid #f1f5f9; padding-top: 15px; }
        .preview-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

    <?php include '../sidebars.php'; ?>

    <div id="mainContent">
        <?php include('../header.php'); ?>
        
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

        <div class="projects-grid">
            <?php while($proj = $projects_result->fetch_assoc()): ?>
            <div class="project-card">
                <h3 class="proj-title"><?= htmlspecialchars($proj['project_name']) ?></h3>
                <p class="proj-desc"><?= htmlspecialchars($proj['description'] ?? 'No description') ?></p>
                <div style="font-size: 12px; margin-top: 10px;">Deadline: <?= date('d M Y', strtotime($proj['deadline'])) ?></div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="task-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Sub-Task Details</th>
                            <th>Project</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="taskTableBody">
                        <?php while($task = $tasks_result->fetch_assoc()): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($task['task_title']) ?></b><br><small><?= htmlspecialchars($task['description']) ?></small></td>
                            <td><?= htmlspecialchars($task['project_name']) ?></td>
                            <td><?= htmlspecialchars($task['assigned_to']) ?></td>
                            <td><?= htmlspecialchars($task['priority']) ?></td>
                            <td><?= date('d M Y', strtotime($task['due_date'])) ?></td>
                            <td align="right"><a href="task_tl.php?delete_task=<?= $task['id'] ?>" style="color:red;"><i data-lucide="trash-2"></i></a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="taskModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="font-weight: 700;">Split Tasks to Employees</h3>
            <form id="multiTaskForm" method="POST" action="task_tl.php">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" id="tasksData" name="tasks_data">

                <div class="input-group">
                    <label>Select Master Project</label>
                    <select id="m_proj_id" name="project_id" class="form-input" required>
                        <option value="">-- Choose Project --</option>
                        <?php 
                        $projects_result->data_seek(0);
                        while($p = $projects_result->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 10px;">ADD NEW SUB-TASK</p>
                    <input type="text" id="m_title" placeholder="Task Title (e.g. Login Page)" class="form-input mb-2" style="margin-bottom: 10px;">
                    <textarea id="m_desc" placeholder="Instructions..." class="form-input mb-2" style="margin-bottom: 10px;"></textarea>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="text" id="m_assignee" placeholder="Assignee Name" class="form-input" list="empList">
                        <input type="date" id="m_date" class="form-input">
                        <select id="m_priority" class="form-input">
                            <option>High</option><option selected>Medium</option><option>Low</option>
                        </select>
                    </div>
                    <button type="button" class="btn" onclick="addToQueue()" style="width: 100%; background: #0d9488;">+ Add Task to List</button>
                </div>

                <div class="task-list-preview" id="queuePreview">
                    <p style="color: #94a3b8; font-size: 12px; text-align: center;">No tasks added to the split queue yet.</p>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" style="background:#fff; color:#475569; border:1px solid #ccc;" onclick="closeModal('taskModal')">Cancel</button>
                    <button type="submit" class="btn" id="submitBtn" disabled>Finalize & Assign All Tasks</button>
                </div>
            </form>
        </div>
    </div>

    <datalist id="empList">
        <?php 
        $e_stmt = $conn->prepare("SELECT full_name FROM employee_profiles WHERE reporting_to = ?");
        $e_stmt->bind_param("i", $tl_id);
        $e_stmt->execute();
        $e_res = $e_stmt->get_result();
        while($e = $e_res->fetch_assoc()) { echo "<option value='".htmlspecialchars($e['full_name'])."'>"; }
        ?>
    </datalist>

    <script>
        lucide.createIcons();
        let taskQueue = [];

        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); taskQueue = []; renderQueue(); }

        function addToQueue() {
            const title = document.getElementById('m_title').value.trim();
            const desc = document.getElementById('m_desc').value.trim();
            const assignee = document.getElementById('m_assignee').value.trim();
            const date = document.getElementById('m_date').value;
            const priority = document.getElementById('m_priority').value;

            if(!title || !assignee || !date) { alert("Please fill Title, Assignee, and Date for this sub-task."); return; }

            taskQueue.push({ title, desc, assignees: assignee, due_date: date, priority });
            
            // Clear inputs for next entry
            document.getElementById('m_title').value = '';
            document.getElementById('m_desc').value = '';
            document.getElementById('m_assignee').value = '';
            
            renderQueue();
        }

        function removeFromQueue(index) {
            taskQueue.splice(index, 1);
            renderQueue();
        }

        function renderQueue() {
            const container = document.getElementById('queuePreview');
            const hiddenInput = document.getElementById('tasksData');
            const submitBtn = document.getElementById('submitBtn');

            if (taskQueue.length === 0) {
                container.innerHTML = '<p style="color: #94a3b8; font-size: 12px; text-align: center;">No tasks added to the split queue yet.</p>';
                submitBtn.disabled = true;
                return;
            }

            submitBtn.disabled = false;
            hiddenInput.value = JSON.stringify(taskQueue);

            container.innerHTML = taskQueue.map((t, i) => `
                <div class="preview-item">
                    <div>
                        <div style="font-weight:700; font-size:13px;">${t.title}</div>
                        <div style="font-size:11px; color:#64748b;">Assignee: ${t.assignees} | Due: ${t.due_date}</div>
                    </div>
                    <button type="button" onclick="removeFromQueue(${i})" style="color:#ef4444; border:none; background:none; cursor:pointer;"><i data-lucide="x-circle" style="width:18px;"></i></button>
                </div>
            `).join('');
            lucide.createIcons();
        }
    </script>
</body>
</html>