<?php
// accounts_reports.php
include '../sidebars.php'; 
include '../header.php';

// --- DATABASE CONNECTION ---
require_once '../include/db_connect.php';

// --- 1. FILTER LOGIC ---
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

// --- 2. FETCH REAL DATA FROM DATABASE ---

// KPIs
$kpi = [
    'total_income' => 0,
    'total_expense' => 0,
    'net_profit' => 0,
    'pending_invoices' => 0,
    'unpaid_invoices' => 0,
    'active_employees' => 0,
    'total_clients' => 0
];

// Total Income (from Paid Invoices)
$inc_query = $conn->query("SELECT SUM(grand_total) as total FROM invoices WHERE status = 'Paid'");
if ($inc_query && $row = $inc_query->fetch_assoc()) {
    $kpi['total_income'] = $row['total'] ?? 0;
}

// Total Expense (From General Ledger or Purchase Orders)
$po_exp = 0;
$po_query = $conn->query("SELECT SUM(grand_total) as total FROM purchase_orders");
if ($po_query && $row = $po_query->fetch_assoc()) {
    $po_exp = $row['total'] ?? 0;
}
$kpi['total_expense'] = $po_exp; 

// Net Profit
$kpi['net_profit'] = $kpi['total_income'] - $kpi['total_expense'];

// Pending Invoices
$pend_inv = $conn->query("SELECT SUM(grand_total) as total FROM invoices WHERE status = 'Pending Approval' OR status = 'Draft'");
if ($pend_inv && $row = $pend_inv->fetch_assoc()) {
    $kpi['pending_invoices'] = $row['total'] ?? 0;
}

// Active Employees
$emp_count = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE status = 'Active'");
if ($emp_count && $row = $emp_count->fetch_assoc()) {
    $kpi['active_employees'] = $row['total'] ?? 0;
}

// Total Clients
$client_count = $conn->query("SELECT COUNT(*) as total FROM clients");
if ($client_count && $row = $client_count->fetch_assoc()) {
    $kpi['total_clients'] = $row['total'] ?? 0;
}


// --- FETCH REAL DATA FOR TABS ---
// 1. Clients Data (Smart Matching Ledger with Invoices)
$real_clients = [];
$client_sql = "
    SELECT 
        c.id, 
        c.client_name, 
        c.gst_number, 
        c.mobile_number, 
        c.payment_method,
        COALESCE(inv.total_invoiced, 0) as total_invoiced,
        (COALESCE(inv.total_invoiced, 0) - COALESCE(ldg.total_paid, 0)) as account_balance
    FROM clients c
    LEFT JOIN (
        SELECT client_id, SUM(grand_total) as total_invoiced 
        FROM invoices 
        WHERE status NOT IN ('Draft', 'Rejected')
        GROUP BY client_id
    ) inv ON c.id = inv.client_id
    LEFT JOIN (
        SELECT TRIM(LOWER(party_name)) as p_name, SUM(credit_amount) as total_paid 
        FROM general_ledger 
        WHERE credit_amount > 0 
        GROUP BY TRIM(LOWER(party_name))
    ) ldg ON TRIM(LOWER(c.client_name)) = ldg.p_name
";

$c_res = $conn->query($client_sql);
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) {
        $real_clients[] = [
            'name' => $row['client_name'],
            'gst' => $row['gst_number'] ? $row['gst_number'] : 'N/A',
            'mob' => $row['mobile_number'] ? $row['mobile_number'] : 'N/A',
            'payment_method' => $row['payment_method'] ? $row['payment_method'] : 'N/A',
            'total' => $row['total_invoiced'],
            'balance' => $row['account_balance']
        ];
    }
}
// 2. Employees Data
$real_employees = [];
$emp_sql = "SELECT emp_id_code, full_name, department, designation, joining_date FROM employee_profiles WHERE status = 'Active'";
$e_res = $conn->query($emp_sql);
if ($e_res) {
    while ($row = $e_res->fetch_assoc()) {
        $real_employees[] = [
            'id' => $row['emp_id_code'] ?? 'N/A',
            'name' => $row['full_name'],
            'dept' => $row['department'],
            'desig' => $row['designation'],
            'type' => 'Permanent', 
            'doj' => $row['joining_date'] ? date('d-m-Y', strtotime($row['joining_date'])) : 'N/A'
        ];
    }
}

