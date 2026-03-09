<?php
// ceo_dashboard.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

// Security check
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

$current_user_id = $_SESSION['user_id'];


// SMART PATH RESOLVER
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

// =========================================================================
// NEW: FETCH LOGGED-IN USER'S REAL NAME AND IMAGE
// =========================================================================
$user_info_query = mysqli_query($conn, "SELECT u.name, ep.profile_img FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = '$current_user_id'");
$user_info = mysqli_fetch_assoc($user_info_query);
$user_real_name = $user_info['name'] ?? $_SESSION['username'] ?? 'CEO';
$user_profile_img = $user_info['profile_img'] ?? '';
// =========================================================================


// =========================================================================
// 1. FETCH ALL DISTINCT DEPARTMENTS FOR DROPDOWN FILTERS
// =========================================================================
$dept_list_query = mysqli_query($conn, "SELECT DISTINCT department FROM employee_profiles WHERE department IS NOT NULL AND department != ''");
$all_depts = [];
while($row = mysqli_fetch_assoc($dept_list_query)) { 
    $all_depts[] = $row['department']; 
}

// =========================================================================
// 2. FETCH KPI STATISTICS
// =========================================================================
$today = date('Y-m-d');

// Employees
$emp_query = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(IF(status='Active', 1, 0)) as active FROM employee_profiles");
$emp_data = mysqli_fetch_assoc($emp_query);
$total_employees = $emp_data['total'] ?? 0;
$active_employees = $emp_data['active'] ?? 0;

// Projects
$proj_query = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(IF(status='Active', 1, 0)) as active FROM projects");
$proj_data = mysqli_fetch_assoc($proj_query);
$total_projects = $proj_data['total'] ?? 0;
$active_projects = $proj_data['active'] ?? 0;

// Clients
$client_query = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(IF(status='Active', 1, 0)) as active FROM clients");
$client_data = mysqli_fetch_assoc($client_query);
$total_clients = $client_data['total'] ?? 0;
$active_clients = $client_data['active'] ?? 0;

// Tasks
$task_query = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(IF(status='Completed', 1, 0)) as completed FROM project_tasks");
$task_data = mysqli_fetch_assoc($task_query);
$total_tasks = $task_data['total'] ?? 0;
$completed_tasks = $task_data['completed'] ?? 0;

// Financials
$earnings_query = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM invoices WHERE status IN ('Approved', 'Paid')");
$total_earnings = mysqli_fetch_assoc($earnings_query)['total'] ?? 0;

$profit_query = mysqli_query($conn, "SELECT SUM(credit_amount) - SUM(debit_amount) as profit FROM general_ledger WHERE YEARWEEK(entry_date, 1) = YEARWEEK(CURDATE(), 1)");
$profit_week = mysqli_fetch_assoc($profit_query)['profit'] ?? 0;

// Recruitment
$job_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM candidates");
$total_applicants = mysqli_fetch_assoc($job_query)['total'] ?? 0;

$hire_query = mysqli_query($conn, "SELECT COUNT(*) as hired FROM employee_profiles WHERE MONTH(joining_date) = MONTH(CURDATE()) AND YEAR(joining_date) = YEAR(CURDATE())");
$new_hires = mysqli_fetch_assoc($hire_query)['hired'] ?? 0;

// =========================================================================
// 3. FETCH DYNAMIC WIDGET DATA (LINKED TO URL FILTERS)
// =========================================================================

// A. Employees By Department Chart
$dept_status = isset($_GET['dept_status']) ? mysqli_real_escape_string($conn, $_GET['dept_status']) : 'Active';
$dept_query = mysqli_query($conn, "SELECT department, COUNT(id) as emp_count FROM employee_profiles WHERE status = '$dept_status' AND department IS NOT NULL AND department != '' GROUP BY department ORDER BY emp_count DESC LIMIT 6");
$departments = []; $dept_counts = [];
while ($row = mysqli_fetch_assoc($dept_query)) {
    $departments[] = $row['department'];
    $dept_counts[] = $row['emp_count'];
}

// B. Employee Status & Types
$emp_dept_filter = isset($_GET['emp_dept']) ? mysqli_real_escape_string($conn, $_GET['emp_dept']) : '';
$type_where = $emp_dept_filter ? "WHERE department = '$emp_dept_filter'" : "";
$type_query = mysqli_query($conn, "SELECT employment_type, COUNT(*) as cnt FROM employee_onboarding $type_where GROUP BY employment_type");
$emp_types = ['Permanent' => 0, 'Contract' => 0, 'Intern' => 0];
$filtered_total_emps = 0;
while($t = mysqli_fetch_assoc($type_query)) { 
    $emp_types[$t['employment_type']] = $t['cnt']; 
    $filtered_total_emps += $t['cnt'];
}
if(!$emp_dept_filter) { $filtered_total_emps = $total_employees; } // Fallback to all if no filter

