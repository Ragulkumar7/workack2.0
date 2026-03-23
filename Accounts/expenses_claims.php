<?php
// expenses_claims.php (Accounts Department)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

$current_user_id = $_SESSION['user_id'] ?? 0;

// =========================================================================
// 1. ENTERPRISE AUTO-PATCHER (Enum Fix & Audit Log Creation)
// =========================================================================
$check_enum = mysqli_query($conn, "SHOW COLUMNS FROM `sales_expenses` LIKE 'status'");
if ($check_enum) {
    $col_data = mysqli_fetch_assoc($check_enum);
    if (strpos($col_data['Type'], 'Settled') === false) {
        mysqli_query($conn, "ALTER TABLE `sales_expenses` MODIFY COLUMN `status` ENUM('Pending', 'Approved', 'Rejected', 'Forwarded', 'Settled') DEFAULT 'Pending'");
    }
}

// Create Audit Log Table if it doesn't exist
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `expense_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `performed_by` INT NOT NULL,
    `remarks` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- AJAX ACTION HANDLERS (Strict Validation & Prepared Statements) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(0); 
    ob_clean();
    header('Content-Type: application/json');
    
    // FINAL SETTLEMENT
    if ($_POST['action'] === 'settle_claim') {
        $id = intval($_POST['id']);
        
        // 🚨 ENTERPRISE SECURITY: State-Locking & Prepared Statements
        $stmt = $conn->prepare("UPDATE sales_expenses SET status = 'Settled' WHERE id = ? AND status IN ('Forwarded', 'Pending')");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if($stmt->affected_rows > 0) {
            // Write to Audit Log
            $log = $conn->prepare("INSERT INTO expense_audit_log (expense_id, action, performed_by, remarks) VALUES (?, 'Settled', ?, 'Accounts verified and mapped to payroll.')");
            $log->bind_param("ii", $id, $current_user_id);
            $log->execute(); $log->close();

            echo json_encode(['status' => 'success', 'message' => 'Expense verified and securely linked to Payroll.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Action failed. The claim may have already been processed or altered.']);
        }
        $stmt->close();
        exit;
    }
    
    // REJECT CLAIM
    if ($_POST['action'] === 'reject_claim') {
        $id = intval($_POST['id']);
        $reason = trim($_POST['reason'] ?? '');
        
        // 🚨 ENTERPRISE SECURITY: Empty Validation
        if(empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Rejection reason cannot be empty.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE sales_expenses SET status = 'Rejected', rejection_reason = ? WHERE id = ? AND status IN ('Forwarded', 'Pending')");
        $stmt->bind_param("si", $reason, $id);
        $stmt->execute();

        if($stmt->affected_rows > 0) {
            // Write to Audit Log
            $log = $conn->prepare("INSERT INTO expense_audit_log (expense_id, action, performed_by, remarks) VALUES (?, 'Rejected', ?, ?)");
            $log->bind_param("iis", $id, $current_user_id, $reason);
            $log->execute(); $log->close();

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Action failed. The claim may have already been processed.']);
        }
        $stmt->close();
        exit;
    }
}

// =========================================================================
// 2. DASHBOARD METRICS & FILTERED FETCH
// =========================================================================
$month_filter = $_GET['month'] ?? date('Y-m');
$status_filter = $_GET['status'] ?? '';
$filt_m = date('m', strtotime($month_filter . '-01'));
$filt_y = date('Y', strtotime($month_filter . '-01'));

$claims = [];
$metrics = ['Pending' => 0, 'Settled_Value' => 0, 'Rejected' => 0];

$sql = "SELECT * FROM sales_expenses WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status != 'Draft'";
if (!empty($status_filter)) { $sql .= " AND status = ?"; }
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($status_filter)) {
    $stmt->bind_param("iis", $filt_m, $filt_y, $status_filter);
} else {
    $stmt->bind_param("ii", $filt_m, $filt_y);
}
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    // Populate Top-Level Metrics
    if($row['status'] === 'Forwarded' || $row['status'] === 'Pending') $metrics['Pending']++;
    if($row['status'] === 'Settled') $metrics['Settled_Value'] += (float)$row['amount'];
    if($row['status'] === 'Rejected') $metrics['Rejected']++;

    $row['department'] = $row['department'] ?? 'General'; 
    
    // File Extraction Logic
    $real_file = '';
    foreach($row as $key => $val) {
        if (is_string($val) && preg_match('/\.(jpg|jpeg|png|pdf|gif|webp)$/i', trim($val))) {
            $real_file = basename(trim($val)); break;
        }
    }
    if (empty($real_file)) {
        $possible_cols = ['proof', 'receipt', 'receipt_file', 'bill_file', 'attachment', 'file_name', 'file', 'image', 'bill', 'upload'];
        foreach($possible_cols as $col) {
            if(!empty($row[$col])) { $real_file = basename(trim($row[$col])); break; }
        }
    }
    $row['receipt_file'] = $real_file; 
    $claims[] = $row;
}
$stmt->close();

if(ob_get_length()) ob_clean();
if (file_exists('../sidebars.php')) include '../sidebars.php';
if (file_exists('../header.php')) include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense AP Verification | Workack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #0f766e; --bg-body: #f8fafc; --text-main: #0f172a; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
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
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px;}
        .page-header h2 { color: var(--text-main); margin: 0 0 5px 0; font-size: 28px; font-weight: 800; letter-spacing: -0.5px;}
        .page-header p { margin: 0; font-size: 14px; color: var(--text-muted); font-weight: 500;}

        /* Dashboard Metrics */
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-card h3 { margin: 0 0 10px; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;}
        .stat-card p { margin: 0; font-size: 24px; font-weight: 900; color: var(--text-main); }

        /* Filter Bar */
        .filter-bar { display: flex; gap: 12px; background: white; padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 25px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .filter-bar input, .filter-bar select { padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; font-weight: 500; color: var(--text-main); outline: none;}
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--theme-color); }

        .card { background: white; border: 1px solid var(--border-color); border-radius: 12px; overflow-x: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.02); margin-bottom: 30px;}
        
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 900px;}
        th { background: #f8fafc; padding: 16px 20px; font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 20px; font-size: 14px; font-weight: 600; color: var(--text-main); border-bottom: 1px solid var(--border-color); vertical-align: middle;}
        tbody tr:hover { background: #f1f5f9; }

        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;}
        .badge-forwarded { background: #fef9c3; color: #d97706; border: 1px solid #fde047; }
        .badge-settled { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-rejected { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

        .btn-view { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; font-size: 13px;}
        .btn-view:hover { background: #e2e8f0; }
        
        .btn-process { background: var(--theme-color); color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; font-size: 13px;}
        .btn-process:hover { background: #0d9488; }

        /* Modal Styles */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(4px);}
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.2s;}
        .close-modal:hover { color: #dc2626; }
        
        .verify-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 20px; margin-bottom: 20px;}
        .verify-box h4 { margin: 0 0 15px 0; font-size: 14px; color: var(--theme-color); font-weight: 800; text-transform: uppercase;}
        
        .checklist-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 14px; font-weight: 600; color: #334155; cursor: pointer;}
        .checklist-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--theme-color); }

        .btn-settle { background: #10b981; color: white; border: none; padding: 14px; border-radius: 8px; width: 100%; font-weight: 800; cursor: pointer; font-size: 14px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;}
        .btn-settle:disabled { background: #cbd5e1; cursor: not-allowed;}
        .btn-settle:not(:disabled):hover { background: #059669; }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div class="page-header">
        <div>
            <h2>Accounts Payable: Expenses</h2>
            <p>Verification and settlement gateway for employee claims.</p>
        </div>
    </div>

    <div class="summary-cards">
        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
            <h3>Claims Pending Audit</h3>
            <p><?= $metrics['Pending'] ?></p>
        </div>
        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <h3>Total Settled (This Month)</h3>
            <p style="color: #059669;">₹<?= number_format($metrics['Settled_Value']) ?></p>
        </div>
        <div class="stat-card" style="border-left: 4px solid #ef4444;">
            <h3>Rejected Claims</h3>
            <p><?= $metrics['Rejected'] ?></p>
        </div>
    </div>

    <form class="filter-bar" method="GET">
        <div style="font-weight: 700; color: var(--text-muted); font-size: 12px; text-transform: uppercase;">Filters:</div>
        <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>" onchange="this.form.submit()">
        <select name="status" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="Forwarded" <?= $status_filter == 'Forwarded' ? 'selected' : '' ?>>Pending Audit</option>
            <option value="Settled" <?= $status_filter == 'Settled' ? 'selected' : '' ?>>Settled / Paid</option>
            <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </form>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee Info</th>
                    <th>Category</th>
                    <th>Claim Amount</th>
                    <th>Receipt / Bill</th>
                    <th>AP Status</th>
                    <th style="text-align: right;">Action Center</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($claims)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">No claims found for this period.</td></tr>
                <?php else: foreach($claims as $claim): 
                    
                    $badgeClass = 'badge-forwarded';
                    $statusText = 'Pending Audit';
                    
                    if($claim['status'] == 'Settled') { 
                        $badgeClass = 'badge-settled'; 
                        $statusText = 'Sent to Payroll'; 
                    } elseif($claim['status'] == 'Rejected') { 
                        $badgeClass = 'badge-rejected'; 
                        $statusText = 'Rejected'; 
                    }
                ?>
                <tr>
                    <td style="color: var(--text-muted); font-size: 13px;">
                        <?= date('M d, Y', strtotime($claim['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">
                                <?= strtoupper(substr($claim['executive_name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div style="color: #1e293b;"><?= htmlspecialchars($claim['executive_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;"><?= htmlspecialchars($claim['department']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="color: #334155;"><?= htmlspecialchars($claim['expense_name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($claim['description'] ?? '') ?>">
                            <?= htmlspecialchars($claim['description'] ?? 'No notes provided') ?>
                        </div>
                    </td>
                    <td style="font-weight: 900; font-size: 16px; color: #0f172a;">₹<?= number_format($claim['amount']) ?></td>
                    <td>
                        <button class="btn-view" onclick="viewReceipt('<?= htmlspecialchars($claim['receipt_file']) ?>')">
                            <i class="ph-bold ph-file-text"></i> View Bill
                        </button>
                    </td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                    <td style="text-align: right;">
                        
                        <?php if($claim['status'] == 'Forwarded' || $claim['status'] == 'Pending'): ?>
                            <button class="btn-process" onclick="openVerificationModal(<?= $claim['id'] ?>, '<?= htmlspecialchars($claim['executive_name']) ?>', <?= $claim['amount'] ?>)">
                                <i class="ph-bold ph-shield-check"></i> Audit Claim
                            </button>
                        <?php elseif($claim['status'] == 'Settled'): ?>
                            <span style="font-size: 12px; font-weight: 700; color: #10b981; display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; padding: 6px 10px; border-radius: 6px; border: 1px solid #d1fae5;">
                                <i class="ph-fill ph-check-circle"></i> Settled
                            </span>
                        <?php elseif($claim['status'] == 'Rejected'): ?>
                            <span style="font-size: 12px; font-weight: 700; color: #dc2626; display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; padding: 6px 10px; border-radius: 6px; border: 1px solid #fecaca;" title="Reason: <?= htmlspecialchars($claim['rejection_reason']) ?>">
                                <i class="ph-fill ph-x-circle"></i> Rejected
                            </span>
                        <?php endif; ?>
                        
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="verifyModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="closeModal()"></i>
        <h3 style="margin-top: 0; font-size: 20px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
            <i class="ph-fill ph-shield-check" style="color: var(--theme-color);"></i> Claim Verification
        </h3>
        
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="font-size: 12px; color: var(--text-muted); margin: 0 0 2px 0; font-weight: 700; text-transform: uppercase;">Employee</p>
                <p style="font-size: 16px; color: var(--text-main); margin: 0; font-weight: 800;" id="m_emp"></p>
            </div>
            <div style="text-align: right;">
                <p style="font-size: 12px; color: var(--text-muted); margin: 0 0 2px 0; font-weight: 700; text-transform: uppercase;">Claim Value</p>
                <p style="font-size: 20px; color: var(--theme-color); margin: 0; font-weight: 900;" id="m_amt"></p>
            </div>
        </div>

        <form id="verifyForm" onsubmit="event.preventDefault(); submitFinalSettle();">
            <input type="hidden" name="action" value="settle_claim">
            <input type="hidden" name="id" id="verifyClaimId">
            
            <div class="verify-box">
                <h4 style="display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-list-check"></i> Accounts Audit Checklist</h4>
                
                <label class="checklist-item">
                    <input type="checkbox" id="chk_receipt" onchange="checkConditions()"> 
                    1. Verified receipt matches claimed amount and date.
                </label>
                
                <label class="checklist-item">
                    <input type="checkbox" id="chk_duplicate" onchange="checkConditions()"> 
                    2. Cross-checked ledger to ensure this is not a duplicate.
                </label>
                
                <label class="checklist-item">
                    <input type="checkbox" id="chk_policy" onchange="checkConditions()"> 
                    3. Expense is valid under current company policy.
                </label>
            </div>

            <button type="submit" id="btnFinalApprove" class="btn-settle" disabled>
                <i class="ph-bold ph-paper-plane-tilt"></i> Verify & Send to Payroll
            </button>
            
            <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                <a href="#" onclick="rejectClaim()" style="color: #dc2626; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Discrepancy found? Reject Claim
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    function viewReceipt(filename) {
        if (!filename || filename.trim() === '') {
            Swal.fire({ icon: 'warning', title: 'No Bill Found', text: 'No file is attached in the database for this expense.' });
        } else {
            // Note: In a stricter environment, this points to a view_receipt.php?file=... endpoint.
            window.open('../uploads/expenses/' + filename, '_blank');
        }
    }

    function openVerificationModal(id, empName, amount) {
        document.getElementById('verifyClaimId').value = id;
        document.getElementById('m_emp').innerText = empName;
        document.getElementById('m_amt').innerText = '₹' + amount.toLocaleString('en-IN');
        
        document.getElementById('chk_receipt').checked = false;
        document.getElementById('chk_duplicate').checked = false;
        document.getElementById('chk_policy').checked = false;
        checkConditions(); 

        document.getElementById('verifyModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('verifyModal').classList.remove('active');
    }

    function checkConditions() {
        const c1 = document.getElementById('chk_receipt').checked;
        const c2 = document.getElementById('chk_duplicate').checked;
        const c3 = document.getElementById('chk_policy').checked;
        document.getElementById('btnFinalApprove').disabled = !(c1 && c2 && c3);
    }

    // 🚀 BULLETPROOF AJAX (No Redirects, Safe Parsing)
    function submitFinalSettle() {
        const btn = document.getElementById('btnFinalApprove');
        const originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';
        btn.disabled = true;

        const form = document.getElementById('verifyForm');
        const fd = new FormData(form);
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(async (response) => {
            const rawText = await response.text();
            try { return JSON.parse(rawText); } 
            catch (e) { throw new Error("Server returned an invalid response."); }
        })
        .then(data => {
            if(data.status === 'success') {
                closeModal();
                Swal.fire({
                    icon: 'success', 
                    title: 'Audit Complete', 
                    text: 'Amount successfully mapped to the employee payroll.',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => { location.reload(); });
            } else {
                btn.innerHTML = originalHtml; btn.disabled = false;
                Swal.fire('Database Error', data.message, 'error');
            }
        })
        .catch(err => {
            btn.innerHTML = originalHtml; btn.disabled = false;
            Swal.fire('System Error', err.message, 'error');
        });
    }

    function rejectClaim() {
        closeModal();
        const id = document.getElementById('verifyClaimId').value;
        
        Swal.fire({
            title: 'Reject Claim',
            input: 'textarea',
            inputLabel: 'Reason for rejection (missing receipt, policy violation, etc.):',
            inputPlaceholder: 'Type specific reason here... (Required)',
            inputAttributes: { 'aria-label': 'Type your reason here' },
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Enforce Rejection',
            preConfirm: (reason) => {
                if (!reason || reason.trim() === '') { Swal.showValidationMessage('You must enter a reason.'); return false; }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const fd = new FormData();
                fd.append('action', 'reject_claim');
                fd.append('id', id);
                fd.append('reason', result.value);
                
                fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        Swal.fire({icon: 'info', title: 'Rejected', text: 'HR and the employee have been notified.', timer: 1500, showConfirmButton: false}).then(() => location.reload());
                    } else { Swal.fire('Error', data.message, 'error'); }
                });
            }
        });
    }
</script>

</body>
</html>