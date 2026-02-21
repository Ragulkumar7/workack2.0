<?php 
// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// 2. DATABASE CONNECTION (Smart Path Resolver)
date_default_timezone_set('Asia/Kolkata');
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

// 3. HANDLE AJAX POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        if ($input['action'] === 'add') {
            $stmt = mysqli_prepare($conn, "INSERT INTO hardware_assets (system_id, category, asset_name, barcode, invoice_number, status, acquisition_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "ssssss", $input['sys'], $input['cat'], $input['desc'], $input['bar'], $input['inv'], $input['cond']);
            $success = mysqli_stmt_execute($stmt);
            echo json_encode(['success' => $success]);
            exit;
        }
        
        if ($input['action'] === 'edit') {
            $formattedDate = date('Y-m-d', strtotime($input['date']));
            $stmt = mysqli_prepare($conn, "UPDATE hardware_assets SET acquisition_date = ?, category = ?, asset_name = ?, barcode = ?, system_id = ?, status = ?, invoice_number = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssssssi", $formattedDate, $input['cat'], $input['desc'], $input['bar'], $input['sys'], $input['cond'], $input['inv'], $input['id']);
            $success = mysqli_stmt_execute($stmt);
            echo json_encode(['success' => $success]);
            exit;
        }
    }
}

// 4. FETCH LIVE STATS
$resTotal = mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets");
$statTotal = mysqli_fetch_array($resTotal)[0] ?? 0;

$resLaptops = mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets WHERE category LIKE '%Laptop%'");
$statLaptops = mysqli_fetch_array($resLaptops)[0] ?? 0;

$resNodes = mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets WHERE category LIKE '%Network Node%'");
$statNodes = mysqli_fetch_array($resNodes)[0] ?? 0;

