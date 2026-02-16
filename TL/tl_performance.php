<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST INCLUDE FUNCTION (Same as team_member.php to prevent errors)
function includeFile($filename) {
    $paths = [ __DIR__ . '/' . $filename, __DIR__ . '/../' . $filename ];
    foreach ($paths as $path) {
        if (file_exists($path)) { include $path; return; }
    }
    echo "";
}

// 3. MOCK DATA: TEAM PERFORMANCE
// In a real app, you would query this based on the selected Month/Year
$performanceData = [
    [
        "id" => "EMP-002", "name" => "Brian Villalobos", "role" => "Senior Developer", "img" => "12",
        "tasks_total" => 45, "tasks_done" => 42,
        "attendance" => 98, // Percentage
        "speed" => 90,      // Percentage
        "quality" => 95,    // Percentage
        "status" => "Excellent"
    ],
    [
        "id" => "EMP-004", "name" => "Stephan Peralt", "role" => "Android Developer", "img" => "14",
        "tasks_total" => 30, "tasks_done" => 20,
        "attendance" => 85,
        "speed" => 70,
        "quality" => 80,
        "status" => "Average"
    ],
    [
        "id" => "EMP-009", "name" => "Julia Gomes", "role" => "UI Designer", "img" => "25",
        "tasks_total" => 25, "tasks_done" => 25,
        "attendance" => 100,
        "speed" => 95,
        "quality" => 98,
        "status" => "Excellent"
    ],
    [
        "id" => "EMP-012", "name" => "Mark Twain", "role" => "Intern", "img" => "8",
        "tasks_total" => 15, "tasks_done" => 10,
        "attendance" => 60,
        "speed" => 50,
        "quality" => 60,
        "status" => "Poor"
    ],
];

// 4. CALCULATE AGGREGATE STATS
$total_employees = count($performanceData);
$avg_attendance = 0;
$avg_task_rate = 0;

foreach ($performanceData as $p) {
    $avg_attendance += $p['attendance'];
    $completion_rate = ($p['tasks_total'] > 0) ? ($p['tasks_done'] / $p['tasks_total']) * 100 : 0;
    $avg_task_rate += $completion_rate;
}

