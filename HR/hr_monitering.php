<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workack HRMS | HR Monitoring View</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-light: #f7f7f7;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #333333;
            --text-muted: #666666;
            --border-light: #e3e3e3;
            --sidebar-width: 260px;
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; display: flex; }
        .sidebar-wrapper { width: var(--sidebar-width); background: var(--white); height: 100vh; position: fixed; border-right: 1px solid var(--border-light); z-index: 100; }
        .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }

        /* Stats Row */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 20px; border-radius: 8px; border: 1px solid var(--border-light); text-align: center; }
        .stat-card h3 { margin: 0; font-size: 24px; color: var(--primary-orange); }
        .stat-card p { margin: 5px 0 0; font-size: 13px; color: var(--text-muted); font-weight: 500; }

        /* Filter Section */
        .filter-bar { background: var(--white); padding: 15px 25px; border-radius: 8px; border: 1px solid var(--border-light); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; }
        .filter-bar select { padding: 8px; border-radius: 6px; border: 1px solid var(--border-light); font-size: 13px; outline: none; }

        /* Monitoring Table */
        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; overflow: hidden; }
        .card-title { font-size: 18px; font-weight: 600; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; }

        .task-table { width: 100%; border-collapse: collapse; }
        .task-table th { text-align: left; padding: 15px 25px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; }
        .task-table td { padding: 18px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; }
        
        .status-badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .on-time { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }
        .delayed { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }

        .action-btn { background: var(--primary-orange); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }

        /* Proof Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 10px; width: 500px; }
        .modal-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        <div class="page-header">
            <h1>HR Productivity Monitor</h1>
        </div>

        <div class="stats-row">
            <div class="stat-card"><h3>45</h3><p>Total Tasks</p></div>
            <div class="stat-card"><h3>32</h3><p>Completed</p></div>
            <div class="stat-card"><h3>8</h3><p>In Progress</p></div>
            <div class="stat-card" style="border-bottom: 3px solid #ff4d4f;"><h3>5</h3><p>Overdue</p></div>
        </div>

        <div class="filter-bar">
            <span><i class="fas fa-filter"></i> Filter By:</span>
            <select><option>All Departments</option><option>IT Team</option><option>Sales</option></select>
            <select><option>All Team Leads</option><option>Ragul Kumar</option></select>
        </div>

        <div class="content-card">
            <div class="card-title">Employee Performance Tracker</div>
            <table class="task-table">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Task Title</th>
                        <th>Team Lead</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th style="text-align: right;">Review Proof</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Suresh Babu</strong></td>
                        <td>Database Schema Design</td>
                        <td>Ragul Kumar</td>
                        <td>08 Feb 2026</td>
                        <td><span class="status-badge on-time">Completed</span></td>
                        <td style="text-align: right;">
                            <button class="action-btn" onclick="viewProof('Suresh Babu', 'Database schema is finalized in MySQL and pushed to GitHub.')">
                                View Proof
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Karthik</strong></td>
                        <td>API Endpoints</td>
                        <td>Ragul Kumar</td>
                        <td>05 Feb 2026</td>
                        <td><span class="status-badge delayed">Delayed</span></td>
                        <td style="text-align: right;">---</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="proofViewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="proofEmpName" style="margin:0;">Work Proof</h3>
                <span style="cursor:pointer;" onclick="closeModal('proofViewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-muted); font-weight: 600;">Submitted Evidence:</p>
                <div id="proofText" style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid var(--border-light); line-height: 1.6; font-size: 14px;">
                </div>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button class="action-btn" onclick="closeModal('proofViewModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function viewProof(empName, proofContent) {
            document.getElementById('proofEmpName').innerText = "Proof by: " + empName;
            document.getElementById('proofText').innerText = proofContent;
            document.getElementById('proofViewModal').style.display = 'block';
        }

        window.onclick = function(event) { if (event.target.className === 'modal') { closeModal('proofViewModal'); } }
    </script>
</body>
</html>