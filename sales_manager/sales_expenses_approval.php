<?php
// Fixes "headers already sent" error by turning on output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

// --- AJAX ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    // APPROVE
    if ($_POST['action'] === 'approve') {
        $id = intval($_POST['id']);
        if(mysqli_query($conn, "UPDATE sales_expenses SET status = 'Approved' WHERE id = $id")) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }
    
    // REJECT
    if ($_POST['action'] === 'reject') {
        $id = intval($_POST['id']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        if(mysqli_query($conn, "UPDATE sales_expenses SET status = 'Rejected', rejection_reason = '$reason' WHERE id = $id")) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // FORWARD TO ACCOUNTS (BULK)
    if ($_POST['action'] === 'forward') {
        $ids_str = $_POST['ids']; // format: "1,2,3"
        // Sanitize
        $id_array = array_map('intval', explode(',', $ids_str));
        $clean_ids = implode(',', $id_array);

        if(!empty($clean_ids)) {
            if(mysqli_query($conn, "UPDATE sales_expenses SET status = 'Forwarded' WHERE id IN ($clean_ids)")) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No valid IDs selected']);
        }
        exit;
    }
}

// --- FETCH ALL TEAM EXPENSES ---
$all_expenses = [];
$metrics = ['total_req' => 0, 'pending' => 0, 'approved' => 0, 'forwarded' => 0];

$query = mysqli_query($conn, "SELECT * FROM sales_expenses ORDER BY created_at DESC");
if ($query) {
    while($row = mysqli_fetch_assoc($query)) {
        $all_expenses[] = $row;
        $metrics['total_req']++;
        
        if($row['status'] == 'Pending') $metrics['pending']++;
        if($row['status'] == 'Approved') $metrics['approved']++;
        if($row['status'] == 'Forwarded') $metrics['forwarded']++;
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
    <title>Expense Approvals | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f8fafc; --text-main: #0f172a; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 40px; width: calc(100% - var(--primary-sidebar-width)); min-height: 100vh; box-sizing: border-box;}
        
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h2 { color: var(--theme-color); margin: 0 0 5px 0; font-size: 24px; font-weight: 800; }
        .page-header p { margin: 0; font-size: 14px; color: var(--text-muted); }

        .btn-primary { background: #0284c7; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 13px; box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.2);}
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); background: #0369a1;}
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none;}

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .stat-card { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-info h4 { margin: 0; font-size: 22px; color: var(--text-main); font-weight: 800;}
        .stat-info p { margin: 0; font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Custom Checkbox */
        .custom-checkbox { width: 18px; height: 18px; border-radius: 4px; border: 2px solid #cbd5e1; appearance: none; cursor: pointer; display: grid; place-content: center; transition: 0.2s;}
        .custom-checkbox::before { content: ""; width: 10px; height: 10px; transform: scale(0); box-shadow: inset 1em 1em white; background-color: white; transform-origin: center; clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%); transition: 120ms transform ease-in-out; }
        .custom-checkbox:checked { background-color: #0284c7; border-color: #0284c7; }
        .custom-checkbox:checked::before { transform: scale(1); }
        .custom-checkbox:disabled { cursor: not-allowed; opacity: 0.4; }

        .table-container { background: white; border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 20px; font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 20px; font-size: 14px; font-weight: 600; color: var(--text-main); border-bottom: 1px solid var(--border-color); vertical-align: middle;}
        tbody tr { transition: background 0.2s;}
        tbody tr:hover { background: #f1f5f9; }
        tbody tr:last-child td { border-bottom: none; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;}
        .stat-Pending { background: #fef9c3; color: #d97706; }
        .stat-Approved { background: #dcfce7; color: #16a34a; }
        .stat-Forwarded { background: #e0f2fe; color: #0284c7; }
        .stat-Rejected { background: #fee2e2; color: #dc2626; }

        .action-btns { display: flex; gap: 8px; }
        .act-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.2s; }
        
        .act-view { background: #e0f2fe; color: #0284c7; }
        .act-view:hover { background: #0284c7; color: white; }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; backdrop-filter: blur(2px);}
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 480px; position: relative; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.2s;}
        .close-modal:hover { color: var(--theme-color); }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.2s;}
        .form-group textarea:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        
        /* Ensures SweetAlert is always on top of Modals */
        .swal2-container { z-index: 9999 !important; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>Team Expense Approvals</h2>
            <p>Review, approve, and forward sales team expenses to accounts.</p>
        </div>
        <button class="btn-primary" id="forwardBtn" onclick="forwardSelected()" disabled>
            <i class="ph-bold ph-paper-plane-tilt" style="font-size: 16px;"></i> Forward Selected to Accounts
        </button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #f1f5f9; color: #475569;"><i class="ph-fill ph-files"></i></div>
            <div class="stat-info"><h4><?= $metrics['total_req'] ?></h4><p>Total Requests</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef9c3; color: #d97706;"><i class="ph-fill ph-clock-countdown"></i></div>
            <div class="stat-info"><h4><?= $metrics['pending'] ?></h4><p>Pending Action</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;"><i class="ph-fill ph-check-circle"></i></div>
            <div class="stat-info"><h4><?= $metrics['approved'] ?></h4><p>Approved (Ready)</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;"><i class="ph-fill ph-paper-plane-tilt"></i></div>
            <div class="stat-info"><h4><?= $metrics['forwarded'] ?></h4><p>Forwarded</p></div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" class="custom-checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)">
                    </th>
                    <th>Executive</th>
                    <th>Expense Info</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($all_expenses)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">No expenses submitted by the team yet.</td></tr>
                <?php else: foreach($all_expenses as $exp): 
                    // Safely encode expense data for JavaScript
                    $expData = htmlspecialchars(json_encode($exp, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php if($exp['status'] === 'Approved'): ?>
                                <input type="checkbox" class="custom-checkbox row-checkbox" value="<?= $exp['id'] ?>" onchange="checkSelections()">
                            <?php else: ?>
                                <input type="checkbox" class="custom-checkbox" disabled>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">
                                    <?= strtoupper(substr($exp['executive_name'], 0, 2)) ?>
                                </div>
                                <?= htmlspecialchars($exp['executive_name']) ?>
                            </div>
                        </td>
                        <td>
                            <?= htmlspecialchars($exp['expense_name']) ?>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Via <?= htmlspecialchars($exp['payment_method']) ?></div>
                        </td>
                        <td style="color: var(--text-muted); font-size: 13px;"><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                        <td style="font-size: 16px; font-weight: 800;">₹<?= number_format($exp['amount']) ?></td>
                        <td>
                            <span class="status-badge stat-<?= $exp['status'] ?>">
                                <?php 
                                    if($exp['status'] == 'Pending') echo '<i class="ph-bold ph-clock"></i> Pending';
                                    elseif($exp['status'] == 'Approved') echo '<i class="ph-bold ph-check"></i> Approved';
                                    elseif($exp['status'] == 'Forwarded') echo '<i class="ph-bold ph-paper-plane-tilt"></i> Forwarded';
                                    else echo '<i class="ph-bold ph-x"></i> Rejected';
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns" style="justify-content: flex-end;">
                                <button class="act-btn act-view" title="Review Expense" onclick='openViewModal(<?= $expData ?>)'>
                                    <i class="ph-bold ph-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="viewExpenseModal">
    <div class="modal-content" style="max-width: 500px;">
        <i class="ph-bold ph-x close-modal" onclick="document.getElementById('viewExpenseModal').classList.remove('active')"></i>
        
        <h3 style="margin-top: 0; color: var(--theme-color); font-size: 20px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <i class="ph-fill ph-file-text"></i> Expense Review
        </h3>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <p style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase; margin:0 0 4px 0;">Executive</p>
                    <p style="margin:0; font-weight:700; font-size:14px;" id="v_exec"></p>
                </div>
                <div>
                    <p style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase; margin:0 0 4px 0;">Date Incurred</p>
                    <p style="margin:0; font-weight:700; font-size:14px;" id="v_date"></p>
                </div>
                <div>
                    <p style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase; margin:0 0 4px 0;">Claim Amount</p>
                    <p style="margin:0; font-weight:800; font-size:18px; color:#0f172a;" id="v_amt"></p>
                </div>
                <div>
                    <p style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase; margin:0 0 4px 0;">Payment Method</p>
                    <p style="margin:0; font-weight:600; font-size:14px;" id="v_method"></p>
                </div>
            </div>
            
            <div>
                <p style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase; margin:0 0 4px 0;">Expense Name / Purpose</p>
                <p style="margin:0; font-weight:600; font-size:14px; line-height: 1.4;" id="v_name"></p>
            </div>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1;" id="v_proof_container">
                <p style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase; margin:0 0 8px 0;">Attached Proof</p>
                <a id="v_proof_link" href="#" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:#e0f2fe; color:#0284c7; padding:8px 14px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700; border:1px solid #bae6fd; transition: 0.2s;">
                    <i class="ph-bold ph-paperclip"></i> View Attached Document
                </a>
                <span id="v_no_proof" style="font-size:12px; font-weight:600; color:#94a3b8; display:none; padding: 6px 0;">No proof document was attached.</span>
            </div>
        </div>

        <div id="v_action_btns" style="display: flex; gap: 10px;">
            <button type="button" class="btn-primary" style="flex: 1; background: #dc2626; justify-content: center; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);" onclick="triggerReject()">Reject Claim</button>
            <button type="button" class="btn-primary" style="flex: 1; background: #16a34a; justify-content: center; box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.2);" onclick="triggerApprove()">Approve Claim</button>
        </div>
        
        <div id="v_status_msg" style="display: none; text-align: center; font-weight: 700; font-size: 13px; color: var(--text-muted); padding: 10px; background: #f1f5f9; border-radius: 8px;">
            This claim has already been processed.
        </div>
    </div>
</div>

<div class="modal-overlay" id="rejectModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="document.getElementById('rejectModal').classList.remove('active')"></i>
        <h3 style="margin-top: 0; color: #dc2626; font-size: 20px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;"><i class="ph-fill ph-warning-circle"></i> Reject Expense</h3>
        
        <form id="rejectForm" onsubmit="event.preventDefault(); submitReject();">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="rejectExpId">
            <div class="form-group">
                <label>Reason for Rejection *</label>
                <textarea name="reason" rows="3" required placeholder="Tell the executive why this is rejected..."></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-primary" style="flex: 1; background: white; color: #64748b; border: 1px solid #cbd5e1; box-shadow: none;" onclick="document.getElementById('rejectModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-primary" style="flex: 1; background: #dc2626; justify-content: center; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);">Reject Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- CHECKBOX LOGIC ---
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            if(!cb.disabled) cb.checked = source.checked;
        });
        checkSelections();
    }

    function checkSelections() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        const btn = document.getElementById('forwardBtn');
        btn.disabled = checkedCount === 0;
        btn.innerHTML = `<i class="ph-bold ph-paper-plane-tilt" style="font-size: 16px;"></i> Forward Selected (${checkedCount}) to Accounts`;
    }

    // --- NEW VIEW & APPROVAL LOGIC ---
    let currentlyViewingId = null;

    function openViewModal(data) {
        currentlyViewingId = data.id;
        
        // Populate Details
        document.getElementById('v_exec').innerText = data.executive_name;
        document.getElementById('v_name').innerText = data.expense_name;
        
        const d = new Date(data.expense_date);
        document.getElementById('v_date').innerText = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        
        document.getElementById('v_amt').innerText = '₹' + parseFloat(data.amount).toLocaleString('en-IN');
        document.getElementById('v_method').innerText = data.payment_method;

        // Handle Proof File Display
        const proofLink = document.getElementById('v_proof_link');
        const noProof = document.getElementById('v_no_proof');
        
        if (data.proof_file && data.proof_file.trim() !== '') {
            let fileUrl = data.proof_file;
            if (fileUrl.indexOf('http') !== 0) fileUrl = '../' + fileUrl.replace(/^\/+/, '');
            proofLink.href = fileUrl;
            proofLink.style.display = 'inline-flex';
            noProof.style.display = 'none';
        } else {
            proofLink.style.display = 'none';
            noProof.style.display = 'block';
        }

        // Handle Button Visibility
        const actionBtns = document.getElementById('v_action_btns');
        const statusMsg = document.getElementById('v_status_msg');
        
        if (data.status === 'Pending') {
            actionBtns.style.display = 'flex';
            statusMsg.style.display = 'none';
        } else {
            actionBtns.style.display = 'none';
            statusMsg.style.display = 'block';
            statusMsg.innerHTML = `Status: <strong>${data.status}</strong>. No further action needed.`;
        }

        document.getElementById('viewExpenseModal').classList.add('active');
    }

    function triggerApprove() {
        if(!currentlyViewingId) return;
        
        Swal.fire({
            title: 'Approve this claim?',
            text: "It will be marked as ready to forward to Accounts.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            confirmButtonText: 'Yes, Approve'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'approve');
                fd.append('id', currentlyViewingId);
                
                fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        Swal.fire({icon: 'success', title: 'Approved!', showConfirmButton: false, timer: 1500})
                        .then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }

    function triggerReject() {
        if(!currentlyViewingId) return;
        // Close View Modal, Open Reject Modal
        document.getElementById('viewExpenseModal').classList.remove('active');
        document.getElementById('rejectExpId').value = currentlyViewingId;
        document.getElementById('rejectForm').reset();
        document.getElementById('rejectModal').classList.add('active');
    }

    function submitReject() {
        fetch(window.location.href, { method: 'POST', body: new FormData(document.getElementById('rejectForm')) })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({icon: 'success', title: 'Rejected!', showConfirmButton: false, timer: 1500})
                .then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function forwardSelected() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if(checkboxes.length === 0) return;

        let ids = [];
        checkboxes.forEach(cb => ids.push(cb.value));

        Swal.fire({
            title: 'Forward to Accounts?',
            text: `You are about to forward ${ids.length} approved expenses.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'Yes, Forward'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'forward');
                fd.append('ids', ids.join(','));
                
                fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        Swal.fire({icon: 'success', title: 'Forwarded!', showConfirmButton: false, timer: 1500})
                        .then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }
</script>

</body>
</html>