// C. Attendance Overview
$att_filter = isset($_GET['att_filter']) ? $_GET['att_filter'] : 'today';
$target_date = ($att_filter == 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : $today;
$att_main_query = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as present FROM attendance WHERE date = '$target_date' AND status != 'Absent'");
$present_today = mysqli_fetch_assoc($att_main_query)['present'] ?? 0;

// D. Clock In/Out List
$clock_dept = isset($_GET['clock_dept']) ? mysqli_real_escape_string($conn, $_GET['clock_dept']) : '';
$clock_sql = "SELECT a.*, ep.full_name, ep.designation, ep.profile_img FROM attendance a JOIN employee_profiles ep ON a.user_id = ep.user_id WHERE a.date = '$today'";
if($clock_dept) { $clock_sql .= " AND ep.department = '$clock_dept'"; }
$clock_sql .= " ORDER BY a.punch_in DESC LIMIT 4";
$clock_query = mysqli_query($conn, $clock_sql);

// E. Sales & Expenses Chart
$sales_year = isset($_GET['sales_year']) ? (int)$_GET['sales_year'] : date('Y');
$sales_data = array_fill(1, 12, ['inc' => 0, 'exp' => 0]);
$chart_query = mysqli_query($conn, "SELECT MONTH(entry_date) as m, SUM(credit_amount) as inc, SUM(debit_amount) as exp FROM general_ledger WHERE YEAR(entry_date) = $sales_year GROUP BY MONTH(entry_date)");
while($c = mysqli_fetch_assoc($chart_query)) {
    $sales_data[(int)$c['m']] = ['inc' => (float)$c['inc'], 'exp' => (float)$c['exp']];
}

// F. Recent Invoices
$inv_status = isset($_GET['inv_status']) ? mysqli_real_escape_string($conn, $_GET['inv_status']) : 'all';
$inv_cond = ($inv_status != 'all') ? "WHERE i.status = '$inv_status'" : "";
$inv_query = mysqli_query($conn, "SELECT i.invoice_no, i.grand_total, i.status, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id $inv_cond ORDER BY i.created_at DESC LIMIT 4");

// G. Projects Overview
$proj_status = isset($_GET['proj_status']) ? mysqli_real_escape_string($conn, $_GET['proj_status']) : 'Active';
$recent_proj_query = mysqli_query($conn, "SELECT project_name, priority, progress, total_tasks, completed_tasks FROM projects WHERE status = '$proj_status' ORDER BY id DESC LIMIT 5");

// H. Task Statistics
$task_pri = isset($_GET['task_pri']) ? mysqli_real_escape_string($conn, $_GET['task_pri']) : '';
$task_pri_where = $task_pri ? "WHERE priority = '$task_pri'" : "";
$ts_query = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM project_tasks $task_pri_where GROUP BY status");
$ts_counts = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0];
while($t = mysqli_fetch_assoc($ts_query)) { $ts_counts[$t['status']] = $t['cnt']; }
$filtered_task_total = array_sum($ts_counts);

// I. Todo List
$todo_filter = isset($_GET['todo_filter']) ? mysqli_real_escape_string($conn, $_GET['todo_filter']) : 'all';
$todo_cond = "user_id = $current_user_id";
if ($todo_filter == 'today') { $todo_cond .= " AND due_date = '$today'"; }
elseif ($todo_filter == 'pending') { $todo_cond .= " AND status != 'completed'"; }
$todo_query = mysqli_query($conn, "SELECT * FROM personal_taskboard WHERE $todo_cond ORDER BY id DESC LIMIT 6");

// J. Static/Basic Fetching (No filters needed)
$perf_query = mysqli_query($conn, "SELECT ep.full_name, ep.designation, ep.profile_img, p.total_score FROM employee_performance p JOIN employee_profiles ep ON p.user_id = ep.user_id ORDER BY p.total_score DESC LIMIT 1");
$top_performer = mysqli_fetch_assoc($perf_query);
$act_query = mysqli_query($conn, "SELECT title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 5");
$sched_query = mysqli_query($conn, "SELECT title, meet_date, meet_time FROM calendar_meetings WHERE meet_date >= CURDATE() ORDER BY meet_date ASC LIMIT 2");
$cand_query = mysqli_query($conn, "SELECT name, applied_role, email FROM candidates ORDER BY id DESC LIMIT 4");
$openings_query = @mysqli_query($conn, "SELECT title, loc FROM jobs ORDER BY id DESC LIMIT 4");

