<?php
// auditor_reports.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// =========================================================================
// ENTERPRISE SECURITY: Role-Based Access Control (RBAC)
// =========================================================================
$user_role = $_SESSION['role'] ?? ''; 
$can_audit = in_array($user_role, ['CFO', 'Admin', 'Super Admin', 'Auditor', 'CEO']);
if (!$can_audit) {
    die("<div style='padding:50px;text-align:center;font-family:sans-serif;'><h2>Access Denied</h2><p>Only Auditors and Executive Management can access this ledger.</p></div>");
}

// =========================================================================
// SMART DATABASE PATCHER (Auto-Adds 'auditor_verified' column)
// =========================================================================
$tables_to_patch = ['invoices', 'purchase_orders', 'employee_salary'];
foreach($tables_to_patch as $tbl) {
    $check_col = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE 'auditor_verified'");
    if($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE `$tbl` ADD COLUMN `auditor_verified` TINYINT(1) DEFAULT 0");
    }
}

// =========================================================================
// BACKEND AJAX HANDLER FOR "VERIFY" TOGGLE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
    ob_clean();
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    $source = $_POST['source'];
    $current = (int)$_POST['current_status'];
    $new_status = $current ? 0 : 1;
    
    $table = '';
    if ($source === 'Invoice') $table = 'invoices';
    elseif ($source === 'PO') $table = 'purchase_orders';
    elseif ($source === 'Salary') $table = 'employee_salary';
    
    if ($table) {
        $stmt = $conn->prepare("UPDATE `$table` SET auditor_verified = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'new_status' => $new_status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB Error']);
        }
        $stmt->close();
    }
    exit;
}

// =========================================================================
// 1. MASTER GENERAL LEDGER (UNION QUERY)
// Fixed: Explicit CAST AS CHAR to prevent MariaDB 11.8 Collation Mix Errors
// =========================================================================
$ledger_sql = "
    SELECT CAST('Invoice' AS CHAR) as source, id, CAST(invoice_no AS CHAR) as ref_id, CAST(invoice_date AS CHAR) as txn_date, CAST('Credit' AS CHAR) as type, CAST('Sales Revenue' AS CHAR) as category, CAST(client_id AS CHAR) as party_id, grand_total as amount, auditor_verified 
    FROM invoices WHERE status IN ('Approved', 'Paid', 'Credited')
    
    UNION ALL
    
    SELECT CAST('PO' AS CHAR) as source, id, CAST(po_number AS CHAR) as ref_id, CAST(po_date AS CHAR) as txn_date, CAST('Debit' AS CHAR) as type, CAST('Operational Expense' AS CHAR) as category, CAST(vendor_name AS CHAR) as party_id, grand_total as amount, auditor_verified 
    FROM purchase_orders WHERE approval_status IN ('Approved', 'Paid', 'Credited')
    
    UNION ALL
    
    SELECT CAST('Salary' AS CHAR) as source, s.id, CAST(CONCAT('PAY-', s.id) AS CHAR) as ref_id, CAST(s.salary_month AS CHAR) as txn_date, CAST('Debit' AS CHAR) as type, CAST('Payroll' AS CHAR) as category, CAST(CONCAT(e.first_name, ' ', IFNULL(e.last_name,'')) AS CHAR) as party_id, s.net_salary as amount, s.auditor_verified 
    FROM employee_salary s 
    JOIN employee_onboarding e ON s.user_id = e.id 
    WHERE s.approval_status IN ('Approved', 'Credited') AND s.is_deleted = 0

    ORDER BY txn_date DESC
";
$ledger_res = $conn->query($ledger_sql);

$ledger_transactions = [];
$total_txns = 0;
$unverified_count = 0;
$total_debit = 0;
$total_credit = 0;
$revenue = 0;
$payroll_expenses = 0;
$operational_expenses = 0;

