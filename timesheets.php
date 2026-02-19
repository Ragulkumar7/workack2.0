<?php
// timesheets.php (Step-by-Step Drill-down View)

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. DATABASE CONNECTION
// (Assuming file is in root directory. If inside a folder, use '../include/db_connect.php')
require_once 'include/db_connect.php';

// 3. GET URL PARAMETERS (To determine which level we are viewing)
$view_mgr = $_GET['view_mgr'] ?? null;
$view_tl  = $_GET['view_tl'] ?? null;
$filter_date = $_GET['f_date'] ?? date('Y-m-d');
$safe_date = mysqli_real_escape_string($conn, $filter_date);

// 4. STEP-BY-STEP HIERARCHY LOGIC
$back_link = "";
$page_title = "";
$query_condition = "";

if ($view_tl) {
    // ==========================================
    // LEVEL 3: View Employees under a Team Lead
    // ==========================================
    $safe_tl = mysqli_real_escape_string($conn, $view_tl);
    
    // Find who this TL reports to (so we know where the 'Back' button should go)
    $parent_mgr = $_GET['view_mgr'] ?? '';
    
    if (empty($parent_mgr)) {
        $mgr_q = mysqli_query($conn, "SELECT manager_id, department FROM employee_profiles WHERE user_id = '$safe_tl'");
        if ($mgr_q && mysqli_num_rows($mgr_q) > 0) {
            $mgr_data = mysqli_fetch_assoc($mgr_q);
            $parent_mgr = $mgr_data['manager_id'] ?? '';
            // Fallback: match by department if no manager_id is set
            if (empty($parent_mgr) && !empty($mgr_data['department'])) {
                $dept_mgr_q = mysqli_query($conn, "SELECT u.id FROM users u JOIN employee_profiles e ON u.id = e.user_id WHERE u.role IN ('Manager', 'CFO', 'HR Manager', 'Sales Head') AND e.department = '{$mgr_data['department']}' LIMIT 1");
                if ($dept_mgr_q && mysqli_num_rows($dept_mgr_q) > 0) {
                    $parent_mgr = mysqli_fetch_assoc($dept_mgr_q)['id'] ?? '';
                }
            }
        }
    }

    $query_condition = "ep.reporting_to = '$safe_tl'";
    $back_link = "?view_mgr=$parent_mgr&f_date=$safe_date";
    $page_title = "Team Members Timesheets";

} elseif ($view_mgr) {
    // ==========================================
    // LEVEL 2: View Team Leads under a Manager
    // ==========================================
    $safe_mgr = mysqli_real_escape_string($conn, $view_mgr);
    
    // Get Manager's Department to find their TLs
    $dept_q = mysqli_query($conn, "SELECT department FROM employee_profiles WHERE user_id = '$safe_mgr'");
    $mgr_dept = "";
    if ($dept_q && mysqli_num_rows($dept_q) > 0) {
        $dept_data = mysqli_fetch_assoc($dept_q);
        $mgr_dept = mysqli_real_escape_string($conn, $dept_data['department'] ?? '');
    }

    $query_condition = "u.role = 'Team Lead' AND (ep.manager_id = '$safe_mgr' OR ep.department = '$mgr_dept')";
    $back_link = "?f_date=$safe_date"; // Back to Level 1 (Root)
    $page_title = "Team Leads Timesheets";

} else {
    // ==========================================
    // LEVEL 1: Root View (Show ALL Managers)
    // ==========================================
    $query_condition = "u.role IN ('Manager', 'CFO', 'HR Manager', 'Sales Head')";
    $back_link = ""; // No back button on root
    $page_title = "Department Managers Timesheets";
}

// 5. FETCH TIMESHEETS (LEFT JOIN so we see them even if they didn't punch in)
$query = "
    SELECT DISTINCT u.id as user_id, u.name as emp_name, u.role, ep.emp_id_code, ep.department, 
           a.punch_in, a.punch_out, a.production_hours, a.status 
    FROM users u 
    JOIN employee_profiles ep ON u.id = ep.user_id 
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = '$safe_date' 
    WHERE $query_condition
    ORDER BY u.name ASC
