<?php
// 1. OUTPUT BUFFERING - Prevents "Headers already sent" by holding output in memory
ob_start();

// 2. SESSION & SECURITY - Must be at the very top
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// 3. DB CONNECTION
include('include/db_connect.php'); 

// 4. FETCH PERFORMANCE DATA
$performanceData = [];
$sql = "SELECT 
            employee_id, 
            full_name, 
            designation, 
            profile_image, 
            performance as performance_status,
            performance_score,
            project_completion_rate,
            task_completion_rate,
            attendance_rate
        FROM team_members 
        ORDER BY performance_score DESC";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $performanceData[] = [
            "id" => $row['employee_id'],
            "name" => $row['full_name'],
            "role" => $row['designation'],
            "img" => $row['profile_image'],
            "tasks_total" => 100, 
            "tasks_done" => (float)$row['task_completion_rate'],
            "attendance" => (float)$row['attendance_rate'],
            "speed" => (float)$row['performance_score'],
            "quality" => (float)$row['project_completion_rate'],
            "status" => !empty($row['performance_status']) ? $row['performance_status'] : 'Average'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Overview</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --bg-body: #f8fafd;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
            --primary: #f97316;
            --sidebar-width: 100px; 
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            margin: 0; 
            color: var(--text-main);
            overflow-x: hidden;
        }

        .layout-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            margin-left: var(--sidebar-width); 
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .main-content { 
            padding: 30px; 
            width: 100%; 
            box-sizing: border-box;
            position: relative; 
        }

        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .header-title h1 { font-size: 28px; font-weight: 700; margin: 0; color: #0f172a; }
        .header-title p { color: var(--text-muted); margin: 5px 0 0; font-size: 14px; }

        .table-header-tools {
            display: flex;
            justify-content: flex-end;
            padding: 20px 24px;
            gap: 12px;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .search-box { 
            position: relative; 
            display: flex; 
            align-items: center;
            border: 1px solid ; 
            border-radius: 8px; 
        }
        
        .search-box i { 
            position: absolute; 
            left: 14px; 
            width: 18px; 
            height: 18px;
            color: var(--text-muted); 
            pointer-events: none;
            z-index: 1;
        }

        .form-control { 
            padding: 10px 15px 10px 42px; 
            border: 1px solid var(--border); 
             
            font-size: 14px; 
            background: var(--white); 
            outline: none; 
            min-width: 280px; 
            transition: border 0.2s; 
        }
        .form-control:focus { border-color: var(--primary); }
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 24px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: #fcfcfd; border-bottom: 1px solid var(--border); }
        td { padding: 20px 24px; font-size: 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

        .emp-cell { display: flex; align-items: center; gap: 12px; }
        .emp-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .emp-name { font-weight: 600; color: #0f172a; display: block; }
        .emp-role { font-size: 12px; color: var(--text-muted); }

        .grade-badge { padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-Excellent { background: #ecfdf5; color: #10b981; }
        .status-Good { background: #eff6ff; color: #3b82f6; }
        .status-Average { background: #fff7ed; color: #f59e0b; }
        .status-Poor { background: #fef2f2; color: #ef4444; }

        .btn-view { background: #1e293b; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; }
        
        .modal-overlay { 
            display: none; 
            width: 100%; 
            background: var(--bg-body); 
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-box { width: 100%; padding-bottom: 50px; }
        .modal-nav { padding: 0 0 20px 0; display: flex; justify-content: space-between; align-items: center; }
        .back-link { display: flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; cursor: pointer; font-weight: 500; }

        .profile-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 0 30px; }
        .profile-info { display: flex; align-items: center; gap: 15px; }
        .profile-info img { width: 55px; height: 55px; border-radius: 50%; }

        .grid-dashboard { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; padding: 0; }
        .card-wide { grid-column: span 2; background: white; border-radius: 12px; border: 1px solid var(--border); padding: 30px; }
        .grade-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .metrics-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 10px; }
        .metric-item { background: #fcfcfd; border: 1px solid var(--border); padding: 20px; border-radius: 12px; }
        .metric-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; display: flex; justify-content: space-between; margin-bottom: 8px; }
        .metric-value { font-size: 24px; font-weight: 700; margin: 5px 0; display: block; }
        .metric-sub { font-size: 12px; color: var(--text-muted); }

        .score-circle-container { position: relative; width: 110px; height: 110px; }
        .score-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .score-num { font-size: 26px; font-weight: 800; color: #1e293b; }

        .info-card { background: white; border-radius: 12px; border: 1px solid var(--border); padding: 25px; }
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #334155; }
        
        .list-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .status-pill { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .on-time { background: #ecfdf5; color: #10b981; }
        .delayed { background: #fef2f2; color: #ef4444; }

        .feedback-section { margin-top: 25px; background: white; border-radius: 12px; border: 1px solid var(--border); padding: 30px; position: relative; }
        .feedback-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 20px; }
        .feedback-title { font-size: 16px; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        
        .slider-wrapper { margin-top: 20px; }
        .slider-labels { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-top: 8px; font-weight: 500; }
        .slider-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .slider-header span { font-size: 14px; font-weight: 500; color: var(--text-muted); }
        .slider-header .val { color: #1e293b; font-weight: 700; font-size: 18px; }

        input[type=range] { -webkit-appearance: none; width: 100%; background: transparent; }
        input[type=range]:focus { outline: none; }
        input[type=range]::-webkit-slider-runnable-track { width: 100%; height: 6px; cursor: pointer; background: #e2e8f0; border-radius: 10px; }
        input[type=range]::-webkit-slider-thumb { height: 18px; width: 18px; border-radius: 50%; background: #3b82f6; cursor: pointer; -webkit-appearance: none; margin-top: -6px; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }

        .comments-area { display: flex; flex-direction: column; gap: 10px; }
        .feedback-textarea { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 15px; font-family: inherit; font-size: 14px; color: var(--text-main); resize: none; min-height: 100px; box-sizing: border-box; }
        .feedback-textarea:focus { outline: none; border-color: #3b82f6; }
        
        .btn-update { 
            margin-top: 20px;
            margin-left: auto;
            display: block;
            background: #1e293b; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 14px; 
            transition: opacity 0.2s; 
        }
        .btn-update:hover { opacity: 0.9; }

        @media (max-width: 992px) {
            .content-area { margin-left: 0 !important; }
            .grid-dashboard, .feedback-grid { grid-template-columns: 1fr; }
            .card-wide { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <div class="layout-wrapper">
        <?php include('sidebars.php'); ?>

        <div class="content-area">
            <?php include('header.php'); ?>

            <div class="main-content">
                
                <div id="listView">
                    <div class="page-header">
                        <div class="header-title">
                            <h1>Performance Overview</h1>
                            <p>Track and manage employee performance ratings</p>
                        </div>
                    </div>

                    <div class="table-container">
                        <div class="table-header-tools">
                            <div class="search-box">
                                <i data-lucide="search"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search employee...">
                            </div>
                        </div>

                        <table id="performanceTable">
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th>Projects</th>
                                    <th>Attendance</th>
                                    <th>Overall Score</th>
                                    <th>Grade</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($performanceData as $row): ?>
                                <tr>
                                    <td>
                                        <div class="emp-cell">
                                            <img src="assets/profiles/<?= !empty($row['img']) ? $row['img'] : 'default.png' ?>" class="emp-img" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['name']) ?>&background=random'">
                                            <div>
                                                <span class="emp-name"><?= htmlspecialchars($row['name']) ?></span>
                                                <span class="emp-role"><?= htmlspecialchars($row['role']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-muted);">Web Development</td>
                                    <td><?= $row['quality'] ?>%</td>
                                    <td><?= $row['attendance'] ?>%</td>
                                    <td><span style="font-weight:700;"><?= $row['speed'] ?></span><span style="color:var(--text-muted); font-size:12px;">/100</span></td>
                                    <td><span class="grade-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                                    <td>
                                        <button class="btn-view" onclick='openDetails(<?= json_encode($row) ?>)'>
                                            <i data-lucide="eye" style="width:14px"></i> View
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
                        <div class="modal-nav">
                            <a class="back-link" onclick="closeModal()">
                                <i data-lucide="arrow-left" style="width:18px"></i> Back to Employee List
                            </a>
                        </div>

                        <div class="profile-header">
                            <div class="profile-info">
                                <img id="modalImg" src="">
                                <div>
                                    <h2 id="modalName" style="margin:0; font-size:24px;"></h2>
                                    <p id="modalRole" style="margin:4px 0 0; color:var(--text-muted); font-size:14px;"></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid-dashboard">
                            <div class="card-wide">
                                <div class="grade-header">
                                    <span style="font-weight:600; font-size:16px;">Performance Grade</span>
                                    <span id="dStatusLabel" style="font-weight:700; color:var(--primary); font-size:18px;">Good</span>
                                </div>
                                
                                <div style="display:flex; align-items:center; gap:50px;">
                                    <div class="score-circle-container">
                                        <svg viewBox="0 0 36 36" style="width:110px; height:110px;">
                                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#eee" stroke-width="3" />
                                            <path id="scoreCircle" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--primary)" stroke-width="3" stroke-dasharray="75, 100" />
                                        </svg>
                                        <div class="score-text">
                                            <span class="score-num" id="dSpeed">65.9</span>
                                            <span style="font-size:11px; color:var(--text-muted); font-weight:700; letter-spacing:1px;">SCORE</span>
                                        </div>
                                    </div>

                                    <div class="metrics-row" style="flex:1;">
                                        <div class="metric-item">
                                            <span class="metric-label">Projects <span style="font-weight:400">40%</span></span>
                                            <span class="metric-value" id="dQual">50%</span>
                                            <span class="metric-sub">2/4 On Time</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Tasks <span style="font-weight:400">30%</span></span>
                                            <span class="metric-value" id="dDone">75%</span>
                                            <span class="metric-sub">30 Completed</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Attendance <span style="font-weight:400">20%</span></span>
                                            <span class="metric-value" id="dAtt">82%</span>
                                            <span class="metric-sub">4 Days Leave</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Manager <span style="font-weight:400">10%</span></span>
                                            <span class="metric-value">70%</span>
                                            <span class="metric-sub">Soft Skills</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="info-card">
                                <div class="card-title"><i data-lucide="layers" style="width:18px; color:#3b82f6;"></i> Project Timelines</div>
                                <div class="list-item"><span>HRMS Portal v2</span> <span style="color:var(--text-muted)">10 Feb 2026</span> <span class="status-pill on-time">On Time</span></div>
                                <div class="list-item"><span>E-Commerce App</span> <span style="color:var(--text-muted)">01 Feb 2026</span> <span class="status-pill delayed">Delayed</span></div>
                                <div class="list-item" style="border:none;"><span>Client API Integ.</span> <span style="color:var(--text-muted)">15 Jan 2026</span> <span class="status-pill on-time">On Time</span></div>
                            </div>

                            <div class="info-card">
                                <div class="card-title"><i data-lucide="list-checks" style="width:18px; color:var(--primary);"></i> Task Efficiency</div>
                                <div class="list-item"><span>Total Tasks Assigned</span> <span style="font-weight:700;">40</span></div>
                                <div class="list-item"><span>Completed On Time</span> <span style="font-weight:700; color:#10b981;">30</span></div>
                                <div class="list-item" style="border:none;"><span>Overdue / Pending</span> <span style="font-weight:700; color:#ef4444;">10</span></div>
                            </div>
                        </div>

                        <div class="feedback-section">
                            <div class="feedback-title">
                                <i data-lucide="message-square" style="width:20px; color:var(--text-muted);"></i> 
                                Manager's Feedback (10% Score)
                            </div>
                            
                            <div class="feedback-grid">
                                <div class="slider-wrapper">
                                    <div class="slider-header">
                                        <span>Soft Skills Rating</span>
                                        <span class="val" id="softSkillVal">70</span>
                                    </div>
                                    <input type="range" min="0" max="100" value="70" id="softSkillSlider" oninput="updateSliderVal(this.value)">
                                    <div class="slider-labels">
                                        <span>Poor</span>
                                        <span>Excellent</span>
                                    </div>
                                </div>

                                <div class="comments-area">
                                    <span style="font-size:14px; font-weight:500; color:var(--text-muted);">Comments</span>
                                    <textarea class="feedback-textarea" id="managerComments" placeholder="Enter feedback here...">Mike needs to improve on meeting deadlines. Technical skills are good.</textarea>
                                </div>
                            </div>
                        </div>
                        <button class="btn-update">Update Review</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function updateSliderVal(val) {
            document.getElementById('softSkillVal').innerText = val;
        }

        function openDetails(data) {
            document.getElementById('modalName').innerText = data.name;
            document.getElementById('modalImg').src = "assets/profiles/" + (data.img ? data.img : 'default.png');
            document.getElementById('modalRole').innerText = data.role + " • Web Development";
            
            document.getElementById('dDone').innerText = data.tasks_done + "%";
            document.getElementById('dAtt').innerText = data.attendance + "%";
            document.getElementById('dQual').innerText = data.quality + "%";
            document.getElementById('dSpeed').innerText = data.speed;
            
            const scoreLabel = document.getElementById('dStatusLabel');
            scoreLabel.innerText = data.status;
            
            const circle = document.getElementById('scoreCircle');
            circle.setAttribute('stroke-dasharray', `${data.speed}, 100`);

            document.getElementById('listView').style.display = 'none';
            document.getElementById('detailModal').classList.add('active');
            window.scrollTo(0, 0);
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
            document.getElementById('listView').style.display = 'block';
        }

        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toUpperCase();
            let rows = document.querySelector("#performanceTable tbody").rows;
            for (let i = 0; i < rows.length; i++) {
                let name = rows[i].querySelector('.emp-name').textContent.toUpperCase();
                rows[i].style.display = name.includes(filter) ? "" : "none";
            }
        });
    </script>
</body>
</html>
<?php 
// End buffering
ob_end_flush(); 
<<<<<<< HEAD
?>
=======
?>
>>>>>>> 077886b7eaf9c615de3a9090f323d87194fb2660
