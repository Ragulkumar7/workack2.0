<?php 
// my_tasks.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

// 1. STRICT USER IDENTIFICATION
if (!isset($_SESSION['user_id'])) {
    die("Error: You must be logged in to view tasks.");
}
$current_user_id = $_SESSION['user_id'];

// Fetch the exact name from the database to ensure 100% match with what the Manager sees
$profile_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.username, u.name) as exact_name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?");
$profile_stmt->bind_param("i", $current_user_id);
$profile_stmt->execute();
$profile_res = $profile_stmt->get_result()->fetch_assoc();
$my_name = $profile_res['exact_name'] ?? 'Unknown';
$profile_stmt->close();

// --- HANDLE AJAX STATUS UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $task_id = intval($_POST['task_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Security check: Only update if the task belongs to this ID or Name
    $sql = "UPDATE sales_tasks SET status = '$new_status' WHERE id = $task_id AND (assigned_to = '$my_name' OR assigned_to = '$current_user_id')";
    if(mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// --- FETCH MY TARGETS & PROGRESS ---
$current_month = date('Y-m');
// Look for targets using both ID and Name to prevent mismatch
$targets_query = mysqli_query($conn, "SELECT * FROM sales_targets WHERE (executive_name = '$my_name' OR executive_name = '$current_user_id') AND target_month = '$current_month'");
$my_targets = [];

// Calculate achieved revenue for progress bar
$achieved_query = mysqli_query($conn, "SELECT SUM(deal_value) as total_achieved FROM crm_clients WHERE status = 'Won' AND DATE_FORMAT(created_at, '%Y-%m') = '$current_month' AND (executive = '$my_name' OR executive = '$current_user_id')");
$achieved_row = mysqli_fetch_assoc($achieved_query);
$achieved_revenue = (float)($achieved_row['total_achieved'] ?? 0);

if ($targets_query) {
    while($row = mysqli_fetch_assoc($targets_query)) {
        $row['achieved_revenue'] = $achieved_revenue;
        $row['progress_percentage'] = ($row['revenue_target'] > 0) ? min(100, round(($achieved_revenue / $row['revenue_target']) * 100)) : 0;
        $my_targets[] = $row;
    }
}

// --- FETCH MY TASKS ---
// THE FIX: Search by BOTH Name and User ID to catch the assignment regardless of how the manager saved it.
$tasks_query = mysqli_query($conn, "SELECT * FROM sales_tasks WHERE assigned_to = '$my_name' OR assigned_to = '$current_user_id' ORDER BY due_date ASC");
$all_tasks = [];
$counts = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0];

