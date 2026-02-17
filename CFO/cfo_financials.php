<?php
// cfo_financials.php
include '../sidebars.php'; 
include '../header.php';

// --- MOCK FINANCIAL DATA (YTD - Year To Date) ---
$financials = [
    'revenue' => 15000000,
    'cogs' => 4500000,       // Cost of Goods Sold
    'gross_profit' => 0,     // Calculated below
    'opex' => 6000000,       // Operating Expenses
    'net_profit' => 0,       // Calculated below
    'cash_reserve' => 4250000,
    'avg_burn_rate' => 500000
];

$financials['gross_profit'] = $financials['revenue'] - $financials['cogs'];
$financials['net_profit'] = $financials['gross_profit'] - $financials['opex'];
$profit_margin = ($financials['revenue'] > 0) ? ($financials['net_profit'] / $financials['revenue']) * 100 : 0;
$runway_months = ($financials['avg_burn_rate'] > 0) ? ($financials['cash_reserve'] / $financials['avg_burn_rate']) : 0;

// Chart Data Mocks
$chart_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$chart_revenue = [1100000, 1250000, 1050000, 1300000, 1400000, 1200000, 1500000, 1600000, 1450000, 1350000, 1550000, 1700000];
$chart_expenses = [800000, 850000, 820000, 900000, 880000, 950000, 890000, 920000, 900000, 940000, 980000, 1050000];

