<?php
// TL/task_tl.php - Team Leader Task Management

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php'; 

$path_to_root = '../';

// CHECK LOGIN & ROLE
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$tl_id = $_SESSION['user_id'];

// --- HANDLE SUB-TASK FORM SUBMISSION (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_task') {
    // Ensure absolutely NO previous output corrupts the JSON response
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $project_id = (int)$_POST['project_id']; 
    $tasks_json = $_POST['tasks_data'] ?? '';
    $tasks_array = json_decode($tasks_json, true);

    if (!empty($tasks_array) && is_array($tasks_array)) {
        $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, task_title, description, assigned_to_user_id, assigned_to, priority, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        
        if ($stmt) {
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
            echo json_encode(['status' => 'success', 'message' => 'All tasks successfully assigned to your team!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: Could not prepare statement.']);
        }
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error: No tasks were found in the queue.']);
        exit();
    }
}

// --- HANDLE MULTIPLE FILE UPLOAD TO MANAGER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_project_files') {
    $project_id = (int)$_POST['project_id'];
    
    if (isset($_FILES['project_files']) && !empty($_FILES['project_files']['name'][0])) {
        $upload_dir = '../uploads/projects/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $zip_filename = time() . '_Project_' . $project_id . '_Final_Submission.zip';
            $zip_filepath = $upload_dir . $zip_filename;
            
            if ($zip->open($zip_filepath, ZipArchive::CREATE) === TRUE) {
                $file_count = count($_FILES['project_files']['name']);
                for($i = 0; $i < $file_count; $i++) {
                    $tmp_name = $_FILES['project_files']['tmp_name'][$i];
                    $name = basename($_FILES['project_files']['name'][$i]);
                    if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                        $zip->addFile($tmp_name, $name);
                    }
                }
                $zip->close();
                
                $db_filepath = 'uploads/projects/' . $zip_filename;
                
                // Update Project Status to Completed and attach ZIP file
                $stmt = $conn->prepare("UPDATE projects SET completed_file = ?, status = 'Completed' WHERE id = ? AND leader_id = ?");
                $stmt->bind_param("sii", $db_filepath, $project_id, $tl_id);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['success_msg'] = "Files auto-zipped and successfully submitted to the Manager for review!";
            } else {
                $_SESSION['error_msg'] = "Failed to bundle files into ZIP. Please try again.";
            }
        } else {
            $_SESSION['error_msg'] = "ZipArchive extension is disabled on your server.";
        }
    }
    header("Location: task_tl.php");
    exit();
}

// --- HANDLE DELETE SUB-TASK ---
if (isset($_GET['delete_task'])) {
    $task_id = intval($_GET['delete_task']);
    $conn->query("DELETE FROM project_tasks WHERE id = $task_id AND created_by = $tl_id");
    header("Location: task_tl.php");
    exit();
}

// --- 1. FETCH PROJECTS ASSIGNED TO ME WITH REAL-TIME TASK STATS ---
$projects_sql = "
    SELECT p.*, 
        (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) as total_tasks,
        (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'Completed') as completed_tasks
    FROM projects p 
    WHERE p.leader_id = ? 
    ORDER BY p.id DESC
";
$p_stmt = $conn->prepare($projects_sql);
$p_stmt->bind_param("i", $tl_id);
$p_stmt->execute();
$projects_result = $p_stmt->get_result();

