<?php
// auditor_reports.php
include '../sidebars.php'; 
include '../header.php';

// --- MOCK DATA FOR AUDIT ---

// 1. General Ledger (All Transactions)
// In a real app, this would query the general_ledger table
 $ledger_transactions = [
    ['id' => 'TXN-1001', 'date' => '2026-02-15', 'type' => 'Credit', 'category' => 'Sales', 'party' => 'Facebook India', 'amount' => 450000, 'mode' => 'NEFT', 'bank' => 'HDFC', 'verified' => true],
    ['id' => 'TXN-1002', 'date' => '2026-02-14', 'type' => 'Debit', 'category' => 'OpEx', 'party' => 'Office Rent', 'amount' => 45000, 'mode' => 'Cheque', 'bank' => 'HDFC', 'verified' => true],
    ['id' => 'TXN-1003', 'date' => '2026-02-13', 'type' => 'Debit', 'category' => 'CapEx', 'party' => 'Dell India', 'amount' => 125000, 'mode' => 'NEFT', 'bank' => 'ICICI', 'verified' => false], // Pending Verification
    ['id' => 'TXN-1004', 'date' => '2026-02-12', 'type' => 'Credit', 'category' => 'Sales', 'party' => 'Google India', 'amount' => 120000, 'mode' => 'UPI', 'bank' => 'HDFC', 'verified' => true],
    ['id' => 'TXN-1005', 'date' => '2026-02-11', 'type' => 'Debit', 'category' => 'OpEx', 'party' => 'Amazon AWS', 'amount' => 15000, 'mode' => 'Auto-Debit', 'bank' => 'HDFC', 'verified' => true],
    ['id' => 'TXN-1006', 'date' => '2026-02-10', 'type' => 'Credit', 'category' => 'Interest', 'party' => 'HDFC Bank', 'amount' => 2500, 'mode' => 'Bank Credit', 'bank' => 'HDFC', 'verified' => true],
    ['id' => 'TXN-1007', 'date' => '2026-02-09', 'type' => 'Debit', 'category' => 'Payroll', 'party' => 'Staff Salary', 'amount' => 450000, 'mode' => 'NEFT Bulk', 'bank' => 'ICICI', 'verified' => false], // Pending Verification
    ['id' => 'TXN-1008', 'date' => '2026-02-08', 'type' => 'Debit', 'category' => 'Utilities', 'party' => 'TNEB', 'amount' => 12000, 'mode' => 'Auto-Pay', 'bank' => 'HDFC', 'verified' => true],
];

// Calculate KPIs
 $total_txns = count($ledger_transactions);
 $unverified_count = 0;
 $total_debit = 0;
 $total_credit = 0;

foreach($ledger_transactions as $t) {
    if(!$t['verified']) $unverified_count++;
    if($t['type'] == 'Debit') $total_debit += $t['amount'];
    if($t['type'] == 'Credit') $total_credit += $t['amount'];
}

// 2. Bank Reconciliation Data
 $bank_recon = [
    ['bank' => 'HDFC Business', 'book_balance' => 850000, 'bank_stmt_balance' => 842500, 'diff' => -7500, 'status' => 'Unreconciled'],
    ['bank' => 'ICICI Current', 'book_balance' => 320000, 'bank_stmt_balance' => 320000, 'diff' => 0, 'status' => 'Reconciled'],
    ['bank' => 'Canara Savings', 'book_balance' => 150000, 'bank_stmt_balance' => 148000, 'diff' => -2000, 'status' => 'Unreconciled'],
];

// 3. Tax Liability
 $tax_data = [
    ['type' => 'GST Payable (Feb)', 'period' => 'Feb 2026', 'amount' => 45000, 'due_date' => '20-Mar-2026', 'status' => 'Pending'],
    ['type' => 'TDS Deducted (Consultants)', 'period' => 'Feb 2026', 'amount' => 12000, 'due_date' => '07-Mar-2026', 'status' => 'Deposited'],
    ['type' => 'Professional Tax', 'period' => 'Feb 2026', 'amount' => 2000, 'due_date' => '15-Mar-2026', 'status' => 'Pending'],
];

