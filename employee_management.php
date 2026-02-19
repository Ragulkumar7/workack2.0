<?php
// employee_management.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. ROBUST SIDEBAR INCLUDE
$sidebarPath = __DIR__ . '/sidebars.php';
if (!file_exists($sidebarPath)) {
    $sidebarPath = __DIR__ . '/../sidebars.php';
}

// 3. DATABASE CONNECTION
require_once 'include/db_connect.php';

// 4. HANDLE FORM SUBMISSIONS

// --- ADD EMPLOYEE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $joining_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $emp_code = "EMP-" . rand(1000, 9999);

    $insertQuery = "INSERT INTO employee_profiles (full_name, email, phone, designation, department, joining_date, status, emp_id_code) 
                    VALUES ('$full_name', '$email', '$phone', '$designation', '$department', '$joining_date', '$status', '$emp_code')";
    
    if (mysqli_query($conn, $insertQuery)) {
        header("Location: employee_management.php?success=1");
        exit();
    } else {
        die("Database Error: " . mysqli_error($conn));
    }
}

// --- EDIT EMPLOYEE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $id = intval($_POST['emp_id_pk']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $joining_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $updateQuery = "UPDATE employee_profiles SET 
                    full_name='$full_name', email='$email', phone='$phone', 
                    designation='$designation', department='$department', 
                    joining_date='$joining_date', status='$status' 
                    WHERE id=$id";
    
    if (mysqli_query($conn, $updateQuery)) {
        header("Location: employee_management.php?updated=1");
        exit();
    } else {
        die("Database Error: " . mysqli_error($conn));
    }
}

// --- DELETE EMPLOYEE ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $deleteQuery = "DELETE FROM employee_profiles WHERE id = $id";
    if (mysqli_query($conn, $deleteQuery)) {
        header("Location: employee_management.php?deleted=1");
        exit();
    }
}

// 5. FETCH DATA & CALCULATE DYNAMIC STATS
$employees = [];
$designations = [];
$activeCount = 0;
$inactiveCount = 0;
$newHiresCount = 0;
$currentMonth = date('m');

