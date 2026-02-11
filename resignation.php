<?php
// resignation.php - Resignation Management Module

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

// 4. MOCK DATA FOR THE TABLE
$resignations = [
    ["id" => "1", "name" => "Anthony Lewis", "dept" => "Finance", "reason" => "Career Change", "notice_date" => "14 Jan 2024", "res_date" => "14 Mar 2024", "img" => "11"],
    ["id" => "2", "name" => "Brian Villalobos", "dept" => "Application Development", "reason" => "Entrepreneurial Pursuits", "notice_date" => "21 Jan 2024", "res_date" => "21 Mar 2024", "img" => "12"],
    ["id" => "3", "name" => "Harvey Smith", "dept" => "Web Development", "reason" => "Relocation", "notice_date" => "18 Feb 2024", "res_date" => "18 Apr 2024", "img" => "13"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resignation - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary: #f97316; /* Professional Orange per image_4c093e.png */
            --primary-hover: #ea580c;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --bg-body: #f8f9fa;
            --white: #ffffff;
            --sidebar-width: 95px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT --- */
        .main-content {
            margin-left: var(--primary-sidebar-width, 95px); /* Matches sidebar width from sidebars.php */
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER SECTION --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #1e293b; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }
        .breadcrumb a { text-decoration: none; color: inherit; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 9px 16px; font-size: 14px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f9fafb; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* --- FILTERS CONTAINER --- */
        .list-container {
            background: white; border: 1px solid var(--border);
            border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .list-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .list-title { font-size: 16px; font-weight: 700; color: #334155; }

        .filters-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 12px; background: white; 
            min-width: 140px; flex: 1;
        }
        .filter-group i { color: #9ca3af; width: 16px; height: 16px; }
        .filter-group select, .filter-group input {
            border: none; outline: none; width: 100%; font-size: 13px; background: transparent; color: var(--text-main); margin-left: 8px;
        }

        .search-container { position: relative; flex: 1; min-width: 250px; }
        .search-input {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border);
            border-radius: 6px; font-size: 13px; outline: none;
        }

        /* --- TABLE --- */
        .table-responsive { overflow-x: auto; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        thead { background: #f9fafb; border-bottom: 1px solid var(--border); }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase; }
        td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        
        .emp-cell { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .emp-name { font-weight: 600; color: var(--text-main); }

        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary); }

        .action-icons { display: flex; gap: 12px; color: #94a3b8; }
        .action-icons i { cursor: pointer; width: 18px; transition: 0.2s; }
        .action-icons i:hover { color: var(--text-main); }

        /* --- MODAL (Exact match for image_4c095f.png) --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: white; width: 500px; max-width: 95%; 
            border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
            overflow: hidden; animation: modalIn 0.3s ease-out;
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .modal-header { 
            padding: 20px 24px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }
        
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #374151; }
        
        .form-control {
            width: 100%; padding: 12px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.2s;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        
        .modal-footer { 
            padding: 16px 24px; border-top: 1px solid var(--border); 
            display: flex; justify-content: flex-end; gap: 12px; background: #f9fafb;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; justify-content: space-between; }
            .filters-row { flex-direction: column; align-items: stretch; }
            .search-container { order: -1; width: 100%; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content" id="mainContent">
        
        <div class="page-header">
            <div>
                <div class="header-title"><h1>Resignation</h1></div>
                <div class="breadcrumb">
                    <a href="#"><i data-lucide="home" style="width:14px;"></i></a>
                    <span>/</span>
                    <a href="#">HRM</a>
                    <span>/</span>
                    <span style="font-weight:600; color:#111827;">Resignation</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="toggleModal(true)">
                    <i data-lucide="plus-circle" style="width:16px;"></i> Add Resignation
                </button>
                <button class="btn"><i data-lucide="chevron-up" style="width:16px;"></i></button>
            </div>
        </div>

        <div class="list-container">
            <div class="list-header-row">
                <span class="list-title">Resignation List</span>
                <div style="display:flex; gap:10px; align-items:center;">
                    <div class="filter-group" style="min-width: 180px;">
                        <i data-lucide="calendar"></i>
                        <input type="text" value="02/03/2026 - 02/09/2026" readonly>
                    </div>
                    <div class="filter-group">
                        <select>
                            <option>Sort By : Last 7 Days</option>
                            <option>Recently Added</option>
                        </select>
                        <i data-lucide="chevron-down" style="width:14px;"></i>
                    </div>
                </div>
            </div>

            <div class="filters-row">
                <div style="font-size:13px; color:var(--text-muted);">
                    Row Per Page 
                    <select style="border:1px solid var(--border); padding:5px; border-radius:4px; margin:0 5px;">
                        <option>10</option><option>25</option>
                    </select> Entries
                </div>
                <div class="search-container" style="margin-left: auto;">
                    <input type="text" class="search-input" placeholder="Search">
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                            <th>Resigning Employee <i data-lucide="arrow-up-down" style="width:12px; float:right; margin-top:2px;"></i></th>
                            <th>Department <i data-lucide="arrow-up-down" style="width:12px; float:right; margin-top:2px;"></i></th>
                            <th>Reason</th>
                            <th>Notice Date <i data-lucide="arrow-up-down" style="width:12px; float:right; margin-top:2px;"></i></th>
                            <th>Resignation Date <i data-lucide="arrow-up-down" style="width:12px; float:right; margin-top:2px;"></i></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resignations as $res): ?>
                        <tr>
                            <td><input type="checkbox" class="row-cb"></td>
                            <td>
                                <div class="emp-cell">
                                    <img src="https://i.pravatar.cc/150?img=<?= $res['img'] ?>" class="emp-img">
                                    <span class="emp-name"><?= $res['name'] ?></span>
                                </div>
                            </td>
                            <td style="color:var(--text-muted);"><?= $res['dept'] ?></td>
                            <td><?= $res['reason'] ?></td>
                            <td><?= $res['notice_date'] ?></td>
                            <td><?= $res['res_date'] ?></td>
                            <td>
                                <div class="action-icons">
                                    <i data-lucide="edit-3"></i>
                                    <i data-lucide="trash-2"></i>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="resignationModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Add Resignation</h3>
                <i data-lucide="x-circle" style="cursor:pointer; color:#94a3b8;" onclick="toggleModal(false)"></i>
            </div>
            
            <div class="modal-body">
                <form id="resignationForm">
                    <div class="form-group">
                        <label>Resigning Employee</label>
                        <select class="form-control">
                            <option>Select</option>
                            <option>Anthony Lewis</option>
                            <option>Brian Villalobos</option>
                            <option>Harvey Smith</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notice Date</label>
                        <input type="date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Resignation Date</label>
                        <input type="date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Reason</label>
                        <textarea class="form-control" rows="4" placeholder="Enter reason for resignation"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn" onclick="toggleModal(false)">Cancel</button>
                <button class="btn btn-primary">Add Resignation</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Modal Toggle
        function toggleModal(show) {
            const modal = document.getElementById('resignationModal');
            modal.classList.toggle('active', show);
            document.body.style.overflow = show ? 'hidden' : 'auto';
        }

        // Select All Logic
        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.row-cb');
            checkboxes.forEach(cb => cb.checked = master.checked);
        }

        // Close on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('resignationModal')) {
                toggleModal(false);
            }
        }
    </script>
</body>
</html>