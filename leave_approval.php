<?php
// leave_approvals.php

// 1. SESSION START & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); // Fixed path to index.php
    exit(); 
}

// FIXED DATABASE CONNECTION PATH
$db_path = __DIR__ . '/include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    require_once 'include/db_connect.php'; 
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// =========================================================================
// 2. PROCESS AJAX LEAVE ACTIONS (Approve/Reject)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id']) && isset($_POST['status'])) {
    $leave_id = intval($_POST['leave_id']);
    $new_status = $_POST['status']; 
    
    $column_to_update = "";
    $update_overall_status = false;

    if ($user_role === 'Team Lead') {
        $column_to_update = "tl_status";
        if ($new_status === 'Rejected') { $update_overall_status = true; } 
    } elseif ($user_role === 'Manager') {
        $column_to_update = "manager_status";
        if ($new_status === 'Rejected') { $update_overall_status = true; }
    } elseif ($user_role === 'HR' || $user_role === 'HR Executive') {
        $column_to_update = "hr_status";
        $update_overall_status = true;
    } else {
        echo "error";
        exit();
    }

    // Prepare the SQL Update
    if ($update_overall_status) {
        $update_query = "UPDATE leave_requests SET $column_to_update = ?, status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssi", $new_status, $new_status, $leave_id);
    } else {
        $update_query = "UPDATE leave_requests SET $column_to_update = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $new_status, $leave_id);
    }

    if ($update_stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    
    $update_stmt->close();
    $conn->close(); // EXPLICITLY CLOSE CONNECTION TO SAVE SERVER LIMITS
    exit(); 
}

// =========================================================================
// 3. FETCH DATA FOR UI DISPLAY
// =========================================================================
$query = "";
if ($user_role === 'Team Lead') {
    $query = "SELECT lr.*, u.name as emp_name, ep.designation as emp_role 
              FROM leave_requests lr 
              JOIN users u ON lr.user_id = u.id 
              LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
              WHERE lr.tl_id = ? ORDER BY lr.created_at DESC";
} elseif ($user_role === 'Manager') {
    $query = "SELECT lr.*, u.name as emp_name, ep.designation as emp_role 
              FROM leave_requests lr 
              JOIN users u ON lr.user_id = u.id 
              LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
              WHERE lr.manager_id = ? AND lr.tl_status = 'Approved' ORDER BY lr.created_at DESC";
} elseif ($user_role === 'HR' || $user_role === 'HR Executive') {
    $query = "SELECT lr.*, u.name as emp_name, ep.designation as emp_role 
              FROM leave_requests lr 
              JOIN users u ON lr.user_id = u.id 
              LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
              WHERE lr.manager_status = 'Approved' ORDER BY lr.created_at DESC";
} else {
    die("Access Denied.");
}

$stmt = $conn->prepare($query);
if ($user_role === 'Team Lead' || $user_role === 'Manager') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

$leave_requests = [];
$pending_count = 0; $approved_count = 0; $rejected_count = 0;

while ($row = $result->fetch_assoc()) {
    $display_status = 'Pending';
    if ($user_role === 'Team Lead') { $display_status = $row['tl_status']; }
    if ($user_role === 'Manager') { $display_status = $row['manager_status']; }
    if ($user_role === 'HR' || $user_role === 'HR Executive') { $display_status = $row['hr_status']; }

    if ($display_status === 'Pending') $pending_count++;
    if ($display_status === 'Approved') $approved_count++;
    if ($display_status === 'Rejected') $rejected_count++;

    $row['display_status'] = $display_status;
    $leave_requests[] = $row;
}
$stmt->close();
$conn->close(); // EXPLICITLY CLOSE CONNECTION TO SAVE SERVER LIMITS