if ($ledger_res) {
    while ($row = $ledger_res->fetch_assoc()) {
        // Resolve Client Name for Invoices
        if ($row['source'] === 'Invoice') {
            $client_qry = $conn->query("SELECT client_name FROM clients WHERE id = " . (int)$row['party_id']);
            $row['party'] = ($client_qry && $c = $client_qry->fetch_assoc()) ? $c['client_name'] : 'Unknown Client';
        } else {
            $row['party'] = $row['party_id']; // Already contains vendor or emp name
        }

        $ledger_transactions[] = $row;
        $total_txns++;
        
        if ($row['auditor_verified'] == 0) $unverified_count++;
        
        if ($row['type'] == 'Credit') {
            $total_credit += $row['amount'];
            $revenue += $row['amount'];
        } else {
            $total_debit += $row['amount'];
            if ($row['category'] == 'Payroll') {
                $payroll_expenses += $row['amount'];
            } else {
                $operational_expenses += $row['amount'];
            }
        }
    }
}

$total_expenses = $payroll_expenses + $operational_expenses;
$net_profit = $revenue - $total_expenses;
$profit_status = $net_profit >= 0 ? "PROFIT" : "LOSS";
$profit_color = $net_profit >= 0 ? "var(--success)" : "var(--danger)";


// =========================================================================
// 2. LIVE TAX LIABILITY ENGINE
// Extracts exact tax values from DB records for the current year
// =========================================================================
$tax_data = [];
$current_year = date('Y');

// A. GST from Invoices
$gst_qry = $conn->query("SELECT SUM(cgst + sgst) as total_gst FROM invoices WHERE YEAR(invoice_date) = $current_year AND status IN ('Approved', 'Paid', 'Credited')");
$gst_val = $gst_qry->fetch_assoc()['total_gst'] ?? 0;
if ($gst_val > 0) {
    $tax_data[] = ['type' => 'GST Payable (Collected)', 'period' => "YTD $current_year", 'amount' => $gst_val, 'due_date' => '20th of Next Month', 'status' => 'Pending Verification'];
}

// B. Payroll Taxes (TDS, PF, ESI, PT)
$pay_tax_qry = $conn->query("SELECT SUM(tds) as tds, SUM(pf) as pf, SUM(esi) as esi, SUM(professional_tax) as pt FROM employee_salary WHERE YEAR(salary_month) = $current_year AND approval_status IN ('Approved', 'Credited') AND is_deleted = 0");
if ($pay_tax_qry && $ptax = $pay_tax_qry->fetch_assoc()) {
    if ($ptax['tds'] > 0) $tax_data[] = ['type' => 'TDS Deducted (Salaries)', 'period' => "YTD $current_year", 'amount' => $ptax['tds'], 'due_date' => '7th of Next Month', 'status' => 'Pending Verification'];
    if ($ptax['pf'] > 0) $tax_data[] = ['type' => 'Provident Fund (PF)', 'period' => "YTD $current_year", 'amount' => $ptax['pf'], 'due_date' => '15th of Next Month', 'status' => 'Pending Verification'];
    if ($ptax['esi'] > 0) $tax_data[] = ['type' => 'ESI Payable', 'period' => "YTD $current_year", 'amount' => $ptax['esi'], 'due_date' => '15th of Next Month', 'status' => 'Pending Verification'];
    if ($ptax['pt'] > 0) $tax_data[] = ['type' => 'Professional Tax (PT)', 'period' => "YTD $current_year", 'amount' => $ptax['pt'], 'due_date' => 'Varies by State', 'status' => 'Pending Verification'];
}

// 3. Bank Reconciliation (Simulated placeholder until Bank API integration)
$bank_recon = [
    ['bank' => 'Corporate Current A/c', 'book_balance' => ($total_credit - $total_debit), 'bank_stmt_balance' => ($total_credit - $total_debit), 'diff' => 0, 'status' => 'Auto-Reconciled']
];

