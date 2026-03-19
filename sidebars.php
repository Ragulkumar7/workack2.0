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

if (in_array($current_dir, ['manager', 'employee', 'tl', 'accounts', 'itadmin', 'it_executive', 'hr_executive','hr','cfo','sales_manager','sales_executive','ceo'])) {
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
                'path' => $base . 'ceo/ceo_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['CEO'] 
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
                'path' => $base . 'IT_Executive/ItExecutive_dashboard.php', 
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
            // --- SALES DASHBOARD ---
            [
                'name' => 'Dashboard', 
                'path' => $base . 'sales_manager/sales_dashboard.php', 
                'icon' => 'layout-dashboard',
                'allowed' => ['Sales Manager']
            ],
             [
                'name' => 'Dashboard', 
                'path' => $base . 'sales_executive/sales_executive_dashboard.php', 
                'icon' => 'layout-dashboard', 
                'allowed' => ['Sales Executive']
            ],

            // --- TEAM CHAT (Common) ---
           [
               'name' => 'Team Chat', 
                'path' => $base . 'Teamchat/index.php', 
               'icon' => 'message-circle', 
                'allowed' => ['Manager', 'System Admin', 'Team Lead', 'Employee', 'Accounts', 'IT Admin', 'IT Executive', 'HR Executive', 'CFO', 'HR', 'Sales Manager', 'Sales Executive', 'CEO']
            ],
            
            // --- ATTENDANCE (HR& HR Executive) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['System Admin', 'HR'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'My Attendance', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Timesheets', 'path' => $base . 'timesheets.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'HR/shift_swap_approval_hr.php', 'icon' => 'arrow-left-right'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'WFH Management', 'path' => $base . 'wfh_management.php', 'icon' => 'home']
                ]
            ],
             [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['CEO'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'Timesheets', 'path' => $base . 'timesheets.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'HR/shift_swap_approval_hr.php', 'icon' => 'arrow-left-right'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'WFH Management', 'path' => $base . 'wfh_management.php', 'icon' => 'home']
                ]
            ],
            // HR Executive attendance
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['HR Executive'],
                'subItems' => [
                    ['name' => 'Attendance (Admin)', 'path' => $base . 'admin_attendance.php', 'icon' => 'user-check'],
                    ['name' => 'My Attendance', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Timesheets', 'path' => $base . 'timesheets.php', 'icon' => 'clock'],
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
                    ['name' => 'My Attendance', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Timesheets', 'path' => $base . 'manager/timesheets_manager.php', 'icon' => 'clock'],
                    ['name' => 'Shift Swap', 'path' => $base . 'manager/shift_swap_approval_manager.php', 'icon' => 'replace'],
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
                    ['name' => 'Shift Swap', 'path' => $base . 'TL/shift_swap_approval_tl.php', 'icon' => 'replace'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'WFH Management', 'path' => $base . 'wfh_management.php', 'icon' => 'home']
                ]
            ],
            
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Sales Manager'],
                'subItems' => [
                    ['name' => 'My Attendance', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Team Attendance', 'path' => $base . 'TL/attendance_tl.php', 'icon' => 'users'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                ]
            ],

            // --- ATTENDANCE (General) ---
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Employee', 'Accounts'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Shift Swap', 'path' => $base . 'employee/shift_swap_request.php', 'icon' => 'arrow-left-right'],
                ]
            ],
             [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['Sales Executive'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                   
                ]
            ],
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => [ 'CFO'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Team Attendance', 'path' => $base . 'TL/attendance_tl.php', 'icon' => 'users'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'WFH Request', 'path' => $base . 'employee/work_from_home.php', 'icon' => 'home'],
                    ['name' => 'Shift Swap', 'path' => $base . 'employee/shift_swap_request.php', 'icon' => 'arrow-left-right'],
                ]
            ],
             [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['IT Executive'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'Shift Swap', 'path' => $base . 'employee/shift_swap_request.php', 'icon' => 'arrow-left-right'],
                ]
            ],
            [
                'name' => 'Attendance', 
                'icon' => 'calendar-check', 
                'allowed' => ['IT Admin'],
                'subItems' => [
                    ['name' => 'Attendance Info', 'path' => $base . 'employee_attendance_details.php', 'icon' => 'user'],
                    ['name' => 'Team Attendance', 'path' => $base . 'ITadmin/it_team_attendance.php', 'icon' => 'users'],
                    ['name' => 'Leave Request', 'path' => $base . 'employee/leave_request.php', 'icon' => 'calendar-plus'],
                    ['name' => 'Shift Swap', 'path' => $base . 'employee/shift_swap_request.php', 'icon' => 'arrow-left-right'],
                    ['name' => 'Leave Management', 'path' => $base . 'leave_approval.php', 'icon' => 'calendar-off'],
                    ['name' => 'Shift Swap Management', 'path' => $base . 'TL/shift_swap_approval_tl.php', 'icon' => 'replace'],

                ]
            ],
            // --- EXTERNAL ATTENDANCE (IT ADMIN) ---
            [
                'name' => 'External<br>Attendance', 
                'path' => $base . 'ITadmin/external_attendance.php',
                'icon' => 'calendar-clock', 
                'allowed' => ['IT Admin']
            ],
            // --- ASSIGN TASK (SALES MANAGER) ---
            [
                'name' => 'Assign Tasks', 
                'path' => $base . 'sales_manager/sales_assigntask.php', 
                'icon' => 'clipboard-plus',
                'allowed' => ['Sales Manager']
            ],
            // --- INVOICE DISPATCH (SALES MANAGER) ---
            [
                'name' => 'Invoice Dispatch', 
                'path' => $base . 'sales_manager/invoice_dispatch.php', 
                'icon' => 'truck',
                'allowed' => ['Sales Manager']
            ],
            // --- CLIENT MANAGEMENT (SALES MANAGER) ---
            [
                'name' => 'Client Management', 
                'path' => $base . 'sales_manager/client_management.php', 
                'icon' => 'users-round',
                'allowed' => ['Sales Manager', 'Sales Executive']
            ],
            // --- EXPENSES APPROVAL (SALES MANAGER) ---
            [
                'name' => 'Expense Approvals', 
                'path' => $base . 'sales_manager/sales_expenses_approval.php', 
                'icon' => 'check-square',
                'allowed' => ['Sales Manager']
            ],
            // --- MY TASKS (SALES EXECUTIVE) ---
            [
                'name' => 'My Tasks', 
                'path' => $base . 'sales_executive/my_tasks.php', 
                'icon' => 'clipboard-list',
                'allowed' => ['Sales Executive']
            ],
            // --- SALES EXPENSES (SALES EXECUTIVE) ---
            [
                'name' => 'My Expenses', 
                'path' => $base . 'sales_executive/sales_expenses.php', 
                'icon' => 'receipt',
                'allowed' => ['Sales Executive']
            ],
            // --- INVOICE INBOX (SALES EXECUTIVE ROLE) ---
            [
                'name' => 'Invoice Inbox', 
                'path' => $base . 'sales_executive/invoice_inbox.php', 
                'icon' => 'inbox',
                'allowed' => ['Sales Executive']
            ],
            // --- TASK MANAGEMENT ---
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Manager', 'System Admin'],
                'subItems' => [
                    ['name' => 'Self Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                    ['name' => 'Team Projects', 'path' => $base . 'manager/manager_projects.php', 'icon' => 'layers'],
                ]
            ],

             [
                'name' => 'Manage Employees', 
                'path' => $base . 'manager/manager_employee.php', 
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
                'allowed' => ['System Admin', 'HR']
            ],
            [
                'name' => 'Employee', 
                'path' => $base . 'TL/team_member.php', 
                'icon' => 'users', 
                'allowed' => ['Team Lead']
            ],
            [
                'name' => 'Clients', 
                'path' => $base . 'manager/client.php', 
                'icon' => 'users', 
                'allowed' => ['Manager']
            ],

            // --- PERFORMANCE & PRODUCTIVITY ---
            [
                'name' => 'Performance', 
                'path' => $base . 'performance_list.php', 
                'icon' => 'trending-up', 
                'allowed' => ['Team Lead'],
            ],
            [
                'name' => 'Performance', 
                'path' => $base . 'HR/all_performance.php', 
                'icon' => 'trending-up', 
                'allowed' => ['HR', 'CEO'],
            ],
            [
                'name' => 'Performance', 
                'path' => $base . 'manager/team_performance.php', 
                'icon' => 'trending-up', 
                'allowed' => ['Manager'],
            ],
            
            // --- HR EXECUTIVE PAGES ---
            [
                'name' => 'Recruitment', 
                'path' => $base . 'HR_executive/jobs.php', 
                'icon' => 'briefcase', 
                'allowed' => ['HR', 'HR Executive', 'CEO']
            ],
            [
                'name' => 'Onboarding', 
                'path' => $base . 'HR_executive/employee_onboarding.php', 
                'icon' => 'user-plus', 
                'allowed' => ['HR Executive', 'HR', 'CEO']
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
            'icon' => 'users',
            'allowed' => ['Manager', 'System Admin'] 
          ],
            

             // NEW: STOCK MAINTENANCE BUTTON
            [
                'name' => 'Stock Maintenance', 
                'path' => $base . 'IT_Executive/stock_maintenance.php',
                'icon' => 'package',
                'allowed' => ['IT Admin', 'IT Executive',"CFO"]
            ],
            // --- TICKETS MANAGEMENT ---
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
    
    [
    'label' => 'Payslip Request', 
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
                'CFO',
                'Sales Manager',
                'Sales Executive'
            ]
        ],
        
    ]
],

    [
        'label' => 'Accounts',
        'items' => [
            // TASK MANAGEMENT ADDED TO THE TOP
            [
                'name' => 'Task Management', 
                'icon' => 'clipboard-check', 
                'allowed' => ['Accounts'],
                'subItems' => [
                    ['name' => 'Self Tasks', 'path' => $base . 'self_task.php', 'icon' => 'check-square'], 
                ]
            ],
            [
                'name' => 'Employee Salary', 
                'path' => $base . './Accounts/employee_salary.php', 
                'icon' => 'banknote',
                'allowed' => ['Accounts', 'HR', 'CEO']
            ],
            [
                'name' => 'Payslip <br>Management', 
                'path' => $base . 'Accounts/payslip_management.php', 
                'icon' => 'file-text', 
                'allowed' => ['Accounts', 'CEO'] 
            ],
            [
                'name' => 'Invoices', 
                'path' => $base . 'Accounts/new_invoice.php', 
                'icon' => 'file-text', 
                'allowed' => ['Accounts', 'CEO'] 
            ],
            [
                'name' => 'Purchase Orders', 
                'path' => $base . 'Accounts/purchase_order.php', 
                'icon' => 'shopping-cart', 
                'allowed' => ['Accounts', 'CEO'] 
            ],
            // --- GENERAL LEDGER (ACCOUNTS ROLE) ---
            [
                'name' => 'General Ledger', 
                'path' => $base . 'Accounts/ledger.php', 
                'icon' => 'book-open',
                'allowed' => ['Accounts', 'CFO', 'CEO']
            ],
            // --- EXPENSES CLAIMS (ACCOUNTS ROLE) ---
            [
                'name' => 'Expenses Claims', 
                'path' => $base . 'Accounts/expenses_claims.php', 
                'icon' => 'wallet',
                'allowed' => ['Accounts', 'CEO']
            ],
            [
                'name' => 'Masters', 
                'path' => $base . 'Accounts/masters.php', 
                'icon' => 'database', 
                'allowed' => ['Accounts', 'CFO', 'CEO']
            ],
            [
                'name' => 'Reports', 
                'path' => $base . 'Accounts/accounts_reports.php', 
                'icon' => 'pie-chart', 
                'allowed' => ['Accounts']
            ]
        ]
    ],
    [
        'label' => 'CFO Management',
        'items' => [
            [
                'name' => 'Approvals',
                'path' => $base . 'CFO/cfo_approvals.php',
                'icon' => 'check-square',
                'allowed' => ['CFO', 'System Admin', 'CEO']
            ],
            [
                'name' => 'Financials',
                'path' => $base . 'CFO/cfo_financials.php',
                'icon' => 'trending-up',
                'allowed' => ['CFO', 'System Admin', 'CEO']
            ],
            [
                'name' => 'Payroll Review',
                'path' => $base . 'CFO/cfo_payroll.php',
                'icon' => 'banknote',
                'allowed' => ['CFO', 'System Admin', 'CEO']
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
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee', 'Team Lead', 'Accounts', 'HR Executive', 'CFO', 'Sales Manager', 'Sales Executive']
            ],
        ]
    ], 

    [
        'label' => 'Support & Tools',
        'items' => [
             // --- ANNOUNCEMENT ---
            [
                'name' => 'Announcement', 
                'path' => $base . 'announcement.php',
                'icon' => 'megaphone', 
                'allowed' => ['Manager', 'System Admin', 'HR Executive', 'HR', 'CEO']
            ],
            [
                'name' => 'Announcement', 
                'path' => $base . 'view_announcements.php',
                'icon' => 'megaphone', 
                'allowed' => ['Accounts', 'Employee', 'Team Lead', 'IT Admin', 'IT Executive', 'CFO','Sales Manager','Sales Executive']
            ],
            [
                'name' => 'Help & Support', 
                'path' => $base . 'help_support.php', 
                'icon' => 'help-circle', 
                'allowed' => ['Manager', 'System Admin', 'Employee', 'Team Lead', 'IT Admin', 'IT Executive', 'Accounts', 'HR Executive', 'CFO','Sales Manager','Sales Executive', 'CEO']
            ],
            [
                'name' => 'Settings', 
                'path' => $base . 'settings.php', 
                'icon' => 'settings', 
                'allowed' => ['Manager', 'System Admin', 'HR', 'Employee', 'Team Lead', 'IT Admin', 'IT Executive', 'Accounts', 'HR Executive', 'CFO','Sales Manager','Sales Executive', 'CEO']
            ],
        ]
    ]
];

