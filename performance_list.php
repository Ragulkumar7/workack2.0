<?php
// 1. OUTPUT BUFFERING - Prevents "Headers already sent"
ob_start();

// 2. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'Manager';

// 3. DB CONNECTION (Dynamic Path mapping)
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/workack2.0/include/db_connect.php';
if (file_exists($dbPath)) { include_once($dbPath); } 
else { include_once('../include/db_connect.php'); }

/// 4. FETCH PERFORMANCE DATA DYNAMICALLY BASED ON HIERARCHY
$performanceData = [];

if ($user_role === 'HR' || $user_role === 'Admin') {
    // LEVEL 4 (HR/Admin): Sees EVERYONE in the company.
    $sql = "SELECT ep.user_id, ep.emp_id_code as employee_id, ep.full_name, ep.designation, ep.profile_img as profile_image, 
            per.manager_rating_pct, per.manager_comments, per.self_review 
            FROM employee_profiles ep 
            LEFT JOIN employee_performance per ON ep.user_id = per.user_id
            WHERE ep.user_id != ?"; // Optionally exclude themselves from the list
            
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);

} elseif ($user_role === 'Manager') {
    // LEVEL 3 (Manager): Sees their direct reports (Team Leads) AND the employees reporting to those Team Leads.
    $sql = "SELECT ep.user_id, ep.emp_id_code as employee_id, ep.full_name, ep.designation, ep.profile_img as profile_image, 
            per.manager_rating_pct, per.manager_comments, per.self_review 
            FROM employee_profiles ep 
            LEFT JOIN employee_performance per ON ep.user_id = per.user_id 
            WHERE ep.reporting_to = ? 
               OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE reporting_to = ?)";
               
    $stmt = mysqli_prepare($conn, $sql);
    // Bind the Manager's ID twice (once for direct reports, once for second-level reports)
    mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $current_user_id);

} else {
    // LEVEL 2 (Team Lead): Sees ONLY the employees reporting directly to them.
    $sql = "SELECT ep.user_id, ep.emp_id_code as employee_id, ep.full_name, ep.designation, ep.profile_img as profile_image, 
            per.manager_rating_pct, per.manager_comments, per.self_review 
            FROM employee_profiles ep 
            LEFT JOIN employee_performance per ON ep.user_id = per.user_id 
            WHERE ep.reporting_to = ?";
            
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // [UPGRADE] Prepared efficient queries for the loop
    // 1. Dynamic working days logic
    $att_stmt = $conn->prepare("SELECT COUNT(*) as total_days, SUM(CASE WHEN status = 'On Time' THEN 1 ELSE 0 END) as present_days, SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days FROM attendance WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    
    // 2. Exclude cancelled tasks
    $task_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue FROM personal_taskboard WHERE user_id = ? AND status != 'cancelled'");
    
    // 3. Strict User ID matching for Projects
    $proj_stmt = $conn->prepare("SELECT status FROM project_tasks WHERE assigned_to_user_id = ? LIMIT 10");

    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $emp_id = $row['user_id'];
            $emp_full_name = $row['full_name'];
            $mgr_pct = $row['manager_rating_pct'] ?? 0;

            // 1. Attendance Calculation (15%)
            $att_stmt->bind_param("i", $emp_id);
            $att_stmt->execute();
            $att_data = $att_stmt->get_result()->fetch_assoc();
            $total_att_days = $att_data['total_days'] > 0 ? $att_data['total_days'] : 1; 
            $present_days = $att_data['present_days'] ?? 0;
            $late_days = $att_data['late_days'] ?? 0;
            $attendance_pct = min(100, round(($present_days / $total_att_days) * 100));

            // 2. Task Calculation (25%)
            $task_stmt->bind_param("i", $emp_id);
            $task_stmt->execute();
            $task_res = $task_stmt->get_result()->fetch_assoc();
            $task_total = $task_res['total'] > 0 ? $task_res['total'] : 1;
            $completed_tasks = $task_res['completed'] ?? 0;
            $overdue_tasks = $task_res['overdue'] ?? 0;
            $task_completion_pct = round(($completed_tasks / $task_total) * 100);

            // 3. Project Calculation (30%)
            $proj_stmt->bind_param("i", $emp_id);
            $proj_stmt->execute();
            $projects_list = $proj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $on_time_projects = 0;
            foreach($projects_list as $p) { if($p['status'] == 'Completed') $on_time_projects++; }
            $proj_total = count($projects_list) > 0 ? count($projects_list) : 1;
            $project_completion_pct = round(($on_time_projects / $proj_total) * 100);

            // 4. System Reliability Rating (10%) - Min Cap at 40
            $automated_rating = 100 - ($late_days * 5) - ($overdue_tasks * 5);
            $automated_rating = max(40, min(100, $automated_rating));

            // FINAL ENTERPRISE AGGREGATION
            $score = ($project_completion_pct * 0.30) + ($task_completion_pct * 0.25) + ($attendance_pct * 0.15) + ($automated_rating * 0.10) + ($mgr_pct * 0.20);
            $score = round($score, 1);

            // Enterprise Grading Scale
            if($score >= 90) $grade = "Outstanding";
            elseif($score >= 75) $grade = "Exceeds Expectations";
            elseif($score >= 50) $grade = "Meets Expectations";
            else $grade = "Needs Improvement";

            $performanceData[] = [
                "user_id" => $emp_id,
                "id" => $row['employee_id'],
                "name" => $emp_full_name,
                "role" => $row['designation'],
                "img" => $row['profile_image'],
                "tasks_total" => $task_res['total'], 
                "tasks_done" => $task_completion_pct,
                "completed_on_time" => $completed_tasks,
                "overdue" => $overdue_tasks,
                "attendance" => $attendance_pct,
                "present_days" => $present_days,
                "total_att_days" => $total_att_days,
                "speed" => $score,
                "quality" => $project_completion_pct,
                "on_time_proj" => $on_time_projects,
                "total_proj" => count($projects_list),
                "sys_score" => $automated_rating,
                "mgr_score" => $mgr_pct,
                "comments" => $row['manager_comments'],
                "self_review" => $row['self_review'],
                "status" => $grade
            ];
        }
    }
    mysqli_stmt_close($stmt);
}

