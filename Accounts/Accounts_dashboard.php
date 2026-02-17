<?php
// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../include/db_connect.php'; 

// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// --- ATTENDANCE LOGIC START ---
$current_user_id = $_SESSION['user_id'];
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
    } elseif (isset($record['break_start']) && $record['break_start']) { // Safely check for break_start
        $status = 'On Break';
    } else {
        $status = 'On Duty';
    }
}

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $now = date('Y-m-d H:i:s');

    if ($_POST['action'] == 'punch_in' && !$record) {
        // Start Day
        $sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $current_user_id, $now, $today);
        mysqli_stmt_execute($stmt);
    
    } elseif ($_POST['action'] == 'start_break' && $status == 'On Duty') {
        // Start Break
        $sql = "UPDATE attendance SET break_start = ?, status = 'On Break' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $now, $record['id']);
        mysqli_stmt_execute($stmt);

    } elseif ($_POST['action'] == 'end_break' && $status == 'On Break') {
        // End Break & Calculate Duration
        $break_start_time = isset($record['break_start']) ? $record['break_start'] : $now;
        $break_start = new DateTime($break_start_time);
        $break_end = new DateTime($now);
        $diff = $break_start->diff($break_end);
        
        // Calculate break hours in decimal
        $decimal_hours = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
        
        $current_total_break = isset($record['total_break_hours']) ? $record['total_break_hours'] : 0;
        $new_total_break = $current_total_break + $decimal_hours;

        $sql = "UPDATE attendance SET break_start = NULL, total_break_hours = ?, status = 'On Duty' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "di", $new_total_break, $record['id']);
        mysqli_stmt_execute($stmt);

    } elseif ($_POST['action'] == 'punch_out') {
        // Punch Out Logic
        $final_break_hours = isset($record['total_break_hours']) ? $record['total_break_hours'] : 0;
        
        // If punching out while on break, close the break first
        if ($status == 'On Break') {
            $break_start_time = isset($record['break_start']) ? $record['break_start'] : $now;
            $break_start = new DateTime($break_start_time);
            $break_end = new DateTime($now);
            $diff = $break_start->diff($break_end);
            $final_break_hours += ($diff->h + ($diff->i / 60) + ($diff->s / 3600));
        }

        // Calculate Production Hours
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
    
    // Refresh to update UI
    echo "<script>window.location.href='Accounts_dashboard.php';</script>";
    exit();
}

// Prepare JS Variables
$js_status = $status;
$js_punch_in = ($record) ? $record['punch_in'] : '';
$js_break_start = ($record && isset($record['break_start'])) ? $record['break_start'] : '';
$js_total_break = ($record && isset($record['total_break_hours'])) ? (float)$record['total_break_hours'] : 0;

// --- ATTENDANCE LOGIC END ---

// 2. INCLUDE UI FILES
include '../sidebars.php'; 
include '../header.php';

