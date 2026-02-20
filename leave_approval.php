<?php
// leave_approvals.php

// 1. SESSION START & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// FIXED DATABASE CONNECTION PATH
$db_path = __DIR__ . '/include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    require_once '../include/db_connect.php'; 
}

$user_id = $_SESSION['user_id'];

// Get user role accurately from DB
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

// =========================================================================
// 2. PROCESS AJAX LEAVE ACTIONS (Approve/Reject)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id']) && isset($_POST['status'])) {
    $leave_id = intval($_POST['leave_id']);
    $new_status = $_POST['status']; // 'Approved' or 'Rejected'
    
    // Fetch current statuses
    $fetch = $conn->prepare("SELECT tl_status, manager_status, hr_status, status FROM leave_requests WHERE id = ?");
    $fetch->bind_param("i", $leave_id);
    $fetch->execute();
    $curr = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    $tl_stat = $curr['tl_status'];
    $mgr_stat = $curr['manager_status'];
    $hr_stat = $curr['hr_status'];
    $global_stat = $new_status; // Set global to whatever they clicked
    $approved_by = $user_role;  // Track exactly who clicked the button

    // Update specific role statuses based on who is logged in
    if ($user_role === 'Team Lead') { $tl_stat = $new_status; }
    if ($user_role === 'Manager') { $mgr_stat = $new_status; }
    if ($user_role === 'HR' || $user_role === 'HR Executive') { $hr_stat = $new_status; }

    // Update everything in the database
    $update_query = "UPDATE leave_requests SET tl_status=?, manager_status=?, hr_status=?, status=?, approved_by=? WHERE id=?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssssi", $tl_stat, $mgr_stat, $hr_stat, $global_stat, $approved_by, $leave_id);

    if ($update_stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    
    $update_stmt->close();
    $conn->close(); 
    exit(); 
}

// =========================================================================
// 3. FETCH DATA FOR UI DISPLAY
// =========================================================================
$base_select = "SELECT lr.*, 
                COALESCE(ep.full_name, u.name, 'Unknown Employee') as emp_name, 
                COALESCE(ep.designation, u.role, 'Employee') as emp_role,
                ep.profile_img,
                (SELECT COALESCE(SUM(total_days), 0) FROM leave_requests 
                 WHERE user_id = lr.user_id 
                   AND status = 'Approved' 
                   AND MONTH(start_date) = MONTH(CURRENT_DATE()) 
                   AND YEAR(start_date) = YEAR(CURRENT_DATE())
                ) as current_month_leaves
              FROM leave_requests lr 
              JOIN users u ON lr.user_id = u.id 
              LEFT JOIN employee_profiles ep ON u.id = ep.user_id";