$sidebarPath = __DIR__ . '/sidebars.php'; 
if (!file_exists($sidebarPath)) { $sidebarPath = 'sidebars.php'; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals - HRMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

    <style>
        :root { --primary: #f97316; --primary-hover: #ea580c; --bg-body: #f8f9fa; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --white: #ffffff; --success: #10b981; --danger: #ef4444; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: 95px; padding: 24px 32px; min-height: 100vh; transition: all 0.3s ease; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 15px; flex-wrap: wrap; }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #0f172a; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); position: relative; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .stat-info h4 { font-size: 13px; color: var(--text-muted); margin: 0 0 5px 0; font-weight: 500; }
        .stat-info h2 { font-size: 28px; font-weight: 700; margin: 0; color: var(--text-main); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .card-pending .stat-icon { background: #fff7ed; color: #f97316; } .card-approved .stat-icon { background: #f0fdf4; color: #16a34a; } .card-rejected .stat-icon { background: #fef2f2; color: #dc2626; } .card-total .stat-icon { background: #eff6ff; color: #2563eb; }
        .list-section { background: white; border-radius: 12px; border: 1px solid var(--border); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filters-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { flex: 2; min-width: 250px; display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; }
        .search-box input { border: none; outline: none; width: 100%; font-size: 13px; margin-left: 8px; }
        .filter-select { flex: 1; min-width: 150px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; color: var(--text-main); outline: none; cursor: pointer; }
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; font-size: 12px; color: #475569; padding: 14px 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        tr:hover { background-color: #fcfcfc; }
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #64748b; }
        .emp-info { display: flex; flex-direction: column; } .emp-name { font-weight: 600; color: #0f172a; } .emp-dept { font-size: 11px; color: #64748b; }
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .status-Pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; } .status-Approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } .status-Rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .leave-type { font-weight: 500; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: #334155; font-size: 12px; }
        .action-container { display: flex; gap: 8px; }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border); background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; color: var(--text-muted); }
        .btn-icon:hover { background: #f8fafc; color: var(--primary); } .btn-approve:hover { background: #dcfce7; color: #166534; border-color: #bbf7d0; } .btn-reject:hover { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; animation: fadeUp 0.2s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .modal-box { background: white; width: 600px; max-width: 95%; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .modal-body { padding: 24px; } .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .detail-item label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 4px; } .detail-item p { margin: 0; font-size: 14px; font-weight: 500; color: #1e293b; }
        .reason-box { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; } .reason-box p { font-size: 13px; color: #334155; line-height: 1.5; margin: 0; }
        .modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; } .btn-outline { background: white; border: 1px solid var(--border); color: #334155; } .btn-green { background: var(--success); color: white; } .btn-red { background: var(--danger); color: white; }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) { include($sidebarPath); } ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1>Leave Approvals (<?php echo htmlspecialchars($user_role); ?>)</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px;"></i>
                    <span>/</span> Leaves <span>/</span> <span style="font-weight:600; color:#0f172a;">Approvals</span>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-pending">
                <div class="stat-info">
                    <h4>Pending Requests</h4>
                    <h2><?php echo $pending_count; ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="clock"></i></div>
            </div>
            <div class="stat-card card-approved">
                <div class="stat-info">
                    <h4>Approved</h4>
                    <h2><?php echo $approved_count; ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="check-circle-2"></i></div>
            </div>
            <div class="stat-card card-rejected">
                <div class="stat-info">
                    <h4>Rejected</h4>
                    <h2><?php echo $rejected_count; ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="x-circle"></i></div>
            </div>
            <div class="stat-card card-total">
                <div class="stat-info">
                    <h4>Total Handled</h4>
                    <h2><?php echo count($leave_requests); ?></h2>
                </div>
                <div class="stat-icon"><i data-lucide="users"></i></div>
            </div>
        </div>

        <div class="list-section">
            
            <div class="filters-row">
                <div class="search-box">
                    <i data-lucide="search" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" id="searchInput" placeholder="Search employee..." onkeyup="filterTable()">
                </div>
                <select class="filter-select" id="typeFilter" onchange="filterTable()">
                    <option value="">All Leave Types</option>
                    <option value="Medical">Medical</option>
                    <option value="Casual">Casual</option>
                    <option value="Annual">Annual</option>
                </select>
                <select class="filter-select" id="statusFilter" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="table-responsive">
                <table id="approvalTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Duration</th>
                            <th>Days</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($leave_requests) > 0): ?>
                            <?php foreach($leave_requests as $leave): ?>
                                <tr>
                                    <td>
                                        <div class="emp-profile">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($leave['emp_name']); ?>&background=random" class="emp-avatar" alt="User">
                                            <div class="emp-info">
                                                <span class="emp-name"><?php echo htmlspecialchars($leave['emp_name']); ?></span>
                                                <span class="emp-dept"><?php echo htmlspecialchars($leave['emp_role']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="leave-type"><?php echo htmlspecialchars($leave['leave_type']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($leave['start_date'])) . ' - ' . date('d M Y', strtotime($leave['end_date'])); ?></td>
                                    <td><?php echo str_pad($leave['total_days'], 2, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                            $badgeClass = "status-" . $leave['display_status'];
                                            $icon = ($leave['display_status'] == 'Approved') ? 'check' : (($leave['display_status'] == 'Rejected') ? 'x' : 'clock');
                                        ?>
                                        <span class="status-badge <?php echo $badgeClass; ?>"><i data-lucide="<?php echo $icon; ?>" style="width:10px;"></i> <?php echo $leave['display_status']; ?></span>
                                    </td>
                                    <td>
                                        <div class="action-container">
                                            <?php if($leave['display_status'] === 'Pending'): ?>
                                                <button class="btn-icon btn-approve" onclick="updateLeave(<?php echo $leave['id']; ?>, 'Approved')" title="Approve"><i data-lucide="check" style="width:16px;"></i></button>
                                                <button class="btn-icon btn-reject" onclick="updateLeave(<?php echo $leave['id']; ?>, 'Rejected')" title="Reject"><i data-lucide="x" style="width:16px;"></i></button>
                                            <?php endif; ?>
                                            <button class="btn-icon" onclick="viewDetails('<?php echo addslashes($leave['emp_name']); ?>', '<?php echo $leave['leave_type']; ?>', '<?php echo date('d M Y', strtotime($leave['start_date'])) . ' - ' . date('d M Y', strtotime($leave['end_date'])); ?>', '<?php echo addslashes($leave['reason']); ?>', '<?php echo $leave['total_days']; ?>')" title="View Details"><i data-lucide="eye" style="width:16px;"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">No leave requests found for your attention.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="approvalModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Leave Request Details</h3>
                <i data-lucide="x" style="cursor:pointer; color:#94a3b8;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Employee Name</label>
                        <p id="mName">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Leave Type</label>
                        <p id="mType">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Duration</label>
                        <p id="mDate">--</p>
                    </div>
                    <div class="detail-item">
                        <label>Total Days</label>
                        <p id="mDays">--</p>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom:8px;">
                    <label>Employee Reason</label>
                </div>
                <div class="reason-box">
                    <p id="mReason">--</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Close Window</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const type = document.getElementById('typeFilter').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            
            const rows = document.querySelectorAll('#approvalTable tbody tr');

            rows.forEach(row => {
                if(row.cells.length < 2) return; 
                const name = row.querySelector('.emp-name').innerText.toLowerCase();
                const lType = row.querySelector('td:nth-child(2)').innerText.toLowerCase();
                const lStatus = row.querySelector('.status-badge').innerText.toLowerCase();

                const matchesSearch = name.includes(search);
                const matchesType = type === '' || lType.includes(type);
                const matchesStatus = status === '' || lStatus.includes(status);

                if (matchesSearch && matchesType && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        const modal = document.getElementById('approvalModal');

        function viewDetails(name, type, date, reason, days) {
            document.getElementById('mName').innerText = name;
            document.getElementById('mType').innerText = type;
            document.getElementById('mDate').innerText = date;
            document.getElementById('mReason').innerText = reason;
            document.getElementById('mDays').innerText = days + " Days";
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function updateLeave(leaveId, newStatus) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to mark this leave as " + newStatus,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: newStatus === 'Approved' ? '#10b981' : '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, ' + newStatus + ' it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Posts back to the exact same file
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `leave_id=${leaveId}&status=${newStatus}`
                    })
                    .then(response => response.text())
                    .then(data => {
                        if(data.trim() === 'success') {
                            Swal.fire('Success!', 'The leave has been ' + newStatus + '.', 'success')
                            .then(() => { location.reload(); }); 
                        } else {
                            Swal.fire('Error', 'Something went wrong processing the request.', 'error');
                        }
                    });
                }
            });
        }

        modal.addEventListener('click', (e) => {
            if(e.target === modal) closeModal();
        });
    </script>
</body>
</html>