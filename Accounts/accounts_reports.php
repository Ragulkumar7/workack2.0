<?php 
// accounts_reports.php

// --- 1. INCLUDE COMMON FILES ---
include '../sidebars.php'; 
include '../header.php';

// --- 2. MOCK DATA GENERATION ---

// KPI Metrics (Power BI Style Data)
$kpi = [
    'total_income' => 1250000,
    'total_expense' => 450000,
    'net_profit' => 800000,
    'pending_invoices' => 125000,
    'active_employees' => 24,
    'total_clients' => 12
];

// Chart Data (Mocking Monthly Financials)
$chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$chart_income = [200000, 450000, 300000, 500000, 400000, 600000];
$chart_expense = [100000, 150000, 120000, 200000, 180000, 250000];

// 1. Client Master & Payment Details
$mock_clients = [
    ['name' => 'Facebook India', 'gst' => '29AAACF...', 'loc' => 'Bangalore', 'mob' => '9876543210', 'total' => 450000],
    ['name' => 'Google India', 'gst' => '29GGGGG...', 'loc' => 'Hyderabad', 'mob' => '9123456780', 'total' => 1250000],
    ['name' => 'Neoera Infotech', 'gst' => '33AAAA...', 'loc' => 'Coimbatore', 'mob' => '9988776655', 'total' => 85000],
];

// 2. Employee Master
$mock_employees = [
    ['id' => 'EMP001', 'name' => 'Rajesh Kumar', 'dept' => 'Management', 'desig' => 'CEO', 'type' => 'Permanent', 'doj' => '2023-01-15'],
    ['id' => 'EMP002', 'name' => 'Vasanth Bro', 'dept' => 'IT', 'desig' => 'Team Lead', 'type' => 'Permanent', 'doj' => '2023-02-20'],
];

// 3. Individual Salary History
$mock_salary = [
    ['month' => 'Jan 2026', 'id' => 'EMP001', 'name' => 'Rajesh Kumar', 'basic' => 50000, 'hra' => 20000, 'deduct' => 5000, 'net' => 65000, 'status' => 'Paid'],
    ['month' => 'Jan 2026', 'id' => 'EMP002', 'name' => 'Vasanth Bro', 'basic' => 40000, 'hra' => 15000, 'deduct' => 3000, 'net' => 52000, 'status' => 'Paid'],
];

// 4. Employee Yearly Summary
$mock_yearly = [
    ['id' => 'EMP001', 'name' => 'Rajesh Kumar', 'year' => '2025-26', 'months' => 10, 'gross' => 650000],
    ['id' => 'EMP002', 'name' => 'Vasanth Bro', 'year' => '2025-26', 'months' => 10, 'gross' => 520000],
];

// 5. Ledger (Income & Expenses)
$mock_ledger = [
    ['date' => '2026-02-10', 'type' => 'Income', 'cat' => 'Project', 'party' => 'Facebook India', 'desc' => 'Milestone 1', 'amount' => 500000, 'mode' => 'Credit'],
    ['date' => '2026-02-09', 'type' => 'Expense', 'cat' => 'Ops', 'party' => 'Office Rent', 'desc' => 'Feb Rent', 'amount' => 45000, 'mode' => 'Debit'],
];

// 6. Purchase Orders
$mock_po = [
    ['no' => 'PO-001', 'vendor' => 'Dell Computers', 'date' => '2026-01-10', 'grand' => 120000, 'paid' => 120000, 'bal' => 0],
    ['no' => 'PO-002', 'vendor' => 'Stationery World', 'date' => '2026-02-01', 'grand' => 5000, 'paid' => 0, 'bal' => 5000],
];

