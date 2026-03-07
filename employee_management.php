<?php
// employee_management.php

// 1. SESSION & SECURITY GUARD
ob_start(); // Prevent headers from bleeding into JSON
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hard Login Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Generate CSRF Token for secure AJAX actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$sidebarPath = __DIR__ . '/sidebars.php';
if (!file_exists($sidebarPath)) {
    $sidebarPath = __DIR__ . '/../sidebars.php';
}

require_once 'include/db_connect.php';

// =========================================================================
// 2. AJAX ENDPOINTS: TERMINATE & REACTIVATE (SECURED)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Check CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_reporting(0); ob_clean(); header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh the page.']);
        exit();
    }

    // --- ACTION: TERMINATE EMPLOYEE ---
    if ($_POST['action'] === 'terminate') {
        error_reporting(0); ini_set('display_errors', 0); ob_clean(); header('Content-Type: application/json');
        
        $emp_id = intval($_POST['emp_id']);
        
        try {
            $user_id = 0;
            $stmt_find = $conn->prepare("SELECT user_id FROM employee_profiles WHERE id = ?");
            if ($stmt_find) {
                $stmt_find->bind_param("i", $emp_id);
                $stmt_find->execute();
                $res = $stmt_find->get_result();
                if ($res && $res->num_rows > 0) { $user_id = $res->fetch_assoc()['user_id']; }
                $stmt_find->close();
            }

            if ($user_id > 0) {
                $stmt_prof = $conn->prepare("UPDATE employee_profiles SET status = 'Inactive' WHERE id = ?");
                if (!$stmt_prof) throw new Exception("Profile Update Error: " . $conn->error);
                $stmt_prof->bind_param("i", $emp_id);
                $profile_updated = $stmt_prof->execute();
                $stmt_prof->close();

                $stmt_user = $conn->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?");
                if ($stmt_user) {
                    $stmt_user->bind_param("i", $user_id);
                    $stmt_user->execute();
                    $stmt_user->close();
                }

                if ($profile_updated) echo json_encode(['success' => true]);
                else echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Employee record not found.']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // --- ACTION: REACTIVATE EMPLOYEE ---
    if ($_POST['action'] === 'reactivate') {
        error_reporting(0); ini_set('display_errors', 0); ob_clean(); header('Content-Type: application/json');
        
        $emp_id = intval($_POST['emp_id']);
        
        try {
            $user_id = 0;
            $stmt_find = $conn->prepare("SELECT user_id FROM employee_profiles WHERE id = ?");
            if ($stmt_find) {
                $stmt_find->bind_param("i", $emp_id);
                $stmt_find->execute();
                $res = $stmt_find->get_result();
                if ($res && $res->num_rows > 0) { $user_id = $res->fetch_assoc()['user_id']; }
                $stmt_find->close();
            }

            if ($user_id > 0) {
                $stmt_prof = $conn->prepare("UPDATE employee_profiles SET status = 'Active' WHERE id = ?");
                if (!$stmt_prof) throw new Exception("Profile Update Error: " . $conn->error);
                $stmt_prof->bind_param("i", $emp_id);
                $profile_updated = $stmt_prof->execute();
                $stmt_prof->close();

                $stmt_user = $conn->prepare("UPDATE users SET status = 'Active' WHERE id = ?");
                if ($stmt_user) {
                    $stmt_user->bind_param("i", $user_id);
                    $stmt_user->execute();
                    $stmt_user->close();
                }

                if ($profile_updated) echo json_encode(['success' => true]);
                else echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Employee record not found.']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// =========================================================================
// 3. EDIT EMPLOYEE DETAILS (SECURED)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $id = intval($_POST['emp_id_pk']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $designation = trim($_POST['designation']);
    $department = trim($_POST['department']);

    $stmt = $conn->prepare("UPDATE employee_profiles SET full_name=?, phone=?, designation=?, department=? WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("ssssi", $full_name, $phone, $designation, $department, $id);
        if ($stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Employee details updated successfully.'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Failed to save changes.'];
        }
        $stmt->close();
    }
    
    header("Location: employee_management.php");
    exit();
}

// =========================================================================
// 4. FETCH DATA (OPTIMIZED) & GROUP BY DEPARTMENT
// =========================================================================
$departments = [];
$designations = [];
$activeCount = 0;
$inactiveCount = 0;
$newHiresCount = 0;
$currentMonth = date('m');
$totalEmployees = 0;

// Optimized Query: Only fetch required columns
$query = "SELECT id, user_id, full_name, email, phone, designation, department, status, joining_date, emp_id_code, profile_img 
          FROM employee_profiles 
          ORDER BY department ASC, full_name ASC";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['emp_id_display'] = $row['emp_id_code'] ?? 'N/A';
        $row['display_name'] = $row['full_name'] ?? 'Unknown';
        
        // Status Normalization
        $status_raw = $row['status'] ?? 'Active';
        if (strcasecmp($status_raw, 'Terminated') === 0 || strcasecmp($status_raw, 'Inactive') === 0) {
            $row['status'] = 'Inactive';
        } else {
            $row['status'] = 'Active';
        }

        // Failsafe Join Date parsing
        if (!empty($row['joining_date']) && $row['joining_date'] !== '0000-00-00') {
            $row['join_date_display'] = date('M d, Y', strtotime($row['joining_date']));
            if (date('m', strtotime($row['joining_date'])) == $currentMonth) { 
                $newHiresCount++; 
            }
        } else {
            $row['join_date_display'] = 'N/A';
        }

        $dept = !empty($row['department']) ? trim($row['department']) : 'Unassigned Dept';

        // Tally Stats
        if ($row['status'] === 'Active') { $activeCount++; } else { $inactiveCount++; }

        // Group by Department
        if (!isset($departments[$dept])) { $departments[$dept] = []; }
        $departments[$dept][] = $row;
        $totalEmployees++;
        
        // Build Designation List
        $desig = trim($row['designation'] ?? '');
        if (!empty($desig) && !in_array($desig, $designations)) {
            $designations[] = $desig;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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

        /* PERFECTLY ALIGNED TOOLBAR & SEARCH */
        .filter-toolbar { 
            display: flex; 
            gap: 16px; 
            margin-bottom: 24px; 
            background: white; 
            padding: 16px; 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            align-items: center; 
            flex-wrap: wrap; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .search-box { 
            flex: 1 1 auto; 
            position: relative; 
            min-width: 250px; 
        }
        .search-box i, .search-box svg { 
    position: absolute; 
    left: 14px; 
    top: 50%; 
    transform: translateY(-50%); 
    width: 18px; 
    height: 18px;
    color: #94a3b8; 
    pointer-events: none; /* Prevents the icon from blocking clicks */
}
        .search-box input { 
            width: 100%; 
            padding: 10px 15px 10px 40px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            outline: none; 
            font-family: 'Inter', sans-serif; 
            font-size: 14px; 
            transition: 0.2s;
            height: 42px; /* Standardized Height */
            box-sizing: border-box;
        }
        .search-box input:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); 
        }
        .filter-select { 
            padding: 0 15px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            outline: none; 
            min-width: 180px; 
            font-family: 'Inter', sans-serif; 
            font-size: 14px; 
            background: #fff; 
            cursor: pointer; 
            transition: 0.2s;
            height: 42px; /* Standardized Height */
            box-sizing: border-box;
            color: #334155;
            font-weight: 500;
        }
        .filter-select:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); 
        }

        .table-responsive { overflow-x: auto; background: white; border-radius: 16px; border: 1px solid var(--border); padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; padding: 14px 16px; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
        td { padding: 14px 16px; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .dept-header { background: #f8fafc; cursor: pointer; border-top: 2px solid var(--border); transition: background 0.2s; }
        .dept-header:hover { background: #f1f5f9; }
        .dept-header td { font-weight: 700; font-size: 0.95rem; color: var(--primary); }
        .dept-icon { transition: transform 0.3s ease; }
        .dept-icon.open { transform: rotate(90deg); }
        
        .emp-row { display: table-row; }
        .emp-row.filtered-out { display: none !important; }
        .emp-row.collapsed { display: none; }
        .dept-header.filtered-out { display: none !important; }

        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .btn-terminate { color: #ef4444; background: #fee2e2; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; text-decoration: none; transition: 0.2s;}
        .btn-terminate:hover { background: #fca5a5; color: #991b1b; }
        
        .btn-reactivate { color: #059669; background: #d1fae5; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s;}
        .btn-reactivate:hover { background: #a7f3d0; color: #047857; }
        
        .btn-edit { color: #3b82f6; background: #dbeafe; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s;}
        .btn-edit:hover { background: #bfdbfe; color: #1d4ed8; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(3px);}
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 400px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; font-family: 'Inter'; transition: 0.2s;}
        .form-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1);}
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600;}
        .btn-cancel { background: white; border: 1px solid var(--border); padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; color: #475569;}
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

        <div class="filter-toolbar">
            <div class="search-box">
                <i data-lucide="search"></i>
                <input type="text" id="searchInput" onkeyup="filterEmployees()" placeholder="Search by name, ID, email, or phone...">
            </div>
            <select id="statusFilter" class="filter-select" onchange="filterEmployees()">
                <option value="All">All Statuses</option>
                <option value="Active">Active Employees</option>
                <option value="Inactive">Terminated / Inactive</option>
            </select>
            <select id="designationFilter" class="filter-select" onchange="filterEmployees()">
                <option value="All">All Designations</option>
                <?php foreach($designations as $desig): ?>
                    <option value="<?= htmlspecialchars($desig) ?>"><?= htmlspecialchars($desig) ?></option>
                <?php endforeach; ?>
            </select>
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
                                    <span style="background: var(--primary); color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; margin-left: 10px;" id="count_<?= $safeDeptId ?>">
                                        <?= count($emps) ?> Employees
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <?php foreach($emps as $emp): 
                            $imgSrc = !empty($emp['profile_img']) ? $emp['profile_img'] : "https://ui-avatars.com/api/?name=".urlencode($emp['display_name'])."&background=random";
                            
                            if (!filter_var($imgSrc, FILTER_VALIDATE_URL) && strpos($imgSrc, 'ui-avatars') === false) {
                                $imgSrc = str_replace('../', '', $imgSrc);
                                if (strpos($imgSrc, '/') === false) {
                                    $imgSrc = 'assets/profiles/' . $imgSrc;
                                }
                                if (!file_exists($imgSrc)) {
                                    $imgSrc = "https://ui-avatars.com/api/?name=".urlencode($emp['display_name'])."&background=random";
                                }
                            }
                        ?>
                        <tr class="emp-row <?= $safeDeptId ?>" data-status="<?= htmlspecialchars($emp['status']) ?>" data-designation="<?= htmlspecialchars($emp['designation']) ?>">
                            <td>
                                <div class="emp-profile" style="padding-left: 30px;">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="emp-img" alt="Profile">
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-weight: 600; font-size: 13px; color: #1e293b;"><?= htmlspecialchars($emp['display_name']) ?></span>
                                        <span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($emp['email'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:600; font-size: 12px; color: #475569;"><?= htmlspecialchars($emp['emp_id_display']) ?></td>
                            <td><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-size:12px; color: #334155; font-weight: 500; border: 1px solid #e2e8f0;"><?= htmlspecialchars($emp['designation']) ?></span></td>
                            <td style="color: #475569; font-size: 13px;"><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                            <td style="font-size: 13px; color: #475569;"><?= $emp['join_date_display'] ?></td>
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
                                    <button class="btn-reactivate" onclick="reactivateEmployee(<?= intval($emp['id']) ?>, '<?= htmlspecialchars(addslashes($emp['display_name'])) ?>')">
                                        <i data-lucide="user-check" style="width:14px;"></i> Reactivate
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
            <h2 style="margin-top:0; font-size:1.2rem; display: flex; justify-content: space-between; color: var(--primary);">
                Edit Employee
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
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['toast'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?= $_SESSION['toast']['type'] ?>',
                title: '<?= $_SESSION['toast']['msg'] ?>',
                showConfirmButton: false,
                timer: 3000
            });
        });
    </script>
    <?php unset($_SESSION['toast']); endif; ?>

    <script>
        lucide.createIcons();
        const csrfToken = "<?= $_SESSION['csrf_token'] ?>";

        // ---------------------------------------------------------
        // REAL-TIME SEARCH AND FILTER ENGINE
        // ---------------------------------------------------------
        function filterEmployees() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const desigFilter = document.getElementById('designationFilter').value;

            const deptHeaders = document.querySelectorAll('.dept-header');
            
            deptHeaders.forEach(header => {
                let nextRow = header.nextElementSibling;
                let visibleCount = 0;

                while (nextRow && nextRow.classList.contains('emp-row')) {
                    const textData = nextRow.innerText.toLowerCase();
                    const empStatus = nextRow.getAttribute('data-status');
                    const empDesig = nextRow.getAttribute('data-designation');

                    const matchesSearch = textData.includes(searchTerm);
                    const matchesStatus = statusFilter === 'All' || empStatus === statusFilter;
                    const matchesDesig = desigFilter === 'All' || empDesig === desigFilter;

                    if (matchesSearch && matchesStatus && matchesDesig) {
                        nextRow.classList.remove('filtered-out');
                        visibleCount++;
                    } else {
                        nextRow.classList.add('filtered-out');
                    }
                    
                    nextRow = nextRow.nextElementSibling;
                }

                // Dynamic Department Badge Count Update
                if (visibleCount > 0) {
                    header.classList.remove('filtered-out');
                    header.querySelector('span').innerText = visibleCount + (visibleCount === 1 ? ' Employee' : ' Employees');
                } else {
                    header.classList.add('filtered-out');
                }
            });
        }

        // Tree Structure Toggle
        function toggleDept(deptClass) {
            const rows = document.querySelectorAll('.' + deptClass);
            const icon = document.getElementById('icon_' + deptClass);
            
            let isCollapsed = false;
            rows.forEach(row => {
                if (row.classList.contains('collapsed')) {
                    row.classList.remove('collapsed');
                } else {
                    row.classList.add('collapsed');
                    isCollapsed = true;
                }
            });

            if (isCollapsed) { icon.classList.remove('open'); } 
            else { icon.classList.add('open'); }
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

        // Secure Termination Logic
        function terminateEmployee(empId, empName) {
            Swal.fire({
                title: 'Terminate Employee?',
                html: `Are you sure you want to terminate <b>${empName}</b>?<br><br><span style="font-size:13px; color:#ef4444;">This will immediately revoke their access to log into the system.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Terminate & Revoke Access'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'terminate');
                    fd.append('emp_id', empId);
                    fd.append('csrf_token', csrfToken); // CSRF added

                    fetch(window.location.href, { method: 'POST', body: fd })
                    .then(async res => {
                        const text = await res.text();
                        try { return JSON.parse(text); } catch (e) { throw new Error(text); }
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Employee Terminated', text: 'System access has been fully revoked.', showConfirmButton: false, timer: 2000 })
                            .then(() => window.location.reload());
                        } else {
                            Swal.fire('Database Error', data.message || 'Failed to terminate employee.', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire({ icon: 'error', title: 'Server Error', html: `An error occurred during termination.<br><br><span style="color:red; font-size:12px; word-break:break-all;">${err.message.substring(0, 150)}</span>` });
                    });
                }
            });
        }

        // Secure Reactivation Logic
        function reactivateEmployee(empId, empName) {
            Swal.fire({
                title: 'Reactivate Employee?',
                html: `Are you sure you want to restore <b>${empName}</b>?<br><br><span style="font-size:13px; color:#059669;">This will instantly reactivate their profile and allow them to log into the system again.</span>`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Reactivate Access'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'reactivate');
                    fd.append('emp_id', empId);
                    fd.append('csrf_token', csrfToken); // CSRF added

                    fetch(window.location.href, { method: 'POST', body: fd })
                    .then(async res => {
                        const text = await res.text();
                        try { return JSON.parse(text); } catch (e) { throw new Error(text); }
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Account Restored', text: 'System access has been successfully reactivated.', showConfirmButton: false, timer: 2000 })
                            .then(() => window.location.reload());
                        } else {
                            Swal.fire('Database Error', data.message || 'Failed to reactivate employee.', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire({ icon: 'error', title: 'Server Error', html: `An error occurred during reactivation.<br><br><span style="color:red; font-size:12px; word-break:break-all;">${err.message.substring(0, 150)}</span>` });
                    });
                }
            });
        }
    </script>
</body>
</html>