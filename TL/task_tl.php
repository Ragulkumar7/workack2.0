<?php
// TL/task_tl.php - Team Leader Task Management

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php'; 

// ROOT PATH FOR FILE DOWNLOADS
$path_to_root = '../';

// CHECK LOGIN & ROLE
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$tl_id = $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION (Secure AJAX Implementation) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_task') {
    ob_clean(); // Prevent any HTML from breaking the JSON response
    header('Content-Type: application/json');

    $project_id = (int)$_POST['project_id']; 
    
    // Get the JSON string and decode it
    $tasks_json = $_POST['tasks_data'] ?? '';
    $tasks_array = json_decode($tasks_json, true);

    if (!empty($tasks_array) && is_array($tasks_array)) {
        // [UPGRADE]: Added assigned_to_user_id to ensure enterprise performance tracking works accurately
        $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, task_title, description, assigned_to_user_id, assigned_to, priority, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        
        foreach ($tasks_array as $task) {
            $title = $task['title'];
            $desc = $task['desc'];
            $assignee_id = (int)$task['assignee_id'];
            $assignee_name = $task['assignee_name'];
            $priority = $task['priority'];
            $due_date = $task['due_date'];
            
            $stmt->bind_param("ississsi", $project_id, $title, $desc, $assignee_id, $assignee_name, $priority, $due_date, $tl_id);
            $stmt->execute();
        }
        $stmt->close();
        
        // Return Success JSON
        echo json_encode(['status' => 'success', 'message' => 'All tasks successfully assigned to your team!']);
        exit();
    } else {
        // Return Error JSON
        echo json_encode(['status' => 'error', 'message' => 'Error: No tasks were found in the queue.']);
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

// --- 2. FETCH TASKS CREATED BY ME (Includes completed_file) ---
$tasks_sql = "SELECT t.*, p.project_name 
              FROM project_tasks t 
              JOIN projects p ON t.project_id = p.id 
              WHERE t.created_by = ? 
              ORDER BY FIELD(t.status, 'Pending', 'In Progress', 'Completed'), t.due_date ASC";
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
        .task-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 14px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; background: #f8fafc; }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary); color: white; gap: 8px; transition: 0.2s; }
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 700px; max-width: 100%; border-radius: 12px; padding: 24px; max-height: 90vh; overflow-y: auto; }
        .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; box-sizing: border-box; margin-bottom: 10px; outline: none; transition: 0.2s; }
        .form-input:focus { border-color: var(--primary); }
        .preview-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        
        .badge-priority { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; }

        /* Modern Toast Notification CSS */
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 14px 20px; position: fixed; z-index: 10000; left: 50%; bottom: 30px; transform: translateX(-50%); opacity: 0; transition: opacity 0.4s, bottom 0.4s; font-size: 14px; font-weight: 600; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.success { background-color: #0f766e; } /* Matches Primary Theme */
        #toast.error { background-color: #ef4444; }
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
                <h3 style="font-size: 16px; font-weight:700; margin: 0 0 8px 0; color: #0f172a;"><?= htmlspecialchars($proj['project_name']) ?></h3>
                <p style="font-size: 13px; color: #64748b; margin: 0; line-height: 1.5;"><?= htmlspecialchars($proj['description'] ?? 'No description') ?></p>
                <div style="font-size: 12px; margin-top: 16px; font-weight:600; color: #334155; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="calendar" style="width: 14px; color: #0f766e;"></i> Deadline: <?= date('d M Y', strtotime($proj['deadline'])) ?>
                </div>
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
                            <th>Status</th>
                            <th style="text-align:right;">Actions / File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($tasks_result) > 0): ?>
                            <?php while($task = $tasks_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <b style="color: #0f172a;"><?= htmlspecialchars($task['task_title']) ?></b><br>
                                    <small style="color: #64748b;"><?= htmlspecialchars($task['description']) ?></small>
                                </td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($task['project_name']) ?></td>
                                <td style="font-weight: 500; color: #0f766e;"><?= htmlspecialchars($task['assigned_to']) ?></td>
                                
                                <td>
                                    <?php 
                                        $prio_bg = '#f1f5f9'; $prio_col = '#64748b';
                                        if($task['priority'] == 'High') { $prio_bg = '#fef2f2'; $prio_col = '#dc2626'; }
                                        if($task['priority'] == 'Medium') { $prio_bg = '#fff7ed'; $prio_col = '#ea580c'; }
                                        if($task['priority'] == 'Low') { $prio_bg = '#f0fdf4'; $prio_col = '#16a34a'; }
                                    ?>
                                    <span class="badge-priority" style="background: <?= $prio_bg ?>; color: <?= $prio_col ?>; border: 1px solid <?= $prio_bg ?>;">
                                        <?= htmlspecialchars($task['priority']) ?>
                                    </span>
                                </td>
                                
                                <td style="font-size: 12px; font-weight: 500;"><?= date('d M Y', strtotime($task['due_date'])) ?></td>
                                
                                <td>
                                    <?php 
                                        $stat_bg = '#f1f5f9'; $stat_col = '#475569';
                                        if ($task['status'] == 'Pending' || $task['status'] == 'To Do') { $stat_bg = '#fff7ed'; $stat_col = '#ea580c'; }
                                        if ($task['status'] == 'In Progress') { $stat_bg = '#eff6ff'; $stat_col = '#2563eb'; }
                                        if ($task['status'] == 'Completed') { $stat_bg = '#f0fdf4'; $stat_col = '#16a34a'; }
                                    ?>
                                    <span class="badge-status" style="background: <?= $stat_bg ?>; color: <?= $stat_col ?>;">
                                        <?= htmlspecialchars($task['status']) ?>
                                    </span>
                                </td>
                                
                                <td align="right">
                                    <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center;">
                                        
                                        <?php if($task['status'] == 'Completed' && !empty($task['completed_file'])): ?>
                                            <a href="<?= $path_to_root . htmlspecialchars($task['completed_file']) ?>" download target="_blank" 
                                               style="color: #0ea5e9; background: #e0f2fe; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 4px; transition: 0.2s;">
                                                <i data-lucide="download" style="width:14px; height:14px;"></i> Download
                                            </a>
                                        <?php elseif($task['status'] == 'Completed'): ?>
                                            <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No File</span>
                                        <?php endif; ?>

                                        <a href="task_tl.php?delete_task=<?= $task['id'] ?>" style="color:#ef4444; background: #fef2f2; padding: 6px; border-radius: 6px; display: flex;" title="Delete Task" onclick="return confirm('Are you sure you want to delete this task?');">
                                            <i data-lucide="trash-2" style="width:16px; height:16px;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 40px; color: #64748b; font-weight: 500;">No tasks created by you yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="taskModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="font-weight: 800; margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #0f172a;">Split Tasks to Employees</h3>
            
            <form id="multiTaskForm">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" id="tasksData" name="tasks_data">

                <div class="input-group" style="margin-bottom: 20px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color: #334155;">Select Master Project</label>
                    <select id="m_proj_id" name="project_id" class="form-input" required>
                        <option value="">-- Choose Project --</option>
                        <?php 
                        $projects_result->data_seek(0);
                        while($p = $projects_result->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 24px; border: 1px solid #e2e8f0;">
                    <p style="font-size: 11px; font-weight: 800; color: #0f766e; margin-top: 0; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">1. Define Sub-Task Details</p>
                    
                    <input type="text" id="m_title" placeholder="Sub-Task Title (e.g. Login Page)" class="form-input">
                    <textarea id="m_desc" placeholder="Specific Instructions for Assignee..." class="form-input" rows="2"></textarea>
                    
                    <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                        <div style="flex: 2;">
                            <label style="font-size:11px; font-weight:600; color: #475569; display: block; margin-bottom: 4px;">Assignee</label>
                            
                            <select id="m_assignee" class="form-input">
                                <option value="">-- Select Employee --</option>
                                <?php 
                                $e_stmt = $conn->prepare("SELECT user_id, full_name FROM employee_profiles WHERE reporting_to = ?");
                                $e_stmt->bind_param("i", $tl_id);
                                $e_stmt->execute();
                                $e_res = $e_stmt->get_result();
                                while($e = $e_res->fetch_assoc()) { 
                                    echo "<option value='".$e['user_id']."'>".htmlspecialchars($e['full_name'])."</option>"; 
                                }
                                ?>
                            </select>

                        </div>
                        <div style="flex: 1;">
                            <label style="font-size:11px; font-weight:600; color: #475569; display: block; margin-bottom: 4px;">Due Date</label>
                            <input type="date" id="m_date" class="form-input">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size:11px; font-weight:600; color: #475569; display: block; margin-bottom: 4px;">Priority</label>
                            <select id="m_priority" class="form-input">
                                <option>High</option><option selected>Medium</option><option>Low</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn" onclick="addToQueue()" style="width: 100%; background: #e0f2fe; color: #0284c7; border: 1px dashed #bae6fd; font-weight: 700;">+ Add Task to Queue</button>
                </div>

                <div id="queuePreview" style="margin-top: 20px; border-top: 2px solid #f1f5f9; padding-top: 20px;">
                    <p style="color: #94a3b8; font-size: 13px; text-align: center; margin: 10px 0;">Queue is empty. Add sub-tasks above.</p>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    <button type="button" class="btn" style="background:#fff; color:#475569; border:1px solid #cbd5e1;" onclick="closeModal('taskModal')">Cancel</button>
                    <button type="submit" class="btn" id="submitBtn" disabled>Finalize & Assign All Tasks</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast"></div>

    <script>
        lucide.createIcons();
        let taskQueue = [];

        function openModal(id) { 
            document.getElementById(id).classList.add('active'); 
        }

        function closeModal(id) { 
            document.getElementById(id).classList.remove('active'); 
            taskQueue = []; 
            renderQueue(); 
            
            // Reset main form inputs
            document.getElementById('m_proj_id').value = '';
            document.getElementById('m_title').value = '';
            document.getElementById('m_desc').value = '';
            document.getElementById('m_assignee').value = '';
            document.getElementById('m_date').value = '';
        }

        function showToast(message, type) {
            const toast = document.getElementById("toast");
            toast.innerText = message;
            toast.className = "show " + type;
            setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
        }

        function addToQueue() {
            const title = document.getElementById('m_title').value.trim();
            const desc = document.getElementById('m_desc').value.trim();
            const assigneeSelect = document.getElementById('m_assignee');
            
            // Get both ID and Name to ensure tracking logic works
            const assignee_id = assigneeSelect.value;
            const assignee_name = assignee_id ? assigneeSelect.options[assigneeSelect.selectedIndex].text : '';
            
            const date = document.getElementById('m_date').value;
            const priority = document.getElementById('m_priority').value;

            if(!title || !assignee_id || !date) { 
                showToast("Please fill out the Task Title, Assignee, and Due Date.", "error"); 
                return; 
            }

            taskQueue.push({ title, desc, assignee_id, assignee_name, due_date: date, priority });
            
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
                container.innerHTML = '<p style="color: #94a3b8; font-size: 13px; text-align: center; margin: 10px 0;">Queue is empty. Add sub-tasks above.</p>';
                submitBtn.disabled = true;
                hiddenInput.value = "";
                return;
            }

            submitBtn.disabled = false;
            hiddenInput.value = JSON.stringify(taskQueue);

            container.innerHTML = '<p style="font-size: 11px; font-weight: 800; color: #0f766e; margin-top: 0; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">2. Assignment List Preview</p>' + 
                taskQueue.map((t, i) => `
                <div class="preview-item">
                    <div>
                        <div style="font-weight:700; font-size:14px; color: #0f172a; margin-bottom: 3px;">${t.title}</div>
                        <div style="font-size:12px; color:#64748b;"><b>To:</b> <span style="color:#0f766e;">${t.assignee_name}</span> | <b>Due:</b> ${t.due_date} | <b>Priority:</b> ${t.priority}</div>
                    </div>
                    <button type="button" onclick="removeFromQueue(${i})" style="color:#ef4444; border:none; background:none; cursor:pointer; padding: 5px; border-radius: 4px;" title="Remove">
                        <i data-lucide="trash-2" style="width:18px;"></i>
                    </button>
                </div>
            `).join('');
            
            lucide.createIcons();
        }

        // Handle Form Submission smoothly via AJAX
        document.getElementById('multiTaskForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent standard page reload

            const projectId = document.getElementById('m_proj_id').value;
            if (!projectId) {
                showToast("Please select a Master Project.", "error");
                return;
            }

            if (taskQueue.length === 0) {
                showToast("Please add at least one task to the queue.", "error");
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Assigning...'; // Visual feedback

            const fd = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: fd
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeModal('taskModal');
                    // Smooth reload to show newly added tasks on the board
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200); 
                } else {
                    showToast(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Finalize & Assign All Tasks';
                }
            })
            .catch(err => {
                console.error("Error:", err);
                showToast('Network error occurred.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerText = 'Finalize & Assign All Tasks';
            });
        });
    </script>
</body>
</html>