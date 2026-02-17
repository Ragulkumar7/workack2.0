<?php
// --- 1. SESSION & DATABASE CONNECTION ---
$path_to_root = '../';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database Connection
require_once '../include/db_connect.php';

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// --- 2. HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $total_days = intval($_POST['total_days']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    if ($leave_type && $start_date && $end_date && $total_days > 0) {
        $sql = "INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, total_days, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssis", $user_id, $leave_type, $start_date, $end_date, $total_days, $reason);
        
        if (mysqli_stmt_execute($stmt)) {
            // Redirect to avoid resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
            exit();
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error submitting request.</div>";
        }
    } else {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Please fill all fields correctly.</div>";
    }
}

// Display Success Message
if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Leave request submitted successfully!</div>";
}

// --- 3. FETCH LEAVE STATISTICS ---
// Defined Quotas (You can move these to a database settings table later)
$quotas = [
    'Annual' => 12,
    'Medical' => 12,
    'Casual' => 12,
    'Other' => 12
];

// Calculate Used Leaves
$stats_sql = "SELECT leave_type, SUM(total_days) as used_days 
              FROM leave_requests 
              WHERE user_id = ? AND status = 'Approved' 
              GROUP BY leave_type";
$stmt_stats = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt_stats, "i", $user_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);

$used = ['Annual' => 0, 'Medical' => 0, 'Casual' => 0, 'Other' => 0];
while ($row = mysqli_fetch_assoc($result_stats)) {
    if (isset($used[$row['leave_type']])) {
        $used[$row['leave_type']] = $row['used_days'];
    }
}

// Total Summaries
$total_entitled = array_sum($quotas);
$total_used = array_sum($used);
$total_remaining = $total_entitled - $total_used;

