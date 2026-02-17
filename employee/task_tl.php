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
$current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : 1;

// 3. HANDLE FORM SUBMISSION (Set Personal Target)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_target'])) {
    $task = mysqli_real_escape_string($conn, $_POST['task']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $time = mysqli_real_escape_string($conn, $_POST['time']);

    if (!empty($task) && !empty($date) && !empty($time)) {
        $stmt = $conn->prepare("INSERT INTO personal_targets (user_id, task_name, target_date, target_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $current_user_id, $task, $date, $time);
        $stmt->execute();
        $stmt->close();
        // Redirect to prevent form resubmission on refresh
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// 4. FETCH TASK STATISTICS
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
    FROM team_tasks WHERE assigned_to = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// 5. FETCH TEAM ASSIGNMENTS
$tasks_query = "SELECT * FROM team_tasks WHERE assigned_to = ? ORDER BY deadline ASC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$assigned_tasks = $stmt->get_result();

// 6. FETCH PERSONAL TARGETS
$targets_query = "SELECT * FROM personal_targets WHERE user_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($targets_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$personal_targets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Board - Workack</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
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

        .main-shifted { 
            margin-left: calc(var(--sidebar-primary-width) + var(--sidebar-secondary-width)) !important;
            width: calc(100% - (var(--sidebar-primary-width) + var(--sidebar-secondary-width)));
        }

        .page-header { margin-bottom: 25px; }
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

        .target-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            align-items: end; 
            margin-bottom: 20px; 
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; }
        .form-control, .form-select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 0.9rem; }
        
        .btn-theme { 
            background-color: var(--theme-color); 
            color: white; 
            border: none; 
            padding: 12px 20px; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            gap: 8px; 
            height: 42px;
        }
        .btn-theme:hover { background-color: #144444; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .main-shifted { margin-left: 0 !important; width: 100%; }
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
            <div class="stat-info"><p>TOTAL TASKS</p><h3><?php echo sprintf("%02d", $stats['total']); ?></h3></div>
            <div class="stat-icon" style="background:#f1f5f9; color:#475569;"><i class="fa-solid fa-list-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #059669;">
            <div class="stat-info"><p>COMPLETED</p><h3><?php echo sprintf("%02d", $stats['completed']); ?></h3></div>
            <div class="stat-icon" style="color: #059669; background: #f0fdf4;"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #1d4ed8;">
            <div class="stat-info"><p>IN PROGRESS</p><h3><?php echo sprintf("%02d", $stats['in_progress']); ?></h3></div>
            <div class="stat-icon" style="color: #1d4ed8; background: #eff6ff;"><i class="fa-solid fa-spinner"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #ea580c;">
            <div class="stat-info"><p>PENDING</p><h3><?php echo sprintf("%02d", $stats['pending']); ?></h3></div>
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
                    <?php while($row = $assigned_tasks->fetch_assoc()): ?>
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
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 700; margin-bottom: 20px; color: var(--theme-color); font-size: 1.1rem;">Set Personal Execution Target</div>
        <form method="POST" action="">
            <div class="target-grid">
                <div class="form-group">
                    <label class="form-label">Select Task</label>
                    <select name="task" id="targetTask" class="form-control" required>
                        <option value="">Choose assigned task...</option>
                        <?php 
                        // Reset pointer and loop again for dropdown
                        $assigned_tasks->data_seek(0);
                        while($opt = $assigned_tasks->fetch_assoc()): 
                        ?>
                        <option value="<?php echo htmlspecialchars($opt['task_title']); ?>"><?php echo htmlspecialchars($opt['task_title']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Date</label>
                    <input type="date" name="date" id="targetDate" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Time</label>
                    <input type="time" name="time" id="targetTime" class="form-control" required>
                </div>
                <button type="submit" name="set_target" class="btn-theme">
                    <i class="fa-solid fa-bullseye"></i> Set Target
                </button>
            </div>
        </form>

        <div class="table-responsive" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Task Commitment</th>
                        <th>Target Deadline</th>
                        <th>Alert Status</th>
                    </tr>
                </thead>
                <tbody id="personalTargetBody">
                    <?php while($target = $personal_targets->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($target['task_name']); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($target['target_date'])); ?> at <?php echo $target['target_time']; ?></td>
                        <td><span class="status-badge badge-working">Watching Deadline</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>