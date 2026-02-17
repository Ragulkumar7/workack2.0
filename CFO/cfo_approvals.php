<?php
// cfo_approvals.php
include '../sidebars.php'; 
include '../header.php';

// --- MOCK DATA ---
$summary = [
    'pending_pos' => 2,
    'pending_invs' => 3,
    'total_pending_value' => 545000
];

// Pending Requests from Accountant
$pending_requests = [
    ['id' => 'PO-IT-205', 'type' => 'Purchase Order', 'vendor_client' => 'Dell Computers', 'by' => 'Catherine (Acc)', 'amount' => 120000, 'date' => '17-Feb-2026'],
    ['id' => 'INV-2026-014', 'type' => 'Invoice', 'vendor_client' => 'Facebook India', 'by' => 'Catherine (Acc)', 'amount' => 45000, 'date' => '16-Feb-2026'],
    ['id' => 'EXP-089', 'type' => 'Expense Override', 'vendor_client' => 'Office Rent', 'by' => 'Catherine (Acc)', 'amount' => 380000, 'date' => '15-Feb-2026'],
];

// History (Recently Approved/Rejected)
$history_requests = [
    ['id' => 'PO-IT-204', 'type' => 'Purchase Order', 'vendor_client' => 'Stationery World', 'amount' => 5000, 'status' => 'Approved', 'date' => '10-Feb-2026'],
    ['id' => 'INV-2026-012', 'type' => 'Invoice', 'vendor_client' => 'Neoera', 'amount' => 8500, 'status' => 'Rejected', 'date' => '05-Feb-2026'],
];
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
        :root {
            --theme-color: #1b5a5a;
            --theme-light: #e0f2f1;
            --bg-body: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --primary-width: 95px;
        }

        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; transition: all 0.3s ease; }
        
        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .page-header p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }

        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 15px; }
        .sc-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .sc-info p { margin: 0; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .sc-info h3 { margin: 4px 0 0 0; font-size: 20px; font-weight: 800; color: var(--text-main); }

        /* Tab Navigation */
        .tabs-container { background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); overflow: hidden; }
        .tabs-header { display: flex; border-bottom: 1px solid var(--border); background: #f8fafc; overflow-x: auto; scrollbar-width: none; }
        .tabs-header::-webkit-scrollbar { display: none; }
        .tab-btn { padding: 16px 24px; background: none; border: none; font-size: 14px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 8px; }
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-badge { background: #ef4444; color: white; padding: 2px 8px; border-radius: 20px; font-size: 11px; }

        .tab-content { padding: 0; display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Data Tables */
        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th { text-align: left; padding: 16px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); background: white; }
        .data-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover { background: #f8fafc; }
        
        .req-id { font-weight: 700; color: var(--theme-color); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; }
        .req-id:hover { text-decoration: underline; }
        .amount-col { font-weight: 700; color: var(--text-main); font-size: 14px; text-align: right; }
        
        /* Badges & Buttons */
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .bg-pending { background: #fef3c7; color: #d97706; }
        .bg-approved { background: #dcfce7; color: #15803d; }
        .bg-rejected { background: #fee2e2; color: #b91c1c; }

        .action-btns { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-sm { padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-view { background: #f1f5f9; color: var(--text-main); }
        .btn-view:hover { background: #e2e8f0; }
        .btn-approve { background: var(--success); color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: var(--danger); color: white; }
        .btn-reject:hover { background: #dc2626; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 20px; }
        .modal-content { background: white; width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; color: var(--theme-color); }
        .close-modal { font-size: 20px; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: var(--danger); }
        .modal-body { padding: 20px; overflow-y: auto; max-height: 60vh; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid var(--border); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-group textarea { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; outline: none; box-sizing: border-box; }
        .form-group textarea:focus { border-color: var(--theme-color); }

        /* Toast Notification */
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 3000; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
        @keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .action-btns { flex-direction: column; gap: 5px; }
            .btn-sm { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="page-header">
        <h1>Maker-Checker: Approval Center</h1>
        <p>Review and authorize financial requests submitted by the Accounts team.</p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="sc-icon" style="background: #e0f2fe; color: #0284c7;"><i class="ph ph-shopping-cart"></i></div>
            <div class="sc-info">
                <p>Pending POs</p>
                <h3><?= $summary['pending_pos'] ?></h3>
            </div>
        </div>
        <div class="summary-card">
            <div class="sc-icon" style="background: #fef3c7; color: #d97706;"><i class="ph ph-file-text"></i></div>
            <div class="sc-info">
                <p>Pending Invoices</p>
                <h3><?= $summary['pending_invs'] ?></h3>
            </div>
        </div>
        <div class="summary-card" style="border-left: 4px solid var(--theme-color);">
            <div class="sc-icon" style="background: var(--theme-light); color: var(--theme-color);"><i class="ph ph-currency-inr"></i></div>
            <div class="sc-info">
                <p>Total Value Pending</p>
                <h3>₹<?= number_format($summary['total_pending_value']) ?></h3>
            </div>
        </div>
    </div>

    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'pending')">
                <i class="ph ph-hourglass-high"></i> Action Required <span class="tab-badge" id="badgeCount">3</span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'history')">
                <i class="ph ph-clock-counter-clockwise"></i> Approval History
            </button>
        </div>

        <div id="pending" class="tab-content active">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID & Type</th>
                            <th>Vendor / Client</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pendingTableBody">
                        <?php foreach($pending_requests as $req): 
                            $icon = $req['type'] == 'Invoice' ? 'ph-file-text' : ($req['type'] == 'Purchase Order' ? 'ph-shopping-cart' : 'ph-receipt');
                        ?>
                        <tr id="row-<?= $req['id'] ?>">
                            <td>
                                <a class="req-id" onclick="openDetailsModal('<?= $req['id'] ?>', '<?= $req['type'] ?>', '<?= $req['vendor_client'] ?>', '<?= number_format($req['amount']) ?>')">
                                    <i class="ph <?= $icon ?>"></i> <?= $req['id'] ?>
                                </a>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;"><?= $req['type'] ?></div>
                            </td>
                            <td><strong><?= $req['vendor_client'] ?></strong></td>
                            <td><?= $req['by'] ?></td>
                            <td><?= $req['date'] ?></td>
                            <td class="amount-col">₹<?= number_format($req['amount']) ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-sm btn-approve" onclick="openActionModal('<?= $req['id'] ?>', 'Approve')"><i class="ph ph-check"></i> Approve</button>
                                    <button class="btn-sm btn-reject" onclick="openActionModal('<?= $req['id'] ?>', 'Reject')"><i class="ph ph-x"></i> Reject</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="history" class="tab-content">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Vendor / Client</th>
                            <th>Date Processed</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php foreach($history_requests as $hist): 
                            $badge = $hist['status'] == 'Approved' ? 'bg-approved' : 'bg-rejected';
                            $icon = $hist['status'] == 'Approved' ? 'ph-check-circle' : 'ph-x-circle';
                        ?>
                        <tr>
                            <td><strong><?= $hist['id'] ?></strong><br><small style="color:var(--text-muted);"><?= $hist['type'] ?></small></td>
                            <td><?= $hist['vendor_client'] ?></td>
                            <td><?= $hist['date'] ?></td>
                            <td class="amount-col">₹<?= number_format($hist['amount']) ?></td>
                            <td><span class="status-badge <?= $badge ?>"><i class="ph <?= $icon ?>"></i> <?= $hist['status'] ?></span></td>
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
        <div class="modal-header">
            <h3 id="modalActionTitle">Confirm Action</h3>
            <i class="ph ph-x close-modal" onclick="closeModal('actionModal')"></i>
        </div>
        <div class="modal-body">
            <p style="font-size: 14px; margin-top: 0;">You are about to <strong id="modalActionText"></strong> request <strong id="modalReqId" style="color:var(--theme-color);"></strong>.</p>
            
            <input type="hidden" id="activeReqId">
            <input type="hidden" id="activeAction">

            <div class="form-group">
                <label>Remarks / Reason (Optional for Approval, Mandatory for Rejection)</label>
                <textarea id="actionRemarks" rows="3" placeholder="Enter any instructions or reasons here..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-sm btn-view" onclick="closeModal('actionModal')">Cancel</button>
            <button class="btn-sm" id="confirmActionBtn" onclick="executeAction()">Confirm</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="detailsModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Document Preview</h3>
            <i class="ph ph-x close-modal" onclick="closeModal('detailsModal')"></i>
        </div>
        <div class="modal-body" id="detailsBody">
            </div>
        <div class="modal-footer">
            <button class="btn-sm btn-view" onclick="closeModal('detailsModal')">Close Preview</button>
        </div>
    </div>
</div>

<div id="toast">Action saved successfully.</div>

<script>
    // --- TAB SWITCHING ---
    function switchTab(evt, tabId) {
        const tabContents = document.getElementsByClassName("tab-content");
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove("active");
        }
        const tabBtns = document.getElementsByClassName("tab-btn");
        for (let i = 0; i < tabBtns.length; i++) {
            tabBtns[i].classList.remove("active");
        }
        document.getElementById(tabId).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // --- MODAL HANDLING ---
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.getElementById('actionRemarks').value = ''; // Reset textarea
    }

    // Close on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = "none";
        }
    }

    // --- VIEW DETAILS MODAL ---
    function openDetailsModal(id, type, party, amount) {
        const body = document.getElementById('detailsBody');
        body.innerHTML = `
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: var(--theme-color); font-size:16px;">${type} Details</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                    <div><span style="color:var(--text-muted);">Request ID:</span> <br><strong>${id}</strong></div>
                    <div><span style="color:var(--text-muted);">Party:</span> <br><strong>${party}</strong></div>
                    <div><span style="color:var(--text-muted);">Requested Amount:</span> <br><strong style="color:var(--theme-color); font-size:15px;">₹${amount}</strong></div>
                    <div><span style="color:var(--text-muted);">Status:</span> <br><span class="status-badge bg-pending">Pending CFO</span></div>
                </div>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); text-align: center;">
                <em>In a production environment, the full itemized list or PDF generated by the Accountant would load here via AJAX.</em>
            </p>
        `;
        document.getElementById('detailsModal').style.display = 'flex';
    }

    // --- APPROVE/REJECT WORKFLOW ---
    function openActionModal(id, action) {
        document.getElementById('activeReqId').value = id;
        document.getElementById('activeAction').value = action;
        
        document.getElementById('modalReqId').textContent = id;
        document.getElementById('modalActionTitle').textContent = action + ' Request';
        
        const actionText = document.getElementById('modalActionText');
        const confirmBtn = document.getElementById('confirmActionBtn');
        const remarksInput = document.getElementById('actionRemarks');

        if (action === 'Approve') {
            actionText.textContent = 'APPROVE';
            actionText.style.color = 'var(--success)';
            confirmBtn.style.backgroundColor = 'var(--success)';
            confirmBtn.style.color = 'white';
            confirmBtn.innerHTML = '<i class="ph ph-check"></i> Confirm Approval';
            remarksInput.placeholder = "Optional remarks for the accountant...";
        } else {
            actionText.textContent = 'REJECT';
            actionText.style.color = 'var(--danger)';
            confirmBtn.style.backgroundColor = 'var(--danger)';
            confirmBtn.style.color = 'white';
            confirmBtn.innerHTML = '<i class="ph ph-x"></i> Confirm Rejection';
            remarksInput.placeholder = "Reason for rejection (Mandatory)...";
        }

        document.getElementById('actionModal').style.display = 'flex';
    }

    function executeAction() {
        const id = document.getElementById('activeReqId').value;
        const action = document.getElementById('activeAction').value;
        const remarks = document.getElementById('actionRemarks').value.trim();

        // Validation for Rejection
        if (action === 'Reject' && remarks === '') {
            alert("Please provide a reason for rejecting this request.");
            document.getElementById('actionRemarks').focus();
            return;
        }

        // 1. Close Modal
        closeModal('actionModal');

        // 2. Remove row from pending table with animation
        const row = document.getElementById('row-' + id);
        row.style.opacity = '0.3';
        row.style.pointerEvents = 'none';
        
        // 3. Simulate AJAX request delay
        setTimeout(() => {
            row.remove();
            
            // Move to History Table visually
            const historyBody = document.getElementById('historyTableBody');
            const dateStr = new Date().toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'}).replace(/ /g, '-');
            const badge = action === 'Approve' ? 'bg-approved' : 'bg-rejected';
            const icon = action === 'Approve' ? 'ph-check-circle' : 'ph-x-circle';
            
            // Extract some basic info from the old row to populate history (Simplified for demo)
            const newHistoryRow = `
                <tr style="background: #f0fdf4;">
                    <td><strong>${id}</strong><br><small style="color:var(--text-muted);">Processed Just Now</small></td>
                    <td>-</td>
                    <td>${dateStr}</td>
                    <td class="amount-col">-</td>
                    <td><span class="status-badge ${badge}"><i class="ph ${icon}"></i> ${action}d</span></td>
                </tr>
            `;
            historyBody.insertAdjacentHTML('afterbegin', newHistoryRow);

            // Update Badge Count
            const badgeEl = document.getElementById('badgeCount');
            let count = parseInt(badgeEl.textContent);
            if (count > 0) {
                badgeEl.textContent = count - 1;
            }

            // Show Toast
            showToast(`Request ${id} has been ${action}d successfully.`);

        }, 600);
    }

    function showToast(msg) {
        const toast = document.getElementById("toast");
        toast.textContent = msg;
        toast.className = "show";
        
        // Change color based on text content
        if(msg.includes('Reject')) {
            toast.style.backgroundColor = "var(--danger)";
        } else {
            toast.style.backgroundColor = "var(--success)";
        }

        setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
    }
</script>

</body>
</html>