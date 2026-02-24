<?php 
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION (Smart Path Resolver)
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Cannot find database connection file.");
}

// Determine Assets Directory Base Path
$assetsBase = 'assets/profiles/';
if (!is_dir(__DIR__ . '/' . $assetsBase)) {
    $assetsBase = '../assets/profiles/';
}

// =========================================================================
// 3. SILENT SCHEMA UPDATE (Fixes the "Unknown column" error automatically)
// =========================================================================
$conn->query("ALTER TABLE clients 
    ADD COLUMN IF NOT EXISTS client_id VARCHAR(20) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS email VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS company VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('Active','Inactive') DEFAULT 'Active',
    ADD COLUMN IF NOT EXISTS address TEXT NULL,
    ADD COLUMN IF NOT EXISTS profile_img VARCHAR(255) NULL
");

// =========================================================================
// 4. PROCESS AJAX REQUESTS (ADD, EDIT, DELETE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- ADD CLIENT ---
    if ($_POST['action'] === 'add_client') {
        $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
        $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $company = mysqli_real_escape_string($conn, $_POST['company']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $project_name = mysqli_real_escape_string($conn, $_POST['project_name'] ?? '');

        // Generate Client ID
        $res = $conn->query("SELECT MAX(id) as max_id FROM clients");
        $row = $res->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;
        $client_id = "CLI-" . str_pad($next_id, 3, "0", STR_PAD_LEFT);

        // Handle Profile Image Upload
        $profile_img = null;
        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/' . $assetsBase;
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $ext = strtolower(pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $new_filename = "client_" . time() . "_" . rand(1000,9999) . "." . $ext;
                if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $upload_dir . $new_filename)) {
                    $profile_img = $new_filename;
                }
            }
        }

        $client_name_combined = trim($fname . ' ' . $lname);
        $stmt = $conn->prepare("INSERT INTO clients (client_id, first_name, last_name, client_name, email, phone, company, status, address, profile_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $client_id, $fname, $lname, $client_name_combined, $email, $phone, $company, $status, $address, $profile_img);
        
        if ($stmt->execute()) {
            if(!empty($project_name)) {
                $leader_id = $_SESSION['user_id']; 
                $proj_stmt = $conn->prepare("INSERT INTO projects (project_name, client_name, leader_id, status, priority, start_date) VALUES (?, ?, ?, 'Active', 'Medium', CURDATE())");
                $proj_stmt->bind_param("ssi", $project_name, $company, $leader_id);
                $proj_stmt->execute();
                $proj_stmt->close();
            }
            echo "success";
        } else { echo "error"; }
        
        $stmt->close();
        exit();
    }

    // --- EDIT CLIENT ---
    if ($_POST['action'] === 'edit_client') {
        $id = (int)$_POST['id'];
        $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
        $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $company = mysqli_real_escape_string($conn, $_POST['company']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $client_name_combined = trim($fname . ' ' . $lname);

        $sql = "UPDATE clients SET first_name=?, last_name=?, client_name=?, email=?, phone=?, company=?, status=?, address=?";
        $params = [$fname, $lname, $client_name_combined, $email, $phone, $company, $status, $address];
        $types = "ssssssss";

        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/' . $assetsBase;
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $ext = strtolower(pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $new_filename = "client_" . time() . "_" . rand(1000,9999) . "." . $ext;
                if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $upload_dir . $new_filename)) {
                    $sql .= ", profile_img=?";
                    $params[] = $new_filename;
                    $types .= "s";
                }
            }
        }

        $sql .= " WHERE id=?";
        $params[] = $id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) { echo "success"; } else { echo "error"; }
        
        $stmt->close();
        exit();
    }

    // --- DELETE CLIENT ---
    if ($_POST['action'] === 'delete_client') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) { echo "success"; } else { echo "error"; }
        $stmt->close();
        exit();
    }
}

// =========================================================================
// 5. FETCH DATA FOR DASHBOARD
// =========================================================================

// A. Stats
$total_clients = $active_clients = $inactive_clients = 0;
$stats_query = "SELECT 
                  COUNT(*) as total, 
                  SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                  SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive
                FROM clients";