$query = "SELECT * FROM employee_profiles ORDER BY id DESC";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['emp_id_display'] = $row['emp_id_code'] ?? ($row['emp_id'] ?? 'EMP-' . $row['id']);
        $row['display_name'] = $row['full_name'] ?? 'Unknown';
        $row['display_dept'] = $row['department'] ?? 'N/A';
        $row['status'] = $row['status'] ?? 'Active';
        $row['join_date'] = $row['joining_date'] ?? ($row['join_date'] ?? '0000-00-00');

        if ($row['status'] === 'Active') {
            $activeCount++;
        } else {
            $inactiveCount++;
        }
        
        if (!empty($row['join_date']) && $row['join_date'] !== '0000-00-00') {
            if (date('m', strtotime($row['join_date'])) == $currentMonth) {
                $newHiresCount++;
            }
        }

        $employees[] = $row;
        
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        :root {
            --primary: #144d4d; 
            --primary-hover: #115e59; 
            --primary-light: #ccfbf1;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg-body: #f1f5f9;
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
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px;
            min-height: 100vh;
        }
        .main-content.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--text-main); letter-spacing: -0.025em; }
        .breadcrumb { display: flex; align-items: center; font-size: 0.875rem; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; font-size: 0.875rem; font-weight: 600;
            border-radius: 10px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 6px -1px rgba(20, 77, 77, 0.1); }
        .btn-primary:hover { background-color: var(--primary-hover); }
        
        .view-toggle { display: flex; gap: 6px; background: #f1f5f9; padding: 4px; border-radius: 10px; }
        .view-btn { padding: 8px; border-radius: 8px; cursor: pointer; border: none; background: transparent; color: var(--text-muted); transition: 0.2s; }
        .view-btn.active { background: white; color: var(--primary); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 24px;
        }
        .stat-card {
            background: white; border-radius: 16px; padding: 20px;
            border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex; align-items: center; justify-content: space-between;
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        
        .stat-icon-box {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;
        }
        .stat-info h3 { font-size: 24px; font-weight: 700; margin: 0; }
        .stat-info span { font-size: 13px; color: var(--text-muted); }
        
        .card-teal .stat-icon-box { background: var(--primary); }
        .card-green .stat-icon-box { background: #10b981; }
        .card-red .stat-icon-box { background: #ef4444; }
        .card-blue .stat-icon-box { background: #3b82f6; }

        .filters-container {
            background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .filters-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 15px; }
        .filter-group {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 10px; padding: 8px 14px; background: white; 
            flex: 1; position: relative; transition: 0.2s;
        }
        .filter-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); }
        .filter-group select, .filter-group input {
            border: none; outline: none; width: 100%; font-size: 0.875rem; background: transparent; color: var(--text-main); margin-left: 8px; cursor: pointer;
        }
        .search-box { flex: 2; min-width: 250px; }

        .table-responsive { overflow-x: auto; background: white; border-radius: 16px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        thead { background: #f8fafc; border-bottom: 1px solid var(--border); }
        th { text-align: left; padding: 14px 16px; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 14px 16px; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:hover td { background: #f8fafc; }
        
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; }
        .emp-details { display: flex; flex-direction: column; }
        .emp-name { font-weight: 600; color: var(--text-main); }
        .emp-role { font-size: 11px; color: var(--text-muted); }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .grid-view-container { display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .grid-view-container.active { display: grid; }
        
        .emp-card {
            background: white; border: 1px solid var(--border); border-radius: 16px;
            padding: 24px; text-align: center; position: relative; transition: transform 0.2s, box-shadow 0.2s;
        }
        .emp-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.1); }
        .card-menu { position: absolute; top: 15px; right: 15px; cursor: pointer; color: #94a3b8; }
        .card-img { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; object-fit: cover; border: 3px solid #f1f5f9; }
        
        .export-dropdown {
            position: absolute; top: 100%; right: 0; background: white; border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 10;
            width: 180px; display: none; flex-direction: column; margin-top: 5px; padding: 8px;
        }
        .export-dropdown.show { display: flex; }
        .export-item { padding: 10px 12px; font-size: 0.875rem; color: var(--text-main); cursor: pointer; display: flex; align-items: center; gap: 10px; border-radius: 8px; }
        .export-item:hover { background: #f1f5f9; }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center;
            z-index: 1000; backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: span 2; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: var(--text-muted); }
        .form-group input, .form-group select {
            width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; box-sizing: border-box;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) { include($sidebarPath); } ?>

    <div class="main-content" id="mainContent">
        
        <?php if (file_exists('header.php')) include 'header.php'; ?>

        <div class="page-header">
            <div>
                <h1>Employees List</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px; height:14px;"></i>
                    <span>/</span>
                    <span>Employees</span>
                    <span>/</span>
                    <span style="font-weight:600; color: var(--primary);">Employees List</span>
                </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <div class="view-toggle">
                    <div class="view-btn active" onclick="switchView('list')" id="btnList"><i data-lucide="list" style="width:18px;"></i></div>
                    <div class="view-btn" onclick="switchView('grid')" id="btnGrid"><i data-lucide="layout-grid" style="width:18px;"></i></div>
                </div>
                <div style="position:relative;">
                    <button class="btn" onclick="toggleExport()"><i data-lucide="download" style="width:16px;"></i> Export <i data-lucide="chevron-down" style="width:14px;"></i></button>
                    <div class="export-dropdown" id="exportMenu">
                        <div class="export-item"><i data-lucide="file-text" style="width:14px;"></i> Export as PDF</div>
                        <div class="export-item"><i data-lucide="sheet" style="width:14px;"></i> Export as Excel</div>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()"><i data-lucide="plus-circle" style="width:16px;"></i> Add Employee</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-teal">
                <div class="stat-info"><span>Total Employees</span><h3><?= count($employees) ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="users"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Active</span><h3><?= $activeCount ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-check"></i></div>
            </div>
            <div class="stat-card card-red">
                <div class="stat-info"><span>Inactive</span><h3><?= $inactiveCount ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-x"></i></div>
            </div>
            <div class="stat-card card-blue">
                <div class="stat-info"><span>New Hires (Month)</span><h3><?= $newHiresCount ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-plus"></i></div>
            </div>
        </div>

        <div class="filters-container">
            <h3 style="font-size:16px; font-weight:600; margin:0; color: var(--text-main);">Filter Employees</h3>
            <div class="filters-row">
                <div class="filter-group">
                    <i data-lucide="calendar" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" id="dateRangeFilter" placeholder="Select Date Range..." readonly>
                </div>
                <div class="filter-group">
                    <select id="designationFilter" onchange="filterEmployees()">
                        <option value="">All Designations</option>
                        <?php foreach($designations as $desig): ?>
                            <option value="<?= htmlspecialchars($desig) ?>"><?= htmlspecialchars($desig) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="statusFilter" onchange="filterEmployees()">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-group search-box">
                    <input type="text" id="searchInput" placeholder="Search employees..." onkeyup="filterEmployees()">
                </div>
            </div>
        </div>

        <div id="listView" class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox"></th>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Designation</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $emp): ?>
                    <tr class="employee-row" 
                        data-designation="<?= htmlspecialchars($emp['designation']) ?>" 
                        data-status="<?= htmlspecialchars($emp['status']) ?>"
                        data-name="<?= htmlspecialchars(strtolower($emp['display_name'])) ?>">
                        <td><input type="checkbox"></td>
                        <td style="font-weight:600; color: var(--text-main);"><?= htmlspecialchars($emp['emp_id_display']) ?></td>
                        <td>
                            <div class="emp-profile">
                                <div class="emp-img" style="display:flex; align-items:center; justify-content:center; background-color:#e2e8f0; color:#94a3b8;">
                                    <i data-lucide="user" style="width:20px; height:20px;"></i>
                                </div>
                                <div class="emp-details">
                                    <span class="emp-name"><?= htmlspecialchars($emp['display_name']) ?></span>
                                    <span class="emp-role"><?= htmlspecialchars($emp['display_dept']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($emp['email'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                        <td><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-size:12px;"><?= htmlspecialchars($emp['designation']) ?></span></td>
                        <td class="join-date-text"><?= htmlspecialchars($emp['join_date']) ?></td>
                        <td><span class="status-badge <?= $emp['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($emp['status']) ?></span></td>
                        <td>
                            <div style="display:flex; gap:10px;">
                                <i data-lucide="edit" style="width:18px; cursor:pointer; color:#64748b;" onclick='openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, "UTF-8") ?>)'></i>
                                <i data-lucide="trash-2" style="width:18px; cursor:pointer; color:#ef4444;" onclick="deleteEmployee(<?= intval($emp['id']) ?>)"></i>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="gridView" class="grid-view-container">
            <?php foreach($employees as $emp): ?>
            <div class="emp-card" 
                 data-designation="<?= htmlspecialchars($emp['designation']) ?>" 
                 data-status="<?= htmlspecialchars($emp['status']) ?>"
                 data-name="<?= htmlspecialchars(strtolower($emp['display_name'])) ?>">
                <div class="card-menu"><i data-lucide="more-vertical" style="width:18px;" onclick='openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, "UTF-8") ?>)'></i></div>
                <div class="card-img" style="display:flex; align-items:center; justify-content:center; background-color:#e2e8f0; color:#94a3b8;">
                    <i data-lucide="user" style="width:40px; height:40px;"></i>
                </div>
                <h4 style="margin:0 0 5px; font-weight:600;"><?= htmlspecialchars($emp['display_name']) ?></h4>
                <p style="margin:0 0 15px; font-size:13px; color:var(--text-muted);"><?= htmlspecialchars($emp['designation']) ?></p>
                <div style="display:flex; justify-content:center; gap:10px; margin-bottom:15px;">
                    <span style="background:#f1f5f9; padding:4px 10px; border-radius:20px; font-size:11px;"><?= htmlspecialchars($emp['display_dept']) ?></span>
                </div>
                <div style="border-top:1px solid #f1f5f9; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="text-align:left;">
                        <span style="display:block; font-size:11px; color:#94a3b8;">Joined</span>
                        <span class="join-date-text" style="font-size:12px; font-weight:600;"><?= htmlspecialchars($emp['join_date']) ?></span>
                    </div>
                    <span class="status-badge <?= $emp['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($emp['status']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal-overlay" id="addEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin:0; font-size:1.25rem;">Add New Employee</h2>
                <i data-lucide="x" style="cursor:pointer;" onclick="closeAddModal()"></i>
            </div>
            <form method="POST">
                <input type="hidden" name="add_employee" value="1">
                <div class="modal-form-grid">
                    <div class="form-group full">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">AC
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" required>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" required>
                    </div>
                    <div class="form-group">
                        <label>Joining Date</label>
                        <input type="date" name="join_date" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin:0; font-size:1.25rem;">Edit Employee</h2>
                <i data-lucide="x" style="cursor:pointer;" onclick="closeEditModal()"></i>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_employee" value="1">
                <input type="hidden" name="emp_id_pk" id="edit_emp_id_pk">
                <div class="modal-form-grid">
                    <div class="form-group full">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="edit_full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="edit_email" required>
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
                    <div class="form-group">
                        <label>Joining Date</label>
                        <input type="date" name="join_date" id="edit_join_date" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        lucide.createIcons();

        function openAddModal() { document.getElementById('addEmployeeModal').style.display = 'flex'; }
        function closeAddModal() { document.getElementById('addEmployeeModal').style.display = 'none'; }

        // EDIT LOGIC
        function openEditModal(emp) {
            document.getElementById('edit_emp_id_pk').value = emp.id;
            document.getElementById('edit_full_name').value = emp.full_name;
            document.getElementById('edit_email').value = emp.email;
            document.getElementById('edit_phone').value = emp.phone;
            document.getElementById('edit_department').value = emp.department;
            document.getElementById('edit_designation').value = emp.designation;
            document.getElementById('edit_join_date').value = emp.join_date;
            document.getElementById('edit_status').value = emp.status;
            
            document.getElementById('editEmployeeModal').style.display = 'flex';
        }
        function closeEditModal() { document.getElementById('editEmployeeModal').style.display = 'none'; }

        // DELETE LOGIC
        function deleteEmployee(id) {
            if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                window.location.href = 'employee_management.php?delete_id=' + id;
            }
        }

        function filterEmployees() {
            const desigVal = document.getElementById('designationFilter').value;
            const statusVal = document.getElementById('statusFilter').value;
            const searchVal = document.getElementById('searchInput').value.toLowerCase();
            const datePicker = document.querySelector("#dateRangeFilter")._flatpickr;
            const selectedDates = datePicker ? datePicker.selectedDates : [];
            
            let start = selectedDates[0] ? new Date(selectedDates[0].setHours(0,0,0,0)) : null;
            let end = selectedDates[1] ? new Date(selectedDates[1].setHours(23,59,59,999)) : null;

            document.querySelectorAll('.employee-row, .emp-card').forEach(item => {
                const itemDesig = item.getAttribute('data-designation');
                const itemStatus = item.getAttribute('data-status');
                const itemName = item.getAttribute('data-name');
                const itemDateStr = item.querySelector('.join-date-text').innerText;
                const itemDate = new Date(itemDateStr);

                let show = true;
                if (desigVal && itemDesig !== desigVal) show = false;
                if (statusVal && itemStatus !== statusVal) show = false;
                if (searchVal && !itemName.includes(searchVal)) show = false;
                if (start && end && (itemDate < start || itemDate > end)) show = false;

                item.style.display = show ? '' : 'none';
            });
        }

        flatpickr("#dateRangeFilter", { mode: "range", dateFormat: "Y-m-d", onClose: filterEmployees });

        function switchView(view) {
            const isList = view === 'list';
            document.getElementById('btnList').classList.toggle('active', isList);
            document.getElementById('btnGrid').classList.toggle('active', !isList);
            document.getElementById('listView').style.display = isList ? 'block' : 'none';
            const grid = document.getElementById('gridView');
            if (isList) {
                grid.classList.remove('active');
                grid.style.display = 'none';
            } else {
                grid.classList.add('active');
                grid.style.display = 'grid';
            }
        }

        function toggleExport() { document.getElementById('exportMenu').classList.toggle('show'); }

        window.onclick = function(e) {
            if (e.target.id == 'addEmployeeModal') closeAddModal();
            if (e.target.id == 'editEmployeeModal') closeEditModal();
            if (!e.target.closest('.btn')) {
                const menu = document.getElementById('exportMenu');
                if (menu) menu.classList.remove('show');
            }
        }
    </script>
</body>
</html>