// --- 4. FETCH LEAVE HISTORY ---
$history_sql = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC";
$stmt_hist = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($stmt_hist, "i", $user_id);
mysqli_stmt_execute($stmt_hist);
$history_result = mysqli_stmt_get_result($stmt_hist);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaves - HRMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script> <style>
        /* --- GLOBAL VARIABLES & RESET --- */
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --bg-body: #f8f9fa;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT ADJUSTMENT --- */
        .main-content {
            margin-left: 95px; /* Adjust based on your sidebar width */
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; gap: 15px; flex-wrap: wrap;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb {
            display: flex; align-items: center; font-size: 13px; color: var(--text-muted);
            gap: 8px; margin-top: 5px;
        }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; font-size: 14px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f1f5f9; }
        .btn-primary {
            background-color: var(--primary); color: white; border-color: var(--primary);
        }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* --- STATS CARDS --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px;
            position: relative; overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
        .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .stat-badge {
            display: inline-block; padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 600;
        }
        .card-decoration {
            position: absolute; right: -20px; top: 50%; transform: translateY(-50%);
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; opacity: 0.15;
        }
        
        /* Specific Colors */
        .card-annual .stat-badge { background: #eefcfd; color: #16636B; }
        .card-annual .stat-value { color: #16636B; }
        .card-annual .card-decoration { background: #16636B; color: #16636B; opacity: 1; }
        .card-annual .card-decoration i { color: white; position: relative; z-index: 2; }
        
        .card-medical .stat-badge { background: #dbeafe; color: #2563eb; }
        .card-medical .card-decoration { background: #3b82f6; }
        
        .card-casual .stat-badge { background: #f3e8ff; color: #9333ea; }
        .card-casual .card-decoration { background: #a855f7; }
        
        .card-other .stat-badge { background: #fce7f3; color: #db2777; }
        .card-other .card-decoration { background: #ec4899; }
        .card-icon { width: 24px; height: 24px; color: white; }

        /* --- LIST SECTION --- */
        .list-section {
            background: white; border-radius: 12px; border: 1px solid var(--border);
            padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .list-header {
            display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .list-title { font-size: 18px; font-weight: 700; margin-right: auto; }
        .badge-pill { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-orange { background: #ffedd5; color: #c2410c; }
        .badge-cyan { background: #ecfeff; color: #0e7490; }

        /* Filters */
        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .input-group {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px; background: white;
            color: var(--text-muted); font-size: 13px; flex: 1; min-width: 150px;
        }
        .input-group input, .input-group select {
            border: none; outline: none; color: var(--text-main); font-size: 13px;
            width: 100%; background: transparent; margin-left: 8px; cursor: pointer;
        }

        /* Table */
        .table-container { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { text-align: left; font-size: 12px; color: var(--text-muted); padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; }
        td { font-size: 13px; color: #334155; padding: 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .status-badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .status-Approved { background: #dcfce7; color: #166534; }
        .status-Pending { background: #fef9c3; color: #854d0e; }
        .status-Rejected { background: #fee2e2; color: #991b1b; }

        /* --- MODAL --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .modal-box {
            background: white; width: 650px; max-width: 95%; border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column; overflow: hidden;
        }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .modal-body { padding: 24px; overflow-y: auto; max-height: 70vh; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #334155; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #fff; }
    </style>
</head>
<body>

    <?php 
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
    if (file_exists($sidebarPath)) { include($sidebarPath); } 
    ?>
    <?php include '../header.php'; ?> 

    <div class="main-content" id="mainContent">
        
        <div class="page-header">
            <div class="header-title">
                <h1>Leaves</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px; height:14px;"></i>
                    <span>/</span> <span>Attendance</span> <span>/</span>
                    <span class="active" style="color:#0f172a; font-weight:600;">Leaves</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal()">
                    <i data-lucide="plus-circle" style="width:16px;"></i> Add Leave
                </button>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="stats-grid">
            <div class="stat-card card-annual">
                <div class="stat-title">Annual Leaves</div>
                <div class="stat-value"><?php echo $used['Annual']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Annual'] - $used['Annual']; ?></div>
                <div class="card-decoration"><i data-lucide="calendar" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-medical">
                <div class="stat-title">Medical Leaves</div>
                <div class="stat-value"><?php echo $used['Medical']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Medical'] - $used['Medical']; ?></div>
                <div class="card-decoration"><i data-lucide="syringe" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-casual">
                <div class="stat-title">Casual Leaves</div>
                <div class="stat-value"><?php echo $used['Casual']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Casual'] - $used['Casual']; ?></div>
                <div class="card-decoration"><i data-lucide="hexagon" class="card-icon"></i></div>
            </div>
            <div class="stat-card card-other">
                <div class="stat-title">Other Leaves</div>
                <div class="stat-value"><?php echo $used['Other']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Other'] - $used['Other']; ?></div>
                <div class="card-decoration"><i data-lucide="package-plus" class="card-icon"></i></div>
            </div>
        </div>

        <div class="list-section">
            <div class="list-header">
                <span class="list-title">Leave History</span>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <span class="badge-pill badge-orange">Total Entitled: <?php echo $total_entitled; ?></span>
                    <span class="badge-pill badge-cyan">Overall Remaining: <?php echo $total_remaining; ?></span>
                </div>
            </div>

            <div class="filters-row">
                <div class="input-group">
                    <i data-lucide="calendar-days" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" id="filterDate" placeholder="Filter by Date..." onkeyup="filterTable()">
                </div>
                <div class="input-group">
                    <select id="filterType" onchange="filterTable()">
                        <option value="">All Leave Types</option>
                        <option value="Annual">Annual Leave</option>
                        <option value="Medical">Medical Leave</option>
                        <option value="Casual">Casual Leave</option>
                    </select>
                </div>
                <div class="input-group">
                    <select id="filterStatus" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table id="leavesTable">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Approved By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($history_result) > 0) {
                            while($row = mysqli_fetch_assoc($history_result)) { 
                                $statusIcon = match($row['status']) {
                                    'Approved' => 'check',
                                    'Rejected' => 'x',
                                    default => 'clock'
                                };
                        ?>
                        <tr>
                            <td><span style="font-weight:600;"><?php echo htmlspecialchars($row['leave_type']); ?></span></td>
                            <td><?php echo date("d/m/Y", strtotime($row['start_date'])) . ' - ' . date("d/m/Y", strtotime($row['end_date'])); ?></td>
                            <td><?php echo $row['total_days']; ?></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td><?php echo $row['approved_by'] ? htmlspecialchars($row['approved_by']) : '--'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i data-lucide="<?php echo $statusIcon; ?>" style="width:10px;"></i> <?php echo $row['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php 
                            } 
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center;'>No leave requests found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leaveModal">
        <div class="modal-box">
            <form method="POST" action="">
                <div class="modal-header">
                    <h3>Add Leave Request</h3>
                    <div class="close-icon" onclick="closeModal()">
                        <i data-lucide="x-circle" style="width:24px; height:24px;"></i>
                    </div>
                </div>
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Leave Type</label>
                            <select name="leave_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="Annual">Annual Leave</option>
                                <option value="Medical">Medical Leave</option>
                                <option value="Casual">Casual Leave</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>From</label>
                            <input type="date" name="start_date" id="dateFrom" class="form-control" required onchange="calculateDays()">
                        </div>
                        <div class="form-group">
                            <label>To</label>
                            <input type="date" name="end_date" id="dateTo" class="form-control" required onchange="calculateDays()">
                        </div>
                        <div class="form-group">
                            <label>No of Days</label>
                            <input type="number" name="total_days" id="noOfDays" class="form-control bg-gray-100" readonly>
                        </div>
                        <div class="form-group full-width">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="submit_leave" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openModal() {
            document.getElementById('leaveModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('leaveModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Calculate Days between two dates
        function calculateDays() {
            const start = document.getElementById('dateFrom').value;
            const end = document.getElementById('dateTo').value;
            const output = document.getElementById('noOfDays');

            if(start && end) {
                const d1 = new Date(start);
                const d2 = new Date(end);
                
                const diffTime = Math.abs(d2 - d1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; 

                if(d2 < d1) {
                    alert("End date cannot be before start date");
                    document.getElementById('dateTo').value = "";
                    output.value = "";
                } else {
                    output.value = diffDays;
                }
            }
        }

        // Simple Table Filter
        function filterTable() {
            const typeFilter = document.getElementById('filterType').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const dateFilter = document.getElementById('filterDate').value.toLowerCase();
            
            const table = document.getElementById('leavesTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const typeTd = tr[i].getElementsByTagName("td")[0];
                const dateTd = tr[i].getElementsByTagName("td")[1];
                const statusTd = tr[i].getElementsByTagName("td")[5];
                
                if (typeTd && statusTd) {
                    const typeTxt = typeTd.textContent || typeTd.innerText;
                    const dateTxt = dateTd.textContent || dateTd.innerText;
                    const statusTxt = statusTd.textContent || statusTd.innerText;

                    const showType = typeTxt.toLowerCase().indexOf(typeFilter) > -1;
                    const showStatus = statusTxt.toLowerCase().indexOf(statusFilter) > -1;
                    const showDate = dateTxt.toLowerCase().indexOf(dateFilter) > -1;

                    tr[i].style.display = (showType && showStatus && showDate) ? "" : "none";
                }
            }
        }
    </script>
</body>
</html>