<?php
// accounts_reports.php
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
$can_view = in_array($user_role, ['Accounts', 'CFO', 'Admin', 'Super Admin', 'Management', 'CEO']);
if (!$can_view) {
    die("<div style='padding:50px;text-align:center;font-family:sans-serif;'><h2>Access Denied</h2><p>You do not have clearance to view Master Financial Reports.</p></div>");
}

// --- FILTER LOGIC ---
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// =========================================================================
// 2. ENTERPRISE KPI CALCULATIONS (YTD for Selected Year)
// =========================================================================
$kpi = [
    'total_income' => 0,
    'total_expense' => 0,
    'net_profit' => 0,
    'pending_invoices' => 0, // Accounts Receivable
    'active_employees' => 0,
    'total_clients' => 0
];

// A. Total Income (Invoices)
$inc_stmt = $conn->prepare("SELECT SUM(grand_total) as total FROM invoices WHERE YEAR(invoice_date) = ? AND status IN ('Paid', 'Approved', 'Credited', 'Partial')");
$inc_stmt->bind_param("i", $selected_year);
$inc_stmt->execute();
$kpi['total_income'] = $inc_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$inc_stmt->close();

// B. Total Expense (Purchase Orders + Salaries)
$po_stmt = $conn->prepare("SELECT SUM(grand_total) as total FROM purchase_orders WHERE YEAR(po_date) = ? AND approval_status IN ('Approved', 'Paid', 'Credited')");
$po_stmt->bind_param("i", $selected_year);
$po_stmt->execute();
$po_exp = $po_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$po_stmt->close();

$sal_stmt = $conn->prepare("SELECT SUM(gross_salary) as total FROM employee_salary WHERE YEAR(salary_month) = ? AND approval_status IN ('Approved', 'Credited') AND is_deleted = 0");
$sal_stmt->bind_param("i", $selected_year);
$sal_stmt->execute();
$sal_exp = $sal_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$sal_stmt->close();

$kpi['total_expense'] = $po_exp + $sal_exp; 
$kpi['net_profit'] = $kpi['total_income'] - $kpi['total_expense'];

// C. Accounts Receivable (Pending / Sent Invoices)
$pend_stmt = $conn->prepare("SELECT SUM(grand_total) as total FROM invoices WHERE YEAR(invoice_date) = ? AND status IN ('Pending Approval', 'Sent', 'Draft', 'Overdue')");
$pend_stmt->bind_param("i", $selected_year);
$pend_stmt->execute();
$kpi['pending_invoices'] = $pend_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$pend_stmt->close();

// D. Database Counts
$kpi['active_employees'] = $conn->query("SELECT COUNT(*) as total FROM employee_onboarding WHERE status = 'Completed'")->fetch_assoc()['total'] ?? 0;
$kpi['total_clients'] = $conn->query("SELECT COUNT(*) as total FROM clients")->fetch_assoc()['total'] ?? 0;


// =========================================================================
// 3. OPTIMIZED CHART DATA GENERATOR (Eliminates N+1 Query loop)
// =========================================================================
$chart_income_data = array_fill(0, 12, 0);
$chart_expense_data = array_fill(0, 12, 0);

// Group Invoices by Month
$chk_inc = $conn->prepare("SELECT MONTH(invoice_date) as m, SUM(grand_total) as val FROM invoices WHERE YEAR(invoice_date) = ? AND status IN ('Paid', 'Approved', 'Credited', 'Partial') GROUP BY MONTH(invoice_date)");
$chk_inc->bind_param("i", $selected_year);
$chk_inc->execute();
$res_inc = $chk_inc->get_result();
while ($row = $res_inc->fetch_assoc()) { $chart_income_data[$row['m'] - 1] = (float)$row['val']; }
$chk_inc->close();

// Group POs by Month
$chk_po = $conn->prepare("SELECT MONTH(po_date) as m, SUM(grand_total) as val FROM purchase_orders WHERE YEAR(po_date) = ? AND approval_status IN ('Approved', 'Paid', 'Credited') GROUP BY MONTH(po_date)");
$chk_po->bind_param("i", $selected_year);
$chk_po->execute();
$res_po = $chk_po->get_result();
while ($row = $res_po->fetch_assoc()) { $chart_expense_data[$row['m'] - 1] += (float)$row['val']; }
$chk_po->close();

