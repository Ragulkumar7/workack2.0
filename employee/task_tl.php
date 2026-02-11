<?php
// sidebar.php is one level up from the current directory
include '../sidebars.php';
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Board - Workack</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --theme-color: #1b5a5a;
            --bg-body: #f8fafc;
            --border-color: #e2e8f0;
            --sidebar-primary-width: 95px;
            --sidebar-secondary-width: 220px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        
        body { 
            background-color: var(--bg-body); 
            display: block; /* Changed from flex to block to prevent layout squishing */
            min-height: 100vh; 
            color: #334155; 
            overflow-x: hidden;
        }

        /* --- MAIN CONTENT & SIDEBAR SHIFT --- */
        .main-content { 
            margin-left: var(--sidebar-primary-width); 
            padding: 30px; 
            transition: margin-left 0.3s ease; 
            width: calc(100% - var(--sidebar-primary-width)); /* Ensures full width occupancy */
        }

        /* This class is triggered by your handleNavClick function in sidebars.php */
        .main-shifted { 
            margin-left: calc(var(--sidebar-primary-width) + var(--sidebar-secondary-width)) !important;
            width: calc(100% - (var(--sidebar-primary-width) + var(--sidebar-secondary-width)));
        }

        .page-header { margin-bottom: 25px; }
        .page-header h2 { color: var(--theme-color); font-weight: 700; font-size: 24px; }
        .breadcrumb { font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }

        /* Stats Cards - Adjusted for full page width */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
            width: 100%;
        }
        
        .stat-card { 
            background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-left: 4px solid var(--theme-color);
        }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .stat-info p { color: #64748b; font-size: 0.85rem; font-weight: 600; }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: #eefcfd; color: var(--theme-color); }

        /* Tables & Cards */
        .card { background: #fff; border-radius: 12px; padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0,0,0,0.02); width: 100%; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; font-size: 0.8rem; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; vertical-align: middle; }

        /* Badges */
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-working { background: #eff6ff; color: #1d4ed8; }
        .badge-completed { background: #f0fdf4; color: #15803d; }
        .badge-priority { border: 1px solid #fee2e2; background: #fef2f2; color: #dc2626; padding: 2px 8px; }

        /* Form Elements */
        .target-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            align-items: end; 
            margin-bottom: 20px; 
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; }
        .form-control, .form-select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 0.9rem; }
        
        .btn-theme { 
            background-color: var(--theme-color); 
            color: white; 
            border: none; 
            padding: 12px 20px; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            gap: 8px; 
            height: 42px;
        }
        .btn-theme:hover { background-color: #144444; }

        /* Mobile View */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .main-shifted { margin-left: 0 !important; width: 100%; }
        }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div class="breadcrumb">Task Management > Task Board</div>
    <div class="page-header">
        <h2>Task Overview</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>TOTAL TASKS</p><h3>12</h3></div>
            <div class="stat-icon" style="background:#f1f5f9; color:#475569;"><i class="fa-solid fa-list-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #059669;">
            <div class="stat-info"><p>COMPLETED</p><h3>08</h3></div>
            <div class="stat-icon" style="color: #059669; background: #f0fdf4;"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #1d4ed8;">
            <div class="stat-info"><p>IN PROGRESS</p><h3>02</h3></div>
            <div class="stat-icon" style="color: #1d4ed8; background: #eff6ff;"><i class="fa-solid fa-spinner"></i></div>
        </div>
        <div class="stat-card" style="border-left-color: #ea580c;">
            <div class="stat-info"><p>PENDING</p><h3>02</h3></div>
            <div class="stat-icon" style="color: #ea580c; background: #fff7ed;"><i class="fa-solid fa-clock"></i></div>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 700; margin-bottom: 20px; color: var(--theme-color); font-size: 1.1rem;">Current Team Assignments</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Task Details</th>
                        <th>Assigned By</th>
                        <th>Deadline</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Patient Appointment System</strong><br>
                            <small style="color: #94a3b8;">Update backend logic for doctors</small>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="https://i.pravatar.cc/150?u=9" style="width:35px; border-radius:50%;">
                                <div><span style="font-weight:600;">Adrian Herman</span><br><small>Team Lead</small></div>
                            </div>
                        </td>
                        <td>28 Feb 2026</td>
                        <td><span class="status-badge badge-priority">High</span></td>
                        <td><span class="status-badge badge-pending">Pending</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 700; margin-bottom: 20px; color: var(--theme-color); font-size: 1.1rem;">Set Personal Execution Target</div>
        <div class="target-grid">
            <div class="form-group">
                <label class="form-label">Select Task</label>
                <select id="targetTask" class="form-control">
                    <option value="">Choose assigned task...</option>
                    <option>Patient Appointment System</option>
                    <option>Mobile App UI Fix</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Target Date</label>
                <input type="date" id="targetDate" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Target Time</label>
                <input type="time" id="targetTime" class="form-control">
            </div>
            <button class="btn-theme" onclick="setPersonalTarget()">
                <i class="fa-solid fa-bullseye"></i> Set Target
            </button>
        </div>

        <div class="table-responsive" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Task Commitment</th>
                        <th>Target Deadline</th>
                        <th>Alert Status</th>
                    </tr>
                </thead>
                <tbody id="personalTargetBody">
                    </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    function setPersonalTarget() {
        const task = document.getElementById('targetTask').value;
        const date = document.getElementById('targetDate').value;
        const time = document.getElementById('targetTime').value;

        if(!task || !date || !time) {
            alert("Please complete all target fields.");
            return;
        }

        const tbody = document.getElementById('personalTargetBody');
        const row = `
            <tr style="animation: fadeIn 0.5s;">
                <td><strong>${task}</strong></td>
                <td>${date} at ${time}</td>
                <td><span class="status-badge badge-working">Watching Deadline</span></td>
            </tr>
        `;
        tbody.insertAdjacentHTML('afterbegin', row);
        
        // Reset form inputs
        document.getElementById('targetTask').value = "";
        document.getElementById('targetDate').value = "";
        document.getElementById('targetTime').value = "";
    }
</script>

</body>
</html>