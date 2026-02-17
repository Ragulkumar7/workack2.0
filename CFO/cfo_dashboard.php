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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .top-layout { display: grid; grid-template-columns: 350px 1fr; gap: 20px; margin-bottom: 24px; align-items: stretch; }
        .bottom-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
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
           ATTENDANCE PUNCH CARD CSS
           ========================================================= */
        .punch-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            width: 100%;
            height: 100%;
            padding: 30px 20px;
            text-align: center;
            border: 1px solid var(--border);
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .punch-card p.subtitle { color: #64748b; font-weight: 500; font-size: 14px; margin: 0; }
        .punch-card h2.clock-time { font-size: 30px; font-weight: 700; color: #1f2937; margin: 5px 0; }
        .punch-card p.date-text { font-size: 12px; color: #9ca3af; font-weight: 500; margin: 0 0 25px 0; text-transform: uppercase;}

        .profile-ring-container {
            position: relative;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: conic-gradient(#10b981 0% 70%, #3b82f6 70% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px auto;
        }

        .profile-ring-inner { width: 128px; height: 128px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .profile-img { width: 115px; height: 115px; border-radius: 50%; object-fit: cover; }

        .production-badge {
            background-color: #f97316;
            color: white;
            display: inline-block;
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            box-shadow: 0 2px 5px rgba(249, 115, 22, 0.3);
            transition: opacity 0.3s ease;
        }

        .status-display { display: flex; align-items: center; justify-content: center; gap: 8px; color: #475569; margin-bottom: 24px; font-weight: 500; font-size: 14px; }

        .btn-punch-out { background-color: #111827; color: white; width: 100%; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 16px; margin-bottom: 12px; transition: background 0.3s; border: none; cursor: pointer; }
        .btn-punch-out:hover { background-color: #1f2937; }

        .btn-break { background-color: white; color: #f97316; border: 1px solid #fed7aa; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 16px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s; cursor: pointer; }
        .btn-break:hover { background-color: #fff7ed; }

        .btn-punch-in { background-color: #f97316; color: white; width: 100%; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 16px; transition: background 0.3s; border: none; cursor: pointer; }
        .btn-punch-in:hover { background-color: #ea580c; }

        @media (max-width: 1024px) { 
            .top-layout, .bottom-layout { grid-template-columns: 1fr; } 
        }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; } 
            .global-filter { width: 100%; justify-content: center; } 
        }
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

    <div class="top-layout">
        
        <div style="display: flex; align-items: stretch; width: 100%;">
            <div class="punch-card">
                <div style="margin-bottom: 24px;">
                    <p class="subtitle">Good Morning, CFO</p>
                    <h2 class="clock-time" id="liveClock">00:00 AM</h2>
                    <p class="date-text" id="liveDate">-- --- ----</p>
                </div>

                <div class="profile-ring-container">
                    <div class="profile-ring-inner">
                        <img src="https://i.pravatar.cc/300?img=11" alt="Profile" class="profile-img">
                    </div>
                </div>

                <div class="production-badge" id="prodBadge">
                    Production : <span id="productionTimer">0.00</span> hrs
                </div>

                <div class="status-display" id="statusDisplay">
                    <i class="ph-fill ph-clock" style="color: #10b981; font-size: 16px;"></i>
                    <span id="punchTimeText">Punch In at 04:12 pm</span>
                </div>

                <div id="actionButtons">
                    </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-bar"></i> Income vs Expense Trend</h3>
            </div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

    </div>

    <div class="bottom-layout">
        
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
    // --- 1. CHART LOGIC ---
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

    // --- 2. LIVE CLOCK (Top of Punch Card) ---
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12; 
        hours = String(hours).padStart(2, '0');

        document.getElementById('liveClock').textContent = `${hours}:${minutes} ${ampm}`;
        
        const options = { day: 'numeric', month: 'short', year: 'numeric' };
        document.getElementById('liveDate').textContent = now.toLocaleDateString('en-GB', options);
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- 3. ATTENDANCE PUNCH IN / OUT LOGIC ---
    let timerInterval;
    let secondsElapsed = 0;
    
    // Initial State (Simulating "Already Punched In" like your screenshot)
    let currentState = 'in'; 
    let punchInTimeStr = "09:00 AM";

    function updatePunchUI() {
        const container = document.getElementById('actionButtons');
        const statusDisplay = document.getElementById('statusDisplay');
        const badge = document.getElementById('prodBadge');

        if (currentState === 'out') {
            container.innerHTML = `
                <button onclick="handlePunch('in')" class="btn-punch-in">
                    Punch In
                </button>`;
            statusDisplay.innerHTML = `<i class="ph-fill ph-fingerprint" style="color: #9ca3af; font-size: 16px;"></i> Not Punched In`;
            badge.style.opacity = '0.5';

        } else if (currentState === 'in') {
            container.innerHTML = `
                <button onclick="handlePunch('out')" class="btn-punch-out">
                    Punch Out
                </button>
                <button onclick="toggleBreak()" class="btn-break">
                    <i class="ph-bold ph-coffee"></i> Take a Break
                </button>`;
            statusDisplay.innerHTML = `<i class="ph-fill ph-clock" style="color: #10b981; font-size: 16px;"></i> Punch In at ${punchInTimeStr}`;
            badge.style.opacity = '1';

        } else if (currentState === 'break') {
            container.innerHTML = `
                <button onclick="toggleBreak()" class="btn-break" style="background:#fef3c7; color:#d97706; border-color:#d97706;">
                    <i class="ph-fill ph-play"></i> Resume Work
                </button>`;
            statusDisplay.innerHTML = `<i class="ph-fill ph-coffee" style="color: #f97316; font-size: 16px;"></i> On Break`;
        }
    }

    function handlePunch(action) {
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12; hours = hours ? hours : 12; 
        const timeString = `${String(hours).padStart(2, '0')}:${minutes} ${ampm}`;

        if (action === 'in') {
            currentState = 'in';
            punchInTimeStr = timeString;
            startTimer();
        } else {
            currentState = 'out';
            stopTimer();
            secondsElapsed = 0; // Reset for demo
            updateTimerDisplay();
        }
        updatePunchUI();
    }

    function toggleBreak() {
        if (currentState === 'in') {
            currentState = 'break';
            stopTimer(); // Pause timer
        } else {
            currentState = 'in';
            startTimer(); // Resume timer
        }
        updatePunchUI();
    }

    function startTimer() {
        stopTimer();
        timerInterval = setInterval(() => {
            secondsElapsed++;
            updateTimerDisplay();
        }, 1000);
    }

    function stopTimer() {
        clearInterval(timerInterval);
    }

    function updateTimerDisplay() {
        // Convert seconds to decimal hours (e.g., 1.50 hrs)
        const hours = (secondsElapsed / 3600).toFixed(2);
        document.getElementById('productionTimer').textContent = hours;
    }

    // Initialize the Punch Card on page load
    updatePunchUI();
    startTimer(); // Start ticking immediately since default state is 'in'

</script>

</body>
</html>