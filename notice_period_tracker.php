<?php
// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check Login (Added based on your reference code to ensure security)
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | Notice Period Tracker</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* --- PREMIUM THEME VARIABLES --- */
        :root {
            --bg-light: #f7f7f7;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #333333;
            --text-muted: #666666;
            --border-light: #e3e3e3;
        }

        body { 
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden; 
            font-size: 14px;
        }

        /* --- SIDEBAR INTEGRATION CSS (Adapted from Announcement Page) --- */
        #mainContent { 
            margin-left: 95px; /* Primary Sidebar Width */
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
        }
        
        /* If your sidebars.php toggles a class on body or main, add that logic here. 
           For now, this matches your provided reference. */
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        /* --- PAGE HEADER --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        /* --- TABS --- */
        .top-settings-nav { 
            display: flex; 
            background: var(--white); 
            padding: 0 15px; 
            border-radius: 8px; 
            border: 1px solid var(--border-light); 
            margin-bottom: 25px; 
        }
        .top-nav-item { 
            padding: 15px 20px; 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 500; 
            border-bottom: 3px solid transparent; 
            cursor: pointer; 
            transition: 0.3s;
        }
        .top-nav-item.active { 
            color: var(--primary-orange); 
            border-bottom: 3px solid var(--primary-orange); 
        }

        /* --- CARDS & TABLES --- */
        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 0; overflow: hidden; display: none; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.01); }
        .content-card.active { display: block; }
        
        .card-header { padding: 20px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; background: var(--white); }
        .card-title { font-size: 18px; font-weight: 600; margin: 0; }

        .table-responsive { overflow-x: auto; }
        .table { width: 100%; margin-bottom: 0; vertical-align: middle; }
        .table thead th { background-color: #f9fafb; font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; padding: 15px 25px; border-bottom: 1px solid var(--border-light); border-top: none; }
        .table tbody td { padding: 15px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; color: var(--text-dark); vertical-align: middle; }
        
        /* --- BADGES --- */
        .badge { padding: 6px 10px; border-radius: 4px; font-weight: 600; font-size: 11px; }
        .badge-active { background-color: rgba(255, 91, 55, 0.1); color: #ff5b37; border: 1px solid rgba(255, 91, 55, 0.2); }
        .badge-completed { background: #e6fdf0; color: #10b981; border: 1px solid #d1fae5; }
        .badge-closing { background: rgba(165, 94, 234, 0.1); color: #a55eea; border: 1px solid rgba(165, 94, 234, 0.2); }

        /* --- BUTTONS & ACTIONS --- */
        .btn-save { background: var(--primary-orange); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .btn-save:hover { background-color: #e54e2d; color: white; }
        .btn-white { background: var(--white); border: 1px solid var(--border-light); color: var(--text-dark); font-weight: 500; }
        
        .avatar-md { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #eee; margin-right: 10px; }
        .action-btn { background: none; border: none; color: var(--text-muted); font-size: 16px; margin-left: 8px; transition: 0.2s; padding: 0; }
        .action-btn:hover { color: var(--primary-orange); }
        .action-btn.text-danger:hover { color: #dc3545; }

        /* --- FORM CONTROLS --- */
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .input-icon input { padding-left: 40px; height: 38px; border-radius: 6px; border: 1px solid var(--border-light); font-size: 14px; }
        
        /* --- MODALS --- */
        .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid var(--border-light); }
        .modal-title { font-weight: 700; font-size: 18px; }
        .modal-body { padding: 25px; }
        .form-label { font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-control, .form-select { padding: 10px 15px; font-size: 14px; border-color: var(--border-light); border-radius: 6px; }
        
        /* --- UPLOAD BOX --- */
        .upload-box { background: #fafafa; border: 1px dashed #ccc; padding: 20px; border-radius: 8px; display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .upload-icon { width: 50px; height: 50px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #888; border: 1px solid #eee; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Notice Period Tracker</h1>
                <div class="breadcrumb">Dashboard / HRM / Notice Period Tracker</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-white btn-sm dropdown-toggle d-flex align-items-center gap-2 p-2 px-3 rounded" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-file-export"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="ti ti-file-type-pdf me-2"></i>PDF</a></li>
                        <li><a class="dropdown-item" href="#"><i class="ti ti-file-type-xls me-2"></i>Excel</a></li>
                    </ul>
                </div>
                <button class="btn-save" data-bs-toggle="modal" data-bs-target="#add_modal">
                    <i class="ti ti-circle-plus"></i> Add New Employee
                </button>
            </div>
        </div>

        <div class="top-settings-nav">
            <div class="top-nav-item active" onclick="switchTab('active', this)">Active Notice</div>
            <div class="top-nav-item" onclick="switchTab('completed', this)">Completed</div>
            <div class="top-nav-item" onclick="switchTab('all', this)">All Records</div>
        </div>

        <div id="active-card" class="content-card active">
            <div class="card-header">
                <h5 class="card-title">Notice Period List</h5>
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <div class="input-icon">
                        <i class="ti ti-search"></i>
                        <input type="text" placeholder="Search Employee" style="width: 200px;">
                    </div>
                    <div class="input-icon">
                        <i class="ti ti-calendar"></i>
                        <input type="text" placeholder="Select Date" style="width: 200px;">
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-white btn-sm dropdown-toggle p-2 px-3 rounded" data-bs-toggle="dropdown">Designation</button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Accountant</a></li>
                            <li><a class="dropdown-item" href="#">Developer</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 20px;"><input type="checkbox" class="form-check-input"></th>
                            <th>Emp ID</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="checkbox" class="form-check-input"></td>
                            <td><a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="offcanvas" data-bs-target="#details_offcanvas">Emp-001</a></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://i.pravatar.cc/150?img=11" class="avatar-md" alt="Img">
                                    <span class="fw-bold text-dark">Anthony Lewis</span>
                                </div>
                            </td>
                            <td>Accountant</td>
                            <td>14 Jun 2025</td>
                            <td>12 Sep 2025</td>
                            <td>90</td>
                            <td><span class="badge badge-active">Active</span></td>
                            <td class="text-end">
                                <button class="action-btn" data-bs-toggle="offcanvas" data-bs-target="#details_offcanvas"><i class="ti ti-eye"></i></button>
                                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#edit_modal"><i class="ti ti-edit"></i></button>
                                <button class="action-btn text-danger" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" class="form-check-input"></td>
                            <td><a href="#" class="text-primary fw-bold text-decoration-none">Emp-003</a></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://i.pravatar.cc/150?img=13" class="avatar-md" alt="Img">
                                    <span class="fw-bold text-dark">Harvey Smith</span>
                                </div>
                            </td>
                            <td>Technician</td>
                            <td>10 May 2025</td>
                            <td>08 Aug 2025</td>
                            <td>90</td>
                            <td><span class="badge badge-closing">Closing Soon</span></td>
                            <td class="text-end">
                                <button class="action-btn"><i class="ti ti-eye"></i></button>
                                <button class="action-btn"><i class="ti ti-edit"></i></button>
                                <button class="action-btn text-danger"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="completed-card" class="content-card" style="display:none;">
            <div class="card-header"><h5 class="card-title">Completed Records</h5></div>
            <div style="padding: 40px; text-align: center; color: #999;">No completed records found.</div>
        </div>

    </div> 

    <div class="modal fade" id="add_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Employee</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="upload-box">
                            <div class="upload-icon"><i class="ti ti-photo"></i></div>
                            <div>
                                <h6 class="mb-1" style="font-size:14px; margin:0;">Upload Profile Image</h6>
                                <p class="text-muted" style="font-size:12px; margin:0 0 5px 0;">Image should be below 4 mb</p>
                                <button class="btn btn-primary btn-sm" style="font-size:11px; background:var(--primary-orange); border:none;">Upload</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Employee Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Designation <span class="text-danger">*</span></label>
                            <select class="form-select"><option>Select</option><option>Accountant</option></select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Joining Date <span class="text-danger">*</span></label>
                                <div class="input-icon"><input type="date" class="form-control"><i class="ti ti-calendar"></i></div>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Notice End Date <span class="text-danger">*</span></label>
                                <div class="input-icon"><input type="date" class="form-control"><i class="ti ti-calendar"></i></div>
                            </div>
                        </div>
                        <div class="text-end pt-2 border-top">
                            <button type="button" class="btn btn-white me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-save">Add Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Employee</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3"><label class="form-label">Employee Name</label><input type="text" class="form-control" value="Anthony Lewis"></div>
                        <div class="mb-3"><label class="form-label">Designation</label><select class="form-select"><option selected>Accountant</option></select></div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Joining Date</label><input type="date" class="form-control" value="2025-06-14"></div>
                            <div class="col-6 mb-3"><label class="form-label">End Date</label><input type="date" class="form-control" value="2025-09-12"></div>
                        </div>
                        <div class="text-end pt-2 border-top">
                            <button type="button" class="btn btn-white me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body p-4">
                    <div style="width:60px; height:60px; background:#fff1f0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px auto;">
                        <i class="ti ti-trash-x" style="font-size:30px; color:#ff4d4f;"></i>
                    </div>
                    <h4 class="mb-2">Confirm Delete</h4>
                    <p class="text-muted mb-4">Are you sure you want to delete this item? This cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-white" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger" style="background:#dc3545; border:none;">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="details_offcanvas" style="width: 400px;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">Employee Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-4">
            <div class="card bg-light border-0 mb-4 shadow-none">
                <div class="card-body d-flex align-items-center p-3">
                    <img src="https://i.pravatar.cc/150?img=11" class="avatar-md me-3" style="width:50px; height:50px;" alt="Img">
                    <div>
                        <h5 class="mb-1 fw-bold">Anthony Lewis</h5>
                        <span class="badge bg-white border text-dark">Accountant</span>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted">Completed Days</span>
                    <span class="fw-bold text-dark">60 Days</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 66%; background-color: var(--primary-orange)!important;" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <div class="border-bottom pb-3 mb-3">
                <h6 class="fw-bold mb-3">Basic Information</h6>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted"><i class="ti ti-id me-2"></i>Employee ID</span><span class="fw-medium">EMP-001</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted"><i class="ti ti-users me-2"></i>Team</span><span class="fw-medium">Finance</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted"><i class="ti ti-calendar me-2"></i>Join Date</span><span class="fw-medium">14 Jan 2025</span></div>
            </div>
            <div>
                <h6 class="fw-bold mb-3">Notice Info</h6>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted"><i class="ti ti-calendar-event me-2"></i>Start Date</span><span class="fw-medium">14 Jun 2025</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted"><i class="ti ti-calendar-minus me-2"></i>End Date</span><span class="fw-medium">12 Sep 2025</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted"><i class="ti ti-clock me-2"></i>Duration</span><span class="fw-medium">90 Days</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted"><i class="ti ti-user-check me-2"></i>Status</span><span class="badge badge-active">Active</span></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabId, element) {
            document.querySelectorAll('.content-card').forEach(card => card.style.display = 'none');
            document.querySelectorAll('.top-nav-item').forEach(item => item.classList.remove('active'));
            document.getElementById(tabId + '-card').style.display = 'block';
            element.classList.add('active');
            
            // Dynamic Breadcrumb
            const texts = { 'active': 'Active Notice Periods', 'completed': 'Completed Notice Periods', 'all': 'All Records' };
            document.querySelector('.breadcrumb').innerText = `Dashboard / HRM / ${texts[tabId]}`;
        }
    </script>
</body>
</html>