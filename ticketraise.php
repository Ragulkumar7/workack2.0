<?php
ob_start(); // <--- CRITICAL FIX: Buffers output so headers/sessions work anywhere

// Determine which page to show based on URL parameter (default is dashboard)
$page = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR - Ticket System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --sidebar-bg: #1e2436;      
            --sidebar-active: #151b2b;   
            --sidebar-text: #aeb7c2;     
            --sidebar-text-active: #ffffff;
            --accent-color: #ff5b37;     
            --bg-body: #f4f6f9;          
            --card-bg: #ffffff;
            --text-primary: #333333;
            --text-secondary: #777777;
            --border-color: #e5e9f2;
            
            /* Status Colors */
            --status-open-bg: #e3f2fd;
            --status-open-text: #0c0c0c;
            --status-solved-bg: #e8f5e9;
            --status-solved-text: #2e7d32;
            --status-pending-bg: #fff3e0;
            --status-pending-text: #ef6c00;
            --status-new-bg: #f3e5f5;
            --status-new-text: #0a0a0a;
            --status-high-bg: #ffebee;
            --status-high-text: #c62828;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: var(--text-primary);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: #fff;
            width: 550px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #1f2d3d;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #777;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.9rem;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-color);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* ==============================
           MAIN CONTENT AREA
           ============================== */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            /* Adjust margin for new sidebar */
            margin-left: 95px; 
            transition: margin-left 0.3s;
        }
        
        /* When secondary sidebar opens (handled by sidebars.php JS), 
           we need to push content. The class 'main-shifted' is added by sidebars.php script */
        .main-content.main-shifted {
            margin-left: 315px; /* 95px + 220px */
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            color: #1f2d3d;
            font-weight: 600;
        }

        /* --- Cards (Stats & Widgets) --- */
        .card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            color: #1f2d3d;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-left: 4px solid transparent;
        }

        /* Specific colors for stat cards border/icons */
        .stat-new { border-left-color: #2962ff; }
        .stat-open { border-left-color: #ff9800; }
        .stat-solved { border-left-color: #00c853; }
        .stat-pending { border-left-color: #9c27b0; }

        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #777;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .stat-new .stat-icon { background: #e3f2fd; color: #2962ff; }
        .stat-open .stat-icon { background: #fff3e0; color: #ff9800; }
        .stat-solved .stat-icon { background: #e8f5e9; color: #00c853; }
        .stat-pending .stat-icon { background: #f3e5f5; color: #9c27b0; }

        /* Layout for Dashboard */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        /* Tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            color: #777;
            font-weight: 500;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.9rem;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 0.9rem;
            color: #333;
        }

        tr:hover td {
            background-color: #fafafa;
        }

        .ticket-id {
            color: var(--accent-color);
            font-weight: 600;
            text-decoration: none;
        }
        .ticket-id:hover { text-decoration: underline; }

        /* Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-open { background: var(--status-open-bg); color: var(--status-open-text); }
        .badge-solved { background: var(--status-solved-bg); color: var(--status-solved-text); }
        .badge-pending { background: var(--status-pending-bg); color: var(--status-pending-text); }
        .badge-new { background: var(--status-new-bg); color: var(--status-new-text); }
        .badge-high { background: var(--status-high-bg); color: var(--status-high-text); }
        .badge-onhold { background: #607d8b; color: white; }

        /* Categories List (Right Sidebar) */
        .category-list {
            list-style: none;
        }
        .category-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            color: #555;
            font-size: 0.9rem;
        }
        .category-item i {
            margin-right: 10px;
            width: 20px;
            color: #999;
        }

        /* Agent Avatars */
        .agent-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .agent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Ticket Details Page Specifics */
        .details-grid {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 25px;
        }
        .chat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        .message {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .msg-bubble {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex: 1;
        }
        .msg-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #666;
        }
        .msg-name { font-weight: 600; color: #333; }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }
        .btn-primary:hover { background-color: #ff5b37; }
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover { background-color: #e0e0e0; }

        /* Report Chart Placeholder */
        .chart-bar-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 250px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .chart-bar {
            width: 12%;
            background: #e0e0e0;
            border-radius: 5px 5px 0 0;
            position: relative;
            transition: height 1s;
        }
        .chart-bar span {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
        }
        .bar-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            border-radius: 5px 5px 0 0;
        }

        /* New Message Styling */
        .new-message {
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {opacity: 0; transform: translateY(20px);}
            to {opacity: 1; transform: translateY(0);}
        }

    </style>
</head>
<body>

    <?php include 'sidebars.php'; ?>

    <main class="main-content" id="mainContent">
        
        <?php if ($page == 'dashboard'): ?>
        <div class="page-header">
            <h2>Tickets</h2>
            <button class="btn btn-primary" onclick="openAddTicketModal()"><i class="fa-solid fa-plus"></i> Add Ticket</button>
        </div>

        <div class="stats-container">
            <div class="stat-card stat-new">
                <div class="stat-info">
                    <h3>120</h3>
                    <p>New Tickets</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-regular fa-envelope"></i>
                </div>
            </div>
            <div class="stat-card stat-open">
                <div class="stat-info">
                    <h3>60</h3>
                    <p>Open Tickets</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-lock-open"></i>
                </div>
            </div>
            <div class="stat-card stat-solved">
                <div class="stat-info">
                    <h3>50</h3>
                    <p>Solved Tickets</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-info">
                    <h3>10</h3>
                    <p>Pending Tickets</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-regular fa-clock"></i>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Ticket List</h4>
                    <div style="font-size:0.85rem; color:#777;">Sort by: Newest</div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Subject</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Last Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><a href="?view=details" class="ticket-id">Tic - 001</a></td>
                                <td>Laptop Issue</td>
                                <td>Juan Hermann</td>
                                <td><span class="badge badge-open">Open</span></td>
                                <td>Oct 24, 2023</td>
                            </tr>
                            <tr>
                                <td>Tic - 002</td>
                                <td>Payment Issue</td>
                                <td>Ann Lynch</td>
                                <td><span class="badge badge-onhold">On Hold</span></td>
                                <td>Oct 23, 2023</td>
                            </tr>
                            <tr>
                                <td>Tic - 003</td>
                                <td>Bug Report</td>
                                <td>Juan Hermann</td>
                                <td><span class="badge badge-new">Reopened</span></td>
                                <td>Oct 22, 2023</td>
                            </tr>
                            <tr>
                                <td>Tic - 004</td>
                                <td>Access Denied</td>
                                <td>Jessie Otero</td>
                                <td><span class="badge badge-open">Open</span></td>
                                <td>Oct 21, 2023</td>
                            </tr>
                            <tr>
                                <td>Tic - 005</td>
                                <td>Network Slow</td>
                                <td>Edgar Hansel</td>
                                <td><span class="badge badge-pending">Pending</span></td>
                                <td>Oct 20, 2023</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="right-widgets">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Ticket Categories</h4>
                    </div>
                    <ul class="category-list">
                        <li class="category-item">
                            <span><i class="fa-solid fa-wifi"></i> Internet Issue</span>
                            <span class="badge" style="background:#eee; color:#555;">12</span>
                        </li>
                        <li class="category-item">
                            <span><i class="fa-solid fa-laptop"></i> Computer</span>
                            <span class="badge" style="background:#eee; color:#555;">08</span>
                        </li>
                        <li class="category-item">
                            <span><i class="fa-solid fa-print"></i> Printer</span>
                            <span class="badge" style="background:#eee; color:#555;">05</span>
                        </li>
                        <li class="category-item">
                            <span><i class="fa-solid fa-shield-halved"></i> Software</span>
                            <span class="badge" style="background:#eee; color:#555;">24</span>
                        </li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Support Agents</h4>
                    </div>
                    <div class="agent-list">
                        <img src="https://ui-avatars.com/api/?name=Ann+Lynch&background=random" class="agent-avatar" title="Ann Lynch">
                        <img src="https://ui-avatars.com/api/?name=Juan+Hermann&background=random" class="agent-avatar" title="Juan Hermann">
                        <img src="https://ui-avatars.com/api/?name=Jessie+Otero&background=random" class="agent-avatar" title="Jessie Otero">
                        <img src="https://ui-avatars.com/api/?name=Edgar+Hansel&background=random" class="agent-avatar" title="Edgar Hansel">
                        <img src="https://ui-avatars.com/api/?name=Sarah+Smith&background=random" class="agent-avatar" title="Sarah Smith">
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page == 'details'): ?>
        <div class="page-header">
            <h2><a href="?view=dashboard" style="text-decoration:none; color:#777; margin-right:10px;"><i class="fa-solid fa-arrow-left"></i></a> Ticket Details</h2>
            <button class="btn btn-primary">Edit Ticket</button>
        </div>

        <div class="details-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Laptop Issue</h3>
                        <small style="color:#777;">Created on Oct 24, 2023 by Adrian Herman</small>
                    </div>
                    <span class="badge badge-open">Open</span>
                </div>

                <div class="chat-box" id="chatBox">
                    <div class="message">
                        <img src="https://ui-avatars.com/api/?name=Adrian+Herman&background=34495e&color=fff" style="width:40px; height:40px; border-radius:50%;">
                        <div class="msg-bubble">
                            <div class="msg-meta">
                                <span class="msg-name">Adrian Herman</span>
                                <span>10:30 AM</span>
                            </div>
                            <p>Hi, my laptop is freezing intermittently. It happens randomly, and I noticed the CPU usage shoots up to 100% when it freezes. Please help.</p>
                        </div>
                    </div>

                    <div class="message">
                        <img src="https://ui-avatars.com/api/?name=Juan+Hermann&background=e67e22&color=fff" style="width:40px; height:40px; border-radius:50%;">
                        <div class="msg-bubble">
                            <div class="msg-meta">
                                <span class="msg-name">Juan Hermann (Agent)</span>
                                <span>11:15 AM</span>
                            </div>
                            <p>Hello Adrian, can you please send me the screenshot of the Task Manager when the freeze happens? Also, is it happening while running a specific application?</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top:15px;">
                    <textarea id="replyInput" placeholder="Type your reply here..." style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; height:90px; resize:none; outline:none;"></textarea>
                    <div style="margin-top:10px; text-align:right;">
                        <button class="btn btn-primary" onclick="sendReply()">Send Reply</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Ticket Details</h4>
                </div>
                <div style="margin-bottom:20px;">
                    <small style="color:#999; font-weight:600;">Assignee</small>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
                        <img src="https://ui-avatars.com/api/?name=Edgar+Hansel&background=random" style="width:35px; height:35px; border-radius:50%;">
                        <span style="font-weight:500;">Edgar Hansel</span>
                    </div>
                </div>
                
                <div style="margin-bottom:20px;">
                    <small style="color:#999; font-weight:600;">Priority</small>
                    <div style="margin-top:8px;">
                        <span class="badge badge-high">High</span>
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <small style="color:#999; font-weight:600;">Category</small>
                    <div style="margin-top:8px; font-weight:500; color:#333;">Hardware</div>
                </div>
                
                <hr style="border:0; border-top:1px solid #eee; margin:15px 0;">
                
                <div>
                    <small style="color:#999; font-weight:600;">Ticket ID</small>
                    <div style="margin-top:5px; font-family:monospace; color:#555;">TIC-001</div>
                </div>
            </div>
        </div>

        <?php elseif ($page == 'automation'): ?>
        <div class="page-header">
            <h2>Ticket Automation</h2>
            <button class="btn btn-primary" onclick="openAddRuleModal()"><i class="fa-solid fa-plus"></i> Add New Rule</button>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Escalation Rules List</h4>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Rule ID</th>
                            <th>Rule Name</th>
                            <th>Trigger Event</th>
                            <th>Condition</th>
                            <th>Action</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#ER005</td>
                            <td>Auto Assign IT Login Issues</td>
                            <td>Ticket Created</td>
                            <td>Category = IT Login</td>
                            <td>Assign Agent</td>
                            <td>IT Support Team</td>
                            <td><span class="badge badge-solved">Active</span></td>
                        </tr>
                        <tr>
                            <td>#ER004</td>
                            <td>Critical Ticket Alert</td>
                            <td>Priority Changed</td>
                            <td>Priority = Critical</td>
                            <td>Send Notification</td>
                            <td>Admin</td>
                            <td><span class="badge badge-solved">Active</span></td>
                        </tr>
                        <tr>
                            <td>#ER003</td>
                            <td>Pending Auto Close</td>
                            <td>Status Pending</td>
                            <td>Time > 48 hours</td>
                            <td>Close Ticket</td>
                            <td>System</td>
                            <td><span class="badge badge-solved">Active</span></td>
                        </tr>
                        <tr>
                            <td>#ER002</td>
                            <td>High Priority Escalation</td>
                            <td>Ticket Updated</td>
                            <td>Priority = High</td>
                            <td>Escalate to L2</td>
                            <td>Manager</td>
                            <td><span class="badge badge-solved">Active</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page == 'report'): ?>
        <div class="page-header">
            <h2>Ticket Report</h2>
            <button class="btn btn-primary"><i class="fa-solid fa-download"></i> Export</button>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>240</h3>
                    <p>Total Projects</p>
                </div>
                <div class="stat-icon" style="background:#e8eaf6; color:#3f51b5;">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>35</h3>
                    <p>Open Tickets</p>
                </div>
                <div class="stat-icon" style="background:#fff3e0; color:#f57c00;">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>170</h3>
                    <p>Resolved Tickets</p>
                </div>
                <div class="stat-icon" style="background:#e8f5e9; color:#388e3c;">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>12%</h3>
                    <p>Growth Rate</p>
                </div>
                <div class="stat-icon" style="background:#fce4ec; color:#d81b60;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Ticket Categories Vs Priority</h4>
            </div>
            <div class="chart-bar-container">
                <div class="chart-bar" style="height: 40%;">
                    <div class="bar-fill" style="height:100%; background:#2962ff;"></div>
                    <span>Internet</span>
                </div>
                <div class="chart-bar" style="height: 70%;">
                    <div class="bar-fill" style="height:100%; background:#9c27b0;"></div>
                    <span>Computer</span>
                </div>
                <div class="chart-bar" style="height: 30%;">
                    <div class="bar-fill" style="height:100%; background:#ff9800;"></div>
                    <span>Printer</span>
                </div>
                <div class="chart-bar" style="height: 85%;">
                    <div class="bar-fill" style="height:100%; background:#00c853;"></div>
                    <span>Software</span>
                </div>
                <div class="chart-bar" style="height: 50%;">
                    <div class="bar-fill" style="height:100%; background:#f44336;"></div>
                    <span>Access</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Recent Ticket Reports</h4>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#TIC016</td>
                            <td>Login not working</td>
                            <td>Access</td>
                            <td><span class="badge badge-high">Critical</span></td>
                            <td>Closed</td>
                        </tr>
                        <tr>
                            <td>#TIC015</td>
                            <td>HR module not loading</td>
                            <td>Software</td>
                            <td><span class="badge badge-high">High</span></td>
                            <td>Open</td>
                        </tr>
                        <tr>
                            <td>#TIC014</td>
                            <td>Wifi disconnecting</td>
                            <td>Network</td>
                            <td><span class="badge badge-pending">Medium</span></td>
                            <td>Pending</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

    </main>

    <div class="modal" id="addTicketModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Ticket</h3>
                <button class="modal-close" onclick="closeAddTicketModal()"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" class="form-control" id="ticketSubject" placeholder="Enter ticket subject">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" id="ticketCategory">
                            <option value="">Select Category</option>
                            <option value="internet">Internet Issue</option>
                            <option value="computer">Computer</option>
                            <option value="printer">Printer</option>
                            <option value="software">Software</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-control" id="ticketPriority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" id="ticketDescription" rows="4" placeholder="Describe the issue in detail"></textarea>
                </div>
                <div class="form-group">
                    <label>Assign To</label>
                    <select class="form-control" id="ticketAssignee">
                        <option value="">Select Assignee</option>
                        <option value="Juan Hermann">Juan Hermann</option>
                        <option value="Ann Lynch">Ann Lynch</option>
                        <option value="Jessie Otero">Jessie Otero</option>
                        <option value="Edgar Hansel">Edgar Hansel</option>
                        <option value="Sarah Smith">Sarah Smith</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAddTicketModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitTicket()">Create Ticket</button>
            </div>
        </div>
    </div>

    <div class="modal" id="addRuleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Escalation Rule</h3>
                <button class="modal-close" onclick="closeAddRuleModal()"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Rule Name</label>
                    <input type="text" class="form-control" id="ruleName" placeholder="Enter rule name">
                </div>
                <div class="form-group">
                    <label>Trigger Event</label>
                    <select class="form-control" id="ruleTrigger">
                        <option value="">Select Trigger</option>
                        <option value="created">Ticket Created</option>
                        <option value="updated">Ticket Updated</option>
                        <option value="status_changed">Status Changed</option>
                        <option value="priority_changed">Priority Changed</option>
                        <option value="comment_added">Comment Added</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Condition</label>
                    <input type="text" class="form-control" id="ruleCondition" placeholder="e.g., Priority = High">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Action</label>
                        <select class="form-control" id="ruleAction">
                            <option value="">Select Action</option>
                            <option value="assign_agent">Assign Agent</option>
                            <option value="send_notification">Send Notification</option>
                            <option value="escalate">Escalate to L2</option>
                            <option value="close_ticket">Close Ticket</option>
                            <option value="change_status">Change Status</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assigned To</label>
                        <select class="form-control" id="ruleAssignee">
                            <option value="">Select Assignee</option>
                            <option value="IT Support Team">IT Support Team</option>
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="System">System</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" id="ruleStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAddRuleModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitRule()">Save Rule</button>
            </div>
        </div>
    </div>

    <script>
        function openAddTicketModal() {
            document.getElementById('addTicketModal').classList.add('show');
        }
        
        function closeAddTicketModal() {
            document.getElementById('addTicketModal').classList.remove('show');
            document.getElementById('ticketSubject').value = '';
            document.getElementById('ticketCategory').value = '';
            document.getElementById('ticketPriority').value = 'medium';
            document.getElementById('ticketDescription').value = '';
            document.getElementById('ticketAssignee').value = '';
        }
        
        function submitTicket() {
            const subject = document.getElementById('ticketSubject').value;
            const category = document.getElementById('ticketCategory').value;
            const priority = document.getElementById('ticketPriority').value;
            const description = document.getElementById('ticketDescription').value;
            
            if (!subject || !category || !description) {
                alert('Please fill in all required fields');
                return;
            }
            
            alert('Ticket "' + subject + '" created successfully!');
            closeAddTicketModal();
        }
        
        // Add Rule Modal Functions
        function openAddRuleModal() {
            document.getElementById('addRuleModal').classList.add('show');
        }
        
        function closeAddRuleModal() {
            document.getElementById('addRuleModal').classList.remove('show');
            document.getElementById('ruleName').value = '';
            document.getElementById('ruleTrigger').value = '';
            document.getElementById('ruleCondition').value = '';
            document.getElementById('ruleAction').value = '';
            document.getElementById('ruleAssignee').value = '';
            document.getElementById('ruleStatus').value = 'active';
        }
        
        function submitRule() {
            const name = document.getElementById('ruleName').value;
            const trigger = document.getElementById('ruleTrigger').value;
            const condition = document.getElementById('ruleCondition').value;
            const action = document.getElementById('ruleAction').value;
            
            if (!name || !trigger || !condition || !action) {
                alert('Please fill in all required fields');
                return;
            }
            
            alert('Rule "' + name + '" created successfully!');
            closeAddRuleModal();
        }
        
        // Send Reply Function for Ticket Details
        function sendReply() {
            const replyText = document.getElementById('replyInput').value;
            
            if (!replyText.trim()) {
                alert('Please enter a reply message');
                return;
            }
            
            const chatBox = document.getElementById('chatBox');
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const newMessage = document.createElement('div');
            newMessage.className = 'message new-message';
            newMessage.innerHTML = `
                <img src="https://ui-avatars.com/api/?name=Adrian+Herman&background=2962ff&color=fff" style="width:40px; height:40px; border-radius:50%;">
                <div class="msg-bubble">
                    <div class="msg-meta">
                        <span class="msg-name">Adrian Herman (You)</span>
                        <span>${timeString}</span>
                    </div>
                    <p>${replyText}</p>
                </div>
            `;
            
            chatBox.appendChild(newMessage);
            document.getElementById('replyInput').value = '';
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    </script>
</body>
</html>