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
// In production, you would run SQL queries here using $selected_month and $selected_year
$multiplier = ($selected_month == date('m')) ? 1 : ($selected_month % 3 + 0.8); // Just to make numbers change for the demo

$kpi = [
    'income' => 1250000 * $multiplier,
    'expense' => 450000 * $multiplier,
    'profit' => (1250000 - 450000) * $multiplier,
    'ar' => 380000 * $multiplier, // Accounts Receivable
];

$recent_invoices = [
    ['no' => 'INV-2026-014', 'client' => 'Facebook India', 'date' => "15-$selected_month-$selected_year", 'amount' => 45000, 'status' => 'Paid'],
    ['no' => 'INV-2026-013', 'client' => 'Google India', 'date' => "12-$selected_month-$selected_year", 'amount' => 12500, 'status' => 'Unpaid'],
    ['no' => 'INV-2026-012', 'client' => 'Neoera', 'date' => "05-$selected_month-$selected_year", 'amount' => 8500, 'status' => 'Overdue'],
];

$recent_pos = [
    ['no' => 'PO-IT-205', 'vendor' => 'Dell Computers', 'date' => "10-$selected_month-$selected_year", 'amount' => 120000, 'status' => 'Approved'],
    ['no' => 'PO-IT-204', 'vendor' => 'Stationery World', 'date' => "08-$selected_month-$selected_year", 'amount' => 5000, 'status' => 'Pending'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFO Executive Dashboard - Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --primary-width: 95px;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            margin: 0; padding: 0;
        }

        .main-content {
            margin-left: var(--primary-width);
            width: calc(100% - var(--primary-width));
            padding: 24px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Header & Filters */
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        .welcome-text h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .welcome-text p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        
        .global-filter { background: white; padding: 10px 20px; border-radius: 50px; border: 1px solid var(--border); display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .global-filter select { border: none; font-size: 14px; font-weight: 600; color: var(--theme-color); outline: none; background: transparent; cursor: pointer; }
        .global-filter i { color: var(--theme-color); font-size: 18px; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .kpi-value { font-size: 24px; font-weight: 800; color: var(--text-main); }
        .kpi-trend { font-size: 12px; margin-top: 8px; font-weight: 600; display: flex; align-items: center; gap: 4px; }

        /* Layout Grids */
        .dashboard-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-card { background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); height: 100%; display: flex; flex-direction: column; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--theme-color); display: flex; align-items: center; gap: 8px; }

        /* Tables */
        .table-responsive { flex-grow: 1; overflow-y: auto; max-height: 300px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border); background: #f8fafc; position: sticky; top: 0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; color: var(--text-main); }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .st-paid { background: #dcfce7; color: #15803d; }
        .st-unpaid { background: #fef08a; color: #a16207; }
        .st-overdue { background: #fee2e2; color: #b91c1c; }

        /* =========================================================
           ATTENDANCE WIDGET STYLES (Preserved Exactly)
           ========================================================= */
        .att-widget-container { display: flex; flex-direction: column; align-items: center; padding: 10px 0; }
        .att-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .att-datetime { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 25px; }

        .circle-container { position: relative; width: 170px; height: 170px; margin-bottom: 20px; }
        .circle-svg { transform: rotate(-90deg); width: 100%; height: 100%; }
        .circle-bg { fill: none; stroke: #f1f5f9; stroke-width: 14; }
        .circle-progress { fill: none; stroke: #0d9488; stroke-width: 14; stroke-linecap: round; stroke-dasharray: 440; stroke-dashoffset: 440; transition: stroke-dashoffset 1s linear; }
        
        .circle-inner { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
        .circle-inner-label { font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .circle-inner-time { font-size: 26px; font-weight: 800; color: #1e293b; font-variant-numeric: tabular-nums; }

        .att-status-badge { background: #ccfbf1; color: #0f766e; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; transition: all 0.3s; }
        .att-status-badge.off-duty { background: #f1f5f9; color: #64748b; }
        
        .punch-info { font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 6px; margin-bottom: 20px; opacity: 0; transition: opacity 0.3s; }
        .punch-info.visible { opacity: 1; }

        .btn-punch { width: 100%; padding: 14px; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; color: white; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-punch-out { background-color: #f97316; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2); }
        .btn-punch-out:hover { background-color: #ea580c; }
        .btn-punch-in { background-color: #10b981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-punch-in:hover { background-color: #059669; }

        @media (max-width: 1024px) { .dashboard-split { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; } .global-filter { width: 100%; justify-content: center; } }
    </style>
</head>
<body>

<main class="main-content" id="mainContent">
    
    <div class="dashboard-header">
        <div class="welcome-text">
            <h1>Executive Overview</h1>
            <p>Financial performance and accounting activity stream</p>
        </div>
        
        <form method="GET" class="global-filter" id="filterForm">
            <i class="ph ph-calendar-blank"></i>
            <select name="month" onchange="document.getElementById('filterForm').submit()">
                <?php foreach($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= ($selected_month == $num) ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" onchange="document.getElementById('filterForm').submit()" style="border-left: 1px solid var(--border); padding-left: 10px; margin-left: 5px;">
                <option value="2026" <?= ($selected_year == '2026') ? 'selected' : '' ?>>2026</option>
                <option value="2025" <?= ($selected_year == '2025') ? 'selected' : '' ?>>2025</option>
            </select>
        </form>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid var(--success);">
            <div class="kpi-label">Total Income</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['income']); ?></div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph ph-trend-up"></i> Collected this month</div>
        </div>
        <div class="kpi-card" style="border-top: 4px solid var(--danger);">
            <div class="kpi-label">Total Expenses</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['expense']); ?></div>
            <div class="kpi-trend" style="color: var(--text-muted);"><i class="ph ph-receipt"></i> Processed by Accounts</div>
        </div>
        <div class="kpi-card" style="border-top: 4px solid var(--theme-color);">
            <div class="kpi-label">Net Profit</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['profit']); ?></div>
            <div class="kpi-trend" style="color: var(--success);"><i class="ph ph-chart-line-up"></i> Margin Overview</div>
        </div>
        <div class="kpi-card" style="border-top: 4px solid var(--warning);">
            <div class="kpi-label">Accounts Receivable</div>
            <div class="kpi-value">₹<?php echo number_format($kpi['ar']); ?></div>
            <div class="kpi-trend" style="color: var(--warning);"><i class="ph ph-clock"></i> Pending Collection</div>
        </div>
    </div>

    <div class="dashboard-split">
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-bar"></i> Income vs Expense Trend</h3>
            </div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="att-widget-container">
                <div class="att-title">TODAY'S ATTENDANCE</div>
                <div class="att-datetime" id="liveDateTime">Loading...</div>

                <div class="circle-container">
                    <svg class="circle-svg" viewBox="0 0 160 160">
                        <circle class="circle-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="circle-progress" id="progressCircle" cx="80" cy="80" r="70"></circle>
                    </svg>
                    <div class="circle-inner">
                        <div class="circle-inner-label">TOTAL HOURS</div>
                        <div class="circle-inner-time" id="timerDisplay">00:00:00</div>
                    </div>
                </div>

                <div class="att-status-badge off-duty" id="statusBadge">
                    <i class="ph ph-clock" id="statusIcon"></i> <span id="statusText">Status: Off Duty</span>
                </div>

                <div class="punch-info" id="punchInfoDiv">
                    <i class="ph ph-fingerprint" style="color: #f97316; font-size: 16px;"></i> 
                    Punch In at <span id="punchInTimeDisplay">--:--</span>
                </div>

                <button id="mainPunchBtn" class="btn-punch btn-punch-in" onclick="togglePunch()">
                    <i class="ph ph-sign-in" id="btnIcon"></i>
                    <span id="btnText">Punch In</span>
                </button>
            </div>
        </div>
    </div>

    <div class="dashboard-split" style="grid-template-columns: 1fr 1fr;">
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-file-text"></i> Recent Invoices Created</h3>
                <a href="accounts_reports.php" style="font-size:12px; color:var(--theme-color); text-decoration:none;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Invoice #</th><th>Client</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_invoices as $inv): 
                            $badge_class = 'st-unpaid';
                            if($inv['status'] == 'Paid') $badge_class = 'st-paid';
                            if($inv['status'] == 'Overdue') $badge_class = 'st-overdue';
                        ?>
                        <tr>
                            <td><strong><?= $inv['no'] ?></strong><br><small style="color:var(--text-muted);"><?= $inv['date'] ?></small></td>
                            <td><?= $inv['client'] ?></td>
                            <td style="font-weight:600;">₹<?= number_format($inv['amount']) ?></td>
                            <td><span class="status-badge <?= $badge_class ?>"><?= $inv['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-shopping-cart"></i> Recent Purchase Orders</h3>
                <a href="cfo_approvals.php" style="font-size:12px; color:var(--theme-color); text-decoration:none;">Manage Approvals</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>PO Number</th><th>Vendor</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_pos as $po): 
                            $badge_class = $po['status'] == 'Approved' ? 'st-paid' : 'st-unpaid';
                        ?>
                        <tr>
                            <td><strong><?= $po['no'] ?></strong><br><small style="color:var(--text-muted);"><?= $po['date'] ?></small></td>
                            <td><?= $po['vendor'] ?></td>
                            <td style="font-weight:600;">₹<?= number_format($po['amount']) ?></td>
                            <td><span class="status-badge <?= $badge_class ?>"><?= $po['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<script>
    // --- CHART LOGIC ---
    // Simulating dynamic chart data based on the selected month/year
    const ctx = document.getElementById('cashFlowChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [
                { label: 'Income', data: [300000, 450000, 200000, <?php echo $kpi['income'] / 4; ?>], backgroundColor: '#10b981', borderRadius: 4 },
                { label: 'Expense', data: [100000, 150000, 50000, <?php echo $kpi['expense'] / 4; ?>], backgroundColor: '#ef4444', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, grid: { borderDash: [2, 2] } }, x: { grid: { display: false } } },
            plugins: { legend: { position: 'top', align: 'end' } }
        }
    });

    // --- ATTENDANCE LIVE CLOCK & DATE ---
    function updateHeaderDateTime() {
        const now = new Date();
        let hours = now.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; 
        const minutes = now.getMinutes().toString().padStart(2, '0');
        
        const day = now.getDate().toString().padStart(2, '0');
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const month = monthNames[now.getMonth()];
        const year = now.getFullYear();
        
        document.getElementById('liveDateTime').textContent = `${hours}:${minutes} ${ampm}, ${day} ${month} ${year}`;
    }
    setInterval(updateHeaderDateTime, 1000);
    updateHeaderDateTime();

    // --- ATTENDANCE PUNCH IN / OUT LOGIC ---
    let isPunchedIn = false; 
    let punchInTimestamp = null;
    let timerInterval = null;
    const circleCircumference = 440; 

    function togglePunch() {
        const btn = document.getElementById('mainPunchBtn');
        const btnIcon = document.getElementById('btnIcon');
        const btnText = document.getElementById('btnText');
        const statusBadge = document.getElementById('statusBadge');
        const statusText = document.getElementById('statusText');
        const punchInfoDiv = document.getElementById('punchInfoDiv');
        const punchInTimeDisplay = document.getElementById('punchInTimeDisplay');
        const progressCircle = document.getElementById('progressCircle');
        
        if (!isPunchedIn) {
            // PUNCH IN
            const now = new Date();
            punchInTimestamp = now;
            
            let h = now.getHours();
            let m = now.getMinutes().toString().padStart(2, '0');
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; h = h ? h : 12;
            punchInTimeDisplay.textContent = `${h.toString().padStart(2, '0')}:${m} ${ampm}`;
            
            statusBadge.classList.remove('off-duty');
            statusText.textContent = 'Status: On Duty';
            punchInfoDiv.classList.add('visible');
            
            btn.classList.remove('btn-punch-in');
            btn.classList.add('btn-punch-out');
            btnIcon.classList.replace('ph-sign-in', 'ph-sign-out');
            btnText.textContent = 'Punch Out';
            isPunchedIn = true;

            timerInterval = setInterval(updateLiveTimer, 1000);
            updateLiveTimer();

        } else {
            // PUNCH OUT
            if(confirm("Are you sure you want to punch out?")) {
                clearInterval(timerInterval);
                statusBadge.classList.add('off-duty');
                statusText.textContent = 'Status: Off Duty';
                punchInfoDiv.classList.remove('visible');
                
                btn.classList.remove('btn-punch-out');
                btn.classList.add('btn-punch-in');
                btnIcon.classList.replace('ph-sign-out', 'ph-sign-in');
                btnText.textContent = 'Punch In';
                isPunchedIn = false;
            }
        }
    }

    function updateLiveTimer() {
        if (!punchInTimestamp) return;
        const now = new Date();
        const diffMs = now - punchInTimestamp;
        
        const diffHrs = Math.floor(diffMs / 3600000);
        const diffMins = Math.floor((diffMs % 3600000) / 60000);
        const diffSecs = Math.floor((diffMs % 60000) / 1000);
        
        document.getElementById('timerDisplay').textContent = 
            `${diffHrs.toString().padStart(2, '0')}:` +
            `${diffMins.toString().padStart(2, '0')}:` +
            `${diffSecs.toString().padStart(2, '0')}`;

        const workDaySeconds = 9 * 60 * 60; 
        const totalElapsedSeconds = Math.floor(diffMs / 1000);
        
        let percentage = totalElapsedSeconds / workDaySeconds;
        if (percentage > 1) percentage = 1;

        const offset = circleCircumference - (percentage * circleCircumference);
        document.getElementById('progressCircle').style.strokeDashoffset = offset;
    }
</script>

</body>
</html>