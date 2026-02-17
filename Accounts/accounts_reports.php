<?php
// cfo_dashboard.php
include '../sidebars.php'; 
include '../header.php';

// --- 1. FILTER LOGIC ---
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

// --- 2. MOCK DATA (Simulating dynamic changes based on filter) ---
$multiplier = ($selected_month == date('m')) ? 1 : ($selected_month % 3 + 0.8); 

$kpi = [
    'total_income' => 1250000 * $multiplier,
    'total_expense' => 450000 * $multiplier,
    'net_profit' => (1250000 - 450000) * $multiplier,
    'pending_invoices' => 125000 * $multiplier,
    'active_employees' => 24,
    'total_clients' => 12
];

$chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$chart_income = [200000, 450000, 300000, 500000, 400000, 600000];
$chart_expense = [100000, 150000, 120000, 200000, 180000, 250000];

$mock_clients = [
    ['name' => 'Facebook India', 'gst' => '29AAACF...', 'loc' => 'Bangalore', 'mob' => '9876543210', 'total' => 450000],
    ['name' => 'Google India', 'gst' => '29GGGGG...', 'loc' => 'Hyderabad', 'mob' => '9123456780', 'total' => 1250000],
    ['name' => 'Neoera Infotech', 'gst' => '33AAAA...', 'loc' => 'Coimbatore', 'mob' => '9988776655', 'total' => 85000],
];

$mock_employees = [
    ['id' => 'EMP001', 'name' => 'Rajesh Kumar', 'dept' => 'Management', 'desig' => 'CEO', 'type' => 'Permanent', 'doj' => '2023-01-15'],
    ['id' => 'EMP002', 'name' => 'Vasanth Bro', 'dept' => 'IT', 'desig' => 'Team Lead', 'type' => 'Permanent', 'doj' => '2023-02-20'],
];

$mock_salary = [
    ['month' => 'Jan 2026', 'id' => 'EMP001', 'name' => 'Rajesh Kumar', 'basic' => 50000, 'hra' => 20000, 'deduct' => 5000, 'net' => 65000, 'status' => 'Paid'],
    ['month' => 'Jan 2026', 'id' => 'EMP002', 'name' => 'Vasanth Bro', 'basic' => 40000, 'hra' => 15000, 'deduct' => 3000, 'net' => 52000, 'status' => 'Paid'],
];

$mock_yearly = [
    ['id' => 'EMP001', 'name' => 'Rajesh Kumar', 'year' => '2025-26', 'months' => 10, 'gross' => 650000],
    ['id' => 'EMP002', 'name' => 'Vasanth Bro', 'year' => '2025-26', 'months' => 10, 'gross' => 520000],
];

$mock_ledger = [
    ['date' => '2026-02-10', 'type' => 'Income', 'cat' => 'Project', 'party' => 'Facebook India', 'desc' => 'Milestone 1', 'amount' => 500000, 'mode' => 'Credit'],
    ['date' => '2026-02-09', 'type' => 'Expense', 'cat' => 'Ops', 'party' => 'Office Rent', 'desc' => 'Feb Rent', 'amount' => 45000, 'mode' => 'Debit'],
];

