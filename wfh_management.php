<?php
// wfh_management.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION (Smart Path)
$db_path = __DIR__ . '/include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    require_once '../include/db_connect.php'; 
}

$user_id = $_SESSION['user_id'];

// Get exact user role from DB
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

// Role Classification for Strict Hierarchy Enforcement
$is_tl = in_array($user_role, ['Team Lead', 'TL']);
$is_mgr = in_array($user_role, ['Manager', 'Project Manager', 'General Manager']);
$is_it_admin = ($user_role === 'IT Admin');
$is_hr_admin = in_array($user_role, ['HR', 'HR Executive', 'Admin', 'System Admin', 'CFO', 'CEO']);

// =========================================================================
// 3. PROCESS AJAX WFH ACTIONS (Sequential Multi-Tier Logic)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id']) && isset($_POST['status'])) {
    $req_id = intval($_POST['request_id']);
    $new_status = $_POST['status']; 
    $reviewer_name = $user_role; // Record who made the change
    
    // Fetch current statuses
    $fetch = $conn->prepare("SELECT tl_status, manager_status, hr_status, status FROM wfh_requests WHERE id = ?");
    $fetch->bind_param("i", $req_id);
    $fetch->execute();
    $curr = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    $tl_stat = $curr['tl_status'] ?? 'Pending';
    $mgr_stat = $curr['manager_status'] ?? 'Pending';
    $hr_stat = $curr['hr_status'] ?? 'Pending';

    // Apply the approval to the CORRECT tier based on whoever clicked it
    if ($is_tl) { $tl_stat = $new_status; }
    elseif ($is_mgr || $is_it_admin) { $mgr_stat = $new_status; } // IT Admin acts as manager tier for IT Execs
    elseif ($is_hr_admin) { $hr_stat = $new_status; } 

    // STRICT 3-TIER DB EVALUATION
    if ($tl_stat === 'Rejected' || $mgr_stat === 'Rejected' || $hr_stat === 'Rejected') {
        $global_stat = 'Rejected';
    } elseif ($tl_stat === 'Approved' && $mgr_stat === 'Approved' && $hr_stat === 'Approved') {
        $global_stat = 'Approved';
    } else {
        $global_stat = 'Pending';
    }

    // Update the status and tag the reviewer's role
    $update_query = "UPDATE wfh_requests SET tl_status=?, manager_status=?, hr_status=?, status=?, reviewer_name=? WHERE id=?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssssi", $tl_stat, $mgr_stat, $hr_stat, $global_stat, $reviewer_name, $req_id);

    if ($update_stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    
    $update_stmt->close();
    exit(); 
}

// =========================================================================
// 4. FETCH DATA FOR UI DISPLAY
// =========================================================================
$base_select = "SELECT w.*, 
                COALESCE(ep.full_name, u.username, 'Unknown Employee') as emp_name, 
                COALESCE(ep.emp_id_code, 'N/A') as emp_id_code,
                u.role as actual_role,
                ep.designation as emp_role,
                ep.profile_img
              FROM wfh_requests w 
              JOIN users u ON w.user_id = u.id 
              LEFT JOIN employee_profiles ep ON u.id = ep.user_id";

