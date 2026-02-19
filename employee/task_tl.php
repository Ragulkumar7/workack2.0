<?php 
// 1. ROBUST DATABASE CONNECTION
$projectRoot = dirname(__DIR__); 
$dbPath = $projectRoot . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'db_connect.php';

if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    die("Error: db_connect.php not found at $dbPath");
}

// 2. INCLUDE SIDEBAR & HEADER
$sidebarPath = $projectRoot . DIRECTORY_SEPARATOR . 'sidebars.php'; 
$headerPath  = $projectRoot . DIRECTORY_SEPARATOR . 'header.php';

if (file_exists($sidebarPath)) include_once $sidebarPath;
if (file_exists($headerPath))  include_once $headerPath;

// Determine current user
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 1);

// --- NEW LOGIC: FETCH CURRENT EMPLOYEE'S NAME ---
// The TL stores assigned names as a comma-separated string, so we need the current user's name to search for it.
$name_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.name) as full_name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?");
$name_stmt->bind_param("i", $current_user_id);
$name_stmt->execute();
$name_res = $name_stmt->get_result()->fetch_assoc();
$current_user_name = $name_res['full_name'] ?? '';
$name_stmt->close();


// 3. FETCH TASK STATISTICS (Updated to query project_tasks)
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

// 4. FETCH TASKS (FOR BOTH LIST & KANBAN)
// Join with users and employee_profiles to dynamically get the Assigning Team Lead's name and role
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
    
    // Map project_tasks column names to the old variables your HTML uses so the UI doesn't break
    $task['task_description'] = $task['description'];
    $task['deadline'] = $task['due_date'];
    
    // Dynamically generate the avatar for the Assigning TL
    $assigner_url_name = urlencode($task['assigned_by_name']);
    $task['assigned_by_img'] = "https://ui-avatars.com/api/?name={$assigner_url_name}&background=random";

    $list_view_tasks[] = $task; 

    // Sort into Kanban Columns
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
        :root {
            --theme-color: #1b5a5a;
            --bg-body: #f8fafc;
            --border-color: #e2e8f0;
            --sidebar-primary-width: 95px;
            --sidebar-secondary-width: 220px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        
        body { 
            background-color: var(--bg-body); 
            display: block; 
            min-height: 100vh; 
            color: #334155; 
            overflow-x: hidden;
        }

        .main-content { 
            margin-left: var(--sidebar-primary-width); 
            padding: 30px; 
            transition: margin-left 0.3s ease; 
            width: calc(100% - var(--sidebar-primary-width)); 
        }

        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h2 { color: var(--theme-color); font-weight: 700; font-size: 24px; }
        .breadcrumb { font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }

        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
            width: 100%;
        }
        
        .stat-card { 
            background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-left: 4px solid var(--theme-color);
        }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .stat-info p { color: #64748b; font-size: 0.85rem; font-weight: 600; }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: #eefcfd; color: var(--theme-color); }

        .card { background: #fff; border-radius: 12px; padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0,0,0,0.02); width: 100%; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; font-size: 0.8rem; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; vertical-align: middle; }

        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-working { background: #eff6ff; color: #1d4ed8; }
        .badge-completed { background: #f0fdf4; color: #15803d; }
        .badge-priority { border: 1px solid #fee2e2; background: #fef2f2; color: #dc2626; padding: 2px 8px; }

        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 20px;
            align-items: start;
        }

        .kanban-column {
            background: #f8fafc; 
            border-radius: 12px;
            min-height: 400px;
        }

        .column-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            padding: 10px 5px;
        }

        .task-count {
            background: #e2e8f0;
            color: #475569;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .kanban-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .card-tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .tag-high { background: #fef2f2; color: #dc2626; }
        .tag-medium { background: #fff7ed; color: #ea580c; }
        .tag-low { background: #f0fdf4; color: #16a34a; }

        .card-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 6px; }
        .card-desc { font-size: 0.85rem; color: #64748b; margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            margin-top: 12px;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .completed-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .check-icon { color: #10b981; font-size: 1.1rem; }
        .completed-text { font-weight: 600; color: #1e293b; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .kanban-board { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div class="breadcrumb">Task Management > Task Board</div>
    <div class="page-header">
        <h2>Task Overview</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>TOTAL TASKS</p><h3><?php echo sprintf("%02d", $stats['total'] ?? 0); ?></h3></div>
            <div class="stat-icon" style="background:#f1f5f9; color:#475569;"><i class="fa-solid fa-list-check"></i></div>
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
                <?php 
                    $prioClass = 'tag-low';
                    if($task['priority'] == 'High' || $task['priority'] == 'Critical') $prioClass = 'tag-high';
                    if($task['priority'] == 'Medium') $prioClass = 'tag-medium';
                ?>
                <span class="card-tag <?php echo $prioClass; ?>"><?php echo $task['priority']; ?></span>
                <div class="card-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($task['task_description']); ?></div>
                <div class="card-footer"><span><?php echo date('d M', strtotime($task['deadline'])); ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="kanban-column">
            <div class="column-header" style="color: #1d4ed8;"><span>In Progress</span><span class="task-count"><?php echo count($tasks_progress); ?></span></div>
            <?php foreach($tasks_progress as $task): ?>
            <div class="kanban-card">
                <?php 
                    $prioClass = 'tag-low';
                    if($task['priority'] == 'High' || $task['priority'] == 'Critical') $prioClass = 'tag-high';
                    if($task['priority'] == 'Medium') $prioClass = 'tag-medium';
                ?>
                <span class="card-tag <?php echo $prioClass; ?>"><?php echo $task['priority']; ?></span>
                <div class="card-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($task['task_description']); ?></div>
                <div class="card-footer"><span><?php echo date('d M', strtotime($task['deadline'])); ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="kanban-column">
            <div class="column-header" style="color: #059669;"><span>Completed</span><span class="task-count"><?php echo count($tasks_completed); ?></span></div>
            <?php foreach($tasks_completed as $task): ?>
            <div class="completed-item">
                <i class="fa-solid fa-circle-check check-icon"></i>
                <span class="completed-text"><?php echo htmlspecialchars($task['task_title']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
</body>
</html>