$avg_attendance = round($avg_attendance / $total_employees, 1);
$avg_task_rate = round($avg_task_rate / $total_employees, 1);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance - TL Dashboard</title>
    
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
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            margin: 0; 
            color: var(--text-main);
            overflow-x: hidden; 
        }

        /* --- LAYOUT --- */
        .main-content { 
            margin-left: var(--primary-sidebar-width, 95px); 
            padding: 24px 32px; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
        }

        /* --- HEADER --- */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 24px; gap: 15px; flex-wrap: wrap; 
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        /* --- STATS CARDS --- */
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

        .card-purple .stat-icon-box { background: #8b5cf6; }
        .card-green .stat-icon-box { background: #10b981; }
        .card-blue .stat-icon-box { background: #3b82f6; }

        /* --- FILTERS --- */
        .filter-row {
            background: white; padding: 15px; border-radius: 12px; 
            border: 1px solid var(--border); margin-bottom: 20px; 
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .form-control { 
            width: 100%; padding: 10px; border: 1px solid #d1d5db; 
            border-radius: 6px; font-size: 14px; box-sizing: border-box; 
            background: white; color: var(--text-main);
        }
        
        /* --- TABLE --- */
        .table-responsive { 
            background: white; border-radius: 12px; border: 1px solid var(--border); 
            overflow-x: auto; 
        }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; background: #f9fafb; color: #4b5563; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }

        /* --- PROFILES & BADGES --- */
        .emp-profile { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-Excellent { background: #dcfce7; color: #166534; }
        .status-Average { background: #fef3c7; color: #92400e; }
        .status-Poor { background: #fee2e2; color: #991b1b; }

        /* --- PROGRESS BARS --- */
        .progress-wrapper { display: flex; align-items: center; gap: 10px; width: 100%; }
        .progress-bg { flex: 1; height: 6px; background: #f3f4f6; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 10px; }
        .progress-text { font-size: 11px; font-weight: 600; min-width: 30px; text-align: right; }

        .fill-green { background: var(--success); }
        .fill-orange { background: var(--warning); }
        .fill-red { background: var(--danger); }
        .fill-blue { background: #3b82f6; }

        /* --- MODAL --- */
        .modal-overlay { 
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); 
            z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); padding: 15px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; width: 600px; max-width: 100%; border-radius: 12px; overflow: hidden; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; }
        .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed var(--border); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-size: 13px; }
        .detail-val { font-weight: 600; font-size: 14px; }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php includeFile('sidebars.php'); ?>

    <div class="main-content">
        <?php includeFile('header.php'); ?>

        <div class="page-header">
            <div>
                <h1>Team Performance</h1>
                <div class="breadcrumb">
                    <i data-lucide="bar-chart-2" style="width:14px;"></i>
                    <span>/</span>
                    <span style="font-weight:600; color:#111827;">Performance Indicator</span>
                </div>
            </div>
            <button class="btn" style="padding: 9px 16px; border:1px solid var(--border); background:white; border-radius:6px; cursor:pointer;" onclick="window.print()">
                <i data-lucide="printer" style="width:16px; margin-right:5px;"></i> Print Report
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card card-purple">
                <div class="stat-info"><span>Avg. Attendance</span><h3><?= $avg_attendance ?>%</h3></div>
                <div class="stat-icon-box"><i data-lucide="clock"></i></div>
            </div>
            <div class="stat-card card-blue">
                <div class="stat-info"><span>Task Completion</span><h3><?= $avg_task_rate ?>%</h3></div>
                <div class="stat-icon-box"><i data-lucide="check-circle"></i></div>
            </div>
            <div class="stat-card card-green">
                <div class="stat-info"><span>Top Performer</span><h3 style="font-size:18px;">Julia Gomes</h3></div>
                <div class="stat-icon-box"><i data-lucide="award"></i></div>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-group">
                <select class="form-control">
                    <option>Select Member (All)</option>
                    <?php foreach($performanceData as $p): ?>
                        <option><?= $p['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <select class="form-control">
                    <option>March 2026</option>
                    <option>February 2026</option>
                    <option>January 2026</option>
                </select>
            </div>
            <div class="filter-group" style="flex: 2;">
                <input type="text" class="form-control" placeholder="Search by designation or name...">
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Tasks (Done/Total)</th>
                        <th>Attendance</th>
                        <th>Speed</th>
                        <th>Quality</th>
                        <th>Overall Rating</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($performanceData as $row): 
                        // Logic for progress bar color
                        $attColor = $row['attendance'] > 90 ? 'fill-green' : ($row['attendance'] > 75 ? 'fill-orange' : 'fill-red');
                        $speedColor = $row['speed'] > 85 ? 'fill-blue' : 'fill-orange';
                    ?>
                    <tr>
                        <td>
                            <div class="emp-profile">
                                <img src="https://i.pravatar.cc/150?img=<?= $row['img'] ?>" class="emp-img">
                                <div>
                                    <div style="font-weight:600;"><?= $row['name'] ?></div>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= $row['role'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600; font-size:13px; margin-bottom:4px;"><?= $row['tasks_done'] ?> / <?= $row['tasks_total'] ?></div>
                            <div class="progress-wrapper">
                                <div class="progress-bg"><div class="progress-fill fill-blue" style="width: <?= ($row['tasks_done']/$row['tasks_total'])*100 ?>%"></div></div>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrapper">
                                <div class="progress-bg"><div class="progress-fill <?= $attColor ?>" style="width: <?= $row['attendance'] ?>%"></div></div>
                                <span class="progress-text"><?= $row['attendance'] ?>%</span>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrapper">
                                <div class="progress-bg"><div class="progress-fill <?= $speedColor ?>" style="width: <?= $row['speed'] ?>%"></div></div>
                                <span class="progress-text"><?= $row['speed'] ?>%</span>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex; gap:2px;">
                                <?php 
                                $stars = round($row['quality'] / 20); 
                                for($i=1; $i<=5; $i++) {
                                    $fill = $i <= $stars ? '#f59e0b' : '#e5e7eb';
                                    echo "<i data-lucide='star' style='width:12px; fill:$fill; color:$fill;'></i>";
                                }
                                ?>
                            </div>
                        </td>
                        <td><span class="status-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                        <td>
                            <button class="btn" style="padding:5px 8px;" onclick='openDetails(<?= json_encode($row) ?>)'>
                                <i data-lucide="eye" style="width:16px; color:var(--text-muted);"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalName">Performance Details</h3>
                <i data-lucide="x" style="cursor:pointer;" onclick="closeModal()"></i>
            </div>
            <div class="modal-body">
                <div style="text-align:center; margin-bottom:20px;">
                    <img id="modalImg" src="" style="width:70px; height:70px; border-radius:50%; border:3px solid #f3f4f6;">
                    <h4 id="modalRole" style="margin:10px 0 0; color:var(--text-muted);"></h4>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Tasks Assigned</span>
                    <span class="detail-val" id="dTotal"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tasks Completed</span>
                    <span class="detail-val" id="dDone" style="color:var(--primary);"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Attendance Record</span>
                    <span class="detail-val" id="dAtt"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Code Quality Score</span>
                    <span class="detail-val" id="dQual"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Delivery Speed</span>
                    <span class="detail-val" id="dSpeed"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Manager Remarks</span>
                    <span class="detail-val status-badge" id="dStatus"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openDetails(data) {
            document.getElementById('modalName').innerText = data.name + " - Performance";
            document.getElementById('modalImg').src = "https://i.pravatar.cc/150?img=" + data.img;
            document.getElementById('modalRole').innerText = data.role;
            
            document.getElementById('dTotal').innerText = data.tasks_total;
            document.getElementById('dDone').innerText = data.tasks_done;
            document.getElementById('dAtt').innerText = data.attendance + "%";
            document.getElementById('dQual').innerText = data.quality + "/100";
            document.getElementById('dSpeed').innerText = data.speed + "/100";
            
            const statusSpan = document.getElementById('dStatus');
            statusSpan.innerText = data.status;
            statusSpan.className = "status-badge status-" + data.status;

            document.getElementById('detailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) closeModal();
        }
    </script>
</body>
</html>