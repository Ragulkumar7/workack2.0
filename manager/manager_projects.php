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
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        :root {
            --primary: #ff9b44;
            --primary-hover: #ff851a;
            --text-dark: #1f1f1f;
            --text-muted: #8e8e8e;
            --bg-body: #f7f7f7;
            --border-color: #e3e3e3;
            --card-bg: #ffffff;
            --success: #55ce63;
            --danger: #f62d51;
            --warning: #ffbc34;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            background-color: var(--bg-body);
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 25px;
            margin-left: 95px;
            transition: margin-left 0.3s;
        }
        .main-content.main-shifted { margin-left: 315px; }

        /* HEADER & UTILS */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title h3 { font-size: 1.25rem; font-weight: 500; color: var(--text-dark); }
        .breadcrumb { font-size: 0.85rem; color: var(--text-muted); display: flex; gap: 5px; }
        .breadcrumb span { color: var(--text-dark); font-weight: 500; }
        
        .header-actions { display: flex; gap: 10px; align-items: center; }
        
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-decoration: none;
        }
        .btn-orange { background: var(--primary); color: white; }
        .btn-orange:hover { background: var(--primary-hover); }
        .btn-white { background: white; border: 1px solid var(--border-color); color: var(--text-dark); position: relative;}
        .btn-white:hover { background: #f9f9f9; }
        .btn-icon { width: 35px; height: 35px; justify-content: center; padding: 0; }
        .btn-icon.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* EXPORT DROPDOWN */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            min-width: 150px;
            display: none;
            z-index: 100;
            margin-top: 5px;
        }
        .dropdown-menu.show { display: block; }
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            font-size: 0.9rem;
            gap: 10px;
            cursor: pointer;
        }
        .dropdown-item:hover { background: #f5f5f5; }

        /* FILTERS */
        .filter-row {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .form-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-muted);
            outline: none;
            cursor: pointer;
        }
        
        /* VIEWS */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .projects-list {
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            overflow-x: auto;
            display: none;
        }
        
        /* CARD STYLES */
        .project-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 20px;
            position: relative;
            transition: 0.3s;
        }
        .project-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; position: relative; }
        .project-title { font-size: 1rem; font-weight: 500; color: var(--text-dark); margin-bottom: 5px; }
        .project-desc { font-size: 0.8rem; color: #777; line-height: 1.5; margin-bottom: 20px; height: 40px; overflow: hidden; }
        .project-meta { display: flex; align-items: center; margin-bottom: 20px; }
        .leader-avatar { width: 30px; height: 30px; border-radius: 50%; margin-right: 10px; }
        .leader-info h5 { font-size: 0.85rem; font-weight: 500; color: var(--text-dark); }
        .leader-info span { font-size: 0.75rem; color: #888; }
        .deadline-badge { margin-left: auto; text-align: right; }
        .deadline-badge span { display: block; font-size: 0.75rem; color: #888; }
        .deadline-badge strong { font-size: 0.8rem; color: var(--text-dark); }
        .card-footer { margin-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .task-count { font-size: 0.8rem; font-weight: 500; color: var(--text-dark); display: flex; align-items: center; gap: 5px;}
        .team-avatars { display: flex; }
        .team-avatars img { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #fff; margin-left: -10px; }
        .team-avatars img:first-child { margin-left: 0; }
        .team-plus { width: 28px; height: 28px; border-radius: 50%; background: #ff5b37; color: white; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; margin-left: -10px; }

        /* ACTION DROPDOWN IN CARD */
        .action-icon { cursor: pointer; padding: 5px; color: #aaa; }
        .card-action-menu {
            position: absolute;
            right: 10px;
            top: 40px;
            background: white;
            border: 1px solid #eee;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 4px;
            z-index: 10;
            display: none;
            width: 120px;
        }
        .card-action-menu.active { display: block; }
        .card-action-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            font-size: 0.85rem;
            color: #333;
            text-decoration: none;
            transition: 0.2s;
        }
        .card-action-item:hover { background: #f9f9f9; }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f9f9f9; font-weight: 600; font-size: 0.9rem; color: #333; border-bottom: 1px solid #eee; }
        td { padding: 15px; vertical-align: middle; font-size: 0.9rem; border-bottom: 1px solid #eee; color: #555; }
        tr:hover td { background: #fafafa; }
        
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; color: white; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .status-badge.active { background: var(--success); }
        .status-badge.inactive { background: var(--danger); }
        .status-dot { width: 6px; height: 6px; background: white; border-radius: 50%; display: inline-block; }
        
        .priority-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; border: 1px solid; font-size: 0.8rem; font-weight: 500; display: flex; align-items: center; gap: 5px; width: fit-content; }
        .priority-high { color: var(--danger); border-color: var(--danger); }
        .priority-medium { color: var(--warning); border-color: var(--warning); }
        .priority-low { color: var(--success); border-color: var(--success); }
        .priority-dot { width: 6px; height: 6px; border-radius: 50%; }
        .priority-high .priority-dot { background: var(--danger); }
        .priority-medium .priority-dot { background: var(--warning); }
        .priority-low .priority-dot { background: var(--success); }

        /* MODAL & FORM */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; width: 800px; max-width: 95%; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.2rem; font-weight: 500; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #aaa; }
        .modal-tabs { display: flex; padding: 0 20px; border-bottom: 1px solid #eee; margin-top: 10px; }
        .tab-btn { padding: 10px 20px; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-weight: 500; color: #777; }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .modal-body { padding: 25px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .col-half { flex: 1; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.9rem; margin-bottom: 8px; font-weight: 400; color: #333; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--primary); outline: none; }
        
        /* Dynamic Tags Input */
        .tags-input-container { border: 1px solid #ddd; padding: 5px; border-radius: 4px; display: flex; flex-wrap: wrap; gap: 5px; min-height: 42px; align-items: center; cursor: text; }
        .tags-input-container:focus-within { border-color: var(--primary); }
        .tag-pill { background: #f0f0f0; border: 1px solid #e0e0e0; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; color: #333; }
        .tag-close { cursor: pointer; font-size: 1rem; line-height: 0.8; color: #999; }
        .tag-close:hover { color: red; }
        .tag-input { border: none; outline: none; flex: 1; padding: 5px; font-size: 0.9rem; min-width: 80px; background: transparent; }

        .editor-toolbar { border: 1px solid #ddd; border-bottom: none; padding: 5px; background: #f9f9f9; border-radius: 4px 4px 0 0; display: flex; gap: 5px; }
        .editor-btn { width: 25px; height: 25px; border: none; background: none; cursor: pointer; color: #555; }
        textarea.has-toolbar { border-radius: 0 0 4px 4px; }

        /* Load More Button */
        .load-more-container { text-align: center; margin-top: 20px; margin-bottom: 40px; }
        .btn-load-more { background: white; border: 1px solid var(--primary); color: var(--primary); padding: 10px 20px; border-radius: 5px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-load-more:hover { background: var(--primary); color: white; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include '../sidebars.php'; ?>

    <main class="main-content" id="mainContent">
      <?php include '../header.php'; ?>
        <div class="page-header">
            <div class="page-title">
                <h3>Projects</h3>
                <div class="breadcrumb">
                    <i class="fa-solid fa-house"></i>
                    Projects > <span id="viewTitle">Projects Grid</span>
                </div>
            </div>
            <div class="header-actions">
                <div style="position:relative;">
                    <input type="text" id="searchInput" placeholder="Search in HRMS" style="padding: 8px 30px 8px 10px; border:1px solid #ddd; border-radius:4px; font-size:0.9rem;">
                    <i class="fa-solid fa-search" style="position:absolute; right:10px; top:10px; color:#aaa; font-size:0.8rem;"></i>
                </div>
                
                <button class="btn btn-white btn-icon active" id="btnGrid" onclick="switchView('grid')"><i class="fa-solid fa-border-all"></i></button>
                <button class="btn btn-white btn-icon" id="btnList" onclick="switchView('list')"><i class="fa-solid fa-list"></i></button>
                
                <div style="position:relative;">
                    <button class="btn btn-white" onclick="toggleExportMenu()"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down" style="font-size:0.7rem;"></i></button>
                    <div class="dropdown-menu" id="exportMenu">
                        <a href="#" class="dropdown-item" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf" style="color:#F40F02"></i> Export as PDF</a>
                        <a href="#" class="dropdown-item" onclick="exportToExcel()"><i class="fa-solid fa-file-excel" style="color:#1D6F42"></i> Export as Excel</a>
                    </div>
                </div>
                
                <button class="btn btn-orange" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add Project</button>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-left">
                <h4 id="viewHeading">Projects Grid</h4>
            </div>
            <div class="filter-right" style="display:flex; gap:10px;">
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

        <div class="projects-grid" id="projectsGridView">
            </div>

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
                <tbody id="tableBody">
                    </tbody>
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
                    <small style="color:#777;">Project ID : PRO-0004</small>
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
                        <div style="width:60px; height:60px; background:#f0f0f0; border-radius:4px; display:flex; align-items:center; justify-content:center; border:1px dashed #ccc;">
                            <i class="fa-regular fa-image" style="color:#ccc; font-size:1.5rem;"></i>
                        </div>
                        <div>
                            <label class="form-label" style="margin-bottom:2px;">Upload Project Logo</label>
                            <span style="font-size:0.75rem; color:#999; display:block; margin-bottom:5px;">Image should be below 4 mb</span>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn btn-orange" style="padding:4px 10px; font-size:0.8rem;">Upload</button>
                                <button type="button" class="btn btn-white" style="padding:4px 10px; font-size:0.8rem; color:red; border-color:red;">Cancel</button>
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
                                        <span style="position:absolute; left:10px; top:10px; color:#777;">$</span>
                                        <input type="number" class="form-control" style="padding-left:25px;">
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
                <button class="btn btn-orange" onclick="saveProject()">Save</button>
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

        // --- RENDER FUNCTIONS (With Pagination) ---
        function renderProjects() {
            // Apply filtering logic first to get the dataset
            let filtered = getFilteredProjects();

            gridView.innerHTML = '';
            tableBody.innerHTML = '';

            // Slice data based on visibleCount
            const toShow = filtered.slice(0, visibleCount);
            
            // Check Load More Visibility
            if(visibleCount >= filtered.length) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.style.display = 'block';
            }

            // Grid View Render
            toShow.forEach(p => {
                const percent = (p.tasks_done / p.tasks_total) * 100;
                const statusColor = p.status === 'Active' ? 'var(--success)' : 'var(--danger)';
                
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
                            <i class="fa-regular fa-square-check" style="color:${statusColor};"></i>
                            Tasks : ${p.tasks_done}/${p.tasks_total}
                        </div>
                        <div class="team-avatars">
                            ${p.team.map(img => `<img src="${img}">`).join('')}
                            <div class="team-plus">+${Math.floor(Math.random() * 5) + 1}</div>
                        </div>
                    </div>
                </div>`;
                gridView.innerHTML += cardHtml;

                // List View Render (Table Row)
                const priorityClass = `priority-${p.priority.toLowerCase()}`;
                const statusClass = p.status.toLowerCase();
                const rowHtml = `
                <tr>
                    <td><input type="checkbox"></td>
                    <td><a href="#" style="color:#333; text-decoration:none;">${p.id}</a></td>
                    <td style="font-weight:500;">${p.name}</td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <img src="${p.leader_img}" style="width:25px; height:25px; border-radius:50%; margin-right:8px;">
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
                            <span class="priority-dot"></span> ${p.priority} <i class="fa-solid fa-chevron-down" style="font-size:0.6rem; margin-left:5px;"></i>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            <span class="status-dot"></span> ${p.status}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; font-size:1rem;">
                            <i class="fa-regular fa-pen-to-square" style="color:#777; cursor:pointer;" onclick="editProject('${p.id}')"></i>
                            <i class="fa-regular fa-trash-can" style="color:#777; cursor:pointer;" onclick="deleteProject('${p.id}')"></i>
                        </div>
                    </td>
                </tr>`;
                tableBody.innerHTML += rowHtml;
            });
        }

        function getFilteredProjects() {
            let filtered = [...projects];
            
            // Search
            const term = searchInput.value.toLowerCase();
            if(term) {
                filtered = filtered.filter(p => p.name.toLowerCase().includes(term) || p.leader.toLowerCase().includes(term));
            }

            // Status Filter
            const status = statusFilter.value;
            if(status !== 'All') {
                filtered = filtered.filter(p => p.status === status);
            }

            // Sorting
            const sortVal = sortFilter.value;
            if(sortVal === 'asc') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            } else if(sortVal === 'desc') {
                filtered.sort((a, b) => b.name.localeCompare(a.name));
            } 
            return filtered;
        }

        function applyFilters() {
            visibleCount = 8; // Reset pagination on filter change
            renderProjects();
        }

        function loadMoreProjects() {
            visibleCount += 4; // Load 4 more
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
            // element is the 'x' span. parent is tag-pill.
            // We need to prevent the click from bubbling to focusTagInput if possible, but removing the element stops it naturally.
            element.parentElement.remove();
        }

        // --- CARD ACTION MENU LOGIC ---
        function toggleCardMenu(id, event) {
            event.stopPropagation();
            // Close others
            document.querySelectorAll('.card-action-menu').forEach(m => {
                if(m.id !== `menu-${id}`) m.classList.remove('active');
            });
            const menu = document.getElementById(`menu-${id}`);
            menu.classList.toggle('active');
        }

        // Close menus when clicking outside
        window.addEventListener('click', () => {
            document.querySelectorAll('.card-action-menu').forEach(m => m.classList.remove('active'));
            exportMenu.classList.remove('show');
        });

        // --- ACTIONS ---
        function editProject(id) {
            alert('Edit Project Modal would open for ID: ' + id);
            // In a real app, populate modal with data here
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
                document.getElementById('viewTitle').innerText = 'Projects Grid';
                document.getElementById('viewHeading').innerText = 'Projects Grid';
                // Show load more in grid view
                loadMoreBtn.style.display = (visibleCount < getFilteredProjects().length) ? 'block' : 'none';
            } else {
                document.getElementById('projectsGridView').style.display = 'none';
                document.getElementById('projectsListView').style.display = 'block';
                document.getElementById('btnGrid').classList.remove('active');
                document.getElementById('btnList').classList.add('active');
                document.getElementById('viewTitle').innerText = 'Projects List';
                document.getElementById('viewHeading').innerText = 'Project List';
                // Typically list view shows pagination, but here we can keep load more or hide it. Keeping it for consistency.
                loadMoreBtn.style.display = (visibleCount < getFilteredProjects().length) ? 'block' : 'none';
            }
        }

        // --- EXPORT FUNCTIONS ---
        function toggleExportMenu() {
            // Event bubbling handled by window click listener
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

        // --- ADD PROJECT MODAL LOGIC ---
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

            // Generate New Object
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