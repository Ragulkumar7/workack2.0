<?php
// expenses_claims.php (Accounts Department)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// --- AJAX ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    // FINAL SETTLEMENT (After Verification)
    if ($_POST['action'] === 'settle_claim') {
        $id = intval($_POST['id']);
        // 🚨 FIXED: Keep the database status as 'Settled' so it doesn't break the ENUM constraint and disappear
        if(mysqli_query($conn, "UPDATE sales_expenses SET status = 'Settled' WHERE id = $id")) {
            echo json_encode(['status' => 'success', 'message' => 'Expense successfully verified and sent to salary.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }
    
    // REJECT CLAIM
    if ($_POST['action'] === 'reject_claim') {
        $id = intval($_POST['id']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        if(mysqli_query($conn, "UPDATE sales_expenses SET status = 'Accounts Rejected', rejection_reason = '$reason' WHERE id = $id")) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }
}

// --- FETCH FORWARDED CLAIMS FOR ACCOUNTS ---
$claims = [];
// 🚨 FIXED: Fetch 'Settled' claims properly
$query = mysqli_query($conn, "SELECT * FROM sales_expenses WHERE status IN ('Forwarded', 'Settled', 'Accounts Rejected') ORDER BY created_at DESC");

if ($query) {
    while($row = mysqli_fetch_assoc($query)) {
        $row['department'] = $row['department'] ?? 'Sales'; 
        
        $real_file = '';
        // Automatically finds the file regardless of what your database column is named!
        foreach($row as $key => $val) {
            if (is_string($val) && preg_match('/\.(jpg|jpeg|png|pdf|gif|webp)$/i', trim($val))) {
                $real_file = basename(trim($val)); // Gets just the filename in case the DB stored a full path
                break;
            }
        }
        
        // Fallback just in case it doesn't have an extension
        if (empty($real_file)) {
            $possible_cols = ['proof', 'receipt', 'receipt_file', 'bill_file', 'attachment', 'file_name', 'file', 'image', 'bill', 'upload'];
            foreach($possible_cols as $col) {
                if(!empty($row[$col])) {
                    $real_file = basename(trim($row[$col]));
                    break;
                }
            }
        }
        
        $row['receipt_file'] = $real_file; 
        $claims[] = $row;
    }
}

if(ob_get_length()) ob_clean();
if (file_exists('../sidebars.php')) include '../sidebars.php';
if (file_exists('../header.php')) include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Claims Verification | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f8fafc; --text-main: #0f172a; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 40px; width: calc(100% - var(--primary-sidebar-width)); min-height: 100vh; box-sizing: border-box;}
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { color: var(--theme-color); margin: 0 0 5px 0; font-size: 24px; font-weight: 800; }
        .page-header p { margin: 0; font-size: 14px; color: var(--text-muted); }

        .card { background: white; border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 30px;}
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 20px; font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 20px; font-size: 14px; font-weight: 600; color: var(--text-main); border-bottom: 1px solid var(--border-color); vertical-align: middle;}
        tbody tr:hover { background: #f1f5f9; }

        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;}
        .badge-forwarded { background: #fef9c3; color: #d97706; border: 1px solid #fde047; }
        .badge-settled { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-rejected { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

        .btn-view { background: #f1f5f9; color: #475569; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;}
        .btn-view:hover { background: #e2e8f0; }
        
        .btn-process { background: #0ea5e9; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;}
        .btn-process:hover { background: #0284c7; }

        /* Modal Styles */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(3px);}
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; position: relative; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; color: #94a3b8; cursor: pointer;}
        
        .verify-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 20px; margin-bottom: 20px;}
        .verify-box h4 { margin: 0 0 15px 0; font-size: 14px; color: var(--theme-color); font-weight: 800; text-transform: uppercase;}
        
        .checklist-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 14px; font-weight: 600; color: #334155; cursor: pointer;}
        .checklist-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--theme-color); }

        .btn-settle { background: #10b981; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; font-weight: 800; cursor: pointer; font-size: 14px; transition: 0.2s;}
        .btn-settle:disabled { background: #94a3b8; cursor: not-allowed; opacity: 0.7;}
        .btn-settle:not(:disabled):hover { background: #059669; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <h2>Expense Claims Verification</h2>
        <p>Accounts department verification portal for forwarded employee expenses.</p>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Category</th>
                    <th>Approved Amount</th>
                    <th>Receipt</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($claims)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">No claims pending in Accounts queue.</td></tr>
                <?php else: foreach($claims as $claim): 
                    $badgeClass = 'badge-forwarded';
                    $statusText = 'Pending Audit';
                    
                    // 🚨 FIXED: The UI will display "Sent" even though the database safely holds "Settled"
                    if($claim['status'] == 'Settled') { $badgeClass = 'badge-settled'; $statusText = 'Sent'; }
                    
                    if($claim['status'] == 'Accounts Rejected') { $badgeClass = 'badge-rejected'; $statusText = 'Rejected'; }
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #475569;">
                                <?= strtoupper(substr($claim['executive_name'], 0, 2)) ?>
                            </div>
                            <?= htmlspecialchars($claim['executive_name']) ?>
                        </div>
                    </td>
                    <td style="color: var(--text-muted);"><?= htmlspecialchars($claim['department']) ?></td>
                    <td><?= htmlspecialchars($claim['expense_name']) ?></td>
                    <td style="font-weight: 800; font-size: 16px;">₹<?= number_format($claim['amount']) ?></td>
                    <td>
                        <button class="btn-view" onclick="viewReceipt('<?= htmlspecialchars($claim['receipt_file']) ?>')">
                            <i class="ph-bold ph-receipt"></i> View Bill
                        </button>
                    </td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                    <td style="text-align: right;">
                        <?php if($claim['status'] == 'Forwarded'): ?>
                            <button class="btn-process" onclick="openVerificationModal(<?= $claim['id'] ?>, '<?= htmlspecialchars($claim['executive_name']) ?>', <?= $claim['amount'] ?>)">
                                <i class="ph-bold ph-shield-check"></i> Process
                            </button>
                        <?php else: ?>
                            <span style="font-size: 12px; color: var(--text-muted);">Action Completed</span>
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
            <i class="ph-fill ph-check-circle" style="color: var(--theme-color);"></i> Claim Verification
        </h3>
        
        <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 5px;">Employee: <strong id="m_emp" style="color: var(--text-main);"></strong></p>
        <p style="font-size: 14px; color: var(--text-muted); margin-top: 0;">Claim Amount: <strong id="m_amt" style="color: var(--text-main); font-size: 18px;"></strong></p>

        <form id="verifyForm" onsubmit="event.preventDefault(); submitFinalSettle();">
            <input type="hidden" name="action" value="settle_claim">
            <input type="hidden" name="id" id="verifyClaimId">
            
            <div class="verify-box">
                <h4>Accounts Audit Checklist</h4>
                
                <label class="checklist-item">
                    <input type="checkbox" id="chk_receipt" onchange="checkConditions()"> 
                    1. Receipt attached clearly matches the claimed amount.
                </label>
                
                <label class="checklist-item">
                    <input type="checkbox" id="chk_duplicate" onchange="checkConditions()"> 
                    2. Verified that this is not a duplicate claim.
                </label>
                
                <label class="checklist-item">
                    <input type="checkbox" id="chk_policy" onchange="checkConditions()"> 
                    3. Expense is valid and falls within company policy limits.
                </label>
            </div>

            <button type="submit" id="btnFinalApprove" class="btn-settle" disabled>
                <i class="ph-bold ph-bank"></i> Add to employee salary
            </button>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="#" onclick="rejectClaim()" style="color: #dc2626; font-size: 12px; font-weight: 700; text-decoration: none;">Discrepancy found? Reject Claim</a>
            </div>
        </form>
    </div>
</div>

<script>
    function viewReceipt(filename) {
        if (!filename || filename.trim() === '') {
            Swal.fire({
                icon: 'warning',
                title: 'No Bill Found',
                text: 'It looks like there is no file attached in the database for this expense.'
            });
        } else {
            window.open('../uploads/expenses/' + filename, '_blank');
        }
    }

    function openVerificationModal(id, empName, amount) {
        document.getElementById('verifyClaimId').value = id;
        document.getElementById('m_emp').innerText = empName;
        document.getElementById('m_amt').innerText = '₹' + amount.toLocaleString();
        
        // Reset checkboxes
        document.getElementById('chk_receipt').checked = false;
        document.getElementById('chk_duplicate').checked = false;
        document.getElementById('chk_policy').checked = false;
        checkConditions(); // Disable button

        document.getElementById('verifyModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('verifyModal').classList.remove('active');
    }

    // Ensure all 3 are verified before settlement
    function checkConditions() {
        const c1 = document.getElementById('chk_receipt').checked;
        const c2 = document.getElementById('chk_duplicate').checked;
        const c3 = document.getElementById('chk_policy').checked;
        
        // Only enable the button if ALL THREE are true
        document.getElementById('btnFinalApprove').disabled = !(c1 && c2 && c3);
    }

    function submitFinalSettle() {
        const fd = new FormData(document.getElementById('verifyForm'));
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({icon: 'success', title: 'Sent!', text: data.message, showConfirmButton: false, timer: 1500})
                .then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function rejectClaim() {
        closeModal();
        const id = document.getElementById('verifyClaimId').value;
        
        Swal.fire({
            title: 'Reject Claim',
            input: 'textarea',
            inputLabel: 'Reason for rejection (missing receipt, policy violation, etc.):',
            inputPlaceholder: 'Type your reason here...',
            inputAttributes: { 'aria-label': 'Type your reason here' },
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Reject'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const fd = new FormData();
                fd.append('action', 'reject_claim');
                fd.append('id', id);
                fd.append('reason', result.value);
                
                fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') location.reload();
                    else Swal.fire('Error', data.message, 'error');
                });
            }
        });
    }
</script>

</body>
</html>