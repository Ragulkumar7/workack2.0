<?php
// 1. SESSION START & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- FIXED PATHS FOR YOUR ENVIRONMENT ---
$db_connect_path = '../include/db_connect.php';
$sidebar_path    = '../sidebars.php';
$header_path     = '../header.php';

// Include DB
if (file_exists($db_connect_path)) {
    include_once($db_connect_path);
} else {
    // Fallback to absolute path
    $db_connect_path = 'C:/xampp/htdocs/workack2.0/include/db_connect.php';
    if (file_exists($db_connect_path)) {
        include_once($db_connect_path);
    } else {
        die("Error: db_connect.php not found. Please check your folder structure.");
    }
}

// 3. CHECK LOGIN
if (!isset($_SESSION['id']) && !isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_wfh'])) {
    // 1. Capture the manually entered name
    $emp_name_manual = mysqli_real_escape_string($conn, $_POST['employee_name']);
    $shift = mysqli_real_escape_string($conn, $_POST['shift']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    // 2. Update Insert Query to include employee_name
    // Note: Ensure you ran the SQL ALTER command to add 'employee_name' column
    $sql = "INSERT INTO wfh_requests (user_id, employee_name, start_date, end_date, shift, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $current_user_id, $emp_name_manual, $start_date, $end_date, $shift, $reason);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
    }
}

// --- FETCH STATS ---
if ($conn) {
    $res_pending = $conn->query("SELECT COUNT(*) as total FROM wfh_requests WHERE user_id = $current_user_id AND status = 'Pending'");
    $pending_count = ($res_pending) ? $res_pending->fetch_assoc()['total'] : 0;

    $res_used = $conn->query("SELECT SUM(DATEDIFF(end_date, start_date) + 1) as total FROM wfh_requests WHERE user_id = $current_user_id AND status = 'Approved' AND MONTH(start_date) = MONTH(CURRENT_DATE())");
    $days_used = ($res_used) ? ($res_used->fetch_assoc()['total'] ?? 0) : 0;

    // --- FETCH HISTORY ---
    $history = [];
    $res_history = $conn->query("SELECT * FROM wfh_requests WHERE user_id = $current_user_id ORDER BY applied_date DESC");
    if ($res_history) {
        while($row = $res_history->fetch_assoc()) {
            $history[] = $row;
        }
    }

    // --- FETCH EMPLOYEE PROFILE (For Designation Only) ---
    $stmt_profile = $conn->prepare("SELECT designation FROM employee_profiles WHERE user_id = ?");
    $stmt_profile->bind_param("i", $current_user_id);
    $stmt_profile->execute();
    $res_profile = $stmt_profile->get_result();
    $profile = $res_profile->fetch_assoc();
} else {
    die("Database connection failed. Please try again later.");
}

