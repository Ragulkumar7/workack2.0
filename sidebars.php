<?php
// sidebars.php

// 1. DYNAMIC SESSION DATA
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['username'] ?? 'User';
$user_role = isset($_SESSION['role']) ? trim($_SESSION['role']) : 'Employee'; 
$first_letter = strtoupper(substr($user_name, 0, 1));
$current_path = basename($_SERVER['PHP_SELF']); 
$current_view = $_GET['view'] ?? ''; 

// --- DETECT FOLDER LEVEL ---
$current_dir = strtolower(basename(dirname($_SERVER['PHP_SELF'])));

// Added 'accounts' to the array so paths work correctly from inside that folder
if (in_array($current_dir, ['manager', 'employee', 'tl', 'accounts'])) {
    $base = '../';
} else {
    $base = '';
}

// 2. DEFINE MENU DATA
$sections = [
    [
        'label' => 'Main',
        'items' => [
            // --- DASHBOARDS ---
            [
                'name' => 'Dashboard', 
                'path' => $base . 'dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Manager', 'System Admin', 'HR'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'TL/tl_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Team Lead', 'Team Leader'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'employee/employee_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Employee'] 
            ],
            // NEW: Accounts Dashboard
            [
                'name' => 'Dashboard', 
                'path' => $base . 'Accounts/Accounts_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Accounts'] 
            ],

            // --- TEAM CHAT (Common) ---
            [
                'name' => 'Team Chat', 
                'path' => $base . 'team_chat.php', 
                'icon' => 'message-circle', 
                'allowed' => ['Manager', 'System Admin', 'Team Lead', 'Team Leader', 'Employee', 'Accounts']
            ],

            // --- EMPLOYEE DETAILS ---
            [
                'name' => 'Employee Details',
                'path' => $base . 'employee/employee_details.php', 
                'icon' => 'user-circle', 
                'allowed' => ['Employee']
            ],
            
            // --- ATTENDANCE (Manager) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Manager', 'System Admin', 'HR'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'Attendance (Emp)', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Timesheets', 'path' => $base . 'timesheets.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'shift_swap_manager.php', 'icon' => 'arrow-left-right'],
                    ['name' => 'Overtime', 'path' => $base . 'overtime_management.php', 'icon' => 'hourglass'],
                    ['name' => 'WFH Request', 'path' => $base . 'wfh_request.php', 'icon' => 'home'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_management.php', 'icon' => 'calendar-off']
                ]
            ],

            // --- ATTENDANCE (TL) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Team Lead', 'Team Leader'],
                'subItems' => [
                    ['name' => 'My Attendance', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Team Attendance', 'path' => $base . 'TL/attendance_tl.php', 'icon' => 'users'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home']
                ]
            ],

            // --- ATTENDANCE (Employee) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Employee'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home']
                ]
            ],

            // --- TASK MANAGEMENT ---
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Manager', 'Team Lead', 'Team Leader', 'HR', 'System Admin'],
                'subItems' => [
                    ['name' => 'My Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Team Tasks', 'path' => $base . 'manager_task.php?view=team_tasks', 'icon' => 'users'],
                ]
            ],
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Employee'],
                'subItems' => [
                    ['name' => 'My Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Task Board', 'path' => $base . 'employee/task_tl.php', 'icon' => 'kanban'],
                ]
            ],

            // --- EMPLOYEE MGMT ---
            [
                'name' => 'Employee', 
                'path' => $base . 'employee_management.php', 
                'icon' => 'users', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Team Lead', 'Team Leader']
            ],

            // --- PROJECTS ---
            [
                'name' => 'Projects', 
                'path' => $base . 'manager/manager_projects.php', 
                'icon' => 'layers', 
                'allowed' => ['Manager', 'System Admin', 'Team Lead', 'Team Leader']
            ],

            // --- CLIENTS ---
            [
                'name' => 'Clients', 
                'path' => $base . 'manager/client.php', 
                'icon' => 'users', 
                'allowed' => ['Manager', 'System Admin', 'HR']
            ],

            // --- PERFORMANCE ---
            [
                'name' => 'Performance', 
                'path' => $base . 'performance_list.php', 
                'icon' => 'trending-up', 
                'allowed' => ['Manager', 'System Admin', 'HR'],
            ],

            // --- RESIGNATION ---
            [
                'name' => 'Resignation', 
                'path' => $base . 'employee/resignation.php', 
                'icon' => 'user-x', 
                'allowed' => ['Employee', 'Team Lead', 'Team Leader']
            ],

            // --- TERMINATION ---
            [
                'name' => 'Termination', 
                'path' => $base . 'manager/termination.php', 
                'icon' => 'user-minus', 
                'allowed' => ['Manager', 'System Admin', 'HR']
            ],

            // --- ANNOUNCEMENT (Common + Accounts) ---
            // --- ANNOUNCEMENT (Manager, HR, Admin, Accounts - Full Access) ---
            [
                'name' => 'Announcement', 
                'path' => $base . 'announcement.php',
                'icon' => 'megaphone', 
                'allowed' => ['Manager', 'System Admin']
            ],

            // --- ANNOUNCEMENT (Employee & TL - View Only) ---
            [
                'name' => 'Announcement', 
                'path' => $base . 'view_announcements.php', // Employee & TL-க்கு மட்டும் இந்த பக்கம் வரும்
                'icon' => 'megaphone', 
                'allowed' => ['HR', 'Accounts','Employee', 'Team Lead', 'Team Leader']
            ],
            // --- TICKET RAISE (Common + Accounts) ---
            [
                'name' => 'Ticket Raise', 
                'path' => $base . 'ticketraise.php', 
                'icon' => 'ticket', 
                'allowed' => ['Manager', 'System Admin', 'Employee', 'Team Lead', 'Team Leader', 'Accounts'],
                'subItems' => [
                    ['name' => 'Ticket Dashboard', 'path' => $base . 'ticketraise.php?view=dashboard', 'icon' => 'layout-dashboard'],
                    ['name' => 'Ticket Details', 'path' => $base . 'ticketraise.php?view=details', 'icon' => 'file-text'],
                    ['name' => 'Ticket Automation', 'path' => $base . 'ticketraise.php?view=automation', 'icon' => 'zap', 'allowed' => ['Manager', 'System Admin']], 
                    ['name' => 'Ticket Report', 'path' => $base . 'ticketraise.php?view=report', 'icon' => 'file-bar-chart'],
                ]
            ],
        ]
    ],

    // --- FINANCE (HR ONLY) ---
    [
        'label' => 'Finance',
        'items' => [
            [
                'name' => 'Salary Hike', 
                'path' => $base . 'payroll_salary.php', 
                'icon' => 'banknote', 
                'allowed' => ['HR'] 
            ],
        ]
    ],

    // --- ACCOUNTS (NEW SECTION) ---
    [
        'label' => 'Accounts',
        'items' => [
            [
                'name' => 'Invoices', 
                'path' => $base . 'Accounts/new_invoice.php', 
                'icon' => 'file-text', 
                'allowed' => ['Accounts'] 
            ],
            [
                'name' => 'Purchase Orders', 
                'path' => $base . 'Accounts/purchase_order.php', 
                'icon' => 'shopping-cart', 
                'allowed' => ['Accounts'] 
            ],
            [
                'name' => 'General Ledger', 
                'path' => $base . 'Accounts/ledger.php', 
                'icon' => 'book', 
                'allowed' => ['Accounts'] 
            ],
            [
                'name' => 'Masters', 
                'path' => $base . 'Accounts/masters.php', 
                'icon' => 'landmark', 
                'allowed' => ['Accounts'] 
            ],
            [
                'name' => 'Reports', 
                'path' => $base . 'Accounts/accounts_reports.php', 
                'icon' => 'pie-chart', 
                'allowed' => ['Accounts'] 
            ],
            [
                'name' => 'Payslip', 
                'path' => $base . 'Accounts/payslip.php', 
                'icon' => 'banknote', 
                'allowed' => ['Accounts'] 
            ],
        ]
    ],

    [
        'label' => 'Support & Tools',
        'items' => [
            // --- REPORTS ---
            [
                'name' => 'Reports', 
                'path' => $base . 'manager/manager_reports.php', 
                'icon' => 'file-bar-chart', 
                'allowed' => ['Manager', 'System Admin', 'HR']
            ],
            // --- HELP & SUPPORT (Common + Accounts) ---
            [
                'name' => 'Help & Support', 
                'path' => $base . 'help_support.php', 
                'icon' => 'help-circle', 
                'allowed' => ['Manager', 'System Admin', 'Employee', 'Team Lead', 'Team Leader', 'Accounts']
            ],
            // --- SETTINGS (Common + Accounts) ---
            [
                'name' => 'Settings', 
                'path' => $base . 'settings.php', 
                'icon' => 'settings', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee', 'Team Lead', 'Team Leader', 'Accounts']
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
                        // Check for direct file match
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