// Sort dynamically calculated data by score (Highest to lowest)
usort($performanceData, function($a, $b) {
    return $b['speed'] <=> $a['speed'];
});
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
            --primary: #0d9488;
            --sidebar-width: 100px; 
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); margin: 0; color: var(--text-main); overflow-x: hidden; }
        .layout-wrapper { display: flex; min-height: 100vh; }
        .content-area { flex: 1; margin-left: var(--sidebar-width); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .main-content { padding: 30px; width: 100%; box-sizing: border-box; position: relative; }

        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .header-title h1 { font-size: 28px; font-weight: 700; margin: 0; color: #0f172a; }
        .header-title p { color: var(--text-muted); margin: 5px 0 0; font-size: 14px; }

        .table-header-tools { display: flex; justify-content: flex-end; padding: 20px 24px; border-bottom: 1px solid #f1f5f9; }
        .search-box { position: relative; display: flex; align-items: center; }
        .search-box i { position: absolute; left: 14px; width: 18px; height: 18px; color: var(--text-muted); pointer-events: none; z-index: 1; }
        .form-control { padding: 10px 15px 10px 42px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; min-width: 280px; outline: none; }
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
        .status-Outstanding { background: #ecfdf5; color: #10b981; }
        .status-Exceeds { background: #eff6ff; color: #3b82f6; }
        .status-Meets { background: #fff7ed; color: #f59e0b; }
        .status-Needs { background: #fef2f2; color: #ef4444; }

        .btn-view { background: #1e293b; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; transition: 0.2s;}
        .btn-view:hover { background: var(--primary); }
        
        .modal-overlay { display: none; width: 100%; animation: fadeIn 0.3s ease; }
        .modal-overlay.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .modal-box { width: 100%; padding-bottom: 50px; }
        .modal-nav { display: flex; justify-content: space-between; padding: 0 0 20px 0; }
        .back-link { display: flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; cursor: pointer; font-weight: 600; }
        .back-link:hover { color: var(--text-main); }
        .btn-evaluate { background: var(--primary); color: white; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px;}
        .btn-evaluate:hover { background: #0f766e; }

        .profile-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 0 30px; }
        .profile-info { display: flex; align-items: center; gap: 15px; }
        .profile-info img { width: 55px; height: 55px; border-radius: 50%; }

        .grid-dashboard { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; padding: 0; }
        .card-wide { grid-column: span 2; background: white; border-radius: 12px; border: 1px solid var(--border); padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);}
        .grade-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        
        .metrics-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 10px; }
        .metric-item { background: #fcfcfd; border: 1px solid var(--border); padding: 15px; border-radius: 12px; }
        .metric-label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; display: flex; justify-content: space-between; margin-bottom: 8px; }
        .metric-value { font-size: 20px; font-weight: 700; margin: 5px 0; display: block; color: #0f172a;}
        .metric-sub { font-size: 11px; color: var(--text-muted); }

        .score-circle-container { position: relative; width: 110px; height: 110px; }
        .score-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .score-num { font-size: 26px; font-weight: 800; color: #1e293b; }

        .info-card { background: white; border-radius: 12px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);}
        .card-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #334155; }
        
        .list-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .list-item:last-child { border-bottom: none; }

        .feedback-text-box { background: #fcfcfd; padding: 20px; border-radius: 8px; border: 1px solid var(--border); font-size: 14px; color: #334155; font-style: italic; line-height: 1.6;}

        @media (max-width: 992px) {
            .content-area { margin-left: 0 !important; }
            .grid-dashboard { grid-template-columns: 1fr; }
            .card-wide { grid-column: span 1; }
            .metrics-row { grid-template-columns: 1fr 1fr; }
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
                            <p>Track and review synchronized dynamic team metrics</p>
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
                                    <th>Designation</th>
                                    <th>Project Score</th>
                                    <th>Task Score</th>
                                    <th>Overall Score</th>
                                    <th>Grade</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($performanceData)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                            No team members are currently assigned to you.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($performanceData as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="emp-cell">
                                                <img src="<?= !empty($row['img']) && strpos($row['img'], 'http') === 0 ? $row['img'] : 'assets/profiles/'.(!empty($row['img']) ? $row['img'] : 'default.png') ?>" class="emp-img" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['name']) ?>&background=random'">
                                                <div>
                                                    <span class="emp-name"><?= htmlspecialchars($row['name']) ?></span>
                                                    <span class="emp-role">ID: <?= htmlspecialchars($row['id'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="color: var(--text-muted); font-weight: 500;"><?= htmlspecialchars($row['role']) ?></td>
                                        <td><?= $row['quality'] ?>%</td>
                                        <td><?= $row['tasks_done'] ?>%</td>
                                        <td><span style="font-weight:700; color: #0f172a;"><?= rtrim(rtrim($row['speed'], '0'), '.') ?></span><span style="color:var(--text-muted); font-size:12px;">/100</span></td>
                                        <td>
                                            <?php 
                                                $b_class = 'status-Needs';
                                                if (strpos($row['status'], 'Outstanding') !== false) $b_class = 'status-Outstanding';
                                                elseif (strpos($row['status'], 'Exceeds') !== false) $b_class = 'status-Exceeds';
                                                elseif (strpos($row['status'], 'Meets') !== false) $b_class = 'status-Meets';
                                            ?>
                                            <span class="grade-badge <?= $b_class ?>"><?= $row['status'] ?></span>
                                        </td>
                                        <td>
                                            <button class="btn-view" onclick='openDetails(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>
                                                <i data-lucide="eye" style="width:14px"></i> Review
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
                            <a href="evaluate_team.php" class="btn-evaluate">
                                <i data-lucide="gavel" style="width:16px"></i> Evaluate & Grade
                            </a>
                        </div>

                        <div class="profile-header">
                            <div class="profile-info">
                                <img id="modalImg" src="">
                                <div>
                                    <h2 id="modalName" style="margin:0; font-size:24px; font-weight: 700; color: #0f172a;"></h2>
                                    <p id="modalRole" style="margin:4px 0 0; color:var(--primary); font-size:13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid-dashboard">
                            <div class="card-wide">
                                <div class="grade-header">
                                    <span style="font-weight:600; font-size:16px;">Enterprise Performance Grade</span>
                                    <span id="dStatusLabel" style="font-weight:800; font-size:20px;">Meets Expectations</span>
                                </div>
                                
                                <div style="display:flex; align-items:center; gap:40px;">
                                    <div class="score-circle-container">
                                        <svg viewBox="0 0 36 36" style="width:110px; height:110px; transform: rotate(-90deg);">
                                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#eee" stroke-width="3" />
                                            <path id="scoreCircle" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--primary)" stroke-width="3" stroke-dasharray="0, 100" style="transition: stroke-dasharray 1s ease-out;" />
                                        </svg>
                                        <div class="score-text">
                                            <span class="score-num" id="dSpeed">0</span>
                                            <span style="font-size:11px; color:var(--text-muted); font-weight:700; letter-spacing:1px;">SCORE</span>
                                        </div>
                                    </div>

                                    <div class="metrics-row" style="flex:1;">
                                        <div class="metric-item">
                                            <span class="metric-label">Projects <span style="font-weight:400">30%</span></span>
                                            <span class="metric-value" id="dQual">0%</span>
                                            <span class="metric-sub" id="dQualSub">0/0 Completed</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Tasks <span style="font-weight:400">25%</span></span>
                                            <span class="metric-value" id="dDone">0%</span>
                                            <span class="metric-sub" id="dDoneSub">0 Completed</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Attendance <span style="font-weight:400">15%</span></span>
                                            <span class="metric-value" id="dAtt">0%</span>
                                            <span class="metric-sub" id="dAttSub">0/0 Days</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">System <span style="font-weight:400">10%</span></span>
                                            <span class="metric-value" id="dSys">0%</span>
                                            <span class="metric-sub">Reliability</span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Manager <span style="font-weight:400">20%</span></span>
                                            <span class="metric-value" id="dManager">0%</span>
                                            <span class="metric-sub">Soft Skills</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="info-card">
                                <div class="card-title"><i data-lucide="user-pen" style="width:20px; color:#0d9488;"></i> Employee Self-Review</div>
                                <div class="feedback-text-box" id="dSelfReview">
                                    No self-review submitted by the employee yet.
                                </div>
                            </div>

                            <div class="info-card">
                                <div class="card-title"><i data-lucide="message-square-quote" style="width:20px; color:#8b5cf6;"></i> Your Official Feedback</div>
                                <div class="feedback-text-box" id="dComments">
                                    No official feedback provided yet.
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openDetails(data) {
            document.getElementById('modalName').innerText = data.name;
            
            // Format Image
            let imgSource = data.img;
            if(!imgSource || imgSource === 'default.png') {
                imgSource = `https://ui-avatars.com/api/?name=${encodeURIComponent(data.name)}&background=0d9488&color=fff`;
            } else if (!imgSource.startsWith('http')) {
                imgSource = "assets/profiles/" + imgSource;
            }
            document.getElementById('modalImg').src = imgSource;
            document.getElementById('modalRole').innerText = data.role;
            
            // Metric Percentages
            document.getElementById('dQual').innerText = data.quality + "%";
            document.getElementById('dDone').innerText = data.tasks_done + "%";
            document.getElementById('dAtt').innerText = data.attendance + "%";
            document.getElementById('dSys').innerText = data.sys_score + "%";
            document.getElementById('dManager').innerText = data.mgr_score + "%";
            
            // Metric Subtexts
            document.getElementById('dQualSub').innerText = data.on_time_proj + "/" + data.total_proj + " Completed";
            document.getElementById('dDoneSub').innerText = data.completed_on_time + " Completed";
            document.getElementById('dAttSub').innerText = data.present_days + "/" + data.total_att_days + " Days";

            // Reviews
            document.getElementById('dSelfReview').innerText = data.self_review ? '"' + data.self_review + '"' : 'Employee has not submitted a self-review yet.';
            document.getElementById('dComments').innerText = data.comments ? '"' + data.comments + '"' : 'No official feedback provided yet.';

            // Score Formatting
            let rawScore = parseFloat(data.speed);
            let formattedScore = Number.isInteger(rawScore) ? rawScore : rawScore.toFixed(1);
            document.getElementById('dSpeed').innerText = formattedScore;
            
            const scoreLabel = document.getElementById('dStatusLabel');
            scoreLabel.innerText = data.status;
            
            // Dynamic Coloring based on Enterprise Grades
            let color = '#ef4444'; // Red (Needs Improvement)
            if(rawScore >= 90) color = '#10b981'; // Green (Outstanding)
            else if (rawScore >= 75) color = '#3b82f6'; // Blue (Exceeds)
            else if (rawScore >= 50) color = '#f97316'; // Orange (Meets)

            scoreLabel.style.color = color;
            document.getElementById('scoreCircle').setAttribute('stroke', color);
            
            // Animation for Ring
            setTimeout(() => {
                const circle = document.getElementById('scoreCircle');
                circle.setAttribute('stroke-dasharray', `${formattedScore}, 100`);
            }, 50);

            document.getElementById('listView').style.display = 'none';
            document.getElementById('detailModal').classList.add('active');
            window.scrollTo(0, 0);
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
            document.getElementById('listView').style.display = 'block';
            document.getElementById('scoreCircle').setAttribute('stroke-dasharray', `0, 100`);
        }

        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toUpperCase();
            let rows = document.querySelector("#performanceTable tbody").rows;
            for (let i = 0; i < rows.length; i++) {
                let nameCell = rows[i].querySelector('.emp-name');
                if(nameCell) { 
                    let name = nameCell.textContent.toUpperCase();
                    rows[i].style.display = name.includes(filter) ? "" : "none";
                }
            }
        });

    </script>
</body>
</html>
<?php 
ob_end_flush(); 
?>