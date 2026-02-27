<?php
// Fixes "headers already sent" error by turning on output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sidebarPath = ''; $headerPath = '';
if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php'; $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php'; $headerPath = '../header.php';
}

if (isset($conn)) {
    // 1. AUTO-CREATE TABLE
    $create_table = "CREATE TABLE IF NOT EXISTS `employee_salary` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `salary_month` varchar(20) NOT NULL,
      `basic` decimal(10,2) DEFAULT 0.00,
      `da` decimal(10,2) DEFAULT 0.00,
      `hra` decimal(10,2) DEFAULT 0.00,
      `conveyance` decimal(10,2) DEFAULT 0.00,
      `allowance` decimal(10,2) DEFAULT 0.00,
      `medical` decimal(10,2) DEFAULT 0.00,
      `others_earnings` decimal(10,2) DEFAULT 0.00,
      `tds` decimal(10,2) DEFAULT 0.00,
      `esi` decimal(10,2) DEFAULT 0.00,
      `pf` decimal(10,2) DEFAULT 0.00,
      `leave_deduction` decimal(10,2) DEFAULT 0.00,
      `professional_tax` decimal(10,2) DEFAULT 0.00,
      `labour_welfare` decimal(10,2) DEFAULT 0.00,
      `others_deductions` decimal(10,2) DEFAULT 0.00,
      `gross_salary` decimal(12,2) DEFAULT 0.00,
      `net_salary` decimal(12,2) DEFAULT 0.00,
      `credit_status` varchar(50) DEFAULT 'Pending',
      `credit_date` date DEFAULT NULL,
      `approval_status` varchar(50) DEFAULT 'Pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($create_table);

    // 2. INTERNAL API LOGIC (Crash-Proofed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
        ob_clean(); // Clears any whitespace before sending JSON
        header('Content-Type: application/json');
        $action = $_POST['ajax_action'];

        if ($action === 'save_salary') {
            $id = (int)($_POST['id'] ?? 0);
            $user_id = (int)($_POST['user_id'] ?? 0);
            $salary_month = $_POST['salary_month'] ?? '';
            $credit_status = $_POST['credit_status'] ?? 'Pending';
            
            // Format date safely for MySQL
            $credit_date = !empty($_POST['credit_date']) ? $_POST['credit_date'] : null;

            $basic = (float)($_POST['basic'] ?? 0);
            $da = (float)($_POST['da'] ?? 0);
            $hra = (float)($_POST['hra'] ?? 0);
            $conveyance = (float)($_POST['conveyance'] ?? 0);
            $allowance = (float)($_POST['allowance'] ?? 0);
            $medical = (float)($_POST['medical'] ?? 0);
            $others_earnings = (float)($_POST['others_earnings'] ?? 0);

            $tds = (float)($_POST['tds'] ?? 0);
            $esi = (float)($_POST['esi'] ?? 0);
            $pf = (float)($_POST['pf'] ?? 0);
            $leave_deduction = (float)($_POST['leave_deduction'] ?? 0);
            $professional_tax = (float)($_POST['professional_tax'] ?? 0);
            $labour_welfare = (float)($_POST['labour_welfare'] ?? 0);
            $others_deductions = (float)($_POST['others_deductions'] ?? 0);

            $gross_salary = $basic + $da + $hra + $conveyance + $allowance + $medical + $others_earnings;
            $total_deductions = $tds + $esi + $pf + $leave_deduction + $professional_tax + $labour_welfare + $others_deductions;
            $net_salary = $gross_salary - $total_deductions;

            if ($id > 0) { 
                $stmt = $conn->prepare("UPDATE employee_salary SET basic=?, da=?, hra=?, conveyance=?, allowance=?, medical=?, others_earnings=?, tds=?, esi=?, pf=?, leave_deduction=?, professional_tax=?, labour_welfare=?, others_deductions=?, gross_salary=?, net_salary=?, credit_status=?, credit_date=?, approval_status='Pending' WHERE id=?");
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'SQL Update Error: ' . $conn->error]);
                    exit;
                }
                $stmt->bind_param("ddddddddddddddddssi", $basic, $da, $hra, $conveyance, $allowance, $medical, $others_earnings, $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions, $gross_salary, $net_salary, $credit_status, $credit_date, $id);
            } else { 
                $stmt = $conn->prepare("INSERT INTO employee_salary (user_id, salary_month, basic, da, hra, conveyance, allowance, medical, others_earnings, tds, esi, pf, leave_deduction, professional_tax, labour_welfare, others_deductions, gross_salary, net_salary, credit_status, credit_date, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'SQL Insert Error: ' . $conn->error]);
                    exit;
                }
                $stmt->bind_param("isddddddddddddddddss", $user_id, $salary_month, $basic, $da, $hra, $conveyance, $allowance, $medical, $others_earnings, $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions, $gross_salary, $net_salary, $credit_status, $credit_date);
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Execution Error: ' . $stmt->error]);
            }
            $stmt->close();
            exit;
        }
        
        if ($action === 'delete_salary') {
            $id = (int)($_POST['id'] ?? 0);
            $conn->query("DELETE FROM employee_salary WHERE id = $id");
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'auto_generate') {
            $month = $_POST['month'] ?? '';
            $emps = $conn->query("SELECT id, salary FROM employee_onboarding WHERE status = 'Completed'");
            
            while($emp = $emps->fetch_assoc()) {
                $uid = $emp['id'];
                $ctc = (float)$emp['salary'];
                $check = $conn->query("SELECT id FROM employee_salary WHERE user_id = $uid AND salary_month = '$month'");
                
                if ($check->num_rows == 0) {
                    $monthlyGross = $ctc > 200000 ? ($ctc / 12) : $ctc;
                    $basic = round($monthlyGross * 0.50, 2); 
                    $da = round($basic * 0.40, 2); 
                    $hra = round($basic * 0.15, 2);
                    $allowance = round($monthlyGross - ($basic + $da + $hra), 2);
                    $gross = $basic + $da + $hra + $allowance;
                    
                    $pf = round($basic * 0.12, 2);
                    $esi = ($gross <= 21000) ? round($gross * 0.0075, 2) : 0;
                    $pt = ($gross > 15000) ? 200 : 0;
                    $deductions = $pf + $esi + $pt;
                    $net = $gross - $deductions;

                    $stmt = $conn->prepare("INSERT INTO employee_salary (user_id, salary_month, basic, da, hra, allowance, esi, pf, professional_tax, gross_salary, net_salary, credit_status, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending')");
                    if($stmt) {
                        $stmt->bind_param("isddddddddd", $uid, $month, $basic, $da, $hra, $allowance, $esi, $pf, $pt, $gross, $net);
                        $stmt->execute();
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => "Salaries Auto-Generated successfully!"]);
            exit;
        }
    }
}

