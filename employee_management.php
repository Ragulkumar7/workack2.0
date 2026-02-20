<?php
// employee_management.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sidebarPath = __DIR__ . '/sidebars.php';
if (!file_exists($sidebarPath)) {
    $sidebarPath = __DIR__ . '/../sidebars.php';
}

require_once 'include/db_connect.php';

// --- EDIT EMPLOYEE (Basic Details) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $id = intval($_POST['emp_id_pk']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);

    $updateQuery = "UPDATE employee_profiles SET 
                    full_name='$full_name', phone='$phone', 
                    designation='$designation', department='$department' 
                    WHERE id=$id";
    
    if (mysqli_query($conn, $updateQuery)) {
        header("Location: employee_management.php?updated=1");
        exit();
    } else {
        die("Database Error: " . mysqli_error($conn));
    }
}

// --- TERMINATE EMPLOYEE ---
if (isset($_GET['terminate_id'])) {
    $id = intval($_GET['terminate_id']);
    // Soft delete by setting status to Inactive
    $terminateQuery = "UPDATE employee_profiles SET status = 'Inactive' WHERE id = $id";
    if (mysqli_query($conn, $terminateQuery)) {
        header("Location: employee_management.php?terminated=1");
        exit();
    }
}

// 5. FETCH DATA & GROUP BY DEPARTMENT
$departments = [];
$designations = [];
$activeCount = 0;
$inactiveCount = 0;
$newHiresCount = 0;
$currentMonth = date('m');
$totalEmployees = 0;

