<?php
// productivity_monitor.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION (Smart Path Resolver)
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Cannot find database connection file.");
}

$user_id = $_SESSION['user_id'];

// Get user role accurately from DB
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

// 3. FETCH DATA FROM DATABASE
// Using personal_taskboard as the source of tasks, joining with employee_profiles for details
$base_query = "SELECT 
                pt.id,
                pt.title,
                pt.due_date as deadline,
                pt.status as raw_status,
                pt.description as proof,
                COALESCE(ep.full_name, u.name, 'Unknown Employee') as emp_name,
                COALESCE(ep.department, 'Unassigned') as dept,
                COALESCE(lead_ep.full_name, lead_u.name, 'Admin / Self') as lead_name
            FROM personal_taskboard pt
            JOIN users u ON pt.user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            LEFT JOIN users lead_u ON ep.reporting_to = lead_u.id
            LEFT JOIN employee_profiles lead_ep ON lead_u.id = lead_ep.user_id";

$where_clauses = [];
$params = [];
$types = "";

// Role-Based Filtering
if ($user_role === 'Manager') {
    $where_clauses[] = "(ep.manager_id = ? OR ep.reporting_to = ? OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = ?))";
    $params = [$user_id, $user_id, $user_id];
    $types = "iii";
} elseif ($user_role === 'Team Lead') {
    $where_clauses[] = "ep.reporting_to = ?";
    $params = [$user_id];
    $types = "i";
}

