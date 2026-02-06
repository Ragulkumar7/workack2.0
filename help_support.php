<?php
/**
 * HRMS Help & Support - Complete Master-Detail System with Sidebar Layout
 */

$support_data = [
    'intro_hrms' => [
        'title' => 'Introduction to HRMS',
        'icon' => 'fa-info-circle',
        'category' => 'General',
        'content' => 'The Human Resource Management System (HRMS) is a digital solution that integrates core HR processes into one platform. It centralizes employee data, simplifies payroll, and automates daily tasks to increase organizational efficiency.'
    ],
    'ess' => [
        'title' => 'Employee Self-Service (ESS)',
        'icon' => 'fa-user-gear',
        'category' => 'Employee Portal',
        'content' => 'ESS allows employees to take control of their own data. You can update personal contact information, view your company profile, download documents, and access your work history without needing to contact the HR department directly.'
    ],
    'mss' => [
        'title' => 'Manager Self-Service (MSS)',
        'icon' => 'fa-users-gear',
        'category' => 'Management',
        'content' => 'MSS provides managers with tools to oversee their teams. This includes approving leave requests, viewing team performance metrics, managing department schedules, and generating reports for direct reports.'
    ],
    'payroll' => [
        'title' => 'Payroll Management',
        'icon' => 'fa-money-check-dollar',
        'category' => 'Finance',
        'content' => 'This module handles salary calculations, tax deductions, and bonuses. Users can view historical payslips and tax statements. Payroll is processed based on validated attendance and approved leave data.'
    ],
    'attendance' => [
        'title' => 'Attendance & Time Tracking',
        'icon' => 'fa-clock',
        'category' => 'Tracking',
        'content' => 'Track your daily work hours, punch-in/out times, and overtime. The system uses biometric or mobile geofencing to ensure accurate time logs. Any discrepancies should be corrected via a regularization request.'
    ],
    'leave' => [
        'title' => 'Leave Management',
        'icon' => 'fa-calendar-check',
        'category' => 'Time Off',
        'content' => 'Request various types of leave, such as Casual, Medical, or Earned leave. You can view your remaining leave balance in real-time and track the approval status of your submitted applications.'
    ],
    'recruitment' => [
        'title' => 'Recruitment & Onboarding',
        'icon' => 'fa-user-plus',
        'category' => 'Hiring',
        'content' => 'Manage the entire hiring lifecycle from job posting to candidate selection. New hires can complete their onboarding paperwork, sign digital contracts, and view orientation materials through this module.'
    ],
    'performance' => [
        'title' => 'Performance Management',
        'icon' => 'fa-chart-line',
        'category' => 'Appraisal',
        'content' => 'This section tracks Key Performance Indicators (KPIs) and goals. It facilitates 360-degree feedback, quarterly reviews, and annual appraisals between employees and their supervisors.'
    ],
    'reports' => [
        'title' => 'Reports & Analysis',
        'icon' => 'fa-chart-pie',
        'category' => 'Analytics',
        'content' => 'Generate visual data summaries regarding workforce demographics, turnover rates, and attendance patterns. These reports help in making data-driven decisions for the organization.'
    ],
    'ticket_center' => [
        'title' => 'Ticket Center',
        'icon' => 'fa-ticket',
        'category' => 'Support',
        'content' => 'If you encounter technical issues or data errors, use the Ticket Center to raise a formal request. You can track the progress of your ticket and communicate directly with the assigned support agent.'
    ],
    'troubleshooting' => [
        'title' => 'Troubleshooting',
        'icon' => 'fa-screwdriver-wrench',
        'category' => 'Support',
        'content' => 'A collection of guides for common issues like password recovery, browser compatibility, and mobile app sync errors. Check here before raising a support ticket for common technical hurdles.'
    ]
];

$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Help & Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --bg: #f4f7f6; --accent: #f26522; --dark: #1e293b; --gray: #64748b; --sidebar-width: 260px; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; color: var(--dark); display: flex; min-height: 100vh; }
        
        /* SIDEBAR SPACE */
        .sidebar {
            width: var(--sidebar-width);
            background: #2c3e50; /* Common HRMS sidebar color */
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        /* MAIN CONTENT AREA */
        .main-wrapper {
            margin-left: var(--sidebar-width); /* Creates the space for the sidebar */
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
        }

        .container { max-width: 1100px; margin: 0 auto; }
        
        /* Dashboard Styles */
        .breadcrumb { font-size: 14px; color: var(--gray); margin-bottom: 10px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card-header { display: flex; align-items: center; margin-bottom: 15px; font-weight: bold; font-size: 17px; }
        .card-header i { margin-right: 12px; color: var(--accent); }
        .list { list-style: none; padding: 0; }
        .list li { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .list li a { text-decoration: none; color: #475569; font-size: 14px; display: block; transition: 0.2s; }
        .list li a:hover { color: var(--accent); padding-left: 5px; }

        /* Detail View Styles */
        .detail-view { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .back-btn { display: inline-block; margin-bottom: 25px; color: var(--gray); text-decoration: none; }
        .tag { background: #fff1eb; color: var(--accent); padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: bold; }
        .content-body { line-height: 1.8; font-size: 16px; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <h3>HRMS PORTAL</h3>
        </div>
        <div style="padding: 20px; font-size: 14px; opacity: 0.7;">
            Sidebar Placeholder <br> (Your navigation menu will appear here)
        </div>
    </div>

    <div class="main-wrapper">
        <div class="container">

            <?php if($view == 'dashboard'): ?>
                <div class="breadcrumb">Administration > Help & Support</div>
                <h1 style="margin-bottom: 30px;">Help & Support Center</h1>

                <div class="grid">
                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-folder-tree"></i> System Overview</div>
                        <ul class="list">
                            <li><a href="?view=intro_hrms">Introduction to HRMS</a></li>
                            <li><a href="?view=ess">Employee Self-Service (ESS)</a></li>
                            <li><a href="?view=mss">Manager Self-Service (MSS)</a></li>
                        </ul>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-briefcase"></i> Operations & Finance</div>
                        <ul class="list">
                            <li><a href="?view=payroll">Payroll Management</a></li>
                            <li><a href="?view=attendance">Attendance & Time Tracking</a></li>
                            <li><a href="?view=leave">Leave Management</a></li>
                        </ul>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-users"></i> HR Lifecycle</div>
                        <ul class="list">
                            <li><a href="?view=recruitment">Recruitment & Onboarding</a></li>
                            <li><a href="?view=performance">Performance Management</a></li>
                            <li><a href="?view=reports">Reports & Analysis</a></li>
                        </ul>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-headset"></i> Help Center</div>
                        <ul class="list">
                            <li><a href="?view=ticket_center">Ticket Center</a></li>
                            <li><a href="?view=troubleshooting">Troubleshooting</a></li>
                        </ul>
                    </div>
                </div>

            <?php elseif(isset($support_data[$view])): 
                $data = $support_data[$view]; ?>
                
                <div class="detail-view">
                    <a href="?view=dashboard" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Support Dashboard</a>
                    <br><span class="tag"><?php echo $data['category']; ?></span>
                    <h1><i class="fa-solid <?php echo $data['icon']; ?>" style="color:var(--accent);"></i> <?php echo $data['title']; ?></h1>
                    <div class="content-body">
                        <p><?php echo $data['content']; ?></p>
                        <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 30px 0;">
                        <p style="font-size: 14px; color: var(--gray);">Last Updated: <?php echo date('M d, Y'); ?></p>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>

</body>
</html>