$query = "SELECT * FROM employee_profiles ORDER BY department ASC, full_name ASC";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['emp_id_display'] = $row['emp_id_code'] ?? 'N/A';
        $row['display_name'] = $row['full_name'] ?? 'Unknown';
        $row['status'] = $row['status'] ?? 'Active';
        $row['join_date'] = $row['joining_date'] ?? '0000-00-00';
        $dept = !empty($row['department']) ? $row['department'] : 'Unassigned';

        if ($row['status'] === 'Active') { $activeCount++; } else { $inactiveCount++; }
        
        if (!empty($row['join_date']) && $row['join_date'] !== '0000-00-00') {
            if (date('m', strtotime($row['join_date'])) == $currentMonth) { $newHiresCount++; }
        }

        // Group by Department
        if (!isset($departments[$dept])) {
            $departments[$dept] = [];
        }
        $departments[$dept][] = $row;
        $totalEmployees++;
        
        if (!empty($row['designation']) && !in_array($row['designation'], $designations)) {
            $designations[] = $row['designation'];
        }
    }
}
sort($designations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - HRMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Keeping your existing CSS variables */
        :root { --primary: #144d4d; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --bg-body: #f1f5f9; --white: #ffffff; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; color: var(--text-main); }
        .main-content { margin-left: 95px; padding: 24px; min-height: 100vh; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header-title h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .breadcrumb { display: flex; align-items: center; font-size: 0.875rem; color: var(--text-muted); gap: 8px; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .stat-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; }
        .stat-info h3 { font-size: 24px; font-weight: 700; margin: 0; }
        .stat-info span { font-size: 13px; color: var(--text-muted); }
        
        .card-teal .stat-icon-box { background: var(--primary); }
        .card-green .stat-icon-box { background: #10b981; }
        .card-red .stat-icon-box { background: #ef4444; }
        .card-blue .stat-icon-box { background: #3b82f6; }

        .table-responsive { overflow-x: auto; background: white; border-radius: 16px; border: 1px solid var(--border); padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; padding: 14px 16px; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
        td { padding: 14px 16px; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        /* Tree Structure Styles */
        .dept-header { background: #f8fafc; cursor: pointer; border-top: 2px solid var(--border); transition: background 0.2s; }
        .dept-header:hover { background: #f1f5f9; }
        .dept-header td { font-weight: 700; font-size: 0.95rem; color: var(--primary); }
        .dept-icon { transition: transform 0.3s ease; }
        .dept-icon.open { transform: rotate(90deg); }
        .emp-row { display: table-row; }
        .emp-row.hidden { display: none; }

        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        /* Terminate Button */
        .btn-terminate { color: #ef4444; background: #fee2e2; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .btn-terminate:hover { background: #fca5a5; color: #991b1b; }
        
        .btn-edit { color: #3b82f6; background: #dbeafe; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: var(--text-muted); }
        .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
        .btn-cancel { background: white; border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <?php if (file_exists($sidebarPath)) { include($sidebarPath); } ?>

    <div class="main-content" id="mainContent">
        <?php if (file_exists('header.php')) include 'header.php'; ?>

        <div class="page-header">
            <div class="header-title">
                <h1>Employee Directory</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px; height:14px;"></i>
                    <span>/</span> <span>Employees</span> <span>/</span> <span style="font-weight:600; color: var(--primary);">Directory</span>
                </div>
            </div>
            </div>

        <div class="stats-grid">
            <div class="stat-card card-teal">
                <div class="stat-info"><span>Total Workforce</span><h3><?= $totalEmployees ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="users"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Active</span><h3><?= $activeCount ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-check"></i></div>
            </div>
            <div class="stat-card card-red">
                <div class="stat-info"><span>Terminated / Inactive</span><h3><?= $inactiveCount ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-x"></i></div>
            </div>
            <div class="stat-card card-blue">
                <div class="stat-info"><span>New Hires (This Month)</span><h3><?= $newHiresCount ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-plus"></i></div>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 250px;">Employee</th>
                        <th>EMP ID</th>
                        <th>Designation</th>
                        <th>Phone</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="employeeTableBody">
                    <?php 
                    $deptIndex = 0;
                    foreach($departments as $deptName => $emps): 
                        $safeDeptId = "dept_" . $deptIndex;
                    ?>
                        <tr class="dept-header" onclick="toggleDept('<?= $safeDeptId ?>')">
                            <td colspan="7">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="chevron-right" class="dept-icon open" id="icon_<?= $safeDeptId ?>"></i>
                                    <i data-lucide="folder" style="width: 18px; color: #94a3b8;"></i>
                                    <?= htmlspecialchars($deptName) ?> 
                                    <span style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px;">
                                        <?= count($emps) ?> Employees
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <?php foreach($emps as $emp): 
                            $imgSrc = !empty($emp['profile_img']) ? $emp['profile_img'] : "https://ui-avatars.com/api/?name=".urlencode($emp['display_name'])."&background=random";
                            // Ensure image path is correct
                            if (!filter_var($imgSrc, FILTER_VALIDATE_URL) && strpos($imgSrc, 'ui-avatars') === false) {
                                $imgSrc = '../' . $imgSrc;
                            }
                        ?>
                        <tr class="emp-row <?= $safeDeptId ?>">
                            <td>
                                <div class="emp-profile" style="padding-left: 30px;">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="emp-img" alt="Profile">
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($emp['display_name']) ?></span>
                                        <span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($emp['email'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:600; font-size: 12px;"><?= htmlspecialchars($emp['emp_id_display']) ?></td>
                            <td><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-size:12px;"><?= htmlspecialchars($emp['designation']) ?></span></td>
                            <td><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                            <td style="font-size: 13px;"><?= date('M d, Y', strtotime($emp['join_date'])) ?></td>
                            <td>
                                <span class="status-badge <?= $emp['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>">
                                    <?= htmlspecialchars($emp['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px; justify-content: flex-end;">
                                    <button class="btn-edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, "UTF-8") ?>)'>
                                        <i data-lucide="edit" style="width:14px;"></i> Edit
                                    </button>
                                    
                                    <?php if($emp['status'] == 'Active'): ?>
                                    <button class="btn-terminate" onclick="terminateEmployee(<?= intval($emp['id']) ?>, '<?= htmlspecialchars(addslashes($emp['display_name'])) ?>')">
                                        <i data-lucide="user-x" style="width:14px;"></i> Terminate
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-terminate" style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed;" disabled>
                                        Terminated
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php 
                        $deptIndex++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="editEmployeeModal">
        <div class="modal-content">
            <h2 style="margin-top:0; font-size:1.2rem; display: flex; justify-content: space-between;">
                Edit Details
                <i data-lucide="x" style="cursor:pointer; width: 20px; color: #94a3b8;" onclick="closeEditModal()"></i>
            </h2>
            <form method="POST">
                <input type="hidden" name="edit_employee" value="1">
                <input type="hidden" name="emp_id_pk" id="edit_emp_id_pk">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" required>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" id="edit_department" required>
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="designation" id="edit_designation" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Tree Structure Toggle Logic
        function toggleDept(deptClass) {
            const rows = document.querySelectorAll('.' + deptClass);
            const icon = document.getElementById('icon_' + deptClass);
            
            let isHidden = false;
            rows.forEach(row => {
                if (row.classList.contains('hidden')) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                    isHidden = true;
                }
            });

            if (isHidden) {
                icon.classList.remove('open');
            } else {
                icon.classList.add('open');
            }
        }

        // Edit Modal Logic
        function openEditModal(emp) {
            document.getElementById('edit_emp_id_pk').value = emp.id;
            document.getElementById('edit_full_name').value = emp.full_name;
            document.getElementById('edit_phone').value = emp.phone;
            document.getElementById('edit_department').value = emp.department;
            document.getElementById('edit_designation').value = emp.designation;
            document.getElementById('editEmployeeModal').style.display = 'flex';
        }
        function closeEditModal() { 
            document.getElementById('editEmployeeModal').style.display = 'none'; 
        }

        // Terminate Logic
        function terminateEmployee(id, name) {
            if (confirm(`CRITICAL ACTION:\nAre you sure you want to TERMINATE ${name}?\n\nThis will revoke their active status.`)) {
                window.location.href = 'employee_management.php?terminate_id=' + id;
            }
        }
    </script>
</body>
</html>