if ($tasks_query) {
    while($row = mysqli_fetch_assoc($tasks_query)) {
        $all_tasks[] = $row;
        if(isset($counts[$row['status']])) $counts[$row['status']]++;
    }
}
$total_tasks = count($all_tasks);

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assigned Tasks | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --theme-color: #1b5a5a; 
            --bg-body: #f1f5f9; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e2e8f0; 
            --primary-sidebar-width: 95px; 
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
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
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }

        /* STATISTICS UI STYLES */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .stat-info h4 { margin: 0; font-size: 22px; color: var(--text-main); font-weight: 800; }
        .stat-info p { margin: 0; font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

        /* TAB BUTTONS STYLES */
        .tab-btn { padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; background: white; border: 1px solid var(--border-color); color: var(--text-muted); display: inline-block; text-align: center;}
        .tab-btn.active { background: var(--theme-color); color: white; border-color: var(--theme-color); }

        /* UPGRADED TARGET CARD UI */
        .target-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; align-items: flex-start; }
        .target-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); transition: transform 0.2s; width: 340px; flex-shrink: 0; }
        .target-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .tc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .tc-name { font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;}
        .tc-month { font-size: 11px; font-weight: 700; background: #f1f5f9; color: var(--text-muted); padding: 5px 12px; border-radius: 20px; }
        .tc-progress-section { display: flex; flex-direction: column; gap: 8px; }
        .tc-stats-row { display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: var(--text-muted); }
        .tc-progress-bar-container { width: 100%; height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden; position: relative; }
        .tc-progress-fill { height: 100%; border-radius: 10px; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); }
        .tc-footer-row { display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 700; margin-top: 4px;}

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
        .tasks-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px; }
        .task-card { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; display: flex; flex-direction: column; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.03), 0 2px 4px -2px rgb(0 0 0 / 0.03); position: relative; }
        .tasks-container:not(.list-view) .task-card:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.01); border-color: #cbd5e1; }
        
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 15px; }
        .task-title { font-size: 19px; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.3; letter-spacing: -0.3px;}
        .task-desc { font-size: 14px; color: var(--text-muted); margin: 0 0 25px 0; line-height: 1.6; flex-grow: 1; }
        
        .task-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding-top: 20px; border-top: 1px dashed #e2e8f0; margin-bottom: 25px; }
        .meta-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 600; }
        .meta-icon { color: var(--theme-color); font-size: 18px; }
        
        /* Grid Status Pills */
        .status-text { grid-column: span 2; padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; width: fit-content; gap: 8px;}
        .status-Pending { background: #fef9c3; color: #d97706; border: 1px solid #fde047; }
        .status-In-Progress { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
        .status-Completed { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        /* Priority Badges */
        .badge { padding: 5px 12px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; height: fit-content; }
        .pri-High { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .pri-Medium { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .pri-Low { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Grid Action Buttons */
        .task-actions { display: flex; gap: 12px; }
        .btn-update { flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; text-align: center; border: none; transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-start { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
        .btn-start:hover { background: #e0f2fe; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(2, 132, 199, 0.1); }
        .btn-complete { background: #16a34a; color: white; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2); }
        .btn-complete:hover { background: #15803d; transform: translateY(-1px); box-shadow: 0 6px 15px rgba(22, 163, 74, 0.3); }
        .btn-completed-disabled { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; cursor: not-allowed; }

        /* LIST VIEW OVERRIDES */
        .tasks-container.list-view { grid-template-columns: 1fr; }
        .tasks-container.list-view .task-card { flex-direction: row; align-items: center; justify-content: space-between; padding: 15px 25px; gap: 20px; border-radius: 12px; box-shadow: none; border: 1px solid var(--border-color); }
        .tasks-container.list-view .task-card:hover { transform: none; box-shadow: none; border-color: var(--border-color); }
        .tasks-container.list-view .task-header { margin-bottom: 0; min-width: 200px; flex-direction: column; align-items: flex-start; }
        .tasks-container.list-view .task-desc { margin-bottom: 0; padding: 0 20px; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); flex-grow: 1; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .tasks-container.list-view .task-meta { border-top: none; padding-top: 0; margin-bottom: 0; display: flex; gap: 20px; grid-template-columns: none; width: auto; flex-direction: row;}
        .tasks-container.list-view .task-actions { min-width: 150px; }
        .tasks-container.list-view .status-text { padding: 0; border: none; background: transparent; }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    
    <div class="page-header">
        <div>
            <h2>My Dashboard</h2>
            <p>Logged in as: <strong style="color:var(--theme-color);"><?= htmlspecialchars($my_name) ?></strong> | Manage your targets and tasks.</p>
        </div>
        <div style="display: flex; gap: 15px; align-items: center;">
            </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #f1f5f9; color: #475569;"><i class="ph-fill ph-clipboard-text"></i></div>
            <div class="stat-info"><h4><?= $total_tasks ?></h4><p>Total Tasks</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef9c3; color: #d97706;"><i class="ph-fill ph-clock-countdown"></i></div>
            <div class="stat-info"><h4><?= $counts['Pending'] ?></h4><p>Pending</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;"><i class="ph-fill ph-spinner-gap"></i></div>
            <div class="stat-info"><h4><?= $counts['In Progress'] ?></h4><p>In Progress</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;"><i class="ph-fill ph-check-circle"></i></div>
            <div class="stat-info"><h4><?= $counts['Completed'] ?></h4><p>Completed</p></div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px;">
        <div style="display: flex; gap: 10px;">
            <div class="tab-btn active" id="btnShowTasks" onclick="toggleMainView('tasks')">My Tasks</div>
            <div class="tab-btn" id="btnShowTargets" onclick="toggleMainView('targets')">My Targets</div>
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
                <?php if(empty($my_targets)): ?>
                    <div style="padding: 20px; background: white; border-radius: 12px; color: var(--text-muted); width: 100%; border: 1px dashed var(--border-color); text-align: center;">No targets set for you in <?php echo date('F Y'); ?>.</div>
                <?php else: foreach($my_targets as $tar): 
                    $prog = $tar['progress_percentage'];
                    $prog_color = '#ef4444'; // Red for low
                    if ($prog >= 40) $prog_color = '#f59e0b'; // Orange for medium
                    if ($prog >= 80) $prog_color = '#10b981'; // Green for good/hit
                ?>
                    <div class="target-card">
                        <div class="tc-header">
                            <h4 class="tc-name"><i class="ph-bold ph-target" style="color: var(--theme-color); font-size: 20px;"></i> Monthly Goal</h4>
                            <span class="tc-month"><i class="ph-bold ph-calendar"></i> <?= date('M Y', strtotime($tar['target_month']."-01")) ?></span>
                        </div>
                        
                        <div class="tc-progress-section">
                            <div class="tc-stats-row">
                                <span>Achieved: <span style="color: var(--theme-color);">₹<?= number_format($tar['achieved_revenue']) ?></span></span>
                                <span>Target: ₹<?= number_format($tar['revenue_target']) ?></span>
                            </div>
                            
                            <div class="tc-progress-bar-container">
                                <div class="tc-progress-fill" style="width: <?= $prog ?>%; background: <?= $prog_color ?>;"></div>
                            </div>
                            
                            <div class="tc-footer-row">
                                <span style="color: <?= $prog_color ?>;"><?= $prog ?>% Completed</span>
                                <?php if($prog >= 100): ?>
                                    <span style="color: #10b981; display:flex; align-items:center; gap:3px;"><i class="ph-fill ph-check-circle"></i> Target Hit!</span>
                                <?php else: ?>
                                    <span>Remaining: ₹<?= number_format(max(0, $tar['revenue_target'] - $tar['achieved_revenue'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="tasks-container" id="tasksContainer">
                <?php if($total_tasks > 0): foreach($all_tasks as $task): ?>
                    
                    <div class="task-card" data-status="<?= $task['status'] ?>" 
                         style="<?= $task['status'] == 'Completed' ? 'opacity:0.65; filter: grayscale(20%);' : '' ?>">
                        
                        <div class="task-header">
                            <h4 class="task-title" style="<?= $task['status'] == 'Completed' ? 'text-decoration:line-through; color:#94a3b8;' : '' ?>">
                                <?= htmlspecialchars($task['title']) ?>
                            </h4>
                            <span class="badge pri-<?= $task['priority'] ?>"><?= $task['priority'] ?></span>
                        </div>
                        <p class="task-desc"><?= htmlspecialchars($task['description']) ?></p>
                        
                        <div class="task-meta">
                            <div class="meta-item" title="Assigned By">
                                <div style="display:flex; align-items:center; gap:6px; color:var(--text-muted);"><i class="ph-bold ph-user-circle meta-icon"></i> <?= htmlspecialchars($task['assigned_by']) ?></div>
                            </div>
                            <div class="meta-item">
                                <div style="display:flex; align-items:center; gap:6px; color:var(--text-muted);"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: <?= date('d M Y', strtotime($task['due_date'])) ?></div>
                            </div>
                            <?php if($task['status'] == 'Pending'): ?>
                                <div class="status-text status-Pending"><i class="ph-bold ph-clock"></i> Status: Pending</div>
                            <?php elseif($task['status'] == 'In Progress'): ?>
                                <div class="status-text status-In-Progress"><i class="ph-bold ph-spinner-gap ph-spin"></i> Status: In Progress</div>
                            <?php else: ?>
                                <div class="status-text status-Completed"><i class="ph-bold ph-check-circle"></i> Status: Completed</div>
                            <?php endif; ?>
                        </div>

                        <div class="task-actions">
                            <?php if($task['status'] == 'Pending'): ?>
                                <button class="btn-update btn-start" onclick="dbUpdateStatus(<?= $task['id'] ?>, 'In Progress')"><i class="ph-bold ph-play"></i> Start Progress</button>
                            <?php elseif($task['status'] == 'In Progress'): ?>
                                <button class="btn-update btn-complete" onclick="dbUpdateStatus(<?= $task['id'] ?>, 'Completed')"><i class="ph-bold ph-check"></i> Mark Completed</button>
                            <?php else: ?>
                                <button class="btn-update btn-completed-disabled" disabled><i class="ph-bold ph-check-circle"></i> Task Finished</button>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; else: ?>
                    <p style="grid-column: 1/-1; text-align:center; color: var(--text-muted); padding: 60px; background: white; border-radius: 20px; border: 1px dashed var(--border-color); font-weight: 600;">Hooray! You have no tasks assigned at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    // --- DROPDOWN TOGGLE LOGIC ---
    const filterBtn = document.getElementById('filterBtn');
    const filterDropdown = document.getElementById('filterDropdown');

    filterBtn.onclick = function(e) {
        e.stopPropagation();
        filterDropdown.classList.toggle('show');
    };

    window.onclick = function() {
        filterDropdown.classList.remove('show');
    };

    // --- VIEW TOGGLE LOGIC ---
    document.addEventListener("DOMContentLoaded", () => {
        const savedView = localStorage.getItem('myTasksViewPreference') || 'grid';
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
            if(save) localStorage.setItem('myTasksViewPreference', 'list');
        } else {
            container.classList.remove('list-view');
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
            if(save) localStorage.setItem('myTasksViewPreference', 'grid');
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

    // --- FILTER LOGIC ---
    const cards = document.getElementsByClassName('task-card');
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    const currentFilterText = document.getElementById('currentFilterText');

    function applyFilter(filter, clickedElement) {
        // Update active class on dropdown items
        dropdownItems.forEach(item => item.classList.remove('active'));
        if (clickedElement) {
            clickedElement.classList.add('active');
        }

        // Update button text (optional, but good for UX)
        currentFilterText.innerText = filter === 'All' ? 'Filter' : filter;

        Array.from(cards).forEach(card => {
            const isMatching = (filter === 'All' || card.getAttribute('data-status') === filter);
            card.style.display = isMatching ? 'flex' : 'none';
        });
    }

    // --- DATABASE STATUS UPDATE LOGIC ---
    function dbUpdateStatus(taskId, newStatus) {
        Swal.fire({
            title: 'Update Task?',
            text: `Are you sure you want to move this task to '${newStatus}'?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1b5a5a',
            confirmButtonText: 'Yes, update it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('task_id', taskId);
                fd.append('status', newStatus);

                fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        Swal.fire({title: 'Updated!', text: `Task marked as ${newStatus}.`, icon: 'success', timer: 1500, showConfirmButton: false})
                        .then(() => location.reload());
                    } else {
                        alert("Error updating database.");
                    }
                });
            }
        });
    }
</script>

</body>
</html>