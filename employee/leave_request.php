<?php
// --- 1. SESSION & DATABASE CONNECTION ---
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database Connection
$dbPath = __DIR__ . '/include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once '../include/db_connect.php'; }

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'Employee'; // Fetch role for strict role-bypassing
session_write_close(); // Prevent Session Locking for performance

$message = "";

// --- 2. HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $total_days = intval($_POST['total_days']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    // Ensure only valid types are submitted
    if (($leave_type === 'Medical' || $leave_type === 'Casual') && $start_date && $end_date && $total_days > 0) {
        
        $tl_id = 0;
        $manager_id = 0;
        
        // Find who this employee reports to
        $get_managers_sql = "SELECT reporting_to, manager_id FROM employee_profiles WHERE user_id = ?";
        $stmt_managers = mysqli_prepare($conn, $get_managers_sql);
        if ($stmt_managers) {
            mysqli_stmt_bind_param($stmt_managers, "i", $user_id);
            mysqli_stmt_execute($stmt_managers);
            $manager_res = mysqli_stmt_get_result($stmt_managers);
            if ($m_row = mysqli_fetch_assoc($manager_res)) {
                $tl_id = !empty($m_row['reporting_to']) ? intval($m_row['reporting_to']) : 0;
                $manager_id = !empty($m_row['manager_id']) ? intval($m_row['manager_id']) : 0;
            }
            mysqli_stmt_close($stmt_managers);
        }

        // =========================================================
        // SMART HIERARCHY BYPASS LOGIC (ROLE-BASED)
        // =========================================================
        $tl_status = ($tl_id > 0) ? 'Pending' : 'Approved';
        $manager_status = ($manager_id > 0) ? 'Pending' : 'Approved';
        $hr_status = 'Pending';
        $final_status = 'Pending';

        // 1. Team Leads bypass the TL approval phase
        if (in_array($user_role, ['Team Lead', 'TL'])) {
            $tl_status = 'Approved';
        }
        
        // 2. Managers and HR Executives bypass both TL and Manager approval phases
        if (in_array($user_role, ['Manager', 'Project Manager', 'General Manager', 'HR', 'HR Executive'])) {
            $tl_status = 'Approved';
            $manager_status = 'Approved';
        }

        // 3. Top-Level Management Auto-Approval (Instant Approval)
        if (in_array($user_role, ['Admin', 'System Admin', 'CFO', 'CEO'])) {
            $tl_status = 'Approved';
            $manager_status = 'Approved';
            $hr_status = 'Approved';
            $final_status = 'Approved';
        }

        $sql = "INSERT INTO leave_requests (user_id, tl_id, manager_id, leave_type, start_date, end_date, total_days, reason, status, tl_status, manager_status, hr_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiisssisssss", $user_id, $tl_id, $manager_id, $leave_type, $start_date, $end_date, $total_days, $reason, $final_status, $tl_status, $manager_status, $hr_status);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
            exit();
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-semibold'>Error submitting request. Please try again.</div>";
        }
    } else {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-semibold'>Please fill all fields correctly.</div>";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = "<div class='bg-emerald-50 border border-emerald-200 text-emerald-700 p-3 rounded-lg mb-4 text-sm font-bold flex items-center gap-2'><i data-lucide='check-circle' style='width:18px;'></i> Leave request submitted successfully!</div>";
}

// --- 3. FETCH STRICT DYNAMIC LEAVE STATISTICS ---

// 3A. Fetch User Email to link with Onboarding table
$u_sql = $conn->prepare("SELECT email FROM users WHERE id = ?");
$u_sql->bind_param("i", $user_id);
$u_sql->execute();
$user_email = $u_sql->get_result()->fetch_assoc()['email'] ?? '';
$u_sql->close();

// 3B. Fetch exact total leaves allocated from employee_onboarding table
$total_entitled = 12; // Standard fallback
$o_sql = $conn->prepare("SELECT total_leaves FROM employee_onboarding WHERE email = ? LIMIT 1");
$o_sql->bind_param("s", $user_email);
$o_sql->execute();
$o_res = $o_sql->get_result();
if ($o_row = $o_res->fetch_assoc()) {
    if(intval($o_row['total_leaves']) > 0) {
        $total_entitled = intval($o_row['total_leaves']);
    }
}
$o_sql->close();

