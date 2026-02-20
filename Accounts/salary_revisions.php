<?php 
// salary_revisions.php (Accountant / CFO Role)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php';

$msg = '';

// --- 1. HANDLE APPROVE / REJECT ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $req_id = intval($_POST['req_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        // Fetch new salary details
        $stmt = $conn->prepare("SELECT emp_id_code, new_salary FROM salary_hike_requests WHERE id = ?");
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $emp_code = $row['emp_id_code'];
            $new_salary = $row['new_salary'];

            // Update main employee_onboarding table (Collation Fix included)
            $upd_sal = $conn->prepare("UPDATE employee_onboarding SET salary = ? WHERE emp_id_code COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci");
            $upd_sal->bind_param("ds", $new_salary, $emp_code);
            
            if($upd_sal->execute()) {
                // Mark request as Approved
                $conn->query("UPDATE salary_hike_requests SET status = 'Approved' WHERE id = $req_id");
                $msg = "<div class='alert-box' style='background:#dcfce7; border-left-color:#16a34a; color:#166534;'>
                            <i class='ph-fill ph-check-circle' style='font-size:20px;'></i> 
                            <div><strong>Approved!</strong> Salary for $emp_code has been officially updated in the database.</div>
                        </div>";
            }
        }
    } elseif ($action === 'reject') {
        $conn->query("UPDATE salary_hike_requests SET status = 'Rejected' WHERE id = $req_id");
        $msg = "<div class='alert-box' style='background:#fee2e2; border-left-color:#dc2626; color:#991b1b;'>
                    <i class='ph-fill ph-x-circle' style='font-size:20px;'></i> 
                    <div><strong>Rejected!</strong> The salary hike request was declined.</div>
                </div>";
    }
}

// --- 2. FETCH ALL REQUESTS SENT BY HR ---
// (Collation Fix included to avoid Illegal mix of collations error)
$sql = "SELECT r.*, ep.full_name, ep.department 
        FROM salary_hike_requests r 
        LEFT JOIN employee_profiles ep ON r.emp_id_code COLLATE utf8mb4_unicode_ci = ep.emp_id_code COLLATE utf8mb4_unicode_ci 
        ORDER BY FIELD(r.status, 'Pending', 'Approved', 'Rejected'), r.requested_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Revisions & Approvals</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- GLOBAL STYLES (MATCHING ACCOUNTS THEME) --- */
        :root {
            --theme-color: #1b5a5a;
            --theme-light: #e0f2f1;
            --bg-body: #f3f4f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.03);
            --primary-sidebar-width: 95px;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0; padding: 0; color: var(--text-main);
        }

        .main-content {
            margin-left: var(--primary-sidebar-width);
            padding: 30px;
            width: calc(100% - var(--primary-sidebar-width));
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header-area h2 { margin: 0; color: var(--theme-color); font-weight: 700; font-size: 24px; }
        .header-area p { color: var(--text-muted); font-size: 13px; margin: 5px 0 0; }

        /* --- CARDS --- */
        .card {
            background: white; padding: 25px; border-radius: 12px;
            box-shadow: var(--card-shadow); border: 1px solid var(--border-color); margin-bottom: 30px;
        }
        .card-header { border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { margin: 0; font-size: 16px; color: var(--theme-color); display: flex; align-items: center; gap: 8px; }

        /* --- TABLES --- */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: #f8fafc; padding: 14px 15px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        td { padding: 16px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--text-main); vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }
        
        /* --- STATUS BADGES --- */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge.pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .badge.approved { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .badge.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

        /* --- ACTION BUTTONS --- */
        .btn-primary {
            background: var(--theme-color); color: white; padding: 10px 20px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;
            text-decoration: none; font-size: 13px;
        }
        .btn-primary:hover { background: #134e4e; transform: translateY(-1px); }

        .btn-approve { background: #10b981; color: white; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-approve:hover { background: #059669; }
        
        .btn-reject { background: white; color: #ef4444; border: 1px solid #fecaca; padding: 8px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-reject:hover { background: #fee2e2; }
        
        /* Alerts */
        .alert-box {
            background: #e0f2fe; border-left: 4px solid #0284c7; padding: 15px;
            margin-bottom: 25px; color: #075985; font-size: 13px; display: flex; align-items: center; gap: 10px; border-radius: 8px; font-weight: 500;
        }
    </style>
</head>
<body>

<?php 
$sidebarPath = __DIR__ . '/sidebars.php';
if (!file_exists($sidebarPath)) { $sidebarPath = __DIR__ . '/../sidebars.php'; }
if (file_exists($sidebarPath)) { include($sidebarPath); }
if (file_exists('../header.php')) { include('../header.php'); }
?>

<main class="main-content">
    
    <div class="header-area">
        <div>
            <h2>Salary Approvals Dashboard</h2>
            <p>Review and authorize performance-based salary increments requested by HR.</p>
        </div>
        <button class="btn-primary" style="background: white; color: var(--text-main); border: 1px solid var(--border-color);" onclick="window.location.href='payslip.php'">
            <i class="ph-bold ph-receipt"></i> Go to Payroll Generation
        </button>
    </div>

    <?= $msg ?>

    <div class="alert-box">
        <i class="ph-fill ph-info" style="font-size: 20px;"></i>
        <span><strong>Accounts Workflow:</strong> HR Evaluates Performance ➝ HR Sends Request ➝ <strong>CFO/Accounts Approves here</strong> ➝ Employee Salary is Officially Updated.</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="ph-fill ph-clock-counter-clockwise"></i> Pending & Processed Revisions</h3>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Requested Date</th>
                        <th>Emp ID</th>
                        <th>Employee Name</th>
                        <th>Current Salary</th>
                        <th>Hike %</th>
                        <th>Proposed Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr style="<?= $row['status'] == 'Pending' ? 'background: #f8fafc;' : '' ?>">
                                <td style="color: #64748b;"><?= date('d M Y, h:i A', strtotime($row['requested_date'])) ?></td>
                                <td><strong><?= htmlspecialchars($row['emp_id_code']) ?></strong></td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($row['full_name'] ?? 'Unknown Employee') ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($row['department'] ?? 'N/A') ?></div>
                                </td>
                                <td>₹<?= number_format($row['old_salary']) ?></td>
                                <td><strong style="color: #10b981;">+<?= floatval($row['hike_percent']) ?>%</strong></td>
                                <td style="font-size: 15px; font-weight: 800; color: var(--theme-color);">₹<?= number_format($row['new_salary']) ?></td>
                                <td>
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <span class="badge pending"><i class="ph-fill ph-hourglass"></i> Pending CFO</span>
                                    <?php elseif($row['status'] == 'Approved'): ?>
                                        <span class="badge approved"><i class="ph-fill ph-check-circle"></i> Approved</span>
                                    <?php else: ?>
                                        <span class="badge rejected"><i class="ph-fill ph-x-circle"></i> Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <div style="display: flex; gap: 8px;">
                                            <form method="POST" style="margin:0;">
                                                <input type="hidden" name="req_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-approve" onclick="return confirm('Approve this salary hike? The employee\'s official salary will be updated immediately.')">
                                                    <i class="ph-bold ph-check"></i> Approve
                                                </button>
                                            </form>

                                            <form method="POST" style="margin:0;">
                                                <input type="hidden" name="req_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-reject" onclick="return confirm('Are you sure you want to reject this salary hike?')">
                                                    <i class="ph-bold ph-x"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:#94a3b8; font-weight: 600;"><i class="ph-bold ph-lock-key"></i> Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="ph-fill ph-tray" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                No salary hike requests from HR at the moment.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</body>
</html>