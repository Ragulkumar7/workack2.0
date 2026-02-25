<?php 
// my_tasks.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

// Get logged in executive's name (Fallback to 'Sam Executive' for testing if session name isn't set)
$my_name = $_SESSION['name'] ?? 'Sam Executive';

// --- HANDLE AJAX STATUS UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $task_id = intval($_POST['task_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sql = "UPDATE sales_tasks SET status = '$new_status' WHERE id = $task_id AND assigned_to = '$my_name'";
    if(mysqli_query($conn, $sql)) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit;
}

// --- FETCH MY TASKS ---
$tasks_query = mysqli_query($conn, "SELECT * FROM sales_tasks WHERE assigned_to = '$my_name' ORDER BY due_date ASC");
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f1f5f9; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; }
        
        .page-header { margin-bottom: 25px; }
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-info h4 { margin: 0; font-size: 22px; color: var(--text-main); font-weight: 800; }
        .stat-info p { margin: 0; font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        .filter-tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px;}
        .tab { padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; background: white; border: 1px solid var(--border-color); color: var(--text-muted);}
        .tab.active { background: var(--theme-color); color: white; border-color: var(--theme-color); }

        .tasks-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .task-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; transition: 0.2s;}
        
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .task-title { font-size: 16px; font-weight: 700; color: var(--text-main); margin: 0; padding-right: 15px;}
        .task-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; flex-grow: 1;}
        
        .task-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-top: 15px; border-top: 1px dashed var(--border-color); margin-bottom: 15px;}
        .meta-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; font-weight: 600; }
        .meta-icon { color: var(--theme-color); font-size: 16px; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase;}
        .pri-High { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .pri-Medium { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .pri-Low { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        .task-actions { display: flex; gap: 10px; }
        .btn-update { flex: 1; padding: 10px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; text-align: center; border: 1px solid transparent; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 6px; }
        .btn-start { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
        .btn-complete { background: #dcfce7; color: #16a34a; border-color: #bbf7d0; }
        .btn-completed-disabled { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="page-header">
        <h2>My Assigned Tasks</h2>
        <p>Logged in as: <strong style="color:var(--theme-color);"><?= htmlspecialchars($my_name) ?></strong> | Manage and update your tasks.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #f1f5f9; color: #64748b;"><i class="ph-fill ph-clipboard-text"></i></div>
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

    <div class="filter-tabs" id="tabContainer">
        <div class="tab active" data-filter="All">All Tasks</div>
        <div class="tab" data-filter="Pending">Pending</div>
        <div class="tab" data-filter="In Progress">In Progress</div>
        <div class="tab" data-filter="Completed">Completed</div>
    </div>

    <div class="tasks-container" id="tasksContainer">
        <?php if($total_tasks > 0): foreach($all_tasks as $task): ?>
            
            <div class="task-card" data-status="<?= $task['status'] ?>" 
                 style="<?= $task['status'] == 'In Progress' ? 'border-color:#bae6fd; box-shadow:0 0 0 2px rgba(2, 132, 199, 0.1);' : ($task['status'] == 'Completed' ? 'opacity:0.7; background:#f8fafc;' : '') ?>">
                
                <div class="task-header">
                    <h4 class="task-title" style="<?= $task['status'] == 'Completed' ? 'text-decoration:line-through; color:#94a3b8;' : '' ?>">
                        <?= htmlspecialchars($task['title']) ?>
                    </h4>
                    <span class="badge pri-<?= $task['priority'] ?>"><?= $task['priority'] ?></span>
                </div>
                <p class="task-desc"><?= htmlspecialchars($task['description']) ?></p>
                
                <div class="task-meta">
                    <div class="meta-item" title="Assigned By"><i class="ph-bold ph-user-circle meta-icon"></i> <?= htmlspecialchars($task['assigned_by']) ?></div>
                    <div class="meta-item"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: <?= date('d M Y', strtotime($task['due_date'])) ?></div>
                    
                    <?php if($task['status'] == 'Pending'): ?>
                        <div class="meta-item" style="grid-column: span 2; color: #d97706;"><i class="ph-fill ph-clock"></i> Status: Pending</div>
                    <?php elseif($task['status'] == 'In Progress'): ?>
                        <div class="meta-item" style="grid-column: span 2; color: #0284c7;"><i class="ph-bold ph-spinner-gap ph-spin"></i> Status: In Progress</div>
                    <?php else: ?>
                        <div class="meta-item" style="grid-column: span 2; color: #16a34a;"><i class="ph-fill ph-check-circle"></i> Status: Completed</div>
                    <?php endif; ?>
                </div>

                <div class="task-actions">
                    <?php if($task['status'] == 'Pending'): ?>
                        <button class="btn-update btn-start" onclick="dbUpdateStatus(<?= $task['id'] ?>, 'In Progress')"><i class="ph-bold ph-play"></i> Start Progress</button>
                    <?php elseif($task['status'] == 'In Progress'): ?>
                        <button class="btn-update btn-complete" onclick="dbUpdateStatus(<?= $task['id'] ?>, 'Completed')"><i class="ph-bold ph-check"></i> Mark Completed</button>
                    <?php else: ?>
                        <button class="btn-update btn-completed-disabled" disabled><i class="ph-bold ph-checks"></i> Task Finished</button>
                    <?php endif; ?>
                </div>
            </div>

        <?php endforeach; else: ?>
            <p style="grid-column: 1/-1; text-align:center; color: var(--text-muted); padding: 40px;">Hooray! You have no tasks assigned at the moment.</p>
        <?php endif; ?>
    </div>
</main>

<script>
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

    // --- TAB FILTER LOGIC ---
    const tabs = document.querySelectorAll('.tab');
    const cards = document.getElementsByClassName('task-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const filter = this.getAttribute('data-filter');
            Array.from(cards).forEach(card => {
                card.style.display = (filter === 'All' || card.getAttribute('data-status') === filter) ? 'flex' : 'none';
            });
        });
    });
</script>

</body>
</html>