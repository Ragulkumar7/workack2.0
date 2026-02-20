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

// =========================================================================
// 3. PROCESS AJAX: ADD NEW CLIENT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_client') {
    
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $company = mysqli_real_escape_string($conn, $_POST['company']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Secure hashing
    $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);

    // Generate Client ID (e.g. CLI-001)
    $res = $conn->query("SELECT MAX(id) as max_id FROM clients");
    $row = $res->fetch_assoc();
    $next_id = ($row['max_id'] ?? 0) + 1;
    $client_id = "CLI-" . str_pad($next_id, 3, "0", STR_PAD_LEFT);

    // Handle Profile Image Upload
    $profile_img = null;
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = (file_exists('../assets/profiles/')) ? '../assets/profiles/' : 'assets/profiles/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        $ext = strtolower(pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $new_filename = "client_" . time() . "_" . rand(1000,9999) . "." . $ext;
            if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $upload_dir . $new_filename)) {
                $profile_img = $new_filename;
            }
        }
    }

    // Insert into Clients table
    $stmt = $conn->prepare("INSERT INTO clients (client_id, first_name, last_name, username, email, password, phone, company, status, address, profile_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss", $client_id, $fname, $lname, $username, $email, $password, $phone, $company, $status, $address, $profile_img);
    
    if ($stmt->execute()) {
        // Automatically create a project for them if requested!
        if(!empty($project_name)) {
            $leader_id = $_SESSION['user_id']; // Default assign to whoever is creating it
            $proj_stmt = $conn->prepare("INSERT INTO projects (project_name, client_name, leader_id, status, priority, start_date) VALUES (?, ?, ?, 'Active', 'Medium', CURDATE())");
            $proj_stmt->bind_param("ssi", $project_name, $company, $leader_id);
            $proj_stmt->execute();
            $proj_stmt->close();
        }
        echo "success";
    } else {
        echo "error";
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// =========================================================================
// 4. FETCH DATA FOR DASHBOARD
// =========================================================================

// A. Stats
$stats_query = "SELECT 
                  COUNT(*) as total, 
                  SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                  SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive
                FROM clients";
$stats_res = $conn->query($stats_query)->fetch_assoc();
$total_clients = $stats_res['total'] ?? 0;
$active_clients = $stats_res['active'] ?? 0;
$inactive_clients = $stats_res['inactive'] ?? 0;

// B. Fetch All Clients with their respective Projects
$clients = [];
$client_query = "SELECT * FROM clients ORDER BY id DESC";
$client_result = $conn->query($client_query);

while($row = $client_result->fetch_assoc()) {
    // Smart Image Resolver
    $imgSource = $row['profile_img'];
    if(empty($imgSource)) {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['first_name'].' '.$row['last_name'])."&background=random";
    } else {
        $imgSource = (file_exists('../assets/profiles/')) ? '../assets/profiles/' . $imgSource : 'assets/profiles/' . $imgSource;
    }
    $row['avatar'] = $imgSource;

    // Fetch ONE associated project for the grid display based on Company Name
    $proj_name = "No Active Project";
    $proj_progress = 0;
    if(!empty($row['company'])) {
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
            background-color: #00c48c; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }
        .status-badge-inactive { 
            background-color: #ef4444; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }
        .dot { height: 6px; width: 6px; background-color: white; border-radius: 50%; display: inline-block; margin-right: 6px; }

        .page-container { display: flex; min-height: 100vh; }
        
        /* Main Content Adjustments */
        .main-content {
            flex-grow: 1;
            margin-left: 95px; /* Matches the sidebar width */
            width: calc(100% - 95px);
            padding: 2rem;
            background-color: #f7f7f7;
            overflow-x: hidden;
            transition: all 0.3s ease;
        }

        .client-table th {
            text-align: left; padding: 12px 16px; font-size: 14px; font-weight: 600;
            color: #333d5e; background-color: #f8f9fa; border-bottom: 1px solid #eee;
        }
        .client-table td {
            padding: 12px 16px; font-size: 14px; vertical-align: middle; border-bottom: 1px solid #f3f4f6;
        }

        /* Modal & Tabs */
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(2px); }
        .tab-btn { padding-bottom: 12px; font-size: 14px; font-weight: 700; color: #9ca3af; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab-btn.active { color: #1a534f; border-bottom: 2px solid #1a534f; }

        /* Toggle Switch UI */
        .switch { position: relative; display: inline-block; width: 38px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #1a534f; }
        input:checked + .slider:before { transform: translateX(18px); }

        .permission-row { border-bottom: 1px solid #f3f4f6; }
        .permission-row:last-child { border-bottom: none; }

        .form-input { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 6px; outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: #1a534f; }
        .required-star { color: #ef4444; margin-left: 2px; }
        .custom-checkbox { width: 18px; height: 18px; accent-color: #1a534f; cursor: pointer; }
        
        .sort-dropdown-menu { box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #f1f1f1; }
        .sort-item { padding: 12px 24px; color: #333d5e; font-size: 15px; transition: all 0.2s; cursor: pointer; }
        .sort-item:hover { background-color: #f9fafb; color: #1a534f; }

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
                        <h1 class="text-2xl font-bold">Clients</h1>
                        <nav class="text-sm text-gray-400 flex items-center space-x-2 mt-1">
                            <i class="fa fa-home"></i> <span>/ Projects / <span id="breadcrumb" class="text-gray-600">Client Grid</span></span>
                        </nav>
                    </div>
                    
                    <div class="flex items-center space-x-3 w-full md:w-auto">
                        <div class="flex bg-white border border-gray-200 rounded-lg p-1 shadow-sm">
                            <button id="listBtn" onclick="switchView('list')" class="px-3 py-2 text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-list-ul"></i>
                            </button>
                            <button id="gridBtn" onclick="switchView('grid')" class="px-3 py-2 bg-custom-teal text-white rounded-md shadow-sm transition">
                                <i class="fa-solid fa-table-cells-large"></i>
                            </button>
                        </div>

                        <div class="relative hidden sm:block">
                            <button onclick="toggleExport(event)" class="px-4 py-2 bg-white border border-gray-200 rounded-lg flex items-center space-x-2 font-semibold text-gray-700 hover:bg-gray-50 transition">
                                <i class="fa-regular fa-file-lines"></i> <span>Export</span> <i class="fa fa-chevron-down text-[10px] ml-1"></i>
                            </button>
                            <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl dropdown-shadow border py-2 z-30 text-sm">
                                <button class="w-full text-left px-4 py-2 hover:bg-gray-50">Export as PDF</button>
                                <button class="w-full text-left px-4 py-2 hover:bg-gray-50">Export as Excel</button>
                                <button class="w-full text-left px-4 py-2 hover:bg-gray-50">Export as CSV</button>
                            </div>
                        </div>

                        <button onclick="toggleModal(true)" class="flex-1 md:flex-none justify-center px-5 py-2 bg-custom-teal text-white rounded-lg flex items-center space-x-2 font-bold shadow-md hover-teal transition">
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
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">Total Clients</p><h3 class="text-xl font-bold"><?php echo $total_clients; ?></h3></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-500"><i class="fa-solid fa-user-check text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">Active Clients</p><h3 class="text-xl font-bold"><?php echo $active_clients; ?></h3></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center text-red-500"><i class="fa-solid fa-user-slash text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">Inactive Clients</p><h3 class="text-xl font-bold"><?php echo $inactive_clients; ?></h3></div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500"><i class="fa-solid fa-user-plus text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">New This Month</p><h3 class="text-xl font-bold">0</h3></div>
                        </div>
                    </div>
                </div>

                <div id="gridView">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-6">
                        <div class="p-6 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                            <h2 class="text-lg font-bold">Client Catalog</h2>
                            <div class="flex items-center space-x-4">
                                <div class="relative">
                                    <input type="text" id="gridSearch" onkeyup="filterClients()" placeholder="Search Clients..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-custom-teal">
                                    <i class="fa fa-search absolute left-4 top-2.5 text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" id="clientGridContainer">
                        <?php if(empty($clients)): ?>
                            <div class="col-span-full text-center py-12 text-gray-400">
                                <i class="fa-solid fa-users-slash text-4xl mb-3"></i>
                                <p>No clients found. Click "Add Client" to create one.</p>
                            </div>
                        <?php else: foreach($clients as $c): 
                            $statusDot = ($c['status'] == 'Active') ? 'bg-emerald-500' : 'bg-red-500';
                            $progressColor = ($c['project_progress'] == 100) ? 'bg-emerald-500' : 'bg-custom-teal';
                        ?>
                        <div class="client-card bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm hover:shadow-md transition">
                            <div class="absolute top-4 right-4 text-gray-300 cursor-pointer hover:text-custom-teal"><i class="fa fa-pen-to-square"></i></div>
                            <div class="relative w-20 h-20 mx-auto mb-3">
                                <img src="<?php echo $c['avatar']; ?>" class="w-full h-full rounded-full avatar-ring object-cover">
                                <div class="absolute bottom-0 right-1 w-3 h-3 <?php echo $statusDot; ?> border-2 border-white rounded-full"></div>
                            </div>
                            <h3 class="font-bold client-name"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></h3>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-600 client-company"><?php echo htmlspecialchars($c['company'] ?? 'Independent'); ?></span>
                            
                            <p class="text-xs text-gray-400 mt-6 mb-2 text-left truncate"><i class="fa-solid fa-layer-group text-custom-teal mr-1"></i> <?php echo htmlspecialchars($c['project_name']); ?></p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2">
                                <div class="<?php echo $progressColor; ?> h-1.5 rounded-full" style="width: <?php echo $c['project_progress']; ?>%"></div>
                            </div>
                            <div class="flex justify-between items-center mb-6 text-[11px] font-bold text-gray-500">
                                <span>Progress</span>
                                <span><?php echo $c['project_progress']; ?>%</span>
                            </div>
                            
                            <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-end">
                                <div>
                                    <p class="text-[9px] text-gray-400 uppercase font-bold">Client ID</p>
                                    <p class="text-sm font-bold text-gray-700"><?php echo $c['client_id']; ?></p>
                                </div>
                                <div class="flex space-x-2 text-gray-300">
                                    <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>"><i class="fa-regular fa-envelope border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal hover:bg-teal-50"></i></a>
                                    <a href="tel:<?php echo htmlspecialchars($c['phone']); ?>"><i class="fa fa-phone border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal hover:bg-teal-50"></i></a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div id="listView" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex flex-col md:flex-row justify-between items-center">
                            <h2 class="text-lg font-bold">Client List</h2>
                            <div class="relative w-full md:w-64 mt-4 md:mt-0">
                                <input type="text" id="listSearch" onkeyup="filterClients()" placeholder="Search Clients..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-custom-teal">
                                <i class="fa fa-search absolute left-4 top-2.5 text-gray-300"></i>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full client-table">
                            <thead>
                                <tr>
                                    <th>Client ID</th>
                                    <th>Client Name</th>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="clientListContainer">
                                <?php if(empty($clients)): ?>
                                    <tr><td colspan="7" class="text-center py-8 text-gray-400">No clients found.</td></tr>
                                <?php else: foreach($clients as $c): 
                                    $badge = ($c['status'] == 'Active') ? 'status-badge-active' : 'status-badge-inactive';
                                ?>
                                <tr class="hover:bg-gray-50 client-row">
                                    <td class="font-medium text-gray-700"><?php echo $c['client_id']; ?></td>
                                    <td>
                                        <div class="flex items-center space-x-3">
                                            <img src="<?php echo $c['avatar']; ?>" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <div class="font-bold text-gray-800 list-name"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></div>
                                                <div class="text-[11px] text-gray-400 uppercase list-company"><?php echo htmlspecialchars($c['company'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-gray-600 font-medium"><?php echo htmlspecialchars($c['company'] ?? '-'); ?></td>
                                    <td class="text-gray-500"><?php echo htmlspecialchars($c['email']); ?></td>
                                    <td class="text-gray-500"><?php echo htmlspecialchars($c['phone']); ?></td>
                                    <td><span class="<?php echo $badge; ?>"><span class="dot"></span><?php echo $c['status']; ?></span></td>
                                    <td>
                                        <div class="flex items-center justify-end space-x-3 text-gray-400">
                                            <button class="hover:text-custom-teal bg-white border p-1.5 rounded-lg shadow-sm"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="hover:text-red-500 bg-white border p-1.5 rounded-lg shadow-sm"><i class="fa-regular fa-trash-can"></i></button>
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

    <div id="addClientModal" class="hidden fixed inset-0 z-[100] modal-overlay flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden">
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 bg-gray-50">
                <h2 class="text-xl font-bold text-[#333d5e]"><i class="fa-solid fa-user-plus text-custom-teal mr-2"></i> Add New Client</h2>
                <button onclick="toggleModal(false)" class="text-gray-400 hover:text-red-500 transition">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>

            <form id="addClientForm" onsubmit="submitClientForm(event)" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_client">
                
                <div class="px-6 pt-4 flex space-x-8 border-b border-gray-100">
                    <button type="button" onclick="switchTab('basic')" id="tab-basic" class="tab-btn active">Basic Information</button>
                    <button type="button" onclick="switchTab('permissions')" id="tab-permissions" class="tab-btn">Permissions & Control</button>
                </div>

                <div id="content-basic" class="p-8 max-h-[65vh] overflow-y-auto">
                    <div class="bg-gray-50 rounded-xl p-6 mb-8 border border-dashed border-gray-300 flex items-center space-x-6">
                        <img id="imgPreview" src="https://ui-avatars.com/api/?name=New+Client&background=f1f5f9&color=94a3b8" class="w-20 h-20 rounded-full border border-gray-200 shadow-sm object-cover">
                        <div>
                            <h4 class="font-bold text-gray-800">Upload Profile Image</h4>
                            <p class="text-xs text-gray-400 mt-0.5 mb-3">JPG, PNG or WEBP. Max size 2MB</p>
                            <label for="profileUpload" class="px-4 py-2 cursor-pointer bg-custom-teal text-white text-xs font-bold rounded-lg shadow-sm hover-teal transition">
                                <i class="fa-solid fa-upload mr-1"></i> Choose File
                            </label>
                            <input type="file" id="profileUpload" name="profile_img" accept="image/*" class="hidden" onchange="previewImage(event)">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                        <div>
                            <label class="block text-sm font-bold mb-1">First Name <span class="required-star">*</span></label>
                            <input type="text" name="first_name" required class="form-input bg-gray-50" placeholder="e.g. John">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Last Name</label>
                            <input type="text" name="last_name" class="form-input bg-gray-50" placeholder="e.g. Doe">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Username <span class="required-star">*</span></label>
                            <input type="text" name="username" required class="form-input bg-gray-50" placeholder="Login ID">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Email Address <span class="required-star">*</span></label>
                            <input type="email" name="email" required class="form-input bg-gray-50" placeholder="client@company.com">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Password <span class="required-star">*</span></label>
                            <input type="password" name="password" required class="form-input bg-gray-50" placeholder="Create a strong password">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Phone Number <span class="required-star">*</span></label>
                            <input type="text" name="phone" required class="form-input bg-gray-50" placeholder="+1 (555) 000-0000">
                        </div>
                        
                        <div class="col-span-full border-t border-gray-100 my-2 pt-4">
                            <h4 class="text-sm font-bold text-custom-teal mb-4 uppercase"><i class="fa-solid fa-briefcase mr-1"></i> Business Details</h4>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold mb-1">Company Name</label>
                            <input type="text" name="company" class="form-input bg-gray-50" placeholder="Enter Company Name">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1 text-teal-700">Assign/Create Project</label>
                            <input type="text" name="project_name" class="form-input border-teal-200 bg-teal-50/30" placeholder="e.g. Mobile App Redesign">
                            <p class="text-[10px] text-gray-400 mt-1">Typing a name here automatically creates a new project.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">Client Status</label>
                            <select name="status" class="form-input bg-gray-50">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-span-full">
                            <label class="block text-sm font-bold mb-1">Billing Address</label>
                            <input type="text" name="address" class="form-input bg-gray-50" placeholder="Full Address / Country">
                        </div>
                    </div>
                </div>

                <div id="content-permissions" class="hidden p-8 max-h-[65vh] overflow-y-auto">
                    <div class="bg-blue-50 text-blue-700 p-3 rounded-lg text-sm mb-5 border border-blue-100">
                        <i class="fa-solid fa-circle-info mr-2"></i> Set what modules this client can access when they log into the portal.
                    </div>
                    
                    <div class="w-full">
                        <div class="grid grid-cols-7 gap-4 pb-4 border-b border-gray-200 mb-2">
                            <div class="col-span-2 text-xs font-bold text-gray-400 uppercase tracking-wider">Module Access</div>
                            <div class="text-xs font-bold text-gray-400 text-center uppercase tracking-wider">Read</div>
                            <div class="text-xs font-bold text-gray-400 text-center uppercase tracking-wider">Write</div>
                            <div class="text-xs font-bold text-gray-400 text-center uppercase tracking-wider">Create</div>
                            <div class="text-xs font-bold text-gray-400 text-center uppercase tracking-wider">Delete</div>
                            <div class="text-xs font-bold text-gray-400 text-center uppercase tracking-wider">Import</div>
                        </div>

                        <?php 
                        $modules = ['Projects', 'Tasks', 'Invoices', 'Support Tickets', 'Files'];
                        foreach($modules as $mod):
                        ?>
                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-700"><?php echo $mod; ?></span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="px-8 py-4 bg-gray-50 flex justify-end space-x-3 border-t border-gray-200">
                    <button type="button" onclick="toggleModal(false)" class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg shadow-sm hover:bg-gray-100 transition">Cancel</button>
                    <button type="submit" class="px-8 py-2.5 bg-custom-teal text-white font-bold rounded-lg shadow-md hover-teal transition flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> Save Client
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sidebar Layout Integration
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

        // UI View Toggles
        function switchTab(tab) {
            document.getElementById('tab-basic').classList.toggle('active', tab === 'basic');
            document.getElementById('tab-permissions').classList.toggle('active', tab === 'permissions');
            document.getElementById('content-basic').classList.toggle('hidden', tab !== 'basic');
            document.getElementById('content-permissions').classList.toggle('hidden', tab !== 'permissions');
        }

        function toggleModal(show) {
            document.getElementById('addClientModal').classList.toggle('hidden', !show);
            if(show) {
                document.getElementById('addClientForm').reset();
                document.getElementById('imgPreview').src = 'https://ui-avatars.com/api/?name=New+Client&background=f1f5f9&color=94a3b8';
                switchTab('basic'); 
            }
        }

        function switchView(view) {
            document.getElementById('gridView').classList.toggle('hidden', view !== 'grid');
            document.getElementById('listView').classList.toggle('hidden', view !== 'list');
            
            document.getElementById('gridBtn').className = view === 'grid' ? 'px-3 py-2 bg-custom-teal text-white rounded-md shadow-sm transition' : 'px-3 py-2 text-gray-400 hover:text-gray-600 transition';
            document.getElementById('listBtn').className = view === 'list' ? 'px-3 py-2 bg-custom-teal text-white rounded-md shadow-sm transition' : 'px-3 py-2 text-gray-400 hover:text-gray-600 transition';
            document.getElementById('breadcrumb').innerText = view === 'grid' ? "Client Grid" : "Client List";
        }

        // Image Preview Logic
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById('imgPreview').src = URL.createObjectURL(file);
            }
        }

        // Client Search Filtering (Works for both Grid and List)
        function filterClients() {
            // Get active search input based on visible view
            const isGrid = !document.getElementById('gridView').classList.contains('hidden');
            const searchVal = document.getElementById(isGrid ? 'gridSearch' : 'listSearch').value.toLowerCase();
            
            // Filter Grid
            const cards = document.querySelectorAll('.client-card');
            cards.forEach(card => {
                const name = card.querySelector('.client-name').innerText.toLowerCase();
                const comp = card.querySelector('.client-company').innerText.toLowerCase();
                if(name.includes(searchVal) || comp.includes(searchVal)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Filter List
            const rows = document.querySelectorAll('.client-row');
            rows.forEach(row => {
                const name = row.querySelector('.list-name').innerText.toLowerCase();
                const comp = row.querySelector('.list-company').innerText.toLowerCase();
                if(name.includes(searchVal) || comp.includes(searchVal)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Handle AJAX Form Submission
        function submitClientForm(e) {
            e.preventDefault();
            const form = document.getElementById('addClientForm');
            const formData = new FormData(form);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                if(data.trim() === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Client successfully added.',
                        icon: 'success',
                        confirmButtonColor: '#1a534f'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', 'Failed to add client. Try again.', 'error');
                }
            });
        }

        // Export Dropdown
        function toggleExport(e) {
            e.stopPropagation();
            document.getElementById('exportMenu').classList.toggle('hidden');
        }
        window.onclick = function() {
            if(document.getElementById('exportMenu')) {
                document.getElementById('exportMenu').classList.add('hidden');
            }
        }
    </script>
</body>
</html>