// 3C. Calculate Used Leaves per category
$stats_sql = "SELECT leave_type, SUM(total_days) as used_days 
              FROM leave_requests 
              WHERE user_id = ? AND status = 'Approved' 
              GROUP BY leave_type";
$stmt_stats = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt_stats, "i", $user_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);

$used = ['Medical' => 0, 'Casual' => 0];
while ($row = mysqli_fetch_assoc($result_stats)) {
    if (isset($used[$row['leave_type']])) {
        $used[$row['leave_type']] = $row['used_days'];
    }
}

$total_used = array_sum($used);
$total_remaining = max(0, $total_entitled - $total_used);


// --- 4. FETCH LEAVE HISTORY ---
$history_sql = "
    SELECT lr.*, 
           (SELECT COALESCE(ep.full_name, u.username) FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = lr.tl_id LIMIT 1) as tl_name,
           (SELECT COALESCE(ep.full_name, u.username) FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = lr.manager_id LIMIT 1) as mgr_name
    FROM leave_requests lr
    WHERE lr.user_id = ? 
    ORDER BY lr.created_at DESC
";
$stmt_hist = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($stmt_hist, "i", $user_id);
mysqli_stmt_execute($stmt_hist);
$history_result = mysqli_stmt_get_result($stmt_hist);

