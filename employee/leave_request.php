<?php
// --- 1. SESSION & DATABASE CONNECTION ---
ob_start(); // Prevent "headers already sent" warnings
$path_to_root = '../';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database Connection
require_once '../include/db_connect.php';

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// [PERFORMANCE FIX]: Prevent Session Locking. 
session_write_close();

$message = "";

// --- 2. HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $total_days = intval($_POST['total_days']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    if ($leave_type && $start_date && $end_date && $total_days > 0) {
        
        // --- NEW LOGIC: FETCH TL AND MANAGER ID ---
        $tl_id = null;
        $manager_id = null;
        
        $get_managers_sql = "SELECT reporting_to, manager_id FROM employee_profiles WHERE user_id = ?";
        $stmt_managers = mysqli_prepare($conn, $get_managers_sql);
        if ($stmt_managers) {
            mysqli_stmt_bind_param($stmt_managers, "i", $user_id);
            mysqli_stmt_execute($stmt_managers);
            $manager_res = mysqli_stmt_get_result($stmt_managers);
            if ($m_row = mysqli_fetch_assoc($manager_res)) {
                $tl_id = isset($m_row['reporting_to']) ? $m_row['reporting_to'] : null;
                $manager_id = isset($m_row['manager_id']) ? $m_row['manager_id'] : null;
            }
            mysqli_stmt_close($stmt_managers);
        }

        // --- UPDATED LOGIC: INSERT LEAVE REQUEST WITH TL AND MANAGER IDs ---
        $sql = "INSERT INTO leave_requests (user_id, tl_id, manager_id, leave_type, start_date, end_date, total_days, reason, status, tl_status, manager_status, hr_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', 'Pending', 'Pending')";
        
        $stmt = mysqli_prepare($conn, $sql);
        
        // "iiisssis" stands for Integer, Integer, Integer, String, String, String, Integer, String
        mysqli_stmt_bind_param($stmt, "iiisssis", $user_id, $tl_id, $manager_id, $leave_type, $start_date, $end_date, $total_days, $reason);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
            exit();
        } else {
            $message = "<div class='alert-error'><i class='fa-solid fa-circle-exclamation'></i> Error submitting request.</div>";
        }
    } else {
        $message = "<div class='alert-error'><i class='fa-solid fa-circle-exclamation'></i> Please fill all fields correctly.</div>";
    }
}

// Display Success Message
if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Leave request submitted successfully!</div>";
}

// --- 3. FETCH LEAVE STATISTICS ---
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

$total_entitled = array_sum($quotas);
$total_used = array_sum($used);
$total_remaining = $total_entitled - $total_used;

// --- 4. FETCH LEAVE HISTORY WITH APPROVER NAMES ---
// [PERFORMANCE FIX]
$history_sql = "
    SELECT lr.*, 
           (SELECT COALESCE(ep.full_name, u.username) FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = lr.tl_id LIMIT 1) as tl_name,
           (SELECT COALESCE(ep.full_name, u.username) FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = lr.manager_id LIMIT 1) as mgr_name
    FROM leave_requests lr
    WHERE lr.user_id = ? 
    ORDER BY lr.created_at DESC