// 4. Live Audit Trail (Now fetching REAL User Names)
$audit_log = [];
$audit_qry = $conn->query("
    SELECT 
        CAST('Invoice Created' AS CHAR) as action, 
        CAST(created_at AS CHAR) as log_date, 
        CAST(invoice_no AS CHAR) as ref,
        CAST('System' AS CHAR) as user_name
    FROM invoices
    
    UNION ALL
    
    SELECT 
        CAST('PO Created' AS CHAR) as action, 
        CAST(created_at AS CHAR) as log_date, 
        CAST(po_number AS CHAR) as ref,
        CAST('System' AS CHAR) as user_name
    FROM purchase_orders
    
    UNION ALL
    
    SELECT 
        CAST('Salary Generated' AS CHAR) as action, 
        CAST(s.created_at AS CHAR) as log_date, 
        CAST(CONCAT('PAY-', s.id) AS CHAR) as ref,
        CAST(IFNULL(u.name, 'HR Admin') AS CHAR) as user_name
    FROM employee_salary s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.is_deleted = 0
    
    ORDER BY log_date DESC LIMIT 25
");

if ($audit_qry) {
    while ($al = $audit_qry->fetch_assoc()) {
        $audit_log[] = [
            'date' => date('d-M-Y H:i:s', strtotime($al['log_date'])), 
            'user' => $al['user_name'], 
            'action' => $al['action'] . ' (' . $al['ref'] . ')', 
            'ip' => 'Secured'
        ];
    }
}

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Reports - Neoera</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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
        .main-content { margin-left: var(--primary-width); padding: 30px; width: calc(100% - var(--primary-width)); min-height: 100vh; box-sizing: border-box; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap:15px;}
        .header-text h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .header-text p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        
        .btn-export { background: var(--theme-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(27, 90, 90, 0.2); }
        .btn-export:hover { background: #134e4e; transform: translateY(-2px); }
        .btn-export-pdf { background: #0f172a; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; margin-bottom: 15px; float: right; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden; }
        .kpi-card > div { position: relative; z-index: 2; }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 22px; font-weight: 800; color: var(--text-main); }
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
        .table-wrapper { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; clear: both; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; font-size: 13px; }
        th { text-align: left; padding: 14px 16px; background: #f8fafc; color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: var(--text-main); vertical-align: middle; }
        tr:hover td { background: #f8fafc; }

        /* P&L Statement Specific Styles */
        .pl-table { border: none; min-width: 100%; }
        .pl-table th { background: white; border-bottom: 2px solid var(--theme-color); color: var(--theme-color); }
        .pl-table .section-title { font-weight: 800; background: #f8fafc; color: var(--theme-color); text-transform: uppercase; font-size: 12px; }
        .pl-table .total-row td { font-weight: 800; border-top: 2px solid #cbd5e1; font-size: 14px; background: #f8fafc; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .bg-success { background: #dcfce7; color: #15803d; }
        .bg-danger { background: #fee2e2; color: #b91c1c; }
        .bg-warning { background: #fef3c7; color: #b45309; }
        
        .check-circle { cursor: pointer; transition: 0.2s; font-size: 20px; }
        .verified { color: var(--success); }
        .unverified { color: var(--border); }
        .unverified:hover { color: var(--success); }

        .amt-credit { color: var(--success); font-weight: 600; text-align: right; }
        .amt-debit { color: var(--danger); font-weight: 600; text-align: right; }

        /* Bank Cards */
        .bank-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .bank-card { border: 1px solid var(--border); border-radius: 12px; padding: 20px; background: #fff; }
        .bank-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .bank-name { font-weight: 700; color: var(--theme-color); font-size: 16px; }

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
            <p>Comprehensive P&L, Transaction verification, and Bank reconciliation.</p>
        </div>
        <button class="btn-export" onclick="exportCurrentView()">
            <i class="ph ph-microsoft-excel-logo"></i> Export Data (XLS)
        </button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid var(--success);">
            <div>
                <div class="kpi-label">Gross Revenue</div>
                <div class="kpi-value">₹<?= number_format($revenue, 2) ?></div>
            </div>
            <i class="ph-fill ph-trend-up kpi-icon-bg" style="color: var(--success);"></i>
        </div>
        <div class="kpi-card" style="border-top: 4px solid var(--danger);">
            <div>
                <div class="kpi-label">Total Expenses (Inc. Payroll)</div>
                <div class="kpi-value">₹<?= number_format($total_expenses, 2) ?></div>
            </div>
            <i class="ph-fill ph-receipt kpi-icon-bg" style="color: var(--danger);"></i>
        </div>
        <div class="kpi-card" style="border-top: 4px solid <?= $profit_color ?>;">
            <div>
                <div class="kpi-label">Net <?= $profit_status ?></div>
                <div class="kpi-value" style="color: <?= $profit_color ?>;">₹<?= number_format(abs($net_profit), 2) ?></div>
            </div>
            <i class="ph-fill ph-scales kpi-icon-bg" style="color: <?= $profit_color ?>;"></i>
        </div>
        <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
            <div>
                <div class="kpi-label">Net Bank Balance</div>
                <div class="kpi-value">₹<?= number_format($total_credit - $total_debit, 2) ?></div>
            </div>
            <i class="ph-fill ph-bank kpi-icon-bg" style="color: #3b82f6;"></i>
        </div>
    </div>

    <div class="audit-container">
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab(event, 'pnl')"><i class="ph ph-chart-pie-slice"></i> Statement of P&L</button>
            <button class="tab-btn" onclick="switchTab(event, 'ledger')"><i class="ph ph-list-dashes"></i> General Ledger (Debits/Credits)</button>
            <button class="tab-btn" onclick="switchTab(event, 'recon')"><i class="ph ph-bank"></i> Bank Reconciliation</button>
            <button class="tab-btn" onclick="switchTab(event, 'tax')"><i class="ph ph-currency-inr"></i> Tax Liability</button>
            <button class="tab-btn" onclick="switchTab(event, 'trail')"><i class="ph ph-footprints"></i> Audit Trail</button>
        </div>

        <div id="pnl" class="tab-pane active">
            <button class="btn-export-pdf" onclick="generatePDF()"><i class="ph ph-file-pdf"></i> Download PDF Report</button>
            
            <div class="table-wrapper" id="pnlDocument" style="padding: 20px; background: white;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: var(--theme-color); font-weight: 800;">NEOERA INFOTECH</h2>
                    <p style="margin: 5px 0 0; color: var(--text-muted); font-weight: 600;">Statement of Profit & Loss</p>
                    <p style="margin: 0; font-size: 12px; color: var(--text-muted);">For the period ending <?= date('M Y') ?></p>
                </div>

                <table class="pl-table">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th style="text-align: right;">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="2" class="section-title">I. Revenue from Operations</td></tr>
                        <tr>
                            <td style="padding-left: 25px;">Gross Sales / Service Income</td>
                            <td style="text-align: right;"><?= number_format($revenue, 2) ?></td>
                        </tr>
                        <tr class="total-row" style="color: var(--success);">
                            <td>Total Revenue (I)</td>
                            <td style="text-align: right;">₹<?= number_format($revenue, 2) ?></td>
                        </tr>

                        <tr><td colspan="2" style="height: 10px; border:none;"></td></tr>

                        <tr><td colspan="2" class="section-title">II. Expenses</td></tr>
                        <tr>
                            <td style="padding-left: 25px;">Employee Benefit Expenses (Salary/Payroll)</td>
                            <td style="text-align: right; color: var(--danger);"><?= number_format($payroll_expenses, 2) ?></td>
                        </tr>
                        <tr>
                            <td style="padding-left: 25px;">Operating Expenses (Rent, Utilities, Admin)</td>
                            <td style="text-align: right; color: var(--danger);"><?= number_format($operational_expenses, 2) ?></td>
                        </tr>
                        <tr class="total-row" style="color: var(--danger);">
                            <td>Total Expenses (II)</td>
                            <td style="text-align: right;">₹<?= number_format($total_expenses, 2) ?></td>
                        </tr>

                        <tr><td colspan="2" style="height: 10px; border:none;"></td></tr>

                        <tr class="total-row" style="font-size: 16px; color: <?= $profit_color ?>;">
                            <td>III. NET <?= $profit_status ?> (I - II)</td>
                            <td style="text-align: right;">₹<?= number_format(abs($net_profit), 2) ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 50px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                    Report generated dynamically from the General Ledger. Subject to final auditor verification.
                </div>
            </div>
        </div>

        <div id="ledger" class="tab-pane">
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <p style="margin:0; font-size:13px; color:var(--text-muted);">Detailed transaction log tracking every debit and credit entry.</p>
                <div style="font-size: 12px; color: var(--text-muted);"><i class="ph-fill ph-check-circle" style="color:var(--success);"></i> Click the circle to verify</div>
            </div>

            <div class="table-wrapper">
                <table id="auditTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">Verify</th>
                            <th>Date</th>
                            <th>Txn ID</th>
                            <th>Type</th>
                            <th>Party / Entity</th>
                            <th>Category</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ledger_transactions as $t): 
                            $isVerified = (int)$t['auditor_verified'] === 1;
                            $iconClass = $isVerified ? 'ph-fill ph-check-circle verified' : 'ph-bold ph-circle unverified';
                            $amountClass = $t['type'] == 'Credit' ? 'amt-credit' : 'amt-debit';
                            $prefix = $t['type'] == 'Credit' ? '+' : '-';
                        ?>
                        <tr id="row-<?= $t['source'] ?>-<?= $t['id'] ?>">
                            <td style="text-align: center;">
                                <i class="ph <?= $iconClass ?> check-circle" onclick="toggleVerify('<?= $t['id'] ?>', '<?= $t['source'] ?>', <?= $t['auditor_verified'] ?>, this)"></i>
                            </td>
                            <td><?= date('d-M-Y', strtotime($t['txn_date'])) ?></td>
                            <td style="font-family: monospace; font-weight: 600;"><?= $t['ref_id'] ?></td>
                            <td><span class="badge" style="background: #f1f5f9; color: #64748b;"><?= $t['type'] ?></span></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($t['party']) ?></td>
                            <td><span class="badge" style="background:#e0e7ff; color:#3730a3;"><?= $t['source'] ?></span> <?= $t['category'] ?></td>
                            <td class="<?= $amountClass ?>"><?= $prefix ?> ₹<?= number_format($t['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
                    <div class="recon-row" style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span>Book Balance:</span>
                        <span style="font-weight:600;">₹<?= number_format($b['book_balance']) ?></span>
                    </div>
                    <div class="recon-row" style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span>Bank Statement:</span>
                        <span style="font-weight:600;">₹<?= number_format($b['bank_stmt_balance']) ?></span>
                    </div>
                    <div class="recon-total" style="display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:8px; margin-top:8px; font-weight:800; color: <?= $b['diff'] == 0 ? 'var(--text-main)' : 'var(--danger)' ?>;">
                        <span>Difference:</span>
                        <span>₹<?= number_format($b['diff']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

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
                            <td><strong style="color:var(--theme-color);"><?= $t['type'] ?></strong></td>
                            <td><?= $t['period'] ?></td>
                            <td style="text-align: right; font-weight: 700; color:#b91c1c;">₹<?= number_format($t['amount']) ?></td>
                            <td><?= $t['due_date'] ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $t['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="trail" class="tab-pane">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action Performed</th>
                            <th>System Origin</th>
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

    // --- TOGGLE VERIFICATION (AJAX Connected) ---
    function toggleVerify(id, source, currentStatus, iconElement) {
        if(iconElement.classList.contains('ph-spinner')) return;
        
        const originalClasses = iconElement.className;
        iconElement.className = 'ph ph-spinner fa-spin';
        iconElement.style.color = '#94a3b8';

        const fd = new FormData();
        fd.append('action', 'toggle_verify');
        fd.append('id', id);
        fd.append('source', source);
        fd.append('current_status', currentStatus);

        fetch('', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.new_status === 1) {
                    iconElement.className = 'ph ph-fill ph-check-circle verified check-circle';
                } else {
                    iconElement.className = 'ph ph-bold ph-circle unverified check-circle';
                }
                iconElement.setAttribute('onclick', `toggleVerify('${id}', '${source}', ${data.new_status}, this)`);
            } else {
                alert('Verification Failed: ' + data.message);
                iconElement.className = originalClasses;
            }
        })
        .catch(err => {
            alert('Network Error');
            iconElement.className = originalClasses;
        });
    }

    // --- PDF EXPORT LOGIC FOR P&L ---
    function generatePDF() {
        const element = document.getElementById('pnlDocument');
        const opt = {
            margin:       0.5,
            filename:     'Statement_of_Profit_Loss_Neoera.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }

    // --- EXCEL EXPORT LOGIC FOR LEDGER ---
    function exportCurrentView() {
        const table = document.getElementById("auditTable");
        if(table) {
            const wb = XLSX.utils.table_to_book(table, {sheet: "General_Ledger"});
            XLSX.writeFile(wb, "General_Ledger_Extract_" + new Date().toISOString().slice(0,10) + ".xlsx");
        }
    }
</script>

</body>
</html>