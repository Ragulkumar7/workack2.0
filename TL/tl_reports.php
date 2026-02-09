<?php 
// Include sidebar (Assuming this file is in the ROOT directory)
// Note: If inside a subfolder, use include '../sidebars.php';
include '../sidebars.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Lead Reports - SmartHR</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            /* --- THEME UPDATE: #1b5a5a --- */
            --primary: #1b5a5a; 
            --primary-dk: #144d4d;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray: #64748b;
            --light: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- Layout Adjustments for Sidebar --- */
        #mainContent {
            margin-left: 95px; /* Matches Primary Sidebar Width */
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Logic handled by sidebars.php JS */
        #mainContent.main-shifted { margin-left: 315px; }

        /* --- Header --- */
        header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; top: 0; z-index: 40;
        }

        .page-title h1 { font-size: 1.5rem; font-weight: 700; color: var(--text); }
        .page-title p { color: var(--gray); font-size: 0.9rem; margin-top: 4px; }

        /* --- Buttons --- */
        .btn-export {
            padding: 0.6rem 1.2rem;
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-export:hover { background: #f1f5f9; border-color: #cbd5e1; }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }
        .btn-primary:hover { background: var(--primary-dk); color: white; }

        /* --- Dropdown --- */
        .export-wrapper { position: relative; }
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0; top: 120%;
            background: white;
            min-width: 180px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            border: 1px solid var(--border);
            z-index: 50;
            overflow: hidden;
        }
        .dropdown-menu.show { display: block; animation: slideDown 0.2s ease-out; }
        .dropdown-menu a {
            display: block;
            padding: 12px 16px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.1s;
        }
        .dropdown-menu a:hover { background: var(--light); color: var(--primary); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Content Area --- */
        .content-wrapper { padding: 2rem; flex: 1; }
        .content-container { max-width: 1400px; margin: 0 auto; }

        /* --- Stats Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .icon-blue { background: #eff6ff; color: var(--primary); } /* Updated to Theme */
        .icon-green { background: #ecfdf5; color: #10b981; }
        .icon-orange { background: #fff7ed; color: #f97316; }
        .icon-red { background: #fef2f2; color: #ef4444; }

        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--text); }
        .stat-label { font-size: 0.85rem; color: var(--gray); font-weight: 500; }
        .stat-trend { font-size: 0.8rem; margin-top: 8px; display: flex; align-items: center; gap: 4px; }
        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }

        /* --- Charts Section --- */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }

        .chart-card {
            background: var(--card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .chart-header { margin-bottom: 1.5rem; }
        .chart-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .chart-canvas-wrapper { position: relative; height: 300px; width: 100%; }

        /* --- Project Table --- */
        .table-card {
            background: var(--card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .table-header h3 { font-size: 1.1rem; font-weight: 700; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 12px; background: #f8fafc; color: #64748b; font-weight: 600; border-bottom: 1px solid var(--border); }
        td { padding: 16px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        /* Progress Bar */
        .progress-wrapper { width: 100px; }
        .progress-bg { height: 6px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 10px; }
        .progress-text { font-size: 0.75rem; color: var(--gray); margin-top: 4px; display: block; }

        /* Status Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-ontime { background: #ecfdf5; color: #059669; }
        .badge-delayed { background: #fef2f2; color: #dc2626; }
        .badge-risk { background: #fffbeb; color: #d97706; }

        /* Team Avatars & Interaction */
        .team-group { display: flex; padding-left: 10px; cursor: pointer; transition: transform 0.2s; }
        .team-group:hover { transform: scale(1.05); }
        .team-avatar {
            width: 30px; height: 30px; border-radius: 50%; border: 2px solid white;
            margin-left: -10px; object-fit: cover; background: #eee;
        }
        .team-more {
            width: 30px; height: 30px; border-radius: 50%; border: 2px solid white;
            margin-left: -10px; background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;
        }

        /* --- MODAL STYLES --- */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000;
            justify-content: center; align-items: center;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .modal-overlay.show { display: flex; opacity: 1; }
        
        .modal-content {
            background: white; width: 90%; max-width: 800px; max-height: 85vh;
            border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden; display: flex; flex-direction: column;
            transform: translateY(20px); transition: transform 0.3s ease;
        }
        .modal-overlay.show .modal-content { transform: translateY(0); }

        .modal-header {
            padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            background: #f8fafc;
        }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--text); margin: 0; }
        .close-modal { font-size: 1.5rem; color: var(--gray); cursor: pointer; transition: color 0.2s; }
        .close-modal:hover { color: var(--danger); }

        .modal-body { padding: 1.5rem; overflow-y: auto; }

        /* Modal Table Tweaks */
        .modal-body table th { background: white; border-top: none; }
        .user-cell { display: flex; align-items: center; gap: 10px; }
        .user-cell img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-info h4 { margin: 0; font-size: 0.9rem; font-weight: 600; }
        .user-info span { font-size: 0.75rem; color: var(--gray); }

    </style>
</head>
<body>

<div id="mainContent">

    <header>
        <div class="page-title">
            <h1>Team Lead Overview</h1>
            <p>Track project velocity, deadlines, and team performance.</p>
        </div>
        
        <div class="export-wrapper">
            <button class="btn-export" onclick="toggleExportMenu()">
                <i class="fa-solid fa-download"></i> Export Reports <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem;"></i>
            </button>
            <div id="exportMenu" class="dropdown-menu">
                <a onclick="exportToExcel()"><i class="fa-solid fa-file-excel" style="color:#1D6F42;"></i> Export as Excel</a>
                <a onclick="exportToPDF()"><i class="fa-solid fa-file-pdf" style="color:#F40F02;"></i> Export as PDF</a>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="content-container" id="reportArea">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">8</div>
                            <div class="stat-label">Allocated Projects</div>
                        </div>
                        <div class="stat-icon icon-blue"><i class="fa-solid fa-layer-group"></i></div>
                    </div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> 2 New this month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">76%</div>
                            <div class="stat-label">Overall Completion</div>
                        </div>
                        <div class="stat-icon icon-green"><i class="fa-solid fa-chart-pie"></i></div>
                    </div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-check"></i> On track</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">12</div>
                            <div class="stat-label">Upcoming Deadlines</div>
                        </div>
                        <div class="stat-icon icon-orange"><i class="fa-solid fa-hourglass-half"></i></div>
                    </div>
                    <div class="stat-trend trend-down"><i class="fa-solid fa-circle-exclamation"></i> 2 Projects at risk</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">1</div>
                            <div class="stat-label">Crossed Deadline</div>
                        </div>
                        <div class="stat-icon icon-red"><i class="fa-solid fa-calendar-xmark"></i></div>
                    </div>
                    <div class="stat-trend trend-down">Needs attention</div>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Team Workload Distribution</h3>
                        <p style="font-size:0.8rem; color:#64748b;">Tasks Completed vs Assigned</p>
                    </div>
                    <div class="chart-canvas-wrapper">
                        <canvas id="teamWorkloadChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Project Deadline Status</h3>
                        <p style="font-size:0.8rem; color:#64748b;">Overview of all allocated projects</p>
                    </div>
                    <div class="chart-canvas-wrapper">
                        <canvas id="deadlineChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h3>Allocated Projects Status</h3>
                    <button class="btn-export btn-primary" onclick="openModal('viewAllProjectsModal')" style="font-size:0.8rem; padding:0.4rem 0.8rem;">View All</button>
                </div>
                <div class="table-responsive">
                    <table id="projectsTable">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Client</th>
                                <th>Team</th>
                                <th>Deadline</th>
                                <th>Completion</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>SmartHR Mobile App</strong></td>
                                <td>Global Tech</td>
                                <td>
                                    <div class="team-group" onclick="viewTeam('SmartHR Mobile App')">
                                        <img src="https://ui-avatars.com/api/?name=Alice&background=random" class="team-avatar">
                                        <img src="https://ui-avatars.com/api/?name=Bob&background=random" class="team-avatar">
                                        <div class="team-more">+3</div>
                                    </div>
                                </td>
                                <td>15 Apr 2026</td>
                                <td>
                                    <div class="progress-wrapper">
                                        <div class="progress-bg"><div class="progress-fill" style="width: 85%; background:#10b981;"></div></div>
                                        <span class="progress-text">85% Complete</span>
                                    </div>
                                </td>
                                <td><span class="badge badge-ontime">On Time</span></td>
                            </tr>
                            <tr>
                                <td><strong>E-Commerce Redesign</strong></td>
                                <td>Retail Inc.</td>
                                <td>
                                    <div class="team-group" onclick="viewTeam('E-Commerce Redesign')">
                                        <img src="https://ui-avatars.com/api/?name=Charlie&background=random" class="team-avatar">
                                        <img src="https://ui-avatars.com/api/?name=David&background=random" class="team-avatar">
                                    </div>
                                </td>
                                <td>10 Mar 2026</td>
                                <td>
                                    <div class="progress-wrapper">
                                        <div class="progress-bg"><div class="progress-fill" style="width: 45%; background:#f59e0b;"></div></div>
                                        <span class="progress-text">45% Complete</span>
                                    </div>
                                </td>
                                <td><span class="badge badge-risk">At Risk</span></td>
                            </tr>
                            <tr>
                                <td><strong>Internal Audit System</strong></td>
                                <td>FinCorp</td>
                                <td>
                                    <div class="team-group" onclick="viewTeam('Internal Audit System')">
                                        <img src="https://ui-avatars.com/api/?name=Eve&background=random" class="team-avatar">
                                        <img src="https://ui-avatars.com/api/?name=Frank&background=random" class="team-avatar">
                                        <div class="team-more">+1</div>
                                    </div>
                                </td>
                                <td>28 Feb 2026</td>
                                <td>
                                    <div class="progress-wrapper">
                                        <div class="progress-bg"><div class="progress-fill" style="width: 95%; background:#ef4444;"></div></div>
                                        <span class="progress-text">95% Complete</span>
                                    </div>
                                </td>
                                <td><span class="badge badge-delayed">Delayed</span></td>
                            </tr>
                            <tr>
                                <td><strong>Website Migration</strong></td>
                                <td>LogiMove</td>
                                <td>
                                    <div class="team-group" onclick="viewTeam('Website Migration')">
                                        <img src="https://ui-avatars.com/api/?name=Grace&background=random" class="team-avatar">
                                    </div>
                                </td>
                                <td>20 May 2026</td>
                                <td>
                                    <div class="progress-wrapper">
                                        <div class="progress-bg"><div class="progress-fill" style="width: 20%; background:#3b82f6;"></div></div>
                                        <span class="progress-text">20% Complete</span>
                                    </div>
                                </td>
                                <td><span class="badge badge-ontime">On Time</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>

<div id="viewAllProjectsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>All Allocated Projects</h3>
            <span class="close-modal" onclick="closeModal('viewAllProjectsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="table-responsive">
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Project Name</th>
                            <th>Client</th>
                            <th>Deadline</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>#PRJ001</td><td>SmartHR Mobile App</td><td>Global Tech</td><td>15 Apr 2026</td><td><span class="badge badge-ontime">On Time</span></td></tr>
                        <tr><td>#PRJ002</td><td>E-Commerce Redesign</td><td>Retail Inc.</td><td>10 Mar 2026</td><td><span class="badge badge-risk">At Risk</span></td></tr>
                        <tr><td>#PRJ003</td><td>Internal Audit System</td><td>FinCorp</td><td>28 Feb 2026</td><td><span class="badge badge-delayed">Delayed</span></td></tr>
                        <tr><td>#PRJ004</td><td>Website Migration</td><td>LogiMove</td><td>20 May 2026</td><td><span class="badge badge-ontime">On Time</span></td></tr>
                        <tr><td>#PRJ005</td><td>POS Integration</td><td>ShopSmart</td><td>12 Jun 2026</td><td><span class="badge badge-ontime">On Time</span></td></tr>
                        <tr><td>#PRJ006</td><td>CRM Dashboard</td><td>SalesForce</td><td>30 Jul 2026</td><td><span class="badge badge-ontime">On Time</span></td></tr>
                        <tr><td>#PRJ007</td><td>AI Chatbot</td><td>TechSoul</td><td>15 Aug 2026</td><td><span class="badge badge-risk">At Risk</span></td></tr>
                        <tr><td>#PRJ008</td><td>Data Analytics Tool</td><td>BigData Co.</td><td>01 Sep 2026</td><td><span class="badge badge-ontime">On Time</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="teamMembersModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="projectTitleForTeam">Project Team</h3>
            <span class="close-modal" onclick="closeModal('teamMembersModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="table-responsive">
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th>Tasks Assigned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="teamMembersList">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // --- Toggle Export Menu ---
    function toggleExportMenu() {
        document.getElementById("exportMenu").classList.toggle("show");
    }
    
    // Close Dropdowns on Outside Click
    window.onclick = function(e) {
        if (!e.target.closest('.btn-export')) {
            const menu = document.getElementById("exportMenu");
            if (menu && menu.classList.contains('show')) {
                menu.classList.remove('show');
            }
        }
        // Close modal on overlay click
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('show');
        }
    }

    // --- Modal Logic ---
    function openModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // --- Dynamic Team View Logic ---
    function viewTeam(projectName) {
        document.getElementById('projectTitleForTeam').innerText = projectName + " - Team";
        
        // Mock Data Generation based on Project Name (For Demo)
        const teamData = [
            { name: 'Alice Johnson', role: 'Frontend Dev', tasks: '12', status: 'Active' },
            { name: 'Bob Smith', role: 'Backend Dev', tasks: '8', status: 'Active' },
            { name: 'Charlie Brown', role: 'UI Designer', tasks: '5', status: 'On Leave' },
            { name: 'David Lee', role: 'QA Tester', tasks: '15', status: 'Active' },
            { name: 'Eva Green', role: 'DevOps', tasks: '4', status: 'Active' }
        ];

        let html = '';
        teamData.forEach(member => {
            let statusColor = member.status === 'Active' ? '#10b981' : '#f59e0b';
            html += `
                <tr>
                    <td>
                        <div class="user-cell">
                            <img src="https://ui-avatars.com/api/?name=${member.name}&background=random" alt="${member.name}">
                            <div class="user-info">
                                <h4>${member.name}</h4>
                                <span>${member.role}</span>
                            </div>
                        </div>
                    </td>
                    <td>${member.role}</td>
                    <td>${member.tasks} Pending</td>
                    <td><span style="color:${statusColor}; font-weight:600; font-size:0.8rem;">● ${member.status}</span></td>
                </tr>
            `;
        });

        document.getElementById('teamMembersList').innerHTML = html;
        openModal('teamMembersModal');
    }

    // --- Chart.js Configuration ---
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Team Workload Chart (Bar)
        const ctxWorkload = document.getElementById('teamWorkloadChart').getContext('2d');
        new Chart(ctxWorkload, {
            type: 'bar',
            data: {
                labels: ['Alice', 'Bob', 'Charlie', 'David', 'Eva'],
                datasets: [
                    {
                        label: 'Completed Tasks',
                        data: [12, 19, 8, 15, 10],
                        backgroundColor: '#1b5a5a', // Theme Color
                        borderRadius: 4
                    },
                    {
                        label: 'Assigned Tasks',
                        data: [15, 22, 12, 18, 11],
                        backgroundColor: '#e2e8f0',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 2] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Deadline Chart (Pie/Doughnut)
        const ctxDeadline = document.getElementById('deadlineChart').getContext('2d');
        new Chart(ctxDeadline, {
            type: 'doughnut',
            data: {
                labels: ['On Time', 'At Risk', 'Delayed'],
                datasets: [{
                    data: [5, 2, 1],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true } }
                }
            }
        });
    });

    // --- Export Functions ---
    function exportToExcel() {
        const table = document.getElementById("projectsTable");
        const wb = XLSX.utils.table_to_book(table, {sheet: "Projects"});
        XLSX.writeFile(wb, "Team_Lead_Report.xlsx");
    }

    function exportToPDF() {
        const element = document.getElementById("reportArea");
        const { jsPDF } = window.jspdf;

        html2canvas(element, { scale: 2 }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            
            pdf.text("Team Lead Project Report", 15, 15);
            pdf.addImage(imgData, 'PNG', 0, 25, pdfWidth, pdfHeight);
            pdf.save("TL_Report.pdf");
        });
    }
</script>

</body>
</html>