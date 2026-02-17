<?php
// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../include/db_connect.php'; 

// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// --- ATTENDANCE LOGIC START ---
$current_user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$today = date('Y-m-d');

// Fetch Record
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$record = mysqli_fetch_assoc($result);

// Determine Current Status
$status = 'Not Started';
if ($record) {
    if ($record['punch_out']) {
        $status = 'Clocked Out';
    } elseif (isset($record['break_start']) && $record['break_start']) { 
        $status = 'On Break';
    } else {
        $status = 'On Duty';
    }
}

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $now = date('Y-m-d H:i:s');

    if ($_POST['action'] == 'punch_in' && !$record) {
        $sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $current_user_id, $now, $today);
        mysqli_stmt_execute($stmt);
    
    } elseif ($_POST['action'] == 'start_break' && $status == 'On Duty') {
        $sql = "UPDATE attendance SET break_start = ?, status = 'On Break' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $now, $record['id']);
        mysqli_stmt_execute($stmt);

    } elseif ($_POST['action'] == 'end_break' && $status == 'On Break') {
        $break_start = new DateTime($record['break_start']);
        $break_end = new DateTime($now);
        $diff = $break_start->diff($break_end);
        $decimal_hours = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
        $current_break = isset($record['total_break_hours']) ? $record['total_break_hours'] : 0;
        $new_total_break = $current_break + $decimal_hours;

        $sql = "UPDATE attendance SET break_start = NULL, total_break_hours = ?, status = 'On Duty' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "di", $new_total_break, $record['id']);
        mysqli_stmt_execute($stmt);

    } elseif ($_POST['action'] == 'punch_out') {
        $final_break_hours = isset($record['total_break_hours']) ? $record['total_break_hours'] : 0;
        
        if ($status == 'On Break') {
            $break_start = new DateTime($record['break_start']);
            $break_end = new DateTime($now);
            $diff = $break_start->diff($break_end);
            $final_break_hours += ($diff->h + ($diff->i / 60) + ($diff->s / 3600));
        }

        $start = new DateTime($record['punch_in']);
        $end = new DateTime($now);
        $total_diff = $start->diff($end);
        $total_duration = $total_diff->h + ($total_diff->i / 60) + ($total_diff->s / 3600);
        $production = $total_duration - $final_break_hours;
        if ($production < 0) $production = 0;

        $sql = "UPDATE attendance SET punch_out = ?, break_start = NULL, total_break_hours = ?, production_hours = ?, status = 'On Duty' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sddi", $now, $final_break_hours, $production, $record['id']);
        mysqli_stmt_execute($stmt);
    }
    
    echo "<script>window.location.href='Accounts_dashboard.php';</script>";
    exit();
}

$js_status = $status;
$js_punch_in = ($record) ? $record['punch_in'] : '';
$js_break_start = ($record && isset($record['break_start'])) ? $record['break_start'] : '';
$js_total_break = ($record && isset($record['total_break_hours'])) ? (float)$record['total_break_hours'] : 0;

// --- END ATTENDANCE LOGIC ---

include '../sidebars.php'; 
include '../header.php';

