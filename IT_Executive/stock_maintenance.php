<?php 
// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Generate CSRF Token for Secure AJAX Requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. DATABASE CONNECTION (Smart Path Resolver)
date_default_timezone_set('Asia/Kolkata');
$dbPath = 'include/db_connect.php';
$root_path = './';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
    $root_path = '../';
} else {
    die("Critical Error: Cannot find database connection file.");
}

$current_user_id = $_SESSION['user_id'];

// --- FETCH LOGGED IN USER ROLE ---
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt_role = $conn->prepare($role_query);
$stmt_role->bind_param("i", $current_user_id);
$stmt_role->execute();
$role_res = $stmt_role->get_result()->fetch_assoc();
$user_role = $role_res['role'] ?? 'Employee';
$stmt_role->close();

// Define Permissions
$finance_roles = ['Accountant', 'Finance', 'CFO', 'System Admin'];
$it_roles = ['IT Admin', 'System Admin', 'IT Executive'];
$can_approve = in_array($user_role, $finance_roles);
$can_manage_assets = in_array($user_role, $it_roles);

// --- DATABASE PATCHER & OPTIMIZER ---
$table_check = "CREATE TABLE IF NOT EXISTS hardware_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    system_id VARCHAR(100) NULL,
    category VARCHAR(100) NULL,
    asset_name VARCHAR(255) NULL,
    barcode VARCHAR(100) NULL,
    invoice_number VARCHAR(100) NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    request_status VARCHAR(50) DEFAULT 'Pending Accountant',
    status VARCHAR(50) DEFAULT 'NEW',
    acquisition_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($table_check);

$enterprise_columns = [
    'assigned_to' => 'INT NULL',
    'assigned_date' => 'DATE NULL',
    'returned_date' => 'DATE NULL',
    'allocation_status' => "VARCHAR(50) DEFAULT 'Available'",
    'qr_code_path' => 'VARCHAR(255) NULL',
    'depreciation_rate' => 'DECIMAL(5,2) DEFAULT 20.00',
    'lifecycle_status' => "VARCHAR(50) DEFAULT 'Available'",
    'invoice_file' => 'VARCHAR(255) NULL'
];

foreach ($enterprise_columns as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM hardware_assets LIKE '$col'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE hardware_assets ADD COLUMN $col $def");
    }
}

// Create Asset History Table for IT Audits
$history_check = "CREATE TABLE IF NOT EXISTS asset_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($history_check);

// Apply Performance Indexes safely
$indexes = [
    'idx_asset_system' => 'system_id', 
    'idx_asset_alloc' => 'allocation_status', 
    'idx_asset_status' => 'request_status'
];
foreach ($indexes as $idx_name => $col_name) {
    $check_idx = $conn->query("SHOW INDEX FROM hardware_assets WHERE Key_name = '$idx_name'");
    if ($check_idx && $check_idx->num_rows === 0) {
        $conn->query("CREATE INDEX $idx_name ON hardware_assets($col_name)");
    }
}

// --- AUDIT LOGGER HELPER FUNCTION ---
function logAssetHistory($conn, $asset_id, $user_id, $action, $remarks = '') {
    $stmt = $conn->prepare("INSERT INTO asset_history (asset_id, user_id, action, remarks) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiss", $asset_id, $user_id, $action, $remarks);
        $stmt->execute();
        $stmt->close();
    }
}

