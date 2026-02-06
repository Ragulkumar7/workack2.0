<?php
// attendance.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. VIEW LOGIC
$view = isset($_GET['view']) ? $_GET['view'] : 'admin_dashboard';
$user_name = $_SESSION['username'] ?? "User";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - <?php echo ucwords(str_replace('_', ' ', $view)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-orange: #ff5e3a; --bg-gray: #f8f9fa; --border-color: #edf2f7; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 13px; color: #333; overflow-x: hidden; }
        
        /* CONTENT LAYOUT - Managed by sidebars.php CSS logic */
        #mainContent { 
            margin-left: 95px; /* Primary Sidebar Width */
            padding: 25px 35px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
        }
        #mainContent.main-shifted {
            margin-left: 315px; /* 95 + 220 */
            width: calc(100% - 315px);
        }

        /* Card & Table Styles */
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px; background: #fff; }
        .table thead th { background: #f9fafb; padding: 15px; border-bottom: 1px solid var(--border-color); color: #4a5568; font-weight: 600; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        
        /* Status Badges */
        .status-pill { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-present { background: #e6fffa; color: #38a169; }
        .bg-absent { background: #fff5f5; color: #e53e3e; }
        .bg-pending { background: #eef6ff; color: #3182ce; }
        .prod-btn { color: white; border: none; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .btn-orange { background: var(--primary-orange); color: white; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 600; }
        
        /* Remove Bootstrap Container Constraints if any */
        .container-fluid { padding: 0; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h4 class="fw-bold mb-0"><?php echo ucwords(str_replace('_', ' ', $view)); ?></h4></div>
            <div class="d-flex gap-2">
                <button class="btn btn-light border btn-sm" onclick="validateAction('Export')"><i class="fa-solid fa-download"></i> Export</button>
                <button class="btn btn-orange btn-sm shadow-sm" onclick="handleGlobalAdd('<?php echo $view; ?>')">
                    <?php 
                        if($view == 'timesheets') echo "+ Add Today's Work";
                        elseif($view == 'overtime') echo "+ Add Overtime";
                        elseif($view == 'shift_swap') echo "+ Add New Request";
                        else echo "+ Add Request";
                    ?>
                </button>
            </div>
        </div>

        <?php if ($view == 'admin_dashboard'): ?>
            <div class="card p-4 mb-4">
                <div class="d-flex align-items-center">
                    <img src="https://i.pravatar.cc/60?img=12" class="rounded-circle me-3 border border-3 border-light">
                    <div>
                        <h5 class="fw-bold mb-0">Welcome Back, <?php echo $user_name; ?> <i class="fa-solid fa-circle-check text-primary small"></i></h5>
                        <p class="text-muted small mb-0">You have <span class="text-danger">21 Pending Approvals</span> & <span class="text-danger">14 Leave Requests</span></p>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <?php 
                $dashStats = [
                    ['t' => 'Attendance Overview', 'v' => '120/154'],
                    ['t' => 'Total No of Projects', 'v' => '90/125'],
                    ['t' => 'Total No of Clients', 'v' => '69/86'],
                    ['t' => 'Earnings', 'v' => '$21,445']
                ];
                foreach($dashStats as $ds): ?>
                <div class="col-md-3"><div class="card p-3"><h6><?php echo $ds['t']; ?></h6><h3 class="fw-bold"><?php echo $ds['v']; ?></h3><a href="#" class="small text-muted text-decoration-none">View Details</a></div></div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view == 'attendance_admin'): ?>
            <div class="card mb-4 text-center">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="text-start"><h6 class="fw-bold mb-0">Attendance Details Today</h6><small>Data from 800+ total employees</small></div>
                    <div class="small fw-bold">Total Absenties today <img src="https://i.pravatar.cc/25?img=1" class="rounded-circle ms-1"><span class="badge bg-orange rounded-circle">+1</span></div>
                </div>
                <div class="row g-0">
                    <div class="col border-end p-3">Present<h4>250</h4><span class="badge bg-success-subtle text-success">+1%</span></div>
                    <div class="col border-end p-3">Late Login<h4>45</h4><span class="badge bg-danger-subtle text-danger">-1%</span></div>
                    <div class="col border-end p-3">Uninformed<h4>15</h4><span class="badge bg-danger-subtle text-danger">-12%</span></div>
                    <div class="col border-end p-3">Permission<h4>03</h4><span class="badge bg-success-subtle text-success">+1%</span></div>
                    <div class="col p-3">Absent<h4>12</h4><span class="badge bg-danger-subtle text-danger">-19%</span></div>
                </div>
            </div>
            <div class="card p-0">
                <table class="table mb-0">
                    <thead><tr><th>Employee</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Break</th><th>Late</th><th>Production Hours</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+10; ?>" class="avatar-img"><strong>User <?php echo $i; ?></strong><br><small>Developer</small></td>
                            <td><span class="status-pill bg-present">● Present</span></td>
                            <td>09:00 AM</td><td>06:<?php echo rand(10,59); ?> PM</td><td>20 Min</td><td>1<?php echo $i; ?> Min</td>
                            <td><span class="prod-btn bg-success">8.55 Hrs</span></td>
                            <td><button class="btn btn-sm" onclick="validateAction('Edit')"><i class="fa-solid fa-pen"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'attendance_employee'): ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <p class="text-muted">Good Morning, <?php echo $user_name; ?></p><h5 class="fw-bold"><?php echo date('H:i A, d M Y'); ?></h5>
                        <img src="https://i.pravatar.cc/100?img=12" class="rounded-circle mx-auto my-3 border border-4 border-success p-1">
                        <div class="badge bg-orange mb-3 p-2 w-100">Production : 3.45 hrs</div>
                        <button class="btn btn-dark w-100 fw-bold" id="punchBtn" onclick="togglePunch()">Punch Out</button>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><div class="card p-3">Total Hours Today<h4 class="fw-bold">8.36 / 9</h4><div class="text-success small">↑ 5% This Week</div></div></div>
                        <div class="col-md-6"><div class="card p-3">Total Hours Week<h4 class="fw-bold">10 / 40</h4><div class="text-success small">↑ 7% Last Week</div></div></div>
                    </div>
                    <div class="card p-4">
                        <div class="d-flex justify-content-between mb-2"><span>Productive: 08h 36m</span><span>Break: 22m 15s</span></div>
                        <div class="progress" style="height: 12px;"><div class="progress-bar bg-success" style="width: 75%"></div><div class="progress-bar bg-warning" style="width: 25%"></div></div>
                    </div>
                </div>
            </div>

        <?php elseif ($view == 'schedule_timing'): ?>
            <div class="card p-0">
                <div class="p-3 border-bottom fw-bold">Schedule Timing List</div>
                <table class="table mb-0">
                    <thead><tr><th>Name</th><th>Job Title</th><th>User Available Timings</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i; ?>" class="avatar-img"> Staff Member <?php echo $i; ?></td>
                            <td>Accountant</td><td class="small">11-03-2020 - 11:00 AM-12:00 PM<br>12-03-2020 - 10:00 AM-11:00 AM</td>
                            <td><button class="btn btn-dark btn-sm" onclick="validateAction('Schedule')">Schedule Timing</button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'shift_swap'): ?>
            <div class="card p-0">
                <table class="table mb-0">
                    <thead><tr><th>Emp ID</th><th>Name</th><th>Designation</th><th>Current Shift</th><th>Requested Shift</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td>Emp-00<?php echo $i; ?></td>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+5; ?>" class="avatar-img"> User Name <?php echo $i; ?></td>
                            <td>Designer</td><td>Regular</td><td>Night</td>
                            <td><span class="status-pill <?php echo ($i%2==0)?'bg-present':'bg-pending'; ?>"><?php echo ($i%2==0)?'Approved':'Pending'; ?></span></td>
                            <td><button class="btn btn-sm" onclick="validateAction('Process Request')"><i class="fa-solid fa-check-circle"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'overtime'): ?>
            <div class="row g-3 mb-4 text-center">
                <div class="col-md-3"><div class="card p-3">Overtime Employee<h4 class="fw-bold">12</h4></div></div>
                <div class="col-md-3"><div class="card p-3">Overtime Hours<h4 class="fw-bold">118</h4></div></div>
                <div class="col-md-3"><div class="card p-3">Pending Request<h4 class="fw-bold">23</h4></div></div>
                <div class="col-md-3"><div class="card p-3">Rejected<h4 class="fw-bold">5</h4></div></div>
            </div>
            <div class="card p-0">
                <table class="table mb-0">
                    <thead><tr><th>Employee</th><th>Date</th><th>Overtime Hours</th><th>Project</th><th>Approved By</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+15; ?>" class="avatar-img"> Employee <?php echo $i; ?></td>
                            <td>14 Jan 2024</td><td><?php echo rand(10,50); ?></td><td>Project <?php echo $i; ?></td>
                            <td>Manager X</td><td><span class="status-pill bg-present">Accepted</span></td>
                            <td><button class="btn btn-sm text-danger" onclick="validateAction('Delete')"><i class="fa-solid fa-trash"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'wfh'): ?>
            <div class="card p-0">
                <div class="p-3 border-bottom fw-bold">Employee List</div>
                <table class="table mb-0">
                    <thead><tr><th>Emp ID</th><th>Name</th><th>Designation</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td>Emp-0<?php echo $i; ?></td>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i+20; ?>" class="avatar-img"> Worker <?php echo $i; ?></td>
                            <td>Support</td><td>Health Issue</td><td><span class="status-pill bg-present">Approved</span></td>
                            <td><button class="btn btn-sm text-primary" onclick="validateAction('View Details')"><i class="fa-solid fa-eye"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($view == 'timesheets'): ?>
            <div class="card p-0">
                <table class="table mb-0">
                    <thead><tr><th>Employee</th><th>Date</th><th>Project</th><th>Assigned Hours</th><th>Worked Hours</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <tr>
                            <td><img src="https://i.pravatar.cc/30?img=<?php echo $i; ?>" class="avatar-img"> User <?php echo $i; ?></td>
                            <td>14 Jan 2024</td><td>Project Beta</td><td>40</td><td><?php echo rand(10,40); ?></td>
                            <td><button class="btn btn-sm" onclick="validateAction('Edit Entry')"><i class="fa-solid fa-edit"></i></button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function validateAction(type) {
            if(confirm(`Are you sure you want to ${type} this entry?`)) {
                alert(`${type} successful!`);
            }
        }
        function togglePunch() {
            const btn = document.getElementById('punchBtn');
            if(btn.innerText === "Punch Out") {
                if(confirm("Confirm Punch Out?")) {
                    btn.innerText = "Punch In"; btn.className = "btn btn-success w-100 fw-bold"; alert("Punched Out Successfully.");
                }
            } else {
                if(confirm("Confirm Punch In?")) {
                    btn.innerText = "Punch Out"; btn.className = "btn btn-dark w-100 fw-bold"; alert("Punched In Successfully.");
                }
            }
        }
        function handleGlobalAdd(view) {
            let name = view.replace('_', ' ');
            let input = prompt(`Enter details for new ${name}:`);
            if(input) { alert("Entry added successfully to " + name); }
        }
    </script>
</body>
</html>