if (!empty($where_clauses)) {
    $base_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$base_query .= " ORDER BY pt.due_date DESC";

$stmt = $conn->prepare($base_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
$total = 0;
$completed = 0;
$progress = 0;
$overdue = 0;
$today = date('Y-m-d');
$departments = []; 

while ($row = $result->fetch_assoc()) {
    $total++;
    $status = 'Pending';
    
    // Smart Status Calculation
    if ($row['raw_status'] === 'completed') {
        $status = 'Completed';
        $completed++;
    } elseif ($row['deadline'] < $today && $row['deadline'] != '0000-00-00' && $row['raw_status'] !== 'completed') {
        $status = 'Overdue';
        $overdue++;
    } elseif ($row['raw_status'] === 'inprogress') {
        $status = 'In Progress';
        $progress++;
    } else {
        $status = 'Pending';
        $progress++; 
    }
    
    $row['display_status'] = $status;
    $row['proof'] = !empty($row['proof']) ? $row['proof'] : 'No proof/description provided for this task.';
    
    // Collect unique departments for the dynamic filter dropdown
    if (!in_array($row['dept'], $departments) && $row['dept'] !== 'Unassigned') {
        $departments[] = $row['dept'];
    }
    
    $tasks[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Productivity Monitor</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --bg-light: #f4f6f8;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-light: #e5e7eb;
            --sidebar-width: 95px;
        }

        body { 
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden;
        }

        /* --- SIDEBAR INTEGRATION --- */
        #mainContent { 
            margin-left: var(--sidebar-width); 
            padding: 30px; 
            width: calc(100% - var(--sidebar-width));
            transition: all 0.3s ease;
            min-height: 100vh;
            box-sizing: border-box;
        }

        /* --- HEADER & STATS --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .page-header h1 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 20px; border-radius: 12px; border: 1px solid var(--border-light); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin: 0; color: #111827; }
        .stat-card p { margin: 5px 0 0; font-size: 13px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        
        .border-orange { border-bottom: 4px solid var(--primary-orange); }
        .border-green { border-bottom: 4px solid #10b981; }
        .border-blue { border-bottom: 4px solid #3b82f6; }
        .border-red { border-bottom: 4px solid #ef4444; }

        /* --- FILTERS --- */
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid var(--border-light); }
        .filter-bar span { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 8px 12px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 13px; color: var(--text-dark); outline: none; min-width: 150px; cursor: pointer; flex-grow: 1; max-width: 250px;}
        .filter-select:focus { border-color: var(--primary-orange); }

        /* --- TABLE --- */
        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .card-title { padding: 20px 25px; border-bottom: 1px solid var(--border-light); font-weight: 700; font-size: 16px; color: #374151; background: #f9fafb; }
        
        .table-responsive { overflow-x: auto; width: 100%; }
        .task-table { width: 100%; border-collapse: collapse; min-width: 900px;}
        .task-table th { text-align: left; padding: 15px 25px; font-size: 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #f9fafb; font-weight: 600; text-transform: uppercase; }
        .task-table td { padding: 16px 25px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #4b5563; vertical-align: middle; }
        .task-table tr:last-child td { border-bottom: none; }
        .task-table tr:hover { background-color: #f8fafc; }

        /* --- BADGES & BUTTONS --- */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .completed { background: #dcfce7; color: #166534; }
        .delayed { background: #fee2e2; color: #991b1b; }
        .progress-badge { background: #dbeafe; color: #1e40af; }
        .pending-badge { background: #fef3c7; color: #b45309; }

        .action-btn { background: var(--white); border: 1px solid var(--border-light); color: var(--text-dark); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .action-btn:hover:not(.disabled) { border-color: var(--primary-orange); color: var(--primary-orange); background: #fff5f2; }
        .action-btn.disabled { opacity: 0.5; cursor: not-allowed; background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }

        /* --- MODAL --- */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;}
        .modal-content { background: white; width: 500px; max-width: 100%; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: modalIn 0.2s ease-out; }
        @keyframes modalIn { from {opacity: 0; transform: scale(0.95);} to {opacity: 1; transform: scale(1);} }
        
        .modal-header { padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; color: #111827; }
        
        .modal-body { padding: 25px; max-height: 60vh; overflow-y: auto;}
        .proof-box { background: #f8fafc; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 8px; font-size: 14px; color: #334155; margin-top: 10px; line-height: 1.6; white-space: pre-wrap;}
        
        .modal-footer { padding: 15px 20px; border-top: 1px solid #e5e7eb; text-align: right; }
        .close-btn { background: #f3f4f6; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; color: #374151; transition: 0.2s;}
        .close-btn:hover { background: #e5e7eb; }

        @media (max-width: 992px) {
            #mainContent { margin-left: 0; width: 100%; padding: 15px; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div id="mainContent">
        <?php include $headerPath; ?>
        
        <div class="page-header mt-4">
            <div>
                <h1>Productivity Monitor</h1>
                <p style="font-size:13px; color:#6b7280; margin-top:4px;">Track employee task completion and review work proofs.</p>
            </div>
            <div style="font-size:13px; font-weight:600; background:#fff; padding:8px 15px; border-radius:20px; border:1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <i class="fa-regular fa-calendar mr-1 text-gray-400"></i> Today: <?php echo date('d M, Y'); ?>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card border-blue">
                <h3><?php echo $total; ?></h3>
                <p>Total Tasks</p>
            </div>
            <div class="stat-card border-green">
                <h3><?php echo $completed; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card border-orange">
                <h3><?php echo $progress; ?></h3>
                <p>Pending / Progress</p>
            </div>
            <div class="stat-card border-red">
                <h3><?php echo $overdue; ?></h3>
                <p>Overdue</p>
            </div>
        </div>

        <div class="filter-bar">
            <span><i class="fas fa-filter text-orange-500"></i> Filter By:</span>
            <select class="filter-select" id="deptFilter" onchange="filterTable()">
                <option value="all">All Departments</option>
                <?php foreach($departments as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" id="statusFilter" onchange="filterTable()">
                <option value="all">All Statuses</option>
                <option value="Completed">Completed</option>
                <option value="In Progress">In Progress</option>
                <option value="Pending">Pending</option>
                <option value="Overdue">Overdue</option>
            </select>
        </div>

        <div class="content-card">
            <div class="card-title"><i class="fa-solid fa-list-check mr-2 text-gray-400"></i> Employee Task Tracker</div>
            <div class="table-responsive">
                <table class="task-table" id="trackerTable">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Task Title</th>
                            <th>Assigned By / Lead</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th style="text-align: right;">Review Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($tasks)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i data-lucide="folder-search" style="width: 40px; height: 40px; color: #cbd5e1; display: block; margin: 0 auto 10px auto;"></i>
                                    No tasks found based on your access level.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($tasks as $row): 
                                $statusClass = '';
                                if($row['display_status'] == 'Completed') $statusClass = 'completed';
                                elseif($row['display_status'] == 'Overdue') $statusClass = 'delayed';
                                elseif($row['display_status'] == 'In Progress') $statusClass = 'progress-badge';
                                else $statusClass = 'pending-badge';
                            ?>
                            <tr class="task-row" data-dept="<?php echo htmlspecialchars($row['dept']); ?>" data-status="<?php echo htmlspecialchars($row['display_status']); ?>">
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:30px; height:30px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#475569;">
                                            <?php echo strtoupper(substr($row['emp_name'], 0, 1)); ?>
                                        </div>
                                        <span style="font-weight:600; color:#1f2937;"><?php echo htmlspecialchars($row['emp_name']); ?></span>
                                    </div>
                                </td>
                                <td><span style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #475569;"><?php echo htmlspecialchars($row['dept']); ?></span></td>
                                <td style="font-weight:500; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($row['title']); ?>">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['lead_name']); ?></td>
                                <td>
                                    <?php 
                                        if($row['deadline'] == '0000-00-00') { echo "No Deadline"; } 
                                        else { echo date('d M, Y', strtotime($row['deadline'])); }
                                    ?>
                                </td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $row['display_status']; ?></span></td>
                                <td style="text-align: right;">
                                    <?php if($row['display_status'] == 'Completed'): ?>
                                        <button class="action-btn" onclick="viewProof('<?php echo htmlspecialchars(addslashes($row['emp_name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['proof'])); ?>')">
                                            <i class="far fa-eye"></i> View Proof
                                        </button>
                                    <?php else: ?>
                                        <button class="action-btn disabled" disabled>
                                            <i class="far fa-clock"></i> Pending
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="proofViewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="proofEmpName">Work Proof</h3>
                <i data-lucide="x" style="cursor:pointer; color:#6b7280;" onclick="closeModal('proofViewModal')"></i>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: #6b7280; font-weight: 600; margin:0;"><i class="fa-solid fa-paperclip mr-1"></i> Submitted Evidence / Description:</p>
                <div class="proof-box" id="proofText"></div>
            </div>
            <div class="modal-footer">
                <button class="close-btn" onclick="closeModal('proofViewModal')">Close Window</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Responsive Sidebar Layout Observer
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            
            if (!primarySidebar || !mainContent) return;

            const updateMargin = () => {
                if (window.innerWidth <= 992) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    return;
                }
                let totalWidth = primarySidebar.offsetWidth;
                if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                    totalWidth += secondarySidebar.offsetWidth;
                }
                mainContent.style.marginLeft = totalWidth + 'px';
                mainContent.style.width = `calc(100% - ${totalWidth}px)`;
            };

            new ResizeObserver(() => updateMargin()).observe(primarySidebar);
            if (secondarySidebar) {
                new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            window.addEventListener('resize', updateMargin);
            updateMargin();
        }
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);

        // 1. Modal Logic
        function closeModal(id) { 
            const modal = document.getElementById(id);
            modal.style.display = 'none'; 
            document.body.style.overflow = 'auto';
        }
        
        function viewProof(empName, proofContent) {
            document.getElementById('proofEmpName').innerText = "Proof: " + empName;
            document.getElementById('proofText').innerText = proofContent;
            
            const modal = document.getElementById('proofViewModal');
            modal.style.display = 'flex'; 
            document.body.style.overflow = 'hidden';
        }

        // Close modal on outside click
        window.onclick = function(event) { 
            const modal = document.getElementById('proofViewModal');
            if (event.target === modal) { 
                closeModal('proofViewModal'); 
            } 
        }

        // 2. Filter Logic
        function filterTable() {
            const dept = document.getElementById('deptFilter').value;
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.task-row');

            rows.forEach(row => {
                const rowDept = row.getAttribute('data-dept');
                const rowStatus = row.getAttribute('data-status');

                let show = true;

                if (dept !== 'all' && rowDept !== dept) show = false;
                if (status !== 'all' && rowStatus !== status) show = false;

                row.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>
</html>