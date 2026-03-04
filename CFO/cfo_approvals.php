<?php
// cfo_approvals.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE CONNECTION
 $projectRoot = __DIR__; 
 $dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// =========================================================================
// AUTO-FIX SCHEMA: Ensure reject_reason AND approver_designation exist
// =========================================================================
 $tables_to_check = ['invoices', 'purchase_orders', 'employee_salary'];
foreach($tables_to_check as $tbl) {
    // Add Reject Reason
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$tbl` LIKE 'reject_reason'");
    if($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE `$tbl` ADD COLUMN reject_reason TEXT DEFAULT NULL");
    }
    // Add Approver Designation (Sync with Invoice Dispatch)
    if ($tbl === 'invoices') {
        $check_desig = mysqli_query($conn, "SHOW COLUMNS FROM `$tbl` LIKE 'approver_designation'");
        if($check_desig && mysqli_num_rows($check_desig) == 0) {
            mysqli_query($conn, "ALTER TABLE `$tbl` ADD COLUMN approver_designation VARCHAR(100) DEFAULT 'CFO' AFTER `status`");
        }
    }
}

// Company details for Print Templates
 $company_details = [
    'name' => 'Neoera infotech',
    'address' => '9/96 h, post, village nagar, Kurumbapalayam SSKulam, coimbatore, Tamil Nadu 641107',
    'phone' => '+91 866 802 5451',
    'email' => 'Contact@neoerainfotech.com',
    'website' => 'www.neoerainfotech.com',
    'logo' => '../assets/neoera.png' 
];

// =========================================================================
// ENTERPRISE SECURITY: Role-Based Access Control (RBAC)
// =========================================================================
 $user_role = $_SESSION['role'] ?? ''; 
 $current_user_id = $_SESSION['user_id'] ?? 0;
 $can_approve = in_array($user_role, ['CFO', 'Admin', 'Super Admin', 'Management']);

// =========================================================================
// 2. BACKEND ACTION HANDLER (AJAX)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if(ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    // Security Block
    if (!$can_approve && strpos($_POST['action'], 'fetch') === false) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only authorized roles can perform approvals.']);
        exit;
    }
    
    // --- APPROVE / REJECT LOGIC (Invoices & POs) ---
    if ($_POST['action'] === 'Approve' || $_POST['action'] === 'Reject') {
        $id = intval($_POST['id']);
        $type = $_POST['type'] ?? '';
        $newStatus = ($_POST['action'] === 'Approve') ? 'Approved' : 'Rejected';
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');

        if ($type === 'Purchase Order') {
            $stmt = $conn->prepare("UPDATE purchase_orders SET approval_status = ?, reject_reason = ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE invoices SET status = ?, reject_reason = ? WHERE id = ?");
        }

        $stmt->bind_param("ssi", $newStatus, $reason, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // --- APPROVE / REJECT LOGIC (Salaries) ---
    if ($_POST['action'] === 'ApproveSalary' || $_POST['action'] === 'RejectSalary') {
        $id = intval($_POST['id']);
        $newStatus = ($_POST['action'] === 'ApproveSalary') ? 'Approved' : 'Rejected';
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
        $approved_at = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("UPDATE employee_salary SET approval_status = ?, reject_reason = ?, approved_by = ?, approved_at = ? WHERE id = ? AND is_deleted = 0");
        $stmt->bind_param("ssisi", $newStatus, $reason, $current_user_id, $approved_at, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // --- FETCH DATA FOR VIEW MODALS & PRINTING ---
    if ($_POST['action'] === 'fetch_invoice_details') {
        $inv_id = intval($_POST['id']);
        $inv_res = mysqli_query($conn, "SELECT i.*, c.client_name, c.company_name, c.mobile_number, c.gst_number as c_gst 
                                        FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = $inv_id");
        $invoice = mysqli_fetch_assoc($inv_res);
        
        $company_bank = null;
        if(!empty($invoice['company_bank_id'])) {
            $check_cb = mysqli_query($conn, "SHOW TABLES LIKE 'company_banks'");
            if(mysqli_num_rows($check_cb) > 0) {
                $cb_res = mysqli_query($conn, "SELECT * FROM company_banks WHERE id = " . $invoice['company_bank_id']);
                $company_bank = mysqli_fetch_assoc($cb_res);
            }
        }

        $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
        $items = [];
        while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
        
        echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items, 'company_bank' => $company_bank]);
        exit;
    }

    if ($_POST['action'] === 'fetch_po_details') {
        $po_id = intval($_POST['id']);
        $po_res = mysqli_query($conn, "SELECT * FROM purchase_orders WHERE id = $po_id");
        if(!$po_res) throw new Exception(mysqli_error($conn));
        $po = mysqli_fetch_assoc($po_res);
        
        $items = [];
        if($po) {
            $check_new = mysqli_query($conn, "SHOW TABLES LIKE 'po_line_items'");
            if(mysqli_num_rows($check_new) > 0) {
                $items_res = @mysqli_query($conn, "SELECT * FROM po_line_items WHERE po_number = '".$po['po_number']."'");
                if($items_res) { while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; } }
            } else {
                $items_res = @mysqli_query($conn, "SELECT * FROM purchase_order_items WHERE po_number = '".$po['po_number']."'");
                if($items_res) { while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; } }
            }
        }
        
        echo json_encode(['status' => 'success', 'po' => $po, 'items' => $items]);
        exit;
    }

    if ($_POST['action'] === 'fetch_salary_details') {
        $sal_id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT s.*, DATE_FORMAT(s.salary_month, '%b %Y') as month_fmt, CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as emp_name, e.emp_id_code as emp_code FROM employee_salary s JOIN employee_onboarding e ON s.user_id = e.id WHERE s.id = ? AND s.is_deleted = 0");
        $stmt->bind_param("i", $sal_id);
        $stmt->execute();
        $salary = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if($salary) {
            echo json_encode(['status' => 'success', 'salary' => $salary]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Salary record not found or was deleted.']);
        }
        exit;
    }
}

// =========================================================================
// 3. FETCH REAL DATA FROM DB
// =========================================================================

// Pending Salaries
 $salary_requests = [];
 $resSalaries = mysqli_query($conn, "SELECT s.*, DATE_FORMAT(s.salary_month, '%b %Y') as month_fmt, CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as emp_name, e.emp_id_code as emp_code FROM employee_salary s JOIN employee_onboarding e ON s.user_id = e.id WHERE s.approval_status = 'Pending' AND s.is_deleted = 0 ORDER BY s.created_at DESC");
 $valSalaries = 0;
if ($resSalaries) {
    while($row = mysqli_fetch_assoc($resSalaries)) {
        $salary_requests[] = $row;
        $valSalaries += (float)$row['net_salary'];
    }
}

// Pending General (POs & Invoices)
 $pendingPosCount = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT COUNT(*) as cnt FROM purchase_orders WHERE approval_status = 'Pending'"))['cnt'] ?? 0;
 $pendingInvsCount = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE status = 'Pending Approval'"))['cnt'] ?? 0;
 $valPOs = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM purchase_orders WHERE approval_status = 'Pending'"))['val'] ?? 0;
 $valInvs = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE status = 'Pending Approval'"))['val'] ?? 0;

 $summary = [
    'pending_pos' => $pendingPosCount,
    'pending_invs' => $pendingInvsCount,
    'pending_salaries' => count($salary_requests),
    'total_pending_value' => $valPOs + $valInvs + $valSalaries
];

 $pending_requests = [];
 $resPOs = @mysqli_query($conn, "SELECT * FROM purchase_orders WHERE approval_status = 'Pending' ORDER BY created_at DESC");
if($resPOs) {
    while($row = mysqli_fetch_assoc($resPOs)) {
        $pending_requests[] = ['id_db' => $row['id'], 'id' => $row['po_number'], 'type' => 'Purchase Order', 'vendor_client' => $row['vendor_name'], 'amount' => $row['grand_total'], 'date' => date('d-M-Y', strtotime($row['po_date'])), 'assigned_to' => 'N/A'];
    }
}

// UPDATED: Fetch assigned_to (approver_designation) for invoices
 $resInvs = @mysqli_query($conn, "SELECT i.*, c.client_name, i.approver_designation FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.status = 'Pending Approval' ORDER BY i.created_at DESC");
if($resInvs) {
    while($row = mysqli_fetch_assoc($resInvs)) {
        $pending_requests[] = [
            'id_db' => $row['id'], 
            'id' => $row['invoice_no'], 
            'type' => 'Invoice', 
            'vendor_client' => $row['client_name'], 
            'amount' => $row['grand_total'], 
            'date' => date('d-M-Y', strtotime($row['invoice_date'])),
            'assigned_to' => $row['approver_designation'] ?? 'CFO'
        ];
    }
}
usort($pending_requests, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

// --- History 1: General Requests (POs & Invoices) ---
 $history_requests = [];
 $resHistoryPOs = @mysqli_query($conn, "SELECT * FROM purchase_orders WHERE approval_status IN ('Approved', 'Rejected') ORDER BY created_at DESC LIMIT 20");
if($resHistoryPOs) {
    while($row = mysqli_fetch_assoc($resHistoryPOs)) {
        $history_requests[] = ['id_db' => $row['id'], 'id' => $row['po_number'], 'type' => 'Purchase Order', 'vendor_client' => $row['vendor_name'], 'amount' => $row['grand_total'], 'status' => $row['approval_status'], 'date' => date('d-M-Y', strtotime($row['po_date'])), 'reason' => $row['reject_reason']];
    }
}

 $resHistoryInvs = @mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.status IN ('Approved', 'Rejected') ORDER BY i.created_at DESC LIMIT 20");
if($resHistoryInvs) {
    while($row = mysqli_fetch_assoc($resHistoryInvs)) {
        $history_requests[] = ['id_db' => $row['id'], 'id' => $row['invoice_no'], 'type' => 'Invoice', 'vendor_client' => $row['client_name'], 'amount' => $row['grand_total'], 'status' => $row['status'], 'date' => date('d-M-Y', strtotime($row['invoice_date'])), 'reason' => $row['reject_reason']];
    }
}
usort($history_requests, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

// --- History 2: Separated Salary History ---
 $history_salaries = [];
 $resHistorySalaries = @mysqli_query($conn, "SELECT s.*, DATE_FORMAT(s.salary_month, '%b %Y') as month_fmt, CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as emp_name FROM employee_salary s JOIN employee_onboarding e ON s.user_id = e.id WHERE s.approval_status IN ('Approved', 'Rejected') AND s.is_deleted = 0 ORDER BY s.approved_at DESC LIMIT 20");
if($resHistorySalaries) {
    while($row = mysqli_fetch_assoc($resHistorySalaries)) {
        $history_salaries[] = ['id_db' => $row['id'], 'id' => 'PAY-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT), 'type' => 'Salary', 'vendor_client' => trim($row['emp_name']) . ' (' . $row['month_fmt'] . ')', 'amount' => $row['net_salary'], 'status' => $row['approval_status'], 'date' => date('d-M-Y', strtotime($row['approved_at'] ?? $row['created_at'])), 'reason' => $row['reject_reason']];
    }
}

if(ob_get_length()) ob_clean();
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Approvals | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --theme-light: #e0f2f1; --bg-body: #f3f4f6; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --success: #10b981; --danger: #ef4444; --primary-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; box-sizing: border-box;}
        
        .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .page-header p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px; }
        .sc-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .sc-info p { margin: 0; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .sc-info h3 { margin: 4px 0 0; font-size: 22px; font-weight: 800; color: var(--text-main); }
        
        .tabs-container { background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.02); overflow: hidden; }
        .tabs-header { display: flex; border-bottom: 1px solid var(--border); background: #f8fafc; }
        .tab-btn { padding: 16px 24px; background: transparent; border: none; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-badge { background: #ef4444; color: white; padding: 2px 8px; border-radius: 20px; font-size: 11px; }
        .tab-content { padding: 0; display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th { text-align: left; padding: 16px; font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); background: #f8fafc; }
        .data-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover { background: #f8fafc; }
        
        .req-id { font-weight: 700; color: var(--theme-color); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .amount-col { font-weight: 800; color: var(--text-main); font-size: 14px; text-align: right; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent;}
        .bg-approved { background: #dcfce7; color: #15803d; border-color: #bbf7d0;}
        .bg-rejected { background: #fee2e2; color: #b91c1c; border-color: #fecaca;}
        .bg-pending { background: #fef9c3; color: #b45309; border-color: #fde047; }
        
        .action-btns { display: flex; gap: 8px; justify-content: flex-end; align-items: center;}
        .btn-sm { padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-view { background: #e0f2fe; color: #0369a1; }
        .btn-view:hover { background: #bae6fd; }
        .btn-print { background: #f1f5f9; color: #475569; border: 1px solid var(--border);}
        .btn-print:hover { background: #e2e8f0; }
        .btn-approve { background: var(--success); color: white; }
        .btn-reject { background: var(--danger); color: white; }
        .btn-disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }
        
        /* Modals & Popups Overrides */
        .swal2-container { z-index: 9999 !important; } /* Force SweetAlert to always be on top */
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 20px; backdrop-filter: blur(3px);}
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; width: 100%; max-width: 700px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;}
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .modal-header h3 { margin: 0; font-size: 18px; color: var(--theme-color); font-weight: 800;}
        .close-modal { font-size: 20px; color: var(--text-muted); cursor: pointer; transition: 0.2s;}
        .close-modal:hover { color: #dc2626; }
        .modal-body { padding: 20px; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid var(--border); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }
        
        /* Details boxes inside Modals */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .detail-box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .detail-box p { margin: 4px 0; font-size: 13px; }

        /* --- STRICT ISOLATED PRINT TEMPLATES --- */
        .print-container { display: none; }
        @media print {
            @page { size: A4; margin: 15mm; }
            body { background: #fff !important; margin: 0; padding: 0; height: auto !important; overflow: visible !important;}
            
            body > * { display: none !important; }
            
            body > .active-print {
                display: block !important;
                position: relative !important;
                width: 100% !important;
                font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
                color: #000 !important;
                line-height: 1.5 !important;
            }
            .active-print * { visibility: visible; }

            .active-print .p-header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
            .active-print .p-logo { max-height: 65px; }
            .active-print .p-title { font-size: 34px; font-weight: 900; letter-spacing: 1px; color: #000; margin-bottom: 12px;}
            
            .active-print .p-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .active-print .p-table th { background-color: #f0f0f0 !important; color: #000; border-bottom: 2px solid #000; padding: 14px 12px; font-size: 13px; text-transform: uppercase; font-weight: bold;}
            .active-print .p-table td { padding: 14px 12px; border-bottom: 1px solid #ddd; font-size: 14px; vertical-align: top;}
            
            .active-print .p-totals { width: 400px; float: right; border-collapse: collapse; margin-bottom: 40px;}
            .active-print .p-totals td { padding: 10px 12px; font-size: 15px; text-align: right;}
            .active-print .p-grand { border-top: 2px solid #000; border-bottom: 2px solid #000; font-size: 20px !important; font-weight: bold; background: #f9f9f9 !important;}
            
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<div id="mainWrapper">
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Request Approvals</h1>
                <p>Review and authorize financial requests drafted by the team.</p>
            </div>
            <?php if(!$can_approve): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 10px 15px; border-radius: 8px; font-size: 13px; font-weight: 700; display:flex; align-items:center; gap:8px;">
                    <i class="ph ph-lock-key"></i> View Only Mode
                </div>
            <?php endif; ?>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="sc-icon" style="background: #e0f2fe; color: #0284c7;"><i class="ph ph-shopping-cart"></i></div>
                <div class="sc-info"><p>Pending POs</p><h3><?= $summary['pending_pos'] ?></h3></div>
            </div>
            <div class="summary-card">
                <div class="sc-icon" style="background: #fef3c7; color: #d97706;"><i class="ph ph-file-text"></i></div>
                <div class="sc-info"><p>Pending Invoices</p><h3><?= $summary['pending_invs'] ?></h3></div>
            </div>
            <div class="summary-card">
                <div class="sc-icon" style="background: #f3e8ff; color: #7e22ce;"><i class="ph ph-users"></i></div>
                <div class="sc-info"><p>Pending Salaries</p><h3><?= $summary['pending_salaries'] ?></h3></div>
            </div>
            <div class="summary-card" style="border-left: 4px solid var(--theme-color);">
                <div class="sc-icon" style="background: var(--theme-light); color: var(--theme-color);"><i class="ph ph-currency-inr"></i></div>
                <div class="sc-info"><p>Total Value Pending</p><h3>₹<?= number_format($summary['total_pending_value'], 2) ?></h3></div>
            </div>
        </div>

        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab(event, 'pending')">
                    <i class="ph ph-hourglass-high"></i> Invoices / POs <span class="tab-badge" id="badgeCount"><?= count($pending_requests) ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab(event, 'salaries')">
                    <i class="ph ph-money"></i> Employee Salaries <?php if(count($salary_requests)>0) echo '<span class="tab-badge" style="background:#f59e0b;">'.count($salary_requests).'</span>'; ?>
                </button>
                <button class="tab-btn" onclick="switchTab(event, 'history')">
                    <i class="ph ph-clock-counter-clockwise"></i> Invoice/PO History
                </button>
                <button class="tab-btn" onclick="switchTab(event, 'salary_history')">
                    <i class="ph ph-clock-counter-clockwise"></i> Salary History
                </button>
            </div>

            <div id="pending" class="tab-content active">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead><tr><th>ID & Type</th><th>Vendor / Client</th><th>Assigned To</th><th>Date</th><th style="text-align: right;">Amount</th><th style="text-align: right;">Action</th></tr></thead>
                        <tbody>
                            <?php if(empty($pending_requests)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No pending requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach($pending_requests as $req): 
                                    $icon = $req['type'] == 'Invoice' ? 'ph-file-text' : 'ph-shopping-cart';
                                ?>
                                <tr>
                                    <td>
                                        <span class="req-id"><i class="ph <?= $icon ?>"></i> <?= $req['id'] ?></span>
                                        <div style="font-size:11px; color:var(--text-muted);"><?= $req['type'] ?></div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($req['vendor_client']) ?></strong></td>
                                    <td>
                                        <!-- NEW: Show who it is assigned to -->
                                        <span class="status-badge bg-pending" style="font-size:10px;">
                                            <i class="ph ph-user-circle"></i> <?= htmlspecialchars($req['assigned_to']) ?>
                                        </span>
                                    </td>
                                    <td><?= $req['date'] ?></td>
                                    <td class="amount-col">₹<?= number_format($req['amount'], 2) ?></td>
                                    <td class="action-btns">
                                        <?php if($can_approve): ?>
                                            <?php if($req['type'] == 'Invoice'): ?>
                                                <button class="btn-sm btn-view" onclick="viewInvoiceToApprove(<?= $req['id_db'] ?>)"><i class="ph ph-eye"></i> View</button>
                                            <?php else: ?>
                                                <button class="btn-sm btn-view" onclick="viewPoToApprove(<?= $req['id_db'] ?>)"><i class="ph ph-eye"></i> View</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn-sm btn-disabled" disabled><i class="ph ph-lock"></i> Locked</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="salaries" class="tab-content">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr><th>Employee</th><th>Month</th><th style="text-align: right;">Net Payable</th><th style="text-align: right;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($salary_requests)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No pending salary approvals.</td></tr>
                            <?php else: ?>
                                <?php foreach($salary_requests as $sal): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sal['emp_name']) ?></strong>
                                        <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($sal['emp_code'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($sal['month_fmt']) ?></td>
                                    <td class="amount-col">₹<?= number_format($sal['net_salary'], 2) ?></td>
                                    <td class="action-btns">
                                        <?php if($can_approve): ?>
                                            <button class="btn-sm btn-view" onclick="viewSalaryToApprove(<?= $sal['id'] ?>)"><i class="ph ph-eye"></i> View</button>
                                        <?php else: ?>
                                            <button class="btn-sm btn-disabled" disabled><i class="ph ph-lock"></i> Locked</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="history" class="tab-content">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead><tr><th>Request ID</th><th>Details</th><th>Date</th><th style="text-align: right;">Amount</th><th>Status</th><th style="text-align: right;">Actions</th></tr></thead>
                        <tbody>
                            <?php if(empty($history_requests)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No history available.</td></tr>
                            <?php else: ?>
                                <?php foreach($history_requests as $hist): 
                                    $isApproved = ($hist['status'] == 'Approved');
                                    $badge = $isApproved ? 'bg-approved' : 'bg-rejected';
                                    $statusIcon = $isApproved ? '<i class="ph-bold ph-check"></i>' : '<i class="ph-bold ph-x"></i>';
                                    
                                    $icon = 'ph-file-text';
                                    if ($hist['type'] == 'Purchase Order') $icon = 'ph-shopping-cart';
                                ?>
                                <tr>
                                    <td>
                                        <span class="req-id"><i class="ph <?= $icon ?>"></i> <?= $hist['id'] ?></span>
                                        <div style="font-size:11px; color:var(--text-muted);"><?= $hist['type'] ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($hist['vendor_client']) ?></td>
                                    <td><?= $hist['date'] ?></td>
                                    <td class="amount-col">₹<?= number_format($hist['amount'], 2) ?></td>
                                    <td><span class="status-badge <?= $badge ?>"><?= $statusIcon ?> <?= $hist['status'] ?></span></td>
                                    <td>
                                        <div class="action-btns" style="justify-content: flex-end;">
                                            <?php if($hist['type'] == 'Invoice'): ?>
                                                <button class="btn-sm btn-view" onclick="viewInvoiceToApprove(<?= $hist['id_db'] ?>, true)" title="View Details"><i class="ph-bold ph-eye"></i></button>
                                            <?php elseif($hist['type'] == 'Purchase Order'): ?>
                                                <button class="btn-sm btn-view" onclick="viewPoToApprove(<?= $hist['id_db'] ?>, true)" title="View Details"><i class="ph-bold ph-eye"></i></button>
                                            <?php endif; ?>

                                            <?php if($isApproved && $hist['type'] == 'Invoice'): ?>
                                                <button class="btn-sm btn-print" onclick="prepareAndPrint('<?= $hist['id_db'] ?>')" title="Print Invoice"><i class="ph-bold ph-printer"></i></button>
                                            <?php elseif($isApproved && $hist['type'] == 'Purchase Order'): ?>
                                                <button class="btn-sm btn-print" onclick="prepareAndPrintPO('<?= $hist['id_db'] ?>')" title="Print PO"><i class="ph-bold ph-printer"></i></button>
                                            <?php elseif(!$isApproved): ?>
                                                <button class="btn-sm" style="background:#fee2e2; color:#b91c1c; border: 1px solid #fecaca;" onclick="showReason('<?= htmlspecialchars(addslashes($hist['reason'] ?? ''), ENT_QUOTES) ?>')" title="View Reason"><i class="ph-bold ph-info"></i></button>
                                            <?php else: ?>
                                                <div style="width: 32px; height: 32px; visibility: hidden;"></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="salary_history" class="tab-content">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead><tr><th>Salary ID</th><th>Employee Details</th><th>Date Approved</th><th style="text-align: right;">Net Amount</th><th>Status</th><th style="text-align: right;">Actions</th></tr></thead>
                        <tbody>
                            <?php if(empty($history_salaries)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No salary history available.</td></tr>
                            <?php else: ?>
                                <?php foreach($history_salaries as $hist): 
                                    $isApproved = ($hist['status'] == 'Approved');
                                    $badge = $isApproved ? 'bg-approved' : 'bg-rejected';
                                    $statusIcon = $isApproved ? '<i class="ph-bold ph-check"></i>' : '<i class="ph-bold ph-x"></i>';
                                ?>
                                <tr>
                                    <td>
                                        <span class="req-id"><i class="ph ph-money"></i> <?= $hist['id'] ?></span>
                                        <div style="font-size:11px; color:var(--text-muted);"><?= $hist['type'] ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($hist['vendor_client']) ?></td>
                                    <td><?= $hist['date'] ?></td>
                                    <td class="amount-col">₹<?= number_format($hist['amount'], 2) ?></td>
                                    <td><span class="status-badge <?= $badge ?>"><?= $statusIcon ?> <?= $hist['status'] ?></span></td>
                                    <td>
                                        <div class="action-btns" style="justify-content: flex-end;">
                                            <button class="btn-sm btn-view" onclick="viewSalaryToApprove(<?= $hist['id_db'] ?>, true)" title="View Details"><i class="ph-bold ph-eye"></i></button>

                                            <?php if($isApproved): ?>
                                                <button class="btn-sm btn-print" onclick="window.location.href='../Accounts/api/generate_payslip.php?id=<?= $hist['id_db'] ?>'" title="View Payslip"><i class="ph-bold ph-file-pdf"></i></button>
                                            <?php else: ?>
                                                <button class="btn-sm" style="background:#fee2e2; color:#b91c1c; border: 1px solid #fecaca;" onclick="showReason('<?= htmlspecialchars(addslashes($hist['reason'] ?? ''), ENT_QUOTES) ?>')" title="View Reason"><i class="ph-bold ph-info"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </main>
</div> 

<div class="modal-overlay" id="viewInvoiceModal">
    <div class="modal-content">
        <div class="modal-header"><h3>Review Invoice: <span id="v_inv_no" style="color: #64748b;"></span></h3><i class="ph-bold ph-x close-modal" onclick="closeModal('viewInvoiceModal')"></i></div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-box">
                    <p style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Client Details</p>
                    <h4 style="margin:0 0 5px 0; font-size:15px; color:var(--theme-color);" id="v_inv_client"></h4>
                    <p id="v_inv_gst"></p>
                    <p id="v_inv_mobile"></p>
                </div>
                <div class="detail-box">
                    <p style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Summary</p>
                    <p style="display:flex; justify-content:space-between;"><span>Date:</span> <strong id="v_inv_date"></strong></p>
                    <p style="display:flex; justify-content:space-between;"><span>Sub Total:</span> <strong id="v_inv_sub"></strong></p>
                    <p style="display:flex; justify-content:space-between;"><span>Tax:</span> <strong id="v_inv_tax"></strong></p>
                    <div style="margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1; font-size:15px; font-weight:800; display:flex; justify-content:space-between;">
                        <span>Grand Total:</span> <span id="v_inv_grand"></span>
                    </div>
                </div>
            </div>
            
            <div id="v_inv_reject_reason_box" style="display: none; background: #fee2e2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong style="color: #b91c1c; font-size: 13px;">Reason for Rejection:</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #991b1b;" id="v_inv_reject_text"></p>
            </div>

            <table class="data-table" style="margin-bottom:0;">
                <thead><tr><th>Description</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Rate</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody id="v_inv_items"></tbody>
            </table>
        </div>
        <div class="modal-footer" id="v_inv_action_footer">
            <button class="btn-sm btn-reject" onclick="openRejectModal('Invoice')"><i class="ph-bold ph-x"></i> Reject</button>
            <button class="btn-sm btn-approve" onclick="executeApprove('Invoice')"><i class="ph-bold ph-check"></i> Approve Invoice</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="viewPoModal">
    <div class="modal-content">
        <div class="modal-header"><h3>Review PO: <span id="v_po_no" style="color: #64748b;"></span></h3><i class="ph-bold ph-x close-modal" onclick="closeModal('viewPoModal')"></i></div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-box">
                    <p style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Vendor Details</p>
                    <h4 style="margin:0 0 5px 0; font-size:15px; color:var(--theme-color);" id="v_po_vendor"></h4>
                </div>
                <div class="detail-box">
                    <p style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Summary</p>
                    <p style="display:flex; justify-content:space-between;"><span>Date:</span> <strong id="v_po_date"></strong></p>
                    <div style="margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1; font-size:15px; font-weight:800; display:flex; justify-content:space-between;">
                        <span>Grand Total:</span> <span id="v_po_grand"></span>
                    </div>
                </div>
            </div>

            <div id="v_po_reject_reason_box" style="display: none; background: #fee2e2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong style="color: #b91c1c; font-size: 13px;">Reason for Rejection:</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #991b1b;" id="v_po_reject_text"></p>
            </div>

            <table class="data-table" style="margin-bottom:0;">
                <thead><tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Rate</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody id="v_po_items"></tbody>
            </table>
        </div>
        <div class="modal-footer" id="v_po_action_footer">
            <button class="btn-sm btn-reject" onclick="openRejectModal('Purchase Order')"><i class="ph-bold ph-x"></i> Reject</button>
            <button class="btn-sm btn-approve" onclick="executeApprove('Purchase Order')"><i class="ph-bold ph-check"></i> Approve PO</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="viewSalaryModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Review Salary</h3><i class="ph-bold ph-x close-modal" onclick="closeModal('viewSalaryModal')"></i></div>
        <div class="modal-body">
            <div class="detail-box" style="margin-bottom: 20px;">
                <p style="display:flex; justify-content:space-between;"><span>Employee:</span> <strong id="v_sal_emp" style="color:var(--theme-color);"></strong></p>
                <p style="display:flex; justify-content:space-between;"><span>Month:</span> <strong id="v_sal_month"></strong></p>
                <hr style="border:0; border-top:1px solid #e2e8f0; margin:15px 0;">
                <p style="display:flex; justify-content:space-between;"><span>Basic Salary:</span> <strong id="v_sal_basic"></strong></p>
                <p style="display:flex; justify-content:space-between;"><span>Allowances:</span> <strong id="v_sal_allow" style="color:#15803d;"></strong></p>
                <p style="display:flex; justify-content:space-between;"><span>Deductions:</span> <strong id="v_sal_deduct" style="color:#b91c1c;"></strong></p>
                <div style="margin-top:15px; padding-top:15px; border-top:1px dashed #cbd5e1; font-size:18px; font-weight:800; display:flex; justify-content:space-between; color: var(--theme-color);">
                    <span>Net Payable:</span> <span id="v_sal_net"></span>
                </div>
            </div>

            <div id="v_sal_reject_reason_box" style="display: none; background: #fee2e2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px;">
                <strong style="color: #b91c1c; font-size: 13px;">Reason for Rejection:</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #991b1b;" id="v_sal_reject_text"></p>
            </div>
        </div>
        <div class="modal-footer" id="v_sal_action_footer">
            <button class="btn-sm btn-reject" onclick="openRejectModal('Salary')"><i class="ph-bold ph-x"></i> Reject</button>
            <button class="btn-sm btn-approve" onclick="executeApprove('Salary')"><i class="ph-bold ph-check"></i> Approve Salary</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="rejectModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: #fee2e2;">
            <h3 style="color: #b91c1c; margin:0;"><i class="ph-fill ph-warning-circle"></i> Reason for Rejection</h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal('rejectModal')"></i>
        </div>
        <div class="modal-body">
            <textarea id="rejectReasonInput" rows="4" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-family: inherit; font-size: 14px; box-sizing: border-box; resize: vertical; outline: none;" placeholder="Type reason here..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-sm btn-view" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn-sm btn-reject" onclick="submitReject()">Confirm Reject</button>
        </div>
    </div>
</div>

<div class="print-container" id="printableInvoice">
    <div class="p-header">
        <div style="text-align: left;">
            <img src="<?= $company_details['logo'] ?>" alt="Logo" class="p-logo">
            <div style="font-size: 18px; font-weight: 800; color: #000; text-transform: uppercase; margin-top: 8px;"><?= $company_details['name'] ?></div>
            <div style="font-size: 14px; margin-top: 5px; color: #333; max-width: 300px;">
                <?= $company_details['address'] ?><br>
                Phone: <?= $company_details['phone'] ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div class="p-title">INVOICE</div>
            <table style="margin-left: auto; font-size: 15px; text-align: left; color: #000;">
                <tr><td style="padding: 4px 10px; font-weight: bold; text-align: right;">DATE:</td><td style="padding: 4px 10px;" id="p_inv_date_lbl"></td></tr>
                <tr><td style="padding: 4px 10px; font-weight: bold; text-align: right;">INVOICE #:</td><td style="padding: 4px 10px; font-weight: bold; color: #1b5a5a;" id="p_inv_no_lbl"></td></tr>
            </table>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; color: #000;">
        <div>
            <h4 style="font-size: 14px; font-weight: bold; margin: 0 0 10px 0; border-bottom: 2px solid #ccc; padding-bottom: 5px; text-transform: uppercase;">Bill To:</h4>
            <p style="font-weight: bold; font-size: 16px; margin: 4px 0; text-transform: uppercase; color: #000;" id="p_inv_client"></p>
            <p style="margin: 4px 0; font-size: 14px; color: #000;" id="p_inv_client_gst"></p>
            <p style="margin: 4px 0; font-size: 14px; color: #000;" id="p_inv_client_phone"></p>
            <p style="margin: 4px 0; font-size: 14px; white-space: pre-line; color: #000;" id="p_inv_client_bank"></p>
        </div>
        
        <div>
            <h4 style="font-size: 14px; font-weight: bold; margin: 0 0 10px 0; border-bottom: 2px solid #ccc; padding-bottom: 5px; text-transform: uppercase;">Account Details:</h4>
            <p style="margin: 4px 0; font-size: 14px; color: #000;"><strong>Account Name:</strong> <span style="text-transform: uppercase;"><?= $company_details['name'] ?></span></p>
            <p style="margin: 4px 0; font-size: 14px; color: #000;"><strong>Account Number:</strong> <span id="p_inv_co_acc"></span></p>
            <p style="margin: 4px 0; font-size: 14px; color: #000;"><strong>IFSC:</strong> <span id="p_inv_co_ifsc"></span></p>
            <p style="margin: 4px 0; font-size: 14px; color: #000;"><strong>UPI ID:</strong> <span id="p_inv_co_upi"></span></p>
        </div>
    </div>

    <table class="p-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">S.No</th>
                <th style="width: 50%; text-align: left;">DESCRIPTION</th>
                <th style="width: 10%; text-align: center;">QUANTITY</th>
                <th style="width: 15%; text-align: right;">UNIT PRICE</th>
                <th style="width: 20%; text-align: right;">AMOUNT</th>
            </tr>
        </thead>
        <tbody id="p_inv_items"></tbody>
    </table>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 40px; color: #000;">
        <table class="p-totals">
            <tr>
                <td>Sub Total</td>
                <td style="width: 150px;"><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_inv_sub">0.00</span></div></td>
            </tr>
            <tr id="tr_p_inv_disc">
                <td>Discount</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_inv_disc">0.00</span></div></td>
            </tr>
            <tr id="tr_p_inv_cgst">
                <td>CGST</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_inv_cgst">0.00</span></div></td>
            </tr>
            <tr id="tr_p_inv_sgst">
                <td>SGST</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_inv_sgst">0.00</span></div></td>
            </tr>
            <tr id="tr_p_inv_roff">
                <td>Round Off</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_inv_roff">0.00</span></div></td>
            </tr>
            <tr class="p-grand">
                <td>TOTAL</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_inv_grand">0.00</span></div></td>
            </tr>
        </table>
    </div>

    <div style="font-size: 14px; margin-bottom: 40px; color: #000;">
        <p style="margin: 5px 0;" id="p_inv_notes"></p>
        <p style="font-weight: bold; margin-top: 15px;" id="p_inv_terms"></p>
    </div>

    <div style="text-align: center; font-size: 14px; border-top: 1px solid #000; padding-top: 15px; font-weight: bold; color: #000; position: fixed; bottom: 15mm; width: 100%;">
        <?= $company_details['name'] ?> | Phone: <?= $company_details['phone'] ?> | <?= $company_details['website'] ?>
    </div>
</div>

<div class="print-container" id="printablePO">
    <div class="p-header">
        <div style="text-align: left;">
            <img src="<?= $company_details['logo'] ?>" alt="Logo" class="p-logo">
            <div style="font-size: 18px; font-weight: 800; color: #000; text-transform: uppercase; margin-top: 8px;"><?= $company_details['name'] ?></div>
            <div style="font-size: 14px; margin-top: 5px; color: #333; max-width: 300px;">
                <?= $company_details['address'] ?><br>
                Phone: <?= $company_details['phone'] ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div class="p-title">PURCHASE ORDER</div>
            <table style="margin-left: auto; font-size: 15px; text-align: left; color: #000;">
                <tr><td style="padding: 4px 10px; font-weight: bold; text-align: right;">DATE:</td><td style="padding: 4px 10px;" id="p_po_date_lbl"></td></tr>
                <tr><td style="padding: 4px 10px; font-weight: bold; text-align: right;">PO #:</td><td style="padding: 4px 10px; font-weight: bold; color: #1b5a5a;" id="p_po_no_lbl"></td></tr>
            </table>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <h4 style="font-size: 14px; font-weight: bold; border-bottom: 2px solid #ccc; padding-bottom: 5px; text-transform: uppercase; color: #555; display: inline-block; margin: 0 0 10px 0;">To (Vendor Details):</h4>
        <div style="font-weight: bold; font-size: 16px; text-transform: uppercase; margin-bottom: 6px;" id="p_po_vendor"></div>
        <div style="font-size: 14px; margin-bottom: 6px; white-space: pre-line;" id="p_po_vendor_address"></div>
        <div style="font-size: 14px; margin-bottom: 6px;" id="p_po_vendor_gst"></div>
        <div style="font-size: 14px;" id="p_po_vendor_contact"></div>
    </div>

    <table class="p-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">S.NO</th>
                <th style="width: 50%;">ITEM DESCRIPTION</th>
                <th style="width: 10%; text-align: center;">QTY</th>
                <th style="width: 15%; text-align: right;">UNIT PRICE</th>
                <th style="width: 20%; text-align: right;">AMOUNT</th>
            </tr>
        </thead>
        <tbody id="p_po_items"></tbody>
    </table>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 40px;">
        <table class="p-totals">
            <tr>
                <td>Sub Total</td>
                <td style="width: 150px;"><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_sub">0.00</span></div></td>
            </tr>
            <tr id="tr_po_tax">
                <td>Tax Amount</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_tax">0.00</span></div></td>
            </tr>
            <tr id="tr_po_freight">
                <td>Freight Charges</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_freight">0.00</span></div></td>
            </tr>
            <tr class="p-grand">
                <td>GRAND TOTAL</td>
                <td><div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_grand">0.00</span></div></td>
            </tr>
        </table>
    </div>

    <div style="font-size: 14px; margin-bottom: 30px;">
        <p style="font-weight: bold; margin: 0 0 5px 0; text-transform: uppercase; color: #555;">Terms & Instructions:</p>
        <p style="white-space: pre-line; line-height: 1.6;" id="p_po_notes"></p>
    </div>

    <div style="text-align: center; font-size: 14px; border-top: 1px solid #000; padding-top: 15px; font-weight: bold; position: fixed; bottom: 15mm; width: 100%;">
        <?= $company_details['name'] ?> | <?= $company_details['phone'] ?> | <?= $company_details['website'] ?>
    </div>
</div>

<script>
    let activeId = null;
    let activeType = null;

    function switchTab(evt, id) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function closeModal(id) { 
        document.getElementById(id).classList.remove('active'); 
    }

    function showReason(reasonText) {
        Swal.fire({
            title: 'Reason Log',
            text: reasonText || 'No reason was provided.',
            icon: 'info',
            confirmButtonColor: '#1b5a5a'
        });
    }

    // --- FETCH & VIEW MODALS LOGIC ---
    function viewInvoiceToApprove(id, isHistory = false) {
        activeId = id;
        const fd = new FormData(); fd.append('action', 'fetch_invoice_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const inv = data.invoice;
                document.getElementById('v_inv_no').innerText = inv.invoice_no;
                document.getElementById('v_inv_client').innerText = inv.company_name || inv.client_name;
                document.getElementById('v_inv_gst').innerText = inv.c_gst ? `GST: ${inv.c_gst}` : '';
                document.getElementById('v_inv_mobile').innerText = inv.mobile_number ? `Mobile: ${inv.mobile_number}` : '';
                document.getElementById('v_inv_date').innerText = new Date(inv.invoice_date).toLocaleDateString('en-GB');
                document.getElementById('v_inv_sub').innerText = '₹' + parseFloat(inv.sub_total).toFixed(2);
                document.getElementById('v_inv_tax').innerText = '₹' + (parseFloat(inv.cgst||0) + parseFloat(inv.sgst||0)).toFixed(2);
                document.getElementById('v_inv_grand').innerText = '₹' + parseFloat(inv.grand_total).toFixed(2);

                const tbody = document.getElementById('v_inv_items'); tbody.innerHTML = '';
                data.items.forEach(it => {
                    tbody.innerHTML += `<tr>
                        <td style="padding:10px; font-size:13px; border-bottom:1px solid #e2e8f0;">${it.description}</td>
                        <td style="padding:10px; font-size:13px; text-align:center; border-bottom:1px solid #e2e8f0;">${it.qty}</td>
                        <td style="padding:10px; font-size:13px; text-align:right; border-bottom:1px solid #e2e8f0;">₹${parseFloat(it.rate).toFixed(2)}</td>
                        <td style="padding:10px; font-size:13px; text-align:right; font-weight:bold; border-bottom:1px solid #e2e8f0;">₹${parseFloat(it.total_amount).toFixed(2)}</td>
                    </tr>`;
                });

                const rejectBox = document.getElementById('v_inv_reject_reason_box');
                if (inv.status === 'Rejected' && inv.reject_reason) {
                    document.getElementById('v_inv_reject_text').innerText = inv.reject_reason;
                    rejectBox.style.display = 'block';
                } else { rejectBox.style.display = 'none'; }

                const footer = document.getElementById('v_inv_action_footer');
                footer.style.display = (isHistory || inv.status !== 'Pending Approval') ? 'none' : 'flex';

                document.getElementById('viewInvoiceModal').classList.add('active');
            }
        });
    }

    function viewPoToApprove(id, isHistory = false) {
        activeId = id;
        const fd = new FormData(); fd.append('action', 'fetch_po_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const po = data.po;
                document.getElementById('v_po_no').innerText = po.po_number;
                document.getElementById('v_po_vendor').innerText = po.vendor_name;
                document.getElementById('v_po_date').innerText = new Date(po.po_date).toLocaleDateString('en-GB');
                document.getElementById('v_po_grand').innerText = '₹' + parseFloat(po.grand_total).toFixed(2);

                const tbody = document.getElementById('v_po_items'); tbody.innerHTML = '';
                if(data.items && data.items.length > 0) {
                    data.items.forEach(it => {
                        tbody.innerHTML += `<tr>
                            <td style="padding:10px; font-size:13px; border-bottom:1px solid #e2e8f0;">${it.item_description || it.description || 'Item'}</td>
                            <td style="padding:10px; font-size:13px; text-align:center; border-bottom:1px solid #e2e8f0;">${it.quantity || it.qty || 1}</td>
                            <td style="padding:10px; font-size:13px; text-align:right; border-bottom:1px solid #e2e8f0;">₹${parseFloat(it.rate || it.unit_price || 0).toFixed(2)}</td>
                            <td style="padding:10px; font-size:13px; text-align:right; font-weight:bold; border-bottom:1px solid #e2e8f0;">₹${parseFloat(it.line_total || it.total_price || it.amount || 0).toFixed(2)}</td>
                        </tr>`;
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:10px; font-size:13px; border-bottom:1px solid #e2e8f0;">Details not broken down. Total Value: ₹${parseFloat(po.grand_total).toFixed(2)}</td></tr>`;
                }

                const rejectBox = document.getElementById('v_po_reject_reason_box');
                if (po.approval_status === 'Rejected' && po.reject_reason) {
                    document.getElementById('v_po_reject_text').innerText = po.reject_reason;
                    rejectBox.style.display = 'block';
                } else { rejectBox.style.display = 'none'; }

                const footer = document.getElementById('v_po_action_footer');
                footer.style.display = (isHistory || po.approval_status !== 'Pending') ? 'none' : 'flex';

                document.getElementById('viewPoModal').classList.add('active');
            }
        });
    }

    function viewSalaryToApprove(id, isHistory = false) {
        activeId = id;
        const fd = new FormData(); fd.append('action', 'fetch_salary_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const sal = data.salary;
                
                const basic = parseFloat(sal.basic || 0);
                const gross = parseFloat(sal.gross_salary || 0);
                const net = parseFloat(sal.net_salary || 0);
                
                const totalAllowances = gross - basic;
                const totalDeductions = gross - net;

                document.getElementById('v_sal_emp').innerText = sal.emp_name + " (" + (sal.emp_code||'') + ")";
                document.getElementById('v_sal_month').innerText = sal.month_fmt;
                document.getElementById('v_sal_basic').innerText = basic.toFixed(2);
                document.getElementById('v_sal_allow').innerText = '+ ' + totalAllowances.toFixed(2);
                document.getElementById('v_sal_deduct').innerText = '- ' + totalDeductions.toFixed(2);
                document.getElementById('v_sal_net').innerText = net.toFixed(2);
                
                const rejectBox = document.getElementById('v_sal_reject_reason_box');
                if (sal.approval_status === 'Rejected' && sal.reject_reason) {
                    document.getElementById('v_sal_reject_text').innerText = sal.reject_reason;
                    rejectBox.style.display = 'block';
                } else { rejectBox.style.display = 'none'; }

                const footer = document.getElementById('v_sal_action_footer');
                footer.style.display = (isHistory || sal.approval_status !== 'Pending') ? 'none' : 'flex';

                document.getElementById('viewSalaryModal').classList.add('active');
            } else {
                Swal.fire('Error', data.message || 'Could not fetch record', 'error');
            }
        });
    }

    // --- APPROVAL / REJECTION EXECUTION ---
    function executeApprove(type) {
        // Automatically close any open review modals to prevent overlay clashing with SweetAlert
        document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active'));

        let action = (type === 'Salary') ? 'ApproveSalary' : 'Approve';
        Swal.fire({
            title: `Approve ${type}?`,
            text: "This action will authorize the request permanently.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#15803d',
            confirmButtonText: 'Yes, Approve'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', action); fd.append('id', activeId); fd.append('type', type);
                fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                    if(data.status === 'success') {
                        Swal.fire({title: 'Approved!', icon: 'success', timer: 1500, showConfirmButton: false}).then(() => location.reload());
                    } else { Swal.fire('Error', data.message, 'error'); }
                });
            }
        });
    }

    function openRejectModal(type) {
        activeType = type;
        document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active')); 
        document.getElementById('rejectReasonInput').value = '';
        document.getElementById('rejectModal').classList.add('active');
    }

    function submitReject() {
        const reason = document.getElementById('rejectReasonInput').value.trim();
        if(reason === '') {
            Swal.fire('Required', 'Please enter a reason for rejection.', 'warning');
            return;
        }

        let action = (activeType === 'Salary') ? 'RejectSalary' : 'Reject';
        const fd = new FormData();
        fd.append('action', action); fd.append('id', activeId); fd.append('type', activeType); fd.append('reason', reason);

        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                Swal.fire({title: 'Rejected', icon: 'info', timer: 1500, showConfirmButton: false}).then(() => location.reload());
            } else { Swal.fire('Error', data.message, 'error'); }
        });
    }

    // --- PRINTING LOGIC ---
    function prepareAndPrint(id) {
        const fd = new FormData(); fd.append('action', 'fetch_invoice_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const inv = data.invoice;
                document.getElementById('p_inv_no_lbl').innerText = inv.invoice_no;
                document.getElementById('p_inv_date_lbl').innerText = new Date(inv.invoice_date).toLocaleDateString('en-GB');
                document.getElementById('p_inv_client').innerText = inv.company_name || inv.client_name;
                document.getElementById('p_inv_client_gst').innerText = inv.c_gst ? `GSTIN: ${inv.c_gst}` : '';
                document.getElementById('p_inv_client_phone').innerText = inv.mobile_number ? `Phone: ${inv.mobile_number}` : '';
                document.getElementById('p_inv_client_bank').innerText = inv.bank_name ? "Client Bank Info:\n" + inv.bank_name.split(' | ').join('\n') : '';

                if (data.company_bank) {
                    document.getElementById('p_inv_co_acc').innerText = data.company_bank.account_number;
                    document.getElementById('p_inv_co_ifsc').innerText = data.company_bank.ifsc_code;
                    document.getElementById('p_inv_co_upi').innerText = data.company_bank.phone_number ? data.company_bank.phone_number + "@upi" : 'N/A';
                } else {
                    document.getElementById('p_inv_co_acc').innerText = "N/A";
                    document.getElementById('p_inv_co_ifsc').innerText = "N/A";
                    document.getElementById('p_inv_co_upi').innerText = "N/A";
                }

                document.getElementById('p_inv_sub').innerText = parseFloat(inv.sub_total).toFixed(2);
                
                if(parseFloat(inv.discount) > 0) { 
                    document.getElementById('p_inv_disc').innerText = parseFloat(inv.discount).toFixed(2); 
                    document.getElementById('tr_p_inv_disc').style.display = 'table-row'; 
                } else { 
                    document.getElementById('tr_p_inv_disc').style.display = 'none'; 
                }
                
                if (parseFloat(inv.cgst) > 0) { 
                    document.getElementById('p_inv_cgst').innerText = parseFloat(inv.cgst).toFixed(2); 
                    document.getElementById('tr_p_inv_cgst').style.display = 'table-row'; 
                } else { 
                    document.getElementById('tr_p_inv_cgst').style.display = 'none'; 
                }
                
                if (parseFloat(inv.sgst) > 0) { 
                    document.getElementById('p_inv_sgst').innerText = parseFloat(inv.sgst).toFixed(2); 
                    document.getElementById('tr_p_inv_sgst').style.display = 'table-row'; 
                } else { 
                    document.getElementById('tr_p_inv_sgst').style.display = 'none'; 
                }
                
                if (parseFloat(inv.round_off) !== 0) { 
                    document.getElementById('p_inv_roff').innerText = parseFloat(inv.round_off).toFixed(2); 
                    document.getElementById('tr_p_inv_roff').style.display = 'table-row'; 
                } else { 
                    document.getElementById('tr_p_inv_roff').style.display = 'none'; 
                }
                
                document.getElementById('p_inv_grand').innerText = parseFloat(inv.grand_total).toFixed(2);
                document.getElementById('p_inv_notes').innerText = inv.notes || '';
                document.getElementById('p_inv_terms').innerText = inv.terms || '';
                
                const table = document.getElementById('p_inv_items'); table.innerHTML = '';
                let sno = 1;
                data.items.forEach(it => { 
                    table.innerHTML += `<tr>
                        <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:center;">${sno++}</td>
                        <td style="border-bottom:1px solid #ddd; padding:14px 10px;">${it.description}</td>
                        <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:center;">${it.qty}</td>
                        <td style="border-bottom:1px solid #ddd; padding:14px 10px;">
                            <div style="display:flex; justify-content:space-between;"><span>₹</span><span>${parseFloat(it.rate).toFixed(2)}</span></div>
                        </td>
                        <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:right;">
                            <div style="display:flex; justify-content:space-between;"><span>₹</span><span>${parseFloat(it.total_amount).toFixed(2)}</span></div>
                        </td>
                    </tr>`; 
                });
                
                document.getElementById('mainWrapper').style.display = 'none';
                document.getElementById('printableInvoice').classList.add('active-print');
                
                setTimeout(() => { 
                    window.print(); 
                    document.getElementById('printableInvoice').classList.remove('active-print'); 
                    document.getElementById('mainWrapper').style.display = 'block';
                }, 500);
            }
        });
    }

    function prepareAndPrintPO(id) {
        const fd = new FormData(); fd.append('action', 'fetch_po_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const po = data.po;
                document.getElementById('p_po_no_lbl').innerText = po.po_number;
                
                const dateObj = new Date(po.po_date);
                document.getElementById('p_po_date_lbl').innerText = dateObj.toLocaleDateString('en-GB');
                
                document.getElementById('p_po_vendor').innerText = po.vendor_name || 'N/A';
                document.getElementById('p_po_vendor_address').innerText = po.vendor_address || '';
                document.getElementById('p_po_vendor_gst').innerText = po.vendor_gstin ? 'GSTIN: ' + po.vendor_gstin : '';
                
                let contactInfo = [];
                if(po.vendor_email) contactInfo.push(po.vendor_email);
                if(po.vendor_phone) contactInfo.push(po.vendor_phone);
                document.getElementById('p_po_vendor_contact').innerText = contactInfo.join(' | ');
                
                document.getElementById('p_po_sub').innerText = parseFloat(po.net_total || 0).toFixed(2);
                
                if(parseFloat(po.tax_amount) > 0) {
                    document.getElementById('p_po_tax').innerText = parseFloat(po.tax_amount).toFixed(2);
                    document.getElementById('tr_po_tax').style.display = 'table-row';
                } else {
                    document.getElementById('tr_po_tax').style.display = 'none';
                }

                if(parseFloat(po.freight_charges) > 0) {
                    document.getElementById('p_po_freight').innerText = parseFloat(po.freight_charges).toFixed(2);
                    document.getElementById('tr_po_freight').style.display = 'table-row';
                } else {
                    document.getElementById('tr_po_freight').style.display = 'none';
                }
                
                document.getElementById('p_po_grand').innerText = parseFloat(po.grand_total || 0).toFixed(2);
                document.getElementById('p_po_notes').innerText = po.terms_conditions || '';

                const tbody = document.getElementById('p_po_items');
                tbody.innerHTML = '';
                let sno = 1;
                
                if(data.items && data.items.length > 0) {
                    data.items.forEach(it => {
                        tbody.innerHTML += `<tr>
                            <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:center;">${sno++}</td>
                            <td style="border-bottom:1px solid #ddd; padding:14px 10px;">${it.item_description || it.description || ''}</td>
                            <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:center;">${it.quantity || it.qty || 1} ${it.unit || ''}</td>
                            <td style="border-bottom:1px solid #ddd; padding:14px 10px;">
                                <div style="display:flex; justify-content:space-between;"><span>₹</span><span>${parseFloat(it.rate || it.unit_price || 0).toFixed(2)}</span></div>
                            </td>
                            <td style="border-bottom:1px solid #ddd; padding:14px 10px; font-weight:bold;">
                                <div style="display:flex; justify-content:space-between;"><span>₹</span><span>${parseFloat(it.line_total || it.total_price || it.amount || 0).toFixed(2)}</span></div>
                            </td>
                        </tr>`;
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px;">No specific items broken down.</td></tr>`;
                }

                document.getElementById('mainWrapper').style.display = 'none';
                document.getElementById('printablePO').classList.add('active-print');
                
                setTimeout(() => { 
                    window.print(); 
                    document.getElementById('printablePO').classList.remove('active-print'); 
                    document.getElementById('mainWrapper').style.display = 'block';
                }, 500);

            } else {
                Swal.fire('Error', 'Could not fetch PO details', 'error');
            }
        });
    }
</script>
</body>
</html>