// 3. Purchase Orders Data
$real_po = [];
$po_sql = "SELECT po_number, vendor_name, po_date, grand_total, paid_amount, balance_amount FROM purchase_orders ORDER BY created_at DESC";
$po_res = $conn->query($po_sql);
if ($po_res) {
    while ($row = $po_res->fetch_assoc()) {
        $real_po[] = [
            'no' => $row['po_number'],
            'vendor' => $row['vendor_name'],
            'date' => date('d-m-Y', strtotime($row['po_date'])),
            'total' => $row['grand_total'],
            'paid' => $row['paid_amount'],
            'balance' => $row['balance_amount']
        ];
    }
}

// 4. Invoices Data
$real_inv = [];
$inv_sql = "SELECT i.invoice_no, c.client_name, i.invoice_date, i.grand_total, i.status 
            FROM invoices i 
            LEFT JOIN clients c ON i.client_id = c.id 
            ORDER BY i.created_at DESC";
$inv_res = $conn->query($inv_sql);
if ($inv_res) {
    while ($row = $inv_res->fetch_assoc()) {
        $real_inv[] = [
            'no' => $row['invoice_no'],
            'client' => $row['client_name'] ?? 'Unknown',
            'date' => date('d-m-Y', strtotime($row['invoice_date'])),
            'total' => $row['grand_total'],
            'status' => $row['status']
        ];
    }
}