// P&L Table Detail Mocks
$pl_details = [
    ['category' => 'Software Subscriptions', 'type' => 'OpEx', 'amount' => 1200000],
    ['category' => 'Payroll & Benefits', 'type' => 'OpEx', 'amount' => 3500000],
    ['category' => 'Office Rent & Utilities', 'type' => 'OpEx', 'amount' => 800000],
    ['category' => 'Marketing & Ads', 'type' => 'OpEx', 'amount' => 500000],
    ['category' => 'Cloud Hosting (AWS/GCP)', 'type' => 'COGS', 'amount' => 2500000],
    ['category' => 'Contractor Fees', 'type' => 'COGS', 'amount' => 2000000],
];
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
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; transition: all 0.3s ease; }

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
        
        /* Ensure text stays above the watermark icon */
        .kpi-card > div { position: relative; z-index: 2; }
        
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .kpi-value { font-size: 24px; font-weight: 800; color: var(--text-main); }
        .kpi-trend { font-size: 12px; margin-top: 8px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        
        /* UPDATED: Background Watermark Icons */
        .kpi-icon-bg { 
            position: absolute; 
            right: -15px; 
            bottom: -25px; 
            font-size: 120px; 
            opacity:0.65; /* Increased opacity */
            pointer-events: none; 
            z-index: 1; 
        }

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
            <p>P&L summary, cash runway, and macro-level expense analytics (YTD 2026)</p>
        </div>
        <div class="header-actions">
            <button class="btn-export" onclick="exportToExcel()"><i class="ph ph-microsoft-excel-logo"></i> Executive Export</button>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid var(--success);">
            <div class="kpi-label">YTD Revenue</div>
            <div class="kpi-value">₹<?= number_format($financials['revenue']) ?></div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph ph-trend-up"></i> +12% YoY</div>
            <i class="ph-fill ph-coins kpi-icon-bg" style="color: var(--success);"></i>
        </div>
        
        <div class="kpi-card" style="border-top: 4px solid var(--warning);">
            <div class="kpi-label">Total COGS & OpEx</div>
            <div class="kpi-value">₹<?= number_format($financials['cogs'] + $financials['opex']) ?></div>
            <div class="kpi-trend" style="color: var(--text-muted);">Avg ₹<?= number_format($financials['avg_burn_rate']) ?> / mo</div>
            <i class="ph-fill ph-calculator kpi-icon-bg" style="color: var(--warning);"></i>
        </div>

        <div class="kpi-card" style="border-top: 4px solid var(--theme-color);">
            <div class="kpi-label">Net Profit Margin</div>
            <div class="kpi-value"><?= number_format($profit_margin, 1) ?>%</div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph ph-check-circle"></i> Target > 25%</div>
            <i class="ph-fill ph-chart-pie-slice kpi-icon-bg" style="color: var(--theme-color);"></i>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
            <div class="kpi-label">Cash Runway</div>
            <div class="kpi-value"><?= number_format($runway_months, 1) ?> Months</div>
            <div class="kpi-trend" style="color: #3b82f6;"><i class="ph ph-bank"></i> ₹<?= number_format($financials['cash_reserve']) ?> Reserve</div>
            <i class="ph-fill ph-hourglass-high kpi-icon-bg" style="color: #3b82f6;"></i>
        </div>
    </div>

    <div class="dashboard-split">
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-line"></i> Cash Flow & Profit Trend (2026)</h3>
            </div>
            <div class="chart-wrapper">
                <canvas id="cashFlowMixChart"></canvas>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-pie-slice"></i> OpEx Breakdown</h3>
            </div>
            <div class="chart-wrapper" style="min-height: 250px;">
                <canvas id="expenseDoughnut"></canvas>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="ph ph-table"></i> Categorized P&L Statement (YTD)</h3>
            <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Data aggregated from General Ledger</span>
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
                        <td class="amt-col" style="color: var(--success); font-weight: 700;">₹<?= number_format($financials['revenue']) ?></td>
                    </tr>
                    
                    <tr><td colspan="3" style="background:#f8fafc; font-size:11px; font-weight:700; color:var(--text-muted);">COST OF GOODS SOLD (COGS)</td></tr>
                    <?php foreach($pl_details as $row): if($row['type'] == 'COGS'): ?>
                    <tr>
                        <td style="padding-left: 30px;"><i class="ph ph-arrow-elbow-down-right" style="color:#cbd5e1; margin-right:8px;"></i> <?= $row['category'] ?></td>
                        <td><span class="type-badge"><?= $row['type'] ?></span></td>
                        <td class="amt-col text-danger">₹<?= number_format($row['amount']) ?></td>
                    </tr>
                    <?php endif; endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;">Gross Profit:</td>
                        <td class="amt-col">₹<?= number_format($financials['gross_profit']) ?></td>
                    </tr>

                    <tr><td colspan="3" style="background:#f8fafc; font-size:11px; font-weight:700; color:var(--text-muted);">OPERATING EXPENSES (OpEx)</td></tr>
                    <?php foreach($pl_details as $row): if($row['type'] == 'OpEx'): ?>
                    <tr>
                        <td style="padding-left: 30px;"><i class="ph ph-arrow-elbow-down-right" style="color:#cbd5e1; margin-right:8px;"></i> <?= $row['category'] ?></td>
                        <td><span class="type-badge"><?= $row['type'] ?></span></td>
                        <td class="amt-col text-danger">₹<?= number_format($row['amount']) ?></td>
                    </tr>
                    <?php endif; endforeach; ?>

                    <tr class="total-row" style="border-top: 2px solid var(--theme-color);">
                        <td colspan="2" style="text-align: right; color: var(--text-main);">NET PROFIT (Pre-Tax):</td>
                        <td class="amt-col" style="color: var(--theme-color); font-size: 16px;">₹<?= number_format($financials['net_profit']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
    // --- 1. MIXED CASH FLOW CHART (Bar + Line) ---
    const ctxMix = document.getElementById('cashFlowMixChart').getContext('2d');
    
    // Calculate net profit array for the line chart overlay
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
                    ticks: { callback: function(value) { return '₹' + (value/100000) + 'L'; } }
                }
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ₹' + context.raw.toLocaleString(); } } }
            }
        }
    });

    // --- 2. EXPENSE DOUGHNUT CHART ---
    const ctxDoughnut = document.getElementById('expenseDoughnut').getContext('2d');
    new Chart(ctxDoughnut, {
        type: 'doughnut',
        data: {
            labels: ['Payroll & Benefits', 'Software Subs', 'Office Rent', 'Marketing'],
            datasets: [{
                data: [3500000, 1200000, 800000, 500000],
                backgroundColor: ['#1b5a5a', '#3b82f6', '#f59e0b', '#8b5cf6'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, padding: 20, font: { size: 11 } } }
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