<?php
// cfo_financials.php
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
$can_view = in_array($user_role, ['CFO', 'Admin', 'Super Admin', 'Management', 'CEO']);
if (!$can_view) {
    die("<div style='padding:50px;text-align:center;font-family:sans-serif;'><h2>Access Denied</h2><p>You do not have clearance to view Executive Financials.</p></div>");
}

// =========================================================================
// 2. LIVE FINANCIAL ENGINE (YTD Aggregation)
// =========================================================================
$current_year = date('Y');
$current_month_num = max(1, (int)date('n')); // Avoid division by zero

// Initialize 12-month arrays for the charts
$chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$chart_revenue = array_fill(0, 12, 0);
$chart_expenses = array_fill(0, 12, 0);

$total_revenue = 0;
$total_cogs = 0; // Cost of Goods Sold (Purchase Orders)
$total_opex = 0; // Operating Expenses (Salaries)

// A. Fetch Revenue (Approved/Paid Invoices)
$inv_sql = "SELECT MONTH(invoice_date) as m, SUM(grand_total) as total 
            FROM invoices 
            WHERE YEAR(invoice_date) = ? AND status IN ('Approved', 'Paid', 'Credited', 'Partial') 
            GROUP BY MONTH(invoice_date)";
$stmt = $conn->prepare($inv_sql);
$stmt->bind_param("i", $current_year);
$stmt->execute();
$inv_res = $stmt->get_result();
while($r = $inv_res->fetch_assoc()) {
    $m_idx = $r['m'] - 1; // Month index (0 for Jan, 11 for Dec)
    $val = (float)$r['total'];
    $chart_revenue[$m_idx] += $val;
    $total_revenue += $val;
}
$stmt->close();

// B. Fetch COGS (Approved Purchase Orders)
$po_sql = "SELECT MONTH(po_date) as m, SUM(grand_total) as total 
           FROM purchase_orders 
           WHERE YEAR(po_date) = ? AND approval_status IN ('Approved', 'Paid', 'Credited') 
           GROUP BY MONTH(po_date)";
$stmt = $conn->prepare($po_sql);
$stmt->bind_param("i", $current_year);
$stmt->execute();
$po_res = $stmt->get_result();
while($r = $po_res->fetch_assoc()) {
    $m_idx = $r['m'] - 1;
    $val = (float)$r['total'];
    $chart_expenses[$m_idx] += $val;
    $total_cogs += $val;
}
$stmt->close();

// C. Fetch OpEx (Approved Salaries)
// Uses gross_salary to account for employer costs. Ignores soft-deleted records.
$sal_sql = "SELECT MONTH(salary_month) as m, SUM(gross_salary) as total 
            FROM employee_salary 
            WHERE YEAR(salary_month) = ? AND approval_status IN ('Approved', 'Credited') AND is_deleted = 0 
            GROUP BY MONTH(salary_month)";
$stmt = $conn->prepare($sal_sql);
$stmt->bind_param("i", $current_year);
$stmt->execute();
$sal_res = $stmt->get_result();
while($r = $sal_res->fetch_assoc()) {
    $m_idx = $r['m'] - 1;
    $val = (float)$r['total'];
    $chart_expenses[$m_idx] += $val;
    $total_opex += $val;
}
$stmt->close();

// =========================================================================
// 3. FINANCIAL MATHEMATICS
// =========================================================================
$gross_profit = $total_revenue - $total_cogs;
$net_profit = $gross_profit - $total_opex;
$profit_margin = ($total_revenue > 0) ? ($net_profit / $total_revenue) * 100 : 0;

$total_expenses = $total_cogs + $total_opex;
$avg_burn_rate = $total_expenses / $current_month_num;

// Cash Reserve Simulation (Base Capital + Retained Earnings)
$base_capital = 5000000; // Simulated starting bank balance
$cash_reserve = $base_capital + $net_profit;
$runway_months = ($avg_burn_rate > 0) ? ($cash_reserve / $avg_burn_rate) : 0;