$query = "";
if ($is_tl) {
    // TL excludes special routing roles
    $query = "$base_select WHERE ep.reporting_to = ? AND w.user_id != ? AND u.role NOT IN ('CFO', 'Accounts', 'Accountant', 'IT Executive', 'IT Admin', 'HR Executive') ORDER BY w.applied_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $user_id);
} elseif ($is_mgr) {
    // Manager excludes special routing roles
    $query = "$base_select WHERE (ep.manager_id = ? OR ep.reporting_to = ? OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE manager_id = ?)) AND w.user_id != ? AND u.role NOT IN ('CFO', 'Accounts', 'Accountant', 'IT Executive', 'IT Admin', 'HR Executive') ORDER BY w.applied_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
} elseif ($is_it_admin) {
    // IT Admin ONLY sees IT Executives
    $query = "$base_select WHERE u.role = 'IT Executive' AND w.user_id != ? ORDER BY w.applied_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
} elseif ($is_hr_admin) {
    // HR/Admins see everything (except their own requests here, they use My WFH)
    $query = "$base_select WHERE w.user_id != ? ORDER BY w.applied_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
} else {
    // Security Seal: If Accountant/Random Role tries to view this page, show 0 requests.
    $query = "$base_select WHERE 1=0";
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();

$wfh_requests = [];
while ($row = $result->fetch_assoc()) {
    // --- SMART PROFILE IMAGE RESOLVER ---
    $imgSource = $row['profile_img'] ?? '';
    
    if (empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=" . urlencode($row['emp_name']) . "&background=random";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = (file_exists('assets/profiles/' . $imgSource)) ? 'assets/profiles/' . $imgSource : '../assets/profiles/' . $imgSource; 
    }
    
    $row['avatar'] = $imgSource;

    // --- DYNAMIC LOGIC SYNC ---
    $tl_stat = $row['tl_status'] ?? 'Pending';
    $mgr_stat = $row['manager_status'] ?? 'Pending';
    $hr_stat = $row['hr_status'] ?? 'Pending';
    $db_global = $row['status'] ?: 'Pending';

    $req_role = trim($row['actual_role']);
    
    if (in_array($req_role, ['Team Lead', 'TL'])) { $tl_stat = 'Approved'; }
    if (in_array($req_role, ['Manager', 'Project Manager', 'General Manager'])) { $tl_stat = 'Approved'; $mgr_stat = 'Approved'; }
    if ($req_role === 'IT Executive') { $tl_stat = 'Approved'; }
    if (in_array($req_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])) { $tl_stat = 'Approved'; $mgr_stat = 'Approved'; }
    if (in_array($req_role, ['Admin', 'System Admin', 'CEO'])) { $tl_stat = 'Approved'; $mgr_stat = 'Approved'; $hr_stat = 'Approved'; $db_global = 'Approved'; }

    // Strict recalculation to ensure accuracy
    if ($db_global === 'Approved' || ($tl_stat === 'Approved' && $mgr_stat === 'Approved' && $hr_stat === 'Approved')) {
        $real_global_status = 'Approved';
    } elseif ($tl_stat === 'Rejected' || $mgr_stat === 'Rejected' || $hr_stat === 'Rejected' || $db_global === 'Rejected') {
        $real_global_status = 'Rejected';
    } else {
        $real_global_status = 'Pending';
    }

    // SEQUENTIAL ACTION VISIBILITY LOGIC
    $can_action = false;
    $waiting_on_other = false;
    
    if ($real_global_status === 'Pending') {
        if ($is_tl) {
            if ($tl_stat === 'Pending') $can_action = true;
        } elseif ($is_mgr) {
            if ($tl_stat === 'Pending') { $waiting_on_other = 'Awaiting TL'; }
            elseif ($mgr_stat === 'Pending') { $can_action = true; }
        } elseif ($is_it_admin) {
            if ($mgr_stat === 'Pending') { $can_action = true; } 
        } elseif ($is_hr_admin) {
            if ($req_role === 'IT Executive') {
                if ($mgr_stat === 'Pending') { $waiting_on_other = 'Awaiting IT Admin'; }
                elseif ($hr_stat === 'Pending') { $can_action = true; }
            } elseif (in_array($req_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])) {
                if ($hr_stat === 'Pending') { $can_action = true; }
            } else {
                if ($tl_stat === 'Pending') { $waiting_on_other = 'Awaiting TL'; }
                elseif ($mgr_stat === 'Pending') { $waiting_on_other = 'Awaiting Manager'; }
                elseif ($hr_stat === 'Pending') { $can_action = true; }
            }
        }
    }

    $row['status'] = $real_global_status;
    $row['can_action'] = $can_action;
    $row['waiting_on_other'] = $waiting_on_other;

    // Save evaluated statuses to the array to use in the UI safely
    $row['eval_tl_stat'] = $tl_stat;
    $row['eval_mgr_stat'] = $mgr_stat;
    $row['eval_hr_stat'] = $hr_stat;

    $wfh_requests[] = $row;
}
$stmt->close();