$query = "";
if ($user_role === 'Team Lead') {
    // TL sees direct reports
    $query = "$base_select WHERE ep.reporting_to = ? OR lr.tl_id = ? ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $user_id);
} elseif ($user_role === 'Manager') {
    // Manager sees direct reports OR reports under their TLs
    $query = "$base_select WHERE ep.manager_id = ? OR ep.reporting_to = ? OR lr.manager_id = ? ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
} else {
    // HR, Admin, Executives see everything
    $query = "$base_select ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();

$leave_requests = [];
$pending_count = 0; 
$approved_count = 0; 
$rejected_count = 0;

while ($row = $result->fetch_assoc()) {
    $viewer_status = 'Pending';
    if ($user_role === 'Team Lead') { $viewer_status = $row['tl_status']; } 
    elseif ($user_role === 'Manager') { $viewer_status = $row['manager_status']; } 
    else { $viewer_status = $row['hr_status']; }

    $global_status = $row['status'] ?: 'Pending';

    // Update Top Counter Cards
    if ($global_status === 'Pending') $pending_count++;
    if ($global_status === 'Approved') $approved_count++;
    if ($global_status === 'Rejected') $rejected_count++;

    // Format Image Source
    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['emp_name'])."&background=random";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = '../assets/profiles/' . $imgSource; 
    }

    $leave_requests[] = [
        'id' => $row['id'],
        'emp_name' => $row['emp_name'],
        'emp_role' => $row['emp_role'],
        'avatar' => $imgSource,
        'leave_type' => $row['leave_type'],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'total_days' => $row['total_days'],
        'created_at' => $row['created_at'],
        'reason' => $row['reason'],
        'current_month_leaves' => $row['current_month_leaves'],
        'viewer_status' => $viewer_status, 
        'global_status' => $global_status, 
        'tl_status' => $row['tl_status'],
        'mgr_status' => $row['manager_status'],
        'hr_status' => $row['hr_status'],
        'approved_by' => $row['approved_by']
    ];
}
$stmt->close();
$conn->close(); 

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
    <title>Leave Approvals - HRMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

    <style>
        :root { --primary: #f97316; --primary-hover: #ea580c; --bg-body: #f8f9fa; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --white: #ffffff; --success: #10b981; --danger: #ef4444; --sidebar-width: 95px;}
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; color: var(--text-main); overflow-x: hidden;}
        
        .main-content { margin-left: var(--sidebar-width); padding: 24px 32px; min-height: 100vh; transition: all 0.3s ease; width: calc(100% - var(--sidebar-width)); box-sizing: border-box;}
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 15px; flex-wrap: wrap; }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #0f172a; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); position: relative; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .stat-info h4 { font-size: 13px; color: var(--text-muted); margin: 0 0 5px 0; font-weight: 500; text-transform: uppercase; }
        .stat-info h2 { font-size: 28px; font-weight: 700; margin: 0; color: var(--text-main); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .card-pending .stat-icon { background: #fff7ed; color: #f97316; } .card-approved .stat-icon { background: #f0fdf4; color: #16a34a; } .card-rejected .stat-icon { background: #fef2f2; color: #dc2626; } .card-total .stat-icon { background: #eff6ff; color: #2563eb; }
        
        .list-section { background: white; border-radius: 12px; border: 1px solid var(--border); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { flex: 2; min-width: 250px; display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; }
        .search-box input { border: none; outline: none; width: 100%; font-size: 13px; margin-left: 8px; }
        .filter-select { flex: 1; min-width: 150px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; color: var(--text-main); outline: none; cursor: pointer; }
        
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; font-size: 12px; color: #475569; padding: 14px 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        tr:hover { background-color: #fcfcfc; }
        
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;}
        .emp-info { display: flex; flex-direction: column; gap: 2px;} .emp-name { font-weight: 600; color: #0f172a; } .emp-dept { font-size: 11px; color: #64748b; }
        .leave-month-badge { font-size: 10px; background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; display: inline-block; width: fit-content; margin-top: 2px; }
        
        .status-badge-container { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; width: fit-content;}
        .status-Pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; } .status-Approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } .status-Rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .approved-by-text { font-size: 10px; color: #64748b; font-weight: 500; margin-left: 2px; }

        .leave-type { font-weight: 500; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: #334155; font-size: 12px; }
        
        .action-container { display: flex; gap: 8px; }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border); background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; color: var(--text-muted); }
        .btn-icon:hover { background: #f8fafc; color: var(--primary); } .btn-approve:hover { background: #dcfce7; color: #166534; border-color: #bbf7d0; } .btn-reject:hover { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        
        /* Modal Transitions */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; animation: fadeUp 0.2s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .modal-box { background: white; width: 600px; max-width: 95%; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .modal-body { padding: 24px; } .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .detail-item label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 4px; } .detail-item p { margin: 0; font-size: 14px; font-weight: 500; color: #1e293b; }
        .reason-box { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; } .reason-box p { font-size: 13px; color: #334155; line-height: 1.5; margin: 0; }
        .modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; } .btn-outline { background: white; border: 1px solid var(--border); color: #334155; }

        /* Approval Track UI */
        .approval-track { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .approval-track h6 { margin: 0 0 12px 0; font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .track-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed var(--border); }
        .track-item:last-child { border-bottom: none; padding-bottom: 0; }
        .track-item span { font-size: 13px; font-weight: 500; color: var(--text-main); }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div class="main-content" id="mainContent">
        <?php include $headerPath; ?>

        <div class="page-header mt-4">
            <div>
                <h1>Leave Approvals <span style="color: var(--primary); font-size: 18px;">(<?php echo htmlspecialchars($user_role); ?>)</span></h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span> Leaves <span>/</span> <span style="font-weight:600; color:#0f172a;">Approvals</span>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-pending">
                <div class="stat-info">
                    <h4>My Pending Tasks</h4>
                    <h2><?php echo $pending_count; ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="clock"></i></div>
            </div>
            <div class="stat-card card-approved">
                <div class="stat-info">
                    <h4>Global Approved</h4>
                    <h2><?php echo $approved_count; ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="check-circle-2"></i></div>
            </div>
            <div class="stat-card card-rejected">
                <div class="stat-info">
                    <h4>Global Rejected</h4>
                    <h2><?php echo $rejected_count; ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="x-circle"></i></div>
            </div>
            <div class="stat-card card-total">
                <div class="stat-info">
                    <h4>Total Team Requests</h4>
                    <h2><?php echo count($leave_requests); ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="users"></i></div>
            </div>
        </div>

        <div class="list-section">
            <div class="filters-row">
                <div class="search-box">
                    <i data-lucide="search" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" id="searchInput" placeholder="Search employee name or type..." onkeyup="filterTable()">
                </div>
                <select class="filter-select" id="typeFilter" onchange="filterTable()">
                    <option value="">All Leave Types</option>
                    <option value="Medical">Medical</option>
                    <option value="Casual">Casual</option>
                    <option value="Annual">Annual</option>
                    <option value="Other">Other</option>
                </select>
                <select class="filter-select" id="statusFilter" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="table-responsive">
                <table id="approvalTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Duration</th>
                            <th>Days</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($leave_requests) > 0): ?>
                            <?php foreach($leave_requests as $leave): ?>
                                <tr>
                                    <td>
                                        <div class="emp-profile">
                                            <img src="<?php echo $leave['avatar']; ?>" class="emp-avatar" alt="User">
                                            <div class="emp-info">
                                                <span class="emp-name"><?php echo htmlspecialchars($leave['emp_name']); ?></span>
                                                <span class="emp-dept"><?php echo htmlspecialchars($leave['emp_role']); ?></span>
                                                <span class="leave-month-badge"><i data-lucide="calendar-check" style="width:10px; height:10px; display:inline; margin-right:2px;"></i> Taken this month: <?php echo $leave['current_month_leaves']; ?> days</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="leave-type"><?php echo htmlspecialchars($leave['leave_type']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($leave['start_date'])) . ' - ' . date('d M Y', strtotime($leave['end_date'])); ?></td>
                                    <td style="font-weight: 600;"><?php echo str_pad($leave['total_days'], 2, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                            $badgeClass = "status-" . $leave['global_status'];
                                            $icon = ($leave['global_status'] == 'Approved') ? 'check' : (($leave['global_status'] == 'Rejected') ? 'x' : 'clock');
                                        ?>
                                        <div class="status-badge-container">
                                            <span class="status-badge <?php echo $badgeClass; ?>"><i data-lucide="<?php echo $icon; ?>" style="width:10px;"></i> <?php echo $leave['global_status']; ?></span>
                                            <?php if($leave['global_status'] !== 'Pending' && !empty($leave['approved_by'])): ?>
                                                <span class="approved-by-text">by <?php echo htmlspecialchars($leave['approved_by']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-container">
                                            <?php if($leave['global_status'] === 'Pending'): ?>
                                                <button class="btn-icon btn-approve" onclick="updateLeave(<?php echo $leave['id']; ?>, 'Approved')" title="Approve"><i data-lucide="check" style="width:16px;"></i></button>
                                                <button class="btn-icon btn-reject" onclick="updateLeave(<?php echo $leave['id']; ?>, 'Rejected')" title="Reject"><i data-lucide="x" style="width:16px;"></i></button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-icon" onclick="viewDetails(
                                                '<?php echo htmlspecialchars(addslashes($leave['emp_name'])); ?>', 
                                                '<?php echo htmlspecialchars($leave['leave_type']); ?>', 
                                                '<?php echo date('d M Y', strtotime($leave['start_date'])) . ' - ' . date('d M Y', strtotime($leave['end_date'])); ?>', 
                                                '<?php echo htmlspecialchars(addslashes($leave['reason'])); ?>', 
                                                '<?php echo $leave['total_days']; ?>', 
                                                '<?php echo $leave['current_month_leaves']; ?>',
                                                '<?php echo $leave['global_status']; ?>',
                                                '<?php echo htmlspecialchars(addslashes($leave['approved_by'] ?? '')); ?>',
                                                '<?php echo $leave['tl_status']; ?>',
                                                '<?php echo $leave['mgr_status']; ?>',
                                                '<?php echo $leave['hr_status']; ?>'
                                            )" title="View Details"><i data-lucide="eye" style="width:16px;"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 40px;">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;"></i>
                                    No leave requests found for your attention.
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
                <h3>Leave Request Details</h3>
                <i data-lucide="x" style="cursor:pointer; color:#94a3b8;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                
                <div class="approval-track" id="approvalTrackBox">
                    <h6>Approval Chain Status</h6>
                    <div class="track-item" id="trackTL">
                        <span>Team Lead</span>
                        <span id="mTlStatus" class="status-badge">--</span>
                    </div>
                    <div class="track-item" id="trackMgr">
                        <span>Manager</span>
                        <span id="mMgrStatus" class="status-badge">--</span>
                    </div>
                    <div class="track-item" id="trackHR">
                        <span>HR Admin</span>
                        <span id="mHrStatus" class="status-badge">--</span>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Employee Name</label>
                        <p id="mName">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Leave Type</label>
                        <p id="mType">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Duration</label>
                        <p id="mDate">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Requesting Days</label>
                        <p id="mDays">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Approved Leaves (This Month)</label>
                        <p id="mMonthLeaves" style="color:#2563eb; font-weight: 700;">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Final Global Status</label>
                        <p id="mStatus" style="font-weight: 700; color: #0f172a;">--</p>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom:8px;">
                    <label>Employee Reason</label>
                </div>
                <div class="reason-box">
                    <p id="mReason">--</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Close Window</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Responsive Sidebar logic 
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

        // Filter functionality
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const type = document.getElementById('typeFilter').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            
            const rows = document.querySelectorAll('#approvalTable tbody tr');

            rows.forEach(row => {
                if(row.cells.length < 2) return; 
                const name = row.querySelector('.emp-name').innerText.toLowerCase();
                const lType = row.querySelector('td:nth-child(2)').innerText.toLowerCase();
                
                // Get the text from the status badge container
                const statusContainer = row.querySelector('.status-badge-container');
                const lStatus = statusContainer ? statusContainer.innerText.toLowerCase() : '';

                const matchesSearch = name.includes(search);
                const matchesType = type === '' || lType.includes(type);
                const matchesStatus = status === '' || lStatus.includes(status);

                if (matchesSearch && matchesType && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Modal Functionality
        const modal = document.getElementById('approvalModal');

        // Helper to set badge colors dynamically
        function setBadge(elementId, status) {
            const el = document.getElementById(elementId);
            el.innerText = status;
            el.className = 'status-badge status-' + status;
        }

        function viewDetails(name, type, date, reason, days, monthLeaves, status, approvedBy, tlStat, mgrStat, hrStat) {
            document.getElementById('mName').innerText = name;
            document.getElementById('mType').innerText = type;
            document.getElementById('mDate').innerText = date;
            document.getElementById('mReason').innerText = reason;
            document.getElementById('mDays').innerText = days + " Day(s)";
            document.getElementById('mMonthLeaves').innerText = monthLeaves + " Day(s)";
            
            // Set Approval Track Badges
            setBadge('mTlStatus', tlStat);
            setBadge('mMgrStatus', mgrStat);
            setBadge('mHrStatus', hrStat);

            // Hide pending tracks
            document.getElementById('trackTL').style.display = (tlStat === 'Pending') ? 'none' : 'flex';
            document.getElementById('trackMgr').style.display = (mgrStat === 'Pending') ? 'none' : 'flex';
            document.getElementById('trackHR').style.display = (hrStat === 'Pending') ? 'none' : 'flex';

            // Hide the entire box if no one has approved/rejected yet
            const approvalBox = document.getElementById('approvalTrackBox');
            if (tlStat === 'Pending' && mgrStat === 'Pending' && hrStat === 'Pending') {
                approvalBox.style.display = 'none';
            } else {
                approvalBox.style.display = 'block';
            }

            let statusText = status;
            if(status !== 'Pending' && approvedBy !== '') {
                statusText += " (by " + approvedBy + ")";
            }
            document.getElementById('mStatus').innerText = statusText;
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Leave Update Action
        function updateLeave(leaveId, newStatus) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to mark this leave as " + newStatus,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: newStatus === 'Approved' ? '#10b981' : '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, ' + newStatus + ' it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `leave_id=${leaveId}&status=${newStatus}`
                    })
                    .then(response => response.text())
                    .then(data => {
                        if(data.trim() === 'success') {
                            Swal.fire('Success!', 'The leave has been ' + newStatus + '.', 'success')
                            .then(() => { location.reload(); }); 
                        } else {
                            Swal.fire('Error', 'Something went wrong processing the request.', 'error');
                        }
                    });
                }
            });
        }

        modal.addEventListener('click', (e) => {
            if(e.target === modal) closeModal();
        });
    </script>
</body>
</html>