<?php
// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | Termination</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* --- THEME VARIABLES (MATCHING PREVIOUS DESIGN) --- */
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

        /* --- SIDEBAR LOGIC --- */
        #mainContent { 
            margin-left: 95px; 
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        /* --- GENERAL UI COMPONENTS --- */
        a { text-decoration: none; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; background: transparent; padding: 0; }
        .breadcrumb-item a { color: var(--text-muted); }
        .breadcrumb-item.active { color: var(--primary-orange); }

        /* --- CARDS & TABLES --- */
        .card { border: 1px solid var(--border-light); border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.01); border:none; margin-bottom: 20px; background: var(--white); }
        .card-header { background: var(--white); padding: 20px 25px; border-bottom: 1px solid var(--border-light); border-radius: 8px 8px 0 0; }
        .card-body { padding: 0; }
        
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; margin-bottom: 0; vertical-align: middle; }
        .table thead th { background-color: #f9fafb; font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; padding: 15px 25px; border-bottom: 1px solid var(--border-light); border-top: none; }
        .table tbody td { padding: 15px 25px; border-bottom: 1px solid var(--border-light); font-size: 14px; color: var(--text-dark); vertical-align: middle; }
        
        /* --- BUTTONS & INPUTS --- */
        .btn-primary { background-color: var(--primary-orange); border-color: var(--primary-orange); }
        .btn-primary:hover { background-color: #e54e2d; border-color: #e54e2d; }
        
        .btn-white { background: var(--white); border: 1px solid var(--border-light); color: var(--text-dark); }
        .btn-white:hover { background: #f8f9fa; }

        .form-control:focus, .form-select:focus { border-color: var(--primary-orange); box-shadow: 0 0 0 0.25rem rgba(255, 91, 55, 0.25); }

        .avatar-md { width: 38px; height: 38px; object-fit: cover; }
        
        /* --- UTILS --- */
        .action-icon a { color: var(--text-muted); font-size: 18px; transition: 0.3s; }
        .action-icon a:hover { color: var(--primary-orange); }
        
        .footer { margin-top: auto; font-size: 13px; color: var(--text-muted); }
        
        /* Custom Input Icon Wrapper */
        .input-icon { position: relative; }
        .input-icon-addon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 5; }
        .input-icon input { padding-left: 35px; }

        .input-icon-end { position: relative; }
        .input-icon-end .input-icon-addon { left: auto; right: 10px; }
        .input-icon-end input { padding-right: 35px; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-4">
            <div class="my-auto mb-2">
                <h2 class="page-title mb-1">Termination</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="index.php"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">HRM</li>
                        <li class="breadcrumb-item active" aria-current="page">Termination</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <div class="mb-2">
                    <a href="#" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#new_termination">
                        <i class="ti ti-circle-plus me-2"></i>Add Termination
                    </a>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="btn btn-white btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                        <h5 class="card-title mb-0">Termination List</h5>
                        <div class="d-flex align-items-center flex-wrap row-gap-3">
                            <div class="input-icon position-relative me-2">
                                <span class="input-icon-addon">
                                    <i class="ti ti-calendar"></i>
                                </span>
                                <input type="text" class="form-control" placeholder="dd/mm/yyyy - dd/mm/yyyy" style="width: 220px;">
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center fs-12" data-bs-toggle="dropdown">
                                    <span class="fs-12 d-inline-flex me-1 text-muted">Sort By : </span>
                                    Last 7 Days
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end p-3">
                                    <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Recently Added</a></li>
                                    <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Ascending</a></li>
                                    <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Descending</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="no-sort" style="width: 40px;">
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                            </div>
                                        </th>
                                        <th>Resigning Employee</th>
                                        <th>Department</th>
                                        <th>Termination Type</th>
                                        <th>Notice Date</th>
                                        <th>Reason</th>
                                        <th>Resignation Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="avatar avatar-md me-2">
                                                    <img src="https://i.pravatar.cc/150?img=32" class="rounded-circle avatar-md" alt="user">
                                                </a>
                                                <h6 class="fw-medium mb-0"><a href="#" class="text-dark">Anthony Lewis</a></h6>
                                            </div>
                                        </td>
                                        <td>Finance</td>
                                        <td>Retirement</td>
                                        <td>14 Jan 2024</td>
                                        <td>Employee retires</td>
                                        <td>14 Mar 2024</td>
                                        <td>
                                            <div class="action-icon d-inline-flex">
                                                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#edit_termination"><i class="ti ti-edit"></i></a>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="avatar avatar-md me-2">
                                                    <img src="https://i.pravatar.cc/150?img=9" class="rounded-circle avatar-md" alt="user">
                                                </a>
                                                <h6 class="fw-medium mb-0"><a href="#" class="text-dark">Brian Villalobos</a></h6>
                                            </div>
                                        </td>
                                        <td>Application Development</td>
                                        <td>Insubordination</td>
                                        <td>21 Jan 2024</td>
                                        <td>Entrepreneurial Pursuits</td>
                                        <td>21 Mar 2024</td>
                                        <td>
                                            <div class="action-icon d-inline-flex">
                                                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#edit_termination"><i class="ti ti-edit"></i></a>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="avatar avatar-md me-2">
                                                    <img src="https://i.pravatar.cc/150?img=1" class="rounded-circle avatar-md" alt="user">
                                                </a>
                                                <h6 class="fw-medium mb-0"><a href="#" class="text-dark">Harvey Smith</a></h6>
                                            </div>
                                        </td>
                                        <td>Web Development</td>
                                        <td>Layoff</td>
                                        <td>18 Feb 2024</td>
                                        <td>Workforce reduction</td>
                                        <td>18 Apr 2024</td>
                                        <td>
                                            <div class="action-icon d-inline-flex">
                                                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#edit_termination"><i class="ti ti-edit"></i></a>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="avatar avatar-md me-2">
                                                    <img src="https://i.pravatar.cc/150?img=33" class="rounded-circle avatar-md" alt="user">
                                                </a>
                                                <h6 class="fw-medium mb-0"><a href="#" class="text-dark">Stephan Peralt</a></h6>
                                            </div>
                                        </td>
                                        <td>UI / UX</td>
                                        <td>Breach of Contract</td>
                                        <td>14 Mar 2024</td>
                                        <td>Violates the terms of the contract</td>
                                        <td>14 Apr 2024</td>
                                        <td>
                                            <div class="action-icon d-inline-flex">
                                                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#edit_termination"><i class="ti ti-edit"></i></a>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="avatar avatar-md me-2">
                                                    <img src="https://i.pravatar.cc/150?img=34" class="rounded-circle avatar-md" alt="user">
                                                </a>
                                                <h6 class="fw-medium mb-0"><a href="#" class="text-dark">Doglas Martini</a></h6>
                                            </div>
                                        </td>
                                        <td>Marketing</td>
                                        <td>Lack of Skills</td>
                                        <td>10 Apr 2024</td>
                                        <td>Unable to perform job duties</td>
                                        <td>10 Jun 2024</td>
                                        <td>
                                            <div class="action-icon d-inline-flex">
                                                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#edit_termination"><i class="ti ti-edit"></i></a>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer d-sm-flex align-items-center justify-content-between bg-white border-top p-3 rounded mt-3">
            <p class="mb-0">2014 - 2026 &copy; Workack HRMS.</p>
            <p class="mb-0">Designed &amp; Developed By <a href="#" class="text-primary">Dreams</a></p>
        </div>
        </div>
    <div class="modal fade" id="new_termination">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h4 class="modal-title">Add Termination</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Terminated Employee</label>
                                    <select class="form-select">
                                        <option>Select</option>
                                        <option>Anthony Lewis</option>
                                        <option>Brian Villalobos</option>
                                        <option>Doglas Martini</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Termination Type</label>
                                    <select class="form-select">
                                        <option>Select</option>
                                        <option>Retirement</option>
                                        <option>Insubordination</option>
                                        <option>Lack of Skills</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Notice Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="date" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Reason</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Resignation Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="date" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Termination</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit_termination">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h4 class="modal-title">Edit Termination</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Terminated Employee</label>
                                    <input type="text" class="form-control" value="Anthony Lewis" readonly>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Termination Type</label>
                                    <select class="form-select">
                                        <option>Select</option>
                                        <option selected>Retirement</option>
                                        <option>Insubordination</option>
                                        <option>Lack of Skills</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Notice Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="date" class="form-control" value="2024-01-14">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Reason</label>
                                    <textarea class="form-control" rows="3">Employee retires</textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Resignation Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="date" class="form-control" value="2024-03-14">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background: #fff1f0; border-radius: 50%;">
                        <i class="ti ti-trash-x fs-36" style="font-size: 30px;"></i>
                    </span>
                    <h4 class="mb-2">Confirm Delete</h4>
                    <p class="mb-4 text-muted">You want to delete all the marked items, this cant be undone once you delete.</p>
                    <div class="d-flex justify-content-center">
                        <button type="button" class="btn btn-white border me-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" style="background-color: #dc3545; border-color: #dc3545;">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html>