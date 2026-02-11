<?php
session_start();
// include 'db_connect.php'; 

// Mock data for dashboard (aggregated from other files' mocks)
$kpi = [
    'total_income' => 1250000,
    'total_expense' => 450000,
    'net_profit' => 800000,
    'pending_invoices' => 125000,
    'active_employees' => 24,
    'total_clients' => 12
];

$chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$chart_income = [200000, 450000, 300000, 500000, 400000, 600000];
$chart_expense = [100000, 150000, 120000, 200000, 180000, 250000];

$recent_invoices = [
    ['no' => 'INV-001', 'client' => 'Facebook', 'date' => '2026-01-20', 'total' => 47200, 'status' => 'Paid'],
    ['no' => 'INV-002', 'client' => 'Neoera', 'date' => '2026-02-02', 'total' => 11800, 'status' => 'Unpaid'],
];

$recent_pos = [
    ['no' => 'PO-001', 'vendor' => 'Dell Computers', 'date' => '2026-01-10', 'grand' => 120000],
    ['no' => 'PO-002', 'vendor' => 'Stationery World', 'date' => '2026-02-01', 'grand' => 5000],
];

$recent_ledger = [
    ['date' => '2026-02-10', 'type' => 'Income', 'party' => 'Facebook India', 'desc' => 'Milestone 1', 'amount' => 500000, 'mode' => 'Credit'],
    ['date' => '2026-02-09', 'type' => 'Expense', 'party' => 'Office Rent', 'desc' => 'Feb Rent', 'amount' => 45000, 'mode' => 'Debit'],
];

$recent_expenses = [
    ['date' => '30-Jan-2026', 'item' => 'Ink Cartridge', 'category' => 'Supplies', 'amount' => 550],
];

$recent_payslips = [
    ['id' => 'IGS4001', 'name' => 'Caro', 'month' => 'Feb 2026', 'salary' => '₹ 50,000.00', 'status' => 'PAID'],
    ['id' => 'IGS2030', 'name' => 'Aisha', 'month' => 'Jan 2026', 'salary' => '₹ 55,000.00', 'status' => 'PAID'],
];

