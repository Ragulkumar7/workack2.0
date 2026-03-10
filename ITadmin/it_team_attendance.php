<?php
// it_team_attendance.php - IT Admin view of IT Executive Attendance

// 1. SESSION & SECURITY GUARD
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION & CONFIG (Smart Path Resolver)
$dbPath = 'include/db_connect.php';
$root_path = './';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
    $root_path = '../';
} else {
    die("Critical Error: Cannot find database connection file.");
}

$admin_id = $_SESSION['user_id'];

// 3. FILTER LOGIC
$filter_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$is_today = ($filter_date === date('Y-m-d'));

// 4. FETCH IT EXECUTIVE TEAM ATTENDANCE DATA
// This safely joins all users with the role 'IT Executive' with their attendance for the selected date
$sql = "
    SELECT u.id as user_id, 
           COALESCE(ep.full_name, u.name, u.username) as full_name, 
           ep.emp_id_code, 
           u.role as designation, 
           ep.profile_img, 
           ep.shift_timings,
           a.id as att_id,
           a.punch_in, 
           a.punch_out, 
           a.status as att_status, 
           a.production_hours
    FROM users u
    LEFT JOIN employee_profiles ep ON u.id = ep.user_id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.role = 'IT Executive'
    GROUP BY u.id
    ORDER BY full_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $filter_date);
$stmt->execute();
$result = $stmt->get_result();

$it_team = [];
$metrics = [
    'total' => 0,
    'present' => 0,
    'late' => 0,
    'on_leave' => 0,
    'absent' => 0,
    'yet_to_punch' => 0
];

while ($row = $result->fetch_assoc()) {
    $metrics['total']++;
    
    // Determine visual status
    $display_status = "Absent";
    $status_color = "bg-rose-50 text-rose-600 border-rose-200";
    
    $db_status = $row['att_status'] ?? '';

    // Check if they are officially marked on Leave
    if (stripos($db_status, 'Leave') !== false) {
        $display_status = "On Leave";
        $status_color = "bg-purple-50 text-purple-600 border-purple-200";
        $metrics['on_leave']++;
    }
    // Check if they punched in
    elseif (!empty($row['punch_in'])) {
        $shift_timings = $row['shift_timings'] ?? '09:00 AM - 06:00 PM';
        $time_parts = explode('-', $shift_timings);
        $shift_start_str = trim($time_parts[0]);
        
        $shift_start = strtotime($filter_date . ' ' . $shift_start_str);
        $actual_in = strtotime($row['punch_in']);
        
        // Auto-detect late if not already marked
        $is_late = ($actual_in > ($shift_start + 60)) || (stripos($db_status, 'Late') !== false);

        if ($is_late) {
            $display_status = "Late";
            $status_color = "bg-amber-50 text-amber-600 border-amber-200";
            $metrics['late']++;
            $metrics['present']++; // Late is still present
        } elseif (stripos($db_status, 'WFH') !== false) {
            $display_status = "WFH";
            $status_color = "bg-blue-50 text-blue-600 border-blue-200";
            $metrics['present']++;
        } else {
            $display_status = "On Time";
            $status_color = "bg-emerald-50 text-emerald-600 border-emerald-200";
            $metrics['present']++;
        }
    } 
    // Handle missing punches
    else {
        if ($is_today) {
            $display_status = "Yet to Punch";
            $status_color = "bg-slate-100 text-slate-500 border-slate-200";
            $metrics['yet_to_punch']++;
        } else {
            $display_status = "Absent";
            $status_color = "bg-rose-50 text-rose-600 border-rose-200";
            $metrics['absent']++;
        }
    }

    // Process Profile Image safely
    $imgSource = $row['profile_img'];
    if(empty($imgSource) || $imgSource === 'default_user.png') {
        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($row['full_name'])."&background=0d9488&color=fff&bold=true";
    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
        $imgSource = $root_path . 'assets/profiles/' . $imgSource;
    }

    $row['display_status'] = $display_status;
    $row['status_color'] = $status_color;
    $row['profile_img'] = $imgSource;
    $it_team[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Executive Attendance | IT Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; overflow-x: hidden; }
        
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 16px; padding-top: 80px; }
        }
    </style>
