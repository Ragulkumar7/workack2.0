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
    // Ensure no previous output (warnings/spaces) breaks JSON
    if (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        $response = ['success' => false, 'error' => 'Unknown action'];

        // ACTION: ADD ASSET
        if ($input['action'] === 'add') {
            $stmt = mysqli_prepare($conn, "INSERT INTO hardware_assets (system_id, category, asset_name, barcode, invoice_number, amount, request_status, status, acquisition_date) VALUES (?, ?, ?, ?, ?, ?, 'Pending Accountant', ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sssssds", $input['sys'], $input['cat'], $input['desc'], $input['bar'], $input['inv'], $input['amt'], $input['cond']);
            $success = mysqli_stmt_execute($stmt);
            $response = ['success' => $success, 'error' => mysqli_error($conn)];
        }
        
        // ACTION: EDIT ASSET
        else if ($input['action'] === 'edit') {
            $formattedDate = date('Y-m-d', strtotime($input['date']));
            $stmt = mysqli_prepare($conn, "UPDATE hardware_assets SET acquisition_date = ?, category = ?, asset_name = ?, barcode = ?, system_id = ?, status = ?, invoice_number = ?, amount = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssssssdi", $formattedDate, $input['cat'], $input['desc'], $input['bar'], $input['sys'], $input['cond'], $input['inv'], $input['amt'], $input['id']);
            $success = mysqli_stmt_execute($stmt);
            $response = ['success' => $success, 'error' => mysqli_error($conn)];
        }

        // ACTION: PROCESS APPROVAL
        else if ($input['action'] === 'approve_reject') {
            $stmt = mysqli_prepare($conn, "UPDATE hardware_assets SET request_status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $input['new_status'], $input['asset_id']);
            $success = mysqli_stmt_execute($stmt);
            $response = ['success' => $success, 'error' => mysqli_error($conn)];
        }

        echo json_encode($response);
        exit;
    }
}

// 4. FETCH LIVE STATS
$statTotal = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets"))[0] ?? 0;
$statLaptops = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets WHERE category LIKE '%Laptop%'"))[0] ?? 0;
$statPending = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets WHERE request_status = 'Pending Accountant'"))[0] ?? 0;

