<?php
// team_member.php (Hierarchy Team View)

// 1. OUTPUT BUFFERING & SESSION
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'Manager';

// 2. DB CONNECTION (Bulletproof Dynamic Path)
$dbPath = __DIR__ . '/include/db_connect.php';
if (file_exists($dbPath)) { 
    include_once($dbPath); 
} else { 
    include_once('../include/db_connect.php'); 
}

// 3. FETCH DYNAMIC TEAM DATA BASED ON HIERARCHY
$teamMembers = [];
$active_count = 0;

$base_query = "SELECT 
            ep.user_id, 
            ep.emp_id_code as employee_id, 
            ep.full_name, 
            ep.profile_img as profile_image, 
            ep.designation, 
            u.email, 
            ep.phone,
            ep.joining_date as joined_date,
            u.status as account_status,
            per.manager_rating_pct
          FROM employee_profiles ep
          JOIN users u ON ep.user_id = u.id
          LEFT JOIN employee_performance per ON ep.user_id = per.user_id";

if (in_array($user_role, ['HR', 'Admin', 'CFO', 'CEO'])) {
    $query = "$base_query WHERE ep.user_id != ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_user_id);
} elseif (in_array($user_role, ['Manager', 'Project Manager', 'General Manager'])) {
    $query = "$base_query WHERE ep.reporting_to = ? OR ep.reporting_to IN (SELECT user_id FROM employee_profiles WHERE reporting_to = ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $current_user_id, $current_user_id);
} else {
    $query = "$base_query WHERE ep.reporting_to = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_user_id);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    // Prepare statements for dynamic performance calculation
    $att_stmt = $conn->prepare("SELECT COUNT(*) as total_days, SUM(CASE WHEN status = 'On Time' THEN 1 ELSE 0 END) as present_days, SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days FROM attendance WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $task_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue FROM personal_taskboard WHERE user_id = ? AND status != 'cancelled'");
    $proj_stmt = $conn->prepare("SELECT status FROM project_tasks WHERE assigned_to_user_id = ? LIMIT 10");

    while($row = $result->fetch_assoc()) {
        $emp_id = $row['user_id'];
        $mgr_pct = $row['manager_rating_pct'] ?? null;

        // --- CALCULATE DYNAMIC ENTERPRISE SCORE ---
        // 1. Attendance Calculation (15%)
        $att_stmt->bind_param("i", $emp_id);
        $att_stmt->execute();
        $att_data = $att_stmt->get_result()->fetch_assoc();
        $total_att_days = (!empty($att_data['total_days'])) ? $att_data['total_days'] : 1; 
        $present_days = $att_data['present_days'] ?? 0;
        $late_days = $att_data['late_days'] ?? 0;
        $attendance_pct = min(100, round(($present_days / $total_att_days) * 100));

        // 2. Task Calculation (25%)
        $task_stmt->bind_param("i", $emp_id);
        $task_stmt->execute();
        $task_res = $task_stmt->get_result()->fetch_assoc();
        $task_total = (!empty($task_res['total'])) ? $task_res['total'] : 1;
        $completed_tasks = $task_res['completed'] ?? 0;
        $overdue_tasks = $task_res['overdue'] ?? 0;
        $task_completion_pct = round(($completed_tasks / $task_total) * 100);

        // 3. Project Calculation (30%)
        $proj_stmt->bind_param("i", $emp_id);
        $proj_stmt->execute();
        $proj_res_obj = $proj_stmt->get_result();
        $projects_list = [];
        while($p_row = $proj_res_obj->fetch_assoc()) { $projects_list[] = $p_row; }
        $on_time_projects = 0;
        foreach($projects_list as $p) { if($p['status'] == 'Completed') $on_time_projects++; }
        $proj_total = (count($projects_list) > 0) ? count($projects_list) : 1;
        $project_completion_pct = round(($on_time_projects / $proj_total) * 100);

        // 4. System Reliability Rating (10%)
        $automated_rating = max(40, min(100, 100 - ($late_days * 5) - ($overdue_tasks * 5)));

        // FINAL ENTERPRISE AGGREGATION
        $mgr_score_val = $mgr_pct ?? 0;
        $final_score = round(($project_completion_pct * 0.30) + ($task_completion_pct * 0.25) + ($attendance_pct * 0.15) + ($automated_rating * 0.10) + ($mgr_score_val * 0.20), 1);

        // Map Fallbacks & Formatting
        $row['emp_type'] = 'Permanent'; 
        $row['phone'] = !empty($row['phone']) ? $row['phone'] : 'Not Provided';
        $row['account_status'] = $row['account_status'] ?? 'Active'; 
        
        // Show Pending ONLY if the manager has never submitted an evaluation, otherwise show exact dynamic score
        if ($mgr_pct === null) {
            $row['performance_score'] = 'Pending';
        } else {
            $row['performance_score'] = rtrim(rtrim(number_format($final_score, 1), '0'), '.');
        }
        
        if ($row['account_status'] === 'Active') {
            $active_count++;
        }
        
        $teamMembers[] = $row;
    }
    $stmt->close();
}

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
    <title>My Team | Workack HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary: #1b5a5a;
            --primary-hover: #134040;
            --sidebar-width: 95px;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; overflow-x: hidden;}
        
        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 24px 32px; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
            width: calc(100% - var(--sidebar-width)); 
            box-sizing: border-box; 
        }

        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Custom Toggle Switch */
        .view-toggle { display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 10px; border: 1px solid #e2e8f0;}
        .view-btn { padding: 8px 16px; border-radius: 8px; cursor: pointer; color: #64748b; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: 0.2s;}
        .view-btn.active { background: white; color: var(--primary); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        /* Grid View Cards */
        .grid-view-container { display: none; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
        .grid-view-container.active { display: grid; animation: fadeIn 0.3s ease; }
        
        .emp-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-align: center; transition: all 0.2s ease; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.03);}
        .emp-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.1); border-color: #cbd5e1;}
        .card-img { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 16px; border: 4px solid #f8fafc; object-fit: cover; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);}

        /* Modal Enhancements */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px);} to { opacity: 1; transform: translateY(0);} }
        
        .modal-box { background: white; width: 600px; max-width: 100%; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column; max-height: 90vh; overflow: hidden;}
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px;}
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>

    <div class="main-content" id="mainContent">
        <?php include $headerPath; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8 mt-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 tracking-tight">My Team Members</h1>
                <div class="flex items-center text-sm text-slate-500 gap-2 mt-1.5 font-medium">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>/</span>
                    <span class="text-slate-800 font-bold">Directory</span>
                </div>
            </div>
            
            <div class="view-toggle">
                <div class="view-btn active" onclick="switchView('list')" id="btnList">
                    <i data-lucide="list" class="w-4 h-4"></i> List View
                </div>
                <div class="view-btn" onclick="switchView('grid')" id="btnGrid">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i> Grid View
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex items-center gap-5">
                <div class="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="users" class="w-7 h-7"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Team Size</p>
                    <h3 class="text-3xl font-black text-slate-800 leading-none"><?php echo count($teamMembers); ?></h3>
                </div>
            </div>
            
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex items-center gap-5">
                <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="user-check" class="w-7 h-7"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Active Accounts</p>
                    <h3 class="text-3xl font-black text-slate-800 leading-none"><?php echo $active_count; ?></h3>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex items-center gap-5">
                <div class="w-14 h-14 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="shield-check" class="w-7 h-7"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Your Role Level</p>
                    <h3 class="text-xl font-black text-slate-800 leading-none mt-1"><?php echo htmlspecialchars($user_role); ?></h3>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 mb-8">
            
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="relative flex-1 max-w-md">
                    <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="searchInput" onkeyup="filterTeam()" placeholder="Search team member by name or ID..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-shadow font-medium text-slate-700">
                </div>
                <select id="statusFilter" onchange="filterTeam()" class="w-full md:w-48 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:border-teal-500 transition-shadow">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div id="listView" class="overflow-x-auto custom-scroll border border-slate-100 rounded-xl">
                <table id="teamTable" class="w-full text-left whitespace-nowrap">
                    <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-100">
                        <tr>
                            <th class="p-4">Employee Details</th>
                            <th class="p-4">Designation</th>
                            <th class="p-4">Joined Date</th>
                            <th class="p-4 text-center">Perf. Score</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if(count($teamMembers) > 0): ?>
                            <?php foreach($teamMembers as $member): 
                                $imgSrc = (!empty($member['profile_image']) && strpos($member['profile_image'], 'http') === 0) ? $member['profile_image'] : 'assets/profiles/'.(!empty($member['profile_image']) ? $member['profile_image'] : 'default.png');
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= $imgSrc ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-slate-200" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($member['full_name']); ?>&background=1b5a5a&color=fff'">
                                        <div>
                                            <span class="emp-name block font-bold text-slate-800"><?php echo htmlspecialchars($member['full_name']); ?></span>
                                            <span class="text-[11px] font-bold text-slate-400 mt-0.5 block flex items-center gap-1"><i data-lucide="mail" class="w-3 h-3"></i> <?php echo htmlspecialchars($member['email']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-semibold text-slate-700"><?php echo htmlspecialchars($member['designation']); ?></td>
                                <td class="p-4 font-medium text-slate-500"><?php echo $member['joined_date'] ? date("d M, Y", strtotime($member['joined_date'])) : 'N/A'; ?></td>
                                <td class="p-4 text-center">
                                    <?php if($member['performance_score'] !== 'Pending'): ?>
                                        <span class="font-black text-slate-800"><?php echo $member['performance_score']; ?></span><span class="text-xs font-bold text-slate-400">/100</span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-slate-400 italic">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border <?php echo $member['account_status'] == 'Active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200'; ?>">
                                        <?php echo htmlspecialchars($member['account_status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button class="bg-white border border-slate-200 text-slate-600 hover:text-teal-700 hover:border-teal-300 hover:bg-teal-50 px-3 py-2 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2 ml-auto" onclick='openDetailsModal(<?php echo htmlspecialchars(json_encode($member), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i data-lucide="eye" class="w-4 h-4"></i> Profile
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="p-10 text-center text-slate-400 font-medium">No team members found under your supervision.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="gridView" class="grid-view-container">
                <?php foreach($teamMembers as $member): 
                    $imgSrc = (!empty($member['profile_image']) && strpos($member['profile_image'], 'http') === 0) ? $member['profile_image'] : 'assets/profiles/'.(!empty($member['profile_image']) ? $member['profile_image'] : 'default.png');
                ?>
                <div class="emp-card" data-name="<?php echo strtolower($member['full_name']); ?>" data-status="<?php echo strtolower($member['account_status']); ?>">
                    <span class="absolute top-4 right-4 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider border <?php echo $member['account_status'] == 'Active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200'; ?>">
                        <?php echo htmlspecialchars($member['account_status']); ?>
                    </span>
                    
                    <img src="<?= $imgSrc ?>" class="card-img" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($member['full_name']); ?>&background=1b5a5a&color=fff'">
                    
                    <h4 class="font-extrabold text-slate-800 text-lg leading-tight"><?php echo htmlspecialchars($member['full_name']); ?></h4>
                    <p class="text-xs font-bold text-teal-600 mt-1 mb-4 uppercase tracking-wide"><?php echo htmlspecialchars($member['designation']); ?></p>
                    
                    <div class="flex justify-center gap-2 mb-5">
                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Email">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </a>
                        <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Call">
                            <i data-lucide="phone" class="w-4 h-4"></i>
                        </a>
                    </div>

                    <button class="w-full bg-slate-800 text-white font-bold py-2.5 rounded-xl text-sm hover:bg-slate-900 shadow-md transition-colors" onclick='openDetailsModal(<?php echo htmlspecialchars(json_encode($member), ENT_QUOTES, "UTF-8"); ?>)'>
                        View Full Profile
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </main>

    <div class="modal-overlay" id="memberModal">
        <div class="modal-box">
            
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 flex-shrink-0">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-2"><i data-lucide="contact" class="text-teal-600 w-5 h-5"></i> Member Information</h3>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-rose-500"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="p-6 overflow-y-auto custom-scroll">
                
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-4 mb-6 bg-white border border-slate-200 p-4 rounded-2xl shadow-sm">
                    <img id="mImg" src="" class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-sm shrink-0">
                    <div class="flex-1 min-w-[120px]">
                        <h2 id="mName" class="text-xl font-extrabold text-slate-800 leading-tight break-words"></h2>
                        <p id="mRole" class="text-xs font-bold text-teal-600 uppercase tracking-widest mt-1 break-words"></p>
                    </div>
                    <span id="mStatusBadge" class="px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border shrink-0 whitespace-nowrap mt-2 sm:mt-0 sm:ml-auto"></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Employee ID</label>
                        <p id="mEmpId" class="font-bold text-slate-700 text-sm"></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Email Address</label>
                        <p id="mEmail" class="font-bold text-slate-700 text-sm break-all"></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Phone Number</label>
                        <p id="mPhone" class="font-bold text-slate-700 text-sm"></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Joined Date</label>
                        <p id="mJoined" class="font-bold text-slate-700 text-sm"></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Employment Type</label>
                        <p id="mType" class="font-bold text-slate-700 text-sm"></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 border-l-4 border-l-indigo-500">
                        <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Overall Performance</label>
                        <p id="mScore" class="font-black text-indigo-700 text-lg leading-none"></p>
                    </div>
                </div>

            </div>
            
            <div class="p-5 border-t border-slate-100 bg-slate-50 flex justify-end flex-shrink-0 rounded-b-16">
                <button class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-100 transition-colors shadow-sm" onclick="closeModal()">Close Window</button>
            </div>
            
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Dynamic Sidebar Layout Observer
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

        // View Toggles
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

        // Search & Filter
        function filterTeam() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            let status = document.getElementById('statusFilter').value.toLowerCase();
            
            // Filter List
            let trs = document.getElementById('teamTable').getElementsByTagName('tr');
            for (let i = 1; i < trs.length; i++) {
                if(trs[i].cells.length < 6) continue;
                let name = trs[i].querySelector('.emp-name').textContent.toLowerCase();
                let stat = trs[i].cells[4].textContent.toLowerCase();
                
                let matchesSearch = name.includes(search);
                // CHANGED: Fixed the logic to exactly match 'active' and 'inactive'
                let matchesStatus = status === "" || stat.trim() === status;
                
                trs[i].style.display = (matchesSearch && matchesStatus) ? "" : "none";
            }

            // Filter Grid
            let cards = document.getElementsByClassName('emp-card');
            for(let i=0; i<cards.length; i++) {
                let name = cards[i].getAttribute('data-name');
                let stat = cards[i].getAttribute('data-status');
                
                let matchesSearch = name.includes(search);
                // CHANGED: Match strictly in grid view too
                let matchesStatus = status === "" || stat.trim() === status;
                
                cards[i].style.display = (matchesSearch && matchesStatus) ? "block" : "none";
            }
        }

        // Modal Logic
        function openDetailsModal(data) {
            document.getElementById('mName').innerText = data.full_name;
            document.getElementById('mRole').innerText = data.designation;
            document.getElementById('mEmpId').innerText = data.employee_id || 'N/A';
            document.getElementById('mEmail').innerText = data.email || 'N/A';
            document.getElementById('mPhone').innerText = data.phone || 'N/A';
            document.getElementById('mType').innerText = data.emp_type || 'Permanent';
            
            // Format Date
            if(data.joined_date) {
                const d = new Date(data.joined_date);
                document.getElementById('mJoined').innerText = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            } else {
                document.getElementById('mJoined').innerText = 'N/A';
            }

            // Status Badge
            const badge = document.getElementById('mStatusBadge');
            badge.innerText = data.account_status;
            if(data.account_status === 'Active') {
                // CHANGED: Added sm:ml-auto, mt-2, sm:mt-0 to keep alignment responsive 
                badge.className = "px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border bg-emerald-50 text-emerald-600 border-emerald-200 shrink-0 whitespace-nowrap mt-2 sm:mt-0 sm:ml-auto";
            } else {
                badge.className = "px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border bg-rose-50 text-rose-600 border-rose-200 shrink-0 whitespace-nowrap mt-2 sm:mt-0 sm:ml-auto";
            }

            // Performance
            document.getElementById('mScore').innerText = data.performance_score !== 'Pending' ? data.performance_score + '/100' : 'Pending';

            // Image
            let imgSource = data.profile_image;
            if(!imgSource || imgSource === 'default.png') {
                imgSource = `https://ui-avatars.com/api/?name=${encodeURIComponent(data.full_name)}&background=1b5a5a&color=fff`;
            } else if (!imgSource.startsWith('http')) {
                imgSource = "assets/profiles/" + imgSource;
            }
            document.getElementById('mImg').src = imgSource;

            document.body.style.overflow = 'hidden';
            document.getElementById('memberModal').classList.add('active');
        }

        function closeModal() {
            document.body.style.overflow = 'auto';
            document.getElementById('memberModal').classList.remove('active');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) closeModal();
        }
    </script>
</body>
</html>