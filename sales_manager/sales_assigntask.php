<?php 
ob_start(); // Start output buffering immediately to catch rogue errors
// sales_assigntask.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

$manager_name = $_SESSION['name'] ?? 'Admin Manager';

// --- HANDLE AJAX FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Capture any hidden errors that might have printed before this point
    $stray_output = ob_get_clean(); 
    header('Content-Type: application/json');
    
    if (!isset($conn) || !$conn) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection lost.']);
        exit;
    }

    // Save New Task
    if ($_POST['action'] === 'save_task') {
        $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
        $assignee = mysqli_real_escape_string($conn, $_POST['assignee'] ?? '');
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date'] ?? '');
        $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'Medium');
        $desc = mysqli_real_escape_string($conn, $_POST['desc'] ?? '');

        // FIXED: Using 'title' instead of 'task_title'
        $sql = "INSERT INTO sales_tasks (assigned_by, assigned_to, title, description, due_date, priority, status) 
                VALUES ('$manager_name', '$assignee', '$title', '$desc', '$due_date', '$priority', 'Pending')";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . mysqli_error($conn) . ' | Stray Output: ' . $stray_output]);
        }
        exit;
    }

    // Save Monthly Target
    if ($_POST['action'] === 'save_target') {
        $exec = mysqli_real_escape_string($conn, $_POST['executive'] ?? '');
        $month = mysqli_real_escape_string($conn, $_POST['month'] ?? '');
        $customers = intval($_POST['customers'] ?? 0);
        $revenue = floatval($_POST['revenue'] ?? 0);

        $sql = "INSERT INTO sales_targets (executive_name, target_month, target_customers, revenue_target) 
                VALUES ('$exec', '$month', $customers, $revenue)";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Delete Task
    if ($_POST['action'] === 'delete_task') {
        $id = intval($_POST['task_id']);
        if(mysqli_query($conn, "DELETE FROM sales_tasks WHERE id=$id")) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }
}

// Clean buffer for standard HTML rendering
if(ob_get_length()) ob_clean();

// --- FETCH DATA FOR UI ---
$executives = mysqli_query($conn, "SELECT name, employee_id FROM users WHERE role IN ('Sales Executive', 'Sales') ORDER BY name ASC");

// Fetch targets for current month
$current_month = date('Y-m');
$targets_query = mysqli_query($conn, "SELECT * FROM sales_targets WHERE target_month = '$current_month'");
$targets_data = [];
if($targets_query) { while($r = mysqli_fetch_assoc($targets_query)) { $targets_data[] = $r; } }

// Fetch All Tasks and Calculate Summary
$tasks_query = mysqli_query($conn, "SELECT * FROM sales_tasks ORDER BY created_at DESC");
$counts = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0];
$total_assigned = 0;
$all_tasks_list = [];

if ($tasks_query) {
    while($row = mysqli_fetch_assoc($tasks_query)) {
        $all_tasks_list[] = $row;
        $total_assigned++;
        if(isset($counts[$row['status']])) $counts[$row['status']]++;
    }
}

// --- LEADERBOARD CALCULATION ---
$leaderboard = [];
// Initialize all users with 0 to ensure everyone shows up
if($executives){ 
    mysqli_data_seek($executives, 0); 
    while($ex = mysqli_fetch_assoc($executives)) {
        $leaderboard[$ex['name']] = 0;
    }
}
// Count completed tasks
foreach($all_tasks_list as $task) {
    if ($task['status'] === 'Completed') {
        $assignee = $task['assigned_to'];
        if (isset($leaderboard[$assignee])) {
            $leaderboard[$assignee]++;
        } else {
            $leaderboard[$assignee] = 1;
        }
    }
}
arsort($leaderboard); // Sort highest to lowest