$user_role = (!empty($profile['designation'])) ? $profile['designation'] : "Staff";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Work From Home - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary: #ea580c; 
            --primary-hover: #c2410c;
            --bg-body: #f8f9fa;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        .main-content {
            margin-left: 95px;
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #111827; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; font-size: 14px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* --- STATS CARDS --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px;
            position: relative; border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
        .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 8px; color: #111827; }
        .stat-icon { position: absolute; right: 20px; top: 20px; color: var(--primary); opacity: 0.2; }

        /* --- LIST SECTION --- */
        .content-card {
            background: white; border: 1px solid var(--border);
            border-radius: 12px; padding: 20px;
        }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 16px; font-weight: 700; color: #111827; }

        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-item {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 6px; padding: 8px 12px; font-size: 13px; flex: 1; min-width: 150px;
        }
        .filter-item select, .filter-item input { border: none; outline: none; width: 100%; margin-left: 8px; }

        /* Table */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; font-size: 12px; color: #4b5563; font-weight: 600; padding: 14px 16px; border-bottom: 1px solid var(--border); text-transform: uppercase; }
        td { font-size: 13px; padding: 16px; border-bottom: 1px solid #f3f4f6; }
        
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-Approved { background: #dcfce7; color: #166534; }
        .status-Pending { background: #dbeafe; color: #1e40af; }
        .status-Rejected { background: #fee2e2; color: #991b1b; }

        /* --- MODAL --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 550px; border-radius: 8px; box-shadow: 0 20px 25px rgba(0,0,0,0.1); }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-control { width: 95%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .filters-row { flex-direction: column; }
        }
    </style>
</head>
<body>

    <?php 
    if (file_exists($sidebar_path)) { include($sidebar_path); } 
    if (file_exists($header_path)) { include($header_path); } 
    ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1>My WFH Requests</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span>
                    <span>Attendance</span>
                    <span>/</span>
                    <span style="color:#111827; font-weight:600;">WFH Application</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal()">
                    <i data-lucide="send" style="width:16px;"></i> Apply WFH
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Monthly Limit</div>
                <div class="stat-value">04 Days</div>
                <i data-lucide="calendar" class="stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-title">Used This Month</div>
                <div class="stat-value"><?= sprintf("%02d", $days_used) ?> Days</div>
                <i data-lucide="check-circle" class="stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-title">Pending Requests</div>
                <div class="stat-value"><?= sprintf("%02d", $pending_count) ?></div>
                <i data-lucide="clock" class="stat-icon"></i>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <span class="card-title">My WFH History</span>
            </div>
            
            <div class="filters-row">
                <div class="filter-item">
                    <i data-lucide="search" style="width:16px; color:#9ca3af;"></i>
                    <input type="text" id="personalSearch" placeholder="Search my requests..." onkeyup="filterTable()">
                </div>
                <div class="filter-item">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="myWfhTable">
                    <thead>
                        <tr>
                            <th>Applied Date</th>
                            <th>WFH Dates</th>
                            <th>Shift</th>
                            <th>Reason</th>
                            <th>Reviewer</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $row): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($row['applied_date'])) ?></td>
                            <td><?= date('d M Y', strtotime($row['start_date'])) ?> to <?= date('d M Y', strtotime($row['end_date'])) ?></td>
                            <td><?= $row['shift'] ?></td>
                            <td><?= htmlspecialchars($row['reason']) ?></td>
                            <td><?= htmlspecialchars($row['reviewer_name']) ?></td>
                            <td><span class="status-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($history)): ?>
                            <tr><td colspan="6" style="text-align:center; color:#9ca3af;">No WFH requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="requestModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>New WFH Request</h3>
                <i data-lucide="x" style="cursor:pointer;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                <form id="wfhForm" method="POST">
                    <input type="hidden" name="submit_wfh" value="1">
                    
                    <div class="form-group">
                        <label>Employee Name</label>
                        <input type="text" name="employee_name" class="form-control" placeholder="Enter your name" required>
                    </div>

                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user_role) ?>" readonly style="background:#f9fafb;">
                    </div>

                    <div class="form-group">
                        <label>Shift <span style="color:red;">*</span></label>
                        <select name="shift" class="form-control" required>
                            <option value="">Select Shift</option>
                            <option value="Regular">Regular</option>
                            <option value="Night">Night</option>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>Start Date <span style="color:red;">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>End Date <span style="color:red;">*</span></label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason <span style="color:red;">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Explain the reason for WFH request..." required maxlength="250" oninput="updateCharCount(this)"></textarea>
                        <div style="text-align:right; font-size:12px; color:#6b7280; margin-top:4px;">
                            <span id="charCount">0</span>/250 characters
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openModal() { document.getElementById('requestModal').classList.add('active'); }
        function closeModal() { document.getElementById('requestModal').classList.remove('active'); }

        // Live Character Count
        function updateCharCount(textarea) {
            const count = textarea.value.length;
            document.getElementById('charCount').innerText = count;
        }

        function filterTable() {
            let input = document.getElementById("personalSearch").value.toUpperCase();
            let status = document.getElementById("statusFilter").value.toUpperCase();
            let tr = document.getElementById("myWfhTable").getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let reason = tr[i].cells[3].textContent.toUpperCase();
                let statText = tr[i].cells[5].textContent.toUpperCase();
                
                let matchesSearch = reason.indexOf(input) > -1;
                let matchesStatus = status === "" || statText.indexOf(status) > -1;

                tr[i].style.display = (matchesSearch && matchesStatus) ? "" : "none";
            }
        }
    </script>
</body>
</html>