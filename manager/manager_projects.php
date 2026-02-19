<?php
// --- INCLUDE YOUR EXISTING CONNECTION ---
// Adjust the path if db_connect.php is in a different location
require_once '../include/db_connect.php';

// Check if connection variable exists from the included file
if (!isset($conn)) {
    die("Database connection failed. Variable \$conn not found in db_connect.php");
}

// --- HANDLE FORM SUBMISSIONS (ADD & DELETE) ---

// A. Add Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {

    // Sanitize inputs
    $name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $leader_id = intval($_POST['leader_id']);
    $client = mysqli_real_escape_string($conn, $_POST['client_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $value = floatval($_POST['project_value']);
    $price_type = mysqli_real_escape_string($conn, $_POST['price_type']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Get logged in user ID (Assuming stored in session, default to 1 if not)
    $created_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

    // Handle Image Upload
    $logo_path = null;
    if (!empty($_FILES['project_logo']['name'])) {
        $target_dir = "../uploads/projects/"; // Ensure this folder exists
        if (!file_exists($target_dir))
            mkdir($target_dir, 0777, true);

        $filename = time() . "_" . basename($_FILES['project_logo']['name']);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES['project_logo']['tmp_name'], $target_file)) {
            $logo_path = "uploads/projects/" . $filename; // Store relative path for DB
        }
    }

    // Insert into Projects Table using Prepared Statement
    $sql = "INSERT INTO projects (project_name, client_name, leader_id, start_date, deadline, priority, project_value, price_type, description, status, project_logo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssisssdssssi", $name, $client, $leader_id, $start_date, $end_date, $priority, $value, $price_type, $desc, $status, $logo_path, $created_by);

        if ($stmt->execute()) {
            $new_project_id = $stmt->insert_id;

            // Insert Team Members
            if (isset($_POST['team_members']) && is_array($_POST['team_members'])) {
                $stmt_member = $conn->prepare("INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
                foreach ($_POST['team_members'] as $user_id) {
                    $u_id = intval($user_id);
                    $stmt_member->bind_param("ii", $new_project_id, $u_id);
                    $stmt_member->execute();
                }
                $stmt_member->close();
            }
            // Redirect to avoid resubmission
            echo "<script>window.location.href='manager_projects.php?msg=added';</script>";
            exit();
        } else {
            $error = "Error executing query: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Error preparing query: " . $conn->error;
    }
}

// B. Delete Project
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    // Simple delete query
    $conn->query("DELETE FROM projects WHERE id = $id");
    echo "<script>window.location.href='manager_projects.php?msg=deleted';</script>";
    exit();
}

// --- FETCH DATA FOR VIEW ---

// Fetch Users for Dropdowns (Leader & Team) - Excluding System Admin if needed
$users_result = $conn->query("SELECT id, name, username, role FROM users WHERE role != 'System Admin' ORDER BY name ASC");
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch Projects with Details
$sql = "SELECT p.*, 
        u.name as leader_name, 
        u.employee_id as leader_emp_id
        FROM projects p 
        LEFT JOIN users u ON p.leader_id = u.id 
        ORDER BY p.id DESC";

