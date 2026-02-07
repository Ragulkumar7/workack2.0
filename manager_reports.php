<?php 
include 'sidebars.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Reports - HRMS</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary:    #d97706;
            --primary-dk: #b45309;
            --success:    #10b981;
            --warning:    #f59e0b;
            --danger:     #ef4444;
            --gray:       #64748b;
            --light:      #f1f5f9;
            --card:       #ffffff;
            --border:     #e2e8f0;
            --text:       #1e293b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main content shifts with sidebar */
        #mainContent {
            margin-left: 95px;
            transition: margin-left 0.4s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 1024px) {
            #mainContent.secondary-visible {
                margin-left: 315px;
            }
        }

        /* --- UPDATED HEADER STYLES --- */
        header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            
            /* Changed from fixed to sticky */
            position: sticky; 
            top: 0;
            z-index: 90; /* High enough to float over content, but usually sidebars are 100+ */
            
            /* Ensure it takes full width of the mainContent container */
            width: 100%; 
        }

        /* --- UPDATED CONTENT WRAPPER --- */
        .content-wrapper {
            /* Removed the large padding-top since header is no longer fixed overlay */
            padding: 2rem;
            flex: 1;
        }

        .content-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }

        .page-title p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card);
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .stat-card .title {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
        }

        .stat-card .value {
            font-size: 2.1rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .trend {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .trend.up    { color: var(--success); }
        .trend.down  { color: var(--danger); }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .chart-card h3 {
            margin-bottom: 1.2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #334155;
        }

        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }

        .chart-container.sm {
            height: 280px;
        }

        .full-width-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .bg-green   { background: #ecfdf5; color: #065f46; }
        .bg-yellow  { background: #fefce8; color: #854d0e; }
        .bg-blue    { background: #eff6ff; color: #1e40af; }
        .bg-red     { background: #fee2e2; color: #991b1b; }

        @media (max-width: 1023px) {
            #mainContent {
                margin-left: 0 !important;
            }
            /* Adjust content wrapper for mobile */
            .content-wrapper {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div id="mainContent">

    <header>
        <div class="page-title">
            <h1>Manager Reports</h1>
            <p>Project progress • Team productivity • Task completion overview</p>
        </div>
        <div style="display:flex; align-items:center; gap:1rem;">
            <button style="padding:0.6rem 1.2rem; background:var(--primary); color:white; border:none; border-radius:6px; cursor:pointer;">
                <i class="fa-solid fa-download"></i> Export Report
            </button>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="content-container">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="title">Total Projects</div>
                    <div class="value">312</div>
                    <div class="trend up"><i class="fa-solid fa-arrow-up"></i> +14 this month</div>
                </div>
                <div class="stat-card">
                    <div class="title">Completed Tasks</div>
                    <div class="value">1,284</div>
                    <div class="trend up"><i class="fa-solid fa-arrow-up"></i> 92.4% completion</div>
                </div>
                <div class="stat-card">
                    <div class="title">Pending Tasks</div>
                    <div class="value">138</div>
                    <div class="trend down"><i class="fa-solid fa-arrow-down"></i> 18 high priority</div>
                </div>
                <div class="stat-card">
                    <div class="title">Active Team Members</div>
                    <div class="value">87</div>
                    <div class="trend up"><i class="fa-solid fa-arrow-up"></i> +3 new joins</div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Project Status Distribution</h3>
                    <div class="chart-container sm">
                        <canvas id="projectStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Tasks Completed vs Assigned (by Department)</h3>
                    <div class="chart-container">
                        <canvas id="tasksByDeptChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="full-width-card">
                <h3 style="margin-bottom:1.2rem;">Active / In-Progress Projects</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Project ID</th>
                                <th>Project Name</th>
                                <th>Project Lead</th>
                                <th>Progress</th>
                                <th>Deadline</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>PRJ-187</strong></td>
                                <td>Hospital Management Phase 2</td>
                                <td>Anthony Lewis</td>
                                <td><strong>88%</strong></td>
                                <td>15 Aug 2025</td>
                                <td><span class="status-badge bg-green">On Track</span></td>
                            </tr>
                            <tr>
                                <td><strong>PRJ-204</strong></td>
                                <td>Payment Gateway Integration</td>
                                <td>Brian Villalobos</td>
                                <td><strong>62%</strong></td>
                                <td>30 Sep 2025</td>
                                <td><span class="status-badge bg-yellow">At Risk</span></td>
                            </tr>
                            <tr>
                                <td><strong>PRJ-205</strong></td>
                                <td>HRMS</td>
                                <td>Damon salvator</td>
                                <td><strong>80%</strong></td>
                                <td>28 Sep 2025</td>
                                <td><span class="status-badge bg-yellow">On track</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
// Wait for DOM + Charts
document.addEventListener('DOMContentLoaded', () => {

    // Charts
    new Chart(document.getElementById('projectStatusChart'), {
        type: 'pie',
        data: {
            labels: ['Completed', 'In Progress', 'On Hold', 'Delayed'],
            datasets: [{
                data: [148, 92, 19, 11],
                backgroundColor: ['#10b981', '#d97706', '#64748b', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('tasksByDeptChart'), {
        type: 'bar',
        data: {
            labels: ['Development', 'QA', 'Design', 'Marketing', 'Support'],
            datasets: [
                { label: 'Assigned',   data: [320,180,95,140,210], backgroundColor: '#cbd5e1' },
                { label: 'Completed',  data: [295,162,88,128,195], backgroundColor: '#d97706' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Sidebar visibility observer
    const secondary = document.querySelector('.sidebar-secondary');
    if (secondary && window.matchMedia("(min-width: 1024px)").matches) {
        const observer = new MutationObserver(() => {
            const isOpen = secondary.classList.contains('open') || 
                          (secondary.style.transform && !secondary.style.transform.includes('-105%'));
            document.getElementById('mainContent').classList.toggle('secondary-visible', isOpen);
        });

        observer.observe(secondary, {
            attributes: true,
            attributeFilter: ['class', 'style']
        });

        const initialOpen = secondary.classList.contains('open');
        document.getElementById('mainContent').classList.toggle('secondary-visible', initialOpen);
    }
});
</script>
</body>
</html>