<?php 
// 1. DATABASE CONNECTION - Using your provided db_connect.php
require_once '../include/db_connect.php';

// 2. HANDLE AJAX POST REQUESTS
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

// 3. FETCH LIVE STATS
$resTotal = mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets");
$statTotal = mysqli_fetch_array($resTotal)[0];

$resLaptops = mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets WHERE category = 'Laptop'");
$statLaptops = mysqli_fetch_array($resLaptops)[0];

$resNodes = mysqli_query($conn, "SELECT COUNT(*) FROM hardware_assets WHERE category = 'Network Node'");
$statNodes = mysqli_fetch_array($resNodes)[0];

// 4. FETCH TABLE DATA
$query = "SELECT *, DATE_FORMAT(acquisition_date, '%b %d, %Y') as display_date, acquisition_date as raw_date FROM hardware_assets ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$assets = mysqli_fetch_all($result, MYSQLI_ASSOC);

include '../sidebars.php'; 
include '../header.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise IT | Stock Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #134e4a; 
            --brand-accent: #0d9488;  
            --brand-light: #f0fdfa;   
            --surface: #ffffff;
            --background: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--background); margin: 0; padding: 10px; }
        .dashboard-container { max-width: 1400px; margin: 0 auto; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-card small { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .stat-card div { font-size: 24px; font-weight: 700; color: var(--brand-primary); margin-top: 5px; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: white; border-radius: 12px 12px 0 0; border: 1px solid #e2e8f0; border-bottom: none; flex-wrap: wrap; gap: 15px; }
        .search-bar { padding: 10px 15px; width: 100%; max-width: 400px; border-radius: 8px; border: 1px solid #cbd5e1; }
        .action-btns { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .dropdown { position: relative; display: inline-block; }
        .dropbtn { background: white; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: #fff; min-width: 160px; box-shadow: 0px 8px 16px rgba(0,0,0,0.1); z-index: 1; border-radius: 8px; border: 1px solid #e2e8f0; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: block; font-size: 14px; }
        .dropdown:hover .dropdown-content { display: block; }

        .btn-register { background: var(--brand-primary); color: white; padding: 8px 16px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; white-space: nowrap; }

        .table-container { background: white; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { background: #f8fafc; padding: 15px; text-align: left; font-size: 12px; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-new { background: #dcfce7; color: #166534; }
        .badge-used { background: #fef9c3; color: #854d0e; }
        .cat-tag { color: var(--brand-accent); font-weight: 700; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); padding: 15px; }
        .modal-content { background: white; padding: 25px; border-radius: 16px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
        .modal-content h3 { margin-top: 0; color: var(--brand-primary); border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .action-btns { width: 100%; }
            .btn-register, .dropdown, .dropbtn { width: 100%; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-bar { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="page-header">
        <div>
            <h1 style="margin:0; color:var(--brand-primary); font-size: 1.5rem;">Corporate Hardware Ledger</h1>
            <p style="margin:5px 0; color:var(--text-muted)">Internal Asset & Stock Tracking System</p>
        </div>
        <div class="action-btns">
            <div class="dropdown">
                <button class="dropbtn">Export ▾</button>
                <div class="dropdown-content">
                    <a href="#">Export as PDF</a>
                    <a href="#">Export as Excel</a>
                </div>
            </div>
            <button class="btn-register" onclick="openModal('add')">+ Register Asset</button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><small>Total Assets</small><div><?= $statTotal ?></div></div>
        <div class="stat-card"><small>Laptops</small><div><?= $statLaptops ?></div></div>
        <div class="stat-card"><small>Network Nodes</small><div><?= $statNodes ?></div></div>
        <div class="stat-card"><small>System Health</small><div style="color:var(--brand-accent)">Optimal</div></div>
    </div>

    <div class="toolbar">
        <input type="text" class="search-bar" placeholder="Search assets...">
        <div style="color: var(--text-muted); font-size: 12px;">Showing live inventory</div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Purchase Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Barcode</th>
                    <th>System No</th>
                    <th>Condition</th>
                    <th>Invoice</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                    <tr><td colspan="9" style="text-align:center; padding: 40px; color: var(--text-muted);">No assets found.</td></tr>
                <?php else: $sno=1; foreach($assets as $row): ?>
                    <tr>
                        <td><?= $sno++ ?></td>
                        <td><?= $row['display_date'] ?></td>
                        <td class="cat-tag"><?= strtoupper($row['category']) ?></td>
                        <td><?= htmlspecialchars($row['asset_name']) ?></td>
                        <td><code><?= htmlspecialchars($row['barcode']) ?></code></td>
                        <td><b><?= htmlspecialchars($row['system_id']) ?></b></td>
                        <td><span class="badge <?= $row['status']=='NEW' ? 'badge-new' : 'badge-used' ?>"><?= $row['status'] ?></span></td>
                        <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                        <td><button onclick='openModal("edit", <?= json_encode($row) ?>)' style="background:var(--brand-primary); color:white; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-weight:600;">Edit</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="assetModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Register Asset</h3>
        <input type="hidden" id="assetId">
        <input type="hidden" id="assetDate">
        
        <div class="form-group">
            <label>System No</label>
            <input type="text" id="inSys" class="form-control" placeholder="e.g., IT-LP-101">
        </div>
        <div class="form-group">
            <label>Category</label>
            <input type="text" id="inCat" class="form-control" placeholder="e.g., Laptop">
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" id="inDesc" class="form-control">
        </div>
        <div class="form-group">
            <label>Barcode / Serial</label>
            <input type="text" id="inBar" class="form-control">
        </div>
        <div class="form-group">
            <label>Invoice Number</label>
            <input type="text" id="inInv" class="form-control">
        </div>
        <div class="form-group">
            <label>Condition</label>
            <select id="inCond" class="form-control">
                <option value="NEW">Brand New</option>
                <option value="USED">Used / Refurbished</option>
            </select>
        </div>
        
        <button onclick="submitAsset()" class="btn-register" style="width:100%; padding: 12px; margin-top: 10px;">Save Changes</button>
        <button onclick="closeModal()" style="width:100%; margin-top:8px; background:none; border:none; color:var(--text-muted); cursor:pointer;">Cancel</button>
    </div>
</div>

<script>
let currentAction = 'add';

function openModal(action, data = null) {
    currentAction = action;
    const modal = document.getElementById('assetModal');
    const title = document.getElementById('modalTitle');
    
    if (action === 'edit' && data) {
        title.innerText = "Edit Hardware Asset";
        document.getElementById('assetId').value = data.id;
        document.getElementById('assetDate').value = data.raw_date;
        document.getElementById('inSys').value = data.system_id;
        document.getElementById('inCat').value = data.category;
        document.getElementById('inDesc').value = data.asset_name;
        document.getElementById('inBar').value = data.barcode;
        document.getElementById('inInv').value = data.invoice_number;
        document.getElementById('inCond').value = data.status;
    } else {
        title.innerText = "Register New Asset";
        document.getElementById('assetId').value = '';
        document.getElementById('assetDate').value = '';
        document.querySelectorAll('.form-control').forEach(el => { if(el.tagName === 'INPUT') el.value = '' });
        document.getElementById('inCond').selectedIndex = 0;
    }
    modal.style.display = 'flex';
}

function closeModal() { document.getElementById('assetModal').style.display = 'none'; }

async function submitAsset() {
    const data = {
        action: currentAction,
        id: document.getElementById('assetId').value,
        date: document.getElementById('assetDate').value,
        sys: document.getElementById('inSys').value,
        cat: document.getElementById('inCat').value,
        desc: document.getElementById('inDesc').value,
        bar: document.getElementById('inBar').value,
        inv: document.getElementById('inInv').value,
        cond: document.getElementById('inCond').value
    };

    try {
        const res = await fetch('', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data) 
        });
        const result = await res.json();
        if(result.success) {
            location.reload();
        } else {
            alert("Error saving data.");
        }
    } catch (err) {
        console.error(err);
    }
}

window.onclick = function(event) {
    if (event.target == document.getElementById('assetModal')) closeModal();
}
</script>
</body>
</html>