$mock_po = [
    ['no' => 'PO-001', 'vendor' => 'Dell Computers', 'date' => '2026-01-10', 'grand' => 120000, 'paid' => 120000, 'bal' => 0],
    ['no' => 'PO-002', 'vendor' => 'Stationery World', 'date' => '2026-02-01', 'grand' => 5000, 'paid' => 0, 'bal' => 5000],
];

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
        .main-content { margin-left: var(--sidebar-width); padding: 30px; width: calc(100% - var(--sidebar-width)); box-sizing: border-box; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { 
            background: white; padding: 20px; border-radius: 12px; 
            border-left: 4px solid var(--theme-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: relative;
            transition: transform 0.2s;
        }
        .kpi-value { font-size: 24px; font-weight: 800; margin-top: 8px; color: var(--text-main); }

        /* Drill-Down Tabs */
        .drill-down-container { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-top: 30px; overflow: hidden; border: 1px solid #e2e8f0; }
        .tab-nav { display: flex; border-bottom: 1px solid #e2e8f0; background: #fafafa; overflow-x: auto; scrollbar-width: none; }
        .tab-nav::-webkit-scrollbar { display: none; }
        .tab-btn { 
            padding: 15px 25px; border: none; background: none; font-size: 13px; font-weight: 700; 
            color: var(--text-light); cursor: pointer; border-bottom: 2px solid transparent; 
            transition: 0.2s; white-space: nowrap; 
        }
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        
        .tab-pane { display: none; padding: 20px; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- FIX: Charts Section --- */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    height: 360px;               /* ← give it a fixed pixel height */
    display: flex;
    flex-direction: column;
    overflow: hidden;            /* ← very important here */
}

.canvas-wrapper {
    position: relative;
    flex: 1 1 auto;              /* better flex behavior */
    min-height: 0;
    width: 100%;
    padding-bottom: 20px;        /* ← breathing room at bottom if needed */
}

.canvas-wrapper canvas {
    position: absolute !important;
    inset: 0 !important;         /* force canvas to fill wrapper exactly */
}

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px 15px; background: #f8fafc; color: var(--text-light); font-size: 11px; font-weight: 700; text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }

        /* Badges */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .st-paid { background: #dcfce7; color: #15803d; }
        .st-unpaid { background: #fee2e2; color: #b91c1c; }
        .amt-pos { color: var(--success); font-weight: 600; }
        .amt-neg { color: var(--danger); font-weight: 600; }

        .btn-export-excel { 
            background: #059669; color: white; border: none; padding: 8px 15px; 
            border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 6px; float: right; margin-bottom: 15px;
        }

        @media (max-width: 768px) { 
            .main-content { margin-left: 0; width: 100%; padding: 15px; } 
            .charts-row { grid-template-columns: 1fr; } 
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="color: var(--theme-color); font-weight: 700; margin: 0;">Financial Intelligence</h2>
            <p style="color: var(--text-light); font-size: 13px; margin: 5px 0 0 0;">Executive Overview & Overall Growth</p>
        </div>
        <button class="btn-export-excel" onclick="exportFullReport()" style="background: var(--theme-color); float: none; margin: 0;">
            <i class="ph ph-file-arrow-down"></i> Export All Data
        </button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card income">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">TOTAL INCOME</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['total_income']); ?></div>
        </div>
        <div class="kpi-card expense">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">TOTAL EXPENSES</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['total_expense']); ?></div>
        </div>
        <div class="kpi-card profit">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">NET PROFIT</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['net_profit']); ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: var(--warning);">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">PENDING INVOICES</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['pending_invoices']); ?></div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-container">
            <div style="font-weight: 700; font-size: 14px; margin-bottom: 15px; color: var(--text-main);"><i class="ph ph-chart-bar"></i> Income vs Expense Trend</div>
            <div class="canvas-wrapper">
                <canvas id="financeChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <div style="font-weight: 700; font-size: 14px; margin-bottom: 15px; color: var(--text-main);"><i class="ph ph-chart-pie"></i> Payment Status</div>
            <div class="canvas-wrapper">
                <canvas id="invoiceChart"></canvas>
            </div>
        </div>
    </div>

    <div class="drill-down-container">
        <div style="padding: 15px 20px; font-weight: 800; color: var(--theme-color); border-bottom: 1px solid #eee;">
            | Departmental Drill-Down
        </div>
        <div class="tab-nav">
            <button class="tab-btn active" onclick="openTab(event, 'clients')">Client Master</button>
            <button class="tab-btn" onclick="openTab(event, 'employees')">Employee Master</button>
            <button class="tab-btn" onclick="openTab(event, 'salary')">Salary History</button>
            <button class="tab-btn" onclick="openTab(event, 'ledger')">Company Ledger</button>
            <button class="tab-btn" onclick="openTab(event, 'po')">Purchase Orders</button>
            <button class="tab-btn" onclick="openTab(event, 'invoices')">Invoices</button>
        </div>

        <div id="clients" class="tab-pane active">
            <button class="btn-export-excel" onclick="exportTable('tableClients', 'Clients_Data')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <table id="tableClients">
                <thead><tr><th>Client Name</th><th>GST</th><th>Location</th><th>Mobile</th><th style="text-align:right">Total Invoiced</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_clients as $c): ?>
                    <tr><td><b><?= $c['name'] ?></b></td><td><?= $c['gst'] ?></td><td><?= $c['loc'] ?></td><td><?= $c['mob'] ?></td><td class="amt-pos" style="text-align:right">₹<?= number_format($c['total']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="employees" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableEmployees', 'Employees')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <table id="tableEmployees">
                <thead><tr><th>ID</th><th>Name</th><th>Dept</th><th>Designation</th><th>Type</th><th>DOJ</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_employees as $e): ?>
                    <tr><td><?= $e['id'] ?></td><td><b><?= $e['name'] ?></b></td><td><?= $e['dept'] ?></td><td><?= $e['desig'] ?></td><td><?= $e['type'] ?></td><td><?= $e['doj'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="salary" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableSalary', 'Salary_History')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <table id="tableSalary">
                <thead><tr><th>Month</th><th>Name</th><th>Basic</th><th>HRA</th><th>Net Pay</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_salary as $s): ?>
                    <tr><td><?= $s['month'] ?></td><td><b><?= $s['name'] ?></b></td><td><?= number_format($s['basic']) ?></td><td><?= number_format($s['hra']) ?></td><td class="amt-pos">₹<?= number_format($s['net']) ?></td><td><span class="status-badge st-paid"><?= $s['status'] ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="ledger" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableLedger', 'Ledger')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <table id="tableLedger">
                <thead><tr><th>Date</th><th>Category</th><th>Party</th><th>Description</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_ledger as $row): ?>
                    <tr><td><?= $row['date'] ?></td><td><?= $row['cat'] ?></td><td><b><?= $row['party'] ?></b></td><td><?= $row['desc'] ?></td><td class="amt-neg" style="text-align:right"><?= $row['mode']=='Debit' ? '₹'.number_format($row['amount']) : '-' ?></td><td class="amt-pos" style="text-align:right"><?= $row['mode']=='Credit' ? '₹'.number_format($row['amount']) : '-' ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="po" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tablePO', 'Purchase_Orders')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <table id="tablePO">
                <thead><tr><th>PO No</th><th>Vendor</th><th>Date</th><th>Grand Total</th><th>Paid</th><th>Balance</th></tr></thead>
                <tbody>
                    <?php foreach ($mock_po as $po): ?>
                    <tr><td><b><?= $po['no'] ?></b></td><td><?= $po['vendor'] ?></td><td><?= $po['date'] ?></td><td>₹<?= number_format($po['grand']) ?></td><td class="amt-pos">₹<?= number_format($po['paid']) ?></td><td class="amt-neg">₹<?= number_format($po['bal']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="invoices" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableInvoices', 'Invoices')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
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
    // Tab Navigation Logic
    function openTab(evt, tabName) {
        let i, tabPane, tabBtn;
        tabPane = document.getElementsByClassName("tab-pane");
        for (i = 0; i < tabPane.length; i++) { tabPane[i].style.display = "none"; tabPane[i].classList.remove("active"); }
        tabBtn = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tabBtn.length; i++) { tabBtn[i].className = tabBtn[i].className.replace(" active", ""); }
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.className += " active";
    }

    // Charting
    const ctxFinance = document.getElementById('financeChart').getContext('2d');
    new Chart(ctxFinance, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_months); ?>,
            datasets: [
                { label: 'Income', data: <?php echo json_encode($chart_income); ?>, backgroundColor: '#059669', borderRadius: 4 },
                { label: 'Expenses', data: <?php echo json_encode($chart_expense); ?>, backgroundColor: '#dc2626', borderRadius: 4 }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [2, 2] } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'end' }
            }
        }
    });

    const ctxInvoice = document.getElementById('invoiceChart').getContext('2d');
    new Chart(ctxInvoice, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Unpaid', 'Pending'],
            datasets: [{ data: [65, 20, 15], backgroundColor: ['#059669', '#dc2626', '#d97706'], borderWidth: 0 }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
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
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableSalary')), "Salary");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableLedger')), "Ledger");
        XLSX.writeFile(wb, "Executive_Financial_Report.xlsx");
    }
</script>

</body>
</html>