<?php
// Fixes "headers already sent" error by turning on output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database and Path configuration
if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    $sidebarPath = '';
    $headerPath = '';
}

// Fetch User Role for Access Control
$current_user_role = $_SESSION['role'] ?? 'Guest'; 

// Fetch strictly ONLY 'Sales Executives' for the Dropdown
$executives = [];
if(isset($conn)) {
    // UPDATED QUERY: Only fetches users whose role is strictly 'Sales Executive'
    $exec_query = mysqli_query($conn, "SELECT name, role FROM users WHERE role = 'Sales Executive' ORDER BY name ASC");
    if($exec_query) {
        while($row = mysqli_fetch_assoc($exec_query)) {
            $executives[] = $row['name'];
        }
    }
}

// Handle API requests (CRUD operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if(ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Invalid action'];

    try {
        if ($action === 'load') {
            $res = mysqli_query($conn, "SELECT * FROM crm_clients ORDER BY created_at DESC");
            $clients = [];
            if($res) {
                while($row = mysqli_fetch_assoc($res)) {
                    $clients[] = $row;
                }
            }
            $response = ['success' => true, 'data' => $clients];
        } 
        elseif ($action === 'create') {
            $id = mysqli_real_escape_string($conn, $_POST['id'] ?? uniqid()); 
            $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
            $company_name = mysqli_real_escape_string($conn, $_POST['company_name'] ?? '');
            $designation = mysqli_real_escape_string($conn, $_POST['designation'] ?? '');
            $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
            $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
            
            // Getting official mail id correctly from AJAX POST
            $official_mail_id = mysqli_real_escape_string($conn, $_POST['official_mail_id'] ?? '');
            
            $gst_number = mysqli_real_escape_string($conn, $_POST['gst_number'] ?? '');
            $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
            $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
            
            $source = mysqli_real_escape_string($conn, $_POST['source'] ?? '');
            $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'New');
            $executive = mysqli_real_escape_string($conn, $_POST['executive'] ?? '');
            $deal_value = floatval($_POST['deal_value'] ?? 0.00);

           $sql = "INSERT INTO crm_clients 
                    (id, name, company_name, designation, phone, email, official_mail, gst_number, location, description, source, status, executive, deal_value) 
                    VALUES 
                    ('$id', '$name', '$company_name', '$designation', '$phone', '$email', '$official_mail_id', '$gst_number', '$location', '$description', '$source', '$status', '$executive', $deal_value)";
            if(mysqli_query($conn, $sql)) {
                $response = ['success' => true, 'message' => 'Client created successfully'];
            } else {
                $response = ['success' => false, 'message' => mysqli_error($conn)];
            }
        } 
        elseif ($action === 'update') {
            $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
            $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
            $company_name = mysqli_real_escape_string($conn, $_POST['company_name'] ?? '');
            $designation = mysqli_real_escape_string($conn, $_POST['designation'] ?? '');
            $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
            $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
            $official_mail_id = mysqli_real_escape_string($conn, $_POST['official_mail_id'] ?? '');
            $gst_number = mysqli_real_escape_string($conn, $_POST['gst_number'] ?? '');
            $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
            $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');

            $source = mysqli_real_escape_string($conn, $_POST['source'] ?? '');
            $status = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
            $executive = mysqli_real_escape_string($conn, $_POST['executive'] ?? '');
            $deal_value = floatval($_POST['deal_value'] ?? 0.00);

            $sql = "UPDATE crm_clients SET 
                    name='$name', 
                    company_name='$company_name', 
                    designation='$designation', 
                    phone='$phone', 
                    email='$email', 
                    official_mail='$official_mail_id', 
                    gst_number='$gst_number', 
                    location='$location', 
                    description='$description', 
                    source='$source', 
                    status='$status', 
                    executive='$executive', 
                    deal_value=$deal_value 
                    WHERE id='$id'";
                    
            if(mysqli_query($conn, $sql)) {
                $response = ['success' => true, 'message' => 'Client updated successfully'];
            } else {
                $response = ['success' => false, 'message' => mysqli_error($conn)];
            }
        } 
        elseif ($action === 'delete') {
            $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
            $sql = "DELETE FROM crm_clients WHERE id='$id'";
            if(mysqli_query($conn, $sql)) {
                $response = ['success' => true, 'message' => 'Client deleted successfully'];
            } else {
                $response = ['success' => false, 'message' => mysqli_error($conn)];
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Server error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales CRM - Client Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --bg-hover: #f1f5f9;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --primary-color: #1b5a5a;
            --primary-hover: #134d4d;
            --danger-color: #ef4444;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --purple-color: #8b5cf6;
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 95px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-primary); line-height: 1.5; -webkit-font-smoothing: antialiased; transition: background-color var(--transition); }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; min-height: 100vh; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        input, select, textarea { font-family: inherit; }

        h1 { font-size: 1.875rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.025em; }
        h2 { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); }

        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .header-content p { color: var(--text-secondary); margin-top: 0.5rem; font-size: 0.95rem; }
        .header-actions { display: flex; gap: 1rem; align-items: center; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: var(--border-radius-sm); font-weight: 500; font-size: 0.875rem; transition: var(--transition); }
        .btn:focus { outline: 2px solid var(--primary-color); outline-offset: 2px; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-primary { background-color: var(--primary-color); color: white; box-shadow: var(--shadow-sm); }
        .btn-primary:hover:not(:disabled) { background-color: var(--primary-hover); transform: translateY(-1px); }
        .btn-secondary { background-color: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-secondary:hover:not(:disabled) { background-color: var(--bg-hover); }
        .btn-icon { padding: 0.5rem; border-radius: var(--border-radius-sm); color: var(--text-secondary); background-color: transparent; }
        .btn-icon:hover { background-color: var(--bg-hover); color: var(--primary-color); }
        .btn-icon.danger:hover { background-color: #fee2e2; color: var(--danger-color); }
        .btn-icon.success:hover { background-color: #dcfce7; color: var(--success-color); }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .metric-card { background-color: var(--bg-surface); border-radius: var(--border-radius); padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1.25rem; transition: var(--transition); }
        .metric-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .metric-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .metric-icon.total { background-color: #e0f2fe; color: var(--info-color); }
        .metric-icon.new { background-color: #fef3c7; color: var(--warning-color); }
        .metric-icon.won { background-color: #dcfce7; color: var(--success-color); }
        .metric-icon.value { background-color: #f3e8ff; color: var(--purple-color); }
        
        .metric-content h3 { font-size: 0.875rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 0.25rem; }
        .metric-content .value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }

        .controls-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .search-wrapper { position: relative; flex-grow: 1; max-width: 400px; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; }
        .search-input { width: 100%; padding: 0.625rem 1rem 0.625rem 2.5rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); background-color: var(--bg-surface); color: var(--text-primary); font-size: 0.875rem; transition: var(--transition); }
        .search-input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }
        .filter-group { display: flex; gap: 1rem; align-items: center; }
        .filter-select { padding: 0.625rem 2rem 0.625rem 1rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); background-color: var(--bg-surface); color: var(--text-primary); font-size: 0.875rem; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1rem; }
        .filter-select:focus { outline: none; border-color: var(--primary-color); }

        .table-container { background-color: var(--bg-surface); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden; position: relative; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: var(--bg-hover); color: var(--text-secondary); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr { transition: var(--transition); }
        tbody tr:hover { background-color: var(--bg-hover); }

        .client-info { display: flex; align-items: center; gap: 1rem; }
        .client-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--theme-light, #e0f2f1); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1rem; flex-shrink: 0; }
        .client-details { display: flex; flex-direction: column; }
        .client-name { font-weight: 600; color: var(--text-primary); margin-bottom: 0.125rem; }
        .client-email { color: var(--text-secondary); font-size: 0.8125rem; }

        .status-badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-new { background-color: #dbeafe; color: #1e40af; }
        .status-contacted { background-color: #fef3c7; color: #b45309; }
        .status-qualified { background-color: #e0e7ff; color: #4338ca; }
        .status-proposal { background-color: #fae8ff; color: #7e22ce; }
        .status-won { background-color: #dcfce7; color: #15803d; }
        .status-lost { background-color: #fee2e2; color: #b91c1c; }

        .deal-value { font-weight: 600; color: var(--text-primary); }
        .table-actions { display: flex; gap: 0.25rem; justify-content: flex-end; }

        .empty-state { padding: 4rem 2rem; text-align: center; color: var(--text-secondary); }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 1rem; color: var(--text-muted); opacity: 0.5; }

        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background-color: var(--bg-surface); border-top: 1px solid var(--border-color); }
        .page-info { font-size: 0.875rem; color: var(--text-secondary); }
        .page-controls { display: flex; gap: 0.5rem; }

        /* Modal Base - Magic Flexbox Fix Applied Here for Scroll & Save Button Visibility */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.3s ease; backdrop-filter: blur(2px); padding: 1rem; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        .modal-content { 
            background-color: var(--bg-surface); 
            border-radius: var(--border-radius); 
            width: 100%; 
            max-width: 700px; 
            box-shadow: var(--shadow-lg); 
            transform: translateY(20px); 
            transition: transform 0.3s ease; 
            display: flex; 
            flex-direction: column; 
            max-height: 90vh; /* Limits maximum height to viewport */
        }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        
        .modal-header { 
            padding: 1.5rem; 
            border-bottom: 1px solid var(--border-color); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex: 0 0 auto; /* Prevents header from shrinking or growing */
        }
        .modal-header h2 { margin: 0; font-size: 1.25rem; }
        .close-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.5rem; border-radius: var(--border-radius-sm); transition: var(--transition); }
        .close-btn:hover { background-color: var(--bg-hover); color: var(--text-primary); }
        
        .modal-body { 
            padding: 1.5rem; 
            overflow-y: auto; 
            flex: 1 1 auto; /* Pushes footer down and allows scrolling inside */
            min-height: 0; /* Important: Allows the body to scroll and not stretch parent flex container */
        }
        
        .modal-footer { 
            padding: 1.5rem; 
            border-top: 1px solid var(--border-color); 
            display: flex; 
            justify-content: flex-end; 
            gap: 1rem; 
            background-color: #f8fafc;
            flex: 0 0 auto; /* Prevents footer from shrinking and hiding below screen */
        }
        
        #confirmModal .modal-content { max-width: 400px; }

        .form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); background-color: var(--bg-body); color: var(--text-primary); font-size: 0.875rem; transition: var(--transition); }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); background-color: var(--bg-surface); }
        .form-group textarea { resize: vertical; }
        
        /* Readonly styling for View Mode */
        .form-group input:read-only, .form-group textarea:read-only { background-color: var(--bg-hover); cursor: default; }
        .form-group select:disabled { background-color: var(--bg-hover); cursor: default; opacity: 1; color: var(--text-primary); }

        .loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 255, 0.7); display: none; align-items: center; justify-content: center; z-index: 10; }
        .loading-overlay.active { display: flex; }
        .spinner { width: 40px; height: 40px; border: 3px solid var(--border-color); border-top-color: var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 1.5rem; } }
        @media (max-width: 768px) { .header { flex-direction: column; align-items: flex-start; gap: 1rem; } .controls-bar { flex-direction: column; align-items: stretch; } .search-wrapper { max-width: 100%; } .filter-group { flex-wrap: wrap; } .form-row { flex-direction: column; gap: 0; } .form-row .form-group { margin-bottom: 1.5rem; } }
    </style>
</head>
<body>
    <?php if ($sidebarPath) include $sidebarPath; ?>
    <?php if ($headerPath) include $headerPath; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-content">
                <h1>Client Management</h1>
                <p>Track leads, manage deals, and build customer relationships.</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" id="addClientBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Client
                </button>
            </div>
        </header>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon total">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="metric-content">
                    <h3>Total Clients</h3>
                    <div class="value" id="metricTotal">0</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon new">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <div class="metric-content">
                    <h3>New Leads</h3>
                    <div class="value" id="metricNew">0</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon won">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-content">
                    <h3>Deals Won</h3>
                    <div class="value" id="metricWon">0</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon value">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-content">
                    <h3>Pipeline Value</h3>
                    <div class="value" id="metricValue">₹0</div>
                </div>
            </div>
        </div>

        <div class="controls-bar">
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" class="search-input" id="searchInput" placeholder="Search clients, emails, or phone...">
            </div>
            <div class="filter-group">
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="New">New</option>
                    <option value="Contacted">Contacted</option>
                    <option value="Qualified">Qualified</option>
                    <option value="Proposal">Proposal</option>
                    <option value="Won">Won</option>
                    <option value="Lost">Lost</option>
                </select>
                <select class="filter-select" id="sourceFilter">
                    <option value="all">All Sources</option>
                    <option value="Website">Website</option>
                    <option value="Referral">Referral</option>
                    <option value="Cold Call">Cold Call</option>
                    <option value="Social Media">Social Media</option>
                    <option value="Trade Show">Trade Show</option>
                    <option value="Email Campaign">Email Campaign</option>
                </select>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="tableLoading">
                <div class="spinner"></div>
            </div>
            <table id="clientsTable">
                <thead>
                    <tr>
                        <th>Client Details</th>
                        <th>Contact Info</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Executive</th>
                        <th>Deal Value</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody"> </tbody>
            </table>
            
            <div class="empty-state" id="emptyState" style="display: none;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3>No clients found</h3>
                <p>We couldn't find any clients matching your current filters.</p>
            </div>

            <div class="pagination">
                <div class="page-info" id="pageInfo">Showing 0 to 0 of 0 entries</div>
                <div class="page-controls">
                    <button class="btn btn-secondary" id="prevPageBtn" disabled>Previous</button>
                    <button class="btn btn-secondary" id="nextPageBtn" disabled>Next</button>
                </div>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="formModal">
        <form id="clientForm" class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Client</h2>
                <button type="button" class="close-btn" id="closeFormBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="clientId">
                
                <h4 style="font-size: 0.75rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Basic Details</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="clientName">Full Name / Company *</label>
                        <input type="text" id="clientName" required placeholder="e.g. John Doe">
                    </div>
                    <div class="form-group">
                        <label for="clientCompany">Company Name</label>
                        <input type="text" id="clientCompany" placeholder="e.g. Neoera Infotech">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="clientDesignation">Designation</label>
                        <input type="text" id="clientDesignation" placeholder="e.g. Managing Director">
                    </div>
                    <div class="form-group">
                        <label for="clientGst">GSTN Number</label>
                        <input type="text" id="clientGst" placeholder="e.g. 22AAAAA0000A1Z5">
                    </div>
                </div>
                
                <h4 style="font-size: 0.75rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; margin-bottom: 1rem; margin-top: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Contact Details</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="clientPhone">Phone Number *</label>
                        <input type="tel" id="clientPhone" required placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label for="clientLocation">Location / City</label>
                        <input type="text" id="clientLocation" placeholder="e.g. Coimbatore, Tamil Nadu">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="clientEmail">Personal Mail ID *</label>
                        <input type="email" id="clientEmail" required placeholder="contact@acme.com">
                    </div>
                    <div class="form-group">
                        <label for="clientOfficialEmail">Official Mail ID</label>
                        <input type="email" id="clientOfficialEmail" placeholder="work@acme.com">
                    </div>
                </div>
                
                <h4 style="font-size: 0.75rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; margin-bottom: 1rem; margin-top: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Deal Specifications</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="clientSource">Lead Source *</label>
                        <select id="clientSource" required>
                            <option value="" disabled selected>Select Source</option>
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="Cold Call">Cold Call</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Trade Show">Trade Show</option>
                            <option value="Email Campaign">Email Campaign</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="clientStatus">Status *</label>
                        <select id="clientStatus" required>
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Won">Won</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="clientExecutive">Assigned Executive *</label>
                        <select id="clientExecutive" required>
                            <option value="" disabled selected>Select Executive</option>
                            <?php foreach($executives as $exec): ?>
                                <option value="<?php echo htmlspecialchars($exec); ?>"><?php echo htmlspecialchars($exec); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="clientValue">Deal Value (₹) *</label>
                        <input type="number" id="clientValue" required min="0" step="0.01" placeholder="50000">
                    </div>
                </div>

                <div class="form-group">
                    <label for="clientDescription">Description / Notes</label>
                    <textarea id="clientDescription" rows="3" placeholder="Add any background details or notes about the client here..."></textarea>
                </div>

            </div>
            <div class="modal-footer" id="modalFooter">
                <button type="button" class="btn btn-secondary" id="cancelFormBtn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveClientBtn">Save Client</button>
            </div>
        </form>
    </div>

    <div class="modal-overlay" id="viewModal">
        <div class="modal-content !max-w-2xl">
            <div class="modal-header bg-[#1b5a5a] text-white">
                <h2 class="text-lg font-bold flex items-center gap-2"><i class="fa-regular fa-address-card"></i> Client Overview</h2>
                <button type="button" class="text-white hover:bg-white/20 p-2 rounded-full transition" onclick="app.closeViewModal()"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body bg-slate-50/50 custom-scrollbar">
                
                <div class="flex items-center justify-between mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-teal-100 text-[#1b5a5a] border-2 border-white shadow flex items-center justify-center text-2xl font-black uppercase" id="viewInitials">--</div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 leading-tight" id="viewName">-</h3>
                            <p class="text-sm font-medium text-slate-500" id="viewDesignationCompany">-</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="status-badge" id="viewStatusBadge">Status</span>
                        <p class="text-xs text-slate-400 font-bold mt-2">Created: <span id="viewDate">--</span></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">Deal Value</p>
                        <p class="font-black text-slate-800 text-lg" id="viewValue">₹0.00</p>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">Assigned Executive</p>
                        <p class="font-bold text-slate-800 text-sm flex items-center gap-2 mt-1"><i class="fa-solid fa-user-tie text-[#1b5a5a]"></i> <span id="viewExecutive">-</span></p>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-4">
                    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50"><h4 class="text-xs font-bold text-[#1b5a5a] uppercase"><i class="fa-solid fa-address-book mr-1"></i> Contact Info</h4></div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                        <div><p class="text-xs text-slate-500 font-medium mb-0.5">Personal Mail ID</p><p class="font-semibold text-slate-800 break-all" id="viewEmail">-</p></div>
                        <div><p class="text-xs text-slate-500 font-medium mb-0.5">Official Mail ID</p><p class="font-semibold text-slate-800 break-all" id="viewOfficialEmail">-</p></div>
                        <div><p class="text-xs text-slate-500 font-medium mb-0.5">Phone Number</p><p class="font-semibold text-slate-800" id="viewPhone">-</p></div>
                        <div><p class="text-xs text-slate-500 font-medium mb-0.5">Location</p><p class="font-semibold text-slate-800" id="viewLocation">-</p></div>
                        <div><p class="text-xs text-slate-500 font-medium mb-0.5">GSTN Number</p><p class="font-semibold text-slate-800" id="viewGst">-</p></div>
                        <div><p class="text-xs text-slate-500 font-medium mb-0.5">Lead Source</p><p class="font-semibold text-slate-800" id="viewSource">-</p></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50"><h4 class="text-xs font-bold text-[#1b5a5a] uppercase"><i class="fa-solid fa-align-left mr-1"></i> Description</h4></div>
                    <div class="p-4">
                        <p class="text-sm text-slate-600 leading-relaxed" id="viewDescription">No description available.</p>
                    </div>
                </div>

            </div>
            <div class="modal-footer justify-end">
                <button type="button" class="btn btn-secondary" onclick="app.closeViewModal()">Close Overview</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Client</h2>
                <button class="close-btn" id="closeConfirmBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this client? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelConfirmBtn">Cancel</button>
                <button type="button" class="btn btn-primary" style="background-color: var(--danger-color);" id="executeDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        class ClientManager {
            constructor() {
                // State
                this.clients = [];
                this.filteredClients = [];
                this.currentPage = 1;
                this.itemsPerPage = 10;
                this.clientToDelete = null;
                
                // Role Based Access (Passed from PHP)
                this.userRole = "<?php echo $current_user_role; ?>";

                // DOM Elements
                this.cacheDOM();
                this.bindEvents();
                
                // Initialize
                this.loadData();
            }

            cacheDOM() {
                // Table & UI
                this.tableBody = document.getElementById('tableBody');
                this.emptyState = document.getElementById('emptyState');
                this.loadingOverlay = document.getElementById('tableLoading');
                
                // Metrics
                this.metricTotal = document.getElementById('metricTotal');
                this.metricNew = document.getElementById('metricNew');
                this.metricWon = document.getElementById('metricWon');
                this.metricValue = document.getElementById('metricValue');
                
                // Controls
                this.searchInput = document.getElementById('searchInput');
                this.statusFilter = document.getElementById('statusFilter');
                this.sourceFilter = document.getElementById('sourceFilter');
                
                // Pagination
                this.pageInfo = document.getElementById('pageInfo');
                this.prevBtn = document.getElementById('prevPageBtn');
                this.nextBtn = document.getElementById('nextPageBtn');
                
                // Modals & Buttons
                this.addBtn = document.getElementById('addClientBtn');
                
                this.formModal = document.getElementById('formModal');
                this.viewModal = document.getElementById('viewModal');
                this.clientForm = document.getElementById('clientForm');
                this.modalTitle = document.getElementById('modalTitle');
                this.modalFooter = document.getElementById('modalFooter');
                this.saveClientBtn = document.getElementById('saveClientBtn');
                this.closeFormBtn = document.getElementById('closeFormBtn');
                this.cancelFormBtn = document.getElementById('cancelFormBtn');
                
                this.confirmModal = document.getElementById('confirmModal');
                this.closeConfirmBtn = document.getElementById('closeConfirmBtn');
                this.cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
                this.executeDeleteBtn = document.getElementById('executeDeleteBtn');

                // Form Inputs mapping
                this.inputs = {
                    id: document.getElementById('clientId'),
                    name: document.getElementById('clientName'),
                    company_name: document.getElementById('clientCompany'),
                    designation: document.getElementById('clientDesignation'),
                    phone: document.getElementById('clientPhone'),
                    email: document.getElementById('clientEmail'),
                    official_mail_id: document.getElementById('clientOfficialEmail'),
                    gst_number: document.getElementById('clientGst'),
                    location: document.getElementById('clientLocation'),
                    source: document.getElementById('clientSource'),
                    status: document.getElementById('clientStatus'),
                    executive: document.getElementById('clientExecutive'),
                    value: document.getElementById('clientValue'),
                    description: document.getElementById('clientDescription')
                };
            }

            bindEvents() {
                // Controls
                this.searchInput.addEventListener('input', () => this.handleFilter());
                this.statusFilter.addEventListener('change', () => this.handleFilter());
                this.sourceFilter.addEventListener('change', () => this.handleFilter());
                
                // Pagination
                this.prevBtn.addEventListener('click', () => this.changePage(-1));
                this.nextBtn.addEventListener('click', () => this.changePage(1));
                
                // Modals
                if(this.addBtn) {
                    this.addBtn.addEventListener('click', () => this.openFormModal());
                }
                this.closeFormBtn.addEventListener('click', () => this.closeFormModal());
                this.cancelFormBtn.addEventListener('click', () => this.closeFormModal());
                this.clientForm.addEventListener('submit', (e) => this.handleSubmit(e));
                
                this.closeConfirmBtn.addEventListener('click', () => this.closeConfirmModal());
                this.cancelConfirmBtn.addEventListener('click', () => this.closeConfirmModal());
                this.executeDeleteBtn.addEventListener('click', () => this.executeDelete());

                // Click outside modal to close
                window.addEventListener('click', (e) => {
                    if (e.target === this.formModal) this.closeFormModal();
                    if (e.target === this.confirmModal) this.closeConfirmModal();
                    if (e.target === this.viewModal) this.closeViewModal();
                });
            }

            // --- DATA LOGIC (AJAX) ---
            async loadData() {
                this.showLoading(true);
                try {
                    const fd = new FormData();
                    fd.append('action', 'load');
                    const response = await fetch('', { method: 'POST', body: fd });
                    const result = await response.json();
                    
                    if(result.success) {
                        this.clients = result.data;
                        this.handleFilter();
                        this.updateMetrics();
                    } else {
                        console.error('Failed to load data:', result.message);
                    }
                } catch (error) {
                    console.error('Error fetching data:', error);
                } finally {
                    this.showLoading(false);
                }
            }

            async saveData(action, data) {
                this.showLoading(true);
                try {
                    const fd = new FormData();
                    fd.append('action', action);
                    for (const key in data) {
                        fd.append(key, data[key]);
                    }
                    
                    const response = await fetch('', { method: 'POST', body: fd });
                    const result = await response.json();
                    return result;
                } catch (error) {
                    console.error('Error saving data:', error);
                    return { success: false, message: error.message };
                } finally {
                    this.showLoading(false);
                }
            }

            // --- RENDER LOGIC ---
            render() {
                this.tableBody.innerHTML = '';
                
                if (this.filteredClients.length === 0) {
                    this.emptyState.style.display = 'block';
                    this.updatePagination(0);
                    return;
                }
                
                this.emptyState.style.display = 'none';
                
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                const pageData = this.filteredClients.slice(start, end);

                // Role Checks
                const canDelete = (this.userRole === 'Sales Manager' || this.userRole === 'Admin' || this.userRole === 'Manager');

                pageData.forEach(client => {
                    const tr = document.createElement('tr');
                    
                    const initials = this.escapeHTML(client.name).split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                    const formattedValue = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(client.deal_value);
                    const subtext = client.company_name || client.designation || 'Individual';

                    // Generate Buttons HTML
                    let actionsHtml = `
                        <div class="table-actions">
                            <button class="btn-icon success" title="View Details" onclick="app.viewClient('${client.id}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>

                            <button class="btn-icon" title="Edit" onclick="app.editClient('${client.id}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                    `;

                    // DELETE BUTTON
                    if (canDelete) {
                        actionsHtml += `
                            <button class="btn-icon danger" title="Delete" onclick="app.requestDelete('${client.id}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        `;
                    }

                    actionsHtml += `</div>`;

                    tr.innerHTML = `
                        <td>
                            <div class="client-info">
                                <div class="client-avatar">${initials}</div>
                                <div class="client-details">
                                    <span class="client-name">${this.escapeHTML(client.name)}</span>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">${this.escapeHTML(subtext)}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="client-details">
                                <span class="client-name" style="font-weight: 500; font-size: 0.8125rem;">${this.escapeHTML(client.phone)}</span>
                                <span class="client-email">${this.escapeHTML(client.email)}</span>
                            </div>
                        </td>
                        <td>${this.escapeHTML(client.source)}</td>
                        <td><span class="status-badge status-${client.status.toLowerCase()}">${this.escapeHTML(client.status)}</span></td>
                        <td>${this.escapeHTML(client.executive)}</td>
                        <td class="deal-value">${formattedValue}</td>
                        <td>${actionsHtml}</td>
                    `;
                    this.tableBody.appendChild(tr);
                });

                this.updatePagination(this.filteredClients.length);
            }

            updateMetrics() {
                const total = this.clients.length;
                const newLeads = this.clients.filter(c => c.status === 'New').length;
                const won = this.clients.filter(c => c.status === 'Won').length;
                const pipelineValue = this.clients.reduce((sum, c) => sum + parseFloat(c.deal_value || 0), 0);

                this.metricTotal.textContent = total;
                this.metricNew.textContent = newLeads;
                this.metricWon.textContent = won;
                this.metricValue.textContent = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(pipelineValue);
            }

            updatePagination(totalItems) {
                const maxPage = Math.ceil(totalItems / this.itemsPerPage) || 1;
                if (this.currentPage > maxPage) this.currentPage = maxPage;
                if (this.currentPage < 1) this.currentPage = 1;
                const start = totalItems === 0 ? 0 : ((this.currentPage - 1) * this.itemsPerPage) + 1;
                const end = Math.min(this.currentPage * this.itemsPerPage, totalItems);
                this.pageInfo.textContent = `Showing ${start} to ${end} of ${totalItems} entries`;
                this.prevBtn.disabled = this.currentPage === 1;
                this.nextBtn.disabled = this.currentPage === maxPage || totalItems === 0;
            }

            changePage(direction) {
                this.currentPage += direction;
                this.render();
            }

            handleFilter() {
                const term = this.searchInput.value.toLowerCase();
                const status = this.statusFilter.value;
                const source = this.sourceFilter.value;
                this.filteredClients = this.clients.filter(client => {
                    const matchTerm = client.name.toLowerCase().includes(term) || client.email.toLowerCase().includes(term) || client.phone.includes(term) || (client.company_name || '').toLowerCase().includes(term);
                    const matchStatus = status === 'all' || client.status === status;
                    const matchSource = source === 'all' || client.source === source;
                    return matchTerm && matchStatus && matchSource;
                });
                this.currentPage = 1; 
                this.render();
            }

            showLoading(show) {
                if (show) this.loadingOverlay.classList.add('active');
                else this.loadingOverlay.classList.remove('active');
            }

            // --- MODAL & FORM LOGIC ---
            openFormModal(client = null) {
                this.clientForm.reset();
                this.inputs.id.value = '';
                
                this.setFormReadOnly(false); 
                this.saveClientBtn.style.display = 'inline-flex';
                this.cancelFormBtn.textContent = "Cancel";

                if (client) {
                    this.inputs.id.value = client.id;
                    this.inputs.name.value = client.name || '';
                    this.inputs.company_name.value = client.company_name || '';
                    this.inputs.designation.value = client.designation || '';
                    this.inputs.phone.value = client.phone || '';
                    this.inputs.email.value = client.email || '';
                    this.inputs.official_mail_id.value = client.official_mail_id || '';
                    this.inputs.gst_number.value = client.gst_number || '';
                    this.inputs.location.value = client.location || '';
                    this.inputs.source.value = client.source || 'Website';
                    this.inputs.status.value = client.status || 'New';
                    this.inputs.executive.value = client.executive || '';
                    this.inputs.value.value = client.deal_value || 0;
                    this.inputs.description.value = client.description || '';

                    this.modalTitle.textContent = 'Edit Client';
                } else {
                    this.modalTitle.textContent = 'Add New Client';
                    this.inputs.status.value = 'New';
                }
                
                this.formModal.classList.add('active');
            }

            setFormReadOnly(isReadOnly) {
                const formInputs = this.clientForm.querySelectorAll('input, select, textarea');
                formInputs.forEach(input => {
                    if (input.type === 'hidden') return;
                    if (input.tagName === 'SELECT') {
                        input.disabled = isReadOnly;
                    } else {
                        input.readOnly = isReadOnly;
                    }
                });
            }

            closeFormModal() {
                this.formModal.classList.remove('active');
            }

            viewClient(id) {
                const client = this.clients.find(c => c.id === id);
                if(!client) return;

                // Bind Data to View Modal
                document.getElementById('viewInitials').innerText = client.name ? client.name.substring(0,2) : 'CL';
                document.getElementById('viewName').innerText = client.name || '-';
                
                let desComp = [];
                if(client.designation) desComp.push(client.designation);
                if(client.company_name) desComp.push(client.company_name);
                document.getElementById('viewDesignationCompany').innerText = desComp.length > 0 ? desComp.join(' at ') : 'No Organization Info';

                const statusMap = { 'New': 'status-new', 'Contacted': 'status-contacted', 'Qualified': 'status-qualified', 'Proposal': 'status-proposal', 'Won': 'status-won', 'Lost': 'status-lost' };
                const badge = document.getElementById('viewStatusBadge');
                badge.className = `status-badge ${statusMap[client.status] || 'status-new'}`;
                badge.innerText = client.status || 'New';

                document.getElementById('viewDate').innerText = client.created_at ? new Date(client.created_at).toLocaleDateString('en-GB') : '-';
                
                document.getElementById('viewValue').innerText = '₹' + parseFloat(client.deal_value || 0).toLocaleString('en-IN');
                document.getElementById('viewExecutive').innerText = client.executive || 'Unassigned';
                
                document.getElementById('viewEmail').innerText = client.email || '-';
                document.getElementById('viewOfficialEmail').innerText = client.official_mail_id || '-';
                document.getElementById('viewPhone').innerText = client.phone || '-';
                document.getElementById('viewLocation').innerText = client.location || '-';
                document.getElementById('viewGst').innerText = client.gst_number || '-';
                document.getElementById('viewSource').innerText = client.source || '-';
                document.getElementById('viewDescription').innerText = client.description || 'No description provided for this client.';

                this.viewModal.classList.add('active');
            }

            closeViewModal() {
                this.viewModal.classList.remove('active');
            }

            editClient(id) {
                const client = this.clients.find(c => c.id === id);
                if (client) this.openFormModal(client);
            }

            async handleSubmit(e) {
                e.preventDefault();
                
                const clientData = {
                    id: this.inputs.id.value || '_' + Math.random().toString(36).substr(2, 9),
                    name: this.inputs.name.value,
                    company_name: this.inputs.company_name.value,
                    designation: this.inputs.designation.value,
                    phone: this.inputs.phone.value,
                    email: this.inputs.email.value,
                    official_mail_id: this.inputs.official_mail_id.value,
                    gst_number: this.inputs.gst_number.value,
                    location: this.inputs.location.value,
                    source: this.inputs.source.value,
                    status: this.inputs.status.value,
                    executive: this.inputs.executive.value,
                    deal_value: parseFloat(this.inputs.value.value) || 0,
                    description: this.inputs.description.value
                };

                const action = this.inputs.id.value ? 'update' : 'create';
                const result = await this.saveData(action, clientData);
                
                if (result.success) {
                    await this.loadData();
                    this.closeFormModal();
                } else {
                    alert('Failed to save client: ' + result.message);
                }
            }

            // --- DELETE LOGIC ---
            requestDelete(id) {
                this.clientToDelete = id;
                this.confirmModal.classList.add('active');
            }

            closeConfirmModal() {
                this.confirmModal.classList.remove('active');
                this.clientToDelete = null;
            }

            async executeDelete() {
                if (this.clientToDelete) {
                    const result = await this.saveData('delete', { id: this.clientToDelete });
                    if (result.success) {
                        await this.loadData();
                        const maxPage = Math.ceil(this.clients.length / this.itemsPerPage) || 1;
                        if (this.currentPage > maxPage) this.currentPage = maxPage;
                        this.render();
                    } else {
                        alert('Failed to delete client: ' + result.message);
                    }
                }
                this.closeConfirmModal();
            }

            // --- UTILITIES ---
            escapeHTML(str) {
                if (str === null || str === undefined) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
        }

        // Boot the Application
        let app;
        document.addEventListener('DOMContentLoaded', () => {
            app = new ClientManager();
        });
    </script>
</body>
</html>