include '../sidebars.php';
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Dashboard</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #1b5a5a;
            --primary-light: #267a7a;
            --accent-gold: #D4AF37;
            --bg-light: #f4f6f9;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --primary-sidebar-width: 95px;
            --secondary-sidebar-width: 220px;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Layout */
        .d-flex-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            transition: margin-left 0.35s ease;
        }

        .sidebar-container {
            width: var(--primary-sidebar-width);
            flex-shrink: 0;
            min-height: 100vh;
            background: #fff;
            border-right: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 1001;
        }

        .main-content {
            flex-grow: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.35s ease;
            padding: 30px;
        }

        /* Push when secondary sidebar opens */
        body.secondary-open .d-flex-wrapper,
        body.secondary-open .main-content {
            margin-left: var(--secondary-sidebar-width);
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .kpi-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
        }

        .kpi-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
        }

        .kpi-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            opacity: 0.1;
            font-size: 40px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .action-btn {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            font-weight: 600;
            color: var(--primary-color);
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 24px;
        }

        /* Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        /* Recent Sections */
        .recent-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .st-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .st-unpaid {
            background: #fee2e2;
            color: #b91c1c;
        }

        .amt-pos {
            color: var(--success);
            font-weight: 600;
        }

        .amt-neg {
            color: var(--danger);
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            body.secondary-open .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="d-flex-wrapper">

    <div class="sidebar-container">
        <?php // sidebars.php is already included at top ?>
    </div>

    <div class="main-content" id="mainContent">
        
        <?php // header.php is already included at top ?>

        <!-- Header -->
        <div class="header-area mb-8">
            <div>
                <h2 class="text-2xl font-bold text-[--primary-color]">Accounts Dashboard</h2>
                <p class="text-sm text-gray-500">Overview of financial operations and quick access to tools</p>
            </div>
            <div class="flex gap-4">
                <button class="bg-[--primary-color] text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-semibold">
                    <i class="ph ph-export"></i> Export Summary
                </button>
                <button class="bg-[--primary-color] text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-semibold">
                    <i class="ph ph-funnel"></i> Advanced Filters
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card relative overflow-hidden">
                <div class="kpi-label">Total Income</div>
                <div class="kpi-value">₹<?= number_format($kpi['total_income'], 2) ?></div>
                <i class="ph ph-arrow-up kpi-icon text-[--success]"></i>
            </div>
            <div class="kpi-card relative overflow-hidden">
                <div class="kpi-label">Total Expenses</div>
                <div class="kpi-value">₹<?= number_format($kpi['total_expense'], 2) ?></div>
                <i class="ph ph-arrow-down kpi-icon text-[--danger]"></i>
            </div>
            <div class="kpi-card relative overflow-hidden">
                <div class="kpi-label">Net Profit</div>
                <div class="kpi-value">₹<?= number_format($kpi['net_profit'], 2) ?></div>
                <i class="ph ph-chart-line-up kpi-icon text-[--primary-color]"></i>
            </div>
            <div class="kpi-card relative overflow-hidden">
                <div class="kpi-label">Pending Invoices</div>
                <div class="kpi-value">₹<?= number_format($kpi['pending_invoices'], 2) ?></div>
                <i class="ph ph-warning kpi-icon text-[--warning]"></i>
            </div>
            <div class="kpi-card relative overflow-hidden">
                <div class="kpi-label">Active Employees</div>
                <div class="kpi-value"><?= $kpi['active_employees'] ?></div>
                <i class="ph ph-users kpi-icon text-[--primary-color]"></i>
            </div>
            <div class="kpi-card relative overflow-hidden">
                <div class="kpi-label">Total Clients</div>
                <div class="kpi-value"><?= $kpi['total_clients'] ?></div>
                <i class="ph ph-handshake kpi-icon text-[--primary-color]"></i>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="recent-section">
            <h3 class="section-title"><i class="ph ph-lightning"></i> Quick Actions</h3>
            <div class="quick-actions">
                <a href="new_invoice.php" class="action-btn">
                    <i class="ph ph-receipt action-icon text-[--primary-color]"></i>
                    New Invoice
                </a>
                <a href="purchase_order.php" class="action-btn">
                    <i class="ph ph-shopping-cart action-icon text-[--primary-color]"></i>
                    New PO
                </a>
                <a href="ledger.php" class="action-btn">
                    <i class="ph ph-book-open action-icon text-[--primary-color]"></i>
                    Ledger Entry
                </a>
                <a href="payslip.php" class="action-btn">
                    <i class="ph ph-currency-inr action-icon text-[--primary-color]"></i>
                    Generate Payslip
                </a>
                <a href="masters.php" class="action-btn">
                    <i class="ph ph-gear action-icon text-[--primary-color]"></i>
                    Masters Setup
                </a>
                <a href="accounts_reports.php" class="action-btn">
                    <i class="ph ph-chart-bar action-icon text-[--primary-color]"></i>
                    View Reports
                </a>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="section-title text-sm mb-4">Income vs Expenses</h3>
                <canvas id="financeChart" height="300"></canvas>
            </div>
            <div class="chart-card">
                <h3 class="section-title text-sm mb-4">Invoice Status</h3>
                <canvas id="invoiceChart" height="300"></canvas>
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="recent-section">
            <h3 class="section-title"><i class="ph ph-receipt"></i> Recent Invoices</h3>
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_invoices as $inv): ?>
                    <tr>
                        <td><b><?= $inv['no'] ?></b></td>
                        <td><?= $inv['client'] ?></td>
                        <td><?= $inv['date'] ?></td>
                        <td class="amt-pos">₹<?= number_format($inv['total']) ?></td>
                        <td><span class="status-badge <?= $inv['status']=='Paid'?'st-paid':'st-unpaid' ?>"><?= $inv['status'] ?></span></td>
                        <td>
                            <a href="new_invoice.php?edit=<?= $inv['no'] ?>" class="text-blue-500 mr-2"><i class="ph ph-pencil"></i></a>
                            <a href="#" class="text-red-500"><i class="ph ph-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Purchase Orders -->
        <div class="recent-section">
            <h3 class="section-title"><i class="ph ph-shopping-cart"></i> Recent Purchase Orders</h3>
            <table>
                <thead>
                    <tr>
                        <th>PO No</th>
                        <th>Vendor</th>
                        <th>Date</th>
                        <th>Grand Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_pos as $po): ?>
                    <tr>
                        <td><b><?= $po['no'] ?></b></td>
                        <td><?= $po['vendor'] ?></td>
                        <td><?= $po['date'] ?></td>
                        <td class="amt-neg">₹<?= number_format($po['grand']) ?></td>
                        <td>
                            <a href="purchase_order.php?edit=<?= $po['no'] ?>" class="text-blue-500 mr-2"><i class="ph ph-pencil"></i></a>
                            <a href="#" class="text-red-500"><i class="ph ph-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Ledger Transactions -->
        <div class="recent-section">
            <h3 class="section-title"><i class="ph ph-book-open"></i> Recent Transactions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Party</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_ledger as $row): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= $row['type'] ?></td>
                        <td><b><?= $row['party'] ?></b></td>
                        <td><?= $row['desc'] ?></td>
                        <td class="amt-neg"><?= $row['mode']=='Debit' ? '₹'.number_format($row['amount']) : '-' ?></td>
                        <td class="amt-pos"><?= $row['mode']=='Credit' ? '₹'.number_format($row['amount']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Expenses -->
        <div class="recent-section">
            <h3 class="section-title"><i class="ph ph-receipt"></i> Recent Expenses</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_expenses as $exp): ?>
                    <tr>
                        <td><?= $exp['date'] ?></td>
                        <td><?= $exp['item'] ?></td>
                        <td><span class="status-badge st-paid"><?= $exp['category'] ?></span></td>
                        <td class="amt-neg">₹<?= number_format($exp['amount']) ?></td>
                        <td>
                            <a href="masters.php?edit=<?= $exp['item'] ?>" class="text-blue-500 mr-2"><i class="ph ph-pencil"></i></a>
                            <a href="#" class="text-red-500"><i class="ph ph-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Payslips -->
        <div class="recent-section">
            <h3 class="section-title"><i class="ph ph-currency-inr"></i> Recent Payslips</h3>
            <table>
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Month</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_payslips as $slip): ?>
                    <tr>
                        <td><b><?= $slip['id'] ?></b></td>
                        <td><?= $slip['name'] ?></td>
                        <td><?= $slip['month'] ?></td>
                        <td class="amt-pos"><?= $slip['salary'] ?></td>
                        <td><span class="status-badge st-paid"><?= $slip['status'] ?></span></td>
                        <td>
                            <a href="payslip.php?reprint=<?= $slip['id'] ?>" class="text-blue-500 mr-2"><i class="ph ph-printer"></i></a>
                            <a href="#" class="text-red-500"><i class="ph ph-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
    // Charts
    const ctxFinance = document.getElementById('financeChart').getContext('2d');
    new Chart(ctxFinance, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_months) ?>,
            datasets: [
                { label: 'Income', data: <?= json_encode($chart_income) ?>, backgroundColor: '#059669' },
                { label: 'Expenses', data: <?= json_encode($chart_expense) ?>, backgroundColor: '#dc2626' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    const ctxInvoice = document.getElementById('invoiceChart').getContext('2d');
    new Chart(ctxInvoice, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Unpaid', 'Pending'],
            datasets: [{ data: [65, 20, 15], backgroundColor: ['#059669', '#dc2626', '#d97706'] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>

</body>
</html>