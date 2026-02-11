<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | TL Team Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
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

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; }
        .sidebar-wrapper { width: var(--sidebar-width); background: var(--white); height: 100vh; position: fixed; border-right: 1px solid var(--border-light); z-index: 100; }
        .main-wrapper { margin-left: var(--sidebar-width); padding: 25px; }
        
        /* Stats Grid for Team Only */
        .card { background: #fff; border: 1px solid #e3e3e3; border-radius: 8px; margin-bottom: 24px; box-shadow: none; }
        .details-today-grid .col { border-right: 1px solid var(--border-light); padding: 15px; text-align: center; }
        .details-today-grid .col:last-child { border-right: none; }

        /* Two-Column Grid matching Notice Board Style */
        .admin-grid { display: grid; grid-template-columns: 1fr 380px; gap: 25px; align-items: start; }

        /* Datatable Styles */
        .table thead th { background-color: #f9fafb; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; padding: 12px; }
        .table tbody td { vertical-align: middle; padding: 12px; border-bottom: 1px solid var(--border-light); font-size: 13px; }

        /* Team Sidebar Styling */
        .side-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; padding: 20px; position: sticky; top: 25px; }
        .side-title { font-size: 16px; font-weight: 700; margin-bottom: 15px; border-bottom: 3px solid var(--primary-orange); display: inline-block; padding-bottom: 5px; }
        
        .team-item { display: flex; gap: 12px; padding: 10px; border-radius: 8px; cursor: pointer; transition: 0.3s; margin-bottom: 8px; border: 1px solid transparent; background: #fafafa; align-items: center; }
        .team-item:hover { background: #fff8f6; border-color: #ffe0d8; }
        .team-item.active { background: #fff1f0; border-color: var(--primary-orange); border-left: 4px solid var(--primary-orange); }
        
        .avatar-circle { width: 35px; height: 35px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; color: var(--primary-orange); flex-shrink: 0; }

        .btn-orange { background-color: var(--primary-orange); color: white; font-weight: 600; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; }
        .badge-leave { background: #fff1f0; color: #ff5b37; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: auto; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        
        <div class="d-md-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="mb-1" style="font-weight:700; font-size: 22px;">Team Attendance Admin</h2>
                <p class="text-muted small m-0">Monitoring <b>My Team</b> logs and requests</p>
            </div>
            <div class="badge bg-white border text-dark p-2 px-3 rounded-pill">
                <i class="ti ti-users"></i> Team Lead View
            </div>
        </div>

        <div class="card border-0 shadow-none mb-4">
            <div class="card-body p-0">
                <div class="border rounded details-today-grid bg-white">
                    <div class="row gx-0">
                        <div class="col"><span class="fw-medium d-block mb-1 fs-12 text-muted">Team Present</span><h5 class="mb-0">08 / 10</h5></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-12 text-muted">Late Logins</span><h5 class="mb-0">02</h5></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-12 text-muted">On Leave</span><h5 class="mb-0">01</h5></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-12 text-muted">Avg Prod.</span><h5 class="mb-0">8.2 Hrs</h5></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-grid">
            <div class="card mb-0">
                <div class="card-header border-bottom bg-transparent d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Team Attendance Logs</h6>
                    <input type="text" class="form-control form-control-sm w-auto" placeholder="Search team member...">
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Production</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="teamLogTable">
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2">SB</div>
                                        <div><strong class="fs-13">Suresh Babu</strong><br><small class="text-muted">Senior Dev</small></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-success-subtle text-success">● Present</span></td>
                                <td>09:00 AM</td>
                                <td><span class="badge bg-success text-white">8.55 Hrs</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm text-muted p-0" data-bs-toggle="modal" data-bs-target="#edit_attendance"><i class="ti ti-edit fs-16"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="side-card">
                    <div class="side-title">Team Members</div>
                    
                    <div class="team-item active" onclick="showMemberDetail('Suresh Babu', 'SB', 'Sick Leave Application Found')">
                        <div class="avatar-circle">SB</div>
                        <div class="news-text"><h6 class="mb-0">Suresh Babu</h6><p class="small">Online</p></div>
                        <span class="badge-leave">1 Request</span>
                    </div>

                    <div class="team-item" onclick="showMemberDetail('Anitha', 'AN', 'No pending requests')">
                        <div class="avatar-circle">AN</div>
                        <div class="news-text"><h6 class="mb-0">Anitha</h6><p class="small">On Break</p></div>
                    </div>

                    <div class="mt-4 p-3 border rounded" style="background: #fff8f6; border-color: #ffe0d8!important;">
                        <p class="small fw-bold mb-2">Leave Review: <span id="activeMember">Suresh Babu</span></p>
                        <div class="bg-white p-2 rounded mb-3 border small italic">"Suffering from cold/fever. Requesting 1 day leave."</div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm flex-fill fw-bold" onclick="alert('Leave Approved')">Approve</button>
                            <button class="btn btn-danger btn-sm flex-fill fw-bold" onclick="alert('Leave Declined')">Decline</button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showMemberDetail(name, initials, status) {
            document.getElementById('activeMember').innerText = name;
            document.querySelectorAll('.team-item').forEach(item => item.classList.remove('active'));
            event.currentTarget.classList.add('active');
            console.log("Filtering logs for " + name); // Logic to filter left table rows
        }
    </script>
</body>
</html>