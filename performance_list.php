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

// 3. DB CONNECTION
$dbPath = __DIR__ . '/include/db_connect.php';
if (file_exists($dbPath)) { 
    include_once($dbPath); 
} else { 
    include_once('../include/db_connect.php'); 
}

// =========================================================================
// ACTION 1: HANDLE HR HIKE / PROMOTION SUBMISSION (HR/Admin ONLY)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_hike') {
    if (in_array($user_role, ['HR', 'Admin', 'CFO', 'CEO'])) {
        $emp_id = (int)$_POST['hike_emp_id'];
        $new_salary = trim($_POST['new_salary']);
        $new_designation = trim($_POST['new_designation']);
        $effective_date = $_POST['effective_date'];
        $remarks = trim($_POST['remarks']);

        if (!empty($new_designation)) {
            $stmt = $conn->prepare("UPDATE employee_profiles SET designation = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_designation, $emp_id);
            $stmt->execute();
        }

        $conn->query("CREATE TABLE IF NOT EXISTS salary_hikes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            approved_by INT NOT NULL,
            new_salary VARCHAR(100),
            new_designation VARCHAR(150),
            effective_date DATE,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $h_stmt = $conn->prepare("INSERT INTO salary_hikes (user_id, approved_by, new_salary, new_designation, effective_date, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $h_stmt->bind_param("iissss", $emp_id, $current_user_id, $new_salary, $new_designation, $effective_date, $remarks);
        $h_stmt->execute();

        $_SESSION['success_msg'] = "Appraisal and Salary Hike successfully approved!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// =========================================================================
// ACTION 2: HANDLE EVALUATION SUBMISSION (Role-Neutral)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_evaluation') {
    $emp_id = (int)$_POST['eval_emp_id'];
    $mgr_score = (float)$_POST['mgr_score']; 
    $mgr_comments = trim($_POST['mgr_comments']); 

    $chk = $conn->prepare("SELECT id FROM employee_performance WHERE user_id = ?");
    $chk->bind_param("i", $emp_id);
    $chk->execute();
    $res = $chk->get_result();

    if ($res->num_rows > 0) {
        $upd = $conn->prepare("UPDATE employee_performance SET manager_rating_pct = ?, manager_comments = ? WHERE user_id = ?");
        $upd->bind_param("dsi", $mgr_score, $mgr_comments, $emp_id);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO employee_performance (user_id, manager_rating_pct, manager_comments) VALUES (?, ?, ?)");
        $ins->bind_param("ids", $emp_id, $mgr_score, $mgr_comments);
        $ins->execute();
    }

    $_SESSION['success_msg'] = "Performance evaluation saved successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// =========================================================================
// INTELLIGENT HRMS FUNCTIONS (AI, Burnout, Task Health)
// =========================================================================

function generateAIInsight($data) {
    $insights = [];
    if ($data['quality'] >= 85) $insights[] = "Exceptional project delivery.";
    if ($data['tasks_done'] < 50) $insights[] = "Task execution is significantly lacking.";
    if ($data['attendance'] < 80) $insights[] = "Attendance reliability is a major concern.";
    if ($data['mgr_score'] >= 85) $insights[] = "Displays strong ownership and leadership potential.";
    
    if (empty($insights)) return "Consistent and stable operational performance.";
    return implode(" ", $insights);
}

function checkPromotionEligibility($data) {
    if ($data['speed'] >= 90 && $data['mgr_score'] >= 85 && $data['quality'] >= 85) {
        return ["text" => "Prime for Promotion", "color" => "bg-emerald-50 text-emerald-700 border-emerald-200"];
    }
    if ($data['speed'] >= 80) {
        return ["text" => "Bonus Eligible", "color" => "bg-blue-50 text-blue-700 border-blue-200"];
    }
    return ["text" => "Standard Review", "color" => "bg-slate-50 text-slate-600 border-slate-200"];
}

function calculateBurnoutRisk($data) {
    $risk = 0;
    if ($data['attendance'] < 75) $risk += 30; // Fatigue / Absences
    if ($data['tasks_done'] < 50) $risk += 30; // Dropping productivity
    if ($data['overdue'] > 5) $risk += 30;     // Burnout/Overload
    if ($data['mgr_score'] < 60) $risk += 10;  // Disengagement

    if ($risk >= 60) return ["level" => "Burnout Warning", "color" => "bg-rose-50 text-rose-700 border-rose-200", "icon" => "flame"];
    if ($risk >= 30) return ["level" => "Overloaded", "color" => "bg-amber-50 text-amber-700 border-amber-200", "icon" => "battery-medium"];
    return ["level" => "Healthy", "color" => "bg-emerald-50 text-emerald-700 border-emerald-200", "icon" => "battery-charging"];
}

function getTaskHealth($completed, $overdue, $total) {
    if ($total == 0) return ["status" => "No Tasks", "color" => "text-slate-400 bg-slate-50"];
    $overdue_pct = ($overdue / $total) * 100;
    if ($overdue_pct > 30) return ["status" => "Critical", "color" => "text-rose-600 bg-rose-50"];
    if ($overdue_pct > 10) return ["status" => "Attention", "color" => "text-amber-600 bg-amber-50"];
    return ["status" => "Healthy", "color" => "text-emerald-600 bg-emerald-50"];
}

// =========================================================================
// 4. FETCH PERFORMANCE DATA (OPTIMIZED BULK AGGREGATION - NO N+1 QUERIES)
// =========================================================================
$performanceData = [];
$employees = [];
$userIds = [];

if (in_array($user_role, ['HR', 'Admin', 'CFO', 'CEO'])) {
    $sql = "SELECT ep.user_id, ep.emp_id_code as employee_id, ep.full_name, ep.designation, ep.profile_img as profile_image, 
            per.manager_rating_pct, per.manager_comments, per.self_review 
            FROM employee_profiles ep 
            LEFT JOIN employee_performance per ON ep.user_id = per.user_id WHERE ep.user_id != ?"; 
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);
} elseif (in_array($user_role, ['Manager', 'Project Manager'])) {
    $sql = "SELECT ep.user_id, ep.emp_id_code as employee_id, ep.full_name, ep.designation, ep.profile_img as profile_image, 
            per.manager_rating_pct, per.manager_comments, per.self_review 
            FROM employee_profiles ep 
            LEFT JOIN employee_performance per ON ep.user_id = per.user_id 
            WHERE ep.reporting_to = ? OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE reporting_to = ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $current_user_id);
} else {
    $sql = "SELECT ep.user_id, ep.emp_id_code as employee_id, ep.full_name, ep.designation, ep.profile_img as profile_image, 
            per.manager_rating_pct, per.manager_comments, per.self_review 
            FROM employee_profiles ep 
            LEFT JOIN employee_performance per ON ep.user_id = per.user_id WHERE ep.reporting_to = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Step 1: Collect Base Users
    while($row = mysqli_fetch_assoc($result)) {
        $employees[$row['user_id']] = $row;
        $userIds[] = $row['user_id'];
    }
    mysqli_stmt_close($stmt);

    // Step 2: Run Bulk Aggregation Queries ONLY IF employees exist
    if (!empty($userIds)) {
        $idStr = implode(',', array_map('intval', $userIds)); // Safe implosion

        // Bulk Attendance (Last 30 Days)
        $attData = [];
        $att_q = "SELECT user_id, COUNT(*) as total_days, 
                  SUM(CASE WHEN status = 'On Time' THEN 1 ELSE 0 END) as present_days, 
                  SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days 
                  FROM attendance WHERE user_id IN ($idStr) AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY user_id";
        $att_res = $conn->query($att_q);
        if($att_res) while($r = $att_res->fetch_assoc()) $attData[$r['user_id']] = $r;

        // Bulk Tasks
        $taskData = [];
        $task_q = "SELECT user_id, COUNT(*) as total, 
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, 
                   SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue 
                   FROM personal_taskboard WHERE user_id IN ($idStr) AND status != 'cancelled' GROUP BY user_id";
        $task_res = $conn->query($task_q);
        if($task_res) while($r = $task_res->fetch_assoc()) $taskData[$r['user_id']] = $r;

        // Bulk Projects
        $projData = [];
        $proj_q = "SELECT assigned_to_user_id as user_id, 
                   COUNT(*) as total, 
                   SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as on_time 
                   FROM project_tasks WHERE assigned_to_user_id IN ($idStr) GROUP BY assigned_to_user_id";
        $proj_res = $conn->query($proj_q);
        if($proj_res) while($r = $proj_res->fetch_assoc()) $projData[$r['user_id']] = $r;

        // Step 3: Strict Math & Processing
        foreach ($employees as $emp_id => $row) {
            
            $att = $attData[$emp_id] ?? ['total_days' => 0, 'present_days' => 0, 'late_days' => 0];
            $tsk = $taskData[$emp_id] ?? ['total' => 0, 'completed' => 0, 'overdue' => 0];
            $prj = $projData[$emp_id] ?? ['total' => 0, 'on_time' => 0];

            // FIXED MATH LOGIC: If total assigned is 0, score is 0% (prevents fake 100% inflation)
            $attendance_pct = ($att['total_days'] > 0) ? min(100, round(($att['present_days'] / $att['total_days']) * 100)) : 0;
            $task_completion_pct = ($tsk['total'] > 0) ? round(($tsk['completed'] / $tsk['total']) * 100) : 0;
            $project_completion_pct = ($prj['total'] > 0) ? round(($prj['on_time'] / $prj['total']) * 100) : 0;
            
            // System Score: Defaults to 100%, penalizes for bad actions
            $automated_rating = max(40, min(100, 100 - ($att['late_days'] * 5) - ($tsk['overdue'] * 5)));
            $mgr_pct = $row['manager_rating_pct'] ?? 0;

            // Final Calculation
            $score = round(($project_completion_pct * 0.30) + ($task_completion_pct * 0.25) + ($attendance_pct * 0.15) + ($automated_rating * 0.10) + ($mgr_pct * 0.20), 1);

            if($score >= 90) $grade = "Outstanding";
            elseif($score >= 75) $grade = "Exceeds Expectations";
            elseif($score >= 50) $grade = "Meets Expectations";
            else $grade = "Needs Improvement";

            // Prepare Data for Smart Features
            $ai_data = [
                'quality' => $project_completion_pct,
                'tasks_done' => $task_completion_pct,
                'attendance' => $attendance_pct,
                'mgr_score' => $mgr_pct,
                'speed' => $score,
                'overdue' => $tsk['overdue']
            ];

            $promo = checkPromotionEligibility($ai_data);
            $risk = calculateBurnoutRisk($ai_data);
            $insight = generateAIInsight($ai_data);
            $task_health = getTaskHealth($tsk['completed'], $tsk['overdue'], $tsk['total']);

            $performanceData[] = [
                "user_id" => $emp_id,
                "id" => $row['employee_id'],
                "name" => $row['full_name'],
                "role" => $row['designation'],
                "img" => $row['profile_image'],
                "tasks_total" => $tsk['total'], 
                "tasks_done" => $task_completion_pct,
                "completed_on_time" => $tsk['completed'],
                "overdue" => $tsk['overdue'],
                "attendance" => $attendance_pct,
                "present_days" => $att['present_days'],
                "total_att_days" => $att['total_days'],
                "speed" => $score,
                "quality" => $project_completion_pct,
                "on_time_proj" => $prj['on_time'],
                "total_proj" => $prj['total'],
                "sys_score" => $automated_rating,
                "mgr_score" => $mgr_pct,
                "comments" => $row['manager_comments'],
                "self_review" => $row['self_review'],
                "status" => $grade,
                "promo_text" => $promo['text'],
                "promo_color" => $promo['color'],
                "risk_text" => $risk['level'],
                "risk_color" => $risk['color'],
                "risk_icon" => $risk['icon'],
                "ai_insight" => $insight,
                "task_health" => $task_health['status'],
                "task_health_color" => $task_health['color']
            ];
        }
    }
}

// RANKING SYSTEM: Sort by highest score, then assign ranks
usort($performanceData, function($a, $b) { return $b['speed'] <=> $a['speed']; });
foreach ($performanceData as $index => &$emp) {
    $emp['rank'] = $index + 1;
}
unset($emp);

$sidebarPath = '../sidebars.php';
$headerPath = '../header.php';
if (!file_exists($sidebarPath)) { 
    $sidebarPath = 'sidebars.php'; 
    $headerPath = 'header.php'; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance & Analytics | Workack HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        /* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT 
           ========================================================== */
        .main-content, #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            padding: 30px; /* Adjust inner padding as needed */
            min-height: 100vh;
        }

        /* Desktop: Shifts content right when secondary sub-menu opens */
        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Mobile & Tablet Adjustments */
        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 80px 15px 30px !important; /* Top padding clears the hamburger menu */
            }
            
            /* Prevent shifting on mobile (menu floats over content instead) */
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* CHANGED: z-index updated to 9999 to cover the sidebar overlay */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: translateY(20px); transition: transform 0.3s ease; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
        .modal-overlay.active .modal-content { transform: translateY(0); }

        #detailModal .modal-content { width: 100%; max-width: 950px; }
        .action-modal .modal-content { width: 100%; max-width: 500px; }

        .score-circle { transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dasharray 1.5s ease-out; }
        
        .podium-card { position: relative; overflow: hidden; transition: 0.3s; }
        .podium-card:hover { transform: translateY(-5px); }
        .rank-badge { position: absolute; top: -10px; right: -10px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 900; color: white; transform: rotate(15deg); font-size: 16px;}
        .rank-1 { background: linear-gradient(135deg, #fbbf24, #d97706); box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
        .rank-2 { background: linear-gradient(135deg, #cbd5e1, #94a3b8); box-shadow: 0 4px 10px rgba(148, 163, 184, 0.3); }
        .rank-3 { background: linear-gradient(135deg, #d97706, #b45309); box-shadow: 0 4px 10px rgba(180, 83, 9, 0.3); }
    </style>
</head>
<body>

    <?php include($sidebarPath); ?>
    <?php include($headerPath); ?>

    <main id="mainContent" class="main-content">
        
        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl mb-6 font-bold flex items-center gap-3 shadow-sm animate-pulse" id="successAlert">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <?= $_SESSION['success_msg'] ?>
                <?php unset($_SESSION['success_msg']); ?>
            </div>
            <script>setTimeout(() => document.getElementById('successAlert').style.display='none', 4000);</script>
        <?php endif; ?>

        <div class="mb-8 flex flex-wrap justify-between items-end gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-[#1b5a5a] tracking-tight">Team Analytics & Performance</h1>
                <p class="text-sm text-slate-500 mt-1 font-medium flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-indigo-500"></i> Track productivity, burnout risks, and rankings
                </p>
            </div>
        </div>

        <?php if(count($performanceData) > 0): ?>
        <div class="mb-8">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2"><i data-lucide="award" class="w-4 h-4 text-amber-500"></i> Top Performers Leaderboard</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <?php 
                $top3 = array_slice($performanceData, 0, 3);
                foreach($top3 as $i => $emp): 
                    $rankClass = 'rank-' . ($i + 1);
                    $imgSrc = (!empty($emp['img']) && strpos($emp['img'], 'http') === 0) ? $emp['img'] : 'assets/profiles/'.(!empty($emp['img']) ? $emp['img'] : 'default.png');
                ?>
                <div class="podium-card bg-white border border-slate-200 rounded-2xl p-5 shadow-sm flex items-center gap-4 cursor-pointer" onclick='openDetails(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="rank-badge <?= $rankClass ?>">#<?= $i + 1 ?></div>
                    <img src="<?= $imgSrc ?>" class="w-14 h-14 rounded-full object-cover shadow-sm border-2 border-white" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($emp['name']) ?>&background=random'">
                    <div>
                        <h4 class="font-extrabold text-slate-800 text-lg leading-tight"><?= htmlspecialchars($emp['name']) ?></h4>
                        <p class="text-xs text-slate-500 font-medium"><?= htmlspecialchars($emp['role']) ?></p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-xs font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100"><?= $emp['speed'] ?> / 100</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div class="relative w-full max-w-xs">
                    <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-shadow font-medium" placeholder="Search team member...">
                </div>
            </div>

            <div class="overflow-x-auto custom-scroll">
                <table id="performanceTable" class="w-full text-left whitespace-nowrap">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-extrabold tracking-widest border-b border-slate-200">
                        <tr>
                            <th class="p-5 w-12 text-center">Rank</th>
                            <th class="p-5">Employee Details</th>
                            <th class="p-5">Task Health</th>
                            <th class="p-5 w-56">Project / Task Progress</th>
                            <th class="p-5 text-center">Burnout Risk</th>
                            <th class="p-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if(empty($performanceData)): ?>
                            <tr>
                                <td colspan="6" class="p-12 text-center text-slate-400 font-medium">
                                    <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                                    No team members are currently assigned to you.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($performanceData as $row): 
                                $imgSrc = (!empty($row['img']) && strpos($row['img'], 'http') === 0) ? $row['img'] : 'assets/profiles/'.(!empty($row['img']) ? $row['img'] : 'default.png');
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-5 text-center font-black text-slate-400">#<?= $row['rank'] ?></td>
                                <td class="p-5">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= $imgSrc ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-slate-200" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['name']) ?>&background=random'">
                                        <div>
                                            <span class="emp-name block font-bold text-slate-800"><?= htmlspecialchars($row['name']) ?></span>
                                            <span class="text-[11px] font-bold text-slate-400 mt-0.5 block"><?= htmlspecialchars($row['role']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?= $row['task_health_color'] ?> flex items-center gap-1.5 w-fit">
                                        <div class="w-1.5 h-1.5 rounded-full bg-current"></div> <?= $row['task_health'] ?>
                                    </span>
                                </td>
                                
                                <td class="p-5">
                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-black text-slate-400 w-10 text-right"><?= $row['quality'] ?>%</span>
                                            <div class="w-24 bg-slate-100 rounded-full h-1.5 shadow-inner overflow-hidden">
                                                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= $row['quality'] ?>%"></div>
                                            </div>
                                            <span class="text-[9px] font-black text-slate-400">PRJ</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-black text-slate-400 w-10 text-right"><?= $row['tasks_done'] ?>%</span>
                                            <div class="w-24 bg-slate-100 rounded-full h-1.5 shadow-inner overflow-hidden">
                                                <div class="bg-teal-500 h-1.5 rounded-full" style="width: <?= $row['tasks_done'] ?>%"></div>
                                            </div>
                                            <span class="text-[9px] font-black text-slate-400">TSK</span>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="p-5 text-center">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border <?= $row['risk_color'] ?> flex items-center justify-center gap-1 w-fit mx-auto">
                                        <i data-lucide="<?= $row['risk_icon'] ?>" class="w-3 h-3"></i> <?= $row['risk_text'] ?>
                                    </span>
                                </td>
                                <td class="p-5 text-right">
                                    <button onclick='openDetails(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' class="bg-white border border-slate-200 text-slate-600 hover:text-indigo-700 hover:border-indigo-300 hover:bg-indigo-50 px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2 ml-auto">
                                        <i data-lucide="chart-pie" class="w-4 h-4"></i> Deep Dive
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-content w-full mx-4">
            
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <img id="modalImg" src="" class="w-12 h-12 rounded-full object-cover border border-slate-200 shadow-sm">
                    <div>
                        <h2 id="modalName" class="text-xl font-extrabold text-slate-800 leading-tight"></h2>
                        <p id="modalRole" class="text-xs font-bold text-teal-600 uppercase tracking-widest mt-1"></p>
                    </div>
                </div>
                <button onclick="closeDetailsModal()" class="text-slate-400 hover:text-rose-500 bg-white p-2 rounded-lg border border-slate-200 shadow-sm transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="p-6 overflow-y-auto custom-scroll bg-white flex-grow">
                
                <div class="flex flex-col lg:flex-row gap-5 mb-6">
                    
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 flex items-center gap-5 lg:w-1/3 shadow-sm shrink-0">
                        <div class="relative w-24 h-24 shrink-0 flex items-center justify-center">
                            <svg viewBox="0 0 36 36" class="w-full h-full score-circle drop-shadow-sm">
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                <path id="scoreCircle" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#0d9488" stroke-width="3" stroke-dasharray="0, 100" />
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center mt-1">
                                <span id="dSpeed" class="text-2xl font-black text-slate-800">0</span>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Final Grade</p>
                            <h3 id="dStatusLabel" class="text-xl font-extrabold text-slate-800 leading-snug"></h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 lg:w-2/3">
                        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm flex flex-col justify-center text-center">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Projects<br><span class="opacity-70">(30%)</span></p>
                            <p id="dQual" class="text-lg font-extrabold text-slate-700">0%</p>
                        </div>
                        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm flex flex-col justify-center text-center">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Tasks<br><span class="opacity-70">(25%)</span></p>
                            <p id="dDone" class="text-lg font-extrabold text-slate-700">0%</p>
                        </div>
                        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm flex flex-col justify-center text-center">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Attendance<br><span class="opacity-70">(15%)</span></p>
                            <p id="dAtt" class="text-lg font-extrabold text-slate-700">0%</p>
                        </div>
                        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm flex flex-col justify-center text-center">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">System<br><span class="opacity-70">(10%)</span></p>
                            <p id="dSys" class="text-lg font-extrabold text-slate-700">0%</p>
                        </div>
                        <div class="bg-teal-50 border border-teal-100 p-4 rounded-2xl shadow-sm flex flex-col justify-center text-center">
                            <p class="text-[9px] font-black text-teal-600 uppercase tracking-widest mb-1">Reviewer<br><span class="opacity-70">(20%)</span></p>
                            <p id="dManager" class="text-lg font-extrabold text-teal-800">0%</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                    <div class="md:col-span-2 bg-indigo-50 border border-indigo-100 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                        <i data-lucide="sparkles" class="absolute -right-4 -bottom-4 w-24 h-24 text-indigo-500 opacity-10"></i>
                        <h5 class="text-xs font-extrabold text-indigo-800 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <i data-lucide="brain-circuit" class="w-4 h-4"></i> AI Performance Analysis
                        </h5>
                        <p id="dAiInsight" class="text-sm text-indigo-900 font-medium leading-relaxed relative z-10"></p>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex-1">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Promotion Suggestion</p>
                            <span id="dPromo" class="px-2.5 py-1 text-[11px] font-black uppercase tracking-wider border rounded-md block w-fit"></span>
                        </div>
                        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex-1">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Burnout & Retention</p>
                            <span id="dRisk" class="px-2.5 py-1 text-[11px] font-black uppercase tracking-wider border rounded-md block w-fit flex items-center gap-1.5"><i id="dRiskIcon" data-lucide="activity" class="w-3 h-3"></i> <span id="dRiskText"></span></span>
                        </div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-5">
                    <div class="border border-slate-200 rounded-2xl p-5 shadow-sm">
                        <div class="flex items-center gap-2 text-slate-500 mb-3 pb-2 border-b border-slate-100">
                            <i data-lucide="user-pen" class="w-4 h-4"></i> <h4 class="font-bold text-sm">Self-Review</h4>
                        </div>
                        <p id="dSelfReview" class="text-sm text-slate-600 italic leading-relaxed"></p>
                    </div>
                    <div class="border border-slate-200 rounded-2xl p-5 shadow-sm">
                        <div class="flex items-center gap-2 text-teal-600 mb-3 pb-2 border-b border-slate-100">
                            <i data-lucide="message-square-quote" class="w-4 h-4"></i> <h4 class="font-bold text-sm">Reviewer Feedback</h4>
                        </div>
                        <p id="dComments" class="text-sm text-slate-600 leading-relaxed"></p>
                    </div>
                </div>

            </div>

            <div class="p-5 border-t border-slate-100 bg-slate-50 flex flex-wrap justify-end gap-3 flex-shrink-0">
                <button onclick="closeDetailsModal()" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-100 transition-colors shadow-sm">Close Dashboard</button>
                
                <button onclick="openEvaluateModal()" class="px-5 py-2.5 bg-indigo-600 text-white font-bold text-sm rounded-xl hover:bg-indigo-700 shadow-md transition flex items-center gap-2">
                    <i data-lucide="gavel" class="w-4 h-4"></i> Enter Feedback
                </button>
                
                <?php if(in_array($user_role, ['HR', 'Admin', 'CFO', 'CEO'])): ?>
                <button onclick="openHikeModal()" class="px-5 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 shadow-md transition flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-4 h-4"></i> Process Hike
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay action-modal" id="evaluateModal">
        <div class="modal-content w-full mx-4">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-2"><i data-lucide="gavel" class="text-indigo-600 w-5 h-5"></i> Submit Feedback</h3>
                <button type="button" onclick="closeEvaluateModal()" class="text-slate-400 hover:text-rose-500"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="save_evaluation">
                <input type="hidden" name="eval_emp_id" id="evalEmpId" value="">
                
                <div class="mb-5 bg-indigo-50 border border-indigo-100 p-3 rounded-xl text-sm font-medium text-indigo-800">
                    Evaluating: <strong id="evalEmpName" class="font-black text-indigo-900"></strong>
                </div>
                
                <div class="mb-5">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Performance Rating (0 - 100%) <span class="text-rose-500">*</span></label>
                    <input type="number" name="mgr_score" id="evalMgrScore" min="0" max="100" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition" placeholder="e.g. 85" required>
                    <p class="text-[10px] text-slate-400 mt-1.5 font-semibold">Accounts for 20% of their final automated system grade.</p>
                </div>

                <div class="mb-6">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Reviewer Feedback / Comments <span class="text-rose-500">*</span></label>
                    <textarea name="mgr_comments" id="evalMgrComments" rows="4" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition custom-scroll" placeholder="Provide constructive feedback on their performance..." required></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEvaluateModal()" class="px-5 py-2.5 bg-slate-100 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-sm rounded-xl hover:bg-indigo-700 shadow-md transition flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Save Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if(in_array($user_role, ['HR', 'Admin', 'CFO', 'CEO'])): ?>
    <div class="modal-overlay action-modal" id="hikeModal">
        <div class="modal-content w-full mx-4">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-2"><i data-lucide="trending-up" class="text-emerald-600 w-5 h-5"></i> Approve Salary Hike</h3>
                <button type="button" onclick="closeHikeModal()" class="text-slate-400 hover:text-rose-500"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="approve_hike">
                <input type="hidden" name="hike_emp_id" id="hikeEmpId" value="">
                
                <div class="mb-5 bg-emerald-50 border border-emerald-100 p-3 rounded-xl text-sm font-medium text-emerald-800">
                    Granting appraisal to: <strong id="hikeEmpName" class="font-black text-emerald-900"></strong>
                </div>
                
                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">New Designation (Optional)</label>
                    <input type="text" name="new_designation" id="hikeEmpRole" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:border-emerald-500 outline-none transition" placeholder="e.g. Senior Developer">
                </div>

                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">New Salary (₹) <span class="text-rose-500">*</span></label>
                        <input type="text" name="new_salary" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:border-emerald-500 outline-none transition" placeholder="800,000" required>
                    </div>
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Effective Date <span class="text-rose-500">*</span></label>
                        <input type="date" name="effective_date" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:border-emerald-500 outline-none transition" required>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">HR Remarks <span class="text-rose-500">*</span></label>
                    <textarea name="remarks" rows="2" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-emerald-500 outline-none transition custom-scroll" placeholder="Reason for appraisal..." required></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeHikeModal()" class="px-5 py-2.5 bg-slate-100 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 shadow-md transition flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Finalize Hike
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        lucide.createIcons();

        let currentSelectedEmpId = null;
        let currentSelectedEmpName = "";
        let currentSelectedEmpRole = "";
        let currentMgrScore = 0;
        let currentMgrComments = "";

        function openDetails(data) {
            currentSelectedEmpId = data.user_id;
            currentSelectedEmpName = data.name;
            currentSelectedEmpRole = data.role;
            currentMgrScore = data.mgr_score;
            currentMgrComments = data.comments;

            document.getElementById('modalName').innerText = data.name;
            
            let imgSource = data.img;
            if(!imgSource || imgSource === 'default.png') {
                imgSource = `https://ui-avatars.com/api/?name=${encodeURIComponent(data.name)}&background=0d9488&color=fff`;
            } else if (!imgSource.startsWith('http')) {
                imgSource = "assets/profiles/" + imgSource;
            }
            document.getElementById('modalImg').src = imgSource;
            document.getElementById('modalRole').innerText = data.role;
            
            document.getElementById('dQual').innerText = data.quality + "%";
            document.getElementById('dDone').innerText = data.tasks_done + "%";
            document.getElementById('dAtt').innerText = data.attendance + "%";
            document.getElementById('dSys').innerText = data.sys_score + "%";
            document.getElementById('dManager').innerText = data.mgr_score + "%";
            
            document.getElementById('dSelfReview').innerText = data.self_review ? '"' + data.self_review + '"' : 'Employee has not submitted a self-review yet.';
            document.getElementById('dComments').innerText = data.comments ? '"' + data.comments + '"' : 'No reviewer feedback provided yet.';

            // Smart Features Setup
            document.getElementById('dAiInsight').innerText = data.ai_insight;
            
            const dPromo = document.getElementById('dPromo');
            dPromo.innerText = data.promo_text;
            dPromo.className = `px-2.5 py-1 text-[11px] font-black uppercase tracking-wider border rounded-md block w-fit ${data.promo_color}`;
            
            const dRiskIcon = document.getElementById('dRiskIcon');
            dRiskIcon.setAttribute('data-lucide', data.risk_icon);
            document.getElementById('dRiskText').innerText = data.risk_text;
            document.getElementById('dRisk').className = `px-2.5 py-1 text-[11px] font-black uppercase tracking-wider border rounded-md block w-fit flex items-center gap-1.5 ${data.risk_color}`;
            lucide.createIcons();

            let rawScore = parseFloat(data.speed);
            let formattedScore = Number.isInteger(rawScore) ? rawScore : rawScore.toFixed(1);
            document.getElementById('dSpeed').innerText = formattedScore;
            
            const scoreLabel = document.getElementById('dStatusLabel');
            scoreLabel.innerText = data.status;
            
            let color = '#ef4444'; 
            if(rawScore >= 90) color = '#10b981'; 
            else if (rawScore >= 75) color = '#3b82f6'; 
            else if (rawScore >= 50) color = '#f59e0b'; 

            scoreLabel.style.color = color;
            document.getElementById('scoreCircle').setAttribute('stroke', color);
            
            setTimeout(() => {
                document.getElementById('scoreCircle').setAttribute('stroke-dasharray', `${formattedScore}, 100`);
            }, 50);

            document.getElementById('detailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailsModal() {
            document.getElementById('detailModal').classList.remove('active');
            document.getElementById('scoreCircle').setAttribute('stroke-dasharray', `0, 100`);
            document.body.style.overflow = 'auto';
        }

        function openEvaluateModal() {
            document.getElementById('detailModal').classList.remove('active'); 
            document.getElementById('evalEmpId').value = currentSelectedEmpId;
            document.getElementById('evalEmpName').innerText = currentSelectedEmpName;
            document.getElementById('evalMgrScore').value = currentMgrScore;
            document.getElementById('evalMgrComments').value = currentMgrComments || "";
            document.getElementById('evaluateModal').classList.add('active');
        }

        function closeEvaluateModal() {
            document.getElementById('evaluateModal').classList.remove('active');
            document.getElementById('detailModal').classList.add('active'); 
        }

        function openHikeModal() {
            document.getElementById('detailModal').classList.remove('active');
            const hikeId = document.getElementById('hikeEmpId');
            if(hikeId) {
                hikeId.value = currentSelectedEmpId;
                document.getElementById('hikeEmpName').innerText = currentSelectedEmpName;
                document.getElementById('hikeEmpRole').value = currentSelectedEmpRole;
                document.getElementById('hikeModal').classList.add('active');
            }
        }

        function closeHikeModal() {
            const modal = document.getElementById('hikeModal');
            if(modal) modal.classList.remove('active');
            document.getElementById('detailModal').classList.add('active');
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
<?php ob_end_flush(); ?>