";
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
    <title>Leave Management - SmartHR</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script> 
    
    <style>
        /* --- GLOBAL VARIABLES & RESET --- */
        :root {
            --primary: #0d9488; /* Teal */
            --primary-hover: #0f766e;
            --bg-body: #f8fafc;
            --border-color: #e2e8f0;
            --sidebar-primary-width: 95px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: #1e293b;
            overflow-x: hidden;
        }

        /* --- LAYOUT ADJUSTMENT --- */
        .main-content {
            margin-left: var(--sidebar-primary-width); 
            padding: 30px;
            width: calc(100% - var(--sidebar-primary-width));
            min-height: 100vh;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        @media (max-width: 991px) {
            .main-content { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }
        }

        /* --- HEADER --- */
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { color: #0f172a; font-weight: 800; font-size: 28px; line-height: 1.2; letter-spacing: -0.5px; margin:0;}
        .breadcrumb { font-size: 13px; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-top: 4px;}
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; font-size: 13px; font-weight: 700;
            border-radius: 8px; border: 1px solid var(--border-color);
            background: white; color: #475569;
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f8fafc; color:#0f172a; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 2px 4px rgba(13, 148, 136, 0.2); }
        .btn-primary:hover { background-color: var(--primary-hover); color:white; }

        /* Alerts */
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }

        /* --- STATS CARDS --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white; border-radius: 16px; padding: 25px;
            position: relative; overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .stat-title { font-size: 11px; color: #64748b; margin-bottom: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;}
        .stat-value { font-size: 32px; font-weight: 900; margin-bottom: 8px; line-height: 1; color: #0f172a;}
        .stat-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; }
        .card-decoration {
            position: absolute; right: -15px; top: 50%; transform: translateY(-50%);
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; opacity: 0.15;
        }
        
        .card-annual .stat-badge { background: #f0fdfa; color: var(--primary); }
        .card-annual .card-decoration { background: var(--primary); color: var(--primary); opacity: 1; }
        .card-annual .card-decoration i { color: white; position: relative; z-index: 2; font-size: 24px;}
        
        .card-medical .stat-badge { background: #eff6ff; color: #2563eb; }
        .card-medical .card-decoration { background: #3b82f6; opacity:1;}
        .card-medical .card-decoration i { color: white; position: relative; z-index: 2; font-size: 24px;}
        
        .card-casual .stat-badge { background: #f3e8ff; color: #9333ea; }
        .card-casual .card-decoration { background: #a855f7; opacity:1;}
        .card-casual .card-decoration i { color: white; position: relative; z-index: 2; font-size: 24px;}
        
        .card-other .stat-badge { background: #fce7f3; color: #db2777; }
        .card-other .card-decoration { background: #ec4899; opacity:1;}
        .card-other .card-decoration i { color: white; position: relative; z-index: 2; font-size: 24px;}

        /* --- LIST SECTION --- */
        .card { background: #fff; border-radius: 16px; padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .list-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .list-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-right: auto; }
        .badge-pill { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-orange { background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5;}
        .badge-cyan { background: #f0fdfa; color: #0d9488; border: 1px solid #ccfbf1;}

        /* Filters */
        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px dashed #cbd5e1;}
        .input-group { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 12px; background: white; color: #64748b; font-size: 13px; flex: 1; min-width: 150px; font-weight: 500;}
        .input-group input, .input-group select { border: none; outline: none; color: #1e293b; font-size: 13px; width: 100%; background: transparent; margin-left: 8px; cursor: pointer; font-family: inherit; font-weight: 600;}

        /* Table */
        .table-container { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { text-align: left; font-size: 12px; color: #64748b; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background:#f8fafc;}
        td { font-size: 13px; color: #334155; padding: 16px 20px; border-bottom: 1px solid #f8fafc; vertical-align: middle; font-weight: 500;}
        tr:hover td { background-color: #f8fafc; }
        
        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .status-Approved { background: #ecfdf5; color: #10b981; }
        .status-Pending { background: #fff7ed; color: #ea580c; }
        .status-Rejected { background: #fef2f2; color: #dc2626; }

        /* --- MODAL --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal-box { background: white; width: 650px; max-width: 95%; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .modal-header h3 { font-size: 20px; font-weight: 800; color: #0f172a; margin:0;}
        .modal-body { padding: 24px; overflow-y: auto; max-height: 70vh; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; color: #475569; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; font-family: inherit; font-weight: 500; transition: 0.2s;}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13,148,136,0.1);}
        .modal-footer { padding: 20px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; }
    </style>
</head>
<body>

    <?php 
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
    if (file_exists($sidebarPath)) { include($sidebarPath); } 
    ?>
    <?php include '../header.php'; ?> 

    <main class="main-content" id="mainContent">
        
        <div class="page-header">
            <div>
                <h2>Time Off Management</h2>
                <div class="breadcrumb">
                    <i class="fa-solid fa-plane-departure text-teal-600 mr-2"></i>
                    Attendance <span style="margin: 0 6px;">/</span> My Leaves
                </div>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fa-solid fa-plus"></i> Request Leave
            </button>
        </div>

        <?php if(!empty($message)) echo $message; ?>

        <div class="stats-grid">
            <div class="stat-card card-annual">
                <div class="stat-title">Annual Leaves</div>
                <div class="stat-value"><?php echo $used['Annual']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Annual'] - $used['Annual']; ?></div>
                <div class="card-decoration"><i class="fa-regular fa-calendar-check"></i></div>
            </div>
            <div class="stat-card card-medical">
                <div class="stat-title">Medical Leaves</div>
                <div class="stat-value"><?php echo $used['Medical']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Medical'] - $used['Medical']; ?></div>
                <div class="card-decoration"><i class="fa-solid fa-suitcase-medical"></i></div>
            </div>
            <div class="stat-card card-casual">
                <div class="stat-title">Casual Leaves</div>
                <div class="stat-value"><?php echo $used['Casual']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Casual'] - $used['Casual']; ?></div>
                <div class="card-decoration"><i class="fa-solid fa-mug-hot"></i></div>
            </div>
            <div class="stat-card card-other">
                <div class="stat-title">Other Leaves</div>
                <div class="stat-value"><?php echo $used['Other']; ?></div>
                <div class="stat-badge">Remaining: <?php echo $quotas['Other'] - $used['Other']; ?></div>
                <div class="card-decoration"><i class="fa-solid fa-plane"></i></div>
            </div>
        </div>

        <div class="card">
            <div class="list-header">
                <span class="list-title">My Leave History</span>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <span class="badge-pill badge-orange">Total Entitled: <?php echo $total_entitled; ?></span>
                    <span class="badge-pill badge-cyan">Overall Remaining: <?php echo $total_remaining; ?></span>
                </div>
            </div>

            <div class="filters-row">
                <div class="input-group">
                    <i class="fa-regular fa-calendar text-slate-400"></i>
                    <input type="text" id="filterDate" placeholder="Filter by Date..." onkeyup="filterTable()">
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-list text-slate-400"></i>
                    <select id="filterType" onchange="filterTable()">
                        <option value="">All Leave Types</option>
                        <option value="Annual">Annual Leave</option>
                        <option value="Medical">Medical Leave</option>
                        <option value="Casual">Casual Leave</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-filter text-slate-400"></i>
                    <select id="filterStatus" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="leavesTable">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date Range</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Reporting Authority</th>
                            <th>Overall Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($history_result) > 0) {
                            while($row = mysqli_fetch_assoc($history_result)) { 
                                $statusIcon = match($row['status']) {
                                    'Approved' => 'fa-solid fa-check',
                                    'Rejected' => 'fa-solid fa-xmark',
                                    default => 'fa-regular fa-clock'
                                };

                                // Check who approved dynamically
                                $approvers = [];
                                if ($row['tl_status'] === 'Approved' && !empty($row['tl_name'])) {
                                    $approvers[] = "<span style='font-weight:700; color:#1e293b;'>" . htmlspecialchars($row['tl_name']) . "</span> <span style='font-size:10px;color:#0d9488;font-weight:700;background:#f0fdfa;padding:2px 6px;border-radius:4px; margin-left:4px;'>TL</span>";
                                }
                                if ($row['manager_status'] === 'Approved' && !empty($row['mgr_name'])) {
                                    $approvers[] = "<span style='font-weight:700; color:#1e293b;'>" . htmlspecialchars($row['mgr_name']) . "</span> <span style='font-size:10px;color:#8b5cf6;font-weight:700;background:#f5f3ff;padding:2px 6px;border-radius:4px; margin-left:4px;'>MGR</span>";
                                }
                                if (empty($approvers) && !empty($row['approved_by'])) {
                                    $approvers[] = "<span style='font-weight:700; color:#1e293b;'>" . htmlspecialchars($row['approved_by']) . "</span>";
                                }

                                $approved_by_display = !empty($approvers) ? implode("<div style='margin-top:6px;'></div>", $approvers) : '<span style="color:#94a3b8; font-style:italic;">Pending Action</span>';
                        ?>
                        <tr>
                            <td><span style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($row['leave_type']); ?></span></td>
                            <td><span style="font-weight:600; color:#475569;"><i class="fa-regular fa-calendar-days text-teal-600 mr-1"></i> <?php echo date("d M Y", strtotime($row['start_date'])) . ' <i class="fa-solid fa-arrow-right mx-1 text-slate-300 text-[10px]"></i> ' . date("d M Y", strtotime($row['end_date'])); ?></span></td>
                            <td><span class="bg-slate-100 text-slate-700 px-2 py-1 rounded font-bold text-xs"><?php echo $row['total_days']; ?> Day(s)</span></td>
                            <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($row['reason']); ?>"><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td style="line-height: 1.2;"><?php echo $approved_by_display; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i class="<?php echo $statusIcon; ?>"></i> <?php echo $row['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php 
                            } 
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding:40px; color:#64748b;'>No leave requests found. You have not applied for time off yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="leaveModal">
        <div class="modal-box">
            <form method="POST" action="">
                <div class="modal-header">
                    <h3>Request Time Off</h3>
                    <div class="close-icon" onclick="closeModal()">
                        <i class="fa-solid fa-xmark text-slate-400 hover:text-slate-700 cursor-pointer text-xl transition"></i>
                    </div>
                </div>
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Leave Category</label>
                            <select name="leave_type" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <option value="Annual">Annual Leave (Vacation)</option>
                                <option value="Medical">Medical Leave (Sick)</option>
                                <option value="Casual">Casual Leave (Personal)</option>
                                <option value="Other">Other Reason</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" id="dateFrom" class="form-control" required onchange="calculateDays()">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" id="dateTo" class="form-control" required onchange="calculateDays()">
                        </div>
                        <div class="form-group">
                            <label>Total Days Calculated</label>
                            <input type="number" name="total_days" id="noOfDays" class="form-control bg-slate-50 text-teal-700 font-bold" readonly placeholder="Auto-calculated">
                        </div>
                        <div class="form-group full-width">
                            <label>Reason / Comments</label>
                            <textarea name="reason" class="form-control custom-scroll" rows="3" required maxlength="250" placeholder="Please provide a brief reason for your request (max 250 characters)" oninput="updateCharCount(this)"></textarea>
                            <div style="text-align:right; font-size:11px; color:#64748b; margin-top:6px; font-weight: 600;">
                                <span id="charCount" class="text-teal-600">0</span> / 250 Characters
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="submit_leave" class="btn btn-primary"><i class="fa-solid fa-paper-plane mr-1"></i> Submit Request</button>
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

        // JS for Character Counter
        function updateCharCount(textarea) {
            document.getElementById('charCount').textContent = textarea.value.length;
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
                    alert("Error: End date cannot be before start date.");
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
                
                if (typeTd && statusTd && dateTd) {
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