$stats_res = $conn->query($stats_query);
if ($stats_res) {
    $stats_data = $stats_res->fetch_assoc();
    $total_clients = $stats_data['total'] ?? 0;
    $active_clients = $stats_data['active'] ?? 0;
    $inactive_clients = $stats_data['inactive'] ?? 0;
}

// B. Fetch All Clients with Smart Fallbacks for older DB rows
$clients = [];
$client_query = "SELECT * FROM clients ORDER BY id DESC";
$client_result = $conn->query($client_query);

if ($client_result) {
    while($row = $client_result->fetch_assoc()) {
        
        // Smart Data Fallbacks for old database entries
        $fname = $row['first_name'] ?? '';
        $lname = $row['last_name'] ?? '';
        
        if (empty($fname) && empty($lname) && !empty($row['client_name'])) {
            $parts = explode(' ', $row['client_name'], 2);
            $fname = $parts[0] ?? '';
            $lname = $parts[1] ?? '';
        }

        $row['first_name'] = $fname;
        $row['last_name'] = $lname;
        $row['display_name'] = trim($fname . ' ' . $lname) ?: 'Unknown Client';
        $row['company'] = !empty($row['company']) ? $row['company'] : ($row['client_name'] ?? 'N/A');
        $row['phone'] = !empty($row['phone']) ? $row['phone'] : ($row['mobile_number'] ?? 'N/A');
        $row['email'] = !empty($row['email']) ? $row['email'] : 'N/A';
        $row['client_id'] = !empty($row['client_id']) ? $row['client_id'] : 'CLI-'.str_pad($row['id'], 3, '0', STR_PAD_LEFT);

        // Smart Image Resolver
        $imgSource = $row['profile_img'] ?? null;
        if(empty($imgSource) || $imgSource === 'default_user.png') {
            $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
        } elseif (!str_starts_with($imgSource, 'http')) {
            if (file_exists(__DIR__ . '/' . $assetsBase . $imgSource)) {
                $imgSource = $assetsBase . $imgSource;
            } else {
                $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            }
        }
        $row['avatar'] = $imgSource;

        // Fetch associated project
        $proj_name = "No Active Project";
        $proj_progress = 0;
        if(!empty($row['company']) && $row['company'] !== 'N/A') {
            $pq = $conn->prepare("SELECT project_name, progress FROM projects WHERE client_name = ? LIMIT 1");
            $pq->bind_param("s", $row['company']);
            $pq->execute();
            $pres = $pq->get_result();
            if($prow = $pres->fetch_assoc()) {
                $proj_name = $prow['project_name'];
                $proj_progress = $prow['progress'];
            }
            $pq->close();
        }
        $row['project_name'] = $proj_name;
        $row['project_progress'] = $proj_progress;

        $clients[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR - Clients Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Primary Theme Color: Dark Teal #1a534f */
        .bg-custom-teal { background-color: #1a534f; }
        .text-custom-teal { color: #1a534f; }
        .border-custom-teal { border-color: #1a534f; }
        .hover-teal:hover { background-color: #14403d; }
        
        .dropdown-shadow { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .avatar-ring { border: 2px solid #1a534f; padding: 2px; }
        
        .status-badge-active { 
            background-color: #00c48c; color: white; padding: 4px 10px; border-radius: 6px; 
            font-size: 11px; font-weight: 700; display: inline-flex; align-items: center;
        }
        .status-badge-inactive { 
            background-color: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; 
            font-size: 11px; font-weight: 700; display: inline-flex; align-items: center;
        }
        .dot { height: 6px; width: 6px; background-color: white; border-radius: 50%; display: inline-block; margin-right: 6px; }

        .page-container { display: flex; min-height: 100vh; }
        
        /* Main Content Adjustments */
        .main-content {
            flex-grow: 1;
            margin-left: 95px; 
            width: calc(100% - 95px);
            padding: 2rem;
            background-color: #f7f7f7;
            overflow-x: hidden;
            transition: all 0.3s ease;
        }

        .client-table th {
            text-align: left; padding: 14px 16px; font-size: 13px; font-weight: 700;
            color: #64748b; background-color: #f8f9fa; border-bottom: 1px solid #eee; text-transform: uppercase;
        }
        .client-table td {
            padding: 14px 16px; font-size: 14px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; color: #334155;
        }

        /* Modal */
        .modal-overlay { background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }

        .form-input { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; transition: border-color 0.2s; font-size: 14px;}
        .form-input:focus { border-color: #1a534f; box-shadow: 0 0 0 3px rgba(26, 83, 79, 0.1); }
        .required-star { color: #ef4444; margin-left: 2px; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; width: 100%; padding: 1rem; }
        }
    </style>
</head>
<body class="text-[#333d5e] font-sans">

    <div class="page-container">
        
        <?php include $sidebarPath; ?>

        <main class="main-content" id="mainContent">
            <?php include $headerPath; ?> 

            <div class="max-w-[1600px] mx-auto mt-6">
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Clients Ledger</h1>
                        <nav class="text-sm text-gray-400 flex items-center space-x-2 mt-1 font-medium">
                            <i class="fa fa-home"></i> <span>/ Projects / <span id="breadcrumb" class="text-gray-600">Client Grid</span></span>
                        </nav>
                    </div>
                    
                    <div class="flex items-center space-x-3 w-full md:w-auto">
                        <div class="flex bg-white border border-gray-200 rounded-lg p-1 shadow-sm">
                            <button id="listBtn" onclick="switchView('list')" class="px-3 py-2 text-gray-400 hover:text-gray-600 transition rounded">
                                <i class="fa-solid fa-list-ul"></i>
                            </button>
                            <button id="gridBtn" onclick="switchView('grid')" class="px-3 py-2 bg-custom-teal text-white rounded shadow-sm transition">
                                <i class="fa-solid fa-table-cells-large"></i>
                            </button>
                        </div>

                        <div class="relative hidden sm:block">
                            <button onclick="toggleExport(event)" class="px-4 py-2.5 bg-white border border-gray-200 rounded-lg flex items-center space-x-2 font-semibold text-gray-700 hover:bg-gray-50 transition shadow-sm">
                                <i class="fa-solid fa-file-export text-gray-400"></i> <span>Export</span> <i class="fa fa-chevron-down text-[10px] ml-1"></i>
                            </button>
                            <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl dropdown-shadow border py-2 z-30 text-sm font-medium">
                                <button class="w-full text-left px-4 py-2 hover:bg-gray-50"><i class="fa-solid fa-file-pdf text-red-500 mr-2"></i> PDF</button>
                                <button class="w-full text-left px-4 py-2 hover:bg-gray-50"><i class="fa-solid fa-file-excel text-green-600 mr-2"></i> Excel</button>
                            </div>
                        </div>

                        <button onclick="openModal('add')" class="flex-1 md:flex-none justify-center px-5 py-2.5 bg-custom-teal text-white rounded-lg flex items-center space-x-2 font-bold shadow-md hover-teal transition">
                            <i class="fa fa-plus-circle"></i> <span>Add Client</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center text-custom-teal">
                                <i class="fa-solid fa-user-group text-xl"></i>
                            </div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Total Clients</p><h3 class="text-2xl font-black mt-1"><?php echo $total_clients; ?></h3></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-500"><i class="fa-solid fa-user-check text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Active Clients</p><h3 class="text-2xl font-black mt-1"><?php echo $active_clients; ?></h3></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center text-red-500"><i class="fa-solid fa-user-slash text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Inactive Clients</p><h3 class="text-2xl font-black mt-1"><?php echo $inactive_clients; ?></h3></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500"><i class="fa-solid fa-user-plus text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">New This Month</p><h3 class="text-2xl font-black mt-1">0</h3></div>
                        </div>
                    </div>
                </div>

                <div id="gridView">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-6">
                        <div class="p-6 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                            <h2 class="text-lg font-bold text-gray-800"><i class="fa-solid fa-address-card text-custom-teal mr-2"></i> Client Catalog</h2>
                            <div class="flex items-center space-x-4 w-full md:w-auto">
                                <div class="relative w-full md:w-72">
                                    <input type="text" id="gridSearch" onkeyup="filterClients()" placeholder="Search by name or company..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-custom-teal bg-gray-50 focus:bg-white transition">
                                    <i class="fa fa-search absolute left-4 top-2.5 text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" id="clientGridContainer">
                        <?php if(empty($clients)): ?>
                            <div class="col-span-full text-center py-16 text-gray-400 bg-white rounded-xl border border-dashed border-gray-300">
                                <i class="fa-solid fa-users-slash text-5xl mb-4 text-gray-300"></i>
                                <p class="font-medium text-lg text-gray-500">No clients registered yet.</p>
                                <button onclick="openModal('add')" class="mt-4 px-4 py-2 bg-custom-teal text-white rounded-lg font-semibold"><i class="fa-solid fa-plus mr-2"></i>Add First Client</button>
                            </div>
                        <?php else: foreach($clients as $c): 
                            $statusDot = ($c['status'] == 'Active') ? 'bg-emerald-500' : 'bg-red-500';
                            $progressColor = ($c['project_progress'] == 100) ? 'bg-emerald-500' : 'bg-custom-teal';
                            $clientData = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="client-card bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm hover:shadow-lg transition">
                            
                            <div class="absolute top-4 right-4 flex gap-2">
                                <button onclick="viewClient(<?php echo $clientData; ?>)" class="text-gray-300 hover:text-blue-500 transition"><i class="fa-regular fa-eye"></i></button>
                                <button onclick="openModal('edit', <?php echo $clientData; ?>)" class="text-gray-300 hover:text-custom-teal transition"><i class="fa-regular fa-pen-to-square"></i></button>
                                <button onclick="deleteClient(<?php echo $c['id']; ?>)" class="text-gray-300 hover:text-red-500 transition"><i class="fa-regular fa-trash-can"></i></button>
                            </div>

                            <div class="relative w-20 h-20 mx-auto mb-4 mt-2">
                                <img src="<?php echo $c['avatar']; ?>" class="w-full h-full rounded-full avatar-ring object-cover shadow-sm">
                                <div class="absolute bottom-0 right-1 w-4 h-4 <?php echo $statusDot; ?> border-2 border-white rounded-full shadow"></div>
                            </div>
                            
                            <h3 class="font-bold text-lg text-gray-800 client-name"><?php echo htmlspecialchars($c['display_name']); ?></h3>
                            <span class="inline-block mt-1 text-[10px] uppercase font-bold px-2.5 py-1 rounded bg-slate-100 text-slate-600 client-company border border-slate-200"><?php echo htmlspecialchars($c['company']); ?></span>
                            
                            <div class="bg-slate-50 rounded-lg p-3 mt-5 mb-4 text-left border border-slate-100">
                                <p class="text-[11px] text-gray-500 font-bold uppercase mb-2 truncate"><i class="fa-solid fa-layer-group text-custom-teal mr-1"></i> <?php echo htmlspecialchars($c['project_name']); ?></p>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mb-1.5">
                                    <div class="<?php echo $progressColor; ?> h-1.5 rounded-full" style="width: <?php echo $c['project_progress']; ?>%"></div>
                                </div>
                                <div class="flex justify-between items-center text-[10px] font-bold text-gray-400 uppercase">
                                    <span>Progress</span>
                                    <span class="text-gray-600"><?php echo $c['project_progress']; ?>%</span>
                                </div>
                            </div>
                            
                            <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-center">
                                <div>
                                    <p class="text-[9px] text-gray-400 uppercase font-bold tracking-wider">Client ID</p>
                                    <p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($c['client_id']); ?></p>
                                </div>
                                <div class="flex space-x-2 text-gray-400">
                                    <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>" title="Email"><i class="fa-solid fa-envelope border border-gray-200 p-2 rounded-lg cursor-pointer hover:text-custom-teal hover:bg-teal-50 transition"></i></a>
                                    <a href="tel:<?php echo htmlspecialchars($c['phone']); ?>" title="Call"><i class="fa-solid fa-phone border border-gray-200 p-2 rounded-lg cursor-pointer hover:text-custom-teal hover:bg-teal-50 transition"></i></a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div id="listView" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-100 bg-gray-50">
                        <div class="flex flex-col md:flex-row justify-between items-center">
                            <h2 class="text-lg font-bold text-gray-800"><i class="fa-solid fa-list text-custom-teal mr-2"></i> Client List</h2>
                            <div class="relative w-full md:w-72 mt-4 md:mt-0">
                                <input type="text" id="listSearch" onkeyup="filterClients()" placeholder="Search Clients..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-custom-teal">
                                <i class="fa fa-search absolute left-4 top-2.5 text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full client-table">
                            <thead>
                                <tr>
                                    <th>Client ID</th>
                                    <th>Client Profile</th>
                                    <th>Company</th>
                                    <th>Contact Info</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="clientListContainer">
                                <?php if(empty($clients)): ?>
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400">No clients found.</td></tr>
                                <?php else: foreach($clients as $c): 
                                    $badge = ($c['status'] == 'Active') ? 'status-badge-active' : 'status-badge-inactive';
                                    $clientData = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-gray-50 transition client-row">
                                    <td class="font-bold text-gray-700"><?php echo htmlspecialchars($c['client_id']); ?></td>
                                    <td>
                                        <div class="flex items-center space-x-3">
                                            <img src="<?php echo $c['avatar']; ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-gray-200">
                                            <div>
                                                <div class="font-bold text-gray-800 list-name"><?php echo htmlspecialchars($c['display_name']); ?></div>
                                                <div class="text-[10px] text-gray-400 uppercase font-semibold mt-0.5"><i class="fa-regular fa-building mr-1"></i> <span class="list-company"><?php echo htmlspecialchars($c['company']); ?></span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-gray-700 font-medium"><?php echo htmlspecialchars($c['company']); ?></td>
                                    <td>
                                        <div class="text-sm text-gray-600"><i class="fa-regular fa-envelope w-4 text-gray-400"></i> <?php echo htmlspecialchars($c['email']); ?></div>
                                        <div class="text-sm text-gray-600 mt-1"><i class="fa-solid fa-phone w-4 text-gray-400"></i> <?php echo htmlspecialchars($c['phone']); ?></div>
                                    </td>
                                    <td><span class="<?php echo $badge; ?> shadow-sm"><span class="dot"></span><?php echo htmlspecialchars($c['status']); ?></span></td>
                                    <td>
                                        <div class="flex items-center justify-end space-x-2 text-gray-400">
                                            <button onclick="viewClient(<?php echo $clientData; ?>)" class="hover:text-blue-500 hover:bg-blue-50 bg-white border border-gray-200 p-2 rounded-lg shadow-sm transition" title="View"><i class="fa-regular fa-eye"></i></button>
                                            <button onclick="openModal('edit', <?php echo $clientData; ?>)" class="hover:text-custom-teal hover:bg-teal-50 bg-white border border-gray-200 p-2 rounded-lg shadow-sm transition" title="Edit"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button onclick="deleteClient(<?php echo $c['id']; ?>)" class="hover:text-red-500 hover:bg-red-50 bg-white border border-gray-200 p-2 rounded-lg shadow-sm transition" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div id="clientModal" class="hidden fixed inset-0 z-[100] modal-overlay flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 bg-slate-50">
                <h2 id="modalTitle" class="text-xl font-bold text-gray-800"><i class="fa-solid fa-user-plus text-custom-teal mr-2"></i> Add New Client</h2>
                <button onclick="toggleModal(false)" class="text-gray-400 hover:text-red-500 transition focus:outline-none">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>

            <form id="clientForm" onsubmit="submitClientForm(event)" enctype="multipart/form-data" class="flex flex-col overflow-hidden">
                <input type="hidden" name="action" id="formAction" value="add_client">
                <input type="hidden" name="id" id="client_db_id" value="">
                
                <div class="p-6 md:p-8 overflow-y-auto custom-scroll flex-grow bg-white">
                    <div class="bg-slate-50 rounded-xl p-6 mb-8 border border-dashed border-gray-300 flex items-center space-x-6">
                        <img id="imgPreview" src="https://ui-avatars.com/api/?name=New+Client&background=f1f5f9&color=94a3b8" class="w-24 h-24 rounded-full border-4 border-white shadow-sm object-cover">
                        <div>
                            <h4 class="font-bold text-gray-800 text-lg">Profile Image</h4>
                            <p class="text-xs text-gray-500 mt-0.5 mb-4 font-medium">Recommended: Square JPG/PNG, Max 2MB</p>
                            <label for="profileUpload" class="px-5 py-2.5 cursor-pointer bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg shadow-sm hover:bg-gray-50 transition">
                                <i class="fa-solid fa-camera mr-2 text-custom-teal"></i> Browse Photo
                            </label>
                            <input type="file" id="profileUpload" name="profile_img" accept="image/*" class="hidden" onchange="previewImage(event)">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">First Name <span class="required-star">*</span></label>
                            <input type="text" name="first_name" id="in_fname" required class="form-input bg-gray-50" placeholder="e.g. John">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Last Name</label>
                            <input type="text" name="last_name" id="in_lname" class="form-input bg-gray-50" placeholder="e.g. Doe">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Email Address <span class="required-star">*</span></label>
                            <input type="email" name="email" id="in_email" required class="form-input bg-gray-50" placeholder="client@company.com">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Phone Number <span class="required-star">*</span></label>
                            <input type="text" name="phone" id="in_phone" required class="form-input bg-gray-50" placeholder="+1 (555) 000-0000">
                        </div>
                        
                        <div class="col-span-full border-t border-gray-100 mt-2 pt-6">
                            <h4 class="text-sm font-bold text-custom-teal mb-4 uppercase tracking-wider"><i class="fa-solid fa-briefcase mr-2"></i> Business Information</h4>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Company Name <span class="required-star">*</span></label>
                            <input type="text" name="company" id="in_company" required class="form-input bg-gray-50" placeholder="Enter Company Name">
                        </div>
                        
                        <div id="projectInputDiv">
                            <label class="block text-xs font-bold text-teal-700 uppercase tracking-wider mb-2">Assign Initial Project</label>
                            <input type="text" name="project_name" id="in_project" class="form-input border-teal-200 bg-teal-50/50" placeholder="e.g. Mobile App Redesign">
                            <p class="text-[10px] text-gray-400 mt-1.5 font-medium"><i class="fa-solid fa-bolt text-amber-500 mr-1"></i> Auto-creates a new project for this client.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Account Status</label>
                            <select name="status" id="in_status" class="form-input bg-gray-50 font-semibold text-gray-700 cursor-pointer">
                                <option value="Active">🟢 Active Account</option>
                                <option value="Inactive">🔴 Inactive Account</option>
                            </select>
                        </div>
                        <div class="col-span-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Billing Address</label>
                            <input type="text" name="address" id="in_address" class="form-input bg-gray-50" placeholder="Full Address / Country">
                        </div>
                    </div>
                </div>

                <div class="px-8 py-5 bg-white flex justify-end space-x-3 border-t border-gray-100 shrink-0 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.02)]">
                    <button type="button" onclick="toggleModal(false)" class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg shadow-sm hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="submitClientBtn" class="px-8 py-2.5 bg-custom-teal text-white font-bold rounded-lg shadow-md hover-teal transition flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> <span id="submitBtnText">Save Client</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewClientModal" class="hidden fixed inset-0 z-[100] modal-overlay flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 bg-custom-teal text-white">
                <h2 class="text-lg font-bold flex items-center"><i class="fa-regular fa-address-card mr-3 text-xl opacity-80"></i> Client Details</h2>
                <button onclick="document.getElementById('viewClientModal').classList.add('hidden')" class="text-teal-100 hover:text-white transition focus:outline-none">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            
            <div class="p-8 overflow-y-auto custom-scroll bg-slate-50 flex-grow">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 flex items-center space-x-6">
                    <img id="v_img" src="" class="w-24 h-24 rounded-full object-cover border-4 border-slate-50 shadow-sm">
                    <div>
                        <div class="flex items-center space-x-3 mb-1">
                            <h3 id="v_name" class="text-2xl font-black text-gray-800"></h3>
                            <span id="v_status" class="status-badge-active shadow-sm"></span>
                        </div>
                        <p id="v_cid" class="text-xs font-bold text-gray-400 tracking-wider mb-3"></p>
                        <p id="v_company" class="inline-block bg-teal-50 text-custom-teal px-3 py-1 rounded border border-teal-100 text-xs font-bold uppercase tracking-wider"></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h4 class="text-sm font-bold text-gray-800 mb-5 border-b pb-2"><i class="fa-solid fa-address-book text-gray-400 mr-2"></i> Contact Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Email Address</p>
                            <p id="v_email" class="font-medium text-gray-800"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Phone Number</p>
                            <p id="v_phone" class="font-medium text-gray-800"></p>
                        </div>
                        <div class="col-span-full">
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Billing Address</p>
                            <p id="v_address" class="font-medium text-gray-800"></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
                    <h4 class="text-sm font-bold text-gray-800 mb-5 border-b pb-2"><i class="fa-solid fa-chart-pie text-gray-400 mr-2"></i> Project Summary</h4>
                    <div class="flex flex-col space-y-2">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Active Project</p>
                        <p id="v_project" class="font-bold text-custom-teal text-lg"></p>
                    </div>
                </div>
            </div>
            
            <div class="px-8 py-4 bg-white border-t border-gray-100 flex justify-end">
                <button onclick="document.getElementById('viewClientModal').classList.add('hidden')" class="px-6 py-2 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Close</button>
            </div>
        </div>
    </div>

    <script>
        // --- Sidebar Logic ---
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            if (!primarySidebar || !mainContent) return;

            const updateMargin = () => {
                if (window.innerWidth <= 992) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    return;
                }
                let totalWidth = primarySidebar.offsetWidth;
                if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                    totalWidth += secondarySidebar.offsetWidth;
                }
                mainContent.style.marginLeft = totalWidth + 'px';
                mainContent.style.width = `calc(100% - ${totalWidth}px)`;
            };

            new ResizeObserver(() => updateMargin()).observe(primarySidebar);
            if (secondarySidebar) {
                new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
            }
            window.addEventListener('resize', updateMargin);
            updateMargin();
        }
        document.addEventListener('DOMContentLoaded', setupLayoutObserver);

        // --- View Switching ---
        function switchView(view) {
            document.getElementById('gridView').classList.toggle('hidden', view !== 'grid');
            document.getElementById('listView').classList.toggle('hidden', view !== 'list');
            
            document.getElementById('gridBtn').className = view === 'grid' ? 'px-3 py-2 bg-custom-teal text-white rounded shadow-sm transition' : 'px-3 py-2 text-gray-400 hover:text-gray-600 transition rounded';
            document.getElementById('listBtn').className = view === 'list' ? 'px-3 py-2 bg-custom-teal text-white rounded shadow-sm transition' : 'px-3 py-2 text-gray-400 hover:text-gray-600 transition rounded';
            document.getElementById('breadcrumb').innerText = view === 'grid' ? "Client Grid" : "Client List";
        }

        // --- Search Filter ---
        function filterClients() {
            const isGrid = !document.getElementById('gridView').classList.contains('hidden');
            const searchVal = document.getElementById(isGrid ? 'gridSearch' : 'listSearch').value.toLowerCase();
            
            const cards = document.querySelectorAll('.client-card');
            cards.forEach(card => {
                const name = card.querySelector('.client-name').innerText.toLowerCase();
                const comp = card.querySelector('.client-company').innerText.toLowerCase();
                card.style.display = (name.includes(searchVal) || comp.includes(searchVal)) ? 'block' : 'none';
            });

            const rows = document.querySelectorAll('.client-row');
            rows.forEach(row => {
                const name = row.querySelector('.list-name').innerText.toLowerCase();
                const comp = row.querySelector('.list-company').innerText.toLowerCase();
                row.style.display = (name.includes(searchVal) || comp.includes(searchVal)) ? '' : 'none';
            });
        }

        // --- View Client Modal ---
        function viewClient(data) {
            document.getElementById('v_img').src = data.avatar;
            document.getElementById('v_name').innerText = data.display_name;
            document.getElementById('v_cid').innerText = 'ID: ' + data.client_id;
            document.getElementById('v_company').innerText = data.company !== 'N/A' ? data.company : 'Independent Client';
            document.getElementById('v_email').innerText = data.email;
            document.getElementById('v_phone').innerText = data.phone;
            document.getElementById('v_address').innerText = data.address || 'Address not provided';
            document.getElementById('v_project').innerText = data.project_name || 'No Active Project';
            
            const statusBadge = document.getElementById('v_status');
            statusBadge.innerText = data.status;
            statusBadge.className = data.status === 'Active' ? 'status-badge-active shadow-sm' : 'status-badge-inactive shadow-sm';
            
            document.getElementById('viewClientModal').classList.remove('hidden');
        }

        // --- Add / Edit Modal ---
        function openModal(action, data = null) {
            const modal = document.getElementById('clientModal');
            const title = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const projectInputDiv = document.getElementById('projectInputDiv');
            const submitBtnText = document.getElementById('submitBtnText');
            
            document.getElementById('clientForm').reset();
            
            if (action === 'edit' && data) {
                title.innerHTML = '<i class="fa-solid fa-pen-to-square text-custom-teal mr-2"></i> Edit Client Profile';
                formAction.value = 'edit_client';
                submitBtnText.innerText = 'Update Client';
                projectInputDiv.style.display = 'none'; // Hide project creation on edit

                document.getElementById('client_db_id').value = data.id;
                document.getElementById('in_fname').value = data.first_name;
                document.getElementById('in_lname').value = data.last_name;
                document.getElementById('in_email').value = data.email !== 'N/A' ? data.email : '';
                document.getElementById('in_phone').value = data.phone !== 'N/A' ? data.phone : '';
                document.getElementById('in_company').value = data.company !== 'N/A' ? data.company : '';
                document.getElementById('in_address').value = data.address;
                document.getElementById('in_status').value = data.status;
                document.getElementById('imgPreview').src = data.avatar;

            } else {
                title.innerHTML = '<i class="fa-solid fa-user-plus text-custom-teal mr-2"></i> Add New Client';
                formAction.value = 'add_client';
                submitBtnText.innerText = 'Save Client';
                projectInputDiv.style.display = 'block';
                document.getElementById('client_db_id').value = '';
                document.getElementById('imgPreview').src = 'https://ui-avatars.com/api/?name=New+Client&background=f1f5f9&color=94a3b8';
            }
            
            // Reset button state
            const btn = document.getElementById('submitClientBtn');
            btn.disabled = false;
            
            modal.classList.remove('hidden');
        }

        function toggleModal(show) {
            document.getElementById('clientModal').classList.toggle('hidden', !show);
        }

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById('imgPreview').src = URL.createObjectURL(file);
            }
        }

        // --- AJAX Form Submit ---
        function submitClientForm(e) {
            e.preventDefault();
            const form = document.getElementById('clientForm');
            const submitBtn = document.getElementById('submitClientBtn');
            const originalBtnHtml = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Saving...';

            const formData = new FormData(form);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => {
                if(data.trim() === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Client records updated successfully.',
                        icon: 'success',
                        confirmButtonColor: '#1a534f'
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire('Error', 'Failed to save client data.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            })
            .catch(() => {
                Swal.fire('Error', 'A network error occurred.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            });
        }

        // --- AJAX Delete Client ---
        function deleteClient(id) {
            Swal.fire({
                title: 'Delete Client?',
                text: "This action will permanently remove this client.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '<i class="fa-solid fa-trash mr-1"></i> Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let formData = new FormData();
                    formData.append('action', 'delete_client');
                    formData.append('id', id);
                    
                    fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(data => {
                        if(data.trim() === 'success') {
                            window.location.reload();
                        } else {
                            Swal.fire('Error', 'Failed to delete client.', 'error');
                        }
                    });
                }
            });
        }

        // --- Export Dropdown ---
        function toggleExport(e) {
            e.stopPropagation();
            document.getElementById('exportMenu').classList.toggle('hidden');
        }
        window.onclick = function(event) {
            const exportMenu = document.getElementById('exportMenu');
            if(exportMenu && !exportMenu.classList.contains('hidden')) {
                exportMenu.classList.add('hidden');
            }
            if (event.target == document.getElementById('clientModal')) toggleModal(false);
            if (event.target == document.getElementById('viewClientModal')) {
                document.getElementById('viewClientModal').classList.add('hidden');
            }
        }
    </script>
</body>
</html>