// 7. Invoices
$mock_invoices = [
    ['no' => 'INV-001', 'client' => 'Facebook', 'date' => '2026-01-20', 'total' => 47200, 'status' => 'Paid'],
    ['no' => 'INV-002', 'client' => 'Neoera', 'date' => '2026-02-02', 'total' => 11800, 'status' => 'Unpaid'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Reports - Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --theme-color: #1b5a5a;
            --theme-dark: #134e4e;
            --accent-gold: #D4AF37;
            --bg-body: #f3f4f6;
            --text-main: #1e293b;
            --text-light: #64748b;
            --success: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --sidebar-width: 95px; 
        }

        body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }

        .main-content { margin-left: var(--sidebar-width); padding: 30px; width: calc(100% - var(--sidebar-width)); }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { margin: 0; color: var(--theme-color); font-size: 24px; font-weight: 700; }
        .page-header p { color: var(--text-light); font-size: 13px; margin: 4px 0 0; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { 
            background: white; padding: 20px; border-radius: 12px; 
            border-left: 4px solid var(--theme-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: relative;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-card.income { border-color: var(--success); }
        .kpi-card.expense { border-color: var(--danger); }
        .kpi-card.profit { border-color: var(--theme-color); }
        .kpi-card.pending { border-color: var(--warning); }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; }
        .kpi-value { font-size: 24px; font-weight: 800; margin-top: 8px; color: var(--text-main); }
        .kpi-icon { position: absolute; right: 20px; bottom: 20px; font-size: 40px; opacity: 0.1; }

        /* Filter Toolbar */
        .filter-toolbar { 
            background: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
            border: 1px solid #e2e8f0;
        }
        .date-group { display: flex; align-items: center; gap: 10px; }
        .date-group label { font-size: 12px; font-weight: 600; color: var(--text-light); }
        .date-group input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; }
        .btn-action { 
            padding: 9px 18px; border-radius: 8px; border: none; font-size: 12px; font-weight: 700; 
            cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; 
        }
        .btn-filter { background: var(--theme-color); color: white; }
        .btn-export { background: #059669; color: white; }

        /* Charts */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 320px; }
        .chart-title { font-size: 14px; font-weight: 700; margin-bottom: 15px; color: var(--theme-color); display: flex; align-items: center; gap: 8px; }

        /* Report Tables */
        .report-card { background: white; border-radius: 12px; overflow: hidden; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
        .report-header { 
            padding: 15px 20px; border-bottom: 1px solid #f1f5f9; display: flex; 
            justify-content: space-between; align-items: center; background: #fafafa;
        }
        .report-title { font-size: 14px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { text-align: left; padding: 12px 20px; background: #fff; color: var(--text-light); font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
        td { padding: 12px 20px; border-bottom: 1px solid #f8fafc; font-size: 13px; }
        
        /* Utility */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .st-paid { background: #dcfce7; color: #15803d; }
        .st-unpaid { background: #fee2e2; color: #b91c1c; }
        .st-pending { background: #fef3c7; color: #b45309; }
        .amt-pos { color: var(--success); font-weight: 600; }
        .amt-neg { color: var(--danger); font-weight: 600; }
        
        .btn-sm-download { background:transparent; border:1px solid #e2e8f0; color:var(--text-light); padding:5px 10px; border-radius:6px; cursor:pointer; font-size:11px; font-weight:600; }
        .btn-sm-download:hover { background:var(--theme-color); color:white; border-color:var(--theme-color); }

        @media (max-width: 1024px) { .charts-row { grid-template-columns: 1fr; height: auto; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; width: 100%; } .filter-toolbar { flex-direction: column; align-items: flex-start; } .btn-export { width: 100%; justify-content: center; } }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">

    <div class="page-header">
        <div>
            <h2>Financial Intelligence</h2>
            <p>Real-time insights, operational data, and detailed reports.</p>
        </div>
        <div>
            <span style="font-size:12px; background: #e0f2f1; padding: 5px 10px; border-radius:6px; color: var(--theme-color); font-weight:600;">FY 2026-27</span>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card income">
            <div class="kpi-label">Total Income</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['total_income']); ?></div>
            <i class="ph ph-trend-up kpi-icon" style="color:var(--success)"></i>
        </div>
        <div class="kpi-card expense">
            <div class="kpi-label">Total Expenses</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['total_expense']); ?></div>
            <i class="ph ph-trend-down kpi-icon" style="color:var(--danger)"></i>
        </div>
        <div class="kpi-card profit">
            <div class="kpi-label">Net Profit</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['net_profit']); ?></div>
            <i class="ph ph-wallet kpi-icon" style="color:var(--theme-color)"></i>
        </div>
        <div class="kpi-card pending">
            <div class="kpi-label">Pending Invoices</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['pending_invoices']); ?></div>
            <i class="ph ph-clock-countdown kpi-icon" style="color:var(--warning)"></i>
        </div>
    </div>

    <div class="filter-toolbar">
        <div class="date-group">
            <label>Period:</label>
            <input type="date" value="<?php echo date('Y-m-01'); ?>">
            <span style="color:#cbd5e1">-</span>
            <input type="date" value="<?php echo date('Y-m-d'); ?>">
            <button class="btn-action btn-filter"><i class="ph ph-funnel"></i> Filter</button>
        </div>
        <button class="btn-action btn-export" onclick="exportFullReport()">
            <i class="ph ph-microsoft-excel-logo"></i> Export All Sheets
        </button>
    </div>

    <div class="charts-row">
        <div class="chart-container">
            <div class="chart-title"><i class="ph ph-chart-bar"></i> Income vs Expense Trend</div>
            <canvas id="financeChart"></canvas>
        </div>
        <div class="chart-container">
            <div class="chart-title"><i class="ph ph-chart-pie-slice"></i> Invoice Status</div>
            <div style="height: 250px; display:flex; justify-content:center;">
                <canvas id="invoiceChart"></canvas>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-user-circle"></i> Client Master & Payment Details</div>
            <button class="btn-sm-download" onclick="exportTable('tableClients', 'Clients_Report')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tableClients">
                <thead><tr><th>Client Name</th><th>GST</th><th>Location</th><th>Mobile</th><th style="text-align:right">Total Invoiced</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_clients as $c): ?>
                    <tr>
                        <td style="font-weight:600"><?= $c['name'] ?></td><td><?= $c['gst'] ?></td><td><?= $c['loc'] ?></td><td><?= $c['mob'] ?></td>
                        <td class="amt-pos" style="text-align:right">₹<?= number_format($c['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-users"></i> Employee Master Report</div>
            <button class="btn-sm-download" onclick="exportTable('tableEmployees', 'Employee_Report')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tableEmployees">
                <thead><tr><th>ID</th><th>Name</th><th>Department</th><th>Designation</th><th>Type</th><th>DOJ</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_employees as $e): ?>
                    <tr><td><?= $e['id'] ?></td><td style="font-weight:600"><?= $e['name'] ?></td><td><?= $e['dept'] ?></td><td><?= $e['desig'] ?></td><td><?= $e['type'] ?></td><td><?= $e['doj'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-money"></i> Individual Salary History</div>
            <button class="btn-sm-download" onclick="exportTable('tableSalary', 'Salary_History')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tableSalary">
                <thead><tr><th>Month</th><th>Name</th><th>Basic</th><th>HRA</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_salary as $s): ?>
                    <tr>
                        <td><?= $s['month'] ?></td><td><?= $s['name'] ?></td><td><?= number_format($s['basic']) ?></td><td><?= number_format($s['hra']) ?></td>
                        <td style="color:#dc2626"><?= number_format($s['deduct']) ?></td><td style="font-weight:700; color:#059669"><?= number_format($s['net']) ?></td>
                        <td><span class="status-badge st-paid"><?= $s['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-calendar"></i> Employee Yearly Salary Summary</div>
            <button class="btn-sm-download" onclick="exportTable('tableYearly', 'Yearly_Salary')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tableYearly">
                <thead><tr><th>ID</th><th>Name</th><th>Fin. Year</th><th>Months Paid</th><th>Gross Yearly Paid</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_yearly as $y): ?>
                    <tr><td><?= $y['id'] ?></td><td><b><?= $y['name'] ?></b></td><td><?= $y['year'] ?></td><td><?= $y['months'] ?></td><td style="font-weight:700">₹<?= number_format($y['gross']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-arrows-left-right"></i> Overall Company Income & Expenses (Ledger)</div>
            <button class="btn-sm-download" onclick="exportTable('tableLedger', 'Ledger_Export')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tableLedger">
                <thead><tr><th>Date</th><th>Category</th><th>Party</th><th>Description</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_ledger as $row): ?>
                    <tr>
                        <td><?= $row['date'] ?></td><td><?= $row['cat'] ?></td><td><b><?= $row['party'] ?></b></td><td><?= $row['desc'] ?></td>
                        <td style="text-align:right;" class="<?= $row['mode']=='Debit' ? 'amt-neg' : '' ?>"><?= $row['mode']=='Debit' ? '₹'.number_format($row['amount']) : '-' ?></td>
                        <td style="text-align:right;" class="<?= $row['mode']=='Credit' ? 'amt-pos' : '' ?>"><?= $row['mode']=='Credit' ? '₹'.number_format($row['amount']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-shopping-cart"></i> Purchase Orders Report</div>
            <button class="btn-sm-download" onclick="exportTable('tablePO', 'PO_Report')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tablePO">
                <thead><tr><th>PO No</th><th>Vendor</th><th>Date</th><th>Grand Total</th><th>Paid</th><th>Balance</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_po as $po): ?>
                    <tr><td><b><?= $po['no'] ?></b></td><td><?= $po['vendor'] ?></td><td><?= $po['date'] ?></td><td><?= number_format($po['grand']) ?></td><td style="color:#059669"><?= number_format($po['paid']) ?></td><td style="color:#dc2626"><?= number_format($po['bal']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div class="report-title"><i class="ph ph-file-text"></i> Invoices Report</div>
            <button class="btn-sm-download" onclick="exportTable('tableInvoices', 'Invoice_Report')">XLS</button>
        </div>
        <div class="table-responsive">
            <table id="tableInvoices">
                <thead><tr><th>Invoice #</th><th>Client</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_invoices as $inv): ?>
                    <tr><td><b><?= $inv['no'] ?></b></td><td><?= $inv['client'] ?></td><td><?= $inv['date'] ?></td><td>₹<?= number_format($inv['total']) ?></td><td><span class="status-badge <?= $inv['status']=='Paid'?'st-paid':'st-unpaid' ?>"><?= $inv['status'] ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
    // --- 1. CHARTS CONFIGURATION ---
    const ctxFinance = document.getElementById('financeChart').getContext('2d');
    const ctxInvoice = document.getElementById('invoiceChart').getContext('2d');

    new Chart(ctxFinance, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_months); ?>,
            datasets: [
                { label: 'Income', data: <?php echo json_encode($chart_income); ?>, backgroundColor: '#059669', borderRadius: 4 },
                { label: 'Expenses', data: <?php echo json_encode($chart_expense); ?>, backgroundColor: '#dc2626', borderRadius: 4 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } }
    });

    new Chart(ctxInvoice, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Unpaid', 'Pending'],
            datasets: [{ data: [65, 20, 15], backgroundColor: ['#059669', '#dc2626', '#d97706'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    // --- 2. EXCEL EXPORT LOGIC ---
    function exportTable(tableId, filename) {
        const table = document.getElementById(tableId);
        const wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
        XLSX.writeFile(wb, filename + ".xlsx");
    }

    function exportFullReport() {
        const wb = XLSX.utils.book_new();
        
        function addSheet(id, name) {
            const el = document.getElementById(id);
            if(el) XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(el), name);
        }
        addSheet('tableClients', 'Clients');
        addSheet('tableEmployees', 'Employees');
        addSheet('tableSalary', 'SalaryHistory');
        addSheet('tableYearly', 'YearlySalary');
        addSheet('tableLedger', 'Ledger');
        addSheet('tablePO', 'PurchaseOrders');
        addSheet('tableInvoices', 'Invoices');

        XLSX.writeFile(wb, "Workack_Full_Management_Report.xlsx");
    }
</script>

</body>
</html>