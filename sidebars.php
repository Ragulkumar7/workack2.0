<?php
// sidebars.php

// 1. DYNAMIC SESSION DATA
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'Employee';
$first_letter = strtoupper(substr($user_name, 0, 1));
$current_path = basename($_SERVER['PHP_SELF']); 
$current_view = $_GET['view'] ?? ''; 

// --- FIX: DETECT FOLDER LEVEL (To prevent double folder pathing) ---
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$base = ($current_dir == 'manager') ? '../' : ''; 
// ------------------------------------------------------------------

// 2. DEFINE MENU DATA
$sections = [
    [
        'label' => 'Main',
        'items' => [
            // --- 1. DASHBOARD (Role Based) ---
            [
                'name' => 'Dashboard', 
                'path' => $base . 'dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Manager', 'System Admin', 'HR'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'employee/employee_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Employee'] 
            ],

            // --- TEAM CHAT (Common) ---
            [
                'name' => 'Team Chat', 
                'path' => $base . 'team_chat.php', 
                'icon' => 'message-circle', 
                'allowed' => ['Manager', 'System Admin', 'Team Lead', 'Employee']
            ],

            // --- 3. EMPLOYEE DETAILS (Employee Only) ---
            [
                'name' => 'Employee Details',
                'path' => $base . 'employee_deets.php', // Assuming filename
                'icon' => 'user-circle',
                'allowed' => ['Employee']
            ],
            
            // --- 2. ATTENDANCE (Manager View) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Manager', 'System Admin', 'HR'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'attendance.php?view=attendance_admin', 'icon' => 'user-check'],
                    ['name' => 'Attendance (Emp)', 'path' => $base . 'attendance.php?view=attendance_employee', 'icon' => 'user'],
                    ['name' => 'Timesheets', 'path' => $base . 'attendance.php?view=timesheets', 'icon' => 'clock'],
                    ['name' => 'Schedule Timing', 'path' => $base . 'attendance.php?view=schedule_timing', 'icon' => 'calendar-days'],
                    ['name' => 'Shift Swap', 'path' => $base . 'attendance.php?view=shift_swap', 'icon' => 'arrow-left-right'],
                    ['name' => 'Overtime', 'path' => $base . 'attendance.php?view=overtime', 'icon' => 'hourglass'],
                    ['name' => 'WFH Request', 'path' => $base . 'attendance.php?view=wfh', 'icon' => 'home'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_management.php', 'icon' => 'calendar-off']
                ]
            ],

            // --- 2. ATTENDANCE (Employee View - Specific Modules) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Employee'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'emp_attendance.php?view=my_attendance', 'icon' => 'user'],
                    ['name' => 'Leave Request', 'path' => $base . 'leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'wfh_request.php', 'icon' => 'home']
                ]
            ],

            // --- 3. TASK MANAGEMENT (Manager View) ---
            [
                'name' => './Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Manager', 'Team Lead', 'HR', 'System Admin'],
                'subItems' => [
                    ['name' => 'My Tasks', 'path' => $base . '/self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Team Tasks', 'path' => $base . 'manager/manager_task.php?view=team_tasks', 'icon' => 'users'],
                ]
            ],

            // --- 3. TASK MANAGEMENT (Employee View - Specific Modules) ---
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Employee'],
                'subItems' => [
                    ['name' => 'My Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Task Board', 'path' => $base . 'task_tl.php', 'icon' => 'kanban'],
                ]
            ],

            // --- PROJECTS (Common) ---
            [
                'name' => 'Projects', 
                'path' => $base . 'manager/manager_projects.php', 
                'icon' => 'layers', 
                'allowed' => ['Manager', 'System Admin', 'Team Lead', 'Employee']
            ],

            // --- CLIENTS (Manager Only) ---
            [
                'name' => 'Clients', 
                'path' => $base . 'manager/client.php', 
                'icon' => 'users', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Team Lead']
            ],

            // --- PERFORMANCE (Common) ---
            [
                'name' => 'Performance', 
                'path' => $base . 'manager/performance.php', 
                'icon' => 'trending-up', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee'],
                'subItems' => [
                    ['name' => 'Performance Indicator', 'path' => $base . 'manager/performance.php?view=indicator', 'icon' => 'target'],
                    ['name' => 'Performance Review', 'path' => $base . 'manager/performance.php?view=review', 'icon' => 'file-text'],
                ]
            ],

            // --- CLIENTS MODULE ---
            [
                'name' => 'Clients', 
                'path' => $base . 'manager/clients.php', 
                'icon' => 'users', 
                'allowed' => ['Manager', 'System Admin']
            ],

            // --- 4. RESIGNATION (Employee Only) ---
            [
                'name' => 'Resignation', 
                'path' => $base . 'resignation.php', 
                'icon' => 'user-x', 
                'allowed' => ['Employee']
            ],

            // --- 5. TERMINATION (Manager Only) ---
            [
                'name' => 'Termination', 
                'path' => $base . 'manager/termination.php', 
                'icon' => 'user-minus', 
                'allowed' => ['Manager', 'System Admin', 'HR']
            ],

            // --- ANNOUNCEMENT (Common) ---
            [
                'name' => 'Announcement', 
                'path' => $base . 'announcement.php',
                'icon' => 'megaphone', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee']
            ],

            // --- TICKET RAISE (Common) ---
            [
                'name' => 'Ticket Raise', 
                'path' => $base . 'ticketraise.php', 
                'icon' => 'ticket', 
                'allowed' => ['Manager', 'System Admin', 'Employee'],
                'subItems' => [
                    ['name' => 'Ticket Dashboard', 'path' => $base . 'ticketraise.php?view=dashboard', 'icon' => 'layout-dashboard'],
                    ['name' => 'Ticket Details', 'path' => $base . 'ticketraise.php?view=details', 'icon' => 'file-text'],
                    ['name' => 'Ticket Automation', 'path' => $base . 'ticketraise.php?view=automation', 'icon' => 'zap', 'allowed' => ['Manager', 'System Admin']], // Hidden for emp if logic applies
                    ['name' => 'Ticket Report', 'path' => $base . 'ticketraise.php?view=report', 'icon' => 'file-bar-chart'],
                ]
            ],
        ]
    ],
    [
        'label' => 'Support & Tools',
        'items' => [
            // --- REPORTS (Common but maybe different views inside) ---
            [
                'name' => 'Reports', 
                'path' => $base . 'manager/manager_reports.php', 
                'icon' => 'file-bar-chart', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee']
            ],
            [
                'name' => 'Help & Support', 
                'path' => $base . 'support.php', 
                'icon' => 'help-circle', 
                'allowed' => ['Manager', 'System Admin', 'Employee']
            ],
            [
                'name' => 'Settings', 
                'path' => $base . 'settings.php', 
                'icon' => 'settings', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee']
            ],
        ]
    ]
];