</head>
<body>

    <?php include $sidebarPath; ?>
    <?php include $headerPath; ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-slate-800 tracking-tight">IT Team Attendance</h1>
                <p class="text-slate-500 text-sm mt-1 font-medium">Monitor daily presence, leave, and shift adherence for IT Executives.</p>
            </div>
            
            <form action="" method="GET" class="flex items-center gap-2 bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm w-full md:w-auto">
                <div class="flex items-center pl-3 pr-2 py-1.5 bg-slate-50 rounded-lg border border-slate-100 w-full md:w-auto">
                    <i class="fa-regular fa-calendar text-teal-600 mr-2"></i>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" 
                           class="bg-transparent border-none outline-none text-sm font-bold text-slate-700 w-full cursor-pointer"
                           onchange="this.form.submit()">
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="card p-5 border-b-4 border-b-slate-800 flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 shrink-0"><i class="fa-solid fa-users"></i></div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">Total Staff</p>
                </div>
                <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['total']; ?></h3>
            </div>
            <div class="card p-5 border-b-4 border-b-emerald-500 flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 shrink-0"><i class="fa-solid fa-user-check"></i></div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">Working</p>
                </div>
                <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['present']; ?></h3>
            </div>
            <div class="card p-5 border-b-4 border-b-amber-500 flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 shrink-0"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">Late</p>
                </div>
                <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['late']; ?></h3>
            </div>
            <div class="card p-5 border-b-4 border-b-purple-500 flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center text-purple-500 shrink-0"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">On Leave</p>
                </div>
                <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['on_leave']; ?></h3>
            </div>
            <div class="card p-5 border-b-4 border-b-rose-500 flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center text-rose-500 shrink-0"><i class="fa-solid fa-user-xmark"></i></div>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">Absent</p>
                </div>
                <h3 class="text-2xl font-black text-slate-800"><?php echo $metrics['absent']; ?></h3>
            </div>
        </div>

        <div class="card flex flex-col flex-grow overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0">
                <h3 class="text-lg font-black text-slate-800">IT Executive Roster <span class="text-sm font-medium text-slate-400 ml-2">(<?php echo date('d M Y', strtotime($filter_date)); ?>)</span></h3>
            </div>
            
            <div class="overflow-x-auto custom-scroll">
                <table class="w-full text-left whitespace-nowrap text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Executive</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Shift Assigned</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Punch In</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Punch Out</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Work Hours</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($it_team)): ?>
                            <?php foreach ($it_team as $emp): 
                                $p_in = !empty($emp['punch_in']) ? date('h:i A', strtotime($emp['punch_in'])) : '--:--';
                                $p_out = !empty($emp['punch_out']) ? date('h:i A', strtotime($emp['punch_out'])) : '--:--';
                                
                                $prod_hrs = !empty($emp['production_hours']) ? number_format($emp['production_hours'], 2) . ' Hrs' : '-';
                                $prod_class = (floatval($emp['production_hours']) > 0 && floatval($emp['production_hours']) < 8) ? "text-rose-500 font-bold" : "text-slate-600 font-bold";
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $emp['profile_img']; ?>" class="w-10 h-10 rounded-full border border-slate-200 shadow-sm object-cover bg-white">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($emp['full_name']); ?></p>
                                            <p class="text-[10px] text-slate-500 font-bold tracking-wider mt-0.5 bg-slate-100 px-1.5 py-0.5 rounded w-max border border-slate-200 uppercase">
                                                <?php echo htmlspecialchars($emp['emp_id_code'] ?? 'ID N/A'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-semibold text-teal-700 bg-teal-50 px-2.5 py-1 rounded-md border border-teal-100">
                                        <?php echo htmlspecialchars($emp['shift_timings'] ?? 'General Shift'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-700"><?php echo $p_in; ?></td>
                                <td class="px-6 py-4 font-bold text-slate-700"><?php echo $p_out; ?></td>
                                <td class="px-6 py-4 <?php echo $prod_class; ?>"><?php echo $prod_hrs; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-md border <?php echo $emp['status_color']; ?>">
                                        <?php echo htmlspecialchars($emp['display_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?php echo $root_path; ?>employee_attendance_details.php?id=<?php echo $emp['user_id']; ?>" 
                                       class="inline-flex items-center justify-center text-slate-500 hover:text-teal-600 bg-white hover:bg-teal-50 border border-slate-200 hover:border-teal-200 px-3 py-2 rounded-lg transition-all shadow-sm text-xs font-bold gap-2">
                                        <i class="fa-regular fa-calendar-days"></i> Monthly Log
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-16">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3 border border-slate-100">
                                            <i class="fa-solid fa-user-shield text-2xl text-slate-300"></i>
                                        </div>
                                        <p class="font-bold text-slate-500">No IT Executives Found</p>
                                        <p class="text-xs mt-1">There are currently no users with the role 'IT Executive' in the system.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // Sidebar Layout Integration
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
    </script>
</body>
</html>