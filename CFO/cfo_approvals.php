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
// 2. BACKEND ACTION HANDLER (Approve / Reject / Fetch Details)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'Approve' || $_POST['action'] === 'Reject') {
        $id = intval($_POST['id']);
        $type = $_POST['type'] ?? '';
        $action = $_POST['action'];

        if ($type === 'Purchase Order') {
            $newStatus = ($action === 'Approve') ? 'Approved' : 'Rejected';
            $updateSql = "UPDATE purchase_orders SET approval_status = '$newStatus' WHERE id = $id";
        } else {
            $newStatus = ($action === 'Approve') ? 'Approved' : 'Rejected';
            $updateSql = "UPDATE invoices SET status = '$newStatus' WHERE id = $id";
        }

        if (mysqli_query($conn, $updateSql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // FIXED: Handle Salary Approval/Rejection targeting 'employee_salary' table
    if ($_POST['action'] === 'ApproveSalary' || $_POST['action'] === 'RejectSalary') {
        $id = intval($_POST['id']);
        $newStatus = ($_POST['action'] === 'ApproveSalary') ? 'Approved' : 'Rejected';
        
        $updateSql = "UPDATE employee_salary SET approval_status = '$newStatus' WHERE id = $id";
        if (mysqli_query($conn, $updateSql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // Fetch details for Template Printing
    if ($_POST['action'] === 'fetch_invoice_details') {
        $inv_id = intval($_POST['id']);
        $inv_res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = $inv_id");
        $invoice = mysqli_fetch_assoc($inv_res);
        $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
        $items = [];
        while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
        echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items]);
        exit;
    }
}

// --- FIXED: Fetch Salary Requests targeting 'employee_salary' table ---
$salary_requests = [];
$resSalaries = @mysqli_query($conn, "
    SELECT s.*, u.name as emp_name, u.employee_id as emp_code 
    FROM employee_salary s 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.created_at DESC
");

if ($resSalaries) {
    while($row = mysqli_fetch_assoc($resSalaries)) {
        $salary_requests[] = $row;
    }
}

// =========================================================================
// 3. FETCH REAL DATA FROM DB (PO & Invoices)
// =========================================================================
$pendingPosCount = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT COUNT(*) as cnt FROM purchase_orders WHERE approval_status = 'Pending'"))['cnt'] ?? 0;
$pendingInvsCount = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE status = 'Pending Approval'"))['cnt'] ?? 0;
$valPOs = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM purchase_orders WHERE approval_status = 'Pending'"))['val'] ?? 0;
$valInvs = mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE status = 'Pending Approval'"))['val'] ?? 0;
$totalPendingValue = $valPOs + $valInvs;

$summary = [
    'pending_pos' => $pendingPosCount,
    'pending_invs' => $pendingInvsCount,
    'total_pending_value' => $totalPendingValue
];

$pending_requests = [];
$resPOs = @mysqli_query($conn, "SELECT * FROM purchase_orders WHERE approval_status = 'Pending' ORDER BY created_at DESC");
if($resPOs) {
    while($row = mysqli_fetch_assoc($resPOs)) {
        $pending_requests[] = ['id_db' => $row['id'], 'id' => $row['po_number'], 'type' => 'Purchase Order', 'vendor_client' => $row['vendor_name'], 'amount' => $row['grand_total'], 'date' => date('d-M-Y', strtotime($row['po_date']))];
    }
}

$resInvs = @mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.status = 'Pending Approval' ORDER BY i.created_at DESC");
if($resInvs) {
    while($row = mysqli_fetch_assoc($resInvs)) {
        $pending_requests[] = ['id_db' => $row['id'], 'id' => $row['invoice_no'], 'type' => 'Invoice', 'vendor_client' => $row['client_name'], 'amount' => $row['grand_total'], 'date' => date('d-M-Y', strtotime($row['invoice_date']))];
    }
}
usort($pending_requests, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

$history_requests = [];
$resHistoryPOs = @mysqli_query($conn, "SELECT * FROM purchase_orders WHERE approval_status IN ('Approved', 'Rejected') ORDER BY created_at DESC LIMIT 10");
if($resHistoryPOs) {
    while($row = mysqli_fetch_assoc($resHistoryPOs)) {
        $history_requests[] = ['id_db' => $row['id'], 'id' => $row['po_number'], 'type' => 'Purchase Order', 'vendor_client' => $row['vendor_name'], 'amount' => $row['grand_total'], 'status' => $row['approval_status'], 'date' => date('d-M-Y', strtotime($row['po_date']))];
    }
}

$resHistoryInvs = @mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.status IN ('Approved', 'Rejected') ORDER BY i.created_at DESC LIMIT 10");
if($resHistoryInvs) {
    while($row = mysqli_fetch_assoc($resHistoryInvs)) {
        $history_requests[] = ['id_db' => $row['id'], 'id' => $row['invoice_no'], 'type' => 'Invoice', 'vendor_client' => $row['client_name'], 'amount' => $row['grand_total'], 'status' => $row['status'], 'date' => date('d-M-Y', strtotime($row['invoice_date']))];
    }
}
usort($history_requests, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Center - CFO Dashboard</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --theme-light: #e0f2f1; --bg-body: #f3f4f6; --surface: #ffffff; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --primary-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; transition: all 0.3s ease; box-sizing: border-box;}
        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .page-header p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
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
        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th { text-align: left; padding: 16px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); background: white; }
        .data-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover { background: #f8fafc; }
        .req-id { font-weight: 700; color: var(--theme-color); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; }
        .amount-col { font-weight: 700; color: var(--text-main); font-size: 14px; text-align: right; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .bg-approved { background: #dcfce7; color: #15803d; }
        .bg-rejected { background: #fee2e2; color: #b91c1c; }
        .action-btns { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-sm { padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-view { background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border); }
        .btn-approve { background: var(--success); color: white; }
        .btn-reject { background: var(--danger); color: white; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 20px; }
        .modal-content { background: white; width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .modal-header h3 { margin: 0; font-size: 18px; color: var(--theme-color); }
        .close-modal { font-size: 20px; color: var(--text-muted); cursor: pointer; }
        .modal-body { padding: 20px; overflow-y: auto; max-height: 70vh; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid var(--border); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 3000; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 14px; font-weight: 600; }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }

        /* --- PRINT TEMPLATE STYLES --- */
        #printableInvoice { display: none; width: 210mm; padding: 20mm; background: white; font-family: 'Plus Jakarta Sans', sans-serif; color: #333; line-height: 1.4; }
        @media print {
            body * { visibility: hidden; }
            #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { display: block !important; position: absolute; left: 0; top: 0; }
        }
        .p-header { border-bottom: 2px solid #1b5a5a; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .p-company { font-size: 26px; font-weight: 800; color: #1b5a5a; }
        .p-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .p-table th { background: #f1f5f9; text-align: left; padding: 10px; font-size: 12px; border: 1px solid #e2e8f0; }
        .p-table td { padding: 10px; border: 1px solid #e2e8f0; font-size: 13px; }
        .p-total-section { float: right; width: 220px; margin-top: 10px; }
        .p-total-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f1f5f9; }
        .p-grand { border-top: 2px solid #1b5a5a; font-weight: 800; color: #1b5a5a; font-size: 16px; margin-top: 5px; padding-top: 5px; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <h1>Maker-Checker: Approval Center</h1>
        <p>Review and authorize financial requests drafted by the Accounts team.</p>
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
        <div class="summary-card" style="border-left: 4px solid var(--theme-color);">
            <div class="sc-icon" style="background: var(--theme-light); color: var(--theme-color);"><i class="ph ph-currency-inr"></i></div>
            <div class="sc-info"><p>Total Value Pending</p><h3>₹<?= number_format($summary['total_pending_value'], 2) ?></h3></div>
        </div>
    </div>

    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'pending')">
                <i class="ph ph-hourglass-high"></i> Action Required <span class="tab-badge" id="badgeCount"><?= count($pending_requests) ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'salaries')">
                <i class="ph ph-users"></i> Employee Salaries
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'history')">
                <i class="ph ph-clock-counter-clockwise"></i> Approval History
            </button>
        </div>

        <div id="pending" class="tab-content active">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>ID & Type</th><th>Vendor / Client</th><th>Date</th><th style="text-align: right;">Amount</th><th style="text-align: right;">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($pending_requests as $req): 
                            $icon = $req['type'] == 'Invoice' ? 'ph-file-text' : 'ph-shopping-cart';
                        ?>
                        <tr>
                            <td>
                                <span class="req-id"><i class="ph <?= $icon ?>"></i> <?= $req['id'] ?></span>
                                <div style="font-size:11px; color:var(--text-muted);"><?= $req['type'] ?></div>
                            </td>
                            <td><strong><?= htmlspecialchars($req['vendor_client']) ?></strong></td>
                            <td><?= $req['date'] ?></td>
                            <td class="amount-col">₹<?= number_format($req['amount'], 2) ?></td>
                            <td class="action-btns">
                                <button class="btn-sm btn-approve" onclick="openActionModal('<?= $req['id_db'] ?>', '<?= $req['id'] ?>', 'Approve', '<?= $req['type'] ?>')"><i class="ph ph-check"></i> Approve</button>
                                <button class="btn-sm btn-reject" onclick="openActionModal('<?= $req['id_db'] ?>', '<?= $req['id'] ?>', 'Reject', '<?= $req['type'] ?>')"><i class="ph ph-x"></i> Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="salaries" class="tab-content">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Month</th>
                            <th style="text-align: right;">Net Payable</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($salary_requests)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No salary records found for approval.</td></tr>
                        <?php else: ?>
                            <?php foreach($salary_requests as $sal): 
                                $isSalPending = ($sal['approval_status'] === 'Pending' || empty($sal['approval_status']));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($sal['emp_name']) ?></strong>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($sal['emp_code'] ?? '') ?></div>
                                </td>
                                <td><?= htmlspecialchars($sal['salary_month']) ?></td>
                                <td class="amount-col">₹<?= number_format($sal['net_salary'], 2) ?></td>
                                <td>
                                    <?php if($sal['approval_status'] === 'Approved'): ?>
                                        <span class="status-badge bg-approved"><i class="ph ph-check"></i> Approved</span>
                                    <?php elseif($sal['approval_status'] === 'Rejected'): ?>
                                        <span class="status-badge bg-rejected"><i class="ph ph-x"></i> Rejected</span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background: #fef9c3; color: #d97706;"><i class="ph ph-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-btns">
                                    <?php if($isSalPending): ?>
                                        <button class="btn-sm btn-approve" onclick="actionSalary('<?= $sal['id'] ?>', 'ApproveSalary')"><i class="ph ph-check"></i> Approve</button>
                                        <button class="btn-sm btn-reject" onclick="actionSalary('<?= $sal['id'] ?>', 'RejectSalary')"><i class="ph ph-x"></i> Reject</button>
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
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Request ID</th><th>Vendor / Client</th><th>Date</th><th style="text-align: right;">Amount</th><th>Status</th><th style="text-align: right;">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($history_requests as $hist): 
                            $isApproved = ($hist['status'] == 'Approved');
                            $badge = $isApproved ? 'bg-approved' : 'bg-rejected';
                        ?>
                        <tr>
                            <td><strong><?= $hist['id'] ?></strong><br><small><?= $hist['type'] ?></small></td>
                            <td><?= htmlspecialchars($hist['vendor_client']) ?></td>
                            <td><?= $hist['date'] ?></td>
                            <td class="amount-col">₹<?= number_format($hist['amount'], 2) ?></td>
                            <td><span class="status-badge <?= $badge ?>"><?= $hist['status'] ?></span></td>
                            <td style="text-align: right;">
                                <?php if($isApproved && $hist['type'] == 'Invoice'): ?>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button class="btn-sm btn-view" onclick="prepareAndPrint('<?= $hist['id_db'] ?>')" title="Print"><i class="ph ph-printer"></i></button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="actionModal">
    <div class="modal-content">
        <div class="modal-header"><h3 id="modalActionTitle">Confirm Action</h3><i class="ph ph-x close-modal" onclick="closeModal('actionModal')"></i></div>
        <div class="modal-body">
            <p>Authorize request <strong id="modalReqId" style="color:var(--theme-color);"></strong>?</p>
            <input type="hidden" id="activeDbId">
            <input type="hidden" id="activeAction">
            <input type="hidden" id="activeType">
            <div class="form-group"><label>Remarks (Optional)</label><textarea id="actionRemarks" rows="3" style="width:100%; border:1px solid #ddd; border-radius:8px; padding:10px;"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn-sm btn-view" onclick="closeModal('actionModal')">Cancel</button>
            <button class="btn-sm" id="confirmActionBtn" onclick="executeAction()" style="color:white;">Confirm</button>
        </div>
    </div>
</div>

<div id="printableInvoice">
    <div class="p-header">
        <div><div class="p-company">NEOERA INFOTECH</div><div style="font-size: 11px;">9/96 h, Post, Village Nagar, SSKulam<br>Coimbatore, TN 641107 | info@neoerait.com</div></div>
        <div style="text-align: right;"><div style="font-size: 20px; font-weight: 800; color: #1b5a5a;">TAX INVOICE</div><div style="font-size: 12px; margin-top: 5px;">Invoice No: <strong id="p_inv_no"></strong></div><div style="font-size: 12px;">Date: <strong id="p_date"></strong></div></div>
    </div>
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div style="width: 45%;"><div style="font-size: 11px; font-weight: 800; color: #1b5a5a; border-bottom: 1px solid #eee; margin-bottom: 8px;">BILLED TO</div><div style="font-size: 14px; font-weight: 700;" id="p_client"></div></div>
        <div style="width: 45%; text-align: right;"><div style="font-size: 11px; font-weight: 800; color: #1b5a5a; border-bottom: 1px solid #eee; margin-bottom: 8px;">BANK DETAILS</div><div style="font-size: 12px;">South Indian Bank<br>A/C: 0663073000000958<br>IFSC: SIBL0000663</div></div>
    </div>
    <table class="p-table">
        <thead><tr><th>PARTICULARS</th><th style="width: 10%; text-align: center;">QTY</th><th style="width: 15%; text-align: right;">RATE</th><th style="width: 15%; text-align: right;">TAX %</th><th style="width: 20%; text-align: right;">AMOUNT</th></tr></thead>
        <tbody id="p_items"></tbody>
    </table>
    <div class="p-total-section">
        <div class="p-total-row"><span>Sub Total</span><span id="p_sub"></span></div>
        <div class="p-total-row"><span>Tax Amount</span><span id="p_tax"></span></div>
        <div class="p-total-row p-grand"><span>GRAND TOTAL</span><span id="p_grand"></span></div>
    </div>
</div>

<div id="toast">Message here</div>

<script>
    function switchTab(evt, id) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function openActionModal(dbId, reqId, action, type) {
        document.getElementById('modalReqId').innerText = reqId;
        document.getElementById('activeDbId').value = dbId;
        document.getElementById('activeAction').value = action;
        document.getElementById('activeType').value = type;
        document.getElementById('modalActionTitle').innerText = action + " Request";
        
        const btn = document.getElementById('confirmActionBtn');
        btn.innerText = action;
        btn.className = action === 'Approve' ? 'btn-sm btn-approve' : 'btn-sm btn-reject';
        
        document.getElementById('actionModal').style.display = 'flex';
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function showToast(msg) {
        const toast = document.getElementById('toast');
        toast.innerText = msg;
        toast.className = 'show';
        setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
    }

    function executeAction() {
        const dbId = document.getElementById('activeDbId').value;
        const action = document.getElementById('activeAction').value;
        const type = document.getElementById('activeType').value;

        const fd = new FormData();
        fd.append('action', action);
        fd.append('id', dbId);
        fd.append('type', type);

        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if(data.status === 'success') location.reload(); });
    }

    // Salary Action (Fixed table fetching logic)
    function actionSalary(id, action) {
        let actionText = action === 'ApproveSalary' ? 'Approve' : 'Reject';
        if(confirm(`Are you sure you want to ${actionText} this salary record?`)) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('id', id);

            fetch('', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast(`Salary record has been ${actionText}d successfully.`);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert("Error: " + data.message);
                }
            });
        }
    }

    function prepareAndPrint(id) {
        const fd = new FormData(); fd.append('action', 'fetch_invoice_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const inv = data.invoice;
                document.getElementById('p_inv_no').innerText = inv.invoice_no;
                document.getElementById('p_date').innerText = inv.invoice_date;
                document.getElementById('p_client').innerText = inv.client_name;
                document.getElementById('p_sub').innerText = '₹' + parseFloat(inv.sub_total).toFixed(2);
                document.getElementById('p_tax').innerText = '₹' + parseFloat((parseFloat(inv.cgst||0) + parseFloat(inv.sgst||0))).toFixed(2);
                document.getElementById('p_grand').innerText = '₹' + parseFloat(inv.grand_total).toFixed(2);

                const itemsBody = document.getElementById('p_items');
                itemsBody.innerHTML = '';
                data.items.forEach((it, idx) => {
                    itemsBody.innerHTML += `<tr><td>${it.description}</td><td style="text-align:center;">${it.qty}</td><td style="text-align:right;">${it.rate}</td><td style="text-align:right;">-</td><td style="text-align:right;">${it.total_amount}</td></tr>`;
                });

                document.getElementById('printableInvoice').style.display = 'block';
                window.print();
                document.getElementById('printableInvoice').style.display = 'none';
            }
        });
    }
</script>
</body>
</html>