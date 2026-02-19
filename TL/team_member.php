<?php
// team_member.php (TL View)

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. ROBUST INCLUDE FUNCTION & DB CONNECTION
function includeFile($filename) {
    $paths = [
        __DIR__ . '/' . $filename,       
        __DIR__ . '/../' . $filename     
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            include $path;
            return;
        }
    }
}

// Fixed DB Connection inclusion
$db_path = __DIR__ . '/../include/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    require_once '../include/db_connect.php'; 
}

$tl_user_id = $_SESSION['user_id']; // E.g., Frank = 19

// 3. FETCH DYNAMIC TEAM DATA
// UPDATED QUERY: Using employee_profiles instead of team_members
$query = "SELECT 
            emp_id_code as employee_id, 
            full_name, 
            profile_img as profile_image, 
            designation, 
            email, 
            phone,
            joining_date as joined_date 
          FROM employee_profiles 
          WHERE reporting_to = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tl_user_id); // Assuming $tl_user_id is your session ID
$stmt->execute();
$result = $stmt->get_result();

$teamMembers = [];
$active_today_count = 0;

while($row = $result->fetch_assoc()) {
    // Split full name for the edit modal
    $name_parts = explode(' ', $row['full_name'], 2);
    $row['first_name'] = $name_parts[0];
    $row['last_name'] = isset($name_parts[1]) ? $name_parts[1] : '';
    
    // Fallbacks if columns don't exist yet to prevent UI errors
    $row['emp_type'] = $row['emp_type'] ?? 'Permanent'; 
    $row['phone'] = $row['phone'] ?? 'N/A';
    $row['salary'] = $row['salary'] ?? 'Confidential';
    $row['status'] = $row['status'] ?? 'Active'; 
    $row['performance'] = $row['performance'] ?? 'High';
    $row['performance_score'] = $row['performance_score'] ?? '85';
    
    if ($row['status'] === 'Active') {
        $active_today_count++;
    }
    
    $teamMembers[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - TL Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --bg-body: #f8f9fa;
            --white: #ffffff;
            --sidebar-width: 95px; /* Adjusted to your primary sidebar width */
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            margin: 0; 
            color: var(--text-main);
            overflow-x: hidden;
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 24px 32px; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
        }

        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 24px; gap: 15px; flex-wrap: wrap; 
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .btn { 
            display: inline-flex; align-items: center; justify-content: center;
            padding: 9px 16px; font-size: 14px; font-weight: 500; 
            border-radius: 6px; border: 1px solid var(--border); 
            background: var(--white); cursor: pointer; gap: 8px; transition: 0.2s;
            text-decoration: none; color: var(--text-main);
        }
        .btn:hover { background: #f9fafb; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-hover); }

        .view-toggle { display: flex; gap: 5px; }
        .view-btn { padding: 8px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border); background: white; color: var(--text-muted); }
        .view-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .stats-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; margin-bottom: 30px; 
        }
        .stat-card { 
            background: white; border-radius: 12px; padding: 20px; 
            border: 1px solid var(--border); 
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .stat-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; }
        .stat-info h3 { font-size: 24px; font-weight: 700; margin: 0; }
        .stat-info span { font-size: 13px; color: var(--text-muted); }

        .card-orange .stat-icon-box { background: var(--primary); }
        .card-green .stat-icon-box { background: #10b981; }
        .card-blue .stat-icon-box { background: #3b82f6; }

        .filter-row {
            background: white; padding: 15px; border-radius: 12px; 
            border: 1px solid var(--border); margin-bottom: 20px; 
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }

        .table-responsive { 
            background: white; border-radius: 12px; border: 1px solid var(--border); 
            overflow-x: auto; -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; background: #f9fafb; color: #4b5563; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .grid-view-container { 
            display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; 
        }
        .grid-view-container.active { display: grid; }
        .emp-card { 
            background: white; border: 1px solid var(--border); border-radius: 12px; 
            padding: 24px; text-align: center; transition: 0.2s; position: relative;
        }
        .emp-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .card-img { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 12px; border: 3px solid #f3f4f6; object-fit: cover; }

        .modal-overlay { 
            display: none; position: fixed; inset: 0; 
            background: rgba(0,0,0,0.5); z-index: 2000; 
            align-items: center; justify-content: center; 
            backdrop-filter: blur(2px); padding: 10px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: white; width: 700px; max-width: 100%; 
            border-radius: 12px; overflow: hidden; 
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .modal-header { 
            padding: 20px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .modal-body { padding: 24px; overflow-y: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 5px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-control { 
            width: 100%; padding: 10px; border: 1px solid #d1d5db; 
            border-radius: 6px; font-size: 14px; box-sizing: border-box; 
        }
        .modal-footer { 
            padding: 16px; border-top: 1px solid var(--border); 
            display: flex; justify-content: flex-end; gap: 10px; background: #fff;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .page-header > div:last-child { width: 100%; justify-content: space-between; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-row input, .filter-row select { width: 100% !important; }
            .form-grid { grid-template-columns: 1fr; }
            .modal-box { width: 100%; }
        }
    </style>
</head>
<body>

    <?php includeFile('sidebars.php'); ?>

    <div class="main-content">
        <?php includeFile('header.php'); ?>

        <div class="page-header mt-4">
            <div>
                <h1>Team Members</h1>
                <div class="breadcrumb">
                    <i data-lucide="users" style="width:14px;"></i>
                    <span>/</span>
                    <span style="font-weight:600; color:#111827;">My Team (Development)</span>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <div class="view-toggle">
                    <div class="view-btn active" onclick="switchView('list')" id="btnList"><i data-lucide="list" style="width:18px;"></i></div>
                    <div class="view-btn" onclick="switchView('grid')" id="btnGrid"><i data-lucide="layout-grid" style="width:18px;"></i></div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-orange">
                <div class="stat-info"><span>Total Team Size</span><h3><?php echo count($teamMembers); ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="users"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Active Status</span><h3><?php echo $active_today_count; ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-check"></i></div>
            </div>
            <div class="stat-card card-blue">
                <div class="stat-info"><span>Open Tasks</span><h3>14</h3></div>
                <div class="stat-icon-box"><i data-lucide="clipboard-list"></i></div>
            </div>
        </div>

        <div class="filter-row">
            <div style="flex:1; position:relative;">
                <i data-lucide="search" style="position:absolute; left:10px; top:10px; width:16px; color:#9ca3af;"></i>
                <input type="text" id="searchInput" onkeyup="filterTeam()" placeholder="Search team member..." class="form-control" style="padding-left:35px;">
            </div>
            <select id="statusFilter" onchange="filterTeam()" class="form-control" style="width:180px;">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>

        <div id="listView" class="table-responsive">
            <table id="teamTable">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Designation</th>
                        <th>Email</th>
                        <th>Joined Date</th>
                        <th>Performance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($teamMembers) > 0): ?>
                        <?php foreach($teamMembers as $member): ?>
                        <tr>
                            <td>
                                <div class="emp-profile">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member['full_name']); ?>&background=random" class="emp-img">
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                        <div style="font-size:11px; color:var(--text-muted);"><?php echo htmlspecialchars($member['employee_id']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($member['designation']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td><?php echo $member['joined_date'] ? date("M d, Y", strtotime($member['joined_date'])) : 'N/A'; ?></td>
                            <td>
                                <span style="font-size:12px; font-weight:500; color: <?php echo $member['performance'] == 'High' ? '#10b981' : ($member['performance'] == 'Average' ? '#f59e0b' : '#6b7280'); ?>;">
                                    <?php echo htmlspecialchars($member['performance']); ?>
                                </span>
                            </td>
                            <td><span class="status-badge <?php echo $member['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars($member['status']); ?></span></td>
                            <td>
                                <button class="btn" style="padding:5px;" onclick='openEditModal(<?php echo json_encode($member); ?>)'>
                                    <i data-lucide="eye" style="width:16px; color:#3b82f6;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#6b7280;">No team members found under your supervision.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="gridView" class="grid-view-container">
            <?php foreach($teamMembers as $member): ?>
            <div class="emp-card" data-name="<?php echo strtolower($member['full_name']); ?>" data-status="<?php echo strtolower($member['status']); ?>">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member['full_name']); ?>&background=random" class="card-img">
                <h4 style="margin:0;"><?php echo htmlspecialchars($member['full_name']); ?></h4>
                <p style="font-size:13px; color:var(--text-muted); margin:5px 0 15px;"><?php echo htmlspecialchars($member['designation']); ?></p>
                <div style="display:flex; justify-content:center; gap:5px; margin-bottom:15px;">
                    <span class="status-badge status-active"><?php echo htmlspecialchars($member['emp_type']); ?></span>
                </div>
                <button class="btn btn-primary" style="width:100%;" onclick='openEditModal(<?php echo json_encode($member); ?>)'>View Details</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal-overlay" id="memberModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Member Details</h3>
                <i data-lucide="x" style="cursor:pointer;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                <form id="memberForm">
                    <div class="form-grid">
                        <div class="form-group"><label>First Name</label><input type="text" id="fName" class="form-control" readonly></div>
                        <div class="form-group"><label>Last Name</label><input type="text" id="lName" class="form-control" readonly></div>
                        <div class="form-group"><label>Email Address</label><input type="email" id="email" class="form-control" readonly></div>
                        <div class="form-group"><label>Phone</label><input type="text" id="phone" class="form-control" readonly></div>
                        <div class="form-group"><label>Designation</label><input type="text" id="desig" class="form-control" readonly></div>
                        <div class="form-group"><label>Status</label><input type="text" id="status" class="form-control" readonly></div>
                        <div class="form-group"><label>Salary</label><input type="text" id="salary" class="form-control" readonly></div>
                        <div class="form-group"><label>Performance Score</label><input type="text" id="perfScore" class="form-control" readonly></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal()">Close Window</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function switchView(view) {
            const listBtn = document.getElementById('btnList');
            const gridBtn = document.getElementById('btnGrid');
            const listView = document.getElementById('listView');
            const gridView = document.getElementById('gridView');

            if(view === 'list') {
                listBtn.classList.add('active'); gridBtn.classList.remove('active');
                listView.style.display = 'block'; gridView.classList.remove('active');
            } else {
                gridBtn.classList.add('active'); listBtn.classList.remove('active');
                listView.style.display = 'none'; gridView.classList.add('active');
            }
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').innerText = "Team Member: " + data.full_name;
            document.getElementById('fName').value = data.first_name || '';
            document.getElementById('lName').value = data.last_name || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('phone').value = data.phone || '';
            document.getElementById('desig').value = data.designation || '';
            document.getElementById('status').value = data.status || '';
            document.getElementById('salary').value = data.salary || 'Confidential';
            document.getElementById('perfScore').value = data.performance_score + '/100' || '85/100';
            
            document.body.style.overflow = 'hidden';
            document.getElementById('memberModal').classList.add('active');
        }

        function closeModal() {
            document.body.style.overflow = 'auto';
            document.getElementById('memberModal').classList.remove('active');
        }

        function filterTeam() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            let status = document.getElementById('statusFilter').value.toLowerCase();
            
            // Filter List View
            let trs = document.getElementById('teamTable').getElementsByTagName('tr');
            for (let i = 1; i < trs.length; i++) {
                if(trs[i].cells.length < 6) continue;
                let name = trs[i].cells[0].textContent.toLowerCase();
                let stat = trs[i].cells[5].textContent.toLowerCase();
                
                let matchesSearch = name.includes(search);
                let matchesStatus = status === "" || stat.includes(status);
                
                trs[i].style.display = (matchesSearch && matchesStatus) ? "" : "none";
            }

            // Filter Grid View
            let cards = document.getElementsByClassName('emp-card');
            for(let i=0; i<cards.length; i++) {
                let name = cards[i].getAttribute('data-name');
                let stat = cards[i].getAttribute('data-status');
                
                let matchesSearch = name.includes(search);
                let matchesStatus = status === "" || stat.includes(status);
                
                cards[i].style.display = (matchesSearch && matchesStatus) ? "block" : "none";
            }
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) closeModal();
        }
    </script>
</body>
</html>