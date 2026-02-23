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

// Unpaid Invoices (Approved but not paid yet)
$unpaid_inv = $conn->query("SELECT SUM(grand_total) as total FROM invoices WHERE status = 'Approved' OR status = 'Unpaid'");
if ($unpaid_inv && $row = $unpaid_inv->fetch_assoc()) {
    $kpi['unpaid_invoices'] = $row['total'] ?? 0;
}

// Total Expense (From Purchase Orders + Salary)
$po_exp = $conn->query("SELECT SUM(grand_total) as total FROM purchase_orders WHERE approval_status = 'Approved'")->fetch_assoc()['total'] ?? 0;
// Assuming you have a salary history table, if not just using POs for now
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

// 1. Clients Data
$real_clients = [];
$client_sql = "SELECT c.client_name, c.id, SUM(i.grand_total) as total_invoiced 
               FROM clients c 
               LEFT JOIN invoices i ON c.id = i.client_id 
               GROUP BY c.id";
$c_res = $conn->query($client_sql);
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) {
        $real_clients[] = [
            'name' => $row['client_name'],
            'gst' => 'N/A', // Update if you add GST to clients table
            'loc' => 'N/A', // Update if you add Location
            'mob' => 'N/A', // Update if you add Mobile
            'total' => $row['total_invoiced'] ?? 0
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
            'type' => 'Permanent', // Update if tracking emp_type
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
            'grand' => $row['grand_total'],
            'paid' => $row['paid_amount'],
            'bal' => $row['balance_amount']
        ];
    }
}

// 4. Invoices Data
$real_invoices = [];
$inv_sql = "SELECT i.invoice_no, c.client_name, i.invoice_date, i.grand_total, i.status 
            FROM invoices i 
            LEFT JOIN clients c ON i.client_id = c.id 
            ORDER BY i.created_at DESC";
$inv_res = $conn->query($inv_sql);
if ($inv_res) {
    while ($row = $inv_res->fetch_assoc()) {
        $real_invoices[] = [
            'no' => $row['invoice_no'],
            'client' => $row['client_name'] ?? 'Unknown',
            'date' => date('d-m-Y', strtotime($row['invoice_date'])),
            'total' => $row['grand_total'],
            'status' => $row['status']
        ];
    }
}

// Keeping Mock for Salary & Ledger until tables exist
$mock_salary = [
    ['month' => date('M Y'), 'id' => 'EMP-007', 'name' => 'Aparna M A', 'basic' => 30000, 'hra' => 800, 'deduct' => 6250, 'net' => 24050, 'status' => 'Paid']
];

$mock_ledger = [
    ['date' => '2026-02-21', 'type' => 'Income', 'cat' => 'Invoice', 'party' => 'Arvind Builders', 'desc' => 'INV-2026-02-491', 'amount' => 4956, 'mode' => 'Credit'],
    ['date' => '2026-02-21', 'type' => 'Expense', 'cat' => 'PO', 'party' => 'prem', 'desc' => 'PO-20260221-691', 'amount' => 24.53, 'mode' => 'Debit'],
];