";

$result = mysqli_query($conn, $query);
$timesheets = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $timesheets[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Timesheets</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; overflow-x: hidden; }
        
        #mainContent { 
            margin-left: 95px; 
            padding: 24px 32px;  
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px; background: #fff; }
        table th { letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
        
        /* Hover effect for clickable rows */
        .click-row { cursor: pointer; transition: background-color 0.2s ease; }
        .click-row:hover { background-color: #f1f5f9; }
        .click-row:hover .row-arrow { transform: translateX(5px); color: #1b5a5a; }
        
        .progress { background-color: #f1f5f9; border-radius: 9999px; overflow: hidden; height: 5px; width: 80px; margin: 6px auto 0 auto; }
        .progress-bar { height: 100%; border-radius: 9999px; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
        <?php include 'header.php'; ?>

        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h4 class="text-2xl font-bold text-gray-800"><?php echo $page_title; ?></h4>
                <p class="text-gray-500 text-sm mt-1">Select a row to drill down into their team. (Target: 8.25 hrs)</p>
                
                <?php if ($back_link !== ""): ?>
                    <a href="<?php echo $back_link; ?>" class="inline-flex items-center mt-3 bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-50 transition shadow-sm text-decoration-none">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Back
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="GET" action="" class="flex items-end gap-3 bg-white p-3 rounded-xl shadow-sm border border-gray-200">
                <?php if($view_mgr): ?> <input type="hidden" name="view_mgr" value="<?php echo htmlspecialchars($view_mgr); ?>"> <?php endif; ?>
                <?php if($view_tl): ?> <input type="hidden" name="view_tl" value="<?php echo htmlspecialchars($view_tl); ?>"> <?php endif; ?>

                <div class="flex flex-col">
                    <label class="text-[10px] font-bold text-gray-400 mb-1 uppercase">Date Filter</label>
                    <input type="date" name="f_date" value="<?php echo htmlspecialchars($filter_date); ?>" 
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a]">
                </div>
                <button type="submit" class="bg-[#1b5a5a] hover:bg-[#134444] text-white px-5 py-2 rounded-lg text-sm font-bold transition-colors shadow-sm">
                    Load
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase">
                            <th class="px-6 py-4 font-bold">Employee Details</th>
                            <th class="px-4 py-4 font-bold">Emp ID</th>
                            <th class="px-4 py-4 font-bold">Department</th>
                            <th class="px-4 py-4 font-bold">Punch In</th>
                            <th class="px-4 py-4 font-bold">Punch Out</th>
                            <th class="px-4 py-4 font-bold text-center">Production</th>
                            <th class="px-4 py-4 font-bold text-center">Overtime</th>
                            <th class="px-4 py-4 font-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (empty($timesheets)) {
                            echo "<tr><td colspan='8' class='text-center py-10 text-gray-400'><i class='fa-solid fa-users-slash text-4xl mb-3 block'></i> No members found in this view.</td></tr>";
                        } else {
                            foreach($timesheets as $row): 
                                
                                // Format Data
                                $has_attendance = !empty($row['punch_in']);
                                $punch_in = $has_attendance ? date('h:i A', strtotime($row['punch_in'])) : '---';
                                $punch_out = $has_attendance && $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : ($has_attendance ? 'Active' : '---');
                                
                                $expected_hours = 8.25;
                                $worked_hours = floatval($row['production_hours'] ?? 0);
                                $overtime = ($worked_hours > $expected_hours) ? ($worked_hours - $expected_hours) : 0;
                                $percent = min(100, ($worked_hours / $expected_hours) * 100);
                                
                                $progressColor = 'bg-yellow-500';
                                if ($worked_hours >= $expected_hours) $progressColor = 'bg-green-500';
                                else if ($worked_hours < 4 && $punch_out != 'Active') $progressColor = 'bg-red-500';

                                if (!$has_attendance) {
                                    $statusClass = "bg-gray-100 text-gray-500 border border-gray-200";
                                    $status_text = "No Record";
                                } else {
                                    $statusClass = "bg-light text-secondary";
                                    if ($row['status'] == 'On Time') $statusClass = "bg-green-500 text-white shadow-sm";
                                    else if ($row['status'] == 'Late') $statusClass = "bg-yellow-400 text-dark";
                                    else if ($row['status'] == 'Absent') $statusClass = "bg-red-500 text-white shadow-sm";
                                    $status_text = htmlspecialchars($row['status']);
                                }

                                // Drill-down click logic
                                $row_link = "";
                                $badge = "";
                                
                                if (empty($view_tl) && empty($view_mgr)) {
                                    // We are at Level 1 (Managers). Clicking goes to Level 2 (Team Leads)
                                    $row_link = "?view_mgr={$row['user_id']}&f_date={$safe_date}";
                                    $badge = "<span class='bg-blue-50 text-blue-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border border-blue-100'>Manager</span>";
                                } elseif (!empty($view_mgr) && empty($view_tl)) {
                                    // We are at Level 2 (Team Leads). Clicking goes to Level 3 (Employees)
                                    $row_link = "?view_tl={$row['user_id']}&view_mgr={$view_mgr}&f_date={$safe_date}";
                                    $badge = "<span class='bg-orange-50 text-orange-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border border-orange-100'>Team Lead</span>";
                                }
                        ?>
                        
                        <tr class="border-b <?php echo $row_link ? 'click-row' : 'hover:bg-slate-50'; ?>" 
                            <?php echo $row_link ? "onclick=\"window.location='$row_link'\"" : ""; ?>>
                            
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['emp_name']); ?>&background=random" class="w-10 h-10 rounded-full border border-gray-200 mr-4 shadow-sm">
                                    <div>
                                        <div class="font-bold text-gray-800 flex items-center">
                                            <?php echo htmlspecialchars($row['emp_name'] ?: 'Unknown'); ?>
                                            <?php if($row_link): ?>
                                                <i class="fa-solid fa-arrow-right ml-2 text-xs text-gray-400 row-arrow transition-transform"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars($row['role']); ?></span>
                                            <?php echo $badge; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-4 py-4 text-sm text-gray-600 font-medium"><?php echo htmlspecialchars($row['emp_id_code'] ?: 'N/A'); ?></td>
                            <td class="px-4 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($row['department'] ?: 'N/A'); ?></td>
                            
                            <td class="px-4 py-4 text-sm font-medium text-emerald-600">
                                <?php if($punch_in !== '---') echo "<i class='fa-solid fa-arrow-right-to-bracket text-[10px] mr-1'></i>"; ?> <?php echo $punch_in; ?>
                            </td>
                            
                            <td class="px-4 py-4">
                                <?php if($punch_out === 'Active'): ?>
                                    <span class="text-blue-600 text-sm font-medium"><i class="fa-solid fa-spinner fa-spin text-[10px] mr-1"></i> Working...</span>
                                <?php elseif($punch_out !== '---'): ?>
                                    <span class="text-red-500 text-sm font-medium"><i class="fa-solid fa-arrow-right-from-bracket text-[10px] mr-1"></i> <?php echo $punch_out; ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">---</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-4 py-4 text-center">
                                <?php if ($has_attendance): ?>
                                    <span class="font-bold text-gray-800 text-sm"><?php echo number_format($worked_hours, 2); ?> Hrs</span>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $progressColor; ?>" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-4 py-4 text-center">
                                <?php if ($overtime > 0): ?>
                                    <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold">+<?php echo number_format($overtime, 2); ?> Hrs</span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-4 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $statusClass; ?>"><?php echo $status_text; ?></span>
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