// Reset executives pointer so the modal dropdown works
if($executives){ mysqli_data_seek($executives, 0); }

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Sales Tasks & Targets | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f1f5f9; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; box-sizing: border-box; }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }
        .header-actions { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        
        .btn-primary { background: var(--theme-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }
        
        .btn-secondary { background: white; color: var(--theme-color); border: 2px solid var(--theme-color); padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-secondary:hover { background: #f8fafc; transform: translateY(-2px); }

        /* STATISTICS UI STYLES */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .stat-info h4 { margin: 0; font-size: 22px; color: var(--text-main); font-weight: 800; }
        .stat-info p { margin: 0; font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

        /* TAB BUTTONS STYLES */
        .tab-btn { padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; background: white; border: 1px solid var(--border-color); color: var(--text-muted); display: inline-block; text-align: center;}
        .tab-btn.active { background: var(--theme-color); color: white; border-color: var(--theme-color); }

        .target-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; align-items: flex-start; }
        .target-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); transition: transform 0.2s; width: 300px; flex-shrink: 0; }
        .target-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .tc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .tc-name { font-size: 15px; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 6px;}
        .tc-month { font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 20px; }
        .tc-stats { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
        .tc-stats strong { color: var(--text-main); font-size: 14px; }

        /* CONTROLS (FILTER + VIEW TOGGLE) STYLES */
        .filter-dropdown { position: relative; display: inline-block; }
        .filter-btn { background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--text-main); cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); height: 38px; box-sizing: border-box; }
        .filter-btn:hover { background: #f8fafc; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 110%; background-color: white; min-width: 160px; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); border-radius: 8px; z-index: 100; border: 1px solid var(--border-color); padding: 5px 0; }
        .dropdown-content.show { display: block; animation: fadeIn 0.15s ease-out;}
        .dropdown-item { padding: 10px 16px; font-size: 13px; color: var(--text-main); font-weight: 500; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: space-between; }
        .dropdown-item:hover { background-color: #f1f5f9; color: var(--theme-color); }
        .dropdown-item.active { background-color: #e0f2fe; color: #0284c7; font-weight: 700; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .view-toggle { display: flex; background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 3px; gap: 4px; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); height: 38px; box-sizing: border-box; }
        .view-btn { padding: 6px 14px; border-radius: 6px; cursor: pointer; border: none; background: transparent; color: var(--text-muted); display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .view-btn.active { background: #f1f5f9; color: var(--theme-color); }
        .view-btn:hover:not(.active) { color: var(--text-main); }

        /* GRID VIEW (DEFAULT) STYLES */
        .tasks-container { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
        .task-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; position: relative; display: flex; flex-direction: column; width: 320px; flex-shrink: 0; box-sizing: border-box; }
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .task-title { font-size: 16px; font-weight: 700; color: var(--text-main); margin: 0; padding-right: 15px; line-height: 1.3;}
        .task-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; flex-grow: 1;}
        .task-meta { display: flex; flex-direction: column; gap: 10px; padding-top: 15px; border-top: 1px dashed var(--border-color); }
        .meta-item { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #475569; font-weight: 600; }
        .btn-delete { position: absolute; top: 15px; right: 15px; background: #fee2e2; border: none; color: #ef4444; cursor: pointer; font-size: 16px; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; }

        /* LIST VIEW OVERRIDES */
        .tasks-container.list-view { flex-direction: column; flex-wrap: nowrap; gap: 20px; }
        .tasks-container.list-view .task-card { width: 100%; flex-direction: row; align-items: center; justify-content: space-between; gap: 20px; padding: 15px 25px; overflow: hidden; }
        .tasks-container.list-view .task-header { order: 1; margin-bottom: 0; width: 200px; flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
        .tasks-container.list-view .task-desc { order: 2; margin-bottom: 0; flex: 1; padding: 0 20px; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tasks-container.list-view .task-meta { order: 3; border-top: none; padding-top: 0; flex-direction: row; gap: 20px; align-items: center; justify-content: flex-end; flex-shrink: 0; white-space: nowrap; }
        .tasks-container.list-view .meta-item { display: flex; align-items: center; justify-content: center; gap: 10px; flex-shrink: 0; }
        .tasks-container.list-view .btn-delete { position: static; order: 4; margin-left: 10px; flex-shrink: 0; }
        .tasks-container.list-view .task-title { padding-right: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

        /* BADGES & STATUSES */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; white-space: nowrap;}
        .pri-High { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .pri-Medium { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .pri-Low { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .stat-Pending { background: #fef9c3; color: #d97706; border: 1px solid #fde047;}
        .stat-In { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;}
        .stat-Completed { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0;}

        /* LEADERBOARD SIDEBAR */
        .leaderboard-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        .leaderboard-header { font-size: 16px; font-weight: 700; color: var(--text-main); margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;}
        .leaderboard-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed var(--border-color); }
        .leaderboard-item:last-child { border-bottom: none; padding-bottom: 0;}
        .lb-user { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: var(--text-main); }
        .lb-score { background: #e0f2fe; color: #0284c7; font-weight: 800; font-size: 12px; padding: 4px 10px; border-radius: 20px; }
        .lb-rank { font-size: 14px; font-weight: 800; width: 22px; text-align: center;}

        /* MODAL */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px;}
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 450px; position: relative; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; color: #94a3b8; cursor: pointer; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 13px; box-sizing: border-box; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>Sales Dashboard & Tasks</h2>
            <p>Monitor executive targets and assign daily tasks.</p>
        </div>
        <div class="header-actions">
            <button class="btn-secondary" onclick="openModal('targetModal')"><i class="ph-bold ph-target"></i> Set Target</button>
            <button class="btn-primary" onclick="openModal('taskModal')"><i class="ph-bold ph-plus-circle"></i> Create Task</button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #f1f5f9; color: #64748b;"><i class="ph-fill ph-clipboard-text"></i></div>
            <div class="stat-info"><h4><?= $total_assigned ?></h4><p>Total Assigned</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef9c3; color: #d97706;"><i class="ph-fill ph-clock-countdown"></i></div>
            <div class="stat-info"><h4><?= $counts['Pending'] ?></h4><p>Pending Action</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;"><i class="ph-fill ph-spinner-gap"></i></div>
            <div class="stat-info"><h4><?= $counts['In Progress'] ?></h4><p>Currently Active</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;"><i class="ph-fill ph-check-circle"></i></div>
            <div class="stat-info"><h4><?= $counts['Completed'] ?></h4><p>Successfully Finished</p></div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px;">
        <div style="display: flex; gap: 10px;">
            <div class="tab-btn active" id="btnShowTasks" onclick="toggleMainView('tasks')">Tasks</div>
            <div class="tab-btn" id="btnShowTargets" onclick="toggleMainView('targets')">Targets</div>
        </div>
        
        <div style="display: flex; gap: 15px; align-items: center;" id="tasksControlsWrapper">
            <div class="filter-dropdown">
                <button class="filter-btn" id="filterBtn">
                    <i class="ph ph-funnel" style="font-size: 16px;"></i> <span id="currentFilterText">Filter</span>
                </button>
                <div class="dropdown-content" id="filterDropdown">
                    <div class="dropdown-item active" onclick="applyFilter('All', this)">All Tasks</div>
                    <div class="dropdown-item" onclick="applyFilter('Pending', this)">Pending</div>
                    <div class="dropdown-item" onclick="applyFilter('In Progress', this)">In Progress</div>
                    <div class="dropdown-item" onclick="applyFilter('Completed', this)">Completed</div>
                </div>
            </div>
            
            <div class="view-toggle">
                <button class="view-btn active" id="gridViewBtn" onclick="toggleView('grid')"><i class="ph-bold ph-squares-four" style="font-size: 16px;"></i> Grid</button>
                <button class="view-btn" id="listViewBtn" onclick="toggleView('list')"><i class="ph-bold ph-list-dashes" style="font-size: 16px;"></i> List</button>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap;">
        
        <div style="flex: 1; min-width: 300px;">
            <div class="target-grid" id="targetsContainer" style="display: none;">
                <?php if(empty($targets_data)): ?>
                    <div style="padding: 20px; background: white; border-radius: 12px; color: var(--text-muted); width: 100%;">No targets set for <?php echo date('F Y'); ?>. Click 'Set Target' to begin.</div>
                <?php else: foreach($targets_data as $tar): ?>
                    <div class="target-card">
                        <div class="tc-header">
                            <h4 class="tc-name"><i class="ph-bold ph-user-circle" style="color: var(--theme-color); font-size: 20px;"></i> <?= htmlspecialchars($tar['executive_name']) ?></h4>
                            <span class="tc-month"><?= date('M Y', strtotime($tar['target_month']."-01")) ?></span>
                        </div>
                        <div class="tc-stats">
                            <span>Target: <strong>₹<?= number_format($tar['revenue_target']) ?></strong></span>
                            <span>Clients: <strong><?= $tar['target_customers'] ?></strong></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="tasks-container" id="tasksContainer">
                <?php if(!empty($all_tasks_list)): foreach($all_tasks_list as $task): ?>
                <div class="task-card" data-status="<?= $task['status'] ?>">
                    <button class="btn-delete" title="Delete Task" onclick="deleteTask(<?= $task['id'] ?>)"><i class="ph-bold ph-trash"></i></button>
                    <div class="task-header"><h4 class="task-title" <?php if($task['status']=='Completed') echo 'style="text-decoration: line-through; color: #94a3b8;"'; ?>><?= htmlspecialchars($task['title']) ?></h4></div>
                    <p class="task-desc"><?= htmlspecialchars($task['description']) ?></p>
                    <div class="task-meta">
                        <div class="meta-item">
                            <div style="display:flex; align-items:center; gap:6px; color:var(--text-muted);"><i class="ph-bold ph-user-circle meta-icon"></i> <?= htmlspecialchars($task['assigned_to']) ?></div>
                            <?php if($task['status'] == 'Pending') $s_class = 'stat-Pending'; elseif($task['status'] == 'In Progress') $s_class = 'stat-In'; else $s_class = 'stat-Completed'; ?>
                            <span class="badge <?= $s_class ?>"><?= $task['status'] ?></span>
                        </div>
                        <div class="meta-item">
                            <div style="display:flex; align-items:center; gap:6px; color:var(--text-muted);"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: <?= date('d M Y', strtotime($task['due_date'])) ?></div>
                            <span class="badge pri-<?= $task['priority'] ?>"><?= $task['priority'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                    <p style="color: var(--text-muted); padding: 20px; width: 100%;">No tasks assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="leaderboard-card" style="width: 320px; flex-shrink: 0; box-sizing: border-box;">
            <div class="leaderboard-header">
                Top Performers
            </div>
            <?php 
            $rank = 1;
            foreach($leaderboard as $name => $score): 
                $icon = $rank;
                
                $color = 'var(--text-muted)';
                if ($rank == 1) $color = '#eab308';
                if ($rank == 2) $color = '#94a3b8';
                if ($rank == 3) $color = '#b45309';
            ?>
                <div class="leaderboard-item">
                    <div class="lb-user">
                        <span class="lb-rank" style="color: <?= $color ?>;"><?= $icon ?></span>
                        <?= htmlspecialchars($name) ?>
                    </div>
                    <div class="lb-score"><?= $score ?> Done</div>
                </div>
            <?php $rank++; endforeach; ?>
            <?php if(empty($leaderboard)): ?>
                <p style="color: var(--text-muted); font-size: 13px; text-align: center; margin-top: 10px;">No executive records found.</p>
            <?php endif; ?>
        </div>

    </div>
</main>

<div class="modal-overlay" id="taskModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="closeModal('taskModal')"></i>
        <h3 style="margin-top: 0; color: var(--theme-color); font-size: 18px; margin-bottom: 20px;">Assign New Task</h3>
        <form id="createTaskForm" onsubmit="event.preventDefault(); submitTask();">
            <input type="hidden" name="action" value="save_task">
            <div class="form-group"><label>Task Title *</label><input type="text" name="title" required></div>
            <div class="form-group">
                <label>Assign To (Executive) *</label>
                <select name="assignee" required>
                    <option value="">-- Select Executive --</option>
                    <?php if($executives){ mysqli_data_seek($executives, 0); while($ex = mysqli_fetch_assoc($executives)) echo "<option value='".htmlspecialchars($ex['name'])."'>".htmlspecialchars($ex['name'])."</option>"; } ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group"><label>Due Date *</label><input type="date" name="due_date" required></div>
                <div class="form-group"><label>Priority</label><select name="priority" required><option value="High">High</option><option value="Medium" selected>Medium</option><option value="Low">Low</option></select></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="desc" rows="3"></textarea></div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px;" id="btnSubmitTask">Create & Assign</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="targetModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="closeModal('targetModal')"></i>
        <h3 style="margin-top: 0; color: var(--theme-color); font-size: 18px; margin-bottom: 20px;">Set Monthly Target</h3>
        <form id="setTargetForm" onsubmit="event.preventDefault(); submitTarget();">
            <input type="hidden" name="action" value="save_target">
            <div class="form-group">
                <label>Select Executive *</label>
                <select name="executive" required>
                    <option value="">-- Choose Executive --</option>
                    <?php if($executives){ mysqli_data_seek($executives, 0); while($ex = mysqli_fetch_assoc($executives)) echo "<option value='".htmlspecialchars($ex['name'])."'>".htmlspecialchars($ex['name'])."</option>"; } ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group"><label>Target Month *</label><input type="month" name="month" required value="<?= date('Y-m') ?>"></div>
                <div class="form-group"><label>Target Customers</label><input type="number" name="customers" required></div>
            </div>
            <div class="form-group"><label>Revenue Target (₹) *</label><input type="number" name="revenue" required></div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px;" id="btnSubmitTarget">Save Target</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function submitTask() {
        const btn = document.getElementById('btnSubmitTask');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Saving...'; 
        btn.disabled = true;

        fetch(window.location.href, { method: 'POST', body: new FormData(document.getElementById('createTaskForm')) })
        .then(async r => {
            const text = await r.text();
            try { 
                return JSON.parse(text); 
            } catch(e) { 
                throw new Error(text); 
            } 
        })
        .then(data => { 
            if(data.status === 'success') {
                location.reload(); 
            } else { 
                Swal.fire('Database Error', data.message, 'error'); 
                btn.innerHTML = origText; 
                btn.disabled = false; 
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'System Error',
                html: '<div style="font-size:12px; color:red; text-align:left; background:#f8f9fa; padding:10px; border-radius:5px; max-height:200px; overflow-y:auto;">' + err.message + '</div>'
            });
            btn.innerHTML = origText; 
            btn.disabled = false;
        });
    }

    function submitTarget() {
        const btn = document.getElementById('btnSubmitTarget');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Saving...'; 
        btn.disabled = true;

        fetch(window.location.href, { method: 'POST', body: new FormData(document.getElementById('setTargetForm')) })
        .then(async r => {
            const text = await r.text();
            try { 
                return JSON.parse(text); 
            } catch(e) { 
                throw new Error(text); 
            }
        })
        .then(data => { 
            if(data.status === 'success') {
                location.reload(); 
            } else { 
                Swal.fire('Database Error', data.message, 'error'); 
                btn.innerHTML = origText; 
                btn.disabled = false; 
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'System Error',
                html: '<div style="font-size:12px; color:red; text-align:left; background:#f8f9fa; padding:10px; border-radius:5px; max-height:200px; overflow-y:auto;">' + err.message + '</div>'
            });
            btn.innerHTML = origText; 
            btn.disabled = false;
        });
    }

    function deleteTask(id) {
        Swal.fire({
            title: 'Delete this task?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData(); fd.append('action', 'delete_task'); fd.append('task_id', id);
                fetch(window.location.href, { method: 'POST', body: fd })
                .then(async r => {
                    const text = await r.text();
                    try { return JSON.parse(text); } catch(e) { throw new Error(text); }
                })
                .then(data => { 
                    if(data.status === 'success') location.reload(); 
                    else Swal.fire('Error', data.message, 'error');
                }).catch(err => {
                    Swal.fire({
                        icon: 'error',
                        title: 'System Error',
                        html: '<div style="font-size:12px; color:red; text-align:left; background:#f8f9fa; padding:10px; border-radius:5px; max-height:200px; overflow-y:auto;">' + err.message + '</div>'
                    });
                });
            }
        });
    }

    // --- GRID / LIST VIEW TOGGLE LOGIC (WITH LOCAL STORAGE) ---
    document.addEventListener("DOMContentLoaded", () => {
        const savedView = localStorage.getItem('tasksViewPreference') || 'grid';
        toggleView(savedView, false);
    });

    function toggleView(view, save = true) {
        const container = document.getElementById('tasksContainer');
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');

        if (!container || !gridBtn || !listBtn) return;

        if (view === 'list') {
            container.classList.add('list-view');
            listBtn.classList.add('active');
            gridBtn.classList.remove('active');
            if (save) localStorage.setItem('tasksViewPreference', 'list');
        } else {
            container.classList.remove('list-view');
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
            if (save) localStorage.setItem('tasksViewPreference', 'grid');
        }
    }

    // --- VIEW MAIN TAB TOGGLE LOGIC ---
    function toggleMainView(view) {
        const targetsContainer = document.getElementById('targetsContainer');
        const tasksContainer = document.getElementById('tasksContainer');
        const controlsWrapper = document.getElementById('tasksControlsWrapper');
        const btnTasks = document.getElementById('btnShowTasks');
        const btnTargets = document.getElementById('btnShowTargets');

        if (view === 'targets') {
            targetsContainer.style.display = 'flex';
            tasksContainer.style.display = 'none';
            if(controlsWrapper) controlsWrapper.style.visibility = 'hidden';
            btnTargets.classList.add('active');
            btnTasks.classList.remove('active');
        } else {
            targetsContainer.style.display = 'none';
            tasksContainer.style.display = 'flex';
            if(controlsWrapper) controlsWrapper.style.visibility = 'visible';
            btnTasks.classList.add('active');
            btnTargets.classList.remove('active');
        }
    }

    // --- DROPDOWN TOGGLE LOGIC ---
    const filterBtn = document.getElementById('filterBtn');
    const filterDropdown = document.getElementById('filterDropdown');

    if(filterBtn) {
        filterBtn.onclick = function(e) {
            e.stopPropagation();
            filterDropdown.classList.toggle('show');
        };
    }

    window.onclick = function() {
        if(filterDropdown) filterDropdown.classList.remove('show');
    };

    // --- FILTER LOGIC ---
    const cards = document.querySelectorAll('.task-card');
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    const currentFilterText = document.getElementById('currentFilterText');

    function applyFilter(filter, clickedElement) {
        // Update active class on dropdown items
        dropdownItems.forEach(item => item.classList.remove('active'));
        if (clickedElement) {
            clickedElement.classList.add('active');
        }

        // Update button text
        currentFilterText.innerText = filter === 'All' ? 'Filter' : filter;

        // Apply filter to cards
        cards.forEach(card => {
            const isMatching = (filter === 'All' || card.getAttribute('data-status') === filter);
            card.style.display = isMatching ? 'flex' : 'none';
        });
    }
</script>

</body>
</html>