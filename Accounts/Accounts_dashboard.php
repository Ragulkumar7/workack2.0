<?php
// accounts_dashboard.php

// 1. INCLUDE COMMON FILES
// Ensure sidebars.php and header.php are in the parent directory as per your structure
include '../sidebars.php'; 
include '../header.php';

// 2. MOCK DATA (Simulating Database Fetches)
$kpi = [
    'balance' => 850000,
    'income'  => 1250000,
    'expense' => 450000,
    'pending' => 125000
];

// Recent Transactions (Mixed from Invoice, PO, Ledger)
$recent_transactions = [
    ['id' => 'INV-014', 'date' => '2026-02-11', 'party' => 'Facebook India', 'type' => 'Invoice', 'amount' => 45000, 'status' => 'Pending'],
    ['id' => 'PO-205',  'date' => '2026-02-10', 'party' => 'Dell Computers', 'type' => 'Purchase Order', 'amount' => 120000, 'status' => 'Paid'],
    ['id' => 'EXP-009', 'date' => '2026-02-09', 'party' => 'Office Rent',    'type' => 'Expense', 'amount' => 25000, 'status' => 'Cleared'],
    ['id' => 'SAL-Feb', 'date' => '2026-02-01', 'party' => 'Staff Salary',   'type' => 'Payroll', 'amount' => 650000, 'status' => 'Processed'],
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
            /* Brand Colors */
            --theme-color: #1b5a5a;       /* Deep Teal */
            --theme-dark: #134e4e;
            --theme-light: #e0f2f1;
            --accent-gold: #D4AF37;
            
            /* UI Colors */
            --bg-body: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            
            /* Functional Colors */
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;

            /* Sidebar logic */
            --sidebar-width: 95px;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            margin: 0; padding: 0;
        }

        /* --- LAYOUT --- */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER SECTION --- */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-bottom: 30px;
        }
        .welcome-text h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--theme-color);
            margin: 0;
        }
        .welcome-text p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 5px 0 0;
        }
        .date-badge {
            background: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            color: var(--theme-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid var(--border);
        }

        /* --- KPI CARDS --- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: var(--surface);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }
        
        .kpi-icon {
            width: 45px; height: 45px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }
        .kpi-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-main); margin-top: 5px; }
        .kpi-trend { font-size: 12px; margin-top: 8px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        
        /* Specific Colors */
        .k-balance .kpi-icon { background: var(--theme-light); color: var(--theme-color); }
        .k-income .kpi-icon { background: #dcfce7; color: var(--success); }
        .k-expense .kpi-icon { background: #fee2e2; color: var(--danger); }
        .k-pending .kpi-icon { background: #ffedd5; color: var(--warning); }

        /* --- QUICK ACTIONS --- */
        .section-title { font-size: 16px; font-weight: 700; color: var(--theme-color); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .action-btn:hover {
            border-color: var(--theme-color);
            background: var(--theme-light);
            transform: translateY(-2px);
        }
        .action-btn i { font-size: 28px; color: var(--theme-color); }
        .action-btn span { font-size: 13px; font-weight: 600; color: var(--text-main); }

        /* --- CHARTS & TABLE LAYOUT --- */
        .dashboard-split {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card, .table-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .chart-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .chart-header h3 { font-size: 16px; font-weight: 700; margin: 0; color: var(--text-main); }

        /* --- RECENT TABLE --- */
        .recent-table { width: 100%; border-collapse: collapse; }
        .recent-table th { text-align: left; padding: 12px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; background: #f8fafc; border-radius: 6px; }
        .recent-table td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .recent-table tr:last-child td { border-bottom: none; }
        
        .txn-icon { 
            width: 32px; height: 32px; border-radius: 8px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 16px; margin-right: 10px; 
        }
        .txn-inv { background: #e0f2fe; color: #0284c7; }
        .txn-po { background: #fce7f3; color: #db2777; }
        .txn-exp { background: #fee2e2; color: #dc2626; }

        .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .st-paid { background: var(--success); }
        .st-pending { background: var(--warning); }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .dashboard-split { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
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
            <div class="kpi-trend" style="color: var(--success);">
                <i class="ph ph-trend-up"></i> +12% vs last month
            </div>
        </div>

        <div class="kpi-card k-income">
            <div class="kpi-icon"><i class="ph ph-arrow-down-left"></i></div>
            <div class="kpi-label">Total Income</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['income']); ?></div>
            <div class="kpi-trend" style="color: var(--success);">
                <i class="ph ph-check-circle"></i> 15 Invoices Paid
            </div>
        </div>

        <div class="kpi-card k-expense">
            <div class="kpi-icon"><i class="ph ph-arrow-up-right"></i></div>
            <div class="kpi-label">Total Expenses</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['expense']); ?></div>
            <div class="kpi-trend" style="color: var(--danger);">
                <i class="ph ph-warning-circle"></i> High Rent Cost
            </div>
        </div>

        <div class="kpi-card k-pending">
            <div class="kpi-icon"><i class="ph ph-clock-countdown"></i></div>
            <div class="kpi-label">Pending Receivables</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['pending']); ?></div>
            <div class="kpi-trend" style="color: var(--warning);">
                <i class="ph ph-bell"></i> 3 Overdue
            </div>
        </div>
    </div>

    <div class="section-title"><i class="ph ph-lightning"></i> Quick Actions</div>
    <div class="action-grid">
        <a href="new_invoice.php" class="action-btn">
            <i class="ph ph-file-plus"></i>
            <span>Create Invoice</span>
        </a>
        <a href="ledger.php" class="action-btn">
            <i class="ph ph-book-open-text"></i>
            <span>View Ledger</span>
        </a>
        <a href="purchase_order.php" class="action-btn">
            <i class="ph ph-shopping-cart"></i>
            <span>New PO</span>
        </a>
        <a href="payslip.php" class="action-btn">
            <i class="ph ph-users-three"></i>
            <span>Payroll</span>
        </a>
        <a href="accounts_reports.php" class="action-btn">
            <i class="ph ph-chart-pie-slice"></i>
            <span>Reports</span>
        </a>
        <a href="masters.php" class="action-btn">
            <i class="ph ph-bank"></i>
            <span>Masters</span>
        </a>
    </div>

    <div class="dashboard-split">
        
        <div class="chart-card">
            <div class="chart-header">
                <h3>Cash Flow Analysis (2026)</h3>
                <select style="border:none; background:#f1f5f9; padding:5px; border-radius:5px; font-size:12px;">
                    <option>Last 6 Months</option>
                    <option>This Year</option>
                </select>
            </div>
            <div style="height: 300px;">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        <div class="table-card">
            <div class="chart-header">
                <h3>Recent Transactions</h3>
                <a href="ledger.php" style="font-size:12px; color:var(--theme-color); text-decoration:none; font-weight:600;">View All</a>
            </div>
            
            <table class="recent-table">
                <tbody>
                    <?php foreach($recent_transactions as $txn): 
                        // Determine Icon & Color logic
                        $iconClass = 'ph-file-text';
                        $bgClass = 'txn-inv';
                        if($txn['type'] == 'Expense') { $iconClass = 'ph-receipt'; $bgClass = 'txn-exp'; }
                        if($txn['type'] == 'Purchase Order') { $iconClass = 'ph-shopping-bag'; $bgClass = 'txn-po'; }
                    ?>
                    <tr>
                        <td width="50">
                            <div class="txn-icon <?php echo $bgClass; ?>">
                                <i class="ph <?php echo $iconClass; ?>"></i>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--text-main);"><?php echo $txn['party']; ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo $txn['type']; ?> • <?php echo $txn['id']; ?></div>
                        </td>
                        <td style="text-align: right;">
                            <div style="font-weight: 700; color: var(--text-main);">₹<?php echo number_format($txn['amount']); ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo $txn['date']; ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="dashboard-split" style="grid-template-columns: 1fr 2fr;">
         <div class="chart-card">
            <div class="chart-header">
                <h3>Expense Distribution</h3>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h3>Invoice Payment Status</h3>
            </div>
            <div style="height: 250px;">
                <canvas id="invoiceBarChart"></canvas>
            </div>
        </div>
    </div>

</main>

<script>
    // 1. Cash Flow Chart (Bar)
    const ctxFlow = document.getElementById('cashFlowChart').getContext('2d');
    new Chart(ctxFlow, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                {
                    label: 'Income',
                    data: [120000, 190000, 30000, 50000, 20000, 300000],
                    backgroundColor: '#1b5a5a',
                    borderRadius: 4
                },
                {
                    label: 'Expense',
                    data: [80000, 50000, 30000, 40000, 10000, 200000],
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [2, 2] } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { position: 'top', align: 'end' } }
        }
    });

    // 2. Expense Breakdown (Doughnut)
    const ctxExp = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctxExp, {
        type: 'doughnut',
        data: {
            labels: ['Rent', 'Salaries', 'Purchase', 'Utilities'],
            datasets: [{
                data: [25, 45, 20, 10],
                backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6366f1'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } },
            cutout: '70%'
        }
    });

    // 3. Invoice Status (Horizontal Bar)
    const ctxInv = document.getElementById('invoiceBarChart').getContext('2d');
    new Chart(ctxInv, {
        type: 'bar',
        indexAxis: 'y',
        data: {
            labels: ['Paid', 'Unpaid', 'Overdue'],
            datasets: [{
                label: 'Count',
                data: [15, 5, 2],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>