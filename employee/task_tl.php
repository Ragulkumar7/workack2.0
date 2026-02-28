<?php 
// 1. ROBUST DATABASE CONNECTION
$projectRoot = dirname(__DIR__); 
$dbPath = $projectRoot . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'db_connect.php';

if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    die("Error: db_connect.php not found at $dbPath");
}

// Determine current user FIRST
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// --- SILENT DB UPDATE FOR FILE UPLOAD ---
$conn->query("ALTER TABLE project_tasks ADD COLUMN IF NOT EXISTS completed_file VARCHAR(255) NULL DEFAULT NULL AFTER status");

// --- HANDLE STATUS UPDATE VIA GET REQUEST (Only for Starting Task) ---
if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $tid = intval($_GET['update_id']);
    $stat = $_GET['new_status'];
    
    if ($stat === 'In Progress') {
        $stmt = $conn->prepare("UPDATE project_tasks SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $stat, $tid);
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
        $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR;
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Safe file name generation
        $fileExtension = strtolower(pathinfo($_FILES['task_file']['name'], PATHINFO_EXTENSION));
        $originalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['task_file']['name'], PATHINFO_FILENAME));
        $fileName = time() . '_' . $originalName . '.' . $fileExtension;
        $targetFilePath = $uploadDir . $fileName;
        
        // Upload new file
        if (move_uploaded_file($_FILES['task_file']['tmp_name'], $targetFilePath)) {
            $dbFilePath = 'uploads/tasks/' . $fileName;
            
            // --- DELETE OLD FILE LOGIC ---
            // Mundu patha file undemo check chesi, unte server nunchi delete cheyali
            $check_stmt = $conn->prepare("SELECT completed_file FROM project_tasks WHERE id = ?");
            $check_stmt->bind_param("i", $tid);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($old_row = $check_res->fetch_assoc()) {
                if (!empty($old_row['completed_file'])) {
                    $old_file_path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $old_row['completed_file']);
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path); // Patha file delete avtundi
                    }
                }
            }
            $check_stmt->close();
            // -----------------------------
            
            // Database update
            $stmt = $conn->prepare("UPDATE project_tasks SET status = 'Completed', completed_file = ? WHERE id = ?");
            $stmt->bind_param("si", $dbFilePath, $tid);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success'] = "File uploaded successfully!";
        } else {
            $_SESSION['error'] = "Failed to move the uploaded file. Check folder permissions.";
        }
    } else {
        $_SESSION['error'] = "File upload is required or an error occurred during upload.";
    }
    
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// 2. INCLUDE SIDEBAR & HEADER
$sidebarPath = $projectRoot . DIRECTORY_SEPARATOR . 'sidebars.php'; 
$headerPath  = $projectRoot . DIRECTORY_SEPARATOR . 'header.php';
$path_to_root = '../';

if (file_exists($sidebarPath)) include_once $sidebarPath;
if (file_exists($headerPath))  include_once $headerPath;

// --- FETCH CURRENT EMPLOYEE'S NAME ---
$name_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.name) as full_name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?");
$name_stmt->bind_param("i", $current_user_id);
$name_stmt->execute();
$name_res = $name_stmt->get_result()->fetch_assoc();
$current_user_name = $name_res['full_name'] ?? '';
$name_stmt->close();

// 3. FETCH TASK STATISTICS
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status IN ('Pending', 'To Do') THEN 1 ELSE 0 END) as pending
    FROM project_tasks WHERE FIND_IN_SET(?, assigned_to) > 0";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("s", $current_user_name);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4. FETCH TASKS
