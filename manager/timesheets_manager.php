<?php
// timesheets_manager.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

// 2. DATABASE CONNECTION
require_once '../include/db_connect.php';

$session_id = $_SESSION['user_id'];

// 3. FETCH CURRENT USER'S ROLE AND DEPARTMENT
$role_q = mysqli_query($conn, "SELECT role FROM users WHERE id = '$session_id'");
$role_row = mysqli_fetch_assoc($role_q);
$role = $role_row['role'] ?? 'Employee';

// Fetch all departments associated with this manager/user
$user_depts = [];
$dept_q = mysqli_query($conn, "SELECT DISTINCT department FROM employee_profiles WHERE user_id = '$session_id' AND department IS NOT NULL");
while ($d = mysqli_fetch_assoc($dept_q)) {
    $user_depts[] = "'" . mysqli_real_escape_string($conn, $d['department']) . "'";
}
$dept_in = count($user_depts) > 0 ? implode(',', $user_depts) : "''";

// 4. HIERARCHY / ROLE-BASED VISIBILITY LOGIC
$hierarchy_sql = "";
if ($role === 'Manager') {
    // Manager sees everyone in their department OR whoever has them assigned as manager_id OR themselves
    $hierarchy_sql = " AND (ep.department IN ($dept_in) OR ep.manager_id = '$session_id' OR u.id = '$session_id')";
} elseif ($role === 'Team Lead') {
    // TL sees themselves and employees explicitly reporting to them
    $hierarchy_sql = " AND (ep.reporting_to = '$session_id' OR u.id = '$session_id')";
} elseif ($role === 'Employee') {
    // Employee only sees their own data
    $hierarchy_sql = " AND u.id = '$session_id'";
} 

// 5. FILTERING LOGIC
$filter_date  = $_GET['f_date'] ?? '';
$filter_month = $_GET['f_month'] ?? '';
$filter_year  = $_GET['f_year'] ?? '';
$filter_tl    = $_GET['f_tl'] ?? '';
$filter_emp   = $_GET['f_emp'] ?? '';

// Default to current month/year if ALL filters are empty
if (empty($filter_date) && empty($filter_month) && empty($filter_year) && empty($filter_tl) && empty($filter_emp)) {
    $filter_month = date('m');
    $filter_year = date('Y');
}

$conditions = [];

// Apply Date Filters
if (!empty($filter_date)) {
    $conditions[] = "a.date = '" . mysqli_real_escape_string($conn, $filter_date) . "'";
} else {
    if (!empty($filter_month)) {
        $conditions[] = "MONTH(a.date) = '" . mysqli_real_escape_string($conn, $filter_month) . "'";
    }
    if (!empty($filter_year)) {
        $conditions[] = "YEAR(a.date) = '" . mysqli_real_escape_string($conn, $filter_year) . "'";
    }
}

// Apply Hierarchy/People Filters
if (!empty($filter_tl)) {
    // Show the selected TL AND their team members
    $conditions[] = "(ep.reporting_to = '" . mysqli_real_escape_string($conn, $filter_tl) . "' OR a.user_id = '" . mysqli_real_escape_string($conn, $filter_tl) . "')";
} else {
    // MANAGER VIEW LOGIC: If Manager and no TL filter applied, show ONLY Team Leads initially
    if ($role === 'Manager' && empty($filter_emp)) {
        $conditions[] = "u.role = 'Team Lead'";
    }
}

if (!empty($filter_emp)) {
    $conditions[] = "a.user_id = '" . mysqli_real_escape_string($conn, $filter_emp) . "'";
}

$whereSQL = count($conditions) > 0 ? " AND " . implode(' AND ', $conditions) : "";

// 6. FETCH TIMESHEET DATA 
$query = "
    SELECT DISTINCT
        a.id as attendance_id,
        u.id as user_id,
        u.name as emp_name,
        u.role,
        ep.emp_id_code,
        ep.department,
        a.date,
        a.punch_in,
        a.punch_out,
        a.production_hours,
        a.status,
        tl.name as team_lead_name,
        mgr.name as manager_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN employee_profiles ep ON u.id = ep.user_id
    LEFT JOIN users tl ON ep.reporting_to = tl.id
    LEFT JOIN users mgr ON ep.manager_id = mgr.id
    WHERE 1=1 
    $hierarchy_sql
    $whereSQL
    ORDER BY a.date DESC, a.punch_in DESC
";

$result = mysqli_query($conn, $query);
$timesheets = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $timesheets[] = $row;
    }
}

