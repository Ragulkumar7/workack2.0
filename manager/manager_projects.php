<?php
ob_start(); 
session_start();

// Mock Data (Expanded for Load More functionality)
 $projects_data = [
    [
        'id' => 'PRO-001',
        'name' => 'Office Management',
        'desc' => 'An office management app project streamlines administrative tasks...',
        'leader' => 'Michael Walker',
        'leader_img' => 'https://ui-avatars.com/api/?name=Michael+Walker&background=random',
        'team' => ['https://ui-avatars.com/api/?name=A&background=random', 'https://ui-avatars.com/api/?name=B&background=random'],
        'deadline' => '2024-09-12',
        'deadline_display' => '12 Sep 2024',
        'priority' => 'High',
        'tasks_done' => 6,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-002',
        'name' => 'Clinic Management',
        'desc' => 'A clinic management project streamlines patient records...',
        'leader' => 'Brian Villalobos',
        'leader_img' => 'https://ui-avatars.com/api/?name=Brian+Villalobos&background=random',
        'team' => ['https://ui-avatars.com/api/?name=C&background=random'],
        'deadline' => '2024-10-24',
        'deadline_display' => '24 Oct 2024',
        'priority' => 'Low',
        'tasks_done' => 7,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-003',
        'name' => 'Educational Platform',
        'desc' => 'An educational platform project provides a centralized space...',
        'leader' => 'Harvey Smith',
        'leader_img' => 'https://ui-avatars.com/api/?name=Harvey+Smith&background=random',
        'team' => ['https://ui-avatars.com/api/?name=D&background=random'],
        'deadline' => '2024-02-18',
        'deadline_display' => '18 Feb 2024',
        'priority' => 'Medium',
        'tasks_done' => 5,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-004',
        'name' => 'Chat & Call Mobile App',
        'desc' => 'A chat and call mobile app enables users to send messages...',
        'leader' => 'Stephan Peralt',
        'leader_img' => 'https://ui-avatars.com/api/?name=Stephan+Peralt&background=random',
        'team' => ['https://ui-avatars.com/api/?name=E&background=random', 'https://ui-avatars.com/api/?name=F&background=random'],
        'deadline' => '2024-10-17',
        'deadline_display' => '17 Oct 2024',
        'priority' => 'Medium',
        'tasks_done' => 6,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-005',
        'name' => 'Travel Planning Website',
        'desc' => 'A travel planning website helps users explore destinations...',
        'leader' => 'Doglas Martini',
        'leader_img' => 'https://ui-avatars.com/api/?name=Doglas+Martini&background=random',
        'team' => ['https://ui-avatars.com/api/?name=G&background=random'],
        'deadline' => '2024-07-20',
        'deadline_display' => '20 Jul 2024',
        'priority' => 'Medium',
        'tasks_done' => 8,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-006',
        'name' => 'Service Booking Software',
        'desc' => 'Service booking software enables users to schedule appointments...',
        'leader' => 'Linda Ray',
        'leader_img' => 'https://ui-avatars.com/api/?name=Linda+Ray&background=random',
        'team' => ['https://ui-avatars.com/api/?name=H&background=random'],
        'deadline' => '2024-04-10',
        'deadline_display' => '10 Apr 2024',
        'priority' => 'High',
        'tasks_done' => 9,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-007',
        'name' => 'Hotel Booking App',
        'desc' => 'A hotel booking app allows users to search, compare, and book...',
        'leader' => 'Elliot Murray',
        'leader_img' => 'https://ui-avatars.com/api/?name=Elliot+Murray&background=random',
        'team' => ['https://ui-avatars.com/api/?name=I&background=random'],
        'deadline' => '2024-04-10',
        'deadline_display' => '10 Apr 2024',
        'priority' => 'Medium',
        'tasks_done' => 2,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-008',
        'name' => 'Car & Bike Rental Software',
        'desc' => 'Car and bike rental software allows users to browse, reserve...',
        'leader' => 'Rebecca Smith',
        'leader_img' => 'https://ui-avatars.com/api/?name=Rebecca+Smith&background=random',
        'team' => ['https://ui-avatars.com/api/?name=J&background=random'],
        'deadline' => '2024-02-22',
        'deadline_display' => '22 Feb 2024',
        'priority' => 'Low',
        'tasks_done' => 6,
        'tasks_total' => 10,
        'status' => 'Inactive'
    ],
    [
        'id' => 'PRO-009',
        'name' => 'Food Order App',
        'desc' => 'A food order app allows users to browse menus, place orders...',
        'leader' => 'Connie Waters',
        'leader_img' => 'https://ui-avatars.com/api/?name=Connie+Waters&background=random',
        'team' => ['https://ui-avatars.com/api/?name=K&background=random'],
        'deadline' => '2024-11-03',
        'deadline_display' => '03 Nov 2024',
        'priority' => 'Medium',
        'tasks_done' => 7,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-010',
        'name' => 'POS Admin Software',
        'desc' => 'POS admin software enables businesses to manage sales, track...',
        'leader' => 'Lori Broaddus',
        'leader_img' => 'https://ui-avatars.com/api/?name=Lori+Broaddus&background=random',
        'team' => ['https://ui-avatars.com/api/?name=L&background=random'],
        'deadline' => '2024-12-17',
        'deadline_display' => '17 Dec 2024',
        'priority' => 'High',
        'tasks_done' => 5,
        'tasks_total' => 10,
        'status' => 'Active'
    ],
    [
        'id' => 'PRO-011',
        'name' => 'Invoicing & Billing Software',
        'desc' => 'Invoicing and billing software automates the creation, sending...',
        'leader' => 'Angela Thomas',
        'leader_img' => 'https://ui-avatars.com/api/?name=Angela+Thomas&background=random',
        'team' => ['https://ui-avatars.com/api/?name=M&background=random'],
        'deadline' => '2024-01-23',
        'deadline_display' => '23 Jan 2024',
        'priority' => 'Low',
        'tasks_done' => 8,
        'tasks_total' => 10,
        'status' => 'Active'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - SmartHR</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        :root {
            /* Updated Color Scheme to match Employee Dashboard exactly */
            --primary: #144d4d; /* Dark Teal Custom */
            --primary-hover: #115e59; /* Teal-700 for hover */
            --primary-light: #ccfbf1;
            --text-dark: #1e293b; /* Slate-800 */
            --text-muted: #64748b; /* Slate-500 */
            --bg-body: #f1f5f9; /* Slate-100 */
            --border-color: #e2e8f0; /* Slate-200 */
            --card-bg: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            background-color: var(--bg-body);
            display: flex;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            flex: 1;
            padding: 24px;
            margin-left: 95px;
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
        }
        .main-content.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* HEADER & UTILS */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title h3 { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); letter-spacing: -0.025em; }
        
        .header-actions { display: flex; gap: 12px; align-items: center; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary { 
            background: var(--primary); /* Dark Teal */
            color: white; 
            box-shadow: 0 4px 6px -1px rgba(20, 77, 77, 0.2);
        }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-white { background: white; border: 1px solid var(--border-color); color: var(--text-dark); }
        .btn-white:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-icon { width: 40px; height: 40px; justify-content: center; padding: 0; border-radius: 10px; }
        .btn-icon.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* EXPORT DROPDOWN */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            min-width: 180px;
            display: none;
            z-index: 100;
            margin-top: 8px;
            padding: 8px;
        }
        .dropdown-menu.show { display: block; }
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.875rem;
            gap: 10px;
            cursor: pointer;
            border-radius: 8px;
            transition: 0.2s;
        }
        .dropdown-item:hover { background: #f1f5f9; }

        /* FILTERS */
        .filter-row {
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border-color);
        }
        .filter-row h4 { font-size: 1rem; font-weight: 600; color: var(--text-dark); }
        
        .form-select {
            padding: 10px 36px 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-muted);
            outline: none;
            cursor: pointer;
            font-size: 0.875rem;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 10px center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(20, 77, 77, 0.1); }
        
        /* VIEWS */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }
        
        .projects-list {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
            display: none;
        }
        
        /* CARD STYLES */
        .project-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            position: relative;
            transition: all 0.3s ease;
        }
        .project-card:hover { 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; position: relative; }
        .project-title { font-size: 1.05rem; font-weight: 600; color: var(--text-dark); margin-bottom: 4px; }
        .project-desc { font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 20px; height: 36px; overflow: hidden; }
        .project-meta { display: flex; align-items: center; margin-bottom: 20px; background: #f8fafc; padding: 10px; border-radius: 10px; }
        .leader-avatar { width: 36px; height: 36px; border-radius: 10px; margin-right: 12px; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .leader-info h5 { font-size: 0.875rem; font-weight: 600; color: var(--text-dark); }
        .leader-info span { font-size: 0.75rem; color: var(--text-muted); }
        .deadline-badge { margin-left: auto; text-align: right; }
        .deadline-badge span { display: block; font-size: 0.75rem; color: var(--text-muted); }
        .deadline-badge strong { font-size: 0.8rem; color: var(--text-dark); }
        .card-footer { margin-top: 16px; display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .task-count { font-size: 0.8rem; font-weight: 500; color: var(--text-dark); display: flex; align-items: center; gap: 6px;}
        .team-avatars { display: flex; }
        .team-avatars img { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #fff; margin-left: -10px; }
        .team-avatars img:first-child { margin-left: 0; }
        .team-plus { width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: white; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; margin-left: -10px; font-weight: 600; }

        /* ACTION DROPDOWN IN CARD */
        .action-icon { cursor: pointer; padding: 8px; color: #94a3b8; border-radius: 6px; transition: 0.2s; }
        .action-icon:hover { background: #f1f5f9; color: var(--text-dark); }
        .card-action-menu {
            position: absolute;
            right: 0;
            top: 35px;
            background: white;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 12px;
            z-index: 10;
            display: none;
            width: 130px;
            padding: 6px;
        }
        .card-action-menu.active { display: block; }
        .card-action-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            font-size: 0.8rem;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 8px;
        }
        .card-action-item:hover { background: #f1f5f9; }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 20px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); }
        td { padding: 14px 20px; vertical-align: middle; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; color: var(--text-dark); }
        tr:hover td { background: #f8fafc; }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .status-badge.active { background: #dcfce7; color: #16a34a; }
        .status-badge.inactive { background: #fee2e2; color: #dc2626; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .status-badge.active .status-dot { background: #16a34a; }
        .status-badge.inactive .status-dot { background: #dc2626; }
        
        .priority-badge { display: inline-flex; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; align-items: center; gap: 6px; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #dbeafe; color: #2563eb; }
        .priority-dot { width: 6px; height: 6px; border-radius: 50%; }
        .priority-high .priority-dot { background: #dc2626; }
        .priority-medium .priority-dot { background: #d97706; }
        .priority-low .priority-dot { background: #2563eb; }

        /* MODAL & FORM */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.4); align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal.show { display: flex; }
        .modal-content { background: white; width: 800px; max-width: 95%; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.125rem; font-weight: 600; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; transition: 0.2s; }
        .close-btn:hover { color: var(--text-dark); }
        .modal-tabs { display: flex; padding: 0 24px; border-bottom: 1px solid var(--border-color); margin-top: 10px; gap: 24px; }
        .tab-btn { padding: 12px 4px; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-weight: 500; color: var(--text-muted); transition: 0.2s; }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .col-half { flex: 1; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.875rem; margin-bottom: 8px; font-weight: 500; color: var(--text-dark); }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.875rem; transition: 0.2s; background: #fff; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); }
        
        /* Dynamic Tags Input */
        .tags-input-container { border: 1px solid var(--border-color); padding: 8px; border-radius: 10px; display: flex; flex-wrap: wrap; gap: 6px; min-height: 44px; align-items: center; cursor: text; background: #fff; }
        .tags-input-container:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); }
        .tag-pill { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; color: var(--text-dark); font-weight: 500; }
        .tag-close { cursor: pointer; font-size: 0.9rem; line-height: 1; color: #94a3b8; transition: 0.2s; }
        .tag-close:hover { color: var(--danger); }
        .tag-input { border: none; outline: none; flex: 1; padding: 4px; font-size: 0.875rem; min-width: 80px; background: transparent; }

        .editor-toolbar { border: 1px solid var(--border-color); border-bottom: none; padding: 8px; background: #f8fafc; border-radius: 10px 10px 0 0; display: flex; gap: 4px; }
        .editor-btn { width: 28px; height: 28px; border: none; background: none; cursor: pointer; color: #64748b; border-radius: 6px; transition: 0.2s; }
        .editor-btn:hover { background: #e2e8f0; color: var(--text-dark); }
        textarea.has-toolbar { border-radius: 0 0 10px 10px; }

        /* Load More Button */
        .load-more-container { text-align: center; margin-top: 24px; margin-bottom: 40px; }
        .btn-load-more { 
            background: white; 
            border: 2px solid var(--primary); 
            color: var(--primary); 
            padding: 10px 24px; 
            border-radius: 10px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: 0.2s; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-load-more:hover { background: var(--primary); color: white; }
    </style>
</head>
<body>

    <?php include '../sidebars.php'; ?>

    <main class="main-content" id="mainContent">
      <?php include '../header.php'; ?>
      
        <div class="page-header">
            <div class="page-title">
                <h3>Projects</h3>
            </div>
            <div class="header-actions">
                <div style="position:relative;">
                    <input type="text" id="searchInput" placeholder="Search in HRMS" style="padding: 10px 36px 10px 14px; border:1px solid var(--border-color); border-radius:10px; font-size:0.875rem; transition: 0.2s;" onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border-color)'">
                    <i class="fa-solid fa-search" style="position:absolute; right:12px; top:12px; color:#94a3b8; font-size:0.9rem;"></i>
                </div>
                
                <button class="btn btn-white btn-icon active" id="btnGrid" onclick="switchView('grid')"><i class="fa-solid fa-border-all"></i></button>
                <button class="btn btn-white btn-icon" id="btnList" onclick="switchView('list')"><i class="fa-solid fa-list"></i></button>
                
                <div style="position:relative;">
                    <button class="btn btn-white" onclick="toggleExportMenu()"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down" style="font-size:0.7rem;"></i></button>
                    <div class="dropdown-menu" id="exportMenu">
                        <a href="#" class="dropdown-item" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i> Export as PDF</a>
                        <a href="#" class="dropdown-item" onclick="exportToExcel()"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> Export as Excel</a>
                    </div>
                </div>
                
                <button class="btn btn-primary" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add Project</button>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-left">
                <h4 id="viewHeading">Projects Grid</h4>
            </div>
            <div class="filter-right" style="display:flex; gap:12px;">
                <select class="form-select" id="statusFilter" onchange="applyFilters()">
                    <option value="All">Select Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <select class="form-select" id="sortFilter" onchange="applyFilters()">
                    <option value="default">Sort By : Last 7 Days</option>
                    <option value="asc">Sort By : Name (A-Z)</option>
                    <option value="desc">Sort By : Name (Z-A)</option>
                </select>
            </div>
        </div>

        <div class="projects-grid" id="projectsGridView"></div>

        <div class="projects-list" id="projectsListView">
            <table id="projectsTable">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox"></th>
                        <th>Project ID</th>
                        <th>Project Name</th>
                        <th>Leader</th>
                        <th>Team</th>
                        <th>Deadline</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <div class="load-more-container" id="loadMoreContainer">
            <button class="btn-load-more" onclick="loadMoreProjects()"><i class="fa-solid fa-spinner"></i> Load More</button>
        </div>

    </main>

    <div class="modal" id="addProjectModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Add Project</h3>
                    <small style="color:var(--text-muted); font-size: 0.8rem;">Project ID : PRO-0004</small>
                </div>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <div class="modal-tabs">
                <button class="tab-btn active" onclick="switchTab('basic')">Basic Information</button>
                <button class="tab-btn" onclick="switchTab('members')">Members</button>
            </div>

            <div class="modal-body" id="tab-basic">
                <form id="projectFormBasic">
                    <div class="form-group" style="display:flex; align-items:center; gap:20px; margin-bottom:25px;">
                        <div style="width:64px; height:64px; background:#f8fafc; border-radius:12px; display:flex; align-items:center; justify-content:center; border:1px dashed #cbd5e1;">
                            <i class="fa-regular fa-image" style="color:#94a3b8; font-size:1.5rem;"></i>
                        </div>
                        <div>
                            <label class="form-label" style="margin-bottom:2px;">Upload Project Logo</label>
                            <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:5px;">Image should be below 4 mb</span>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:0.8rem;">Upload</button>
                                <button type="button" class="btn btn-white" style="padding:6px 12px; font-size:0.8rem; color:var(--danger); border-color:var(--danger);">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Project Name <span style="color:red">*</span></label>
                        <input type="text" class="form-control" id="inpProjectName" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Client</label>
                        <select class="form-control">
                            <option>Select</option>
                            <option>Global Technologies</option>
                            <option>Delta Infotech</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" value="2024-05-02">
                        </div>
                        <div class="col-half">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="inpDeadline" value="2024-05-02">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Priority</label>
                            <select class="form-control" id="inpPriority">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="col-half">
                            <div style="display:flex; gap:10px;">
                                <div style="flex:1;">
                                    <label class="form-label">Project Value</label>
                                    <div style="position:relative;">
                                        <span style="position:absolute; left:12px; top:11px; color:var(--text-muted);">$</span>
                                        <input type="number" class="form-control" style="padding-left:28px;">
                                    </div>
                                </div>
                                <div style="flex:1;">
                                    <label class="form-label">Price Type</label>
                                    <select class="form-control">
                                        <option>Hourly</option>
                                        <option>Fixed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <div class="editor-toolbar">
                            <button type="button" class="editor-btn"><b>B</b></button>
                            <button type="button" class="editor-btn"><i>I</i></button>
                            <button type="button" class="editor-btn"><u>U</u></button>
                            <button type="button" class="editor-btn"><s>S</s></button>
                            <button type="button" class="editor-btn"><i class="fa-solid fa-image"></i></button>
                        </div>
                        <textarea class="form-control has-toolbar" id="inpDesc" rows="4"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-body" id="tab-members" style="display:none;">
                <form id="projectFormMembers">
                    <div class="form-group">
                        <label class="form-label">Team Members</label>
                        <div class="tags-input-container" id="tag-members" onclick="focusTagInput(this)">
                            <span class="tag-pill">Jerald <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <span class="tag-pill">Andrew <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <span class="tag-pill">Philip <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <input type="text" class="tag-input" placeholder="Add new" onkeydown="addTag(event, this)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Leader</label>
                        <div class="tags-input-container" id="tag-leader" onclick="focusTagInput(this)">
                            <span class="tag-pill">Hendry <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <input type="text" class="tag-input" placeholder="Add new" id="inpLeaderName" onkeydown="addTag(event, this)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Project Manager</label>
                        <div class="tags-input-container" id="tag-manager" onclick="focusTagInput(this)">
                            <span class="tag-pill">Dwight <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <input type="text" class="tag-input" placeholder="Add new" onkeydown="addTag(event, this)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <div class="tags-input-container" id="tag-generic" onclick="focusTagInput(this)">
                            <span class="tag-pill">Collab <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <span class="tag-pill">Promotion <span class="tag-close" onclick="removeTag(this)">&times;</span></span>
                            <input type="text" class="tag-input" placeholder="Add new" onkeydown="addTag(event, this)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="inpStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-white" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveProject()">Save</button>
            </div>
        </div>
    </div>

    <script>
        // Init Data from PHP
        let projects = <?= json_encode($projects_data) ?>;
        let visibleCount = 8; // Initial load count
        let currentView = 'grid'; 

        // DOM Elements
        const gridView = document.getElementById('projectsGridView');
        const listView = document.getElementById('projectsListView');
        const tableBody = document.getElementById('tableBody');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const sortFilter = document.getElementById('sortFilter');
        const modal = document.getElementById('addProjectModal');
        const exportMenu = document.getElementById('exportMenu');
        const loadMoreBtn = document.getElementById('loadMoreContainer');

        // Initial Render
        renderProjects();

        // --- RENDER FUNCTIONS ---
        function renderProjects() {
            let filtered = getFilteredProjects();
            gridView.innerHTML = '';
            tableBody.innerHTML = '';

            const toShow = filtered.slice(0, visibleCount);
            
            if(visibleCount >= filtered.length) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.style.display = 'block';
            }

            toShow.forEach(p => {
                const statusBadgeClass = p.status === 'Active' ? 'active' : 'inactive';
                
                const cardHtml = `
                <div class="project-card">
                    <div class="card-header">
                        <h5 class="project-title">${p.name}</h5>
                        <div style="position:relative;">
                            <i class="fa-solid fa-ellipsis-vertical action-icon" onclick="toggleCardMenu('${p.id}', event)"></i>
                            <div class="card-action-menu" id="menu-${p.id}">
                                <a href="#" class="card-action-item" onclick="editProject('${p.id}')"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
                                <a href="#" class="card-action-item" onclick="deleteProject('${p.id}')"><i class="fa-regular fa-trash-can"></i> Delete</a>
                            </div>
                        </div>
                    </div>
                    <p class="project-desc">${p.desc}</p>
                    <div class="project-meta">
                        <img src="${p.leader_img}" class="leader-avatar">
                        <div class="leader-info">
                            <h5>${p.leader}</h5>
                            <span>Project Leader</span>
                        </div>
                        <div class="deadline-badge">
                            <span>Deadline</span>
                            <strong>${p.deadline_display}</strong>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="task-count">
                            <i class="fa-regular fa-square-check" style="color: var(--primary);"></i>
                            Tasks : ${p.tasks_done}/${p.tasks_total}
                        </div>
                        <div class="team-avatars">
                            ${p.team.map(img => `<img src="${img}">`).join('')}
                            <div class="team-plus">+${Math.floor(Math.random() * 5) + 1}</div>
                        </div>
                    </div>
                </div>`;
                gridView.innerHTML += cardHtml;

                const priorityClass = `priority-${p.priority.toLowerCase()}`;
                const statusClass = p.status.toLowerCase();
                const rowHtml = `
                <tr>
                    <td><input type="checkbox"></td>
                    <td><a href="#" style="color:var(--text-dark); text-decoration:none; font-weight:500;">${p.id}</a></td>
                    <td style="font-weight:500;">${p.name}</td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <img src="${p.leader_img}" style="width:28px; height:28px; border-radius:8px; margin-right:10px;">
                            <span>${p.leader}</span>
                        </div>
                    </td>
                    <td>
                        <div class="team-avatars">
                            ${p.team.map(img => `<img src="${img}">`).join('')}
                            <div class="team-plus">+${Math.floor(Math.random() * 5) + 1}</div>
                        </div>
                    </td>
                    <td>${p.deadline_display}</td>
                    <td>
                        <div class="priority-badge ${priorityClass}">
                            <span class="priority-dot"></span> ${p.priority} <i class="fa-solid fa-chevron-down" style="font-size:0.6rem; margin-left:4px; opacity: 0.7;"></i>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            <span class="status-dot"></span> ${p.status}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; font-size:1rem;">
                            <i class="fa-regular fa-pen-to-square" style="color:var(--text-muted); cursor:pointer;" onclick="editProject('${p.id}')"></i>
                            <i class="fa-regular fa-trash-can" style="color:var(--danger); cursor:pointer;" onclick="deleteProject('${p.id}')"></i>
                        </div>
                    </td>
                </tr>`;
                tableBody.innerHTML += rowHtml;
            });
        }

        function getFilteredProjects() {
            let filtered = [...projects];
            const term = searchInput.value.toLowerCase();
            if(term) {
                filtered = filtered.filter(p => p.name.toLowerCase().includes(term) || p.leader.toLowerCase().includes(term));
            }

            const status = statusFilter.value;
            if(status !== 'All') {
                filtered = filtered.filter(p => p.status === status);
            }

            const sortVal = sortFilter.value;
            if(sortVal === 'asc') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            } else if(sortVal === 'desc') {
                filtered.sort((a, b) => b.name.localeCompare(a.name));
            } 
            return filtered;
        }

        function applyFilters() {
            visibleCount = 8;
            renderProjects();
        }

        function loadMoreProjects() {
            visibleCount += 4;
            renderProjects();
        }

        // --- TAG INPUT LOGIC ---
        function focusTagInput(container) {
            container.querySelector('input').focus();
        }

        function addTag(e, input) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = input.value.trim();
                if (value) {
                    const span = document.createElement('span');
                    span.className = 'tag-pill';
                    span.innerHTML = `${value} <span class="tag-close" onclick="removeTag(this)">&times;</span>`;
                    input.parentNode.insertBefore(span, input);
                    input.value = '';
                }
            }
        }

        function removeTag(element) {
            element.parentElement.remove();
        }

        // --- CARD ACTION MENU LOGIC ---
        function toggleCardMenu(id, event) {
            event.stopPropagation();
            document.querySelectorAll('.card-action-menu').forEach(m => {
                if(m.id !== `menu-${id}`) m.classList.remove('active');
            });
            const menu = document.getElementById(`menu-${id}`);
            menu.classList.toggle('active');
        }

        window.addEventListener('click', () => {
            document.querySelectorAll('.card-action-menu').forEach(m => m.classList.remove('active'));
            exportMenu.classList.remove('show');
        });

        // --- ACTIONS ---
        function editProject(id) {
            alert('Edit Project Modal would open for ID: ' + id);
        }

        function deleteProject(id) {
            if(confirm('Are you sure you want to delete this project?')) {
                projects = projects.filter(p => p.id !== id);
                renderProjects();
            }
        }

        // --- VIEW TOGGLE ---
        function switchView(view) {
            currentView = view;
            if(view === 'grid') {
                document.getElementById('projectsGridView').style.display = 'grid';
                document.getElementById('projectsListView').style.display = 'none';
                document.getElementById('btnGrid').classList.add('active');
                document.getElementById('btnList').classList.remove('active');
                document.getElementById('viewHeading').innerText = 'Projects Grid';
                loadMoreBtn.style.display = (visibleCount < getFilteredProjects().length) ? 'block' : 'none';
            } else {
                document.getElementById('projectsGridView').style.display = 'none';
                document.getElementById('projectsListView').style.display = 'block';
                document.getElementById('btnGrid').classList.remove('active');
                document.getElementById('btnList').classList.add('active');
                document.getElementById('viewHeading').innerText = 'Project List';
                loadMoreBtn.style.display = (visibleCount < getFilteredProjects().length) ? 'block' : 'none';
            }
        }

        // --- EXPORT FUNCTIONS ---
        function toggleExportMenu() {
            setTimeout(() => exportMenu.classList.toggle('show'), 10); 
        }

        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.text("Project List Report", 14, 15);
            const tableColumn = ["ID", "Project Name", "Leader", "Deadline", "Status"];
            const tableRows = [];
            projects.forEach(p => {
                tableRows.push([p.id, p.name, p.leader, p.deadline_display, p.status]);
            });
            doc.autoTable({ head: [tableColumn], body: tableRows, startY: 20 });
            doc.save("projects_report.pdf");
        }

        function exportToExcel() {
            const excelData = projects.map(p => ({
                "Project ID": p.id,
                "Project Name": p.name,
                "Leader": p.leader,
                "Deadline": p.deadline_display,
                "Priority": p.priority,
                "Status": p.status
            }));
            const worksheet = XLSX.utils.json_to_sheet(excelData);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Projects");
            XLSX.writeFile(workbook, "projects_export.xlsx");
        }

        // --- MODAL LOGIC ---
        function openModal() { modal.classList.add('show'); }
        function closeModal() { 
            modal.classList.remove('show'); 
            document.getElementById('projectFormBasic').reset();
            document.getElementById('projectFormMembers').reset();
            switchTab('basic');
        }
        
        function switchTab(tabName) {
            const basic = document.getElementById('tab-basic');
            const members = document.getElementById('tab-members');
            const btns = document.querySelectorAll('.tab-btn');
            
            if(tabName === 'basic') {
                basic.style.display = 'block';
                members.style.display = 'none';
                btns[0].classList.add('active');
                btns[1].classList.remove('active');
            } else {
                basic.style.display = 'none';
                members.style.display = 'block';
                btns[0].classList.remove('active');
                btns[1].classList.add('active');
            }
        }

        function saveProject() {
            const name = document.getElementById('inpProjectName').value;
            if(!name) { alert('Project Name is required'); return; }

            const leaderInput = document.getElementById('inpLeaderName').value || "New User"; 
            const deadlineVal = document.getElementById('inpDeadline').value;
            const descVal = document.getElementById('inpDesc').value || "No description provided.";
            const priorityVal = document.getElementById('inpPriority').value;
            const statusVal = document.getElementById('inpStatus').value;

            const newProject = {
                id: 'PRO-' + (Math.floor(Math.random() * 1000) + 100),
                name: name,
                desc: descVal,
                leader: leaderInput,
                leader_img: 'https://ui-avatars.com/api/?name=' + leaderInput + '&background=random',
                team: ['https://ui-avatars.com/api/?name=N&background=random'],
                deadline: deadlineVal,
                deadline_display: new Date(deadlineVal).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}),
                priority: priorityVal,
                tasks_done: 0,
                tasks_total: 10,
                status: statusVal
            };

            projects.unshift(newProject); 
            applyFilters();
            closeModal();
        }
    </script>
</body>
</html>