$result = $conn->query($sql);
$projects_data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Fetch Team Members for this project
        $pid = $row['id'];
        $team_sql = "SELECT u.name FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = $pid";
        $team_res = $conn->query($team_sql);
        $team_avatars = [];

        if ($team_res) {
            while ($member = $team_res->fetch_assoc()) {
                // Generate Avatar based on name
                $name_enc = urlencode($member['name'] ?? 'User');
                $team_avatars[] = "https://ui-avatars.com/api/?name=$name_enc&background=random&color=fff&size=32";
            }
        }

        // Leader Avatar
        $leader_name = !empty($row['leader_name']) ? $row['leader_name'] : "Unknown";
        $leader_img = "https://ui-avatars.com/api/?name=" . urlencode($leader_name) . "&background=0d9488&color=fff";

        // Format Date
        $deadline_display = ($row['deadline'] && $row['deadline'] != '0000-00-00') ? date("d M Y", strtotime($row['deadline'])) : 'No Deadline';

        // Build Array for JS
        $projects_data[] = [
            'id' => 'PRO-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'real_id' => $row['id'],
            'name' => htmlspecialchars($row['project_name']),
            'desc' => htmlspecialchars(substr(strip_tags($row['description'] ?? ''), 0, 100)) . '...',
            'leader' => htmlspecialchars($leader_name),
            'leader_img' => $leader_img,
            'team' => $team_avatars,
            'deadline' => $row['deadline'],
            'deadline_display' => $deadline_display,
            'priority' => $row['priority'],
            'tasks_done' => $row['completed_tasks'] ?? 0,
            'tasks_total' => ($row['total_tasks'] ?? 0) == 0 ? 10 : $row['total_tasks'],
            'status' => $row['status']
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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --primary-light: #ccfbf1;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f1f5f9;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            display: flex;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            flex: 1;
            padding: 24px;

            /* FIX: Add margin to push content right. Match this to your sidebar's width. 
       Based on your image, your sidebar looks like a "mini" sidebar (~80px - 100px). */
            margin-left: 100px;

            /* FIX: Adjust width so it doesn't cause horizontal scrollbar */
            width: calc(100% - 100px);

            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }

            /* Fix grid for mobile to show 1 card per row */
            .projects-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* HEADER & UTILS */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-title h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.025em;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

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
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(20, 77, 77, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-white {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        .btn-white:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            justify-content: center;
            padding: 0;
            border-radius: 10px;
        }

        .btn-icon.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

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

        .dropdown-menu.show {
            display: block;
        }

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

        .dropdown-item:hover {
            background: #f1f5f9;
        }

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

        .filter-row h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-select {
            padding: 10px 36px 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-muted);
            outline: none;
            cursor: pointer;
            font-size: 0.875rem;
            background-color: #fff;
            height: 42px;
        }

        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(20, 77, 77, 0.1);
        }

        /* VIEWS */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            width: 100%;
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
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            position: relative;
        }

        .project-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .project-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 20px;
            height: 36px;
            overflow: hidden;
        }

        .project-meta {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 10px;
            border-radius: 10px;
        }

        .leader-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            margin-right: 12px;
            border: 2px solid white;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .leader-info h5 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .leader-info span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .deadline-badge {
            margin-left: auto;
            text-align: right;
        }

        .deadline-badge span {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .deadline-badge strong {
            font-size: 0.8rem;
            color: var(--text-dark);
        }

        .card-footer {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .task-count {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .team-avatars {
            display: flex;
        }

        .team-avatars img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-left: -10px;
        }

        .team-avatars img:first-child {
            margin-left: 0;
        }

        .team-plus {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            margin-left: -10px;
            font-weight: 600;
        }

        /* ACTION DROPDOWN IN CARD */
        .action-icon {
            cursor: pointer;
            padding: 8px;
            color: #94a3b8;
            border-radius: 6px;
            transition: 0.2s;
        }

        .action-icon:hover {
            background: #f1f5f9;
            color: var(--text-dark);
        }

        .card-action-menu {
            position: absolute;
            right: 0;
            top: 35px;
            background: white;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            z-index: 10;
            display: none;
            width: 130px;
            padding: 6px;
        }

        .card-action-menu.active {
            display: block;
        }

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

        .card-action-item:hover {
            background: #f1f5f9;
        }

        /* TABLE STYLES */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 14px 20px;
            background: #f8fafc;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 14px 20px;
            vertical-align: middle;
            font-size: 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-dark);
        }

        tr:hover td {
            background: #f8fafc;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-badge.active .status-dot {
            background: #16a34a;
        }

        .status-badge.inactive .status-dot {
            background: #dc2626;
        }

        .priority-badge {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            align-items: center;
            gap: 6px;
        }

        .priority-high {
            background: #fee2e2;
            color: #dc2626;
        }

        .priority-medium {
            background: #fef3c7;
            color: #d97706;
        }

        .priority-low {
            background: #dbeafe;
            color: #2563eb;
        }

        .priority-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .priority-high .priority-dot {
            background: #dc2626;
        }

        .priority-medium .priority-dot {
            background: #d97706;
        }

        .priority-low .priority-dot {
            background: #2563eb;
        }

        /* MODAL & FORM */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.4);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 800px;
            max-width: 95%;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
            transition: 0.2s;
        }

        .close-btn:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .col-half {
            flex: 1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.875rem;
            transition: 0.2s;
            background: #fff;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1);
        }

        /* Select2 Overrides */
        .select2-container .select2-selection--single,
        .select2-container .select2-selection--multiple {
            height: 42px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 6px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .load-more-container {
            text-align: center;
            margin-top: 24px;
            margin-bottom: 40px;
        }

        .btn-load-more {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-load-more:hover {
            background: var(--primary);
            color: white;
        }
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
                    <input type="text" id="searchInput" placeholder="Search Projects"
                        style="padding: 10px 36px 10px 14px; border:1px solid var(--border-color); border-radius:10px; font-size:0.875rem; transition: 0.2s; outline:none;"
                        onkeyup="applyFilters()">
                    <i class="fa-solid fa-search"
                        style="position:absolute; right:12px; top:12px; color:#94a3b8; font-size:0.9rem;"></i>
                </div>

                <button class="btn btn-white btn-icon active" id="btnGrid" onclick="switchView('grid')"><i
                        class="fa-solid fa-border-all"></i></button>
                <button class="btn btn-white btn-icon" id="btnList" onclick="switchView('list')"><i
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

                <button class="btn btn-primary" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add
                    Project</button>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-left">
                <h4 id="viewHeading">Projects Grid</h4>
            </div>
            <div class="filter-right" style="display:flex; gap:12px;">
                <select class="form-select" id="statusFilter" onchange="applyFilters()">
                    <option value="All">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <select class="form-select" id="sortFilter" onchange="applyFilters()">
                    <option value="default">Sort By : Default</option>
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
            <button class="btn-load-more" onclick="loadMoreProjects()"><i class="fa-solid fa-spinner"></i> Load
                More</button>
        </div>

    </main>

    <div class="modal" id="addProjectModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Add Project</h3>
                </div>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form id="projectForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_project">

                    <div class="form-group" style="display:flex; align-items:center; gap:20px; margin-bottom:25px;">
                        <div
                            style="width:64px; height:64px; background:#f8fafc; border-radius:12px; display:flex; align-items:center; justify-content:center; border:1px dashed #cbd5e1;">
                            <i class="fa-regular fa-image" style="color:#94a3b8; font-size:1.5rem;"></i>
                        </div>
                        <div>
                            <label class="form-label" style="margin-bottom:2px;">Upload Project Logo</label>
                            <span
                                style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:5px;">Image
                                should be below 4 mb</span>
                            <input type="file" name="project_logo" class="form-control" style="padding:6px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Project Name <span style="color:red">*</span></label>
                        <input type="text" name="project_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Client</label>
                        <input type="text" name="client_name" class="form-control" placeholder="e.g. Global Tech">
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-half">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-control">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="col-half">
                            <div style="display:flex; gap:10px;">
                                <div style="flex:1;">
                                    <label class="form-label">Value ($)</label>
                                    <input type="number" name="project_value" class="form-control" value="0">
                                </div>
                                <div style="flex:1;">
                                    <label class="form-label">Type</label>
                                    <select name="price_type" class="form-control">
                                        <option value="Hourly">Hourly</option>
                                        <option value="Fixed" selected>Fixed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-half">
                            <label class="form-label">Project Leader</label>
                            <select name="leader_id" class="form-control select2-modal" style="width:100%" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['name'] ?: $user['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-half">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Members</label>
                        <select name="team_members[]" class="form-control select2-modal" multiple="multiple"
                            style="width:100%">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['name'] ?: $user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>

                    <div style="text-align:right; margin-top:20px;">
                        <button type="button" class="btn btn-white" onclick="closeModal()"
                            style="display:inline-block;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="display:inline-block;">Save
                            Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Init Data from PHP
        let projects = <?= json_encode($projects_data) ?>;
        let visibleCount = 8;
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

        // Initialize Select2 inside Modal
        $(document).ready(function () {
            $('.select2-modal').select2({
                dropdownParent: $('#addProjectModal')
            });
        });

        // Initial Render
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
                gridView.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:#64748b;">No projects found</div>';
            }

            toShow.forEach(p => {
                const statusBadgeClass = p.status === 'Active' ? 'active' : 'inactive';

                // Grid Card HTML
                const cardHtml = `
                <div class="project-card">
                    <div class="card-header">
                        <h5 class="project-title">${p.name}</h5>
                        <div style="position:relative;">
                            <i class="fa-solid fa-ellipsis-vertical action-icon" onclick="toggleCardMenu('${p.real_id}', event)"></i>
                            <div class="card-action-menu" id="menu-${p.real_id}">
                                <a href="#" class="card-action-item"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
                                <a href="?delete_id=${p.real_id}" class="card-action-item" onclick="return confirm('Delete this project?')"><i class="fa-regular fa-trash-can"></i> Delete</a>
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
                            ${p.team.slice(0, 4).map(img => `<img src="${img}">`).join('')}
                            ${p.team.length > 4 ? `<div class="team-plus">+${p.team.length - 4}</div>` : ''}
                        </div>
                    </div>
                </div>`;
                gridView.innerHTML += cardHtml;

                // List Row HTML
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
                             ${p.team.slice(0, 3).map(img => `<img src="${img}">`).join('')}
                             ${p.team.length > 3 ? `<div class="team-plus">+${p.team.length - 3}</div>` : ''}
                        </div>
                    </td>
                    <td>${p.deadline_display}</td>
                    <td>
                        <div class="priority-badge ${priorityClass}">
                            <span class="priority-dot"></span> ${p.priority}
                        </div>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass == 'active' ? 'active' : 'inactive'}">
                            <span class="status-dot"></span> ${p.status}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; font-size:1rem;">
                            <i class="fa-regular fa-pen-to-square" style="color:var(--text-muted); cursor:pointer;"></i>
                            <a href="?delete_id=${p.real_id}" onclick="return confirm('Delete this project?')"><i class="fa-regular fa-trash-can" style="color:var(--danger); cursor:pointer;"></i></a>
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
                document.getElementById('viewHeading').innerText = 'Projects Grid';
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

        // --- MODAL LOGIC ---
        function openModal() { modal.classList.add('show'); }
        function closeModal() { modal.classList.remove('show'); }

        // --- EXPORT FUNCTIONALITY (Client Side) ---
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