// 5. FETCH TABLE DATA
$query = "SELECT *, DATE_FORMAT(acquisition_date, '%b %d, %Y') as display_date, acquisition_date as raw_date FROM hardware_assets ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$assets = ($result) ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise IT | Asset Ledger & Approvals</title>
    
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
            --success: #166534;
            --danger: #991b1b;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--background); margin: 0; color: var(--text-main); }
        #mainContent { margin-left: var(--sidebar-width); padding: 24px 32px; min-height: 100vh; width: calc(100% - var(--sidebar-width)); box-sizing: border-box; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); }
        .stat-card small { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .stat-card div { font-size: 28px; font-weight: 800; color: var(--primary-color); margin-top: 5px; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: white; border-radius: 12px 12px 0 0; border: 1px solid var(--border-color); border-bottom: none; }
        .search-bar { padding: 10px 15px 10px 40px; border-radius: 8px; border: 1px solid #cbd5e1; width: 300px; }
        
        .btn-register { background: var(--primary-color); color: white; padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;}
        
        .table-container { background: white; border-radius: 0 0 12px 12px; border: 1px solid var(--border-color); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 16px 20px; text-align: left; font-size: 12px; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }

        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-pending { background: #ffedd5; color: #9a3412; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        .btn-action { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 11px; margin-right: 4px; transition: 0.2s; }
        .btn-approve { background: #dcfce7; color: #166534; }
        .btn-reject { background: #fee2e2; color: #991b1b; }
        .btn-edit { background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color); }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 450px; position: relative; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box;}
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <main id="mainContent">
        <?php include $headerPath; ?>

        <div class="dashboard-container mt-4">
            <div class="page-header" style="display:flex; justify-content: space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h1 style="margin:0; font-size: 1.5rem; font-weight: 800;">Asset Management & Approvals</h1>
                    <p style="color:var(--text-muted);">Internal Control Ledger</p>
                </div>
                <button class="btn-register" onclick="openModal('add')"><i class="fa-solid fa-plus"></i> Register Asset</button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <small>Total Inventory</small>
                    <div><?= $statTotal ?></div>
                </div>
                <div class="stat-card">
                    <small>Pending Approvals</small>
                    <div style="color:#9a3412"><?= $statPending ?></div>
                </div>
                <div class="stat-card">
                    <small>Laptops</small>
                    <div><?= $statLaptops ?></div>
                </div>
                <div class="stat-card">
                    <small>System Status</small>
                    <div style="font-size:18px"><i class="fa-solid fa-shield-check"></i> Connected</div>
                </div>
            </div>

            <div class="toolbar">
                <div style="position:relative">
                    <i class="fa-solid fa-search" style="position:absolute; left:15px; top:12px; color:var(--text-muted)"></i>
                    <input type="text" id="searchInput" class="search-bar" placeholder="Filter assets..." onkeyup="filterAssets()">
                </div>
            </div>

            <div class="table-container">
                <table id="assetTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Asset Details</th>
                            <th>System ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th style="text-align:right">Management Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">No assets found in history.</td></tr>
                        <?php else: foreach($assets as $row): ?>
                        <tr class="asset-row">
                            <td><?= $row['display_date'] ?></td>
                            <td><b style="color:var(--primary-color)"><?= strtoupper($row['category'] ?? '') ?></b></td>
                            <td>
                                <?= htmlspecialchars($row['asset_name'] ?? '') ?><br>
                                <small style="color:var(--text-muted)"><?= htmlspecialchars($row['invoice_number'] ?? 'No Invoice') ?></small>
                            </td>
                            <td><code><?= htmlspecialchars($row['system_id'] ?? 'N/A') ?></code></td>
                            <td><b>₹<?= number_format($row['amount'] ?? 0, 2) ?></b></td>
                            <td>
                                <?php 
                                    $statusClass = strtolower(explode(' ', $row['request_status'] ?? 'pending')[0]);
                                ?>
                                <span class="badge badge-<?= $statusClass ?>">
                                    <?= $row['request_status'] ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <?php if(($row['request_status'] ?? '') === 'Pending Accountant'): ?>
                                    <button onclick="processApproval(<?= $row['id'] ?>, 'Approved')" class="btn-action btn-approve" title="Approve">Approve</button>
                                    <button onclick="processApproval(<?= $row['id'] ?>, 'Rejected')" class="btn-action btn-reject" title="Reject">Reject</button>
                                <?php endif; ?>
                                <button onclick='openModal("edit", <?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn-action btn-edit">
                                    <i class="fa-solid fa-pen"></i>
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
            <h3 id="modalTitle">Register Asset</h3>
            <input type="hidden" id="assetId">
            <input type="hidden" id="assetDate">
            
            <div class="form-group"><label>System ID</label><input type="text" id="inSys" class="form-control" placeholder="e.g. SYS-101"></div>
            <div class="form-group"><label>Category</label><input type="text" id="inCat" class="form-control" placeholder="e.g. Laptop"></div>
            <div class="form-group"><label>Asset Name / Description</label><input type="text" id="inDesc" class="form-control" placeholder="e.g. Dell Latitude 5420"></div>
            <div class="form-group"><label>Barcode / Serial No</label><input type="text" id="inBar" class="form-control"></div>
            <div class="form-group"><label>Invoice Number</label><input type="text" id="inInv" class="form-control"></div>
            <div class="form-group"><label>Amount (₹)</label><input type="number" id="inAmt" class="form-control"></div>
            <div class="form-group">
                <label>Condition</label>
                <select id="inCond" class="form-control">
                    <option value="NEW">New</option>
                    <option value="USED">Used</option>
                </select>
            </div>
            
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="submitAsset()" id="saveBtn" class="btn-register" style="flex:1; justify-content:center">Save Asset</button>
                <button onclick="closeModal()" class="btn-action" style="background:#ccc; color: #333">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function filterAssets() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.asset-row').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        function openModal(action, data = null) {
            window.currentAction = action;
            document.getElementById('assetModal').style.display = 'flex';
            document.getElementById('modalTitle').innerText = (action === 'edit') ? 'Edit Asset' : 'Register New Asset';
            
            if(action === 'edit' && data) {
                document.getElementById('assetId').value = data.id;
                document.getElementById('inSys').value = data.system_id;
                document.getElementById('inCat').value = data.category;
                document.getElementById('inDesc').value = data.asset_name;
                document.getElementById('inBar').value = data.barcode;
                document.getElementById('inInv').value = data.invoice_number;
                document.getElementById('inAmt').value = data.amount;
                document.getElementById('assetDate').value = data.raw_date;
                document.getElementById('inCond').value = data.status;
            } else {
                document.querySelectorAll('.form-control').forEach(i => i.value = '');
                document.getElementById('inCond').value = 'NEW';
            }
        }

        function closeModal() { document.getElementById('assetModal').style.display = 'none'; }

        async function submitAsset() {
            const data = {
                action: window.currentAction,
                id: document.getElementById('assetId').value,
                sys: document.getElementById('inSys').value,
                cat: document.getElementById('inCat').value,
                desc: document.getElementById('inDesc').value,
                bar: document.getElementById('inBar').value,
                inv: document.getElementById('inInv').value,
                amt: document.getElementById('inAmt').value,
                date: document.getElementById('assetDate').value,
                cond: document.getElementById('inCond').value
            };

            if(!data.sys || !data.desc || !data.amt) {
                Swal.fire('Error', 'Please fill in System ID, Description and Amount', 'error');
                return;
            }

            try {
                const res = await fetch(window.location.href, { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data) 
                });
                
                const text = await res.text(); // Get raw text first to debug if needed
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error("Invalid JSON response:", text);
                    Swal.fire('Error', 'Server returned an invalid response. Check console.', 'error');
                    return;
                }
                
                if(result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Asset data has been saved.',
                        timer: 1500
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Database Error', result.error || 'Failed to save asset', 'error');
                }
            } catch (e) {
                Swal.fire('Network Error', 'Could not connect to the server', 'error');
            }
        }

        async function processApproval(id, status) {
            const result = await Swal.fire({
                title: 'Confirm action?',
                text: `Set this asset request to ${status}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1b5a5a'
            });

            if (result.isConfirmed) {
                try {
                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ action: 'approve_reject', asset_id: id, new_status: status })
                    });
                    const response = await res.json();
                    if(response.success) {
                        Swal.fire('Updated!', `Asset has been ${status}.`, 'success').then(() => location.reload());
                    }
                } catch(e) {
                    Swal.fire('Error', 'Failed to process request.', 'error');
                }
            }
        }

        window.onclick = function(event) {
            let modal = document.getElementById('assetModal');
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>