<?php
// --- INCLUDE YOUR EXISTING CONNECTION ---
require_once '../include/db_connect.php';

// Check if connection variable exists
if (!isset($conn)) {
    die("Database connection failed.");
}

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? '';

// --- SILENT DB UPDATES FOR FILE UPLOADS ---
$conn->query("ALTER TABLE projects ADD COLUMN IF NOT EXISTS completed_file VARCHAR(255) NULL DEFAULT NULL AFTER status");
$conn->query("ALTER TABLE projects ADD COLUMN IF NOT EXISTS project_file VARCHAR(255) NULL DEFAULT NULL AFTER description");

// --- HANDLE FORM SUBMISSIONS (ADD, EDIT, DELETE) ---

// A. Add or Edit Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {
    
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // Sanitize inputs
    $name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $leader_id = intval($_POST['leader_id']);
    $client = mysqli_real_escape_string($conn, $_POST['client_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Default values for removed form fields
    $value = 0;
    $price_type = 'Fixed';

    // Handle Image Upload (Project Logo)
    $logo_path = null;
    $target_dir = "../uploads/projects/"; 
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    if (!empty($_FILES['project_logo']['name'])) {
        $filename = time() . "_logo_" . basename($_FILES['project_logo']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['project_logo']['tmp_name'], $target_file)) {
            $logo_path = "uploads/projects/" . $filename; 
        }
    }

    // Handle Document Upload (Project Requirements File)
    $project_file_path = null;
    if (!empty($_FILES['project_file']['name'])) {
        $filename2 = time() . "_req_" . basename($_FILES['project_file']['name']);
        $target_file2 = $target_dir . $filename2;
        if (move_uploaded_file($_FILES['project_file']['tmp_name'], $target_file2)) {
            $project_file_path = "uploads/projects/" . $filename2; 
        }
    }

    if ($edit_id > 0) {
        // UPDATE EXISTING PROJECT
        $sql = "UPDATE projects SET project_name=?, client_name=?, leader_id=?, start_date=?, deadline=?, priority=?, project_value=?, price_type=?, description=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisssdsssi", $name, $client, $leader_id, $start_date, $end_date, $priority, $value, $price_type, $desc, $status, $edit_id);
        $stmt->execute();
        $stmt->close();

        if ($logo_path) {
            $conn->query("UPDATE projects SET project_logo='$logo_path' WHERE id=$edit_id");
        }
        if ($project_file_path) {
            $conn->query("UPDATE projects SET project_file='$project_file_path' WHERE id=$edit_id");
        }

        echo "<script>window.location.href='manager_projects.php?msg=updated';</script>";
        exit();

    } else {
        // INSERT NEW PROJECT
        $sql = "INSERT INTO projects (project_name, client_name, leader_id, start_date, deadline, priority, project_value, price_type, description, project_file, status, project_logo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssisssdsssssi", $name, $client, $leader_id, $start_date, $end_date, $priority, $value, $price_type, $desc, $project_file_path, $status, $logo_path, $current_user_id);
            $stmt->execute();
            $stmt->close();
            
            echo "<script>window.location.href='manager_projects.php?msg=added';</script>";
            exit();
        }
    }
}

// B. Delete Project
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM projects WHERE id = $id");
    echo "<script>window.location.href='manager_projects.php?msg=deleted';</script>";
    exit();
}

// --- FETCH DATA FOR VIEW ---

// 1. STRICT HIERARCHY FILTER
$hierarchy_filter = in_array($current_role, ['System Admin', 'CEO', 'HR']) 
    ? "1=1" 
    : "(ep.reporting_to = $current_user_id OR ep.manager_id = $current_user_id)";

// 2. Fetch Strictly Team Leads assigned to this manager
$tl_query = "SELECT u.id, COALESCE(ep.full_name, u.name, u.username) as name, ep.designation 
             FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
             WHERE u.status != 'Inactive' 
             AND u.role = 'Team Lead' 
             AND $hierarchy_filter
             ORDER BY name ASC";
$tl_result = $conn->query($tl_query);
$team_leads = [];
if ($tl_result) { while ($row = $tl_result->fetch_assoc()) { $team_leads[] = $row; } }

// 3. Fetch Projects with Details
$sql = "SELECT p.*, COALESCE(u.name, ep.full_name) as leader_name, u.employee_id as leader_emp_id
        FROM projects p LEFT JOIN users u ON p.leader_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id
        ORDER BY p.id DESC";