// 3. HANDLE AJAX POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? '';
    if (empty($action)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $_POST = $input;
            $action = $_POST['action'] ?? '';
        }
    }

    if (!empty($action)) {
        if (ob_get_length()) ob_clean(); 
        header('Content-Type: application/json');
        
        // CSRF VALIDATION
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Security token mismatch. Please refresh.']);
            exit;
        }

        $response = ['success' => false, 'error' => 'Unknown action'];

        // ACTION: ADD ASSET
        if ($action === 'add' && $can_manage_assets) {
            $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
            
            // SECURE Invoice Upload (Using @ to suppress XAMPP folder permission warnings breaking JSON)
            $invoice_path = null;
            if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
                $file_size = $_FILES['invoice_file']['size'];
                $ext = strtolower(pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'png', 'jpg', 'jpeg'];
                
                if (in_array($ext, $allowed) && $file_size <= 5 * 1024 * 1024) {
                    $dir = $root_path . 'uploads/invoices/';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $filename = 'INV_' . uniqid() . '.' . $ext;
                    if (@move_uploaded_file($_FILES['invoice_file']['tmp_name'], $dir . $filename)) {
                        $invoice_path = 'uploads/invoices/' . $filename;
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid file format or size exceeds 5MB.']);
                    exit;
                }
            }

            // Generate QR Code via API
            $qr_path = null;
            if (!empty($_POST['sys'])) {
                $qr_dir = $root_path . 'uploads/qrcodes/';
                if (!is_dir($qr_dir)) @mkdir($qr_dir, 0777, true);
                $qr_data = "ASSET: " . $_POST['sys'] . "\n" . $_POST['desc'] . "\nSN: " . $_POST['bar'];
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);
                $qr_img = @file_get_contents($qr_url);
                if ($qr_img) {
                    $qr_filename = 'QR_' . $_POST['sys'] . '_' . uniqid() . '.png';
                    @file_put_contents($qr_dir . $qr_filename, $qr_img);
                    $qr_path = 'uploads/qrcodes/' . $qr_filename;
                }
            }

            $stmt = $conn->prepare("INSERT INTO hardware_assets (system_id, category, asset_name, barcode, invoice_number, amount, status, acquisition_date, depreciation_rate, invoice_file, qr_code_path, request_status, lifecycle_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Accountant', 'Available')");
            
            $amt = floatval($_POST['amt']);
            $dep = floatval($_POST['dep_rate']);
            
            $stmt->bind_param("sssssdssdss", $_POST['sys'], $_POST['cat'], $_POST['desc'], $_POST['bar'], $_POST['inv'], $amt, $_POST['cond'], $date, $dep, $invoice_path, $qr_path);
            
            if ($stmt->execute()) {
                $new_asset_id = $stmt->insert_id;
                logAssetHistory($conn, $new_asset_id, $current_user_id, 'Created', 'Asset registered into inventory.');
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => $stmt->error];
            }
            $stmt->close();
        }
        
        // ACTION: EDIT ASSET
        else if ($action === 'edit' && $can_manage_assets) {
            $formattedDate = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
            $amt = floatval($_POST['amt']);
            $dep = floatval($_POST['dep_rate']);
            $asset_id = (int)$_POST['id'];

            $stmt = $conn->prepare("UPDATE hardware_assets SET acquisition_date = ?, category = ?, asset_name = ?, barcode = ?, system_id = ?, status = ?, invoice_number = ?, amount = ?, depreciation_rate = ?, lifecycle_status = ? WHERE id = ?");
            $stmt->bind_param("sssssssddsi", $formattedDate, $_POST['cat'], $_POST['desc'], $_POST['bar'], $_POST['sys'], $_POST['cond'], $_POST['inv'], $amt, $dep, $_POST['lifecycle'], $asset_id);
            
            if ($stmt->execute()) {
                logAssetHistory($conn, $asset_id, $current_user_id, 'Updated', 'Asset details modified.');
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => $stmt->error];
            }
            $stmt->close();
        }

        // ACTION: ALLOCATE ASSET
        else if ($action === 'allocate' && $can_manage_assets) {
            $asset_id = (int)$_POST['asset_id'];
            $emp_id = (int)$_POST['employee_id'];
            $alloc_date = $_POST['alloc_date'];
            
            $stmt = $conn->prepare("UPDATE hardware_assets SET assigned_to = ?, assigned_date = ?, allocation_status = 'Assigned', lifecycle_status = 'Assigned' WHERE id = ?");
            $stmt->bind_param("isi", $emp_id, $alloc_date, $asset_id);
            if ($stmt->execute()) {
                logAssetHistory($conn, $asset_id, $current_user_id, 'Allocated', "Asset assigned to User ID: $emp_id");
                
                $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, source_type, created_at) VALUES (?, 'Asset Allocated', 'You have been assigned a new IT asset.', 'info', 'system', NOW())");
                if($n_stmt) {
                    $n_stmt->bind_param("i", $emp_id);
                    $n_stmt->execute();
                    $n_stmt->close();
                }
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => $stmt->error];
            }
            $stmt->close();
        }

        // ACTION: RETURN ASSET
        else if ($action === 'return' && $can_manage_assets) {
            $asset_id = (int)$_POST['asset_id'];
            $ret_date = date('Y-m-d');
            $stmt = $conn->prepare("UPDATE hardware_assets SET returned_date = ?, allocation_status = 'Returned', lifecycle_status = 'Available', assigned_to = NULL WHERE id = ?");
            $stmt->bind_param("si", $ret_date, $asset_id);
            if ($stmt->execute()) {
                logAssetHistory($conn, $asset_id, $current_user_id, 'Returned', 'Asset returned to inventory.');
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => $stmt->error];
            }
            $stmt->close();
        }

        // ACTION: PROCESS APPROVAL (Finance Only)
        else if ($action === 'approve_reject') {
            if ($can_approve) {
                $new_status = $_POST['new_status'];
                $asset_id = (int)$_POST['asset_id'];

                $stmt = $conn->prepare("UPDATE hardware_assets SET request_status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $asset_id);
                if ($stmt->execute()) {
                    logAssetHistory($conn, $asset_id, $current_user_id, 'Finance Approval', "Invoice marked as $new_status.");
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'error' => $stmt->error];
                }
                $stmt->close();
            } else {
                $response = ['success' => false, 'error' => 'Permission denied. Only Finance can approve.'];
            }
        }

        echo json_encode($response);
        exit;
    }
}