$sidebarPath = __DIR__ . '/sidebars.php'; 
if (!file_exists($sidebarPath)) { $sidebarPath = '../sidebars.php'; }
$headerPath = __DIR__ . '/header.php';
if (!file_exists($headerPath)) { $headerPath = '../header.php'; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WFH Management - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #1b5a5a; 
            --primary-hover: #144343;
            --bg-body: #f8f9fa;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --white: #ffffff;
            --sidebar-width: 95px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
            line-height: 1.5;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 32px;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            transition: all 0.3s ease;
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #0f172a; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 8px 16px; font-size: 13px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 6px;
        }
        .btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* --- MANAGEMENT CARD --- */
        .management-card {
            background: white; border: 1px solid var(--border);
            border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #1e293b;}

        /* --- FILTERS --- */
        .filters-grid {
            display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 25px; align-items: center;
        }
        
        .filter-group {
            display: flex; align-items: center; 
            border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px; background: white; 
            flex: 1; min-width: 140px; position: relative; transition: border-color 0.2s;
        }
        .filter-group:hover { border-color: #9ca3af; }
        .filter-group i { color: #9ca3af; width: 16px; height: 16px; flex-shrink: 0; }
        
        .filter-group select, .filter-group input {
            border: none; outline: none; width: 100%; margin-left: 8px; 
            font-size: 13px; color: var(--text-main); background: transparent; cursor: pointer;
        }
        
        .search-bar-group {
            border: 1px solid #9ca3af !important; 
            flex: 2; 
            min-width: 250px;
        }
        .search-bar-group:focus-within {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 2px rgba(27, 90, 90, 0.1);
        }

        .filter-label {
            position: absolute; top: -9px; left: 10px; background: white;
            padding: 0 5px; font-size: 11px; color: #6b7280; font-weight: 500;
        }

        /* --- TABLE --- */
        .table-responsive { overflow-x: auto; width: 100%; border-radius: 8px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        
        thead { background: #f9fafb; }
        th { 
            text-align: left; padding: 14px 16px; font-size: 11px; 
            font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        td { padding: 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        tr:hover { background-color: #fcfcfc; }
        
        .emp-cell { display: flex; align-items: center; gap: 12px; }
        .emp-avatar {
            width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
            background: #e5e7eb; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 11px; flex-shrink: 0;
        }
        .emp-name { font-weight: 600; color: #0f172a; display: block; }
        .emp-desig { font-size: 11px; color: #64748b; }

        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .status-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .status-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* --- VISUAL APPROVAL CHAIN UI --- */
        .approval-chain { display: flex; align-items: center; gap: 4px; }
        .chain-node { width: 22px; height: 22px; border-radius: 50%; color: white; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: help; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .chain-link { width: 12px; height: 2px; background: #cbd5e1; border-radius: 2px; }
        
        /* Updated dynamic colors for approval chain nodes based on status */
        .node-approved { background: #22c55e; } /* Green */
        .node-pending { background: #f59e0b; }  /* Yellow/Amber */
        .node-rejected { background: #ef4444; } /* Red */

        /* --- MODALS --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(4px); padding: 20px;
        }
        .modal-overlay.active { display: flex; animation: fadeUp 0.2s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal-box { 
            background: white; width: 550px; max-width: 100%; 
            border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
            overflow: hidden; 
        }

        .modal-header { 
            padding: 20px 24px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; background: #fff;
        }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;}
        
        .modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569; }
        .form-group label span { color: #ef4444; }
        
        .form-control {
            width: 100%; padding: 12px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;
        }
        .form-control:focus { border-color: var(--primary); }
        .form-control:disabled, .form-control[readonly] { background-color: #f8fafc; color: #64748b; cursor: not-allowed;}
        
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #f9fafb; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 16px; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content" id="mainContent">
        <?php if (file_exists($headerPath)) include($headerPath); ?>
        
        <div class="page-header mt-4">
            <div>
                <h1 class="header-title">Work From Home Management <span style="color: var(--primary); font-size: 18px;">(<?php echo htmlspecialchars($user_role); ?>)</span></h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span> Leaves <span>/</span> <span style="font-weight:600; color:#0f172a;">WFH Requests</span>
                </div>
            </div>
            
        </div>

        <div class="management-card">
            <h3 class="card-title">Employee WFH Requests</h3>
            
            <div class="filters-grid">
                <div class="filter-group search-bar-group">
                    <i data-lucide="search"></i>
                    <input type="text" id="mainSearch" placeholder="Search by employee name or ID..." onkeyup="runFilter()">
                </div>

                <div class="filter-group">
                    <select id="filterStatus" onchange="runFilter()">
                        <option value="">Final Status: All</option>
                        <option value="Approved">Final Approved</option>
                        <option value="Pending">In Pipeline (Pending)</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">From Date</label>
                    <i data-lucide="calendar" style="color:var(--primary);"></i>
                    <input type="date" id="fromDate" onchange="runFilter()">
                </div>

                <div class="filter-group">
                    <label class="filter-label">To Date</label>
                    <i data-lucide="calendar" style="color:var(--primary);"></i>
                    <input type="date" id="toDate" onchange="runFilter()">
                </div>
            </div>

            <div class="table-responsive">
                <table id="employeeWfhTable">
                    <thead>
                        <tr>
                            <th>Emp ID</th>
                            <th>Employee</th>
                            <th>Dates</th>
                            <th>Reason</th>
                            <th>Approval Chain</th>
                            <th>Final Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($wfh_requests) > 0): ?>
                            <?php foreach($wfh_requests as $req): ?>
                                <tr data-start="<?php echo $req['start_date']; ?>">
                                    <td style="font-weight:700; color:#475569; font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($req['emp_id_code']); ?></td>
                                    <td>
                                        <div class="emp-cell">
                                            <img src="<?php echo htmlspecialchars($req['avatar']); ?>" class="emp-avatar" alt="Avatar">
                                            <div>
                                                <span class="emp-name"><?php echo htmlspecialchars($req['emp_name']); ?></span>
                                                <span class="emp-desig"><?php echo htmlspecialchars($req['emp_role']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: #334155;"><?php echo date('d M', strtotime($req['start_date'])) . ' - ' . date('d M Y', strtotime($req['end_date'])); ?></span><br>
                                        <span style="font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo htmlspecialchars($req['shift']); ?></span>
                                    </td>
                                    <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($req['reason']); ?>">
                                        <?php echo htmlspecialchars($req['reason']); ?>
                                    </td>
                                    
                                    <td>
                                        <div class="approval-chain">
                                            <?php 
                                                // Using dynamically resolved statuses to render the correct UI elements
                                                $tl_node = $req['eval_tl_stat'] == 'Approved' ? 'node-approved' : ($req['eval_tl_stat'] == 'Rejected' ? 'node-rejected' : 'node-pending');
                                                $mgr_node = $req['eval_mgr_stat'] == 'Approved' ? 'node-approved' : ($req['eval_mgr_stat'] == 'Rejected' ? 'node-rejected' : 'node-pending');
                                                $hr_node = $req['eval_hr_stat'] == 'Approved' ? 'node-approved' : ($req['eval_hr_stat'] == 'Rejected' ? 'node-rejected' : 'node-pending');
                                            ?>
                                            
                                            <?php if ($req['actual_role'] === 'IT Executive'): ?>
                                                <span class="chain-node <?php echo $mgr_node; ?>" title="IT Admin: <?php echo $req['eval_mgr_stat']; ?>">A</span>
                                                <div class="chain-link"></div>
                                                <span class="chain-node <?php echo $hr_node; ?>" title="HR: <?php echo $req['eval_hr_stat']; ?>">H</span>
                                            <?php elseif (in_array($req['actual_role'], ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])): ?>
                                                <span class="chain-node <?php echo $hr_node; ?>" title="HR: <?php echo $req['eval_hr_stat']; ?>">H</span>
                                            <?php else: ?>
                                                <span class="chain-node <?php echo $tl_node; ?>" title="Team Lead: <?php echo $req['eval_tl_stat']; ?>">T</span>
                                                <div class="chain-link"></div>
                                                <span class="chain-node <?php echo $mgr_node; ?>" title="Manager: <?php echo $req['eval_mgr_stat']; ?>">M</span>
                                                <div class="chain-link"></div>
                                                <span class="chain-node <?php echo $hr_node; ?>" title="HR: <?php echo $req['eval_hr_stat']; ?>">H</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php 
                                            $badgeClass = "status-" . strtolower($req['status']);
                                            $icon = ($req['status'] == 'Approved') ? 'check' : (($req['status'] == 'Rejected') ? 'x' : 'clock');
                                        ?>
                                        <span class="status-pill <?php echo $badgeClass; ?>"><i data-lucide="<?php echo $icon; ?>" style="width:12px;"></i> <?php echo $req['status']; ?></span>
                                    </td>
                                    
                                    <td>
                                        <?php if($req['can_action']): ?>
                                            <button class="btn btn-sm btn-primary" 
                                                data-id="<?php echo $req['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($req['emp_name']); ?>"
                                                data-reason="<?php echo htmlspecialchars($req['reason']); ?>"
                                                data-status="<?php echo $req['status']; ?>"
                                                onclick="openApprovalModal(this, false)">
                                                <i data-lucide="edit-3" style="width:14px;"></i> Review
                                            </button>
                                        <?php elseif($req['waiting_on_other']): ?>
                                            <span style="font-size: 11px; font-weight: 700; color: #94a3b8; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                                                <i data-lucide="lock" style="width:12px; display:inline; margin-bottom:-2px;"></i> <?php echo $req['waiting_on_other']; ?>
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-sm" style="background: #f8fafc; border-color: #e2e8f0; color: #475569;"
                                                data-id="<?php echo $req['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($req['emp_name']); ?>"
                                                data-reason="<?php echo htmlspecialchars($req['reason']); ?>"
                                                data-status="<?php echo $req['status']; ?>"
                                                data-reviewer="<?php echo htmlspecialchars($req['reviewer_name'] ?? ''); ?>"
                                                onclick="openApprovalModal(this, true)">
                                                <i data-lucide="eye" style="width:14px;"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;"></i>
                                    No WFH requests found in your pipeline.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="approvalModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Update Request Status</h3>
                <i data-lucide="x" style="cursor:pointer; color: #94a3b8;" onclick="toggleModal('approvalModal', false)"></i>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editRequestId">
                
                <div class="form-group">
                    <label>Employee Name</label>
                    <input type="text" id="editEmpName" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Stated Reason</label>
                    <textarea id="editEmpReason" class="form-control" readonly rows="3"></textarea>
                </div>
                
                <div class="form-group" id="reviewerGroup" style="display: none;">
                    <label>Action Taken By</label>
                    <input type="text" id="editReviewerName" class="form-control" readonly style="font-weight: 600; color: #1e293b;">
                </div>

                <div class="form-group" id="decisionGroup">
                    <label>Request Status <span id="statusAsterisk">*</span></label>
                    <select id="editStatusSelect" class="form-control" style="-webkit-appearance: auto; appearance: auto;">
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="toggleModal('approvalModal', false)">Close Window</button>
                <button class="btn btn-primary" id="saveStatusBtn" onclick="saveStatusUpdate()">Save Changes</button>
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

            const ro = new ResizeObserver(() => { updateMargin(); });
            ro.observe(primarySidebar);

            if (secondarySidebar) {
                const mo = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            updateMargin();
                        }
                    });
                });
                mo.observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            window.addEventListener('resize', updateMargin);
            updateMargin();
        }
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);

        // Modal Toggle
        function toggleModal(modalId, show) {
            const modal = document.getElementById(modalId);
            modal.classList.toggle('active', show);
            document.body.style.overflow = show ? 'hidden' : 'auto';
        }

        // Open Modal (Smart read-only logic)
        function openApprovalModal(btnElement, isReadOnly) {
            // Extract data from attributes to prevent JSON/newline breakage
            const id = btnElement.getAttribute('data-id');
            const name = btnElement.getAttribute('data-name');
            const reason = btnElement.getAttribute('data-reason');
            const status = btnElement.getAttribute('data-status');
            const reviewer = btnElement.getAttribute('data-reviewer');

            document.getElementById('editRequestId').value = id;
            document.getElementById('editEmpName').value = name;
            document.getElementById('editEmpReason').value = reason;
            document.getElementById('editStatusSelect').value = status;
            
            const saveBtn = document.getElementById('saveStatusBtn');
            const selectBox = document.getElementById('editStatusSelect');
            const reviewerGroup = document.getElementById('reviewerGroup');
            const decisionGroup = document.getElementById('decisionGroup');
            const asterisk = document.getElementById('statusAsterisk');

            if (isReadOnly) {
                // View Mode
                document.getElementById('modalTitle').innerText = "View Request Details";
                saveBtn.style.display = 'none';
                
                // Hide selection box, show only text input of reviewer
                decisionGroup.style.display = 'none';
                
                reviewerGroup.style.display = 'block';
                document.getElementById('editReviewerName').value = reviewer ? reviewer : 'Admin System';
            } else {
                // Edit Mode
                document.getElementById('modalTitle').innerText = "Update Request Status";
                saveBtn.style.display = 'block';
                
                decisionGroup.style.display = 'block';
                selectBox.disabled = false;
                selectBox.style.borderColor = 'var(--primary)';
                asterisk.style.display = 'inline';
                
                reviewerGroup.style.display = 'none';
            }
            
            toggleModal('approvalModal', true);
        }

        // Save status via AJAX
        function saveStatusUpdate() {
            const id = document.getElementById('editRequestId').value;
            const newStatus = document.getElementById('editStatusSelect').value;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `request_id=${id}&status=${newStatus}`
            })
            .then(response => response.text())
            .then(data => {
                if(data.trim() === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'WFH Status has been updated.',
                        icon: 'success',
                        confirmButtonColor: '#1b5a5a'
                    }).then(() => {
                        location.reload(); 
                    });
                } else {
                    Swal.fire('Error', 'Failed to update status.', 'error');
                }
            });
        }

        // Search and Calendar Date Filter Logic
        function runFilter() {
            const search = document.getElementById('mainSearch').value.toUpperCase();
            const status = document.getElementById('filterStatus').value.toUpperCase();
            const fromDate = document.getElementById('fromDate').value; 
            const toDate = document.getElementById('toDate').value;     
            
            const rows = document.getElementById('employeeWfhTable').getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                if(rows[i].cells.length < 7) continue; 

                const idTxt = rows[i].cells[0].textContent.toUpperCase();
                const nameTxt = rows[i].cells[1].textContent.toUpperCase();
                const reasonTxt = rows[i].cells[3].textContent.toUpperCase(); 
                const statusTxt = rows[i].cells[5].textContent.toUpperCase();
                
                const rowStartDate = rows[i].getAttribute('data-start'); 

                const matchesSearch = nameTxt.includes(search) || idTxt.includes(search) || reasonTxt.includes(search);
                const matchesStatus = status === "" || statusTxt.includes(status);
                
                let matchesDate = true;
                if (fromDate && rowStartDate < fromDate) {
                    matchesDate = false;
                }
                if (toDate && rowStartDate > toDate) {
                    matchesDate = false;
                }

                rows[i].style.display = (matchesSearch && matchesStatus && matchesDate) ? "" : "none";
            }
        }
    </script>
</body>
</html>