// --- 2. FETCH SUB-TASKS CREATED BY ME ---
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #0f766e; --primary-hover: #115e59; --bg-body: #f8fafc; --bg-card: #ffffff; --text-main: #0f172a; --text-muted: #64748b; --border: #e2e8f0; --sidebar-width: 95px; }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; }
/* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT 
           ========================================================== */
        .main-content, #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            padding: 30px; /* Adjust inner padding as needed */
            min-height: 100vh;
        }

        /* Desktop: Shifts content right when secondary sub-menu opens */
        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Mobile & Tablet Adjustments */
        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 80px 15px 30px !important; /* Top padding clears the hamburger menu */
            }
            
            /* Prevent shifting on mobile (menu floats over content instead) */
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.5px;}
        .breadcrumb { font-size: 13px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; margin-top: 6px; font-weight: 500;}
        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; margin-bottom: 40px; align-items: stretch; }
        .project-card { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); border-top: 4px solid var(--primary); padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; justify-content: space-between; min-height: 200px;}
        .project-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.08); }
        .task-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 16px 24px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; background: #f8fafc; letter-spacing: 0.5px; border-bottom: 1px solid var(--border);}
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary); color: white; gap: 8px; transition: 0.2s; box-shadow: 0 2px 4px rgba(15, 118, 110, 0.2);}
        .btn:hover { background-color: var(--primary-hover); transform: translateY(-1px); }
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 700px; max-width: 100%; border-radius: 16px; padding: 28px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .form-input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; box-sizing: border-box; margin-bottom: 12px; outline: none; transition: 0.2s; font-weight: 500; color: #1e293b;}
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1); }
        .form-input::file-selector-button { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 12px; color: #475569; font-weight: 700; cursor: pointer; transition: 0.2s; margin-right: 10px;}
        .form-input::file-selector-button:hover { background: #e2e8f0; }
        .preview-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        
        .badge-priority { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;}
        .badge-status { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Modern Toast Notification CSS */
        #toast { visibility: hidden; min-width: 250px; background-color: #1e293b; color: #fff; text-align: center; border-radius: 8px; padding: 14px 20px; position: fixed; z-index: 10000; left: 50%; bottom: 30px; transform: translateX(-50%); opacity: 0; transition: opacity 0.4s, bottom 0.4s; font-size: 14px; font-weight: 600; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.success { background-color: #0f766e; } 
        #toast.error { background-color: #ef4444; }
    </style>
</head>
<body>

    <?php include '../sidebars.php'; ?>

    <main id="mainContent" class="main-content">
        <?php include('../header.php'); ?>
        
        <div class="page-header">
            <div>
                <h1>Team Task Management</h1>
                <div class="breadcrumb">
                    <i data-lucide="layout-dashboard" style="width:14px; color: #0f766e;"></i>
                    <span>/</span> Performance <span>/</span> Task Board
                </div>
            </div>
            <button class="btn" onclick="openModal('taskModal')">
                <i data-lucide="plus" style="width:16px;"></i> Split New Task
            </button>
        </div>

        <div id="toast"></div>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <script>document.addEventListener("DOMContentLoaded", function() { showToast("<?= $_SESSION['success_msg'] ?>", "success"); });</script>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error_msg'])): ?>
            <script>document.addEventListener("DOMContentLoaded", function() { showToast("<?= $_SESSION['error_msg'] ?>", "error"); });</script>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <div class="projects-grid">
            <?php while($proj = $projects_result->fetch_assoc()): 
                // Dynamic Live Progress Calculation
                $total_t = $proj['total_tasks'] > 0 ? $proj['total_tasks'] : 1; 
                $calc_progress = round(($proj['completed_tasks'] / $total_t) * 100);
            ?>
            <div class="project-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 10px;">
                        <h3 style="font-size: 16px; font-weight:800; margin: 0; color: #0f172a; line-height: 1.3;"><?= htmlspecialchars($proj['project_name']) ?></h3>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;">
                            
                            <?php 
                                $p_prio_bg = '#f1f5f9'; $p_prio_col = '#64748b';
                                if($proj['priority'] == 'High') { $p_prio_bg = '#fef2f2'; $p_prio_col = '#dc2626'; }
                                if($proj['priority'] == 'Medium') { $p_prio_bg = '#fff7ed'; $p_prio_col = '#ea580c'; }
                                if($proj['priority'] == 'Low') { $p_prio_bg = '#f0fdf4'; $p_prio_col = '#16a34a'; }
                            ?>
                            <span class="badge-priority" style="background: <?= $p_prio_bg ?>; color: <?= $p_prio_col ?>; border: 1px solid <?= $p_prio_bg ?>;">
                                <?= htmlspecialchars($proj['priority']) ?>
                            </span>
                            
                            <?php 
                                $p_stat_bg = '#f1f5f9'; $p_stat_col = '#475569';
                                if ($proj['status'] == 'Pending') { $p_stat_bg = '#fff7ed'; $p_stat_col = '#ea580c'; }
                                if ($proj['status'] == 'Active' || $proj['status'] == 'In Progress') { $p_stat_bg = '#eff6ff'; $p_stat_col = '#2563eb'; }
                                if ($proj['status'] == 'Completed') { $p_stat_bg = '#f0fdf4'; $p_stat_col = '#16a34a'; }
                                if ($proj['status'] == 'Hold') { $p_stat_bg = '#fef2f2'; $p_stat_col = '#dc2626'; }
                            ?>
                            <span class="badge-status" style="background: <?= $p_stat_bg ?>; color: <?= $p_stat_col ?>;">
                                <?= htmlspecialchars($proj['status']) ?>
                            </span>

                        </div>
                    </div>
                    <p style="font-size: 13px; color: #64748b; margin: 0 0 16px 0; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= htmlspecialchars($proj['description'] ?? 'No description provided for this project.') ?>
                    </p>
                </div>

                <div>
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 10px; font-weight: 800; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <span>Project Progress</span>
                            <span style="color: #0f766e;"><?= $calc_progress ?>%</span>
                        </div>
                        <div style="width: 100%; background: #f1f5f9; border-radius: 6px; height: 6px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                            <div style="height: 100%; background: #0f766e; width: <?= $calc_progress ?>%; border-radius: 6px; transition: width 0.5s ease;"></div>
                        </div>
                    </div>

                    <div style="font-size: 12px; font-weight:600; color: #334155; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                        <span style="display: flex; align-items: center; gap: 6px;" title="Target Deadline">
                            <i data-lucide="calendar" style="width: 14px; color: #0f766e;"></i> <?= date('d M Y', strtotime($proj['deadline'])) ?>
                        </span>
                        <span style="font-size: 10px; color: #64748b; font-weight: 800; background: #f8fafc; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; text-transform: uppercase;">
                            <span style="color:#0f766e;"><?= $proj['completed_tasks'] ?? 0 ?></span> / <?= $proj['total_tasks'] ?? 0 ?> Tasks
                        </span>
                    </div>

                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e2e8f0;">
                        <?php if (!empty($proj['completed_file'])): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size: 11px; color: #10b981; font-weight: 700; display:flex; align-items:center; gap:4px;"><i data-lucide="check-circle" style="width:14px;"></i> Submitted for Review</span>
                                <button onclick="document.getElementById('upload-form-<?= $proj['id'] ?>').style.display='block'" style="background:none; border:none; color:#0f766e; font-size:10px; font-weight:800; cursor:pointer; text-transform:uppercase; padding:0;">Resubmit</button>
                            </div>
                        <?php else: ?>
                            <p style="font-size: 10px; font-weight: 800; color: #64748b; margin-top: 0; margin-bottom: 8px; text-transform: uppercase; display:flex; align-items:center; gap:4px;"><i data-lucide="upload" style="width:12px;"></i> Submit Final Work to Manager</p>
                        <?php endif; ?>
                        
                        <form id="upload-form-<?= $proj['id'] ?>" action="" method="POST" enctype="multipart/form-data" style="display: <?= !empty($proj['completed_file']) ? 'none' : 'block' ?>; margin-top: 8px;">
                            <input type="hidden" name="action" value="submit_project_files">
                            <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                            <div style="display: flex; gap: 8px; align-items: stretch;">
                                <input type="file" name="project_files[]" multiple required class="form-input" style="margin-bottom: 0; padding: 6px; font-size: 11px; flex: 1; border: 1px dashed #cbd5e1; background: #fff;" title="Hold Ctrl/Cmd to select multiple files">
                                <button type="submit" class="btn" style="padding: 6px 12px; font-size: 11px; white-space: nowrap;"><i data-lucide="send" style="width:14px;"></i> Submit</button>
                            </div>
                            <small style="font-size: 9px; color: #94a3b8; display: block; margin-top: 6px; font-weight:500;">Tip: Hold Ctrl/Cmd to select multiple files. They will be auto-zipped.</small>
                        </form>
                    </div>

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
                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td>
                                    <b style="color: #0f172a; font-size: 14px;"><?= htmlspecialchars($task['task_title']) ?></b><br>
                                    <small style="color: #64748b; font-size: 12px;"><?= htmlspecialchars($task['description']) ?></small>
                                </td>
                                <td style="font-weight: 600; color: #334155;"><?= htmlspecialchars($task['project_name']) ?></td>
                                <td style="font-weight: 700; color: #0f766e;"><?= htmlspecialchars($task['assigned_to']) ?></td>
                                
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
                                
                                <td style="font-size: 13px; font-weight: 600; color: #475569;"><?= date('d M Y', strtotime($task['due_date'])) ?></td>
                                
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
                                               style="color: #0ea5e9; background: #e0f2fe; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 6px; transition: 0.2s; border: 1px solid #bae6fd;">
                                                <i data-lucide="download" style="width:14px; height:14px;"></i> Download
                                            </a>
                                        <?php elseif($task['status'] == 'Completed'): ?>
                                            <span style="font-size: 11px; color: #94a3b8; font-style: italic; font-weight: 600;">No File Submitted</span>
                                        <?php endif; ?>

                                        <a href="task_tl.php?delete_task=<?= $task['id'] ?>" style="color:#ef4444; background: #fef2f2; padding: 6px 8px; border-radius: 6px; display: flex; border: 1px solid #fecaca; transition: 0.2s;" title="Delete Task" onclick="return confirm('Are you sure you want to delete this task entirely?');" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                                            <i data-lucide="trash-2" style="width:16px; height:16px;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 60px; color: #94a3b8; font-weight: 500;">
                                <i data-lucide="clipboard-list" style="width: 40px; height: 40px; margin-bottom: 12px; opacity: 0.5;"></i><br>
                                No tasks created by you yet.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="taskModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-weight: 800; margin: 0; font-size: 20px; color: #0f172a;">Split Tasks to Employees</h3>
                <button onclick="closeModal('taskModal')" style="background:none; border:none; color:#64748b; cursor:pointer;"><i data-lucide="x" style="width: 24px;"></i></button>
            </div>
            
            <form id="multiTaskForm">
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" id="tasksData" name="tasks_data">

                <div class="input-group" style="margin-bottom: 24px;">
                    <label style="display:block; font-size:12px; font-weight:800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom:8px; color: #475569;">Select Master Project</label>
                    <select id="m_proj_id" name="project_id" class="form-input" required style="font-size: 15px; padding: 12px;">
                        <option value="">-- Choose Project to Split --</option>
                        <?php 
                        $projects_result->data_seek(0);
                        while($p = $projects_result->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="background: #f8fafc; padding: 24px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #e2e8f0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <p style="font-size: 11px; font-weight: 800; color: #0f766e; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;"><i data-lucide="list-plus" style="width: 14px;"></i> 1. Define Sub-Task Details</p>
                    
                    <input type="text" id="m_title" placeholder="Sub-Task Title (e.g. Develop Login Page API)" class="form-input">
                    <textarea id="m_desc" placeholder="Specific Instructions for Assignee..." class="form-input" rows="2"></textarea>
                    
                    <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                        <div style="flex: 2;">
                            <label style="font-size:11px; font-weight:700; color: #475569; display: block; margin-bottom: 6px; text-transform: uppercase;">Assignee</label>
                            
                            <select id="m_assignee" class="form-input">
                                <option value="">-- Select Employee --</option>
                                <?php 
                                $e_stmt = $conn->prepare("
                                    SELECT ep.user_id, COALESCE(ep.full_name, u.name, u.username) as full_name, ep.designation 
                                    FROM employee_profiles ep
                                    LEFT JOIN users u ON ep.user_id = u.id
                                    WHERE (ep.reporting_to = ? OR ep.manager_id = ? OR ep.user_id = ?) 
                                    ORDER BY ep.full_name ASC
                                ");
                                $e_stmt->bind_param("iii", $tl_id, $tl_id, $tl_id);
                                $e_stmt->execute();
                                $e_res = $e_stmt->get_result();
                                while($e = $e_res->fetch_assoc()) { 
                                    $designation_badge = !empty($e['designation']) ? htmlspecialchars($e['designation']) : 'Employee';
                                    echo "<option value='".$e['user_id']."'>".htmlspecialchars($e['full_name'])." (".$designation_badge.")</option>"; 
                                }
                                $e_stmt->close();
                                ?>
                            </select>

                        </div>
                        <div style="flex: 1;">
                            <label style="font-size:11px; font-weight:700; color: #475569; display: block; margin-bottom: 6px; text-transform: uppercase;">Due Date</label>
                            <input type="date" id="m_date" class="form-input">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size:11px; font-weight:700; color: #475569; display: block; margin-bottom: 6px; text-transform: uppercase;">Priority</label>
                            <select id="m_priority" class="form-input">
                                <option>High</option><option selected>Medium</option><option>Low</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn" onclick="addToQueue()" style="width: 100%; background: #f0fdfa; color: #0d9488; border: 1px dashed #99f6e4; font-weight: 800; font-size: 14px; box-shadow: none;">+ Add Task to Queue</button>
                </div>

                <div id="queuePreview" style="margin-top: 20px; border-top: 2px solid #f1f5f9; padding-top: 20px;">
                    <p style="color: #94a3b8; font-size: 13px; text-align: center; margin: 10px 0; font-weight: 500;">Queue is empty. Add sub-tasks above.</p>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
                    <button type="button" class="btn" style="background:#fff; color:#475569; border:1px solid #cbd5e1; box-shadow: none;" onclick="closeModal('taskModal')">Cancel</button>
                    <button type="submit" class="btn" id="submitBtn" disabled>Finalize & Assign All Tasks</button>
                </div>
            </form>
        </div>
    </div>

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
            
            document.getElementById('m_proj_id').value = '';
            document.getElementById('m_title').value = '';
            document.getElementById('m_desc').value = '';
            document.getElementById('m_assignee').value = '';
            document.getElementById('m_date').value = '';
        }

        function showToast(message, type) {
            const toast = document.getElementById("toast");
            if (toast) { // Added a check just to be extra safe
                toast.innerText = message;
                toast.className = "show " + type;
                setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
            } else {
                console.warn("Toast element not found! Message was:", message);
            }
        }

        function addToQueue() {
            const title = document.getElementById('m_title').value.trim();
            const desc = document.getElementById('m_desc').value.trim();
            const assigneeSelect = document.getElementById('m_assignee');
            
            const assignee_id = assigneeSelect.value;
            let assignee_name = assignee_id ? assigneeSelect.options[assigneeSelect.selectedIndex].text : '';
            assignee_name = assignee_name.split(' (')[0]; 
            
            const date = document.getElementById('m_date').value;
            const priority = document.getElementById('m_priority').value;

            if(!title || !assignee_id || !date) { 
                showToast("Please fill out the Task Title, Assignee, and Due Date.", "error"); 
                return; 
            }

            taskQueue.push({ title, desc, assignee_id, assignee_name, due_date: date, priority });
            
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
                container.innerHTML = '<p style="color: #94a3b8; font-size: 13px; text-align: center; margin: 10px 0; font-weight: 500;">Queue is empty. Add sub-tasks above.</p>';
                submitBtn.disabled = true;
                hiddenInput.value = "";
                return;
            }

            submitBtn.disabled = false;
            hiddenInput.value = JSON.stringify(taskQueue);

            container.innerHTML = '<p style="font-size: 11px; font-weight: 800; color: #0f766e; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;"><i data-lucide="layers" style="width: 14px;"></i> 2. Assignment List Preview</p>' + 
                taskQueue.map((t, i) => `
                <div class="preview-item">
                    <div>
                        <div style="font-weight:800; font-size:14px; color: #0f172a; margin-bottom: 4px;">${t.title}</div>
                        <div style="font-size:12px; color:#64748b; display: flex; gap: 12px; align-items: center;">
                            <span><b>To:</b> <span style="color:#0f766e; font-weight: 700;">${t.assignee_name}</span></span>
                            <span><b>Due:</b> ${t.due_date}</span>
                            <span><b>Priority:</b> <span class="badge-priority" style="font-size: 9px; padding: 2px 6px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">${t.priority}</span></span>
                        </div>
                    </div>
                    <button type="button" onclick="removeFromQueue(${i})" style="color:#ef4444; border:1px solid #fecaca; background:#fef2f2; cursor:pointer; padding: 8px; border-radius: 6px; transition: 0.2s;" title="Remove from Queue" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                        <i data-lucide="trash-2" style="width:16px;"></i>
                    </button>
                </div>
            `).join('');
            
            lucide.createIcons();
        }

        document.getElementById('multiTaskForm').addEventListener('submit', function(e) {
            e.preventDefault(); 

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
            submitBtn.innerText = 'Assigning...'; 

            const fd = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: fd
            })
            .then(response => {
                // Handle cases where the server returns HTML instead of JSON
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeModal('taskModal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200); 
                } else {
                    showToast(data.message || 'Server error', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Finalize & Assign All Tasks';
                }
            })
            .catch(err => {
                console.error("Error:", err);
                showToast('Network error or invalid response from server.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerText = 'Finalize & Assign All Tasks';
            });
        });
    </script>
</body>
</html>