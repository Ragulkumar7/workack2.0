<?php
// leave_approvals.php - Enterprise Leave Management

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

// Role Classification for Strict Hierarchy Enforcement
$is_tl = in_array($user_role, ['Team Lead', 'TL']);
$is_mgr = in_array($user_role, ['Manager', 'Project Manager', 'General Manager', 'Sales Manager']);
$is_it_admin = ($user_role === 'IT Admin');
$is_hr_admin = in_array($user_role, ['HR', 'HR Executive', 'Admin', 'System Admin', 'CFO', 'CEO']);

// =========================================================================
// 2. PROCESS AJAX LEAVE ACTIONS (Sequential Multi-Tier Logic)
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

    $tl_stat = $curr['tl_status'] ?? 'Pending';
    $mgr_stat = $curr['manager_status'] ?? 'Pending';
    $hr_stat = $curr['hr_status'] ?? 'Pending';
    $approved_by = $user_role;  

    // Apply the approval to the CORRECT tier based on whoever clicked it
    if ($is_tl) { $tl_stat = $new_status; }
    elseif ($is_mgr || $is_it_admin) { $mgr_stat = $new_status; } 
    elseif ($is_hr_admin) { $hr_stat = $new_status; } 

    // STRICT 3-TIER DB EVALUATION
    if ($tl_stat === 'Rejected' || $mgr_stat === 'Rejected' || $hr_stat === 'Rejected') {
        $global_stat = 'Rejected';
    } elseif ($tl_stat === 'Approved' && $mgr_stat === 'Approved' && $hr_stat === 'Approved') {
        $global_stat = 'Approved';
    } else {
        $global_stat = 'Pending';
    }

    $update_query = "UPDATE leave_requests SET tl_status=?, manager_status=?, hr_status=?, status=?, approved_by=? WHERE id=?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssssi", $tl_stat, $mgr_stat, $hr_stat, $global_stat, $approved_by, $leave_id);

    if ($update_stmt->execute()) { echo "success"; } else { echo "error"; }
    
    $update_stmt->close();
    exit(); 
}

// =========================================================================
// 3. FETCH DATA & LIVE LEAVE BALANCES FOR UI DISPLAY
// =========================================================================
// 🚀 ENTERPRISE UPGRADE: Fetches allocated quota and calculates yearly consumed balance dynamically
$base_select = "SELECT lr.*, 
                COALESCE(ep.full_name, u.username, 'Unknown Employee') as emp_name, 
                COALESCE(ep.designation, u.role, 'Employee') as emp_role,
                u.role as actual_role,
                ep.profile_img,
                COALESCE(eo.total_leaves, 12) as allocated_leaves,
                (SELECT COALESCE(SUM(total_days), 0) FROM leave_requests 
                 WHERE user_id = lr.user_id 
                   AND status = 'Approved' 
                   AND YEAR(start_date) = YEAR(CURRENT_DATE())
                ) as yearly_taken_leaves,
                (SELECT COALESCE(SUM(total_days), 0) FROM leave_requests 
                 WHERE user_id = lr.user_id 
                   AND status = 'Approved' 
                   AND MONTH(start_date) = MONTH(CURRENT_DATE()) 
                   AND YEAR(start_date) = YEAR(CURRENT_DATE())
                ) as current_month_leaves
              FROM leave_requests lr 
              JOIN users u ON lr.user_id = u.id 
              LEFT JOIN employee_profiles ep ON u.id = ep.user_id
              LEFT JOIN employee_onboarding eo ON u.employee_id = eo.emp_id_code";

