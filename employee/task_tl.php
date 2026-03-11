<?php 
// 1. ROBUST DATABASE CONNECTION & SESSION
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/workack2.0/include/db_connect.php';
if (file_exists($dbPath)) { include_once($dbPath); $path_to_root = '../'; } 
else { include_once('../include/db_connect.php'); $path_to_root = '../'; }

if (!isset($_SESSION['user_id'])) { header("Location: {$path_to_root}index.php"); exit(); }
$current_user_id = $_SESSION['user_id'];

// --- SILENT DB UPDATE FOR FILE UPLOAD ---
$conn->query("ALTER TABLE project_tasks ADD COLUMN IF NOT EXISTS completed_file VARCHAR(255) NULL DEFAULT NULL AFTER status");

// --- HANDLE STATUS UPDATE VIA GET REQUEST (Starting Task) ---
if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $tid = intval($_GET['update_id']);
    $stat = $_GET['new_status'];
    
    if ($stat === 'In Progress') {
        $stmt = $conn->prepare("UPDATE project_tasks SET status = ? WHERE id = ? AND assigned_to_user_id = ?");
        $stmt->bind_param("sii", $stat, $tid, $current_user_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// --- HANDLE TASK COMPLETION & RE-UPLOAD VIA POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_task_id'])) {
    $tid = intval($_POST['complete_task_id']);
    
    // Check if file is uploaded
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
        $projectRoot = dirname(__DIR__); 
        $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR;
        
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        
        // Safe file name generation
        $fileExtension = strtolower(pathinfo($_FILES['task_file']['name'], PATHINFO_EXTENSION));
        $originalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['task_file']['name'], PATHINFO_FILENAME));
        $fileName = time() . '_' . $originalName . '.' . $fileExtension;
        $targetFilePath = $uploadDir . $fileName;
        
        // Upload new file
        if (move_uploaded_file($_FILES['task_file']['tmp_name'], $targetFilePath)) {
            $dbFilePath = 'uploads/tasks/' . $fileName;
            
            // Delete old file logic
            $check_stmt = $conn->prepare("SELECT completed_file FROM project_tasks WHERE id = ?");
            $check_stmt->bind_param("i", $tid);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($old_row = $check_res->fetch_assoc()) {
                if (!empty($old_row['completed_file'])) {
                    $old_file_path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $old_row['completed_file']);
                    if (file_exists($old_file_path)) { unlink($old_file_path); }
                }
            }
            $check_stmt->close();
            
            // Database update to Completed (Triggers Performance Score Increase!)
            $stmt = $conn->prepare("UPDATE project_tasks SET status = 'Completed', completed_file = ? WHERE id = ? AND assigned_to_user_id = ?");
            $stmt->bind_param("sii", $dbFilePath, $tid, $current_user_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success'] = "Task completed! File uploaded successfully.";
        } else {
            $_SESSION['error'] = "Failed to move the uploaded file. Check folder permissions.";
        }
    } else {
        $_SESSION['error'] = "File upload is required or an error occurred during upload.";
    }
    
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// 2. FETCH CURRENT EMPLOYEE'S NAME
$name_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.username) as full_name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?");
$name_stmt->bind_param("i", $current_user_id);
$name_stmt->execute();
$name_res = $name_stmt->get_result()->fetch_assoc();
$current_user_name = $name_res['full_name'] ?? '';
$name_stmt->close();

// 3. FETCH TASK STATISTICS [CRITICAL UPGRADE: Using assigned_to_user_id instead of Name]
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status IN ('Pending', 'To Do') THEN 1 ELSE 0 END) as pending
    FROM project_tasks WHERE assigned_to_user_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4. FETCH TASKS [CRITICAL UPGRADE: Using assigned_to_user_id]