// 3. FILTER SECTIONS BY USER ROLE
$activeSections = [];
foreach ($sections as $section) {
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
    /* Hide the mobile close button on Desktop by default */
    .mobile-close-container {
        display: none;
        width: 100%; 
        text-align: left; /* Changed to left to match your screenshot */
        padding: 15px 0 0 15px; 
        margin-bottom: 5px;
    }
    /* 📱 NEW: Mobile Overlay */
    .sidebar-overlay {
        display: none; 
        position: fixed; inset: 0; background: rgba(0,0,0,0.5); 
        z-index: 1000; backdrop-filter: blur(2px);
    }
    .sidebar-overlay.active { display: block; }

    /* 📱 NEW: Mobile Hamburger Toggle */
    /* 📱 NEW: Mobile Hamburger Toggle */
    .mobile-menu-btn {
        display: none; position: fixed; top: 15px; left: 15px; z-index: 999; /* Changed to 999 so sidebar covers it */
        background: #16636B; color: #fff; border: none; border-radius: 8px;
        padding: 10px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        align-items: center; justify-content: center;
    }

    .sidebar-primary {
        width: var(--primary-sidebar-width); 
        height: 100vh;
        border-right: 1px solid var(--border-color);
        background: #fff; 
        position: fixed; left: 0; top: 0; z-index: 1001;
        overflow-y: auto; overflow-x: hidden; scrollbar-width: none;
        display: flex; flex-direction: column;
        transition: transform 0.3s ease; /* For smooth mobile sliding */
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

    /* 📱 ----------------------------------------- */
    /* 📱 RESPONSIVE MEDIA QUERIES (TABLET & MOBILE)*/
    /* 📱 ----------------------------------------- */
    @media (max-width: 991px) {
        .mobile-menu-btn { 
            display: flex; /* Show the hamburger button */
        }
        .mobile-close-container {
            display: block; /* Show the red arrow ONLY on mobile */
        }
        
        .sidebar-primary {
            transform: translateX(-100%); /* Hide off-screen by default */
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        .sidebar-primary.mobile-open {
            transform: translateX(0); /* Slide in when open */
        }

        .sidebar-secondary {
            left: 0; /* Attach to left edge on mobile */
            width: 100%; max-width: 280px; /* Take up more screen space on small devices */
            z-index: 1002; /* Float entirely above the primary menu when opened */
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
        }
    }
</style>

<button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Toggle Menu">
    <i data-lucide="menu"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileMenu()"></div>

<aside class="sidebar-primary" id="primarySidebar">
    <div class="nav-inner">
        <div class="mobile-close-container">
             <i data-lucide="arrow-left" style="cursor: pointer; color: #ef4444; width: 24px; height: 24px;" onclick="toggleMobileMenu()"></i>
        </div>
        
        <div style="padding-bottom: 20px; flex-shrink: 0;">
            <img src="<?= $base ?>assets/logo.png" style="height: 40px; width: auto; border-radius: 8px;">
        </div>
        
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
    
    // 1. Mobile Toggle Function
    function toggleMobileMenu() {
        const sidebar = document.getElementById('primarySidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
        
        if(!sidebar.classList.contains('mobile-open')) {
            closeSubMenu();
        }
    }

    // 2. Navigation Handler (Updated to support active states)
    function handleNavClick(item, element) {
        const panel = document.getElementById('secondaryPanel');
        const container = document.getElementById('subItemContainer');
        const main = document.getElementById('mainContent');

        // Reset active states for primary icons
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        if (item.subItems && item.subItems.length > 0) {
            panel.classList.add('open');
            if(main) main.classList.add('main-shifted');
            
            container.innerHTML = `<div class="back-btn" onclick="closeSubMenu()"><i data-lucide="chevron-left" style="width: 16px; height: 16px; margin-right: 8px;"></i>Back</div><h3 style="font-size:14px; font-weight:700; margin-bottom:15px; padding-left:10px;">${item.name}</h3>`;
            
            item.subItems.forEach(sub => {
                // Check if this specific sub-item is the current page
                const isCurrentPage = window.location.href.includes(sub.path);
                const activeClass = isCurrentPage ? 'style="background: var(--active-bg); color: #16636B; font-weight: 700; border-left: 3px solid #16636B;"' : '';
                
                container.innerHTML += `
                    <a href="${sub.path}" class="sub-item" ${activeClass}>
                        <i data-lucide="${sub.icon || 'circle'}" class="sub-icon"></i>
                        <span style="flex:1">${sub.name}</span>
                        <i data-lucide="chevron-right" style="width:12px; height:12px; color:#a1a1aa"></i>
                    </a>`;
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

    // 3. NEW: PERSISTENCE LOGIC
    // Runs automatically when the page loads to keep the sidebar open
    document.addEventListener("DOMContentLoaded", function() {
        const currentURL = window.location.href;
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(nav => {
            const onclickAttr = nav.getAttribute('onclick');
            if(onclickAttr) {
                // Parse the data from the handleNavClick function call
                try {
                    const match = onclickAttr.match(/handleNavClick\(({.*}), this\)/);
                    if(match && match[1]) {
                        const itemData = JSON.parse(match[1]);
                        
                        if(itemData.subItems) {
                            // Check if any sub-item path matches the current URL
                            const hasActiveSub = itemData.subItems.some(sub => currentURL.includes(sub.path));
                            if(hasActiveSub) {
                                // Re-trigger the click logic to show the menu
                                handleNavClick(itemData, nav);
                            }
                        }
                    }
                } catch(e) { console.error("Sidebar Persistence Error:", e); }
            }
        });
    });
</script>