// 3. FETCH AND DISPLAY DATA
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$employees_data = []; $grouped_data = [];
$tot_payroll = 0; $tot_credited = 0; $tot_pending = 0; $tot_deductions = 0;

if (isset($conn)) {
    $query = "SELECT e.id as user_id, CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as name, 
                     e.emp_id_code as emp_code, e.profile_img, e.designation, e.department, e.salary as ctc,
                     s.id as salary_id, s.salary_month, s.gross_salary, s.net_salary, s.credit_status, s.approval_status,
                     s.basic, s.da, s.hra, s.conveyance, s.allowance, s.medical, s.others_earnings,
                     s.tds, s.esi, s.pf, s.leave_deduction, s.professional_tax, s.labour_welfare, s.others_deductions
              FROM employee_onboarding e
              LEFT JOIN employee_salary s ON e.id = s.user_id AND s.salary_month = ?
              WHERE e.status = 'Completed'";
    if (!empty($status_filter)) $query .= " AND s.approval_status = ?";
    $query .= " ORDER BY e.department ASC, e.first_name ASC";

    if ($stmt = $conn->prepare($query)) {
        if (!empty($status_filter)) $stmt->bind_param("ss", $month_filter, $status_filter);
        else $stmt->bind_param("s", $month_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $dept = !empty($row['department']) ? trim($row['department']) : 'Unassigned Department';
            $row['department'] = $dept;
            $employees_data[] = $row;
            if (!isset($grouped_data[$dept])) $grouped_data[$dept] = [];
            $grouped_data[$dept][] = $row;
            
            if (!empty($row['salary_id'])) {
                $gross = (float)$row['gross_salary'];
                $net = (float)$row['net_salary'];
                $deductions = (float)($row['tds'] + $row['esi'] + $row['pf'] + $row['leave_deduction'] + $row['professional_tax'] + $row['labour_welfare'] + $row['others_deductions']);
                
                $tot_payroll += $gross;
                $tot_deductions += $deductions;
                if ($row['credit_status'] === 'Credited') $tot_credited += $net;
                if ($row['approval_status'] === 'Pending') $tot_pending += $net;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Salary Management | WorkAck HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #f97316; --success: #22c55e; --danger: #ef4444; --gray: #6b7280; --bg: #f3f4f6; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; overflow-x: hidden; }
        .main-content { margin-left: 100px; padding-top: 10px; padding-left: 25px; padding-right: 25px; padding-bottom: 30px; min-height: 100vh; box-sizing: border-box; transition: all 0.3s ease; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-left: 15px; padding-right: 15px; padding-top: 80px; } }
        .dashboard { max-width: 1400px; margin: 0 auto; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card { background: #fff; padding: 22px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
        .card h3 { margin: 0 0 10px; font-size: 13px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px;}
        .card p { margin: 0; font-size: 26px; font-weight: 700; color: #1f2937; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 18px 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; color: #fff; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 13px; }
        .btn-primary { background: #3b82f6; }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); }
        .btn-dark { background: #1f2937; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
        .btn:hover { filter: brightness(92%); transform: translateY(-1px); }
        .table-responsive { overflow-x: auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { background: #f9fafb; padding: 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray); text-transform: uppercase; border-bottom: 1px solid #edf2f7; }
        td { padding: 16px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; border-radius: 50%; background: #eee; object-fit: cover; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.Pending { background: #fee2e2; color: #dc2626; }
        .badge.Credited { background: #e0f2fe; color: #0284c7; }
        .badge.Approved { background: #dcfce7; color: #16a34a; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
        .modal-content { background: #fff; padding: 30px; width: 100%; max-width: 850px; border-radius: 16px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        .section-title { grid-column: 1 / -1; margin: 20px 0 10px; font-weight: 800; color: #111827; font-size: 16px; border-bottom: 2px solid #f3f4f6; padding-bottom: 8px; }
    </style>
</head>
<body>

<?php 
if (!empty($sidebarPath) && file_exists($sidebarPath)) require_once $sidebarPath; 
if (!empty($headerPath) && file_exists($headerPath)) require_once $headerPath; 
?>

<div class="main-content">
    <div class="dashboard">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="margin:0; color: #111827; font-weight: 800;">Salary & Payroll Management</h2>
            <div style="color: var(--gray); font-size: 14px; font-weight: 600; background: #fff; padding: 8px 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" id="current-date"></div>
        </div>
        
        <div class="summary-cards">
            <div class="card" style="border-left-color: #3b82f6;"><h3>Total Payroll (This Month)</h3><p>₹<?php echo number_format($tot_payroll, 2); ?></p></div>
            <div class="card" style="border-left-color: var(--success);"><h3>Net Credited</h3><p>₹<?php echo number_format($tot_credited, 2); ?></p></div>
            <div class="card" style="border-left-color: var(--danger);"><h3>Pending Approval</h3><p>₹<?php echo number_format($tot_pending, 2); ?></p></div>
            <div class="card" style="border-left-color: #8b5cf6;"><h3>Total Deductions</h3><p>₹<?php echo number_format($tot_deductions, 2); ?></p></div>
        </div>

        <div class="top-bar">
            <form method="GET" action="" style="display:flex; gap: 12px; align-items:center; flex-wrap: wrap; margin: 0;">
                <input type="month" name="month" id="filter-month" value="<?php echo htmlspecialchars($month_filter); ?>" onchange="this.form.submit()" style="padding:10px; border:1px solid #ddd; border-radius:8px;">
                <select name="status" onchange="this.form.submit()" style="padding:10px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">All Approval Status</option>
                    <option value="Pending" <?php if($status_filter == 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Approved" <?php if($status_filter == 'Approved') echo 'selected'; ?>>Approved</option>
                </select>
                <input type="text" id="search-emp" onkeyup="filterTable()" placeholder="Search Employee Name or ID..." style="padding:10px; border:1px solid #ddd; border-radius:8px; min-width: 250px;">
            </form>
            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-outline" onclick="exportCSV()"><i class="fa-solid fa-download"></i> Export CSV</button>
                <button class="btn btn-dark" onclick="autoGenerateAll()"><i class="fa-solid fa-bolt"></i> Auto-Generate All</button>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee Details</th>
                        <th>Month</th>
                        <th>Salary Breakdown</th>
                        <th>Credit Status</th>
                        <th>CFO Approval</th>
                        <th style="text-align: right;">Action Center</th>
                    </tr>
                </thead>
                <tbody id="salary-body">
                    <?php if (empty($grouped_data)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:50px; color:#94a3b8; font-size:16px;">No records found for the selected period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($grouped_data as $dept => $employees): ?>
                            <tr class="dept-header">
                                <td colspan="6" style="background:#f3f4f6; font-weight:800; color:#1f2937; padding:12px 20px; text-transform:uppercase; font-size:13px; letter-spacing:0.5px;">
                                    <i class="fa-solid fa-building" style="margin-right:8px; color:var(--primary);"></i> <?php echo htmlspecialchars($dept); ?>
                                </td>
                            </tr>
                            
                            <?php foreach ($employees as $row): 
                                $avatar = 'https://ui-avatars.com/api/?name='.urlencode($row['name']).'&background=0ea5e9&color=fff';
                                if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
                                    $img = $row['profile_img'];
                                    if (strpos($img, 'http') === 0) { $avatar = $img; } 
                                    elseif (strpos($img, 'uploads/') === 0 || strpos($img, 'assets/') === 0) { $avatar = '../' . $img; } 
                                    else { $avatar = '../assets/profiles/' . $img; }
                                }
                                
                                $hasSalary = !empty($row['salary_id']);
                                $gross = $hasSalary ? (float)$row['gross_salary'] : 0;
                                $net = $hasSalary ? (float)$row['net_salary'] : 0;
                                $isApproved = $hasSalary && $row['approval_status'] === 'Approved';
                                $ctc = (float)($row['ctc'] ?? 0);
                            ?>
                            <tr class="emp-row">
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="user-avatar" alt="Avatar" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=User&background=0ea5e9&color=fff';">
                                        <div>
                                            <div class="emp-name" style="font-weight:700; color:#1e293b; font-size:15px;"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <div style="font-size:12px; color:var(--gray); margin-top:3px;">
                                                <span class="emp-code" style="color:var(--primary); font-weight:600;"><?php echo htmlspecialchars($row['emp_code'] ?: 'N/A'); ?></span> | <?php echo htmlspecialchars($row['designation'] ?: 'N/A'); ?>
                                            </div>
                                            <div style="font-size:11px; margin-top:4px; font-weight:700; color:#059669;">
                                                Base CTC: ₹<?php echo number_format($ctc, 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight:600; color:#475569;"><?php echo htmlspecialchars($row['salary_month'] ?? $month_filter); ?></td>
                                <td>
                                    <?php if($hasSalary): ?>
                                        <div style="font-weight:800; color:#111; font-size:16px;">₹<?php echo number_format($net, 2); ?></div>
                                        <div style="font-size:11px; color:var(--gray); margin-top:4px;">Gross: ₹<?php echo number_format($gross, 2); ?></div>
                                    <?php else: ?>
                                        <div style="font-weight:700; color:#9ca3af; font-size:13px; font-style:italic;">Not Generated</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($hasSalary): ?>
                                        <span class="badge <?php echo htmlspecialchars($row['credit_status']); ?>"><?php echo htmlspecialchars($row['credit_status']); ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f1f5f9; color:#94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($hasSalary): ?>
                                        <span class="badge <?php echo htmlspecialchars($row['approval_status'] ?: 'Pending'); ?>"><?php echo htmlspecialchars($row['approval_status'] ?: 'Pending'); ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f1f5f9; color:#94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                                        <?php if($hasSalary): ?>
                                            <?php if($isApproved): ?>
                                                <button class="btn btn-dark" style="padding:6px 12px; font-size:12px;" onclick="window.location.href='api/generate_payslip.php?id=<?php echo $row['salary_id']; ?>'"><i class="fa-solid fa-file-pdf"></i> Payslip</button>
                                            <?php else: ?>
                                                <button class="btn btn-success" style="padding:6px 12px; font-size:12px;" onclick="askApproval(<?php echo $row['salary_id']; ?>)"><i class="fa-solid fa-paper-plane"></i> Ask Approval</button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline" style="padding:6px 12px;" onclick="editSalary(<?php echo $row['salary_id']; ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                            <button class="btn btn-danger" style="padding:6px 12px;" onclick="deleteSalary(<?php echo $row['salary_id']; ?>)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        <?php else: ?>
                                            <button class="btn btn-primary" style="padding:6px 14px;" onclick="generateManual(<?php echo $row['user_id']; ?>)"><i class="fa-solid fa-plus"></i> Generate</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="salaryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title" style="margin:0; font-weight: 800; color: #111827;">Process Salary Record</h3>
            <button type="button" onclick="closeModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
        </div>
        <form id="salaryForm" onsubmit="submitSalary(event)">
            <input type="hidden" name="ajax_action" value="save_salary">
            <input type="hidden" name="id" id="salary_db_id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="user_id" id="employeeSelect" required style="background: #f3f4f6; pointer-events: none; border-color: #e5e7eb;">
                    </select>
                </div>
                <div class="form-group">
                    <label>Salary Month</label>
                    <input type="month" name="salary_month" id="form_salary_month" required readonly style="background: #f3f4f6; border-color: #e5e7eb;">
                </div>
                
                <div class="form-group">
                    <label>Credit Status</label>
                    <select name="credit_status" id="creditStatus" onchange="toggleDate(this.value)">
                        <option value="Pending">Pending</option>
                        <option value="Credited">Credited</option>
                    </select>
                </div>
                <div class="form-group" id="creditDateDiv" style="display: none;">
                    <label>Date of Credit</label>
                    <input type="date" name="credit_date" id="creditDate">
                </div>

                <div class="section-title" style="color: var(--primary);">Earnings (In ₹)</div>
                <div class="form-group"><label>Basic Pay</label><input type="number" name="basic" id="form_basic" value="0" step="0.01"></div>
                <div class="form-group"><label>DA (40%)</label><input type="number" name="da" id="form_da" value="0" step="0.01"></div>
                <div class="form-group"><label>HRA (15%)</label><input type="number" name="hra" id="form_hra" value="0" step="0.01"></div>
                <div class="form-group"><label>Conveyance</label><input type="number" name="conveyance" id="form_conveyance" value="0" step="0.01"></div>
                <div class="form-group"><label>Special Allowance</label><input type="number" name="allowance" id="form_allowance" value="0" step="0.01"></div>
                <div class="form-group"><label>Medical Allowance</label><input type="number" name="medical" id="form_medical" value="0" step="0.01"></div>
                <div class="form-group"><label>Others Earnings</label><input type="number" name="others_earnings" id="form_others_earnings" value="0" step="0.01"></div>

                <div class="section-title" style="color: var(--danger);">Deductions (In ₹)</div>
                <div class="form-group"><label>TDS (Tax)</label><input type="number" name="tds" id="form_tds" value="0" step="0.01"></div>
                <div class="form-group"><label>ESI (0.75%)</label><input type="number" name="esi" id="form_esi" value="0" step="0.01"></div>
                <div class="form-group"><label>Provident Fund (PF - 12%)</label><input type="number" name="pf" id="form_pf" value="0" step="0.01"></div>
                <div class="form-group"><label>Leave / LOP</label><input type="number" name="leave_deduction" id="form_leave_deduction" value="0" step="0.01"></div>
                <div class="form-group"><label>Professional Tax</label><input type="number" name="professional_tax" id="form_professional_tax" value="0" step="0.01"></div>
                <div class="form-group"><label>Labour Welfare</label><input type="number" name="labour_welfare" id="form_labour_welfare" value="0" step="0.01"></div>
                <div class="form-group"><label>Other Deductions</label><input type="number" name="others_deductions" id="form_others_deductions" value="0" step="0.01"></div>
                
                <div class="form-group" style="grid-column: 1 / -1; margin-top:20px; border-top:1px solid #f3f4f6; padding-top: 20px; display:flex; flex-direction:row; justify-content:flex-end; gap:12px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-btn">Save & Send for CFO Approval</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const serverData = <?php echo json_encode($employees_data); ?>;
    
    document.getElementById('current-date').innerText = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    function toggleDate(status) {
        const dateDiv = document.getElementById('creditDateDiv');
        dateDiv.style.display = (status === 'Credited') ? 'flex' : 'none';
        if (status === 'Credited' && !document.getElementById('creditDate').value) {
            document.getElementById('creditDate').value = new Date().toISOString().slice(0, 10);
        }
    }

    function filterTable() {
        const term = document.getElementById('search-emp').value.toLowerCase();
        const rows = document.querySelectorAll('#salary-body .emp-row');
        rows.forEach(row => {
            const name = row.querySelector('.emp-name').innerText.toLowerCase();
            const code = row.querySelector('.emp-code').innerText.toLowerCase();
            row.style.display = (name.includes(term) || code.includes(term)) ? '' : 'none';
        });

        document.querySelectorAll('#salary-body .dept-header').forEach(header => {
            let next = header.nextElementSibling;
            let hasVisibleEmp = false;
            while (next && next.classList.contains('emp-row')) {
                if (next.style.display !== 'none') { hasVisibleEmp = true; break; }
                next = next.nextElementSibling;
            }
            header.style.display = hasVisibleEmp ? '' : 'none';
        });
    }

    async function autoGenerateAll() {
        const month = document.getElementById('filter-month').value;
        if(!confirm(`Auto-generate base payroll records for all missing employees for ${month}?`)) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'auto_generate');
        formData.append('month', month);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const rawText = await res.text();
            try {
                const result = JSON.parse(rawText);
                alert(result.message || "Auto-generation complete!");
                if(result.success) window.location.reload();
            } catch(e) {
                alert("Database Error! Message from server: " + rawText.substring(0,100));
            }
        } catch(err) { alert("Network Error. Check connection."); }
    }

    function askApproval(id) {
        if(confirm("Notify CFO for final approval of this record?")) {
            alert("Approval request has been sent to the CFO Dashboard.");
        }
    }

    function generateManual(userId) {
        const item = serverData.find(d => d.user_id == userId);
        if(!item) return;

        document.getElementById('modal-title').innerText = `Generate Salary: ${item.name}`;
        document.getElementById('salary_db_id').value = ''; 
        
        const empSelect = document.getElementById('employeeSelect');
        empSelect.innerHTML = `<option value="${item.user_id}" selected>${item.name} (${item.emp_code})</option>`;
        
        document.getElementById('form_salary_month').value = document.getElementById('filter-month').value;
        document.getElementById('creditStatus').value = 'Pending';
        toggleDate('Pending');
        
        let rawCTC = parseFloat(item.ctc) || 0;
        let monthlyGross = rawCTC > 200000 ? (rawCTC / 12) : rawCTC; 

        let basicPay = monthlyGross * 0.50;
        let daPay = basicPay * 0.40;
        let hraPay = basicPay * 0.15;
        let allowancePay = monthlyGross - (basicPay + daPay + hraPay);
        if(allowancePay < 0) allowancePay = 0;

        let pfDeduct = basicPay * 0.12;
        let esiDeduct = monthlyGross <= 21000 ? (monthlyGross * 0.0075) : 0;
        let ptDeduct = monthlyGross > 15000 ? 200 : 0;

        document.getElementById('form_basic').value = basicPay.toFixed(2);
        document.getElementById('form_da').value = daPay.toFixed(2);
        document.getElementById('form_hra').value = hraPay.toFixed(2);
        document.getElementById('form_allowance').value = allowancePay.toFixed(2);
        
        document.getElementById('form_conveyance').value = 0;
        document.getElementById('form_medical').value = 0;
        document.getElementById('form_others_earnings').value = 0;

        document.getElementById('form_pf').value = pfDeduct.toFixed(2);
        document.getElementById('form_esi').value = esiDeduct.toFixed(2);
        document.getElementById('form_professional_tax').value = ptDeduct.toFixed(2);

        document.getElementById('form_tds').value = 0;
        document.getElementById('form_leave_deduction').value = 0;
        document.getElementById('form_labour_welfare').value = 0;
        document.getElementById('form_others_deductions').value = 0;
        
        document.getElementById('submit-btn').innerText = 'Save & Send for CFO Approval';
        openModal();
    }

    function editSalary(salaryId) {
        const item = serverData.find(d => d.salary_id == salaryId);
        if(!item) return;

        document.getElementById('modal-title').innerText = `Update Salary: ${item.name}`;
        document.getElementById('salary_db_id').value = item.salary_id;
        
        const empSelect = document.getElementById('employeeSelect');
        empSelect.innerHTML = `<option value="${item.user_id}" selected>${item.name} (${item.emp_code})</option>`;

        document.getElementById('form_salary_month').value = item.salary_month;
        document.getElementById('creditStatus').value = item.credit_status || 'Pending';
        
        if(item.credit_date && item.credit_date !== 'null' && item.credit_date !== '0000-00-00') {
            document.getElementById('creditDate').value = item.credit_date.substring(0, 10);
        } else {
            document.getElementById('creditDate').value = '';
        }
        toggleDate(item.credit_status);
        
        const fields = ['basic', 'da', 'hra', 'conveyance', 'allowance', 'medical', 'others_earnings', 'tds', 'esi', 'pf', 'leave_deduction', 'professional_tax', 'labour_welfare', 'others_deductions'];
        fields.forEach(f => {
            const el = document.getElementById('form_' + f);
            if(el) el.value = item[f] ? parseFloat(item[f]).toFixed(2) : 0;
        });
        
        document.getElementById('submit-btn').innerText = 'Update & Re-send for Approval';
        openModal();
    }

    async function submitSalary(e) {
        e.preventDefault();
        const empSelect = document.getElementById('employeeSelect');
        empSelect.style.pointerEvents = 'auto'; 
        
        const formData = new FormData(e.target);
        
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const rawText = await res.text();
            
            try {
                const result = JSON.parse(rawText);
                if(result.success) {
                    closeModal();
                    window.location.reload(); 
                } else {
                    alert("Database Error: " + result.message);
                    empSelect.style.pointerEvents = 'none'; 
                }
            } catch(parseErr) {
                console.error("PHP Error Details:", rawText);
                alert("Database Error: A column might be missing. See console for details.\n\n" + rawText.substring(0,100));
                empSelect.style.pointerEvents = 'none'; 
            }
            
        } catch(err) {
            alert("Network error. Please try again.");
            empSelect.style.pointerEvents = 'none'; 
        }
    }

    async function deleteSalary(id) {
        if(confirm("Are you sure you want to permanently delete this payroll record?")) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_salary');
            formData.append('id', id);
            
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const result = await res.json();
                if(result.success) window.location.reload();
                else alert("Error: " + result.message);
            } catch(err) { alert("Error deleting record."); }
        }
    }

    function exportCSV() {
        if(!serverData.length) return alert("No data to export");
        let csv = "Employee ID,Name,Department,Month,Gross Salary,Net Salary,Credit Status,Approval Status\n";
        let hasData = false;
        
        serverData.forEach(r => {
            if(r.salary_id) { 
                hasData = true;
                const gross = parseFloat(r.gross_salary) || 0;
                const net = parseFloat(r.net_salary) || 0;
                csv += `"${r.emp_code}","${r.name}","${r.department}","${r.salary_month}","${gross}","${net}","${r.credit_status}","${r.approval_status}"\n`;
            }
        });
        
        if(!hasData) return alert("No generated salaries to export for this month.");
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = window.URL.createObjectURL(blob);
        a.download = `Payroll_Report_${document.getElementById('filter-month').value}.csv`;
        a.click();
    }

    function openModal() { document.getElementById('salaryModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('salaryModal').style.display = 'none'; document.getElementById('salaryForm').reset(); }
</script>
</body>
</html>