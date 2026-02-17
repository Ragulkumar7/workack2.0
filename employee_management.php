<?php
// employee_management.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR INCLUDE
 $sidebarPath = __DIR__ . '/sidebars.php'; 
if (!file_exists($sidebarPath)) {
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
}

// 3. LOGIN CHECK
if (!isset($_SESSION['user_id'])) { 
    // header("Location: index.php"); 
    // exit(); 
}

// 4. MOCK DATA (Updated with Bank & Statutory Details)
 $employees = [
    [
        "id" => "EMP-001", "first_name" => "Anthony", "last_name" => "Lewis", 
        "email" => "anthony@example.com", "phone" => "(123) 4567 890", 
        "designation" => "Team Lead", "dept" => "Finance", "company" => "Abac Company",
        "join_date" => "2024-09-12", "status" => "Active", "img" => "11",
        "emp_type" => "Permanent",
        "pan" => "ABCDE1234F", "pf_no" => "PF101010", "esi_no" => "ESI202020",
        "bank_name" => "HDFC Bank", "account_no" => "1234567890", "ifsc" => "HDFC000123"
    ],
    [
        "id" => "EMP-002", "first_name" => "Brian", "last_name" => "Villalobos", 
        "email" => "brian@example.com", "phone" => "(179) 7382 829", 
        "designation" => "Senior Developer", "dept" => "Development", "company" => "Abac Company",
        "join_date" => "2024-10-24", "status" => "Active", "img" => "12",
        "emp_type" => "Contract",
        "pan" => "FGHIJ5678K", "pf_no" => "PF303030", "esi_no" => "ESI404040",
        "bank_name" => "SBI", "account_no" => "0987654321", "ifsc" => "SBIN000456"
    ],
    [
        "id" => "EMP-003", "first_name" => "Harvey", "last_name" => "Smith", 
        "email" => "harvey@example.com", "phone" => "(782) 8291 920", 
        "designation" => "Team Lead", "dept" => "Sales", "company" => "Abac Company",
        "join_date" => "2025-02-15", "status" => "Inactive", "img" => "13",
        "emp_type" => "Permanent",
        "pan" => "", "pf_no" => "", "esi_no" => "",
        "bank_name" => "", "account_no" => "", "ifsc" => ""
    ],
    [
        "id" => "EMP-004", "first_name" => "Stephan", "last_name" => "Peralt", 
        "email" => "stephan@example.com", "phone" => "(929) 1022 222", 
        "designation" => "Android Developer", "dept" => "Development", "company" => "Abac Company",
        "join_date" => "2025-03-01", "status" => "Active", "img" => "14",
        "emp_type" => "Intern",
        "pan" => "", "pf_no" => "", "esi_no" => "",
        "bank_name" => "", "account_no" => "", "ifsc" => ""
    ],
];
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
        :root {
            /* Updated Theme to Dark Teal */
            --primary: #144d4d; /* Dark Teal Custom */
            --primary-hover: #115e59; /* Teal-700 */
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

        /* --- CRITICAL LAYOUT FIX FOR SIDEBAR --- */
        .main-content {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px;
            min-height: 100vh;
        }
        /* When Secondary Sidebar Opens */
        .main-content.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--text-main); letter-spacing: -0.025em; }
        .breadcrumb { display: flex; align-items: center; font-size: 0.875rem; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        /* Buttons & Toggles */
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

        /* --- STATS CARDS --- */
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
        .stat-badge { font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
        
        .card-teal .stat-icon-box { background: var(--primary); } .card-teal .stat-badge { background: var(--primary-light); color: var(--primary); }
        .card-green .stat-icon-box { background: #10b981; } .card-green .stat-badge { background: #dcfce7; color: #16a34a; }
        .card-red .stat-icon-box { background: #ef4444; } .card-red .stat-badge { background: #fee2e2; color: #dc2626; }
        .card-blue .stat-icon-box { background: #3b82f6; } .card-blue .stat-badge { background: #dbeafe; color: #2563eb; }

        /* --- FILTERS --- */
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
            border: none; outline: none; width: 100%; font-size: 0.875rem; background: transparent; color: var(--text-main); margin-left: 8px;
        }
        .search-box { flex: 2; min-width: 250px; }

        /* --- LIST VIEW --- */
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

        /* --- GRID VIEW --- */
        .grid-view-container { display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .grid-view-container.active { display: grid; }
        
        .emp-card {
            background: white; border: 1px solid var(--border); border-radius: 16px;
            padding: 24px; text-align: center; position: relative; transition: transform 0.2s, box-shadow 0.2s;
        }
        .emp-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.1); }
        .card-menu { position: absolute; top: 15px; right: 15px; cursor: pointer; color: #94a3b8; transition: 0.2s; }
        .card-menu:hover { color: var(--primary); }
        .card-img { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; object-fit: cover; border: 3px solid #f1f5f9; }
        
        /* --- EXPORT DROPDOWN --- */
        .export-dropdown {
            position: absolute; top: 100%; right: 0; background: white; border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 10;
            width: 180px; display: none; flex-direction: column; margin-top: 5px; padding: 8px;
        }
        .export-dropdown.show { display: flex; }
        .export-item { padding: 10px 12px; font-size: 0.875rem; color: var(--text-main); cursor: pointer; display: flex; align-items: center; gap: 10px; border-radius: 8px; }
        .export-item:hover { background: #f1f5f9; }

        /* --- MODAL --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: white; width: 850px; max-width: 95%; max-height: 90vh; overflow-y: auto;
            border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        }
        .modal-header { 
            padding: 20px 24px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 1.125rem; font-weight: 600; color: #1e293b; }
        .modal-header span { color: #6b7280; font-weight: 400; font-size: 0.875rem; margin-left: 10px; }
        
        .modal-tabs { padding: 0 24px; border-bottom: 1px solid var(--border); display: flex; gap: 24px; background: white; position: sticky; top: 0; z-index: 10; }
        .tab-item { padding: 12px 0; font-size: 0.875rem; font-weight: 500; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; }
        .tab-item.active { color: var(--primary); border-bottom-color: var(--primary); }

        .modal-body { padding: 24px; }
        
        /* Image Upload Area */
        .img-upload-area {
            display: flex; align-items: center; gap: 20px; padding: 20px;
            background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; margin-bottom: 20px;
        }
        .preview-circle { width: 64px; height: 64px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #94a3b8; overflow: hidden; }
        .preview-circle img { width: 100%; height: 100%; object-fit: cover; }

        /* Form Grid */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 5px; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 6px; color: #374151; }
        .form-group label span { color: #ef4444; }
        
        .form-control {
            width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0;
            border-radius: 10px; font-size: 0.875rem; box-sizing: border-box; outline: none; transition: 0.2s;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); }
        
        .password-group { position: relative; }
        .password-toggle { position: absolute; right: 12px; top: 38px; color: #9ca3af; cursor: pointer; width: 16px; }

        .form-section-title { font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 1px dashed var(--border); }

        .modal-footer { 
            padding: 16px 24px; border-top: 1px solid var(--border); 
            display: flex; justify-content: flex-end; gap: 12px; 
            background: #f8fafc;
        }

        /* PERMISSIONS TABLE */
        .perm-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .perm-table th { background: #f8fafc; padding: 12px; text-align: center; font-size: 0.75rem; border-bottom: 1px solid var(--border); font-weight: 600; color: var(--text-muted); }
        .perm-table th:first-child { text-align: left; padding-left: 20px; }
        .perm-table td { padding: 10px; text-align: center; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
        .perm-table td:first-child { text-align: left; padding-left: 20px; font-weight: 500; color: var(--text-main); }
        .role-check { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; border-radius: 4px; }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .filters-row { flex-direction: column; align-items: stretch; }
            .form-grid { grid-template-columns: 1fr; }
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content" id="mainContent">
        
        <?php include 'header.php'; ?>

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
                <div class="stat-info"><span>Total Employees</span><h3>1007</h3></div>
                <div class="stat-icon-box"><i data-lucide="users"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Active</span><h3>980</h3></div>
                <div class="stat-icon-box"><i data-lucide="user-check"></i></div>
            </div>
            <div class="stat-card card-red">
                <div class="stat-info"><span>Inactive</span><h3>27</h3></div>
                <div class="stat-icon-box"><i data-lucide="user-x"></i></div>
            </div>
            <div class="stat-card card-blue">
                <div class="stat-info"><span>New Hires</span><h3>67</h3></div>
                <div class="stat-icon-box"><i data-lucide="user-plus"></i></div>
            </div>
        </div>

        <div class="filters-container">
            <h3 style="font-size:16px; font-weight:600; margin:0; color: var(--text-main);">Filter Employees</h3>
            <div class="filters-row">
                <div class="filter-group">
                    <i data-lucide="calendar" style="width:16px; color:#94a3b8;"></i>
                    <input type="text" value="02/03/2026 - 02/09/2026" readonly>
                </div>
                <div class="filter-group">
                    <select><option>Designation</option><option>Team Lead</option></select>
                    <i data-lucide="chevron-down" style="width:14px; color:#94a3b8;"></i>
                </div>
                <div class="filter-group">
                    <select><option>Select Status</option><option>Active</option></select>
                    <i data-lucide="chevron-down" style="width:14px; color:#94a3b8;"></i>
                </div>
                <div class="filter-group search-box">
                    <input type="text" placeholder="Search employees..." style="margin-left:0;">
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
                    <tr>
                        <td><input type="checkbox"></td>
                        <td style="font-weight:600; color: var(--text-main);"><?= $emp['id'] ?></td>
                        <td>
                            <div class="emp-profile">
                                <img src="https://i.pravatar.cc/150?img=<?= $emp['img'] ?>" class="emp-img">
                                <div class="emp-details">
                                    <span class="emp-name"><?= $emp['first_name'] . ' ' . $emp['last_name'] ?></span>
                                    <span class="emp-role"><?= $emp['dept'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= $emp['email'] ?></td>
                        <td><?= $emp['phone'] ?></td>
                        <td><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:500;"><?= $emp['designation'] ?></span></td>
                        <td><?= $emp['join_date'] ?></td>
                        <td><span class="status-badge <?= $emp['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>"><?= $emp['status'] ?></span></td>
                        <td>
                            <div style="display:flex; gap:10px;">
                                <i data-lucide="edit" style="width:18px; cursor:pointer; color:#64748b;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='#64748b'" onclick='openEditModal(<?= json_encode($emp) ?>)'></i>
                                <i data-lucide="trash-2" style="width:18px; cursor:pointer; color:#64748b;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'"></i>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="gridView" class="grid-view-container">
            <?php foreach($employees as $emp): ?>
            <div class="emp-card">
                <div class="card-menu"><i data-lucide="more-vertical" style="width:18px;" onclick='openEditModal(<?= json_encode($emp) ?>)'></i></div>
                <img src="https://i.pravatar.cc/150?img=<?= $emp['img'] ?>" class="card-img">
                <h4 style="margin:0 0 5px; font-weight:600; color: var(--text-main);"><?= $emp['first_name'] . ' ' . $emp['last_name'] ?></h4>
                <p style="margin:0 0 15px; font-size:13px; color:var(--text-muted);"><?= $emp['designation'] ?></p>
                <div style="display:flex; justify-content:center; gap:10px; margin-bottom:15px;">
                    <span style="background:#f1f5f9; padding:4px 10px; border-radius:20px; font-size:11px; color:var(--text-muted); font-weight:500;"><?= $emp['dept'] ?></span>
                </div>
                <div style="border-top:1px solid #f1f5f9; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="text-align:left;">
                        <span style="display:block; font-size:11px; color:#94a3b8;">Joined</span>
                        <span style="font-size:12px; font-weight:600; color:var(--text-main);"><?= $emp['join_date'] ?></span>
                    </div>
                    <span class="status-badge <?= $emp['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>"><?= $emp['status'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

 

    <script>
        lucide.createIcons();

        // 1. OPEN MODAL FOR ADDING
        function openAddModal() {
            document.getElementById('modalTitle').innerText = "Add New Employee";
            document.getElementById('modalIdDisplay').innerText = "";
            document.getElementById('saveBtn').innerText = "Save";
            document.getElementById('empForm').reset();
            document.getElementById('imgPreview').innerHTML = '<i data-lucide="image" style="width:24px;"></i>';
            
            // Switch to first tab
            const tabs = document.querySelectorAll('.tab-item');
            switchTab(tabs[0], 'tab-basic');
            
            toggleModalDisplay(true);
        }

        // 2. OPEN MODAL FOR EDITING (Pre-fills Data)
        function openEditModal(empData) {
            document.getElementById('modalTitle').innerText = "Edit Employee";
            document.getElementById('modalIdDisplay').innerText = "Employee ID : " + empData.id;
            document.getElementById('saveBtn').innerText = "Update";

            // Switch to first tab
            const tabs = document.querySelectorAll('.tab-item');
            switchTab(tabs[0], 'tab-basic');

            // Fill Fields - Basic
            document.getElementById('fName').value = empData.first_name;
            document.getElementById('lName').value = empData.last_name;
            document.getElementById('empId').value = empData.id;
            document.getElementById('joinDate').value = empData.join_date;
            document.getElementById('uName').value = empData.first_name.toLowerCase();
            document.getElementById('email').value = empData.email;
            document.getElementById('phone').value = empData.phone;
            document.getElementById('company').value = empData.company || 'Abac Company';
            document.getElementById('dept').value = empData.dept;
            document.getElementById('desig').value = empData.designation;
            document.getElementById('empType').value = empData.emp_type || 'Permanent';

            // Fill Fields - Bank & Statutory
            document.getElementById('panNo').value = empData.pan || '';
            document.getElementById('bankName').value = empData.bank_name || '';
            document.getElementById('accNo').value = empData.account_no || '';
            document.getElementById('ifsc').value = empData.ifsc || '';
            document.getElementById('pfNo').value = empData.pf_no || '';
            document.getElementById('esiNo').value = empData.esi_no || '';

            // Image Preview
            document.getElementById('imgPreview').innerHTML = `<img src="https://i.pravatar.cc/150?img=${empData.img}" style="width:100%; height:100%; border-radius:50%;">`;

            toggleModalDisplay(true);
        }

        // 3. COMMON TOGGLE FUNCTION
        function toggleModalDisplay(show) {
            const modal = document.getElementById('employeeModal');
            modal.classList.toggle('active', show);
            document.body.style.overflow = show ? 'hidden' : 'auto';
        }

        function closeModal() {
            toggleModalDisplay(false);
        }

        // 4. TAB SWITCHER
        function switchTab(element, contentId) {
            // Remove active class from all tabs
            document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            
            // Activate current
            element.classList.add('active');
            document.getElementById(contentId).style.display = 'block';
        }

        // 5. VIEW SWITCHER
        function switchView(view) {
            const listBtn = document.getElementById('btnList');
            const gridBtn = document.getElementById('btnGrid');
            const listView = document.getElementById('listView');
            const gridView = document.getElementById('gridView');

            if(view === 'list') {
                listBtn.classList.add('active'); gridBtn.classList.remove('active');
                listView.style.display = 'block'; gridView.classList.remove('active');
            } else {
                gridBtn.classList.add('active'); listBtn.classList.remove('active');
                listView.style.display = 'none'; gridView.classList.add('active');
            }
        }

        function toggleExport() {
            const menu = document.getElementById('exportMenu');
            menu.classList.toggle('show');
        }

        // Close on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('employeeModal')) {
                closeModal();
            }
            if (!event.target.matches('.btn') && !event.target.matches('.btn *')) {
                var dropdowns = document.getElementsByClassName("export-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
                }
            }
        }
    </script>
</body>
</html>