$sidebarPath = '../sidebars.php';
$headerPath = '../header.php';
if (!file_exists($sidebarPath)) { 
    $sidebarPath = 'sidebars.php'; 
    $headerPath = 'header.php'; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leaves - HRMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script> 
    <style>
        :root {
            --primary: #1b5a5a;
            --primary-hover: #134040;
            --bg-body: #f8f9fa;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
            --sidebar-width: 95px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; color: var(--text-main); overflow-x: hidden; }

        .main-content { margin-left: var(--sidebar-width); padding: 24px 32px; min-height: 100vh; transition: all 0.3s ease; width: calc(100% - var(--sidebar-width)); box-sizing: border-box; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 15px; flex-wrap: wrap; }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #0f172a;}
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; font-size: 13px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border); background: var(--white); color: var(--text-main); cursor: pointer; transition: 0.2s; gap: 8px; }
        .btn:hover { background: #f1f5f9; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 6px -1px rgba(27, 90, 90, 0.2); }
        .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-1px); }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; position: relative; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}
        .stat-value { font-size: 28px; font-weight: 800; margin-bottom: 8px; color: #0f172a;}
        .stat-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .card-decoration { position: absolute; right: -15px; top: 50%; transform: translateY(-50%); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0.1; }
        
        .card-total .stat-badge { background: #e0f2fe; color: #0284c7; } .card-total .card-decoration { background: #0ea5e9; opacity: 1; } .card-total .card-decoration i { color: white; position: relative; z-index: 2; }
        .card-medical .stat-badge { background: #fee2e2; color: #b91c1c; } .card-medical .card-decoration { background: #ef4444; }
        .card-casual .stat-badge { background: #ffedd5; color: #c2410c; } .card-casual .card-decoration { background: #f97316; }
        .card-remain .stat-badge { background: #dcfce7; color: #15803d; } .card-remain .card-decoration { background: #10b981; }
        .card-icon { width: 28px; height: 28px; color: white; }

        /* List Section */
        .list-section { background: white; border-radius: 12px; border: 1px solid var(--border); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .list-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .list-title { font-size: 16px; font-weight: 700; margin-right: auto; color: #0f172a;}

        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .input-group { display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; background: #f8fafc; color: var(--text-muted); font-size: 13px; flex: 1; min-width: 150px; }
        .input-group input, .input-group select { border: none; outline: none; color: var(--text-main); font-size: 13px; width: 100%; background: transparent; margin-left: 8px; cursor: pointer; font-weight: 500;}

        /* Table */
        .table-container { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { text-align: left; font-size: 11px; color: #64748b; padding: 14px 16px; border-bottom: 1px solid var(--border); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc;}
        td { font-size: 13px; color: #334155; padding: 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-weight: 500;}
        tr:hover { background-color: #fcfcfc; }
        
        /* UI Elements */
        .status-badge-container { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
        .status-badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .status-Approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;}
        .status-Pending { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;}
        .status-Rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;}
        .awaiting-text { font-size: 10px; color: #64748b; font-weight: 600; margin-left: 2px;}

        /* APPROVAL CHAIN UI */
        .chain-wrapper { display: inline-flex; align-items: center; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; }
        .chain-node { width: 24px; height: 24px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1); position: relative; z-index: 2; text-shadow: 0px 1px 1px rgba(0,0,0,0.2);}
        .chain-line { width: 16px; height: 2px; background-color: #cbd5e1; margin: 0 -2px; z-index: 1; }
        .node-approved { background-color: #10b981; }
        .node-pending { background-color: #f59e0b; }
        .node-rejected { background-color: #ef4444; }

        /* --- PERFECT RESPONSIVE MODAL --- */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .modal-box { 
            background: white; width: 500px; max-width: 100%; border-radius: 16px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); 
            display: flex; flex-direction: column; 
            max-height: 85vh; 
        }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;}
        
        .modal-body { padding: 24px; overflow-y: auto; flex: 1 1 auto; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;}
        .form-control { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: 0.2s; font-family: inherit; box-sizing: border-box;}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1);}
        
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; flex-shrink: 0; border-radius: 0 0 16px 16px;}

        @media (max-width: 992px) {
            .main-content { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px;}
        }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div class="main-content" id="mainContent">
        <?php include $headerPath; ?> 
        
        <div class="page-header mt-4">
            <div class="header-title">
                <h1>My Leaves</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px; height:14px;"></i>
                    <span>/</span> <span>Attendance</span> <span>/</span>
                    <span class="active" style="color:#0f172a; font-weight:600;">Leaves</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal()">
                    <i data-lucide="plus-circle" style="width:16px;"></i> Apply for Leave
                </button>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="stats-grid">
            <div class="stat-card card-total">
                <div class="stat-title">Total Entitled</div>
                <div class="stat-value"><?php echo $total_entitled; ?></div>
                <div class="stat-badge">Annual Quota</div>
                <div class="card-decoration"><i data-lucide="award" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-medical">
                <div class="stat-title">Medical Leaves</div>
                <div class="stat-value"><?php echo $used['Medical']; ?></div>
                <div class="stat-badge">Days Taken</div>
                <div class="card-decoration"><i data-lucide="activity" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-casual">
                <div class="stat-title">Casual Leaves</div>
                <div class="stat-value"><?php echo $used['Casual']; ?></div>
                <div class="stat-badge">Days Taken</div>
                <div class="card-decoration"><i data-lucide="coffee" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-remain">
                <div class="stat-title">Overall Remaining</div>
                <div class="stat-value"><?php echo $total_remaining; ?></div>
                <div class="stat-badge">Available Days</div>
                <div class="card-decoration"><i data-lucide="calendar-check-2" class="card-icon"></i></div>
            </div>
        </div>

        <div class="list-section">
            <div class="list-header">
                <span class="list-title">Leave History</span>
            </div>

            <div class="filters-row">
                <div class="input-group">
                    <i data-lucide="search" style="width:16px;"></i>
                    <input type="text" id="filterDate" placeholder="Search by reason or date..." onkeyup="filterTable()">
                </div>
                <div class="input-group">
                    <i data-lucide="filter" style="width:16px;"></i>
                    <select id="filterType" onchange="filterTable()">
                        <option value="">All Leave Types</option>
                        <option value="Medical">Medical Leave</option>
                        <option value="Casual">Casual Leave</option>
                    </select>
                </div>
                <div class="input-group">
                    <i data-lucide="activity" style="width:16px;"></i>
                    <select id="filterStatus" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table id="leavesTable">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date Range</th>
                            <th>Total Days</th>
                            <th>Reason</th>
                            <th>Approval Chain</th>
                            <th>Global Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($history_result) > 0) {
                            while($row = mysqli_fetch_assoc($history_result)) { 
                                
                                $t = $row['tl_status'] ?? 'Pending';
                                $m = $row['manager_status'] ?? 'Pending';
                                $h = $row['hr_status'] ?? 'Pending';
                                $db_stat = $row['status'];

                                // Determine which nodes to SHOW dynamically
                                $show_t_node = true;
                                $show_m_node = true;

                                // DYNAMIC UI FIX: Override display logic for old/legacy rows based on current user role
                                if (in_array($user_role, ['Team Lead', 'TL'])) { 
                                    $t = 'Approved'; 
                                    $show_t_node = false; // Hide TL circle
                                }
                                if (in_array($user_role, ['Manager', 'Project Manager', 'General Manager', 'HR', 'HR Executive'])) { 
                                    $t = 'Approved'; 
                                    $m = 'Approved'; 
                                    $show_t_node = false; // Hide TL circle
                                    $show_m_node = false; // Hide Manager circle
                                }
                                if (in_array($user_role, ['Admin', 'System Admin', 'CFO', 'CEO'])) { 
                                    $t = 'Approved'; 
                                    $m = 'Approved'; 
                                    $h = 'Approved'; 
                                    $db_stat = 'Approved';
                                    $show_t_node = false; // Hide TL circle
                                    $show_m_node = false; // Hide Manager circle
                                }

                                // Calculate real overall status
                                if ($db_stat === 'Approved' || ($t === 'Approved' && $m === 'Approved' && $h === 'Approved')) {
                                    $real_status = 'Approved';
                                } elseif ($t === 'Rejected' || $m === 'Rejected' || $h === 'Rejected' || $db_stat === 'Rejected') {
                                    $real_status = 'Rejected';
                                } else {
                                    $real_status = 'Pending';
                                }

                                $statusIcon = match($real_status) {
                                    'Approved' => 'check', 'Rejected' => 'x', default => 'clock'
                                };

                                // Calculate Colors for Approval Chain UI Nodes
                                $tl_node = ($t === 'Approved') ? 'node-approved' : (($t === 'Rejected') ? 'node-rejected' : 'node-pending');
                                $mgr_node = ($m === 'Approved') ? 'node-approved' : (($m === 'Rejected') ? 'node-rejected' : 'node-pending');
                                $hr_node = ($h === 'Approved') ? 'node-approved' : (($h === 'Rejected') ? 'node-rejected' : 'node-pending');

                                // STRICT AWAITING LOGIC (Shows exact next step without displaying skipped steps)
                                $awaiting = '';
                                if ($real_status === 'Pending') {
                                    if ($t === 'Pending' && $show_t_node) {
                                        $awaiting = 'Awaiting Team Lead';
                                    } elseif ($m === 'Pending' && $show_m_node) {
                                        $awaiting = 'Awaiting Manager';
                                    } elseif ($h === 'Pending') {
                                        $awaiting = 'Awaiting HR Approval';
                                    } else {
                                        $awaiting = 'Processing...';
                                    }
                                } elseif ($real_status === 'Rejected') {
                                    $awaiting = 'Denied by Management';
                                }
                        ?>
                        <tr>
                            <td>
                                <span style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:700; color:#475569;">
                                    <?php echo htmlspecialchars($row['leave_type']); ?>
                                </span>
                            </td>
                            <td><?php echo date("d M Y", strtotime($row['start_date'])) . ' <i data-lucide="arrow-right" style="width:12px; display:inline; margin:0 4px; color:#cbd5e1;"></i> ' . date("d M Y", strtotime($row['end_date'])); ?></td>
                            <td><span style="font-weight: 800; color:#0f172a;"><?php echo str_pad($row['total_days'], 2, '0', STR_PAD_LEFT); ?></span></td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['reason']); ?>">
                                <?php echo htmlspecialchars($row['reason']); ?>
                            </td>
                            <td>
                                <div class="chain-wrapper" title="TL: <?php echo $t; ?> | Mgr: <?php echo $m; ?> | HR: <?php echo $h; ?>">
                                    <?php if($show_t_node): ?>
                                    <div class="chain-node <?php echo $tl_node; ?>">T</div>
                                    <div class="chain-line"></div>
                                    <?php endif; ?>
                                    
                                    <?php if($show_m_node): ?>
                                    <div class="chain-node <?php echo $mgr_node; ?>">M</div>
                                    <div class="chain-line"></div>
                                    <?php endif; ?>
                                    
                                    <div class="chain-node <?php echo $hr_node; ?>">H</div>
                                </div>
                            </td>
                            <td>
                                <div class="status-badge-container">
                                    <span class="status-badge status-<?php echo $real_status; ?>">
                                        <i data-lucide="<?php echo $statusIcon; ?>" style="width:12px;"></i> <?php echo $real_status; ?>
                                    </span>
                                    <?php if($awaiting): ?>
                                        <span class="awaiting-text"><?php echo $awaiting; ?></span>
                                    <?php elseif($real_status === 'Approved'): ?>
                                        <span class="awaiting-text" style="color: #16a34a;">Verified</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            } 
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 40px; color:#64748b;'><i data-lucide='file-x-2' style='width:40px; height:40px; margin: 0 auto 10px auto; opacity:0.5;'></i>You haven't requested any leaves yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

   <div class="modal-overlay" id="leaveModal">
        <form method="POST" action="" class="modal-box" style="overflow: hidden;">
            <div class="modal-header">
                <h3>Apply for Leave</h3>
                <i data-lucide="x" style="cursor:pointer; color:#94a3b8;" onclick="closeModal()"></i>
            </div>
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Leave Type <span class="text-red-500">*</span></label>
                        <select name="leave_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Medical">Medical Leave</option>
                            <option value="Casual">Casual Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Date <span class="text-red-500">*</span></label>
                        <input type="date" name="start_date" id="dateFrom" class="form-control" required onchange="calculateDays()">
                    </div>
                    <div class="form-group">
                        <label>To Date <span class="text-red-500">*</span></label>
                        <input type="date" name="end_date" id="dateTo" class="form-control" required onchange="calculateDays()">
                    </div>
                    <div class="form-group full-width">
                        <label>Total Number of Days</label>
                        <input type="number" name="total_days" id="noOfDays" class="form-control bg-slate-50 border-slate-200 text-slate-500 font-bold" readonly>
                    </div>
                    <div class="form-group full-width">
                        <label>Reason for Leave <span class="text-red-500">*</span></label>
                        <textarea name="reason" class="form-control custom-scroll" rows="3" required maxlength="250" placeholder="Briefly explain the reason for your leave request..." oninput="updateCharCount(this)"></textarea>
                        <div style="text-align:right; font-size:11px; color:#94a3b8; font-weight:600; margin-top:4px;">
                            <span id="charCount">0</span>/250
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" name="submit_leave" class="btn btn-primary"><i data-lucide="send" style="width:14px;"></i> Submit Request</button>
            </div>
        </form>
    </div>

    <script>
        lucide.createIcons();

        // 1. DYNAMIC SIDEBAR OBSERVER
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

        // 2. MODAL CONTROLS
        function openModal() {
            document.getElementById('leaveModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('leaveModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close on clicking outside the box
        document.getElementById('leaveModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });

        // 3. UTILITIES
        function updateCharCount(textarea) {
            document.getElementById('charCount').textContent = textarea.value.length;
        }

        function calculateDays() {
            const start = document.getElementById('dateFrom').value;
            const end = document.getElementById('dateTo').value;
            const output = document.getElementById('noOfDays');

            if(start && end) {
                const d1 = new Date(start);
                const d2 = new Date(end);
                
                const diffTime = d2 - d1;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; 

                if(d2 < d1) {
                    alert("End date cannot be before start date.");
                    document.getElementById('dateTo').value = "";
                    output.value = "";
                } else {
                    output.value = diffDays;
                }
            }
        }

        // 4. TABLE FILTER
        function filterTable() {
            const typeFilter = document.getElementById('filterType').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const dateFilter = document.getElementById('filterDate').value.toLowerCase();
            
            const table = document.getElementById('leavesTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                // Adjust index based on column position
                const typeTd = tr[i].getElementsByTagName("td")[0];
                const dateTd = tr[i].getElementsByTagName("td")[1];
                const reasonTd = tr[i].getElementsByTagName("td")[3];
                const statusTd = tr[i].getElementsByTagName("td")[5]; // Adjusted for new Approval Chain column
                
                if (typeTd && statusTd && dateTd && reasonTd) {
                    const typeTxt = typeTd.textContent || typeTd.innerText;
                    const dateTxt = dateTd.textContent || dateTd.innerText;
                    const reasonTxt = reasonTd.textContent || reasonTd.innerText;
                    const statusTxt = statusTd.textContent || statusTd.innerText;

                    const showType = typeFilter === '' || typeTxt.toLowerCase().includes(typeFilter);
                    const showStatus = statusFilter === '' || statusTxt.toLowerCase().includes(statusFilter);
                    const showSearch = dateFilter === '' || dateTxt.toLowerCase().includes(dateFilter) || reasonTxt.toLowerCase().includes(dateFilter);

                    tr[i].style.display = (showType && showStatus && showSearch) ? "" : "none";
                }
            }
        }
    </script>
</body>
</html>