// 4. FETCH EMPLOYEES FOR ALLOCATION
$employees = [];
$e_res = $conn->query("SELECT u.id, COALESCE(ep.full_name, u.username) as name, ep.emp_id_code FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id ORDER BY name ASC");
while ($e = $e_res->fetch_assoc()) { $employees[] = $e; }

// 5. FETCH DYNAMIC CATEGORIES
$asset_categories = [];
$cat_result = $conn->query("SELECT DISTINCT category FROM hardware_assets WHERE category IS NOT NULL AND category != ''");
if ($cat_result) {
    while($c = $cat_result->fetch_assoc()) { $asset_categories[] = trim($c['category']); }
}
$default_cats = ['Laptop', 'Desktop', 'Monitor', 'Printer', 'Networking', 'Accessories'];
$asset_categories = array_unique(array_merge($asset_categories, $default_cats));
sort($asset_categories);

// 6. FETCH TABLE DATA & CALCULATE DEPRECIATION
$query = "SELECT a.*, DATE_FORMAT(a.acquisition_date, '%d M Y') as display_date, 
                 COALESCE(ep.full_name, u.username) as assigned_name 
          FROM hardware_assets a
          LEFT JOIN users u ON a.assigned_to = u.id
          LEFT JOIN employee_profiles ep ON u.id = ep.user_id
          ORDER BY a.id DESC";
$result = $conn->query($query);
$assets = [];