// 3. FILTER SECTIONS BY USER ROLE
$activeSections = [];
foreach ($sections as $section) {
    $filteredItems = array_filter($section['items'], function ($item) use ($user_role) {
        return in_array($user_role, $item['allowed']);
    });
    if (!empty($filteredItems)) {
        $section['items'] = $filteredItems;
        $activeSections[] = $section;
    }
}
?>

<script src="https://unpkg.com/lucide@latest"></script>
<style>
    :root {
        --primary-sidebar-width: 95px;
        --secondary-sidebar-width: 220px; 
        --active-bg: #f4f4f5;
        --border-color: #e4e4e7;
        --text-muted: #71717a;
    }
    /* PRIMARY SIDEBAR */
    .sidebar-primary {
        width: var(--primary-sidebar-width); 
        height: 100vh;
        border-right: 1px solid var(--border-color);
        background: #fff; 
        position: fixed; left: 0; top: 0; z-index: 1001;
        overflow-y: auto; overflow-x: hidden; scrollbar-width: none;
        display: flex; flex-direction: column;
    }
    .sidebar-primary::-webkit-scrollbar { display: none; }
    .nav-inner { display: flex; flex-direction: column; align-items: center; padding: 20px 0; flex-grow: 1; width: 100%; }
    .nav-item {
        width: 100%; padding: 12px 0; display: flex; flex-direction: column; align-items: center;
        cursor: pointer; text-decoration: none; color: var(--text-muted); transition: 0.2s; flex-shrink: 0; white-space: nowrap;
    }
    .nav-item:hover, .nav-item.active { color: #16636B; background: #eefcfd; border-right: 3px solid #16636B; }
    .nav-item span { font-size: 10px; margin-top: 5px; font-weight: 500; text-align: center; padding: 0 4px; }

    /* SECONDARY SIDEBAR */
    .sidebar-secondary {
        width: var(--secondary-sidebar-width); height: 100vh; background: #fff;
        border-right: 1px solid var(--border-color); position: fixed;
        left: var(--primary-sidebar-width); top: 0;
        transform: translateX(-105%); transition: transform 0.3s ease; z-index: 1000; overflow-y: auto; scrollbar-width: none;
    }
    .sidebar-secondary.open { transform: translateX(0); }
    #subItemContainer { padding: 30px 15px; display: flex; flex-direction: column; }
    .sub-item {
        display: flex; align-items: center; padding: 10px; text-decoration: none; color: #3f3f46;
        border-radius: 8px; font-size: 13px; margin-bottom: 4px; transition: 0.2s; font-weight: 500;
    }
    .sub-item:hover { background: var(--active-bg); color: #000; }
    .sub-item .sub-icon { margin-right: 10px; width: 16px; height: 16px; color: #71717a; }
    .back-btn {
        display: flex; align-items: center; padding: 10px; margin-bottom: 20px;
        cursor: pointer; color: var(--text-muted); font-size: 13px; font-weight: 600;
        border-radius: 8px; transition: 0.2s; background: #f4f4f5;
    }
    .back-btn:hover { color: #000; background: #e4e4e7; }

    .user-footer { margin-top: auto; padding-bottom: 20px; width: 100%; display: flex; flex-direction: column; align-items: center; border-top: 1px solid var(--border-color); background: #fff; padding-top: 15px; }
    .logout-link { font-size: 11px; color: #ef4444; text-decoration: none; font-weight: 700; margin-top: 8px; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
    .logout-link:hover { opacity: 0.8; }
</style>

<aside class="sidebar-primary">
    <div class="nav-inner">
        <div style="padding-bottom: 20px; flex-shrink: 0;"><img src="<?= $base ?>assets/logo.png" style="height: 40px; width: auto; border-radius: 8px;"></div>
        <?php foreach ($activeSections as $section): ?>
            <?php foreach ($section['items'] as $item): 
                $itemPath = $item['path'] ?? '#';
                $isSubActive = false;
                // Check if a sub-item is currently active based on URL query param
                if (isset($item['subItems'])) {
                    foreach($item['subItems'] as $sub) {
                        // Special check for Task Management sub-items
                        if (strpos($sub['path'], $current_view) !== false && $current_view != '') {
                            $isSubActive = true;
                            break;
                        }
                        // Check for direct file match (like self_task.php)
                        if (basename($sub['path']) == $current_path) {
                            $isSubActive = true;
                            break;
                        }
                    }
                }
                $isActive = ($current_path == basename($itemPath) || $isSubActive);
            ?>
                <a href="javascript:void(0)" class="nav-item <?= $isActive ? 'active' : '' ?>" onclick='handleNavClick(<?= json_encode($item) ?>, this)'>
                    <i data-lucide="<?= $item['icon'] ?>"></i>
                    <span><?= $item['name'] ?></span>
                </a>
            <?php endforeach; ?>
            <div style="width: 40px; height: 1px; background: var(--border-color); margin: 10px 0; flex-shrink: 0;"></div>
        <?php endforeach; ?>
    </div>
    <div class="user-footer">
        <div style="width: 42px; height: 42px; background: #16636B; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px; margin-bottom: 8px;"><?= $first_letter ?></div>
        <div style="font-size: 11px; font-weight: 600; color: #18181b; text-align: center; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($user_name) ?></div>
        <div style="font-size: 9px; color: var(--text-muted); text-align: center;"><?= htmlspecialchars($user_role) ?></div>
        <a href="<?= $base ?>logout.php" class="logout-link"><i data-lucide="log-out" style="width: 12px; height: 12px;"></i> Logout</a>
    </div>
</aside>

<aside class="sidebar-secondary" id="secondaryPanel"><div id="subItemContainer"></div></aside>

<script>
    lucide.createIcons();
    function handleNavClick(item, element) {
        const panel = document.getElementById('secondaryPanel');
        const container = document.getElementById('subItemContainer');
        const main = document.getElementById('mainContent');

        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        if (item.subItems && item.subItems.length > 0) {
            panel.classList.add('open');
            if(main) main.classList.add('main-shifted');
            container.innerHTML = `<div class="back-btn" onclick="closeSubMenu()"><i data-lucide="chevron-left" style="width: 16px; height: 16px; margin-right: 8px;"></i>Back</div><h3 style="font-size:14px; font-weight:700; margin-bottom:15px; padding-left:10px;">${item.name}</h3>`;
            item.subItems.forEach(sub => {
                container.innerHTML += `<a href="${sub.path}" class="sub-item"><i data-lucide="${sub.icon || 'circle'}" class="sub-icon"></i><span style="flex:1">${sub.name}</span><i data-lucide="chevron-right" style="width:12px; height:12px; color:#a1a1aa"></i></a>`;
            });
            lucide.createIcons();
        } else {
            closeSubMenu();
            if(item.path && item.path !== '#') window.location.href = item.path;
        }
    }
    function closeSubMenu() {
        const panel = document.getElementById('secondaryPanel');
        const main = document.getElementById('mainContent');
        if(panel) panel.classList.remove('open');
        if(main) main.classList.remove('main-shifted');
    }
</script>