// 3. MOCK DATA
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
            --theme-dark: #154545;
            --bg-body: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --primary-width: 95px;
            --secondary-width: 220px;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            margin: 0; padding: 0;
        }

        /* --- LAYOUT LOGIC --- */
        .main-content {
            margin-left: var(--primary-width); 
            width: calc(100% - var(--primary-width));
            padding: 24px;
            min-height: 100vh;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .main-content.main-shifted {
            margin-left: calc(var(--primary-width) + var(--secondary-width));
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

        /* --- ATTENDANCE & ACTIONS ROW --- */
        .top-row-grid { display: grid; grid-template-columns: 340px 1fr; gap: 20px; margin-bottom: 24px; }
        @media (max-width: 1100px) { .top-row-grid { grid-template-columns: 1fr; } }

        .dashboard-card { background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        
        /* Punch Card Styles */
        .punch-card { text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .punch-header { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; text-align: left; }
        .punch-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--text-main); }
        .punch-date { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .punch-circle-container { position: relative; width: 160px; height: 160px; margin: 10px auto 25px; }
        .punch-circle-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .circle-bg { fill: none; stroke: #f1f5f9; stroke-width: 10; }
        .circle-progress { fill: none; stroke: var(--theme-color); stroke-width: 10; stroke-linecap: round; stroke-dasharray: 440; stroke-dashoffset: 440; transition: stroke-dashoffset 1s ease; }
        
        .punch-time-display { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
        .punch-time-display .time { font-size: 28px; font-weight: 800; color: var(--text-main); display: block; line-height: 1; }
        .punch-time-display .label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; display: block; }

        .punch-status { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600; color: var(--text-muted); }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--danger); }
        .status-dot.active { background: var(--success); animation: pulse 2s infinite; }
        
        /* Button Grid */
        .btn-grid { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-action { border: none; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; }
        
        .btn-break { background: #f59e0b; color: white; }
        .btn-break:hover { background: #d97706; }
        
        .btn-resume { background: #10b981; color: white; grid-column: span 2; }
        .btn-resume:hover { background: #059669; }

        .btn-out { background: #ea580c; color: white; } 
        .btn-out:hover { background: #c2410c; }

        .btn-in { background: var(--theme-color); color: white; grid-column: span 2; }
        .btn-in:hover { background: #134e4e; }
        
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

        /* Quick Actions Styles */
        .actions-card { padding: 24px 30px; }
        .section-title { font-size: 15px; font-weight: 700; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .action-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        @media (max-width: 1200px) { .action-grid { grid-template-columns: repeat(2, 1fr); } }
        
        .action-btn { background: #f8fafc; padding: 15px 10px; border-radius: 10px; border: 1px solid var(--border); text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .action-btn:hover { border-color: var(--theme-color); background: var(--theme-light); transform: translateY(-2px); }
        .action-btn i { font-size: 24px; color: var(--theme-color); }
        .action-btn span { font-size: 12px; font-weight: 600; color: var(--text-main); text-align: center; }

        /* Dashboard Split Layout */
        .dashboard-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
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
            .action-grid { grid-template-columns: 1fr 1fr; }
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

    <div class="top-row-grid">
        
        <div class="dashboard-card punch-card">
            <div class="punch-header">
                <h3>Today's Attendance</h3>
                <span class="punch-date"><?php echo date('l, d M'); ?></span>
            </div>

            <div class="punch-circle-container">
                <svg class="punch-circle-svg" viewBox="0 0 160 160">
                    <circle class="circle-bg" cx="80" cy="80" r="70"></circle>
                    <circle class="circle-progress" id="progressCircle" cx="80" cy="80" r="70"></circle>
                </svg>
                <div class="punch-time-display">
                    <span class="time" id="timerDisplay">00:00:00</span>
                    <span class="label" id="timerLabel">Active Hours</span>
                </div>
            </div>

            <div class="punch-status">
                <div class="status-dot <?php echo ($status == 'On Duty') ? 'active' : ''; ?>" id="statusDot"></div>
                <span id="statusText">
                    <?php 
                        if ($status == 'On Duty') echo 'Status: On Duty';
                        elseif ($status == 'On Break') echo 'Status: On Break';
                        elseif ($status == 'Clocked Out') echo 'Shift Completed';
                        else echo 'Not Started';
                    ?>
                </span>
            </div>

            <form method="POST" class="btn-grid">
                <?php if($status == 'Not Started'): ?>
                    <button type="submit" name="action" value="punch_in" class="btn-action btn-in">
                        <i class="ph ph-fingerprint-simple"></i> Punch In
                    </button>
                
                <?php elseif($status == 'On Duty'): ?>
                    <button type="submit" name="action" value="start_break" class="btn-action btn-break">
                        <i class="ph ph-coffee"></i> Take Break
                    </button>
                    <button type="submit" name="action" value="punch_out" class="btn-action btn-out">
                        <i class="ph ph-sign-out"></i> Punch Out
                    </button>

                <?php elseif($status == 'On Break'): ?>
                    <button type="submit" name="action" value="end_break" class="btn-action btn-resume">
                        <i class="ph ph-play"></i> End Break & Resume
                    </button>

                <?php else: ?>
                    <button disabled class="btn-action" style="grid-column: span 2; background:#e2e8f0; color:#94a3b8; cursor:not-allowed;">
                        <i class="ph ph-check-circle"></i> Work Completed
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <div class="dashboard-card actions-card">
            <div class="section-title"><i class="ph ph-lightning"></i> Quick Actions</div>
            <div class="action-grid">
                <a href="new_invoice.php" class="action-btn"><i class="ph ph-file-plus"></i><span>Create Invoice</span></a>
                <a href="ledger.php" class="action-btn"><i class="ph ph-book-open-text"></i><span>View Ledger</span></a>
                <a href="purchase_order.php" class="action-btn"><i class="ph ph-shopping-cart"></i><span>New PO</span></a>
                <a href="payslip.php" class="action-btn"><i class="ph ph-users-three"></i><span>Payroll</span></a>
                <a href="accounts_reports.php" class="action-btn"><i class="ph ph-chart-pie-slice"></i><span>Reports</span></a>
                <a href="masters.php" class="action-btn"><i class="ph ph-bank"></i><span>Masters</span></a>
            </div>
        </div>

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
    // --- TIMER LOGIC (Client Side) ---
    const status = "<?php echo $js_status; ?>";
    const punchInTime = "<?php echo str_replace(' ', 'T', $js_punch_in); ?>";
    const breakStartTime = "<?php echo str_replace(' ', 'T', $js_break_start); ?>";
    const totalBreakHours = <?php echo $js_total_break; ?>;

    function updateTimer() {
        if (status === 'Not Started' || status === 'Clocked Out') return;

        const now = new Date();
        const start = new Date(punchInTime);
        
        if (isNaN(start.getTime())) return; // Safety check

        // Total Elapsed Time
        let diffMs = now - start;
        
        // Subtract Completed Breaks
        let breakMs = totalBreakHours * 60 * 60 * 1000;
        diffMs -= breakMs;

        // If currently ON BREAK, subtract the current ongoing break time
        if (status === 'On Break' && breakStartTime) {
            const breakStart = new Date(breakStartTime);
            if (!isNaN(breakStart.getTime())) {
                const currentBreakDuration = now - breakStart;
                diffMs -= currentBreakDuration;
            }
        }

        // Convert to HH:MM:SS
        let totalSeconds = Math.floor(diffMs / 1000);
        if(totalSeconds < 0) totalSeconds = 0;

        let h = Math.floor(totalSeconds / 3600);
        let m = Math.floor((totalSeconds % 3600) / 60);
        let s = totalSeconds % 60;

        document.getElementById('timerDisplay').innerText = 
            (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);

        // Circular Progress (9 Hours)
        const maxSeconds = 9 * 60 * 60; 
        let progress = totalSeconds / maxSeconds;
        if(progress > 1) progress = 1;
        const offset = 440 - (440 * progress);
        const circle = document.getElementById('progressCircle');
        if(circle) circle.style.strokeDashoffset = offset;
    }

    // Run Timer
    if (status === 'On Duty' || status === 'On Break') {
        setInterval(updateTimer, 1000);
        updateTimer();
    } else if (status === 'Clocked Out') {
        const circle = document.getElementById('progressCircle');
        if(circle) circle.style.strokeDashoffset = 0;
    }

    // --- CHARTS ---
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