$tasks_query = "SELECT pt.*, 
                COALESCE(ep.full_name, u.name, 'Admin') as assigned_by_name,
                COALESCE(ep.designation, u.role, 'Team Lead') as assigned_by_role
                FROM project_tasks pt
                LEFT JOIN users u ON pt.created_by = u.id
                LEFT JOIN employee_profiles ep ON pt.created_by = ep.user_id
                WHERE FIND_IN_SET(?, pt.assigned_to) > 0 
                ORDER BY pt.due_date ASC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("s", $current_user_name);
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
    $task['assigned_by_img'] = "https://ui-avatars.com/api/?name={$assigner_url_name}&background=random";

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
    <title>Task Board - Workack</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f8fafc; --border-color: #e2e8f0; --sidebar-primary-width: 95px; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: var(--bg-body); color: #334155; overflow-x: hidden; }
        .main-content { margin-left: var(--sidebar-primary-width); padding: 30px; width: calc(100% - var(--sidebar-primary-width)); transition: all 0.3s ease; }
        @media (max-width: 991px) { .main-content { margin-left: 0; width: 100%; padding: 70px 15px 15px 15px; } }
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { color: var(--theme-color); font-weight: 700; font-size: 24px; }
        .breadcrumb { font-size: 0.8rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--theme-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .stat-info p { color: #64748b; font-size: 0.85rem; font-weight: 600; }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: #eefcfd; color: var(--theme-color); }
        
        .card { background: #fff; border-radius: 12px; padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 15px; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; font-size: 0.8rem; text-transform: uppercase; background: #f8fafc; }
        td { padding: 16px 24px; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; vertical-align: middle; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-working { background: #eff6ff; color: #1d4ed8; }
        .badge-completed { background: #f0fdf4; color: #15803d; }
        .badge-priority { border: 1px solid #fee2e2; background: #fef2f2; color: #dc2626; padding: 2px 8px; }
        
        .kanban-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 20px; align-items: start;}
        .kanban-column { background: #f8fafc; border-radius: 12px; min-height: 400px; padding: 12px; border: 1px solid var(--border-color); }
        .column-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; padding: 0 5px; }
        .task-count { background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; }
        
        .kanban-card { background: #fff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; margin-bottom: 16px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .card-tag { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; margin-bottom: 12px; text-transform: uppercase; }
        .tag-high { background: #fef2f2; color: #dc2626; }
        .tag-medium { background: #fff7ed; color: #ea580c; }
        .tag-low { background: #f0fdf4; color: #16a34a; }
        .card-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 6px; line-height: 1.3; }
        .card-desc { font-size: 0.85rem; color: #64748b; margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        .card-footer { display: flex; flex-direction: column; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 12px; margin-top: 12px; font-size: 0.8rem; color: #94a3b8; }
        .footer-top { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        
        .action-btn { background: transparent; border: none; color: var(--theme-color); font-weight: 700; font-size: 0.85rem; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; transition: 0.2s;}
        .action-btn:hover { color: #0f766e; }
        
        .upload-form { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .file-input { flex-grow: 1; min-width: 150px; font-size: 0.7rem; color: #64748b; background: white; padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; }
        .file-input::file-selector-button { background: #e2e8f0; border: none; padding: 4px 8px; border-radius: 4px; color: #475569; font-weight: 600; cursor: pointer; margin-right: 8px; transition: 0.2s;}
        .file-input::file-selector-button:hover { background: #cbd5e1; }
        .submit-btn { background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(16,185,129,0.2); transition: 0.2s;}
        .submit-btn:hover { background: #059669; }
        .update-btn { background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(59,130,246,0.2); transition: 0.2s;}
        .update-btn:hover { background: #2563eb; }
        
        .completed-item { display: flex; flex-direction: column; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .completed-header { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
        .check-icon { color: #10b981; font-size: 1.1rem; margin-top: 2px; }
        .completed-text { font-weight: 600; color: #1e293b; line-height: 1.3; }
        .completed-actions { display: flex; justify-content: space-between; align-items: center; width: 100%; border-top: 1px solid #f1f5f9; padding-top: 10px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 700px; max-width: 100%; border-radius: 12px; padding: 24px; max-height: 90vh; overflow-y: auto; }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div class="breadcrumb">Task Management > Task Board</div>
    <div class="page-header">
        <h2>Task Overview</h2>
    </div>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>TOTAL TASKS</p><h3><?php echo sprintf("%02d", $stats['total'] ?? 0); ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #059669;">
            <div class="stat-info"><p>COMPLETED</p><h3><?php echo sprintf("%02d", $stats['completed'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="color: #059669; background: #f0fdf4;"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #1d4ed8;">
            <div class="stat-info"><p>IN PROGRESS</p><h3><?php echo sprintf("%02d", $stats['in_progress'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="color: #1d4ed8; background: #eff6ff;"><i class="fa-solid fa-spinner"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #ea580c;">
            <div class="stat-info"><p>PENDING</p><h3><?php echo sprintf("%02d", $stats['pending'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="color: #ea580c; background: #fff7ed;"><i class="fa-solid fa-clock"></i></div>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 700; margin-bottom: 20px; color: var(--theme-color); font-size: 1.1rem;">Current Team Assignments</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Task Details</th>
                        <th>Assigned By</th>
                        <th>Deadline</th>
                        <th>Priority</th>
                        <th>Status</th>
                        </tr>
                </thead>
                <tbody>
                    <?php if (count($list_view_tasks) > 0): ?>
                        <?php foreach($list_view_tasks as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['task_title']); ?></strong><br>
                                <small style="color: #94a3b8;"><?php echo htmlspecialchars($row['task_description']); ?></small>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo $row['assigned_by_img']; ?>" style="width:35px; border-radius:50%;">
                                    <div><span style="font-weight:600;"><?php echo htmlspecialchars($row['assigned_by_name']); ?></span><br><small><?php echo htmlspecialchars($row['assigned_by_role']); ?></small></div>
                                </div>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['deadline'])); ?></td>
                            <td><span class="status-badge badge-priority"><?php echo $row['priority']; ?></span></td>
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
                        <tr><td colspan="5" style="text-align: center; padding: 25px; color: #64748b;">No tasks assigned to you right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="font-weight: 700; margin-bottom: 15px; color: var(--theme-color); font-size: 1.1rem;">Execution Targets</div>
    
    <div class="kanban-board">
        
        <div class="kanban-column">
            <div class="column-header"><span>To Do</span><span class="task-count"><?php echo count($tasks_todo); ?></span></div>
            <?php foreach($tasks_todo as $task): ?>
            <div class="kanban-card">
                <span class="card-tag <?php echo strtolower($task['priority']) == 'high' ? 'tag-high' : (strtolower($task['priority']) == 'medium' ? 'tag-medium' : 'tag-low'); ?>"><?php echo $task['priority']; ?></span>
                <div class="card-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($task['task_description']); ?></div>
                <div class="card-footer">
                    <div class="footer-top">
                        <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M', strtotime($task['deadline'])); ?></span>
                        <a href="?update_id=<?php echo $task['id']; ?>&new_status=In Progress" class="action-btn">Start <i class="fa-solid fa-arrow-right" style="margin-left: 5px;"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="kanban-column">
            <div class="column-header" style="color: #1d4ed8;"><span>In Progress</span><span class="task-count"><?php echo count($tasks_progress); ?></span></div>
            <?php foreach($tasks_progress as $task): ?>
            <div class="kanban-card">
                <span class="card-tag <?php echo strtolower($task['priority']) == 'high' ? 'tag-high' : (strtolower($task['priority']) == 'medium' ? 'tag-medium' : 'tag-low'); ?>"><?php echo $task['priority']; ?></span>
                <div class="card-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($task['task_description']); ?></div>
                
                <div class="card-footer">
                    <div class="footer-top">
                        <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M', strtotime($task['deadline'])); ?></span>
                    </div>
                    
                    <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="complete_task_id" value="<?php echo $task['id']; ?>">
                        <input type="file" name="task_file" required class="file-input">
                        <button type="submit" class="submit-btn">
                            Finish <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="kanban-column">
            <div class="column-header" style="color: #059669;"><span>Completed</span><span class="task-count"><?php echo count($tasks_completed); ?></span></div>
            <?php foreach($tasks_completed as $task): ?>
            <div class="completed-item">
                <div class="completed-header">
                    <i class="fa-solid fa-circle-check check-icon"></i>
                    <span class="completed-text"><?php echo htmlspecialchars($task['task_title']); ?></span>
                </div>
                
                <div class="completed-actions">
                    <?php if(!empty($task['completed_file'])): ?>
                        <span style="font-size: 11px; color: #0f766e; font-weight: 600;">
                            <i class="fa-solid fa-file-lines"></i> <?php echo htmlspecialchars(basename($task['completed_file'])); ?>
                        </span>
                    <?php else: ?>
                        <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No File Attached</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 12px; border-top: 1px dashed #e2e8f0; padding-top: 12px;">
                    <p style="font-size: 0.7rem; color: #64748b; margin-bottom: 6px; font-weight: 500;"><i class="fa-solid fa-rotate-right"></i> Re-Upload File (Optional)</p>
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