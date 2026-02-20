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

// --- FIX: UPDATED FOLDER DETECTION ---
$current_dir = strtolower(basename(dirname($_SERVER['PHP_SELF'])));

// Added 'hr_executive' to ensure paths correctly use '../' to go to root.
if (in_array($current_dir, ['manager', 'employee', 'tl', 'accounts', 'itadmin', 'it_executive', 'hr_executive','hr','cfo'])) {
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
                'path' => $base . 'manager/manager_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Manager'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'HR/hr_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['HR'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'TL/tl_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Team Lead'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'employee/employee_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Employee'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'Accounts/Accounts_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Accounts'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'ITadmin/ITadmin_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['IT Admin'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'IT_Executive/ITexecutive_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['IT Executive'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'HR_executive/HR_executive_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['HR Executive'] 
            ],
            [
                'name' => 'Dashboard', 
                'path' => $base . 'CFO/cfo_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['CFO'] 
            ],

            // --- TEAM CHAT (Common) ---
            [
                'name' => 'Team Chat', 
                'path' => $base . 'team_chat.php', 
                'icon' => 'message-circle', 
                'allowed' => ['Manager', 'System Admin', 'Team Lead', 'Employee', 'Accounts', 'IT Admin', 'IT Executive', 'HR Executive', 'CFO', 'HR']
            ],
            
            // --- ATTENDANCE (HR& HR Executive) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['System Admin', 'HR', 'HR Executive'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'Timesheets', 'path' => $base . 'timesheets.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'shift_swap_manager.php', 'icon' => 'arrow-left-right'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'WFH Management', 'path' => $base . 'wfh_management.php', 'icon' => 'home']
                ]
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['HR Executive'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'Timesheets', 'path' => $base . 'timesheets.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'shift_swap_approval_tl.php', 'icon' => 'replce'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                ]
            ],
            //Only manger  attendance management and timesheet
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Manager'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'Timesheets', 'path' => $base . 'manager/timesheets_manager.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'shift_swap_manager.php', 'icon' => 'arrow-left-right'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'WFH Management', 'path' => $base . 'wfh_management.php', 'icon' => 'home']
                ]
            ],

            // --- ATTENDANCE (TL) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Team Lead'],
                'subItems' => [
                    ['name' => 'My Attendance', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Team Attendance', 'path' => $base . 'TL/attendance_tl.php', 'icon' => 'users'],
                    ['name' => 'Shift Swap', 'path' => $base . 'shift_swap_approval_tl.php', 'icon' => 'replce'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'WFH Management', 'path' => $base . 'wfh_management.php', 'icon' => 'home']
                ]
            ],

            // --- ATTENDANCE (General) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Employee', 'IT Admin', 'IT Executive', 'Accounts'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Shift Swap', 'path' => $base . 'shift_swap_request.php', 'icon' => 'arrow-left-right'],
                ]
            ],

            // --- TASK MANAGEMENT ---
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Manager', 'System Admin'],
                'subItems' => [
                    ['name' => 'Self Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Team Tasks', 'path' => $base . 'manager_task.php?view=team_tasks', 'icon' => 'users'],
                    ['name' => 'Projects', 'path' => $base . 'manager/manager_projects.php', 'icon' => 'layers'],
                ]
            ],

             [
                'name' => 'Manage Employees', 
                'path' => $base . 'Manager/manager_employee.php', 
                'icon' => 'users', 
                'allowed' => ['Manager']
            ],
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Team Lead'],
                'subItems' => [
                    ['name' => 'Self Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Team Tasks', 'path' => $base . 'TL/task_tl.php', 'icon' => 'users'],
                ]
            ],
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Employee'],
                'subItems' => [
                    ['name' => 'Task Board', 'path' => $base . 'employee/task_tl.php', 'icon' => 'kanban'],
                    ['name' => 'Self Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'],
                    ['name' => 'Efficiency', 'path' => $base . 'employee/emp_efficiency.php', 'icon' => 'gauge']
                ]
            ],

            // --- EMPLOYEE MANAGEMENT ---
            [
                'name' => 'Employee', 
                'path' => $base . 'employee_management.php', 
                'icon' => 'users', 
                'allowed' => ['Manager', 'System Admin', 'HR']
            ],
            [
                'name' => 'Employee', 
                'path' => $base . 'TL/team_member.php', 
                'icon' => 'users', 
                'allowed' => ['Team Lead']
            ],
           // salary hike for HR only
            [
                'name' => 'Salary Hike', 
                'path' => $base . 'HR/hr_salaryhikes.php', 
                'icon' => 'banknote', 
                'allowed' => ['HR'] // Only HR can see Salary Hike option
            ],

            // --- PROJECTS & CLIENTS ---
            [
                'name' => 'Projects', 
                'path' => $base . 'manager/manager_projects.php', 
                'icon' => 'layers', 
                'allowed' => ['Manager', 'System Admin']
            ],
            [
                'name' => 'Clients', 
                'path' => $base . 'manager/client.php', 
                'icon' => 'users', 
                'allowed' => ['Manager', 'System Admin', 'HR']
            ],

            // --- PERFORMANCE & PRODUCTIVITY ---
            [
                'name' => 'Performance', 
                'path' => $base . 'performance_list.php', 
                'icon' => 'trending-up', 
                'allowed' => ['System Admin', 'HR', 'Team Lead'],
            ],
            [
                'name' => 'Performance', 
                'path' => $base . 'manager/team_performance.php', 
                'icon' => 'trending-up', 
                'allowed' => ['Manager'],
            ],
            [
                'name' => 'Productivity', 
                'path' => $base . 'manager/productivity_monitor.php', 
                'icon' => 'activity', 
                'allowed' => ['Manager'] 
            ],
            
            // --- HR EXECUTIVE PAGES ---
            [
                'name' => 'Recruitment', 
                'path' => $base . 'HR_executive/jobs.php', 
                'icon' => 'briefcase', 
                'allowed' => ['HR', 'HR Executive']
            ],
            [
                'name' => 'Onboarding', 
                'path' => $base . 'HR_executive/employee_onboarding.php', 
                'icon' => 'user-plus', 
                'allowed' => ['HR Executive']
            ],
        
            [
                'name' => 'ATS Screener', 
                'path' => $base . 'HR_executive/ats.php', 
                'icon' => 'file-search',
                'allowed' => ['HR Executive']
            ], 
            // employee requirements for manager only 
            [
                'name' => 'Employee <br> Requirements', 
                'path' => $base . 'manager/employee_requirements.php', 
                'icon' => 'user-plus',
                'allowed' => ['Manager']
            ],  
            [
            'name' => 'My Team', 
            'path' => $base . 'manager/my_team.php', 
            'icon' => 'users', // Uses lucide/font-awesome icon
            'allowed' => ['Manager', 'System Admin'] 
        ],
             // --- ANNOUNCEMENT ---
            [
                'name' => 'Announcement', 
                'path' => $base . 'announcement.php',
                'icon' => 'megaphone', 
                'allowed' => ['Manager', 'System Admin', 'HR Executive', 'HR']
            ],
            [
                'name' => 'Announcement', 
                'path' => $base . 'view_announcements.php',
                'icon' => 'megaphone', 
                'allowed' => ['Accounts', 'Employee', 'Team Lead', 'IT Admin', 'IT Executive', 'CFO']
            ],

            // --- TICKETS ---
            [
                'name' => 'Manage Tickets', 
                'path' => $base . 'ITadmin/manage_tickets.php', 
                'icon' => 'clipboard-list', 
                'allowed' => ['IT Admin']
            ],
            [
                'name' => 'Ticket Actions', 
                'path' => $base . 'IT_Executive/it_exec_ticket_action.php', 
                'icon' => 'file-check-2', 
                'allowed' => ['IT Executive']
            ],
        ]
    ],
    // --- PAYROLL & ACCOUNTS SECTION FIXED ---
    [
        'label' => 'Finance & Payroll',
        'items' => [
            [
                'name' => 'Payslip <br>Management', 
                'icon' => 'file-text', 
                'allowed' => ['Accounts'],
                'subItems' => [
                    ['name' => 'Generate Payslip', 'path' => $base . 'Accounts/payslip_management.php?view=generate', 'icon' => 'plus-circle'],
                    ['name' => 'Pending Approvals', 'path' => $base . 'Accounts/payslip_management.php?view=approvals', 'icon' => 'clock'],
                    ['name' => 'All Payslips', 'path' => $base . 'Accounts/payslip_management.php?view=history', 'icon' => 'files'],
                ]
            ],
        ]
    ],
    [
    'label' => 'Payslip Request', // FIX: Added separate section for Employee Payslip Request
    'items' => [
        [
            'name' => 'Request Payslip', 
            'path' => $base . 'payslip_request.php', 
            'icon' => 'file-text', 
            'allowed' => [
                'System Admin', 
                'HR', 
                'Manager', 
                'Team Lead', 
                'Employee', 
                'IT Admin', 
                'IT Executive', 
                'HR Executive',
                'CFO'
            ]
        ],
        
    ]
],
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
        ]
    ],
    [
        'label' => 'Salary Management',
        'items' => [
    [
    'name' => 'Salary Revisions', 
    'path' => $base . 'Accounts/salary_revisions.php', 
    'icon' => 'trending-up', // அல்லது 'history'
    'allowed' => ['Accounts', 'HR', 'CFO']
]
        ]
    ],  
    [
        'label' => 'CFO Management',
        'items' => [
            [
                'name' => 'Approvals',
                'path' => $base . 'CFO/cfo_approvals.php',
                'icon' => 'check-square', // Icon for Approvals
                'allowed' => ['CFO', 'System Admin']
            ],
            [
                'name' => 'Financials',
                'path' => $base . 'CFO/cfo_financials.php',
                'icon' => 'trending-up', // Icon for Financials
                'allowed' => ['CFO', 'System Admin']
            ],
            [
                'name' => 'Payroll Review',
                'path' => $base . 'CFO/cfo_payroll.php',
                'icon' => 'banknote', // Icon for Payroll
                'allowed' => ['CFO', 'System Admin']
            ],
        ]
    ],
    //ticket raise
    [
        'label' => 'Ticket Raise',
        'items' => [
            [
                'name' => 'Raise Ticket', 
                'path' => $base . 'ticketraise_form.php', 
                'icon' => 'plus-circle', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee', 'Team Lead', 'Accounts', 'HR Executive', 'CFO']
            ],
        ]
    ],
    // --- FIX: Corrected Reports Section Syntax ---
    [
        'label' => 'Reports',
        'items' => [
            [
                'name' => 'Reports', 
                'path' => $base . 'Accounts/accounts_reports.php', 
                'icon' => 'pie-chart', 
                'allowed' => ['Accounts', 'CFO']
            ]
        ]
    ],
    //Au
     [
     'label' => 'Auditor Report',
        'items' => [
    [
    'name' => 'Auditor Report', 
    'path' => $base . 'CFO/cfo_auditor_report.php', 
    'icon' => 'file-check-2', // அல்லது 'clipboard-check'
    'allowed' => ['CFO']
   ]
        ]
     ],

    [
        'label' => 'Support & Tools',
        'items' => [
            [
                'name' => 'Help & Support', 
                'path' => $base . 'help_support.php', 
                'icon' => 'help-circle', 
                'allowed' => ['Manager', 'System Admin', 'Employee', 'Team Lead', 'IT Admin', 'IT Executive', 'Accounts', 'HR Executive', 'CFO']
            ],
            [
                'name' => 'Settings', 
                'path' => $base . 'settings.php', 
                'icon' => 'settings', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee', 'Team Lead', 'IT Admin', 'IT Executive', 'Accounts', 'HR Executive', 'CFO']
            ],
        ]
    ]
];

// 3. FILTER SECTIONS BY USER ROLE
$activeSections = [];
foreach ($sections as $section) {
    // FIX: Added isset check to prevent crashes on line 314
    $filteredItems = array_filter($section['items'], function ($item) use ($user_role) {
        return isset($item['allowed']) && in_array($user_role, $item['allowed']);
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
                if (isset($item['subItems'])) {
                    foreach($item['subItems'] as $sub) {
                        if (strpos($sub['path'], $current_view) !== false && $current_view != '') {
                            $isSubActive = true;
                            break;
                        }
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