<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | Attendance & Leave Admin</title>
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
        
        /* Stats Grid Styles */
        .card { background: #fff; border: 1px solid #e3e3e3; border-radius: 8px; margin-bottom: 24px; box-shadow: none; }
        .details-today-grid .col { border-right: 1px solid var(--border-light); padding: 15px 20px; }
        .details-today-grid .col:last-child { border-right: none; }
        .badge-success { background-color: #e6fdf0; color: #10b981; border: none; }
        .badge-danger { background-color: #fef2f2; color: #ef4444; border: none; }

        /* Two-Column Grid matching Notice Board */
        .admin-grid { display: grid; grid-template-columns: 1fr 380px; gap: 25px; align-items: start; }

        /* Datatable Styles */
        .table thead th { background-color: #f9fafb; font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; padding: 12px 20px; }
        .table tbody td { vertical-align: middle; padding: 15px 20px; border-bottom: 1px solid var(--border-light); font-size: 14px; }
        .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; }

        /* Sidebar Styles (Notice Board Style) */
        .side-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; padding: 25px; position: sticky; top: 25px; }
        .side-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; border-bottom: 3px solid var(--primary-orange); display: inline-block; padding-bottom: 5px; }
        .request-item { display: flex; gap: 15px; padding: 12px; border-radius: 10px; cursor: pointer; transition: 0.3s; margin-bottom: 10px; border: 1px solid transparent; background: #fafafa; }
        .request-item:hover { background: #fff8f6; border-color: #ffe0d8; }
        .request-item.active { background: #fff1f0; border-color: var(--primary-orange); border-left: 5px solid var(--primary-orange); }
        .date-box { width: 50px; height: 50px; background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; font-size: 14px; }
        .date-box span { font-size: 9px; text-transform: uppercase; color: var(--primary-orange); }

        /* Modal / Icons */
        .input-icon-group { position: relative; }
        .input-icon-group .ti { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .btn-orange { background-color: var(--primary-orange); color: white; font-weight: 600; border: none; padding: 10px 25px; border-radius: 6px; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        
        <div class="d-md-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="mb-1" style="font-weight:700;">Attendance Admin</h2>
                <nav class="small"><ol class="breadcrumb mb-0"><li class="breadcrumb-item">Attendance</li><li class="breadcrumb-item active">Attendance Admin</li></ol></nav>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-white border btn-sm"><i class="ti ti-file-export"></i> Export</button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#attendance_report"><i class="ti ti-file-analytics"></i> Report</button>
            </div>
        </div>

        <div class="card border-0 shadow-none mb-4">
            <div class="card-body">
                <div class="row align-items-center mb-4">
                    <div class="col-md-5"><h4>Attendance Details Today</h4><p class="text-muted fs-13">Data from the 800+ total employees</p></div>
                    <div class="col-md-7 text-md-end"><h6>Total Absentees today <span class="badge bg-primary rounded-circle ms-2">+1</span></h6></div>
                </div>
                <div class="border rounded details-today-grid">
                    <div class="row gx-0 text-center">
                        <div class="col"><span class="fw-medium d-block mb-1 fs-14">Present</span><h5>250</h5><span class="badge badge-success fs-10">+1%</span></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-14">Late Login</span><h5>45</h5><span class="badge badge-danger fs-10">-1%</span></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-14">Uninformed</span><h5>15</h5><span class="badge badge-danger fs-10">-12%</span></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-14">Permission</span><h5>03</h5><span class="badge badge-success fs-10">+1%</span></div>
                        <div class="col"><span class="fw-medium d-block mb-1 fs-14">Absent</span><h5>12</h5><span class="badge badge-danger fs-10">-19%</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-grid">
            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daily Logs</h5>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px;">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Break</th>
                                <th>Production</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="date-box me-2" style="width:32px; height:32px; border-radius:50%; background:#eee;">AL</div>
                                        <div><strong class="fs-13">Anthony Lewis</strong><br><small class="text-muted">UI/UX Team</small></div>
                                    </div>
                                </td>
                                <td><span class="badge badge-success-transparent">● Present</span></td>
                                <td>09:00 AM</td>
                                <td>06:45 PM</td>
                                <td>30 Min</td>
                                <td><span class="badge bg-success text-white">8.55 Hrs</span></td>
                                <td class="text-end">
                                    <button class="btn btn-icon text-muted" data-bs-toggle="modal" data-bs-target="#edit_attendance"><i class="ti ti-edit"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="side-card">
                    <div class="side-title">Pending Applications</div>
                    
                    <div class="request-item active" onclick="alert('Review John Doe Leave')">
                        <div class="date-box">08<span>Feb</span></div>
                        <div class="news-text"><h4 class="mb-1">John Doe</h4><p>Sick Leave - 3 Days</p></div>
                    </div>

                    <div class="request-item" onclick="alert('Review Emily Davis Leave')">
                        <div class="date-box">12<span>Feb</span></div>
                        <div class="news-text"><h4 class="mb-1">Emily Davis</h4><p>Casual Leave - 1 Day</p></div>
                    </div>

                    <div class="mt-4 p-3 border rounded bg-light">
                        <p class="small fw-bold mb-2">Review Selected:</p>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm flex-fill fw-bold" onclick="confirm('Approve?')">Approve</button>
                            <button class="btn btn-danger btn-sm flex-fill fw-bold" onclick="confirm('Decline?')">Decline</button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div class="modal fade" id="edit_attendance" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h4 class="modal-title">Edit Attendance</h4><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label fw-semibold">Date</label>
                                <div class="input-icon-group"><input type="text" class="form-control" value="15 Apr 2025"><i class="ti ti-calendar"></i></div>
                            </div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Check In</label>
                                <div class="input-icon-group"><input type="text" class="form-control" value="09:00 AM"><i class="ti ti-clock"></i></div>
                            </div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Check Out</label>
                                <div class="input-icon-group"><input type="text" class="form-control" value="18:45 PM"><i class="ti ti-clock"></i></div>
                            </div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Break</label><input type="text" class="form-control" value="30 Min"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Late</label><input type="text" class="form-control" value="32 Min"></div>
                            <div class="col-12"><label class="form-label fw-semibold">Production Hours</label>
                                <div class="input-icon-group"><input type="text" class="form-control" value="08:55 AM"><i class="ti ti-clock"></i></div>
                            </div>
                            <div class="col-12"><label class="form-label fw-semibold">Status</label><select class="form-select"><option selected>Present</option><option>Absent</option></select></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-orange">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>