$tasks_query = "SELECT pt.*, 
                COALESCE(ep.full_name, u.username, 'Admin') as assigned_by_name,
                COALESCE(ep.designation, u.role, 'Team Lead') as assigned_by_role
                FROM project_tasks pt
                LEFT JOIN users u ON pt.created_by = u.id
                LEFT JOIN employee_profiles ep ON pt.created_by = ep.user_id
                WHERE pt.assigned_to_user_id = ? 
                ORDER BY pt.due_date ASC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$all_tasks_result = $stmt->get_result();

$tasks_todo = [];
$tasks_progress = [];
$tasks_completed = [];
$list_view_tasks = [];

while($task = $all_tasks_result->fetch_assoc()) {
    $task['task_description'] = $task['description'];
    $task['deadline'] = $task['due_date'];
    $assigner_url_name = urlencode($task['assigned_by_name']);
    $task['assigned_by_img'] = "https://ui-avatars.com/api/?name={$assigner_url_name}&background=0d9488&color=fff";

    $list_view_tasks[] = $task; 

    if($task['status'] == 'Pending' || $task['status'] == 'To Do') {
        $tasks_todo[] = $task;
    } elseif($task['status'] == 'In Progress') {
        $tasks_progress[] = $task;
    } elseif($task['status'] == 'Completed') {
        $tasks_completed[] = $task;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Task Board - SmartHR</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --theme-color: #0d9488; /* Updated to Teal */
            --theme-hover: #0f766e;
            --bg-body: #f8fafc; 
            --border-color: #e2e8f0; 
            --sidebar-primary-width: 95px; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-body); color: #1e293b; overflow-x: hidden; }
        .main-content { margin-left: var(--sidebar-primary-width); padding: 30px; width: calc(100% - var(--sidebar-primary-width)); transition: all 0.3s ease; }
        @media (max-width: 991px) { .main-content { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; } }
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { color: #0f172a; font-weight: 800; font-size: 28px; line-height: 1.2; letter-spacing: -0.5px;}
        .breadcrumb { font-size: 13px; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 8px;}
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; border-left: 5px solid var(--theme-color); box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .stat-info h3 { font-size: 32px; font-weight: 900; color: #0f172a; line-height: 1; margin-top: 4px;}
        .stat-info p { color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;}
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: #f0fdfa; color: var(--theme-color); }
        
        .card { background: #fff; border-radius: 16px; padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 16px 24px; color: #64748b; font-weight: 700; border-bottom: 1px solid #f1f5f9; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; }
        td { padding: 16px 24px; border-bottom: 1px solid #f8fafc; font-size: 14px; vertical-align: middle; color: #334155; }
        
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-pending { background: #fff7ed; color: #ea580c; }
        .badge-working { background: #eff6ff; color: #2563eb; }
        .badge-completed { background: #ecfdf5; color: #10b981; }
        .badge-priority { border: 1px solid transparent; padding: 4px 10px; border-radius: 6px;}
        .prio-High { background: #fef2f2; color: #dc2626; }
        .prio-Medium { background: #fff7ed; color: #ea580c; }
        .prio-Low { background: #f0fdf4; color: #16a34a; }
        
        .kanban-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 20px; align-items: start;}
        .kanban-column { background: #f8fafc; border-radius: 16px; min-height: 400px; padding: 16px; border: 1px solid var(--border-color); }
        .column-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; padding: 0 5px; letter-spacing: 0.5px;}
        .task-count { background: #e2e8f0; color: #475569; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        
        .kanban-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 16px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .kanban-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08); border-color: #cbd5e1;}
        .card-tag { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;}
        
        .card-title { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 8px; line-height: 1.3; }
        .card-desc { font-size: 13px; color: #64748b; margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;}
        
        .card-footer { display: flex; flex-direction: column; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 16px; font-size: 12px; font-weight: 600; color: #94a3b8; }
        .footer-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        
        .action-btn { background: #f0fdfa; border: 1px solid #ccfbf1; color: var(--theme-color); font-weight: 700; font-size: 12px; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; transition: 0.2s; padding: 8px 16px; border-radius: 8px;}
        .action-btn:hover { background: var(--theme-color); color: white; }
        
        .upload-form { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .file-input { flex-grow: 1; min-width: 150px; font-size: 11px; font-weight: 600; color: #64748b; background: white; padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; }
        .file-input::file-selector-button { background: #e2e8f0; border: none; padding: 6px 12px; border-radius: 4px; color: #475569; font-weight: 700; cursor: pointer; margin-right: 8px; transition: 0.2s;}
        .file-input::file-selector-button:hover { background: #cbd5e1; }
        
        .submit-btn { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(16,185,129,0.2); transition: 0.2s;}
        .submit-btn:hover { background: #059669; }
        .update-btn { background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(59,130,246,0.2); transition: 0.2s;}
        .update-btn:hover { background: #2563eb; }
        
        .completed-item { display: flex; flex-direction: column; padding: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .completed-header { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
        .check-icon { color: #10b981; font-size: 18px; margin-top: 2px; }
        .completed-text { font-weight: 700; color: #1e293b; line-height: 1.4; font-size: 14px;}
        .completed-actions { display: flex; justify-content: space-between; align-items: center; width: 100%; border-top: 1px solid #f1f5f9; padding-top: 12px; }

        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<?php include($path_to_root . 'sidebars.php'); ?>
<?php include($path_to_root . 'header.php'); ?>

<main id="mainContent" class="main-content">
    
    <div class="page-header">
        <div>
            <h2>My Assigned Tasks</h2>
            <div class="breadcrumb">
                <i class="fa-solid fa-layer-group text-teal-600 mr-2"></i>
                Performance <span style="margin: 0 6px;">/</span> Task Execution Board
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error" id="errorAlert">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <script>setTimeout(() => document.getElementById('errorAlert').style.display='none', 5000);</script>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success" id="successAlert">
            <i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <script>setTimeout(() => document.getElementById('successAlert').style.display='none', 5000);</script>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>Total Tasks</p><h3><?php echo sprintf("%02d", $stats['total'] ?? 0); ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #10b981;">
            <div class="stat-info"><p>Completed</p><h3><?php echo sprintf("%02d", $stats['completed'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="color: #10b981; background: #ecfdf5;"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #3b82f6;">
            <div class="stat-info"><p>In Progress</p><h3><?php echo sprintf("%02d", $stats['in_progress'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="color: #3b82f6; background: #eff6ff;"><i class="fa-solid fa-spinner"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b;">
            <div class="stat-info"><p>Pending</p><h3><?php echo sprintf("%02d", $stats['pending'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="color: #f59e0b; background: #fffbeb;"><i class="fa-regular fa-clock"></i></div>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 800; margin-bottom: 20px; color: #0f172a; font-size: 18px;">Master Assignment List</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="35%">Task Description</th>
                        <th width="20%">Assigned By</th>
                        <th width="15%">Deadline</th>
                        <th width="15%">Priority</th>
                        <th width="15%">Status</th>
                        </tr>
                </thead>
                <tbody>
                    <?php if (count($list_view_tasks) > 0): ?>
                        <?php foreach($list_view_tasks as $row): ?>
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td>
                                <strong style="color: #1e293b; display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($row['task_title']); ?></strong>
                                <small style="color: #64748b; font-size: 12px; line-height: 1.4; display: block;"><?php echo htmlspecialchars($row['task_description']); ?></small>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img src="<?php echo $row['assigned_by_img']; ?>" style="width:36px; height:36px; border-radius:50%; object-fit: cover; border: 1px solid #e2e8f0;">
                                    <div>
                                        <span style="font-weight:700; color: #1e293b; display: block; font-size: 13px;"><?php echo htmlspecialchars($row['assigned_by_name']); ?></span>
                                        <small style="color: #94a3b8; font-size: 11px; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($row['assigned_by_role']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #475569;">
                                    <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date('d M Y', strtotime($row['deadline'])); ?>
                                </div>
                            </td>
                            <td><span class="status-badge badge-priority prio-<?php echo $row['priority']; ?>"><?php echo $row['priority']; ?></span></td>
                            <td>
                                <?php 
                                    $badgeClass = 'badge-pending';
                                    if($row['status'] == 'In Progress') $badgeClass = 'badge-working';
                                    if($row['status'] == 'Completed') $badgeClass = 'badge-completed';
                                ?>
                                <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $row['status']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b; font-weight: 500;">No active tasks currently assigned to you.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="font-weight: 800; margin-top: 30px; margin-bottom: 10px; color: #0f172a; font-size: 20px;">Execution Board</div>
    
    <div class="kanban-board">
        
        <div class="kanban-column">
            <div class="column-header"><span>To Do</span><span class="task-count"><?php echo count($tasks_todo); ?></span></div>
            <?php foreach($tasks_todo as $task): ?>
            <div class="kanban-card">
                <span class="card-tag prio-<?php echo $task['priority']; ?>"><?php echo $task['priority']; ?> Priority</span>
                <div class="card-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($task['task_description']); ?></div>
                <div class="card-footer">
                    <div class="footer-top">
                        <span style="display: flex; align-items: center; gap: 6px;"><i class="fa-regular fa-clock text-orange-500"></i> <?php echo date('d M', strtotime($task['deadline'])); ?></span>
                        <a href="?update_id=<?php echo $task['id']; ?>&new_status=In Progress" class="action-btn">Start Task <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="kanban-column">
            <div class="column-header" style="color: #2563eb;"><span>In Progress</span><span class="task-count" style="background: #dbeafe; color: #1d4ed8;"><?php echo count($tasks_progress); ?></span></div>
            <?php foreach($tasks_progress as $task): ?>
            <div class="kanban-card" style="border-left: 3px solid #3b82f6;">
                <span class="card-tag prio-<?php echo $task['priority']; ?>"><?php echo $task['priority']; ?> Priority</span>
                <div class="card-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($task['task_description']); ?></div>
                
                <div class="card-footer">
                    <div class="footer-top">
                        <span style="display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-hourglass-half text-blue-500 animate-pulse"></i> Due: <?php echo date('d M', strtotime($task['deadline'])); ?></span>
                    </div>
                    
                    <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="complete_task_id" value="<?php echo $task['id']; ?>">
                        <input type="file" name="task_file" required class="file-input">
                        <button type="submit" class="submit-btn">
                            Complete <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="kanban-column">
            <div class="column-header" style="color: #059669;"><span>Completed</span><span class="task-count" style="background: #d1fae5; color: #047857;"><?php echo count($tasks_completed); ?></span></div>
            <?php foreach($tasks_completed as $task): ?>
            <div class="completed-item" style="border-left: 3px solid #10b981;">
                <div class="completed-header">
                    <i class="fa-solid fa-circle-check check-icon"></i>
                    <span class="completed-text"><?php echo htmlspecialchars($task['task_title']); ?></span>
                </div>
                
                <div class="completed-actions">
                    <?php if(!empty($task['completed_file'])): ?>
                        <span style="font-size: 11px; color: #0d9488; font-weight: 700; display: flex; align-items: center; gap: 6px; background: #f0fdfa; padding: 4px 8px; border-radius: 6px; border: 1px solid #ccfbf1;">
                            <i class="fa-solid fa-file-lines"></i> <?php echo htmlspecialchars(basename($task['completed_file'])); ?>
                        </span>
                    <?php else: ?>
                        <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No File Attached</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 14px; border-top: 1px dashed #e2e8f0; padding-top: 14px;">
                    <p style="font-size: 11px; color: #64748b; margin-bottom: 8px; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-rotate-right mr-1"></i> Re-Upload File</p>
                    <form action="" method="POST" enctype="multipart/form-data" class="upload-form" style="background: transparent; padding: 0; border: none;">
                        <input type="hidden" name="complete_task_id" value="<?php echo $task['id']; ?>">
                        <input type="file" name="task_file" required class="file-input" style="padding: 4px;">
                        <button type="submit" class="update-btn">
                            Update
                        </button>
                    </form>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        
    </div>
</main>
</body>
</html>