// Chart Data (Mocked for trend visualization, build dynamic later based on months)
$chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$chart_income = [20000, 146556, 0, 0, 0, 0];
$chart_expense = [10000, 29.53, 0, 0, 0, 0];

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
        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 30px; 
            width: calc(100% - var(--sidebar-width)); 
            box-sizing: border-box; 
            z-index: 1; /* Add z-index to stay below fixed header if you have one */
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

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
        .st-paid, .st-approved { background: #dcfce7; color: #15803d; }
        .st-unpaid, .st-rejected { background: #fee2e2; color: #b91c1c; }
        .st-pending, .st-draft { background: #fef3c7; color: #d97706; }
        .amt-pos { color: var(--success); font-weight: 600; }
        .amt-neg { color: var(--danger); font-weight: 600; }

        .btn-export-excel { 
            background: #059669; color: white; border: none; padding: 8px 15px; 
            border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 6px; float: right; margin-bottom: 15px;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        @media (max-width: 992px) { 
            .main-content { 
                margin-left: 0; 
                width: 100%; 
                padding: 15px; 
                /* Ensures the content sits below a fixed mobile header, adjust padding-top if needed based on your header height */
                padding-top: 80px; 
            } 
            .charts-row { grid-template-columns: 1fr; } 
            
            /* Add some spacing for header wrapping on small screens */
            .page-header {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .btn-export-excel {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2 style="color: var(--theme-color); font-weight: 700; margin: 0;">Financial Intelligence</h2>
            <p style="color: var(--text-light); font-size: 13px; margin: 5px 0 0 0;">Executive Overview & Overall Growth</p>
        </div>
        <button class="btn-export-excel" onclick="exportFullReport()" style="background: var(--theme-color); margin: 0;">
            <i class="ph ph-file-arrow-down"></i> Export All Data
        </button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card income">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">TOTAL INCOME (PAID)</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['total_income'], 2); ?></div>
        </div>
        <div class="kpi-card expense">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">TOTAL EXPENSES (PO)</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['total_expense'], 2); ?></div>
        </div>
        <div class="kpi-card profit">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">NET PROFIT</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['net_profit'], 2); ?></div>
        </div>
        <div class="kpi-card" style="border-left-color: var(--warning);">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-light);">PENDING INVOICES</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['pending_invoices'], 2); ?></div>
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
            <div class="table-responsive">
                <table id="tableClients">
                    <thead><tr><th>Client Name</th><th>GST</th><th>Location</th><th>Mobile</th><th style="text-align:right">Total Invoiced</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_clients as $c): ?>
                        <tr><td><b><?= htmlspecialchars($c['name']) ?></b></td><td><?= $c['gst'] ?></td><td><?= $c['loc'] ?></td><td><?= $c['mob'] ?></td><td class="amt-pos" style="text-align:right">₹<?= number_format($c['total'], 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="employees" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableEmployees', 'Employees')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <div class="table-responsive">
                <table id="tableEmployees">
                    <thead><tr><th>ID</th><th>Name</th><th>Dept</th><th>Designation</th><th>Type</th><th>DOJ</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_employees as $e): ?>
                        <tr><td><?= htmlspecialchars($e['id']) ?></td><td><b><?= htmlspecialchars($e['name']) ?></b></td><td><?= htmlspecialchars($e['dept']) ?></td><td><?= htmlspecialchars($e['desig']) ?></td><td><?= $e['type'] ?></td><td><?= $e['doj'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="salary" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableSalary', 'Salary_History')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <div class="table-responsive">
                <table id="tableSalary">
                    <thead><tr><th>Month</th><th>Name</th><th>Basic</th><th>HRA</th><th>Net Pay</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($mock_salary as $s): ?>
                        <tr><td><?= $s['month'] ?></td><td><b><?= $s['name'] ?></b></td><td><?= number_format($s['basic']) ?></td><td><?= number_format($s['hra']) ?></td><td class="amt-pos">₹<?= number_format($s['net']) ?></td><td><span class="status-badge st-paid"><?= $s['status'] ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="ledger" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableLedger', 'Ledger')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <div class="table-responsive">
                <table id="tableLedger">
                    <thead><tr><th>Date</th><th>Category</th><th>Party</th><th>Description</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th></tr></thead>
                    <tbody>
                        <?php foreach ($mock_ledger as $row): ?>
                        <tr><td><?= $row['date'] ?></td><td><?= $row['cat'] ?></td><td><b><?= $row['party'] ?></b></td><td><?= $row['desc'] ?></td><td class="amt-neg" style="text-align:right"><?= $row['mode']=='Debit' ? '₹'.number_format($row['amount'],2) : '-' ?></td><td class="amt-pos" style="text-align:right"><?= $row['mode']=='Credit' ? '₹'.number_format($row['amount'],2) : '-' ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="po" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tablePO', 'Purchase_Orders')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <div class="table-responsive">
                <table id="tablePO">
                    <thead><tr><th>PO No</th><th>Vendor</th><th>Date</th><th>Grand Total</th><th>Paid</th><th>Balance</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_po as $po): ?>
                        <tr><td><b><?= htmlspecialchars($po['no']) ?></b></td><td><?= htmlspecialchars($po['vendor']) ?></td><td><?= $po['date'] ?></td><td>₹<?= number_format($po['grand'], 2) ?></td><td class="amt-pos">₹<?= number_format($po['paid'], 2) ?></td><td class="amt-neg">₹<?= number_format($po['bal'], 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="invoices" class="tab-pane">
            <button class="btn-export-excel" onclick="exportTable('tableInvoices', 'Invoices')"><i class="ph ph-microsoft-excel-logo"></i> XLS</button>
            <div class="table-responsive">
                <table id="tableInvoices">
                    <thead><tr><th>Invoice #</th><th>Client</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($real_invoices as $inv): 
                            $st_class = 'st-pending';
                            if ($inv['status'] === 'Paid' || $inv['status'] === 'Approved') $st_class = 'st-paid';
                            if ($inv['status'] === 'Unpaid' || $inv['status'] === 'Rejected') $st_class = 'st-unpaid';
                        ?>
                        <tr><td><b><?= htmlspecialchars($inv['no']) ?></b></td><td><?= htmlspecialchars($inv['client']) ?></td><td><?= $inv['date'] ?></td><td>₹<?= number_format($inv['total'], 2) ?></td><td><span class="status-badge <?= $st_class ?>"><?= htmlspecialchars($inv['status']) ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

    const invData = [<?php echo $kpi['total_income']; ?>, <?php echo $kpi['unpaid_invoices']; ?>, <?php echo $kpi['pending_invoices']; ?>];
    const sumInv = invData.reduce((a, b) => a + b, 0);

    const ctxInvoice = document.getElementById('invoiceChart').getContext('2d');
    new Chart(ctxInvoice, {
        type: 'doughnut',
        data: {
            labels: sumInv === 0 ? ['No Data'] : ['Paid', 'Unpaid', 'Pending'],
            datasets: [{ 
                data: sumInv === 0 ? [1] : invData, 
                backgroundColor: sumInv === 0 ? ['#e5e7eb'] : ['#059669', '#dc2626', '#d97706'], 
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
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableSalary')), "Salary");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableLedger')), "Ledger");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tablePO')), "Purchase Orders");
        XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(document.getElementById('tableInvoices')), "Invoices");
        XLSX.writeFile(wb, "Executive_Financial_Report.xlsx");
    }
</script>

</body>
</html>