<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST INCLUDE FUNCTION
// This function checks the current folder AND the parent folder to prevent "No such file" errors.
function includeFile($filename) {
    $paths = [
        __DIR__ . '/' . $filename,       // Look in current TL folder
        __DIR__ . '/../' . $filename     // Look in parent folder (workack2.0)
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            include $path;
            return;
        }
    }
    // Optional: Echo a warning if not found, or silence it
    echo "";
}

// 3. MOCK DATA (Team Members)
$teamMembers = [
    [
        "id" => "EMP-002", "first_name" => "Brian", "last_name" => "Villalobos", 
        "email" => "brian@example.com", "phone" => "(179) 7382 829", 
        "designation" => "Senior Developer", "dept" => "Development", 
        "join_date" => "2024-10-24", "status" => "Active", "img" => "12",
        "emp_type" => "Contract", "performance" => "High",
        "pan" => "FGHIJ5678K", "bank_name" => "SBI", "account_no" => "0987654321"
    ],
    [
        "id" => "EMP-004", "first_name" => "Stephan", "last_name" => "Peralt", 
        "email" => "stephan@example.com", "phone" => "(929) 1022 222", 
        "designation" => "Android Developer", "dept" => "Development", 
        "join_date" => "2025-03-01", "status" => "Active", "img" => "14",
        "emp_type" => "Intern", "performance" => "Average",
        "pan" => "", "bank_name" => "", "account_no" => ""
    ],
    [
        "id" => "EMP-009", "first_name" => "Julia", "last_name" => "Gomes", 
        "email" => "julia@example.com", "phone" => "(929) 555 0192", 
        "designation" => "UI Designer", "dept" => "Development", 
        "join_date" => "2025-05-12", "status" => "Inactive", "img" => "25",
        "emp_type" => "Permanent", "performance" => "N/A",
        "pan" => "KJHGF8821L", "bank_name" => "ICICI", "account_no" => "5566778899"
    ],
];
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
            --sidebar-width: 250px; /* Default sidebar width assumption */
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            margin: 0; 
            color: var(--text-main);
            overflow-x: hidden; /* Prevent body scroll on mobile */
        }

        /* --- LAYOUT --- */
        .main-content { 
            margin-left: var(--primary-sidebar-width, 95px); /* Matches your variable */
            padding: 24px 32px; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
        }

        /* --- HEADER --- */
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px; 
            gap: 15px; 
            flex-wrap: wrap; 
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        /* --- BUTTONS & CONTROLS --- */
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

        /* --- STATS CARDS --- */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
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

        /* --- FILTERS --- */
        .filter-row {
            background: white; padding: 15px; border-radius: 12px; 
            border: 1px solid var(--border); margin-bottom: 20px; 
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }

        /* --- TABLE VIEW --- */
        .table-responsive { 
            background: white; border-radius: 12px; border: 1px solid var(--border); 
            overflow-x: auto; /* CRITICAL FOR MOBILE */
            -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; min-width: 800px; /* Forces scroll on small screens */ }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; background: #f9fafb; color: #4b5563; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        /* --- GRID VIEW --- */
        .grid-view-container { 
            display: none; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 20px; 
        }
        .grid-view-container.active { display: grid; }
        .emp-card { 
            background: white; border: 1px solid var(--border); border-radius: 12px; 
            padding: 24px; text-align: center; transition: 0.2s; position: relative;
        }
        .emp-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .card-img { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 12px; border: 3px solid #f3f4f6; object-fit: cover; }

        /* --- MODAL --- */
        .modal-overlay { 
            display: none; position: fixed; inset: 0; 
            background: rgba(0,0,0,0.5); z-index: 2000; 
            align-items: center; justify-content: center; 
            backdrop-filter: blur(2px); 
            padding: 10px; /* Padding for mobile edges */
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: white; width: 700px; max-width: 100%; 
            border-radius: 12px; overflow: hidden; 
            display: flex; flex-direction: column;
            max-height: 90vh; /* Prevents modal from being taller than screen */
        }
        .modal-header { 
            padding: 20px; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .modal-body { 
            padding: 24px; overflow-y: auto; /* Scrollable content */
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 5px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-control { 
            width: 100%; padding: 10px; border: 1px solid #d1d5db; 
            border-radius: 6px; font-size: 14px; box-sizing: border-box; 
        }
        .modal-footer { 
            padding: 16px; border-top: 1px solid var(--border); 
            display: flex; justify-content: flex-end; gap: 10px; 
            background: #fff;
        }

        /* --- RESPONSIVE MEDIA QUERIES --- */
        @media (max-width: 768px) {
            /* 1. Reset Margin for Mobile (Assuming sidebar collapses) */
            .main-content { margin-left: 0; padding: 16px; }

            /* 2. Header Stack: Title on top, buttons below */
            .page-header { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 15px; 
            }
            .page-header > div:last-child {
                width: 100%;
                justify-content: space-between;
            }

            /* 3. Filter Row Stack */
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-row input, .filter-row select { width: 100% !important; }

            /* 4. Form Grid Single Column */
            .form-grid { grid-template-columns: 1fr; }

            /* 5. Modal Width */
            .modal-box { width: 100%; }
        }
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
            <div style="display:flex; gap:10px;">
                <div class="view-toggle">
                    <div class="view-btn active" onclick="switchView('list')" id="btnList"><i data-lucide="list" style="width:18px;"></i></div>
                    <div class="view-btn" onclick="switchView('grid')" id="btnGrid"><i data-lucide="layout-grid" style="width:18px;"></i></div>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i data-lucide="user-plus" style="width:16px;"></i> 
                    <span class="btn-text">Add Member</span>
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-orange">
                <div class="stat-info"><span>Total Team Size</span><h3><?= count($teamMembers) ?></h3></div>
                <div class="stat-icon-box"><i data-lucide="users"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Active Today</span><h3>2</h3></div>
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
                <input type="text" placeholder="Search team member..." class="form-control" style="padding-left:35px;">
            </div>
            <select class="form-control" style="width:180px;"><option>All Status</option><option>Active</option></select>
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
                    <?php foreach($teamMembers as $member): ?>
                    <tr>
                        <td>
                            <div class="emp-profile">
                                <img src="https://i.pravatar.cc/150?img=<?= $member['img'] ?>" class="emp-img">
                                <div>
                                    <div style="font-weight:600;"><?= $member['first_name'].' '.$member['last_name'] ?></div>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= $member['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= $member['designation'] ?></td>
                        <td><?= $member['email'] ?></td>
                        <td><?= date("M d, Y", strtotime($member['join_date'])) ?></td>
                        <td>
                            <span style="font-size:12px; font-weight:500; color: <?= $member['performance'] == 'High' ? '#10b981' : '#f59e0b' ?>;">
                                <?= $member['performance'] ?>
                            </span>
                        </td>
                        <td><span class="status-badge <?= $member['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>"><?= $member['status'] ?></span></td>
                        <td>
                            <button class="btn" style="padding:5px;" onclick='openEditModal(<?= json_encode($member) ?>)'>
                                <i data-lucide="eye" style="width:16px; color:#3b82f6;"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="gridView" class="grid-view-container">
            <?php foreach($teamMembers as $member): ?>
            <div class="emp-card">
                <img src="https://i.pravatar.cc/150?img=<?= $member['img'] ?>" class="card-img">
                <h4 style="margin:0;"><?= $member['first_name'].' '.$member['last_name'] ?></h4>
                <p style="font-size:13px; color:var(--text-muted); margin:5px 0 15px;"><?= $member['designation'] ?></p>
                <div style="display:flex; justify-content:center; gap:5px; margin-bottom:15px;">
                    <span class="status-badge status-active"><?= $member['emp_type'] ?></span>
                </div>
                <button class="btn btn-primary" style="width:100%;" onclick='openEditModal(<?= json_encode($member) ?>)'>View Details</button>
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
                        <div class="form-group"><label>First Name</label><input type="text" id="fName" class="form-control"></div>
                        <div class="form-group"><label>Last Name</label><input type="text" id="lName" class="form-control"></div>
                        <div class="form-group"><label>Email Address</label><input type="email" id="email" class="form-control"></div>
                        <div class="form-group"><label>Phone</label><input type="text" id="phone" class="form-control"></div>
                        <div class="form-group"><label>Designation</label>
                            <select id="desig" class="form-control">
                                <option>Senior Developer</option>
                                <option>Android Developer</option>
                                <option>UI Designer</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Status</label>
                            <select id="status" class="form-control">
                                <option>Active</option>
                                <option>Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">Close</button>
                <button class="btn btn-primary">Update Profile</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // View Switching Logic
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

        // Modal Logic
        function openEditModal(data) {
            document.getElementById('modalTitle').innerText = "Team Member: " + data.first_name;
            // Pre-fill data
            document.getElementById('fName').value = data.first_name;
            document.getElementById('lName').value = data.last_name;
            document.getElementById('email').value = data.email;
            document.getElementById('phone').value = data.phone;
            document.getElementById('desig').value = data.designation;
            document.getElementById('status').value = data.status;
            
            document.body.style.overflow = 'hidden'; // Stop background scrolling
            document.getElementById('memberModal').classList.add('active');
        }

        function openAddModal() {
            document.getElementById('modalTitle').innerText = "Add New Team Member";
            document.getElementById('memberForm').reset();
            document.body.style.overflow = 'hidden'; // Stop background scrolling
            document.getElementById('memberModal').classList.add('active');
        }

        function closeModal() {
            document.body.style.overflow = 'auto'; // Restore background scrolling
            document.getElementById('memberModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) closeModal();
        }
    </script>
</body>
</html>