$result = $conn->query($sql);
$projects_data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $leader_name = !empty($row['leader_name']) ? $row['leader_name'] : "Unknown";
        $leader_img = "https://ui-avatars.com/api/?name=" . urlencode($leader_name) . "&background=0d9488&color=fff";
        $deadline_display = ($row['deadline'] && $row['deadline'] != '0000-00-00') ? date("d M Y", strtotime($row['deadline'])) : 'No Deadline';

        // Format File Paths safely
        $comp_file = null;
        if (!empty($row['completed_file'])) {
            $comp_file = (strpos($row['completed_file'], 'http') === 0) ? $row['completed_file'] : '../' . ltrim($row['completed_file'], '/');
        }
        
        $req_file = null;
        if (!empty($row['project_file'])) {
            $req_file = (strpos($row['project_file'], 'http') === 0) ? $row['project_file'] : '../' . ltrim($row['project_file'], '/');
        }

        $projects_data[] = [
            'id' => 'PRO-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'real_id' => $row['id'],
            'name' => htmlspecialchars($row['project_name']),
            'client_name' => htmlspecialchars($row['client_name'] ?? ''),
            'start_date' => $row['start_date'],
            'desc_raw' => htmlspecialchars($row['description'] ?? ''),
            'desc' => htmlspecialchars(substr(strip_tags($row['description'] ?? ''), 0, 100)) . '...',
            'leader' => htmlspecialchars($leader_name),
            'leader_id' => $row['leader_id'],
            'leader_img' => $leader_img,
            'deadline' => $row['deadline'],
            'deadline_display' => $deadline_display,
            'priority' => $row['priority'],
            'status' => $row['status'],
            'completed_file' => $comp_file,
            'req_file' => $req_file
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - SmartHR</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        :root {
            --primary: #144d4d;
            --primary-hover: #115e59;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f1f5f9;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); display: flex; min-height: 100vh; color: var(--text-dark); }
        /* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT 
           ========================================================== */
        .main-content, #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            padding: 30px; /* Adjust inner padding as needed */
            min-height: 100vh;
        }

        /* Desktop: Shifts content right when secondary sub-menu opens */
        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Mobile & Tablet Adjustments */
        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 80px 15px 30px !important; /* Top padding clears the hamburger menu */
            }
            
            /* Prevent shifting on mobile (menu floats over content instead) */
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            .projects-grid { grid-template-columns: 1fr !important; }
            .form-row { flex-direction: column; gap: 10px; }
        }

        /* HEADER & UTILS */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title h3 { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.025em; }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        .btn { padding: 10px 20px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 6px -1px rgba(20, 77, 77, 0.2); }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-white { background: white; border: 1px solid var(--border-color); color: var(--text-dark); }
        .btn-white:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-icon { width: 40px; height: 40px; justify-content: center; padding: 0; border-radius: 10px; }
        .btn-icon.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* EXPORT DROPDOWN */
        .dropdown-menu { position: absolute; top: 100%; right: 0; background: white; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); min-width: 180px; display: none; z-index: 100; margin-top: 8px; padding: 8px; }
        .dropdown-menu.show { display: block; }
        .dropdown-item { display: flex; align-items: center; padding: 10px 12px; color: var(--text-dark); text-decoration: none; font-size: 0.875rem; gap: 10px; cursor: pointer; border-radius: 8px; transition: 0.2s; }
        .dropdown-item:hover { background: #f1f5f9; }

        /* FILTERS */
        .filter-row { background: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border-color); }
        .filter-row h4 { font-size: 1rem; font-weight: 700; color: var(--text-dark); }
        .form-select { padding: 10px 36px 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-muted); outline: none; cursor: pointer; font-size: 0.875rem; background-color: #fff; height: 42px; }
        .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(20, 77, 77, 0.1); }

        /* VIEWS */
        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; width: 100%; }
        .projects-list { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow-x: auto; }

        /* CARD STYLES */
        .project-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; position: relative; transition: all 0.3s ease; display: flex; flex-direction: column; }
        .project-card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); transform: translateY(-2px); border-color: #cbd5e1; }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; position: relative; }
        .project-title { font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; line-height: 1.3;}
        .project-desc { font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; height: 36px; overflow: hidden; }
        .project-meta { display: flex; align-items: center; margin-bottom: 16px; background: #f8fafc; padding: 10px; border-radius: 10px; border: 1px solid #f1f5f9;}
        .leader-avatar { width: 36px; height: 36px; border-radius: 10px; margin-right: 12px; border: 2px solid white; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
        .leader-info h5 { font-size: 0.875rem; font-weight: 700; color: var(--text-dark); }
        .leader-info span { font-size: 0.75rem; color: var(--text-muted); font-weight: 600;}
        .deadline-badge { margin-left: auto; text-align: right; }
        .card-footer { margin-top: auto; display: flex; flex-direction: column; gap: 12px; padding-top: 16px; border-top: 1px solid #f1f5f9; }

        /* ACTION DROPDOWN IN CARD */
        .action-icon { cursor: pointer; padding: 8px; color: #94a3b8; border-radius: 6px; transition: 0.2s; }
        .action-icon:hover { background: #f1f5f9; color: var(--text-dark); }
        .card-action-menu { position: absolute; right: 0; top: 35px; background: white; border: 1px solid var(--border-color); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-radius: 12px; z-index: 10; display: none; width: 130px; padding: 6px; }
        .card-action-menu.active { display: block; }
        .card-action-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; font-size: 0.8rem; font-weight: 600; color: var(--text-dark); text-decoration: none; border-radius: 8px; }
        .card-action-item:hover { background: #f1f5f9; }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 20px; background: #f8fafc; font-weight: 700; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); }
        td { padding: 14px 20px; vertical-align: middle; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; color: var(--text-dark); }
        tr:hover td { background: #f8fafc; }
        
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.5px;}
        .status-badge.active { background: #ecfdf5; color: #16a34a; border: 1px solid #dcfce7; }
        .status-badge.inactive { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        .priority-badge { display: inline-flex; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; align-items: center; gap: 6px; letter-spacing: 0.5px; border: 1px solid transparent; }
        .priority-high { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .priority-medium { background: #fff7ed; color: #ea580c; border-color: #fed7aa; }
        .priority-low { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }

        /* MODAL & FORM */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal.show { display: flex; }
        .modal-content { background: white; width: 800px; max-width: 95%; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #1e293b; color: white; border-radius: 16px 16px 0 0;}
        .modal-title { font-size: 1.125rem; font-weight: 700; }
        .close-btn { background: rgba(255,255,255,0.1); width: 32px; height: 32px; border-radius: 50%; border: none; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; color: white; transition: 0.2s; }
        .close-btn:hover { background: #ef4444; }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .col-half { flex: 1; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.75rem; margin-bottom: 6px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;}
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.875rem; transition: 0.2s; background: #fff; font-weight: 500;}
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); }

        .select2-container .select2-selection--single { height: 42px; border: 1px solid var(--border-color); border-radius: 10px; padding: 6px; font-weight: 500;}

        .load-more-container { text-align: center; margin-top: 24px; margin-bottom: 40px; }
        .btn-load-more { background: white; border: 2px solid var(--primary); color: var(--primary); padding: 10px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-load-more:hover { background: var(--primary); color: white; }
    </style>
</head>

<body>

    <?php include '../sidebars.php'; ?>

    <main class="main-content" id="mainContent">
        <?php include '../header.php'; ?>

        <div class="page-header">
            <div class="page-title">
                <h3>Projects & Master Tasks</h3>
            </div>
            <div class="header-actions">
                <div style="position:relative;">
                    <input type="text" id="searchInput" placeholder="Search Projects"
                        style="padding: 10px 36px 10px 14px; border:1px solid var(--border-color); border-radius:10px; font-size:0.875rem; font-weight: 500; transition: 0.2s; outline:none;"
                        onkeyup="applyFilters()">
                    <i class="fa-solid fa-search"
                        style="position:absolute; right:12px; top:12px; color:#94a3b8; font-size:0.9rem;"></i>
                </div>

                <button class="btn btn-white btn-icon" id="btnGrid" onclick="switchView('grid')"><i
                        class="fa-solid fa-border-all"></i></button>
                <button class="btn btn-white btn-icon active" id="btnList" onclick="switchView('list')"><i
                        class="fa-solid fa-list"></i></button>

                <div style="position:relative;">
                    <button class="btn btn-white" onclick="toggleExportMenu()"><i class="fa-solid fa-file-export"></i>
                        Export <i class="fa-solid fa-chevron-down" style="font-size:0.7rem;"></i></button>
                    <div class="dropdown-menu" id="exportMenu">
                        <a href="#" class="dropdown-item" onclick="exportToPDF()"><i class="fa-solid fa-file-pdf"
                                style="color:#dc2626"></i> Export as PDF</a>
                        <a href="#" class="dropdown-item" onclick="exportToExcel()"><i class="fa-solid fa-file-excel"
                                style="color:#16a34a"></i> Export as Excel</a>
                    </div>
                </div>

                <button class="btn btn-primary" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Create Project</button>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-left">
                <h4 id="viewHeading">Project List</h4>
            </div>
            <div class="filter-right" style="display:flex; gap:12px;">
                <select class="form-select" id="statusFilter" onchange="applyFilters()" style="font-weight: 600;">
                    <option value="All">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <select class="form-select" id="sortFilter" onchange="applyFilters()" style="font-weight: 600;">
                    <option value="default">Sort By : Default</option>
                    <option value="asc">Sort By : Name (A-Z)</option>
                    <option value="desc">Sort By : Name (Z-A)</option>
                </select>
            </div>
        </div>

        <div class="projects-grid" id="projectsGridView" style="display: none;"></div>

        <div class="projects-list" id="projectsListView" style="display: block;">
            <table id="projectsTable">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox"></th>
                        <th>Project Name</th>
                        <th>Assigned To</th>
                        <th>Deadline & Priority</th>
                        <th>Overall Status</th>
                        <th style="text-align: right;">Actions / File</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <div class="load-more-container" id="loadMoreContainer">
            <button class="btn-load-more" onclick="loadMoreProjects()"><i class="fa-solid fa-spinner"></i> Load
                More</button>
        </div>

    </main>

    <div class="modal" id="addProjectModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalTitle">Create / Assign Project</h3>
                </div>
                <button type="button" class="close-btn" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body bg-slate-50/50">
                <form id="projectForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_project">
                    <input type="hidden" name="edit_id" id="editRowId" value="0">

                    <div class="form-group" style="display:flex; align-items:center; gap:20px; margin-bottom:25px; background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="width:64px; height:64px; background:#f8fafc; border-radius:12px; display:flex; align-items:center; justify-content:center; border:1px dashed #cbd5e1;">
                            <i class="fa-regular fa-image" style="color:#94a3b8; font-size:1.5rem;"></i>
                        </div>
                        <div>
                            <label class="form-label" style="margin-bottom:2px;">Upload Project Logo</label>
                            <span style="font-size:0.7rem; color:var(--text-muted); display:block; margin-bottom:8px; font-weight: 600;">Image should be below 4 MB</span>
                            <input type="file" name="project_logo" class="form-control" style="padding:6px;" accept="image/*">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select or Type Project Name <span style="color:red">*</span></label>
                        <select name="project_name" id="projectName" class="form-control select2-modal" style="width:100%" required>
                            <option value="">-- Select or Type Project --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Client Name</label>
                        <input type="text" name="client_name" id="clientName" class="form-control" placeholder="e.g. Global Tech Solutions">
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="startDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-half">
                            <label class="form-label">End Date (Deadline)</label>
                            <input type="date" name="end_date" id="endDate" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Priority</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="col-half">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Assign To (Project Manager/TL)</label>
                        <select name="leader_id" id="leaderId" class="form-control select2-modal" style="width:100%" required>
                            <option value="">-- Select Person In Charge --</option>
                            <?php foreach ($team_leads as $tl): ?>
                                <option value="<?= $tl['id'] ?>">
                                    <?= htmlspecialchars($tl['name']) ?> (<?= htmlspecialchars($tl['designation'] ?? 'Team Lead') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detailed Description / Instructions</label>
                        <textarea name="description" id="description" class="form-control" rows="4" placeholder="Provide full context for the Team Lead..."></textarea>
                    </div>

                    <div class="form-group" style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <label class="form-label">Project Requirement File (PDF, DOC, ZIP)</label>
                        <span style="font-size:0.7rem; color:var(--text-muted); display:block; margin-bottom:8px; font-weight: 500;">Attach any SRS documents or instructions for the team.</span>
                        <input type="file" name="project_file" class="form-control" style="padding:6px;" accept=".pdf,.doc,.docx,.zip,.rar">
                        <div id="currentFileLink" style="display:none; margin-top: 8px;"></div>
                    </div>

                    <div style="text-align:right; margin-top:30px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <button type="button" class="btn btn-white" onclick="closeModal()" style="display:inline-block; font-weight: 700;">Cancel</button>
                        <button type="submit" id="submitBtn" class="btn btn-primary" style="display:inline-block;">Save & Assign Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Init Data from PHP
        let projects = <?= json_encode($projects_data) ?>;
        let visibleCount = 8;
        let currentView = 'list'; // Set Default View to List

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

        // 🚀 SMART DROPDOWN INITIALIZATION & LOGIC 
        $(document).ready(function () {
            $('.select2-modal').select2({
                dropdownParent: $('#addProjectModal'),
                tags: true // Allows typing a new project name!
            });
            
            populateProjectDropdown();
            
            // Listen for project selection changes
            $('#projectName').on('change', function() {
                autoFillProjectDetails();
            });
        });

        function populateProjectDropdown() {
            const projectDropdown = $('#projectName');
            // Keep the first empty option, remove others
            projectDropdown.find('option:not(:first)').remove();
            
            projects.forEach(p => {
                projectDropdown.append(`<option value="${p.name}" data-id="${p.real_id}">${p.name}</option>`);
            });
        }

        function autoFillProjectDetails() {
            const selectEl = document.getElementById('projectName');
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            
            if (!selectedOption || !selectedOption.getAttribute('data-id')) {
                // It's a new, typed project name
                document.getElementById('editRowId').value = "0";
                document.getElementById('submitBtn').innerText = "Save & Assign Project";
                return;
            }

            const selectedId = selectedOption.getAttribute('data-id');
            const p = projects.find(x => x.real_id == selectedId);
            
            if (p) {
                document.getElementById('editRowId').value = p.real_id;
                document.getElementById('clientName').value = p.client_name || '';
                document.getElementById('startDate').value = p.start_date || '';
                document.getElementById('endDate').value = p.deadline || '';
                document.getElementById('priority').value = p.priority || 'Medium';
                document.getElementById('description').value = p.desc_raw || '';
                document.getElementById('status').value = p.status || 'Active';
                
                $('#leaderId').val(p.leader_id).trigger('change.select2');
                
                const fileLinkBox = document.getElementById('currentFileLink');
                if (p.req_file) {
                    fileLinkBox.style.display = 'block';
                    fileLinkBox.innerHTML = `<a href="${p.req_file}" target="_blank" style="color: #0284c7; font-weight: 700; text-decoration: none; font-size: 12px; padding: 4px 8px; background: #e0f2fe; border-radius: 6px;"><i class="fa-solid fa-paperclip"></i> View Current Requirements File</a>`;
                } else {
                    fileLinkBox.style.display = 'none';
                }

                document.getElementById('submitBtn').innerText = "Update & Assign Project";
            }
        }

        renderProjects();

        // --- RENDER FUNCTIONS ---
        function renderProjects() {
            let filtered = getFilteredProjects();
            gridView.innerHTML = '';
            tableBody.innerHTML = '';

            const toShow = filtered.slice(0, visibleCount);

            if (visibleCount >= filtered.length) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.style.display = 'block';
            }

            if (toShow.length === 0) {
                gridView.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px; color:#94a3b8; font-weight:600;"><i class="fa-regular fa-folder-open text-4xl mb-3 opacity-50 block"></i> No projects found matching your criteria.</div>';
            }

            toShow.forEach(p => {
                const statusBadgeClass = p.status.toLowerCase() === 'active' || p.status.toLowerCase() === 'in progress' || p.status.toLowerCase() === 'completed' ? 'active' : 'inactive';
                const priorityClass = `priority-${p.priority.toLowerCase()}`;

                // GRID CARD HTML
                const cardHtml = `
                <div class="project-card">
                    <div class="card-header">
                        <h5 class="project-title">${p.name}</h5>
                        <div style="position:relative;">
                            <i class="fa-solid fa-ellipsis-vertical action-icon" onclick="toggleCardMenu('${p.real_id}', event)"></i>
                            <div class="card-action-menu" id="menu-${p.real_id}">
                                <a href="#" class="card-action-item" onclick="editProjectById(${p.real_id}, event)"><i class="fa-regular fa-pen-to-square"></i> Edit Details</a>
                                <a href="#" class="card-action-item" onclick="deleteProject(${p.real_id}, event)"><i class="fa-regular fa-trash-can" style="color: #ef4444;"></i> Delete</a>
                            </div>
                        </div>
                    </div>
                    
                    ${p.req_file ? `
                    <a href="${p.req_file}" download target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.65rem; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 4px 8px; border-radius: 6px; text-decoration: none; margin-bottom: 12px; border: 1px solid #bae6fd; text-transform:uppercase;">
                        <i class="fa-solid fa-paperclip"></i> Project Requirements
                    </a>` : ''}

                    <p class="project-desc" style="${p.req_file ? 'margin-bottom: 8px;' : ''}">${p.desc}</p>

                    <div class="project-meta">
                        <img src="${p.leader_img}" class="leader-avatar">
                        <div class="leader-info">
                            <h5>${p.leader}</h5>
                            <span>Team Lead</span>
                        </div>
                        <div class="deadline-badge">
                            <span class="status-badge ${statusBadgeClass}" style="padding: 4px 8px; font-size: 0.65rem;">
                                ${p.status}
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        ${p.completed_file ? `
                        <a href="${p.completed_file}" download target="_blank" style="display: flex; justify-content: center; align-items: center; gap: 8px; background: #f0fdfa; color: #0d9488; padding: 10px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; text-decoration: none; border: 1px dashed #99f6e4; transition: 0.2s; margin-top: 4px;">
                            <i class="fa-solid fa-file-arrow-down text-lg"></i> Download Submitted File
                        </a>` : 
                        (p.status === 'Completed' ? `<div style="text-align: center; padding: 10px; font-size: 0.75rem; color: #94a3b8; font-weight: 600; font-style: italic; background: #f8fafc; border-radius: 10px; border: 1px dashed #e2e8f0; margin-top: 4px;">No File Uploaded by TL</div>` : '')}
                    </div>
                </div>`;
                gridView.innerHTML += cardHtml;

                // LIST ROW HTML 
                const rowHtml = `
                <tr>
                    <td><input type="checkbox"></td>
                    <td>
                        <div style="font-weight:700; color:var(--text-dark); margin-bottom:2px; font-size: 0.95rem;">${p.name}</div>
                        ${p.req_file ? `<a href="${p.req_file}" target="_blank" style="font-size:0.65rem; color:#0284c7; font-weight:700; text-transform:uppercase; text-decoration:none;"><i class="fa-solid fa-paperclip"></i> Requirements Attached</a>` : `<div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:0.5px;">${p.id}</div>`}
                    </td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <img src="${p.leader_img}" style="width:30px; height:30px; border-radius:8px; margin-right:10px; border: 1px solid #e2e8f0;">
                            <div style="line-height: 1.2;">
                                <span style="font-weight:700; display:block;">${p.leader}</span>
                                <span style="font-size: 0.65rem; color: var(--primary); font-weight: 800; text-transform: uppercase;">Team Lead</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">
                            <span class="priority-badge ${priorityClass}" style="padding:2px 8px; font-size:0.65rem;">
                                <span class="priority-dot"></span> ${p.priority.toUpperCase()}
                            </span>
                            <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted);"><i class="fa-regular fa-calendar mr-1"></i> ${p.deadline_display}</span>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge ${statusBadgeClass}">
                            <span class="status-dot"></span> ${p.status}
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <div style="display:flex; justify-content: flex-end; align-items: center; gap:8px;">
                            ${p.completed_file ? `
                            <a href="${p.completed_file}" download target="_blank" style="color: #0d9488; background: #f0fdfa; padding: 8px 12px; border-radius: 8px; text-decoration: none; font-size: 0.75rem; font-weight: 800; border: 1px solid #ccfbf1; display:flex; align-items:center; gap:6px; transition: 0.2s;" title="Download Final File">
                                <i class="fa-solid fa-download"></i> <span style="display:none;">File</span>
                            </a>` : ''}
                            <button type="button" style="background:white; border:1px solid #e2e8f0; color:#64748b; padding:8px 10px; border-radius:8px; cursor:pointer; transition: 0.2s;" title="Edit Project" onclick="editProjectById(${p.real_id}, event)"><i class="fa-regular fa-pen-to-square"></i></button>
                            <button type="button" onclick="deleteProject(${p.real_id}, event)" style="background:white; border:1px solid #fecaca; color:var(--danger); padding:8px 10px; border-radius:8px; cursor:pointer; transition: 0.2s;" title="Delete Project"><i class="fa-regular fa-trash-can"></i></button>
                        </div>
                    </td>
                </tr>`;
                tableBody.innerHTML += rowHtml;
            });
        }

        function getFilteredProjects() {
            let filtered = [...projects];
            const term = searchInput.value.toLowerCase();
            if (term) {
                filtered = filtered.filter(p => p.name.toLowerCase().includes(term) || p.leader.toLowerCase().includes(term));
            }

            const status = statusFilter.value;
            if (status !== 'All') {
                filtered = filtered.filter(p => p.status === status);
            }

            const sortVal = sortFilter.value;
            if (sortVal === 'asc') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            } else if (sortVal === 'desc') {
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

        // --- UI TOGGLES ---
        function toggleCardMenu(id, event) {
            event.stopPropagation();
            document.querySelectorAll('.card-action-menu').forEach(m => {
                if (m.id !== `menu-${id}`) m.classList.remove('active');
            });
            const menu = document.getElementById(`menu-${id}`);
            if (menu) menu.classList.toggle('active');
        }

        window.addEventListener('click', () => {
            document.querySelectorAll('.card-action-menu').forEach(m => m.classList.remove('active'));
            exportMenu.classList.remove('show');
        });

        function switchView(view) {
            currentView = view;
            if (view === 'grid') {
                document.getElementById('projectsGridView').style.display = 'grid';
                document.getElementById('projectsListView').style.display = 'none';
                document.getElementById('btnGrid').classList.add('active');
                document.getElementById('btnList').classList.remove('active');
                document.getElementById('viewHeading').innerText = 'Project Grid';
            } else {
                document.getElementById('projectsGridView').style.display = 'none';
                document.getElementById('projectsListView').style.display = 'block';
                document.getElementById('btnGrid').classList.remove('active');
                document.getElementById('btnList').classList.add('active');
                document.getElementById('viewHeading').innerText = 'Project List';
            }
        }

        function toggleExportMenu() {
            setTimeout(() => exportMenu.classList.toggle('show'), 10);
        }

        // --- CRUD ACTIONS ---
        function deleteProject(id, event) {
            if(event) event.preventDefault();
            if(confirm("Warning: Are you sure you want to permanently delete this project?")) {
                window.location.href = `manager_projects.php?delete_id=${id}`;
            }
        }

        function editProjectById(id, event) {
            if(event) event.preventDefault();
            const p = projects.find(x => x.real_id == id);
            if(p) {
                document.getElementById('editRowId').value = p.real_id;
                
                // 🚀 Sync smart dropdown with edit click
                if ($('#projectName').find("option[value='" + p.name + "']").length) {
                    $('#projectName').val(p.name).trigger('change');
                } else { 
                    var newOption = new Option(p.name, p.name, true, true);
                    $('#projectName').append(newOption).trigger('change');
                }

                document.getElementById('clientName').value = p.client_name;
                document.getElementById('startDate').value = p.start_date;
                document.getElementById('endDate').value = p.deadline;
                document.getElementById('priority').value = p.priority;
                document.getElementById('description').value = p.desc_raw;
                document.getElementById('status').value = p.status;
                
                $('#leaderId').val(p.leader_id).trigger('change');

                // Show Requirement file link if exists
                const fileLinkBox = document.getElementById('currentFileLink');
                if (p.req_file) {
                    fileLinkBox.style.display = 'block';
                    fileLinkBox.innerHTML = `<a href="${p.req_file}" target="_blank" style="color: #0284c7; font-weight: 700; text-decoration: none; font-size: 12px; padding: 4px 8px; background: #e0f2fe; border-radius: 6px;"><i class="fa-solid fa-paperclip"></i> View Current Requirements File</a>`;
                } else {
                    fileLinkBox.style.display = 'none';
                }

                document.getElementById('modalTitle').innerText = "Edit Project";
                document.getElementById('submitBtn').innerText = "Update Project";
                modal.classList.add('show');
            }
        }

        function openAddModal() { 
            document.getElementById('projectForm').reset();
            document.getElementById('editRowId').value = "0";
            $('#leaderId').val(null).trigger('change');
            $('#projectName').val(null).trigger('change'); // Clear smart dropdown
            document.getElementById('currentFileLink').style.display = 'none';
            document.getElementById('modalTitle').innerText = "Create / Assign Project";
            document.getElementById('submitBtn').innerText = "Save & Assign Project";
            modal.classList.add('show'); 
        }

        function closeModal() { 
            modal.classList.remove('show'); 
        }

        // --- EXPORT FUNCTIONALITY ---
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
    </script>
</body>
</html>