$statTotal = 0;
$statAssigned = 0;
$statAvailable = 0;
$statTotalCurrentValue = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $statTotal++;
        if ($row['allocation_status'] === 'Assigned') $statAssigned++;
        if ($row['allocation_status'] === 'Available' || $row['allocation_status'] === 'Returned') $statAvailable++;

        // Calculate Depreciation Dynamically
        $purchase_value = floatval($row['amount']);
        $dep_rate = floatval($row['depreciation_rate'] ?? 20);
        $current_value = $purchase_value;
        
        if (!empty($row['acquisition_date'])) {
            $acq_date = new DateTime($row['acquisition_date']);
            $now = new DateTime();
            $interval = $acq_date->diff($now);
            $years_used = $interval->y + ($interval->m / 12);
            
            $dep_amount = $purchase_value * ($dep_rate / 100) * $years_used;
            $current_value = max(0, $purchase_value - $dep_amount);
        }
        
        $row['current_value'] = $current_value;
        $statTotalCurrentValue += $current_value;
        
        $assets[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise IT | Asset Ledger</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .modal-overlay { display: none; position: fixed; z-index: 9999; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }

        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

        .ts-wrapper { width: 100%; }
        .ts-control { border-radius: 0.5rem !important; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; min-height: 38px; display: flex; align-items: center; font-size: 0.875rem; background-color: #fff; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);}
        .ts-dropdown { border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: #e2e8f0; font-size: 0.875rem; z-index: 10000; }
        .ts-control.focus { border-color: #0d9488; box-shadow: 0 0 0 1px #0d9488; }

        /* CRITICAL: Forces SweetAlert to appear over any custom modal */
        .swal2-container { z-index: 100000 !important; }

        @media (max-width: 1024px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 16px; padding-top: 80px; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>
    <?php include $headerPath; ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">Enterprise Asset Management</h1>
                <p class="text-slate-500 text-sm mt-1 font-medium">Inventory, Depreciation, & Allocations.</p>
            </div>
            
            <div class="flex gap-2">
                <button onclick="exportCSV()" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-file-export"></i> Export Data
                </button>
                
                <?php if($can_manage_assets): ?>
                <button onclick="openModal('add')" class="bg-[#1b5a5a] hover:bg-[#144343] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition transform active:scale-95 flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Register Asset
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card p-5 border-l-4 border-l-blue-500 flex flex-col justify-center hover:shadow-md transition">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Assets</p>
                <h3 class="text-3xl font-black text-slate-800"><?= $statTotal ?></h3>
            </div>
            <div class="card p-5 border-l-4 border-l-emerald-500 flex flex-col justify-center hover:shadow-md transition">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Assets Available</p>
                <h3 class="text-3xl font-black text-emerald-600"><?= $statAvailable ?></h3>
            </div>
            <div class="card p-5 border-l-4 border-l-amber-500 flex flex-col justify-center hover:shadow-md transition">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Assets Assigned</p>
                <h3 class="text-3xl font-black text-amber-600"><?= $statAssigned ?></h3>
            </div>
            <div class="card p-5 border-l-4 border-l-purple-500 flex flex-col justify-center hover:shadow-md transition">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Current Total Value</p>
                <h3 class="text-2xl font-black text-slate-800">₹<?= number_format($statTotalCurrentValue) ?></h3>
            </div>
        </div>

        <div class="card flex flex-col flex-grow overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white shrink-0">
                <h3 class="text-lg font-black text-slate-800">Stock & Allocation Ledger</h3>
                <div class="relative w-full sm:w-72">
                    <i class="fa-solid fa-search absolute left-3.5 top-3 text-slate-400"></i>
                    <input type="text" id="searchInput" onkeyup="filterAssets()" placeholder="Search asset, ID, or employee..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm font-medium focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                </div>
            </div>
            
            <div class="overflow-x-auto custom-scroll max-h-[600px]">
                <table class="w-full text-left whitespace-nowrap text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Asset Name & ID</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">QR Code</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Allocation</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Financial Value</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Inv Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($assets)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-16">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <i class="fa-solid fa-boxes-stacked text-3xl mb-3 text-slate-300"></i>
                                        <p class="font-bold text-slate-500">No Assets Registered</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach($assets as $row): 
                            $status_class = 'bg-slate-100 text-slate-600';
                            if($row['request_status'] === 'Approved') $status_class = 'bg-emerald-50 text-emerald-600 border border-emerald-200';
                            if($row['request_status'] === 'Rejected') $status_class = 'bg-rose-50 text-rose-600 border border-rose-200';
                            if($row['request_status'] === 'Pending Accountant') $status_class = 'bg-amber-50 text-amber-600 border border-amber-200';

                            $alloc_class = 'text-emerald-600'; 
                            if($row['allocation_status'] === 'Assigned') $alloc_class = 'text-amber-600';
                            
                            $display_date = !empty($row['display_date']) ? $row['display_date'] : (!empty($row['acquisition_date']) ? date('d M Y', strtotime($row['acquisition_date'])) : 'N/A');
                        ?>
                        <tr class="asset-row hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-600"><?= $display_date ?></td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($row['asset_name'] ?? 'N/A') ?></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="bg-slate-100 border border-slate-200 text-slate-700 px-1.5 py-0.5 rounded text-[10px] font-bold font-mono shadow-sm">
                                        <?= htmlspecialchars($row['system_id'] ?? 'N/A') ?>
                                    </span>
                                    <span class="text-[9px] font-black text-[#1b5a5a] bg-[#1b5a5a]/10 px-1.5 py-0.5 rounded uppercase tracking-widest"><?= htmlspecialchars($row['category']) ?></span>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <?php if(!empty($row['qr_code_path'])): ?>
                                    <button onclick="viewQR('<?= $root_path . htmlspecialchars($row['qr_code_path']) ?>', '<?= htmlspecialchars($row['system_id']) ?>')" class="text-teal-600 hover:text-teal-800 bg-teal-50 px-3 py-1.5 rounded border border-teal-200 text-xs font-bold transition shadow-sm">
                                        <i class="fa-solid fa-qrcode"></i> View QR
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">No QR</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4">
                                <p class="font-bold text-sm <?= $alloc_class ?>"><i class="fa-solid fa-circle text-[8px] mr-1"></i><?= $row['allocation_status'] ?></p>
                                <?php if($row['allocation_status'] === 'Assigned'): ?>
                                    <p class="text-xs text-slate-600 font-medium mt-1"><i class="fa-regular fa-user mr-1"></i><?= htmlspecialchars($row['assigned_name']) ?></p>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4">
                                <p class="font-black text-slate-800">C.V: ₹<?= number_format($row['current_value'], 2) ?></p>
                                <p class="text-[10px] text-slate-500 font-bold mt-0.5 uppercase">Pur: ₹<?= number_format($row['amount'] ?? 0, 2) ?> | Dep: <?= $row['depreciation_rate'] ?>%</p>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider rounded-md <?= $status_class ?>">
                                    <?= htmlspecialchars($row['request_status']) ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right flex justify-end gap-1">
                                <button onclick='openViewModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm border border-blue-100 flex items-center gap-1">
                                    <i class="fa-solid fa-eye"></i> View
                                </button>

                                <?php if($can_manage_assets): ?>
                                    <?php if($row['allocation_status'] !== 'Assigned'): ?>
                                        <button onclick="openAllocateModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['asset_name']) ?>')" class="bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100 px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm flex items-center gap-1">
                                            <i class="fa-solid fa-user-plus"></i> Assign
                                        </button>
                                    <?php else: ?>
                                        <button onclick="returnAsset(<?= $row['id'] ?>)" class="bg-slate-100 border border-slate-300 text-slate-700 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm flex items-center gap-1">
                                            <i class="fa-solid fa-rotate-left"></i> Return
                                        </button>
                                    <?php endif; ?>

                                    <button onclick='openModal("edit", <?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="bg-white border border-slate-200 text-slate-600 hover:text-[#1b5a5a] hover:border-[#1b5a5a]/30 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div id="viewAssetModal" class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all" id="viewModalBox">
            <div class="p-5 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="text-lg font-bold flex items-center gap-2"><i class="fa-solid fa-eye text-teal-400"></i> Asset Details</h3>
                <button onclick="closeModal('viewAssetModal')" class="w-8 h-8 rounded-full bg-white/10 hover:bg-rose-500 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6 bg-slate-50/50">
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="col-span-2 md:col-span-1"><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Asset Name</p><p class="font-bold text-slate-800 text-sm" id="v_name"></p></div>
                    <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Sys ID / Category</p><p class="font-bold text-slate-800 text-sm" id="v_sys_cat"></p></div>
                    <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Serial / Barcode</p><p class="font-bold text-slate-800 text-sm" id="v_serial"></p></div>
                    <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Lifecycle / Cond.</p><p class="font-bold text-slate-800 text-sm" id="v_cond_life"></p></div>
                </div>

                <div class="border-t border-slate-200 pt-4 mb-4">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-3">Financial Information</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Acquired Date</p><p class="font-bold text-slate-800 text-sm mt-1" id="v_date"></p></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Purchase Value</p><p class="font-black text-slate-800 text-lg" id="v_purchase"></p></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Depreciated Value</p><p class="font-black text-emerald-600 text-lg" id="v_current"></p></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Invoice Number</p><p class="font-bold text-slate-800 text-sm mt-1" id="v_invoice"></p></div>
                    </div>
                </div>

                <div id="v_invoice_container" class="mb-4 text-center border-2 border-dashed border-slate-200 p-4 rounded-xl bg-white">
                    </div>

                <?php if($can_approve): ?>
                <div id="v_approval_section" class="border-t border-slate-200 pt-4 mt-4 hidden">
                    <p class="text-xs font-black text-slate-800 uppercase tracking-widest mb-3 text-center">Finance Action Required</p>
                    <div class="flex gap-3 justify-center">
                        <button id="v_btn_approve" class="bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-2.5 rounded-xl text-sm font-bold shadow-md transition flex items-center gap-2">
                            <i class="fa-solid fa-check"></i> Approve Invoice
                        </button>
                        <button id="v_btn_reject" class="bg-rose-500 hover:bg-rose-600 text-white px-8 py-2.5 rounded-xl text-sm font-bold shadow-md transition flex items-center gap-2">
                            <i class="fa-solid fa-xmark"></i> Reject Invoice
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="assetModal" class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all" id="modalBox">
            <div class="p-5 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 id="modalTitle" class="text-lg font-bold flex items-center gap-2"><i class="fa-solid fa-microchip text-teal-400"></i> Register Asset</h3>
                <button onclick="closeModal('assetModal')" class="w-8 h-8 rounded-full bg-white/10 hover:bg-rose-500 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="p-6 bg-slate-50/50">
                <form id="assetForm">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">System ID <span class="text-red-500">*</span></label>
                            <input type="text" id="inSys" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" placeholder="SYS-101">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Category <span class="text-red-500">*</span></label>
                            <select id="inCat" required placeholder="Select or type new category...">
                                <option value="">Select or type...</option>
                                <?php foreach($asset_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Asset Name / Model <span class="text-red-500">*</span></label>
                        <input type="text" id="inDesc" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" placeholder="Dell Latitude 5420">
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Serial / Barcode</label>
                            <input type="text" id="inBar" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" placeholder="SN-XXXXX">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Condition</label>
                            <select id="inCond" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm">
                                <option value="NEW">New</option>
                                <option value="USED">Used</option>
                                <option value="REPAIR">In Repair</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Lifecycle Status</label>
                            <select id="inLife" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm">
                                <option value="Available">Available</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Retired">Retired</option>
                                <option value="Scrapped">Scrapped</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4 mt-2 mb-4">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Financial Details & Invoicing</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                            <div class="col-span-1">
                                <label class="block text-xs font-bold text-slate-500 mb-1">Purchase Amt (₹) *</label>
                                <input type="number" step="0.01" id="inAmt" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" placeholder="0.00">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs font-bold text-slate-500 mb-1">Depreciation %</label>
                                <input type="number" step="0.01" id="inDep" value="20.00" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" placeholder="20.00">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-500 mb-1">Acquired Date</label>
                                <input type="date" id="assetDate" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Invoice No</label>
                                <input type="text" id="inInv" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" placeholder="INV-001">
                            </div>
                            <div id="invoiceUploadDiv">
                                <label class="block text-xs font-bold text-slate-500 mb-1">Upload Invoice (PDF/Img)</label>
                                <input type="file" id="inInvoiceFile" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm" accept=".pdf,.png,.jpg,.jpeg">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <input type="hidden" id="assetId">
                        <button type="button" onclick="closeModal('assetModal')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition shadow-sm">Cancel</button>
                        <button type="button" onclick="submitAsset()" id="saveBtn" class="bg-[#1b5a5a] hover:bg-[#144343] text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md transition">Save Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="allocateModal" class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-5 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="text-lg font-bold flex items-center gap-2"><i class="fa-solid fa-user-tag text-amber-400"></i> Assign Asset</h3>
                <button onclick="closeModal('allocateModal')" class="w-8 h-8 rounded-full bg-white/10 hover:bg-rose-500 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6 bg-slate-50/50">
                <input type="hidden" id="allocAssetId">
                <p class="text-sm text-slate-600 mb-4">Assigning <strong id="allocAssetName" class="text-slate-800"></strong> to an employee.</p>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Select Employee <span class="text-red-500">*</span></label>
                    <select id="allocEmp" required placeholder="Search employee...">
                        <option value="">Select Employee...</option>
                        <?php foreach($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> [<?= htmlspecialchars($emp['emp_id_code'] ?? 'ID N/A') ?>]</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Allocation Date</label>
                    <input type="date" id="allocDate" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 transition shadow-sm">
                </div>

                <div class="flex justify-end gap-3">
                    <button onclick="closeModal('allocateModal')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition shadow-sm">Cancel</button>
                    <button onclick="submitAllocation()" id="allocBtn" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md transition">Assign</button>
                </div>
            </div>
        </div>
    </div>

    <div id="qrModal" class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden text-center p-6 relative">
            <button onclick="closeModal('qrModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 hover:text-rose-600 flex items-center justify-center transition-colors text-slate-500">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Asset QR Code</h3>
            <p class="text-sm font-bold text-teal-600 mb-4" id="qrSysId"></p>
            <div class="border-2 border-slate-100 p-2 rounded-xl inline-block shadow-sm">
                <img id="qrImage" src="" alt="QR Code" class="w-48 h-48 object-contain">
            </div>
            <div class="mt-6">
                <a id="qrDownload" href="" download class="w-full block bg-slate-800 hover:bg-slate-900 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition"><i class="fa-solid fa-download mr-1"></i> Download Tag</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <script>
        let categorySelect; 
        let empSelect;
        const csrfToken = "<?= $_SESSION['csrf_token'] ?>";
        const allAssets = <?= json_encode($assets, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            if (!primarySidebar || !mainContent) return;

            const updateMargin = () => {
                if (window.innerWidth <= 1024) {
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
        
        document.addEventListener('DOMContentLoaded', () => {
            setupLayoutObserver();
            
            categorySelect = new TomSelect("#inCat", { 
                create: true, 
                sortField: { field: "text", direction: "asc" } 
            });
            empSelect = new TomSelect("#allocEmp", { create: false, sortField: { field: "text", direction: "asc" } });
        });

        function filterAssets() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.asset-row').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        function exportCSV() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "System ID,Category,Asset Name,Serial Number,Invoice Number,Purchase Amount (Rs),Current Depreciated Value (Rs),Allocation Status,Assigned To,Lifecycle Status,Finance Approval Status,Acquired Date\n";
            
            allAssets.forEach(a => {
                let row = [
                    a.system_id || 'N/A',
                    a.category || 'N/A',
                    a.asset_name || 'N/A',
                    a.barcode || 'N/A',
                    a.invoice_number || 'N/A',
                    a.amount || 0,
                    a.current_value || 0,
                    a.allocation_status || 'Available',
                    a.assigned_name || 'N/A',
                    a.lifecycle_status || 'Available',
                    a.request_status || 'Pending',
                    a.acquisition_date || 'N/A'
                ];
                row = row.map(str => `"${String(str).replace(/"/g, '""')}"`);
                csvContent += row.join(",") + "\n";
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "Enterprise_Asset_Ledger.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function openViewModal(data) {
            document.getElementById('v_name').innerText = data.asset_name || 'N/A';
            document.getElementById('v_sys_cat').innerText = (data.system_id || 'N/A') + ' / ' + (data.category || 'N/A');
            document.getElementById('v_serial').innerText = data.barcode || 'N/A';
            document.getElementById('v_cond_life').innerText = (data.status || 'N/A') + ' / ' + (data.lifecycle_status || 'N/A');
            
            let acq_date_display = 'N/A';
            if (data.display_date && data.display_date.trim() !== '') {
                acq_date_display = data.display_date;
            } else if (data.acquisition_date && data.acquisition_date.trim() !== '') {
                acq_date_display = data.acquisition_date;
            }
            document.getElementById('v_date').innerText = acq_date_display;

            document.getElementById('v_purchase').innerText = '₹' + parseFloat(data.amount || 0).toFixed(2);
            document.getElementById('v_current').innerText = '₹' + parseFloat(data.current_value || 0).toFixed(2);
            document.getElementById('v_invoice').innerText = data.invoice_number || 'N/A';

            let invContainer = document.getElementById('v_invoice_container');
            if(data.invoice_file && data.invoice_file !== '') {
                let path = '<?= $root_path ?>' + data.invoice_file;
                invContainer.innerHTML = `<p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Attached Document</p><a href="${path}" target="_blank" class="inline-flex items-center text-sm font-bold text-blue-600 hover:text-blue-800 bg-blue-50 px-4 py-2 rounded-lg border border-blue-100 transition shadow-sm"><i class="fa-solid fa-file-invoice me-2"></i> View Uploaded Invoice</a>`;
            } else {
                invContainer.innerHTML = '<p class="text-xs text-slate-500 italic"><i class="fa-solid fa-circle-info mr-1"></i> No physical invoice document was uploaded for this asset.</p>';
            }

            let appSec = document.getElementById('v_approval_section');
            if(appSec) {
                if(data.request_status === 'Pending Accountant') {
                    appSec.classList.remove('hidden');
                    document.getElementById('v_btn_approve').onclick = () => { closeViewAndApprove(data.id, 'Approved'); };
                    document.getElementById('v_btn_reject').onclick = () => { closeViewAndApprove(data.id, 'Rejected'); };
                } else {
                    appSec.classList.add('hidden');
                }
            }

            document.getElementById('viewAssetModal').classList.add('active');
        }

        function closeViewAndApprove(id, status) {
            closeModal('viewAssetModal');
            processApproval(id, status);
        }

        function openModal(action, data = null) {
            window.currentAction = action;
            document.getElementById('modalTitle').innerHTML = (action === 'edit') ? '<i class="fa-solid fa-pen-to-square text-teal-400"></i> Edit Asset' : '<i class="fa-solid fa-microchip text-teal-400"></i> Register Asset';
            
            if(action === 'edit' && data) {
                document.getElementById('assetId').value = data.id;
                document.getElementById('inSys').value = data.system_id;
                document.getElementById('inDesc').value = data.asset_name;
                document.getElementById('inBar').value = data.barcode;
                document.getElementById('inInv').value = data.invoice_number;
                document.getElementById('inAmt').value = data.amount;
                document.getElementById('inDep').value = data.depreciation_rate;
                document.getElementById('assetDate').value = data.acquisition_date || ''; 
                document.getElementById('inCond').value = data.status;
                document.getElementById('inLife').value = data.lifecycle_status || 'Available';
                
                document.getElementById('invoiceUploadDiv').style.display = 'none'; 
                
                if(categorySelect) {
                    categorySelect.addOption({value: data.category, text: data.category});
                    categorySelect.setValue(data.category);
                }
            } else {
                document.getElementById('assetForm').reset();
                document.getElementById('inCond').value = 'NEW';
                document.getElementById('inLife').value = 'Available';
                document.getElementById('inDep').value = '20.00';
                document.getElementById('assetDate').value = new Date().toISOString().split('T')[0];
                document.getElementById('invoiceUploadDiv').style.display = 'block';
                if(categorySelect) categorySelect.clear();
            }
            
            document.getElementById('assetModal').classList.add('active');
        }

        function openAllocateModal(id, name) {
            document.getElementById('allocAssetId').value = id;
            document.getElementById('allocAssetName').innerText = name;
            document.getElementById('allocDate').value = new Date().toISOString().split('T')[0];
            if(empSelect) empSelect.clear();
            document.getElementById('allocateModal').classList.add('active');
        }

        function viewQR(imgPath, sysId) {
            document.getElementById('qrImage').src = imgPath;
            document.getElementById('qrSysId').innerText = sysId;
            document.getElementById('qrDownload').href = imgPath;
            document.getElementById('qrModal').classList.add('active');
        }

        function closeModal(modalId) { 
            document.getElementById(modalId).classList.remove('active'); 
        }

        async function submitAsset() {
            const btn = document.getElementById('saveBtn');
            const sys = document.getElementById('inSys').value;
            const cat = document.getElementById('inCat').value;
            const desc = document.getElementById('inDesc').value;
            const amt = document.getElementById('inAmt').value;
            const dateVal = document.getElementById('assetDate').value;

            if(!sys || !desc || !amt || !cat) {
                Swal.fire('Missing Fields', 'System ID, Category, Name, and Amount are required.', 'warning');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken); 
            formData.append('action', window.currentAction);
            formData.append('id', document.getElementById('assetId').value);
            formData.append('sys', sys);
            formData.append('cat', cat);
            formData.append('desc', desc);
            formData.append('bar', document.getElementById('inBar').value);
            formData.append('inv', document.getElementById('inInv').value);
            formData.append('amt', amt);
            formData.append('dep_rate', document.getElementById('inDep').value);
            formData.append('date', dateVal);
            formData.append('cond', document.getElementById('inCond').value);
            formData.append('lifecycle', document.getElementById('inLife').value);

            const fileInput = document.getElementById('inInvoiceFile');
            if(fileInput && fileInput.files[0]) {
                formData.append('invoice_file', fileInput.files[0]);
            }

            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const text = await res.text();
                let result;
                try { result = JSON.parse(text); } 
                catch(e) { throw new Error(text); }
                
                if(result.success) {
                    Swal.fire({ icon: 'success', title: 'Secured!', showConfirmButton: false, timer: 1500 }).then(() => location.reload());
                } else {
                    Swal.fire('Database Error', result.error || 'Failed to save asset', 'error');
                    btn.disabled = false; btn.innerHTML = 'Save Asset';
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'Server processing failed. Ensure upload directories exist.', 'error');
                btn.disabled = false; btn.innerHTML = 'Save Asset';
            }
        }

        async function submitAllocation() {
            const btn = document.getElementById('allocBtn');
            const empId = document.getElementById('allocEmp').value;
            if(!empId) { Swal.fire('Error', 'Please select an employee.', 'warning'); return; }

            btn.disabled = true; btn.innerHTML = 'Assigning...';

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        csrf_token: csrfToken, 
                        action: 'allocate',
                        asset_id: document.getElementById('allocAssetId').value,
                        employee_id: empId,
                        alloc_date: document.getElementById('allocDate').value
                    })
                });
                const result = await res.json();
                if(result.success) location.reload();
                else Swal.fire('Error', result.error, 'error');
            } catch(e) { Swal.fire('Error', 'Connection failed', 'error'); }
            btn.disabled = false; btn.innerHTML = 'Assign';
        }

        async function returnAsset(id) {
            const confirmed = await Swal.fire({
                title: 'Return Asset?', text: "Mark this asset as returned to IT inventory?", icon: 'question',
                showCancelButton: true, confirmButtonColor: '#1b5a5a'
            });
            if(confirmed.isConfirmed) {
                const res = await fetch(window.location.href, {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'return', asset_id: id, csrf_token: csrfToken }) 
                });
                const result = await res.json();
                if(result.success) location.reload();
            }
        }

        async function processApproval(id, status) {
            const result = await Swal.fire({
                title: 'Confirm Invoice', text: `Mark invoice as ${status}?`, icon: 'question',
                showCancelButton: true, confirmButtonColor: '#1b5a5a'
            });

            if (result.isConfirmed) {
                const res = await fetch(window.location.href, {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'approve_reject', asset_id: id, new_status: status, csrf_token: csrfToken }) 
                });
                const response = await res.json();
                if(response.success) location.reload();
            }
        }
    </script>
</body>
</html>