// Chart Logic
$chart_income_data = [];
$chart_expense_data = [];
for ($m=1; $m<=12; $m++) {
    $inc_m = $conn->query("SELECT SUM(grand_total) as val FROM invoices WHERE MONTH(invoice_date) = $m AND YEAR(invoice_date) = '$selected_year' AND status='Paid'");
    $chart_income_data[] = ($inc_m && $r = $inc_m->fetch_assoc()) ? ($r['val'] ?? 0) : 0;

    $exp_m = $conn->query("SELECT SUM(grand_total) as val FROM purchase_orders WHERE MONTH(po_date) = $m AND YEAR(po_date) = '$selected_year'");
    $chart_expense_data[] = ($exp_m && $r = $exp_m->fetch_assoc()) ? ($r['val'] ?? 0) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Reports | Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f3f4f6; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; color: var(--text-main); }
        .main-content { margin-left: 95px; padding: 30px; }

        /* Headers & Buttons */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .page-header h2 { margin: 0; color: var(--theme-color); font-weight: 700; font-size: 24px; }
        .btn-export { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13px; }
        
        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--border-color); border-left: 4px solid var(--theme-color); }
        .kpi-val { font-size: 22px; font-weight: 800; margin-top: 5px; color: var(--text-main); }
        .kpi-title { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        
        /* Tabs */
        .report-card { background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 30px; }
        .tabs-header { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); overflow-x: auto; }
        .tab-btn { 
            padding: 15px 25px; background: none; border: none; font-size: 13px; font-weight: 700; 
            color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; 
            transition: 0.2s; white-space: nowrap; 
        }
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        
        .tab-pane { display: none; padding: 20px; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Charts Section */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-container {
            background: white; padding: 20px; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 360px;
            display: flex; flex-direction: column; overflow: hidden;
        }

        .canvas-wrapper { position: relative; flex: 1 1 auto; min-height: 0; width: 100%; }
        .chart-container h3 { margin: 0 0 15px 0; font-size: 15px; color: var(--text-main); }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 15px; background: #f1f5f9; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .amt-pos { color: #059669; font-weight: 700; }
        .amt-neg { color: #dc2626; font-weight: 700; }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; display: inline-block; }
        .st-paid { background: #dcfce7; color: #16a34a; }
        .st-pend { background: #fef9c3; color: #d97706; }
        .st-over { background: #fee2e2; color: #dc2626; }
        .st-appr { background: #dcfce7; color: #16a34a; }
        .st-draft { background: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>Master Financial Reports</h2>
            <p>Comprehensive overview of accounts, clients, and company health</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="year" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    <option value="2026" <?= $selected_year=='2026'?'selected':'' ?>>2026</option>
                    <option value="2025" <?= $selected_year=='2025'?'selected':'' ?>>2025</option>
                </select>
                <button type="submit" style="background: var(--theme-color); color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor:pointer;">Filter</button>
            </form>
            <button class="btn-export" onclick="exportFullReport()"><i class="ph-bold ph-microsoft-excel-logo"></i> Export All to Excel</button>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Gross Revenue (Paid)</div>
            <div class="kpi-val" style="color: #059669;">₹<?= number_format($kpi['total_income'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #dc2626;">
            <div class="kpi-title">Total PO / Expenses</div>
            <div class="kpi-val" style="color: #dc2626;">₹<?= number_format($kpi['total_expense'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #3b82f6;">
            <div class="kpi-title">Net Profit Margin</div>
            <div class="kpi-val" style="color: #3b82f6;">₹<?= number_format($kpi['net_profit'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #f59e0b;">
            <div class="kpi-title">Pending Invoices (A/R)</div>
            <div class="kpi-val" style="color: #f59e0b;">₹<?= number_format($kpi['pending_invoices'], 2) ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: #8b5cf6;">
            <div class="kpi-title">Active Database</div>
            <div class="kpi-val" style="font-size: 16px; margin-top: 8px;"><?= $kpi['total_clients'] ?> Clients | <?= $kpi['active_employees'] ?> Staff</div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-container">
            <h3>Revenue vs Expense Trend (<?= $selected_year ?>)</h3>
            <div class="canvas-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <h3>Invoice Status Breakdown</h3>
            <div class="canvas-wrapper">
                <canvas id="invChart"></canvas>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-clients')">Client Billing</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-emp')">Employee Data</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-po')">Purchase Orders</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-inv')">Invoices List</button>
        </div>

        <div id="tab-clients" class="tab-pane active">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="margin:0; font-size:16px;">Client Billing Overview</h3>
                <button class="btn-export" onclick="exportTable('tableClients', 'Client_Report')"><i class="ph ph-download-simple"></i> Excel</button>
            </div>
            <div style="overflow-x: auto;">
                <table id="tableClients">
                    <thead>
                        <tr>
                            <th style="width: 50px;">S.No</th>
                            <th>Client Name</th>
                            <th>GST</th>
                            <th>Mobile Number</th>
                            <th>Payment Method</th>
                            <th style="text-align:right">Total Invoiced</th>
                            <th style="text-align:right">Total Account Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sno = 1; foreach ($real_clients as $c): ?>
                        <tr>
                            <td><?= $sno++ ?></td>
                            <td><b><?= htmlspecialchars($c['name']) ?></b></td>
                            <td><?= htmlspecialchars($c['gst']) ?></td>
                            <td><?= htmlspecialchars($c['mob']) ?></td>
                            <td><span style="background: #f8fafc; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid #e2e8f0;"><?= htmlspecialchars($c['payment_method']) ?></span></td>
                            <td class="amt-pos" style="text-align:right">₹<?= number_format($c['total'], 2) ?></td>
                            <td style="text-align:right; font-weight: 700; color: #1b5a5a;">₹<?= number_format($c['balance'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-emp" class="tab-pane">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="margin:0; font-size:16px;">Employee Directory</h3>
                <button class="btn-export" onclick="exportTable('tableEmployees', 'Employee_Report')"><i class="ph ph-download-simple"></i> Excel</button>
            </div>
            <div style="overflow-x: auto;">
                <table id="tableEmployees">
                    <thead><tr><th>Emp ID</th><th>Name</th><th>Department</th><th>Designation</th><th>Date of Join</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_employees as $e): ?>
                        <tr>
                            <td><b><?= $e['id'] ?></b></td>
                            <td><?= htmlspecialchars($e['name']) ?></td>
                            <td><?= htmlspecialchars($e['dept']) ?></td>
                            <td><?= htmlspecialchars($e['desig']) ?></td>
                            <td><?= $e['doj'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-po" class="tab-pane">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="margin:0; font-size:16px;">Purchase Order Report</h3>
                <button class="btn-export" onclick="exportTable('tablePO', 'PO_Report')"><i class="ph ph-download-simple"></i> Excel</button>
            </div>
            <div style="overflow-x: auto;">
                <table id="tablePO">
                    <thead><tr><th>PO Number</th><th>Vendor</th><th>Date</th><th style="text-align:right">Total</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_po as $p): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($p['no']) ?></b></td>
                            <td><?= htmlspecialchars($p['vendor']) ?></td>
                            <td><?= $p['date'] ?></td>
                            <td style="text-align:right">₹<?= number_format($p['total'], 2) ?></td>
                            <td class="amt-pos" style="text-align:right">₹<?= number_format($p['paid'], 2) ?></td>
                            <td class="amt-neg" style="text-align:right">₹<?= number_format($p['balance'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-inv" class="tab-pane">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="margin:0; font-size:16px;">Invoice Aging & Status</h3>
                <button class="btn-export" onclick="exportTable('tableInv', 'Invoice_Report')"><i class="ph ph-download-simple"></i> Excel</button>
            </div>
            <div style="overflow-x: auto;">
                <table id="tableInv">
                    <thead><tr><th>Invoice No</th><th>Client</th><th>Date</th><th style="text-align:right">Total Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_inv as $i): 
                            $bg = 'st-pend';
                            if($i['status'] == 'Paid') $bg = 'st-paid';
                            if($i['status'] == 'Approved') $bg = 'st-appr';
                            if($i['status'] == 'Draft') $bg = 'st-draft';
                            if($i['status'] == 'Rejected' || strpos(strtolower($i['status']), 'overdue') !== false) $bg = 'st-over';
                        ?>
                        <tr>
                            <td><b><?= htmlspecialchars($i['no']) ?></b></td>
                            <td><?= htmlspecialchars($i['client']) ?></td>
                            <td><?= $i['date'] ?></td>
                            <td class="amt-pos" style="text-align:right">₹<?= number_format($i['total'], 2) ?></td>
                            <td><span class="status-badge <?= $bg ?>"><?= htmlspecialchars($i['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
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
                    label: 'Income (₹)',
                    data: <?= json_encode($chart_income_data) ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    fill: true, tension: 0.4
                },
                {
                    label: 'Expense (₹)',
                    data: <?= json_encode($chart_expense_data) ?>,
                    borderColor: '#dc2626',
                    backgroundColor: 'transparent',
                    borderDash: [5,5], tension: 0.4
                }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } } },
            scales: { y: { beginAtZero: true, grid: { borderDash: [2,2] } }, x: { grid: { display: false } } }
        }
    });

    // Calculate dynamic data for Donut Chart
    <?php
        $paid = 0; $pend = 0; $over = 0;
        foreach($real_inv as $inv) {
            if($inv['status'] == 'Paid' || $inv['status'] == 'Approved') $paid++;
            elseif($inv['status'] == 'Pending Approval' || $inv['status'] == 'Draft') $pend++;
            else $over++;
        }
    ?>
    const invData = [<?= $paid ?>, <?= $pend ?>, <?= $over ?>];
    const sumInv = invData.reduce((a,b)=>a+b, 0);

    const invCtx = document.getElementById('invChart').getContext('2d');
    new Chart(invCtx, {
        type: 'doughnut',
        data: {
            labels: ['Paid/Appr.', 'Pending', 'Rejected/Overdue'],
            datasets: [{
                data: sumInv === 0 ? [1] : invData,
                backgroundColor: sumInv === 0 ? ['#e2e8f0'] : ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
                tooltip: { enabled: sumInv !== 0 }
            }
        }
    });

    // Excel Logic
    function exportTable(tableId, filename) {
        const table = document.getElementById(tableId);
        const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
        XLSX.writeFile(wb, filename + ".xlsx");
    }

    function exportFullReport() {
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableClients')), "Clients");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableEmployees')), "Employees");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tablePO')), "Purchase Orders");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableInv')), "Invoices");
        XLSX.writeFile(wb, "Master_Financial_Report.xlsx");
    }
</script>

</body>
</html>