$financials = [
    'revenue' => $total_revenue,
    'cogs' => $total_cogs,
    'gross_profit' => $gross_profit,
    'opex' => $total_opex,
    'net_profit' => $net_profit,
    'cash_reserve' => $cash_reserve,
    'avg_burn_rate' => $avg_burn_rate
];

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Intelligence - CFO Dashboard</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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

        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; transition: all 0.3s ease; box-sizing: border-box; }

        /* Header Area */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        .header-text h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .header-text p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        
        .header-actions { display: flex; gap: 10px; }
        .btn-export { background: var(--theme-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(27, 90, 90, 0.2); }
        .btn-export:hover { background: #134e4e; transform: translateY(-2px); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: relative; overflow: hidden; }
        .kpi-card > div { position: relative; z-index: 2; }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .kpi-value { font-size: 24px; font-weight: 800; color: var(--text-main); }
        .kpi-trend { font-size: 12px; margin-top: 8px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        
        .kpi-icon-bg { position: absolute; right: -15px; bottom: -25px; font-size: 120px; opacity:0.15; pointer-events: none; z-index: 1; }

        /* Charts Layout */
        .dashboard-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-card { background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); height: 100%; display: flex; flex-direction: column; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
        .card-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--theme-color); display: flex; align-items: center; gap: 8px; }
        .chart-wrapper { flex-grow: 1; position: relative; min-height: 300px; }

        /* P&L Table */
        .table-responsive { overflow-x: auto; }
        .pl-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .pl-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border); background: #f8fafc; }
        .pl-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--text-main); }
        .pl-table tr.total-row td { font-weight: 800; background: #f8fafc; color: var(--theme-color); border-top: 2px solid var(--border); border-bottom: none; font-size: 14px; }
        .amt-col { text-align: right; font-variant-numeric: tabular-nums; }
        .type-badge { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 700; background: #f1f5f9; color: var(--text-muted); }

        @media (max-width: 1024px) { .dashboard-split { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; width: 100%; padding: 15px; } 
            .header-actions { width: 100%; justify-content: flex-start; margin-top: 10px; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="page-header">
        <div class="header-text">
            <h1>Financial Intelligence</h1>
            <p>Live General Ledger Aggregation (YTD <?= $current_year ?>)</p>
        </div>
        <div class="header-actions">
            <button class="btn-export" onclick="exportToExcel()"><i class="ph ph-microsoft-excel-logo"></i> Executive Export</button>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid var(--success);">
            <div class="kpi-label">YTD Revenue</div>
            <div class="kpi-value">₹<?= number_format($financials['revenue'], 2) ?></div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph-bold ph-receipt"></i> From Approved Invoices</div>
            <i class="ph-fill ph-coins kpi-icon-bg" style="color: var(--success);"></i>
        </div>
        
        <div class="kpi-card" style="border-top: 4px solid var(--warning);">
            <div class="kpi-label">Total Burn (COGS + OpEx)</div>
            <div class="kpi-value">₹<?= number_format($financials['cogs'] + $financials['opex'], 2) ?></div>
            <div class="kpi-trend" style="color: var(--text-muted);">Avg ₹<?= number_format($financials['avg_burn_rate']) ?> / mo</div>
            <i class="ph-fill ph-calculator kpi-icon-bg" style="color: var(--warning);"></i>
        </div>

        <div class="kpi-card" style="border-top: 4px solid var(--theme-color);">
            <div class="kpi-label">Net Profit Margin</div>
            <div class="kpi-value"><?= number_format($profit_margin, 1) ?>%</div>
            <div class="kpi-trend" style="color: <?= $profit_margin >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                <i class="ph <?= $profit_margin >= 0 ? 'ph-trend-up' : 'ph-trend-down' ?>"></i> 
                <?= $profit_margin >= 0 ? 'Profitable' : 'Deficit' ?>
            </div>
            <i class="ph-fill ph-chart-pie-slice kpi-icon-bg" style="color: var(--theme-color);"></i>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
            <div class="kpi-label">Est. Cash Runway</div>
            <div class="kpi-value"><?= number_format($runway_months, 1) ?> Months</div>
            <div class="kpi-trend" style="color: #3b82f6;"><i class="ph ph-bank"></i> ₹<?= number_format($financials['cash_reserve']) ?> Bank Est.</div>
            <i class="ph-fill ph-hourglass-high kpi-icon-bg" style="color: #3b82f6;"></i>
        </div>
    </div>

    <div class="dashboard-split">
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-line"></i> Cash Flow & Profit Trend (<?= $current_year ?>)</h3>
            </div>
            <div class="chart-wrapper">
                <canvas id="cashFlowMixChart"></canvas>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-pie-slice"></i> Expense Breakdown</h3>
            </div>
            <div class="chart-wrapper" style="min-height: 250px;">
                <canvas id="expenseDoughnut"></canvas>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="ph ph-table"></i> Categorized P&L Statement (YTD)</h3>
            <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Data aggregated from Live Ledgers</span>
        </div>
        <div class="table-responsive">
            <table class="pl-table" id="plTable">
                <thead>
                    <tr>
                        <th style="width: 40%;">Ledger Category</th>
                        <th style="width: 20%;">Type</th>
                        <th class="amt-col" style="width: 40%;">Total Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Gross Revenue (Sales & Services)</strong></td>
                        <td><span class="type-badge">Income</span></td>
                        <td class="amt-col" style="color: var(--success); font-weight: 700;">₹<?= number_format($financials['revenue'], 2) ?></td>
                    </tr>
                    
                    <tr><td colspan="3" style="background:#f8fafc; font-size:11px; font-weight:700; color:var(--text-muted);">COST OF GOODS SOLD (COGS)</td></tr>
                    <tr>
                        <td style="padding-left: 30px;"><i class="ph ph-arrow-elbow-down-right" style="color:#cbd5e1; margin-right:8px;"></i> Vendor Payments (Purchase Orders)</td>
                        <td><span class="type-badge">COGS</span></td>
                        <td class="amt-col text-danger">₹<?= number_format($financials['cogs'], 2) ?></td>
                    </tr>
                    
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;">Gross Profit:</td>
                        <td class="amt-col">₹<?= number_format($financials['gross_profit'], 2) ?></td>
                    </tr>

                    <tr><td colspan="3" style="background:#f8fafc; font-size:11px; font-weight:700; color:var(--text-muted);">OPERATING EXPENSES (OpEx)</td></tr>
                    <tr>
                        <td style="padding-left: 30px;"><i class="ph ph-arrow-elbow-down-right" style="color:#cbd5e1; margin-right:8px;"></i> Payroll & Benefits (Salaries)</td>
                        <td><span class="type-badge">OpEx</span></td>
                        <td class="amt-col text-danger">₹<?= number_format($financials['opex'], 2) ?></td>
                    </tr>

                    <tr class="total-row" style="border-top: 2px solid var(--theme-color);">
                        <td colspan="2" style="text-align: right; color: var(--text-main);">NET PROFIT (Pre-Tax):</td>
                        <td class="amt-col" style="color: var(--theme-color); font-size: 16px;">₹<?= number_format($financials['net_profit'], 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
    // --- 1. MIXED CASH FLOW CHART (Bar + Line) ---
    const ctxMix = document.getElementById('cashFlowMixChart').getContext('2d');
    
    const incomeData = <?php echo json_encode($chart_revenue); ?>;
    const expenseData = <?php echo json_encode($chart_expenses); ?>;
    const profitData = incomeData.map((inc, index) => inc - expenseData[index]);

    new Chart(ctxMix, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_months); ?>,
            datasets: [
                {
                    type: 'line',
                    label: 'Net Profit',
                    data: profitData,
                    borderColor: '#D4AF37', // Accent Gold
                    backgroundColor: '#D4AF37',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y'
                },
                {
                    type: 'bar',
                    label: 'Revenue',
                    data: incomeData,
                    backgroundColor: '#10b981', // Success Green
                    borderRadius: 4
                },
                {
                    type: 'bar',
                    label: 'Expenses',
                    data: expenseData,
                    backgroundColor: '#ef4444', // Danger Red
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false } },
                y: { 
                    beginAtZero: true, 
                    grid: { borderDash: [4, 4], color: '#e2e8f0' },
                    ticks: { callback: function(value) { return '₹' + (value/1000).toFixed(1) + 'K'; } } // Formats side axis
                }
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ₹' + context.raw.toLocaleString(); } } }
            }
        }
    });

    // --- 2. EXPENSE DOUGHNUT CHART ---
    // Dynamically populated from the Live Database arrays
    const cogsVal = <?= $financials['cogs'] ?>;
    const opexVal = <?= $financials['opex'] ?>;
    
    const ctxDoughnut = document.getElementById('expenseDoughnut').getContext('2d');
    new Chart(ctxDoughnut, {
        type: 'doughnut',
        data: {
            labels: ['Vendor Payments (COGS)', 'Payroll & Benefits (OpEx)'],
            datasets: [{
                data: [cogsVal, opexVal],
                backgroundColor: ['#f59e0b', '#1b5a5a'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12 } } },
                tooltip: { callbacks: { label: function(context) { return context.label + ': ₹' + context.raw.toLocaleString(); } } }
            }
        }
    });

    // --- 3. EXCEL EXPORT LOGIC (SheetJS) ---
    function exportToExcel() {
        const table = document.getElementById('plTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "P&L Statement" });
        const dateStr = new Date().toISOString().slice(0, 10);
        const fileName = `Executive_Financial_Report_${dateStr}.xlsx`;
        XLSX.writeFile(wb, fileName);
    }
</script>

</body>
</html>