$query = "";
if ($is_tl) {
    $query = "$base_select WHERE (ep.reporting_to = ? OR lr.tl_id = ?) AND lr.user_id != ? AND u.role NOT IN ('CFO', 'Accounts', 'Accountant', 'IT Executive', 'IT Admin', 'HR Executive', 'Sales Executive') ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
} elseif ($is_mgr) {
    $query = "$base_select WHERE (ep.manager_id = ? OR ep.reporting_to = ? OR lr.manager_id = ?) AND lr.user_id != ? AND u.role NOT IN ('CFO', 'Accounts', 'Accountant', 'IT Executive', 'IT Admin', 'HR Executive') ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
} elseif ($is_it_admin) {
    $query = "$base_select WHERE u.role = 'IT Executive' AND lr.user_id != ? ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
} else {
    $query = "$base_select WHERE lr.user_id != ? ORDER BY lr.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$leave_requests = [];
$pending_count = 0; 
$approved_count = 0; 
$rejected_count = 0;

while ($row = $result->fetch_assoc()) {
    $tl_stat = $row['tl_status'] ?? 'Pending';
    $mgr_stat = $row['manager_status'] ?? 'Pending';
    $hr_stat = $row['hr_status'] ?? 'Pending';
    $db_global = $row['status'] ?: 'Pending';

    // Balance Math
    $allocated_leaves = floatval($row['allocated_leaves']);
    $yearly_taken = floatval($row['yearly_taken_leaves']);
    $leave_balance = $allocated_leaves - $yearly_taken;

    // USE STRICT ACTUAL ROLE FOR LOGIC EVALUATION
    $req_role = trim($row['actual_role']);
    $display_role = trim($row['emp_role']);
    
    if (in_array($req_role, ['Team Lead', 'TL'])) { 
        $tl_stat = 'Approved'; 
    }
    if (in_array($req_role, ['Manager', 'Project Manager', 'General Manager', 'Sales Manager'])) { 
        $tl_stat = 'Approved'; 
        $mgr_stat = 'Approved'; 
    }
    if (in_array($req_role, ['IT Executive', 'Sales Executive'])) {
        $tl_stat = 'Approved'; 
    }
    if (in_array($req_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])) {
        $tl_stat = 'Approved';
        $mgr_stat = 'Approved'; 
    }
    if (in_array($req_role, ['Admin', 'System Admin', 'CEO'])) {
        $tl_stat = 'Approved'; 
        $mgr_stat = 'Approved'; 
        $hr_stat = 'Approved'; 
        $db_global = 'Approved';
    }

    // Strict recalculation to ensure accuracy
    if ($db_global === 'Approved' || ($tl_stat === 'Approved' && $mgr_stat === 'Approved' && $hr_stat === 'Approved')) {
        $real_global_status = 'Approved';
    } elseif ($tl_stat === 'Rejected' || $mgr_stat === 'Rejected' || $hr_stat === 'Rejected' || $db_global === 'Rejected') {
        $real_global_status = 'Rejected';
    } else {
        $real_global_status = 'Pending';
    }

    $viewer_status = 'Pending';
    if ($is_tl) { $viewer_status = $tl_stat; } 
    elseif ($is_mgr || $is_it_admin) { $viewer_status = $mgr_stat; } 
    elseif ($is_hr_admin) { $viewer_status = $hr_stat; }

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
            } elseif ($req_role === 'Sales Executive') {
                if ($mgr_stat === 'Pending') { $waiting_on_other = 'Awaiting Sales Manager'; }
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

    $awaiting = '';
    if ($real_global_status === 'Pending') {
        if ($req_role === 'IT Executive') {
            if ($mgr_stat === 'Pending') { $awaiting = 'Awaiting IT Admin'; }
            elseif ($hr_stat === 'Pending') { $awaiting = 'Awaiting HR Approval'; }
        } elseif ($req_role === 'Sales Executive') {
            if ($mgr_stat === 'Pending') { $awaiting = 'Awaiting Sales Manager'; }
            elseif ($hr_stat === 'Pending') { $awaiting = 'Awaiting HR Approval'; }
        } elseif (in_array($req_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])) {
            if ($hr_stat === 'Pending') { $awaiting = 'Awaiting HR Approval'; }
        } else {
            if ($tl_stat === 'Pending') { $awaiting = 'Awaiting Team Lead'; }
            elseif ($mgr_stat === 'Pending') { $awaiting = 'Awaiting Manager'; }
            elseif ($hr_stat === 'Pending') { $awaiting = 'Awaiting HR Approval'; }
        }
    } elseif ($real_global_status === 'Rejected') {
        $awaiting = 'Denied by ' . htmlspecialchars($row['approved_by'] ?? 'Management');
    }

    // Calculate Colors for Approval Chain UI Nodes
    $tl_node = ($tl_stat === 'Approved') ? 'node-approved' : (($tl_stat === 'Rejected') ? 'node-rejected' : 'node-pending');
    $mgr_node = ($mgr_stat === 'Approved') ? 'node-approved' : (($mgr_stat === 'Rejected') ? 'node-rejected' : 'node-pending');
    $hr_node = ($hr_stat === 'Approved') ? 'node-approved' : (($hr_stat === 'Rejected') ? 'node-rejected' : 'node-pending');

    if ($req_role === 'IT Executive') { $mgr_bubble_letter = 'A'; $mgr_tooltip_title = 'IT Admin'; } 
    elseif ($req_role === 'Sales Executive') { $mgr_bubble_letter = 'M'; $mgr_tooltip_title = 'Sales Mgr'; } 
    else { $mgr_bubble_letter = 'M'; $mgr_tooltip_title = 'Mgr'; }

    if ($real_global_status === 'Pending') $pending_count++;
    if ($real_global_status === 'Approved') $approved_count++;
    if ($real_global_status === 'Rejected') $rejected_count++;

    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['emp_name'])."&background=random";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = '../assets/profiles/' . $imgSource; 
    }

    $leave_requests[] = [
        'id' => $row['id'],
        'emp_name' => $row['emp_name'],
        'emp_role' => $display_role,
        'actual_role' => $req_role,
        'avatar' => $imgSource,
        'leave_type' => $row['leave_type'],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'total_days' => $row['total_days'],
        'created_at' => $row['created_at'],
        'reason' => $row['reason'],
        'current_month_leaves' => $row['current_month_leaves'],
        'yearly_taken' => $yearly_taken,
        'allocated_leaves' => $allocated_leaves,
        'leave_balance' => $leave_balance,
        'global_status' => $real_global_status, 
        'can_action' => $can_action,
        'waiting_on_other' => $waiting_on_other,
        'awaiting_text' => $awaiting,
        'tl_status' => $tl_stat,
        'mgr_status' => $mgr_stat,
        'hr_status' => $hr_stat,
        'tl_node' => $tl_node,
        'mgr_node' => $mgr_node,
        'hr_node' => $hr_node,
        'mgr_bubble_letter' => $mgr_bubble_letter,
        'mgr_tooltip_title' => $mgr_tooltip_title,
        'approved_by' => $row['approved_by']
    ];
}
$stmt->close();

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
        :root { --primary: #f97316; --primary-hover: #ea580c; --bg-body: #f8f9fa; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --white: #ffffff; --sidebar-width: 95px;}
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; color: var(--text-main); overflow-x: hidden;}
        
        /* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT
           ========================================================== */
        .main-content, #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box; padding: 30px; min-height: 100vh;
        }

        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 315px; width: calc(100% - 315px);
        }

        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important; width: 100% !important;
                padding: 80px 15px 30px !important; 
            }
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important; width: 100% !important;
            }
        }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 15px; flex-wrap: wrap; }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #0f172a; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .stat-info h4 { font-size: 13px; color: var(--text-muted); margin: 0 0 5px 0; font-weight: 500; text-transform: uppercase; }
        .stat-info h2 { font-size: 28px; font-weight: 700; margin: 0; color: var(--text-main); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .card-pending .stat-icon { background: #fff7ed; color: #f97316; } .card-approved .stat-icon { background: #f0fdf4; color: #16a34a; } .card-rejected .stat-icon { background: #fef2f2; color: #dc2626; } .card-total .stat-icon { background: #eff6ff; color: #2563eb; }
        
        .list-section { background: white; border-radius: 12px; border: 1px solid var(--border); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { flex: 2; min-width: 250px; display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; }
        .search-box input { border: none; outline: none; width: 100%; font-size: 13px; margin-left: 8px; }
        .filter-select { flex: 1; min-width: 150px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; }
        
        .table-responsive { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 1050px; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; font-size: 12px; color: #475569; padding: 14px 20px; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; white-space: nowrap; }
        tr:hover { background-color: #fcfcfc; }
        
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .emp-info { display: flex; flex-direction: column; gap: 2px; max-width: 250px; white-space: normal;} 
        .emp-name { font-weight: 600; color: #0f172a; } 
        .emp-dept { font-size: 11px; color: #64748b; }
        
        /* Dynamic Balance Badge Styles */
        .balance-badge { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; width: fit-content; margin-top: 4px; border: 1px solid;}
        .bal-healthy { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .bal-warning { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        
        /* APPROVAL CHAIN UI */
        .chain-wrapper { display: inline-flex; align-items: center; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; }
        .chain-node { width: 24px; height: 24px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1); position: relative; z-index: 2; text-shadow: 0px 1px 1px rgba(0,0,0,0.2);}
        .chain-line { width: 16px; height: 2px; background-color: #cbd5e1; margin: 0 -2px; z-index: 1; }
        .node-approved { background-color: #10b981; }
        .node-pending { background-color: #f59e0b; }
        .node-rejected { background-color: #ef4444; }

        /* STATUS BADGE UI */
        .status-badge-container { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
        .status-badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; width: fit-content;}
        .status-Approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;}
        .status-Pending { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;}
        .status-Rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;}
        .awaiting-text { font-size: 10px; color: #64748b; font-weight: 600; margin-left: 2px;}

        .leave-type { font-weight: 600; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: #334155; font-size: 12px; }
        
        .action-container { display: flex; gap: 8px; align-items: center; }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border); background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; color: var(--text-muted); }
        .btn-icon:hover { background: #f8fafc; color: var(--primary); } 
        .btn-approve:hover { background: #dcfce7; color: #166534; border-color: #bbf7d0; } 
        .btn-reject:hover { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        
        /* Modal Transitions */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; animation: fadeUp 0.2s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .modal-box { background: white; width: 600px; max-width: 95%; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-radius: 16px 16px 0 0; flex-shrink: 0; }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;}
        .modal-body { padding: 24px; overflow-y: auto; } 
        
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .detail-item { background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9;}
        .detail-item label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; } 
        .detail-item p { margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; }
        
        .reason-box { background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; } 
        .reason-box p { font-size: 13px; color: #334155; line-height: 1.6; margin: 0; font-weight: 500;}
        
        .modal-footer { padding: 16px 24px; background: #fff; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; border-radius: 0 0 16px 16px;}
        .btn { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s;} 
        .btn-outline { background: white; border: 1px solid var(--border); color: #334155; }
        .btn-outline:hover { background: #f1f5f9; }

        .approval-track { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);}
        .approval-track h6 { margin: 0 0 12px 0; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;}
        .track-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed var(--border); }
        .track-item:last-child { border-bottom: none; padding-bottom: 0; }
        .track-item span { font-size: 13px; font-weight: 600; color: var(--text-main); }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <main class="main-content" id="mainContent">
        <?php include $headerPath; ?>

        <div class="page-header mt-4">
            <div>
                <h1>Leave Approvals <span style="color: #64748b; font-size: 16px; font-weight: 600;">(<?php echo htmlspecialchars($user_role); ?>)</span></h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span> Leaves <span>/</span> <span style="font-weight:600; color:#0f172a;">Approvals</span>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-pending">
                <div class="stat-info">
                    <h4>Global Pending</h4>
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
                    <input type="text" id="searchInput" placeholder="Search employee name or leave type..." onkeyup="filterTable()">
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
                            <th>Employee Details</th>
                            <th>Leave Type</th>
                            <th>Duration</th>
                            <th>Req. Days</th>
                            <th>Approval Chain</th>
                            <th>Global Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($leave_requests) > 0): ?>
                            <?php foreach($leave_requests as $leave): 
                                $bal_class = ($leave['leave_balance'] < $leave['total_days']) ? 'bal-warning' : 'bal-healthy';
                                $bal_icon = ($leave['leave_balance'] < $leave['total_days']) ? 'alert-triangle' : 'wallet';
                            ?>
                                <tr>
                                    <td>
                                        <div class="emp-profile">
                                            <img src="<?php echo $leave['avatar']; ?>" class="emp-avatar" alt="User">
                                            <div class="emp-info">
                                                <span class="emp-name"><?php echo htmlspecialchars($leave['emp_name']); ?></span>
                                                <span class="emp-dept"><?php echo htmlspecialchars($leave['emp_role']); ?></span>
                                                
                                                <span class="balance-badge <?php echo $bal_class; ?>" title="Yearly Balance">
                                                    <i data-lucide="<?php echo $bal_icon; ?>" style="width:12px; height:12px;"></i> 
                                                    Bal: <?php echo $leave['leave_balance']; ?> / <?php echo $leave['allocated_leaves']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="leave-type"><?php echo htmlspecialchars($leave['leave_type']); ?></span></td>
                                    <td style="color: #334155; font-weight: 500; font-size: 12px;"><?php echo date('d M Y', strtotime($leave['start_date'])) . '<br><span style="color:#94a3b8; font-size:10px;">to</span><br>' . date('d M Y', strtotime($leave['end_date'])); ?></td>
                                    <td>
                                        <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-weight: 700; border: 1px solid #e2e8f0; color: #0f172a;">
                                            <?php echo str_pad($leave['total_days'], 2, '0', STR_PAD_LEFT); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php if (in_array($leave['actual_role'], ['IT Executive', 'Sales Executive'])): ?>
                                            <div class="chain-wrapper" title="<?php echo $leave['mgr_tooltip_title']; ?>: <?php echo $leave['mgr_status']; ?> | HR: <?php echo $leave['hr_status']; ?>">
                                                <div class="chain-node <?php echo $leave['mgr_node']; ?>"><?php echo $leave['mgr_bubble_letter']; ?></div>
                                                <div class="chain-line"></div>
                                                <div class="chain-node <?php echo $leave['hr_node']; ?>">H</div>
                                            </div>
                                        <?php elseif (in_array($leave['actual_role'], ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])): ?>
                                            <div class="chain-wrapper" title="HR: <?php echo $leave['hr_status']; ?>">
                                                <div class="chain-node <?php echo $leave['hr_node']; ?>">H</div>
                                            </div>
                                        <?php else: ?>
                                            <div class="chain-wrapper" title="TL: <?php echo $leave['tl_status']; ?> | Mgr: <?php echo $leave['mgr_status']; ?> | HR: <?php echo $leave['hr_status']; ?>">
                                                <div class="chain-node <?php echo $leave['tl_node']; ?>">T</div>
                                                <div class="chain-line"></div>
                                                <div class="chain-node <?php echo $leave['mgr_node']; ?>">M</div>
                                                <div class="chain-line"></div>
                                                <div class="chain-node <?php echo $leave['hr_node']; ?>">H</div>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php 
                                            $badgeClass = "status-" . $leave['global_status'];
                                            $icon = ($leave['global_status'] == 'Approved') ? 'check' : (($leave['global_status'] == 'Rejected') ? 'x' : 'clock');
                                        ?>
                                        <div class="status-badge-container">
                                            <span class="status-badge <?php echo $badgeClass; ?>">
                                                <i data-lucide="<?php echo $icon; ?>" style="width:12px;"></i> <?php echo $leave['global_status']; ?>
                                            </span>
                                            
                                            <?php if($leave['awaiting_text']): ?>
                                                <span class="awaiting-text"><?php echo $leave['awaiting_text']; ?></span>
                                            <?php elseif($leave['global_status'] === 'Approved'): ?>
                                                <span class="awaiting-text" style="color: #16a34a;"><i data-lucide="shield-check" style="width:10px; display:inline;"></i> Fully Verified</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="action-container" style="justify-content: flex-end;">
                                            <?php if($leave['can_action']): ?>
                                                <button class="btn-icon btn-approve" onclick="updateLeave(<?php echo $leave['id']; ?>, 'Approved')" title="Approve Request"><i data-lucide="check" style="width:16px;"></i></button>
                                                <button class="btn-icon btn-reject" onclick="updateLeave(<?php echo $leave['id']; ?>, 'Rejected')" title="Reject Request"><i data-lucide="x" style="width:16px;"></i></button>
                                            <?php elseif($leave['waiting_on_other']): ?>
                                                <span style="font-size: 10px; font-weight: 700; color: #94a3b8; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; border: 1px dashed #cbd5e1; text-transform: uppercase;">
                                                    <i data-lucide="lock" style="width:12px; display:inline; margin-bottom:-2px;"></i> <?php echo $leave['waiting_on_other']; ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <button class="btn-icon" onclick="viewDetails(
                                                '<?php echo htmlspecialchars(addslashes($leave['emp_name'])); ?>', 
                                                '<?php echo htmlspecialchars($leave['leave_type']); ?>', 
                                                '<?php echo date('d M Y', strtotime($leave['start_date'])) . ' - ' . date('d M Y', strtotime($leave['end_date'])); ?>', 
                                                '<?php echo htmlspecialchars(addslashes($leave['reason'])); ?>', 
                                                '<?php echo $leave['total_days']; ?>', 
                                                '<?php echo $leave['leave_balance']; ?>',
                                                '<?php echo $leave['allocated_leaves']; ?>',
                                                '<?php echo $leave['global_status']; ?>',
                                                '<?php echo htmlspecialchars(addslashes($leave['approved_by'] ?? '')); ?>',
                                                '<?php echo $leave['tl_status']; ?>',
                                                '<?php echo $leave['mgr_status']; ?>',
                                                '<?php echo $leave['hr_status']; ?>',
                                                '<?php echo htmlspecialchars(addslashes($leave['actual_role'])); ?>'
                                            )" title="View Audit Details"><i data-lucide="eye" style="width:16px;"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 60px 20px;">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;"></i>
                                    <h3 style="margin: 0; color: #334155; font-size: 16px; font-weight: 700;">No Leave Requests</h3>
                                    <p style="margin: 5px 0 0 0; font-size: 13px;">There are no pending requests requiring your attention.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="approvalModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Leave Request Audit</h3>
                <i data-lucide="x" style="cursor:pointer; color:#94a3b8; width: 20px;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                
                <div class="approval-track" id="approvalTrackBox">
                    <h6>Multi-Tier Approval Chain</h6>
                    <div class="track-item" id="trackTL">
                        <span>1. Team Lead</span>
                        <span id="mTlStatus" class="status-badge">--</span>
                    </div>
                    <div class="track-item" id="trackMgr">
                        <span>2. Manager</span>
                        <span id="mMgrStatus" class="status-badge">--</span>
                    </div>
                    <div class="track-item" id="trackHR">
                        <span>3. HR Admin</span>
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
                    
                    <div class="detail-item bg-blue-50 border-blue-100">
                        <label style="color: #1d4ed8;"><i data-lucide="wallet" style="width:12px; display:inline;"></i> Yearly Leave Balance</label>
                        <p id="mBalance" style="color:#1e40af; font-weight: 800; font-size: 18px;">--</p>
                    </div>
                    
                    <div class="detail-item bg-slate-50">
                        <label>Final Global Status</label>
                        <p id="mStatus" style="font-weight: 800; color: #0f172a;">--</p>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom:8px; border:none; padding:0; background:transparent;">
                    <label>Employee Justification</label>
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

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const type = document.getElementById('typeFilter').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            
            const rows = document.querySelectorAll('#approvalTable tbody tr');

            rows.forEach(row => {
                if(row.cells.length < 2) return; 
                const name = row.querySelector('.emp-name').innerText.toLowerCase();
                const lType = row.querySelector('td:nth-child(2)').innerText.toLowerCase();
                
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

        const modal = document.getElementById('approvalModal');

        function setBadge(elementId, status) {
            const el = document.getElementById(elementId);
            el.innerText = status;
            el.className = 'status-badge status-' + status;
        }

        // 🚀 UPDATED MODAL POPULATOR
        function viewDetails(name, type, date, reason, days, balance, allocated, status, approvedBy, tlStat, mgrStat, hrStat, empRole) {
            document.getElementById('mName').innerText = name;
            document.getElementById('mType').innerText = type;
            document.getElementById('mDate').innerText = date;
            document.getElementById('mReason').innerText = reason || 'No reason provided by employee.';
            document.getElementById('mDays').innerText = days + " Day(s)";
            
            // Smart Balance Coloring in Modal
            const balEl = document.getElementById('mBalance');
            balEl.innerText = balance + " / " + allocated + " Available";
            if(parseFloat(balance) < parseFloat(days)) {
                balEl.style.color = '#dc2626'; // Red if requesting more than they have
            } else {
                balEl.style.color = '#1e40af'; // Standard Blue
            }
            
            setBadge('mTlStatus', tlStat);
            setBadge('mMgrStatus', mgrStat);
            setBadge('mHrStatus', hrStat);

            // DYNAMIC UI MODAL TIER VISIBILITY
            if (empRole === 'IT Executive') {
                document.getElementById('trackTL').style.display = 'none';
                document.getElementById('trackMgr').style.display = 'flex';
                document.getElementById('trackMgr').querySelector('span:first-child').innerText = '1. IT Admin Approval';
                document.getElementById('trackHR').querySelector('span:first-child').innerText = '2. HR Admin';
            } else if (empRole === 'Sales Executive') {
                document.getElementById('trackTL').style.display = 'none';
                document.getElementById('trackMgr').style.display = 'flex';
                document.getElementById('trackMgr').querySelector('span:first-child').innerText = '1. Sales Manager';
                document.getElementById('trackHR').querySelector('span:first-child').innerText = '2. HR Admin';
            } else if (['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'].includes(empRole)) {
                document.getElementById('trackTL').style.display = 'none';
                document.getElementById('trackMgr').style.display = 'none';
                document.getElementById('trackHR').querySelector('span:first-child').innerText = '1. HR Admin';
            } else {
                document.getElementById('trackTL').style.display = 'flex';
                document.getElementById('trackMgr').style.display = 'flex';
                document.getElementById('trackTL').querySelector('span:first-child').innerText = '1. Team Lead';
                document.getElementById('trackMgr').querySelector('span:first-child').innerText = '2. Manager';
                document.getElementById('trackHR').querySelector('span:first-child').innerText = '3. HR Admin';
            }

            let statusText = status;
            if(status === 'Pending') {
                statusText = "Pending (Awaiting Full Approval)";
            } else if(status === 'Approved') {
                statusText = "Approved (Verified by All Tiers)";
            } else {
                statusText = "Rejected (Denied by " + (approvedBy || 'Management') + ")";
            }
            
            document.getElementById('mStatus').innerText = statusText;
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function updateLeave(leaveId, newStatus) {
            Swal.fire({
                title: 'Confirm ' + newStatus,
                text: "Are you sure you want to mark your step of this leave as " + newStatus + "?",
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
                            Swal.fire('Success!', 'Your approval status has been updated.', 'success')
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