// 4. Audit Trail (System Logs)
 $audit_log = [
    ['date' => '2026-02-15 10:30', 'user' => 'Catherine (Acc)', 'action' => 'Created Invoice INV-014', 'ip' => '192.168.1.45'],
    ['date' => '2026-02-15 09:15', 'user' => 'Rajesh (CFO)', 'action' => 'Approved PO-205', 'ip' => '192.168.1.10'],
    ['date' => '2026-02-14 16:45', 'user' => 'System', 'action' => 'Auto Backup Completed', 'ip' => 'Localhost'],
    ['date' => '2026-02-14 14:20', 'user' => 'Vasanth (IT)', 'action' => 'Modified Vendor Master', 'ip' => '192.168.1.55'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor & Compliance Reports - Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        .main-content { margin-left: var(--primary-width); padding: 30px; width: calc(100% - var(--primary-width)); min-height: 100vh; }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header-text h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .header-text p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        
        .btn-export { background: var(--theme-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(27, 90, 90, 0.2); }
        .btn-export:hover { background: #134e4e; transform: translateY(-2px); }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden; }
        .kpi-card > div { position: relative; z-index: 2; }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 24px; font-weight: 800; color: var(--text-main); }
        .kpi-icon-bg { position: absolute; right: -10px; bottom: -20px; font-size: 100px; opacity: 0.05; z-index: 1; }

        /* Tab Container */
        .audit-container { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid var(--border); }
        .tab-nav { display: flex; border-bottom: 1px solid var(--border); background: #f8fafc; overflow-x: auto; scrollbar-width: none; }
        .tab-nav::-webkit-scrollbar { display: none; }
        .tab-btn { padding: 18px 25px; border: none; background: none; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; transition: 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 8px; }
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-pane { display: none; padding: 24px; animation: fadeIn 0.3s ease; }
        .tab-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Tables */
        .table-wrapper { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 13px; }
        th { text-align: left; padding: 14px 16px; background: #f8fafc; color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: var(--text-main); vertical-align: middle; }
        tr:hover td { background: #f8fafc; }

        /* Status Badges */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .bg-success { background: #dcfce7; color: #15803d; }
        .bg-danger { background: #fee2e2; color: #b91c1c; }
        .bg-warning { background: #fef3c7; color: #b45309; }
        
        /* Specific for Verification */
        .check-circle { cursor: pointer; transition: 0.2s; font-size: 20px; }
        .verified { color: var(--success); }
        .unverified { color: var(--border); }
        .unverified:hover { color: var(--success); }

        .amt-credit { color: var(--success); font-weight: 600; text-align: right; }
        .amt-debit { color: var(--danger); font-weight: 600; text-align: right; }

        /* Filter Bar */
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-input { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; outline: none; }
        .filter-input:focus { border-color: var(--theme-color); }

        /* Bank Cards */
        .bank-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .bank-card { border: 1px solid var(--border); border-radius: 12px; padding: 20px; background: #fff; }
        .bank-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .bank-name { font-weight: 700; color: var(--theme-color); font-size: 16px; }
        .recon-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .recon-total { margin-top: 15px; padding-top: 10px; border-top: 1px dashed var(--border); font-weight: 700; font-size: 16px; display: flex; justify-content: space-between; }

        @media (max-width: 768px) { 
            .main-content { margin-left: 0; width: 100%; padding: 15px; } 
            .kpi-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div class="header-text">
            <h1>Auditor & Compliance Reports</h1>
            <p>Transaction verification, bank reconciliation, and audit trails.</p>
        </div>
        <button class="btn-export" onclick="exportCurrentView()">
            <i class="ph ph-download-simple"></i> Export Audit Report
        </button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Total Transactions</div>
                <div class="kpi-value"><?= $total_txns ?></div>
            </div>
            <i class="ph ph-receipt kpi-icon-bg" style="color: var(--theme-color);"></i>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Unreconciled Items</div>
                <div class="kpi-value" style="color: var(--danger);"><?= $unverified_count ?></div>
            </div>
            <i class="ph ph-warning-circle kpi-icon-bg" style="color: var(--danger);"></i>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Total Tax Liability</div>
                <div class="kpi-value">₹59,000</div>
            </div>
            <i class="ph ph-scales kpi-icon-bg" style="color: var(--warning);"></i>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Net Book Balance</div>
                <div class="kpi-value">₹<?= number_format($total_credit - $total_debit) ?></div>
            </div>
            <i class="ph ph-bank kpi-icon-bg" style="color: #3b82f6;"></i>
        </div>
    </div>

    <div class="audit-container">
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab(event, 'ledger')"><i class="ph ph-list-dashes"></i> General Ledger (Audit)</button>
            <button class="tab-btn" onclick="switchTab(event, 'recon')"><i class="ph ph-bank"></i> Bank Reconciliation</button>
            <button class="tab-btn" onclick="switchTab(event, 'tax')"><i class="ph ph-currency-inr"></i> Tax & Compliance</button>
            <button class="tab-btn" onclick="switchTab(event, 'trail')"><i class="ph ph-footprints"></i> Audit Trail Log</button>
        </div>

        <!-- TAB 1: GENERAL LEDGER -->
        <div id="ledger" class="tab-pane active">
            <div class="filter-bar">
                <input type="text" placeholder="Search Party or ID..." class="filter-input" style="width: 250px;">
                <select class="filter-input">
                    <option>All Types</option>
                    <option>Credit Only</option>
                    <option>Debit Only</option>
                </select>
                <select class="filter-input">
                    <option>All Status</option>
                    <option>Unverified Only</option>
                </select>
                <div style="margin-left: auto; font-size: 12px; color: var(--text-muted);">
                    <i class="ph ph-info"></i> Click the circle to verify transactions
                </div>
            </div>

            <div class="table-wrapper">
                <table id="auditTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Verify</th>
                            <th>Date</th>
                            <th>Txn ID</th>
                            <th>Type</th>
                            <th>Party / Entity</th>
                            <th>Category</th>
                            <th>Mode</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ledger_transactions as $t): 
                            $isVerified = $t['verified'];
                            $iconClass = $isVerified ? 'ph-check-circle verified' : 'ph-circle unverified';
                            $statusBadge = $isVerified 
                                ? '<span class="badge bg-success"><i class="ph ph-check"></i> Verified</span>' 
                                : '<span class="badge bg-warning"><i class="ph ph-clock"></i> Pending</span>';
                            
                            $amountClass = $t['type'] == 'Credit' ? 'amt-credit' : 'amt-debit';
                            $prefix = $t['type'] == 'Credit' ? '+' : '-';
                        ?>
                        <tr id="row-<?= $t['id'] ?>">
                            <td style="text-align: center;">
                                <i class="ph <?= $iconClass ?> check-circle" onclick="toggleVerify('<?= $t['id'] ?>', this)"></i>
                            </td>
                            <td><?= $t['date'] ?></td>
                            <td style="font-family: monospace; font-weight: 600;"><?= $t['id'] ?></td>
                            <td><span class="badge" style="background: #f1f5f9; color: #64748b;"><?= $t['type'] ?></span></td>
                            <td style="font-weight: 600;"><?= $t['party'] ?></td>
                            <td><?= $t['category'] ?></td>
                            <td><small><?= $t['mode'] ?> (<?= $t['bank'] ?>)</small></td>
                            <td class="<?= $amountClass ?>"><?= $prefix ?> ₹<?= number_format($t['amount']) ?></td>
                            <td id="status-<?= $t['id'] ?>"><?= $statusBadge ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: BANK RECONCILIATION -->
        <div id="recon" class="tab-pane">
            <div class="bank-grid">
                <?php foreach($bank_recon as $b): 
                    $statusColor = $b['diff'] == 0 ? 'bg-success' : 'bg-danger';
                    $statusText = $b['diff'] == 0 ? 'Reconciled' : 'Difference Found';
                ?>
                <div class="bank-card">
                    <div class="bank-header">
                        <span class="bank-name"><?= $b['bank'] ?></span>
                        <span class="badge <?= $statusColor ?>"><?= $statusText ?></span>
                    </div>
                    <div class="recon-row">
                        <span>Book Balance:</span>
                        <span>₹<?= number_format($b['book_balance']) ?></span>
                    </div>
                    <div class="recon-row">
                        <span>Bank Statement:</span>
                        <span>₹<?= number_format($b['bank_stmt_balance']) ?></span>
                    </div>
                    <div class="recon-total" style="color: <?= $b['diff'] == 0 ? 'var(--text-main)' : 'var(--danger)' ?>;">
                        <span>Difference:</span>
                        <span>₹<?= number_format($b['diff']) ?></span>
                    </div>
                    <?php if($b['diff'] != 0): ?>
                        <button style="margin-top:15px; width:100%; padding:8px; background:#f1f5f9; border:1px solid var(--border); border-radius:6px; cursor:pointer; font-size:12px; font-weight:600;">Investigate Difference</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 3: TAX & COMPLIANCE -->
        <div id="tax" class="tab-pane">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tax Type</th>
                            <th>Period</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Due Date</th>
                            <th>Filing Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tax_data as $t): 
                            $badge = $t['status'] == 'Deposited' ? 'bg-success' : 'bg-warning';
                        ?>
                        <tr>
                            <td><strong><?= $t['type'] ?></strong></td>
                            <td><?= $t['period'] ?></td>
                            <td style="text-align: right; font-weight: 700;">₹<?= number_format($t['amount']) ?></td>
                            <td><?= $t['due_date'] ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $t['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: AUDIT TRAIL -->
        <div id="trail" class="tab-pane">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action Performed</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($audit_log as $log): ?>
                        <tr>
                            <td style="font-family: monospace; color: var(--text-muted);"><?= $log['date'] ?></td>
                            <td style="font-weight: 600;"><?= $log['user'] ?></td>
                            <td><?= $log['action'] ?></td>
                            <td style="font-family: monospace;"><?= $log['ip'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    // --- TAB SWITCHING ---
    function switchTab(evt, tabId) {
        const panes = document.getElementsByClassName("tab-pane");
        const btns = document.getElementsByClassName("tab-btn");

        for (let i = 0; i < panes.length; i++) { panes[i].classList.remove("active"); }
        for (let i = 0; i < btns.length; i++) { btns[i].classList.remove("active"); }

        document.getElementById(tabId).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // --- TOGGLE VERIFICATION ---
    function toggleVerify(id, iconElement) {
        // Toggle Icon Visuals
        const isVerified = iconElement.classList.contains('verified');
        
        if (isVerified) {
            // Unverify
            iconElement.classList.remove('ph-check-circle', 'verified');
            iconElement.classList.add('ph-circle', 'unverified');
            updateStatusBadge(id, 'Pending', 'bg-warning', 'ph-clock');
        } else {
            // Verify
            iconElement.classList.remove('ph-circle', 'unverified');
            iconElement.classList.add('ph-check-circle', 'verified');
            updateStatusBadge(id, 'Verified', 'bg-success', 'ph-check');
        }
    }

    function updateStatusBadge(id, text, bgClass, phIcon) {
        const statusCell = document.getElementById('status-' + id);
        statusCell.innerHTML = `<span class="badge ${bgClass}"><i class="ph ${phIcon}"></i> ${text}</span>`;
    }

    // --- EXPORT LOGIC ---
    function exportCurrentView() {
        const table = document.getElementById("auditTable");
        if(table) {
            const wb = XLSX.utils.table_to_book(table, {sheet: "Audit_Ledger"});
            XLSX.writeFile(wb, "Auditor_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
        } else {
            alert("Please switch to the General Ledger tab to export data.");
        }
    }
</script>

</body>
</html>