// Group Salaries by Month
$chk_sal = $conn->prepare("SELECT MONTH(salary_month) as m, SUM(gross_salary) as val FROM employee_salary WHERE YEAR(salary_month) = ? AND approval_status IN ('Approved', 'Credited') AND is_deleted = 0 GROUP BY MONTH(salary_month)");
$chk_sal->bind_param("i", $selected_year);
$chk_sal->execute();
$res_sal = $chk_sal->get_result();
while ($row = $res_sal->fetch_assoc()) { $chart_expense_data[$row['m'] - 1] += (float)$row['val']; }
$chk_sal->close();


// =========================================================================
// 4. FETCH REAL DATA FOR TABS
// =========================================================================
// Tab 1: Clients
$real_clients = [];
$client_sql = "
    SELECT c.id, c.client_name, c.gst_number, c.mobile_number, c.payment_method, COALESCE(inv.total_invoiced, 0) as total_invoiced, (COALESCE(inv.total_invoiced, 0) - COALESCE(ldg.total_paid, 0)) as account_balance
    FROM clients c
    LEFT JOIN (SELECT client_id, SUM(grand_total) as total_invoiced FROM invoices WHERE status NOT IN ('Draft', 'Rejected') GROUP BY client_id) inv ON c.id = inv.client_id
    LEFT JOIN (SELECT TRIM(LOWER(party_name)) as p_name, SUM(credit_amount) as total_paid FROM general_ledger WHERE credit_amount > 0 GROUP BY TRIM(LOWER(party_name))) ldg ON TRIM(LOWER(c.client_name)) = ldg.p_name
";
$c_res = $conn->query($client_sql);
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) {
        $real_clients[] = ['name' => $row['client_name'], 'gst' => $row['gst_number'] ?: 'N/A', 'mob' => $row['mobile_number'] ?: 'N/A', 'payment_method' => $row['payment_method'] ?: 'N/A', 'total' => $row['total_invoiced'], 'balance' => $row['account_balance']];
    }
}

// Tab 2: Employees (Migrated to employee_onboarding and added Salary)
$real_employees = [];
$emp_sql = "SELECT emp_id_code, CONCAT(first_name, ' ', IFNULL(last_name,'')) as full_name, department, designation, joining_date, salary, salary_type FROM employee_onboarding WHERE status = 'Completed'";
$e_res = $conn->query($emp_sql);
if ($e_res) {
    while ($row = $e_res->fetch_assoc()) {
        $real_employees[] = [
            'id' => $row['emp_id_code'] ?: 'N/A', 
            'name' => $row['full_name'], 
            'dept' => $row['department'], 
            'desig' => $row['designation'], 
            'doj' => $row['joining_date'] ? date('d-M-Y', strtotime($row['joining_date'])) : 'N/A',
            'salary' => $row['salary'] ? (float)$row['salary'] : 0,
            'salary_type' => $row['salary_type'] ?: 'Annual'
        ];
    }
}

// Tab 3: Purchase Orders
$real_po = [];
$po_sql = "SELECT po_number, vendor_name, po_date, grand_total, paid_amount, balance_amount FROM purchase_orders ORDER BY created_at DESC";
$po_res = $conn->query($po_sql);
if ($po_res) {
    while ($row = $po_res->fetch_assoc()) {
        $real_po[] = ['no' => $row['po_number'], 'vendor' => $row['vendor_name'], 'date' => date('d-M-Y', strtotime($row['po_date'])), 'total' => $row['grand_total'], 'paid' => $row['paid_amount'], 'balance' => $row['balance_amount']];
    }
}