// MOCK DATA
$kpi = ['balance' => 850000, 'income' => 1250000, 'expense' => 450000, 'pending' => 125000];
$recent_transactions = [
    ['id' => 'INV-014', 'date' => '11 Feb', 'party' => 'Facebook India', 'type' => 'Invoice', 'amount' => 45000],
    ['id' => 'PO-205',  'date' => '10 Feb', 'party' => 'Dell Computers', 'type' => 'PO', 'amount' => 120000],
    ['id' => 'EXP-009', 'date' => '09 Feb', 'party' => 'Office Rent',    'type' => 'Expense', 'amount' => 25000],
    ['id' => 'SAL-Feb', 'date' => '01 Feb', 'party' => 'Staff Salary',   'type' => 'Payroll', 'amount' => 650000],
    ['id' => 'INV-013', 'date' => '30 Jan', 'party' => 'Google India',   'type' => 'Invoice', 'amount' => 12500],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Overview - Workack</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --theme-color: #1b5a5a;
            --theme-light: #e0f2f1;
            --theme-dark: #154545;
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --primary-width: 95px;
            --secondary-width: 220px;
        }

        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }

        /* Layout */
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; transition: all 0.3s ease; box-sizing: border-box; }
        .main-content.main-shifted { margin-left: calc(var(--primary-width) + var(--secondary-width)); width: calc(100% - (var(--primary-width) + var(--secondary-width))); }

        /* Dashboard Header */
        .dashboard-header { display: flex; justify-content: space-between; align-items: end; margin-bottom: 24px; }
        .date-badge { background: white; padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; color: var(--theme-color); border: 1px solid var(--border); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        
        .k-balance .kpi-icon { background: var(--theme-light); color: var(--theme-color); }
        .k-income .kpi-icon { background: #dcfce7; color: #16a34a; }
        .k-expense .kpi-icon { background: #fee2e2; color: #dc2626; }
        .k-pending .kpi-icon { background: #ffedd5; color: #d97706; }

        /* --- TOP ROW (Fixed Height Logic) --- */
        .top-row-grid { 
            display: grid; 
            grid-template-columns: 340px 1fr; 
            gap: 20px; 
            margin-bottom: 24px; 
            align-items: stretch; /* Forces both children to be same height */
        }
        @media (max-width: 1100px) { .top-row-grid { grid-template-columns: 1fr; } }

        /* ATTENDANCE CARD */
        .punch-card-new {
            background: white; border-radius: 16px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid var(--border);
            text-align: center; padding: 24px;
            display: flex; flex-direction: column; justify-content: space-between; 
            height: 100%; /* Important */
        }
        .profile-ring-container {
            position: relative; width: 120px; height: 120px; border-radius: 50%;
            background: conic-gradient(#10b981 0% 70%, #3b82f6 70% 100%);
            display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;
        }
        .profile-ring-inner { width: 108px; height: 108px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .profile-img { width: 95px; height: 95px; border-radius: 50%; object-fit: cover; }
        
        /* Buttons */
        .btn-punch-out { background-color: #111827; color: white; width: 100%; padding: 14px; border-radius: 10px; font-weight: 600; font-size: 14px; margin-bottom: 10px; transition: 0.2s; border: none; cursor: pointer; }
        .btn-punch-out:hover { background-color: #1f2937; }
        .btn-break { background-color: white; color: #f97316; border: 1px solid #fed7aa; width: 100%; padding: 12px; border-radius: 10px; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; cursor: pointer; }
        .btn-break:hover { background-color: #fff7ed; }
        .btn-punch-in { background-color: #f97316; color: white; width: 100%; padding: 14px; border-radius: 10px; font-weight: 600; font-size: 15px; border: none; cursor: pointer; }
        .btn-resume { background-color: #ecfdf5; color: #059669; border: 1px solid #6ee7b7; }

        .production-badge-new {
            background-color: #f97316; color: white; display: inline-block; padding: 6px 20px;
            border-radius: 8px; font-weight: 600; font-size: 13px; margin-bottom: 16px;
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.2);
        }

        /* --- QUICK ACTIONS (FULL HEIGHT FIX) --- */
        .actions-card { 
            background: white; padding: 24px; border-radius: 16px; 
            border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            height: 100%; /* Fill parent height */
            display: flex; flex-direction: column; 
        }
        .action-grid { 
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; 
            flex-grow: 1; /* Key: This makes the grid expand to fill vertical space */
        }
        .action-btn { 
            background: #f8fafc; border-radius: 12px; border: 1px solid var(--border); 
            text-decoration: none; display: flex; flex-direction: column; align-items: center; 
            justify-content: center; gap: 8px; transition: all 0.2s; 
            height: 100%; /* Key: Each button fills its grid cell height */
            width: 100%;
        }
        .action-btn:hover { border-color: var(--theme-color); background: var(--theme-light); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .action-btn i { font-size: 24px; color: var(--theme-color); }
        .action-btn span { font-size: 13px; font-weight: 600; color: var(--text-main); text-align: center; }

        /* Tables & Charts */
        .dashboard-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-card { background: white; padding: 24px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        
        .recent-table { width: 100%; border-collapse: collapse; }
        .recent-table td { padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .txn-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px; }
        .bg-inv { background: #e0f2fe; color: #0284c7; }
        .bg-po { background: #fce7f3; color: #db2777; }
        .bg-exp { background: #fee2e2; color: #dc2626; }
        .bg-pay { background: #f0fdf4; color: #16a34a; }

        @media (max-width: 1024px) { 
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .dashboard-split { grid-template-columns: 1fr; } 
        }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 15px; } 
            .kpi-grid { grid-template-columns: 1fr; }
            .action-grid { grid-template-columns: repeat(2, 1fr); min-height: 250px; }
        }
    </style>
</head>
<body>

<main class="main-content" id="mainContent">
    
    <div class="dashboard-header">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Accounts Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Financial overview for Neoera Infotech</p>
        </div>
        <div class="date-badge"><i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i> <?php echo date('d M, Y'); ?></div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card k-balance">
            <div class="flex items-center gap-3 mb-2">
                <div class="kpi-icon"><i data-lucide="wallet"></i></div>
                <span class="text-xs font-bold text-gray-500 uppercase">Balance</span>
            </div>
            <div class="text-2xl font-bold text-slate-800">₹<?php echo number_format($kpi['balance']); ?></div>
        </div>
        <div class="kpi-card k-income">
            <div class="flex items-center gap-3 mb-2">
                <div class="kpi-icon"><i data-lucide="arrow-down-left"></i></div>
                <span class="text-xs font-bold text-gray-500 uppercase">Income</span>
            </div>
            <div class="text-2xl font-bold text-slate-800">₹<?php echo number_format($kpi['income']); ?></div>
        </div>
        <div class="kpi-card k-expense">
            <div class="flex items-center gap-3 mb-2">
                <div class="kpi-icon"><i data-lucide="arrow-up-right"></i></div>
                <span class="text-xs font-bold text-gray-500 uppercase">Expense</span>
            </div>
            <div class="text-2xl font-bold text-slate-800">₹<?php echo number_format($kpi['expense']); ?></div>
        </div>
        <div class="kpi-card k-pending">
            <div class="flex items-center gap-3 mb-2">
                <div class="kpi-icon"><i data-lucide="clock"></i></div>
                <span class="text-xs font-bold text-gray-500 uppercase">Pending</span>
            </div>
            <div class="text-2xl font-bold text-slate-800">₹<?php echo number_format($kpi['pending']); ?></div>
        </div>
    </div>

    <div class="top-row-grid">
        
        <div class="punch-card-new">
            <div class="mb-2">
                <p class="text-gray-500 font-medium text-xs">Good Morning, <?php echo $username; ?></p>
                <h2 class="text-2xl font-bold text-gray-800 mt-1" id="liveClock">00:00 AM</h2>
                <p class="text-xs text-gray-400 font-medium mt-1" id="liveDate">-- --- ----</p>
            </div>

            <div class="profile-ring-container">
                <div class="profile-ring-inner">
                    <img src="https://i.pravatar.cc/300?img=11" alt="Profile" class="profile-img">
                </div>
            </div>

            <div class="production-badge-new">
                Production : <span id="productionTimer">0.00</span> hrs
            </div>

            <div class="flex items-center justify-center gap-2 text-gray-600 mb-6 text-sm">
                <i data-lucide="clock" class="w-4 h-4 text-emerald-500"></i>
                <span class="font-medium" id="punchTimeText">
                    <?php echo ($record) ? 'Punch In at ' . date('h:i A', strtotime($record['punch_in'])) : 'Not Punched In'; ?>
                </span>
            </div>

            <form method="POST" id="actionButtons">
                <?php if($status == 'Not Started'): ?>
                    <button type="submit" name="action" value="punch_in" class="btn-punch-in">Punch In</button>
                <?php elseif($status == 'On Duty'): ?>
                    <button type="submit" name="action" value="punch_out" class="btn-punch-out">Punch Out</button>
                    <button type="submit" name="action" value="start_break" class="btn-break"><i data-lucide="coffee" class="w-4 h-4"></i> Take a Break</button>
                <?php elseif($status == 'On Break'): ?>
                    <button type="submit" name="action" value="end_break" class="btn-break btn-resume"><i data-lucide="play" class="w-4 h-4"></i> Resume Work</button>
                <?php else: ?>
                    <button disabled class="btn-punch-out" style="background:#e5e7eb; color:#9ca3af; cursor:not-allowed;">Shift Completed</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="actions-card">
            <div class="flex items-center gap-2 mb-4">
                <i data-lucide="zap" class="text-teal-600 w-5 h-5"></i>
                <h3 class="font-bold text-base text-slate-800">Quick Actions</h3>
            </div>
            <div class="action-grid">
                <a href="new_invoice.php" class="action-btn group">
                    <i data-lucide="file-plus" class="text-slate-400 group-hover:text-teal-600"></i>
                    <span class="group-hover:text-teal-700">Invoice</span>
                </a>
                <a href="ledger.php" class="action-btn group">
                    <i data-lucide="book-open" class="text-slate-400 group-hover:text-teal-600"></i>
                    <span class="group-hover:text-teal-700">Ledger</span>
                </a>
                <a href="purchase_order.php" class="action-btn group">
                    <i data-lucide="shopping-cart" class="text-slate-400 group-hover:text-teal-600"></i>
                    <span class="group-hover:text-teal-700">New PO</span>
                </a>
                <a href="payslip.php" class="action-btn group">
                    <i data-lucide="users" class="text-slate-400 group-hover:text-teal-600"></i>
                    <span class="group-hover:text-teal-700">Payroll</span>
                </a>
                <a href="accounts_reports.php" class="action-btn group">
                    <i data-lucide="pie-chart" class="text-slate-400 group-hover:text-teal-600"></i>
                    <span class="group-hover:text-teal-700">Reports</span>
                </a>
                <a href="masters.php" class="action-btn group">
                    <i data-lucide="landmark" class="text-slate-400 group-hover:text-teal-600"></i>
                    <span class="group-hover:text-teal-700">Masters</span>
                </a>
            </div>
        </div>

    </div>

    <div class="dashboard-split">
        <div class="dashboard-card">
            <h3 class="font-bold text-slate-800 text-sm mb-4">Cash Flow (2026)</h3>
            <div class="h-64"><canvas id="cashFlowChart"></canvas></div>
        </div>
        <div class="dashboard-card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-800 text-sm">Recent Transactions</h3>
                <a href="#" class="text-xs font-bold text-teal-600 hover:underline">View All</a>
            </div>
            <table class="recent-table">
                <?php foreach($recent_transactions as $txn): 
                    $icon = 'ph-file-text'; $bg = 'bg-inv';
                    if($txn['type']=='Expense'){$icon='ph-receipt'; $bg='bg-exp';}
                    if($txn['type']=='PO'){$icon='ph-shopping-bag'; $bg='bg-po';}
                ?>
                <tr>
                    <td style="display:flex; align-items:center;">
                        <div class="txn-icon <?php echo $bg; ?>"><i class="ph <?php echo $icon; ?>"></i></div>
                        <div>
                            <div style="font-weight:600;"><?php echo $txn['party']; ?></div>
                            <div style="font-size:11px; color:#64748b;"><?php echo $txn['type']; ?></div>
                        </div>
                    </td>
                    <td style="text-align:right; font-weight:700;">₹<?php echo number_format($txn['amount']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="dashboard-split" style="grid-template-columns: 1fr 1fr;">
         <div class="dashboard-card">
            <h3 class="font-bold text-slate-800 text-sm mb-4">Expense Distribution</h3>
            <div style="height: 220px;"><canvas id="expenseChart"></canvas></div>
        </div>
        <div class="dashboard-card">
            <h3 class="font-bold text-slate-800 text-sm mb-4">Invoice Payment Status</h3>
            <div style="height: 220px;"><canvas id="invoiceBarChart"></canvas></div>
        </div>
    </div>

</main>

<script>
    lucide.createIcons();

    // 1. CLOCK
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12; hours = hours ? hours : 12; 
        document.getElementById('liveClock').textContent = `${String(hours).padStart(2,'0')}:${minutes} ${ampm}`;
        const options = { day: 'numeric', month: 'short', year: 'numeric' };
        document.getElementById('liveDate').textContent = now.toLocaleDateString('en-GB', options);
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. ATTENDANCE TIMER
    const status = "<?php echo $js_status; ?>";
    const punchInTime = "<?php echo str_replace(' ', 'T', $js_punch_in); ?>";
    const breakStartTime = "<?php echo str_replace(' ', 'T', $js_break_start); ?>";
    const totalBreakHours = <?php echo $js_total_break; ?>;

    function updateTimer() {
        if (status === 'Not Started' || status === 'Clocked Out') return;

        const now = new Date();
        const start = new Date(punchInTime);
        if (isNaN(start.getTime())) return; 

        let diffMs = now - start;
        let breakMs = totalBreakHours * 60 * 60 * 1000;
        diffMs -= breakMs;

        if (status === 'On Break' && breakStartTime) {
            const breakStart = new Date(breakStartTime);
            if (!isNaN(breakStart.getTime())) {
                const currentBreakDuration = now - breakStart;
                diffMs -= currentBreakDuration;
            }
        }

        let totalSeconds = Math.floor(diffMs / 1000);
        if(totalSeconds < 0) totalSeconds = 0;
        
        const decimalHours = (totalSeconds / 3600).toFixed(2);
        document.getElementById('productionTimer').textContent = decimalHours;
    }

    if (status === 'On Duty' || status === 'On Break') {
        setInterval(updateTimer, 1000);
        updateTimer();
    }

    // 3. CHARTS
    const commonOptions = {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }
    };

    new Chart(document.getElementById('cashFlowChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                { label: 'Income', data: [120, 190, 30, 50, 20, 300], backgroundColor: '#1b5a5a', borderRadius: 4 },
                { label: 'Expense', data: [80, 50, 30, 40, 10, 200], backgroundColor: '#ef4444', borderRadius: 4 }
            ]
        },
        options: { ...commonOptions, scales: { y: { beginAtZero: true, grid: { borderDash: [2, 2] } }, x: { grid: { display: false } } } }
    });

    new Chart(document.getElementById('expenseChart'), {
        type: 'doughnut',
        data: {
            labels: ['Rent', 'Salaries', 'Purchase', 'Utils'],
            datasets: [{ data: [25, 45, 20, 10], backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6366f1'], borderWidth: 0 }]
        },
        options: { ...commonOptions, cutout: '70%' }
    });

    new Chart(document.getElementById('invoiceBarChart'), {
        type: 'bar',
        indexAxis: 'y',
        data: {
            labels: ['Paid', 'Unpaid', 'Overdue'],
            datasets: [{ label: 'Count', data: [15, 5, 2], backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderRadius: 4, barThickness: 20 }]
        },
        options: { ...commonOptions, scales: { x: { grid: { display: false } }, y: { grid: { display: false } } } }
    });
</script>

</body>
</html>