// 7. PREPARE DROPDOWN DATA
$current_year = date('Y');
$years = range(2024, $current_year + 2);
$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

$tls = [];
$tl_q = mysqli_query($conn, "SELECT DISTINCT u.id, u.name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.role = 'Team Lead' $hierarchy_sql");
if($tl_q) { while($r = mysqli_fetch_assoc($tl_q)) { $tls[] = $r; } }

$emps = [];
$emp_q = mysqli_query($conn, "SELECT DISTINCT u.id, u.name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.role != 'System Admin' $hierarchy_sql");
if($emp_q) { while($r = mysqli_fetch_assoc($emp_q)) { $emps[] = $r; } }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Timesheets</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-orange: #1b5a5a; --bg-gray: #f8f9fa; --border-color: #edf2f7; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 13px; color: #333; overflow-x: hidden; }
        
        #mainContent { 
            margin-left: 95px; 
            padding: 25px 35px;  
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px; background: #fff; }
        
        .table thead th { background: #f9fafb; padding: 15px; border-bottom: 1px solid var(--border-color); color: #4a5568; font-weight: 600; text-transform: uppercase; font-size: 12px; white-space: nowrap; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .avatar-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .badge-ot { background-color: #fef3c7; color: #d97706; padding: 4px 8px; border-radius: 6px; font-weight: bold; font-size: 11px; }
        .badge-status { padding: 4px 8px; border-radius: 6px; font-weight: bold; font-size: 11px; }
        .team-badge { background-color: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('../sidebars.php'); ?>

    <main id="mainContent">
        <?php include '../header.php'; ?>

        <div class="mb-4">
            <h4 class="fw-bold mb-0 text-dark">
                <?php 
                    if($role === 'Manager') echo "Department Timesheets";
                    elseif($role === 'Team Lead') echo "My Team Timesheets";
                    else echo "Employee Timesheets";
                ?>
            </h4>
            <p class="text-muted small mb-0">Daily production & overtime calculations (Target: 8.25 hrs)</p>
            
            <?php if (!empty($filter_tl)): ?>
                <a href="timesheets_manager.php?f_month=<?php echo $filter_month; ?>&f_year=<?php echo $filter_year; ?>" class="inline-block mt-3 bg-white border border-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-gray-50 transition shadow-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Team Leads
                </a>
            <?php endif; ?>
        </div>

        <form method="GET" action="timesheets_manager.php" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
            
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Exact Date</label>
                <input type="date" name="f_date" value="<?php echo htmlspecialchars($filter_date); ?>" 
                       class="w-full border border-gray-300 rounded-lg px-3 text-sm outline-none focus:border-[#1b5a5a] h-[42px]">
            </div>

            <div class="flex-1 min-w-[130px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Month</label>
                <select name="f_month" class="w-full border border-gray-300 rounded-lg px-3 text-sm outline-none focus:border-[#1b5a5a] h-[42px]">
                    <option value="">All Months</option>
                    <?php foreach($months as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($filter_month == $num) ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-[110px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Year</label>
                <select name="f_year" class="w-full border border-gray-300 rounded-lg px-3 text-sm outline-none focus:border-[#1b5a5a] h-[42px]">
                    <option value="">All Years</option>
                    <?php foreach($years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($filter_year == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if($role === 'Manager' || $role === 'HR' || $role === 'System Admin' || $role === 'HR Executive'): ?>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Team (Lead)</label>
                <select name="f_tl" class="w-full border border-gray-300 rounded-lg px-3 text-sm outline-none focus:border-[#1b5a5a] h-[42px]">
                    <option value="">All Teams</option>
                    <?php foreach($tls as $tl): ?>
                        <option value="<?php echo $tl['id']; ?>" <?php echo ($filter_tl == $tl['id']) ? 'selected' : ''; ?>>
                            Team: <?php echo htmlspecialchars($tl['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if($role !== 'Employee'): ?>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Employee</label>
                <select name="f_emp" class="w-full border border-gray-300 rounded-lg px-3 text-sm outline-none focus:border-[#1b5a5a] h-[42px]">
                    <option value="">All Employees</option>
                    <?php foreach($emps as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo ($filter_emp == $emp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-2 lg:col-span-1">
                <button type="submit" class="bg-[#1b5a5a] hover:bg-[#134444] text-white px-4 rounded-lg font-semibold transition-colors h-[42px] flex items-center justify-center gap-2 w-full">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
                <a href="timesheets_manager.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 rounded-lg font-semibold transition-colors h-[42px] flex items-center justify-center text-decoration-none w-full border">
                    Reset
                </a>
            </div>
            
        </form>

        <div class="card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Employee Details</th>
                            <th>Emp ID</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th class="text-center">Total Prod. Hours</th>
                            <th class="text-center">Overtime</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (empty($timesheets)) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'><i class='fa-regular fa-folder-open text-3xl mb-2 text-gray-300 block'></i> No attendance records found for the selected criteria.</td></tr>";
                        } else {
                            foreach($timesheets as $row): 
                                
                                $date_formatted = date('d M Y', strtotime($row['date']));
                                $punch_in = $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : '---';
                                $punch_out = $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : 'Active';
                                
                                $expected_hours = 8.25;
                                $worked_hours = floatval($row['production_hours']);
                                
                                $overtime = ($worked_hours > $expected_hours) ? ($worked_hours - $expected_hours) : 0;
                                
                                $percent = ($worked_hours / $expected_hours) * 100;
                                if ($percent > 100) $percent = 100;
                                
                                $progressColor = 'bg-warning';
                                if ($worked_hours >= $expected_hours) $progressColor = 'bg-success';
                                else if ($worked_hours < 4 && $punch_out != 'Active') $progressColor = 'bg-danger';

                                $statusClass = "bg-light text-secondary";
                                if ($row['status'] == 'On Time') $statusClass = "bg-success text-white";
                                else if ($row['status'] == 'Late') $statusClass = "bg-warning text-dark";
                                else if ($row['status'] == 'Absent') $statusClass = "bg-danger text-white";
                                
                                $team_label = "";
                                if ($row['role'] === 'Team Lead') {
                                    $team_label = "<span class='team-badge bg-orange-100 text-orange-700'>Team Lead</span>";
                                } elseif (!empty($row['team_lead_name'])) {
                                    $team_label = "<span class='team-badge'>Team: " . htmlspecialchars($row['team_lead_name']) . "</span>";
                                } elseif (!empty($row['manager_name'])) {
                                    $team_label = "<span class='team-badge bg-purple-100 text-purple-700'>Reports to: " . htmlspecialchars($row['manager_name']) . "</span>";
                                }
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['emp_name']); ?>&background=random" class="avatar-img">
                                    <div>
                                        <?php if ($row['role'] === 'Team Lead' && $role === 'Manager' && empty($filter_tl)): ?>
                                            <a href="timesheets_manager.php?f_tl=<?php echo $row['user_id']; ?>&f_month=<?php echo $filter_month; ?>&f_year=<?php echo $filter_year; ?>" class="fw-bold text-[#1b5a5a] text-decoration-none hover:underline" title="Click to view Team Members">
                                                <?php echo htmlspecialchars($row['emp_name'] ?: 'Unknown'); ?> <i class="fa-solid fa-arrow-right-to-bracket ms-1 text-[10px]"></i>
                                            </a>
                                        <?php else: ?>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['emp_name'] ?: 'Unknown'); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-1 flex items-center gap-2">
                                            <small class="text-muted"><?php echo htmlspecialchars($row['role']); ?></small>
                                            <?php echo $team_label; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="font-medium text-gray-700"><?php echo htmlspecialchars($row['emp_id_code'] ?: 'N/A'); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($row['department'] ?: 'N/A'); ?></td>
                            
                            <td class="font-medium text-gray-700"><?php echo $date_formatted; ?></td>
                            <td><span class="text-success fw-medium"><i class="fa-solid fa-arrow-right-to-bracket text-xs"></i> <?php echo $punch_in; ?></span></td>
                            <td>
                                <?php if($punch_out === 'Active'): ?>
                                    <span class="text-primary fw-medium"><i class="fa-solid fa-spinner fa-spin text-xs"></i> Working...</span>
                                <?php else: ?>
                                    <span class="text-danger fw-medium"><i class="fa-solid fa-arrow-right-from-bracket text-xs"></i> <?php echo $punch_out; ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-center">
                                <span class="fw-bold text-dark"><?php echo number_format($worked_hours, 2); ?> Hrs</span>
                                <div class="progress mt-1" style="height: 5px; width: 80px; margin: 0 auto; background-color: #f1f5f9;">
                                    <div class="progress-bar <?php echo $progressColor; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </td>
                            
                            <td class="text-center">
                                <?php if ($overtime > 0): ?>
                                    <span class="badge-ot">+<?php echo number_format($overtime, 2); ?> Hrs</span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-center">
                                <span class="badge-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                            </td>
                        </tr>
                        <?php 
                            endforeach; 
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>