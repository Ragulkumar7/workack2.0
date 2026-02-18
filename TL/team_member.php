<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. DATABASE CONNECTION (Using your robust include logic)
$db_path = __DIR__ . '/../include/db_connect.php';
if (file_exists($db_path)) {
    include $db_path;
} else {
    die("Database connection file not found at: " . $db_path);
}

// 3. FETCH DYNAMIC STATS FROM DATABASE
$total_query = "SELECT COUNT(*) as total FROM team_members";
$total_result = mysqli_query($conn, $total_query);
$total_count = mysqli_fetch_assoc($total_result)['total'];

$active_query = "SELECT COUNT(*) as active FROM team_members WHERE status = 'Active'";
$active_result = mysqli_query($conn, $active_query);
$active_count = mysqli_fetch_assoc($active_result)['active'];

// 4. FETCH FILTERED TEAM MEMBERS
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Base SQL
$sql = "SELECT * FROM team_members WHERE (full_name LIKE '$search%' OR employee_id LIKE '$search%')";

// Add Status Filter to SQL if a specific status is selected
if ($status_filter !== '' && $status_filter !== 'All Status') {
    $sql .= " AND status = '$status_filter'";
}

$sql .= " ORDER BY id DESC";
$team_result = mysqli_query($conn, $sql);

// Function for path includes
function includeFile($filename) {
    $paths = [__DIR__ . '/' . $filename, __DIR__ . '/../' . $filename];
    foreach ($paths as $path) {
        if (file_exists($path)) { include $path; return; }
    }
}
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
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width, 95px); padding: 24px 32px; min-height: 100vh; transition: all 0.3s ease; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .stat-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; }
        .card-orange .stat-icon-box { background: var(--primary); }
        .card-green .stat-icon-box { background: #10b981; }
        .card-blue .stat-icon-box { background: #3b82f6; }

        .filter-row { background: white; padding: 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; }
        .filter-form { display: flex; gap: 15px; align-items: center; width: 100%; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; outline: none; }
        .form-control:focus { border-color: var(--primary); }

        .table-responsive { background: white; border-radius: 12px; border: 1px solid var(--border); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; background: #f9fafb; color: #4b5563; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
        
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .view-toggle { display: flex; gap: 5px; }
        .view-btn { padding: 8px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border); background: white; }
        .view-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 600px; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-footer { padding: 16px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
        
        .btn { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; font-size: 14px; text-decoration: none; background: white; }
        .btn-primary { background: var(--primary); color: white; border: none; }
    </style>
</head>
<body>

    <?php includeFile('sidebars.php'); ?>

    <div class="main-content">
        <?php includeFile('header.php'); ?>

        <div class="page-header">
            <div>
                <h1>Team Members</h1>
                <div class="breadcrumb">
                    <i data-lucide="users" style="width:14px;"></i>
                    <span>/</span>
                    <span style="font-weight:600; color:#111827;">My Team (Development)</span>
                </div>
            </div>
            <div class="view-toggle">
                <div class="view-btn active" onclick="switchView('list')" id="btnList"><i data-lucide="list" style="width:18px;"></i></div>
                <div class="view-btn" onclick="switchView('grid')" id="btnGrid"><i data-lucide="layout-grid" style="width:18px;"></i></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-orange">
                <div class="stat-info"><span>Total Team Size</span><h3><?= $total_count ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="users"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Active Today</span><h3><?= $active_count ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="user-check"></i></div>
            </div>
            <div class="stat-card card-blue">
                <div class="stat-info"><span>Open Tasks</span><h3>14</h3></div>
                <div class="stat-icon-box"><i data-lucide="clipboard-list"></i></div>
            </div>
        </div>

        <div class="filter-row">
            <form method="GET" id="filterForm" class="filter-form">
                <div style="flex:1; position:relative;">
                    <i data-lucide="search" style="position:absolute; left:10px; top:10px; width:16px; color:#9ca3af;"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search team member..." class="form-control" style="padding-left:35px;" value="<?= htmlspecialchars($search) ?>" oninput="liveSearch()">
                </div>
                
                <select name="status" class="form-control" style="width:180px;" onchange="this.form.submit()">
                    <option value="" <?= $status_filter == '' ? 'selected' : '' ?>>All Status</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </form>
        </div>

        <div id="listView" class="table-responsive">
            <table>
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
                    <?php if (mysqli_num_rows($team_result) > 0): ?>
                        <?php while($member = mysqli_fetch_assoc($team_result)): ?>
                        <tr>
                            <td>
                                <div class="emp-profile">
                                    <img src="../assets/img/<?= $member['profile_image'] ?>" class="emp-img" onerror="this.src='https://i.pravatar.cc/150?u=<?= $member['employee_id'] ?>'">
                                    <div>
                                        <div style="font-weight:600;"><?= $member['full_name'] ?></div>
                                        <div style="font-size:11px; color:var(--text-muted);"><?= $member['employee_id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= $member['designation'] ?></td>
                            <td><?= $member['email'] ?></td>
                            <td><?= date("M d, Y", strtotime($member['joined_date'])) ?></td>
                            <td>
                                <span style="font-size:12px; font-weight:500; color: <?= $member['performance'] == 'High' ? '#10b981' : ($member['performance'] == 'Average' ? '#f59e0b' : '#6b7280') ?>;">
                                    <?= $member['performance'] ?>
                                </span>
                            </td>
                            <td><span class="status-badge <?= strtolower($member['status']) == 'active' ? 'status-active' : 'status-inactive' ?>"><?= $member['status'] ?></span></td>
                            <td>
                                <button class="btn" style="padding:5px;" onclick='openEditModal(<?= json_encode($member) ?>)'>
                                    <i data-lucide="eye" style="width:16px; color:#3b82f6;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No members found matches your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="memberModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Member Details</h3>
                <i data-lucide="x" style="cursor:pointer;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input type="text" id="mName" class="form-control" readonly></div>
                    <div class="form-group"><label>Employee ID</label><input type="text" id="mID" class="form-control" readonly></div>
                    <div class="form-group"><label>Email</label><input type="text" id="mEmail" class="form-control" readonly></div>
                    <div class="form-group"><label>Designation</label><input type="text" id="mDesig" class="form-control" readonly></div>
                    <div class="form-group"><label>Joined Date</label><input type="text" id="mJoin" class="form-control" readonly></div>
                    <div class="form-group"><label>Status</label><input type="text" id="mStatus" class="form-control" readonly></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        let typingTimer;
        function liveSearch() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 600); 
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput && searchInput.value.length > 0) {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }

        function switchView(view) {
            const listBtn = document.getElementById('btnList');
            const gridBtn = document.getElementById('btnGrid');
            const listView = document.getElementById('listView');
            if(view === 'list') {
                listBtn.classList.add('active'); gridBtn.classList.remove('active');
                listView.style.display = 'block';
            } else {
                gridBtn.classList.add('active'); listBtn.classList.remove('active');
                listView.style.display = 'none';
            }
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').innerText = "Member: " + data.full_name;
            document.getElementById('mName').value = data.full_name;
            document.getElementById('mID').value = data.employee_id;
            document.getElementById('mEmail').value = data.email;
            document.getElementById('mDesig').value = data.designation;
            document.getElementById('mJoin').value = data.joined_date;
            document.getElementById('mStatus').value = data.status;
            document.getElementById('memberModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('memberModal').classList.remove('active');
        }
    </script>
</body>
</html>