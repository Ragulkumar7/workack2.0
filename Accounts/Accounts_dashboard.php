<?php
// 1. INCLUDE COMMON FILES
include '../sidebars.php'; 
include '../header.php';

// 2. MOCK DATA
$kpi = [
    'balance' => 850000,
    'income'  => 1250000,
    'expense' => 450000,
    'pending' => 125000
];

$recent_transactions = [
    ['id' => 'INV-014', 'date' => '11 Feb', 'party' => 'Facebook India', 'type' => 'Invoice', 'amount' => 45000, 'status' => 'Pending'],
    ['id' => 'PO-205',  'date' => '10 Feb', 'party' => 'Dell Computers', 'type' => 'PO', 'amount' => 120000, 'status' => 'Paid'],
    ['id' => 'EXP-009', 'date' => '09 Feb', 'party' => 'Office Rent',    'type' => 'Expense', 'amount' => 25000, 'status' => 'Cleared'],
    ['id' => 'SAL-Feb', 'date' => '01 Feb', 'party' => 'Staff Salary',   'type' => 'Payroll', 'amount' => 650000, 'status' => 'Processed'],
    ['id' => 'INV-013', 'date' => '30 Jan', 'party' => 'Google India',   'type' => 'Invoice', 'amount' => 12500, 'status' => 'Paid'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Overview - Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            
            /* Sidebar Dimensions */
            --primary-width: 95px;
            --secondary-width: 220px;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            margin: 0; padding: 0;
        }

        /* --- LAYOUT LOGIC (CRITICAL FIX) --- */
        .main-content {
            margin-left: var(--primary-width); /* Default state: 95px */
            width: calc(100% - var(--primary-width));
            padding: 24px;
            min-height: 100vh;
            transition: all 0.3s ease; /* Smooth animation */
        }

        /* This class is added by sidebars.php JS when a menu opens */
        .main-content.main-shifted {
            margin-left: calc(var(--primary-width) + var(--secondary-width)); /* 315px */
            width: calc(100% - (var(--primary-width) + var(--secondary-width)));
        }

        /* --- DASHBOARD STYLING --- */
        .dashboard-header { display: flex; justify-content: space-between; align-items: end; margin-bottom: 24px; }
        .welcome-text h1 { font-size: 22px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .welcome-text p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        .date-badge { background: white; padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; color: var(--theme-color); border: 1px solid var(--border); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid var(--border); transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 12px; }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .kpi-value { font-size: 24px; font-weight: 800; color: var(--text-main); margin-top: 4px; }
        .kpi-trend { font-size: 12px; margin-top: 6px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        
        .k-balance .kpi-icon { background: var(--theme-light); color: var(--theme-color); }
        .k-income .kpi-icon { background: #dcfce7; color: var(--success); }
        .k-expense .kpi-icon { background: #fee2e2; color: var(--danger); }
        .k-pending .kpi-icon { background: #ffedd5; color: var(--warning); }

        /* Quick Actions */
        .section-title { font-size: 15px; font-weight: 700; color: var(--theme-color); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 24px; }
        .action-btn { background: white; padding: 15px; border-radius: 10px; border: 1px solid var(--border); text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .action-btn:hover { border-color: var(--theme-color); background: var(--theme-light); }
        .action-btn i { font-size: 24px; color: var(--theme-color); }
        .action-btn span { font-size: 12px; font-weight: 600; color: var(--text-main); }

        /* Dashboard Split Layout */
        .dashboard-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); height: 100%; display: flex; flex-direction: column; }
        
        .chart-header { display: flex; justify-content: space-between; margin-bottom: 15px; align-items: center; }
        .chart-header h3 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-main); }
        
        /* Tables */
        .recent-table-wrapper { flex-grow: 1; overflow-y: auto; max-height: 300px; }
        .recent-table { width: 100%; border-collapse: collapse; }
        .recent-table td { padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .recent-table tr:last-child td { border-bottom: none; }
        .txn-info { display: flex; align-items: center; gap: 10px; }
        .txn-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .bg-inv { background: #e0f2fe; color: #0284c7; }
        .bg-po { background: #fce7f3; color: #db2777; }
        .bg-exp { background: #fee2e2; color: #dc2626; }
        .bg-pay { background: #f0fdf4; color: #16a34a; }

        @media (max-width: 1024px) { .dashboard-split { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; } 
            .kpi-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content" id="mainContent">
    
    <div class="dashboard-header">
        <div class="welcome-text">
            <h1>Accounts Dashboard</h1>
            <p>Financial overview for Neoera Infotech</p>
        </div>
        <div class="date-badge">
            <i class="ph ph-calendar-blank"></i> <?php echo date('d M, Y'); ?>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card k-balance">
            <div class="kpi-icon"><i class="ph ph-wallet"></i></div>
            <div class="kpi-label">Current Balance</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['balance']); ?></div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph ph-trend-up"></i> +12%</div>
        </div>
        <div class="kpi-card k-income">
            <div class="kpi-icon"><i class="ph ph-arrow-down-left"></i></div>
            <div class="kpi-label">Total Income</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['income']); ?></div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph ph-check-circle"></i> 15 Paid</div>
        </div>
        <div class="kpi-card k-expense">
            <div class="kpi-icon"><i class="ph ph-arrow-up-right"></i></div>
            <div class="kpi-label">Total Expenses</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['expense']); ?></div>
            <div class="kpi-trend" style="color: var(--danger);"><i class="ph ph-warning-circle"></i> High Rent</div>
        </div>
        <div class="kpi-card k-pending">
            <div class="kpi-icon"><i class="ph ph-clock-countdown"></i></div>
            <div class="kpi-label">Pending</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['pending']); ?></div>
            <div class="kpi-trend" style="color: var(--warning);"><i class="ph ph-bell"></i> 3 Overdue</div>
        </div>
    </div>

    <div class="section-title"><i class="ph ph-lightning"></i> Quick Actions</div>
    <div class="action-grid">
        <a href="new_invoice.php" class="action-btn"><i class="ph ph-file-plus"></i><span>Create Invoice</span></a>
        <a href="ledger.php" class="action-btn"><i class="ph ph-book-open-text"></i><span>View Ledger</span></a>
        <a href="purchase_order.php" class="action-btn"><i class="ph ph-shopping-cart"></i><span>New PO</span></a>
        <a href="payslip.php" class="action-btn"><i class="ph ph-users-three"></i><span>Payroll</span></a>
        <a href="accounts_reports.php" class="action-btn"><i class="ph ph-chart-pie-slice"></i><span>Reports</span></a>
        <a href="masters.php" class="action-btn"><i class="ph ph-bank"></i><span>Masters</span></a>
    </div>

    <div class="dashboard-split">
        <div class="dashboard-card">
            <div class="chart-header">
                <h3>Cash Flow Analysis (2026)</h3>
                <select style="border:none; background:#f1f5f9; padding:4px 8px; border-radius:5px; font-size:11px;">
                    <option>Last 6 Months</option>
                    <option>This Year</option>
                </select>
            </div>
            <div style="flex-grow: 1; min-height: 250px;">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="chart-header">
                <h3>Recent Transactions</h3>
                <a href="ledger.php" style="font-size:12px; color:var(--theme-color); text-decoration:none; font-weight:600;">View All</a>
            </div>
            <div class="recent-table-wrapper">
                <table class="recent-table">
                    <tbody>
                        <?php foreach($recent_transactions as $txn): 
                            $icon = 'ph-file-text'; $bg = 'bg-inv';
                            if($txn['type'] == 'Expense') { $icon = 'ph-receipt'; $bg = 'bg-exp'; }
                            if($txn['type'] == 'PO') { $icon = 'ph-shopping-bag'; $bg = 'bg-po'; }
                            if($txn['type'] == 'Payroll') { $icon = 'ph-users'; $bg = 'bg-pay'; }
                        ?>
                        <tr>
                            <td>
                                <div class="txn-info">
                                    <div class="txn-icon <?php echo $bg; ?>"><i class="ph <?php echo $icon; ?>"></i></div>
                                    <div>
                                        <div style="font-weight:600; color:var(--text-main);"><?php echo $txn['party']; ?></div>
                                        <div style="font-size:11px; color:var(--text-muted);"><?php echo $txn['type']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <div style="font-weight:700; color:var(--text-main);">₹<?php echo number_format($txn['amount']); ?></div>
                                <div style="font-size:11px; color:var(--text-muted);"><?php echo $txn['date']; ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-split" style="grid-template-columns: 1fr 1fr;">
         <div class="dashboard-card">
            <div class="chart-header"><h3>Expense Distribution</h3></div>
            <div style="height: 220px; position: relative;">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="chart-header"><h3>Invoice Payment Status</h3></div>
            <div style="height: 220px; position: relative;">
                <canvas id="invoiceBarChart"></canvas>
            </div>
        </div>
    </div>

</main>

<script>
    // 1. CASH FLOW CHART
    new Chart(document.getElementById('cashFlowChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                { label: 'Income', data: [120000, 190000, 30000, 50000, 20000, 300000], backgroundColor: '#1b5a5a', borderRadius: 4 },
                { label: 'Expense', data: [80000, 50000, 30000, 40000, 10000, 200000], backgroundColor: '#ef4444', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, grid: { borderDash: [2, 2] } }, x: { grid: { display: false } } },
            plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10 } } }
        }
    });

    // 2. EXPENSE DOUGHNUT
    new Chart(document.getElementById('expenseChart'), {
        type: 'doughnut',
        data: {
            labels: ['Rent', 'Salaries', 'Purchase', 'Utils'],
            datasets: [{
                data: [25, 45, 20, 10],
                backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6366f1'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 10 } } },
            cutout: '75%'
        }
    });

    // 3. INVOICE BAR
    new Chart(document.getElementById('invoiceBarChart'), {
        type: 'bar',
        indexAxis: 'y',
        data: {
            labels: ['Paid', 'Unpaid', 'Overdue'],
            datasets: [{
                label: 'Count', data: [15, 5, 2],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderRadius: 4, barThickness: 25
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { x: { grid: { display: false } }, y: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>