// 5. FETCH TABLE DATA
$query = "SELECT *, DATE_FORMAT(acquisition_date, '%b %d, %Y') as display_date, acquisition_date as raw_date FROM hardware_assets ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$assets = [];
if ($result) {
    $assets = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise IT | Stock Management</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #1b5a5a;
            --primary-light: #267a7a;
            --surface: #ffffff;
            --background: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --sidebar-width: 95px;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--background); 
            margin: 0; 
            padding: 0; 
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Main Content Wrapper */
        #mainContent { 
            margin-left: var(--sidebar-width); 
            padding: 24px 32px; 
            transition: margin-left 0.3s ease, width 0.3s ease; 
            min-height: 100vh; 
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
        }

        .dashboard-container { max-width: 1600px; margin: 0 auto; width: 100%; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.05); }
        .stat-card small { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        .stat-card div { font-size: 28px; font-weight: 800; color: var(--primary-color); margin-top: 5px; }

        /* Toolbar */
        .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: white; border-radius: 12px 12px 0 0; border: 1px solid var(--border-color); border-bottom: none; flex-wrap: wrap; gap: 15px; }
        .search-wrapper { position: relative; width: 100%; max-width: 400px; }
        .search-wrapper i { position: absolute; left: 15px; top: 12px; color: var(--text-muted); }
        .search-bar { width: 100%; padding: 10px 15px 10px 40px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; transition: 0.2s; box-sizing: border-box; font-family: 'Inter', sans-serif;}
        .search-bar:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }
        
        .action-btns { display: flex; gap: 10px; flex-wrap: wrap; }
        
        /* Dropdown */
        .dropdown { position: relative; display: inline-block; }
        .dropbtn { background: white; border: 1px solid #cbd5e1; color: var(--text-main); padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .dropbtn:hover { background: #f1f5f9; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: #fff; min-width: 160px; box-shadow: 0px 8px 16px rgba(0,0,0,0.1); z-index: 10; border-radius: 8px; border: 1px solid var(--border-color); overflow: hidden;}
        .dropdown-content a { color: var(--text-main); padding: 12px 16px; text-decoration: none; display: block; font-size: 14px; transition: 0.2s;}
        .dropdown-content a:hover { background-color: #f8fafc; color: var(--primary-color); }
        .dropdown:hover .dropdown-content { display: block; }

        /* Buttons */
        .btn-register { background: var(--primary-color); color: white; padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; white-space: nowrap; transition: 0.2s; display: flex; align-items: center; gap: 8px;}
        .btn-register:hover { background: var(--primary-light); }

        /* Table */
        .table-container { background: white; border-radius: 0 0 12px 12px; border: 1px solid var(--border-color); overflow-x: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: #f8fafc; padding: 16px 20px; text-align: left; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); white-space: nowrap;}
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        .badge-new { background: #dcfce7; color: #166534; }
        .badge-used { background: #fef9c3; color: #854d0e; }
        .cat-tag { color: var(--primary-color); font-weight: 700; background: #f0fdfa; padding: 4px 8px; border-radius: 6px; font-size: 12px;}

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); padding: 15px; box-sizing: border-box;}
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; position: relative;}
        .modal-content h3 { margin-top: 0; color: var(--primary-color); border-bottom: 1px solid var(--border-color); padding-bottom: 15px; font-size: 1.25rem; font-weight: 700;}
        .close-modal { position: absolute; top: 25px; right: 25px; font-size: 20px; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: #ef4444; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; font-family: 'Inter', sans-serif; transition: 0.2s; outline: none;}
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 992px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 16px; }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .action-btns { width: 100%; display: grid; grid-template-columns: 1fr 1fr; }
            .dropdown { width: 100%; }
            .dropbtn { width: 100%; }
            .btn-register { width: 100%; justify-content: center; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-wrapper { max-width: 100%; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <main id="mainContent">
        <?php include $headerPath; ?>

        <div class="dashboard-container mt-4">
            <div class="page-header">
                <div>
                    <h1 style="margin:0; color:var(--text-main); font-size: 1.5rem; font-weight: 800;">Corporate Hardware Ledger</h1>
                    <p style="margin:5px 0 0 0; color:var(--text-muted); font-size: 0.9rem;">Internal Asset & Stock Tracking System</p>
                </div>
                <div class="action-btns">
                    <div class="dropdown">
                        <button class="dropbtn"><i class="fa-solid fa-file-export mr-2"></i> Export ▾</button>
                        <div class="dropdown-content">
                            <a href="#"><i class="fa-solid fa-file-pdf mr-2 text-red-500"></i> Export as PDF</a>
                            <a href="#"><i class="fa-solid fa-file-excel mr-2 text-green-600"></i> Export as Excel</a>
                        </div>
                    </div>
                    <button class="btn-register" onclick="openModal('add')"><i class="fa-solid fa-plus"></i> Register Asset</button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <small>Total Assets</small>
                    <div><?= htmlspecialchars($statTotal) ?></div>
                </div>
                <div class="stat-card">
                    <small>Laptops Assigned</small>
                    <div><?= htmlspecialchars($statLaptops) ?></div>
                </div>
                <div class="stat-card">
                    <small>Network Nodes</small>
                    <div><?= htmlspecialchars($statNodes) ?></div>
                </div>
                <div class="stat-card">
                    <small>System Health</small>
                    <div style="color:var(--primary-color)"><i class="fa-solid fa-circle-check text-green-500 text-xl mr-2"></i>Optimal</div>
                </div>
            </div>

            <div class="toolbar">
                <div class="search-wrapper">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" class="search-bar" placeholder="Search by asset name, category, or barcode..." onkeyup="filterAssets()">
                </div>
                <div style="color: var(--text-muted); font-size: 13px; font-weight: 500;">
                    Showing live inventory database
                </div>
            </div>

            <div class="table-container">
                <table id="assetTable">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Purchase Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Barcode / Serial</th>
                            <th>System No</th>
                            <th>Condition</th>
                            <th>Invoice</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding: 60px 20px; color: var(--text-muted);">
                                    <i class="fa-solid fa-boxes-stacked text-4xl mb-3 opacity-50"></i>
                                    <p class="mb-0">No hardware assets registered yet.</p>
                                </td>
                            </tr>
                        <?php else: $sno=1; foreach($assets as $row): ?>
                            <tr class="asset-row">
                                <td class="font-medium text-slate-500"><?= $sno++ ?></td>
                                <td class="font-medium"><?= htmlspecialchars($row['display_date']) ?></td>
                                <td><span class="cat-tag"><?= strtoupper(htmlspecialchars($row['category'])) ?></span></td>
                                <td class="asset-desc font-medium text-slate-800"><?= htmlspecialchars($row['asset_name']) ?></td>
                                <td><code style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-weight:600; color:var(--primary-color); border:1px solid #e2e8f0;"><?= htmlspecialchars($row['barcode']) ?></code></td>
                                <td class="font-bold text-slate-700"><?= htmlspecialchars($row['system_id']) ?></td>
                                <td>
                                    <span class="badge <?= strtoupper($row['status']) == 'NEW' ? 'badge-new' : 'badge-used' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted"><i class="fa-solid fa-file-invoice mr-1 opacity-50"></i> <?= htmlspecialchars($row['invoice_number']) ?></td>
                                <td class="text-right">
                                    <button onclick='openModal("edit", <?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background:var(--primary-color); color:white; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px; transition:0.2s;" onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background='var(--primary-color)'">
                                        <i class="fa-solid fa-pen mr-1"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="assetModal" class="modal">
        <div class="modal-content">
            <i class="fa-solid fa-xmark close-modal" onclick="closeModal()"></i>
            <h3 id="modalTitle">Register Asset</h3>
            
            <input type="hidden" id="assetId">
            <input type="hidden" id="assetDate">
            
            <div class="form-group mt-4">
                <label>System No (Identifier)</label>
                <input type="text" id="inSys" class="form-control" placeholder="e.g., IT-LP-101">
            </div>
            <div class="form-group">
                <label>Asset Category</label>
                <input type="text" id="inCat" class="form-control" placeholder="e.g., Laptop, Monitor, Networking">
            </div>
            <div class="form-group">
                <label>Asset Description / Model</label>
                <input type="text" id="inDesc" class="form-control" placeholder="e.g., Dell Latitude 5420">
            </div>
            <div class="form-group">
                <label>Barcode / Serial Number</label>
                <input type="text" id="inBar" class="form-control" placeholder="Unique serial code">
            </div>
            <div class="form-group">
                <label>Invoice Number</label>
                <input type="text" id="inInv" class="form-control" placeholder="Vendor invoice reference">
            </div>
            <div class="form-group">
                <label>Condition Status</label>
                <select id="inCond" class="form-control">
                    <option value="NEW">Brand New</option>
                    <option value="USED">Used / Refurbished</option>
                </select>
            </div>
            
            <button onclick="submitAsset()" id="saveBtn" class="btn-register" style="width:100%; padding: 14px; margin-top: 20px; justify-content:center; font-size: 15px;">
                <i class="fa-solid fa-save"></i> Save Asset
            </button>
        </div>
    </div>

    <script>
        // --- Responsive Sidebar Logic ---
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

        // --- Live Search Filter ---
        function filterAssets() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let rows = document.querySelectorAll('.asset-row');
            
            rows.forEach(row => {
                // Get text content from the row (specifically category, desc, and barcode)
                let text = row.textContent.toLowerCase();
                if (text.includes(input)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // --- Modal & AJAX Logic ---
        let currentAction = 'add';

        function openModal(action, data = null) {
            currentAction = action;
            const modal = document.getElementById('assetModal');
            const title = document.getElementById('modalTitle');
            
            if (action === 'edit' && data) {
                title.innerHTML = "<i class='fa-solid fa-pen-to-square mr-2'></i> Edit Hardware Asset";
                document.getElementById('assetId').value = data.id;
                document.getElementById('assetDate').value = data.raw_date;
                document.getElementById('inSys').value = data.system_id;
                document.getElementById('inCat').value = data.category;
                document.getElementById('inDesc').value = data.asset_name;
                document.getElementById('inBar').value = data.barcode;
                document.getElementById('inInv').value = data.invoice_number;
                
                // Select correct option
                const condSelect = document.getElementById('inCond');
                for(let i=0; i<condSelect.options.length; i++) {
                    if(condSelect.options[i].value === data.status.toUpperCase()) {
                        condSelect.selectedIndex = i;
                        break;
                    }
                }
            } else {
                title.innerHTML = "<i class='fa-solid fa-plus-circle mr-2'></i> Register New Asset";
                document.getElementById('assetId').value = '';
                document.getElementById('assetDate').value = '';
                document.querySelectorAll('.form-control').forEach(el => { if(el.tagName === 'INPUT') el.value = '' });
                document.getElementById('inCond').selectedIndex = 0;
            }
            modal.style.display = 'flex';
        }

        function closeModal() { 
            document.getElementById('assetModal').style.display = 'none'; 
        }

        async function submitAsset() {
            const saveBtn = document.getElementById('saveBtn');
            const data = {
                action: currentAction,
                id: document.getElementById('assetId').value,
                date: document.getElementById('assetDate').value,
                sys: document.getElementById('inSys').value.trim(),
                cat: document.getElementById('inCat').value.trim(),
                desc: document.getElementById('inDesc').value.trim(),
                bar: document.getElementById('inBar').value.trim(),
                inv: document.getElementById('inInv').value.trim(),
                cond: document.getElementById('inCond').value
            };

            // Basic validation
            if(!data.sys || !data.cat || !data.desc) {
                Swal.fire({ icon: 'warning', title: 'Missing Info', text: 'System No, Category, and Description are required.', confirmButtonColor: '#1b5a5a' });
                return;
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            try {
                const res = await fetch(window.location.href, { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data) 
                });
                const result = await res.json();
                
                if(result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Hardware asset updated successfully.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save data. Ensure Barcode/System No is unique.', confirmButtonColor: '#1b5a5a' });
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save Asset';
                }
            } catch (err) {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to the server.', confirmButtonColor: '#1b5a5a' });
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save Asset';
            }
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == document.getElementById('assetModal')) closeModal();
        }
    </script>
</body>
</html>