// Avatar Generator
function getAvatar($img, $name) {
    if(empty($img) || $img == 'default_user.png') return "https://ui-avatars.com/api/?name=".urlencode($name ?? 'U')."&background=0d9488&color=fff";
    return (strpos($img, 'http') === 0) ? $img : '../assets/profiles/' . $img;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Dashboard | Enterprise HRMS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        @media (max-width: 1024px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 16px; padding-top: 80px; }
        }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; flex-direction: column; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .card-title { font-size: 16px; font-weight: 800; color: #1e293b; }
        .card-body { padding: 24px; flex: 1; }
        
        .custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        .stacked-bar { display: flex; height: 12px; border-radius: 999px; overflow: hidden; width: 100%; margin: 15px 0 25px 0;}
        .widget-select { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 8px; outline: none; cursor: pointer; }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>
    <?php include $headerPath; ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Admin Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1 font-medium flex items-center gap-2">
                    <i class="fa-solid fa-home text-slate-400"></i> <i class="fa-solid fa-chevron-right text-[10px] text-slate-300"></i> Dashboard <i class="fa-solid fa-chevron-right text-[10px] text-slate-300"></i> <span class="text-slate-700">Admin Dashboard</span>
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <button class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>
                <div class="bg-white border border-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-slate-400"></i> <?php echo date('M Y'); ?>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 mb-8 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <img src="<?= getAvatar($user_profile_img, $user_real_name) ?>" class="w-14 h-14 rounded-full shadow-sm object-cover" alt="CEO">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">Welcome Back, <?= htmlspecialchars(explode(' ', $user_real_name)[0]) ?> <i class="fa-solid fa-pen text-slate-300 text-xs cursor-pointer hover:text-slate-500"></i></h2>
                <p class="text-sm text-slate-500 mt-0.5">Your enterprise systems are running smoothly.</p>
            </div>
            
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='admin_attendance.php'">
                <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-calendar-check"></i></div>
                <p class="text-slate-500 text-xs font-bold">Attendance Overview</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3"><?= $present_today ?>/<?= $active_employees ?></h3>
                <span class="text-xs font-bold text-slate-400">View Details</span>
            </div>
            
            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='projects.php'">
                <div class="w-12 h-12 rounded-xl bg-[#16636B]/10 text-[#16636B] flex items-center justify-center text-xl mb-4"><i class="fa-regular fa-folder-open"></i></div>
                <p class="text-slate-500 text-xs font-bold">Total No of Projects</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3"><?= $active_projects ?>/<?= $total_projects ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>
            
            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='clients.php'">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-users"></i></div>
                <p class="text-slate-500 text-xs font-bold">Total No of Clients</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3"><?= $active_clients ?>/<?= $total_clients ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>

            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='tasks.php'">
                <div class="w-12 h-12 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-list-check"></i></div>
                <p class="text-slate-500 text-xs font-bold">Total No of Tasks</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3"><?= $completed_tasks ?>/<?= $total_tasks ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>

            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='finance.php'">
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-sack-dollar"></i></div>
                <p class="text-slate-500 text-xs font-bold">Earnings (Paid Invoices)</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3">₹<?= number_format($total_earnings) ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>

            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='finance.php'">
                <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-chart-pie"></i></div>
                <p class="text-slate-500 text-xs font-bold">Profit This Week</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3">₹<?= number_format($profit_week) ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>

            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='recruitment.php'">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-user-group"></i></div>
                <p class="text-slate-500 text-xs font-bold">Job Applicants</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3"><?= $total_applicants ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>

            <div class="card p-5 hover:shadow-md transition cursor-pointer" onclick="window.location.href='employee_management.php'">
                <div class="w-12 h-12 rounded-xl bg-slate-800 text-white flex items-center justify-center text-xl mb-4"><i class="fa-solid fa-user-tie"></i></div>
                <p class="text-slate-500 text-xs font-bold">New Hires (This Mth)</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 mb-3"><?= $new_hires ?></h3>
                <span class="text-xs font-bold text-slate-400">View All</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h3 class="card-title">Employees By Department</h3>
                    <select class="widget-select" onchange="updateUrlFilter('dept_status', this.value)">
                        <option value="Active" <?= ($dept_status=='Active')?'selected':'' ?>>Active Employees</option>
                        <option value="Inactive" <?= ($dept_status=='Inactive')?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="card-body">
                    <div id="departmentChart" style="min-height: 250px;"></div>
                </div>
            </div>

            <div class="card lg:col-span-1">
                <div class="card-header">
                    <h3 class="card-title">Employee Status</h3>
                    <select class="widget-select" onchange="updateUrlFilter('emp_dept', this.value)">
                        <option value="">All Departments</option>
                        <?php foreach($all_depts as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= ($emp_dept_filter==$d)?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="card-body">
                    <div class="flex justify-between items-end mb-1">
                        <span class="text-sm font-bold text-slate-500">Total Employee</span>
                        <span class="text-2xl font-black text-slate-800"><?= $filtered_total_emps ?></span>
                    </div>
                    
                    <?php 
                        $pct_perm = $filtered_total_emps > 0 ? round(($emp_types['Permanent'] / $filtered_total_emps) * 100) : 0;
                        $pct_cont = $filtered_total_emps > 0 ? round(($emp_types['Contract'] / $filtered_total_emps) * 100) : 0;
                        $pct_int = $filtered_total_emps > 0 ? round(($emp_types['Intern'] / $filtered_total_emps) * 100) : 0;
                        $pct_wfh = 100 - ($pct_perm + $pct_cont + $pct_int); 
                    ?>
                    <div class="stacked-bar">
                        <div class="bg-[#eab308] h-full" style="width: <?= max(5, $pct_perm) ?>%"></div>
                        <div class="bg-[#0f766e] h-full" style="width: <?= max(5, $pct_cont) ?>%"></div>
                        <div class="bg-[#ef4444] h-full" style="width: <?= max(5, $pct_int) ?>%"></div>
                        <div class="bg-[#ec4899] h-full" style="width: <?= max(5, $pct_wfh) ?>%"></div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-8">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#eab308]"></span> Permanent (<?= $pct_perm ?>%)</p>
                            <h4 class="text-3xl font-black text-slate-800 mt-1"><?= $emp_types['Permanent'] ?></h4>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#0f766e]"></span> Contract (<?= $pct_cont ?>%)</p>
                            <h4 class="text-3xl font-black text-slate-800 mt-1"><?= $emp_types['Contract'] ?></h4>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#ef4444]"></span> Intern (<?= $pct_int ?>%)</p>
                            <h4 class="text-3xl font-black text-slate-800 mt-1"><?= $emp_types['Intern'] ?></h4>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#ec4899]"></span> WFH (<?= $pct_wfh ?>%)</p>
                            <h4 class="text-3xl font-black text-slate-800 mt-1">-</h4>
                        </div>
                    </div>

                    <h4 class="text-sm font-bold text-slate-800 mb-3">Top Performer</h4>
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex justify-between items-center mb-6">
                        <?php if($top_performer): ?>
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <img src="<?= getAvatar($top_performer['profile_img'], $top_performer['full_name']) ?>" class="w-10 h-10 rounded-full border-2 border-white object-cover">
                                    <i class="fa-solid fa-medal absolute -bottom-1 -left-1 text-orange-500 text-lg bg-white rounded-full"></i>
                                </div>
                                <div>
                                    <h5 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($top_performer['full_name']) ?></h5>
                                    <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($top_performer['designation']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Score</p>
                                <p class="text-lg font-black text-orange-500"><?= (float)$top_performer['total_score'] ?></p>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-slate-500">No performance data generated yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card lg:col-span-1">
                <div class="card-header">
                    <h3 class="card-title">Attendance Overview</h3>
                    <select class="widget-select" onchange="updateUrlFilter('att_filter', this.value)">
                        <option value="today" <?= ($att_filter=='today')?'selected':'' ?>>Today</option>
                        <option value="yesterday" <?= ($att_filter=='yesterday')?'selected':'' ?>>Yesterday</option>
                    </select>
                </div>
                <div class="card-body flex flex-col">
                    <div class="relative h-44 w-full flex justify-center mt-2 mb-6">
                        <canvas id="attendanceGauge"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-end pb-4 pointer-events-none">
                            <p class="text-xs text-slate-500 font-bold mb-1">Present <?= ucfirst($att_filter) ?></p>
                            <h2 class="text-4xl font-black text-slate-800"><?= $present_today ?></h2>
                        </div>
                    </div>

                    <?php 
                        $pt_pct = $active_employees > 0 ? round(($present_today / $active_employees) * 100) : 0;
                        $abs_pct = 100 - $pt_pct;
                    ?>
                    <h4 class="text-sm font-bold text-slate-800 mb-3">Status Matrix</h4>
                    <div class="space-y-3 mb-auto">
                        <div class="flex justify-between items-center text-sm font-semibold">
                            <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span> Present</span>
                            <span class="text-slate-800"><?= $pt_pct ?>%</span>
                        </div>
                        <div class="flex justify-between items-center text-sm font-semibold">
                            <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span> Absent / Leave</span>
                            <span class="text-slate-800"><?= $abs_pct ?>%</span>
                        </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex justify-between items-center mt-6">
                        <span class="text-xs font-bold text-slate-500">Total Absentees</span>
                        <div class="flex items-center gap-3">
                            <span class="font-bold text-rose-500"><?= max(0, $active_employees - $present_today) ?> Staff</span>
                            <a href="admin_attendance.php" class="text-xs font-bold text-orange-500 hover:underline">View Details</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h3 class="card-title">Clock-In/Out (Today)</h3>
                    <div class="flex gap-2">
                        <select class="widget-select" onchange="updateUrlFilter('clock_dept', this.value)">
                            <option value="">All Departments</option>
                            <?php foreach($all_depts as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= ($clock_dept==$d)?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0 flex flex-col h-full">
                    <div class="flex-1 overflow-y-auto custom-scroll p-4 space-y-4 h-[380px] grid grid-cols-1 sm:grid-cols-2 gap-4">
                        
                        <?php if(mysqli_num_rows($clock_query) > 0): ?>
                            <?php 
                            while($att = mysqli_fetch_assoc($clock_query)): 
                                $isLate = ($att['status'] == 'Late');
                                $timeStr = date('h:i A', strtotime($att['punch_in']));
                                $color = $isLate ? 'rose' : 'emerald';
                            ?>
                                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden cursor-pointer h-min">
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#16636B]"></div>
                                    <div class="flex items-center justify-between mb-4 pl-2">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= getAvatar($att['profile_img'], $att['full_name']) ?>" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($att['full_name']) ?></h4>
                                                <p class="text-[11px] text-slate-500"><?= htmlspecialchars($att['designation']) ?></p>
                                            </div>
                                        </div>
                                        <div class="bg-<?= $color ?>-50 text-<?= $color ?>-600 border border-<?= $color ?>-200 px-2 py-1 rounded-md text-xs font-bold flex items-center gap-1">
                                            <i class="fa-regular fa-clock"></i> <?= $timeStr ?>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 pl-2 border-t border-slate-100 pt-3">
                                        <div><p class="text-[10px] font-bold text-slate-400 flex items-center gap-1 mb-0.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In</p><p class="text-xs font-bold text-slate-700"><?= $timeStr ?></p></div>
                                        <div><p class="text-[10px] font-bold text-slate-400 flex items-center gap-1 mb-0.5"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Out</p><p class="text-xs font-bold text-slate-700"><?= $att['punch_out'] ? date('h:i A', strtotime($att['punch_out'])) : '--:--' ?></p></div>
                                        <div><p class="text-[10px] font-bold text-slate-400 flex items-center gap-1 mb-0.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Prod.</p><p class="text-xs font-bold text-slate-700"><?= $att['production_hours'] ?> Hrs</p></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 text-center py-10 col-span-2">No punches recorded for the selected criteria.</p>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 border-t border-slate-100 mt-auto">
                        <button onclick="window.location.href='admin_attendance.php'" class="w-full py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-lg border border-slate-200 transition">View All Attendance</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card lg:col-span-2 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-bold text-slate-800 text-lg">Sales & Expenses Overview</h3>
                    <div class="flex items-center gap-3">
                        <select class="widget-select" onchange="updateUrlFilter('sales_year', this.value)">
                            <option value="<?= date('Y') ?>" <?= ($sales_year==date('Y'))?'selected':'' ?>>This Year (<?= date('Y') ?>)</option>
                            <option value="<?= date('Y')-1 ?>" <?= ($sales_year==date('Y')-1)?'selected':'' ?>>Last Year (<?= date('Y')-1 ?>)</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-4 mb-4 text-xs font-bold text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-[#ea580c]"></span> Income</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-200"></span> Expenses</span>
                    <span class="ml-auto font-medium">Auto-Synced with Ledger</span>
                </div>
                <div class="relative w-full h-64">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="card flex flex-col lg:col-span-1">
                <div class="card-header">
                    <h3 class="card-title">Recent Invoices</h3>
                    <select class="widget-select" onchange="updateUrlFilter('inv_status', this.value)">
                        <option value="all" <?= ($inv_status=='all')?'selected':'' ?>>All Invoices</option>
                        <option value="Paid" <?= ($inv_status=='Paid')?'selected':'' ?>>Paid</option>
                        <option value="Pending Approval" <?= ($inv_status=='Pending Approval')?'selected':'' ?>>Pending</option>
                    </select>
                </div>
                <div class="card-body p-0 flex flex-col h-full">
                    <div class="flex-1 overflow-y-auto custom-scroll p-4 space-y-4 h-[250px]">
                        <?php if(mysqli_num_rows($inv_query) > 0): ?>
                            <?php while($inv = mysqli_fetch_assoc($inv_query)): 
                                $isPaid = ($inv['status'] === 'Paid' || $inv['status'] === 'Approved');
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center font-bold text-lg"><i class="fa-solid fa-file-invoice"></i></div>
                                    <div><h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($inv['client_name']) ?></h4><p class="text-[11px] text-slate-500"><?= $inv['invoice_no'] ?></p></div>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-0.5">Amount</p>
                                    <p class="text-sm font-bold text-slate-800 mb-1">₹<?= number_format($inv['grand_total']) ?></p>
                                    <?php if($isPaid): ?>
                                        <span class="text-[9px] font-black text-emerald-600 bg-emerald-50 border border-emerald-100 px-1.5 py-0.5 rounded flex items-center gap-1 justify-end"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Paid</span>
                                    <?php else: ?>
                                        <span class="text-[9px] font-black text-rose-500 bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded flex items-center gap-1 justify-end"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Unpaid</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 text-center mt-5">No invoices found.</p>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 border-t border-slate-100">
                        <button onclick="window.location.href='finance.php'" class="w-full py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-lg border border-slate-200 transition">View All Invoices</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card lg:col-span-2 overflow-hidden flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Projects Overview</h3>
                    <select class="widget-select" onchange="updateUrlFilter('proj_status', this.value)">
                        <option value="Active" <?= ($proj_status=='Active')?'selected':'' ?>>Active Projects</option>
                        <option value="Completed" <?= ($proj_status=='Completed')?'selected':'' ?>>Completed</option>
                        <option value="Hold" <?= ($proj_status=='Hold')?'selected':'' ?>>On Hold</option>
                    </select>
                </div>
                <div class="overflow-x-auto custom-scroll flex-1 p-0">
                    <table class="w-full text-left whitespace-nowrap text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-5 py-3 text-xs font-bold text-slate-500">Name</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-500">Priority</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-500">Task Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(mysqli_num_rows($recent_proj_query) > 0): ?>
                                <?php while($proj = mysqli_fetch_assoc($recent_proj_query)): 
                                    $ptot = (int)$proj['total_tasks'];
                                    $pcomp = (int)$proj['completed_tasks'];
                                    $pct = $ptot > 0 ? round(($pcomp / $ptot) * 100) : 0;
                                    
                                    $p_class = "bg-emerald-50 text-emerald-600";
                                    $p_dot = "bg-emerald-500";
                                    if($proj['priority'] == 'High') { $p_class = "bg-rose-50 text-rose-600"; $p_dot = "bg-rose-500"; }
                                    if($proj['priority'] == 'Medium') { $p_class = "bg-pink-50 text-pink-600"; $p_dot = "bg-pink-500"; }
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-4 font-bold text-slate-800"><?= htmlspecialchars($proj['project_name']) ?></td>
                                    <td class="px-5 py-4"><span class="<?= $p_class ?> px-2.5 py-1 rounded text-[10px] font-black uppercase flex items-center w-max gap-1"><span class="w-1.5 h-1.5 rounded-full <?= $p_dot ?>"></span> <?= $proj['priority'] ?></span></td>
                                    <td class="px-5 py-4 text-slate-500 font-medium text-xs">
                                        <?= $pcomp ?>/<?= $ptot ?> Tasks 
                                        <div class="w-32 bg-slate-200 h-1.5 rounded-full mt-1.5"><div class="bg-orange-500 h-1.5 rounded-full" style="width: <?= $pct ?>%;"></div></div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-5 text-slate-500 text-sm">No projects found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card p-6 flex flex-col lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-800 text-lg">Tasks Statistics</h3>
                    <select class="widget-select" onchange="updateUrlFilter('task_pri', this.value)">
                        <option value="">All Priorities</option>
                        <option value="High" <?= ($task_pri=='High')?'selected':'' ?>>High Priority</option>
                        <option value="Medium" <?= ($task_pri=='Medium')?'selected':'' ?>>Medium Priority</option>
                        <option value="Low" <?= ($task_pri=='Low')?'selected':'' ?>>Low Priority</option>
                    </select>
                </div>
                
                <div class="relative h-44 w-full flex justify-center mt-2">
                    <canvas id="taskDonut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-end pb-6 pointer-events-none">
                        <p class="text-xs text-slate-500 font-bold mb-1">Total</p>
                        <h2 class="text-3xl font-black text-slate-800"><?= $filtered_task_total ?></h2>
                    </div>
                </div>

                <?php 
                    $t_pend = $ts_counts['Pending'] ?? 0;
                    $t_prog = $ts_counts['In Progress'] ?? 0;
                    $t_comp = $ts_counts['Completed'] ?? 0;
                ?>
                <div class="flex justify-between mt-auto px-2">
                    <div class="text-center"><span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6] inline-block mb-1"></span><p class="text-[10px] font-bold text-slate-500">Pending</p><p class="font-black text-slate-800"><?= $t_pend ?></p></div>
                    <div class="text-center"><span class="w-2.5 h-2.5 rounded-full bg-[#eab308] inline-block mb-1"></span><p class="text-[10px] font-bold text-slate-500">In Progress</p><p class="font-black text-slate-800"><?= $t_prog ?></p></div>
                    <div class="text-center"><span class="w-2.5 h-2.5 rounded-full bg-[#10b981] inline-block mb-1"></span><p class="text-[10px] font-bold text-slate-500">Completed</p><p class="font-black text-slate-800"><?= $t_comp ?></p></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Jobs Applicants</h3>
                    <button onclick="window.location.href='recruitment.php'" class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition hover:bg-slate-100">View All</button>
                </div>
                <div class="card-body p-0 flex flex-col">
                    <div class="p-4 border-b border-slate-100">
                        <div class="bg-slate-100 p-1 flex rounded-lg">
                            <button id="tabOpenings" onclick="switchJobTab('openings')" class="flex-1 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 transition">Openings</button>
                            <button id="tabApplicants" onclick="switchJobTab('applicants')" class="flex-1 py-2 text-sm font-bold text-white bg-[#ea580c] rounded shadow-sm">Applicants</button>
                        </div>
                    </div>
                    
                    <div id="viewApplicants" class="flex-1 overflow-y-auto custom-scroll p-4 space-y-5 h-[320px]">
                        <?php 
                        if(mysqli_num_rows($cand_query) > 0): 
                            while($cand = mysqli_fetch_assoc($cand_query)):
                        ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($cand['name']) ?>&background=random" class="w-10 h-10 rounded-full object-cover">
                                <div><h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($cand['name']) ?></h4><p class="text-[11px] font-medium text-slate-500 truncate w-32"><?= htmlspecialchars($cand['email']) ?></p></div>
                            </div>
                            <span class="bg-[#0f766e] text-white px-2 py-1 rounded text-[10px] font-bold"><?= htmlspecialchars($cand['applied_role']) ?></span>
                        </div>
                        <?php endwhile; else: ?>
                            <p class="text-center text-sm text-slate-500">No applicants yet.</p>
                        <?php endif; ?>
                    </div>

                    <div id="viewOpenings" class="flex-1 overflow-y-auto custom-scroll p-4 space-y-5 h-[320px] hidden">
                        <?php 
                        if($openings_query && mysqli_num_rows($openings_query) > 0): 
                            while($opn = mysqli_fetch_assoc($openings_query)):
                        ?>
                        <div class="flex items-center justify-between p-2 border border-slate-100 rounded-lg bg-slate-50">
                            <div>
                                <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($opn['title']) ?></h4>
                                <p class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($opn['loc']) ?></p>
                            </div>
                            <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-1 rounded text-[10px] font-bold">Open</span>
                        </div>
                        <?php endwhile; else: ?>
                            <p class="text-center text-sm text-slate-500 mt-5">No open positions currently available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Employees Directory</h3>
                    <button onclick="window.location.href='employee_management.php'" class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition hover:bg-slate-100">View All</button>
                </div>
                <div class="card-body p-0 flex flex-col">
                    <div class="flex-1 overflow-y-auto custom-scroll p-4 space-y-5 h-[395px]">
                        <?php 
                        $dir_query = mysqli_query($conn, "SELECT full_name, designation, department, profile_img FROM employee_profiles WHERE status='Active' ORDER BY id DESC LIMIT 5");
                        while($dir = mysqli_fetch_assoc($dir_query)):
                        ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="<?= getAvatar($dir['profile_img'], $dir['full_name']) ?>" class="w-10 h-10 rounded-full object-cover">
                                <div><h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($dir['full_name']) ?></h4><p class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($dir['department']) ?></p></div>
                            </div>
                            <span class="bg-blue-50 text-blue-600 px-2 py-1 rounded text-[10px] font-bold border border-blue-100"><?= htmlspecialchars($dir['designation']) ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="card flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Todo</h3>
                    <div class="flex gap-2 items-center">
                        <select class="widget-select" onchange="updateUrlFilter('todo_filter', this.value)">
                            <option value="all" <?= ($todo_filter=='all')?'selected':'' ?>>All</option>
                            <option value="today" <?= ($todo_filter=='today')?'selected':'' ?>>Today</option>
                            <option value="pending" <?= ($todo_filter=='pending')?'selected':'' ?>>Pending</option>
                        </select>
                        <button onclick="window.location.href='tasks.php'" class="bg-[#ea580c] text-white w-8 h-8 rounded-lg flex items-center justify-center shadow hover:bg-[#c2410c]"><i class="fa-solid fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0 flex flex-col">
                    <div class="flex-1 overflow-y-auto custom-scroll p-4 space-y-3 h-[395px]">
                        <?php if(mysqli_num_rows($todo_query) > 0): ?>
                            <?php 
                            $todoColors = [
                                ['bg' => 'slate-50', 'border' => 'slate-100', 'text' => 'slate-700'],
                                ['bg' => 'orange-50', 'border' => 'orange-100', 'text' => 'orange-700'],
                                ['bg' => 'rose-50', 'border' => 'rose-100', 'text' => 'rose-700'],
                                ['bg' => 'purple-50', 'border' => 'purple-100', 'text' => 'purple-700'],
                                ['bg' => 'blue-50', 'border' => 'blue-100', 'text' => 'blue-700'],
                                ['bg' => 'yellow-50', 'border' => 'yellow-100', 'text' => 'yellow-700']
                            ];
                            $c_idx = 0;
                            while($todo = mysqli_fetch_assoc($todo_query)): 
                                $c = $todoColors[$c_idx % 6];
                                $c_idx++;
                                $checked = ($todo['status'] == 'completed') ? 'checked' : '';
                            ?>
                            <div class="bg-<?= $c['bg'] ?> border border-<?= $c['border'] ?> p-3 rounded-xl flex items-center gap-3">
                                <i class="fa-solid fa-grip-vertical text-<?= $c['text'] ?> opacity-30 text-xs cursor-move"></i>
                                <input type="checkbox" class="w-4 h-4 rounded cursor-pointer" <?= $checked ?>>
                                <span class="text-sm font-bold text-<?= $c['text'] ?> <?= $checked ? 'line-through opacity-70' : '' ?>"><?= htmlspecialchars($todo['title']) ?></span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-sm text-slate-500 mt-5">No tasks found. Enjoy!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Schedules</h3>
                    <button onclick="window.location.href='calendar.php'" class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition hover:bg-slate-100">View All</button>
                </div>
                <div class="card-body p-4 space-y-4 overflow-y-auto custom-scroll h-[380px]">
                    <?php if(mysqli_num_rows($sched_query) > 0): ?>
                        <?php while($sched = mysqli_fetch_assoc($sched_query)): ?>
                        <div class="border border-slate-200 rounded-xl p-4 shadow-sm bg-slate-50 hover:border-slate-300 transition">
                            <span class="bg-[#16636B] text-teal-100 text-[10px] font-black uppercase px-2 py-1 rounded mb-2.5 inline-block">Meeting</span>
                            <h4 class="font-bold text-slate-800 text-sm mb-2"><?= htmlspecialchars($sched['title']) ?></h4>
                            <div class="flex items-center text-xs text-slate-500 font-medium mb-4 gap-3">
                                <span class="flex items-center gap-1"><i class="fa-regular fa-calendar"></i> <?= date('D, d M Y', strtotime($sched['meet_date'])) ?></span>
                                <span class="flex items-center gap-1"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($sched['meet_time']) ?></span>
                            </div>
                            <div class="flex justify-between items-center mt-2 border-t border-slate-200 pt-3">
                                <div class="flex -space-x-2">
                                    <img src="https://i.pravatar.cc/150?img=1" class="w-8 h-8 rounded-full border-2 border-white object-cover shadow-sm">
                                    <img src="https://i.pravatar.cc/150?img=2" class="w-8 h-8 rounded-full border-2 border-white object-cover shadow-sm">
                                </div>
                                <button class="bg-white border border-slate-200 text-slate-700 hover:text-[#16636B] hover:border-[#16636B] px-4 py-2 rounded-lg text-xs font-bold transition shadow-sm">Join Meeting</button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-sm text-slate-500 text-center py-10">No upcoming schedules.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Recent Activities</h3>
                    <button class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition hover:bg-slate-100">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="overflow-y-auto custom-scroll p-5 space-y-5 h-[380px]">
                        <?php if(mysqli_num_rows($act_query) > 0): ?>
                            <?php while($act = mysqli_fetch_assoc($act_query)): ?>
                            <div class="flex items-start gap-3.5 border-b border-slate-50 pb-4">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-bell"></i></div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-center">
                                        <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($act['title']) ?></h4>
                                        <span class="text-[10px] text-slate-400 font-medium"><?= date('h:i A', strtotime($act['created_at'])) ?></span>
                                    </div>
                                    <p class="text-sm text-slate-500 mt-0.5 truncate w-64"><?= htmlspecialchars($act['message']) ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-slate-500 text-sm mt-5">No recent activities.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card flex flex-col">
                <div class="card-header">
                    <h3 class="card-title">Birthdays</h3>
                    <button class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition hover:bg-slate-100">View All</button>
                </div>
                <div class="card-body p-4 space-y-4 overflow-y-auto custom-scroll h-[380px]">
                    <?php 
                        $bday_query = mysqli_query($conn, "SELECT full_name, designation, profile_img, dob FROM employee_profiles WHERE MONTH(dob) = MONTH(CURDATE()) AND dob != '0000-00-00' AND dob IS NOT NULL AND status='Active' ORDER BY DAY(dob) ASC");
                        
                        $today_bdays = [];
                        $upcoming_bdays = [];
                        $current_day = (int)date('d');

                        while($bday = mysqli_fetch_assoc($bday_query)) {
                            $bday_day = (int)date('d', strtotime($bday['dob']));
                            if ($bday_day === $current_day) {
                                $today_bdays[] = $bday;
                            } elseif ($bday_day > $current_day) {
                                $upcoming_bdays[] = $bday;
                            }
                        }
                    ?>
                    
                    <?php if(count($today_bdays) > 0 || count($upcoming_bdays) > 0): ?>
                        
                        <?php if(count($today_bdays) > 0): ?>
                            <div>
                                <h4 class="text-xs font-black text-slate-800 mb-2 uppercase tracking-widest">Today</h4>
                                <?php foreach($today_bdays as $tbday): ?>
                                <div class="bg-[#16636B] rounded-xl p-3 flex justify-between items-center text-white shadow-md relative overflow-hidden border border-[#0d3f44] mb-3">
                                    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 12px 12px;"></div>
                                    <div class="flex items-center gap-3 relative z-10">
                                        <img src="<?= getAvatar($tbday['profile_img'], $tbday['full_name']) ?>" class="w-11 h-11 rounded-full border-2 border-white/30 object-cover shadow-sm">
                                        <div>
                                            <h5 class="text-sm font-bold"><?= htmlspecialchars($tbday['full_name']) ?></h5>
                                            <p class="text-[11px] text-teal-100 font-medium"><?= htmlspecialchars($tbday['designation']) ?></p>
                                        </div>
                                    </div>
                                    <button class="bg-white text-slate-700 text-xs font-bold px-3 py-2 rounded-lg flex items-center gap-1.5 shadow hover:bg-slate-50 transition relative z-10"><i class="fa-solid fa-gift text-[#ea580c]"></i> Send</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(count($upcoming_bdays) > 0): ?>
                            <div>
                                <h4 class="text-xs font-black text-slate-800 mb-2 mt-3 uppercase tracking-widest">Upcoming</h4>
                                <div class="space-y-3">
                                    <?php foreach($upcoming_bdays as $ubday): ?>
                                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 flex justify-between items-center hover:shadow-sm transition cursor-pointer">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= getAvatar($ubday['profile_img'], $ubday['full_name']) ?>" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <h5 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($ubday['full_name']) ?></h5>
                                                <p class="text-[11px] text-slate-500 font-medium flex items-center gap-1"><i class="fa-regular fa-calendar text-slate-400"></i> <?= date('M d', strtotime($ubday['dob'])) ?> • <?= htmlspecialchars($ubday['designation']) ?></p>
                                            </div>
                                        </div>
                                        <button class="bg-white border border-slate-200 text-slate-600 text-xs font-bold px-3 py-1.5 rounded-lg flex items-center gap-1 shadow-sm hover:border-slate-300 hover:text-slate-800 transition"><i class="fa-solid fa-gift text-slate-400"></i></button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-full text-slate-400">
                            <i class="fa-solid fa-cake-candles text-4xl mb-3 text-slate-300"></i>
                            <p class="text-center text-sm font-medium">No upcoming birthdays<br>this month.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </main>

    <script>
        // URL Parameter Updater Function (Keeps options linked dynamically)
        function updateUrlFilter(param, value) {
            const url = new URL(window.location.href);
            if (value) {
                url.searchParams.set(param, value);
            } else {
                url.searchParams.delete(param); // Clear filter if "All" is selected
            }
            window.location.href = url.href;
        }

        // Layout Observer (Maintains Sidebar structure)
        function setupLayoutObserver() {
            const primarySidebar = document.querySelector('.sidebar-primary');
            const secondarySidebar = document.querySelector('.sidebar-secondary');
            const mainContent = document.getElementById('mainContent');
            if (!primarySidebar || !mainContent) return;

            const updateMargin = () => {
                if (window.innerWidth <= 1024) {
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

        // Job Applicants / Openings Tab Toggle Function
        function switchJobTab(tabName) {
            const btnOpenings = document.getElementById('tabOpenings');
            const btnApplicants = document.getElementById('tabApplicants');
            const viewOpenings = document.getElementById('viewOpenings');
            const viewApplicants = document.getElementById('viewApplicants');

            if (tabName === 'openings') {
                btnOpenings.className = 'flex-1 py-2 text-sm font-bold text-white bg-[#ea580c] rounded shadow-sm';
                btnApplicants.className = 'flex-1 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 transition';
                viewOpenings.classList.remove('hidden');
                viewApplicants.classList.add('hidden');
            } else {
                btnApplicants.className = 'flex-1 py-2 text-sm font-bold text-white bg-[#ea580c] rounded shadow-sm';
                btnOpenings.className = 'flex-1 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 transition';
                viewApplicants.classList.remove('hidden');
                viewOpenings.classList.add('hidden');
            }
        }

        // ================= APEXCHARTS: EMPLOYEES BY DEPARTMENT =================
        document.addEventListener('DOMContentLoaded', function () {
            var options = {
                series: [{
                    name: 'Employees',
                    data: <?= json_encode($dept_counts) ?>
                }],
                chart: {
                    type: 'bar',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif'
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: '40%',
                        colors: {
                            ranges: [{
                                from: 0,
                                to: 10000,
                                color: '#ea580c' // Orange match
                            }]
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                xaxis: {
                    categories: <?= json_encode($departments) ?>,
                    labels: { style: { colors: '#718096', fontWeight: 500 } }
                },
                yaxis: {
                    labels: { style: { colors: '#4a5568', fontWeight: 600 } }
                },
                grid: {
                    xaxis: { lines: { show: true } },
                    yaxis: { lines: { show: false } },
                    borderColor: '#f1f5f9'
                },
                tooltip: {
                    theme: 'light'
                }
            };

            var chart = new ApexCharts(document.querySelector("#departmentChart"), options);
            chart.render();
        });

        // ================= CHART.JS CONFIGURATIONS =================
        Chart.defaults.font.family = "'Inter', sans-serif";

        // 1. Attendance Gauge Chart
        const attGaugeCtx = document.getElementById('attendanceGauge').getContext('2d');
        new Chart(attGaugeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [<?= $present_today ?>, <?= max(0, $active_employees - $present_today) ?>],
                    backgroundColor: ['#10b981', '#ef4444'], // Green, Red
                    borderWidth: 0,
                    cutout: '75%',
                    borderRadius: [10, 10]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                rotation: -90, 
                circumference: 180,
                plugins: { legend: { display: false }, tooltip: { enabled: true } }
            }
        });

        // 2. Sales Overview Bar Chart 
        const salesData = <?= json_encode(array_values($sales_data)) ?>;
        const incData = salesData.map(d => d.inc);
        const expData = salesData.map(d => d.exp);

        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Income',
                        data: incData,
                        backgroundColor: '#ea580c', 
                        borderRadius: 6,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Expenses',
                        data: expData, 
                        backgroundColor: '#f1f5f9', 
                        borderRadius: 6,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8,
                        grouped: false, 
                        order: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: '600' } } },
                    y: { border: { dash: [4, 4] }, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8' } }
                },
                plugins: { legend: { display: false } }
            }
        });

        // 3. Tasks Statistics Half-Donut
        const taskCtx = document.getElementById('taskDonut').getContext('2d');
        new Chart(taskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [<?= $t_pend ?>, <?= $t_prog ?>, <?= $t_comp ?>],
                    backgroundColor: ['#3b82f6', '#eab308', '#10b981'],
                    borderWidth: 0,
                    borderRadius: 15,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                rotation: -90,
                circumference: 180,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>