// Tab 4: Invoices
$real_inv = [];
$inv_sql = "SELECT i.invoice_no, c.client_name, i.invoice_date, i.grand_total, i.status FROM invoices i LEFT JOIN clients c ON i.client_id = c.id ORDER BY i.created_at DESC";
$inv_res = $conn->query($inv_sql);
if ($inv_res) {
    while ($row = $inv_res->fetch_assoc()) {
        $real_inv[] = ['no' => $row['invoice_no'], 'client' => $row['client_name'] ?? 'Unknown', 'date' => date('d-M-Y', strtotime($row['invoice_date'])), 'total' => $row['grand_total'], 'status' => $row['status']];
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
    <title>Master Accounts Report | Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f3f4f6; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; --primary-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-width); padding: 30px; box-sizing: border-box; min-height: 100vh;}

        /* Headers & Buttons */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .page-header h2 { margin: 0; color: var(--theme-color); font-weight: 800; font-size: 24px; }
        .page-header p { margin: 4px 0 0 0; color: var(--text-muted); font-size: 13px;}
        .btn-export { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13px; transition: 0.2s; box-shadow: 0 4px 10px rgba(16,185,129,0.2);}
        .btn-export:hover { transform: translateY(-2px); filter: brightness(1.05); }
        
        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 22px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--border-color); border-left: 4px solid var(--theme-color); position: relative; overflow: hidden;}
        .kpi-val { font-size: 24px; font-weight: 800; margin-top: 6px; color: var(--text-main); z-index: 2; position: relative;}
        .kpi-title { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; z-index: 2; position: relative;}
        .kpi-icon { position: absolute; right: -10px; bottom: -15px; font-size: 90px; opacity: 0.05; z-index: 1;}

        /* Tabs */
        .report-card { background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 30px; }
        .tabs-header { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); overflow-x: auto; }
        .tab-btn { padding: 16px 24px; background: none; border: none; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; white-space: nowrap; }
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-pane { display: none; padding: 20px; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Charts Section */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--border-color); height: 360px; display: flex; flex-direction: column; overflow: hidden; }
        .canvas-wrapper { position: relative; flex: 1 1 auto; min-height: 0; width: 100%; }
        .chart-container h3 { margin: 0 0 15px 0; font-size: 16px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;}

        /* Tables */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 800px;} /* Increased min-width for the new column */
        th { text-align: left; padding: 14px 16px; background: #f8fafc; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 800;}
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
        tr:hover td { background: #f8fafc; }
        .amt-pos { color: #059669; font-weight: 700; }
        .amt-neg { color: #dc2626; font-weight: 700; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid transparent; display: inline-block;}
        .st-paid { background: #dcfce7; color: #16a34a; border-color: #bbf7d0;}
        .st-pend { background: #fef9c3; color: #d97706; border-color: #fde047;}
        .st-over { background: #fee2e2; color: #dc2626; border-color: #fecaca;}
        .st-appr { background: #dbeafe; color: #0284c7; border-color: #bae6fd;}
        .st-draft { background: #f1f5f9; color: #64748b; border-color: #e2e8f0;}

        @media (max-width: 1024px) { .charts-row { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; padding-top: 80px; } }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>Master Financial Reports</h2>
            <p>Comprehensive overview of accounts, clients, payroll, and company health</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <select name="year" style="padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; outline: none; cursor: pointer;">
                    <?php 
                        $curr_yr = date('Y');
                        for($y = $curr_yr; $y >= 2023; $y--) {
                            $sel = ($selected_year == $y) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                    ?>
                </select>
                <button type="submit" style="background: var(--theme-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor:pointer;">Filter</button>
            </form>
            <button class="btn-export" onclick="exportFullReport()"><i class="ph-bold ph-microsoft-excel-logo"></i> Export Book</button>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <i class="ph-fill ph-trend-up kpi-icon" style="color: #059669;"></i>
            <div class="kpi-title">Gross Revenue (Invoices)</div>
            <div class="kpi-val" style="color: #059669;">₹<?= number_format($kpi['total_income'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #dc2626;">
            <i class="ph-fill ph-trend-down kpi-icon" style="color: #dc2626;"></i>
            <div class="kpi-title">Total Expenses (PO + Payroll)</div>
            <div class="kpi-val" style="color: #dc2626;">₹<?= number_format($kpi['total_expense'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #3b82f6;">
            <i class="ph-fill ph-scales kpi-icon" style="color: #3b82f6;"></i>
            <div class="kpi-title">Net Profit Margin</div>
            <div class="kpi-val" style="color: <?= $kpi['net_profit'] >= 0 ? '#3b82f6' : '#dc2626' ?>;">₹<?= number_format($kpi['net_profit'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #f59e0b;">
            <i class="ph-fill ph-hourglass-high kpi-icon" style="color: #f59e0b;"></i>
            <div class="kpi-title">Accounts Recv. (Pending Inv)</div>
            <div class="kpi-val" style="color: #f59e0b;">₹<?= number_format($kpi['pending_invoices'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #8b5cf6;">
            <i class="ph-fill ph-database kpi-icon" style="color: #8b5cf6;"></i>
            <div class="kpi-title">Active Database</div>
            <div class="kpi-val" style="font-size: 18px; margin-top: 8px; color: #6b7280;"><b><?= $kpi['total_clients'] ?></b> Clients | <b><?= $kpi['active_employees'] ?></b> Staff</div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-container">
            <h3><i class="ph-fill ph-chart-line-up" style="color:var(--theme-color)"></i> Income vs Expense Trend (<?= $selected_year ?>)</h3>
            <div class="canvas-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <h3><i class="ph-fill ph-chart-pie-slice" style="color:var(--theme-color)"></i> Invoice Status Overview</h3>
            <div class="canvas-wrapper">
                <canvas id="invChart"></canvas>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-clients')"><i class="ph-bold ph-buildings mr-1"></i> Client Billing</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-emp')"><i class="ph-bold ph-users mr-1"></i> Employee Data</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-po')"><i class="ph-bold ph-shopping-cart mr-1"></i> Purchase Orders</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-inv')"><i class="ph-bold ph-receipt mr-1"></i> Invoices Ledger</button>
        </div>

        <div id="tab-clients" class="tab-pane active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-size:16px; font-weight:800;">Client Billing & A/R Overview</h3>
                <button class="btn-export" style="background:#f1f5f9; color:#0f172a; box-shadow:none; border:1px solid #cbd5e1;" onclick="exportTable('tableClients', 'Client_Report')"><i class="ph-bold ph-download-simple"></i> Download List</button>
            </div>
            <div class="table-responsive">
                <table id="tableClients">
                    <thead>
                        <tr>
                            <th style="width: 50px;">S.No</th>
                            <th>Client Name</th>
                            <th>GST / Tax ID</th>
                            <th>Mobile Number</th>
                            <th>Payment Terms</th>
                            <th style="text-align:right">Total Invoiced (₹)</th>
                            <th style="text-align:right">Outstanding Balance (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($real_clients)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 30px; color:#94a3b8;">No clients found.</td></tr>
                        <?php else: ?>
                            <?php $sno = 1; foreach ($real_clients as $c): ?>
                            <tr>
                                <td><?= $sno++ ?></td>
                                <td><b><?= htmlspecialchars($c['name']) ?></b></td>
                                <td><span style="font-family: monospace; color:#475569;"><?= htmlspecialchars($c['gst']) ?></span></td>
                                <td><?= htmlspecialchars($c['mob']) ?></td>
                                <td><span style="background: #f8fafc; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #e2e8f0;"><?= htmlspecialchars($c['payment_method']) ?></span></td>
                                <td class="amt-pos" style="text-align:right"><?= number_format($c['total'], 2) ?></td>
                                <td style="text-align:right; font-weight: 800; color: <?= $c['balance'] > 0 ? '#dc2626' : '#1b5a5a' ?>;"><?= number_format($c['balance'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-emp" class="tab-pane">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-size:16px; font-weight:800;">Active Employee Roster</h3>
                <button class="btn-export" style="background:#f1f5f9; color:#0f172a; box-shadow:none; border:1px solid #cbd5e1;" onclick="exportTable('tableEmployees', 'Employee_Report')"><i class="ph-bold ph-download-simple"></i> Download List</button>
            </div>
            <div class="table-responsive">
                <table id="tableEmployees">
                    <thead>
                        <tr>
                            <th>Emp ID</th>
                            <th>Full Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Date of Join</th>
                            <th style="text-align:right">Base Salary (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($real_employees)): ?>
                            <tr><td colspan="6" style="text-align:center; padding: 30px; color:#94a3b8;">No active employees found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($real_employees as $e): ?>
                            <tr>
                                <td><b style="color:var(--theme-color);"><?= htmlspecialchars($e['id']) ?></b></td>
                                <td style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($e['name']) ?></td>
                                <td><span style="background: #f3e8ff; color: #7e22ce; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #e9d5ff;"><?= htmlspecialchars($e['dept']) ?></span></td>
                                <td><?= htmlspecialchars($e['desig']) ?></td>
                                <td><?= $e['doj'] ?></td>
                                <td style="text-align:right;">
                                    <span class="amt-pos" style="display:block;">₹<?= number_format($e['salary'], 2) ?></span>
                                    <span style="font-size:10px; font-weight:700; color:var(--text-muted); text-transform:uppercase;"><?= htmlspecialchars($e['salary_type']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-po" class="tab-pane">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-size:16px; font-weight:800;">Purchase Order Ledger (COGS)</h3>
                <button class="btn-export" style="background:#f1f5f9; color:#0f172a; box-shadow:none; border:1px solid #cbd5e1;" onclick="exportTable('tablePO', 'PO_Report')"><i class="ph-bold ph-download-simple"></i> Download List</button>
            </div>
            <div class="table-responsive">
                <table id="tablePO">
                    <thead><tr><th>PO Number</th><th>Vendor</th><th>Date</th><th style="text-align:right">Total (₹)</th><th style="text-align:right">Paid (₹)</th><th style="text-align:right">Balance (₹)</th></tr></thead>
                    <tbody>
                        <?php if(empty($real_po)): ?>
                            <tr><td colspan="6" style="text-align:center; padding: 30px; color:#94a3b8;">No purchase orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($real_po as $p): ?>
                            <tr>
                                <td><b style="color:var(--theme-color);"><?= htmlspecialchars($p['no']) ?></b></td>
                                <td><?= htmlspecialchars($p['vendor']) ?></td>
                                <td><?= $p['date'] ?></td>
                                <td style="text-align:right; font-weight:700;"><?= number_format($p['total'], 2) ?></td>
                                <td class="amt-pos" style="text-align:right"><?= number_format($p['paid'], 2) ?></td>
                                <td class="amt-neg" style="text-align:right"><?= number_format($p['balance'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-inv" class="tab-pane">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-size:16px; font-weight:800;">Invoice Master Ledger</h3>
                <button class="btn-export" style="background:#f1f5f9; color:#0f172a; box-shadow:none; border:1px solid #cbd5e1;" onclick="exportTable('tableInv', 'Invoice_Report')"><i class="ph-bold ph-download-simple"></i> Download List</button>
            </div>
            <div class="table-responsive">
                <table id="tableInv">
                    <thead><tr><th>Invoice No</th><th>Client</th><th>Date</th><th style="text-align:right">Total Amount (₹)</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if(empty($real_inv)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 30px; color:#94a3b8;">No invoices found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($real_inv as $i): 
                                $bg = 'st-pend';
                                if($i['status'] == 'Paid' || strpos(strtolower($i['status']), 'credit') !== false) $bg = 'st-paid';
                                if($i['status'] == 'Approved') $bg = 'st-appr';
                                if($i['status'] == 'Draft') $bg = 'st-draft';
                                if($i['status'] == 'Rejected' || strpos(strtolower($i['status']), 'overdue') !== false) $bg = 'st-over';
                            ?>
                            <tr>
                                <td><b style="color:var(--theme-color);"><?= htmlspecialchars($i['no']) ?></b></td>
                                <td style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($i['client']) ?></td>
                                <td><?= $i['date'] ?></td>
                                <td class="amt-pos" style="text-align:right"><?= number_format($i['total'], 2) ?></td>
                                <td><span class="status-badge <?= $bg ?>"><?= htmlspecialchars($i['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
    function switchTab(evt, id) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    // Chart.js Implementations
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [
                {
                    label: 'Gross Income (₹)',
                    data: <?= json_encode($chart_income_data) ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.15)',
                    borderWidth: 3,
                    pointBackgroundColor: '#059669',
                    fill: true, tension: 0.4
                },
                {
                    label: 'Total Expense (₹)',
                    data: <?= json_encode($chart_expense_data) ?>,
                    borderColor: '#dc2626',
                    backgroundColor: 'transparent',
                    borderWidth: 3,
                    pointBackgroundColor: '#dc2626',
                    borderDash: [5,5], tension: 0.4
                }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { 
                legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ₹' + context.raw.toLocaleString(); } } }
            },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    grid: { borderDash: [4,4], color: '#e2e8f0' },
                    ticks: { callback: function(value) { return '₹' + (value/1000).toFixed(1) + 'K'; } }
                }, 
                x: { grid: { display: false } } 
            }
        }
    });

    // Calculate dynamic data for Donut Chart
    <?php
        $paid = 0; $pend = 0; $over = 0;
        foreach($real_inv as $inv) {
            $stat = strtolower($inv['status']);
            if($stat == 'paid' || $stat == 'approved' || strpos($stat, 'credit') !== false) $paid++;
            elseif($stat == 'pending approval' || $stat == 'draft' || $stat == 'sent') $pend++;
            else $over++;
        }
    ?>
    const invData = [<?= $paid ?>, <?= $pend ?>, <?= $over ?>];
    const sumInv = invData.reduce((a,b)=>a+b, 0);

    const invCtx = document.getElementById('invChart').getContext('2d');
    new Chart(invCtx, {
        type: 'doughnut',
        data: {
            labels: ['Paid / Approved', 'Pending / Draft', 'Rejected / Overdue'],
            datasets: [{
                data: sumInv === 0 ? [1] : invData,
                backgroundColor: sumInv === 0 ? ['#f1f5f9'] : ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0, hoverOffset: 4
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: {size: 11} } },
                tooltip: { enabled: sumInv !== 0 }
            }
        }
    });

    // Excel Logic
    function exportTable(tableId, filename) {
        const table = document.getElementById(tableId);
        const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
        const dateStr = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, filename + "_" + dateStr + ".xlsx");
    }

    function exportFullReport() {
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableClients')), "Clients");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableEmployees')), "Employees");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tablePO')), "Purchase Orders");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableInv')), "Invoices");
        
        const dateStr = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, "Master_Financial_Report_" + dateStr + ".xlsx");
    }
</script>

</body>
</html>